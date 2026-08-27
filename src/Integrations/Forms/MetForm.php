<?php

declare(strict_types=1);

namespace Maspik\Integrations\Forms;

use Maspik\Application\SpamGate;
use Maspik\Domain\Check\HoneypotCheck;
use Maspik\Domain\Check\VerificationKeyCheck;
use Maspik\Domain\Model\FieldType;
use Maspik\Integrations\AbstractFormIntegration;
use Maspik\Integrations\Support\FieldMapper;

/**
 * MetForm adapter (Wpmet's Elementor form widget).
 * Filter: mf_after_validation_check ($validationData) — with is_valid /
 * form_data / file_data. Return an is_valid=false array to reject.
 *
 * MetForm's payload carries no field types, so — like v2 — we infer each
 * field's type from its name and value. Guard fields (honeypot/key) arrive in
 * $_POST via the shared front-end guard script.
 */
final class MetForm extends AbstractFormIntegration
{
    /** Inferred type => FieldType. */
    public static function typeMap(): array
    {
        return [
            'text' => FieldType::TEXT,
            'email' => FieldType::EMAIL,
            'phone' => FieldType::TEL,
            'url' => FieldType::URL,
            'textarea' => FieldType::TEXTAREA,
        ];
    }

    public function id(): string
    {
        return 'metform';
    }

    public function label(): string
    {
        return 'MetForm';
    }

    public function toggleKey(): string
    {
        return 'maspik_support_metform_forms';
    }

    public function isAvailable(): bool
    {
        return defined('METFORM_VERSION') || class_exists('\MetForm\Plugin');
    }

    public function register(SpamGate $gate): void
    {
        add_filter('mf_after_validation_check', function ($validationData) use ($gate) {
            // Respect MetForm's own verdict; only run when it considers valid.
            if (! is_array($validationData) || empty($validationData['is_valid'])) {
                return $validationData;
            }

            $formData = isset($validationData['form_data']) && is_array($validationData['form_data'])
                ? $validationData['form_data'] : [];

            $formId = isset($formData['form_id']) ? (int) $formData['form_id'] : 0;
            if (apply_filters('maspik_disable_metform_spam_check', false, $formId, $formData)) {
                return $validationData;
            }

            $raw = $this->classifyFields($formData);
            $verdict = $gate->evaluate($this->submissionFrom($raw, self::typeMap()));
            if ($verdict->isSpam) {
                return $this->fail($validationData, $gate->errorMessage($verdict));
            }

            return $validationData;
        }, 10, 1);
    }

    /**
     * Turn MetForm's typeless field map into name/type/value rows, inferring the
     * type and dropping internal + guard fields.
     *
     * @param array<string, mixed> $formData
     * @return array<int, array{name: string, type: string, value: string}>
     */
    private function classifyFields(array $formData): array
    {
        $skip = [
            'form_id' => true, 'form_settings' => true, 'action' => true, 'id' => true, 'form_nonce' => true,
            HoneypotCheck::FIELD_NAME => true, VerificationKeyCheck::FIELD_NAME => true,
        ];

        $raw = [];
        foreach ($formData as $name => $value) {
            $name = (string) $name;
            if ($name === '' || $name[0] === '_' || isset($skip[$name])) {
                continue;
            }

            // A value that arrives as an array in a typeless payload is a
            // multi-choice field (checkbox group, radio, multi-select). Those
            // are options the site owner defined, not visitor-written text — a
            // spammer cannot put anything in them, and scanning them only risks
            // blocking every legitimate submission when an option label happens
            // to contain a blacklisted word.
            if (is_array($value)) {
                continue;
            }
            $value = FieldMapper::flatten($value);
            if (trim($value) === '') {
                continue;
            }

            $raw[] = ['name' => $name, 'type' => $this->classify($name, $value), 'value' => $value];
        }

        return $raw;
    }

    /** Infer a field type from its name/value (v2's is_*_field heuristics). */
    private function classify(string $name, string $value): string
    {
        $lower = strtolower($name);
        if (strpos($lower, 'email') !== false || filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return 'email';
        }
        if (strpos($lower, 'phone') !== false || strpos($lower, 'tel') !== false || strpos($lower, 'mobile') !== false) {
            return 'phone';
        }
        if (strpos($lower, 'url') !== false || strpos($lower, 'website') !== false || strpos($lower, 'link') !== false
            || filter_var($value, FILTER_VALIDATE_URL)) {
            return 'url';
        }
        if (strlen($value) > 100 || strpos($lower, 'message') !== false || strpos($lower, 'comment') !== false
            || strpos($lower, 'description') !== false) {
            return 'textarea';
        }

        return 'text';
    }

    /**
     * Build MetForm's rejection payload. Mirrors v2's belt-and-suspenders shape
     * (message + several error-array keys MetForm versions have expected).
     *
     * @param array<string, mixed> $validationData
     * @return array<string, mixed>
     */
    private function fail(array $validationData, string $message): array
    {
        return [
            'is_valid' => false,
            'message' => $message,
            'form_data' => $validationData['form_data'] ?? [],
            'file_data' => $validationData['file_data'] ?? [],
            'error' => $message,
            'errors' => [$message],
            'validation_errors' => ['spam' => $message],
            'field_errors' => ['general' => $message],
        ];
    }
}
