<?php

declare(strict_types=1);

namespace Maspik\Integrations\Forms;

use Maspik\Application\SpamGate;
use Maspik\Domain\Model\FieldType;
use Maspik\Integrations\AbstractFormIntegration;

/**
 * Everest Forms adapter.
 * Hook: everest_forms_process_initial_errors ($errors, $form_data). Field types
 * live in $form_data['form_fields']; values in $form_data['entry']['form_fields'].
 * Reject: $errors[form_id][field_id] = msg.
 */
final class EverestForms extends AbstractFormIntegration
{
    /** Everest field type => FieldType. */
    public static function typeMap(): array
    {
        return [
            'first-name' => FieldType::TEXT,
            'last-name' => FieldType::TEXT,
            'text' => FieldType::TEXT,
            'email' => FieldType::EMAIL,
            'tel' => FieldType::TEL,
            'textarea' => FieldType::TEXTAREA,
        ];
    }

    public function id(): string
    {
        return 'everestforms';
    }

    public function label(): string
    {
        return 'Everest Forms';
    }

    public function toggleKey(): string
    {
        return 'maspik_support_everestforms';
    }

    public function isAvailable(): bool
    {
        return defined('EVF_VERSION');
    }

    public function register(SpamGate $gate): void
    {
        add_filter('everest_forms_process_initial_errors', function ($errors, $formData) use ($gate) {
            $formId = isset($formData['id']) ? $formData['id'] : 0;
            if (apply_filters('maspik_disable_everestforms_spam_check', false, $formId)) {
                return $errors;
            }

            $fields = isset($formData['form_fields']) && is_array($formData['form_fields']) ? $formData['form_fields'] : [];
            $entry = isset($formData['entry']['form_fields']) && is_array($formData['entry']['form_fields'])
                ? $formData['entry']['form_fields'] : [];

            $raw = [];
            foreach ($fields as $fieldId => $field) {
                $value = isset($entry[$fieldId]) ? $entry[$fieldId] : '';
                $raw[] = [
                    'name' => (string) $fieldId,
                    'type' => (string) ($field['type'] ?? ''),
                    'value' => is_array($value) ? $value : (string) $value,
                ];
            }

            $verdict = $gate->evaluate($this->submissionFrom($raw, self::typeMap()));
            if (! $verdict->isSpam) {
                return $errors;
            }

            $fieldName = $verdict->violation !== null ? $verdict->violation->fieldName : null;
            $target = $fieldName !== null ? $fieldName : (isset($raw[0]) ? $raw[0]['name'] : 0);
            if (! is_array($errors)) {
                $errors = [];
            }
            $errors[$formId][$target] = $gate->errorMessage($verdict);

            return $errors;
        }, 10, 2);
    }
}
