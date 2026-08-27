<?php

declare(strict_types=1);

namespace Maspik\Domain\Check\Support;

/**
 * Counts links the way v2.9.x did: three patterns, summed (a value matching
 * more than one pattern counts multiple times — that is the shipped behavior).
 */
final class LinkCounter
{
    private const PATTERNS = [
        '/<a[^>]*href[^>]*>/i',                    // HTML anchors
        '/https?:\/\/[^\s<>"\']+/i',               // http(s) URLs
        '/www\.[a-z0-9][-a-z0-9.]+\.[a-z0-9-]+/i', // bare www.domain.tld
    ];

    public static function count(string $value): int
    {
        $total = 0;
        foreach (self::PATTERNS as $pattern) {
            $count = preg_match_all($pattern, $value);
            $total += $count !== false ? $count : 0;
        }

        return $total;
    }
}
