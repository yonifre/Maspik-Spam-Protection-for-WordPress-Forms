<?php

declare(strict_types=1);

namespace Maspik\Infrastructure\Settings;

if (! defined('ABSPATH')) {
    exit;
}


/**
 * THE single source of truth for every option: type, default, and where the
 * dashboard API may contribute a value. The REST layer and the admin SPA
 * render/validate from this same definition.
 *
 * Defaults are copied from v2.9.x $MASPIK_DEFAULT_SETTINGS.
 * (Scaffold: core keys listed; the full ~90-key schema lands in Phase 1.)
 */
final class Schema
{
    public const TYPE_BOOL = 'bool';
    public const TYPE_INT = 'int';
    public const TYPE_TEXT = 'text';
    public const TYPE_MULTILINE = 'multiline'; // newline-separated rule lists
    public const TYPE_SELECT = 'select';

    /**
     * @return array<string, array{type: string, default: string, dashboard?: bool, pro?: bool, legacy?: string}>
     */
    public static function options(): array
    {
        return [
            // Behavioral layers — dashboard values OR-merge with local (v2 sync map).
            'maspikHoneypot' => ['type' => self::TYPE_BOOL, 'default' => '1', 'dashboard' => true],
            // v3 rename of v2's 'maspikTimeCheck' — same on/off flag, clearer name
            // (it verifies a page-rendered submission, it was never about elapsed
            // time). 'legacy' lets Settings fall back to the old option/dashboard
            // value until Upgrade migrates it, so existing sites need no action.
            'verification_key' => ['type' => self::TYPE_BOOL, 'default' => '1', 'dashboard' => true, 'legacy' => 'maspikTimeCheck'],
            // Note: v2's 'NeedPageurl' toggle is gone. Direct POST detection now
            // always runs (DirectPostSignal) — it only scores a submission for
            // Matrix and never blocks by itself, so there is nothing to tune.

            // Client signals. Observation only: the guard script measures how a
            // submission was produced and the result is reported to InputGate
            // under its own key. Nothing here participates in a verdict, local
            // or remote, so there is no false-positive risk to weigh and it is
            // on by default like the other always-safe layers.
            'client_signals_observe' => ['type' => self::TYPE_BOOL, 'default' => '1'],
            // Rendering-anomaly probes. Off by default: they cost real work on
            // every page load, and unlike the rest of the collector they draw to
            // a canvas, so they stay something a site opts into.
            'client_signals_probes' => ['type' => self::TYPE_BOOL, 'default' => ''],

            // MemberPress: also spam-check signups that charge at checkout.
            // Off by default — MemberPress creates the account before taking
            // payment, so a false positive there aborts a purchase rather than
            // just refusing a form.
            'maspik_memberpress_gate_paid' => ['type' => self::TYPE_BOOL, 'default' => ''],

            // Content rules.
            'text_blacklist' => ['type' => self::TYPE_MULTILINE, 'default' => '', 'dashboard' => true],
            'emails_blacklist' => ['type' => self::TYPE_MULTILINE, 'default' => '', 'dashboard' => true],
            'url_blacklist' => ['type' => self::TYPE_MULTILINE, 'default' => '', 'dashboard' => true],
            'tel_formats' => ['type' => self::TYPE_MULTILINE, 'default' => '', 'dashboard' => true],

            // Limits & toggles.
            'text_limit_toggle' => ['type' => self::TYPE_BOOL, 'default' => ''],
            'MinCharactersInTextField' => ['type' => self::TYPE_INT, 'default' => ''],
            'MaxCharactersInTextField' => ['type' => self::TYPE_INT, 'default' => ''],
            'textarea_limit_toggle' => ['type' => self::TYPE_BOOL, 'default' => ''],
            'MinCharactersInTextAreaField' => ['type' => self::TYPE_INT, 'default' => ''],
            'MaxCharactersInTextAreaField' => ['type' => self::TYPE_INT, 'default' => ''],
            'tel_limit_toggle' => ['type' => self::TYPE_BOOL, 'default' => ''],
            'MinCharactersInPhoneField' => ['type' => self::TYPE_INT, 'default' => ''],
            'MaxCharactersInPhoneField' => ['type' => self::TYPE_INT, 'default' => ''],
            'textarea_link_limit_toggle' => ['type' => self::TYPE_BOOL, 'default' => ''],
            'contain_links' => ['type' => self::TYPE_INT, 'default' => '', 'dashboard' => true],
            'emoji_check' => ['type' => self::TYPE_BOOL, 'default' => ''],

            // Origin rules.
            'ip_blacklist' => ['type' => self::TYPE_MULTILINE, 'default' => ''],
            // Allow lists — populated by the Logs "Not spam" / "Whitelist" actions.
            'ip_whitelist' => ['type' => self::TYPE_MULTILINE, 'default' => ''],
            'emails_whitelist' => ['type' => self::TYPE_MULTILINE, 'default' => ''],
            'AllowedOrBlockCountries' => ['type' => self::TYPE_SELECT, 'default' => 'block', 'dashboard' => true, 'pro' => true],
            'country_blacklist' => ['type' => self::TYPE_MULTILINE, 'default' => '', 'dashboard' => true, 'pro' => true],
            'lang_needed' => ['type' => self::TYPE_MULTILINE, 'default' => '', 'dashboard' => true, 'pro' => true],
            'lang_forbidden' => ['type' => self::TYPE_MULTILINE, 'default' => '', 'dashboard' => true, 'pro' => true],

            // Reputation APIs.
            'abuseipdb_api' => ['type' => self::TYPE_TEXT, 'default' => '', 'dashboard' => true],
            'abuseipdb_score' => ['type' => self::TYPE_INT, 'default' => '', 'dashboard' => true],
            'proxycheck_io_api' => ['type' => self::TYPE_TEXT, 'default' => '', 'dashboard' => true],
            'proxycheck_io_risk' => ['type' => self::TYPE_INT, 'default' => '', 'dashboard' => true],

            // Matrix / AI.
            'maspik_ai_enabled' => ['type' => self::TYPE_BOOL, 'default' => '1', 'dashboard' => true],
            'maspik_ai_context' => ['type' => self::TYPE_TEXT, 'default' => ''],
            'maspik_matrix_api_mode' => ['type' => self::TYPE_SELECT, 'default' => '2'],
            // "Not Now" on the full-protection invitation. Stored here rather
            // than in wp_options so the admin app can dismiss it through the
            // settings endpoint it already uses, instead of needing a route of
            // its own. See Admin\FullModeNudge.
            'maspik_full_protection_nudge_dismissed' => ['type' => self::TYPE_BOOL, 'default' => ''],

            // Logging & misc. maspik_Store_log: 'none' | 'blocked' | 'all'
            // (legacy 'no' => none, 'yes'/'' => blocked; see Settings::logMode()).
            'maspik_Store_log' => ['type' => self::TYPE_SELECT, 'default' => 'blocked'],
            'spam_log_limit' => ['type' => self::TYPE_INT, 'default' => '1000'],
            'spam_log_max_age_days' => ['type' => self::TYPE_INT, 'default' => ''],
            'error_message' => ['type' => self::TYPE_TEXT, 'default' => ''],
            'maspik_delete_data_on_uninstall' => ['type' => self::TYPE_SELECT, 'default' => 'no'],
            // Community data-sharing consent — v2 stored it under 'shere_data',
            // carried over via the legacy fallback so existing opt-ins persist.
            'maspik_share_data' => ['type' => self::TYPE_BOOL, 'default' => '', 'legacy' => 'shere_data'],
            // Central Dashboard connection (syncs blacklists across sites).
            // v2 stored the same id under 'private_file_id'; the legacy fallback
            // keeps an existing connection alive across an in-place update.
            'maspik_dashboard_id' => ['type' => self::TYPE_TEXT, 'default' => '', 'legacy' => 'private_file_id'],
            // Opt-in to the shared "popular spam" rule list appended to the
            // dashboard pull (v2: 'popular_spam').
            'maspik_popular_spam' => ['type' => self::TYPE_BOOL, 'default' => '', 'legacy' => 'popular_spam'],

            // WooCommerce checkout (Pro, opt-in). The adapter only runs for the
            // gateways selected here (and optionally zero-total orders), so
            // nothing is checked until the user deliberately configures it.
            'maspik_woo_orders_gateways_to_check' => ['type' => self::TYPE_MULTILINE, 'default' => ''],
            'maspik_woo_orders_check_zero_total' => ['type' => self::TYPE_BOOL, 'default' => ''],
            'maspik_woo_orders_error_message' => ['type' => self::TYPE_TEXT, 'default' => ''],
        ];
    }

    public static function defaultOf(string $key): string
    {
        return self::options()[$key]['default'] ?? '';
    }

    /** The old option name this key replaces, if any (see 'legacy' above). */
    public static function legacyKeyOf(string $key): ?string
    {
        return self::options()[$key]['legacy'] ?? null;
    }

    /**
     * Reverse lookup: the current key a legacy name was renamed to, if any.
     * Used to remap old export/import files onto today's key names.
     */
    public static function currentKeyOf(string $legacyKey): ?string
    {
        foreach (self::options() as $key => $definition) {
            if (($definition['legacy'] ?? null) === $legacyKey) {
                return $key;
            }
        }

        return null;
    }
}
