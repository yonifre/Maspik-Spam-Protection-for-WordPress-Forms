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
 * Easy Digital Downloads customer registration adapter.
 *
 * Hook: `edd_process_register_form` (action). It fires at the end of EDD's own
 * validation, immediately before EDD reads its error bag and — finding it empty
 * — creates and logs in the new customer. Adding an error there refuses the
 * registration before the account exists.
 *
 * Registration only. EDD's product reviews live in a separate commercial
 * add-on that is not part of the plugin, so there is nothing here to hook and
 * nothing that could be tested; a review integration written blind against
 * documentation would be a guess wearing the clothes of a feature.
 *
 * The checkout is deliberately untouched. EDD takes payment there, and an
 * anti-spam layer that can refuse a purchase is one a store owner removes the
 * first time it is wrong — along with the protection it was providing
 * everywhere else.
 *
 * Like AffiliateWP, EDD returns early for logged-in users, so this only ever
 * sees anonymous signups. That is the right surface: someone already
 * authenticated on the site is not the account-farming problem this closes.
 */
final class EasyDigitalDownloads extends AbstractFormIntegration
{
    /** Our own row types; EDD posts a flat form, not a typed field list. */
    public static function typeMap(): array
    {
        return [
            'text' => FieldType::TEXT,
            'email' => FieldType::EMAIL,
        ];
    }

    /**
     * Posted name => our row type.
     *
     * From the plugin's own `templates/shortcode-register.php` and the
     * validation in `includes/users/register.php`. `edd_payment_email` is not
     * in the stock template but the validation reads it, so a themed or
     * extended form can send it.
     *
     * `edd_honeypot` is EDD's own trap and is left alone: it is already
     * checked, and reading it here would double-report the same catch.
     */
    private const FIELDS = [
        'edd_user_login' => 'text',
        'edd_user_email' => 'email',
        'edd_payment_email' => 'email',
    ];

    public function id(): string
    {
        return 'edd_registration';
    }

    public function label(): string
    {
        return 'Easy Digital Downloads Registration';
    }

    public function toggleKey(): string
    {
        return 'maspik_support_edd_registration';
    }

    public function isAvailable(): bool
    {
        return defined('EDD_VERSION') || class_exists('\Easy_Digital_Downloads');
    }

    public function register(SpamGate $gate): void
    {
        add_action('edd_process_register_form', function () use ($gate) {
            if (apply_filters('maspik_disable_edd_registration_spam_check', false)) {
                return;
            }

            // EDD has already refused this one — a taken username, its own
            // honeypot, mismatched passwords. A second complaint about a form
            // that is not going through helps nobody.
            if (function_exists('edd_get_errors') && ! empty(edd_get_errors())) {
                return;
            }

            $raw = $this->fields();
            if ($raw === []) {
                return;
            }

            $verdict = $gate->evaluate($this->submissionFrom($raw, self::typeMap()));
            if (! $verdict->isSpam) {
                return;
            }

            if (function_exists('edd_set_error')) {
                edd_set_error('maspik_spam', $gate->errorMessage($verdict));
            }
        });
    }

    /**
     * @return array<int, array{name: string, type: string, value: string}>
     */
    private function fields(): array
    {
        $post = isset($_POST) ? (array) wp_unslash($_POST) : [];

        $rows = [];
        foreach (self::FIELDS as $name => $type) {
            if (! isset($post[$name]) || ! is_scalar($post[$name])) {
                continue;
            }

            $value = trim((string) $post[$name]);
            if ($value === '') {
                continue;
            }

            $rows[] = [
                'name' => $name,
                'type' => $type,
                'value' => $type === 'email' ? sanitize_email($value) : sanitize_text_field($value),
            ];
        }

        return $rows;
    }
}
