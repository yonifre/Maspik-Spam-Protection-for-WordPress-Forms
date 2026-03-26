<?php
// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Main MetForm validation functions
 *
 * DEVELOPER HOOK: maspik_disable_metform_spam_check
 * 
 * This filter allows developers to disable spam check for specific MetForm forms.
 * 
 * @param bool   $disable     Whether to disable spam check (default: false)
 * @param int    $form_id     ID of the MetForm
 * @param array  $form_data   Form submission data
 * @return bool  True to disable spam check, false to proceed with spam check
 * 
 * USAGE EXAMPLES:
 * 
 * 1. Disable spam check for specific form by ID:
 * add_filter('maspik_disable_metform_spam_check', function($disable, $form_id, $form_data) {
 *     if ($form_id === 123) {
 *         return true; // Disable spam check for this form
 *     }
 *     return $disable;
 * }, 10, 3);
 * 
 * 2. Disable spam check for multiple forms:
 * add_filter('maspik_disable_metform_spam_check', function($disable, $form_id, $form_data) {
 *     $excluded_forms = [123, 456, 789];
 *     if (in_array($form_id, $excluded_forms)) {
 *         return true;
 *     }
 *     return $disable;
 * }, 10, 3);
 * 
 * 3. Disable spam check for logged-in administrators:
 * add_filter('maspik_disable_metform_spam_check', function($disable, $form_id, $form_data) {
 *     if (is_user_logged_in() && current_user_can('administrator')) {
 *         return true;
 *     }
 *     return $disable;
 * }, 10, 3);
 */

class MaspikMetFormValidation {
    
    public function __construct() {
        add_filter('mf_after_validation_check', [$this, 'external_validation_check'], 10, 1);
    }
    
    /**
     * 
     * @param array $validation_data - Validation information
     * @return array - Validation result
     */
    public function external_validation_check($validation_data) {
        // Check if internal validation passed
        if (!$validation_data['is_valid']) {
            return $validation_data;
        }
        
        $form_data = $validation_data['form_data'];
        $file_data = $validation_data['file_data'];
        $form_id = isset($form_data['form_id']) ? intval($form_data['form_id']) : 0;
        
        // Debug logging
        //error_log('Maspik MetForm: Starting validation for form ID: ' . $form_id);
        //error_log('Maspik MetForm: Form data: ' . print_r($form_data, true));
        
        // Allow developers to disable spam check for specific forms
        $disable_metform_spam_check = apply_filters('maspik_disable_metform_spam_check', false, $form_id, $form_data);
        if ($disable_metform_spam_check) {
            //error_log('Maspik MetForm: Spam check disabled for form ID: ' . $form_id);
            return $validation_data;
        }
        
        // Check text fields
        if ($this->check_text_fields($form_data)) {
            $error_message = cfas_get_error_text(); // Use default error message
            //error_log('Maspik MetForm: Text spam detected, returning error: ' . $error_message);
            return [
                'is_valid' => false,
                'message' => $error_message,
                'form_data' => $form_data,
                'file_data' => $file_data,
                'error' => [$error_message], // MetForm might expect an array
                'errors' => [$error_message], // Alternative error format
                'validation_errors' => ['spam' => $error_message], // Another possible format
                'field_errors' => ['general' => $error_message] // Field-specific errors
            ];
        }
        
        // Check email fields
        if ($this->check_email_fields($form_data)) {
            $error_message = cfas_get_error_text('emails_blacklist'); // This parameter exists
            return [
                'is_valid' => false,
                'message' => $error_message,
                'form_data' => $form_data,
                'file_data' => $file_data,
                'error' => $error_message, // MetForm expects this field
                'errors' => [$error_message] // Alternative error format
            ];
        }
        
        // Check phone fields
        if ($this->check_phone_fields($form_data)) {
            $error_message = cfas_get_error_text(); // Use default error message
            return [
                'is_valid' => false,
                'message' => $error_message,
                'form_data' => $form_data,
                'file_data' => $file_data,
                'error' => $error_message, // MetForm expects this field
                'errors' => [$error_message] // Alternative error format
            ];
        }
        
        // Check URL fields
        if ($this->check_url_fields($form_data)) {
            $error_message = cfas_get_error_text(); // Use default error message
            return [
                'is_valid' => false,
                'message' => $error_message,
                'form_data' => $form_data,
                'file_data' => $file_data,
                'error' => $error_message, // MetForm expects this field
                'errors' => [$error_message] // Alternative error format
            ];
        }
        
        // Check textarea fields
        if ($this->check_textarea_fields($form_data)) {
            $error_message = cfas_get_error_text(); // Use default error message
            return [
                'is_valid' => false,
                'message' => $error_message,
                'form_data' => $form_data,
                'file_data' => $file_data,
                'error' => $error_message, // MetForm expects this field
                'errors' => [$error_message] // Alternative error format
            ];
        }
        
        // General check (Country/IP, Honeypot, Time, Year)
        if ($this->check_general_spam($form_data)) {
            $error_message = cfas_get_error_text(); // Use default error message
            return [
                'is_valid' => false,
                'message' => $error_message,
                'form_data' => $form_data,
                'file_data' => $file_data,
                'error' => $error_message, // MetForm expects this field
                'errors' => [$error_message] // Alternative error format
            ];
        }
        
        return $validation_data;
    }
    
    /**
     * Check text fields
     */
    private function check_text_fields($form_data) {
        foreach ($form_data as $field_name => $field_value) {
            // Skip internal MetForm fields
            if (strpos($field_name, '_') === 0 || in_array($field_name, ['form_id', 'form_settings'])) {
                continue;
            }
            
            if (empty($field_value)) {
                continue;
            }
            
            // Convert array values to string for validation
            if (is_array($field_value)) {
                $field_value = implode(' ', array_filter(array_map('strval', $field_value)));
            }
            
            $field_value = strtolower(sanitize_text_field($field_value));
            
            // Check if this looks like a text field (not email, phone, url, or textarea)
            if ($this->is_text_field($field_name, $field_value)) {
                $validateTextField = validateTextField($field_value);
                $spam = isset($validateTextField['spam']) ? $validateTextField['spam'] : false;
                
                if ($spam) {
                    $spam_lbl = isset($validateTextField['label']) ? $validateTextField['label'] : '';
                    $spam_val = isset($validateTextField['option_value']) ? $validateTextField['option_value'] : '';
                    efas_add_to_log('text', $spam, $form_data, 'MetForm', $spam_lbl, $spam_val);
                    //error_log('Maspik MetForm: Text spam detected in field: ' . $field_name . ' with value: ' . $field_value);
                    return true; // Spam detected
                }
            }
        }
        
        return false; // No spam detected
    }
    
    /**
     * Check email fields
     */
    private function check_email_fields($form_data) {
        foreach ($form_data as $field_name => $field_value) {
            if (strpos($field_name, '_') === 0 || in_array($field_name, ['form_id', 'form_settings'])) {
                continue;
            }
            
            if (empty($field_value)) {
                continue;
            }
            
            if (is_array($field_value)) {
                $field_value = implode(' ', array_filter(array_map('strval', $field_value)));
            }
            
            $field_value = strtolower(sanitize_text_field($field_value));
            
            // Check if this is an email field
            if ($this->is_email_field($field_name, $field_value)) {
                $spam = checkEmailForSpam($field_value);
                
                if ($spam) {
                    efas_add_to_log('email', $spam, $form_data, 'MetForm', 'emails_blacklist', $field_value);
                    return true; // Spam detected
                }
            }
        }
        
        return false; // No spam detected
    }
    
    /**
     * Check phone fields
     */
    private function check_phone_fields($form_data) {
        foreach ($form_data as $field_name => $field_value) {
            if (strpos($field_name, '_') === 0 || in_array($field_name, ['form_id', 'form_settings'])) {
                continue;
            }
            
            if (empty($field_value)) {
                continue;
            }
            
            if (is_array($field_value)) {
                $field_value = implode(' ', array_filter(array_map('strval', $field_value)));
            }
            
            $field_value = sanitize_text_field($field_value);
            
            // Check if this is a phone field
            if ($this->is_phone_field($field_name, $field_value)) {
                $checkTelForSpam = checkTelForSpam($field_value);
                $valid = isset($checkTelForSpam['valid']) ? $checkTelForSpam['valid'] : true;
                
                if (!$valid) {
                    $reason = isset($checkTelForSpam['reason']) ? $checkTelForSpam['reason'] : '';
                    $spam_lbl = isset($checkTelForSpam['label']) ? $checkTelForSpam['label'] : '';
                    $spam_val = isset($checkTelForSpam['option_value']) ? $checkTelForSpam['option_value'] : '';
                    efas_add_to_log('tel', $reason, $form_data, 'MetForm', $spam_lbl, $spam_val);
                    return true; // Spam detected
                }
            }
        }
        
        return false; // No spam detected
    }
    
    /**
     * Check URL fields
     */
    private function check_url_fields($form_data) {
        foreach ($form_data as $field_name => $field_value) {
            if (strpos($field_name, '_') === 0 || in_array($field_name, ['form_id', 'form_settings'])) {
                continue;
            }
            
            if (empty($field_value)) {
                continue;
            }
            
            if (is_array($field_value)) {
                $field_value = implode(' ', array_filter(array_map('strval', $field_value)));
            }
            
            $field_value = sanitize_text_field($field_value);
            
            // Check if this is a URL field
            if ($this->is_url_field($field_name, $field_value)) {
                $checkUrlForSpam = checkUrlForSpam($field_value);
                $spam = isset($checkUrlForSpam['spam']) ? $checkUrlForSpam['spam'] : 0;
                
                if ($spam) {
                    $spam_lbl = isset($checkUrlForSpam['label']) ? $checkUrlForSpam['label'] : '';
                    $spam_val = isset($checkUrlForSpam['option_value']) ? $checkUrlForSpam['option_value'] : '';
                    efas_add_to_log('url', $spam, $form_data, 'MetForm', $spam_lbl, $spam_val);
                    return true; // Spam detected
                }
            }
        }
        
        return false; // No spam detected
    }
    
    /**
     * Check textarea fields
     */
    private function check_textarea_fields($form_data) {
        foreach ($form_data as $field_name => $field_value) {
            if (strpos($field_name, '_') === 0 || in_array($field_name, ['form_id', 'form_settings'])) {
                continue;
            }
            
            if (empty($field_value)) {
                continue;
            }
            
            if (is_array($field_value)) {
                $field_value = implode(' ', array_filter(array_map('strval', $field_value)));
            }
            
            $field_value = strtolower(sanitize_text_field($field_value));
            
            // Check if this is a textarea field
            if ($this->is_textarea_field($field_name, $field_value)) {
                $checkTextareaForSpam = checkTextareaForSpam($field_value);
                $spam = isset($checkTextareaForSpam['spam']) ? $checkTextareaForSpam['spam'] : false;
                
                if ($spam) {
                    $spam_lbl = isset($checkTextareaForSpam['label']) ? $checkTextareaForSpam['label'] : '';
                    $spam_val = isset($checkTextareaForSpam['option_value']) ? $checkTextareaForSpam['option_value'] : '';
                    efas_add_to_log('textarea', $spam, $form_data, 'MetForm', $spam_lbl, $spam_val);
                    return true; // Spam detected
                }
            }
        }
        
        return false; // No spam detected
    }
    
    /**
     * General check (Country/IP, Honeypot, Time, Year)
     */
    private function check_general_spam($form_data) {
        $ip = maspik_get_real_ip();
        $spam = false;
        $reason = '';
        
        // Build content fields for AI from textual/email/phone/textarea-like fields
        $content_fields = array();
        foreach ( $form_data as $field_name => $field_value ) {
            if ( strpos( $field_name, '_' ) === 0 || in_array( $field_name, array( 'form_id', 'form_settings' ), true ) ) {
                continue;
            }

            if ( empty( $field_value ) ) {
                continue;
            }

            if ( is_array( $field_value ) ) {
                $field_value = implode( ' ', array_filter( array_map( 'strval', $field_value ) ) );
            }

            $field_value = sanitize_text_field( (string) $field_value );
            if ( $field_value === '' ) {
                continue;
            }

            // Consider only fields that look like text/email/phone/textarea according to helpers
            if (
                $this->is_text_field( $field_name, $field_value ) ||
                $this->is_email_field( $field_name, $field_value ) ||
                $this->is_phone_field( $field_name, $field_value ) ||
                $this->is_textarea_field( $field_name, $field_value )
            ) {
                $content_fields[ $field_name ] = $field_value;
            }
        }
        
        // Add spam keys to form data for general check
        $datatocheck = maspik_add_spam_keys_to_array($form_data, $_POST);
        
        $GeneralCheck = GeneralCheck($ip, $spam, $reason, $datatocheck, 'metform', $content_fields);
        $spam = isset($GeneralCheck['spam']) ? $GeneralCheck['spam'] : false;
        
        if ($spam) {
            $reason = isset($GeneralCheck['reason']) ? $GeneralCheck['reason'] : '';
            $message = isset($GeneralCheck['message']) ? $GeneralCheck['message'] : '';
            $spam_val = isset($GeneralCheck['value']) ? $GeneralCheck['value'] : '';
            $type = isset($GeneralCheck['type']) ? $GeneralCheck['type'] : 'General';
            
            efas_add_to_log($type, $reason, $form_data, 'MetForm', $message, $spam_val);
            return true; // Spam detected
        }
        
        return false; // No spam detected
    }
    
    /**
     * Spam check before saving data
     * 
     * @param array $form_data - Form data
     * @param int $form_id - Form ID
     * @param array $form_settings - Form settings
     * @param array $attributes - Additional attributes
     * @return array - Form data (can be processed)
     */
    public function spam_check_before_store($form_data, $form_id, $form_settings, $attributes) {
        
        // Allow developers to disable spam check for specific forms
        $disable_metform_spam_check = apply_filters('maspik_disable_metform_spam_check', false, $form_id, $form_data);
        if ($disable_metform_spam_check) {
            return $form_data;
        }
        
        // Check if already identified as spam
        if ($this->check_general_spam($form_data)) {
            $form_data['_spam_detected'] = true;
            $form_data['_spam_reason'] = 'General spam check failed';
        }
        
        return $form_data;
    }
    
    /**
     * Additional validation before saving
     */
    public function additional_validation_before_store($form_id, $form_data, $form_settings, $attributes) {
        
        // Allow developers to disable spam check for specific forms
        $disable_metform_spam_check = apply_filters('maspik_disable_metform_spam_check', false, $form_id, $form_data);
        if ($disable_metform_spam_check) {
            return;
        }
        
        // Check if already marked as spam
        if (isset($form_data['_spam_detected']) && $form_data['_spam_detected']) {
            // Option to stop the process here
            // or continue but mark as spam
        }
        
        // Additional checks as needed
        $this->log_form_submission($form_id, $form_data);
    }
    
    /**
     * Helper functions to determine field types
     */
    private function is_text_field($field_name, $field_value) {
        $field_name_lower = strtolower($field_name);
        
        //skip if field name is action,id,form_nonce,maspik_spam_key,full-name-maspik-hp
        if (in_array($field_name, ['action', 'id', 'form_nonce', 'maspik_spam_key', 'full-name-maspik-hp'])) {
            return false;
        }
        
        // Skip if it's clearly another type
        if ($this->is_email_field($field_name, $field_value) || 
            $this->is_phone_field($field_name, $field_value) || 
            $this->is_url_field($field_name, $field_value) || 
            $this->is_textarea_field($field_name, $field_value)) {
            return false;
        }
        
        // Default to text for short fields
        return strlen($field_value) <= 100;
    }
    
    private function is_email_field($field_name, $field_value) {
        $field_name_lower = strtolower($field_name);
        
        // Email detection
        if (strpos($field_name_lower, 'email') !== false || filter_var($field_value, FILTER_VALIDATE_EMAIL)) {
            return true;
        }
        
        return false;
    }
    
    private function is_phone_field($field_name, $field_value) {
        $field_name_lower = strtolower($field_name);
        
        // Phone detection - only based on field name, not content
        if (strpos($field_name_lower, 'phone') !== false || strpos($field_name_lower, 'tel') !== false || 
            strpos($field_name_lower, 'mobile') !== false) {
            return true;
        }
        
        return false;
    }
    
    private function is_url_field($field_name, $field_value) {
        $field_name_lower = strtolower($field_name);
        
        // URL detection
        if (strpos($field_name_lower, 'url') !== false || strpos($field_name_lower, 'website') !== false || 
            strpos($field_name_lower, 'link') !== false || filter_var($field_value, FILTER_VALIDATE_URL)) {
            return true;
        }
        
        return false;
    }
    
    private function is_textarea_field($field_name, $field_value) {
        $field_name_lower = strtolower($field_name);
        
        //skip if field name is action,id,form_nonce,maspik_spam_key,full-name-maspik-hp
        if (in_array($field_name, ['action', 'id', 'form_nonce', 'maspik_spam_key', 'full-name-maspik-hp'])) {
            return false;
        }
        
        // Textarea detection (longer text)
        if (strlen($field_value) > 100 || strpos($field_name_lower, 'message') !== false || 
            strpos($field_name_lower, 'comment') !== false || strpos($field_name_lower, 'description') !== false) {
            return true;
        }
        
        return false;
    }
    

}

// Initialize the class
new MaspikMetFormValidation(); 