<?php

declare(strict_types=1);

namespace Maspik\Integrations\Forms;

use Maspik\Application\SpamGate;
use Maspik\Domain\Check\HoneypotCheck;
use Maspik\Domain\Check\VerificationKeyCheck;
use Maspik\Domain\Model\FieldType;
use Maspik\Infrastructure\Matrix\DirectPostSignal;
use Maspik\Integrations\AbstractFormIntegration;
use Maspik\Integrations\Support\FieldMapper;

/**
 * Elementor Pro Atomic Forms adapter — the Elementor 4+ "e-form" engine that
 * runs alongside the classic Elementor Forms handler.
 *
 * Filter: elementor_pro/atomic_forms/spam_check ($isSpam, $formFields,
 * $widgetSettings, $postId). Returning true marks the submission as spam and
 * Elementor rejects it; returning the incoming $isSpam preserves other filters.
 *
 * Unlike classic Elementor, fields arrive as a flat list of ['id','type','value']
 * and the guard fields (honeypot/key) travel as pseudo-fields INSIDE that list
 * (data-interaction-id), not in $_POST — so we read them from $formFields by
 * their Atomic ids, which the guard script injects into e-form markup.
 */
final class ElementorAtomic extends AbstractFormIntegration
{
    /** Atomic interaction ids for the injected guard pseudo-fields (JS parity). */
    public const HP_ID = 'maspik_atomic_hp';
    public const KEY_ID = 'maspik_atomic_sk';

    /** Atomic field type => FieldType (same set as classic Elementor). */
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
        return 'elementor_atomic';
    }

    public function label(): string
    {
        return 'Elementor Atomic Forms';
    }

    public function toggleKey(): string
    {
        // Follows the classic Elementor toggle, exactly as v2 does.
        return 'maspik_support_Elementor_forms';
    }

    public function isAvailable(): bool
    {
        // The AtomicForm module (Elementor 4+) is what fires
        // elementor_pro/atomic_forms/spam_check — absent on older Pro versions.
        return class_exists('\ElementorPro\Modules\AtomicForm\Module');
    }

    public function register(SpamGate $gate): void
    {
        add_filter('elementor_pro/atomic_forms/spam_check', function ($isSpam, $formFields = [], $widgetSettings = [], $postId = 0) use ($gate) {
            // Respect an upstream spam decision (e.g. Elementor's own checks).
            if ($isSpam) {
                return true;
            }
            if (apply_filters('maspik_disable_elementor_atomic_forms_spam_check', false, $formFields, $widgetSettings, $postId)) {
                return false;
            }

            $formFields = is_array($formFields) ? $formFields : [];

            $raw = [];
            $honeypot = '';
            $key = '';
            foreach ($formFields as $field) {
                if (! is_array($field) || ! isset($field['id'])) {
                    continue;
                }
                $fieldId = (string) $field['id'];
                $value = $this->flatten($field['value'] ?? '');

                if ($fieldId === self::HP_ID) {
                    $honeypot = $value;
                    continue;
                }
                if ($fieldId === self::KEY_ID) {
                    $key = $value;
                    continue;
                }
                $raw[] = ['name' => $fieldId, 'type' => (string) ($field['type'] ?? 'text'), 'value' => $value];
            }

            $hidden = [
                HoneypotCheck::FIELD_NAME => $honeypot,
                VerificationKeyCheck::FIELD_NAME => $key,
            ];

            // Direct-POST evidence (weaker than classic Elementor: Atomic posts
            // no `referrer`, but a post id still identifies the source page).
            $hasContext = ! empty($_SERVER['HTTP_REFERER']) || (int) $postId > 0;
            add_filter('maspik/direct_post_score', static function ($score) use ($hasContext) {
                return $hasContext ? $score : max((int) $score, DirectPostSignal::ELEMENTOR_ATOMIC);
            }, 10, 1);

            $verdict = $gate->evaluate($this->submissionWithHidden(FieldMapper::map($raw, self::typeMap()), $hidden));

            return $verdict->isSpam ? true : $isSpam;
        }, 10, 4);
    }

    /** Collapse array values to a single string, mirroring v2 normalization. */
    private function flatten($value): string
    {
        return FieldMapper::flatten($value);
    }
}
