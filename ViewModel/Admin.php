<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Redirects\ViewModel;

use Magento\Backend\Model\UrlInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\StoreManagerInterface;
use Zero\Redirects\Model\Config;
use Zero\Redirects\Model\Log;

/**
 * Marketing > SEO & Search > 404 Log: the filters, a page of rows with a
 * suggested target for the open ones, the links.
 */
class Admin implements ArgumentInterface
{
    /** @var array{rows: array<int, array<string, mixed>>, total: int}|null */
    private ?array $page = null;

    public function __construct(
        private readonly Log $log,
        private readonly RequestInterface $request,
        private readonly StoreManagerInterface $storeManager,
        private readonly UrlInterface $url,
        private readonly Config $config
    ) {
    }

    /** 404s are being logged (the store-wide setting). */
    public function isLogging(): bool
    {
        return $this->config->logsByDefault();
    }

    public function status(): string
    {
        $status = (string) $this->request->getParam('status', Log::OPEN);

        return in_array($status, ['', Log::OPEN, Log::REDIRECTED, Log::IGNORED], true) ? $status : Log::OPEN;
    }

    public function search(): string
    {
        return mb_substr(trim((string) $this->request->getParam('q')), 0, 120);
    }

    public function currentPage(): int
    {
        return max(1, (int) $this->request->getParam('p', 1));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function rows(): array
    {
        return $this->load()['rows'];
    }

    public function lastPage(): int
    {
        return max(1, (int) ceil($this->load()['total'] / Log::PAGE_SIZE));
    }

    /**
     * @return array<string, string> status => label with its count
     */
    public function statuses(): array
    {
        $counts = $this->log->counts();

        return [
            Log::OPEN => (string) __('Open (%1)', $counts[Log::OPEN] ?? 0),
            Log::REDIRECTED => (string) __('Redirected (%1)', $counts[Log::REDIRECTED] ?? 0),
            Log::IGNORED => (string) __('Ignored (%1)', $counts[Log::IGNORED] ?? 0),
            '' => (string) __('All (%1)', array_sum($counts)),
        ];
    }

    /** The likely target cron worked out for an open row ('' when none was close). */
    public function suggestion(array $row): string
    {
        return $row['status'] === Log::OPEN ? (string) $row['suggested_path'] : '';
    }

    public function storeName(array $row): string
    {
        try {
            return (string) $this->storeManager->getStore((int) $row['store_id'])->getName();
        } catch (\Exception $e) {
            return (string) $row['store_id'];
        }
    }

    /** The address as a shopper opens it. */
    public function storefrontUrl(array $row): string
    {
        try {
            return $this->storeManager->getStore((int) $row['store_id'])->getBaseUrl() . $row['path'];
        } catch (\Exception $e) {
            return (string) $row['path'];
        }
    }

    public function rewriteUrl(array $row): string
    {
        return $row['url_rewrite_id'] ? $this->url->getUrl('adminhtml/url_rewrite/edit', ['id' => (int) $row['url_rewrite_id']]) : '';
    }

    public function url(string $route, array $params = []): string
    {
        return $this->url->getUrl($route, $params);
    }

    /**
     * @return array{rows: array<int, array<string, mixed>>, total: int}
     */
    private function load(): array
    {
        return $this->page ??= $this->log->page($this->status(), $this->search(), null, $this->currentPage());
    }
}
