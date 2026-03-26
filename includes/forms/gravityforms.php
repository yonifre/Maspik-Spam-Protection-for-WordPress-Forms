<?php
// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Gravity Forms validation functions
 *
 * DEVELOPER HOOK: maspik_disable_gravityforms_spam_check
 * 
 * This filter allows developers to disable spam check for specific Gravity Forms forms.
 * 
 * @param bool   $disable     Whether to disable spam check (default: false)
 * @param int    $form_id     ID of the Gravity Forms form
 * @param object $form        Gravity Forms form object
 * @return bool  True to disable spam check, false to proceed with spam check
 * 
 * USAGE EXAMPLES:
 * 
 * 1. Disable spam check for specific form by ID:
 * add_filter('maspik_disable_gravityforms_spam_check', function($disable, $form_id, $form) {
 *     if ($form_id === 123) {
 *         return true; // Disable spam check for form ID 123
 *     }
 *     return $disable;
 * }, 10, 3);
 * 
 * 2. Disable spam check for multiple form IDs:
 * add_filter('maspik_disable_gravityforms_spam_check', function($disable, $form_id, $form) {
 *     $excluded_form_ids = [123, 456, 789];
 *     if (in_array($form_id, $excluded_form_ids)) {
 *         return true;
 *     }
 *     return $disable;
 * }, 10, 3);
 * 
 * 3. Disable spam check for logged-in administrators:
 * add_filter('maspik_disable_gravityforms_spam_check', function($disable, $form_id, $form) {
 *     if (is_user_logged_in() && current_user_can('administrator')) {
 *         return true;
 *     }
 *     return $disable;
 * }, 10, 3);
 */

/**
 * Shared store for content fields collected during per-field validation.
 * Called from gform_field_validation to add fields, from gform_validation to retrieve them.
 */
function maspik_gf_content_fields_store( $field_id = null, $field_value = null ) {
    static $fields = array();
    if ( $field_id !== null ) {
        $fields[ $field_id ] = $field_value;
    }
    return $fields;
}

add_filter('gform_field_validation', 'maspik_validation_process_gravity', 10, 4);
function maspik_validation_process_gravity($result, $value, $form, $field) {
    static $spam_check_done = false;
    
    if ($spam_check_done) {
        $result['is_valid'] = false;
        return $result;
    }

    static $disable_check = null;
    if ($disable_check === null) {
        $form_id = 0;
        if (is_array($form) && isset($form['id'])) {
            $form_id = intval($form['id']);
        } elseif (is_object($form) && isset($form->id)) {
            $form_id = intval($form->id);
        }
        $disable_check = apply_filters('maspik_disable_gravityforms_spam_check', false, $form_id, $form);
    }
    if ($disable_check) {
        return $result;
    }

    if (!$result['is_valid'] || empty($value)) {
        return $result;
    }

    $field_value = is_array($value) ? implode(" ", $value) : $value;
    $field_type = $field->type;
    $field_value = strtolower($field_value);

    switch ($field_type) {
        case 'text':
        case 'name':
            $validateTextField = validateTextField($field_value);
            if (isset($validateTextField['spam']) && $validateTextField['spam']) {
                efas_add_to_log("text", $validateTextField['spam'], $_POST, 'GravityForms', 
                    $validateTextField['label'] ?? '', $validateTextField['option_value'] ?? '');
                $result['is_valid'] = false;
                $result['message'] = cfas_get_error_text($validateTextField['message'] ?? '');
                $spam_check_done = true;
                return $result;
            }
            if ( empty($validateTextField['spam']) ) {
                maspik_gf_content_fields_store( $field->id, $field_value );
            }
            break;

        case 'email':
            $spam = checkEmailForSpam($field_value);
            if ($spam) {
                efas_add_to_log("email", $spam, $_POST, "GravityForms", "emails_blacklist", $field_value);
                $result['is_valid'] = false;
                $result['message'] = cfas_get_error_text();
                $spam_check_done = true;
                return $result;
            }
            if ( ! $spam ) {
                maspik_gf_content_fields_store( $field->id, $field_value );
            }
            break;

        case 'phone':
            $checkTelForSpam = checkTelForSpam($field_value);
            if (isset($checkTelForSpam['valid']) && !$checkTelForSpam['valid']) {
                efas_add_to_log("tel", $checkTelForSpam['reason'] ?? '', $_POST, "GravityForms", 
                    $checkTelForSpam['label'] ?? '', $checkTelForSpam['option_value'] ?? '');
                $result['is_valid'] = false;
                $result['message'] = cfas_get_error_text($checkTelForSpam['message'] ?? '');
                $spam_check_done = true;
                return $result;
            }
            if ( isset($checkTelForSpam['valid']) && $checkTelForSpam['valid'] ) {
                maspik_gf_content_fields_store( $field->id, $field_value );
            }
            break;

        case 'textarea':
            $checkTextareaForSpam = checkTextareaForSpam($field_value);
            if (isset($checkTextareaForSpam['spam']) && $checkTextareaForSpam['spam']) {
                efas_add_to_log("textarea", $checkTextareaForSpam['spam'], $_POST, "GravityForms", 
                    $checkTextareaForSpam['label'] ?? '', $checkTextareaForSpam['option_value'] ?? '');
                $result['is_valid'] = false;
                $result['message'] = cfas_get_error_text($checkTextareaForSpam['message'] ?? '');
                $spam_check_done = true;
                return $result;
            }
            if ( empty($checkTextareaForSpam['spam']) ) {
                maspik_gf_content_fields_store( $field->id, $field_value );
            }
            break;

        case 'url':
            $checkUrlForSpam = checkUrlForSpam($field_value);
            if (isset($checkUrlForSpam['spam']) && $checkUrlForSpam['spam']) {
                efas_add_to_log("url", $checkUrlForSpam['spam'], $_POST, "GravityForms", 
                    $checkUrlForSpam['label'] ?? '', $checkUrlForSpam['option_value'] ?? '');
                $result['is_valid'] = false;
                $result['message'] = cfas_get_error_text($checkUrlForSpam['message'] ?? '');
                $spam_check_done = true;
                return $result;
            }
            break;
    }

    return $result;
}

/**
 * GeneralCheck runs AFTER all field validations via gform_validation,
 * so $content_fields is fully populated with only text-type fields.
 */
add_filter('gform_validation', 'maspik_general_check_gravity', 10, 1);
function maspik_general_check_gravity( $validation_result ) {
    $form    = $validation_result['form'];
    $form_id = isset($form['id']) ? intval($form['id']) : 0;

    $disable_check = apply_filters('maspik_disable_gravityforms_spam_check', false, $form_id, $form);
    if ( $disable_check ) {
        return $validation_result;
    }

    // If per-field checks already rejected the submission, skip GeneralCheck to save resources
    if ( ! $validation_result['is_valid'] ) {
        return $validation_result;
    }

    $content_fields = maspik_gf_content_fields_store();

    try {
        $ip     = maspik_get_real_ip();
        $spam   = false;
        $reason = '';
        $GeneralCheck = GeneralCheck($ip, $spam, $reason, $_POST, "gravityforms", $content_fields);

        if ( isset($GeneralCheck['spam']) && $GeneralCheck['spam'] ) {
            $reason   = $GeneralCheck['reason'] ?? '';
            $message  = $GeneralCheck['message'] ?? '';
            $spam_val = $GeneralCheck['value'] ?? '';
            $type     = isset($GeneralCheck['type']) ? $GeneralCheck['type'] : 'General';

            efas_add_to_log($type, $reason, $_POST, 'GravityForms', $message, $spam_val);

            $validation_result['is_valid'] = false;
            foreach ( $validation_result['form']['fields'] as &$field ) {
                if ( $field->type !== 'hidden' && $field->type !== 'html' && $field->type !== 'section' ) {
                    $field->failed_validation  = true;
                    $field->validation_message = cfas_get_error_text($message);
                    break;
                }
            }
        }
    } catch ( Exception $e ) {
        if ( defined('WP_DEBUG') && WP_DEBUG ) {
            error_log('Maspik GravityForms GeneralCheck Exception: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        }
        efas_add_to_log('General', 'Exception in GeneralCheck: ' . $e->getMessage(), $_POST, 'GravityForms', 'general_check_exception', '');
    } catch ( Error $e ) {
        if ( defined('WP_DEBUG') && WP_DEBUG ) {
            error_log('Maspik GravityForms GeneralCheck Fatal Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        }
        efas_add_to_log('General', 'Fatal Error in GeneralCheck: ' . $e->getMessage(), $_POST, 'GravityForms', 'general_check_fatal_error', '');
    }

    return $validation_result;
}

add_filter('gform_submit_button', 'add_maspikhp_html_to_gform', 99, 2);
function add_maspikhp_html_to_gform($button, $form) {
    if (is_admin()) {
        return $button;
    }
    $addhtml = "";

    if (efas_get_spam_api('maspikHoneypot', 'bool')) {
        $honeypot_name = maspik_HP_name();
        $addhtml .= '<div class="gfield gfield--type-text maspik-field">
            <label for="' . esc_attr( $honeypot_name ) . '" class="ginput_container_text">' . esc_html( maspik_honeypot_aria_label() ) . '</label>
            <input size="1" type="text" autocomplete="off" aria-hidden="true" tabindex="-1" aria-label="' . esc_attr( maspik_honeypot_aria_label() ) . '" name="' . esc_attr( $honeypot_name ) . '" id="' . esc_attr( $honeypot_name ) . '" class="ginput_text" placeholder="' . esc_attr( maspik_honeypot_aria_label() ) . '">
        </div>';
    }

    if (maspik_get_settings('maspikYearCheck')) {
        $addhtml .= '<div class="gfield gfield--type-text maspik-field">
            <label for="Maspik-currentYear" class="ginput_container_text">' . esc_html( maspik_honeypot_aria_label() ) . '</label>
            <input size="1" type="text" autocomplete="off" aria-hidden="true" tabindex="-1" aria-label="' . esc_attr( maspik_honeypot_aria_label() ) . '" name="Maspik-currentYear" id="Maspik-currentYear" class="ginput_text" placeholder="">
        </div>';
    }

    return $addhtml . $button;
}

