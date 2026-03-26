<?php

add_filter('everest_forms_process_initial_errors', 'maspik_validate_everest_forms', 10, 2);

function maspik_validate_everest_forms($errors, $form_data) {
    $error_message = cfas_get_error_text();
    $spam = false;
    $reason = "";
    $ip = maspik_get_real_ip();
    $fields = $form_data['form_fields'];
    $form_id = $form_data['id'];
    $entry = $form_data['entry']['form_fields'];
    // Collect relevant content fields for AI based on field types (to be used after per-field checks)
    $content_fields = array();

    // Perform spam validation for each form field (and collect content fields for AI)
    foreach ($fields as $field_id => $field) {
        $field_value = is_array($entry[$field_id]) ? $entry[$field_id] : sanitize_text_field($entry[$field_id]);
        $field_type = $field['type'];

        // Build content_fields only for relevant types
        if ( ! empty( $field_value ) && in_array( $field_type, array( 'first-name', 'last-name', 'text', 'email', 'tel', 'textarea' ), true ) ) {
            $normalized_value = is_array( $entry[ $field_id ] )
                ? implode( ' ', array_map( 'strval', (array) $entry[ $field_id ] ) )
                : sanitize_text_field( $entry[ $field_id ] );
            if ( $normalized_value !== '' ) {
                $content_fields[ $field_id ] = $normalized_value;
            }
        }


        if ( ( $field_type === 'first-name' || $field_type === 'last-name' ) && !empty($field_value)) {
            $validateTextField = validateTextField($field_value);
            $spam = isset($validateTextField['spam']) ? $validateTextField['spam'] : false;
            $message = isset($validateTextField['message']) ? $validateTextField['message'] : false;
            $spam_lbl = isset($validateTextField['label']) ? $validateTextField['label'] : 0 ;
            $spam_val = isset($validateTextField['option_value']) ? $validateTextField['option_value'] : 0 ;

            if ($spam) {
                efas_add_to_log("text", $spam, $entry, "Everest Forms", $spam_lbl, $spam_val);
                $errors[$form_id][$field_id] = cfas_get_error_text($message);
                return $errors;
            }
        }

    
        if ($field_type === 'text' && !empty($field_value)) {
            $validateTextField = validateTextField($field_value);
            $spam = isset($validateTextField['spam']) ? $validateTextField['spam'] : false;
            $message = isset($validateTextField['message']) ? $validateTextField['message'] : false;
            $spam_lbl = isset($validateTextField['label']) ? $validateTextField['label'] : 0 ;
            $spam_val = isset($validateTextField['option_value']) ? $validateTextField['option_value'] : 0 ;

            if ($spam) {
                efas_add_to_log("text", $spam, $entry, "Everest Forms", $spam_lbl, $spam_val);
                $errors[$form_id][$field_id] = cfas_get_error_text($message);
                return $errors;
            }
        }

        if ($field_type === 'email' && !empty($field_value)) {
            $spam = checkEmailForSpam($field_value);
            $spam_val = $field_value;

            if ($spam) {
                efas_add_to_log("email", $spam, $entry, "Everest Forms", "emails_blacklist", $spam_val);
                $errors[$form_id][$field_id] = cfas_get_error_text($message);
                return $errors;
            }
        }

        if ($field_type === 'tel' && !empty($field_value)) {
            $checkTelForSpam = checkTelForSpam($field_value);
            $reason = isset($checkTelForSpam['reason']) ? $checkTelForSpam['reason'] : false;
            $valid = isset($checkTelForSpam['valid']) ? $checkTelForSpam['valid'] : true;
            $message = isset($checkTelForSpam['message']) ? $checkTelForSpam['message'] : false;
            $spam_lbl = isset($checkTelForSpam['label']) ? $checkTelForSpam['label'] : 0 ;
            $spam_val = isset($checkTelForSpam['option_value']) ? $checkTelForSpam['option_value'] : 0 ;

            if (!$valid) {
                efas_add_to_log("tel", $reason, $entry, "Everest Forms", $spam_lbl, $spam_val);
                $errors[$form_id][$field_id] = cfas_get_error_text($message);
                return $errors;
            }
        }

        if ($field_type === 'textarea' && !empty($field_value)) {
            $checkTextareaForSpam = checkTextareaForSpam($field_value);
            $spam = isset($checkTextareaForSpam['spam']) ? $checkTextareaForSpam['spam'] : false;
            $message = isset($checkTextareaForSpam['message']) ? $checkTextareaForSpam['message'] : false;
            $spam_lbl = isset($checkTextareaForSpam['label']) ? $checkTextareaForSpam['label'] : 0 ;
            $spam_val = isset($checkTextareaForSpam['option_value']) ? $checkTextareaForSpam['option_value'] : 0 ;

            if ($spam) {
                efas_add_to_log("textarea", $spam, $entry, "Everest Forms", $spam_lbl, $spam_val);
                $errors[$form_id][$field_id] = cfas_get_error_text($message);
                return $errors;
            }
        }
    }

    // General check (Country/IP, honeypot, spam key, AI Matrix, etc.) – after per-field checks
    $GeneralCheck = GeneralCheck($ip, $spam, $reason, $_POST, "everest", $content_fields);
    $spam = isset($GeneralCheck['spam']) ? $GeneralCheck['spam'] : false;
    $reason = isset($GeneralCheck['reason']) ? $GeneralCheck['reason'] : false;
    $message = isset($GeneralCheck['message']) ? $GeneralCheck['message'] : false;
    $spam_val = isset($GeneralCheck['value']) ? $GeneralCheck['value'] : false;
    $type = isset($GeneralCheck['type']) ? $GeneralCheck['type'] : 'General';

    if ($spam) {
        efas_add_to_log($type, $reason, $entry, "Everest Forms", $message, $spam_val);
        $errors[$form_id][] = cfas_get_error_text($message);
        return $errors;
    }

    return $errors;
}



//// ADD honeypots fields 
add_action('everest_forms_frontend_output', 'add_maspikhp_html_to_everest', 15, 1);

function add_maspikhp_html_to_everest($form_data) {

    $addhtml = "";

    if (efas_get_spam_api('maspikHoneypot', 'bool')) {
        $honeypot_name = maspik_HP_name();
        $addhtml .= '<div class="evf-honeypot-container evf-field-hp maspik-field">
            <label for="' . esc_attr($honeypot_name) . '" class="evf-field-label">' . esc_html( maspik_honeypot_aria_label() ) . '</label>
            <input type="text" name="' . esc_attr($honeypot_name) . '" id="' . esc_attr($honeypot_name) . '" class="input-text" aria-label="' . esc_attr( maspik_honeypot_aria_label() ) . '">
        </div>';
    }

    if (maspik_get_settings('maspikYearCheck')) {
        $addhtml .= '<div class="evf-honeypot-container evf-field-hp maspik-field">
            <label for="Maspik-currentYear" class="evf-field-label">' . esc_html( maspik_honeypot_aria_label() ) . '</label>
            <input type="text" name="Maspik-currentYear" id="Maspik-currentYear" class="input-text" aria-label="' . esc_attr( maspik_honeypot_aria_label() ) . '">
        </div>';
    }

    // Output the additional HTML before the submit button
    echo $addhtml;
}
