<?php
/**
 * Uninstall handler. Data is only removed when the user explicitly opted in
 * (Advanced → Danger zone → "Delete all data on uninstall"), matching v2 behavior.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

if ( get_option( 'maspik_delete_data_on_uninstall' ) !== 'yes' ) {
    return;
}

global $wpdb;
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}maspik_spam_logs" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}maspik_options" );

foreach ( $wpdb->get_col( $wpdb->prepare(
    "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
    $wpdb->esc_like( 'maspik_' ) . '%'
) ) as $option ) {
    delete_option( $option );
}
// Two v2 leftovers that the maspik_ prefix above does not match. The user
// asked for all data to go, and a row that survives an uninstall also makes a
// later fresh install look like an upgrade from v2 to Upgrade::cameFromV2().
delete_option( 'spamapi' );
delete_option( 'spamcounter' );
delete_option( 'shere_data' );
