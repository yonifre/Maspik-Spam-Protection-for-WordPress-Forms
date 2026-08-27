<?php

declare(strict_types=1);

namespace Maspik\Application;

use Maspik\Infrastructure\Settings\Schema;
use Maspik\Infrastructure\Settings\Settings;

/**
 * Settings import/export in the v2.9.x file format, so a file exported by any
 * existing site imports cleanly into 3.0 and vice-versa (docs/04 § 1.6).
 *
 * File format (three parts separated by blank lines):
 *   <plugin version marker>
 *
 *   <source site URL>
 *
 *   <JSON object of settings + system info>
 *
 * parse()/serialize() are pure (no WordPress) so the format is unit-tested
 * directly; export()/import() are the WordPress-facing wrappers.
 */
final class ImportExport
{
    /** v2's legacy first-line marker, still accepted on import. */
    private const LEGACY_MARKER = 'OnlyYouKnowWhatIsGoodForYou';

    /** Minimum exporting-plugin version whose files import safely. */
    private const MIN_VERSION = '2.8.0';

    /** System-info / bundled-option keys that are never imported as settings. */
    private const METADATA_KEYS = [
        'wordpress_version', 'plugin_version', 'wordpress_language',
        'php_version', 'theme_name', 'spamcounter', 'maspik_api_requests',
        'shere_data',
    ];

    /** @var Settings */
    private $settings;

    public function __construct(Settings $settings)
    {
        $this->settings = $settings;
    }

    // -- WordPress-facing ---------------------------------------------------

    public function export(): string
    {
        $settings = $this->settings->all();
        // Drop secrets, add the system info v2 exports carry.
        unset($settings['maspik_ai_logs'], $settings['maspik_ai_client_secret']);
        $settings['plugin_version'] = MASPIK_VERSION;
        $settings['wordpress_version'] = get_bloginfo('version');
        $settings['php_version'] = phpversion();

        return self::serialize($settings, get_site_url(), MASPIK_VERSION);
    }

    /**
     * @return array{ok: bool, imported: int, skipped: int, reason?: string}
     */
    public function import(string $content): array
    {
        $parsed = self::parse($content);
        if ($parsed === null) {
            return ['ok' => false, 'imported' => 0, 'skipped' => 0, 'reason' => 'invalid_file'];
        }
        if (! self::isImportable($parsed['header'], $parsed['settings'])) {
            return ['ok' => false, 'imported' => 0, 'skipped' => 0, 'reason' => 'unsupported_version'];
        }

        $imported = 0;
        $skipped = 0;
        foreach ($parsed['settings'] as $key => $value) {
            $key = (string) $key;
            // A file exported before a key was renamed still carries the old
            // name — remap it onto today's key so the value isn't dropped.
            $key = Schema::currentKeyOf($key) ?? $key;
            if (! self::isImportableKey($key) || is_array($value)) {
                $skipped++;
                continue;
            }
            $this->settings->save($key, self::sanitizeValue($key, (string) $value));
            $imported++;
        }

        return ['ok' => true, 'imported' => $imported, 'skipped' => $skipped];
    }

    // -- Pure format helpers (unit-tested without WordPress) ----------------

    /**
     * @param array<string, mixed> $settings
     */
    public static function serialize(array $settings, string $domain, string $versionMarker): string
    {
        return $versionMarker . "\n\n" . $domain . "\n\n" . json_encode($settings);
    }

    /**
     * @return array{header: string, domain: string, settings: array<string, mixed>}|null
     */
    public static function parse(string $content): ?array
    {
        $parts = explode("\n\n", $content, 3);
        if (count($parts) !== 3) {
            return null;
        }

        $settings = json_decode($parts[2], true);
        if (! is_array($settings)) {
            return null;
        }

        return [
            'header' => trim($parts[0]),
            'domain' => trim($parts[1]),
            'settings' => $settings,
        ];
    }

    /**
     * Accept v2's legacy marker or any exporting version >= 2.8.0 (from the
     * header line or the JSON plugin_version).
     *
     * @param array<string, mixed> $settings
     */
    public static function isImportable(string $header, array $settings): bool
    {
        if ($header === self::LEGACY_MARKER) {
            return true;
        }

        foreach ([$header, (string) ($settings['plugin_version'] ?? '')] as $candidate) {
            if (preg_match('/^(\d+(?:\.\d+){0,3})/', trim($candidate), $m)
                && version_compare($m[1], self::MIN_VERSION, '>=')) {
                return true;
            }
        }

        return false;
    }

    private static function isImportableKey(string $key): bool
    {
        if ($key === '' || is_numeric($key) || in_array($key, self::METADATA_KEYS, true)) {
            return false;
        }

        $options = Schema::options();

        return isset($options[$key])
            || strpos($key, 'maspik_support_') === 0
            || strpos($key, 'custom_error_message_') === 0;
    }

    /** Multiline settings must keep their newlines (blacklists, messages). */
    private static function sanitizeValue(string $key, string $value): string
    {
        $options = Schema::options();
        $isMultiline = (isset($options[$key]) && $options[$key]['type'] === Schema::TYPE_MULTILINE)
            || strpos($key, 'custom_error_message_') === 0
            || in_array($key, ['error_message', 'maspik_ai_context'], true);

        return $isMultiline ? sanitize_textarea_field($value) : sanitize_text_field($value);
    }
}
