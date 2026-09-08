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
 * Bricks builder forms adapter.
 * Hook: bricks/form/validate ($errors, $form). Field types from
 * $form->get_settings()['fields']; values from $form->get_field_value(id).
 * Reject: $errors[] = msg (a flat list of error strings).
 */
final class Bricks extends AbstractFormIntegration
{
    /** Bricks field type => FieldType. */
    /**
     * Bricks field type => FieldType.
     *
     * The keys are the slugs Bricks registers in its form element
     * (includes/elements/form.php), not the labels its builder shows.
     *
     * `url` was missing here, so a URL field on a Bricks form was dropped by
     * FieldMapper and never reached a single layer — no URL blocklist, no link
     * limit, nothing — with no warning and nothing in the log to show for it.
     * Verified present in Bricks 1.9.5 and 1.3.6.
     *
     * Deliberately absent: `number`, `checkbox`, `select`, `radio`, `file`,
     * `datepicker`, `hidden`, and `password`, which hold figures, choices,
     * files or secrets rather than free text.
     */
    public static function typeMap(): array
    {
        return [
            'text' => FieldType::TEXT,
            'email' => FieldType::EMAIL,
            'tel' => FieldType::TEL,
            'url' => FieldType::URL,
            'textarea' => FieldType::TEXTAREA,
        ];
    }

    public function id(): string
    {
        return 'bricks';
    }

    public function label(): string
    {
        return 'Bricks';
    }

    public function toggleKey(): string
    {
        return 'maspik_support_bricks_forms';
    }

    public function isAvailable(): bool
    {
        return defined('BRICKS_VERSION');
    }

    public function register(SpamGate $gate): void
    {
        add_filter('bricks/form/validate', function ($errors, $form) use ($gate) {
            if (apply_filters('maspik_disable_bricks_spam_check', false, $form)) {
                return $errors;
            }

            $settings = method_exists($form, 'get_settings') ? $form->get_settings() : [];
            $fields = isset($settings['fields']) && is_array($settings['fields']) ? $settings['fields'] : [];

            $raw = [];
            foreach ($fields as $field) {
                if (! isset($field['id'])) {
                    continue;
                }
                $value = method_exists($form, 'get_field_value') ? $form->get_field_value($field['id']) : '';
                $raw[] = [
                    'name' => (string) $field['id'],
                    'type' => (string) ($field['type'] ?? ''),
                    'value' => is_array($value) ? $value : (string) $value,
                ];
            }

            $verdict = $gate->evaluate($this->submissionFrom($raw, self::typeMap()));
            if ($verdict->isSpam) {
                if (! is_array($errors)) {
                    $errors = [];
                }
                $errors[] = $gate->errorMessage($verdict);
            }

            return $errors;
        }, 10, 2);
    }
}
