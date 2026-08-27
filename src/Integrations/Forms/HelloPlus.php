<?php

declare(strict_types=1);

namespace Maspik\Integrations\Forms;

use Maspik\Application\SpamGate;
use Maspik\Domain\Model\FieldType;
use Maspik\Integrations\AbstractFormIntegration;
use Maspik\Integrations\Support\FieldMapper;

/**
 * Hello+ (HelloPlus) forms adapter. Same Elementor-style record/ajax_handler
 * mechanism as the Elementor adapter, on its own hook.
 * Hook: hello_plus/forms/validation ($record, $ajax_handler).
 * Reject: $ajax_handler->add_error(field_id, msg) / add_error_message(msg).
 */
final class HelloPlus extends AbstractFormIntegration
{
    /** Hello+ field type => FieldType. */
    public static function typeMap(): array
    {
        return [
            'text' => FieldType::TEXT,
            'email' => FieldType::EMAIL,
            'url' => FieldType::URL,
            'tel' => FieldType::TEL,
            'textarea' => FieldType::TEXTAREA,
        ];
    }

    public function id(): string
    {
        return 'helloplus';
    }

    public function label(): string
    {
        return 'Hello Plus';
    }

    public function toggleKey(): string
    {
        return 'maspik_support_helloplus_forms';
    }

    public function isAvailable(): bool
    {
        return defined('HELLOPLUS_VERSION') || defined('HELLO_PLUS_VERSION');
    }

    public function register(SpamGate $gate): void
    {
        add_action('hello_plus/forms/validation', function ($record, $ajaxHandler) use ($gate) {
            if (apply_filters('maspik_disable_helloplus_spam_check', false, $record)) {
                return;
            }

            $raw = [];
            $lastFieldId = '';
            foreach ((array) $record->get('fields') as $fieldId => $field) {
                $value = FieldMapper::flatten($field['value'] ?? '');
                $lastFieldId = (string) ($field['id'] ?? $fieldId);
                $raw[] = ['name' => (string) $fieldId, 'type' => (string) ($field['type'] ?? ''), 'value' => $value];
            }

            $verdict = $gate->evaluate($this->submissionFrom($raw, self::typeMap()));
            if (! $verdict->isSpam) {
                return;
            }

            $message = $gate->errorMessage($verdict);

            // Hello+ (like Elementor) aborts only when $ajax_handler->errors is
            // non-empty, not on is_success — so add_error_message() alone would
            // still let the email through. Attach the error to a real field.
            $fieldName = $verdict->violation !== null ? $verdict->violation->fieldName : null;
            $target = $fieldName !== null && $fieldName !== '' ? $fieldName : $lastFieldId;
            if ($target === '') {
                $target = 'maspik';
            }
            $ajaxHandler->add_error($target, $message);
        }, 10, 2);
    }
}
