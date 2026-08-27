<?php

declare(strict_types=1);

namespace Maspik\Kernel;

use Maspik\Admin\FullModeNudge;
use Maspik\Domain\Check\LanguageCheck;
use Maspik\Infrastructure\Geo\FreeIpApiResolver;
use Maspik\Infrastructure\Logging\LogRepository;
use Maspik\Infrastructure\Settings\DashboardRules;
use Maspik\Infrastructure\Settings\Settings;
use Maspik\Premium\License;

/**
 * Idempotent upgrade routine (docs/04 § 3). Runs once per version change on
 * admin_init — for the common case where the plugin files are replaced by a
 * wp.org update without a fresh activation hook.
 *
 * The big migration win is that settings already carry over untouched: 3.0
 * reads the same wp_maspik_options table with the same keys v2 wrote. What this
 * routine does is: ensure the tables exist, carry over the few scattered
 * wp_options v2 used under different names, and stamp the version.
 */
final class Upgrade
{
    private const VERSION_OPTION = 'maspik_plugin_version';
    private const LOCK = 'maspik_upgrade_running';
    private const DEPTH_REPAIRED = 'maspik_matrix_depth_repaired';
    private const SHARE_REPAIRED = 'maspik_share_data_repaired';
    private const CUSTOM_FORM_REPAIRED = 'maspik_custom_form_optin_repaired';
    private const LANGUAGE_LISTS_REPAIRED = 'maspik_language_lists_repaired';

    public static function maybeRun(): void
    {
        // Runs on its own marker, not the version stamp: sites updated by a
        // build that already carried the bug are stamped 3.0.0 and would never
        // see a version-gated repair.
        //
        // Both markers are autoloaded, unlike the one-shot markers inside run().
        // These two are read before the version gate, so they are read on every
        // request the site ever serves; left out of alloptions that is two extra
        // queries per page view forever, which is precisely the cost the version
        // check below is written to avoid.
        self::repairInputGateDepth();
        self::repairShareDataOptIn();
        self::repairCustomFormOptIn();
        self::repairLanguageLists();

        $stored = (string) get_option(self::VERSION_OPTION, '');
        if ($stored === MASPIK_VERSION) {
            // The overwhelmingly common path, on every request: one read of an
            // autoloaded option, already in the options cache. No query.
            return;
        }

        // Now that this runs on the front end, several visitors can arrive at
        // once on the first request after an update. Every step below is
        // idempotent, but there is no reason to let them all do the work; the
        // losers simply skip and pick up the migrated state on their next hit.
        if (! add_option(self::LOCK, time(), '', false)) {
            return;
        }

        try {
            self::run($stored);

            update_option(self::VERSION_OPTION, MASPIK_VERSION);
            update_option('maspik_db_version', '3.0');
        } finally {
            // Released even if a step throws, so a single failure cannot wedge
            // the site on unmigrated state forever.
            delete_option(self::LOCK);
        }
    }

    /**
     * Keep the InputGate depth an upgrading site was already running.
     *
     * Both versions store the depth in `maspik_matrix_api_mode`, but they
     * resolve an unset or unrecognised value in opposite directions: v2 fell
     * back to 4 (full pipeline), 3.0 falls back to 2 (IP only). A v2 site that
     * never explicitly picked a depth was therefore running full analysis, and
     * updating silently dropped it to IP-only — an update quietly reducing
     * protection, which is worse than any single missed setting.
     *
     * Only sites that actually ran v2 are touched, identified by options only
     * v2 ever wrote. A fresh 3.0 install keeps the privacy-first default it was
     * deliberately given.
     */
    private static function repairInputGateDepth(): void
    {
        if (get_option(self::DEPTH_REPAIRED)) {
            return;
        }

        if (self::cameFromV2()) {
            $settings = new Settings();
            $stored = trim($settings->raw('maspik_matrix_api_mode'));
            // '' and anything unrecognised meant "full" under v2, and so did
            // the retired '3' (IP + banned words), which 3.0 already treats
            // as full. Normalising it here keeps the settings UI coherent too.
            if (! in_array($stored, ['2', '4'], true)) {
                $settings->save('maspik_matrix_api_mode', '4');
            }
        }

        update_option(self::DEPTH_REPAIRED, '1');
    }

    /**
     * Carry over the v2 "share anonymous data" opt-in.
     *
     * v2 kept this answer in two different places and treated the feature as on
     * if *either* said so (class-maspik.php):
     *
     *     maspik_get_settings("shere_data", '', 'old')   // the wp_options row
     *     || maspik_get_settings("shere_data")           // the settings table
     *
     * Sites that opted in through the older admin notice ended up with 'yes' in
     * wp_options while the settings table still held '0' from a later form save.
     * v2 read that as on. Copying only the settings table therefore turned the
     * opt-in off during the upgrade - the site had said yes and we silently
     * changed the answer to no.
     *
     * Runs on its own marker rather than the version stamp, so installs already
     * updated by a build that had the bug are repaired too.
     */
    private static function repairShareDataOptIn(): void
    {
        if (get_option(self::SHARE_REPAIRED)) {
            return;
        }

        $settings = new Settings();

        // Never override a decision made in 3.0 itself.
        if (! $settings->bool('maspik_share_data')) {
            $fromOption = get_option('shere_data', '');
            $fromOption = is_scalar($fromOption) ? strtolower(trim((string) $fromOption)) : '';
            $fromTable = strtolower(trim($settings->raw('shere_data')));

            if (self::wasSharingOn($fromOption, $fromTable)) {
                $settings->save('maspik_share_data', '1');
            }
        }

        update_option(self::SHARE_REPAIRED, '1');
    }

    /**
     * v2's own rule, isolated so it can be tested: on if *either* store says so.
     *
     * The accepted vocabulary is deliberately wide. Across v2's lifetime this
     * value was written as 1 (the opt-in notice), 'yes' (the settings form) and
     * as an integer by direct update_option calls, so narrowing this list would
     * silently opt sites back out - which is the bug it exists to prevent.
     */
    public static function wasSharingOn(string $fromOption, string $fromTable): bool
    {
        $on = ['1', 'yes', 'on', 'true'];

        return in_array($fromOption, $on, true) || in_array($fromTable, $on, true);
    }

    /**
     * Whether this install ever ran v2, identified by options only v2 wrote.
     *
     * The repairs above carry v2 defaults forward, and each one must leave a
     * genuinely fresh 3.0 install alone — otherwise a new site inherits
     * decisions made for a plugin it never had.
     */
    private static function cameFromV2(): bool
    {
        return get_option('spamcounter', null) !== null
            || get_option('spamapi', null) !== null
            || get_option('maspik_dlm_license', null) !== null;
    }

    /**
     * Keep custom-PHP-form protection on for sites that were already using it.
     *
     * 3.0 makes this integration opt-in: it is not a plugin we can detect, it is
     * a filter a developer calls from their own code, so leaving it on by
     * default puts a listener on every site that will never use it.
     *
     * v2 read the toggle as `maspik_support_custom_forms != "no"` — an unset
     * value meant ON. Reading that same unset value as "off" would quietly stop
     * protecting sites whose forms depend on the filter, and nothing in the
     * admin would explain why spam started arriving. So a site that came from v2
     * has its answer written down explicitly instead of inferred.
     */
    private static function repairCustomFormOptIn(): void
    {
        if (get_option(self::CUSTOM_FORM_REPAIRED)) {
            return;
        }

        if (self::cameFromV2()) {
            $settings = new Settings();
            if (self::customFormWasOn($settings->raw('maspik_support_custom_forms'))) {
                $settings->save('maspik_support_custom_forms', 'yes');
            }
        }

        update_option(self::CUSTOM_FORM_REPAIRED, '1');
    }

    /**
     * v2's own rule, isolated so it can be tested: custom-form protection was on
     * unless the toggle literally said "no".
     *
     *     maspik_get_settings('maspik_support_custom_forms') != "no"
     *
     * The unset case is the one that matters — it is what almost every v2 site
     * has, and it meant ON.
     */
    public static function customFormWasOn(string $stored): bool
    {
        return strtolower(trim($stored)) !== 'no';
    }

    /**
     * Split a v2-era required/forbidden language value back into one script
     * per line, so the stored value matches what 3.0 writes and reads.
     *
     * LanguageCheck::normalizedList() already recovers the correct patterns at
     * request time regardless of the stored format — a v2 site is fully
     * protected again the moment this build is active, with no dependency on
     * this repair having run. This repair exists for the value itself: left
     * as one line, the Language screen in Protection shows it as a single
     * unrecognized chip (the raw "\p{Hebrew} [A-Za-z]") instead of two
     * recognized ones, which reads as broken even though nothing is. Rewriting
     * it once, to what the 3.0 UI would have saved directly, fixes that
     * without the admin needing to touch anything.
     *
     * Not gated on cameFromV2(): the operation is a no-op for any value
     * normalizedList() would return unchanged (every fresh 3.0 install, and
     * every site that already saved this setting through the 3.0 UI), so
     * there is nothing v2-specific to guard against here.
     */
    private static function repairLanguageLists(): void
    {
        if (get_option(self::LANGUAGE_LISTS_REPAIRED)) {
            return;
        }

        $settings = new Settings();
        foreach (['lang_needed', 'lang_forbidden'] as $key) {
            $stored = $settings->list($key);
            $recovered = LanguageCheck::normalizedList($stored);
            if ($recovered !== $stored) {
                $settings->save($key, implode("\n", $recovered));
            }
        }

        update_option(self::LANGUAGE_LISTS_REPAIRED, '1');
    }

    private static function run(string $fromVersion): void
    {
        // 1. Tables may not exist on an in-place update (no activation hook fired).
        Activation::createTables();

        // 2. Carry the lifetime spam counter from v2's 'spamcounter' option into
        //    3.0's 'maspik_spam_count'.
        //
        //    Guarded by its own marker rather than by "is the new key still
        //    unset?". That older test silently lost the number whenever
        //    anything wrote the new key first: LogRepository seeds it from a
        //    default of 0 on the first block, so the key existed, the test
        //    failed, and a site's lifetime total was reset to 1 for good. The
        //    marker makes the carry-over happen exactly once no matter what
        //    ran before it, and the total is folded in rather than replaced.
        if (! get_option('maspik_spam_count_migrated')) {
            $legacy = (int) get_option('spamcounter', 0);
            if ($legacy > 0) {
                $current = (int) get_option('maspik_spam_count', 0);
                update_option('maspik_spam_count', $current + $legacy, false);
            }
            // Marker last. Written first, a failure between the two lines would
            // leave the site permanently marked as migrated with the count
            // never carried, which is the exact loss this guard exists to stop.
            update_option('maspik_spam_count_migrated', '1', false);
        }

        // 3. v2 stored dashboard rules in `spamapi` as newline strings under the
        //    old ACF field names; 3.0's engine reads them as arrays under the
        //    new Schema keys. Re-normalise in place so synced rules keep working
        //    after an in-place update. Idempotent, so re-running is harmless.
        $spamapi = get_option('spamapi');
        if (is_array($spamapi) && $spamapi !== []) {
            $normalized = DashboardRules::normalize($spamapi);
            if ($normalized !== $spamapi) {
                update_option('spamapi', $normalized);
            }
        }

        // 4. v3 renamed the 'maspikTimeCheck' flag to 'verification_key' (same
        //    on/off semantics, clearer name — the setting itself was never
        //    about elapsed time). Copy the stored value onto the new key so
        //    existing sites need no action; Settings::raw() also falls back to
        //    the legacy key at read time, so this is a convenience, not a
        //    requirement for correctness.
        $settings = new Settings();
        $settings->renameLegacyOption('maspikTimeCheck', 'verification_key');
        // Carry over the v2 community data-sharing opt-in ('shere_data').
        // Community data sharing is carried over by repairShareDataOptIn(),
        // which has to read two stores rather than one; see the note there.
        // Carry over the Dashboard connection: v2 stored the account id under
        // 'private_file_id'. Without this the site silently stops syncing shared
        // rules after an in-place update, so it must survive the upgrade.
        $settings->renameLegacyOption('private_file_id', 'maspik_dashboard_id');
        $settings->renameLegacyOption('popular_spam', 'maspik_popular_spam');

        // v2 asked the same "move to the full check?" question and stored the
        // answer in wp_options. Carry it into the settings key both 3.0
        // surfaces read, so a site that already declined is not asked again
        // just because it upgraded.
        if (get_option(FullModeNudge::LEGACY_DISMISSED_OPTION) && ! $settings->bool(FullModeNudge::DISMISSED_KEY)) {
            $settings->save(FullModeNudge::DISMISSED_KEY, '1');
        }

        // 5. Carry over an existing v2/2.x license activation (the ideologix
        //    dlm-wp-simple-checker SDK's `maspik_dlm_license` array option) so
        //    an already-licensed site doesn't lose Pro on upgrade. No re-entry;
        //    the recheck cron revalidates the carried-over token shortly after.
        (new License())->migrateLegacy();

        // 6. Treat existing log rows as already seen, so a site updating with
        //    thousands of historical entries doesn't open wp-admin to a huge
        //    "new spam" badge for submissions it dealt with long ago.
        (new LogRepository(new Settings(), new FreeIpApiResolver()))->baselineSeen();

        /**
         * Fires after MASPIK migrates to a new version. Third parties (and
         * future in-plugin migrations) can hook version-specific steps.
         *
         * @param string $fromVersion previous stored version ('' on first run)
         * @param string $toVersion   MASPIK_VERSION
         */
        do_action('maspik/upgraded', $fromVersion, MASPIK_VERSION);
    }
}
