<?php

declare(strict_types=1);

namespace Maspik\Integrations\Forms;

use Maspik\Application\SpamGate;
use Maspik\Domain\Model\FieldType;
use Maspik\Infrastructure\Matrix\DirectPostSignal;
use Maspik\Integrations\AbstractFormIntegration;
use Maspik\Integrations\Support\FieldMapper;

if (! defined('ABSPATH')) {
    exit;
}


/**
 * SureForms adapter.
 *
 * Two hooks, because neither one alone carries what is needed:
 *
 *  - `srfm_before_fields_processing` (filter) sees the raw posted data, whose
 *    keys still say what each field is. This is the only place the field types
 *    survive, so it is where they are captured. The data is returned untouched.
 *  - `srfm_before_submission` (action) is where the check runs and where a
 *    spam submission is refused, after SureForms' own validation and before it
 *    sends mail or stores an entry.
 *
 * The split exists because SureForms' `prepare_submission_data()` rewrites
 * every key to its human label on the way between the two — `srfm-email-a1b2`
 * becomes `Your Email` — so by the time the second hook fires there is nothing
 * left to map a type from. Reading `$_POST` at that point would work today
 * (the form posts multipart, so PHP populates it) but would quietly stop
 * working the day SureForms sends a JSON body, and the failure would look like
 * "no fields" rather than an error.
 *
 * Field key format: `srfm-<type>-<blockid>-lbl-<encoded label>`.
 */
final class SureForms extends AbstractFormIntegration
{
    /**
     * SureForms field type => FieldType.
     *
     * The keys are the slugs SureForms puts in the field name, taken from its
     * own `inc/fields/*-markup.php` classes rather than from the labels its
     * form builder shows.
     *
     * `input` is the one to be careful about: it is SureForms' plain text
     * field, and nothing about the name suggests it. Mapping `text` here — the
     * obvious guess, and what every other plugin calls it — would leave every
     * single-line text field on every SureForms form unscanned, with nothing
     * anywhere to say so.
     *
     * Deliberately absent: `checkbox`, `dropdown`, `multichoice`, `gdpr`,
     * `upload`, `inlinebutton`, `number`. A visitor picks those from a list or
     * they hold a file or a figure, so there is no free text in them for a
     * content rule to act on.
     */
    public static function typeMap(): array
    {
        return [
            // SureForms' plain text field. Not 'text'.
            'input' => FieldType::TEXT,
            'email' => FieldType::EMAIL,
            'phone' => FieldType::TEL,
            'url' => FieldType::URL,
            'textarea' => FieldType::TEXTAREA,
            // Composite (street, city, …); FieldMapper flattens the parts.
            'address' => FieldType::TEXT,
        ];
    }

    public function id(): string
    {
        return 'sureforms';
    }

    public function label(): string
    {
        return 'SureForms';
    }

    public function toggleKey(): string
    {
        return 'maspik_support_sureforms';
    }

    public function isAvailable(): bool
    {
        return defined('SRFM_VER') || class_exists('\SRFM\Inc\Form_Submit');
    }

    /**
     * Fields captured from the filter, for the action that follows.
     *
     * Request-scoped: both hooks fire inside one submission.
     *
     * @var array<int, array{name: string, type: string, value: mixed}>
     */
    private $captured = [];

    public function register(SpamGate $gate): void
    {
        add_filter('srfm_before_fields_processing', function ($formData) {
            $this->captured = is_array($formData) ? self::fieldsFrom($formData) : [];

            // Read-only. Returning anything else here would change what
            // SureForms stores and emails.
            return $formData;
        }, 5, 1);

        add_action('srfm_before_submission', function ($data) use ($gate) {
            $formId = is_array($data) && isset($data['form_id']) ? (int) $data['form_id'] : 0;

            if (apply_filters('maspik_disable_sureforms_spam_check', false, $formId)) {
                return;
            }

            if ($this->captured === []) {
                return;
            }

            // Direct-POST evidence: a submission from a rendered form always
            // carries a form id. Signal only — it never blocks on its own.
            add_filter('maspik/direct_post_score', static function ($score) use ($formId) {
                return $formId > 0 ? $score : max((int) $score, DirectPostSignal::SUREFORMS);
            }, 10, 1);

            $verdict = $gate->evaluate($this->submissionFrom($this->captured, self::typeMap()));
            $this->captured = [];

            if (! $verdict->isSpam) {
                return;
            }

            // SureForms answers its own REST endpoint with wp_send_json_error,
            // and its front-end script renders `message`. Matching that shape
            // is what puts our reason in front of the visitor instead of a
            // generic failure.
            wp_send_json_error([
                'code' => 'maspik_spam',
                'message' => $gate->errorMessage($verdict),
            ]);
        }, 5, 1);
    }

    /**
     * Turn SureForms' posted data into name/type/value rows.
     *
     * Keys look like `srfm-input-a1b2c3-lbl-<encoded label>`: the slug after
     * `srfm-` is the field type, and everything from `-lbl-` on is the label,
     * encoded. Anything without `-lbl-` is SureForms' own plumbing — the form
     * id, the nonce — and not something a visitor typed.
     *
     * The name kept for each row is the full key. It is not pretty in the log,
     * but it is the only stable identifier here: the label is encoded, and the
     * block id alone repeats across forms.
     *
     * @param array<string, mixed> $formData
     * @return array<int, array{name: string, type: string, value: mixed}>
     */
    public static function fieldsFrom(array $formData): array
    {
        $rows = [];

        foreach ($formData as $key => $value) {
            $key = (string) $key;
            if (strpos($key, '-lbl-') === false || strpos($key, 'srfm-') !== 0) {
                continue;
            }

            // 'srfm-input-a1b2c3' => ['srfm', 'input', 'a1b2c3']
            $parts = explode('-', explode('-lbl-', $key)[0]);
            if (! isset($parts[1]) || $parts[1] === '') {
                continue;
            }

            $rows[] = [
                'name' => $key,
                'type' => $parts[1],
                'value' => $value,
            ];
        }

        return $rows;
    }
}
