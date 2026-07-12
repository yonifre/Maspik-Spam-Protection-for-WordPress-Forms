<?php
/**
 * Divi Builder / theme — Contact Form module (et_pb_contact_form).
 *
 * @package ContactFormsAntiSpam
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

add_action( 'wp_loaded', 'maspik_divi_early_validate', 0 );
add_filter( 'pre_wp_mail', 'maspik_divi_pre_wp_mail', 5, 2 );
add_filter( 'gettext', 'maspik_divi_gettext_divi_mail_failure_message', 10, 3 );
add_action( 'shutdown', 'maspik_divi_reset_request_globals', 999 );

/**
 * Divi 5: replace generic "mail failed" copy with Maspik spam reason (runs during translation / render).
 * Only when {@see $GLOBALS['maspik_divi_public_message_is_maspik_spam']} is set in {@see maspik_divi_early_validate()}.
 *
 * @param string $translated Translated or default string.
 * @param string $text       Original msgid (English) from source.
 * @param string $domain     Text domain.
 * @return string
 */
function maspik_divi_gettext_divi_mail_failure_message( $translated, $text, $domain ) {
	if ( 'et_builder_5' !== $domain ) {
		return $translated;
	}
	if ( empty( $GLOBALS['maspik_divi_public_message_is_maspik_spam'] ) || empty( $GLOBALS['maspik_divi_error_message'] ) ) {
		return $translated;
	}
	// Exact msgid from ContactFormModule.php (Divi 5).
	if ( 'There was an error trying to send your message. Please try again later.' !== $text ) {
		return $translated;
	}
	return wp_strip_all_tags( (string) $GLOBALS['maspik_divi_error_message'] );
}

/**
 * Whether the current request is a Divi Contact Form POST.
 *
 * @return bool
 */
function maspik_divi_is_contact_form_request() {
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Presence check only; validated later.
	foreach ( array_keys( $_POST ) as $key ) {
		if ( strpos( (string) $key, 'et_pb_contactform_submit_' ) === 0 ) {
			return true;
		}
	}
	return false;
}

/**
 * Collect form identifiers from POST keys (legacy numeric index or Divi 5 unique id / UUID).
 *
 * @return string[]
 */
function maspik_divi_get_submitted_form_ids() {
	$ids = array();
	// phpcs:ignore WordPress.Security.NonceVerification.Missing
	foreach ( array_keys( $_POST ) as $key ) {
		$key = (string) $key;
		if ( preg_match( '/^et_pb_contactform_submit_(.+)$/', $key, $m ) && '' !== $m[1] ) {
			$ids[] = $m[1];
		}
	}
	return array_values( array_unique( $ids ) );
}

/**
 * Only Divi field types that represent user-authored text for Matrix / GeneralCheck (positive allowlist).
 *
 * @param array $content_fields field_id => value (already validated).
 * @param array $field_type_map field_id => Divi field_type (from JSON or fallback).
 * @return array<string, string>
 */
function maspik_divi_filter_content_fields_for_general_check( array $content_fields, array $field_type_map ) {
	$allowed = array( 'email', 'text', 'input' );
	$out     = array();
	foreach ( $content_fields as $fid => $val ) {
		$ft = isset( $field_type_map[ $fid ] ) ? strtolower( (string) $field_type_map[ $fid ] ) : 'input';
		if ( ! in_array( $ft, $allowed, true ) ) {
			continue;
		}
		$out[ $fid ] = $val;
	}
	return $out;
}

/**
 * Run Maspik checks for one Divi form submission; returns a translated error string or ''.
 *
 * @param string $form_id Form index (legacy) or module unique id (Divi 5).
 * @return string
 */
function maspik_divi_validate_form_fields( $form_id ) {
	$form_id_str = (string) $form_id;
	// Divi 5 also posts et_pb_contact_email_fields_{uniqueId} JSON; legacy uses numeric index only.
	// phpcs:ignore WordPress.Security.NonceVerification.Missing
	if ( isset( $_POST[ 'et_pb_contact_email_fields_' . $form_id_str ] ) && ctype_digit( $form_id_str ) ) {
		return maspik_divi_validate_legacy_form_fields( (int) $form_id_str );
	}
	return maspik_divi_validate_builder5_form_fields( $form_id_str );
}

/**
 * Normalize a submitted field value: arrays to string, trim, optional rawurldecode when still percent-encoded.
 *
 * @param mixed $val Raw POST value.
 * @return string
 */
function maspik_divi_normalize_field_string_value( $val ) {
	if ( is_array( $val ) ) {
		$val = implode( ' ', array_map( 'strval', $val ) );
	}
	if ( ! is_string( $val ) ) {
		return '';
	}
	$val = trim( $val );
	if ( '' !== $val && preg_match( '/%[0-9A-Fa-f]{2}/', $val ) ) {
		$decoded = rawurldecode( $val );
		if ( is_string( $decoded ) ) {
			$val = trim( $decoded );
		}
	}
	return $val;
}

/**
 * Parse Divi 5 hidden field et_pb_contact_email_fields_{uuid} (authoritative field_type, original_id).
 *
 * @param string $unique_id Module unique id.
 * @return array<int, array>|null
 */
function maspik_divi_parse_email_fields_json_for_builder5( $unique_id ) {
	$key = 'et_pb_contact_email_fields_' . (string) $unique_id;
	// phpcs:ignore WordPress.Security.NonceVerification.Missing
	if ( empty( $_POST[ $key ] ) || ! is_string( $_POST[ $key ] ) ) {
		return null;
	}
	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Missing
	$raw     = str_replace( '\\', '', wp_unslash( $_POST[ $key ] ) );
	$decoded = json_decode( $raw, true );
	return is_array( $decoded ) && ! empty( $decoded ) ? $decoded : null;
}

/**
 * Build Divi 5 rows + flat values from et_pb_contact_email_fields_* JSON (normal path).
 *
 * @param string $unique_id Module unique id.
 * @return array{0: array<int, array>, 1: array<string, string>} Rows and values_flat.
 */
function maspik_divi_builder5_rows_and_values_from_json( $unique_id ) {
	$rows = array();
	$flat = array();
	$json = maspik_divi_parse_email_fields_json_for_builder5( $unique_id );
	if ( ! is_array( $json ) ) {
		return array( $rows, $flat );
	}
	foreach ( $json as $jr ) {
		if ( ! is_array( $jr ) || empty( $jr['field_id'] ) ) {
			continue;
		}
		$fid = (string) $jr['field_id'];
		if ( 0 === strpos( $fid, 'et_pb_contact_et_number_' ) ) {
			continue;
		}
		$rows[] = array(
			'field_id'    => $fid,
			'original_id' => isset( $jr['original_id'] ) ? (string) $jr['original_id'] : $fid,
			'field_type'  => isset( $jr['field_type'] ) ? strtolower( (string) $jr['field_type'] ) : 'input',
			'field_label' => isset( $jr['field_label'] ) && is_string( $jr['field_label'] ) ? $jr['field_label'] : '',
		);
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$raw = isset( $_POST[ $fid ] ) ? wp_unslash( $_POST[ $fid ] ) : '';
		$flat[ $fid ] = maspik_divi_normalize_field_string_value( $raw );
	}
	return array( $rows, $flat );
}

/**
 * Fallback when Divi does not post et_pb_contact_email_fields_* JSON (rare): scan POST keys + minimal type guess.
 *
 * @return array{0: array<int, array>, 1: array<string, string>}
 */
function maspik_divi_builder5_rows_and_values_post_fallback() {
	$rows = array();
	$flat = array();
	$seen = array();
	// phpcs:ignore WordPress.Security.NonceVerification.Missing
	foreach ( array_keys( $_POST ) as $key ) {
		$key = (string) $key;
		if ( ! preg_match( '/^et_pb_contact_\d+_[a-z0-9_-]+_\d+$/i', $key ) ) {
			continue;
		}
		if ( isset( $seen[ $key ] ) ) {
			continue;
		}
		$seen[ $key ] = true;
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$val = isset( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : '';
		$val = maspik_divi_normalize_field_string_value( $val );
		$flat[ $key ] = $val;
		$lk           = strtolower( $key );
		$ftype        = 'input';
		if ( preg_match( '/_email_\d+$/', $lk ) ) {
			$ftype = 'email';
		} elseif ( preg_match( '/_phone_\d+$/', $lk ) || preg_match( '/_tel_\d+$/', $lk ) ) {
			$ftype = 'phone';
		} elseif ( preg_match( '/_message_\d+$/', $lk ) || preg_match( '/_comment_\d+$/', $lk ) || strlen( $val ) > 160 ) {
			$ftype = 'text';
		}
		$rows[] = array(
			'field_id'    => $key,
			'original_id' => $key,
			'field_type'  => $ftype,
		);
	}
	return array( $rows, $flat );
}

/**
 * Whether a Divi field row should be skipped (honeypot / empty id).
 *
 * @param array $row Divi JSON row.
 * @return bool
 */
function maspik_divi_is_skippable_row( array $row ) {
	$field_id = isset( $row['field_id'] ) ? (string) $row['field_id'] : '';
	if ( '' === $field_id || 0 === strpos( $field_id, 'et_pb_contact_et_number_' ) ) {
		return true;
	}
	return false;
}

/**
 * Normalized value for a row (values_flat keyed by field_id or original_id).
 *
 * @param array $row         Divi JSON row.
 * @param array $values_flat Pre-normalized values.
 * @return string
 */
function maspik_divi_get_row_value( array $row, array $values_flat ) {
	$field_id = isset( $row['field_id'] ) ? (string) $row['field_id'] : '';
	if ( '' === $field_id ) {
		return '';
	}
	if ( isset( $values_flat[ $field_id ] ) ) {
		return (string) $values_flat[ $field_id ];
	}
	$oid = isset( $row['original_id'] ) ? (string) $row['original_id'] : $field_id;
	if ( $oid !== $field_id && isset( $values_flat[ $oid ] ) ) {
		return (string) $values_flat[ $oid ];
	}
	// phpcs:ignore WordPress.Security.NonceVerification.Missing
	return maspik_divi_normalize_field_string_value( isset( $_POST[ $field_id ] ) ? wp_unslash( $_POST[ $field_id ] ) : '' );
}

/**
 * Run per-field spam rules for one Divi contact field.
 *
 * @param array  $row         Divi row (field_id, field_type, original_id).
 * @param string $val         Normalized submitted value.
 * @param array  $datatocheck Logging context from maspik_add_spam_keys_to_array().
 * @return array{error: string, content_value: string|null, field_type: string}
 */
function maspik_divi_check_single_field( array $row, $val, array $datatocheck ) {
	$field_id = isset( $row['field_id'] ) ? (string) $row['field_id'] : '';
	$ftype    = isset( $row['field_type'] ) ? (string) $row['field_type'] : 'input';
	$oid_lower  = strtolower( (string) ( isset( $row['original_id'] ) ? $row['original_id'] : $field_id ) );
	$fid_lower  = strtolower( $field_id );
	$is_message = ( false !== strpos( $oid_lower, 'message' ) || false !== strpos( $fid_lower, 'message' ) );
	$is_urlish  = ( false !== strpos( $oid_lower, 'url' ) || false !== strpos( $fid_lower, 'url' ) || false !== strpos( $oid_lower, 'website' ) );

	$content_value = null;
	$empty         = array(
		'error'         => '',
		'content_value' => null,
		'field_type'    => strtolower( $ftype ),
	);

	switch ( $ftype ) {
		case 'email':
			$hit = checkEmailForSpam( $val );
			if ( $hit ) {
				efas_add_to_log( 'email', $hit, $datatocheck, 'Divi', 'emails_blacklist', $val );
				return array_merge( $empty, array( 'error' => cfas_get_error_text( 'emails_blacklist' ) ) );
			}
			$content_value = sanitize_email( $val );
			break;

		case 'text':
			if ( $is_message || strlen( $val ) > 160 ) {
				$checkTextareaForSpam = checkTextareaForSpam( $val );
				$spam_hit             = isset( $checkTextareaForSpam['spam'] ) ? $checkTextareaForSpam['spam'] : 0;
				if ( $spam_hit ) {
					$spam_lbl = isset( $checkTextareaForSpam['label'] ) ? $checkTextareaForSpam['label'] : '';
					$spam_val = isset( $checkTextareaForSpam['option_value'] ) ? $checkTextareaForSpam['option_value'] : '';
					$message  = isset( $checkTextareaForSpam['message'] ) ? $checkTextareaForSpam['message'] : '';
					efas_add_to_log( 'textarea', $spam_hit, $datatocheck, 'Divi', $spam_lbl, $spam_val );
					return array_merge( $empty, array( 'error' => cfas_get_error_text( $message ) ) );
				}
				$content_value = $val;
				break;
			}
			// Fall through for short text fields.
			// no break
		case 'input':
			if ( $is_urlish ) {
				$checkUrlForSpam = checkUrlForSpam( $val );
				$spam_hit        = isset( $checkUrlForSpam['spam'] ) ? $checkUrlForSpam['spam'] : 0;
				if ( $spam_hit ) {
					$spam_lbl = isset( $checkUrlForSpam['label'] ) ? $checkUrlForSpam['label'] : '';
					$spam_val = isset( $checkUrlForSpam['option_value'] ) ? $checkUrlForSpam['option_value'] : '';
					efas_add_to_log( 'url', $spam_hit, $datatocheck, 'Divi', $spam_lbl, $spam_val );
					return array_merge( $empty, array( 'error' => cfas_get_error_text( isset( $checkUrlForSpam['message'] ) ? $checkUrlForSpam['message'] : '' ) ) );
				}
				$content_value = $val;
				break;
			}
			// Divi sometimes uses field_type input with phone/tel in the field id.
			if ( false !== strpos( $fid_lower, 'phone' ) || false !== strpos( $fid_lower, 'tel' ) || false !== strpos( $oid_lower, 'phone' ) ) {
				$checkTelForSpam = checkTelForSpam( $val );
				$valid           = isset( $checkTelForSpam['valid'] ) ? $checkTelForSpam['valid'] : true;
				if ( ! $valid ) {
					$reason   = isset( $checkTelForSpam['reason'] ) ? $checkTelForSpam['reason'] : '';
					$spam_lbl = isset( $checkTelForSpam['label'] ) ? $checkTelForSpam['label'] : '';
					$spam_val = isset( $checkTelForSpam['option_value'] ) ? $checkTelForSpam['option_value'] : '';
					$message  = isset( $checkTelForSpam['message'] ) ? $checkTelForSpam['message'] : 'tel_formats';
					efas_add_to_log( 'tel', $reason, $datatocheck, 'Divi', $spam_lbl, $spam_val );
					return array_merge( $empty, array( 'error' => cfas_get_error_text( $message ) ) );
				}
				$content_value = $val;
				break;
			}
			$validateTextField = validateTextField( $val );
			$spam_hit          = isset( $validateTextField['spam'] ) ? $validateTextField['spam'] : 0;
			if ( $spam_hit ) {
				$spam_lbl = isset( $validateTextField['label'] ) ? $validateTextField['label'] : '';
				$spam_val = isset( $validateTextField['option_value'] ) ? $validateTextField['option_value'] : '';
				$message  = isset( $validateTextField['message'] ) ? $validateTextField['message'] : '';
				efas_add_to_log( 'text', $spam_hit, $datatocheck, 'Divi', $spam_lbl, $spam_val );
				return array_merge( $empty, array( 'error' => cfas_get_error_text( $message ) ) );
			}
			if ( in_array( $ftype, array( 'text', 'input' ), true ) ) {
				$content_value = $val;
			}
			break;

		case 'tel':
		case 'phone':
			$checkTelForSpam = checkTelForSpam( $val );
			$valid           = isset( $checkTelForSpam['valid'] ) ? $checkTelForSpam['valid'] : true;
			if ( ! $valid ) {
				$reason   = isset( $checkTelForSpam['reason'] ) ? $checkTelForSpam['reason'] : '';
				$spam_lbl = isset( $checkTelForSpam['label'] ) ? $checkTelForSpam['label'] : '';
				$spam_val = isset( $checkTelForSpam['option_value'] ) ? $checkTelForSpam['option_value'] : '';
				$message  = isset( $checkTelForSpam['message'] ) ? $checkTelForSpam['message'] : 'tel_formats';
				efas_add_to_log( 'tel', $reason, $datatocheck, 'Divi', $spam_lbl, $spam_val );
				return array_merge( $empty, array( 'error' => cfas_get_error_text( $message ) ) );
			}
			$content_value = $val;
			break;

		case 'select':
		case 'checkbox':
		case 'radio':
			return $empty;
	}

	return array(
		'error'         => '',
		'content_value' => $content_value,
		'field_type'    => strtolower( $ftype ),
	);
}

/**
 * Per-field checks + GeneralCheck for a list of Divi rows.
 *
 * @param array $rows        Field rows from Divi JSON.
 * @param array $values_flat Normalized values (field_id and/or original_id keys).
 * @return string Error message or ''.
 */
function maspik_divi_process_rows_spam_check( array $rows, array $values_flat ) {
	$datatocheck         = maspik_add_spam_keys_to_array( $values_flat, $_POST );
	$content_fields        = array();
	$divi_field_type_map = array();
	foreach ( $rows as $row ) {
		if ( ! is_array( $row ) || maspik_divi_is_skippable_row( $row ) ) {
			continue;
		}
		$field_id = (string) $row['field_id'];
		$val      = maspik_divi_get_row_value( $row, $values_flat );
		if ( '' === $val ) {
			continue;
		}

		$check = maspik_divi_check_single_field( $row, $val, $datatocheck );
		if ( '' !== $check['error'] ) {
			return $check['error'];
		}
		if ( null !== $check['content_value'] ) {
			$content_fields[ $field_id ]      = $check['content_value'];
			$divi_field_type_map[ $field_id ] = $check['field_type'];
		}
	}

	$general_fields = maspik_divi_filter_content_fields_for_general_check( $content_fields, $divi_field_type_map );

	$spam     = false;
	$reason   = '';
	$ip       = maspik_get_real_ip();
	$result   = GeneralCheck( $ip, $spam, $reason, $_POST, 'divi', $general_fields );
	$spam     = isset( $result['spam'] ) ? $result['spam'] : false;
	$reason   = isset( $result['reason'] ) ? $result['reason'] : false;
	$message  = isset( $result['message'] ) ? $result['message'] : false;
	$spam_val = isset( $result['value'] ) ? $result['value'] : false;
	$type     = isset( $result['type'] ) ? $result['type'] : 'General';

	if ( $spam ) {
		efas_add_to_log( $type, $reason, $datatocheck, 'Divi', $message, $spam_val );
		return cfas_get_error_text( $message );
	}

	return '';
}

/**
 * Divi 5 contact form: nonce + fields. Rows come from Divi JSON when present; otherwise a tiny POST scan fallback.
 *
 * @param string $unique_id Module unique id from et_pb_contactform_submit_{id}.
 * @return string Error message or ''.
 */
function maspik_divi_validate_builder5_form_fields( $unique_id ) {
	$unique_id = (string) $unique_id;
	$nonce_key = '_wpnonce-et-pb-contact-form-submitted-' . $unique_id;
	// phpcs:ignore WordPress.Security.NonceVerification.Missing
	if ( empty( $_POST[ $nonce_key ] ) ) {
		return '';
	}
	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Missing
	if ( ! wp_verify_nonce( wp_unslash( $_POST[ $nonce_key ] ), 'et-pb-contact-form-submit-' . $unique_id ) ) {
		return '';
	}

	list( $rows, $values_flat ) = maspik_divi_builder5_rows_and_values_from_json( $unique_id );
	if ( empty( $rows ) ) {
		list( $rows, $values_flat ) = maspik_divi_builder5_rows_and_values_post_fallback();
	}

	if ( empty( $rows ) ) {
		return '';
	}

	return maspik_divi_process_rows_spam_check( $rows, $values_flat );
}

/**
 * Legacy Divi Builder contact form (JSON et_pb_contact_email_fields_*).
 *
 * @param int $form_num Divi form instance number from POST keys.
 * @return string
 */
function maspik_divi_validate_legacy_form_fields( $form_num ) {
	$form_num = (int) $form_num;
	$nonce_key = '_wpnonce-et-pb-contact-form-submitted-' . $form_num;
	// phpcs:ignore WordPress.Security.NonceVerification.Missing
	if ( empty( $_POST[ $nonce_key ] ) ) {
		return '';
	}
	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Missing
	if ( ! wp_verify_nonce( wp_unslash( $_POST[ $nonce_key ] ), 'et-pb-contact-form-submit' ) ) {
		return '';
	}
	// phpcs:ignore WordPress.Security.NonceVerification.Missing
	if ( ! empty( $_POST[ 'et_pb_contact_et_number_' . $form_num ] ) ) {
		return '';
	}

	$fields_key = 'et_pb_contact_email_fields_' . $form_num;
	// phpcs:ignore WordPress.Security.NonceVerification.Missing
	if ( empty( $_POST[ $fields_key ] ) ) {
		return '';
	}

	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Missing
	$raw_fields = str_replace( '\\', '', wp_unslash( $_POST[ $fields_key ] ) );
	$rows       = json_decode( $raw_fields, true );
	if ( ! is_array( $rows ) || empty( $rows ) ) {
		return '';
	}

	$values_flat = array();
	foreach ( $rows as $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}
		$field_id = isset( $row['field_id'] ) ? (string) $row['field_id'] : '';
		if ( '' === $field_id || 0 === strpos( $field_id, 'et_pb_contact_et_number_' ) ) {
			continue;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$val = isset( $_POST[ $field_id ] ) ? wp_unslash( $_POST[ $field_id ] ) : '';
		$val = maspik_divi_normalize_field_string_value( $val );
		$oid = isset( $row['original_id'] ) ? (string) $row['original_id'] : $field_id;
		$values_flat[ $oid ] = $val;
	}

	return maspik_divi_process_rows_spam_check( $rows, $values_flat );
}

/**
 * Validate Divi contact POST before the theme renders and sends mail.
 * Hooked to {@see 'wp_loaded'} because this file loads during {@see 'init'} (see file header).
 */
function maspik_divi_early_validate() {
	if ( is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
		return;
	}
	if ( empty( $_POST ) || ! maspik_divi_is_contact_form_request() ) {
		return;
	}

	$form_ids = maspik_divi_get_submitted_form_ids();
	if ( empty( $form_ids ) ) {
		return;
	}

	$error = '';
	foreach ( $form_ids as $form_id ) {
		$error = maspik_divi_validate_form_fields( $form_id );
		if ( '' !== $error ) {
			break;
		}
	}

	if ( '' === $error ) {
		return;
	}

	$GLOBALS['maspik_divi_error_message']                 = $error;
	$GLOBALS['maspik_divi_spam_blocked']                  = true;
	$GLOBALS['maspik_divi_public_message_is_maspik_spam'] = true;
	// Block a small burst of wp_mail calls (admin + optional confirmation).
	$GLOBALS['maspik_divi_block_wp_mail_remaining'] = 5;
}

/**
 * Short-circuit wp_mail while handling a blocked Divi submission.
 *
 * @param null|bool|\WP_Error $short_circuit Short-circuit return value.
 * @param array               $atts          Compact wp_mail arguments.
 * @return null|bool|\WP_Error
 */
function maspik_divi_pre_wp_mail( $short_circuit, $atts ) {
	if ( null !== $short_circuit ) {
		return $short_circuit;
	}
	if ( empty( $GLOBALS['maspik_divi_spam_blocked'] ) ) {
		return null;
	}
	$left = isset( $GLOBALS['maspik_divi_block_wp_mail_remaining'] ) ? (int) $GLOBALS['maspik_divi_block_wp_mail_remaining'] : 0;
	if ( $left < 1 ) {
		return null;
	}
	$GLOBALS['maspik_divi_block_wp_mail_remaining'] = $left - 1;
	return false;
}

/**
 * Clear request-scoped globals.
 */
function maspik_divi_reset_request_globals() {
	unset(
		$GLOBALS['maspik_divi_error_message'],
		$GLOBALS['maspik_divi_spam_blocked'],
		$GLOBALS['maspik_divi_public_message_is_maspik_spam'],
		$GLOBALS['maspik_divi_block_wp_mail_remaining']
	);
}
