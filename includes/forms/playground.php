<?php
// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

// Playground Form

add_action('wp_ajax_maspik_handle_playground_form', 'maspik_handle_playground_form');
add_action('wp_ajax_nopriv_maspik_handle_playground_form', 'maspik_handle_playground_form');

function maspik_handle_playground_form() {
    

    //check_ajax_referer('contact_form_nonce', 'nonce');

    $name = sanitize_text_field(wp_unslash($_POST['userName']));
    $email = sanitize_text_field(wp_unslash($_POST['userEmail']));
    $tel = sanitize_text_field(wp_unslash($_POST['tel']));
    $url = sanitize_url(wp_unslash($_POST['url']));
    $content = wp_kses(wp_unslash($_POST['content']), array('a' => array('href' => array(), 'title' => array())));

    // Example: Save form data to database or send an email
    $success = 1;
    $name_spam = "";
    $email_spam = "";
    $tel_spam = "";
    $url_spam = "";
    $textarea_spam = "";
    if (empty($name) && empty($tel) && empty($email) && empty($url) && empty($content)) {
        // Process the form data (e.g., save to database, send email, etc.)
        $response = array(
            'status' => 'Error',
            'name' => '',
            'email' => '',
            'tel' => '',
            'url' => '',
            'textarea' => '',
            'message' => 'Please fill in at least one of the fields.'
        );
        wp_send_json($response);
        wp_die();
    }
    $spam = false;
    // ip
    $ip = maspik_get_real_ip();
    $reason = false;
    // Country IP Check 
    $GeneralCheck = GeneralCheck($ip,$spam,$reason,false,false);
    $spam = isset($GeneralCheck['spam']) ? $GeneralCheck['spam'] : 0;
    $Country_reason = $GeneralCheck['reason'] ? "<b>SPAM - ".$GeneralCheck['reason']."</b><br>" : "";  
    if($name){
        $validateTextField = validateTextField($name);
        $name_spam = isset($validateTextField['spam']) ? $validateTextField['spam'] : 0;
        $name_spam = $name_spam ? "SPAM - ".$name_spam : "";
    }
    if($email){
         $email_spam = checkEmailForSpam($email);
         $email_spam = $email_spam ? $email_spam : "";
    }
    if($tel){
         $tel_spam = checkTelForSpam($tel);  
         $tel_spam_reason = $tel_spam['reason'];      
         $tel_spam_valid = $tel_spam['valid'];   
         $tel_spam = $tel_spam_valid ? "" : $tel_spam_reason;
    }
    if($url){
        $checkUrlForSpam = checkUrlForSpam($url);
        $url_spam = isset($checkUrlForSpam['spam']) ? $checkUrlForSpam['spam'] : 0;
        $url_spam = $url_spam ? "SPAM - ".$url_spam : "";       
    }
    if($content){
        $checkTextareaForSpam = checkTextareaForSpam($content);
        $textarea_spam = isset($checkTextareaForSpam['spam'])? $checkTextareaForSpam['spam'] : 0;
        $textarea_spam = $textarea_spam ? "SPAM - ".$textarea_spam : "";       
    }
    $message = 'Spam check was finish - No spam found.';
    if( $name_spam || $email_spam || $tel_spam || $url_spam || $textarea_spam ){
        $message = 'Spam Found - See note above.';
    }
    // Prepare response
    if ($success) {
        $response = array(
            'status' => 'success',
            'name' => $name_spam,
            'email' => $email_spam,
            'tel' => $tel_spam,
            'url' => $url_spam,
            'textarea' => $textarea_spam,
            'message' => $Country_reason.$message
        );
    } else {
        $response = array(
            'status' => 'Error',
            'name' => $name_spam,
            'email' => $email_spam,
            'tel' => $tel_spam,
            'url' => $url_spam,
            'textarea' => $textarea_spam,
            'message' => 'error occurred (002).'
        );
    }

    // Return JSON response
    wp_send_json($response);
    wp_die();
}

