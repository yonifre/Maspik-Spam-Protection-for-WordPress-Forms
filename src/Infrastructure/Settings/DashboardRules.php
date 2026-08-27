<?php

declare(strict_types=1);

namespace Maspik\Infrastructure\Settings;

use Maspik\Domain\Check\LanguageCheck;

/**
 * Normalises the shared-rules payload (from wpmaspik.com, or an existing v2
 * `spamapi` option) into the shape the engine reads: local Schema keys, with
 * every multiline rule field expressed as an array.
 *
 * Pure and idempotent — safe to run on the live API response and on an
 * already-normalised option, which is what makes the v2→3.0 migration and the
 * cron refresh share one code path.
 */
final class DashboardRules
{
    /**
     * ACF field names on wpmaspik.com → local Schema keys (renamed in v3).
     *
     * The on/off switches matter as much as the rule lists and were being lost:
     * anything whose name is not a Schema key is dropped by normalize(), and the
     * Dashboard sends `maspikhoneypot` in lower case and calls the verification
     * key `advancekeycheck`. Neither matched, so a site that switched Honeypot
     * Trap on in the Dashboard silently got no honeypot — the protection was
     * requested centrally and never applied. The names come from v2's own
     * $MASPIK_SYNC_BOOL_API_TO_LOCAL table, which is what the Dashboard is
     * still written against.
     */
    private const KEY_MAP = [
        'text_field' => 'text_blacklist',
        'textarea_field' => 'text_blacklist',
        'email_field' => 'emails_blacklist',
        'url_field' => 'url_blacklist',
        'maspikhoneypot' => 'maspikHoneypot',
        'advancekeycheck' => 'verification_key',
        // v2 also accepted the un-renamed key straight from the Dashboard.
        'maspikTimeCheck' => 'verification_key',
        // The Dashboard's phone-format list is v2's `phone_format`, merged into
        // the same list the local `tel_formats` field feeds (v2: spam-block.php
        // merged efas_get_spam_api('phone_format') into $tel_formats).
        'phone_format' => 'tel_formats',
        // The Dashboard's "Block IP address" field. v2 shipped this field but
        // never consumed it (spam-block.php carried a literal "Todo: api to
        // $ip_blacklist"), so IPs entered centrally were silently ignored on
        // every site. Mapped here so the list is actually enforced.
        'ip' => 'ip_blacklist',
    ];

    /**
     * @param array<string, mixed> $raw
     * @return array<string, mixed>
     */
    public static function normalize(array $raw): array
    {
        $out = [];
        foreach ($raw as $field => $value) {
            $key = self::KEY_MAP[$field] ?? $field;

            // Per-check error messages are keyed by check id, so they cannot be
            // enumerated in the Schema — but v2 read them from the Dashboard
            // (cfas_get_error_text: efas_get_spam_api("custom_error_message_$field")),
            // and dropping them here is why a message set centrally never
            // reached the visitor. They are plain text, so they pass straight
            // through with no list handling.
            if (strpos($key, 'custom_error_message_') === 0) {
                if (is_scalar($value) && trim((string) $value) !== '') {
                    $out[$key] = (string) $value;
                }
                continue;
            }

            $definition = Schema::options()[$key] ?? null;
            if ($definition === null) {
                continue;
            }

            if ($definition['type'] === Schema::TYPE_MULTILINE) {
                $list = self::toList($key, $value);
                $out[$key] = isset($out[$key]) && is_array($out[$key])
                    ? array_values(array_unique(array_merge($out[$key], $list)))
                    : $list;
            } else {
                $out[$key] = $value;
            }
        }

        return $out;
    }

    /**
     * Split a rule field into a de-duplicated list. Already-array input is just
     * cleaned (idempotent re-runs); country codes are whitespace-separated (v2);
     * every other list is one rule per line so multi-word phrases stay intact.
     *
     * @param mixed $value
     * @return string[]
     */
    private static function toList(string $key, $value): array
    {
        if (is_array($value)) {
            $parts = $value;
        } elseif ($key === 'country_blacklist') {
            $parts = preg_split('/\s+/', trim((string) $value));
        } else {
            $parts = explode("\n", str_replace("\r", '', (string) $value));
        }

        // Language rules are regex fragments, and real Dashboards hold entries
        // like `'\p{Thai}` where a stray apostrophe survived however the value
        // was entered. Cleaned here as well as in the engine so the stored
        // value and the Language screen show the script the Dashboard meant,
        // rather than a rule that reads as configured but matches nothing.
        $isLanguage = $key === 'lang_needed' || $key === 'lang_forbidden';

        $items = [];
        foreach ((array) $parts as $part) {
            $part = trim((string) $part);
            if ($isLanguage) {
                $part = LanguageCheck::stripStrayQuotes($part);
            }
            if ($part !== '' && ! in_array($part, $items, true)) {
                $items[] = $part;
            }
        }

        return $items;
    }
}
