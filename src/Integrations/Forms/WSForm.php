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
 * WS Form adapter.
 *
 * Hook: `wsf_submit_validate` (filter). It receives the validation errors
 * collected so far; a string added to that array becomes a message shown to the
 * visitor, and the submission is refused before WS Form saves an entry or runs
 * any action.
 *
 * WHY NOT `wsf_submit_spam_check`
 *
 * WS Form has a purpose-built spam hook, and it is the one Akismet and
 * AbuseIPDB use. It is not used here because it does something different: it
 * sets a 0–100 `spam_level`, and when that crosses the form's threshold WS Form
 * still saves the submission and simply stops every action except the database
 * one. The visitor sees success; the entry lands in a spam folder.
 *
 * That is a reasonable design, but it is not what this plugin does anywhere
 * else. Everywhere else a blocked submission is refused, the visitor is told,
 * and the site owner reads it in the log. Quarantining here instead would mean
 * WS Form was the one integration where "blocked" quietly meant "delivered to a
 * folder", which is exactly the sort of inconsistency nobody discovers until
 * they are looking for a message that was never refused.
 */
final class WSForm extends AbstractFormIntegration
{
    /**
     * WS Form field type => FieldType.
     *
     * The keys are the slugs WS Form registers, read from the running plugin's
     * own field-type config rather than from its documentation.
     *
     * `texteditor` is its rich-text field and matters more than it looks: it
     * accepts the most content and renders links, so it is the field a spammer
     * reaches for first. Leaving it out would mean the blocklists never see the
     * one place spam actually goes.
     *
     * Deliberately absent: `password` (never scanned anywhere in this plugin),
     * `hidden` (plumbing, not something a visitor typed), and every choice,
     * price, media and layout type, which hold selections or files rather than
     * free text.
     */
    public static function typeMap(): array
    {
        return [
            'text' => FieldType::TEXT,
            'email' => FieldType::EMAIL,
            'tel' => FieldType::TEL,
            'url' => FieldType::URL,
            'textarea' => FieldType::TEXTAREA,
            'texteditor' => FieldType::TEXTAREA,
            // Address autocomplete — free text the visitor typed.
            'googleaddress' => FieldType::TEXT,
        ];
    }

    public function id(): string
    {
        return 'wsform';
    }

    public function label(): string
    {
        return 'WS Form';
    }

    public function toggleKey(): string
    {
        return 'maspik_support_wsform';
    }

    public function isAvailable(): bool
    {
        return defined('WS_FORM_VERSION') || class_exists('\WS_Form_Submit');
    }

    public function register(SpamGate $gate): void
    {
        add_filter('wsf_submit_validate', function ($errors, $postMode = '', $submit = null) use ($gate) {
            $errors = is_array($errors) ? $errors : [];

            // Only a real submission. WS Form runs this filter for saves and
            // partial posts too, and refusing a half-finished draft would be
            // both wrong and baffling.
            if ($postMode !== 'submit') {
                return $errors;
            }

            $formId = is_object($submit) && isset($submit->form_id) ? (int) $submit->form_id : 0;

            if (apply_filters('maspik_disable_wsform_spam_check', false, $formId)) {
                return $errors;
            }

            // WS Form already refused this one; a second complaint helps nobody.
            if ($errors !== []) {
                return $errors;
            }

            $raw = self::fieldsFrom($submit);
            if ($raw === []) {
                return $errors;
            }

            $verdict = $gate->evaluate($this->submissionFrom($raw, self::typeMap()));
            if ($verdict->isSpam) {
                // A plain string here becomes a form-level message: WS Form's
                // own filter_validate() expands it into
                // ['action' => 'message', 'message' => …].
                $errors[] = $gate->errorMessage($verdict);
            }

            return $errors;
        }, 10, 3);
    }

    /**
     * Read the submitted fields off the WS Form submit object.
     *
     * WS Form does not post a flat form: values live in `$submit->meta`, keyed
     * `field_<id>`, each entry carrying its own `type` alongside the `value`.
     * That is better than most — the type travels with the value instead of
     * having to be inferred — so the only work here is reading it safely.
     *
     * @param mixed $submit WS_Form_Submit
     * @return array<int, array{name: string, type: string, value: mixed}>
     */
    public static function fieldsFrom($submit): array
    {
        if (! is_object($submit) || ! isset($submit->meta) || ! is_array($submit->meta)) {
            return [];
        }

        $rows = [];
        foreach ($submit->meta as $key => $entry) {
            if (! is_array($entry) || ! isset($entry['type'], $entry['value'])) {
                continue;
            }

            $value = $entry['value'];
            if (! is_scalar($value) && ! is_array($value)) {
                continue;
            }

            $rows[] = [
                'name' => (string) $key,
                'type' => (string) $entry['type'],
                'value' => $value,
            ];
        }

        return $rows;
    }
}
