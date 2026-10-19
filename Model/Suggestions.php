<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Redirects\Model;

use Magento\Framework\App\ResourceConnection;

/**
 * Where a missing address probably went: the store view's product,
 * category or CMS page URL most like it (the URLs with its last segment's
 * longest word, letters before numbers, then the closest whole path). "old-blue-hoodie.html" finds
 * "blue-hoodie.html". Null when nothing is close.
 */
class Suggestions
{
    private const CANDIDATES = 25;
    private const MIN_SIMILARITY = 55;

    public function __construct(
        private readonly ResourceConnection $resource
    ) {
    }

    public function forPath(int $storeId, string $path): ?string
    {
        $last = (string) preg_replace('/\.[a-z0-9]{2,5}$/i', '', basename($path));
        $words = array_filter(preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($last)) ?: [], static fn ($w) => mb_strlen($w) >= 3);
        if (!$words) {
            return null;
        }
        // Longest first, words with letters before numbers (an id or a date rarely names the page).
        usort($words, static fn ($a, $b) => [ctype_digit($a), -mb_strlen($a)] <=> [ctype_digit($b), -mb_strlen($b)]);
        $connection = $this->resource->getConnection();
        $candidates = [];
        foreach (array_slice($words, 0, 3) as $word) {
            $candidates = $connection->fetchCol($connection->select()
                ->from($this->resource->getTableName('url_rewrite'), ['request_path'])
                ->where('store_id = ?', $storeId)
                ->where('redirect_type = 0')
                ->where('entity_type IN (?)', ['product', 'category', 'cms-page'])
                ->where('request_path LIKE ?', '%' . addcslashes($word, '%_\\') . '%')
                ->where('request_path <> ?', $path)
                ->limit(self::CANDIDATES));
            if ($candidates) {
                break;
            }
        }
        $best = null;
        $bestScore = 0.0;
        foreach ($candidates as $candidate) {
            similar_text(mb_strtolower($path), mb_strtolower((string) $candidate), $percent);
            if ($percent > $bestScore) {
                $bestScore = $percent;
                $best = (string) $candidate;
            }
        }

        return $bestScore >= self::MIN_SIMILARITY ? $best : null;
    }
}
