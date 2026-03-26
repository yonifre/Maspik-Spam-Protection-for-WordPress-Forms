<?php
// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}
// Fluent Forms hook file


add_filter('fluentform/validation_errors', 'maspik_validate_fluentform_general', 10, 4);
function maspik_validate_fluentform_general( $errors, $formData, $form, $fields){
    
  $spam = false;
  $reason ="";
  // ip
  $ip =  maspik_get_real_ip();

  // Parse the query string data from Fluent Forms
  $parsed_data = array();
  if (isset($_POST['data']) && is_string($_POST['data'])) {
      parse_str($_POST['data'], $parsed_data);
      // Drop top-level keys whose name contains these fragments (substring, case-insensitive).
      $skip_key_fragments = array( 'hidden', 'radio', 'checkbox', 'select' );
      $parsed_data = array_filter(
          $parsed_data,
          function ( $value, $key ) use ( $skip_key_fragments ) {
              $key = (string) $key;
              foreach ( $skip_key_fragments as $frag ) {
                  if ( stripos( $key, $frag ) !== false ) {
                      return false;
                  }
              }
              return true;
          },
          ARRAY_FILTER_USE_BOTH
      );
  }

  // Remove fields that start with underscore
  $parsed_data = array_filter($parsed_data, function($value, $key) {
      return strpos($key, '_') !== 0;
  }, ARRAY_FILTER_USE_BOTH);


  // General check (Country/IP, honeypot, spam key, AI Matrix, etc.)
  // Use parsed_data both as full post data and as content fields base for AI.
  $GeneralCheck = GeneralCheck($ip,$spam,$reason,$parsed_data,"fluentforms", $parsed_data);
  $spam = isset($GeneralCheck['spam']) ? $GeneralCheck['spam'] : false ;
  $reason = isset($GeneralCheck['reason']) ? $GeneralCheck['reason'] : false ;
  $message = isset($GeneralCheck['message']) ? $GeneralCheck['message'] : false ;
  $spam_val = isset($GeneralCheck['value']) ? $GeneralCheck['value'] : false ;
  $type = isset($GeneralCheck['type']) ? $GeneralCheck['type'] : 'General';

  if ( $spam) {
    efas_add_to_log($type, $reason, $parsed_data, "Fluent Forms", $message, $spam_val);
    $errors['spam'] = cfas_get_error_text($message);
  }
return $errors;
}


// Add custom validation for Fluentforms text fields
function maspik_validate_fluentforms_text($errorMessage, $field, $formData, $fields, $form){
    $fieldName = $field['name'];
    if (empty($formData[$fieldName])) {
        return $errorMessage;
    }
    $field_value = is_array($formData[$fieldName])  ?  strtolower( implode( " ", $formData[$fieldName] ) ) : strtolower( $formData[$fieldName] ) ; 

	$validateTextField = validateTextField($field_value);
    $spam = isset($validateTextField['spam']) ? $validateTextField['spam'] : 0;
    $message = isset($validateTextField['message']) ? $validateTextField['message'] : '';
    $spam_lbl = isset($validateTextField['label']) ? $validateTextField['label'] : 0 ;
    $spam_val = isset($validateTextField['option_value']) ? $validateTextField['option_value'] : 0 ;

    if( $spam ) {
      $error_message = cfas_get_error_text($message);
      efas_add_to_log($type = "text",$spam, $formData, "Fluent Forms", $spam_lbl, $spam_val);          
      $errorMessage = $error_message;
    }
    
	return $errorMessage;
}
add_filter('fluentform/validate_input_item_input_text', 'maspik_validate_fluentforms_text', 10, 5);


// Add custom validation for fluentforms email fields
function maspik_validate_fluentforms_email($errorMessage, $field, $formData, $fields, $form){
    $fieldName = $field['name'];
    if (empty($formData[$fieldName])) {
        return $errorMessage;
    }
    $field_value = strtolower( $formData[$fieldName]); 

    $spam = checkEmailForSpam($field_value);
    $spam_val = $field_value;

   if( $spam ) {
      $error_message = cfas_get_error_text();
      efas_add_to_log($type = "email", $spam, $formData, "Fluent Forms", "emails_blacklist", $spam_val);
      $errorMessage = $error_message;
   }
   return $errorMessage;
}
add_filter('fluentform/validate_input_item_input_email', 'maspik_validate_fluentforms_email', 10, 5);

// Add custom validation for Tel fields
function maspik_validate_fluentforms_tel($errorMessage, $field, $formData, $fields, $form){
    $fieldName = $field['name'];
    if (empty($formData[$fieldName])) {
        return $errorMessage;
    }
    $field_value = strtolower( $formData[$fieldName]); 
  
  	$checkTelForSpam = checkTelForSpam($field_value);
 	  $reason = isset($checkTelForSpam['reason']) ? $checkTelForSpam['reason'] : 0 ;      
 	  $valid = isset($checkTelForSpam['valid']) ? $checkTelForSpam['valid'] : "yes" ;   
    $message = isset($checkTelForSpam['message']) ? $checkTelForSpam['message'] : 0 ;  
    $spam_lbl = isset($checkTelForSpam['label']) ? $checkTelForSpam['label'] : 0 ;
    $spam_val = isset($checkTelForSpam['option_value']) ? $checkTelForSpam['option_value'] : 0 ;

  	if(!$valid){
        efas_add_to_log($type = "tel",$reason , $formData, "Fluent Forms", $spam_lbl, $spam_val);
        $errorMessage = cfas_get_error_text($message);  
    } 

   return $errorMessage;
}
add_filter('fluentform/validate_input_item_phone', 'maspik_validate_fluentforms_tel', 10, 5);


// Add custom validation for fluentforms textarea fields
function maspik_validate_fluentforms_textarea($errorMessage, $field, $formData, $fields, $form){
    $fieldName = $field['name'];
    if (empty($formData[$fieldName])) {
        return $errorMessage;
    }
    $field_value = strtolower( $formData[$fieldName]); 

    $error_message = cfas_get_error_text(); 
    $checkTextareaForSpam = checkTextareaForSpam($field_value);
    $spam = isset($checkTextareaForSpam['spam']) ? $checkTextareaForSpam['spam'] : 0;
    $message = isset($checkTextareaForSpam['message']) ? $checkTextareaForSpam['message'] : 0;
    $spam_lbl = isset($checkTextareaForSpam['label']) ? $checkTextareaForSpam['label'] : 0 ;
    $spam_val = isset($checkTextareaForSpam['option_value']) ? $checkTextareaForSpam['option_value'] : 0 ;

    if ( $spam ) {
      efas_add_to_log($type = "textarea",$spam, $formData, "Fluent Forms", $spam_lbl, $spam_val);
      return $errorMessage = cfas_get_error_text($message); 
    }

	return $errorMessage;
}
add_filter('fluentform/validate_input_item_textarea', 'maspik_validate_fluentforms_textarea', 10, 5);



// add Maspik Honeypot fields to fluentform
add_filter('fluentform/rendering_form', function($form){
    
    $last_field = end($form->fields['fields']);
    // Retrieve the element and index values
    $last_element = isset($last_field['element']) ? $last_field['element'] : null;
    $last_index = isset($last_field['index']) ? $last_field['index'] : null;
    
    add_filter("fluentform/rendering_field_html_$last_element", function ($html, $data, $form) {
        
        if ( efas_get_spam_api('maspikHoneypot', 'bool') || efas_get_spam_api('maspikTimeCheck', 'bool') || maspik_get_settings('maspikYearCheck') ) {
            $custom_html = "";

            if (efas_get_spam_api('maspikHoneypot', 'bool')) {
                $custom_html .= '<div class="ff-el-group maspik-field">
                    <label for="full-name-maspik-hp" class="ff-el-input--label">' . esc_html( maspik_honeypot_aria_label() ) . '</label>
                    <input size="1" type="text" autocomplete="off" aria-hidden="true" tabindex="-1" aria-label="' . esc_attr( maspik_honeypot_aria_label() ) . '" name="full-name-maspik-hp" id="full-name-maspik-hp" class="ff-el-form-control" placeholder="' . esc_attr( maspik_honeypot_aria_label() ) . '">
                </div>';
            }

            if (maspik_get_settings('maspikYearCheck')) {
                $custom_html .= '<div class="ff-el-group maspik-field">
                    <label for="Maspik-currentYear" class="ff-el-input--label">' . esc_html( maspik_honeypot_aria_label() ) . '</label>
                    <input size="1" type="text" autocomplete="off" aria-hidden="true" tabindex="-1" aria-label="' . esc_attr( maspik_honeypot_aria_label() ) . '" name="Maspik-currentYear" id="Maspik-currentYear" class="ff-el-form-control" placeholder="">
                </div>';
            }

         return   $html . $custom_html;
           
        }
            
        return   $html;
            
    }, 10, 3);  
    
   return $form;
}, 10, 1);




