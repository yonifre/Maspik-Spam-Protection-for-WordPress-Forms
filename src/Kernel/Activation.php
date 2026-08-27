<?php

declare(strict_types=1);

namespace Maspik\Kernel;

/**
 * Activation / upgrade routines. Table definitions are byte-compatible with
 * v2.9.x — see docs/04-migration.md: we keep both tables as-is.
 */
final class Activation
{
    public static function activate(): void
    {
        self::createTables();
        update_option('maspik_plugin_version', MASPIK_VERSION);

        // Keep Pro working across a deactivate/activate: reschedule the license
        // recheck and revalidate the stored token (no new activation seat used).
        (new \Maspik\Premium\License())->resume();
    }

    public static function deactivate(): void
    {
        // Never destroy data on deactivation — only tear down scheduled work.
        wp_clear_scheduled_hook(\Maspik\Admin\Rest\DashboardController::CRON_HOOK);
        wp_clear_scheduled_hook(\Maspik\Premium\License::CRON_HOOK);
        wp_clear_scheduled_hook('maspik_log_prune');
    }

    public static function createTables(): void
    {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset = $wpdb->get_charset_collate();

        // Same schema as v2.9.x create_maspik_log_table().
        dbDelta("CREATE TABLE {$wpdb->prefix}maspik_spam_logs (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            spam_type varchar(191) NOT NULL,
            spam_value varchar(191) NOT NULL,
            spam_detail longtext NOT NULL,
            spam_ip varchar(191) NOT NULL,
            spam_country varchar(191) NOT NULL,
            spam_agent varchar(191) NOT NULL,
            spam_date varchar(191) NOT NULL,
            spam_source varchar(191) NOT NULL,
            spamsrc_label varchar(191) NOT NULL DEFAULT '',
            spamsrc_val varchar(191) NOT NULL DEFAULT '',
            spam_tag varchar(191) NOT NULL DEFAULT '',
            PRIMARY KEY  (id)
        ) $charset");

        // Same schema as v2.9.x create_maspik_table().
        dbDelta("CREATE TABLE {$wpdb->prefix}maspik_options (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            option_name varchar(191) NOT NULL,
            option_value longtext NOT NULL,
            PRIMARY KEY  (id)
        ) $charset");
    }
}
