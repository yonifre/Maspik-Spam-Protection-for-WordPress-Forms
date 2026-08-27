<?php

declare(strict_types=1);

namespace Maspik\Infrastructure\Reputation;

use Maspik\Infrastructure\Logging\LayerStatus;

/**
 * External IP-reputation lookups — AbuseIPDB and Proxycheck.io — using the same
 * endpoints v2 called. Each returns an int score or null on any failure so the
 * check stays fail-open.
 *
 * Scores are transient-cached per IP (short TTL: reputation changes faster than
 * geo, so it is cached separately from FreeIpApiResolver).
 */
final class IpReputationResolver
{
    private const CACHE_PREFIX = 'maspik_rep_';
    private const CACHE_TTL = HOUR_IN_SECONDS;

    /** @return callable(string): ?int */
    public function abuseipdb(string $apiKey): callable
    {
        return function (string $ip) use ($apiKey): ?int {
            return $this->lookupAbuseipdb($ip, $apiKey);
        };
    }

    /** @return callable(string): ?int */
    public function proxycheck(string $apiKey): callable
    {
        return function (string $ip) use ($apiKey): ?int {
            return $this->lookupProxycheck($ip, $apiKey);
        };
    }

    private function lookupAbuseipdb(string $ip, string $apiKey): ?int
    {
        if ($apiKey === '' || ! filter_var($ip, FILTER_VALIDATE_IP)) {
            return null;
        }
        if (! $this->isPublic($ip)) {
            LayerStatus::record('abuseipdb_api', LayerStatus::SKIPPED, 'Private IP');

            return null;
        }

        $cached = $this->cacheGet('abuse', $ip);
        if ($cached !== null) {
            return $cached;
        }

        $response = wp_remote_get(
            'https://api.abuseipdb.com/api/v2/check?ipAddress=' . rawurlencode($ip) . '&maxAgeInDays=90',
            ['timeout' => 20, 'headers' => ['Key' => $apiKey, 'Accept' => 'application/json']]
        );
        if (is_wp_error($response)) {
            LayerStatus::record('abuseipdb_api', LayerStatus::TIMEOUT, 'AbuseIPDB unavailable');

            return null;
        }
        if ((int) wp_remote_retrieve_response_code($response) !== 200) {
            LayerStatus::record('abuseipdb_api', LayerStatus::ERROR, 'AbuseIPDB error');

            return null;
        }

        $data = json_decode((string) wp_remote_retrieve_body($response), true);
        if (! is_array($data) || ! isset($data['data']['abuseConfidenceScore'])) {
            return null;
        }

        $score = (int) $data['data']['abuseConfidenceScore'];
        $this->cacheSet('abuse', $ip, $score);

        return $score;
    }

    private function lookupProxycheck(string $ip, string $apiKey): ?int
    {
        if ($apiKey === '' || ! filter_var($ip, FILTER_VALIDATE_IP)) {
            return null;
        }
        if (! $this->isPublic($ip)) {
            LayerStatus::record('proxycheck_io_api', LayerStatus::SKIPPED, 'Private IP');

            return null;
        }

        $cached = $this->cacheGet('proxy', $ip);
        if ($cached !== null) {
            return $cached;
        }

        $response = wp_remote_get(
            'https://proxycheck.io/v2/' . rawurlencode($ip) . '?key=' . rawurlencode($apiKey) . '&risk=1&vpn=1',
            ['timeout' => 20, 'headers' => ['Accept' => 'application/json']]
        );
        if (is_wp_error($response)) {
            LayerStatus::record('proxycheck_io_api', LayerStatus::TIMEOUT, 'Proxycheck.io unavailable');

            return null;
        }
        if ((int) wp_remote_retrieve_response_code($response) !== 200) {
            LayerStatus::record('proxycheck_io_api', LayerStatus::ERROR, 'Proxycheck.io error');

            return null;
        }

        $data = json_decode((string) wp_remote_retrieve_body($response), true);
        if (! is_array($data) || ! isset($data[$ip]['risk'])) {
            return null;
        }

        $risk = (int) $data[$ip]['risk'];
        $this->cacheSet('proxy', $ip, $risk);

        return $risk;
    }

    /** Reputation APIs only make sense for public IPs. */
    private function isPublic(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }

    private function cacheGet(string $provider, string $ip): ?int
    {
        $value = get_transient(self::CACHE_PREFIX . $provider . '_' . md5($ip));

        return is_numeric($value) ? (int) $value : null;
    }

    private function cacheSet(string $provider, string $ip, int $score): void
    {
        set_transient(self::CACHE_PREFIX . $provider . '_' . md5($ip), $score, self::CACHE_TTL);
    }
}
