<?php

declare(strict_types=1);

namespace Maspik\Integrations\Forms;

use Maspik\Application\SpamGate;
use Maspik\Domain\Model\FieldType;
use Maspik\Infrastructure\Matrix\DirectPostSignal;
use Maspik\Integrations\AbstractFormIntegration;
use Maspik\Integrations\Support\FieldMapper;

/**
 * Elementor Pro Forms adapter.
 * Hook: elementor_pro/forms/validation ($record, $ajax_handler).
 * Fields: $record->get('fields') → [id => ['id','value','type']].
 * Reject: $ajax_handler->add_error(field_id, msg) / add_error_message(msg).
 */
final class Elementor extends AbstractFormIntegration
{
    /** Elementor field type => FieldType. */
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
        return 'elementor';
    }

    public function label(): string
    {
        return 'Elementor forms';
    }

    public function toggleKey(): string
    {
        return 'maspik_support_Elementor_forms';
    }

    public function isAvailable(): bool
    {
        // The classic Forms module is what fires elementor_pro/forms/validation.
        return class_exists('\ElementorPro\Modules\Forms\Module');
    }

    public function register(SpamGate $gate): void
    {
        // Direct-POST evidence: Elementor's own JS always posts `referrer`
        // (see its form bundle). Its absence means the request never went
        // through the site UI. Signal only — never blocks on its own.
        add_filter('maspik/direct_post_score', static function ($score, $submission = null) {
            return self::directPostSentinel($submission) === null
                ? $score
                : max((int) $score, DirectPostSignal::ELEMENTOR);
        }, 10, 2);

        // The matching sentinel, forwarded as `maspik_referrer` (v2: no_referrer).
        add_filter('maspik/direct_post_referrer', static function ($sentinel, $submission = null) {
            return self::directPostSentinel($submission) ?? $sentinel;
        }, 10, 2);

        add_action('elementor_pro/forms/validation', function ($record, $ajaxHandler) use ($gate) {
            $formName = method_exists($record, 'get_form_settings') ? $record->get_form_settings('form_name') : '';
            if (apply_filters('maspik_disable_elementor_spam_check', false, $formName, $record)) {
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

            // Elementor aborts the submission (and skips the email/webhook
            // actions) based on whether $ajax_handler->errors is non-empty —
            // NOT on is_success. add_error_message() alone sets is_success=false
            // but leaves errors empty, so the email would still be sent. We must
            // populate errors via add_error(). Submission-level violations
            // (honeypot, key, country, IP, Matrix) have no field, so we attach
            // the error to the last real field, exactly as v2 did.
            $fieldName = $verdict->violation !== null ? $verdict->violation->fieldName : null;
            $target = $fieldName !== null && $fieldName !== '' ? $fieldName : $lastFieldId;
            if ($target === '') {
                $target = 'maspik';
            }
            $ajaxHandler->add_error($target, $message);
        }, 10, 2);
    }

    /**
     * Elementor's own JS always posts `referrer`; its absence is the marker.
     * Shared by the score and sentinel filters so they cannot disagree.
     *
     * @param mixed $submission
     */
    private static function directPostSentinel($submission): ?string
    {
        if ($submission === null || $submission->source !== 'elementor') {
            return null;
        }

        return isset($_POST['referrer']) ? null : 'no_referrer';
    }
}
