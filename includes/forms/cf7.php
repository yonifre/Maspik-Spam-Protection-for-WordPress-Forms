<?php
// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * CF7 Validation Hook
 * 
 * DEVELOPER HOOK: maspik_disable_cf7_spam_check
 * 
 * This filter allows developers to disable spam check for specific Contact Form 7 forms by ID.
 * 
 * @param bool   $disable     Whether to disable spam check (default: false)
 * @param int    $form_id     ID of the CF7 form
 * @return bool  True to disable spam check, false to proceed with spam check
 * 
 * USAGE EXAMPLES:
 * 
 * 1. Disable spam check for specific form by ID:
 * add_filter('maspik_disable_cf7_spam_check', function($disable, $form_id) {
 *     if ($form_id === 123) {
 *         return true; // Disable spam check for form ID 123
 *     }
 *     return $disable;
 * }, 10, 2);
 * 
 * 2. Disable spam check for multiple form IDs:
 * add_filter('maspik_disable_cf7_spam_check', function($disable, $form_id) {
 *     $excluded_form_ids = [123, 456, 789];
 *     if (in_array($form_id, $excluded_form_ids)) {
 *         return true;
 *     }
 *     return $disable;
 * }, 10, 2);
 * 
 * 3. Disable spam check for logged-in administrators:
 * add_filter('maspik_disable_cf7_spam_check', function($disable, $form_id) {
 *     if (is_user_logged_in() && current_user_can('administrator')) {
 *         return true;
 *     }
 *     return $disable;
 * }, 10, 2);
 */
add_filter( 'wpcf7_validate', 'maspik_validate_cf7_process', 10, 2 );
function maspik_validate_cf7_process( $result, $tags ) {

    // Get form ID for developer filtering
    $form_id = 0;
    if (isset($_POST['_wpcf7'])) {
        $form_id = intval($_POST['_wpcf7']);
    }

    //option to disable cf7 spam check for developers
    $disable_cf7_spam_check = apply_filters('maspik_disable_cf7_spam_check', false, $form_id);
    if($disable_cf7_spam_check){
        return $result;
    }

    $error_message = cfas_get_error_text();
    $spam = false;
    $reason = "";

    // Collect relevant content fields for AI (text, email, tel, textarea)
    $content_fields = array();

    // Loop through each tag (field) in the form
    foreach ( $tags as $tag ) {
        $name = $tag->name;
        $type = $tag->basetype;

        if ( ! isset( $_POST[ $name ] ) ) {
            continue;
        }

        $field_value =  is_array( $_POST[ $name ] ) ? $_POST[ $name ] : sanitize_text_field( $_POST[ $name ] );

        if ( empty( $field_value ) ) {
            continue;
        }

        switch ( $type ) {
            case 'text':
                // Text Field Validation
                $validateTextField = validateTextField( $field_value );
                $spam = isset( $validateTextField['spam'] ) ? $validateTextField['spam'] : false;
                $message = isset( $validateTextField['message'] ) ? $validateTextField['message'] : '';
                $spam_lbl = isset( $validateTextField['label'] ) ? $validateTextField['label'] : '';
                $spam_val = isset( $validateTextField['option_value'] ) ? $validateTextField['option_value'] : '';

                if ( $spam ) {
                    $error_message = cfas_get_error_text( $message );
                    $post_entries = array_filter( $_POST, function( $key ) {
                        return strpos( $key, '_wpcf7' ) === false;
                    }, ARRAY_FILTER_USE_KEY );
                    efas_add_to_log( "text", $spam, $post_entries, "Contact Form 7", $spam_lbl, $spam_val );
                    $result->invalidate( $tag, $error_message );
                    return $result;
                }

                // Add to AI content fields if valid
                if ( ! $spam ) {
                    $content_fields[ $name ] = is_array( $field_value ) ? implode( ' ', (array) $field_value ) : $field_value;
                }
                break;

            case 'email':
                // Email Field Validation
                $spam = checkEmailForSpam( $field_value );
                $spam_val = $field_value;

                if ( $spam ) {
                    $error_message = cfas_get_error_text( "emails_blacklist" );
                    $post_entries = array_filter( $_POST, function( $key ) {
                        return strpos( $key, '_wpcf7' ) === false;
                    }, ARRAY_FILTER_USE_KEY );
                    efas_add_to_log( "email", $spam, $post_entries, "Contact Form 7", "emails_blacklist", $spam_val );
                    $result->invalidate( $tag, $error_message );
                    return $result;
                }

                // Add to AI content fields if valid
                if ( ! $spam ) {
                    $content_fields[ $name ] = is_array( $field_value ) ? implode( ' ', (array) $field_value ) : $field_value;
                }
                break;

            case 'tel':
                // Tel Field Validation
                $checkTelForSpam = checkTelForSpam( $field_value );
                $reason = isset( $checkTelForSpam['reason'] ) ? $checkTelForSpam['reason'] : '';
                $valid = isset( $checkTelForSpam['valid'] ) ? $checkTelForSpam['valid'] : true;
                $message = isset( $checkTelForSpam['message'] ) ? $checkTelForSpam['message'] : '';
                $spam_lbl = isset( $checkTelForSpam['label'] ) ? $checkTelForSpam['label'] : '';
                $spam_val = isset( $checkTelForSpam['option_value'] ) ? $checkTelForSpam['option_value'] : '';

                if ( ! $valid ) {
                    $post_entries = array_filter( $_POST, function( $key ) {
                        return strpos( $key, '_wpcf7' ) === false;
                    }, ARRAY_FILTER_USE_KEY );
                    $error_message = cfas_get_error_text( $message );
                    efas_add_to_log( "tel", $reason, $post_entries, "Contact Form 7", $spam_lbl, $spam_val );
                    $result->invalidate( $tag, $error_message );
                    return $result;
                }

                // Add to AI content fields if valid
                if ( $valid ) {
                    $content_fields[ $name ] = is_array( $field_value ) ? implode( ' ', (array) $field_value ) : $field_value;
                }
                break;

            case 'url':
                // URL Field Validation
                $checkUrlForSpam = checkUrlForSpam($field_value);
                $spam = isset($checkUrlForSpam['spam']) ? $checkUrlForSpam['spam'] : 0;
                $message = isset($checkUrlForSpam['message']) ? $checkUrlForSpam['message'] : 0;
                $spam_lbl = isset($checkUrlForSpam['label']) ? $checkUrlForSpam['label'] : 0;
                $spam_val = isset($checkUrlForSpam['option_value']) ? $checkUrlForSpam['option_value'] : 0;

                if ($spam) {
                    $post_entries = array_filter($_POST, function($key) {
                        return strpos($key, '_wpcf7') === false;
                    }, ARRAY_FILTER_USE_KEY);
                    $error_message = cfas_get_error_text($message);
                    efas_add_to_log('url', $spam, $post_entries, 'Contact Form 7', $spam_lbl, $spam_val);
                    $result->invalidate($tag, $error_message);
                    return $result;
                }
                break;

            case 'textarea':
                // Textarea Field Validation
                $checkTextareaForSpam = checkTextareaForSpam( $field_value );
                $spam = isset( $checkTextareaForSpam['spam'] ) ? $checkTextareaForSpam['spam'] : false;
                $message = isset( $checkTextareaForSpam['message'] ) ? $checkTextareaForSpam['message'] : '';
                $spam_lbl = isset( $checkTextareaForSpam['label'] ) ? $checkTextareaForSpam['label'] : '';
                $spam_val = isset( $checkTextareaForSpam['option_value'] ) ? $checkTextareaForSpam['option_value'] : '';

                if ( $spam ) {
                    $post_entries = array_filter( $_POST, function( $key ) {
                        return strpos( $key, '_wpcf7' ) === false;
                    }, ARRAY_FILTER_USE_KEY );
                    $error_message = cfas_get_error_text( $message );
                    efas_add_to_log( "textarea", $spam, $post_entries, "Contact Form 7", $spam_lbl, $spam_val );
                    $result->invalidate( $tag, $error_message );
                    return $result;
                }

                // Add to AI content fields if valid
                if ( ! $spam ) {
                    $content_fields[ $name ] = is_array( $field_value ) ? implode( ' ', (array) $field_value ) : $field_value;
                }
                break;

        }
    }

    // General Check
    $ip = maspik_get_real_ip();
    $GeneralCheck = GeneralCheck( $ip, $spam, $reason, $_POST, "cf7", $content_fields );
    $spam = isset( $GeneralCheck['spam'] ) ? $GeneralCheck['spam'] : false;
    $reason = isset( $GeneralCheck['reason'] ) ? $GeneralCheck['reason'] : false;
    $message = isset( $GeneralCheck['message'] ) ? $GeneralCheck['message'] : false;
    $spam_val = isset( $GeneralCheck['value'] ) ? $GeneralCheck['value'] : false;
    $type = isset( $GeneralCheck['type'] ) ? $GeneralCheck['type'] : "General";

    if ( $spam ) {
        $result->invalidate( '', cfas_get_error_text( $message ) );
        $post_entries = isset($_POST) ? $_POST : array();
        efas_add_to_log( $type, $reason, $post_entries, "Contact Form 7", $message, $spam_val );
        return $result;
    }

    return $result;
}

function maspik_honeypot_to_cf7_form( $form_content ) {
    if ( efas_get_spam_api( 'maspikHoneypot', 'bool' ) || efas_get_spam_api( 'maspikTimeCheck', 'bool' ) || maspik_get_settings( 'maspikYearCheck' ) ) {
        $custom_html = '';

        if ( efas_get_spam_api( 'maspikHoneypot', 'bool' ) ) {
            $custom_html .= '<div class="wpcf7-form-control-wrap maspik-field">
                <label for="full-name-maspik-hp" class="wpcf7-form-control-label">' . esc_html( maspik_honeypot_aria_label() ) . '</label>
                <input size="1" type="text" autocomplete="off" aria-hidden="true" tabindex="-1" aria-label="' . esc_attr( maspik_honeypot_aria_label() ) . '" name="full-name-maspik-hp" id="full-name-maspik-hp" class="wpcf7-form-control wpcf7-text" placeholder="' . esc_attr( maspik_honeypot_aria_label() ) . '">
            </div>';
        }

        if ( maspik_get_settings( 'maspikYearCheck' ) ) {
            $custom_html .= '<div class="wpcf7-form-control-wrap maspik-field">
                <label for="Maspik-currentYear" class="wpcf7-form-control-label">' . esc_html( maspik_honeypot_aria_label() ) . '</label>
                <input size="1" type="text" autocomplete="off" aria-hidden="true" tabindex="-1" aria-label="' . esc_attr( maspik_honeypot_aria_label() ) . '" name="Maspik-currentYear" id="Maspik-currentYear" class="wpcf7-form-control wpcf7-text" placeholder="">
            </div>';
        }

        $form_content .= $custom_html;
    }

    return $form_content;
}
add_filter( 'wpcf7_form_elements', 'maspik_honeypot_to_cf7_form' );