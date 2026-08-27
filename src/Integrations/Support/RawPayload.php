<?php

declare(strict_types=1);

namespace Maspik\Integrations\Support;

use Maspik\Domain\Check\HoneypotCheck;
use Maspik\Domain\Check\VerificationKeyCheck;

/**
 * Captures everything a form submitted, for the log.
 *
 * The engine only scans text-ish fields, and that is correct — a submit button
 * or an acceptance checkbox is not spam signal. But the log answers a different
 * question: was this a real customer? An owner cannot judge that from the text
 * fields alone when the form also asked which service, which branch, and on what
 * date. Version 2 logged the whole request for exactly this reason; keeping only
 * the scanned fields quietly took that away.
 *
 * Every form plugin structures its data differently — Gravity Forms posts
 * `input_1_3`, Divi posts `et_pb_contact_0_name_0`, WPForms nests under
 * `wpforms[fields][3]` — so this reads the request as a whole instead of
 * teaching each adapter a new trick.
 *
 * Pure by design (no WordPress), so the flattening, masking and capping can be
 * tested against the shapes real plugins actually post.
 */
final class RawPayload
{
    /** Values longer than this are clipped; a spam body can be megabytes. */
    private const MAX_VALUE_LENGTH = 5000;

    /** Hard cap on entries, so a crafted request cannot bloat the log table. */
    private const MAX_FIELDS = 200;

    /**
     * Request keys that are ours, not the visitor's. They carry no information
     * an owner could use to judge a submission, and the verification key is a
     * per-site secret that has no business being stored in a log row.
     */
    private const SKIP_KEYS = [
        HoneypotCheck::FIELD_NAME,
        VerificationKeyCheck::FIELD_NAME,
    ];

    /**
     * Form plumbing, never something a visitor typed.
     *
     * Matched at any depth, because plugins carry the same housekeeping inside
     * their own envelope: WPForms posts wpforms[nonce] and wpforms[token]
     * alongside the real answers. Nonces and tokens are the important ones —
     * they are single-use secrets with no business sitting in a log row — but
     * the rest is equally useless to an owner deciding whether a submission was
     * a real customer.
     */
    private const NOISE_KEYS = [
        'action',
        'security',
        'nonce',
        '_nonce',
        '_wpnonce',
        '_ajax_nonce',
        '_wp_http_referer',
        'token',
        'submit',
        'post_id',
        'page_id',
        'page_title',
        'url_referer',
        'url_referrer',
        'start_timestamp',
        'form_id',
        'gform_submit',
        'gform_unique_id',
        'gform_field_values',
    ];

    /**
     * Plumbing whose name carries the form id, so it cannot be listed exactly:
     * Gravity Forms posts is_submit_2, state_2, gform_target_page_number_2 and
     * friends alongside the answers.
     */
    /**
     * Plumbing recognisable by a fragment of the name rather than its start.
     *
     * Fluent Forms names its transport fields around the form id, so neither an
     * exact match nor a prefix catches them: item_3__fluent_sf,
     * __fluent_form_embded_post_id, _fluentform_3_fluentformnonce. Nonces are
     * matched the same way — no visitor field is ever called one, and they are
     * single-use secrets with no business in a log row.
     */
    private const NOISE_SUBSTRINGS = [
        'nonce',
        '_fluent_',
    ];

    private const NOISE_PREFIXES = [
        'is_submit_',
        'state_',
        // Contact Form 7 stamps every submission with its own transport fields
        // — _wpcf7, _wpcf7_version, _wpcf7_locale, _wpcf7_unit_tag,
        // _wpcf7_container_post, _wpcf7_posted_data_hash. Six rows of form
        // plumbing next to the four the visitor actually filled in.
        '_wpcf7',
        // Gravity Forms namespaces every one of its own transport fields with
        // `gform_` and names the visitor's answers `input_*`, so the whole
        // prefix is plumbing: gform_submit, gform_theme, gform_style_settings,
        // gform_submission_method, gform_target_page_number… The worst of them
        // is state_N, a long base64 blob that the log picked as the submission's
        // "message" simply because it was the longest value on the row.
        'gform_',
    ];

    /**
     * @param array<string|int, mixed> $post typically $_POST, already unslashed
     * @return array<string, string>
     */
    /**
     * @param array<string|int, mixed> $post typically $_POST, already unslashed
     * @param string $secret the site's verification key, redacted wherever it appears
     * @return array<string, string>
     */
    public static function capture(array $post, string $secret = ''): array
    {
        $out = [];
        self::walk($post, '', $out, $secret);

        return $out;
    }

    /**
     * Flattens nested values into bracketed paths (`wpforms[fields][3]`) so a
     * plugin that nests its data keeps every value *and* the shape that explains
     * where the value came from.
     *
     * @param mixed $value
     * @param array<string, string> $out
     */
    private static function walk($value, string $path, array &$out, string $secret = ''): void
    {
        if (count($out) >= self::MAX_FIELDS) {
            return;
        }

        if (is_array($value)) {
            foreach ($value as $key => $child) {
                $name = (string) $key;
                if (self::isSkippable($name, $path === '')) {
                    continue;
                }
                self::walk($child, $path === '' ? $name : $path . '[' . $name . ']', $out, $secret);
            }

            return;
        }

        // The verification key is a per-site secret. SKIP_KEYS drops it when it
        // arrives under its own name, but Elementor Atomic carries it inside its
        // field envelope as form_fields[6][value], where the name says nothing —
        // so it was being written to the log, and being the longest value on the
        // row it was even displayed as the visitor's "message". Matched by value
        // so it cannot hide behind whatever shape the next integration invents.
        if ($secret !== '' && is_scalar($value) && (string) $value === $secret) {
            return;
        }

        if ($path === '' || is_object($value)) {
            return;
        }

        // Some plugins post the whole form as one JSON string in a single field
        // — Ninja Forms sends `formData` that way. Stored verbatim it is an
        // unreadable wall that also buries the real answers, so it is expanded
        // like any other nested value: every submitted value stays, and each one
        // is legible on its own line.
        $unwrapped = self::decodeStructure($value) ?? self::decodeFormBody($value);
        if ($unwrapped !== null) {
            self::walk($unwrapped, $path, $out, $secret);

            return;
        }

        // An expanded envelope repeats its own indexes back as data:
        // formData[fields][5][id] = 5 says nothing the key did not already say.
        if (self::echoesItsOwnKey($path, $value)) {
            return;
        }

        $out[$path] = self::clean($path, $value);
    }

    /**
     * True for a leaf that merely restates the key it hangs under, such as
     * `…[5][id] = 5`. Real answers are dropped only if a visitor typed a field's
     * own index into it, which costs nothing to lose.
     *
     * @param mixed $value
     */
    private static function echoesItsOwnKey(string $path, $value): bool
    {
        if (! is_scalar($value) || substr($path, -4) !== '[id]') {
            return false;
        }

        $parts = explode('[', rtrim($path, ']'));
        $parent = rtrim(end($parts) ?: '', ']');
        $parent = $parts ? rtrim($parts[count($parts) - 2] ?? '', ']') : '';

        return $parent !== '' && (string) $value === $parent;
    }

    /**
     * Envelope members that describe the form rather than the submission.
     *
     * Only ever skipped *inside* a plugin's envelope, never at the top level,
     * for the same reason as `id`: a visitor-facing field could legitimately be
     * named "settings", and dropping a real answer is the worse mistake.
     *
     * Ninja Forms is why this exists. It posts the entire form definition back
     * with every submission under formData[settings] — the styling boxes, the
     * builder metadata, and its whole front-end translation table: every
     * validation message, every month name, every weekday abbreviation. A real
     * three-field contact form logged 186 values, 181 of them from that block
     * and one of them the visitor's. That buried the actual answers, and at 186
     * it was close enough to MAX_FIELDS that a longer form would have started
     * dropping real ones to make room for month names.
     */
    private const NOISE_ENVELOPE_KEYS = [
        'id',
        'settings',
        // Elementor Atomic posts its whole field list alongside the answers:
        // form_fields[0][type] = text, form_fields[0][label] = url, and so on
        // for every field on the form. That is the form's design, not what
        // anybody submitted — 14 of the 22 values on a real row.
        'type',
        'label',
    ];

    /**
     * True for our own guard fields and for form plumbing.
     *
     * A bare `id` or `settings` is only dropped inside an envelope
     * (wpforms[id], formData[settings]). At the top level either is far likelier
     * to be a field someone actually named that, and dropping a real answer is
     * the worse mistake.
     */
    private static function isSkippable(string $name, bool $atRoot): bool
    {
        $lower = strtolower($name);

        if (in_array($name, self::SKIP_KEYS, true) || in_array($lower, self::NOISE_KEYS, true)) {
            return true;
        }

        foreach (self::NOISE_PREFIXES as $prefix) {
            if (strpos($lower, $prefix) === 0) {
                return true;
            }
        }

        foreach (self::NOISE_SUBSTRINGS as $fragment) {
            if (strpos($lower, $fragment) !== false) {
                return true;
            }
        }

        return ! $atRoot && in_array($lower, self::NOISE_ENVELOPE_KEYS, true);
    }

    /**
     * A JSON object/array carried inside a string field, or null when the value
     * is just text. Scalars stay scalars: a message that happens to start with a
     * brace is still a message.
     *
     * @param mixed $value
     * @return array<int|string, mixed>|null
     */
    private static function decodeStructure($value): ?array
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '' || ($trimmed[0] !== '{' && $trimmed[0] !== '[')) {
            return null;
        }

        $decoded = json_decode($trimmed, true);

        return is_array($decoded) && $decoded !== [] ? $decoded : null;
    }

    /**
     * A whole form body carried inside one string field, or null.
     *
     * Fluent Forms submits over AJAX with the entire form serialised into
     * $_POST['data'], so the log stored one unreadable line —
     * "item_3__fluent_sf=&_fluentform_3_fluentformnonce=…&Email=ken%40…" — as
     * the visitor's message, with the site's verification key sitting in the
     * middle of it. Expanded, every answer becomes its own entry and the guard
     * fields are dropped by name like they are everywhere else.
     *
     * The test is deliberately strict, because a person can legitimately type
     * "a=1&b=2" into a message box. A serialised body percent-encodes its
     * spaces, so any literal whitespace means this is prose, not a payload; and
     * one pair is an equation, while several with well-formed names is a form.
     *
     * @param mixed $value
     * @return array<int|string, mixed>|null
     */
    public static function decodeFormBody($value): ?array
    {
        if (! is_string($value) || $value === '' || preg_match('/\s/', $value) === 1) {
            return null;
        }
        if (strpos($value, '=') === false || strpos($value, '&') === false) {
            return null;
        }

        $pairs = explode('&', $value);
        if (count($pairs) < 3) {
            return null;
        }
        foreach ($pairs as $pair) {
            // Every segment must read as name=value, with a name that could be
            // a form field. One malformed segment and this is not a body.
            if (strpos($pair, '=') === false) {
                return null;
            }
            $name = substr($pair, 0, strpos($pair, '='));
            if ($name === '' || preg_match('/^[A-Za-z_][A-Za-z0-9_.\-\[\]%]*$/', $name) !== 1) {
                return null;
            }
        }

        $parsed = [];
        parse_str($value, $parsed);

        return count($parsed) > 1 ? $parsed : null;
    }

    /**
     * A guard field's submitted value, wherever the plugin put it.
     *
     * The honeypot and the verification key are added to the form by our own
     * script, so they are posted like any other input — unless the plugin does
     * not post inputs. Fluent Forms submits over AJAX with the entire form
     * serialised into $_POST['data'], so `$_POST['maspik_spam_key']` is unset
     * even though the field is right there in the markup. The key check then
     * saw nothing and rejected the submission: every genuine Fluent Forms
     * submission on a site with the key enabled was blocked as spam.
     *
     * Searching the request instead of one fixed location fixes it for any
     * plugin that batches its fields, without each adapter having to know it
     * does. A bot that omits the field still finds nothing here, so the check
     * stays as strict as it was.
     *
     * @param array<string|int, mixed> $post already unslashed
     */
    public static function findField(array $post, string $name): string
    {
        if (isset($post[$name]) && is_scalar($post[$name])) {
            return (string) $post[$name];
        }

        foreach ($post as $value) {
            if (is_array($value)) {
                $nested = self::findField($value, $name);
                if ($nested !== '') {
                    return $nested;
                }
                continue;
            }

            if (! is_string($value)) {
                continue;
            }

            $unwrapped = self::decodeStructure($value) ?? self::decodeFormBody($value);
            if ($unwrapped !== null) {
                $found = self::findField($unwrapped, $name);
                if ($found !== '') {
                    return $found;
                }
            }
        }

        return '';
    }

    /**
     * The same capture for adapters that never see `$_POST`: the Custom Form API
     * is handed its fields by the developer's own code, and the WooCommerce block
     * checkout reads them off the order object.
     *
     * Takes the adapter's own field list *before* type filtering, so a select or
     * a date reaches the log even though the engine will not scan it.
     *
     * @param array<int, array{name?: string, type?: string, value?: mixed}> $rawFields
     * @return array<string, string>
     */
    public static function fromList(array $rawFields): array
    {
        $out = [];
        foreach ($rawFields as $field) {
            if (! is_array($field)) {
                continue;
            }
            $name = isset($field['name']) ? (string) $field['name'] : '';
            if ($name === '' || in_array($name, self::SKIP_KEYS, true)) {
                continue;
            }
            if (count($out) >= self::MAX_FIELDS) {
                break;
            }
            $value = $field['value'] ?? '';
            $out[$name] = is_array($value)
                ? self::clean($name, implode(' ', self::leaves($value)))
                : self::clean($name, $value);
        }

        return $out;
    }

    /**
     * @param array<int|string, mixed> $value
     * @return array<int, string>
     */
    private static function leaves(array $value): array
    {
        $out = [];
        array_walk_recursive($value, static function ($leaf) use (&$out): void {
            if (is_scalar($leaf)) {
                $out[] = (string) $leaf;
            }
        });

        return $out;
    }

    /**
     * @param mixed $value
     */
    private static function clean(string $path, $value): string
    {
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (! is_scalar($value) && $value !== null) {
            return '';
        }

        $text = (string) $value;

        // Never store a password, even though a form that posts one through a
        // spam check is already doing something unusual. Matches v2's rule.
        if (stripos($path, 'pass') !== false) {
            return $text === '' ? '' : '********';
        }

        // Strip control characters that would corrupt the JSON envelope, but
        // keep newlines and tabs — a message field's line breaks are part of
        // what the owner is reading.
        $text = (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text);

        if (mb_strlen($text) > self::MAX_VALUE_LENGTH) {
            $text = mb_substr($text, 0, self::MAX_VALUE_LENGTH) . '…';
        }

        return $text;
    }
}
