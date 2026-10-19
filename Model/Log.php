<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Redirects\Model;

use Magento\Framework\App\ResourceConnection;

/**
 * The 404 log table (zero_redirects_404): one row per store view and path.
 */
class Log
{
    public const TABLE = 'zero_redirects_404';
    public const OPEN = 'open';
    public const REDIRECTED = 'redirected';
    public const IGNORED = 'ignored';
    public const PAGE_SIZE = 50;

    public function __construct(
        private readonly ResourceConnection $resource
    ) {
    }

    /**
     * Hits counted in: new rows, or existing ones counted up (status kept).
     *
     * @param array<int, array{store_id: int, path: string, referer: string|null, hits: int, first: int, last: int}> $rows
     */
    public function add(array $rows): void
    {
        $connection = $this->resource->getConnection();
        foreach (array_chunk($rows, 500) as $chunk) {
            $connection->insertOnDuplicate(
                $this->table(),
                array_map(static fn (array $r) => [
                    'store_id' => $r['store_id'],
                    'path' => $r['path'],
                    'referer' => $r['referer'],
                    'hits' => $r['hits'],
                    'first_seen_at' => gmdate('Y-m-d H:i:s', $r['first']),
                    'last_seen_at' => gmdate('Y-m-d H:i:s', $r['last']),
                ], $chunk),
                [
                    'hits' => new \Zend_Db_Expr('hits + VALUES(hits)'),
                    'last_seen_at' => new \Zend_Db_Expr('GREATEST(last_seen_at, VALUES(last_seen_at))'),
                    'referer' => new \Zend_Db_Expr('IFNULL(VALUES(referer), referer)'),
                ]
            );
        }
    }

    /**
     * Open rows beyond $max: the least asked for and longest unseen go.
     */
    public function cap(int $max): int
    {
        $connection = $this->resource->getConnection();
        $open = (int) $connection->fetchOne($connection->select()->from($this->table(), ['n' => new \Zend_Db_Expr('COUNT(*)')])->where('status = ?', self::OPEN));
        if ($open <= $max) {
            return 0;
        }
        $ids = $connection->fetchCol($connection->select()->from($this->table(), ['log_id'])
            ->where('status = ?', self::OPEN)->order(['hits ASC', 'last_seen_at ASC'])->limit($open - $max));

        return $ids ? $connection->delete($this->table(), ['log_id IN (?)' => $ids]) : 0;
    }

    /**
     * Open rows whose likely target isn't worked out yet, most asked for first.
     *
     * @return array<int, array{log_id: string, store_id: string, path: string}>
     */
    public function withoutSuggestion(int $limit): array
    {
        $connection = $this->resource->getConnection();

        return $connection->fetchAll($connection->select()->from($this->table(), ['log_id', 'store_id', 'path'])
            ->where('status = ?', self::OPEN)->where('suggested = 0')->order('hits DESC')->limit($limit));
    }

    public function setSuggestion(int $logId, ?string $path): void
    {
        $this->resource->getConnection()->update($this->table(), ['suggested_path' => $path, 'suggested' => 1], ['log_id = ?' => $logId]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get(int $logId): ?array
    {
        $connection = $this->resource->getConnection();

        return $connection->fetchRow($connection->select()->from($this->table())->where('log_id = ?', $logId)) ?: null;
    }

    /**
     * A page of rows, most hits first.
     *
     * @return array{rows: array<int, array<string, mixed>>, total: int}
     */
    public function page(string $status, string $search, ?int $storeId, int $page): array
    {
        $connection = $this->resource->getConnection();
        $select = $connection->select()->from($this->table());
        if ($status !== '') {
            $select->where('status = ?', $status);
        }
        if ($search !== '') {
            $select->where('path LIKE ?', '%' . addcslashes($search, '%_\\') . '%');
        }
        if ($storeId !== null) {
            $select->where('store_id = ?', $storeId);
        }
        $count = clone $select;
        $count->reset(\Magento\Framework\DB\Select::COLUMNS)->columns(['n' => new \Zend_Db_Expr('COUNT(*)')]);
        $select->order(['hits DESC', 'last_seen_at DESC'])->limitPage(max(1, $page), self::PAGE_SIZE);

        return ['rows' => $connection->fetchAll($select), 'total' => (int) $connection->fetchOne($count)];
    }

    /**
     * @return array<string, int> status => rows
     */
    public function counts(): array
    {
        $connection = $this->resource->getConnection();

        return array_map('intval', $connection->fetchPairs(
            $connection->select()->from($this->table(), ['status', 'n' => new \Zend_Db_Expr('COUNT(*)')])->group('status')
        ));
    }

    public function setStatus(int $logId, string $status, ?int $urlRewriteId = null): void
    {
        $this->resource->getConnection()->update(
            $this->table(),
            ['status' => $status, 'url_rewrite_id' => $urlRewriteId],
            ['log_id = ?' => $logId]
        );
    }

    /**
     * Open rows nobody asked for in $days days: removed.
     */
    public function forget(int $days): int
    {
        return $this->resource->getConnection()->delete($this->table(), [
            'status = ?' => self::OPEN,
            'last_seen_at < ?' => gmdate('Y-m-d H:i:s', time() - $days * 86400),
        ]);
    }

    private function table(): string
    {
        return $this->resource->getTableName(self::TABLE);
    }
}
