<?php

declare(strict_types=1);

namespace Maspik\Integrations\Forms;

use Maspik\Application\SpamGate;
use Maspik\Domain\Model\Field;
use Maspik\Domain\Model\FieldType;
use Maspik\Integrations\AbstractFormIntegration;
use WP_Error;

/**
 * WordPress core user registration.
 * Hook: registration_errors ($errors, $login, $email). Reject: $errors->add().
 * Always available — no plugin required.
 */
final class WordPressRegistration extends AbstractFormIntegration
{
    public function id(): string
    {
        return 'registration';
    }

    public function label(): string
    {
        return 'Wordpress Registration';
    }

    public function toggleKey(): string
    {
        return 'maspik_support_registration';
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function register(SpamGate $gate): void
    {
        add_filter('registration_errors', function ($errors, $login, $email) use ($gate) {
            if (apply_filters('maspik_disable_registration_spam_check', false)) {
                return $errors;
            }

            $fields = [];
            if (is_string($login) && $login !== '') {
                $fields[] = new Field('user_login', FieldType::TEXT, $login);
            }
            if (is_string($email) && $email !== '') {
                $fields[] = new Field('user_email', FieldType::EMAIL, $email);
            }

            $verdict = $gate->evaluate($this->submission($fields));
            if ($verdict->isSpam && $errors instanceof WP_Error) {
                $errors->add('maspik_error', $gate->errorMessage($verdict));
            }

            return $errors;
        }, 10, 3);
    }
}
