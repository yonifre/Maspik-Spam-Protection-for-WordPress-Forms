<?php
/**
 * Plugin Name:       Spam Protection | Maspik
 * Plugin URI:        https://wpmaspik.com
 * Description:       Blocks spam the moment you activate it. No CAPTCHA, no setup, no API key. Multi-Layer. Just install and forget.
 * Version:           3.1.2
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            WpMaspik
 * Author URI:        https://wpmaspik.com
 * License:           GPLv3
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       contact-forms-anti-spam
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'MASPIK_VERSION', '3.1.2' );
define( 'MASPIK_FILE', __FILE__ );
define( 'MASPIK_DIR', __DIR__ );
define( 'MASPIK_URL', plugin_dir_url( __FILE__ ) );

// Composer autoloader (PSR-4: Maspik\ → src/).
$maspik_autoload = __DIR__ . '/vendor/autoload.php';
if ( ! is_readable( $maspik_autoload ) ) {
    add_action( 'admin_notices', static function () {
        echo '<div class="notice notice-error"><p>MASPIK: run <code>composer install</code> (development build).</p></div>';
    } );
    return;
}
require $maspik_autoload;

// Public helpers for custom forms (maspik_check_spam / maspik_is_spam).
//
// Guarded like the autoloader above. A theme calls these directly from its own
// form handler, so when the file is missing the choice is between our fatal and
// theirs: an unguarded require_once takes every page on the site down, and
// leaving the functions undefined moves the same fatal into the theme. The
// fallbacks answer "not spam", which is what the site would get without us.
if ( is_readable( __DIR__ . '/api.php' ) ) {
    require_once __DIR__ . '/api.php';
} else {
    add_action( 'plugins_loaded', static function () {
        maspik_record_boot_failure( new \Error( 'api.php is missing from the plugin directory' ) );
    }, 4 );
    if ( ! function_exists( 'maspik_check_spam' ) ) {
        function maspik_check_spam( $fields, $form_name = 'Custom Form' ) {
            return false;
        }
    }
    if ( ! function_exists( 'maspik_is_spam' ) ) {
        function maspik_is_spam( $fields, $form_name = 'Custom Form' ) {
            return false;
        }
    }
    if ( ! function_exists( 'maspik_spam_message' ) ) {
        function maspik_spam_message( $fields, $form_name = 'Custom Form' ) {
            return '';
        }
    }
}

register_activation_hook( __FILE__, [ Maspik\Kernel\Activation::class, 'activate' ] );
register_deactivation_hook( __FILE__, [ Maspik\Kernel\Activation::class, 'deactivate' ] );

add_action( 'init', static function () {
    load_plugin_textdomain( 'contact-forms-anti-spam', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
} );

/**
 * Record a failure without using a single plugin class.
 *
 * That constraint is the whole point: the failures this exists for are the ones
 * where our classes cannot be loaded at all, so anything that autoloads here
 * would fatal a second time inside the handler. Maspik\Kernel\Guard writes the
 * same option for failures inside hook callbacks; the shape is shared so the
 * notice below can read either without loading anything.
 *
 * @param Throwable $e
 */
function maspik_record_boot_failure( $e ) {
    update_option( 'maspik_failure', array(
        'stage'  => 'boot',
        'detail' => sprintf( '%s: %s (%s:%d)', get_class( $e ), $e->getMessage(), $e->getFile(), $e->getLine() ),
        'time'   => time(),
    ), false );
}

/**
 * Whether a recorded failure means "a file is missing" rather than "Maspik has a
 * bug". The two need opposite advice: reinstalling fixes the first and does
 * nothing for the second, and telling someone to reinstall for a bug sends them
 * round in circles while the notice stays up.
 *
 * @param string $detail
 * @return bool
 */
function maspik_failure_is_missing_file( $detail ) {
    return (bool) preg_match(
        '/\b(Class|Interface|Trait|Enum) "[^"]+" not found|Failed opening|is missing from the plugin directory/',
        (string) $detail
    );
}

// Shown on every admin page for as long as the failure stands - not only on the
// request that hit it. A form submission that failed on the front end is never
// followed by an admin page in the same request, so a notice tied to that
// request would never be seen.
add_action( 'admin_notices', static function () {
    $failure = get_option( 'maspik_failure' );
    if ( ! is_array( $failure ) || empty( $failure['detail'] ) || ! current_user_can( 'activate_plugins' ) ) {
        return;
    }

    $boot = 'boot' === ( $failure['stage'] ?? '' );

    // A runtime failure that has not happened again for a week is over - one
    // odd submission should not leave an alarming notice up indefinitely. Guard
    // refreshes the timestamp at most daily while a failure keeps recurring, so
    // a live problem never ages out. A boot failure never expires this way: it
    // means protection is off entirely, and the next successful boot clears it.
    if ( ! $boot && time() - (int) ( $failure['time'] ?? 0 ) > 7 * DAY_IN_SECONDS ) {
        delete_option( 'maspik_failure' );

        return;
    }

    $lead = $boot
        ? '<strong>' . esc_html__( 'Maspik could not start.', 'contact-forms-anti-spam' ) . '</strong> '
            . esc_html__( 'Spam protection is off on this site.', 'contact-forms-anti-spam' )
        : '<strong>' . esc_html__( 'Part of Maspik is not working.', 'contact-forms-anti-spam' ) . '</strong> '
            . esc_html__( 'Some spam protection may be off; the rest of your site is unaffected.', 'contact-forms-anti-spam' );

    $advice = maspik_failure_is_missing_file( $failure['detail'] )
        ? esc_html__( 'One of its files is missing or damaged, almost always from an update that did not finish. Delete the plugin and install it again from Plugins > Add New. If your host uses a PHP opcode cache, clear it too.', 'contact-forms-anti-spam' )
        : esc_html__( 'This looks like a problem in Maspik itself rather than in your installation, so reinstalling is unlikely to help. Please send the message below to Maspik support at wpmaspik.com.', 'contact-forms-anti-spam' );

    echo '<div class="notice notice-error"><p>' . $lead . ' ' . $advice . '</p><p><code>'
        . esc_html( (string) $failure['detail'] ) . '</code></p></div>';
} );

// A fresh install or an update replaces every file, so whatever was recorded
// described files that no longer exist.
add_action( 'activate_' . plugin_basename( __FILE__ ), static function () {
    delete_option( 'maspik_failure' );
} );
add_action( 'upgrader_process_complete', static function ( $upgrader, $extra = array() ) {
    $plugins = isset( $extra['plugins'] ) ? (array) $extra['plugins'] : array();
    if ( in_array( plugin_basename( __FILE__ ), $plugins, true ) ) {
        delete_option( 'maspik_failure' );
    }
}, 10, 2 );

add_action( 'plugins_loaded', static function () {
    // A spam filter must never be able to take a site down with it.
    //
    // Two support reports arrived for the same shape: a white screen on
    // wp-login, and every REST request on the site failing (which killed the
    // Elementor editor), both from "Class Maspik\...\LogsController not found".
    // The shipped package had the file, the classmap entry and the PSR-4
    // fallback, so the class was missing on those sites and not in the build -
    // a half-finished update, a security plugin quarantining a file, or an
    // opcode cache still serving a previous release's Composer bootstrap.
    //
    // This covers boot itself. Everything boot() registers is wrapped by
    // Maspik\Kernel\Guard, which covers the callbacks that run afterwards.
    try {
        Maspik\Kernel\Plugin::boot();
    } catch ( \Throwable $e ) {
        maspik_record_boot_failure( $e );

        return;
    }

    // Boot succeeding clears a boot failure. A runtime one stays: boot always
    // succeeds on an install where only a form adapter is broken, and clearing
    // it here would hide the notice before anyone could read it.
    $failure = get_option( 'maspik_failure' );
    if ( is_array( $failure ) && 'boot' === ( $failure['stage'] ?? '' ) ) {
        delete_option( 'maspik_failure' );
    }
}, 5 );
