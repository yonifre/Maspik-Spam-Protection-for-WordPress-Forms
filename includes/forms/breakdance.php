<?php
// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Main Breakdance Builder validation functions
 *
 */

// Hook into Breakdance form actions
add_filter('breakdance_form_run_action_store_submission', 'maspik_validation_process_breakdance', 1, 5);
add_filter('breakdance_form_run_action_email', 'maspik_validation_process_breakdance', 1, 5);
add_filter('breakdance_form_run_action_webhook', 'maspik_validation_process_breakdance', 1, 5);
add_filter('breakdance_form_run_action_custom_javascript', 'maspik_validation_process_breakdance', 1, 5);
add_filter('breakdance_form_run_action_mailchimp', 'maspik_validation_process_breakdance', 1, 5);
add_filter('breakdance_form_run_action_popup', 'maspik_validation_process_breakdance', 1, 5);
add_filter('breakdance_form_run_action_slack', 'maspik_validation_process_breakdance', 1, 5);
add_filter('breakdance_form_run_action_drip', 'maspik_validation_process_breakdance', 1, 5);

function maspik_validation_process_breakdance($canExecute, $action, $extra, $form, $settings) {
    
    // Validate input parameters
    if (!$canExecute || !is_array($extra) || !is_array($form)) {
        return $canExecute;
    }
    
    // Use static variable to remember validation results per form submission
    static $validation_results = array();

    // Get form fields from the correct location
    $form_fields = isset($extra['fields']) && is_array($extra['fields']) ? $extra['fields'] : array();

    // Create unique key for this form submission
    $submission_key = (isset($extra['formId']) ? $extra['formId'] : '') . '_' . (isset($extra['postId']) ? $extra['postId'] : '') . '_' . md5(serialize($form_fields));

    // If we already validated this submission, return the cached result
    if (isset($validation_results[$submission_key])) {
        return $validation_results[$submission_key];
    }

    $spam = false;
    $reason = '';
    $form_id = isset($extra['formId']) ? $extra['formId'] : 0;
    $datatocheck = maspik_add_spam_keys_to_array($form_fields, $_POST);

    // Collect relevant content fields for AI
    $content_fields = array();

    // Get the form fields from Breakdance
    if (empty($form_fields)) {
        $validation_results[$submission_key] = $canExecute;
        return $canExecute;
    }

    // Extract common fields
    $email = '';
    $url = '';
    $phone = '';
    $name = '';

    // Extract email field
    if (isset($form_fields['email']) && is_string($form_fields['email'])) {
        $email = sanitize_email($form_fields['email']);
    } else {
        // Look for any field containing 'email'
        foreach ($form_fields as $field_name => $field_value) {
            if (is_string($field_name) && is_string($field_value) && 
                stripos($field_name, 'email') !== false) {
                $email = sanitize_email($field_value);
                break;
            }
        }
    }

    // Extract URL field
    foreach ($form_fields as $field_name => $field_value) {
        if (is_string($field_name) && is_string($field_value) && 
            (stripos($field_name, 'url') !== false || 
             stripos($field_name, 'website') !== false || 
             stripos($field_name, 'link') !== false)) {
            $url = sanitize_url($field_value);
            break;
        }
    }

    // Extract phone field
    foreach ($form_fields as $field_name => $field_value) {
        if (is_string($field_name) && is_string($field_value) && 
            (stripos($field_name, 'phone') !== false || 
             stripos($field_name, 'tel') !== false || 
             stripos($field_name, 'mobile') !== false)) {
            $phone = sanitize_text_field($field_value);
            break;
        }
    }

    // Extract name field
    foreach ($form_fields as $field_name => $field_value) {
        if (is_string($field_name) && is_string($field_value) && 
            (stripos($field_name, 'name') !== false || 
             stripos($field_name, 'first') !== false || 
             stripos($field_name, 'last') !== false)) {
            $name = sanitize_text_field($field_value);
            break;
        }
    }

    // Check name field for spam
    if (!empty($name)) {
        $validateTextField = validateTextField($name);
        $spam = isset($validateTextField['spam']) ? $validateTextField['spam'] : 0;
        if ($spam) {
            $error_message = cfas_get_error_text($validateTextField['message']);
            efas_add_to_log('text', $validateTextField['spam'], $datatocheck, 'Breakdance Builder', $validateTextField['label'], $validateTextField['option_value']);
            
            // Cache the error result
            $validation_results[$submission_key] = new WP_Error('spam_detected', $error_message);
            return $validation_results[$submission_key];
        }

        // Add to AI content fields if valid
        $content_fields['name'] = $name;
    }

    // Check email field for spam
    if (!empty($email)) {
        $spam = checkEmailForSpam($email);
        if ($spam) {
            $error_message = cfas_get_error_text("emails_blacklist");
            efas_add_to_log("email", $spam, $datatocheck, "Breakdance Builder", "emails_blacklist", $email);
            
            // Cache the error result
            $validation_results[$submission_key] = new WP_Error('spam_detected', $error_message);
            return $validation_results[$submission_key];
        }

        // Add to AI content fields if valid
        $content_fields['email'] = $email;
    }

    // Check phone field for spam
    if (!empty($phone)) {
        $checkTelForSpam = checkTelForSpam($phone);
        $valid = isset($checkTelForSpam['valid']) ? $checkTelForSpam['valid'] : true;
        if (!$valid) {
            $reason = isset($checkTelForSpam['reason']) ? $checkTelForSpam['reason'] : false;
            $spam_lbl = isset($checkTelForSpam['label']) ? $checkTelForSpam['label'] : 0;
            $spam_val = isset($checkTelForSpam['option_value']) ? $checkTelForSpam['option_value'] : 0;
            $message = isset($checkTelForSpam['message']) ? $checkTelForSpam['message'] : "tel_formats";

            $error_message = cfas_get_error_text($message);
            efas_add_to_log("tel", $reason, $datatocheck, "Breakdance Builder", $spam_lbl, $spam_val);
            
            // Cache the error result
            $validation_results[$submission_key] = new WP_Error('spam_detected', $error_message);
            return $validation_results[$submission_key];
        }

        // Add to AI content fields if valid
        $content_fields['phone'] = $phone;
    }

    // Check URL field for spam
    if (!empty($url)) {
        $checkUrlForSpam = checkUrlForSpam($url);
        $spam = isset($checkUrlForSpam['spam']) ? $checkUrlForSpam['spam'] : 0;
        if ($spam) {
            $error_message = cfas_get_error_text($checkUrlForSpam['message']);
            efas_add_to_log('url', $spam, $datatocheck, 'Breakdance Builder', $checkUrlForSpam['label'], $checkUrlForSpam['option_value']);
            
            // Cache the error result
            $validation_results[$submission_key] = new WP_Error('spam_detected', $error_message);
            return $validation_results[$submission_key];
        }
    }

    // Check all textarea/content/message fields for spam
    foreach ($form_fields as $field_name => $field_value) {
        if (is_string($field_value) && !empty($field_value)) {
            $field_name_lower = strtolower($field_name);
            
            // Check if this is a textarea/content field
            if (stripos($field_name_lower, 'message') !== false ||
                stripos($field_name_lower, 'content') !== false ||
                stripos($field_name_lower, 'textarea') !== false ||
                stripos($field_name_lower, 'comment') !== false ||
                stripos($field_name_lower, 'description') !== false ||
                strlen($field_value) > 100) { // If content is longer than 100 chars, treat as textarea
                
                $checkTextareaForSpam = checkTextareaForSpam($field_value);
                $spam = isset($checkTextareaForSpam['spam']) ? $checkTextareaForSpam['spam'] : 0;
                if ($spam) {
                    $error_message = cfas_get_error_text($checkTextareaForSpam['message']);
                    efas_add_to_log("textarea", $spam, $datatocheck, "Breakdance Builder", $checkTextareaForSpam['label'], $checkTextareaForSpam['option_value']);
                    
                    // Cache the error result
                    $validation_results[$submission_key] = new WP_Error('spam_detected', $error_message);
                    return $validation_results[$submission_key];
                }

                // Add to AI content fields if valid
                $content_fields[ $field_name ] = $field_value;
            }
        }
    }

    // General Check - use IP from extra data
    $ip = isset($extra['ip']) ? $extra['ip'] : maspik_get_real_ip();
    $GeneralCheck = GeneralCheck($ip, $spam, $reason, $datatocheck, "breakdance", $content_fields);
    $spam = isset($GeneralCheck['spam']) ? $GeneralCheck['spam'] : false;
    $reason = isset($GeneralCheck['reason']) ? $GeneralCheck['reason'] : false;
    $message = isset($GeneralCheck['message']) ? $GeneralCheck['message'] : false;
    $spam_val = isset($GeneralCheck['value']) ? $GeneralCheck['value'] : false;
    $type = isset($GeneralCheck['type']) ? $GeneralCheck['type'] : 'General';

    if ($spam) {
        $error_message = cfas_get_error_text($message);
        efas_add_to_log($type, $reason, $datatocheck, "Breakdance Builder", $message, $spam_val);
        
        // Cache the error result
        $validation_results[$submission_key] = new WP_Error('spam_detected', $error_message);
        return $validation_results[$submission_key];
    }

    // Page URL Check
    /*
    $NeedPageurl = efas_get_spam_api('NeedPageurl', 'bool');

    if (!isset($_POST['referrer']) && $NeedPageurl) {
        $reason = 'Page source url is empty';
        $message_key = 'block_empty_source';
        $error_message = cfas_get_error_text($message_key);
        
        efas_add_to_log('General', $reason, $form_fields, 'Breakdance Builder', $message_key, $reason);
        return new WP_Error('spam_detected', $error_message);
    }
    */

    // Cache the success result
    $validation_results[$submission_key] = $canExecute;
    return $canExecute;
}