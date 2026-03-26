<?php
// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}


/**
 * Filter to validate form fields for spam
 * 
 * Usage example in a theme or plugin, use with caution, contact support if you have any questions
 * 
 * // Option 1: Send specific fields
 * $fields = [
 *   ['type' => 'text', 'value' => 'John', 'field_name' => 'first_name'],
 *   ['type' => 'text', 'value' => 'Doe', 'field_name' => 'last_name'],
 *   ['type' => 'email', 'value' => 'john@example.com', 'field_name' => 'email'],
 *   ['type' => 'textarea', 'value' => 'Message content', 'field_name' => 'message'],
 *   ['type' => 'tel', 'value' => '123-456-7890', 'field_name' => 'phone']
 * 
 * if in the plugin setting Honeypot Trap, Advance key check , JavaScript check are enabled, you shold send this as well
 *   ['type' => 'hidden', 'value' => '', 'field_name' => 'Maspik-currentYear'], // this field will need to contain the current year, if not, it will be detected as spam you can add the current year by using with JS or in the php code
 *   ['type' => 'hidden', 'value' => '2025', 'field_name' => 'full-name-maspik-hp'], // this field will need to be empty, if not, it will be detected as spam
 *   ['type' => 'hidden', 'value' => 'somerandomkeycomingfromourjs', 'field_name' => 'maspik_spam_key'], // spam key will be filled automatically by maspik JS
 * ];
 * 
 * // Option 2: Send entire $_POST data
 * $fields = array_map(function($value, $key) {
 *   return [
 *     'type' => 'text', // Set appropriate type based on your form fields
 *     'value' => $value,
 *     'field_name' => $key
 *   ];
 * }, $_POST, array_keys($_POST));
 * 
 * please include the human verification field in the $fields array
 * 
 * 
 * $is_spam = apply_filters('maspik_validate_custom_form_fields', false, $fields, 'My Custom Form');
 * if ($is_spam) {
 *   // Spam detected by maspik
 *   $error = [
 *     'message' => $is_spam['message'],    // Error message to display
 *     'reason' => $is_spam['reason'],      // Technical reason for spam detection
 *     'field_type' => $is_spam['field_type'], // Type of field that triggered spam detection
 *     'field_name' => $is_spam['field_name']  // Name of field that triggered spam detection
 *   ];
 *   // Handle error...
 * }
 * 
 * @param bool|array $is_spam Default false, or array with spam details if detected
 * @param array $fields Array of form fields with 'type', 'value', and optional 'field_name' keys
 * @param string $form_name Optional form identifier for logging
 * @return bool|array Returns false if no spam detected, or array with error details if spam detected
 */
add_filter('maspik_validate_custom_form_fields', function($is_spam, $fields, $form_name = 'Custom Form') {
    // If spam was already detected by another filter, return that result
    if ($is_spam !== false) {
        return $is_spam;
    }

    $spam = false;
    $reason = '';
    
    // Convert fields to array if string provided
    if (!is_array($fields)) {
        $fields = array(['type' => 'textarea', 'value' => $fields]);
    }

    // Create $_POST data for compatibility with existing functions
    $post_data = [];
    foreach ($fields as $field) {
        if (isset($field['field_name'])) {
            $post_data[$field['field_name']] = $field['value'];
        }
    }

    // Loop through all fields
    foreach ($fields as $field) {
        // Skip if required keys are missing
        if (!isset($field['type']) || !isset($field['value']) || !isset($field['field_name']) || isset($field['type']) && $field['type'] == 'hidden' ) {
            continue;
        }

        $field_value = $field['value'];
        $field_type = strtolower($field['type']);
        $field_name = isset($field['field_name']) ? $field['field_name'] : '';

        // Skip empty fields
        if (empty($field_value)) {
            continue;
        }

        switch ($field_type) {
            case 'text':
                $validateTextField = validateTextField($field_value);
                if (isset($validateTextField['spam'])) {
                    efas_add_to_log("Text", $validateTextField['spam'], $_POST, $form_name, $validateTextField['label'], $validateTextField['option_value']);
                    return [
                        'spam' => true,
                        'message' => cfas_get_error_text($validateTextField['message']),
                        'reason' => $validateTextField['spam'],
                        'field_type' => 'text',
                        'field_name' => $field_name
                    ];
                }
                break;

            case 'email':
                $spam = checkEmailForSpam($field_value);
                if ($spam) {
                    efas_add_to_log("Email", $spam, $_POST, $form_name, $field_name, $field_value);
                    return [
                        'spam' => true,
                        'message' => cfas_get_error_text('emails_blacklist'),
                        'reason' => $spam,
                        'field_type' => 'email',
                        'field_name' => $field_name
                    ];
                }
                break;

            case 'tel':
                $checkTelForSpam = checkTelForSpam($field_value);
                if (!isset($checkTelForSpam['valid']) || !$checkTelForSpam['valid']) {
                    efas_add_to_log("Tel", $checkTelForSpam['reason'], $_POST, $form_name, $field_name, $field_value);
                    return [
                        
                        'spam' => true,
                        'message' => cfas_get_error_text($checkTelForSpam['message']),
                        'reason' => $checkTelForSpam['reason'],
                        'field_type' => 'tel',
                        'field_name' => $field_name
                    ];
                }
                break;

            case 'textarea':
                $checkTextareaForSpam = checkTextareaForSpam($field_value);
                if (isset($checkTextareaForSpam['spam'])) {
                    efas_add_to_log("Textarea", $checkTextareaForSpam['reason'], $_POST, $form_name, $field_name, $field_value);
                    return [
                        'spam' => true,
                        'message' => cfas_get_error_text($checkTextareaForSpam['message']),
                        'reason' => $checkTextareaForSpam['spam'],
                        'field_type' => 'textarea',
                        'field_name' => $field_name
                    ];
                }
                break;
        }
    }

    // General Check
    $ip = maspik_get_real_ip();

    $hidden_fields = ['maspik_spam_key', 'Maspik-currentYear', 'full-name-maspik-hp'];
    $hidden_data = array();
    foreach ($fields as $field) {
        if (isset($field['field_name']) && in_array($field['field_name'], $hidden_fields)) {
            $hidden_data[$field['field_name']] = $field['value'];
        }
    }

    $GeneralCheck = GeneralCheck($ip, $spam, $reason, $hidden_data, $form_name);
    
    if (isset($GeneralCheck['spam']) && $GeneralCheck['spam']) {
        $message = isset($GeneralCheck['message']) ? $GeneralCheck['message'] : false;
        $spam_val = isset($GeneralCheck['value']) && $GeneralCheck['value'] ? $GeneralCheck['value'] : false;
        $type = isset($GeneralCheck['type']) ? $GeneralCheck['type'] : "General";
        efas_add_to_log($type, $GeneralCheck['reason'], $_POST, $form_name, $message, $spam_val);
        return [
            'spam' => true,
            'message' => cfas_get_error_text($GeneralCheck['message']),
            'reason' => $GeneralCheck['reason'],
            'field_type' => 'general',
            'field_name' => 'general'
        ];
    }

    // No spam detected
    return false;
}, 10, 3);
