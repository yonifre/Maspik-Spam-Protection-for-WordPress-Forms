<?php
// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}
// Buddypress

/**
 * Whitelist-only subset of BuddyPress signup POST for Matrix/AI (no hidden/internal keys can slip in).
 *
 * Allowed: core signup keys, multisite blog fields if present, and xProfile field_{id} keys only.
 *
 * @param array $post Typically $_POST.
 * @return array
 */
function maspik_buddypress_registration_fields_for_ai( $post ) {
    if ( ! is_array( $post ) ) {
        return array();
    }
    $post = wp_unslash( $post );

    $allow_exact = array(
        'signup_username',
        'signup_email',
        'signup_blog_url',
        'signup_blog_title',
    );
    /**
     * Exact POST keys to send to Matrix/AI for BuddyPress registration (whitelist).
     *
     * @param array $allow_exact Key order not significant.
     */
    $allow_exact = apply_filters( 'maspik_buddypress_registration_ai_whitelist_keys', $allow_exact );
    $allow_exact = array_unique( array_map( 'strval', (array) $allow_exact ) );

    $out = array();
    foreach ( $allow_exact as $key ) {
        if ( isset( $post[ $key ] ) ) {
            $out[ $key ] = $post[ $key ];
        }
    }

    foreach ( $post as $key => $value ) {
        $key = (string) $key;
        if ( preg_match( '/^field_\d+$/', $key ) ) {
            $out[ $key ] = $value;
        }
    }

    return $out;
}

/**
 * Check BuddyPress registration form for spam
 */
function maspik_check_bp_registration_form() {
    $error_message   = cfas_get_error_text();
    $spam            = false;
    $reason          = '';
    $message         = '';
    $spam_lbl        = '';
    $spam_val        = '';
  //  $user_email      = isset($_POST['signup_email']) ? sanitize_email($_POST['signup_email']) : '';
    $ip = maspik_get_real_ip();
    global $bp;

    $user_email = sanitize_email($bp->signup->email);
    $user_login = sanitize_text_field($bp->signup->username);

    // General Check (full $_POST for honeypot/time checks; trimmed map for Matrix/AI — same pattern as other forms)
    if (!$spam) {
        $bp_content_fields = maspik_buddypress_registration_fields_for_ai( $_POST );
        $GeneralCheck = GeneralCheck($ip, $spam, $reason, $_POST, "buddypress_registration", $bp_content_fields);
        $spam         = $GeneralCheck['spam'] ?? false;
        $reason       = $GeneralCheck['reason'] ?? '';
        $message      = $GeneralCheck['message'] ?? '';
        $spam_val     = $GeneralCheck['value'] ?? '';
        $spam_lbl     = $GeneralCheck['reason'] ?? '';
        $type         = $GeneralCheck['type'] ?? 'General';
    }

    // Email check
    if ($user_email && !$spam) {
        $spam = checkEmailForSpam($user_email);
        if ($spam && !$reason) {
            $reason     = $spam;
            $spam_lbl   = 'emails_blacklist';
            $spam_val   = $user_email;
            $type       = "Email";
        }
    }

    if ($user_login && !$spam) {
        $validateTextField = validateTextField( $user_login );
        $spam  = $reason = isset( $validateTextField['spam'] ) ? $validateTextField['spam'] : false;
        $message = isset( $validateTextField['message'] ) ? $validateTextField['message'] : '';
        $spam_lbl = isset( $validateTextField['label'] ) ? $validateTextField['label'] : '';
        $spam_val = isset( $validateTextField['option_value'] ) ? $validateTextField['option_value'] : '';
        $type = "Username";
    }

    // Log and display error if spam is detected
    if ($spam) {  
        efas_add_to_log("$type", $reason, $_POST, 'BuddyPress registration', $spam_lbl, $spam_val);
        $error_message = cfas_get_error_text($message);
//        bp_core_add_message($error_message, 'error');
        if ($type == "Email") {
            $bp->signup->errors['signup_email'] = $error_message;
        } else {
            $bp->signup->errors['signup_username'] = $error_message;
        }
        return;
    }
}
add_action('bp_signup_validate', 'maspik_check_bp_registration_form');

/**
 * Add honeypot field to the BuddyPress registration form
 */
function maspik_add_honeypot_to_bp_registration_form() {
    
    if (efas_get_spam_api('maspikHoneypot', 'bool')) {

        echo '<div class="register-section maspik-field" id="maspik-honeypot-section" style="display: none;">
        <label for="full-name-maspik-hp">' . esc_html( maspik_honeypot_aria_label() ) . '</label>
            <input type="text" name="full-name-maspik-hp" id="full-name-maspik-hp" value="" tabindex="-1" autocomplete="off" aria-label="' . esc_attr( maspik_honeypot_aria_label() ) . '" />
        </div>';
    }

    if ( maspik_get_settings( 'maspikYearCheck' ) ) {
        echo '<div class="register-section maspik-field" style="display: none;">
            <label for="Maspik-currentYear" class="bp-form-control-label">' . esc_html( maspik_honeypot_aria_label() ) . '</label>
            <input size="1" type="text" autocomplete="off" aria-hidden="true" tabindex="-1" aria-label="' . esc_attr( maspik_honeypot_aria_label() ) . '" name="Maspik-currentYear" id="Maspik-currentYear" class="buddypress-form-control" placeholder="">
        </div>';
    }


}
add_action('bp_before_registration_submit_buttons', 'maspik_add_honeypot_to_bp_registration_form', 9999);