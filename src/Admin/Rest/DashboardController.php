<?php

declare(strict_types=1);

namespace Maspik\Admin\Rest;

use Maspik\Infrastructure\Settings\DashboardRules;
use Maspik\Infrastructure\Settings\Settings;
use Maspik\Premium\ProGate;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Central-Dashboard connection. A site connects with its Dashboard ID; the
 * Dashboard then pushes shared rules (blacklists) into the local `spamapi`
 * option, which the engine already merges into every check.
 *
 *   GET    /maspik/v1/dashboard          → { connected, id, synced_rules }
 *   POST   /maspik/v1/dashboard  {id}    → connect + pull rules
 *   DELETE /maspik/v1/dashboard          → disconnect
 *
 * refresh() pulls the shared rules from wpmaspik.com (mirrors v2's
 * cfas_refresh_api) and normalises them into the shape the engine reads from
 * `spamapi`. Runs on connect and can be re-fired on a cron.
 */
final class DashboardController
{
    private const API_BASE = 'https://wpmaspik.com/wp-json/acf/v3';

    /** Cron hook that re-pulls Dashboard rules (v2 weekly refresh). */
    public const CRON_HOOK = 'maspik_dashboard_sync';

    /** Outcome of a pull. Kept distinct because they need opposite handling. */
    private const FETCH_OK = 'ok';
    private const FETCH_NOT_FOUND = 'not_found';
    private const FETCH_UNREACHABLE = 'unreachable';

    /** Records why the last pull failed, so the admin can say so. */
    private const LAST_ERROR_OPTION = 'maspik_dashboard_last_error';

    /** @var Settings */
    private $settings;

    /** @var ProGate */
    private $pro;

    public function __construct(Settings $settings, ProGate $pro)
    {
        $this->settings = $settings;
        $this->pro = $pro;
    }

    public function registerRoutes(): void
    {
        $can = static function (): bool {
            return current_user_can('manage_options');
        };

        register_rest_route('maspik/v1', '/dashboard', [
            ['methods' => 'GET', 'callback' => [$this, 'status'], 'permission_callback' => $can],
            ['methods' => 'POST', 'callback' => [$this, 'connect'], 'permission_callback' => $can],
            ['methods' => 'DELETE', 'callback' => [$this, 'disconnect'], 'permission_callback' => $can],
        ]);
    }

    public function status(): WP_REST_Response
    {
        $id = $this->settings->raw('maspik_dashboard_id');

        return new WP_REST_Response([
            'connected' => $id !== '',
            'id' => $id,
            'synced_rules' => $this->syncedRuleCount(),
            'last_sync' => get_option('maspik_dashboard_last_sync', ''),
            // '' when the last pull succeeded; otherwise why it did not, so the
            // admin can say the rules on screen may be out of date.
            'last_error' => (string) get_option(self::LAST_ERROR_OPTION, ''),
        ]);
    }

    public function connect(WP_REST_Request $request): WP_REST_Response
    {
        if (! $this->pro->supports('pro')) {
            return new WP_REST_Response(['ok' => false, 'reason' => 'requires_pro'], 403);
        }

        $params = (array) $request->get_json_params();
        $id = isset($params['id']) ? sanitize_text_field((string) $params['id']) : '';
        if ($id === '') {
            return new WP_REST_Response(['ok' => false, 'reason' => 'missing_id'], 400);
        }

        // Verify before committing. The id is typed by hand — including, quite
        // legitimately, someone else's — so a typo is ordinary. Saving first and
        // reporting success regardless left a site "connected" to nothing,
        // syncing no rules, with nothing on screen to say so.
        $previous = $this->settings->raw('maspik_dashboard_id');
        $this->settings->save('maspik_dashboard_id', $id);

        $status = $this->refresh();
        if ($status !== self::FETCH_OK) {
            // Put the site back where it was rather than leave it pointing at an
            // id we could not confirm — reconnecting to a working Dashboard must
            // never be the price of one mistyped character.
            $this->settings->save('maspik_dashboard_id', $previous);

            return new WP_REST_Response(
                ['ok' => false, 'reason' => $status],
                $status === self::FETCH_NOT_FOUND ? 404 : 503
            );
        }

        if (! wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time() + DAY_IN_SECONDS, 'daily', self::CRON_HOOK);
        }

        return $this->status();
    }

    public function disconnect(): WP_REST_Response
    {
        $this->settings->save('maspik_dashboard_id', '');
        delete_option('spamapi');
        delete_option('maspik_dashboard_last_sync');
        delete_option(self::LAST_ERROR_OPTION);
        wp_clear_scheduled_hook(self::CRON_HOOK);

        return $this->status();
    }

    /** Cron callback: re-pull the shared rules. */
    public function sync(): void
    {
        $this->refresh();
    }

    /**
     * Pull shared rules from wpmaspik.com into the `spamapi` option (v2
     * cfas_refresh_api). Pro-only, like v2's cfes_is_supporting() gate.
     */
    private function refresh(): string
    {
        $id = $this->settings->raw('maspik_dashboard_id');
        if ($id === '' || ! $this->pro->supports('pro')) {
            return self::FETCH_UNREACHABLE;
        }

        $domain = (string) wp_parse_url(home_url(), PHP_URL_HOST);

        // Private per-account file (num is v2's opaque cache-buster).
        $result = $this->fetchAcf(self::API_BASE . '/apis/' . rawurlencode($id) . '?num=2367816&site=' . rawurlencode($domain));

        // A pull that did not succeed must leave the stored rules alone.
        // Overwriting them with the empty result of a failed request wiped
        // every centrally-managed rule on the site — silently, and on nothing
        // worse than one timed-out cron run, leaving the site unprotected until
        // the next successful sync. Only a confirmed answer replaces the rules.
        if ($result['status'] !== self::FETCH_OK) {
            update_option(self::LAST_ERROR_OPTION, $result['status'], false);

            return $result['status'];
        }

        $acf = $result['acf'];

        // Optional shared "popular spam" list, appended to the text/email/url
        // fields (v2 behaviour, gated on a setting; off by default). A failure
        // here only costs the extra list, so the account's own rules still sync.
        if ($this->settings->bool('maspik_popular_spam')) {
            $popularResult = $this->fetchAcf(self::API_BASE . '/options/public_api?num=234442&site=' . rawurlencode($domain));
            $popular = $popularResult['status'] === self::FETCH_OK ? $popularResult['acf'] : [];
            foreach (['text_field', 'textarea_field', 'email_field', 'url_field', 'contain_links'] as $f) {
                if (isset($popular[$f]) && $popular[$f] !== '') {
                    $acf[$f] = isset($acf[$f]) && $acf[$f] !== '' ? $acf[$f] . "\n" . $popular[$f] : $popular[$f];
                }
            }
        }

        update_option('spamapi', DashboardRules::normalize($acf));
        update_option('maspik_dashboard_last_sync', current_time('mysql'));
        delete_option(self::LAST_ERROR_OPTION);
        /** Fires after the site pulls rules from the Dashboard. */
        do_action('maspik/dashboard_refresh', $id);

        return self::FETCH_OK;
    }

    /**
     * GET an ACF v3 endpoint.
     *
     * Returns the outcome alongside the payload, because "no rules came back"
     * has three very different meanings and the caller must tell them apart:
     * a Dashboard that genuinely has no rules (replace what we hold), an id
     * that does not exist (tell the user their id is wrong), and a request
     * that never arrived (change nothing and try again later). Collapsing all
     * three into an empty array is what let a single failed request erase a
     * site's rules.
     *
     * @return array{status: string, acf: array<string, mixed>}
     */
    private function fetchAcf(string $url): array
    {
        $response = wp_remote_get($url, ['timeout' => 15]);
        if (is_wp_error($response)) {
            return ['status' => self::FETCH_UNREACHABLE, 'acf' => []];
        }

        $code = (int) wp_remote_retrieve_response_code($response);

        // The Dashboard answered and said this id is not one of its posts.
        if (in_array($code, [400, 401, 403, 404, 410], true)) {
            return ['status' => self::FETCH_NOT_FOUND, 'acf' => []];
        }
        if ($code !== 200) {
            return ['status' => self::FETCH_UNREACHABLE, 'acf' => []];
        }

        $body = json_decode((string) wp_remote_retrieve_body($response), true);
        if (! is_array($body) || ! array_key_exists('acf', $body)) {
            // A 200 that is not an ACF record — a WP REST error body, an HTML
            // error page from a proxy. Not something to trust as "no rules".
            return ['status' => self::FETCH_NOT_FOUND, 'acf' => []];
        }

        // ACF returns false, not [], for a record with no fields set. That is a
        // real, empty Dashboard — a success, not a failure.
        $acf = is_array($body['acf']) ? $body['acf'] : [];

        return ['status' => self::FETCH_OK, 'acf' => $acf];
    }

    private function syncedRuleCount(): int
    {
        $dashboard = get_option('spamapi');
        if (! is_array($dashboard)) {
            return 0;
        }
        $count = 0;
        foreach ($dashboard as $value) {
            if (is_array($value)) {
                $count += count($value);
            }
        }

        return $count;
    }
}
