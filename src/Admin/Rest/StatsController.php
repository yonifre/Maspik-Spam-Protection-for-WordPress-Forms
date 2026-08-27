<?php

declare(strict_types=1);

namespace Maspik\Admin\Rest;

use Maspik\Infrastructure\Logging\LogRepository;
use Maspik\Premium\ProGate;
use WP_REST_Response;

/**
 * GET /maspik/v1/stats — aggregates powering Dashboard, Protection layer
 * cards ("Blocked 142 submissions", "Last blocked 3 minutes ago"), Analytics
 * charts, and the Matrix cloud-check usage meter. One round-trip per page load.
 */
final class StatsController
{
    /** @var LogRepository */
    private $logs;

    /** @var ProGate */
    private $pro;

    public function __construct(LogRepository $logs, ProGate $pro)
    {
        $this->logs = $logs;
        $this->pro = $pro;
    }

    public function registerRoutes(): void
    {
        register_rest_route('maspik/v1', '/stats', [
            'methods' => 'GET',
            'callback' => [$this, 'index'],
            'permission_callback' => static function (): bool {
                return current_user_can('manage_options');
            },
        ]);
    }

    public function index(): WP_REST_Response
    {
        $byDay = $this->logs->countsByDay(30);
        $thisMonth = 0;
        $monthPrefix = current_time('Y-m');
        foreach ($byDay as $row) {
            if (strpos((string) $row['day'], $monthPrefix) === 0) {
                $thisMonth += (int) $row['count'];
            }
        }

        return new WP_REST_Response([
            'total_blocked' => $this->logs->totalBlocked(),
            'this_month' => $thisMonth,
            'by_type' => $this->logs->countsByType(),
            'by_source' => $this->logs->countsBySource(),
            'by_day' => $byDay,
            'last_blocked' => $this->logs->lastBlocked(),
            'matrix' => $this->matrixUsage(),
            'now' => current_time('mysql'),
        ]);
    }

    /**
     * Maspik Matrix cloud-check usage for the current month.
     * Free plans get a monthly quota; Pro is unlimited.
     *
     * @return array{pro: bool, limit: int|null, used: int, remaining: int|null}
     */
    private function matrixUsage(): array
    {
        $pro = $this->pro->supports('pro');
        $used = (int) get_option('maspik_matrix_used_' . current_time('Y-m'), 0);
        $limit = $pro ? null : (int) apply_filters('maspik/matrix_monthly_limit', 100);

        return [
            'pro' => $pro,
            'limit' => $limit,
            'used' => $used,
            'remaining' => $limit === null ? null : max(0, $limit - $used),
        ];
    }
}
