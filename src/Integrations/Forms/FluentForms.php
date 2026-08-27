<?php

declare(strict_types=1);

namespace Maspik\Integrations\Forms;

use Maspik\Application\SpamGate;
use Maspik\Domain\Model\FieldType;
use Maspik\Integrations\AbstractFormIntegration;

/**
 * Fluent Forms adapter.
 * Hook: fluentform/validation_errors ($errors, $formData, $form, $fields).
 * Field element types come from the form definition; submitted values from
 * $formData (name => value). Reject: $errors['spam'] = msg (v2's key).
 *
 * v2 parity note: v2 split this across a whole-form hook (honeypot/IP/country)
 * plus per-field-type filters (content checks); the rebuild runs the unified
 * pipeline in the single whole-form hook — same verdict (approved deviation #1,
 * docs/10). Source id 'fluentforms' is NOT guard-exempt, so honeypot/advanced
 * key apply, matching v2.
 */
final class FluentForms extends AbstractFormIntegration
{
    /** Fluent element type => FieldType. */
    public static function typeMap(): array
    {
        return [
            'input_text' => FieldType::TEXT,
            'input_name' => FieldType::TEXT,
            'input_email' => FieldType::EMAIL,
            'input_url' => FieldType::URL,
            'phone' => FieldType::TEL,
            'textarea' => FieldType::TEXTAREA,
        ];
    }

    public function id(): string
    {
        return 'fluentforms';
    }

    public function label(): string
    {
        return 'Fluent Forms';
    }

    public function toggleKey(): string
    {
        return 'maspik_support_fluentforms_forms';
    }

    public function isAvailable(): bool
    {
        return defined('FLUENTFORM_VERSION') || function_exists('wpFluentForm');
    }

    public function register(SpamGate $gate): void
    {
        add_filter('fluentform/validation_errors', function ($errors, $formData, $form, $fields = null) use ($gate) {
            $formId = is_object($form) && isset($form->id) ? $form->id : 0;
            if (apply_filters('maspik_disable_fluentforms_spam_check', false, $formId)) {
                return $errors;
            }

            $defs = self::fieldDefinitions($form, $fields);
            $raw = [];
            foreach ($defs as $def) {
                $name = $def['name'];
                if ($name === '' || ! array_key_exists($name, (array) $formData)) {
                    continue;
                }
                $value = $formData[$name];
                $raw[] = ['name' => $name, 'type' => $def['element'], 'value' => is_array($value) ? $value : (string) $value];
            }

            $verdict = $gate->evaluate($this->submissionFrom($raw, self::typeMap()));
            if ($verdict->isSpam && is_array($errors)) {
                $errors['spam'] = $gate->errorMessage($verdict);
            }

            return $errors;
        }, 10, 4);
    }

    /**
     * Flatten a Fluent form's field definitions to [{ name, element }],
     * recursing through containers/columns. Reads the form's decoded fields
     * (from $fields when passed, else the $form object).
     *
     * @return array<int, array{name: string, element: string}>
     */
    private static function fieldDefinitions($form, $fields): array
    {
        // What Fluent actually passes: FormFieldsParser::getInputs(), a flat map
        // keyed by input name whose values carry `element` at the top level.
        // walk() reads names from $field['attributes']['name'], which this shape
        // does not have, so it found nothing and returned an empty list — no
        // fields to scan meant no content check could ever fire, and every
        // blacklisted word sailed through Fluent Forms.
        //
        // Checked before the tree shapes because it is the live one; the others
        // remain for callers that hand over the form definition instead.
        if (is_array($fields) && $fields !== [] && ! isset($fields['fields'])) {
            $flat = self::fromInputMap($fields);
            if ($flat !== []) {
                return $flat;
            }
        }

        $tree = [];
        if (is_array($fields) && isset($fields['fields'])) {
            $tree = $fields['fields'];
        } elseif (is_array($fields)) {
            $tree = $fields;
        } elseif (is_object($form) && isset($form->form_fields)) {
            $decoded = is_string($form->form_fields) ? json_decode($form->form_fields, true) : $form->form_fields;
            $tree = isset($decoded['fields']) ? $decoded['fields'] : [];
        }

        $out = [];
        self::walk($tree, $out);

        return $out;
    }

    /**
     * @param mixed $node
     * @param array<int, array{name: string, element: string}> $out
     */
    /**
     * Read Fluent's flat input map: { "email": { element: "input_email", … } }.
     *
     * A composite field appears both as itself and as its parts — `names`
     * alongside `names[first_name]` — so only the parent is taken: $formData
     * holds the composite as an array, and the bracketed keys are absent from
     * it, while FieldMapper flattens the array for scanning anyway.
     *
     * @param array<string, mixed> $fields
     * @return array<int, array{name: string, element: string}>
     */
    private static function fromInputMap(array $fields): array
    {
        $out = [];
        foreach ($fields as $name => $definition) {
            $name = (string) $name;
            if ($name === '' || ! is_array($definition) || strpos($name, '[') !== false) {
                continue;
            }
            $element = isset($definition['element']) ? (string) $definition['element'] : '';
            if ($element === '' && isset($definition['raw']['element'])) {
                $element = (string) $definition['raw']['element'];
            }
            if ($element !== '') {
                $out[] = ['name' => $name, 'element' => $element];
            }
        }

        return $out;
    }

    private static function walk($node, array &$out): void
    {
        if (! is_array($node)) {
            return;
        }
        foreach ($node as $field) {
            if (! is_array($field)) {
                continue;
            }
            $element = isset($field['element']) ? (string) $field['element'] : '';
            $name = isset($field['attributes']['name']) ? (string) $field['attributes']['name'] : '';
            if ($element !== '' && $name !== '') {
                $out[] = ['name' => $name, 'element' => $element];
            }
            // Containers nest child fields under 'fields' and/or 'columns'.
            if (isset($field['fields'])) {
                self::walk($field['fields'], $out);
            }
            if (isset($field['columns']) && is_array($field['columns'])) {
                foreach ($field['columns'] as $column) {
                    if (isset($column['fields'])) {
                        self::walk($column['fields'], $out);
                    }
                }
            }
        }
    }
}
