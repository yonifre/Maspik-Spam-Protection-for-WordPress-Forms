<?php

declare(strict_types=1);

namespace Maspik\Integrations\Forms;

use Maspik\Application\SpamGate;
use Maspik\Domain\Model\FieldType;
use Maspik\Infrastructure\Matrix\DirectPostSignal;
use Maspik\Integrations\AbstractFormIntegration;

/**
 * Gravity Forms adapter.
 * Hook: gform_validation ($validation_result). Fields are GF_Field objects in
 * $result['form']['fields']; submitted values come from rgpost('input_{id}').
 * Reject: set is_valid = false and mark the field's failed_validation.
 */
final class GravityForms extends AbstractFormIntegration
{
    /** Gravity field type => FieldType. */
    public static function typeMap(): array
    {
        return [
            'text' => FieldType::TEXT,
            'name' => FieldType::TEXT,
            'email' => FieldType::EMAIL,
            'phone' => FieldType::TEL,
            'website' => FieldType::URL,
            'textarea' => FieldType::TEXTAREA,
        ];
    }

    public function id(): string
    {
        return 'gravityforms';
    }

    public function label(): string
    {
        return 'Gravityforms';
    }

    public function toggleKey(): string
    {
        return 'maspik_support_gravity_forms';
    }

    public function pro(): bool
    {
        return true;
    }

    public function isAvailable(): bool
    {
        return class_exists('GFForms');
    }

    public function register(SpamGate $gate): void
    {
        // Direct-POST evidence for InputGate. A genuine Gravity submit carries
        // gform_submit with the form id, is_submit_<id>=1 and a state token;
        // any of those missing means the post did not come from the rendered
        // form. Signal only - it never blocks on its own. Score 6 identifies
        // Gravity Forms as the source in the cloud (v2: gravityforms.php).
        add_filter('maspik/direct_post_score', static function ($score, $submission = null) {
            return self::directPostSentinel($submission) === null
                ? $score
                : max((int) $score, DirectPostSignal::GRAVITY_FORMS);
        }, 10, 2);

        // The matching sentinel, forwarded as `maspik_referrer`. Gravity has
        // several distinct failure markers and v2 reported which one fired, so
        // the server can tell a forged post from a stale form cache.
        add_filter('maspik/direct_post_referrer', static function ($sentinel, $submission = null) {
            return self::directPostSentinel($submission) ?? $sentinel;
        }, 10, 2);

        add_filter('gform_validation', function ($result) use ($gate) {
            $form = isset($result['form']) ? $result['form'] : array();
            $formId = isset($form['id']) ? $form['id'] : 0;
            if (apply_filters('maspik_disable_gravityforms_spam_check', false, $formId, $form)) {
                return $result;
            }

            $raw = [];
            foreach (isset($form['fields']) ? $form['fields'] : array() as $field) {
                $value = function_exists('rgpost') ? rgpost('input_' . $field->id) : '';
                $raw[] = ['name' => (string) $field->id, 'type' => (string) $field->type, 'value' => is_array($value) ? $value : (string) $value];
            }

            $verdict = $gate->evaluate($this->submissionFrom($raw, self::typeMap()));
            if (! $verdict->isSpam) {
                return $result;
            }

            $result['is_valid'] = false;
            $message = $gate->errorMessage($verdict);
            $fieldName = $verdict->violation !== null ? $verdict->violation->fieldName : null;

            $marked = false;
            foreach ($result['form']['fields'] as $field) {
                if ((string) $field->id === (string) $fieldName) {
                    $field->failed_validation = true;
                    $field->validation_message = $message;
                    $marked = true;
                    break;
                }
            }
            if (! $marked && ! empty($result['form']['fields'])) {
                // Submission-level block: attach to the first visible field.
                foreach ($result['form']['fields'] as $field) {
                    if (! in_array($field->type, ['hidden', 'html', 'section'], true)) {
                        $field->failed_validation = true;
                        $field->validation_message = $message;
                        break;
                    }
                }
            }

            return $result;
        }, 10, 1);
    }

    /**
     * Which of Gravity's page-context markers is missing, or null when the post
     * looks genuine. A real submit carries gform_submit with the form id,
     * is_submit_<id>=1 and a state token; the cascade mirrors v2 so each
     * sentinel keeps the meaning the server already correlates.
     *
     * @param mixed $submission
     */
    private static function directPostSentinel($submission): ?string
    {
        if ($submission === null || $submission->source !== 'gravityforms') {
            return null;
        }

        if (empty($_POST['gform_submit'])) {
            return 'no_gform_submit';
        }

        $formId = absint(wp_unslash($_POST['gform_submit']));
        $isSubmit = isset($_POST['is_submit_' . $formId])
            ? (string) wp_unslash($_POST['is_submit_' . $formId]) : '';
        if ($isSubmit !== '1') {
            return 'is_submit_invalid';
        }

        $state = isset($_POST['state_' . $formId])
            ? (string) wp_unslash($_POST['state_' . $formId]) : '';
        if ($state === '') {
            return 'no_state_token';
        }

        return null;
    }
}
