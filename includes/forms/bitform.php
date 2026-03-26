<?php
// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Main BitForm validation functions
 *
 * DEVELOPER HOOK: maspik_disable_bitform_spam_check
 * 
 * This filter allows developers to disable spam check for specific BitForm forms.
 * 
 * @param bool   $disable     Whether to disable spam check (default: false)
 * @param int    $form_id     ID of the BitForm
 * @param array  $form_data   Form submission data
 * @return bool  True to disable spam check, false to proceed with spam check
 * 
 * USAGE EXAMPLES:
 * 
 * 1. Disable spam check for specific form by ID:
 * add_filter('maspik_disable_bitform_spam_check', function($disable, $form_id, $form_data) {
 *     if ($form_id === 123) {
 *         return true; // Disable spam check for this form
 *     }
 *     return $disable;
 * }, 10, 3);
 * 
 * 2. Disable spam check for multiple forms:
 * add_filter('maspik_disable_bitform_spam_check', function($disable, $form_id, $form_data) {
 *     $excluded_forms = [123, 456, 789];
 *     if (in_array($form_id, $excluded_forms)) {
 *         return true;
 *     }
 *     return $disable;
 * }, 10, 3);
 * 
 * 3. Disable spam check for logged-in administrators:
 * add_filter('maspik_disable_bitform_spam_check', function($disable, $form_id, $form_data) {
 *     if (is_user_logged_in() && current_user_can('administrator')) {
 *         return true;
 *     }
 *     return $disable;
 * }, 10, 3);
 */

/**
 * Validate BitForm for spam using the filter hook
 * 
 * @param bool $validated - Current validation status
 * @param int $form_id - BitForm ID
 * @return bool - False if spam detected, true otherwise
 */
function maspik_validate_bitform_for_spam($validated, $form_id) {
    //
    //error_log('Maspik BitForm: Starting validation for form ID: ' . $form_id);
    //error_log('Maspik BitForm: POST data: ' . print_r($_POST, true));
    
    // If already invalid, don't check further
    if (!$validated) {
        return $validated;
    }
    
    // Allow developers to disable spam check for specific forms
    $disable_bitform_spam_check = apply_filters('maspik_disable_bitform_spam_check', false, $form_id, $_POST);
    if ($disable_bitform_spam_check) {
        return $validated;
    }
    
    // Try to get BitForm fields using their API
    try {
        if (class_exists('\BitCode\BitForm\Core\Form\FormManager')) {
            $formManager = new \BitCode\BitForm\Core\Form\FormManager($form_id);
            $fields = $formManager->getFields();
            
            /* Debug: Print the fields structure safely
            if (is_array($fields)) {
                error_log('Maspik BitForm: Fields structure: ' . print_r($fields, true));
            } else {
                error_log('Maspik BitForm: Fields is not an array: ' . gettype($fields));
            }*/
        } else {
            //error_log('Maspik BitForm: FormManager class not found');
        }
    } catch (Exception $e) {
        //error_log('Maspik BitForm: Error getting fields: ' . $e->getMessage());
    } catch (Error $e) {
        //error_log('Maspik BitForm: Fatal error getting fields: ' . $e->getMessage());
    }
    
    // Get form data from POST
    $form_data = $_POST;
    
    // Check for spam
    $spam_message = maspik_check_bitform_for_spam($form_data, $form_id);
    if ($spam_message) {
        // Get the specific error message based on spam type
        $error_message = $spam_message;
        return new WP_Error('spam_detection', $error_message);
    }
    
    return $validated; // Keep the current validation status
}

/**
 * Simple spam check function - checks only relevant fields
 * 
 * @param array $form_data - Form submission data
 * @param int $form_id - Form ID
 * @return string|false - Spam type if detected, false otherwise
 */
function maspik_check_bitform_for_spam($form_data, $form_id) {
    
    // Get field types from BitForm API
    try {
        if (class_exists('\BitCode\BitForm\Core\Form\FormManager')) {
            $formManager = new \BitCode\BitForm\Core\Form\FormManager($form_id);
            $fields = $formManager->getFields();
            
            if (is_array($fields)) {
                // Collect relevant content fields for AI
                $content_fields = array();

                // Check each field based on its actual type
                foreach ($form_data as $field_key => $field_value) {
                    // Skip internal BitForm fields
                    if (strpos($field_key, '_') === 0 || in_array($field_key, ['form_id', 'action', 'nonce'])) {
                        continue;
                    }
                    
                    if (empty($field_value)) {
                        continue;
                    }
                    
                    // Get field type from our API data
                    if (isset($fields[$field_key]['type'])) {
                        $field_type = $fields[$field_key]['type'];
                        
                        // Convert array values to string for validation
                        if (is_array($field_value)) {
                            $field_value = implode(' ', array_filter(array_map('strval', $field_value)));
                        }
                        
                        $field_value = strtolower(sanitize_text_field($field_value));
                        
                        // Check based on field type
                        switch ($field_type) {
                            case 'text':
                            case 'name':
                                $validateTextField = validateTextField($field_value);
                                $spam = isset($validateTextField['spam']) ? $validateTextField['spam'] : false;
                                $message = isset($validateTextField['message']) ? $validateTextField['message'] : 0;
                                if ($spam) {
                                    $error_message = cfas_get_error_text($message);
                                    $spam_lbl = isset($validateTextField['label']) ? $validateTextField['label'] : '';
                                    $spam_val = isset($validateTextField['option_value']) ? $validateTextField['option_value'] : '';
                                    efas_add_to_log('text', $spam, $form_data, 'BitForm', $spam_lbl, $spam_val);
                                    return $error_message;
                                }
                                // Add to AI content fields if valid
                                $content_fields[ $field_key ] = $field_value;
                                break;
                                
                            case 'email':
                                $spam = checkEmailForSpam($field_value);
                                $spam_val = $field_value;
                                if ($spam) {
                                    $error_message = cfas_get_error_text("emails_blacklist");
                                    efas_add_to_log('email', $spam, $form_data, 'BitForm', 'emails_blacklist', $spam_val);
                                    return $error_message;
                                }
                                // Add to AI content fields if valid
                                $content_fields[ $field_key ] = $field_value;
                                break;
                                
                            case 'phone-number':
                            case 'phone':
                            case 'tel':
                                $checkTelForSpam = checkTelForSpam($field_value);
                                $valid = isset($checkTelForSpam['valid']) ? $checkTelForSpam['valid'] : true;
                                
                                if (!$valid) {
                                    $reason = isset($checkTelForSpam['reason']) ? $checkTelForSpam['reason'] : '';
                                    $spam_lbl = isset($checkTelForSpam['label']) ? $checkTelForSpam['label'] : '';
                                    $spam_val = isset($checkTelForSpam['option_value']) ? $checkTelForSpam['option_value'] : '';
                                    $message = isset($checkTelForSpam['message']) ? $checkTelForSpam['message'] : 0;
                                    $error_message = cfas_get_error_text($message);
                                    efas_add_to_log('tel', $reason, $form_data, 'BitForm', $spam_lbl, $spam_val);
                                    return $error_message;
                                }
                                // Add to AI content fields if valid
                                if ( $valid ) {
                                    $content_fields[ $field_key ] = $field_value;
                                }
                                break;
                                
                            case 'url':
                            case 'website':
                            case 'link':

                                $checkUrlForSpam = checkUrlForSpam($field_value);
                                $spam = isset($checkUrlForSpam['spam']) ? $checkUrlForSpam['spam'] : 0;
                                $message = isset($checkUrlForSpam['message']) ? $checkUrlForSpam['message'] : 0;
                                $spam_lbl = isset($checkUrlForSpam['label']) ? $checkUrlForSpam['label'] : 0;
                                $spam_val = isset($checkUrlForSpam['option_value']) ? $checkUrlForSpam['option_value'] : 0;
                                                                
                                if ($spam) {
                                    $error_message = cfas_get_error_text($message);
                                    efas_add_to_log('url', $spam, $form_data, 'BitForm', $spam_lbl, $spam_val);
                                    return $error_message;
                                }
                                break;
                                
                            case 'textarea':
                                $validateTextField = checkTextareaForSpam($field_value);
                                $spam = isset($validateTextField['spam']) ? $validateTextField['spam'] : false;
                                
                                if ($spam) {
                                    $message = isset($validateTextField['message'])? $validateTextField['message'] : 0;
                                    $spam_lbl = isset($validateTextField['label']) ? $validateTextField['label'] : '';
                                    $spam_val = isset($validateTextField['option_value']) ? $validateTextField['option_value'] : '';
                                    efas_add_to_log('textarea', $spam, $form_data, 'BitForm', $spam_lbl, $spam_val);
                                    $error_message = cfas_get_error_text($message);
                                    return $error_message;
                                }
                                // Add to AI content fields if valid
                                $content_fields[ $field_key ] = $field_value;
                                break;
                                
                            default:
                                // Skip all other field types
                                break;
                        }
                    }
                }
            }
        }
    } catch (Exception $e) {
        //error_log('Maspik BitForm: Error checking fields: ' . $e->getMessage());
    } catch (Error $e) {
        //error_log('Maspik BitForm: Fatal error checking fields: ' . $e->getMessage());
    }
    
    // General check (Country/IP, Honeypot, Time, Year)
    $ip = maspik_get_real_ip();
    $spam = false;
    $reason = '';
    
    // If we built content_fields above, reuse it; otherwise fall back to full form_data.
    if ( ! isset( $content_fields ) || ! is_array( $content_fields ) ) {
        $content_fields = $form_data;
    }
    
    $GeneralCheck = GeneralCheck($ip, $spam, $reason, $form_data, 'bitform', $content_fields);
    $spam = isset($GeneralCheck['spam']) ? $GeneralCheck['spam'] : false;
    
    if ($spam) {
        $reason = isset($GeneralCheck['reason']) ? $GeneralCheck['reason'] : '';
        $message = isset($GeneralCheck['message']) ? $GeneralCheck['message'] : '';
        $spam_val = isset($GeneralCheck['value']) ? $GeneralCheck['value'] : '';
        $type = isset($GeneralCheck['type']) ? $GeneralCheck['type'] : 'General';
        $error_message = cfas_get_error_text($message);
        efas_add_to_log($type, $reason, $form_data, 'BitForm', $message, $spam_val);
        return $error_message;
    }
    
    return false;
}

// Hook into BitForm validation
add_filter('bitform_filter_form_validation', 'maspik_validate_bitform_for_spam', 10, 2);


// Add Maspik fields to BitForm
add_action('wp_footer', 'maspik_add_bitform_fields', 100);

function maspik_add_bitform_fields() {
    // if honeypot or key check is not active, don't add fields
    if( efas_get_spam_api('maspikHoneypot', 'bool') || efas_get_spam_api('maspikTimeCheck', 'bool') ){


        $spam_key = maspik_get_spam_key();
        $honeypot_field = maspik_HP_name();
        ?>
        <script>
        jQuery(document).ready(function($) {
            // Add honeypot field to all BitForm forms
            $('.bit-form form').each(function() {
                var $form = $(this);
                
                // Check if honeypot field already exists
                if ($form.find('.maspik-honeypot').length === 0) {
                    // Add honeypot field
                    var honeypotField = '<div class="maspik-field" style="position: absolute; left: -99999px; top: -99999px; display: none;">' +
                        '<input type="text" name="<?php echo esc_attr( $honeypot_field ); ?>" value="" style="display: none;" aria-label="<?php echo esc_attr( maspik_honeypot_aria_label() ); ?>" />' +
                        '</div>';
                    
                    $form.append(honeypotField);
                    
                    // Add spam key field
                    var spamKeyField = '<input type="hidden" name="maspik_spam_key" value="<?php echo esc_attr( $spam_key ); ?>" aria-hidden="true" />';
                    $form.append(spamKeyField);
                    
                    //console.log('Maspik fields added to BitForm');
                }
            });
            
            // Also try to add fields to forms that might be loaded dynamically
            $(document).on('bitform_form_loaded', function() {
                $('.bit-form form').each(function() {
                    var $form = $(this);
                    
                    if ($form.find('.maspik-honeypot').length === 0) {
                        var honeypotField = '<div class="maspik-field" style="position: absolute; left: -99999px; top: -99999px; display: none;">' +
                            '<input type="text" name="<?php echo esc_attr( $honeypot_field ); ?>" value="" style="display: none;" aria-label="<?php echo esc_attr( maspik_honeypot_aria_label() ); ?>" />' +
                            '</div>';
                        
                        $form.append(honeypotField);
                        
                        var spamKeyField = '<input type="hidden" name="maspik_spam_key" value="<?php echo esc_attr( $spam_key ); ?>" aria-hidden="true" />';
                        $form.append(spamKeyField);
                        
                        //console.log('Maspik fields added to dynamically loaded BitForm');
                    }
                });
            });
        });
        </script>
        <?php
    }// end if honeypot or key check is not active
}