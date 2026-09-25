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
 * AffiliateWP registration adapter.
 *
 * Hook: `affwp_process_register_form` (action). It fires at the end of
 * AffiliateWP's own validation, immediately before the plugin checks whether
 * any errors were collected and, finding none, creates both the WordPress user
 * and the affiliate record. Adding an error there refuses the registration
 * before either exists.
 *
 * Worth protecting despite the small install base: a spam affiliate signup does
 * not just land in an inbox, it creates a payable account that then appears in
 * commission reports and payout runs. Cleaning one up costs far more than
 * deleting a contact-form message.
 *
 * No direct-POST signal is raised here. AffiliateWP verifies its own nonce
 * before this hook runs, so a request that reaches us already came through a
 * rendered form — inventing a suspicion score for it would add a number to the
 * scale that means nothing.
 */
final class AffiliateWP extends AbstractFormIntegration
{
    /** Our own row types; AffiliateWP posts a flat form, not a typed field list. */
    public static function typeMap(): array
    {
        return [
            'text' => FieldType::TEXT,
            'email' => FieldType::EMAIL,
            'url' => FieldType::URL,
            'textarea' => FieldType::TEXTAREA,
        ];
    }

    /**
     * Posted name => our row type.
     *
     * Taken from the plugin's own templates/register.php rather than from its
     * documentation. `affwp_promotion_method` is the interesting one: it is a
     * free-text box asking how the applicant plans to promote the site, which
     * makes it the field a spammer fills with links.
     */
    private const FIELDS = [
        'affwp_user_name' => 'text',
        'affwp_user_login' => 'text',
        'affwp_user_email' => 'email',
        'affwp_payment_email' => 'email',
        'affwp_user_url' => 'url',
        'affwp_promotion_method' => 'textarea',
    ];

    public function id(): string
    {
        return 'affiliatewp';
    }

    public function label(): string
    {
        return 'AffiliateWP';
    }

    public function toggleKey(): string
    {
        return 'maspik_support_affiliatewp';
    }

    public function isAvailable(): bool
    {
        return defined('AFFILIATEWP_VERSION') || function_exists('affiliate_wp');
    }

    public function register(SpamGate $gate): void
    {
        add_action('affwp_process_register_form', \Maspik\Kernel\Guard::wrap(function () use ($gate) {
            if (apply_filters('maspik_disable_affiliatewp_spam_check', false)) {
                return;
            }

            $register = $this->register_handler();
            if ($register === null) {
                // Nothing to report a refusal through. Blocking without a way
                // to say so would fail the registration silently, which is
                // worse than letting it through to a human.
                return;
            }

            // AffiliateWP has already refused this one — its own honeypot, a
            // taken username, an invalid URL. Adding a second complaint helps
            // nobody.
            if (method_exists($register, 'get_errors') && $register->get_errors() !== []) {
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

            $register->add_error('maspik_spam', $gate->errorMessage($verdict));
        }));
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

            $value = (string) $post[$name];
            if (trim($value) === '') {
                continue;
            }

            $rows[] = [
                'name' => $name,
                'type' => $type,
                'value' => $type === 'email'
                    ? sanitize_email($value)
                    : sanitize_textarea_field($value),
            ];
        }

        return $rows;
    }

    /**
     * AffiliateWP's registration handler, or null when it cannot be reached.
     *
     * Guarded rather than assumed: this runs inside AffiliateWP's own hook, so
     * the object is normally there, but a fatal from a property that moved in a
     * future release would take the whole signup page down with it.
     *
     * @return object|null
     */
    private function register_handler()
    {
        if (! function_exists('affiliate_wp')) {
            return null;
        }

        $affwp = affiliate_wp();
        if (! is_object($affwp) || ! isset($affwp->register) || ! is_object($affwp->register)) {
            return null;
        }

        return method_exists($affwp->register, 'add_error') ? $affwp->register : null;
    }
}
