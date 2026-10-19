<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Redirects\Observer;

use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use Zero\Redirects\Model\Config;
use Zero\Redirects\Model\Buffer;

/**
 * Every storefront 404 (Magento's no-route page, cms_noroute_index) counts
 * one hit for its path: a line appended to the buffer file (Model\Buffer),
 * no database on the request; cron folds them into the log (Model\Importer).
 * Not logged: static files, media, other files that aren't pages (images,
 * scripts, fonts), the configured scanner patterns.
 * A 404 the page cache answers isn't rendered and isn't counted: the log
 * tells which addresses are missing, the hits are a lower bound.
 */
class LogNotFound implements ObserverInterface
{
    private const NOT_PAGES = '/\.(css|js|map|json|png|jpe?g|gif|webp|avif|svg|ico|woff2?|ttf|eot|otf|mp4|webm|txt|xml|gz|zip)$/i';

    public function __construct(
        private readonly HttpRequest $request,
        private readonly StoreManagerInterface $storeManager,
        private readonly Config $config,
        private readonly Buffer $buffer,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(Observer $observer): void
    {
        if (!$this->config->logs() || !$this->request->isGet()) {
            return;
        }
        $path = $this->path();
        if ($path === null) {
            return;
        }
        $referer = (string) $this->request->getServer('HTTP_REFERER');
        try {
            $this->buffer->append(
                (int) $this->storeManager->getStore()->getId(),
                $path,
                $referer !== '' ? mb_substr($referer, 0, 512) : null
            );
        } catch (\Exception $e) {
            // A full disk never breaks the 404 page.
            $this->logger->warning('Zero_Redirects: 404 not logged: ' . $e->getMessage());
        }
    }

    /**
     * The requested path, relative to the store view, as a URL rewrite's
     * request path; null when it isn't logged.
     */
    private function path(): ?string
    {
        $path = trim(rawurldecode((string) $this->request->getOriginalPathInfo()), '/');
        if ($path === '' || mb_strlen($path) > 255 || preg_match('#^(static|media|pub|errors)/#', $path) || preg_match(self::NOT_PAGES, $path)) {
            return null;
        }
        $lower = mb_strtolower($path);
        foreach ($this->config->ignored() as $text) {
            if (str_contains($lower, $text)) {
                return null;
            }
        }

        return $path;
    }
}
