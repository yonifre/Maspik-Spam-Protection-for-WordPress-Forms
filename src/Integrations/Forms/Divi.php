<?php

declare(strict_types=1);

namespace Maspik\Integrations\Forms;

use Maspik\Application\SpamGate;
use Maspik\Domain\Model\FieldType;
use Maspik\Integrations\AbstractFormIntegration;
use Maspik\Integrations\Support\FieldMapper;

if (! defined('ABSPATH')) {
    exit;
}


/**
 * Divi Builder / theme — Contact Form module (et_pb_contact_form).
 *
 * Divi exposes no spam hook that can reject a submission, so — as in v2 — we
 * validate early on wp_loaded and, when spam is found, block the outgoing
 * wp_mail() calls (pre_wp_mail) and swap Divi 5's generic failure text for the
 * spam reason (gettext). Request-scoped state lives on the instance (v2 used
 * $GLOBALS) and is cleared on shutdown.
 *
 * Two payload shapes are supported: legacy (numeric form index) and Divi 5
 * (uuid). Both post et_pb_contact_email_fields_{id} — a JSON array of field
 * rows — with values in $_POST[field_id]. Guard fields (honeypot/key) arrive in
 * $_POST via the shared front-end guard script.
 */
final class Divi extends AbstractFormIntegration
{
    /** Divi 5's exact failure msgid we replace with the spam reason. */
    private const DIVI5_MAIL_FAILURE = 'There was an error trying to send your message. Please try again later.';

    /** @var bool spam detected this request */
    private $blocked = false;

    /** @var string error message to surface */
    private $errorMessage = '';

    /** @var int remaining wp_mail calls to suppress (admin + confirmation) */
    private $mailBlocksRemaining = 0;

    /** Divi field row type => FieldType. */
    public static function typeMap(): array
    {
        return [
            'text' => FieldType::TEXT,
            'email' => FieldType::EMAIL,
            'tel' => FieldType::TEL,
            'url' => FieldType::URL,
            'textarea' => FieldType::TEXTAREA,
        ];
    }

    public function id(): string
    {
        return 'divi';
    }

    public function label(): string
    {
        return 'Divi';
    }

    public function toggleKey(): string
    {
        return 'maspik_support_divi_forms';
    }

    public function isAvailable(): bool
    {
        return defined('ET_BUILDER_VERSION') || defined('ET_CORE_VERSION') || function_exists('et_pb_is_pagebuilder_used');
    }

    public function register(SpamGate $gate): void
    {
        add_action('wp_loaded', function () use ($gate) {
            $this->earlyValidate($gate);
        }, 0);
        add_filter('pre_wp_mail', function ($shortCircuit, $atts = []) {
            return $this->preWpMail($shortCircuit);
        }, 5, 2);
        add_filter('gettext', function ($translated, $text = '', $domain = '') {
            return $this->gettext($translated, (string) $text, (string) $domain);
        }, 10, 3);
        add_action('shutdown', function () {
            $this->reset();
        }, 999);
    }

    /** Validate the Divi contact POST before the theme renders/sends mail. */
    private function earlyValidate(SpamGate $gate): void
    {
        if (empty($_POST) || ! $this->isContactRequest()) {
            return;
        }

        // Admin, admin-ajax and REST requests are skipped — unless the request
        // is provably a contact submission.
        //
        // The guard is there so a builder save or an API call that happens to
        // carry a Divi key is never treated as a visitor's form. But refusing
        // every one of those contexts outright is a standing bet that Divi will
        // keep posting to the page: the moment it moves the submission to
        // admin-ajax or a REST route, scanning stops with nothing to show for
        // it. Divi 5 already submits over XHR — to the page, so WordPress calls
        // it an ordinary request — and that is a thin margin to rely on.
        //
        // A valid per-form nonce is what makes the difference. Divi issues it
        // only for a rendered form and rejects the submission without it, so
        // nothing else can produce one; a stray admin or REST request carrying
        // the submit key still has no nonce and is still skipped.
        $contextual = is_admin() || wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST);
        if ($contextual && ! $this->anySubmittedFormIsGenuine()) {
            return;
        }

        foreach ($this->formIds() as $id) {
            if (! $this->nonceOk($id)) {
                continue;
            }
            // Divi's own honeypot already flagged it — let Divi handle it.
            if (! empty($_POST['et_pb_contact_et_number_' . $id])) {
                continue;
            }

            $rows = $this->parseRows($id);
            if ($rows === []) {
                // Divi 5 builds et_pb_contact_email_fields_<id> in JavaScript at
                // submit time, so a bot that posts the form directly never sends
                // it. Divi does not need it — it reads its own stored field
                // definition server side and mails the submission anyway — so
                // skipping here meant every scripted submission went unscanned
                // while real visitors, whose browsers run the script, were
                // checked normally. That is the shape of the report: manual
                // tests block, the counter barely moves, and the inbox fills up.
                $rows = $this->rowsFromPost($id);
            }
            if ($rows === []) {
                continue;
            }

            $raw = $this->buildFields($rows);
            $verdict = $gate->evaluate($this->submissionFrom($raw, self::typeMap()));
            if ($verdict->isSpam) {
                $this->blocked = true;
                $this->errorMessage = $gate->errorMessage($verdict);
                // Suppress a small burst of mails (admin + optional confirmation).
                $this->mailBlocksRemaining = 5;

                return;
            }
        }
    }

    /** Short-circuit wp_mail while handling a blocked Divi submission. */
    private function preWpMail($shortCircuit)
    {
        if ($shortCircuit !== null) {
            return $shortCircuit;
        }
        if (! $this->blocked || $this->mailBlocksRemaining < 1) {
            return $shortCircuit;
        }
        $this->mailBlocksRemaining--;

        return false;
    }

    /** Divi 5: replace the generic "mail failed" copy with the spam reason. */
    private function gettext($translated, string $text, string $domain)
    {
        if ($domain !== 'et_builder_5' || ! $this->blocked || $this->errorMessage === '') {
            return $translated;
        }
        if ($text !== self::DIVI5_MAIL_FAILURE) {
            return $translated;
        }

        return wp_strip_all_tags($this->errorMessage);
    }

    private function reset(): void
    {
        $this->blocked = false;
        $this->errorMessage = '';
        $this->mailBlocksRemaining = 0;
    }

    /** Whether this request is a Divi Contact Form POST. */
    /**
     * True when at least one submitted form id carries a valid Divi nonce.
     *
     * Only used to decide whether an admin, ajax or REST request is really a
     * visitor's submission. Each form's nonce is verified again in the loop, so
     * this decides context and nothing about whether a form gets scanned.
     */
    private function anySubmittedFormIsGenuine(): bool
    {
        foreach ($this->formIds() as $id) {
            if ($this->nonceOk($id)) {
                return true;
            }
        }

        return false;
    }

    private function isContactRequest(): bool
    {
        foreach (array_keys($_POST) as $key) {
            if (strpos((string) $key, 'et_pb_contactform_submit_') === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Submitted form ids (legacy numeric index or Divi 5 uuid).
     *
     * @return string[]
     */
    private function formIds(): array
    {
        $ids = [];
        foreach (array_keys($_POST) as $key) {
            if (preg_match('/^et_pb_contactform_submit_(.+)$/', (string) $key, $m) && $m[1] !== '') {
                $ids[] = $m[1];
            }
        }

        return array_values(array_unique($ids));
    }

    /** Verify the per-form nonce (accepts the Divi 5 and legacy actions). */
    private function nonceOk(string $id): bool
    {
        $key = '_wpnonce-et-pb-contact-form-submitted-' . $id;
        if (empty($_POST[$key])) {
            return false;
        }
        $nonce = wp_unslash($_POST[$key]);

        return (bool) wp_verify_nonce($nonce, 'et-pb-contact-form-submit-' . $id)
            || (bool) wp_verify_nonce($nonce, 'et-pb-contact-form-submit');
    }

    /**
     * Parse the et_pb_contact_email_fields_{id} JSON field-row array.
     *
     * @return array<int, array<string, mixed>>
     */
    private function parseRows(string $id): array
    {
        $key = 'et_pb_contact_email_fields_' . $id;
        if (empty($_POST[$key]) || ! is_string($_POST[$key])) {
            return [];
        }
        $raw = str_replace('\\', '', wp_unslash($_POST[$key]));
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Build name/type/value rows from Divi field rows + $_POST values.
     *
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array{name: string, type: string, value: string}>
     */
    private function buildFields(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            if (! is_array($row) || empty($row['field_id'])) {
                continue;
            }
            $fieldId = (string) $row['field_id'];
            if (strpos($fieldId, 'et_pb_contact_et_number_') === 0) {
                continue;
            }

            $value = isset($_POST[$fieldId]) ? $this->normalize(wp_unslash($_POST[$fieldId])) : '';
            if ($value === '') {
                continue;
            }

            $type = $this->classify($row, $value);
            if ($type === null) {
                continue;
            }

            $out[] = ['name' => $fieldId, 'type' => $type, 'value' => $value];
        }

        return $out;
    }

    /** Normalize a submitted value: arrays to string, trim, decode if encoded. */
    private function normalize($value): string
    {
        if (is_array($value)) {
            $value = FieldMapper::flatten($value);
        }
        if (! is_string($value)) {
            return '';
        }
        $value = trim($value);
        if ($value !== '' && preg_match('/%[0-9A-Fa-f]{2}/', $value)) {
            $decoded = rawurldecode($value);
            if (is_string($decoded)) {
                $value = trim($decoded);
            }
        }

        return $value;
    }

    /**
     * Pick a FieldType for a Divi row from its declared type + id hints
     * (Divi's "input"/"text" types cover message/url/phone by field id).
     *
     * @param array<string, mixed> $row
     */
    /**
     * Reconstruct the field rows from the request when the JavaScript-supplied
     * list is absent.
     *
     * Divi names every input et_pb_contact_<original id>_<form id>, so the
     * request carries enough to rebuild what the script would have sent. The
     * plumbing is excluded by name: the honeypot and the captcha are Divi's own
     * anti-spam fields, not anything a visitor typed, and the field list itself
     * is what we are standing in for.
     *
     * @return array<int, array<string, mixed>>
     */
    private function rowsFromPost(string $id): array
    {
        $suffix = '_' . $id;
        $skip = [
            'et_pb_contact_et_number' . $suffix,
            'et_pb_contact_captcha' . $suffix,
            'et_pb_contact_captcha_first_digit' . $suffix,
            'et_pb_contact_captcha_second_digit' . $suffix,
            'et_pb_contact_email_fields' . $suffix,
        ];

        $rows = [];
        foreach (array_keys((array) $_POST) as $key) {
            $key = (string) $key;
            if (strpos($key, 'et_pb_contact_') !== 0 || substr($key, -strlen($suffix)) !== $suffix) {
                continue;
            }
            if (in_array($key, $skip, true)) {
                continue;
            }

            // et_pb_contact_message_0 -> "message", which classify() reads to
            // pick the field type exactly as it would from the script's list.
            $original = substr($key, strlen('et_pb_contact_'), -strlen($suffix));
            $rows[] = [
                'field_id' => $key,
                'original_id' => $original,
                'field_type' => $original === 'email' ? 'email' : 'input',
            ];
        }

        return $rows;
    }

    private function classify(array $row, string $value): ?string
    {
        $ft = strtolower((string) ($row['field_type'] ?? 'input'));
        $fid = strtolower((string) ($row['field_id'] ?? ''));
        $oid = strtolower((string) ($row['original_id'] ?? $fid));

        if ($ft === 'email') {
            return 'email';
        }
        if ($ft === 'tel' || $ft === 'phone') {
            return 'tel';
        }
        if (in_array($ft, ['select', 'checkbox', 'radio'], true)) {
            return null;
        }
        if (strpos($oid, 'url') !== false || strpos($fid, 'url') !== false || strpos($oid, 'website') !== false) {
            return 'url';
        }
        if (strpos($fid, 'phone') !== false || strpos($oid, 'phone') !== false || strpos($fid, 'tel') !== false) {
            return 'tel';
        }
        if (strpos($oid, 'message') !== false || strpos($fid, 'message') !== false || strlen($value) > 160) {
            return 'textarea';
        }

        return 'text';
    }
}
