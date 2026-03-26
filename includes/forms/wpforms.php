<?php
// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}
/**
 * Main Wpforms validation functions file
 *
 */


/*
 * Check the form validtion.
*/ 

add_action('wpforms_process_before', function( $entry, $form_data ) {
  $error_message = cfas_get_error_text();
  $spam = false;
  $fields = isset($form_data['fields']) && is_array($form_data['fields']) ? $form_data['fields'] : array();
  $reversed = array_reverse($fields);
  $last = !empty($reversed) ? $reversed[0] : null;

  // ip
  $ip = maspik_get_real_ip();
  $reason = "";

  // Collect relevant content fields for AI (text, name, email, phone, textarea)
  $content_fields = array();

  if ( isset( $entry['fields'] ) && is_array( $entry['fields'] ) ) {
      foreach ( $entry['fields'] as $field_id => $field_entry ) {
          $field_type = '';
          if ( isset( $fields[ $field_id ]['type'] ) ) {
              $field_type = $fields[ $field_id ]['type'];
          } elseif ( isset( $field_entry['type'] ) ) {
              $field_type = $field_entry['type'];
          }

          if ( empty( $field_type ) ) {
              continue;
          }

          // Only care about textual content fields
          if ( ! in_array( $field_type, array( 'text', 'name', 'email', 'phone', 'textarea' ), true ) ) {
              continue;
          }

          $value = isset( $field_entry['value'] ) ? $field_entry['value'] : '';
          if ( is_array( $value ) ) {
              $value = implode( ' ', array_map( 'strval', $value ) );
          }
          $value = sanitize_text_field( (string) $value );

          if ( $value === '' ) {
              continue;
          }

          $key = isset( $fields[ $field_id ]['label'] ) && $fields[ $field_id ]['label'] !== ''
              ? (string) $fields[ $field_id ]['label']
              : (string) $field_id;

          $content_fields[ $key ] = $value;
      }
  }

  // Add key 'maspik_spam_key' with value from $_POST['maspik_spam_key'] if exists to the entry fields
  $all_fields = array_merge($entry['fields'], isset($_POST['maspik_spam_key']) ? ['maspik_spam_key' => $_POST['maspik_spam_key']] : []);

    // General check (Country/IP, honeypot, spam key, AI Matrix, etc.)
    $GeneralCheck = GeneralCheck($ip,$spam,$reason,$all_fields,"wpforms", $content_fields);
    $spam = isset($GeneralCheck['spam']) ? $GeneralCheck['spam'] : false ;
    $reason = isset($GeneralCheck['reason']) ? $GeneralCheck['reason'] : false ;
    $message = isset($GeneralCheck['message']) ? $GeneralCheck['message'] : false ;
    $spam_val = isset($GeneralCheck['value']) ? $GeneralCheck['value'] : false ;
    $type = isset($GeneralCheck['type']) ? $GeneralCheck['type'] : 'General';

    if ( $spam ) {
    efas_add_to_log($type, $reason, $entry['fields'], "Wpforms", $message, $spam_val);
    if ($last && isset($last['id'])) {
        wpforms()->process->errors[ $form_data['id'] ][ $last['id'] ] = cfas_get_error_text($message);
    }
      return;
  }
  
  
}, 10, 2);


/*
 * Check the single line text field.
*/ 
add_action( 'wpforms_process_validate_text', 'cfas_validate_wpforms_text_name', 10, 3);
add_action( 'wpforms_process_validate_name', 'cfas_validate_wpforms_text_name', 10, 3);
function cfas_validate_wpforms_text_name( $field_id, $field_submit, $form_data ) {
    $field_submit = is_array($field_submit) ?  implode(" ",$field_submit) : $field_submit;
  	$field_value = strtolower($field_submit) ; 

    if ( empty( $field_value ) ) {
      return;
    }

    $validateTextField = validateTextField($field_value);
    $spam = isset($validateTextField['spam']) ? $validateTextField['spam'] : 0;
    $message = isset($validateTextField['message']) ?  $validateTextField['message'] : 0;
    $spam_lbl = isset($validateTextField['label']) ? $validateTextField['label'] : 0 ;
    $spam_val = isset($validateTextField['option_value']) ? $validateTextField['option_value'] : 0 ;

    if($spam ) {
      efas_add_to_log($type = "text/name","$spam", $_POST, "Wpforms", $spam_lbl, $spam_val);          
      wpforms()->process->errors[ $form_data['id'] ][ $field_id ] = cfas_get_error_text($message);
      return;
    }
}


/*
 * Check the email field.
*/ 
add_action( 'wpforms_process_validate_email', function( $field_id, $field_submit, $form_data ) {
  	$field_value = strtolower($field_submit); 
    if(!$field_value){
      return;
    }
	$spam = checkEmailForSpam($field_value);
  $spam_val = $field_value;
    if( $spam) {
      $error_message = cfas_get_error_text();
      efas_add_to_log($type = "email", $spam, $_POST, "Wpforms", "emails_blacklist", $spam_val);
      wpforms()->process->errors[ $form_data['id'] ][ $field_id ] = $error_message;
    }
}, 10, 3 );

/*
 * Check the phone field.
*/ 
add_action( 'wpforms_process_validate_phone', function( $field_id, $field_submit, $form_data ) {
  	$field_value = strtolower($field_submit); 
    if ( empty( $field_value ) ) {
        return false; // Not spam if the field is empty or no formats are provided.
    }
  	$checkTelForSpam = checkTelForSpam($field_value);
 	  $reason = isset($checkTelForSpam['reason']) ? $checkTelForSpam['reason'] : 0 ;      
 	  $valid = isset($checkTelForSpam['valid']) ? $checkTelForSpam['valid'] : "yes" ;   
    $message = isset($checkTelForSpam['message']) ? $checkTelForSpam['message'] : 0 ;  
    $spam_lbl = isset($checkTelForSpam['label']) ? $checkTelForSpam['label'] : 0 ;
    $spam_val = isset($checkTelForSpam['option_value']) ? $checkTelForSpam['option_value'] : 0 ;
  
    if(!$valid){
         efas_add_to_log($type = "tel", $reason, $_POST, "Wpforms", $spam_lbl, $spam_val);
      	 wpforms()->process->errors[ $form_data['id'] ][ $field_id ] = cfas_get_error_text($message);
      }
}, 10, 3 );


/*
 * Check the textarea field.
*/ 
add_action( 'wpforms_process_validate_textarea', function( $field_id, $field_submit, $form_data ) {
  	$field_value = strtolower($field_submit); 

    if(!$field_value){
      return;
    }
    $checkTextareaForSpam = checkTextareaForSpam($field_value);
    $spam = isset($checkTextareaForSpam['spam']) ? $checkTextareaForSpam['spam'] : 0;
    $message = isset($checkTextareaForSpam['message']) ? $checkTextareaForSpam['message'] : 0;
    $spam_lbl = isset($checkTextareaForSpam['label']) ? $checkTextareaForSpam['label'] : 0 ;
    $spam_val = isset($checkTextareaForSpam['option_value']) ? $checkTextareaForSpam['option_value'] : 0 ;

    if ( $spam ) {
          efas_add_to_log($type = "textarea", $spam , $_POST, "Wpforms", $spam_lbl, $spam_val);
          wpforms()->process->errors[ $form_data['id'] ][ $field_id ] = cfas_get_error_text($message);
    }

}, 10, 3 );


add_filter('wpforms_display_submit_before', 'add_maspikhp_html_to_wpforms' );
function add_maspikhp_html_to_wpforms() {
    if ( !is_admin() ){

        if (efas_get_spam_api('maspikHoneypot', 'bool')) {
            $honeypot_name = maspik_HP_name();
            echo  '<div class="wpforms-field wpforms-field-name maspik-field">
                <label for="' . esc_attr( $honeypot_name ) . '" class="wpforms-field-label">' . esc_html( maspik_honeypot_aria_label() ) . '</label>
                <input size="1" type="text" autocomplete="off" aria-hidden="true" tabindex="-1" aria-label="' . esc_attr( maspik_honeypot_aria_label() ) . '" name="' . esc_attr( $honeypot_name ) . '" id="' . esc_attr( $honeypot_name ) . '" class="wpforms-field-medium" placeholder="' . esc_attr( maspik_honeypot_aria_label() ) . '">
            </div>';
        }

        if (maspik_get_settings('maspikYearCheck')) {
            echo  '<div class="wpforms-field wpforms-field-name maspik-field">
                <label for="Maspik-currentYear" class="wpforms-field-label">' . esc_html( maspik_honeypot_aria_label() ) . '</label>
                <input size="1" type="text" autocomplete="off" aria-hidden="true" tabindex="-1" aria-label="' . esc_attr( maspik_honeypot_aria_label() ) . '" name="Maspik-currentYear" id="Maspik-currentYear" class="wpforms-field-medium" placeholder="">
            </div>';
        }
    }
}

