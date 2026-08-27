<?php

declare(strict_types=1);

namespace Maspik\Integrations\Forms;

use Maspik\Application\SpamGate;
use Maspik\Domain\Model\FieldType;
use Maspik\Integrations\AbstractFormIntegration;
use Maspik\Integrations\Support\FieldMapper;

/**
 * JetFormBuilder adapter.
 * Hook: jet-form-builder/form-handler/before-send ($form_handler).
 * Fields: $form_handler->request_handler->_fields — Gutenberg block defs with
 * ['blockName'] and ['attrs']['name'|'field_type']; values live in $_POST.
 * Reject: throw \Jet_Form_Builder\Exceptions\Request_Exception($msg, [$field => $msg]).
 *
 * Guard fields (honeypot/key) are standard name-based inputs, so the shared
 * front-end guard script injects them and they arrive in $_POST as usual.
 */
final class JetFormBuilder extends AbstractFormIntegration
{
    /** JetFormBuilder field mapping keyed by block name / field_type. */
    public static function typeMap(): array
    {
        return [
            'text' => FieldType::TEXT,
            'email' => FieldType::EMAIL,
            'tel' => FieldType::TEL,
            'textarea' => FieldType::TEXTAREA,
        ];
    }

    public function id(): string
    {
        return 'jetform';
    }

    public function label(): string
    {
        return 'JetFormBuilder';
    }

    public function toggleKey(): string
    {
        return 'maspik_support_jetforms';
    }

    public function isAvailable(): bool
    {
        return defined('JET_FORM_BUILDER_VERSION') || class_exists('\Jet_Form_Builder\Plugin');
    }

    public function register(SpamGate $gate): void
    {
        add_action('jet-form-builder/form-handler/before-send', function ($formHandler) use ($gate) {
            $raw = $this->extractFields($formHandler);
            if ($raw === []) {
                return;
            }

            $verdict = $gate->evaluate($this->submissionFrom($raw, self::typeMap()));
            if (! $verdict->isSpam) {
                return;
            }

            $message = $gate->errorMessage($verdict);
            $fieldName = $verdict->violation !== null ? $verdict->violation->fieldName : null;
            $key = $fieldName !== null && $fieldName !== '' ? $fieldName : 'ip_check';

            if (class_exists('\Jet_Form_Builder\Exceptions\Request_Exception')) {
                throw new \Jet_Form_Builder\Exceptions\Request_Exception($message, [$key => $message]);
            }
        }, 10, 1);
    }

    /**
     * Flatten JetFormBuilder's field block defs into name/type/value rows,
     * reading submitted values from $_POST.
     *
     * @return array<int, array{name: string, type: string, value: string}>
     */
    private function extractFields($formHandler): array
    {
        $raw = [];
        if (! isset($formHandler->request_handler->_fields) || ! is_array($formHandler->request_handler->_fields)) {
            return $raw;
        }

        $post = $_POST;
        foreach ($formHandler->request_handler->_fields as $field) {
            if (! is_array($field) || empty($field['attrs']['name'])) {
                continue;
            }

            $blockName = isset($field['blockName']) ? (string) $field['blockName'] : '';
            $fieldType = isset($field['attrs']['field_type']) ? (string) $field['attrs']['field_type'] : '';
            $type = $this->fieldType($blockName, $fieldType);
            if ($type === null) {
                continue;
            }

            $name = (string) $field['attrs']['name'];
            $value = isset($post[$name]) ? FieldMapper::flatten(wp_unslash($post[$name])) : '';
            $raw[] = ['name' => $name, 'type' => $type, 'value' => $value];
        }

        return $raw;
    }

    /** Map a JetFormBuilder block to a check type, or null to skip it. */
    private function fieldType(string $blockName, string $fieldType): ?string
    {
        if ($blockName === 'jet-forms/textarea-field' || $blockName === 'jet-forms/wysiwyg-field') {
            return 'textarea';
        }
        if ($blockName === 'jet-forms/text-field') {
            if ($fieldType === 'email') {
                return 'email';
            }
            if ($fieldType === 'tel') {
                return 'tel';
            }

            return 'text';
        }

        return null;
    }
}
