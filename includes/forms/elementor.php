<?php
// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Main Elementor validation functions
 *
 * DEVELOPER HOOK: maspik_disable_elementor_spam_check
 * 
 * This filter allows developers to disable spam check for specific Elementor forms.
 * 
 * @param bool   $disable     Whether to disable spam check (default: false)
 * @param string $form_name   Name of the Elementor form
 * @param object $record      Elementor form record object
 * @return bool  True to disable spam check, false to proceed with spam check
 * 
 * USAGE EXAMPLES:
 * 
 * 1. Disable spam check for specific form by name:
 * add_filter('maspik_disable_elementor_spam_check', function($disable, $form_name, $record) {
 *     if ($form_name === 'MY_CONTACT_FORM') {
 *         return true; // Disable spam check for this form
 *     }
 *     return $disable;
 * }, 10, 3);
 * 
 * 2. Disable spam check for multiple forms:
 * add_filter('maspik_disable_elementor_spam_check', function($disable, $form_name, $record) {
 *     $excluded_forms = ['MY_FORM_1', 'MY_FORM_2', 'ADMIN_TEST_FORM'];
 *     if (in_array($form_name, $excluded_forms)) {
 *         return true;
 *     }
 *     return $disable;
 * }, 10, 3);
 * 
 * 3. Disable spam check for logged-in administrators:
 * add_filter('maspik_disable_elementor_spam_check', function($disable, $form_name, $record) {
 *     if (is_user_logged_in() && current_user_can('administrator')) {
 *         return true;
 *     }
 *     return $disable;
 * }, 10, 3);
 */

add_action( 'elementor_pro/forms/validation', 'maspik_validation_process_elementor', 10, 2 );
function maspik_validation_process_elementor( $record, $ajax_handler ) {

    // Get form name for developer filtering
    $form_name = $record->get_form_settings( 'form_name' );
    
    //option to disable elementor spam check for developers
    $disable_elementor_spam_check = apply_filters('maspik_disable_elementor_spam_check', false, $form_name, $record);
    if($disable_elementor_spam_check){
        return;
    }


    $spam = false;
    $reason = '';
    $form_data = array_map('sanitize_text_field', isset($_POST['form_fields']) ? $_POST['form_fields'] : array());
    
    // Get all form fields
    $form_fields = $record->get( 'fields' );
    $form_fields = is_array($form_fields) ? $form_fields : array();
    $keys = array_keys($form_fields);
    $lastKeyId = !empty($keys) ? end($keys) : '';

    // Collect only relevant visible content fields for AI (text/name/email/tel/textarea)
    $content_fields = array();

    
    // Loop through all fields
    foreach ( $form_fields as $field_id => $field ) {
        $field_id = $field['id']; // Custom ID of the field
        $field_value = isset( $field['value'] ) && !is_array( $field['value'] ) ? sanitize_text_field( $field['value'] ) : '';
        $field_type = $field['type'];

        if ( empty( $field_value ) ) {
            continue;
        }

        switch ( $field_type ) {
            case 'text':
                // Text Field Validation
                $validateTextField = validateTextField($field_value);
                $spam = isset($validateTextField['spam']) ? $validateTextField['spam'] : 0;
                $message = isset($validateTextField['message']) ? $validateTextField['message'] : 0;
                $spam_lbl = isset($validateTextField['label']) ? $validateTextField['label'] : 0 ;
                $spam_val = isset($validateTextField['option_value']) ? $validateTextField['option_value'] : 0 ;

                if ( $spam ) {
                    $error_message = cfas_get_error_text( $validateTextField['message'] );
                    efas_add_to_log( 'text', $validateTextField['spam'], $form_data, 'Elementor forms', $validateTextField['label'], $validateTextField['option_value'] );
                    $ajax_handler->add_error( $field_id, $error_message );
                    return;
                }

                // Add to AI content fields if valid
                if ( ! $spam ) {
                    $content_fields[ $field_id ] = $field_value;
                }
                break;

            case 'email':
                // Check Email For Spam
                $spam = checkEmailForSpam($field_value);
                $spam_val = $field_value;

                if ($spam) {
                    $error_message = cfas_get_error_text("emails_blacklist");
                    efas_add_to_log($type = "email", $spam, $form_data,"Elementor forms", "emails_blacklist", $spam_val);
                    $ajax_handler->add_error($field_id, $error_message);
                    return;
                }

                // Add to AI content fields if valid
                if ( ! $spam ) {
                    $content_fields[ $field_id ] = $field_value;
                }
                break;

            case 'tel':
                // Tel Field Validation
                $checkTelForSpam = checkTelForSpam($field_value);
                $valid = isset($checkTelForSpam['valid']) ? $checkTelForSpam['valid'] : true;
                if(!$valid){
                  $reason = isset($checkTelForSpam['reason']) ? $checkTelForSpam['reason'] : false;
                  $spam_lbl = isset($checkTelForSpam['label']) ? $checkTelForSpam['label'] : 0 ;
                  $spam_val = isset($checkTelForSpam['option_value']) ? $checkTelForSpam['option_value'] : 0 ;
                  $message = isset($checkTelForSpam['message']) ? $checkTelForSpam['message'] : "tel_formats" ;
      
                  $error_message = cfas_get_error_text($message);
                  efas_add_to_log($type = "tel", $reason, $form_data,"Elementor forms", $spam_lbl, $spam_val);
                  $ajax_handler->add_error($field_id, $error_message);
                  return;
                }

                // Add to AI content fields if valid
                if ( $valid ) {
                    $content_fields[ $field_id ] = $field_value;
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
                    $error_message = cfas_get_error_text($message);
                    efas_add_to_log('url', $spam, $form_data, 'Elementor forms', $spam_lbl, $spam_val);
                    $ajax_handler->add_error($field_id, $error_message);
                    return;
                }
                break;
                
            case 'textarea':
                // Textarea Field Validation
                $checkTextareaForSpam = checkTextareaForSpam($field_value);
                $spam = isset($checkTextareaForSpam['spam'])? $checkTextareaForSpam['spam'] : 0;
                $message = isset($checkTextareaForSpam['message'])? $checkTextareaForSpam['message'] : 0;
                $error_message = cfas_get_error_text($message);
                $spam_lbl = isset($checkTextareaForSpam['label']) ? $checkTextareaForSpam['label'] : 0 ;
                $spam_val = isset($checkTextareaForSpam['option_value']) ? $checkTextareaForSpam['option_value'] : 0 ;
            
                if ( $spam ) {
                      efas_add_to_log($type = "textarea",$spam, $form_data,"Elementor forms", $spam_lbl, $spam_val);
                      $ajax_handler->add_error( $field_id, $error_message );
                      return;
                }

                // Add to AI content fields if valid
                if ( ! $spam ) {
                    $content_fields[ $field_id ] = $field_value;
                }
                break;

            // end
        }
    }

    // Page URL: no longer block locally when referrer is missing — Matrix API gets plugin_spam_likelihood 9 (see GeneralCheck).
    $NeedPageurl = efas_get_spam_api('NeedPageurl', 'bool');
    if ( ! isset( $_POST['referrer'] ) && $NeedPageurl && function_exists( 'maspik_matrix_raise_plugin_spam_likelihood_floor' ) ) {
        $referrer = 'no_referrer';
        maspik_matrix_raise_plugin_spam_likelihood_floor( 9  , $referrer);
    }

    // General Check
    try {
        $ip = maspik_get_real_ip();
        // Country IP Check 
        $GeneralCheck = GeneralCheck($ip, $spam, $reason, $_POST, "elementor", $content_fields);
        $spam = isset($GeneralCheck['spam']) ? $GeneralCheck['spam'] : false;
        $reason = isset($GeneralCheck['reason']) ? $GeneralCheck['reason'] : false;
        $message = isset($GeneralCheck['message']) ? $GeneralCheck['message'] : false;
        $type = isset($GeneralCheck['type']) ? $GeneralCheck['type'] : "General";
        $error_message = cfas_get_error_text($message);
        $spam_val = isset($GeneralCheck['value']) && $GeneralCheck['value'] ? $GeneralCheck['value'] : false;
        
        if($spam){
            efas_add_to_log($type,$reason, $form_data,"Elementor forms", $message,  $spam_val);
            $ajax_handler->add_error( $lastKeyId, $error_message );
            return;
        }
    } catch ( Exception $e ) {
        // On exception, don't block the form - log error and allow submission
        if ( defined('WP_DEBUG') && WP_DEBUG ) {
            error_log('Maspik Elementor GeneralCheck Exception: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        }
        // Log to spam log for debugging
        efas_add_to_log('General', 'Exception in GeneralCheck: ' . $e->getMessage(), $form_data, 'Elementor forms', 'general_check_exception', '');
        // Don't block - allow submission to continue
    } catch ( Error $e ) {
        // On fatal error, don't block the form - log error and allow submission
        if ( defined('WP_DEBUG') && WP_DEBUG ) {
            error_log('Maspik Elementor GeneralCheck Fatal Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        }
        // Log to spam log for debugging
        efas_add_to_log('General', 'Fatal Error in GeneralCheck: ' . $e->getMessage(), $form_data, 'Elementor forms', 'general_check_fatal_error', '');
        // Don't block - allow submission to continue
    }
}