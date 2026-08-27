<?php
/**
 * MASPIK public API for custom forms.
 *
 * Built your own form? These helpers are the whole integration surface. They
 * are plain functions in the global namespace so they read naturally in theme
 * and plugin code.
 *
 * Quick start — list the fields you want analysed, then act on the result:
 *
 *     $fields = [
 *         [ 'type' => 'text',     'field_name' => 'name',    'value' => $_POST['name'] ?? '' ],
 *         [ 'type' => 'email',    'field_name' => 'email',   'value' => $_POST['email'] ?? '' ],
 *         [ 'type' => 'textarea', 'field_name' => 'message', 'value' => $_POST['message'] ?? '' ],
 *     ];
 *
 *     if ( function_exists( 'maspik_is_spam' ) && maspik_is_spam( $fields, 'Contact form' ) ) {
 *         wp_die( 'Your submission looks like spam.' );
 *     }
 *
 * Need the reason (to show a message or flag a field)?
 *
 *     $result = maspik_check_spam( $fields, 'Contact form' );
 *     if ( $result ) {
 *         echo esc_html( $result['message'] );   // safe to show the visitor
 *         error_log( $result['reason'] );        // technical detail for you
 *     }
 *
 * Listing the fields explicitly is the recommended approach: MASPIK then looks
 * at exactly the human-written values you care about, and never at nonces,
 * redirects, tokens, checkboxes or other technical inputs that would only make
 * detection less predictable.
 *
 * For quick prototypes you may also pass a plain [ name => value ] map (such as
 * $_POST) and MASPIK will infer each field's type — convenient, but it inspects
 * everything you hand it, so prefer the explicit list in production.
 *
 * The honeypot and verification key are read from $_POST automatically — you
 * don't need to forward them.
 *
 * @package Maspik
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'maspik_check_spam' ) ) {
    /**
     * Check a submission and return the details when it is spam.
     *
     * @param array|string $fields    The fields to analyse. Recommended: an
     *                                explicit list of
     *                                ['type','field_name','value'] entries, so
     *                                only the values you choose are inspected.
     *                                Also accepts a plain [name => value] map
     *                                (e.g. $_POST) with inferred types, or a
     *                                single string to check as a message.
     * @param string       $form_name Label shown in the MASPIK logs.
     * @return array|false False when the submission is clean. When it is spam:
     *                     [
     *                       'spam'       => true,
     *                       'message'    => string, // safe to show the visitor
     *                       'reason'     => string, // technical, for your logs
     *                       'field_type' => string, // 'email', 'textarea', … or 'general'
     *                       'field_name' => string, // offending field, or 'general'
     *                       'check_id'   => string, // layer that blocked it
     *                     ]
     */
    function maspik_check_spam( $fields, $form_name = 'Custom Form' ) {
        return apply_filters( 'maspik_validate_custom_form_fields', false, $fields, $form_name );
    }
}

if ( ! function_exists( 'maspik_is_spam' ) ) {
    /**
     * Simple yes/no check, for when you only need to stop the submission.
     *
     * @param array|string $fields    See {@see maspik_check_spam()}.
     * @param string       $form_name Label shown in the MASPIK logs.
     * @return bool True when the submission is spam.
     */
    function maspik_is_spam( $fields, $form_name = 'Custom Form' ) {
        return false !== maspik_check_spam( $fields, $form_name );
    }
}

if ( ! function_exists( 'maspik_spam_message' ) ) {
    /**
     * The message to show a visitor whose submission was blocked, or '' when
     * the submission is clean.
     *
     * @param array|string $fields    See {@see maspik_check_spam()}.
     * @param string       $form_name Label shown in the MASPIK logs.
     * @return string
     */
    function maspik_spam_message( $fields, $form_name = 'Custom Form' ) {
        $result = maspik_check_spam( $fields, $form_name );

        return is_array( $result ) && isset( $result['message'] ) ? (string) $result['message'] : '';
    }
}
