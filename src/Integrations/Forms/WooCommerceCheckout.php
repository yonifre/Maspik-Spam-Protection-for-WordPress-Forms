<?php

declare(strict_types=1);

namespace Maspik\Integrations\Forms;

use Maspik\Application\SpamGate;
use Maspik\Domain\Model\FieldType;
use Maspik\Domain\Model\Submission;
use Maspik\Infrastructure\ClientIp;
use Maspik\Infrastructure\Settings\Settings;
use Maspik\Integrations\AbstractFormIntegration;
use Maspik\Integrations\Support\FieldMapper;
use Maspik\Integrations\Support\RawPayload;
use Maspik\Premium\ProGate;

/**
 * WooCommerce Checkout (Orders) spam check — Pro only, OFF by default.
 * Hook: woocommerce_after_checkout_validation ($data, $errors). Block by
 * $errors->add('maspik_spam', $message).
 *
 * A whitelist decides when to run: only for zero-total orders (when that toggle
 * is on) or for payment gateways the user explicitly selected. So nothing is
 * checked unless deliberately enabled — a deliberately safe default that needs
 * Settings + ProGate, unlike the other adapters.
 */
final class WooCommerceCheckout extends AbstractFormIntegration
{
    /** Free-text address lines worth scanning (deliberately no codes). */
    private const ADDRESS_FIELDS = [
        'billing_address_1', 'billing_address_2', 'billing_city',
        'shipping_first_name', 'shipping_last_name', 'shipping_company',
        'shipping_address_1', 'shipping_address_2', 'shipping_city',
    ];

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
            'tel' => FieldType::TEL,
            // Order notes — without this the field is silently dropped by
            // FieldMapper and never scanned.
            'textarea' => FieldType::TEXTAREA,
        ];
    }

    public function id(): string
    {
        return 'woocommerce_orders';
    }

    public function label(): string
    {
        return 'WooCommerce Checkout';
    }

    public function toggleKey(): string
    {
        return 'maspik_support_woocommerce_orders';
    }

    public function pro(): bool
    {
        return true;
    }

    /** Off until deliberately enabled — never interfere with checkout by default. */
    public function optIn(): bool
    {
        return true;
    }

    public function isAvailable(): bool
    {
        return class_exists('WooCommerce') || function_exists('WC');
    }

    public function register(SpamGate $gate): void
    {
        // Block (Store API) checkout — the default for new stores. It never
        // fires woocommerce_after_checkout_validation, so it needs its own hook.
        // This one runs after the order is built but before payment is taken,
        // and a RouteException aborts checkout with a message for the customer.
        add_action('woocommerce_store_api_checkout_order_processed', function ($order) use ($gate) {
            if (! $this->enabled() || ! is_object($order) || ! method_exists($order, 'get_total')) {
                return;
            }

            $total = (float) $order->get_total();
            $paymentMethod = (string) $order->get_payment_method();
            if (! $this->shouldRunCheck($total, $paymentMethod)) {
                return;
            }

            $verdict = $gate->evaluate($this->submissionFromOrder($order));
            if (! $verdict->isSpam) {
                return;
            }

            $custom = trim($this->settings->raw('maspik_woo_orders_error_message'));
            $message = $custom !== '' ? $custom : $gate->errorMessage($verdict);

            // WooCommerce has already created this order and set it to
            // "pending" by the time we get here, so it stays in the store's
            // order list. Leave a note explaining why, otherwise a merchant
            // finds an unpaid order with no explanation. We deliberately do not
            // change the status: both "failed" and "cancelled" send the
            // merchant an email, and WooCommerce's own hold-stock timeout
            // already clears unpaid pending orders.
            if (method_exists($order, 'add_order_note')) {
                $order->add_order_note(__('MASPIK blocked this checkout as spam', 'contact-forms-anti-spam'));
            }

            if (class_exists('\Automattic\WooCommerce\StoreApi\Exceptions\RouteException')) {
                throw new \Automattic\WooCommerce\StoreApi\Exceptions\RouteException(
                    'maspik_spam',
                    wp_strip_all_tags($message),
                    403
                );
            }
        }, 10, 1);

        add_action('woocommerce_after_checkout_validation', function ($data, $errors) use ($gate) {
            // Pro-only, and strictly opt-in (default off — must equal 'yes').
            if (! $this->proGate->supports('plugin')) {
                return;
            }
            if ($this->settings->raw('maspik_support_woocommerce_orders') !== 'yes') {
                return;
            }
            if (! function_exists('WC') || ! WC() || ! WC()->cart) {
                return;
            }
            if (! is_object($errors) || ! method_exists($errors, 'add')) {
                return;
            }

            $posted = is_array($data) ? $data : (is_array($_POST) ? $_POST : []);
            $paymentMethod = isset($posted['payment_method']) && is_string($posted['payment_method'])
                ? sanitize_text_field($posted['payment_method']) : '';

            $orderTotal = 0.0;
            try {
                $totalRaw = WC()->cart->get_total('raw');
                $orderTotal = is_numeric($totalRaw) ? (float) $totalRaw : 0.0;
            } catch (\Throwable $e) {
                $orderTotal = 0.0;
            }

            if (! $this->shouldRunCheck($orderTotal, $paymentMethod)) {
                return;
            }

            $raw = $this->checkedFields($posted);
            $verdict = $gate->evaluate($this->submissionFrom($raw, self::typeMap()));
            if (! $verdict->isSpam) {
                return;
            }

            $custom = trim($this->settings->raw('maspik_woo_orders_error_message'));
            $message = $custom !== '' ? $custom : $gate->errorMessage($verdict);
            $errors->add('maspik_spam', $message);
        }, 10, 2);
    }

    /** Pro-only, and strictly opt-in — the toggle must be an explicit 'yes'. */
    private function enabled(): bool
    {
        return $this->proGate->supports('plugin')
            && $this->settings->raw('maspik_support_woocommerce_orders') === 'yes';
    }

    /**
     * Build a Submission from a finished Store API order.
     *
     * The source is 'woocommerce_checkout_block' on purpose: GuardPolicy skips
     * the honeypot and verification key for it, because the Store API posts
     * JSON and never carries those fields — checking them would block every
     * single block-checkout order.
     */
    private function submissionFromOrder($order): Submission
    {
        $get = static function ($order, string $method): string {
            return method_exists($order, $method) ? trim((string) $order->{$method}()) : '';
        };

        $raw = [];
        $name = trim(implode(' ', array_filter([
            $get($order, 'get_billing_first_name'),
            $get($order, 'get_billing_last_name'),
            $get($order, 'get_billing_company'),
        ], 'strlen')));
        if ($name !== '') {
            $raw[] = ['name' => 'billing_name', 'type' => 'text', 'value' => $name];
        }
        // Must stay in step with checkedFields()/ADDRESS_FIELDS on the classic
        // path: the two are the same feature, and a field scanned on one
        // checkout but not the other means spam is blocked or let through
        // depending on which checkout the store happens to use.
        foreach ([
            'billing_email' => ['get_billing_email', 'email'],
            'billing_phone' => ['get_billing_phone', 'tel'],
            'order_comments' => ['get_customer_note', 'textarea'],
            'billing_address_1' => ['get_billing_address_1', 'text'],
            'billing_address_2' => ['get_billing_address_2', 'text'],
            'billing_city' => ['get_billing_city', 'text'],
            'shipping_first_name' => ['get_shipping_first_name', 'text'],
            'shipping_last_name' => ['get_shipping_last_name', 'text'],
            'shipping_company' => ['get_shipping_company', 'text'],
            'shipping_address_1' => ['get_shipping_address_1', 'text'],
            'shipping_address_2' => ['get_shipping_address_2', 'text'],
            'shipping_city' => ['get_shipping_city', 'text'],
        ] as $field => [$method, $type]) {
            $value = $get($order, $method);
            if ($value !== '') {
                $raw[] = ['name' => $field, 'type' => $type, 'value' => $value];
            }
        }

        $ip = $get($order, 'get_customer_ip_address');

        return new Submission(
            FieldMapper::map($raw, self::typeMap()),
            'woocommerce_checkout_block',
            'WooCommerce Checkout',
            $ip !== '' ? $ip : $this->clientIp->get(),
            [],
            isset($_SERVER['HTTP_REFERER']) ? esc_url_raw(wp_unslash($_SERVER['HTTP_REFERER'])) : null,
            RawPayload::fromList($raw)
        );
    }

    /**
     * Run the check only when the order matches the user's whitelist: a
     * zero-total order (with that toggle on) OR a selected payment gateway.
     */
    private function shouldRunCheck(float $orderTotal, string $paymentMethod): bool
    {
        $checkZeroTotal = $this->settings->bool('maspik_woo_orders_check_zero_total');
        $gatewaySelected = in_array($paymentMethod, $this->gatewaysToCheck(), true);
        $isZeroTotal = $orderTotal <= 0;

        return ($isZeroTotal && $checkZeroTotal) || $gatewaySelected;
    }

    /**
     * The selected payment-gateway ids. Stored as a newline list or a JSON
     * array, so both are accepted.
     *
     * @return string[]
     */
    private function gatewaysToCheck(): array
    {
        $raw = trim($this->settings->raw('maspik_woo_orders_gateways_to_check'));
        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return array_values(array_filter(array_map('trim', array_map('strval', $decoded))));
        }

        return $this->settings->list('maspik_woo_orders_gateways_to_check');
    }

    /**
     * The fields v2 blacklist-checks at checkout: the combined billing name
     * (first + last + company), billing email, billing phone, the order notes,
     * and the free-text address lines.
     *
     * @param array<string, mixed> $posted
     * @return array<int, array{name: string, type: string, value: string}>
     */
    private function checkedFields(array $posted): array
    {
        $raw = [];

        $name = trim(implode(' ', array_filter([
            $this->str($posted, 'billing_first_name'),
            $this->str($posted, 'billing_last_name'),
            $this->str($posted, 'billing_company'),
        ], 'strlen')));
        if ($name !== '') {
            $raw[] = ['name' => 'billing_name', 'type' => 'text', 'value' => $name];
        }

        $email = isset($posted['billing_email']) && is_string($posted['billing_email'])
            ? sanitize_email($posted['billing_email']) : '';
        if ($email !== '') {
            $raw[] = ['name' => 'billing_email', 'type' => 'email', 'value' => $email];
        }

        $phone = $this->str($posted, 'billing_phone');
        if ($phone !== '') {
            $raw[] = ['name' => 'billing_phone', 'type' => 'tel', 'value' => $phone];
        }

        // Order notes are the checkout's free-text field — where a spammer
        // actually writes their message — so they get the textarea treatment.
        $notes = isset($posted['order_comments']) && is_string($posted['order_comments'])
            ? sanitize_textarea_field($posted['order_comments']) : '';
        if ($notes !== '') {
            $raw[] = ['name' => 'order_comments', 'type' => 'textarea', 'value' => $notes];
        }

        // Address lines carry prose too, and spam links are routinely dropped
        // there. Codes (postcode, state, country) are skipped — they can't hold
        // a message and would only invite false positives.
        foreach (self::ADDRESS_FIELDS as $key) {
            $value = $this->str($posted, $key);
            if ($value !== '') {
                $raw[] = ['name' => $key, 'type' => 'text', 'value' => $value];
            }
        }

        return $raw;
    }

    /** @param array<string, mixed> $posted */
    private function str(array $posted, string $key): string
    {
        return isset($posted[$key]) && is_string($posted[$key]) ? sanitize_text_field($posted[$key]) : '';
    }
}
