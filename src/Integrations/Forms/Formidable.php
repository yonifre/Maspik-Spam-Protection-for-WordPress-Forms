<?php

declare(strict_types=1);

namespace Maspik\Integrations\Forms;

use Maspik\Application\SpamGate;
use Maspik\Domain\Model\FieldType;
use Maspik\Integrations\AbstractFormIntegration;
use Maspik\Integrations\Support\FieldMapper;

/**
 * Formidable Forms adapter.
 *
 * Formidable validates per-field (frm_validate_field_entry) and once per entry
 * (frm_validate_entry). We use the per-field pass only to collect each field's
 * id/type/value into a request buffer, then run the whole engine once in the
 * entry pass — matching the rebuild's single-evaluate model. Blocking = adding
 * any key to the returned $errors array.
 *
 * Guard fields (honeypot/key) are standard name-based inputs in $_POST, so the
 * shared front-end guard script covers them.
 */
final class Formidable extends AbstractFormIntegration
{
    /** Fields collected during per-field validation, for the entry-pass evaluate. */
    private $collected = [];

    /** Formidable field type => FieldType. */
    public static function typeMap(): array
    {
        return [
            'text' => FieldType::TEXT,
            'email' => FieldType::EMAIL,
            'phone' => FieldType::TEL,
            'textarea' => FieldType::TEXTAREA,
            'url' => FieldType::URL,
        ];
    }

    public function id(): string
    {
        return 'formidable';
    }

    public function label(): string
    {
        return 'Formidable';
    }

    public function toggleKey(): string
    {
        return 'maspik_support_formidable_forms';
    }

    public function isAvailable(): bool
    {
        return class_exists('FrmForm') || class_exists('FrmAppHelper') || defined('FRM_VERSION');
    }

    public function register(SpamGate $gate): void
    {
        // Per-field pass: collect id/type/value (types aren't in item_meta).
        add_filter('frm_validate_field_entry', function ($errors, $postedField = null, $postedValue = '', $args = []) {
            $this->collect($postedField, $postedValue);

            return $errors;
        }, 10, 4);

        // Entry pass (runs after all fields): evaluate once, then reset.
        add_filter('frm_validate_entry', function ($errors, $values = []) use ($gate) {
            $raw = $this->collected;
            $this->collected = [];

            if (! is_array($errors)) {
                $errors = [];
            }

            $verdict = $gate->evaluate($this->submissionFrom($raw, self::typeMap()));
            if ($verdict->isSpam) {
                $message = $gate->errorMessage($verdict);
                $fieldName = $verdict->violation !== null ? $verdict->violation->fieldName : null;
                // A field violation carries "field{id}" (Formidable's error key);
                // submission-level violations go under the generic 'spam' key.
                $key = $fieldName !== null && $fieldName !== '' ? $fieldName : 'spam';
                $errors[$key] = $message;
            }

            return $errors;
        }, 20, 2);
    }

    /** Buffer one posted field as name/type/value (name = Formidable error key). */
    private function collect($postedField, $postedValue): void
    {
        if (! is_object($postedField) || ! isset($postedField->id, $postedField->type)) {
            return;
        }

        $value = FieldMapper::flatten($postedValue);
        $this->collected[] = [
            'name' => 'field' . $postedField->id,
            'type' => (string) $postedField->type,
            'value' => $value,
        ];
    }
}
