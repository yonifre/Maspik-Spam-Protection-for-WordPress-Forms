<?php

declare(strict_types=1);

namespace Maspik\Infrastructure\Feedback;

if (! defined('ABSPATH')) {
    exit;
}


/**
 * Reports a blocked submission the site owner has told us was not spam.
 *
 * This is the only correction the engine ever receives from a human. Every
 * other signal is a guess about a submission; this one is a person looking at a
 * real message from a real customer and saying it should have gone through.
 * Nothing else can distinguish a rule that is catching spam from a rule that is
 * turning away business.
 *
 * Endpoint and payload are v2's, unchanged, so reports from both versions land
 * in the same table and remain comparable.
 *
 * SENT ONLY WHEN ASKED
 *
 * The report carries the log row, which holds what the visitor wrote. That is
 * someone else's data, so it leaves the site only when the owner ticks the box
 * on that particular correction — never as a side effect of marking something
 * not spam, and never in bulk. Same rule v2 applied.
 *
 * Never throws and never blocks: a correction that fails to report is still a
 * correction, and the owner has already been told it was applied.
 */
final class FalsePositiveReporter
{
    private const ENDPOINT = 'https://ipapi.wpmaspik.com/report';

    /** Short: nobody should wait on this to see their own correction applied. */
    private const TIMEOUT = 5;

    /**
     * @param array<string, mixed> $row the stored log row, as the admin sees it
     * @return array{sent: bool, error: string}
     */
    public function report(array $row, string $action = 'not_spam'): array
    {
        try {
            $body = wp_json_encode($this->payload($row, $action));
            if (! is_string($body)) {
                return ['sent' => false, 'error' => 'JSON encoding failed'];
            }

            $response = wp_remote_post(self::ENDPOINT, [
                'headers' => [
                    'Content-Type' => 'application/json',
                    // v2 sent this alongside the body flag; the receiver reads
                    // it, so it stays.
                    'plugin_report' => 'true',
                ],
                'body' => $body,
                'timeout' => self::TIMEOUT,
                'sslverify' => true,
            ]);

            if (is_wp_error($response)) {
                return ['sent' => false, 'error' => $response->get_error_message()];
            }

            $code = (int) wp_remote_retrieve_response_code($response);
            if ($code >= 200 && $code < 300) {
                return ['sent' => true, 'error' => ''];
            }

            return [
                'sent' => false,
                'error' => 'HTTP ' . $code . substr((string) wp_remote_retrieve_body($response), 0, 200),
            ];
        } catch (\Throwable $e) {
            // A failed report must never cost the owner the correction itself.
            return ['sent' => false, 'error' => 'Exception: ' . $e->getMessage()];
        }
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function payload(array $row, string $action): array
    {
        return [
            'plugin_report' => true,
            'site_url' => home_url(),
            'server_ip' => $this->serverIp(),
            'server_host' => isset($_SERVER['HTTP_HOST'])
                ? sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST'])) : '',
            'wp_version' => get_bloginfo('version'),
            'plugin_version' => defined('MASPIK_VERSION') ? MASPIK_VERSION : '',
            'marked_at' => current_time('mysql'),
            'action' => $action,
            'log_entry' => $row,
        ];
    }

    /**
     * The site's own address, so a report can be attributed to a host rather
     * than only to a URL. Resolved from the request when the server exposes it,
     * otherwise looked up from the site's own hostname.
     */
    private function serverIp(): string
    {
        if (! empty($_SERVER['SERVER_ADDR'])) {
            return sanitize_text_field(wp_unslash($_SERVER['SERVER_ADDR']));
        }

        if (! function_exists('gethostbyname')) {
            return '';
        }

        $host = wp_parse_url(home_url(), PHP_URL_HOST);
        if (! is_string($host) || $host === '') {
            return '';
        }

        $ip = gethostbyname($host);

        return $ip === $host ? '' : $ip;
    }
}
