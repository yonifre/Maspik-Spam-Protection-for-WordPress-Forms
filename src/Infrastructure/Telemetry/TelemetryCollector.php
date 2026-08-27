<?php

declare(strict_types=1);

namespace Maspik\Infrastructure\Telemetry;

use Maspik\Infrastructure\Logging\LogRepository;
use Maspik\Infrastructure\Settings\Schema;
use Maspik\Infrastructure\Settings\Settings;
use Maspik\Integrations\Registry;
use Maspik\Premium\ProGate;

/**
 * Builds the opt-in telemetry payload.
 *
 * The hard rule this class exists to enforce: **nothing a visitor wrote ever
 * leaves the site**. No submitted field values, no sender emails, no IP
 * addresses, no user agents, no log rows. What goes out is the shape of the
 * install and counts of what happened — enough to see which layers earn their
 * keep in the wild, and nothing that could identify a person who filled in a
 * form.
 *
 * The site's own configuration is reported as *counts*, not contents: knowing a
 * site blocks 42 words is useful, knowing which 42 words is the site owner's
 * business and can itself carry names and addresses.
 *
 * Collecting is deliberately separate from sending, so the payload can be
 * inspected and tested without any network involved.
 */
final class TelemetryCollector
{
    /** Bump when the payload shape changes, so the receiver can branch on it. */
    public const SCHEMA_VERSION = 1;

    /** @var Settings */
    private $settings;

    /** @var LogRepository */
    private $logs;

    /** @var Registry */
    private $registry;

    /** @var ProGate */
    private $pro;

    public function __construct(Settings $settings, LogRepository $logs, Registry $registry, ProGate $pro)
    {
        $this->settings = $settings;
        $this->logs = $logs;
        $this->registry = $registry;
        $this->pro = $pro;
    }

    /**
     * @return array<string, mixed>
     */
    public function collect(): array
    {
        return [
            'schema' => self::SCHEMA_VERSION,
            'sent_at' => gmdate('c'),
            'site' => $this->site(),
            'maspik' => $this->maspik(),
            'env' => $this->environment(),
            'usage' => $this->usage(),
            'config' => $this->config(),
        ];
    }

    /** @return array<string, mixed> */
    private function site(): array
    {
        global $wpdb;

        return [
            'domain' => $this->domain(),
            'wp' => get_bloginfo('version'),
            'php' => PHP_VERSION,
            'mysql' => isset($wpdb) ? (string) $wpdb->db_version() : '',
            'locale' => get_locale(),
            'is_multisite' => is_multisite(),
            'is_ssl' => is_ssl(),
        ];
    }

    /** @return array<string, mixed> */
    private function maspik(): array
    {
        $quota = get_option('maspik_matrix_server_quota', null);

        return [
            'version' => defined('MASPIK_VERSION') ? MASPIK_VERSION : '',
            'plan' => $this->pro->isActive() ? 'pro' : 'free',
            'upgraded_from' => (string) get_option('maspik_previous_version', ''),
            'matrix_enabled' => $this->settings->bool('maspik_ai_enabled'),
            'matrix_mode' => $this->settings->matrixMode(),
            'log_mode' => $this->settings->logMode(),
            'matrix_quota' => is_array($quota) ? $quota : null,
        ];
    }

    /**
     * The surrounding install: theme and plugins, name and version only.
     *
     * @return array<string, mixed>
     */
    private function environment(): array
    {
        $theme = wp_get_theme();
        $parent = $theme->parent();

        return [
            'theme' => [
                'name' => $theme->get('Name'),
                'version' => $theme->get('Version'),
                'parent' => $parent ? $parent->get('Name') : null,
            ],
            'plugins' => $this->activePlugins(),
            'integrations' => $this->integrations(),
        ];
    }

    /**
     * Active plugins as name + version. Uses the same get_plugins() data the
     * Plugins screen shows, so nothing is inferred from file paths.
     *
     * @return array<int, array{name: string, version: string}>
     */
    private function activePlugins(): array
    {
        if (! function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $all = function_exists('get_plugins') ? get_plugins() : [];

        return self::normalisePlugins((array) get_option('active_plugins', []), is_array($all) ? $all : []);
    }

    /**
     * Pure shaping, kept separate from WordPress so it can be tested against
     * the malformed data real installs produce: a plugin listed as active but
     * uninstalled, an entry with no Name, a header that is an array.
     *
     * @param array<int|string, mixed> $active
     * @param array<string, mixed> $all
     * @return array<int, array{name: string, version: string}>
     */
    public static function normalisePlugins(array $active, array $all): array
    {
        $out = [];
        foreach ($active as $file) {
            if (! is_string($file) || ! isset($all[$file]) || ! is_array($all[$file])) {
                continue;
            }
            $name = $all[$file]['Name'] ?? '';
            $version = $all[$file]['Version'] ?? '';
            if (! is_scalar($name) || (string) $name === '') {
                continue;
            }
            $out[] = [
                'name' => (string) $name,
                'version' => is_scalar($version) ? (string) $version : '',
            ];
        }

        return $out;
    }

    /**
     * Which form plugins MASPIK detected and whether its protection is on for
     * them — the single most useful signal for deciding where to invest.
     *
     * @return array<int, array{id: string, available: bool, enabled: bool}>
     */
    private function integrations(): array
    {
        $out = [];
        foreach ($this->registry->describe() as $row) {
            $out[] = [
                'id' => (string) ($row['id'] ?? ''),
                'available' => (bool) ($row['available'] ?? false),
                'enabled' => (bool) ($row['enabled'] ?? false),
            ];
        }

        return $out;
    }

    /**
     * Counts only — how much was blocked, by which layer, from which form,
     * over time. No row ever leaves the site.
     *
     * @return array<string, mixed>
     */
    private function usage(): array
    {
        $byDay = $this->logs->countsByDay(30);
        $last = $this->logs->lastBlocked();

        return [
            'total_blocked' => $this->logs->totalBlocked(),
            'blocked_30d' => array_sum(array_map(static fn ($r): int => (int) ($r['count'] ?? 0), $byDay)),
            'by_layer' => self::pairs($this->logs->countsByType(), 'type'),
            'by_source' => $this->sourceCounts(),
            'by_day' => self::pairs($byDay, 'day'),
            // Date only, so it says "still in use" without pinpointing a person's submission.
            'last_blocked_on' => $last !== null ? substr((string) ($last['spam_date'] ?? ''), 0, 10) : null,
        ];
    }

    /**
     * Blocks per form plugin, with the page dropped.
     *
     * `spam_source` is stored as "Label|||https://site/page", so counting the
     * raw column would ship every URL a form sits on — including query strings
     * with preview nonces, and, on a site that serves more than one domain,
     * other people's addresses. Only the label before the separator is any use
     * for telemetry, so that is all that leaves.
     *
     * @return array<string, int>
     */
    private function sourceCounts(): array
    {
        return self::normaliseSources($this->logs->countsBySource());
    }

    /**
     * @param array<int, mixed> $rows
     * @return array<string, int>
     */
    public static function normaliseSources(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $raw = $row['source'] ?? '';
            if (! is_scalar($raw)) {
                continue;
            }
            $label = trim(explode('|||', (string) $raw)[0]);
            if ($label === '') {
                continue;
            }
            $count = $row['count'] ?? 0;
            $out[$label] = ($out[$label] ?? 0) + (is_numeric($count) ? (int) $count : 0);
        }

        arsort($out);

        return $out;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<string, int>
     */
    public static function pairs(array $rows, string $keyField): array
    {
        $out = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $key = $row[$keyField] ?? '';
            if (! is_scalar($key) || (string) $key === '') {
                continue;
            }
            $count = $row['count'] ?? 0;
            $out[(string) $key] = is_numeric($count) ? (int) $count : 0;
        }

        return $out;
    }

    /**
     * Which layers are switched on, and how big each rule list is.
     *
     * Counts, never contents: a blocklist routinely holds a competitor's
     * domain or a real person's address, and that is the site owner's data.
     *
     * @return array<string, mixed>
     */
    private function config(): array
    {
        $enabled = [];
        $ruleCounts = [];

        foreach (Schema::options() as $key => $definition) {
            $type = (string) ($definition['type'] ?? '');

            if ($type === Schema::TYPE_BOOL) {
                $enabled[$key] = $this->settings->bool($key);
                continue;
            }

            if ($type === Schema::TYPE_MULTILINE) {
                $ruleCounts[$key] = count($this->settings->list($key));
            }
        }

        return [
            'layers_on' => $enabled,
            'rule_counts' => $ruleCounts,
        ];
    }

    /** The registered domain, which is what identifies a report to the receiver. */
    private function domain(): string
    {
        $host = wp_parse_url(home_url(), PHP_URL_HOST);

        return is_string($host) ? $host : '';
    }
}
