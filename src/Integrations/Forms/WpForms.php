<?php

declare(strict_types=1);

namespace Maspik\Integrations\Forms;

use Maspik\Application\SpamGate;
use Maspik\Domain\Model\FieldType;
use Maspik\Integrations\AbstractFormIntegration;
use Maspik\Integrations\Support\FieldMapper;

/**
 * WPForms adapter.
 * Hook: wpforms_process ($fields, $entry, $form_data). Field value lives in
 * $fields[id]['value'], type in $fields[id]['type'].
 * Reject: wpforms()->process->errors[form_id][field_id | 'header'] = msg.
 */
final class WpForms extends AbstractFormIntegration
{
    /** WPForms field type => FieldType. */
    public static function typeMap(): array
    {
        return [
            'name' => FieldType::TEXT,
            'text' => FieldType::TEXT,
            'email' => FieldType::EMAIL,
            'phone' => FieldType::TEL,
            'url' => FieldType::URL,
            'textarea' => FieldType::TEXTAREA,
        ];
    }

    public function id(): string
    {
        return 'wpforms';
    }

    public function label(): string
    {
        return 'WPForms';
    }

    public function toggleKey(): string
    {
        return 'maspik_support_Wpforms';
    }

    public function pro(): bool
    {
        return true;
    }

    public function isAvailable(): bool
    {
        return function_exists('wpforms');
    }

    public function register(SpamGate $gate): void
    {
        add_action('wpforms_process', function ($fields, $entry, $formData) use ($gate) {
            $formId = isset($formData['id']) ? $formData['id'] : 0;
            if (apply_filters('maspik_disable_wpforms_spam_check', false, $formId)) {
                return;
            }

            $raw = [];
            foreach ((array) $fields as $fieldId => $field) {
                $raw[] = [
                    'name' => (string) $fieldId,
                    'type' => (string) ($field['type'] ?? ''),
                    'value' => FieldMapper::flatten($field['value'] ?? ''),
                ];
            }

            $verdict = $gate->evaluate($this->submissionFrom($raw, self::typeMap()));
            if (! $verdict->isSpam) {
                return;
            }

            $message = $gate->errorMessage($verdict);
            $fieldName = $verdict->violation !== null ? $verdict->violation->fieldName : null;
            $target = ($fieldName !== null && isset($fields[$fieldName])) ? $fieldName : 'header';
            wpforms()->process->errors[$formId][$target] = $message;
        }, 10, 3);
    }
}
