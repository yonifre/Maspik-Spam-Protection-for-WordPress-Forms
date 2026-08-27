<?php

declare(strict_types=1);

namespace Maspik\Admin\Rest;

use Maspik\Premium\License;
use Maspik\Premium\ProGate;
use WP_REST_Request;
use WP_REST_Response;

/**
 * /maspik/v1/license — Pro status + key activation for the License screen.
 *
 * Activation stores the key and flips the Pro flag locally; verifying the key
 * against the ideologix/dlm server is the remaining Phase-4 step (License.php).
 */
final class LicenseController
{
    /** @var ProGate */
    private $pro;

    /** @var License */
    private $license;

    public function __construct(ProGate $pro, License $license)
    {
        $this->pro = $pro;
        $this->license = $license;
    }

    public function registerRoutes(): void
    {
        $can = static function (): bool {
            return current_user_can('manage_options');
        };

        register_rest_route('maspik/v1', '/license', [
            ['methods' => 'GET', 'callback' => [$this, 'index'], 'permission_callback' => $can],
            ['methods' => 'POST', 'callback' => [$this, 'activate'], 'permission_callback' => $can],
            ['methods' => 'DELETE', 'callback' => [$this, 'deactivate'], 'permission_callback' => $can],
        ]);
    }

    /**
     * Show only the last 4 characters: enough for a user to recognise which key
     * is stored, useless to anyone else. Short keys are fully masked.
     */
    private static function maskKey(string $key): string
    {
        $key = trim($key);
        if ($key === '') {
            return '';
        }
        $tail = mb_substr($key, -4);

        return mb_strlen($key) <= 4 ? str_repeat('•', mb_strlen($key)) : str_repeat('•', 8) . ' ' . $tail;
    }

    public function index(): WP_REST_Response
    {
        $active = $this->pro->supports('pro');
        $status = $this->license->status();

        return new WP_REST_Response([
            'active' => $active,
            'plan' => $active ? 'Pro' : 'Free',
            'has_key' => $this->license->key() !== '',
            // Masked server-side: the admin screen only ever needs to show that
            // a key is stored, never the key itself.
            'masked_key' => self::maskKey($this->license->key()),
            'state' => $status['state'],
            'status_message' => $status['message'],
            'expires_at' => $status['expires_at'],
            'checked_at' => $status['checked_at'],
            'dashboard_suggested' => $status['dashboard_suggested'],
            'features' => [
                // "Restrictions", not "blocking": the list can allow-only or block.
                ['id' => 'country_location', 'label' => 'Country & continent restrictions', 'included' => $this->pro->supports('country_location')],
                ['id' => 'language', 'label' => 'Language rules', 'included' => $this->pro->supports('country_location')],
                [
                    'id' => 'inputgate',
                    'label' => 'Unlimited InputGate Checks',
                    'hint' => 'Free users are limited to 100 checks per month. Pro users have unlimited checks.',
                    'included' => $active,
                ],
                ['id' => 'premium_integrations', 'label' => 'Premium form integrations (WPForms, Gravity Forms, WooCommerce)', 'included' => $active],
                ['id' => 'dashboard_sync', 'label' => 'Dashboard sync (manage rules across multiple websites)', 'included' => $active],
                ['id' => 'priority_support', 'label' => 'Priority support', 'included' => $active],
            ],
        ]);
    }

    public function activate(WP_REST_Request $request): WP_REST_Response
    {
        $params = (array) $request->get_json_params();
        $key = isset($params['key']) && is_string($params['key']) ? $params['key'] : '';

        $result = $this->license->activate($key);

        // Always 200: the SPA reads {ok, reason} from the body. A non-2xx would
        // make the fetch wrapper throw and swallow the reason.
        return new WP_REST_Response($result, 200);
    }

    public function deactivate(): WP_REST_Response
    {
        $this->license->deactivate();

        return new WP_REST_Response(['ok' => true]);
    }
}
