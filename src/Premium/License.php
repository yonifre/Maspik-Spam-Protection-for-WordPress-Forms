<?php

declare(strict_types=1);

namespace Maspik\Premium;

/**
 * License activation against the wpmaspik.com Digital License Manager (DLM v1).
 *
 * A thin client over the same REST endpoints v2 used through the ideologix/dlm
 * SDK — activate/validate/deactivate a key, authenticated with the store's
 * consumer key/secret (HTTP Basic). We store the returned activation token and
 * expiry locally; ProGate reads `maspik_license_active`.
 *
 *   activate:   GET wp-json/dlm/v1/licenses/activate/{key}
 *   validate:   GET wp-json/dlm/v1/licenses/validate/{token}
 *   deactivate: GET wp-json/dlm/v1/licenses/deactivate/{token}
 *
 * A lapsed license (expired, or a temporary server issue) keeps its token and
 * keeps rechecking twicedaily, so a renewal or payment reactivates Pro
 * automatically without the customer re-entering the key.
 */
final class License
{
    /** Recheck cron — revalidates the token and lapses/reactivates Pro. */
    public const CRON_HOOK = 'maspik_license_recheck';

    private const KEY_OPTION = 'maspik_license_key';
    private const ACTIVE_OPTION = 'maspik_license_active';
    private const TOKEN_OPTION = 'maspik_license_token';
    private const EXPIRES_OPTION = 'maspik_license_expires';
    private const CHECKED_OPTION = 'maspik_license_checked';
    private const STATUS_OPTION = 'maspik_license_status';
    private const MIGRATED_MARKER = 'maspik_license_migrated';
    private const SUGGESTED_DASHBOARD_OPTION = 'maspik_dashboard_suggested';

    private const API_BASE = 'https://wpmaspik.com/wp-json/dlm/v1';
    private const CONSUMER_KEY = 'ck_3fc0620008eb219e510b42d7a1164c7e0d28b2f1';
    private const CONSUMER_SECRET = 'cs_1eef46aeae9ef30571491672fd14b9cfcaf50856';

    public function key(): string
    {
        return (string) get_option(self::KEY_OPTION, '');
    }

    /** Per-site activation token from DLM — used to authenticate Matrix calls. */
    public function token(): string
    {
        return (string) get_option(self::TOKEN_OPTION, '');
    }

    public function isActive(): bool
    {
        return get_option(self::ACTIVE_OPTION, 'no') === 'yes';
    }

    /**
     * License state for the UI: whether Pro is active, when it was last checked,
     * expiry, and a human-readable note explaining a non-active state.
     *
     * @return array{active: bool, has_key: bool, expires_at: string, checked_at: string, state: string, message: string}
     */
    public function status(): array
    {
        $status = get_option(self::STATUS_OPTION, []);
        $status = is_array($status) ? $status : [];

        return [
            'active' => $this->isActive(),
            'has_key' => $this->key() !== '',
            'expires_at' => (string) get_option(self::EXPIRES_OPTION, ''),
            'checked_at' => (string) get_option(self::CHECKED_OPTION, ''),
            'state' => isset($status['state']) ? (string) $status['state'] : ($this->isActive() ? 'active' : 'free'),
            'message' => isset($status['message']) ? (string) $status['message'] : '',
            // Dashboard IDs the license server associated with this account (v2
            // user_first_api_post_id) — the UI offers to sync one of these.
            'dashboard_suggested' => $this->suggestedDashboardIds(),
        ];
    }

    /** @return string[] Dashboard IDs linked to the activated license. */
    public function suggestedDashboardIds(): array
    {
        $raw = get_option(self::SUGGESTED_DASHBOARD_OPTION, '');
        // Accept any scalar, not just a string. A single all-digits id can come
        // back as an int depending on how the value was written and whether the
        // options cache round-tripped it through the database, and rejecting
        // that left the account's only Dashboard missing from the picker with
        // nothing on screen to explain why.
        if (! is_scalar($raw)) {
            return [];
        }
        $raw = trim((string) $raw);
        if ($raw === '') {
            return [];
        }

        $ids = [];
        foreach (explode(',', $raw) as $id) {
            $id = trim($id);
            if ($id !== '' && ! in_array($id, $ids, true)) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    /**
     * Persist the Dashboard IDs the license server returns with an activation
     * (DLM `user_first_api_post_id`, comma-separated). Kept even if empty is
     * skipped, so a re-activation without the field doesn't wipe a prior one.
     *
     * @param array<string, mixed> $data
     */
    private function storeSuggestedDashboardIds(array $data): void
    {
        $raw = isset($data['user_first_api_post_id']) ? trim((string) $data['user_first_api_post_id']) : '';
        if ($raw !== '' && $raw !== '0') {
            update_option(self::SUGGESTED_DASHBOARD_OPTION, sanitize_text_field($raw), false);
        }
    }

    /**
     * One-time migration from v2/2.x's license storage: the ideologix/dlm-wp-
     * simple-checker SDK kept one array option, `{prefix}dlm_license` (this
     * plugin's prefix is 'maspik_', so `maspik_dlm_license`), holding
     * key/token/expires_at/deactivated_at. Without this, an already-licensed
     * site loses Pro on upgrade — the new code never looked at the old key.
     *
     * Guarded by a dedicated marker (not "is our key set"), so it never
     * re-fires — even after the customer explicitly deactivates, which clears
     * KEY_OPTION and would otherwise make this look unmigrated again and
     * silently re-activate from the stale legacy data.
     *
     * Trusts the carried-over token immediately (no re-entry, matching
     * docs/04's "license state must not require re-entry"); the recheck cron
     * then revalidates it against the current DLM v1 API within the hour and
     * self-corrects if the token turns out to be stale.
     */
    public function migrateLegacy(): void
    {
        if (get_option(self::MIGRATED_MARKER)) {
            return;
        }
        update_option(self::MIGRATED_MARKER, '1', false);

        $legacy = get_option('maspik_dlm_license');
        if (! is_array($legacy) || empty($legacy['key']) || empty($legacy['token'])) {
            return;
        }

        $expiresAt = isset($legacy['expires_at']) ? (string) $legacy['expires_at'] : '';
        $deactivatedAt = isset($legacy['deactivated_at']) ? (string) $legacy['deactivated_at'] : '';

        update_option(self::KEY_OPTION, (string) $legacy['key']);
        update_option(self::TOKEN_OPTION, (string) $legacy['token'], false);
        update_option(self::EXPIRES_OPTION, $expiresAt, false);
        update_option(self::CHECKED_OPTION, isset($legacy['checked_at']) ? (string) $legacy['checked_at'] : '', false);

        // Mirror the old SDK's isLicenseValid()/getStatus(): valid unless
        // explicitly deactivated or past its expiry.
        $active = $deactivatedAt === '' && ! ($expiresAt !== '' && $this->isExpired($expiresAt));
        update_option(self::ACTIVE_OPTION, $active ? 'yes' : 'no', false);

        $this->scheduleRecheck();

        // A real token just became available — let Matrix's usage meter refresh
        // from the server immediately instead of showing a stale/zeroed count
        // until the next real submission.
        do_action('maspik/license_changed');
    }

    /**
     * Activate a key with the DLM server. On a valid, non-expired response Pro
     * turns on. An expired-but-recognised key is stored and kept under recheck
     * so a later renewal flips Pro on automatically.
     *
     * @return array{ok: bool, reason?: string, message?: string}
     */
    public function activate(string $key): array
    {
        $key = trim($key);
        if (! self::looksValid($key)) {
            return ['ok' => false, 'reason' => 'invalid_key_format'];
        }

        try {
            $response = $this->request('/licenses/activate/' . rawurlencode($key), [
                'label' => home_url(),
                'meta' => [
                    'wp_version' => get_bloginfo('version'),
                    'php_version' => PHP_VERSION,
                ],
            ]);
            if (! $response['ok']) {
                $this->setStatus($response['reason'], $response['message']);

                return ['ok' => false, 'reason' => $response['reason'], 'message' => $response['message']];
            }

            $data = $response['data'];
            $token = isset($data['token']) ? (string) $data['token'] : '';
            if ($token === '') {
                return ['ok' => false, 'reason' => 'no_token'];
            }

            $eval = $this->evaluateLicense($data);
            update_option(self::KEY_OPTION, $key);
            update_option(self::TOKEN_OPTION, $token, false);
            update_option(self::EXPIRES_OPTION, $eval['expires_at'], false);
            update_option(self::CHECKED_OPTION, current_time('mysql'), false);
            $this->storeSuggestedDashboardIds($data);
            $this->scheduleRecheck();

            // Recognised but not currently usable (expired / disabled): keep the
            // token + recheck loop so a renewal or re-enable reactivates Pro on
            // its own, without the customer re-entering the key.
            if (! $eval['valid']) {
                update_option(self::ACTIVE_OPTION, 'no', false);
                $this->setStatus($eval['reason'], self::reasonMessage($eval['reason']));

                return ['ok' => false, 'reason' => $eval['reason'], 'message' => self::reasonMessage($eval['reason'])];
            }

            update_option(self::ACTIVE_OPTION, 'yes', false);
            $this->clearStatus();

            return ['ok' => true];
        } finally {
            // A token now exists (or was just found unusable) — let listeners
            // (Matrix's usage meter) refresh from the server, regardless of
            // which branch above returned.
            do_action('maspik/license_changed');
        }
    }

    /**
     * Called when the plugin is (re)activated. If a license is already stored,
     * reschedule the recheck cron and revalidate the existing token so Pro keeps
     * working across a deactivate/activate — without consuming a new activation
     * seat (which a fresh activate() would). Fail-open: a network hiccup here
     * leaves the stored active state untouched.
     */
    public function resume(): void
    {
        if ((string) get_option(self::TOKEN_OPTION, '') === '') {
            return;
        }

        $this->scheduleRecheck();
        $this->recheck();
    }

    public function deactivate(): void
    {
        $token = (string) get_option(self::TOKEN_OPTION, '');
        if ($token !== '') {
            // Best-effort: release the seat on the server. Local state is cleared
            // regardless of the server's reply.
            $this->request('/licenses/deactivate/' . rawurlencode($token), []);
        }

        update_option(self::ACTIVE_OPTION, 'no', false);
        delete_option(self::KEY_OPTION);
        delete_option(self::TOKEN_OPTION);
        delete_option(self::EXPIRES_OPTION);
        delete_option(self::STATUS_OPTION);
        delete_option(self::SUGGESTED_DASHBOARD_OPTION);
        wp_clear_scheduled_hook(self::CRON_HOOK);

        // Drops back to the free-tier usage bucket — let the usage meter
        // refresh right away instead of showing a stale Pro-era number.
        do_action('maspik/license_changed');
    }

    /**
     * Cron recheck: always revalidate the stored token with the server so a
     * renewal is picked up even after the license has lapsed. Pro lapses on a
     * definitive rejection or expiry; a transport failure keeps the current
     * state (grace period). The token and cron are never dropped here, so the
     * check keeps running until the customer explicitly deactivates.
     *
     * Also the main mechanism that keeps the Matrix usage meter fresh day to
     * day (fires `maspik/license_changed` every run, not just on transitions).
     */
    public function recheck(): void
    {
        $token = (string) get_option(self::TOKEN_OPTION, '');
        if ($token === '') {
            return;
        }

        try {
            $response = $this->request('/licenses/validate/' . rawurlencode($token), []);
            update_option(self::CHECKED_OPTION, current_time('mysql'), false);

            if (! $response['ok']) {
                // Transport / server-side failure → grace period: keep the
                // current state, keep checking. Never lapse a paid license
                // over an outage.
                if ($response['reason'] === 'network_error' || $response['reason'] === 'server_error') {
                    return;
                }
                // Definitive rejection (invalid/removed/expired on the server side).
                update_option(self::ACTIVE_OPTION, 'no', false);
                $this->setStatus($response['reason'], $response['message']);

                return;
            }

            // Token accepted. Re-evaluate against the fresh license state
            // (expiry + status), so a renewal reactivates and a
            // disable/refund lapses Pro.
            $eval = $this->evaluateLicense($response['data']);
            update_option(self::EXPIRES_OPTION, $eval['expires_at'], false);

            // Capture the linked Dashboard IDs here too, not only on activation.
            // A site that activated before this field was read — or that carried
            // its license over from version 2, which never calls activate() at
            // all — would otherwise never learn which Dashboards its account
            // owns, and the connect screen would offer nothing however many the
            // customer actually has. The recheck already receives the same
            // license payload twice a day, so this costs nothing and needs no
            // re-entry of the key.
            $this->storeSuggestedDashboardIds($response['data']);

            if (! $eval['valid']) {
                update_option(self::ACTIVE_OPTION, 'no', false);
                $this->setStatus($eval['reason'], self::reasonMessage($eval['reason']));

                return;
            }

            update_option(self::ACTIVE_OPTION, 'yes', false);
            $this->clearStatus();
        } finally {
            do_action('maspik/license_changed');
        }
    }

    /**
     * Decide whether a license is currently usable from a DLM activation /
     * validation payload. A DISABLED/INACTIVE status or an expired term makes it
     * unusable; DLM's own `is_expired` flag is trusted alongside `expires_at`.
     *
     * @param array<string, mixed> $data
     * @return array{valid: bool, reason: string, expires_at: string}
     */
    private function evaluateLicense(array $data): array
    {
        $license = isset($data['license']) && is_array($data['license']) ? $data['license'] : [];
        $expiresAt = isset($license['expires_at']) ? (string) $license['expires_at'] : '';
        $status = isset($license['status']) ? (int) $license['status'] : 0;

        // DLM statuses: 1 SOLD, 2 DELIVERED, 3 ACTIVE, 4 INACTIVE, 5 DISABLED.
        if ($status === 4 || $status === 5) {
            return ['valid' => false, 'reason' => 'disabled', 'expires_at' => $expiresAt];
        }

        $isExpired = ! empty($license['is_expired']) || ($expiresAt !== '' && $this->isExpired($expiresAt));
        if ($isExpired) {
            return ['valid' => false, 'reason' => 'expired', 'expires_at' => $expiresAt];
        }

        return ['valid' => true, 'reason' => '', 'expires_at' => $expiresAt];
    }

    private static function reasonMessage(string $reason): string
    {
        $map = [
            'expired' => 'This license has expired. It will reactivate automatically once renewed.',
            'disabled' => 'This license has been disabled. Contact support if you believe this is a mistake.',
            'activation_limit' => 'This license has reached its activation limit. Deactivate it on another site, or upgrade your plan for more.',
            'invalid_key' => "This license key wasn't found. Double-check it and try again.",
            'server_error' => 'The license server returned an unexpected response. Please try again shortly.',
            'network_error' => "Couldn't reach the license server. Check your connection and try again.",
        ];

        return isset($map[$reason]) ? $map[$reason] : 'This license is not currently active.';
    }

    /**
     * One authenticated DLM GET.
     *
     * @param array<string, mixed> $query
     * @return array{ok: bool, reason: string, message: string, data: array<string, mixed>}
     */
    private function request(string $path, array $query): array
    {
        $url = self::API_BASE . $path;
        if ($query !== []) {
            $url = add_query_arg($query, $url);
        }

        $key = defined('MASPIK_DLM_CONSUMER_KEY') ? MASPIK_DLM_CONSUMER_KEY : self::CONSUMER_KEY;
        $secret = defined('MASPIK_DLM_CONSUMER_SECRET') ? MASPIK_DLM_CONSUMER_SECRET : self::CONSUMER_SECRET;

        $response = wp_remote_get($url, [
            'timeout' => 15,
            'headers' => ['Authorization' => 'Basic ' . base64_encode($key . ':' . $secret)],
        ]);
        if (is_wp_error($response)) {
            return ['ok' => false, 'reason' => 'network_error', 'message' => $response->get_error_message(), 'data' => []];
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $body = json_decode((string) wp_remote_retrieve_body($response), true);
        if (! is_array($body)) {
            // Non-JSON reply (e.g. an nginx 405/5xx HTML page). An HTTP error is
            // a server-side problem, not a bad key — treat it as transient so an
            // active license is never lapsed over it.
            return ['ok' => false, 'reason' => $code >= 400 ? 'server_error' : 'network_error', 'message' => '', 'data' => []];
        }

        // Success envelope: { success: true, data: {...} }.
        if (isset($body['success']) && $body['success'] === true) {
            return ['ok' => true, 'reason' => '', 'message' => '', 'data' => isset($body['data']) && is_array($body['data']) ? $body['data'] : []];
        }

        // Definitive DLM rejection — { success: false } or a WP_Error envelope
        // { code, message, data: { status } }. Prefer DLM's `code` for a precise
        // reason (e.g. activation limit vs a genuinely missing key).
        $dlmCode = isset($body['code']) ? (string) $body['code'] : '';
        $status = isset($body['data']['status']) ? (int) $body['data']['status'] : $code;
        $message = isset($body['message']) ? (string) $body['message'] : '';
        if ($dlmCode === 'license_activation_limit_reached') {
            $reason = 'activation_limit';
        } elseif ($status === 404) {
            $reason = 'invalid_key';
        } else {
            $reason = 'invalid_response';
        }

        return ['ok' => false, 'reason' => $reason, 'message' => $message, 'data' => []];
    }

    private function scheduleRecheck(): void
    {
        if (! wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time() + DAY_IN_SECONDS, 'twicedaily', self::CRON_HOOK);
        }
    }

    private function setStatus(string $state, string $message): void
    {
        update_option(self::STATUS_OPTION, ['state' => $state, 'message' => $message], false);
    }

    private function clearStatus(): void
    {
        delete_option(self::STATUS_OPTION);
    }

    private function isExpired(string $expiresAt): bool
    {
        $ts = strtotime($expiresAt);

        return $ts !== false && $ts < time();
    }

    /** Basic shape check: DLM keys are long alphanumeric-with-dashes strings. */
    private static function looksValid(string $key): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9\-]{16,}$/', $key);
    }
}
