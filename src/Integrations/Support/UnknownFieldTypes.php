<?php

declare(strict_types=1);

namespace Maspik\Integrations\Support;

if (! defined('ABSPATH')) {
    exit;
}


/**
 * The type strings an adapter did not recognise, for this request.
 *
 * Guessing is a floor, not an answer. The answer is to put the real string in
 * the adapter's map — and the only reliable way to learn it is to watch what
 * real installs actually send, because a plugin's own field names are not
 * discoverable from here and the name shown in its form builder is often not
 * the name in its code. Everest Forms calls its phone field `phone` where every
 * other plugin says `tel`, and that cost a whole layer for as long as nobody
 * looked.
 *
 * So this records the misses. It changes nothing about a submission; it is the
 * feedback loop that lets each map converge on being correct instead of staying
 * permanently a little behind.
 *
 * Names only — a type string is the plugin's own vocabulary, never anything a
 * visitor wrote.
 *
 * Request-scoped and static, like LayerStatus: ephemeral diagnostic metadata,
 * not application state.
 */
final class UnknownFieldTypes
{
    /** Bounded so a crafted payload cannot grow this without limit. */
    private const MAX = 25;

    /** @var array<string, array<int, string>> integration id => type strings */
    private static $seen = [];

    public static function reset(): void
    {
        self::$seen = [];
    }

    public static function record(string $source, string $type): void
    {
        if ($type === '' || $source === '') {
            return;
        }

        if (! isset(self::$seen[$source])) {
            self::$seen[$source] = [];
        }

        if (count(self::$seen[$source]) >= self::MAX
            || in_array($type, self::$seen[$source], true)
        ) {
            return;
        }

        self::$seen[$source][] = $type;
    }

    /** @return array<string, array<int, string>> */
    public static function all(): array
    {
        return self::$seen;
    }
}
