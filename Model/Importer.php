<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Redirects\Model;

/**
 * From the buffer file into the log table (cron every 5 minutes, and when
 * the 404 Log page opens): the buffered 404s folded into one row per store
 * view and path, written in batches; the log kept to MAX_OPEN open rows; the
 * likely target worked out for up to SUGGEST rows not done yet (each one is
 * up to three LIKE scans of url_rewrite, so a few at a time), so the admin
 * page never searches the catalog itself.
 */
class Importer
{
    public const MAX_OPEN = 20000;
    private const SUGGEST = 50;

    public function __construct(
        private readonly Buffer $buffer,
        private readonly Log $log,
        private readonly Suggestions $suggestions
    ) {
    }

    public function import(int $suggest = self::SUGGEST): void
    {
        $rows = [];
        foreach ($this->buffer->take() as [$storeId, $path, $referer, $time]) {
            $key = $storeId . "\0" . $path;
            if (!isset($rows[$key])) {
                $rows[$key] = ['store_id' => $storeId, 'path' => $path, 'referer' => $referer, 'hits' => 0, 'first' => $time, 'last' => $time];
            }
            $rows[$key]['hits']++;
            $rows[$key]['first'] = min($rows[$key]['first'], $time);
            $rows[$key]['last'] = max($rows[$key]['last'], $time);
            if ($referer !== null) {
                $rows[$key]['referer'] = $referer;
            }
        }
        if ($rows) {
            $this->log->add(array_values($rows));
            $this->log->cap(self::MAX_OPEN);
        }
        foreach ($this->log->withoutSuggestion($suggest) as $row) {
            $this->log->setSuggestion((int) $row['log_id'], $this->suggestions->forPath((int) $row['store_id'], (string) $row['path']));
        }
    }
}
