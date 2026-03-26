<?php

/**
 * DEVELOPER HOOK: maspik_disable_wp_comments_spam_check
 * 
 * This filter allows developers to disable spam check for WordPress comments on specific posts.
 * 
 * @param bool $disable   Whether to disable spam check (default: false) (True mean skip spam check)
 * @param int  $post_id   ID of the post where comment is being made
 * @param array $data     Comment data array
 * @return bool True to disable spam check, false to proceed with spam check
 * 
 * USAGE EXAMPLES:
 * 
 * 1. Disable spam check for specific post by ID:
 * add_filter('maspik_disable_wp_comments_spam_check', function($disable, $post_id, $data) {
 *     if ($post_id === 123) {
 *         return true; // Disable spam check for comments on post ID 123
 *     }
 *     return $disable;
 * }, 10, 3);
 * 
 * 2. Disable spam check for multiple posts:
 * add_filter('maspik_disable_wp_comments_spam_check', function($disable, $post_id, $data) {
 *     $excluded_post_ids = [123, 456, 789];
 *     if (in_array($post_id, $excluded_post_ids)) {
 *         return true;
 *     }
 *     return $disable;
 * }, 10, 3);
 * 
 * 3. Disable spam check for logged-in administrators:
 * add_filter('maspik_disable_wp_comments_spam_check', function($disable, $post_id, $data) {
 *     if (is_user_logged_in() && current_user_can('administrator')) {
 *         return true;
 *     }
 *     return $disable;
 * }, 10, 3);
 */

// WP comments and WooCommerce reviews
function maspik_comments_checker(array $data) {
    // Get post ID for developer filtering
    $post_id = isset($data['comment_post_ID']) ? intval($data['comment_post_ID']) : 0;
    
    // Allow developers to disable spam check for specific posts
    $disable_comments_spam_check = apply_filters('maspik_disable_wp_comments_spam_check', false, $post_id, $data);
    if ($disable_comments_spam_check) {
        return $data;
    }

    // Extracting data from the comment with validation
    if ( isset( $data['comment_content'] ) ) {
        $content = strtolower( sanitize_text_field( $data['comment_content'] ) );
    } elseif ( isset( $data['comment'] ) ) {
        $content = strtolower( sanitize_text_field( $data['comment'] ) );
    } else {
        $content = '';
    }
    if ( isset( $data['comment_author_email'] ) ) {
        $email = strtolower( sanitize_email( $data['comment_author_email'] ) );
    } elseif ( isset( $data['email'] ) ) {
        $email = strtolower( sanitize_email( $data['email'] ) );
    } else {
        $email = '';
    }
    if ( isset( $data['comment_author'] ) ) {
        $name = strtolower( sanitize_text_field( $data['comment_author'] ) );
    } elseif ( isset( $data['author'] ) ) {
        $name = strtolower( sanitize_text_field( $data['author'] ) );
    } else {
        $name = '';
    }
    $comment_type = isset($data['comment_type']) ? sanitize_text_field($data['comment_type']) : 'no_type';
    // Review uses WooCommerce toggle; any other comment_type (empty, "comment", custom, etc.) uses WP comments toggle.
    $run = false;
    if ( 'review' === $comment_type ) {
        if ( maspik_get_settings( 'maspik_support_woocommerce_review' ) !== 'no' ) {
            $run = true;
        }
    } elseif ( maspik_get_settings( 'maspik_support_wp_comment' ) !== 'no' ) {
        $run = true;
    }
    if (!$run) {
        return $data;
    }
    if ( current_user_can( 'edit_posts' ) ) {
        // If user can edit posts
        // skip spam check
        return $data;
    }

    $spam = false;
    $ip = maspik_get_real_ip();
    $reason = '';

    $comment_ai_fields = array();
    if ( $name !== '' ) {
        $comment_ai_fields['comment_author'] = $name;
    }
    if ( $email !== '' ) {
        $comment_ai_fields['comment_author_email'] = $email;
    }
    if ( $content !== '' ) {
        $comment_ai_fields['comment_content'] = $content;
    }
    // Country IP + honeypot use full $_POST; Matrix/AI uses whitelisted comment fields only.
    $GeneralCheck = GeneralCheck( $ip, $spam, $reason, $_POST, $comment_type, $comment_ai_fields );
    $spam = $GeneralCheck['spam'] ?? false;
    $reason = $GeneralCheck['reason'] ?? '';
    $message = $GeneralCheck['message'] ?? '';
    $spam_val = $GeneralCheck['value'] ?? '';
    $spam_lbl = $GeneralCheck['message'] ?? '';
    $type = $GeneralCheck['type'] ?? "General";

    // Name check
    if (!empty($name) && !$spam) {
        $validateTextField = validateTextField($name);
        $spam =  $reason = $validateTextField['spam'] ?? false;
        $message = $validateTextField['message'] ?? '';
        $spam_lbl = $validateTextField['label'] ?? '';
        $spam_val = $validateTextField['option_value'] ?? '';
        $type = "Name";
    }

    // Email Spam check
    if (!empty($email) && !$spam) {
        $spam = checkEmailForSpam($email);
        if ($spam) {
            $reason = $spam;
            $spam_lbl = 'emails_blacklist';
            $spam_val = $email;
            $type = "Email";
        }
    }

    // URL check
    $url = isset($data['comment_author_url']) ? strtolower(sanitize_url($data['comment_author_url'])) : '';
    if (!empty($url) && !$spam) {
        $checkUrlForSpam = checkUrlForSpam($url);
        $spam = $reason = $checkUrlForSpam['spam'] ?? false;
        $message = $checkUrlForSpam['message'] ?? '';
        $spam_lbl = $checkUrlForSpam['label'] ?? '';
        $spam_val = $checkUrlForSpam['option_value'] ?? '';
        $type = "URL";
    }

    // Content check
    if (!empty($content) && !$spam) {
        $checkTextareaForSpam = checkTextareaForSpam($content);
        $spam =  $reason = $checkTextareaForSpam['spam'] ?? false;
        $message = $checkTextareaForSpam['message'] ?? '';
        $spam_lbl = $checkTextareaForSpam['label'] ?? '';
        $spam_val = $checkTextareaForSpam['option_value'] ?? '';
        $type = "Content";
    }

    if ($spam) {
        // If identified as spam, handle the action (logging, error message, etc.)
        $error_message = cfas_get_error_text($message);
        $args = ['response' => 200];
        efas_add_to_log("$type", $reason, $data, $comment_type, $spam_lbl, $spam_val);

        wp_die($error_message, "Spam error", $args);
    }

    return $data;
}

add_filter('preprocess_comment', 'maspik_comments_checker');


function add_custom_html_to_comment_form( $submit_button, $args ) {
    if ( efas_get_spam_api('maspikHoneypot', 'bool') || efas_get_spam_api('maspikTimeCheck', 'bool') || maspik_get_settings('maspikYearCheck') ) {
        $custom_html = "";

        if (efas_get_spam_api('maspikHoneypot', 'bool')) {
            $custom_html .= '<div class="comment-form maspik-field" style="display: none;">
                <label for="full-name-maspik-hp" class="comment-form-label">' . esc_html( maspik_honeypot_aria_label() ) . '</label>
                <input size="1" type="text" autocomplete="off" aria-hidden="true" tabindex="-1" aria-label="' . esc_attr( maspik_honeypot_aria_label() ) . '" name="full-name-maspik-hp" id="full-name-maspik-hp" class="comment-form-input" placeholder="' . esc_attr( maspik_honeypot_aria_label() ) . '" data-form-type="other" data-lpignore="true">
            </div>';
        }

        if (maspik_get_settings('maspikYearCheck')) {
            $custom_html .= '<div class="comment-form maspik-field" style="display: none;">
                <label for="Maspik-currentYear" class="comment-form-label">' . esc_html( maspik_honeypot_aria_label() ) . '</label>
                <input size="1" type="text" autocomplete="off" aria-hidden="true" tabindex="-1" aria-label="' . esc_attr( maspik_honeypot_aria_label() ) . '" name="Maspik-currentYear" id="Maspik-currentYear" class="comment-form-input" placeholder="" data-form-type="other" data-lpignore="true">
            </div>';
        }

        $submit_before = $custom_html;
        return $submit_before . $submit_button;
    }
    return $submit_button;
}

add_filter( 'comment_form_submit_button', 'add_custom_html_to_comment_form', 10, 2 );


/**
 * Fields to send to Matrix/AI for wp-login registration (whitelist / normalized keys only).
 *
 * Full $_POST stays available to GeneralCheck for honeypot and spam key; this map is the 6th argument only.
 *
 * Includes common registration/profile keys (first_name, last_name, …) that appear often via core or plugins.
 * Password-related POST keys are never copied.
 *
 * @param array $post Typically $_POST.
 * @return array Normalized key => value (non-empty only).
 */
function maspik_wp_registration_fields_for_ai( $post ) {
    if ( ! is_array( $post ) ) {
        return array();
    }
    $post = wp_unslash( $post );
    $out  = array();

    $login = '';
    if ( isset( $post['user_login'] ) && is_string( $post['user_login'] ) ) {
        $login = sanitize_text_field( trim( $post['user_login'] ) );
    } elseif ( isset( $post['username'] ) && is_string( $post['username'] ) ) {
        $login = sanitize_text_field( trim( $post['username'] ) );
    }
    if ( $login !== '' ) {
        $out['user_login'] = $login;
    }

    $mail = '';
    if ( isset( $post['user_email'] ) && is_string( $post['user_email'] ) ) {
        $mail = sanitize_email( trim( $post['user_email'] ) );
    } elseif ( isset( $post['email'] ) && is_string( $post['email'] ) ) {
        $mail = sanitize_email( trim( $post['email'] ) );
    }
    if ( $mail !== '' ) {
        $out['user_email'] = $mail;
    }

    // Text fields frequently present on registration (official user meta or popular plugin patterns).
    $optional_keys = array(
        'first_name',
        'last_name',
        'nickname',
        'display_name',
    );

    /**
     * Extra POST keys to include for Matrix on WP registration (string fields only; each trimmed + sanitized).
     * Do not add password or secret field names. Keys containing "pass" are skipped automatically.
     *
     * @param array $optional_keys Field names.
     * @param array $post          Unslashed POST (read-only context).
     */
    $optional_keys = apply_filters( 'maspik_wp_registration_ai_optional_field_keys', $optional_keys, $post );
    $optional_keys = array_unique( array_map( 'strval', (array) $optional_keys ) );

    foreach ( $optional_keys as $key ) {
        if ( '' === $key ) {
            continue;
        }
        $key_lower = strtolower( $key );
        if ( false !== strpos( $key_lower, 'pass' ) || false !== strpos( $key_lower, 'secret' ) ) {
            continue;
        }
        if ( ! isset( $post[ $key ] ) || ! is_string( $post[ $key ] ) ) {
            continue;
        }
        $val = sanitize_text_field( trim( $post[ $key ] ) );
        if ( $val !== '' ) {
            $out[ $key ] = $val;
        }
    }


    /**
     * Normalized registration field map for Matrix (WP core register + common extras).
     *
     * @param array $out  Keys e.g. user_login, user_email, first_name, user_url, …
     * @param array $post Unslashed POST snapshot used to build $out.
     */
    return apply_filters( 'maspik_wp_registration_ai_fields', $out, $post );
}


/**
 * Check WP registration form for spam
 */
function maspik_check_wp_registration_form($errors) {

    if ( maspik_get_settings("maspik_support_registration") !== "no" ) {
        $user_email = isset( $_POST['user_email'] ) ? sanitize_email( wp_unslash( $_POST['user_email'] ) ) : ( isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '' );
        $user_login = isset( $_POST['user_login'] ) ? sanitize_text_field( wp_unslash( $_POST['user_login'] ) ) : ( isset( $_POST['username'] ) ? sanitize_text_field( wp_unslash( $_POST['username'] ) ) : '' );
        
        $spam = false;
        $ip = maspik_get_real_ip();
        $reason = "";
        $message = "";

        // General Check
        if (!$spam) {
            $registration_ai_fields = maspik_wp_registration_fields_for_ai( $_POST );
            $GeneralCheck = GeneralCheck( $ip, $spam, $reason, $_POST, 'wp_registration', $registration_ai_fields );
            $spam = $GeneralCheck['spam'] ?? false;
            $reason = $GeneralCheck['reason'] ?? '';
            $message = $GeneralCheck['message'] ?? '';
            $spam_val = $GeneralCheck['value'] ?? '';
            $spam_lbl = $GeneralCheck['message'] ?? '';
            $type = $GeneralCheck['type'] ?? 'General';
        }

        // Email check
        if ($user_email && !$spam) {
            $spam = checkEmailForSpam($user_email);
            if ($spam && !$reason) {
                $reason = $spam;
                $spam_lbl = 'emails_blacklist';
                $spam_val = $user_email;
                $type = "Email";
            }
        }

        // Username check
        if ($user_login && !$spam) {
            $validateTextField = validateTextField( $user_login );
            $spam  = $reason = isset( $validateTextField['spam'] ) ? $validateTextField['spam'] : false;
            $message = isset( $validateTextField['message'] ) ? $validateTextField['message'] : '';
            $spam_lbl = isset( $validateTextField['label'] ) ? $validateTextField['label'] : '';
            $spam_val = isset( $validateTextField['option_value'] ) ? $validateTextField['option_value'] : '';
            $type = "Username";
        }
    
        $error_message = cfas_get_error_text($message);
        if ( $spam && isset($_POST['wp-submit']) ) {
            efas_add_to_log("$type", $reason, $_POST, 'WP registration', $spam_lbl, $spam_val);
            $errors->add('maspik_error', $error_message);
        }

    }

    return $errors;
}
add_filter('registration_errors', 'maspik_check_wp_registration_form', 10, 1);


/**
 * Check WooCommerce registration form for spam
 * 
 * IMPORTANT: This hook fires both during:
 * 1. Explicit registration form submissions (wp-login.php?action=register)
 * 2. Automatic account creation during checkout (when "create account" is enabled)
 * 
 * We use $errors->add() instead of wp_die() to properly integrate with WooCommerce's
 * error handling system and prevent fatal errors during checkout account creation.
 * 
 * CRITICAL: We skip validation entirely when registration happens during checkout.
 * During checkout, billing fields (billing_first_name, billing_email, etc.) are present
 * in $_POST, which indicates this is checkout account creation. In this case, we rely
 * solely on checkout validation (woocommerce_after_checkout_validation) to prevent spam.
 * This prevents double validation and reduces risk of false positives blocking legitimate purchases.
 */
function maspik_register_form_honeypot_check_in_woocommerce_registration($errors, $username, $email) {
    if ( maspik_if_woo_support_is_enabled() ) {
        
        // SAFEGUARD: Skip spam checks if this is being called for an existing logged-in user
        // (shouldn't happen during normal registration, but protects against edge cases)
        if ( is_user_logged_in() && current_user_can( 'edit_posts' ) ) {
            return $errors;
        }
        
        // CRITICAL: Skip validation if this registration is happening during checkout
        // During checkout, $_POST contains billing fields (billing_first_name, billing_email, etc.)
        // If we detect billing fields, this is checkout account creation - skip registration validation
        // and rely solely on checkout validation to prevent spam
        $has_billing_fields = false;
        if ( isset( $_POST ) && is_array( $_POST ) ) {
            foreach ( $_POST as $key => $value ) {
                if ( is_string( $key ) && strpos( $key, 'billing_' ) === 0 ) {
                    $has_billing_fields = true;
                    break;
                }
            }
        }
        
        if ( $has_billing_fields ) {
            // This is checkout account creation - skip registration validation
            // Checkout validation (woocommerce_after_checkout_validation) will handle spam detection
            return $errors;
        }

        $user_email = sanitize_email($email);
        $user_login = sanitize_text_field($username);
        $spam = false;
        $ip = maspik_get_real_ip();
        $reason = "";

        // Content fields for AI: only username and email (the visible fields we validate).
        $content_fields = array();
        if ( $user_login !== '' ) {
            $content_fields['username'] = $user_login;
        }
        if ( $user_email !== '' ) {
            $content_fields['email'] = $user_email;
        }

        // Country IP Check
        $GeneralCheck = GeneralCheck($ip, $spam, $reason, $_POST, "woocommerce_registration", $content_fields);
        $spam = $GeneralCheck['spam'] ?? false;
        $reason = $GeneralCheck['reason'] ?? '';
        $message = $GeneralCheck['message'] ?? '';
        $spam_val = $GeneralCheck['value'] ?? '';
        $spam_lbl = $GeneralCheck['message'] ?? '';
        $error_message = cfas_get_error_text($message);
        $type = $GeneralCheck['type'] ?? 'General';
        if ($user_email && !$spam) {
            $spam = checkEmailForSpam($user_email);
            if ($spam && !$reason) {
                $reason = $spam;
                $spam_lbl = 'emails_blacklist';
                $spam_val = $user_email;
                $type = "Email";
            }
        }

        // do user_login check 
        if ($user_login && !$spam) {
            $validateTextField = validateTextField( $user_login );
            $spam  = $reason = isset( $validateTextField['spam'] ) ? $validateTextField['spam'] : false;
            $message = isset( $validateTextField['message'] ) ? $validateTextField['message'] : '';
            $spam_lbl = isset( $validateTextField['label'] ) ? $validateTextField['label'] : '';
            $spam_val = isset( $validateTextField['option_value'] ) ? $validateTextField['option_value'] : '';
            $type = "Username";
        }   


        if ($spam) {
            $error_message = cfas_get_error_text($message);
            efas_add_to_log("$type", $reason, $_POST, 'Woocommerce registration', $spam_lbl, $spam_val);
            
            // FIXED: Use $errors->add() instead of wp_die() to properly integrate with WooCommerce
            // This prevents fatal errors during checkout account creation and allows proper error display
            // wp_die() was causing issues when accounts are created during checkout
            if ( ! is_wp_error( $errors ) ) {
                $errors = new WP_Error();
            }
            $errors->add('maspik_spam_registration', $error_message);
        }
    }
    return $errors;
}
add_filter('woocommerce_registration_errors', 'maspik_register_form_honeypot_check_in_woocommerce_registration', 9999, 3);




/**
 * Add honeypot field to the woocommerce + WP registration form
 */
function maspik_add_honeypot_to_register_form() {
    //if maspik_support_registration is no, and WooCommerce is not supported, don't add the honeypot
    if (maspik_get_settings("maspik_support_registration") === "no" && maspik_if_woo_support_is_enabled() === false) {
        return;
    }
    ?>
        <p class="form-row maspik-field" style="display: none;" aria-hidden="true">
            <label for="full-name-maspik-hp"><?php echo esc_html( maspik_honeypot_aria_label() ); ?></label>
            <input type="text" 
                   name="full-name-maspik-hp" 
                   id="full-name-maspik-hp"
                   value="" 
                   tabindex="-1" 
                   autocomplete="off"
                   autocorrect="off"
                   autocapitalize="off"
                   spellcheck="false"
                   data-form-type="other"
                   aria-label="<?php echo esc_attr( maspik_honeypot_aria_label() ); ?>"
                   aria-hidden="true">
        </p>
        <p class="form-row maspik-field" style="display: none;" aria-hidden="true">
            <label for="Maspik-currentYear"><?php echo esc_html( maspik_honeypot_aria_label() ); ?></label>
            <input type="text" 
                   name="Maspik-currentYear" 
                   id="Maspik-currentYear"
                   value="<?php echo intval(date('Y')); // adding current year for admin area ?>"
                   tabindex="-1" 
                   autocomplete="off"
                   autocorrect="off"
                   autocapitalize="off"
                   spellcheck="false"
                   data-form-type="other"
                   aria-label="<?php echo esc_attr( maspik_honeypot_aria_label() ); ?>"
                   aria-hidden="true">
        </p>


    <?php
}
/**
 * Add honeypot field to the WP registration form
 */
add_action('register_form', 'maspik_add_honeypot_to_register_form');

/**
 * Add honeypot field to the WooCommerce registration form
 */
add_action('woocommerce_register_form', 'maspik_add_honeypot_to_register_form', 9999);

