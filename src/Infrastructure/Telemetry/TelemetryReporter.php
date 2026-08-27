<?php

declare(strict_types=1);

namespace Maspik\Infrastructure\Telemetry;

use Maspik\Infrastructure\Settings\Settings;

/**
 * Sends the telemetry payload, weekly, and only when the site has opted in.
 *
 * Three rules this class keeps:
 *
 * 1. Nothing is sent unless `maspik_share_data` is on. The check runs at send
 *    time, not at scheduling time, so switching the toggle off stops the next
 *    report even though the cron event is already booked.
 * 2. It never blocks a page load. The work happens on a cron event, and the
 *    request is short-timeout and non-blocking, so a slow or dead receiver
 *    costs a visitor nothing.
 * 3. It fails silently. Telemetry is a favour the site owner is doing us; it
 *    must never surface an error on their screen or in their log.
 */
final class TelemetryReporter
{
    public const CRON_HOOK = 'maspik_send_telemetry';
    private const LAST_SENT_OPTION = 'maspik_telemetry_last_sent';

    /**
     * Default receiver. Override per-site with the MASPIK_TELEMETRY_URL
     * constant, or globally with the `maspik/telemetry_endpoint` filter — which
     * is also the switch for pointing a staging install at a test worker.
     */
    private const DEFAULT_ENDPOINT = 'https://rec.wpmaspik.com/v1/telemetry';

    /** @var Settings */
    private $settings;

    /** @var TelemetryCollector */
    private $collector;

    public function __construct(Settings $settings, TelemetryCollector $collector)
    {
        $this->settings = $settings;
        $this->collector = $collector;
    }

    public function register(): void
    {
        add_action(self::CRON_HOOK, [$this, 'send']);

        // Schedule lazily: only once someone has actually opted in, so a site
        // that never opts in carries no cron event at all.
        add_action('init', [$this, 'maybeSchedule'], 20);
    }

    public function maybeSchedule(): void
    {
        $scheduled = wp_next_scheduled(self::CRON_HOOK);

        if (! $this->optedIn()) {
            if ($scheduled) {
                wp_unschedule_event((int) $scheduled, self::CRON_HOOK);
            }

            return;
        }

        if (! $scheduled) {
            // A random offset inside the first day keeps thousands of installs
            // from reporting in the same second.
            wp_schedule_event(time() + wp_rand(0, DAY_IN_SECONDS), 'weekly', self::CRON_HOOK);
        }
    }

    /**
     * Never throws, never warns, never blocks.
     *
     * The whole body is wrapped because telemetry is a favour the site owner is
     * doing us: a broken receiver, an option holding an unexpected type, or a
     * transport that raises must cost them nothing. A site must never see a
     * fatal, a notice, or a slow page because it agreed to share statistics.
     * Errors are swallowed on purpose - there is nothing here the site could
     * act on, and surfacing it would only alarm someone who did us a favour.
     */
    public function send(): void
    {
        try {
            if (! $this->optedIn()) {
                return;
            }

            $payload = $this->collector->collect();

            // json_encode returns false on malformed UTF-8, which a plugin or
            // theme name can absolutely contain.
            $body = wp_json_encode($payload);
            if (! is_string($body) || $body === '') {
                return;
            }

            $endpoint = $this->endpoint();
            if ($endpoint === '') {
                return;
            }

            $response = wp_remote_post($endpoint, [
                'timeout' => 5,
                // Fire and forget: the response tells us nothing the site can act on.
                'blocking' => false,
                'headers' => [
                    'Content-Type' => 'application/json',
                    'X-Maspik-Schema' => (string) TelemetryCollector::SCHEMA_VERSION,
                ],
                'body' => $body,
            ]);

            // A WP_Error here is normal (DNS down, firewall, offline site).
            // Recording the attempt anyway keeps a dead receiver from making
            // every cron run retry the same work.
            unset($response);

            update_option(self::LAST_SENT_OPTION, gmdate('c'), false);
        } catch (\Throwable $e) {
            // Deliberately silent. See the docblock.
        }
    }

    /** The payload as it would be sent — used by the admin "see my data" view. */
    public function preview(): array
    {
        return $this->collector->collect();
    }

    private function optedIn(): bool
    {
        return $this->settings->bool('maspik_share_data');
    }

    /**
     * The receiver URL, or '' when nothing usable is configured.
     *
     * Both the constant and the filter are set by people, and a filter that
     * returns an array or an object would raise "Array to string conversion"
     * on the way to the cast. Anything that is not a plain http(s) URL is
     * discarded rather than coerced, and send() then does nothing at all.
     */
    private function endpoint(): string
    {
        $url = self::DEFAULT_ENDPOINT;

        if (defined('MASPIK_TELEMETRY_URL') && is_string(MASPIK_TELEMETRY_URL)) {
            $url = MASPIK_TELEMETRY_URL;
        }

        /**
         * The telemetry receiver URL.
         *
         * @param string $url
         */
        $filtered = apply_filters('maspik/telemetry_endpoint', $url);
        if (is_string($filtered)) {
            $url = $filtered;
        }

        $url = trim($url);

        return preg_match('#^https?://#i', $url) === 1 ? $url : '';
    }
}
