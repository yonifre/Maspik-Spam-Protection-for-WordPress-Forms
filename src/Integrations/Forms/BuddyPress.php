<?php

declare(strict_types=1);

namespace Maspik\Integrations\Forms;

use Maspik\Application\SpamGate;
use Maspik\Domain\Model\FieldType;
use Maspik\Integrations\AbstractFormIntegration;

/**
 * BuddyPress registration adapter.
 * Action: bp_signup_validate — the signup email/username live on the global
 * $bp->signup. There is no return value; a validation error is raised by
 * setting $bp->signup->errors['signup_email'|'signup_username'].
 *
 * Guard fields (honeypot/key) arrive in $_POST via the shared front-end guard
 * script and are read by the engine's submission-level checks.
 */
final class BuddyPress extends AbstractFormIntegration
{
    public static function typeMap(): array
    {
        return [
            'text' => FieldType::TEXT,
            'email' => FieldType::EMAIL,
        ];
    }

    public function id(): string
    {
        return 'buddypress';
    }

    public function label(): string
    {
        return 'BuddyPress';
    }

    public function toggleKey(): string
    {
        return 'maspik_support_buddypress_forms';
    }

    public function isAvailable(): bool
    {
        return defined('BP_VERSION') || function_exists('buddypress');
    }

    public function register(SpamGate $gate): void
    {
        add_action('bp_signup_validate', function () use ($gate) {
            global $bp;
            if (! isset($bp->signup) || ! is_object($bp->signup)) {
                return;
            }

            $email = isset($bp->signup->email) ? sanitize_email((string) $bp->signup->email) : '';
            $username = isset($bp->signup->username) ? sanitize_text_field((string) $bp->signup->username) : '';

            $raw = [];
            if ($username !== '') {
                $raw[] = ['name' => 'signup_username', 'type' => 'text', 'value' => $username];
            }
            if ($email !== '') {
                $raw[] = ['name' => 'signup_email', 'type' => 'email', 'value' => $email];
            }

            $verdict = $gate->evaluate($this->submissionFrom($raw, self::typeMap()));
            if (! $verdict->isSpam) {
                return;
            }

            $message = $gate->errorMessage($verdict);
            $fieldName = $verdict->violation !== null ? $verdict->violation->fieldName : null;
            // Attach to the email field for an email violation; otherwise the
            // username field (v2's default for submission-level blocks).
            $key = $fieldName === 'signup_email' ? 'signup_email' : 'signup_username';

            if (! isset($bp->signup->errors) || ! is_array($bp->signup->errors)) {
                $bp->signup->errors = [];
            }
            $bp->signup->errors[$key] = $message;
        });
    }
}
