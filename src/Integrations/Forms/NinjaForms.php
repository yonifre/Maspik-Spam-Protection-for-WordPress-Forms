<?php

declare(strict_types=1);

namespace Maspik\Integrations\Forms;

use Maspik\Application\SpamGate;
use Maspik\Domain\Model\FieldType;
use Maspik\Integrations\AbstractFormIntegration;

/**
 * Ninja Forms adapter.
 * Hook: ninja_forms_submit_data ($form_data). Fields: $form_data['fields'],
 * each with 'id', 'key', 'value'. Reject: $form_data['errors']['fields'][id].
 *
 * v2 parity notes (Rule 4):
 *  - Source id is 'ninjaforms' so GuardPolicy skips honeypot/advanced-key,
 *    exactly as v2 did (Ninja's AJAX request can't carry those fields).
 *  - Fields are classified by their KEY (name) using v2's substring heuristics,
 *    not a field-type map. Single-type priority is textarea > email > tel >
 *    text (approved deviation #3, docs/10): v2 could run several field-type
 *    checks on one field, but a Submission's Field has one type. Because 'text'
 *    is a substring of 'textarea'/'textbox', textarea must be tested first or
 *    no Ninja multiline field would ever get textarea-specific checks. The
 *    shared checks (text blacklist / emoji / link limit apply to both) keep
 *    those verdicts identical; only char-limit field + language can differ.
 */
final class NinjaForms extends AbstractFormIntegration
{
    /** Key-substring heuristics; textarea first (see class doc). */
    private const KEY_HEURISTICS = [
        FieldType::TEXTAREA => ['textarea', 'textbox', 'message'],
        FieldType::EMAIL => ['email', 'contact'],
        FieldType::TEL => ['tel', 'phone'],
        FieldType::TEXT => ['name', 'text', 'single-line'],
    ];

    public function id(): string
    {
        return 'ninjaforms';
    }

    public function label(): string
    {
        return 'Ninjaforms';
    }

    public function toggleKey(): string
    {
        return 'maspik_support_ninjaforms';
    }

    public function isAvailable(): bool
    {
        return class_exists('Ninja_Forms');
    }

    /**
     * Classify a Ninja field key into a FieldType (or null to skip) using v2's
     * substring heuristics. Public + static so it is unit-tested directly.
     */
    public static function classifyKey(string $key): ?string
    {
        $key = strtolower($key);
        foreach (self::KEY_HEURISTICS as $fieldType => $needles) {
            foreach ($needles as $needle) {
                if (strpos($key, $needle) !== false) {
                    return $fieldType;
                }
            }
        }

        return null;
    }

    public function register(SpamGate $gate): void
    {
        add_filter('ninja_forms_submit_data', function ($formData) use ($gate) {
            $formId = isset($formData['id']) ? $formData['id'] : 0;
            if (apply_filters('maspik_disable_ninjaforms_spam_check', false, $formId)) {
                return $formData;
            }

            $fields = isset($formData['fields']) && is_array($formData['fields']) ? $formData['fields'] : [];
            $raw = [];
            $firstKey = null;
            foreach ($fields as $field) {
                if (! is_array($field)) {
                    continue;
                }
                $key = isset($field['key']) && $field['key'] !== '' ? (string) $field['key']
                    : (isset($field['id']) ? (string) $field['id'] : '');
                if ($key === '' || in_array($key, ['submit', 'maspik_spam_key'], true)) {
                    continue;
                }
                if ($firstKey === null) {
                    $firstKey = isset($field['id']) ? (string) $field['id'] : $key;
                }
                $type = self::classifyKey($key);
                if ($type === null) {
                    continue;
                }
                $value = isset($field['value']) ? $field['value'] : '';
                // Field name = the Ninja field id, so we can attach errors back.
                $raw[] = ['name' => (string) ($field['id'] ?? $key), 'type' => $type, 'value' => is_array($value) ? $value : (string) $value];
            }

            $verdict = $gate->evaluate($this->submissionFrom($raw, self::identityTypeMap()));
            if (! $verdict->isSpam) {
                return $formData;
            }

            $fieldName = $verdict->violation !== null ? $verdict->violation->fieldName : null;
            $target = $fieldName !== null ? $fieldName : ($firstKey !== null ? $firstKey : 0);
            $formData['errors']['fields'][$target] = $gate->errorMessage($verdict);

            return $formData;
        });
    }

    /**
     * classifyKey() already yields FieldType constants, so the map is identity.
     *
     * @return array<string, string>
     */
    private static function identityTypeMap(): array
    {
        return [
            FieldType::TEXT => FieldType::TEXT,
            FieldType::EMAIL => FieldType::EMAIL,
            FieldType::TEL => FieldType::TEL,
            FieldType::TEXTAREA => FieldType::TEXTAREA,
        ];
    }
}
