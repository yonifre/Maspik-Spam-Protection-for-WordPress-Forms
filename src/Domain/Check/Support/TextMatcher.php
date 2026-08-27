<?php

declare(strict_types=1);

namespace Maspik\Domain\Check\Support;

/**
 * String-matching primitives ported verbatim from v2.9.x
 * (maspik_is_field_value_exist_in_string, efas wildcard handling).
 * Behavioral parity is the contract here — do not "improve" matching rules.
 */
final class TextMatcher
{
    /**
     * Word-boundary match: term must appear surrounded by whitespace/string
     * edges, optionally followed by . , ! or ? — exactly the v2 regex.
     */
    public static function containsWord(string $needle, string $haystack): bool
    {
        if ($needle === '' || $haystack === '') {
            return false;
        }

        $needle = strtolower(trim($needle));
        $haystack = strtolower(trim($haystack));

        return (bool) preg_match(
            '/(?:^|\s)' . preg_quote($needle, '/') . '[.,!?]?(?:$|\s)/i',
            $haystack
        );
    }

    /** Plain case-insensitive substring match (v2 $make_space = 0 path). */
    public static function containsSubstring(string $needle, string $haystack): bool
    {
        if ($needle === '' || $haystack === '') {
            return false;
        }

        return strpos(strtolower(trim($haystack)), strtolower(trim($needle))) !== false;
    }

    /** fnmatch with case folding — v2's wildcard engine for * and ? rules. */
    public static function matchesWildcard(string $pattern, string $value): bool
    {
        return self::fnmatchLong($pattern, $value);
    }

    /**
     * Textarea wildcard semantics from v2: force *pattern* wrapping so the rule
     * matches anywhere inside the message.
     */
    public static function matchesWildcardChunked(string $pattern, string $value): bool
    {
        return self::fnmatchLong('*' . trim($pattern, '*') . '*', $value);
    }

    /**
     * fnmatch() refuses any subject of 1024 bytes or more: it returns false and
     * emits a warning instead of matching. v2 chunked at 4000 to work around
     * that, but every 4000-byte chunk is itself over the ceiling, so wildcard
     * rules silently never matched a message longer than 1024 bytes — exactly
     * where spam text lives, and only ~512 characters of Hebrew.
     *
     * Slide a window that stays under the ceiling, overlapping each step by the
     * pattern length so a match straddling a window boundary is still caught.
     */
    private static function fnmatchLong(string $pattern, string $value): bool
    {
        $limit = 1000; // safely below fnmatch's 1024-byte ceiling

        if (strlen($value) < $limit) {
            return fnmatch($pattern, $value, FNM_CASEFOLD);
        }

        // Cap the overlap so a pathologically long rule cannot degrade the
        // scan to a one-byte step over a large message.
        $step = $limit - min(strlen($pattern), 256);

        for ($offset = 0; $offset < strlen($value); $offset += $step) {
            if (fnmatch($pattern, substr($value, $offset, $limit), FNM_CASEFOLD)) {
                return true;
            }
        }

        return false;
    }

    /** Is this blacklist entry a /regex/ rule? (v2: leading slash.) */
    public static function isRegexRule(string $rule): bool
    {
        return strpos($rule, '/') === 0;
    }

    /** Validate a regex without emitting warnings (v2 behavior: skip invalid). */
    public static function isValidRegex(string $pattern): bool
    {
        set_error_handler(static function (): bool {
            return true;
        }, E_WARNING);
        $valid = @preg_match($pattern, '') !== false;
        restore_error_handler();

        return $valid;
    }

    /** Emoji detection — same Unicode ranges as v2 maspik_is_contains_emoji(). */
    public static function containsEmoji(string $value): bool
    {
        $pattern = '/[\x{1F600}-\x{1F64F}\x{1F300}-\x{1F5FF}\x{1F680}-\x{1F6FF}'
            . '\x{1F700}-\x{1F77F}\x{1F780}-\x{1F7FF}\x{1F800}-\x{1F8FF}'
            . '\x{1F900}-\x{1F9FF}\x{1FA70}-\x{1FAFF}\x{2600}-\x{26FF}'
            . '\x{2700}-\x{27BF}]/u';

        return preg_match($pattern, $value) === 1;
    }
}
