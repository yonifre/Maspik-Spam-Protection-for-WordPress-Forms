<?php

declare(strict_types=1);

namespace Maspik\Integrations\Forms;

use Maspik\Application\SpamGate;
use Maspik\Domain\Model\FieldType;
use Maspik\Integrations\AbstractFormIntegration;

/**
 * Forminator adapter.
 * Hook: forminator_custom_form_submit_errors ($errors, $form_id, $field_data).
 * Each entry: ['name' => field_id, 'value' => …, 'field_type' => …].
 * Reject: $errors[][field_id] = msg.
 */
final class Forminator extends AbstractFormIntegration
{
    /** Forminator field_type => FieldType. */
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
        return 'forminator';
    }

    public function label(): string
    {
        return 'Forminator';
    }

    public function toggleKey(): string
    {
        return 'maspik_support_forminator_forms';
    }

    public function isAvailable(): bool
    {
        return class_exists('Forminator');
    }

    public function register(SpamGate $gate): void
    {
        add_filter('forminator_custom_form_submit_errors', function ($errors, $formId, $fieldData) use ($gate) {
            if (apply_filters('maspik_disable_forminator_spam_check', false, $formId)) {
                return $errors;
            }

            $raw = [];
            foreach ((array) $fieldData as $current) {
                if (! isset($current['name'])) {
                    continue;
                }
                $value = isset($current['value']) ? $current['value'] : '';
                $raw[] = [
                    'name' => (string) $current['name'],
                    'type' => (string) ($current['field_type'] ?? ''),
                    'value' => is_array($value) ? $value : (string) $value,
                ];
            }

            $verdict = $gate->evaluate($this->submissionFrom($raw, self::typeMap()));
            if (! $verdict->isSpam) {
                return $errors;
            }

            $fieldName = $verdict->violation !== null ? $verdict->violation->fieldName : null;
            $target = $fieldName !== null ? $fieldName : (isset($raw[0]) ? $raw[0]['name'] : 'form');
            $errors[][$target] = $gate->errorMessage($verdict);

            return $errors;
        }, 30, 3);
    }
}
