<?php

declare(strict_types=1);

namespace Maspik\Integrations\Forms;

use Maspik\Application\SpamGate;
use Maspik\Domain\Model\FieldType;
use Maspik\Integrations\AbstractFormIntegration;

if (! defined('ABSPATH')) {
    exit;
}


/**
 * Everest Forms adapter.
 * Hook: everest_forms_process_initial_errors ($errors, $form_data). Field types
 * live in $form_data['form_fields']; values in $form_data['entry']['form_fields'].
 * Reject: $errors[form_id][field_id] = msg.
 */
final class EverestForms extends AbstractFormIntegration
{
    /**
     * Everest field type => FieldType.
     *
     * The keys are the strings Everest itself registers ($this->type in each
     * includes/fields/class-evf-field-*.php), not what the type is called in
     * the form builder's interface. FieldMapper drops a field whose type is not
     * a key here without a word, so a name that does not match exactly means
     * that field is never scanned by any layer — no phone format check, no
     * blocklist, nothing.
     *
     * `phone` is the one to be careful about: every other integration in this
     * plugin calls that field `tel`, and Everest does not.
     */
    public static function typeMap(): array
    {
        return [
            'first-name' => FieldType::TEXT,
            'last-name' => FieldType::TEXT,
            'text' => FieldType::TEXT,
            'title' => FieldType::TEXT,
            // Everest's own string. Not 'tel'.
            'phone' => FieldType::TEL,
            'email' => FieldType::EMAIL,
            'url' => FieldType::URL,
            'textarea' => FieldType::TEXTAREA,
            // Rich text, and the field a spammer reaches for first: it accepts
            // the most content and renders links.
            'wysiwyg' => FieldType::TEXTAREA,
            // Composite (street, city, …). FieldMapper flattens the array.
            'address' => FieldType::TEXT,
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
