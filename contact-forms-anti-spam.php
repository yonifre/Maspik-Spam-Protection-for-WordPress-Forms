<?php
/**
 * Plugin Name:       Spam Protection | Maspik
 * Plugin URI:        https://wpmaspik.com
 * Description:       Blocks spam the moment you activate it. No CAPTCHA, no setup, no API key. Multi-Layer. Just install and forget.
 * Version:           3.1.0
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

define( 'MASPIK_VERSION', '3.1.0' );
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
require_once __DIR__ . '/api.php';

register_activation_hook( __FILE__, [ Maspik\Kernel\Activation::class, 'activate' ] );
register_deactivation_hook( __FILE__, [ Maspik\Kernel\Activation::class, 'deactivate' ] );

add_action( 'init', static function () {
    load_plugin_textdomain( 'contact-forms-anti-spam', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
} );

add_action( 'plugins_loaded', static function () {
    Maspik\Kernel\Plugin::boot();
}, 5 );
