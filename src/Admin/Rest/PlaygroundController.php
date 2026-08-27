<?php

declare(strict_types=1);

namespace Maspik\Admin\Rest;

use Maspik\Application\Playground;
use Maspik\Infrastructure\ClientIp;
use WP_REST_Request;
use WP_REST_Response;

final class PlaygroundController
{
    /** @var Playground */
    private $playground;

    /** @var ClientIp */
    private $clientIp;

    public function __construct(Playground $playground, ClientIp $clientIp)
    {
        $this->playground = $playground;
        $this->clientIp = $clientIp;
    }

    public function registerRoutes(): void
    {
        register_rest_route('maspik/v1', '/playground', [
            'methods' => 'POST',
            'callback' => [$this, 'simulate'],
            'permission_callback' => static fn (): bool => current_user_can('manage_options'),
        ]);
    }

    public function simulate(WP_REST_Request $request): WP_REST_Response
    {
        $params = (array) $request->get_json_params();

        $input = [];
        foreach (['name', 'email', 'tel', 'url', 'message'] as $key) {
            if (isset($params[$key]) && is_string($params[$key])) {
                $input[$key] = sanitize_textarea_field($params[$key]);
            }
        }

        $ip = isset($params['ip']) && is_string($params['ip']) && filter_var($params['ip'], FILTER_VALIDATE_IP)
            ? $params['ip']
            : $this->clientIp->get();

        return new WP_REST_Response($this->playground->simulate($input, $ip));
    }
}
