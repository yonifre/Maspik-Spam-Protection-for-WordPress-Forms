<?php

declare(strict_types=1);

namespace Maspik\Integrations\Forms;

use Maspik\Application\SpamGate;
use Maspik\Domain\Model\FieldType;
use Maspik\Integrations\AbstractFormIntegration;
use Maspik\Integrations\Support\FieldMapper;

/**
 * Bit Form adapter.
 * Filter: bitform_filter_form_validation ($validated, $form_id). Return a
 * WP_Error to reject; return $validated to allow.
 *
 * Bit Form's POST payload is typeless, but the plugin exposes field types via
 * its FormManager API — we read those (guarded) and check only typed fields,
 * exactly like v2. Guard fields (honeypot/key) arrive in $_POST via the shared
 * front-end guard script.
 */
final class BitForm extends AbstractFormIntegration
{
    /** Bit Form field type => FieldType. */
    public static function typeMap(): array
    {
        return [
            'text' => FieldType::TEXT,
            'name' => FieldType::TEXT,
            'email' => FieldType::EMAIL,
            'phone-number' => FieldType::TEL,
            'phone' => FieldType::TEL,
            'tel' => FieldType::TEL,
            'url' => FieldType::URL,
            'website' => FieldType::URL,
            'link' => FieldType::URL,
            'textarea' => FieldType::TEXTAREA,
        ];
    }

    public function id(): string
    {
        return 'bitform';
    }

    public function label(): string
    {
        return 'Bit Form';
    }

    public function toggleKey(): string
    {
        return 'maspik_support_bitform_forms';
    }

    public function isAvailable(): bool
    {
        return defined('BITFORMS_VERSION')
            || defined('BITFORM_VERSION')
            || class_exists('\BitCode\BitForm\Core\Form\FormManager');
    }

    public function register(SpamGate $gate): void
    {
        add_filter('bitform_filter_form_validation', function ($validated, $formId = 0) use ($gate) {
            if (! $validated) {
                return $validated;
            }
            if (apply_filters('maspik_disable_bitform_spam_check', false, $formId, $_POST)) {
                return $validated;
            }

            $raw = $this->extractFields((int) $formId);
            $verdict = $gate->evaluate($this->submissionFrom($raw, self::typeMap()));
            if ($verdict->isSpam) {
                return new \WP_Error('spam_detection', $gate->errorMessage($verdict));
            }

            return $validated;
        }, 10, 2);
    }

    /**
     * Build name/type/value rows from $_POST, typed via Bit Form's API. Only
     * fields the API reports a type for are content-checked (v2 parity);
     * internal/guard fields have no API type and are naturally excluded.
     *
     * @return array<int, array{name: string, type: string, value: string}>
     */
    private function extractFields(int $formId): array
    {
        $types = $this->fieldTypes($formId);
        if ($types === []) {
            return [];
        }

        $raw = [];
        foreach ($_POST as $key => $value) {
            $key = (string) $key;
            if (! isset($types[$key])) {
                continue;
            }
            $value = FieldMapper::flatten(wp_unslash($value));
            if (trim($value) === '') {
                continue;
            }
            $raw[] = ['name' => $key, 'type' => $types[$key], 'value' => $value];
        }

        return $raw;
    }

    /**
     * field_key => Bit Form field type, via FormManager. Returns [] if the API
     * is unavailable or errors (the engine still runs its submission-level
     * checks on $_POST, matching v2's fallback).
     *
     * @return array<string, string>
     */
    private function fieldTypes(int $formId): array
    {
        if ($formId <= 0 || ! class_exists('\BitCode\BitForm\Core\Form\FormManager')) {
            return [];
        }

        try {
            $manager = new \BitCode\BitForm\Core\Form\FormManager($formId);
            $fields = $manager->getFields();
            if (! is_array($fields)) {
                return [];
            }

            $out = [];
            foreach ($fields as $key => $def) {
                if (is_array($def) && isset($def['type'])) {
                    $out[(string) $key] = (string) $def['type'];
                }
            }

            return $out;
        } catch (\Throwable $e) {
            return [];
        }
    }
}
