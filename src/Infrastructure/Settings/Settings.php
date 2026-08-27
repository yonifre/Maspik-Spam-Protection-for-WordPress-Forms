<?php

declare(strict_types=1);

namespace Maspik\Infrastructure\Settings;

/**
 * Typed access to plugin settings, backed by the v2 wp_maspik_options table,
 * with the dashboard-sync precedence rules (v2's efas_get_spam_api ⊕
 * maspik_get_settings call-site logic) encoded in ONE place.
 *
 * Precedence, exactly as shipped in v2.9.x:
 *  - booleans in the sync map: local OR dashboard (either turns it on)
 *  - lists (blacklists, formats): local MERGED WITH dashboard
 *  - scalars (API keys, thresholds): local wins, dashboard is fallback
 */
class Settings
{
    /** @var array<string, string>|null */
    private $local;

    /** @var array<string, mixed>|null */
    private $dashboard;

    /**
     * Raw local value from wp_maspik_options (request-cached). Falls back to a
     * renamed key's legacy option name if the new key's row doesn't exist yet
     * (i.e. before Upgrade's rename migration has run) — see Schema::$legacy.
     */
    public function raw(string $key): string
    {
        $this->loadLocal();

        if (isset($this->local[$key])) {
            return $this->local[$key];
        }

        $legacyKey = Schema::legacyKeyOf($key);
        if ($legacyKey !== null && isset($this->local[$legacyKey])) {
            return $this->local[$legacyKey];
        }

        return Schema::defaultOf($key);
    }

    /**
     * Logging mode, normalizing legacy values: 'none' | 'blocked' | 'all'.
     * v2/early-v3 stored 'no' (off) or 'yes' (on) here — mapped so existing
     * sites keep their behavior (off stays off, on becomes "blocked only").
     */
    /**
     * Maspik Matrix analysis depth: '2' = IP only, '4' = full pipeline.
     *
     * v2 also offered '3' ("IP + banned words"). It sent exactly the same
     * payload as the full pipeline — only the mode flag differed — so the extra
     * choice bought nothing but confusion. Stored '3' resolves to '4' here,
     * which sends no additional data and simply analyses it properly.
     */
    public function matrixMode(): string
    {
        $mode = trim($this->raw('maspik_matrix_api_mode'));

        // Whitelist the full-pipeline values ('3' is the retired "IP + banned
        // words", which sent the same payload). Anything else — including an
        // empty or unrecognised value — falls back to IP-only, so form content
        // is never sent to the cloud unless it was explicitly chosen.
        return ($mode === '4' || $mode === '3') ? '4' : '2';
    }

    public function logMode(): string
    {
        $value = $this->raw('maspik_Store_log');
        if ($value === 'none' || $value === 'no') {
            return 'none';
        }
        if ($value === 'all') {
            return 'all';
        }

        return 'blocked';
    }

    /**
     * Every stored option (raw), for export. Not defaults-filled — only rows
     * that actually exist in wp_maspik_options.
     *
     * @return array<string, string>
     */
    public function all(): array
    {
        $this->loadLocal();

        return $this->local === null ? [] : $this->local;
    }

    public function bool(string $key): bool
    {
        $value = $this->raw($key);

        return $value === '1' || $value === 'yes' || $value === 'on';
    }

    /** Boolean with dashboard OR-merge (v2 $MASPIK_SYNC_BOOL_API_TO_LOCAL). */
    public function boolEffective(string $key): bool
    {
        if ($this->bool($key)) {
            return true;
        }

        $dashboard = $this->dashboardValue($key);

        return $dashboard === true || $dashboard === '1' || $dashboard === 1;
    }

    public function intOrNull(string $key): ?int
    {
        $value = $this->raw($key);
        if (is_numeric($value)) {
            return (int) $value;
        }

        // v2 read these the same way: the local value wins, and the Dashboard
        // fills in when the site has not set one of its own
        // (spam-block.php: maspik_get_settings(X) ?: efas_get_spam_api(X)).
        // Without this a character or link limit configured centrally was
        // simply never applied.
        $dashboard = $this->dashboardValue($key);
        if (is_array($dashboard)) {
            $dashboard = $dashboard[0] ?? null;
        }

        return is_numeric($dashboard) ? (int) $dashboard : null;
    }

    /**
     * True when the Dashboard supplies a usable value for any of these keys.
     *
     * v2 used this to let a centrally-pushed rule switch its own check on, so a
     * site did not also have to flip the matching local toggle
     * (maspik_is_contain_api). Without it, a limit set in the Dashboard sat
     * inert on every site whose local toggle happened to be off.
     *
     * @param string[] $keys
     */
    public function dashboardProvides(array $keys): bool
    {
        foreach ($keys as $key) {
            $value = $this->dashboardValue($key);
            if (is_array($value)) {
                $value = $value[0] ?? null;
            }
            if ($value !== null && $value !== '' && $value !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * String value with dashboard fallback: the local setting wins, and the
     * dashboard value fills in only when the local one is empty (v2: "site
     * setting is stronger"). Used for the external-IP API keys and thresholds.
     */
    public function stringEffective(string $key): string
    {
        $local = trim($this->raw($key));
        if ($local !== '') {
            return $local;
        }

        $dashboard = $this->dashboardValue($key);
        if (is_string($dashboard)) {
            return trim($dashboard);
        }
        if (is_array($dashboard) && isset($dashboard[0]) && is_string($dashboard[0])) {
            return trim($dashboard[0]);
        }

        return '';
    }

    /**
     * Newline-separated rule list; optionally merged with the dashboard list
     * (v2: array_merge(local, api)).
     *
     * @return string[]
     */
    public function list(string $key, bool $merged = false): array
    {
        $items = array_filter(
            array_map('trim', explode("\n", str_replace("\r", '', $this->raw($key)))),
            static function (string $item): bool {
                return $item !== '';
            }
        );

        if ($merged) {
            $api = $this->dashboardValue($key);
            if (is_array($api)) {
                $items = array_merge($items, array_map('strval', $api));
            }
        }

        return array_values($items);
    }

    /**
     * Only the Dashboard's own entries for a rule list, without the local ones.
     *
     * list($key, true) answers "what does the engine enforce" by merging both
     * sources, which is right at submission time. This answers the narrower
     * question the rule editors need: which rules apply that the admin is *not*
     * currently editing. The tester there works on an unsaved draft, so it
     * cannot use the merged list — it has to combine the draft in hand with
     * these.
     *
     * @return string[]
     */
    public function dashboardList(string $key): array
    {
        $value = $this->dashboardValue($key);
        if (! is_array($value)) {
            return [];
        }

        $out = [];
        foreach ($value as $item) {
            if (is_scalar($item) && trim((string) $item) !== '') {
                $out[] = (string) $item;
            }
        }

        return $out;
    }

    /** @return array{0: string, 1: string[]} mode ('allow'|'block') + list */
    public function countryRules(): array
    {
        $mode = $this->raw('AllowedOrBlockCountries') === 'allow' ? 'allow' : 'block';
        $list = [];
        $localRaw = trim($this->raw('country_blacklist'));
        if ($localRaw !== '') {
            $list = explode(' ', $localRaw);
        }

        // Dashboard overrides entirely when it defines country rules (v2 behavior).
        $apiList = $this->dashboardValue('country_blacklist');
        $apiMode = $this->dashboardValue('AllowedOrBlockCountries');
        if (is_array($apiList) && $apiList !== [] && in_array($apiMode, ['allow', 'block'], true)) {
            $mode = $apiMode;
            $list = array_map('strval', $apiList);
        }

        return [$mode, array_values(array_filter($list))];
    }

    /**
     * The Verification Key expected value — same behavior as v2's
     * maspik_get_spam_key(): a single random value, generated once and stored
     * permanently in wp_options (native, not wp_maspik_options — matching v2
     * exactly so an existing site's stored key is picked up unchanged).
     *
     * Deliberately NOT derived from the date or a WP secret: a value that
     * rotates changes behind a full-page cache (WP Rocket, LiteSpeed, Cloudflare
     * APO, Varnish, …) and blocks real visitors served a stale cached page.
     */
    public function spamKey(): string
    {
        $key = get_option('maspik_spam_key');
        if (! is_string($key) || $key === '') {
            $key = wp_generate_password(64, false, false);
            update_option('maspik_spam_key', $key, false);
        }

        return $key;
    }

    public function customErrorMessage(string $checkId): string
    {
        if ($checkId === '') {
            return '';
        }

        $message = trim($this->raw('custom_error_message_' . $checkId));
        if ($message !== '') {
            return $message;
        }

        // Fall back to a custom message configured under the pre-rename check
        // id, so an existing site's message keeps working across the rename.
        if ($checkId === 'verification_key') {
            $legacy = trim($this->raw('custom_error_message_maspikTimeCheck'));
            if ($legacy !== '') {
                return $legacy;
            }
        }

        // Then the Dashboard's own per-check message. v2 ranked these exactly
        // this way (cfas_get_error_text): the site's own wording wins, and the
        // centrally-managed one applies when the site has not set its own.
        foreach (self::dashboardMessageKeys($checkId) as $key) {
            $fromDashboard = $this->dashboardValue($key);
            if (is_array($fromDashboard)) {
                $fromDashboard = $fromDashboard[0] ?? null;
            }
            if (is_string($fromDashboard) && trim($fromDashboard) !== '') {
                return trim($fromDashboard);
            }
        }

        return '';
    }

    /**
     * Dashboard option names that can carry a message for one check.
     *
     * The renamed check is listed under both ids, because the Dashboard is
     * still authored against v2's field names for existing accounts.
     *
     * @return string[]
     */
    private static function dashboardMessageKeys(string $checkId): array
    {
        $keys = ['custom_error_message_' . $checkId];
        if ($checkId === 'verification_key') {
            $keys[] = 'custom_error_message_maspikTimeCheck';
        }

        return $keys;
    }

    public function defaultErrorMessage(): string
    {
        $message = trim($this->raw('error_message'));
        if ($message !== '') {
            return $message;
        }

        // The Dashboard's site-wide message, before the plugin's built-in text
        // (v2: $text_general ?: $textAPI_general ?: $default_text).
        $fromDashboard = $this->dashboardValue('error_message');
        if (is_array($fromDashboard)) {
            $fromDashboard = $fromDashboard[0] ?? null;
        }
        if (is_string($fromDashboard) && trim($fromDashboard) !== '') {
            return trim($fromDashboard);
        }

        return self::builtinErrorMessage();
    }

    /**
     * What a blocked visitor sees when neither a per-check message nor the
     * site-wide default is set. Exposed so the admin can show the real fallback
     * as a placeholder rather than restating it in JS.
     */
    public static function builtinErrorMessage(): string
    {
        return __('This submission was blocked as spam.', 'contact-forms-anti-spam');
    }

    /**
     * Append a value to a newline-separated list setting, de-duplicated.
     * Used by the Logs whitelist actions. Returns true if it was added.
     */
    public function appendToList(string $key, string $value): bool
    {
        $value = trim($value);
        if ($value === '') {
            return false;
        }

        $items = $this->list($key);
        if (in_array($value, $items, true)) {
            return false;
        }

        $items[] = $value;
        $this->save($key, implode("\n", $items));

        return true;
    }

    /**
     * Remove a value from a delimited list setting (case-sensitive exact match).
     * Powers the Logs "Remove … from blocked list" action. The separator lets it
     * serve both newline lists (text/email/url/ip/language) and the
     * space-separated country list. Returns true if the value was removed.
     */
    public function removeFromList(string $key, string $value, string $separator = "\n"): bool
    {
        $value = trim($value);
        if ($value === '') {
            return false;
        }

        $raw = str_replace("\r", '', $this->raw($key));
        $items = array_values(array_filter(
            array_map('trim', explode($separator, $raw)),
            static function (string $item): bool {
                return $item !== '';
            }
        ));

        $filtered = array_values(array_filter($items, static function (string $item) use ($value): bool {
            return $item !== $value;
        }));

        if (count($filtered) === count($items)) {
            return false;
        }

        $this->save($key, implode($separator, $filtered));

        return true;
    }

    /**
     * One-time rename migration: copy a legacy option's stored value onto its
     * new key, if the new key holds no value yet. Idempotent — safe to call on
     * every upgrade run. The legacy row is left in place (never destroy data).
     *
     * An empty new key counts as "not set": some upgrade paths write blank rows
     * for every schema key, which would otherwise silently swallow the carried
     * value (e.g. losing a site's Dashboard connection on update).
     */
    public function renameLegacyOption(string $legacyKey, string $newKey): void
    {
        $this->loadLocal();

        if (isset($this->local[$newKey]) && trim((string) $this->local[$newKey]) !== '') {
            return;
        }
        if (isset($this->local[$legacyKey])) {
            $this->save($newKey, $this->local[$legacyKey]);
        }
    }

    public function save(string $key, string $value): void
    {
        global $wpdb;
        $this->loadLocal();

        $table = $wpdb->prefix . 'maspik_options';
        if (is_array($this->local) && array_key_exists($key, $this->local)) {
            $wpdb->update($table, ['option_value' => $value], ['option_name' => $key]);
        } else {
            $wpdb->insert($table, ['option_name' => $key, 'option_value' => $value]);
        }
        $this->local[$key] = $value;
    }

    private function loadLocal(): void
    {
        if ($this->local !== null) {
            return;
        }

        global $wpdb;
        $this->local = [];
        $rows = $wpdb->get_results(
            "SELECT option_name, option_value FROM {$wpdb->prefix}maspik_options",
            ARRAY_A
        );
        foreach ((array) $rows as $row) {
            $this->local[(string) $row['option_name']] = (string) $row['option_value'];
        }
    }

    /** @return mixed */
    private function dashboardValue(string $key)
    {
        if ($this->dashboard === null) {
            $option = get_option('spamapi');
            $this->dashboard = is_array($option) ? $option : [];
        }

        if (isset($this->dashboard[$key])) {
            return $this->dashboard[$key];
        }

        $legacyKey = Schema::legacyKeyOf($key);

        return $legacyKey !== null && isset($this->dashboard[$legacyKey]) ? $this->dashboard[$legacyKey] : null;
    }
}
