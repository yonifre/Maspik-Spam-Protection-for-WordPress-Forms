<?php
// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * WooCommerce Checkout (Orders) spam check – Pro only, off by default.
 *
 * Uses a whitelist: spam check runs only for payment gateways (and optionally
 * zero-total orders) that the user has selected in settings. Nothing is checked
 * unless explicitly chosen (safe default).
 *
 * Debug: in wp-config.php add define( 'MASPIK_WOO_DEBUG', true ); then check the log file inside the active (child) theme folder: maspik-woo-debug.log
 *
 * @package Maspik
 */

if ( ! function_exists( '_maspik_woo_errlog' ) ) {
    function _maspik_woo_errlog( $msg ) {
        if ( defined( 'MASPIK_WOO_DEBUG' ) && MASPIK_WOO_DEBUG && function_exists( 'error_log' ) ) {
            @error_log( $msg );
        }
    }
}

function maspik_woo_log( $message, $context = array() ) {
    if ( ! defined( 'MASPIK_WOO_DEBUG' ) || ! MASPIK_WOO_DEBUG ) {
        return;
    }
    $line = '[' . gmdate( 'Y-m-d H:i:s' ) . '] [Maspik Woo] ' . $message;
    if ( ! empty( $context ) ) {
        $line .= ' | ' . wp_json_encode( $context );
    }
    $line .= "\n";
    $dir = function_exists( 'get_stylesheet_directory' ) ? get_stylesheet_directory() : ( defined( 'WP_CONTENT_DIR' ) ? WP_CONTENT_DIR . '/themes' : '' );
    if ( $dir !== '' ) {
        $file = $dir . '/maspik-woo-debug.log';
        @file_put_contents( $file, $line, FILE_APPEND | LOCK_EX );
    }
}

/**
 * Get the list of payment gateway IDs that should be checked for spam (user whitelist).
 *
 * @return array
 */
function maspik_woo_orders_gateways_to_check() {
    $gateways = maspik_get_settings( 'maspik_woo_orders_gateways_to_check' );
    if ( is_array( $gateways ) ) {
        $list = array_values( array_filter( array_map( 'trim', $gateways ) ) );
    } else {
        $list = array();
        if ( is_string( $gateways ) ) {
            $decoded = json_decode( $gateways, true );
            if ( is_array( $decoded ) ) {
                $list = array_values( array_filter( array_map( 'trim', $decoded ) ) );
            }
        }
    }
    return $list;
}

/**
 * Check if we should run the spam check for this checkout (whitelist: only when chosen by user).
 * 
 * Run spam check if EITHER:
 * 1. Order total is zero AND zero-total toggle is enabled, OR
 * 2. Selected payment method is in the gateways-to-check list.
 * 
 * This means: zero-total does not bypass gateway list, and gateway selection triggers check even when total is 0.
 *
 * @param float  $order_total    Cart total.
 * @param string $payment_method Payment method ID.
 * @return bool
 */
function maspik_woo_orders_should_run_check( $order_total, $payment_method ) {
    $check_zero = maspik_get_settings( 'maspik_woo_orders_check_zero_total', 'form-toggle' );
    $check_zero_total = ( $check_zero === 'yes' || $check_zero === 1 || $check_zero === true );

    $gateways_to_check = maspik_woo_orders_gateways_to_check();
    $gateway_selected  = in_array( $payment_method, $gateways_to_check, true );

    $is_zero_total = (float) $order_total <= 0;

    return ( $is_zero_total && $check_zero_total ) || $gateway_selected;
}

/**
 * Validate WooCommerce checkout for spam when option is enabled (Pro only).
 * Only runs for gateways (and optionally zero-total) selected by the user (whitelist).
 * 
 * IMPORTANT: This validation runs BEFORE account creation during checkout.
 * If spam is detected here, the checkout is blocked and no account will be created.
 * This prevents orphaned user accounts from being created when spam is detected.
 * 
 * The woocommerce_registration_errors hook also validates account creation separately,
 * providing a second layer of protection if account creation happens despite this check.
 *
 * @param array    $data   Checkout posted data.
 * @param WP_Error $errors Checkout errors object.
 */
function maspik_woo_checkout_validate_spam( $data, $errors ) {
    maspik_woo_log( 'validate_spam called', array( 'data_keys' => is_array( $data ) ? array_keys( $data ) : 'not_array' ) );

    if ( ! cfes_is_supporting( 'plugin' ) ) {
        maspik_woo_log( 'skip: not Pro' );
        return;
    }
    if ( maspik_get_settings( 'maspik_support_woocommerce_orders', 'form-toggle' ) !== 'yes' ) {
        maspik_woo_log( 'skip: WooCommerce Orders option is off' );
        return;
    }
    if ( ! class_exists( 'WooCommerce' ) || ! function_exists( 'WC' ) || ! WC()->cart ) {
        maspik_woo_log( 'skip: WooCommerce or cart not available' );
        return;
    }


    $posted         = is_array( $data ) ? $data : ( isset( $_POST ) && is_array( $_POST ) ? $_POST : array() );
    $payment_method = isset( $posted['payment_method'] ) && is_string( $posted['payment_method'] ) ? sanitize_text_field( $posted['payment_method'] ) : '';
    $order_total    = 0.0;

    try {
        $total_raw   = WC()->cart->get_total( 'raw' );
        $order_total = is_numeric( $total_raw ) ? floatval( $total_raw ) : 0.0;
    } catch ( Exception $e ) {
        $order_total = 0.0;
    }

    maspik_woo_log( 'payment_method & total', array( 'payment_method' => $payment_method, 'order_total' => $order_total ) );

    if ( ! maspik_woo_orders_should_run_check( $order_total, $payment_method ) ) {
        maspik_woo_log( 'skip: should_run_check=false (neither zero-total enabled nor gateway in whitelist)', array( 'payment_method' => $payment_method, 'order_total' => $order_total ) );
        return;
    }

    $gateways = maspik_woo_orders_gateways_to_check();
    $check_zero = maspik_get_settings( 'maspik_woo_orders_check_zero_total', 'form-toggle' );
    $check_zero_total = ( $check_zero === 'yes' || $check_zero === 1 || $check_zero === true );
    maspik_woo_log( 'running checks', array( 'gateways_count' => count( $gateways ), 'check_zero_total' => $check_zero_total ) );

    $ip     = maspik_get_real_ip();
    $spam   = false;
    $reason = '';
    $post   = $posted;

    // WooCommerce get_posted_data() only includes registered checkout fields; maspik_spam_key and honeypot are not there. Merge from $_POST so GeneralCheck (spam key + honeypot) sees them.
    if ( isset( $_POST['maspik_spam_key'] ) ) {
        $post['maspik_spam_key'] = sanitize_text_field( wp_unslash( $_POST['maspik_spam_key'] ) );
    }
    if ( isset( $_POST['full-name-maspik-hp'] ) ) {
        $post['full-name-maspik-hp'] = sanitize_text_field( wp_unslash( $_POST['full-name-maspik-hp'] ) );
    }

    // Content fields for AI: only billing/shipping/order_comments (text, email, phone), exclude payment/nonce/hidden.
    $content_fields = array();
    $checkout_content_keys = array(
        'billing_first_name', 'billing_last_name', 'billing_company', 'billing_address_1', 'billing_address_2',
        'billing_city', 'billing_state', 'billing_postcode', 'billing_email', 'billing_phone',
        'shipping_first_name', 'shipping_last_name', 'shipping_company', 'shipping_address_1', 'shipping_address_2',
        'shipping_city', 'shipping_state', 'order_comments',
    );
    foreach ( $checkout_content_keys as $key ) {
        if ( isset( $post[ $key ] ) && is_string( $post[ $key ] ) && trim( $post[ $key ] ) !== '' ) {
            $content_fields[ $key ] = sanitize_text_field( $post[ $key ] );
        }
    }

    $GeneralCheck = GeneralCheck( $ip, $spam, $reason, $post, 'woocommerce_checkout', $content_fields );
    $spam    = isset( $GeneralCheck['spam'] ) ? (bool) $GeneralCheck['spam'] : false;
    $reason  = isset( $GeneralCheck['reason'] ) ? $GeneralCheck['reason'] : '';
    $message = isset( $GeneralCheck['message'] ) ? $GeneralCheck['message'] : '';
    $spam_val = isset( $GeneralCheck['value'] ) ? $GeneralCheck['value'] : '';
    $type    = isset( $GeneralCheck['type'] ) ? $GeneralCheck['type'] : 'General';

    maspik_woo_log( 'GeneralCheck result', array( 'spam' => $spam, 'type' => $type, 'reason' => $reason ) );

    if ( $spam ) {
        maspik_woo_log( 'BLOCKED by GeneralCheck', array( 'reason' => $reason ) );
        maspik_woo_orders_add_spam_error( $errors, $message, $type, $reason, $post, $spam_val );
        return;
    }

    $billing_first   = isset( $posted['billing_first_name'] ) ? sanitize_text_field( is_string( $posted['billing_first_name'] ) ? $posted['billing_first_name'] : '' ) : '';
    $billing_last    = isset( $posted['billing_last_name'] ) ? sanitize_text_field( is_string( $posted['billing_last_name'] ) ? $posted['billing_last_name'] : '' ) : '';
    $billing_company = isset( $posted['billing_company'] ) ? sanitize_text_field( is_string( $posted['billing_company'] ) ? $posted['billing_company'] : '' ) : '';
    $billing_name_combined = trim( implode( ' ', array_filter( array( $billing_first, $billing_last, $billing_company ), 'strlen' ) ) );
    $billing_email   = isset( $posted['billing_email'] ) ? sanitize_email( is_string( $posted['billing_email'] ) ? $posted['billing_email'] : '' ) : '';
    $billing_phone   = isset( $posted['billing_phone'] ) ? sanitize_text_field( is_string( $posted['billing_phone'] ) ? $posted['billing_phone'] : '' ) : '';

    maspik_woo_log( 'billing fields from posted', array(
        'has_billing_first' => $billing_first !== '',
        'has_billing_email' => $billing_email !== '',
        'has_billing_phone' => $billing_phone !== '',
        'name_combined_len' => strlen( $billing_name_combined ),
    ) );

    if ( $billing_name_combined !== '' ) {
        $text_check = validateTextField( $billing_name_combined );
        maspik_woo_log( 'text_check result', array( 'is_array' => is_array( $text_check ), 'has_spam' => is_array( $text_check ) && ! empty( $text_check['spam'] ) ) );
        if ( is_array( $text_check ) && ! empty( $text_check['spam'] ) ) {
            $msg  = isset( $text_check['message'] ) ? $text_check['message'] : 'text_blacklist';
            $val  = isset( $text_check['option_value'] ) ? $text_check['option_value'] : $billing_name_combined;
            $lbl  = isset( $text_check['label'] ) ? $text_check['label'] : 'text_blacklist';
            maspik_woo_log( 'BLOCKED by text blacklist (name)', array( 'reason' => $text_check['spam'] ) );
            efas_add_to_log( 'Text Field', $text_check['spam'], $post, 'Woocommerce checkout', $lbl, $val );
            maspik_woo_orders_add_spam_error( $errors, $msg, 'Text Field', $text_check['spam'], $post, $val );
            return;
        }
    }

    if ( $billing_email !== '' ) {
        $email_spam = checkEmailForSpam( $billing_email );
        maspik_woo_log( 'email_check result', array( 'email_spam' => (bool) $email_spam ) );
        if ( $email_spam ) {
            maspik_woo_log( 'BLOCKED by email blacklist', array( 'reason' => $email_spam ) );
            efas_add_to_log( 'Email', $email_spam, $post, 'Woocommerce checkout', 'emails_blacklist', $billing_email );
            maspik_woo_orders_add_spam_error( $errors, 'emails_blacklist', 'Email', $email_spam, $post, $billing_email );
            return;
        }
    }

    if ( $billing_phone !== '' ) {
        $tel_check = checkTelForSpam( $billing_phone );
        if ( ! is_array( $tel_check ) ) {
            $tel_check = array( 'valid' => true );
        }
        $tel_valid = isset( $tel_check['valid'] ) ? (bool) $tel_check['valid'] : true;
        maspik_woo_log( 'tel_check result', array( 'tel_valid' => $tel_valid ) );
        if ( ! $tel_valid ) {
            maspik_woo_log( 'BLOCKED by phone (whitelist/format)', array( 'reason' => isset( $tel_check['reason'] ) ? $tel_check['reason'] : '' ) );
            $reason_tel = isset( $tel_check['reason'] ) ? $tel_check['reason'] : '';
            $msg_tel   = isset( $tel_check['message'] ) ? $tel_check['message'] : 'tel_formats';
            $lbl_tel   = isset( $tel_check['label'] ) ? $tel_check['label'] : 'tel_formats';
            $val_tel   = isset( $tel_check['option_value'] ) ? $tel_check['option_value'] : $billing_phone;
            efas_add_to_log( 'Phone', $reason_tel, $post, 'Woocommerce checkout', $lbl_tel, $val_tel );
            maspik_woo_orders_add_spam_error( $errors, $msg_tel, 'Phone', $reason_tel, $post, $val_tel );
            return;
        }
    }

    maspik_woo_log( 'all checks passed – checkout allowed' );
}

/**
 * Add spam error to checkout and optionally log (shared for GeneralCheck and field checks).
 *
 * @param WP_Error $errors       Checkout errors object.
 * @param string   $message_key  Message key for cfas_get_error_text (e.g. 'text_blacklist', 'emails_blacklist', 'tel_formats').
 * @param string   $type         Log type (e.g. 'General', 'Text Field', 'Email', 'Phone').
 * @param string   $reason       Reason for log.
 * @param array    $post         Posted data.
 * @param mixed    $spam_val     Value that triggered the block.
 */
function maspik_woo_orders_add_spam_error( $errors, $message_key, $type, $reason, $post, $spam_val = '' ) {
    $custom_msg = maspik_get_settings( 'maspik_woo_orders_error_message' );
    $error_message = is_string( $custom_msg ) && trim( $custom_msg ) !== '' ? trim( $custom_msg ) : cfas_get_error_text( $message_key );
    // Note: Logs are created before calling this function (in the calling code), so we don't duplicate logging here
    $errors->add( 'maspik_spam', $error_message );
}

add_action( 'woocommerce_after_checkout_validation', 'maspik_woo_checkout_validate_spam', 10, 2 );
