<?php

declare(strict_types=1);

namespace Maspik\Integrations\Forms;

use Maspik\Application\SpamGate;
use Maspik\Domain\Model\FieldType;
use Maspik\Integrations\AbstractFormIntegration;
use Maspik\Integrations\Support\FieldMapper;

/**
 * Breakdance Builder forms adapter.
 *
 * Breakdance has no single spam hook; instead each "action" (email, webhook,
 * store_submission, …) runs through breakdance_form_run_action_{action}
 * ($canExecute, $action, $extra, $form, $settings). Returning a WP_Error blocks
 * that action. Because one submission fires several of these, we evaluate once
 * and cache the verdict per submission (keyed like v2) so the engine logs a
 * single entry and makes at most one Matrix call.
 *
 * $extra['fields'] is a typeless name => value map, so — as in v2 — we infer
 * each field's type from its name/length. Guard fields (honeypot/key) arrive in
 * $_POST via the shared front-end guard script.
 */
final class Breakdance extends AbstractFormIntegration
{
    /** Breakdance form actions that should be gated. */
    private const ACTIONS = [
        'store_submission', 'email', 'webhook', 'custom_javascript',
        'mailchimp', 'popup', 'slack', 'drip',
    ];

    /** Per-submission verdict cache (true | WP_Error), request-scoped. */
    private $cache = [];

    /** Inferred type => FieldType. */
    public static function typeMap(): array
    {
        return [
            'text' => FieldType::TEXT,
            'email' => FieldType::EMAIL,
            'phone' => FieldType::TEL,
            'url' => FieldType::URL,
            'textarea' => FieldType::TEXTAREA,
        ];
    }

    public function id(): string
    {
        return 'breakdance';
    }

    public function label(): string
    {
        return 'Breakdance';
    }

    public function toggleKey(): string
    {
        return 'maspik_support_breakdance_forms';
    }

    public function isAvailable(): bool
    {
        return defined('__BREAKDANCE_VERSION') || defined('BREAKDANCE_VERSION');
    }

    public function register(SpamGate $gate): void
    {
        foreach (self::ACTIONS as $action) {
            add_filter('breakdance_form_run_action_' . $action, function ($canExecute, $action = null, $extra = [], $form = [], $settings = []) use ($gate) {
                return $this->maybeBlock($gate, $canExecute, $extra);
            }, 1, 5);
        }
    }

    /**
     * @param mixed $canExecute
     * @param mixed $extra
     * @return mixed true to allow, WP_Error to block
     */
    private function maybeBlock(SpamGate $gate, $canExecute, $extra)
    {
        if (! $canExecute || ! is_array($extra)) {
            return $canExecute;
        }

        $fields = isset($extra['fields']) && is_array($extra['fields']) ? $extra['fields'] : [];
        if ($fields === []) {
            return $canExecute;
        }

        // Same submission fires multiple action filters — evaluate/log once.
        $key = (string) ($extra['formId'] ?? '') . '_' . (string) ($extra['postId'] ?? '') . '_' . md5(serialize($fields));
        if (array_key_exists($key, $this->cache)) {
            return $this->cache[$key];
        }

        $raw = $this->classifyFields($fields);
        $verdict = $gate->evaluate($this->submissionFrom($raw, self::typeMap()));

        $result = $canExecute;
        if ($verdict->isSpam) {
            $result = new \WP_Error('spam_detected', $gate->errorMessage($verdict));
        }

        $this->cache[$key] = $result;

        return $result;
    }

    /**
     * @param array<string, mixed> $fields
     * @return array<int, array{name: string, type: string, value: string}>
     */
    private function classifyFields(array $fields): array
    {
        $raw = [];
        foreach ($fields as $name => $value) {
            if (! is_string($name)) {
                continue;
            }
            // A value that arrives as an array in a typeless payload is a
            // multi-choice field (checkbox group, radio, multi-select). Those
            // are options the site owner defined, not visitor-written text — a
            // spammer cannot put anything in them, and scanning them only risks
            // blocking every legitimate submission when an option label happens
            // to contain a blacklisted word.
            if (is_array($value)) {
                continue;
            }
            $value = FieldMapper::flatten($value);
            if (trim($value) === '') {
                continue;
            }

            $type = $this->classify($name, $value);
            if ($type === null) {
                continue;
            }

            $raw[] = ['name' => $name, 'type' => $type, 'value' => $value];
        }

        return $raw;
    }

    /** Infer a field type from its name/length (v2 Breakdance heuristics). */
    private function classify(string $name, string $value): ?string
    {
        $lower = strtolower($name);
        if (strpos($lower, 'email') !== false || filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return 'email';
        }
        if (strpos($lower, 'phone') !== false || strpos($lower, 'tel') !== false || strpos($lower, 'mobile') !== false) {
            return 'phone';
        }
        if (strpos($lower, 'url') !== false || strpos($lower, 'website') !== false || strpos($lower, 'link') !== false) {
            return 'url';
        }
        if (strpos($lower, 'message') !== false || strpos($lower, 'content') !== false || strpos($lower, 'textarea') !== false
            || strpos($lower, 'comment') !== false || strpos($lower, 'description') !== false || strlen($value) > 100) {
            return 'textarea';
        }
        if (strpos($lower, 'name') !== false || strpos($lower, 'first') !== false || strpos($lower, 'last') !== false) {
            return 'text';
        }

        return null;
    }
}
