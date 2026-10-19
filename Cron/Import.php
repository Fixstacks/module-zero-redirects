<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Redirects\Cron;

use Zero\Redirects\Model\Importer;

/**
 * Every 5 minutes: the buffered 404s into the log (Model\Importer).
 */
class Import
{
    public function __construct(
        private readonly Importer $importer
    ) {
    }

    public function execute(): void
    {
        $this->importer->import();
    }
}
