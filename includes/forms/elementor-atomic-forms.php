<?php
/**
 * Elementor Pro Atomic Forms integration (`elementor_pro/atomic_forms/spam_check`).
 *
 * Runs alongside the classic Elementor Forms handler in elementor.php.
 *
 * Pseudo-fields (data-interaction-id on `form[data-element_type="e-form"]`) are merged
 * server-side into the POST shape expected by GeneralCheck / maspik_make_extra_spam_check.
 *
 * DEVELOPER HOOK: maspik_disable_elementor_atomic_forms_spam_check
 *
 * @param bool  $disable          Whether to disable spam check (default: false).
 * @param array $form_fields      Raw fields from the Atomic Forms request.
 * @param array $widget_settings  Resolved widget settings.
 * @param int   $post_id          Document post ID.
 * @return bool True to skip Maspik checks for this submission.
 */
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Interaction ids injected by inline footer script (must stay in sync with JS below).
 *
 * @return string[]
 */
function maspik_elementor_atomic_internal_field_ids() {
	return array( 'maspik_atomic_hp', 'maspik_atomic_sk' );
}

/**
 * Whether Atomic Form experiments are active (Elementor 4+).
 *
 * @return bool
 */
function maspik_elementor_atomic_forms_feature_available() {
	if ( ! defined( 'ELEMENTOR_VERSION' ) || version_compare( ELEMENTOR_VERSION, '4.0', '<' ) ) {
		return false;
	}
	if ( ! defined( 'ELEMENTOR_PRO_VERSION' ) || ! class_exists( '\Elementor\Plugin' ) ) {
		return false;
	}
	$plugin = \Elementor\Plugin::$instance;
	if ( ! is_object( $plugin ) || ! isset( $plugin->experiments ) || ! is_object( $plugin->experiments ) ) {
		return false;
	}
	$experiments = $plugin->experiments;
	if ( ! method_exists( $experiments, 'is_feature_active' ) ) {
		return false;
	}
	return $experiments->is_feature_active( 'e_pro_atomic_form' )
		&& $experiments->is_feature_active( 'e_atomic_elements' );
}

/**
 * Print Atomic e-form injector in the footer (inline, no external JS file).
 *
 * Filter `maspik_elementor_atomic_forms_enqueue_script` still controls whether output runs (default: experiments active).
 *
 * Accessibility: honeypot is visually hidden, tabindex=-1, aria-hidden; aria-label + placeholder from maspik_honeypot_aria_label() (user-requested parity with other forms).
 * Hidden inputs use native type=hidden only (no redundant aria-hidden). Referrer for checks is set server-side only (HTTP_REFERER), not injected from the front.
 * All injected fields get name + unique id (same name as data-interaction-id for Elementor payload; id suffixed per form instance).
 */
function maspik_elementor_atomic_forms_print_footer_inline_script() {
	static $done = false;
	if ( $done ) {
		return;
	}

	if ( is_admin() && ! wp_doing_ajax() ) {
		return;
	}
	if ( maspik_get_settings( 'maspik_support_Elementor_forms' ) === 'no' ) {
		return;
	}
	if ( ! function_exists( 'maspik_is_plugin_active' ) || ! maspik_is_plugin_active( 'elementor-pro/elementor-pro.php' ) ) {
		return;
	}

	$need_injector = efas_get_spam_api( 'maspikHoneypot', 'bool' )
		|| efas_get_spam_api( 'maspikTimeCheck', 'bool' );

	if ( ! $need_injector ) {
		return;
	}

	// Prefer experiments flag; still print on Elementor 4+ Pro when experiments are off (common on hybrid sites).
	$default_print = maspik_elementor_atomic_forms_feature_available();
	if ( ! $default_print && defined( 'ELEMENTOR_VERSION' ) && version_compare( ELEMENTOR_VERSION, '4.0', '>=' ) && defined( 'ELEMENTOR_PRO_VERSION' ) ) {
		$default_print = true;
	}
	$enqueue = apply_filters( 'maspik_elementor_atomic_forms_enqueue_script', $default_print );
	if ( ! $enqueue ) {
		return;
	}

	$done = true;

	$config = array(
		'active'      => true,
		'honeypot'    => (bool) efas_get_spam_api( 'maspikHoneypot', 'bool' ),
		'spamKey'     => efas_get_spam_api( 'maspikTimeCheck', 'bool' ) ? (string) maspik_get_spam_key() : '',
		'honeypotLbl' => wp_strip_all_tags( maspik_honeypot_aria_label() ),
	);

	$json_flags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
	if ( defined( 'JSON_UNESCAPED_UNICODE' ) ) {
		$json_flags |= JSON_UNESCAPED_UNICODE;
	}
	$config_json = wp_json_encode( $config, $json_flags );

	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_json_encode with JSON_HEX_* escapes script-breaking sequences.
	?>
<script id="maspik-elementor-atomic-forms">
window.maspikElementorAtomic = <?php echo $config_json; ?>;
(function () {
	'use strict';
	var cfg = window.maspikElementorAtomic || {};
	var DEBOUNCE_MS = 80;
	var timer = null;
	var IDS = { hp: 'maspik_atomic_hp', sk: 'maspik_atomic_sk' };
	function getAtomicFormInstanceKey(form) {
		if (!form.dataset.maspikAtomicIx) {
			window.maspikAtomicFormSeq = (window.maspikAtomicFormSeq || 0) + 1;
			form.dataset.maspikAtomicIx = String(window.maspikAtomicFormSeq);
		}
		return form.dataset.maspikAtomicIx;
	}
	/* Stable name + unique id per form (WCAG/HTML: fields should have id or name; duplicate ids across forms are invalid). */
	function stampMaspikAtomicField(form, input, interactionId) {
		input.setAttribute('name', interactionId);
		input.id = interactionId + '__f' + getAtomicFormInstanceKey(form);
	}
	function shouldSkipForm(form) {
		if (!form || form.tagName !== 'FORM') { return true; }
		// HTML default for missing method is GET; Atomic/AJAX forms often omit method — only skip explicit method="get".
		var methodAttr = form.getAttribute('method');
		if (methodAttr && methodAttr.toLowerCase() === 'get') { return true; }
		var role = (form.getAttribute('role') || '').toLowerCase();
		if (role === 'search') { return true; }
		var aria = (form.getAttribute('aria-label') || '').toLowerCase();
		if (aria.indexOf('search') !== -1) { return true; }
		var action = (form.getAttribute('action') || '').toLowerCase();
		if (action.indexOf('?s=') !== -1 || action.indexOf('search=') !== -1 || /\/search(\/?|\?|$)/.test(action)) { return true; }
		if (form.querySelector('input[type="search"],input[name="s"],input[name*="search"]')) { return true; }
		return false;
	}
	function ensureStyleOnce() {
		if (document.getElementById('maspik-atomic-e-form-style')) { return; }
		var s = document.createElement('style');
		s.id = 'maspik-atomic-e-form-style';
		s.textContent = '.maspik-atomic-e-form-hp{position:absolute!important;left:-99999px!important;width:1px!important;height:1px!important;opacity:0!important;pointer-events:none!important;overflow:hidden!important;}';
		document.head.appendChild(s);
	}
	function appendHidden(form, id, value) {
		if (form.querySelector('[data-interaction-id="' + id + '"]')) { return; }
		var input = document.createElement('input');
		input.type = 'hidden';
		input.setAttribute('data-interaction-id', id);
		stampMaspikAtomicField(form, input, id);
		input.value = value == null ? '' : String(value);
		input.setAttribute('autocomplete', 'off');
		input.className = 'maspik-atomic-e-form-field maspik-field';
		form.appendChild(input);
	}
	function appendHoneypot(form) {
		if (form.querySelector('[data-interaction-id="' + IDS.hp + '"]')) { return; }
		var input = document.createElement('input');
		input.type = 'text';
		input.setAttribute('data-interaction-id', IDS.hp);
		stampMaspikAtomicField(form, input, IDS.hp);
		/* Off-screen + not in tab order; aria-hidden limits exposure to AT. aria-label/placeholder kept for site-owner / audit expectations (same as other Maspik integrations). */
		input.setAttribute('aria-hidden', 'true');
		input.setAttribute('tabindex', '-1');
		input.setAttribute('autocomplete', 'off');
		if (cfg.honeypotLbl) {
			input.setAttribute('aria-label', cfg.honeypotLbl);
			input.setAttribute('placeholder', cfg.honeypotLbl);
		}
		input.className = 'maspik-atomic-e-form-hp maspik-atomic-e-form-field maspik-field';
		input.value = '';
		form.appendChild(input);
	}
	function injectIntoForm(form) {
		if (shouldSkipForm(form)) { return; }
		ensureStyleOnce();
		if (cfg.honeypot) { appendHoneypot(form); }
		if (cfg.spamKey) { appendHidden(form, IDS.sk, cfg.spamKey); }
	}
	function injectAll() {
		document.querySelectorAll('form[data-element_type="e-form"], form.e-form').forEach(function (form) { injectIntoForm(form); });
	}
	function schedule() {
		clearTimeout(timer);
		timer = setTimeout(injectAll, DEBOUNCE_MS);
	}
	function init() {
		if (!cfg.active) { return; }
		injectAll();
		if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', schedule); }
		window.addEventListener('load', schedule);
		if (window.jQuery && window.jQuery.fn) { window.jQuery(window).on('elementor/frontend/init', schedule); }
	}
	init();
})();
</script>
	<?php
}
add_action( 'wp_footer', 'maspik_elementor_atomic_forms_print_footer_inline_script', 25 );



/**
 * Read Atomic field value by interaction id (first match).
 *
 * @param array  $form_fields Atomic form_fields list.
 * @param string $id          data-interaction-id / submitted id.
 * @return string|null        null if field id not present in payload.
 */
function maspik_elementor_atomic_field_value_by_id( array $form_fields, $id ) {
	$id = (string) $id;
	foreach ( $form_fields as $field ) {
		if ( ! is_array( $field ) || ! isset( $field['id'] ) ) {
			continue;
		}
		if ( (string) $field['id'] !== $id ) {
			continue;
		}
		$v = $field['value'] ?? '';
		if ( is_array( $v ) ) {
			return sanitize_text_field( implode( ' ', array_map( 'sanitize_text_field', $v ) ) );
		}
		return sanitize_text_field( (string) $v );
	}
	return null;
}

/**
 * Merge Atomic pseudo-fields into a POST-like array for GeneralCheck.
 *
 * @param array $form_fields Atomic form_fields.
 * @return array
 */
function maspik_elementor_atomic_get_merged_post_for_checks( array $form_fields ) {
	$post = isset( $_POST ) && is_array( $_POST ) ? wp_unslash( $_POST ) : array();

	$hp = maspik_elementor_atomic_field_value_by_id( $form_fields, 'maspik_atomic_hp' );
	if ( null !== $hp ) {
		$post['full-name-maspik-hp'] = $hp;
	}

	$sk = maspik_elementor_atomic_field_value_by_id( $form_fields, 'maspik_atomic_sk' );
	if ( null !== $sk ) {
		$post['maspik_spam_key'] = $sk;
	}

	$ref = isset( $_POST['referrer'] ) ? $_POST['referrer'] : (isset( $_SERVER['HTTP_REFERER'] ) ? $_SERVER['HTTP_REFERER'] : 'no_referrer');
	if ( $ref ) {
		$post['maspik_referrer'] = esc_url_raw( $ref );
	}

	return $post;
}

add_filter( 'elementor_pro/atomic_forms/spam_check', 'maspik_elementor_atomic_forms_spam_check', 10, 4 );
function maspik_elementor_atomic_forms_spam_check( $is_spam, $form_fields, $widget_settings, $post_id ) {
	if ( $is_spam ) {
		return true;
	}

	$disable = apply_filters( 'maspik_disable_elementor_atomic_forms_spam_check', false, $form_fields, $widget_settings, $post_id );
	if ( $disable ) {
		return false;
	}

	$form_fields = is_array( $form_fields ) ? $form_fields : array();

	$merged_post = maspik_elementor_atomic_get_merged_post_for_checks( $form_fields );
	$form_data   = maspik_elementor_atomic_build_form_data_for_log( $form_fields, $merged_post );

	$internal = array_flip( maspik_elementor_atomic_internal_field_ids() );

	$spam           = false;
	$reason         = '';
	$content_fields = array();

	foreach ( $form_fields as $field ) {
		if ( ! is_array( $field ) ) {
			continue;
		}

		$field_id = isset( $field['id'] ) ? sanitize_text_field( (string) $field['id'] ) : '';
		if ( $field_id === '' || isset( $internal[ $field_id ] ) ) {
			continue;
		}

		$field_type  = isset( $field['type'] ) ? sanitize_text_field( (string) $field['type'] ) : 'text';
		$field_value = maspik_elementor_atomic_normalize_field_value( $field['value'] ?? '', $field_type );

		if ( $field_value === '' ) {
			continue;
		}

		switch ( $field_type ) {
			case 'text':
				$validate_text = validateTextField( $field_value );
				$spam          = isset( $validate_text['spam'] ) ? $validate_text['spam'] : 0;
				if ( $spam ) {
					efas_add_to_log( 'text', $validate_text['spam'], $form_data, 'Elementor Atomic Forms', $validate_text['label'], $validate_text['option_value'] );
					return true;
				}
				$content_fields[ $field_id ] = $field_value;
				break;

			case 'email':
				$spam = checkEmailForSpam( $field_value );
				if ( $spam ) {
					efas_add_to_log( 'email', $spam, $form_data, 'Elementor Atomic Forms', 'emails_blacklist', $field_value );
					return true;
				}
				$content_fields[ $field_id ] = $field_value;
				break;

			case 'tel':
				$check_tel = checkTelForSpam( $field_value );
				$valid     = isset( $check_tel['valid'] ) ? $check_tel['valid'] : true;
				if ( ! $valid ) {
					$reason    = isset( $check_tel['reason'] ) ? $check_tel['reason'] : false;
					$spam_lbl  = isset( $check_tel['label'] ) ? $check_tel['label'] : 0;
					$spam_val  = isset( $check_tel['option_value'] ) ? $check_tel['option_value'] : 0;
					$message   = isset( $check_tel['message'] ) ? $check_tel['message'] : 'tel_formats';
					efas_add_to_log( 'tel', $reason, $form_data, 'Elementor Atomic Forms', $spam_lbl, $spam_val );
					return true;
				}
				$content_fields[ $field_id ] = $field_value;
				break;

			case 'url':
				$check_url = checkUrlForSpam( $field_value );
				$spam      = isset( $check_url['spam'] ) ? $check_url['spam'] : 0;
				if ( $spam ) {
					efas_add_to_log( 'url', $spam, $form_data, 'Elementor Atomic Forms', $check_url['label'], $check_url['option_value'] );
					return true;
				}
				break;

			case 'textarea':
				$check_textarea = checkTextareaForSpam( $field_value );
				$spam           = isset( $check_textarea['spam'] ) ? $check_textarea['spam'] : 0;
				if ( $spam ) {
					efas_add_to_log( 'textarea', $spam, $form_data, 'Elementor Atomic Forms', $check_textarea['label'], $check_textarea['option_value'] );
					return true;
				}
				$content_fields[ $field_id ] = $field_value;
				break;

			default:
				break;
		}
	}

	$need_pageurl = efas_get_spam_api( 'NeedPageurl', 'bool' );
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- Elementor validates nonce before this filter.
	// No local block: signal Matrix via plugin_spam_likelihood floor (7) when page source is unknown.
	if ( $need_pageurl && function_exists( 'maspik_matrix_raise_plugin_spam_likelihood_floor' ) ) {
		$has_post_referrer = ! empty( $merged_post['maspik_referrer'] );
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated -- read-only signal; not stored.
		$has_http_referer = ! empty( $_SERVER['HTTP_REFERER'] );
		$has_post_context = (int) $post_id > 0;
		if ( ! $has_post_referrer && ! $has_http_referer && ! $has_post_context ) {
			maspik_matrix_raise_plugin_spam_likelihood_floor( 7 );
		}
	}
	// phpcs:enable WordPress.Security.NonceVerification.Missing

	try {
		$ip            = maspik_get_real_ip();
		$general_check = GeneralCheck( $ip, $spam, $reason, $merged_post, 'elementor_atomic', $content_fields );
		$spam          = isset( $general_check['spam'] ) ? $general_check['spam'] : false;
		$reason        = isset( $general_check['reason'] ) ? $general_check['reason'] : false;
		$message       = isset( $general_check['message'] ) ? $general_check['message'] : false;
		$type          = isset( $general_check['type'] ) ? $general_check['type'] : 'General';
		$spam_val      = isset( $general_check['value'] ) && $general_check['value'] ? $general_check['value'] : false;

		if ( $spam ) {
			efas_add_to_log( $type, $reason, $form_data, 'Elementor Atomic Forms', $message, $spam_val );
			return true;
		}
	} catch ( Exception $e ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'Maspik Elementor Atomic GeneralCheck Exception: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() );
		}
		efas_add_to_log( 'General', 'Exception in GeneralCheck: ' . $e->getMessage(), $form_data, 'Elementor Atomic Forms', 'general_check_exception', '' );
	} catch ( Error $e ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'Maspik Elementor Atomic GeneralCheck Fatal Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() );
		}
		efas_add_to_log( 'General', 'Fatal Error in GeneralCheck: ' . $e->getMessage(), $form_data, 'Elementor Atomic Forms', 'general_check_fatal_error', '' );
	}

	return false;
}

/**
 * Flat id => value map for spam logs (mirrors Elementor server sanitization). Omits Maspik internal Atomic ids.
 *
 * @param array $form_fields  Atomic form_fields array.
 * @param array $merged_post  POST + merged pseudo-fields.
 * @return array
 */
function maspik_elementor_atomic_build_form_data_for_log( array $form_fields, array $merged_post ) {
	$form_data = array();
	$skip      = array_flip( maspik_elementor_atomic_internal_field_ids() );

	foreach ( $form_fields as $field ) {
		if ( ! is_array( $field ) ) {
			continue;
		}

		$id = isset( $field['id'] ) ? sanitize_text_field( (string) $field['id'] ) : '';
		if ( $id === '' || isset( $skip[ $id ] ) ) {
			continue;
		}

		$value = $field['value'] ?? '';
		if ( is_array( $value ) ) {
			$form_data[ $id ] = array_map( 'sanitize_text_field', $value );
			continue;
		}

		$type = isset( $field['type'] ) ? sanitize_text_field( (string) $field['type'] ) : 'text';
		if ( 'textarea' === $type ) {
			$form_data[ $id ] = sanitize_textarea_field( (string) $value );
		} else {
			$form_data[ $id ] = sanitize_text_field( (string) $value );
		}
	}

	if ( ! empty( $merged_post['maspik_referrer'] ) ) {
		$form_data['maspik_referrer'] = sanitize_text_field( (string) $merged_post['maspik_referrer'] );
	}

	return $form_data;
}

/**
 * Single string for validation (arrays collapsed like validateTextField).
 *
 * @param mixed  $value       Raw field value.
 * @param string $field_type  Field type from Atomic payload.
 * @return string
 */
function maspik_elementor_atomic_normalize_field_value( $value, $field_type ) {
	if ( is_array( $value ) ) {
		return sanitize_text_field( implode( ' ', array_map( 'sanitize_text_field', $value ) ) );
	}

	if ( 'textarea' === $field_type ) {
		return sanitize_textarea_field( (string) $value );
	}

	return sanitize_text_field( (string) $value );
}
