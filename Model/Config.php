<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Redirects\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Stores > Configuration > Zero > 404 Log and Redirects.
 */
class Config
{
    public const ENABLED = 'zero_redirects/log/enabled';
    private const PATH = 'zero_redirects/log/';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    public function logs(): bool
    {
        return $this->scopeConfig->isSetFlag(self::ENABLED, ScopeInterface::SCOPE_STORE);
    }

    /** The store-wide setting (default scope), as the 404 Log page's switch shows it. */
    public function logsByDefault(): bool
    {
        return $this->scopeConfig->isSetFlag(self::ENABLED);
    }

    /**
     * @return string[] lower-case texts an address must not contain to be logged
     */
    public function ignored(): array
    {
        $lines = preg_split('/\R/', (string) $this->scopeConfig->getValue(self::PATH . 'ignore', ScopeInterface::SCOPE_STORE)) ?: [];

        return array_values(array_filter(array_map(static fn ($line) => mb_strtolower(trim($line)), $lines)));
    }

    public function keepDays(): int
    {
        return max(1, (int) $this->scopeConfig->getValue(self::PATH . 'keep_days'));
    }
}
