<?php

declare(strict_types=1);

namespace Maspik\Admin\Rest;

use Maspik\Infrastructure\Settings\Settings;
use Maspik\Integrations\Registry;
use WP_REST_Request;
use WP_REST_Response;

/**
 * GET  /maspik/v1/integrations         list every form integration + status
 * PATCH /maspik/v1/integrations        { toggleKey: 'yes'|'no' } enable/disable
 *
 * The valid toggle keys come from the registry itself (each FormIntegration
 * declares its own key) — so only real integrations can be toggled, without
 * hardcoding the 22 support keys into the settings schema.
 */
final class IntegrationsController
{
    /** @var Registry */
    private $registry;

    /** @var Settings */
    private $settings;

    public function __construct(Registry $registry, Settings $settings)
    {
        $this->registry = $registry;
        $this->settings = $settings;
    }

    public function registerRoutes(): void
    {
        register_rest_route('maspik/v1', '/integrations', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'index'],
                'permission_callback' => [$this, 'can'],
            ],
            [
                'methods' => 'PATCH',
                'callback' => [$this, 'update'],
                'permission_callback' => [$this, 'can'],
            ],
        ]);
    }

    public function can(): bool
    {
        return current_user_can('manage_options');
    }

    public function index(): WP_REST_Response
    {
        return new WP_REST_Response([
            'integrations' => $this->registry->describe(),
            // Powers the WooCommerce checkout gateway picker; empty when Woo
            // isn't active, so the UI can hide that panel entirely.
            'woo_gateways' => $this->wooGateways(),
        ]);
    }

    /**
     * The site's available WooCommerce payment gateways, as
     * [{ id: 'cod', label: 'Cash on delivery' }] — the real ids the checkout
     * posts, so the whitelist matches what WooCommerce actually sends.
     *
     * @return array<int, array{id: string, label: string}>
     */
    private function wooGateways(): array
    {
        if (! function_exists('WC') || ! WC() || ! isset(WC()->payment_gateways)) {
            return [];
        }

        $gateways = WC()->payment_gateways->payment_gateways();
        if (! is_array($gateways)) {
            return [];
        }

        $out = [];
        foreach ($gateways as $gateway) {
            if (! is_object($gateway) || ! isset($gateway->id)) {
                continue;
            }
            $title = isset($gateway->method_title) && $gateway->method_title !== ''
                ? $gateway->method_title
                : (isset($gateway->title) ? $gateway->title : $gateway->id);
            $out[] = ['id' => (string) $gateway->id, 'label' => wp_strip_all_tags((string) $title)];
        }

        return $out;
    }

    public function update(WP_REST_Request $request): WP_REST_Response
    {
        $allowed = $this->registry->toggleKeys();
        $updated = [];

        foreach ((array) $request->get_json_params() as $key => $value) {
            if (! in_array($key, $allowed, true)) {
                continue;
            }
            $this->settings->save((string) $key, $value ? 'yes' : 'no');
            $updated[] = $key;
        }

        return new WP_REST_Response(['updated' => $updated]);
    }
}
