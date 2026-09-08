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
 * MemberPress registration adapter.
 *
 * Hook: `mepr_validate_signup` (filter). It receives the errors MemberPress has
 * collected so far and returns them; appending a string refuses the signup and
 * shows the message on the form. Registered late (priority 20) so MemberPress'
 * own validation runs first — there is no point spam-checking a signup that is
 * about to be refused for a missing password.
 *
 * PAID CHECKOUTS ARE NOT GATED BY DEFAULT
 *
 * MemberPress creates the WordPress account before it takes payment, so a false
 * positive on a paid signup does not merely annoy someone — it aborts a
 * purchase. Free memberships, free trials and fully-discounted signups have no
 * money at stake and are exactly the fake-account vector worth closing, so they
 * stay gated. Anything that charges at signup is left alone unless the site
 * owner opts in.
 *
 * That asymmetry is deliberate and worth keeping: anti-spam that costs a sale
 * gets uninstalled the same day, and the protection it offered goes with it.
 */
final class MemberPress extends AbstractFormIntegration
{
    /** Our own row types; MemberPress posts a flat form, not a typed field list. */
    public static function typeMap(): array
    {
        return [
            'text' => FieldType::TEXT,
            'email' => FieldType::EMAIL,
        ];
    }

    public function id(): string
    {
        return 'memberpress';
    }

    public function label(): string
    {
        return 'MemberPress';
    }

    public function toggleKey(): string
    {
        return 'maspik_support_memberpress';
    }

    /**
     * Off until deliberately enabled.
     *
     * This gate sits in front of account creation on a membership site, where a
     * wrong refusal costs a signup rather than an inbox message. MemberPress
     * also varies a great deal between installs - which fields the form asks
     * for, whether the username is the email, which memberships charge at
     * signup - so whether it belongs in the request path is a judgement about
     * one site, not a default worth making for every site.
     */
    public function optIn(): bool
    {
        return true;
    }

    /**
     * Prompts the owner to confirm a real signup still completes after they
     * switch this on - see the admin screen. Worth it wherever the check runs
     * before an account exists, because the failure mode is a member who could
     * not join rather than spam that got through.
     */
    public function needsVerification(): bool
    {
        return true;
    }

    public function isAvailable(): bool
    {
        return defined('MEPR_VERSION') || class_exists('\MeprProduct');
    }

    public function register(SpamGate $gate): void
    {
        add_filter('mepr_validate_signup', function ($errors) use ($gate) {
            $errors = is_array($errors) ? $errors : [];

            $productId = $this->productId();

            if (apply_filters('maspik_disable_memberpress_spam_check', false, $productId)) {
                return $errors;
            }

            // Someone else already refused this signup. Checking it would add a
            // second complaint about a form that is not going through anyway.
            if ($errors !== []) {
                return $errors;
            }

            if ($this->chargesAtSignup($productId) && ! $this->gatesPaidSignups()) {
                return $errors;
            }

            $raw = $this->fields();
            if ($raw === []) {
                return $errors;
            }

            $verdict = $gate->evaluate($this->submissionFrom($raw, self::typeMap()));
            if ($verdict->isSpam) {
                $errors[] = $gate->errorMessage($verdict);
            }

            return $errors;
        }, 20, 1);
    }

    /**
     * The registrant, as MemberPress posts them.
     *
     * `user_login` is absent when the "members must use their email address as
     * their username" option is on, and the name fields depend on which fields
     * the site asks for — so each one is optional and the set is built from
     * whatever actually arrived.
     *
     * @return array<int, array{name: string, type: string, value: string}>
     */
    private function fields(): array
    {
        $post = isset($_POST) ? (array) wp_unslash($_POST) : [];

        $rows = [];

        $email = self::scalar($post, 'user_email');
        if ($email !== '') {
            $rows[] = ['name' => 'user_email', 'type' => 'email', 'value' => sanitize_email($email)];
        }

        foreach (['user_login', 'user_first_name', 'user_last_name'] as $key) {
            $value = self::scalar($post, $key);
            if ($value !== '') {
                $rows[] = ['name' => $key, 'type' => 'text', 'value' => sanitize_text_field($value)];
            }
        }

        return $rows;
    }

    /**
     * True when this membership takes money at signup.
     *
     * A paid membership whose trial is free charges nothing today, so it counts
     * as free here: it is a prime fake-account vector and refusing one costs no
     * sale. A coupon that discounts the signup to zero is treated the same way,
     * which is why the code is passed through rather than ignored.
     *
     * @param int|string $productId
     */
    private function chargesAtSignup($productId): bool
    {
        if (! is_int($productId) || $productId <= 0 || ! class_exists('\MeprProduct')) {
            return false;
        }

        try {
            $product = new \MeprProduct($productId);
            if (empty($product->ID)) {
                return false;
            }

            $coupon = $this->couponCode();
            if (method_exists($product, 'is_payment_required')
                && ! $product->is_payment_required($coupon !== '' ? $coupon : null)
            ) {
                return false;
            }

            if (! empty($product->trial) && (float) ($product->trial_amount ?? 0) <= 0.0) {
                return false;
            }

            return true;
        } catch (\Throwable $e) {
            // An unreadable product is not a reason to refuse a signup, and not
            // a reason to skip the check either — fall back to gating, which is
            // the behaviour for everything free.
            return false;
        }
    }

    private function gatesPaidSignups(): bool
    {
        return (bool) apply_filters(
            'maspik/memberpress_gate_paid_signups',
            get_option('maspik_memberpress_gate_paid') === '1'
        );
    }

    /** @return int|string the membership id, or a sentinel when absent */
    private function productId()
    {
        $post = isset($_POST) ? (array) wp_unslash($_POST) : [];
        $id = (int) self::scalar($post, 'mepr_product_id');

        return $id > 0 ? $id : 'mepr_signup';
    }

    private function couponCode(): string
    {
        $post = isset($_POST) ? (array) wp_unslash($_POST) : [];

        return sanitize_text_field(self::scalar($post, 'mepr_coupon_code'));
    }

    /**
     * One posted value as a string, or '' — guarded against array payloads such
     * as `user_email[]=x`, which would otherwise reach a string cast.
     *
     * @param array<string|int, mixed> $post
     */
    private static function scalar(array $post, string $key): string
    {
        return isset($post[$key]) && is_scalar($post[$key]) ? (string) $post[$key] : '';
    }
}
