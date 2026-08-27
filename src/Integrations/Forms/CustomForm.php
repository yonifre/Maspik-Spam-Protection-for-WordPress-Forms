<?php

declare(strict_types=1);

namespace Maspik\Integrations\Forms;

use Maspik\Application\SpamGate;
use Maspik\Domain\Check\HoneypotCheck;
use Maspik\Domain\Check\VerificationKeyCheck;
use Maspik\Domain\Model\FieldType;
use Maspik\Domain\Model\Submission;
use Maspik\Integrations\AbstractFormIntegration;
use Maspik\Integrations\Support\FieldMapper;
use Maspik\Integrations\Support\RawPayload;

/**
 * Public API for hand-built forms — the one integration with no plugin behind
 * it. A theme or plugin hands MASPIK its fields and gets a verdict back:
 *
 *     $fields = [
 *         ['type' => 'text',     'field_name' => 'name',    'value' => $_POST['name'] ?? ''],
 *         ['type' => 'email',    'field_name' => 'email',   'value' => $_POST['email'] ?? ''],
 *         ['type' => 'textarea', 'field_name' => 'message', 'value' => $_POST['message'] ?? ''],
 *     ];
 *
 *     if (maspik_is_spam($fields, 'My Form')) { … }
 *
 * Listing the fields explicitly is the recommended approach — only the values
 * the developer chooses are analysed. The honeypot and verification key are
 * read from $_POST automatically, so they never need to be forwarded.
 *
 * The v2 filter remains the underlying contract and still works verbatim:
 *
 *     $is_spam = apply_filters('maspik_validate_custom_form_fields', false, $fields, 'My Form');
 *
 * Returns false when clean, or an array with spam/message/reason/field_type/
 * field_name — byte-compatible with v2.9.x, so existing integrations built
 * against v2 keep working unchanged after the upgrade.
 */
final class CustomForm extends AbstractFormIntegration
{
    /** Field types accepted from callers. 'hidden' is handled separately. */
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
        return 'custom';
    }

    public function label(): string
    {
        return 'Custom PHP Form';
    }

    public function toggleKey(): string
    {
        return 'maspik_support_custom_forms';
    }

    /**
     * Off until switched on. Unlike every other integration here, this one is
     * not a plugin we detect — it is a PHP filter a developer has to call from
     * their own code, so isAvailable() can only ever answer "true". Leaving it
     * on by default puts a listener on every site that will never use it.
     *
     * v2 treated an unset toggle as ON, so upgrading sites are carried over
     * explicitly by Upgrade::repairCustomFormOptIn() rather than being flipped
     * off by this change.
     */
    public function optIn(): bool
    {
        return true;
    }

    /** Always available: it is an API, not a detected plugin. */
    public function isAvailable(): bool
    {
        return true;
    }

    /**
     * Normalize caller-supplied fields. Pure (no WordPress), so the contract is
     * unit-testable.
     *
     * Three input shapes are accepted:
     *   1. RECOMMENDED — an explicit list:
     *      [['type'=>'email','field_name'=>'email','value'=>…], …]. The caller
     *      decides exactly which values are analysed, so nonces, redirects,
     *      tokens, checkboxes and other technical inputs never reach the engine
     *      and can't cause surprising blocks.
     *   2. A flat map, e.g. $_POST — ['name' => 'John', 'email' => 'a@b.co'];
     *      types are inferred. Convenient, but inspects everything it is given.
     *   3. A bare string, treated as one message body.
     *
     * `hidden` only contains guard fields the caller actually supplied; the
     * caller fills the rest from $_POST (see resolveGuards) so a developer who
     * omits them doesn't accidentally fail the verification-key check.
     *
     * @param mixed $fields the caller's $fields argument
     * @return array{raw: array<int, array{name: string, type: string, value: string}>, hidden: array<string, string>, types: array<string, string>}
     */
    public static function parseFields($fields): array
    {
        // A bare string is treated as one message body, as in v2.
        if (! is_array($fields)) {
            $fields = [['type' => 'textarea', 'value' => (string) $fields, 'field_name' => 'message']];
        }

        $raw = [];
        $hidden = [];
        $types = [];

        foreach ($fields as $key => $field) {
            // Shape 1: flat map entry (value is a scalar, key is the name).
            if (! is_array($field) || ! array_key_exists('value', $field)) {
                if (is_array($field) || ! is_string($key) || $key === '') {
                    continue;
                }
                $name = $key;
                $value = (string) $field;
                $type = self::inferType($name, $value);
            } else {
                // Shape 2: explicit entry.
                $name = isset($field['field_name']) ? (string) $field['field_name'] : '';
                $value = FieldMapper::flatten($field['value']);
                $type = isset($field['type']) ? strtolower((string) $field['type']) : self::inferType($name, $value);
            }

            // Guard fields ride along as hidden inputs; never content-checked.
            if ($name === HoneypotCheck::FIELD_NAME || $name === VerificationKeyCheck::FIELD_NAME) {
                $hidden[$name] = $value;
                continue;
            }
            if ($type === 'hidden' || $name === '') {
                continue;
            }

            $types[$name] = $type;
            $raw[] = ['name' => $name, 'type' => $type, 'value' => $value];
        }

        return ['raw' => $raw, 'hidden' => $hidden, 'types' => $types];
    }

    /**
     * Best-effort field type from a name and value, so callers can hand over a
     * plain form array without labelling every field.
     */
    public static function inferType(string $name, string $value): string
    {
        $lower = strtolower($name);
        if (strpos($lower, 'email') !== false || strpos($lower, 'mail') !== false || filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return 'email';
        }
        if (strpos($lower, 'phone') !== false || strpos($lower, 'tel') !== false || strpos($lower, 'mobile') !== false) {
            return 'tel';
        }
        if (strpos($lower, 'url') !== false || strpos($lower, 'website') !== false || strpos($lower, 'link') !== false) {
            return 'url';
        }
        if (strpos($lower, 'message') !== false || strpos($lower, 'comment') !== false
            || strpos($lower, 'content') !== false || strpos($lower, 'description') !== false
            || strlen($value) > 100) {
            return 'textarea';
        }

        return 'text';
    }

    /**
     * Fill in any guard field the caller didn't pass, from the real request.
     *
     * The front-end guard script injects the honeypot and verification key into
     * the form, so they are already in $_POST — reading them here means a
     * developer who forgets to forward them still gets working bot protection
     * instead of every legitimate visitor failing the key check.
     *
     * @param array<string, string> $supplied guards the caller passed explicitly
     * @return array<string, string>
     */
    private static function resolveGuards(array $supplied): array
    {
        $guards = [];
        foreach ([HoneypotCheck::FIELD_NAME, VerificationKeyCheck::FIELD_NAME] as $name) {
            if (array_key_exists($name, $supplied)) {
                $guards[$name] = $supplied[$name];   // explicit value always wins
                continue;
            }
            $guards[$name] = isset($_POST[$name]) && ! is_array($_POST[$name])
                ? (string) wp_unslash($_POST[$name])
                : '';
        }

        return $guards;
    }

    public function register(SpamGate $gate): void
    {
        add_filter('maspik_validate_custom_form_fields', function ($isSpam, $fields = [], $formName = 'Custom Form') use ($gate) {
            // Another handler already decided — respect it (v2 behaviour).
            if ($isSpam !== false) {
                return $isSpam;
            }

            ['raw' => $raw, 'hidden' => $supplied, 'types' => $declaredType] = self::parseFields($fields);
            $hidden = self::resolveGuards($supplied);

            $formName = is_string($formName) && $formName !== '' ? $formName : 'Custom Form';
            $submission = new Submission(
                FieldMapper::map($raw, self::typeMap()),
                $this->id(),
                $formName,
                $this->clientIp->get(),
                $hidden,
                isset($_SERVER['HTTP_REFERER']) ? esc_url_raw(wp_unslash($_SERVER['HTTP_REFERER'])) : null,
                // Everything the caller passed, including types the engine will
                // not scan, so the log shows the whole submission.
                RawPayload::fromList($raw)
            );

            $verdict = $gate->evaluate($submission);
            if (! $verdict->isSpam || $verdict->violation === null) {
                return false;
            }

            $fieldName = (string) ($verdict->violation->fieldName ?? '');

            return [
                'spam' => true,
                'message' => $gate->errorMessage($verdict),
                'reason' => $verdict->violation->reason,
                // v2 reported the offending field's declared type, or 'general'
                // for submission-level layers (honeypot, key, country, IP, …).
                'field_type' => $fieldName !== '' && isset($declaredType[$fieldName]) ? $declaredType[$fieldName] : 'general',
                'field_name' => $fieldName !== '' ? $fieldName : 'general',
                // v3 addition: the layer that blocked it, for precise handling.
                'check_id' => $verdict->violation->checkId,
            ];
        }, 10, 3);
    }
}
