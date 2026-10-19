<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Redirects\Cron;

use Zero\Redirects\Model\Config;
use Zero\Redirects\Model\Log;

/**
 * Daily: open 404s nobody asked for in the configured days leave the log.
 */
class Forget
{
    public function __construct(
        private readonly Log $log,
        private readonly Config $config
    ) {
    }

    public function execute(): void
    {
        $this->log->forget($this->config->keepDays());
    }
}
