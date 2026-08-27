<?php

declare(strict_types=1);

namespace Maspik\Integrations\Forms;

use Maspik\Application\SpamGate;
use Maspik\Domain\Model\FieldType;
use Maspik\Infrastructure\ClientIp;
use Maspik\Infrastructure\Settings\Settings;
use Maspik\Integrations\AbstractFormIntegration;
use Maspik\Premium\ProGate;

/**
 * WooCommerce account registration adapter — Pro only, on by default when Woo
 * is active. Filter: woocommerce_registration_errors ($errors, $username,
 * $email). Block by $errors->add(...).
 *
 * The filter also fires for programmatic wc_create_new_customer() calls and for
 * account creation during checkout, so — like v2 — we only act on a genuine
 * register-form submission and skip checkout (the checkout adapter handles that)
 * to avoid double validation / false positives.
 */
final class WooCommerceRegistration extends AbstractFormIntegration
{
    /** @var Settings */
    private $settings;

    /** @var ProGate */
    private $proGate;

    public function __construct(ClientIp $clientIp, Settings $settings, ProGate $proGate)
    {
        parent::__construct($clientIp);
        $this->settings = $settings;
        $this->proGate = $proGate;
    }

    public static function typeMap(): array
    {
        return [
            'text' => FieldType::TEXT,
            'email' => FieldType::EMAIL,
        ];
    }

    public function id(): string
    {
        return 'woocommerce_registration';
    }

    public function label(): string
    {
        return 'WooCommerce Registration';
    }

    public function toggleKey(): string
    {
        return 'maspik_support_Woocommerce_registration';
    }

    public function pro(): bool
    {
        return true;
    }

    public function isAvailable(): bool
    {
        return class_exists('WooCommerce') || function_exists('WC');
    }

    public function register(SpamGate $gate): void
    {
        add_filter('woocommerce_registration_errors', function ($errors, $username = '', $email = '') use ($gate) {
            // Pro-only; default on (only an explicit 'no' disables it).
            if (! $this->proGate->supports('plugin')) {
                return $errors;
            }
            if ($this->settings->raw('maspik_support_Woocommerce_registration') === 'no') {
                return $errors;
            }
            // Edge case: not a real visitor registration.
            if (is_user_logged_in() && current_user_can('edit_posts')) {
                return $errors;
            }
            // Only a genuine WC register-form submission (not programmatic creation).
            if (! isset($_POST['register']) && ! isset($_POST['woocommerce-register-nonce'])) {
                return $errors;
            }
            // Account creation during checkout — the checkout adapter handles it.
            if ($this->isCheckoutAccountCreation()) {
                return $errors;
            }

            $raw = [];
            $login = sanitize_text_field((string) $username);
            $mail = sanitize_email((string) $email);
            if ($login !== '') {
                $raw[] = ['name' => 'username', 'type' => 'text', 'value' => $login];
            }
            if ($mail !== '') {
                $raw[] = ['name' => 'email', 'type' => 'email', 'value' => $mail];
            }

            $verdict = $gate->evaluate($this->submissionFrom($raw, self::typeMap()));
            if ($verdict->isSpam) {
                if (! is_wp_error($errors)) {
                    $errors = new \WP_Error();
                }
                $errors->add('maspik_spam_registration', $gate->errorMessage($verdict));
            }

            return $errors;
        }, 9999, 3);
    }

    /** Whether billing_* fields are present, i.e. account creation at checkout. */
    private function isCheckoutAccountCreation(): bool
    {
        foreach (array_keys($_POST) as $key) {
            if (is_string($key) && strpos($key, 'billing_') === 0) {
                return true;
            }
        }

        return false;
    }
}
