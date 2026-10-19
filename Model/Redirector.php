<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Redirects\Model;

use Magento\Cms\Model\PageFactory;
use Magento\Cms\Model\ResourceModel\Page as PageResource;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\UrlRewrite\Model\ResourceModel\UrlRewrite as UrlRewriteResource;
use Magento\UrlRewrite\Model\UrlRewriteFactory;

/**
 * A logged 404 redirected: a Magento URL rewrite (custom, 301) from its path
 * to the target, so Marketing > URL Rewrites shows and edits it like any
 * other. The cached 404 pages are cleaned so the redirect answers at once.
 */
class Redirector
{
    public function __construct(
        private readonly Log $log,
        private readonly UrlRewriteFactory $urlRewriteFactory,
        private readonly UrlRewriteResource $urlRewriteResource,
        private readonly StoreManagerInterface $storeManager,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly PageFactory $pageFactory,
        private readonly PageResource $pageResource,
        private readonly ManagerInterface $eventManager
    ) {
    }

    /**
     * @throws LocalizedException
     */
    public function redirect(int $logId, string $target): int
    {
        $row = $this->log->get($logId);
        if (!$row) {
            throw new LocalizedException(__('That 404 is no longer in the log.'));
        }
        $storeId = (int) $row['store_id'];
        $target = $this->target($target, $storeId);
        if ($target === trim((string) $row['path'], '/')) {
            throw new LocalizedException(__('The redirect would point to itself.'));
        }
        $rewrite = $this->urlRewriteFactory->create();
        $rewrite->setEntityType('custom')
            ->setEntityId(0)
            ->setRequestPath((string) $row['path'])
            ->setTargetPath($target)
            ->setRedirectType(301)
            ->setStoreId($storeId)
            ->setDescription((string) __('From the 404 log'));
        try {
            $this->urlRewriteResource->save($rewrite);
        } catch (\Magento\Framework\Exception\AlreadyExistsException $e) {
            throw new LocalizedException(__('A URL rewrite for "%1" already exists: edit it in Marketing > URL Rewrites.', $row['path']));
        }
        $this->log->setStatus($logId, Log::REDIRECTED, (int) $rewrite->getId());
        $this->cleanNotFoundPage($storeId);

        return (int) $rewrite->getId();
    }

    /**
     * The target as a URL rewrite takes it: a path relative to the store view
     * (this store's own absolute URLs become one), or another site's URL.
     *
     * @throws LocalizedException
     */
    private function target(string $target, int $storeId): string
    {
        $target = trim($target);
        if ($target === '') {
            throw new LocalizedException(__('Enter where the address should go.'));
        }
        $base = (string) $this->storeManager->getStore($storeId)->getBaseUrl();
        if (str_starts_with($target, $base)) {
            $target = substr($target, strlen($base));
        } elseif (preg_match('#^https?://#i', $target)) {
            if (!filter_var($target, FILTER_VALIDATE_URL)) {
                throw new LocalizedException(__('"%1" is not a valid URL.', $target));
            }

            return $target;
        } elseif (preg_match('#^[a-z][a-z0-9+.-]*:#i', $target)) {
            throw new LocalizedException(__('A redirect goes to a page: a path like /new-page.html or an http(s) URL.'));
        }

        return ltrim($target, '/');
    }

    /**
     * The 404 page (Content > Pages, the store's "CMS No Route Page") cached
     * for this address would answer before the new rewrite: its cache goes.
     */
    private function cleanNotFoundPage(int $storeId): void
    {
        $identifier = (string) $this->scopeConfig->getValue('web/default/cms_no_route', ScopeInterface::SCOPE_STORE, $storeId);
        if ($identifier === '') {
            return;
        }
        $page = $this->pageFactory->create();
        $page->setStoreId($storeId);
        $this->pageResource->load($page, $identifier, 'identifier');
        if ($page->getId()) {
            $this->eventManager->dispatch('clean_cache_by_tags', ['object' => $page]);
        }
    }
}
