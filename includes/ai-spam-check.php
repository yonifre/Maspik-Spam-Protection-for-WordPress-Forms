<?php
// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * AI-based spam check functionality for Maspik plugin
 * 
 * This file contains the main AI spam check function that communicates
 * with an external AI API to detect spam submissions.
 * 
 * @package Maspik
 * @since 2.5.5
 */

/**
 * Resolved Matrix API check depth (mode): 2, 3, or 4. Default 4 when unset or invalid.
 *
 * @return int
 */
function maspik_matrix_api_mode_int(): int {
    $raw = maspik_get_settings( 'maspik_matrix_api_mode' );
    if ( $raw === null || $raw === '' ) {
        return 4;
    }
    $n = (int) $raw;
    return in_array( $n, array( 2, 3, 4 ), true ) ? $n : 4;
}

/**
 * First non-empty referrer: integration global → POST → HTTP Referer.
 *
 * @return string Unslashed raw string or ''.
 */
function maspik_matrix_resolve_referrer_raw(): string {
    $glob = isset( $GLOBALS['maspik_matrix_plugin_spam_likelihood_referrer'] ) ? $GLOBALS['maspik_matrix_plugin_spam_likelihood_referrer'] : null;
    if ( is_string( $glob ) && $glob !== '' ) {
        return $glob;
    }
    // phpcs:disable WordPress.Security.NonceVerification.Missing -- read-only; forwarded to Matrix only.
    if ( isset( $_POST['referrer'] ) && is_string( $_POST['referrer'] ) && $_POST['referrer'] !== '' ) {
        return wp_unslash( $_POST['referrer'] );
    }
    if ( ! empty( $_SERVER['HTTP_REFERER'] ) && is_string( $_SERVER['HTTP_REFERER'] ) ) {
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
        return wp_unslash( $_SERVER['HTTP_REFERER'] );
    }
    // phpcs:enable WordPress.Security.NonceVerification.Missing

    return '';
}

/**
 * Value for Matrix `maspik_referrer`: valid URL, else sanitized text label, else sentinel `no_referrer`.
 *
 * Detection rule:
 *   - Contains `://` → treated as URL → esc_url_raw() (may normalize/escape).
 *   - Otherwise      → treated as a sentinel/label and passed through sanitize_text_field() only.
 *     This avoids esc_url_raw() prepending `http://` and turning labels like `no_referrer` into
 *     invented URLs like `http://no_referrer`.
 *
 * Sentinels currently set by integrations (forwarded as-is, server-side correlates each one):
 *   - Elementor (elementor.php):           `no_referrer`           — $_POST['referrer'] missing + NeedPageurl on.
 *   - Contact Form 7 (cf7.php):            `no_cf7_id`             — $_POST['_wpcf7'] missing + NeedPageurl on.
 *   - Gravity Forms (gravityforms.php):    `no_gform_submit`       — $_POST['gform_submit'] empty/missing
 *                                          `no_match_gform_submit` — gform_submit ≠ resolved form_id
 *                                          `is_submit_invalid`     — is_submit_<id> missing/not '1'
 *                                          `no_state_token`        — state_<id> missing/empty
 *                                          `no_current_lead`       — GFFormsModel::get_current_lead() empty/non-array
 *   - WP Comments (wp-general.php):        `no_comment_post_id`    — $_POST['comment_post_ID'] missing/empty
 *                                          `no_comment_parent`     — $_POST['comment_parent'] not set
 *                                          `no_comment_body`       — $_POST['comment'] missing/empty
 *
 * @param string $raw From maspik_matrix_resolve_referrer_raw().
 * @return string
 */
function maspik_matrix_referrer_for_payload( string $raw ): string {
    if ( $raw === '' ) {
        return 'no_referrer';
    }
    if ( strpos( $raw, '://' ) === false ) {
        $as_label = sanitize_text_field( $raw );
        return ( $as_label !== '' ) ? $as_label : 'no_referrer';
    }
    $as_url = esc_url_raw( $raw );
    if ( $as_url !== '' ) {
        return $as_url;
    }
    $as_text = sanitize_text_field( $raw );

    return ( $as_text !== '' ) ? $as_text : 'no_referrer';
}

/**
 * Transient TTL (seconds) for the "monthly limit reached" cache after a server 429 response.
 *
 * The Matrix server is the source of truth for the monthly cap (HTTP 429 = limit reached).
 * After the server returns 429, the plugin caches that signal locally for this duration to
 * avoid hammering the API on every form submission until the cap resets / TTL expires.
 *
 * Filter: `maspik_matrix_limit_cache_ttl` — clamped to [60, 86400] seconds.
 *
 * @return int Seconds.
 */
function maspik_matrix_limit_cache_ttl(): int {
    $default = 15 * 60; // 15 minutes
    $ttl = (int) apply_filters( 'maspik_matrix_limit_cache_ttl', $default );
    return max( 60, min( 86400, $ttl ) );
}

/**
 * Whether the Matrix monthly limit was recently reported by the server (local cache).
 *
 * @return bool True only when the server-reported "limit reached" transient is active.
 */
function maspik_matrix_is_limit_reached_cached(): bool {
    return (bool) get_transient( 'maspik_matrix_limit_reached' );
}

/**
 * Mark the Matrix monthly limit as reached locally.
 *
 * TTL strategy:
 *   - If the server reported a `reset_at` (UTC ISO 8601 in the 429 response), cache until that
 *     instant — capped at 30 days as a safety belt against bad clocks. This avoids retrying the
 *     server during the entire month after the cap is hit.
 *   - Otherwise, cache for `maspik_matrix_limit_cache_ttl()` (default 15 min).
 *
 * Called from the 429 handler in maspik_ai_check_submission() AFTER quota info is saved, so the
 * `reset_at` from the response is already available. Pro sites are exempt — the cache is never
 * set for them, so a Free → Pro upgrade takes effect immediately.
 */
function maspik_matrix_set_limit_reached_cache(): void {
    if ( function_exists( 'cfes_is_supporting' ) && cfes_is_supporting() ) {
        return;
    }
    $ttl = maspik_matrix_limit_cache_ttl();
    if ( function_exists( 'maspik_matrix_get_quota_reset_at_ts' ) ) {
        $reset_ts = maspik_matrix_get_quota_reset_at_ts();
        if ( $reset_ts > 0 ) {
            $until_reset = $reset_ts - time();
            if ( $until_reset > 0 ) {
                $ttl = min( 30 * 86400, max( $ttl, $until_reset ) );
            }
        }
    }
    set_transient( 'maspik_matrix_limit_reached', 1, $ttl );
}

/**
 * Clear the local "limit reached" cache. Useful for tests, admin tooling, manual reset, or
 * when you want to force-recheck the server (e.g. after upgrading the plan).
 */
function maspik_matrix_clear_limit_reached_cache(): void {
    delete_transient( 'maspik_matrix_limit_reached' );
}

/**
 * Server-reported Matrix monthly quota info.
 *
 * Populated from Matrix API responses (200 + 429) when the site is on the free tier and the
 * server counted the request at its gate. Pro/licensed sites do not receive these fields.
 * Returns array with optional keys: `limit`, `used`, `remaining`, `reset_at` (ISO 8601 UTC),
 * `updated_at` (UNIX timestamp of last refresh).
 *
 * @return array
 */
function maspik_matrix_get_server_quota_info(): array {
    $info = get_option( 'maspik_matrix_server_quota', array() );
    return is_array( $info ) ? $info : array();
}

/**
 * Persist server-reported Matrix monthly quota info. Each parameter is merged only when not null
 * so the option is updated incrementally (e.g. a 200 response brings `remaining`, a 429 brings
 * `reset_at`). Always refreshes `updated_at`.
 *
 * @param int|null    $limit     monthly_limit from server.
 * @param int|null    $used      monthly_used from server.
 * @param int|null    $remaining monthly_remaining from server (200 only).
 * @param string|null $reset_at  ISO 8601 reset timestamp from server (429).
 */
function maspik_matrix_save_server_quota_info( $limit = null, $used = null, $remaining = null, $reset_at = null ): void {
    $current = maspik_matrix_get_server_quota_info();

    if ( $limit !== null && is_numeric( $limit ) ) {
        $current['limit'] = max( 0, (int) $limit );
    }
    if ( $used !== null && is_numeric( $used ) ) {
        $current['used'] = max( 0, (int) $used );
    }
    if ( $remaining !== null && is_numeric( $remaining ) ) {
        $current['remaining'] = max( 0, (int) $remaining );
    }
    if ( $reset_at !== null && is_string( $reset_at ) && $reset_at !== '' ) {
        $current['reset_at'] = sanitize_text_field( $reset_at );
    }
    $current['updated_at'] = time();

    update_option( 'maspik_matrix_server_quota', $current, false );
}

/**
 * UNIX timestamp of the next quota reset reported by the server, or 0 when unknown / already past.
 *
 * @return int
 */
function maspik_matrix_get_quota_reset_at_ts(): int {
    $info = maspik_matrix_get_server_quota_info();
    if ( ! isset( $info['reset_at'] ) || ! is_string( $info['reset_at'] ) || $info['reset_at'] === '' ) {
        return 0;
    }
    $ts = strtotime( $info['reset_at'] );
    if ( ! $ts || $ts <= time() ) {
        return 0;
    }
    return $ts;
}

/**
 * Check submission using AI-based spam detection
 * 
 * @param array $fields Array of form fields and their values
 * @param string $form_type Form integration slug (e.g. elementor, cf7).
 * @param int|null $plugin_spam_likelihood Optional 1–9; null = request floor (maspik_matrix_get_plugin_spam_likelihood_floor(), default 1).
 * @return array Array containing spam check results
 */
function maspik_ai_check_submission( array $fields, string $form_type = '', $plugin_spam_likelihood = null ): array {
    $ai_metrics_sent = 0;
    $ai_metrics_spam = 0;
    // Wrap entire function in try-catch to prevent any exceptions from breaking the site
    try {
        // Server is the source of truth for the monthly limit. We only short-circuit locally when
        // the server has *recently* told us the limit is reached (HTTP 429 → local transient cache,
        // see maspik_matrix_set_limit_reached_cache). Pro sites are exempt and bypass the cache.
        $is_pro_plan = function_exists( 'cfes_is_supporting' ) && cfes_is_supporting();
        if ( ! $is_pro_plan && maspik_matrix_is_limit_reached_cached() ) {
            if ( function_exists( 'maspik_ai_metrics_record_limit_skip' ) ) {
                maspik_ai_metrics_record_limit_skip( 1 );
            }
            return array( 'allow' => true, 'reason' => 'MASPIK Matrix paused: monthly limit reached (server-reported, cached)' );
        }

        // Get AI settings from options/DB with fallbacks to constants
        $endpoint = defined('MASPIK_AI_ENDPOINT') ? MASPIK_AI_ENDPOINT : '';

    // Pull license & token from the DLM option first
    $dlm = get_option('maspik_dlm_license'); // array with keys: key, token, expires_at, etc.
    $license = 'try_free_as_beta';
    $token   = 'try_free_as_beta';
    if ( is_array( $dlm ) && cfes_is_supporting() ) {
        $license = isset($dlm['key'])   ? trim((string)$dlm['key'])   : '';
        $token   = isset($dlm['token']) ? trim((string)$dlm['token']) : '';
    }
    
    //error_log('Maspik AI: Endpoint=' . $endpoint . ', Mode=' . (maspik_is_ai_beta_mode() ? 'beta' : 'live'));
    

    $context   = maspik_get_settings('maspik_ai_context', '' );
    $context   = is_string($context) ? $context : '';
    
    // Limit context to 170 characters
    if ( strlen($context) > 170 ) {
        $context = substr($context, 0, 170);
    }
    // In the new flow, the HMAC secret is the site token from DLM
    $secret    = $token;
    
    // Remove verbose config logging

    // If AI is disabled or missing configuration, allow submission
    if ( empty($endpoint) ) {
            return ['allow' => true, 'reason' => 'Matrix disabled: missing endpoint'];
    }
    
    if ( empty($license) ) {
        return ['allow' => true, 'reason' => 'Matrix disabled: missing license key'];
    }
    
    if ( empty($token) ) {
        return ['allow' => true, 'reason' => 'Matrix disabled: missing site token'];
    }

    // Build request payload with safe fallbacks
    $site_name = get_bloginfo('name');
    $site_desc = get_bloginfo('description');
    $site_title_tagline = (is_string($site_name) ? $site_name : '') . ' ' . (is_string($site_desc) ? $site_desc : '');
    
    $site_languages = maspik_detect_languages_array();
    if (!is_array($site_languages)) {
        $site_languages = [];
    }
    
    $client_ip = maspik_get_real_ip();
    if (!is_string($client_ip) || empty($client_ip)) {
        $client_ip = '';
    }

    $matrix_mode = maspik_matrix_api_mode_int();

    if ( $plugin_spam_likelihood === null && function_exists( 'maspik_matrix_get_plugin_spam_likelihood_floor' ) ) {
        $plugin_spam_likelihood = maspik_matrix_get_plugin_spam_likelihood_floor();
    }
    if ( $plugin_spam_likelihood === null ) {
        $plugin_spam_likelihood = 1;
    }
    $plugin_spam_likelihood = max( 1, min( 9, (int) $plugin_spam_likelihood ) );

    // Mode 2: IP reputation only — do not send form field contents (no text analysis on server).
    $context_block = [
        'business_info' => $context, // max 170 characters
        'site_url'      => home_url(),
        'plugin_version'=> defined('MASPIK_VERSION') ? MASPIK_VERSION : '1.0.0',
        'site_title_and_tagline' => $site_title_tagline,
        'site_languages' => $site_languages, // V2 array of all languages in the site
        'form_type' => $form_type,
        'mode'      => $matrix_mode,
        // 1 = low suspicion, 9 = high; integrations raise floor — filter: maspik_matrix_plugin_spam_likelihood_1_9.
        'plugin_spam_likelihood' => $plugin_spam_likelihood,
    ];

    $payload = [ 'context' => $context_block ];
    if ( $matrix_mode !== 2 ) {
        $payload['fields'] = $fields;
    }
    if ( efas_get_spam_api( 'NeedPageurl', 'bool' ) ) {
        $payload['maspik_referrer'] = maspik_matrix_referrer_for_payload( maspik_matrix_resolve_referrer_raw() );
    }
    
    // JSON string (not array!) - important for Lambda to receive proper structure
    $request_body = wp_json_encode( $payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
    
    // Verify JSON is valid
    if ( json_last_error() !== JSON_ERROR_NONE ) {
        //error_log('Maspik AI: JSON encode error: ' . json_last_error_msg());
        return ['allow' => true, 'reason' => 'AI error: JSON encoding failed'];
    }
    
    // Set up headers with authentication (License + site token + HMAC)
    $headers = [
        'Content-Type'      => 'application/json',
        'Accept'            => 'application/json',
        'User-Agent'        => 'Maspik-Plugin/' . ( defined( 'MASPIK_VERSION' ) ? MASPIK_VERSION : '2.0' ) . '; ' . home_url( '', 'https' ),
        'X-maspik-tkn'    => $token, // send token so Lambda can validate & cache on first request
        'X-Maspik-Is-Pro'   => cfes_is_supporting() ? '1' : '0',
        'Maspik-client_ip'  => $client_ip, // send client ip to validate against the IP blacklist
        'X-Maspik-Mode'     => (string) $matrix_mode,
    ];

    
    $headers['Authorization'] = 'Bearer ' . $license;
    

    // Add signature (HMAC over raw body using the per-site token)
    if ( ! empty($secret) && !empty($request_body) ) {
        $raw_sig = hash_hmac('sha256', $request_body, $secret, true);
        if ($raw_sig !== false) {
            $sig_encoded = base64_encode($raw_sig);
            if ($sig_encoded !== false) {
                $headers['X-Maspik-Signature'] = $sig_encoded;
            }
        }
    }

    $ai_metrics_sent = 1;

    // Send request to AI API
    $timeout = (int) apply_filters( 'maspik_ai_request_timeout', 7 );
    $timeout = max( 3, min( 30, $timeout ) ); // clamp 3–30 seconds

    //print request headers and body
    //error_log('Maspik AI: Request headers: ' . json_encode($headers));
    //error_log('Maspik AI: Request body: ' . $request_body);
    $resp = wp_remote_post( $endpoint, [
        'timeout'     => $timeout,
        'headers'     => $headers,
        'body'        => $request_body,     // keep as string, not array
        'sslverify'   => (bool) apply_filters( 'maspik_ai_ssl_verify', true ),
    ]);
    // Handle request errors gracefully
    if ( is_wp_error($resp) ) {
        // On failure, don't block - allow submission and log error
        $error_msg = $resp->get_error_message();
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'Maspik Matrix: HTTP error - ' . $error_msg );
        }
        return ['allow' => true, 'reason' => 'Matrix unavailable: ' . $error_msg];
    }

    $code = wp_remote_retrieve_response_code($resp);
    $response_body = wp_remote_retrieve_body($resp );
    
    // Ensure code is valid integer
    if (!is_numeric($code)) {
        $code = 0;
    } else {
        $code = (int) $code;
    }
    
    // Ensure response_body is string
    if (!is_string($response_body)) {
        $response_body = '';
    }
    
    // Try to decode JSON response
    $json = null;
    if ( !empty($response_body) && is_string($response_body) ) {
        $json = json_decode( $response_body, true );
        if ( json_last_error() !== JSON_ERROR_NONE ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log('Maspik Matrix: JSON decode failed - ' . json_last_error_msg());
            }
            $json = null; // Ensure json is null on error
        }
    }

    // Process successful response (new API format only)
    if ( $code === 200 && is_array($json) && !empty($json) ) {
        /**
         * New Maspik AI / ipapi.wpmaspik.com response format (no backward compatibility):
         * {
         *   "is_spam": true|false,
         *   "spam_score": 0-100|null,
         *   "reasons": ["..."],
         *   "model": "sms-spam-classifier",
         *   "monthly_limit": 200,        // optional, free tier with active quota
         *   "monthly_used": 75,          // optional
         *   "monthly_remaining": 125,    // optional
         *   ...
         * }
         *
         * - `is_spam` is the single source of truth for block/allow.
         * - `spam_score` is used only for UI / logs (optional).
         * - `monthly_*` are mirrored locally so the UI shows the server-reported numbers.
         */

        // Mirror server-reported monthly quota info (free tier only — Pro responses omit these).
        if ( isset( $json['monthly_limit'] ) || isset( $json['monthly_used'] ) || isset( $json['monthly_remaining'] ) ) {
            maspik_matrix_save_server_quota_info(
                $json['monthly_limit']     ?? null,
                $json['monthly_used']      ?? null,
                $json['monthly_remaining'] ?? null,
                null
            );
        }

        // Require at least is_spam or spam_score to make a decision
        if ( ! isset( $json['is_spam'] ) && ! isset( $json['spam_score'] ) ) {
            return ['allow' => true, 'reason' => 'Matrix response invalid: missing is_spam / spam_score'];
        }

        // Score is optional – use it only if it's valid, otherwise null
        $score = null;
        if ( isset( $json['spam_score'] ) ) {
            $tmp_score = (int) $json['spam_score'];
            if ( $tmp_score >= 0 && $tmp_score <= 100 ) {
                $score = $tmp_score;
            }
        }

        // Build human-friendly reason string:
        // 1. Prefer `user_reason` from the new API (already localized & readable for humans)
        // 2. Fallback to reasons[] / reason (technical reasons)
        $reason = '';
        if ( isset( $json['user_reason'] ) && is_string( $json['user_reason'] ) && $json['user_reason'] !== '' ) {
            $reason = $json['user_reason'];
        } elseif ( ! empty( $json['reasons'] ) && is_array( $json['reasons'] ) ) {
            $reason = implode( ', ', $json['reasons'] );
        } elseif ( isset( $json['reason'] ) && is_string( $json['reason'] ) ) {
            $reason = $json['reason'];
        }

        // Block decision: only by is_spam when present; if missing, allow
        $block = isset( $json['is_spam'] ) ? (bool) $json['is_spam'] : false;
        if ( $block ) {
            $ai_metrics_spam = 1;
        }

        if ( defined( 'WP_DEBUG' ) && WP_DEBUG && isset( $json['mode'] ) ) {
            $resp_mode = (int) $json['mode'];
            if ( $resp_mode !== $matrix_mode ) {
                error_log(
                    sprintf(
                        'Maspik Matrix API: mode mismatch (plugin sent %d, API returned %d)',
                        $matrix_mode,
                        $resp_mode
                    )
                );
            }
        }

        $result = [
            'allow'        => ! $block,
            'score'        => $score !== null ? $score : 0,
            'reason'       => $reason,
            'field_errors' => [], // not used in new API
            'provider'     => $json['model'] ?? 'ai',
            'business_info_preview' => [],
        ];
        
        // Save AI log to database (only if we have valid data)
        if ( is_array($fields) && is_array($json) && is_array($result) ) {
            try {
                maspik_save_ai_log($fields, $json, $result);
            } catch ( Exception $e ) {
                error_log('Maspik Matrix: Failed to save log: ' . $e->getMessage());
            }
        }
        
        return $result;
    }

    // Handle specific error codes with better error messages
    if ( $code === 401 ) {
        if ( maspik_is_ai_beta_mode() ) {
            $error_result = ['allow' => true, 'reason' => 'AI beta mode: unauthorized - check if endpoint supports beta mode'];
        } else {
            $error_result = ['allow' => true, 'reason' => 'AI live mode: unauthorized - check license key and signature'];
        }
        
        // Save 401 error log
        $error_response = ['error' => true, 'http_code' => 401, 'error_detail' => 'Unauthorized', 'response_body' => $response_body];
        if ( is_array($fields) && is_array($error_response) && is_array($error_result) ) {
            try {
                maspik_save_ai_log($fields, $error_response, $error_result);
            } catch ( Exception $e ) {
                if ( defined('WP_DEBUG') && WP_DEBUG ) {
                    error_log('Maspik AI: Failed to save 401 error log: ' . $e->getMessage());
                }
            }
        }
        return $error_result;
    }
    
    if ( $code === 403 ) {
        $error_result = ['allow' => true, 'reason' => 'AI live mode: license invalid or expired'];
        
        // Save 403 error log
        $error_response = ['error' => true, 'http_code' => 403, 'error_detail' => 'License invalid or expired', 'response_body' => $response_body];
        if ( is_array($fields) && is_array($error_response) && is_array($error_result) ) {
            try {
                maspik_save_ai_log($fields, $error_response, $error_result);
            } catch ( Exception $e ) {
            }
        }
        return $error_result;
    }
    
    if ( $code === 429 ) {
        // Server reports the monthly limit is reached. Expected JSON shape:
        //   { "error": "monthly_limit_reached", "monthly_limit": 200, "monthly_used": 200,
        //     "reset_at": "2026-06-01T00:00:00.000Z" }
        // Mirror those values locally FIRST so the smart TTL inside maspik_matrix_set_limit_reached_cache()
        // can read `reset_at` and cache until that instant (instead of falling back to 15 min).
        if ( is_array( $json ) ) {
            maspik_matrix_save_server_quota_info(
                $json['monthly_limit'] ?? null,
                $json['monthly_used']  ?? null,
                null,
                $json['reset_at']      ?? null
            );
        }
        if ( function_exists( 'maspik_matrix_set_limit_reached_cache' ) ) {
            maspik_matrix_set_limit_reached_cache();
        }

        // This attempt did not result in a real spam check — don't count it toward the local
        // "checks sent" counter. Record it as a limit skip instead so metrics stay accurate.
        $ai_metrics_sent = 0;
        if ( function_exists( 'maspik_ai_metrics_record_limit_skip' ) ) {
            maspik_ai_metrics_record_limit_skip( 1 );
        }

        $error_result = ['allow' => true, 'reason' => 'Matrix: monthly limit reached (server 429)'];

        // Save 429 error log
        $error_response = ['error' => true, 'http_code' => 429, 'error_detail' => 'Monthly limit reached', 'response_body' => $response_body];
        if ( is_array($fields) && is_array($error_response) && is_array($error_result) ) {
            try {
                maspik_save_ai_log($fields, $error_response, $error_result);
            } catch ( Exception $e ) {
                if ( defined('WP_DEBUG') && WP_DEBUG ) {
                    error_log('Maspik AI: Failed to save 429 error log: ' . $e->getMessage());
                }
            }
        }
        return $error_result;
    }
    
    if ( $code === 500 ) {
        $error_result = ['allow' => true, 'reason' => 'AI server error - check server logs'];
        
        // Enhanced 500 error logging with more details
        $error_detail = 'Server error';
        $response_headers = wp_remote_retrieve_headers($resp);
        $headers_array = [];
        
        // Safely extract headers
        if ($response_headers && is_object($response_headers) && method_exists($response_headers, 'getAll')) {
            try {
                $headers_array = $response_headers->getAll();
                if (!is_array($headers_array)) {
                    $headers_array = [];
                }
            } catch (Exception $e) {
                $headers_array = [];
            }
        }
        
        // Try to extract more info from response body
        if ( !empty($response_body) && is_string($response_body) ) {
            $json_error = json_decode($response_body, true);
            if ( is_array($json_error) && isset($json_error['error']) && is_string($json_error['error']) ) {
                $error_detail = $json_error['error'];
            } else {
                $error_detail = 'Server error: ' . substr($response_body, 0, 200); // First 200 chars
            }
        }
        
        // Save enhanced 500 error log
        $error_response = [
            'error' => true, 
            'http_code' => 500, 
            'error_detail' => $error_detail, 
            'response_body' => is_string($response_body) ? $response_body : '',
            'response_headers' => $headers_array,
            'timestamp' => current_time('mysql')
        ];
        
        if ( is_array($fields) && is_array($error_response) && is_array($error_result) ) {
            try {
                maspik_save_ai_log($fields, $error_response, $error_result);
            } catch ( Exception $e ) {
                if ( defined('WP_DEBUG') && WP_DEBUG ) {
                    error_log('Maspik AI: Failed to save 500 error log: ' . $e->getMessage());
                }
            }
        }
        
        // Also log to WordPress error log for immediate visibility
        if ( defined('WP_DEBUG') && WP_DEBUG ) {
            error_log('Maspik AI 500 Error: ' . $error_detail . ' | Response: ' . $response_body);
            // Try to extract user_reason from response
            if ( !empty($response_body) ) {
                $response_json = json_decode($response_body, true);
                if ( is_array($response_json) && isset($response_json['user_reason']) ) {
                    error_log( 'Maspik AI: user_reason = ' . $response_json['user_reason'] );
                } else {
                    error_log( 'Maspik AI: user_reason = N/A (not found in response)' );
                }
            }
        }
        
        return $error_result;
    }

    // Handle unknown error codes
    $error_detail = '';
    
    if ( !empty($response_body) && is_string($response_body) ) {
        $error_json = json_decode($response_body, true);
        if ( is_array($error_json) && isset($error_json['error']) && is_string($error_json['error']) ) {
            $error_detail = ' - ' . $error_json['error'];
        }
    }
        
    $error_result = ['allow' => true, 'reason' => 'AI unknown error ' . $code . $error_detail];
    
    // Save error log to database
    $error_response = [
        'error' => true,
        'http_code' => $code,
        'error_detail' => $error_detail,
        'response_body' => $response_body
    ];
    
    if ( is_array($fields) && is_array($error_response) && is_array($error_result) ) {
        try {
            maspik_save_ai_log($fields, $error_response, $error_result);
        } catch ( Exception $e ) {
            if ( defined('WP_DEBUG') && WP_DEBUG ) {
                error_log('Maspik AI: Failed to save error log: ' . $e->getMessage());
            }
        }
    }
    
    return $error_result;

    } catch ( Exception $e ) {
        // On any exception, don't block the form - log error and allow submission
        if ( defined('WP_DEBUG') && WP_DEBUG ) {
            error_log('Maspik AI Check Exception: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        }
        return ['allow' => true, 'reason' => 'AI check error: ' . $e->getMessage()];
    } catch ( Error $e ) {
        // On fatal error, don't block the form - log error and allow submission
        if ( defined('WP_DEBUG') && WP_DEBUG ) {
            error_log('Maspik AI Check Fatal Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        }
        return ['allow' => true, 'reason' => 'AI check fatal error: ' . $e->getMessage()];
    } finally {
        if ( $ai_metrics_sent > 0 ) {
            maspik_ai_metrics_record( $ai_metrics_sent, $ai_metrics_spam );
        }
    }
}

/**
 * Save AI log entry to settings
 * 
 * @param array $fields Form fields that were submitted
 * @param array $ai_response Full AI response
 * @param array $result Final result (allow/block)
 * @return bool Success status
 */
function maspik_save_ai_log( array $fields, array $ai_response, array $result ): bool {
    // Get current logs and ensure it's an array
    $current_logs = maspik_get_settings('maspik_ai_logs', []);
    
    // If current_logs is null or not an array, initialize as empty array
    if ( !is_array($current_logs) ) {
        $current_logs = [];
    }
    
    // Prepare new log entry
    $log_entry = [
        'timestamp' => current_time('mysql'),
        'fields' => $fields,
        'ai_response' => $ai_response,
        'result' => $result,
        'ip_address' => maspik_get_real_ip(),
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
    ];
    
    // Add new entry to beginning of array
    array_unshift($current_logs, $log_entry);
    
    // Keep only last 10 entries
    $current_logs = array_slice($current_logs, 0, 10);
    
    // Save back to settings
    $saved = maspik_save_settings('maspik_ai_logs', $current_logs);
        
    return $saved;
}

/**
 * Get AI logs from settings (last 10 entries)
 * 
 * @return array Array of log entries
 */
function maspik_get_ai_logs(): array {
    $logs = maspik_get_settings('maspik_ai_logs', []);
    
    // Ensure we return an array
    if ( !is_array($logs) ) {
        return [];
    }
    
    return $logs;
}


/**
 * Check if AI endpoint is in beta mode
 * 
 * @return bool True if beta mode is enabled
 */
function maspik_is_ai_beta_mode(): bool {
    // Check if there's a specific beta mode setting
   
    return 1;
}

/**
 * Helper function to prepare fields for AI analysis
 * 
 * @param array $form_data Raw form data
 * @param string $form_type Type of form (e.g., 'elementor', 'contact-form-7') - now optional, uniform processing
 * @return array Processed fields ready for AI analysis - flat key-value array
 */
function maspik_prepare_fields_for_ai( array $form_data, string $form_type = '' ): array {
    $processed_fields = [];

    // Each integration passes content_fields or post; use as-is and process uniformly.
    $raw_fields = $form_data;

    // Process all fields uniformly
    foreach ( $raw_fields as $key => $value ) {
        // Convert key to string for consistent processing
        $key = (string) $key;
        
        // Skip fields that start with underscore
        if ( strpos($key, '_') === 0 ) {
            continue;
        }
                
        // Skip keys that contain unwanted terms (case-insensitive)
        $unwanted_terms = ['hidden','action', 'nonce', 'submit', 'referrer', 'time', 'key', 'gclid', 'utm_', 'url', 'redirect', 'link', 'ref','hash','maspik','full-name-maspik-hp','honeypot','token','password','productid','formId','postId','campaign','date','turnstile','cf-chl','response','recaptcha','captcha','gform_'];
        $key_lower = strtolower($key);
        foreach ( $unwanted_terms as $term ) {
            $t = strtolower( $term );
            // WordPress comment website field — must not match generic substring "url".
            if ( 'url' === $t && 'comment_author_url' === $key_lower ) {
                continue;
            }
            if ( strpos( $key_lower, $t ) !== false ) {
                continue 2; // Skip to next field in outer loop
            }
        }
        
        // Extract value from nested structures
        $processed_value = $value;
        
        // Handle array values (extract 'value' key if exists)
        if ( is_array($processed_value) ) {
            if ( isset($processed_value['value']) ) {
                $processed_value = $processed_value['value'];
            } else {
                // For other array structures, convert to string representation
                $processed_value = implode(', ', array_filter(array_map('strval', $processed_value)));
            }
        }
        
        // Convert to string and sanitize
        $processed_value = sanitize_text_field((string) $processed_value);
        
        // Skip empty values after processing
        if ( empty($processed_value) ) {
            continue;
        }
        
        // Limit field value to 500 characters
        if ( strlen($processed_value) > 500 ) {
            $processed_value = substr($processed_value, 0, 500);
        }
        
        // Add to final processed fields array
        $processed_fields[$key] = $processed_value;
    }

    // Add form type as a field so the AI sees it alongside other fields (prepared here, not in the submission function).
    // Not needed for now:
    //$processed_fields['Form type'] = is_string( $form_type ) ? $form_type : '';

    return $processed_fields;
} 


/**
 * Fast lightweight language detector for WordPress sites.
 *
 * Returns only short 2-letter codes (['he','en','fr']).
 * Optimized for running on every form submission.
 *
 *
 * @return array
 */
function maspik_detect_languages_array() {
    static $runtime_cache = null;

    // Cache to avoid recomputing inside same request
    if ( is_array( $runtime_cache ) ) {
        return $runtime_cache;
    }

    $codes = array();

    // Extract "he_IL" → "he"
    $extract = function( $locale ) {
        if ( empty( $locale ) || ! is_string( $locale ) ) {
            return null;
        }

        $parts = explode( '_', $locale );
        return strtolower( $parts[0] );
    };

    // Add safely
    $add = function( $locale ) use ( &$codes, $extract ) {
        $code = $extract( $locale );
        if ( $code && ! in_array( $code, $codes, true ) ) {
            $codes[] = $code;
        }
    };

    // 1. Site locale
    $add( get_locale() );

    // 2. User locale
    if ( function_exists( 'get_user_locale' ) ) {
        $add( get_user_locale() );
    }

    // 3. All installed core languages (Settings → Site Language list)
    if ( function_exists( 'get_available_languages' ) ) {
        $installed = get_available_languages();
        if ( is_array( $installed ) ) {
            foreach ( $installed as $locale ) {
                $add( $locale );
            }
        }
    }

    // 4. WPML
    if ( has_filter( 'wpml_active_languages' ) ) {
        $wpml = apply_filters( 'wpml_active_languages', null );
        if ( is_array( $wpml ) ) {
            foreach ( $wpml as $data ) {
                if ( ! empty( $data['default_locale'] ) ) {
                    $add( $data['default_locale'] );
                } elseif ( ! empty( $data['language_code'] ) ) {
                    $add( $data['language_code'] );
                }
            }
        }
    }

    // 5. Polylang
    if ( function_exists( 'pll_the_languages' ) ) {
        $pll = pll_the_languages( array( 'raw' => 1 ) );
        if ( is_array( $pll ) ) {
            foreach ( $pll as $lang ) {
                if ( ! empty( $lang['locale'] ) ) {
                    $add( $lang['locale'] );
                }
            }
        }
    }

    // 6. TranslatePress
    if ( class_exists( 'TRP_Languages' ) ) {
        $trp = TRP_Languages::get_languages();
        if ( is_array( $trp ) ) {
            foreach ( $trp as $info ) {
                if ( ! empty( $info['default_locale'] ) ) {
                    $add( $info['default_locale'] );
                } elseif ( ! empty( $info['code'] ) ) {
                    $add( $info['code'] );
                }
            }
        }
    }

    // 7. Weglot
    if ( function_exists( 'weglot_get_languages_available' ) ) {
        $weglot = weglot_get_languages_available();
        if ( is_array( $weglot ) ) {
            foreach ( array_keys( $weglot ) as $code ) {
                $code = strtolower( $code );
                if ( ! in_array( $code, $codes, true ) ) {
                    $codes[] = $code;
                }
            }
        }
    }

    // Final cleanup: keep exactly 2-letter codes
    $codes = array_filter( $codes, function( $code ) {
        return is_string( $code ) && strlen( $code ) === 2 && ctype_alpha( $code );
    } );

    sort( $codes );
    $codes = array_values( $codes );

    // Save in single-request runtime cache
    $runtime_cache = $codes;

    return $codes;
}




