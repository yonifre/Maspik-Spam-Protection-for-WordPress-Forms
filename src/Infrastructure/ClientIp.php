<?php

declare(strict_types=1);

namespace Maspik\Infrastructure;

/**
 * Resolves the real client IP with a trust model, not blind header trust.
 *
 * REMOTE_ADDR is the only inherently trustworthy source (the TCP peer). A
 * forwarded header is honoured only once the immediate peer has been
 * established as trusted:
 *
 *  1. Cloudflare — CF-Connecting-IP is trusted only when REMOTE_ADDR is a
 *     Cloudflare edge IP (shipped ranges, overridable via `maspik/cloudflare_ips`).
 *  2. Reverse proxy / LB — when REMOTE_ADDR is a trusted proxy, X-Forwarded-For
 *     is parsed right-to-left (proxies append, so the right is our infra; the
 *     client can only inject on the left, which is never reached) and the first
 *     public, non-infra IP is returned. A peer counts as a trusted proxy when
 *     it is in the configured list (`MASPIK_TRUSTED_PROXIES` / the
 *     `maspik/trusted_proxies` filter) or — by default — when it is a
 *     private/reserved address (same-host/same-network proxy; toggle with
 *     `maspik/trust_private_proxy`). A public direct visitor can never make
 *     REMOTE_ADDR appear private, so this can't be abused to spoof.
 *  3. Direct connection — REMOTE_ADDR is public and unknown: it IS the client;
 *     forwarded headers are ignored (the shared-hosting case).
 *
 * Always returns a single, validated IP; `maspik/client_ip` can override.
 */
final class ClientIp
{
    /**
     * Cloudflare published edge ranges (IPv4 + IPv6), as of the current list.
     * Override with the `maspik/cloudflare_ips` filter if they ever change.
     *
     * @var string[]
     */
    private const CLOUDFLARE_RANGES = [
        '173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22',
        '141.101.64.0/18', '108.162.192.0/18', '190.93.240.0/20', '188.114.96.0/20',
        '197.234.240.0/22', '198.41.128.0/17', '162.158.0.0/15', '104.16.0.0/13',
        '104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22',
        '2400:cb00::/32', '2606:4700::/32', '2803:f800::/32', '2405:b500::/32',
        '2405:8100::/32', '2a06:98c0::/29', '2c0f:f248::/32',
    ];

    /** @var string|null request-cached result */
    private $cached;

    public function get(): string
    {
        if ($this->cached === null) {
            $this->cached = $this->resolve();
        }

        return $this->cached;
    }

    private function resolve(): string
    {
        $remote = isset($_SERVER['REMOTE_ADDR'])
            ? trim((string) wp_unslash($_SERVER['REMOTE_ADDR']))
            : '';
        if (! filter_var($remote, FILTER_VALIDATE_IP)) {
            // No trustworthy peer — don't consult any forwarded header (they'd
            // be fully spoofable). This only happens on odd/CLI SAPIs.
            return '0.0.0.0';
        }

        $resolved = $remote;

        if ($this->matchesAny($remote, $this->cloudflareRanges())) {
            // 1. Genuine Cloudflare edge → trust its client header.
            $cf = $this->headerIp('HTTP_CF_CONNECTING_IP');
            if ($cf !== '') {
                $resolved = $cf;
            }
        } else {
            // 2. Trusted reverse proxy / load balancer.
            $trusted = $this->trustedProxies();
            $isProxy = ($trusted !== [] && $this->matchesAny($remote, $trusted))
                || ($this->trustPrivateProxy() && $this->isPrivateOrReserved($remote));
            if ($isProxy) {
                $client = $this->clientFromForwardedFor($trusted);
                if ($client !== '') {
                    $resolved = $client;
                }
            }
            // 3. Otherwise a direct public connection: keep REMOTE_ADDR.
        }

        /** Final say for hosts with a bespoke setup. Must return a valid IP. */
        $filtered = (string) apply_filters('maspik/client_ip', $resolved, $remote);

        return filter_var($filtered, FILTER_VALIDATE_IP) ? $filtered : $resolved;
    }

    /**
     * First public, non-infrastructure IP walking X-Forwarded-For from the
     * right. Skips the configured proxies, Cloudflare ranges, and any
     * private/reserved hop, so multi-hop internal chains resolve correctly.
     *
     * @param string[] $trusted
     */
    private function clientFromForwardedFor(array $trusted): string
    {
        $header = isset($_SERVER['HTTP_X_FORWARDED_FOR'])
            ? (string) wp_unslash($_SERVER['HTTP_X_FORWARDED_FOR'])
            : '';
        if ($header === '') {
            return '';
        }

        $skip = array_merge($trusted, $this->cloudflareRanges());
        $parts = array_map('trim', explode(',', $header));

        for ($i = count($parts) - 1; $i >= 0; $i--) {
            $ip = $parts[$i];
            if (! filter_var($ip, FILTER_VALIDATE_IP)) {
                continue;
            }
            if ($this->isPrivateOrReserved($ip) || $this->matchesAny($ip, $skip)) {
                continue; // an infra hop - keep walking left toward the client
            }

            return $ip;
        }

        return '';
    }

    private function headerIp(string $serverKey): string
    {
        if (empty($_SERVER[$serverKey])) {
            return '';
        }
        $ip = trim((string) wp_unslash($_SERVER[$serverKey]));

        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '';
    }

    /** @return string[] */
    private function trustedProxies(): array
    {
        $list = [];
        if (defined('MASPIK_TRUSTED_PROXIES')) {
            $configured = MASPIK_TRUSTED_PROXIES;
            $list = is_array($configured) ? $configured : explode(',', (string) $configured);
        }

        /** @var mixed $list */
        $list = apply_filters('maspik/trusted_proxies', $list);
        if (! is_array($list)) {
            return [];
        }

        $out = [];
        foreach ($list as $entry) {
            $entry = trim((string) $entry);
            if ($entry !== '') {
                $out[] = $entry;
            }
        }

        return $out;
    }

    private function trustPrivateProxy(): bool
    {
        return (bool) apply_filters('maspik/trust_private_proxy', true);
    }

    /** @return string[] */
    private function cloudflareRanges(): array
    {
        $ranges = apply_filters('maspik/cloudflare_ips', self::CLOUDFLARE_RANGES);

        return is_array($ranges) ? $ranges : self::CLOUDFLARE_RANGES;
    }

    private function isPrivateOrReserved(string $ip): bool
    {
        // Valid IP, but rejected once private + reserved ranges are excluded.
        return filter_var($ip, FILTER_VALIDATE_IP) !== false
            && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    }

    /**
     * @param string[] $ranges each an IP or a CIDR (v4 or v6)
     */
    private function matchesAny(string $ip, array $ranges): bool
    {
        foreach ($ranges as $range) {
            if ($this->ipInRange($ip, (string) $range)) {
                return true;
            }
        }

        return false;
    }

    /** CIDR / single-IP membership for both IPv4 and IPv6. */
    private function ipInRange(string $ip, string $range): bool
    {
        $range = trim($range);
        if ($range === '') {
            return false;
        }

        if (strpos($range, '/') === false) {
            $a = @inet_pton($ip);
            $b = @inet_pton($range);

            return $a !== false && $a === $b;
        }

        list($subnet, $bitsRaw) = explode('/', $range, 2);
        $ipBin = @inet_pton($ip);
        $subnetBin = @inet_pton($subnet);
        if ($ipBin === false || $subnetBin === false || strlen($ipBin) !== strlen($subnetBin)) {
            return false; // malformed, or an IPv4/IPv6 family mismatch
        }

        $bits = (int) $bitsRaw;
        $maxBits = strlen($ipBin) * 8;
        if ($bits < 0 || $bits > $maxBits) {
            return false;
        }

        $wholeBytes = intdiv($bits, 8);
        if ($wholeBytes > 0 && strncmp($ipBin, $subnetBin, $wholeBytes) !== 0) {
            return false;
        }

        $remainingBits = $bits % 8;
        if ($remainingBits === 0) {
            return true;
        }

        $mask = 0xff << (8 - $remainingBits) & 0xff;

        return (ord($ipBin[$wholeBytes]) & $mask) === (ord($subnetBin[$wholeBytes]) & $mask);
    }
}
