<?php

declare(strict_types=1);

namespace Maspik\Integrations\Forms;

use Maspik\Application\SpamGate;
use Maspik\Domain\Model\FieldType;
use Maspik\Integrations\AbstractFormIntegration;
use Maspik\Integrations\Support\FieldMapper;

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
        if (is_admin() || wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST)) {
            return;
        }
        if (empty($_POST) || ! $this->isContactRequest()) {
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
