<?php
// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}
// formidable hook file

add_filter('frm_validate_entry', 'maspik_validate_formidable_general', 20, 2);
function maspik_validate_formidable_general($errors, $values){
    
  $spam = false;
  $reason ="";
  $ip =  maspik_get_real_ip(); 

  // Add $_POST['maspik_spam_key'] to the $_POST['item_meta']
  $datatocheck = maspik_add_spam_keys_to_array($_POST['item_meta'],$_POST);

  // General check (Country/IP, honeypot, spam key, AI Matrix, etc.)
  $GeneralCheck = GeneralCheck($ip,$spam,$reason,$datatocheck,"formidable", $datatocheck);
  $spam = isset($GeneralCheck['spam']) ? $GeneralCheck['spam'] : false ;
  $reason = isset($GeneralCheck['reason']) ? $GeneralCheck['reason'] : false ;
  $message = isset($GeneralCheck['message']) ? $GeneralCheck['message'] : false ;
  $spam_val = isset($GeneralCheck['value']) ? $GeneralCheck['value'] : false ;
  $type = isset($GeneralCheck['type']) ? $GeneralCheck['type'] : 'General';

  if ( $spam) {
    efas_add_to_log($type, $reason, $_POST['item_meta'], "Formidable", $message, $spam_val);
    $errors['spam'] = cfas_get_error_text($message);
  }
return $errors;
}

// Add custom validation for Formidable text fields
function maspik_validate_formidable_text($errors, $posted_field, $posted_value, $args){
  	$field_value = is_array($posted_value) ? '' : strtolower($posted_value);
    if ( empty( $field_value ) || $posted_field->type != 'text') {
      return $errors;
    }
	$validateTextField = validateTextField($field_value);
    $spam = isset($validateTextField['spam']) ? $validateTextField['spam'] : 0;
  
    if($spam ) {
      $message = isset($validateTextField['message']) ? $validateTextField['message'] : false ;
      $spam_lbl = isset($validateTextField['label']) ? $validateTextField['label'] : 0 ;
      $spam_val = isset($validateTextField['option_value']) ? $validateTextField['option_value'] : 0 ;

      efas_add_to_log($type = "text",$spam, $_POST['item_meta'], "Formidable", $spam_lbl, $spam_val);          
      $errors[ 'field'. $posted_field->id ] = cfas_get_error_text($message);
    }
    
	return $errors;
}
add_filter('frm_validate_field_entry', 'maspik_validate_formidable_text', 10, 4);


// Add custom validation for Formidable email fields
function maspik_validate_formidable_email($errors, $posted_field, $posted_value, $args){
  	$field_value = is_array($posted_value) ? '' : strtolower($posted_value); 
    if ( empty( $field_value ) || $posted_field->type != 'email') {
      return $errors;
    }
	// check Email For Spam
	$spam = checkEmailForSpam($field_value);
  $spam_val = $field_value;

   if( $spam ) {
      $error_message = cfas_get_error_text();
      efas_add_to_log($type = "email", $spam, $_POST['item_meta'], "Formidable", "emails_blacklist", $spam_val);
      $errors[ 'field'. $posted_field->id ] = $error_message;
   }
   return $errors;
}
add_filter('frm_validate_field_entry', 'maspik_validate_formidable_email', 10, 4);

// Add custom validation for Formidable email fields
function maspik_validate_formidable_tel($errors, $posted_field, $posted_value, $args){
  	$field_value = is_array($posted_value) ? '' : $posted_value; 

    if ( empty( $field_value ) || $posted_field->type != 'phone' ) {
      return $errors;
    }
  
  	$checkTelForSpam = checkTelForSpam($field_value);
  	$reason = isset($checkTelForSpam['reason']) ? $checkTelForSpam['reason'] : 0 ;      
 	  $valid = isset($checkTelForSpam['valid']) ? $checkTelForSpam['valid'] : "yes" ;   
    $message = isset($checkTelForSpam['message']) ? $checkTelForSpam['message'] : 0 ;  
    $spam_lbl = isset($checkTelForSpam['label']) ? $checkTelForSpam['label'] : 0 ;
    $spam_val = isset($checkTelForSpam['option_value']) ? $checkTelForSpam['option_value'] : 0 ;

  	if(!$valid){
        efas_add_to_log($type = "tel", $reason, $_POST['item_meta'], "Formidable", $spam_lbl, $spam_val);
        $errors[ 'field'. $posted_field->id ] = cfas_get_error_text($message);  
    } 

   return $errors;
}
add_filter('frm_validate_field_entry', 'maspik_validate_formidable_tel', 10, 4);


// Add custom validation for Formidable textarea fields
function maspik_validate_formidable_textarea($errors, $posted_field, $posted_value, $args){
  	$field_value = is_array($posted_value) ? '' : strtolower( $posted_value ) ; 
    if($field_value == "" || !$field_value || $posted_field->type != 'textarea'){
		return $errors;
    }
    $checkTextareaForSpam = checkTextareaForSpam($field_value);
    $spam = isset($checkTextareaForSpam['spam']) ? $checkTextareaForSpam['spam'] : 0;
    if ( $spam ) {
      $message = isset($checkTextareaForSpam['message']) ? $checkTextareaForSpam['message'] : 0;
      $spam_lbl = isset($checkTextareaForSpam['label']) ? $checkTextareaForSpam['label'] : 0 ;
      $spam_val = isset($checkTextareaForSpam['option_value']) ? $checkTextareaForSpam['option_value'] : 0 ;
      efas_add_to_log($type = "textarea",$spam, $_POST['item_meta'], "Formidable", $spam_lbl, $spam_val);
      $errors[ 'field'. $posted_field->id ] = cfas_get_error_text($message); 
      return $errors;
    }

	return $errors;
}
add_filter('frm_validate_field_entry', 'maspik_validate_formidable_textarea', 10, 4);

