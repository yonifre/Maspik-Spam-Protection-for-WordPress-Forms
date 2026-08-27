<?php

declare(strict_types=1);

namespace Maspik\Admin\Rest;

use Maspik\Application\ImportExport;
use Maspik\Infrastructure\Settings\Schema;
use Maspik\Infrastructure\Settings\Settings;
use WP_REST_Request;
use WP_REST_Response;

/**
 * GET/PATCH /maspik/v1/settings — schema-driven: the SPA renders from the
 * same Schema the server validates against. Plus export/import of the whole
 * settings set in the v2-compatible file format.
 */
final class SettingsController
{
    /** @var Settings */
    private $settings;

    /** @var ImportExport */
    private $importExport;

    public function __construct(Settings $settings, ImportExport $importExport)
    {
        $this->settings = $settings;
        $this->importExport = $importExport;
    }

    public function registerRoutes(): void
    {
        $can = static function (): bool {
            return current_user_can('manage_options');
        };

        register_rest_route('maspik/v1', '/settings', [
            ['methods' => 'GET', 'callback' => [$this, 'index'], 'permission_callback' => $can],
            ['methods' => 'PATCH', 'callback' => [$this, 'update'], 'permission_callback' => $can],
        ]);
        register_rest_route('maspik/v1', '/settings/export', [
            'methods' => 'GET', 'callback' => [$this, 'export'], 'permission_callback' => $can,
        ]);
        register_rest_route('maspik/v1', '/settings/import', [
            'methods' => 'POST', 'callback' => [$this, 'import'], 'permission_callback' => $can,
        ]);
    }

    public function export(): WP_REST_Response
    {
        return new WP_REST_Response([
            'filename' => 'maspik-settings.json',
            'content' => $this->importExport->export(),
        ]);
    }

    public function import(WP_REST_Request $request): WP_REST_Response
    {
        $params = (array) $request->get_json_params();
        $content = isset($params['content']) && is_string($params['content']) ? $params['content'] : '';

        // DoS guard: exports are tiny; reject oversized payloads.
        if (strlen($content) > 2 * 1024 * 1024) {
            return new WP_REST_Response(['ok' => false, 'reason' => 'too_large'], 413);
        }

        $result = $this->importExport->import($content);

        return new WP_REST_Response($result, $result['ok'] ? 200 : 400);
    }

    public function index(): WP_REST_Response
    {
        $values = [];
        foreach (Schema::options() as $key => $definition) {
            $values[$key] = [
                'value' => $this->settings->raw($key),
                'type' => $definition['type'],
                'pro' => isset($definition['pro']) ? $definition['pro'] : false,
            ];
        }

        // Per-check messages are keyed by check id, so they cannot live in the
        // schema and were being dropped here: update() accepted them and the
        // engine used them, but this response never returned them, so the field
        // came back empty on every reload and looked like the save had failed.
        foreach ($this->settings->all() as $key => $value) {
            if (strpos($key, 'custom_error_message_') === 0 && ! isset($values[$key])) {
                $values[$key] = [
                    'value' => $value,
                    'type' => Schema::TYPE_TEXT,
                    'pro' => false,
                ];
            }
        }

        return new WP_REST_Response([
            'settings' => $values,
            'synced' => $this->syncedFromDashboard(),
            'forced' => $this->forcedByDashboard(),
            // The values themselves, so a field can show what the Dashboard is
            // actually applying instead of rendering blank next to a rule the
            // engine is enforcing.
            'dashboard_values' => $this->dashboardValues(),
            // The text a visitor sees when nothing is configured. Sent so the
            // admin can show it as a placeholder instead of duplicating the
            // string in JS, where it would drift from the PHP the moment either
            // side is reworded or translated.
            'builtin' => ['error_message' => Settings::builtinErrorMessage()],
        ]);
    }

    /**
     * Rules pushed from the central MASPIK Dashboard (stored in the `spamapi`
     * option). The UI shows these read-only, separately from local rules, so
     * it's clear which rules are managed centrally.
     *
     * @return array<string, string[]>
     */
    private function syncedFromDashboard(): array
    {
        $dashboard = get_option('spamapi');
        if (! is_array($dashboard)) {
            return [];
        }

        $synced = [];
        foreach (Schema::options() as $key => $definition) {
            if ($definition['type'] === Schema::TYPE_MULTILINE
                && isset($dashboard[$key]) && is_array($dashboard[$key]) && $dashboard[$key] !== []) {
                $synced[$key] = array_values(array_map('strval', $dashboard[$key]));
            }
        }

        return $synced;
    }

    /**
     * Every setting the Dashboard currently supplies a value for.
     *
     * Reported so the admin can stop contradicting itself: until now a layer
     * enabled centrally still rendered as "off" here, which told the owner they
     * were unprotected while the check was in fact running. Only a Dashboard
     * "on" is listed — a Dashboard "off" forces nothing, because the local
     * toggle can still enable the layer on its own.
     *
     * @return array<string, bool>
     */
    private function forcedByDashboard(): array
    {
        $dashboard = get_option('spamapi');
        if (! is_array($dashboard)) {
            return [];
        }

        $forced = [];
        foreach ($dashboard as $key => $value) {
            $key = (string) $key;
            $definition = Schema::options()[$key] ?? null;
            $isMessage = strpos($key, 'custom_error_message_') === 0;

            // Booleans are only "forced" when the Dashboard says ON — a
            // Dashboard "off" forces nothing, because the local toggle can
            // still enable the layer on its own (Settings::boolEffective ORs
            // the two).
            if ($definition !== null && $definition['type'] === Schema::TYPE_BOOL) {
                if ($value === true || $value === 1 || $value === '1' || $value === 'yes') {
                    $forced[$key] = true;
                }
                continue;
            }

            // Everything else the Dashboard actually supplies a value for:
            // limits, thresholds, API keys, rule lists and per-check messages.
            // The admin needs all of them, not just the on/off switches, or a
            // field shows blank while a centrally-set value is what the engine
            // is really applying.
            if ($definition === null && ! $isMessage) {
                continue;
            }
            if (is_array($value) ? $value !== [] : (is_scalar($value) && trim((string) $value) !== '')) {
                $forced[$key] = true;
            }
        }

        return $forced;
    }

    /**
     * The Dashboard's own value for every setting it supplies, as display text.
     *
     * forcedByDashboard() answers "is this managed centrally"; this answers
     * "with what". Without it the admin can flag a field as Dashboard-managed
     * but still show an empty box, which is exactly the "I can't tell which
     * configuration is in control" complaint.
     *
     * @return array<string, string>
     */
    private function dashboardValues(): array
    {
        $dashboard = get_option('spamapi');
        if (! is_array($dashboard)) {
            return [];
        }

        $out = [];
        foreach ($dashboard as $key => $value) {
            $key = (string) $key;
            if (is_array($value)) {
                $flat = array_filter(array_map('strval', $value), static function (string $v): bool {
                    return trim($v) !== '';
                });
                if ($flat !== []) {
                    $out[$key] = implode("\n", $flat);
                }
                continue;
            }
            if (is_bool($value)) {
                $out[$key] = $value ? '1' : '0';
                continue;
            }
            if (is_scalar($value) && trim((string) $value) !== '') {
                $out[$key] = (string) $value;
            }
        }

        return $out;
    }

    public function update(WP_REST_Request $request): WP_REST_Response
    {
        $schema = Schema::options();
        $updated = [];

        foreach ((array) $request->get_json_params() as $key => $value) {
            $key = (string) $key;
            // Schema keys, plus per-check custom messages (custom_error_message_*)
            // which are dynamic and not enumerated in Schema. Anything else is
            // rejected — and every allowed key writes only to the plugin's own
            // maspik_options table, never wp_options.
            $allowed = isset($schema[$key]) || strpos($key, 'custom_error_message_') === 0;
            if (! $allowed || ! is_scalar($value)) {
                continue;
            }
            $this->settings->save($key, sanitize_textarea_field((string) $value));
            $updated[] = $key;
        }

        return new WP_REST_Response(['updated' => $updated]);
    }
}
