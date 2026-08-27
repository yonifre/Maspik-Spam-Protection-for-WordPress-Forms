<?php

declare(strict_types=1);

namespace Maspik\Domain\Check\Support;

/**
 * IPv4 CIDR matching, ported from v2.9.x cidr_match() / ip_is_cidr().
 */
final class CidrMatcher
{
    public static function isCidr(string $value): bool
    {
        return (bool) preg_match('/^(\d{1,3}\.){3}\d{1,3}(\/(\d|[1-2]\d|3[0-2]))?$/', $value);
    }

    public static function matches(string $ip, string $cidr): bool
    {
        $parts = explode('/', $cidr);
        if (count($parts) < 2) {
            return false;
        }

        [$subnet, $bits] = $parts;
        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);
        if ($ipLong === false || $subnetLong === false) {
            return false;
        }

        $mask = -1 << (32 - (int) $bits);
        $subnetLong &= $mask; // align the subnet, as v2 did

        return ($ipLong & $mask) === $subnetLong;
    }
}
