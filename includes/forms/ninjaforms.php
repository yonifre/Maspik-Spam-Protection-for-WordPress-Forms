<?php
// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

// Function to check if a type contains any word in the given array
function maspik_if_contains_string_in_array($type, $to_check_array) {
    foreach ($to_check_array as $word) {
        if (strpos($type, $word) !== false || $type == $word) {
            return true;
        }
    }
    return false;
}

/**
 * Flatten Ninja Forms fields into key => value array
 *
 * @param array $nf_fields Raw Ninja Forms fields array
 * @return array
 */
function maspik_ninjaforms_flatten_fields( array $nf_fields ): array {
	$flat = [];
	foreach ( $nf_fields as $field ) {
		if ( ! is_array( $field ) ) {
			continue;
		}
		$key = isset($field['key']) && $field['key'] !== '' ? (string) $field['key'] : ( isset($field['id']) ? (string) $field['id'] : '' );
		if ( $key === '' ) {
			continue;
		}
		// Skip internal keys
		if ( in_array( $key, [ 'submit', 'maspik_spam_key' ], true ) ) {
			continue;
		}
		$value = isset($field['value']) ? $field['value'] : '';
		if ( is_array( $value ) ) {
			$value = implode( ' ', array_map( 'strval', $value ) );
		}
		$value = sanitize_text_field( (string) $value );
		if ( $value === '' ) {
			continue;
		}
		$flat[$key] = $value;
	}
	return $flat;
}

/**
 * Main Ninja Forms validation functions
 *
 */

add_filter( 'ninja_forms_submit_data', 'my_ninja_forms_submit_data' );

function my_ninja_forms_submit_data( $form_data ) {
    
    $to_check_text = array("name", "text", "single-line");
    $to_check_tel = array("tel", "phone");
    $to_check_email = array("email", "contact");
    $to_check_textarea = array("textarea", "textbox","message");

    $spam = false;
    $reason ="";
    // ip
    $ip =  maspik_get_real_ip();

    $fields = isset($form_data['fields']) && is_array($form_data['fields']) ? $form_data['fields'] : array();
    $new_fields = maspik_ninjaforms_flatten_fields($fields);

    // Collect relevant content fields for AI (based on the same key heuristics)
    $content_fields = array();
    foreach ( $new_fields as $key => $value ) {
        if (
            maspik_if_contains_string_in_array( $key, $to_check_text ) ||
            maspik_if_contains_string_in_array( $key, $to_check_email ) ||
            maspik_if_contains_string_in_array( $key, $to_check_tel ) ||
            maspik_if_contains_string_in_array( $key, $to_check_textarea )
        ) {
            $content_fields[ $key ] = $value;
        }
    }

    // General check (Country/IP, honeypot, spam key, AI Matrix, etc.)
    $GeneralCheck = GeneralCheck($ip,$spam,$reason,$new_fields,"ninjaforms", $content_fields);
    $spam = isset($GeneralCheck['spam']) ? $GeneralCheck['spam'] : false ;
    $reason = isset($GeneralCheck['reason']) ? $GeneralCheck['reason'] : false ;
    $message = isset($GeneralCheck['message']) ? $GeneralCheck['message'] : false ;
    $spam_val = isset($GeneralCheck['value']) ? $GeneralCheck['value'] : false ;
    $type = isset($GeneralCheck['type']) ? $GeneralCheck['type'] : 'General';

    // First field key for error placement (fallback to first array key or 0)
    $field_keys = array_keys($fields);
    $first_key = !empty($field_keys) ? $field_keys[0] : 0;

    if ( $spam ) {
        efas_add_to_log($type, $reason, $new_fields, "Ninja Forms", $message, $spam_val);
        $form_data['errors']['fields'][$first_key] = __('General: ', 'contact-forms-anti-spam') . cfas_get_error_text($message);
        return $form_data;
    }
    
    
    foreach( $form_data[ 'fields' ] as $current ) {
        
        $field_id    = $current[ 'id' ];
        $current_type   = $current[ 'key' ] ? $current[ 'key' ] : "none";

        $field_value = is_array($current['value'])  ?  strtolower( implode( " ", $current['value'] ) ) : strtolower( $current['value'] ) ; 
        
        // Skiping empty fields
        if( empty($field_value) ) continue;

            
        // Validate Text Field
        if ( maspik_if_contains_string_in_array($current_type, $to_check_text) ) {

            $validateTextField = validateTextField($field_value);

            $spam = isset($validateTextField['spam']) ? $validateTextField['spam'] : 0;
            if($spam) {
                $message = $validateTextField['message'];
                $spam_lbl = isset($validateTextField['label']) ? $validateTextField['label'] : 0 ;
                $spam_val = isset($validateTextField['option_value']) ? $validateTextField['option_value'] : 0 ;

                efas_add_to_log($type = "text",$spam, $fields, "Ninja Forms", $spam_lbl, $spam_val);           
                $form_data['errors']['fields'][$field_id] = cfas_get_error_text($message);
                return $form_data;
            }
        }
        
        //Email
        if ( maspik_if_contains_string_in_array($current_type, $to_check_email) ) {
            $spam = checkEmailForSpam($field_value);
            $spam_val = $field_value;
            if($spam) {
                efas_add_to_log($type = "email", $spam, $fields, "Ninja Forms", "emails_blacklist", $spam_val);
                $form_data['errors']['fields'][$field_id] = cfas_get_error_text();
                return $form_data;
            }
        }
        // Phone
        if ( maspik_if_contains_string_in_array($current_type, $to_check_tel) ) {
            $checkTelForSpam = checkTelForSpam($field_value);
            $reason = isset($checkTelForSpam['reason']) ? $checkTelForSpam['reason'] : 0 ;      
            $valid = isset($checkTelForSpam['valid']) ? $checkTelForSpam['valid'] : "yes" ;   
            $message = isset($checkTelForSpam['message']) ? $checkTelForSpam['message'] : 0 ;  
            $spam_lbl = isset($checkTelForSpam['label']) ? $checkTelForSpam['label'] : 0 ;
            $spam_val = isset($checkTelForSpam['option_value']) ? $checkTelForSpam['option_value'] : 0 ;

            if(!$valid) {
                $message = $checkTelForSpam['message'];
                efas_add_to_log($type = "tel","Phone number <b>$field_value</b> not feet the given format ($reason)", $fields, "Ninja Forms", $spam_lbl, $spam_val);
                $form_data['errors']['fields'][$field_id] = cfas_get_error_text($message);
                return $form_data;
            }
        }
        
        // Textarea
        if ( maspik_if_contains_string_in_array($current_type, $to_check_textarea) ) {
            $checkTextareaForSpam = checkTextareaForSpam($field_value);
            $spam = isset($checkTextareaForSpam['spam']) ? $checkTextareaForSpam['spam'] : 0;
            if($spam) {
                $message = isset($checkTextareaForSpam['message']) ? $checkTextareaForSpam['message'] : 0;
                $spam_lbl = isset($checkTextareaForSpam['label']) ? $checkTextareaForSpam['label'] : 0 ;
                $spam_val = isset($checkTextareaForSpam['option_value']) ? $checkTextareaForSpam['option_value'] : 0 ;

                efas_add_to_log($type = "textarea",$spam, $fields, "Ninja Forms", $spam_lbl, $spam_val);
                $form_data['errors']['fields'][$field_id] = cfas_get_error_text($message);
                return $form_data;
            }
        }
        
    // end foreach   
    }

	return $form_data;
}



function add_custom_html_to_ninja_forms( $form_id, $settings, $form_fields ) {
    
    if ( efas_get_spam_api('maspikHoneypot', 'bool') || efas_get_spam_api('maspikTimeCheck', 'bool') || maspik_get_settings('maspikYearCheck') ) {
        $custom_html = "";

        if (efas_get_spam_api('maspikHoneypot', 'bool')) {
            $custom_html .= '<div class="ninja-forms-field maspik-field">
                <label for="full-name-maspik-hp" class="ninja-forms-field-label">' . esc_html( maspik_honeypot_aria_label() ) . '</label>
                <input size="1" type="text" autocomplete="off" aria-hidden="true" tabindex="-1" aria-label="' . esc_attr( maspik_honeypot_aria_label() ) . '" name="full-name-maspik-hp" id="full-name-maspik-hp" class="ninja-forms-field-element" placeholder="' . esc_attr( maspik_honeypot_aria_label() ) . '">
            </div>';
        }

        if (maspik_get_settings('maspikYearCheck')) {
            $custom_html .= '<div class="ninja-forms-field maspik-field">
                <label for="Maspik-currentYear" class="ninja-forms-field-label">' . esc_html( maspik_honeypot_aria_label() ) . '</label>
                <input size="1" type="text" autocomplete="off" aria-hidden="true" tabindex="-1" aria-label="' . esc_attr( maspik_honeypot_aria_label() ) . '" name="Maspik-currentYear" id="Maspik-currentYear" class="ninja-forms-field-element" placeholder="">
            </div>';
        }

        echo $custom_html;
    }
}
add_action( 'ninja_forms_before_container', 'add_custom_html_to_ninja_forms', 10, 3 );
