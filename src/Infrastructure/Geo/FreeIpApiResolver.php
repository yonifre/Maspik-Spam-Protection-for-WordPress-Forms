<?php

declare(strict_types=1);

namespace Maspik\Infrastructure\Geo;

use Maspik\Infrastructure\Logging\LayerStatus;

/**
 * Country/continent/ASN lookup via freeipapi.com — same endpoint as v2.
 * Returns null on any failure so callers stay fail-open.
 *
 * Results are transient-cached per IP. Safe for the verdict: a country/ASN of
 * an IP is effectively stable, so caching it cannot flip a decision within the
 * cache window (unlike reputation scores, which are cached separately).
 */
final class FreeIpApiResolver
{
    private const CACHE_PREFIX = 'maspik_geo_';
    private const CACHE_TTL = 6 * HOUR_IN_SECONDS;

    /**
     * @return callable(string): ?array{countryCode: string, continentCode: string, asnOrganization: string}
     */
    public function resolver(): callable
    {
        return function (string $ip): ?array {
            return $this->lookup($ip);
        };
    }

    /**
     * @return array{countryCode: string, continentCode: string, asnOrganization: string}|null
     */
    public function lookup(string $ip): ?array
    {
        if ($ip === '' || ! filter_var($ip, FILTER_VALIDATE_IP)) {
            return null;
        }

        $cacheKey = self::CACHE_PREFIX . md5($ip);
        $cached = get_transient($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        $response = wp_remote_get('https://free.freeipapi.com/api/json/' . rawurlencode($ip));
        if (is_wp_error($response)) {
            LayerStatus::record('country_blacklist', LayerStatus::TIMEOUT, 'Location lookup unavailable');

            return null;
        }
        if (wp_remote_retrieve_response_code($response) !== 200) {
            LayerStatus::record('country_blacklist', LayerStatus::ERROR, 'Location lookup failed');

            return null;
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (! is_array($data)) {
            return null;
        }

        $result = [
            'countryCode' => (string) ($data['countryCode'] ?? ''),
            'continentCode' => (string) ($data['continentCode'] ?? ''),
            'asnOrganization' => (string) ($data['asnOrganization'] ?? ''),
        ];

        set_transient($cacheKey, $result, self::CACHE_TTL);

        return $result;
    }
}
