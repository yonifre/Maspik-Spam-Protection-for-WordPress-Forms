<?php

declare(strict_types=1);

namespace Maspik\Admin\Rest;

use Maspik\Application\CheckFactory;
use Maspik\Infrastructure\Logging\LogRepository;
use Maspik\Infrastructure\Settings\Settings;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Logs endpoints. Beyond listing, these power the inbox actions that let a
 * user recover a false positive: delete an entry, whitelist the sender's IP
 * or email, or mark a whole submission "not spam" (whitelist + delete).
 */
final class LogsController
{
    /** @var LogRepository */
    private $logs;

    /** @var Settings */
    private $settings;

    /** @var CheckFactory */
    private $checkFactory;

    public function __construct(LogRepository $logs, Settings $settings, CheckFactory $checkFactory)
    {
        $this->logs = $logs;
        $this->settings = $settings;
        $this->checkFactory = $checkFactory;
    }

    public function registerRoutes(): void
    {
        $can = static function (): bool {
            return current_user_can('manage_options');
        };

        register_rest_route('maspik/v1', '/logs', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'index'],
                'permission_callback' => $can,
                'args' => [
                    'page' => ['type' => 'integer', 'default' => 1, 'minimum' => 1],
                    'per_page' => ['type' => 'integer', 'default' => 50, 'minimum' => 1, 'maximum' => 200],
                    'outcome' => ['type' => 'string', 'default' => 'all', 'enum' => ['all', 'blocked', 'clean']],
                ],
            ],
            [
                'methods' => 'DELETE',
                'callback' => [$this, 'deleteAll'],
                'permission_callback' => $can,
                'args' => [
                    'outcome' => ['type' => 'string', 'default' => 'all', 'enum' => ['all', 'blocked', 'clean']],
                ],
            ],
        ]);

        register_rest_route('maspik/v1', '/logs/export', [
            'methods' => 'GET',
            'callback' => [$this, 'export'],
            'permission_callback' => $can,
            'args' => [
                'outcome' => ['type' => 'string', 'default' => 'all', 'enum' => ['all', 'blocked', 'clean']],
            ],
        ]);
        register_rest_route('maspik/v1', '/logs/(?P<id>\d+)', [
            'methods' => 'DELETE',
            'callback' => [$this, 'delete'],
            'permission_callback' => $can,
        ]);

        register_rest_route('maspik/v1', '/logs/(?P<id>\d+)/not-spam', [
            'methods' => 'POST',
            'callback' => [$this, 'notSpam'],
            'permission_callback' => $can,
        ]);

        register_rest_route('maspik/v1', '/logs/(?P<id>\d+)/mark-spam', [
            'methods' => 'POST',
            'callback' => [$this, 'markSpam'],
            'permission_callback' => $can,
        ]);

        register_rest_route('maspik/v1', '/logs/whitelist', [
            'methods' => 'POST',
            'callback' => [$this, 'whitelist'],
            'permission_callback' => $can,
        ]);

        register_rest_route('maspik/v1', '/logs/unblock', [
            'methods' => 'POST',
            'callback' => [$this, 'unblock'],
            'permission_callback' => $can,
        ]);
    }

    /**
     * Undo the rule that caused a block: remove a value from one of the newline
     * blocklists. Body: { key: string, value: string }. Only the removable
     * blocklists are allowed (not structured settings like country rules).
     */
    public function unblock(WP_REST_Request $request): WP_REST_Response
    {
        // key => storage separator. Newline lists, plus the space-separated
        // country list. Structured settings (phone formats, allow-mode country)
        // are edited from the settings screen instead, not removed here.
        $allowed = [
            'text_blacklist' => "\n",
            'emails_blacklist' => "\n",
            'url_blacklist' => "\n",
            'ip_blacklist' => "\n",
            'lang_forbidden' => "\n",
            'country_blacklist' => ' ',
        ];
        $params = (array) $request->get_json_params();
        $key = isset($params['key']) ? (string) $params['key'] : '';
        $value = isset($params['value']) ? trim((string) $params['value']) : '';

        if (! isset($allowed[$key])) {
            return new WP_REST_Response(['ok' => false, 'reason' => 'invalid_key'], 400);
        }
        if ($value === '') {
            return new WP_REST_Response(['ok' => false, 'reason' => 'missing_value'], 400);
        }

        $removed = $this->settings->removeFromList($key, $value, $allowed[$key]);

        return new WP_REST_Response(['ok' => true, 'removed' => $removed, 'key' => $key, 'value' => $value]);
    }

    public function index(WP_REST_Request $request): WP_REST_Response
    {
        $perPage = (int) $request['per_page'];
        $page = (int) $request['page'];
        $outcome = in_array($request['outcome'], ['blocked', 'clean'], true) ? (string) $request['outcome'] : 'all';

        $entries = $this->logs->recent($perPage, ($page - 1) * $perPage, $outcome);
        $total = $this->logs->countStored($outcome);

        return new WP_REST_Response([
            'total_blocked' => $this->logs->totalBlocked(),
            'entries' => $entries,
            // How many rows exist for this outcome, and whether asking for the
            // next page would return anything. Without these the screen could
            // only report what it had already loaded — a site with 53 entries
            // showed "50 logged" and no way to reach the other three.
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'has_more' => $page * $perPage < $total,
            // Fallback ordering for legacy rows that predate the stored trace.
            'pipeline_order' => $this->checkFactory->pipelineOrder(),
        ]);
    }

    /**
     * Turn a passed submission into a rule: blacklist its email, IP, or a phrase
     * the user supplies. Body: { type: 'email'|'ip'|'phrase', value: string }.
     */
    public function markSpam(WP_REST_Request $request): WP_REST_Response
    {
        $params = (array) $request->get_json_params();
        $type = isset($params['type']) ? (string) $params['type'] : '';
        $value = isset($params['value']) ? trim((string) $params['value']) : '';
        if ($value === '') {
            return new WP_REST_Response(['ok' => false, 'reason' => 'missing_value'], 400);
        }

        switch ($type) {
            case 'email':
                if (! filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    return new WP_REST_Response(['ok' => false, 'reason' => 'invalid_email'], 400);
                }
                $added = $this->settings->appendToList('emails_blacklist', strtolower($value));
                break;
            case 'ip':
                if (! filter_var($value, FILTER_VALIDATE_IP)) {
                    return new WP_REST_Response(['ok' => false, 'reason' => 'invalid_ip'], 400);
                }
                $added = $this->settings->appendToList('ip_blacklist', $value);
                break;
            case 'phrase':
                $added = $this->settings->appendToList('text_blacklist', $value);
                break;
            default:
                return new WP_REST_Response(['ok' => false, 'reason' => 'invalid_type'], 400);
        }

        // Remove the now-classified row from the passed list.
        $this->logs->delete((int) $request['id']);

        return new WP_REST_Response(['ok' => true, 'added' => $added, 'type' => $type, 'value' => $value]);
    }

    public function delete(WP_REST_Request $request): WP_REST_Response
    {
        $deleted = $this->logs->delete((int) $request['id']);

        return new WP_REST_Response(['deleted' => $deleted]);
    }

    /**
     * Empty the log for one outcome. Reviewing a large log ends in clearing it,
     * and deleting hundreds of rows one at a time is not a workflow.
     *
     * Scoped to the same outcome the list and the CSV export use, so the button
     * removes what the screen is showing and never more. The row count comes
     * back so the confirmation can say what actually happened.
     */
    public function deleteAll(WP_REST_Request $request): WP_REST_Response
    {
        $outcome = in_array($request['outcome'], ['blocked', 'clean'], true) ? (string) $request['outcome'] : 'all';

        return new WP_REST_Response(['deleted' => $this->logs->deleteAll($outcome), 'outcome' => $outcome]);
    }

    /**
     * Add an IP and/or email to the allow list. Body: { ip?: string, email?: string }.
     */
    public function whitelist(WP_REST_Request $request): WP_REST_Response
    {
        $params = (array) $request->get_json_params();
        $added = [];

        if (! empty($params['ip']) && filter_var($params['ip'], FILTER_VALIDATE_IP)) {
            if ($this->settings->appendToList('ip_whitelist', (string) $params['ip'])) {
                $added['ip'] = $params['ip'];
            }
        }
        if (! empty($params['email']) && filter_var($params['email'], FILTER_VALIDATE_EMAIL)) {
            if ($this->settings->appendToList('emails_whitelist', strtolower((string) $params['email']))) {
                $added['email'] = strtolower((string) $params['email']);
            }
        }

        return new WP_REST_Response(['whitelisted' => $added]);
    }

    /**
     * "Not spam": whitelist the submission's sender (email if present, else IP)
     * and delete the log entry. One atomic recovery action.
     */
    public function notSpam(WP_REST_Request $request): WP_REST_Response
    {
        $id = (int) $request['id'];
        $row = $this->logs->find($id);
        if ($row === null) {
            return new WP_REST_Response(['error' => 'not_found'], 404);
        }

        $whitelisted = [];
        $email = $this->extractEmail($row);
        if ($email !== null && $this->settings->appendToList('emails_whitelist', $email)) {
            $whitelisted['email'] = $email;
        }
        if ($whitelisted === [] && ! empty($row['spam_ip']) && filter_var($row['spam_ip'], FILTER_VALIDATE_IP)) {
            if ($this->settings->appendToList('ip_whitelist', (string) $row['spam_ip'])) {
                $whitelisted['ip'] = $row['spam_ip'];
            }
        }

        // Keep the submission. Marking a false positive used to delete it,
        // which threw away the only copy of a real customer's details at the
        // exact moment the owner discovered they had been wrongly turned away.
        $this->logs->markNotSpam($id);

        return new WP_REST_Response(['whitelisted' => $whitelisted, 'kept' => true]);
    }

    /** Rows per query while building the file, so memory stays flat. */
    private const EXPORT_BATCH = 500;

    /** Hard ceiling, in case a site has raised its retention limit a long way. */
    private const EXPORT_MAX_ROWS = 50000;

    /**
     * The spam log as CSV.
     *
     * Returns { filename, content } like the settings export, so the admin reuses
     * the same download path. Rows are read in batches through the repository,
     * which means v2's serialized rows are normalised on the way out and export
     * with their fields intact, exactly as they now display.
     */
    public function export(WP_REST_Request $request): WP_REST_Response
    {
        $outcome = in_array($request['outcome'], ['blocked', 'clean'], true) ? (string) $request['outcome'] : 'all';

        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            return new WP_REST_Response(['ok' => false, 'reason' => 'no_buffer'], 500);
        }

        // The empty escape argument matters. PHP's default is a backslash, which
        // is not RFC-4180 and makes any value containing \" break out of its
        // quotes — splitting that row into extra columns in Excel and in every
        // standard parser. Spam bodies are full of backslashes, so this is the
        // difference between a clean file and a silently mangled one.
        self::putRow($handle, [
            __('Date', 'contact-forms-anti-spam'),
            __('Outcome', 'contact-forms-anti-spam'),
            __('Form', 'contact-forms-anti-spam'),
            __('Page URL', 'contact-forms-anti-spam'),
            __('Blocked by', 'contact-forms-anti-spam'),
            __('Rule', 'contact-forms-anti-spam'),
            __('Reason', 'contact-forms-anti-spam'),
            __('Matched value', 'contact-forms-anti-spam'),
            __('IP', 'contact-forms-anti-spam'),
            __('Country', 'contact-forms-anti-spam'),
            __('User agent', 'contact-forms-anti-spam'),
            __('Submitted fields', 'contact-forms-anti-spam'),
        ]);

        $written = 0;
        for ($offset = 0; $offset < self::EXPORT_MAX_ROWS; $offset += self::EXPORT_BATCH) {
            $rows = $this->logs->recent(self::EXPORT_BATCH, $offset, $outcome);
            if ($rows === []) {
                break;
            }
            foreach ($rows as $row) {
                self::putRow($handle, self::exportRow($row));
                $written++;
            }
        }

        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        return new WP_REST_Response([
            'filename' => 'maspik-spam-log-' . gmdate('Y-m-d') . '.csv',
            // Excel reads a CSV as the system's legacy encoding unless the file
            // announces UTF-8, which turns every Hebrew, Russian or Chinese
            // submission into mojibake. The BOM is what makes it open correctly.
            'content' => "\xEF\xBB\xBF" . $csv,
            'rows' => $written,
        ]);
    }

    /**
     * @param resource $handle
     * @param array<int, string> $row
     */
    private static function putRow($handle, array $row): void
    {
        fputcsv($handle, $row, ',', '"', '');
    }

    /**
     * @param array<string, mixed> $row
     * @return array<int, string>
     */
    private static function exportRow(array $row): array
    {
        // v2 packed the page URL into spam_source as "Label|||URL".
        $source = explode('|||', (string) ($row['spam_source'] ?? ''));
        $detail = json_decode((string) ($row['spam_detail'] ?? ''), true);
        $fields = is_array($detail) && isset($detail['fields']) && is_array($detail['fields']) ? $detail['fields'] : [];
        $url = is_array($detail) && ! empty($detail['url']) ? (string) $detail['url'] : (string) ($source[1] ?? '');

        $pairs = [];
        foreach ($fields as $name => $value) {
            $pairs[] = $name . ': ' . (is_scalar($value) ? (string) $value : '');
        }

        return [
            (string) ($row['spam_date'] ?? ''),
            ($row['spam_tag'] ?? '') === 'clean' ? 'passed' : 'blocked',
            (string) ($source[0] ?? ''),
            $url,
            (string) ($row['spam_type'] ?? ''),
            self::plain((string) ($row['spamsrc_label'] ?? '')),
            self::plain((string) ($row['spamsrc_val'] ?? '')),
            (string) ($row['spam_value'] ?? ''),
            (string) ($row['spam_ip'] ?? ''),
            (string) ($row['spam_country'] ?? ''),
            (string) ($row['spam_agent'] ?? ''),
            implode("\n", $pairs),
        ];
    }

    /** Reasons carry *!…!* highlight markers for the admin; strip them for a file. */
    private static function plain(string $text): string
    {
        return (string) preg_replace('/\*!(.*?)!\*/', '$1', $text);
    }

    /** Find a valid email among the stored submission fields. */
    private function extractEmail(array $row): ?string
    {
        $detail = json_decode((string) ($row['spam_detail'] ?? ''), true);
        $fields = is_array($detail) && isset($detail['fields']) && is_array($detail['fields'])
            ? $detail['fields']
            : (is_array($detail) ? $detail : []);

        foreach ($fields as $value) {
            if (is_string($value) && filter_var(trim($value), FILTER_VALIDATE_EMAIL)) {
                return strtolower(trim($value));
            }
        }

        return null;
    }
}
