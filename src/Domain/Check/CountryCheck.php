<?php

declare(strict_types=1);

namespace Maspik\Domain\Check;

use Maspik\Domain\Model\Submission;
use Maspik\Domain\Model\Violation;

/**
 * Country / continent allow- or block-list (Pro).
 *
 * The geo lookup is injected as a callable so this class stays pure:
 *   fn (string $ip): ?array{countryCode: string, continentCode: string, asnOrganization: string}
 *
 * v2 quirks preserved:
 *  - lookup failure ⇒ pass (fail-open)
 *  - Cloudflare ASN ⇒ skip entirely (edge IPs must not be geo-blocked)
 *  - entries prefixed "Continent:" are continent codes
 */
final class CountryCheck implements SubmissionCheck
{
    /** @var string 'allow'|'block' */
    private $mode;

    /** @var string[] country codes and "Continent:XX" entries */
    private $countryList;

    /** @var callable(string): ?array */
    private $geoResolver;

    /**
     * @param string   $mode 'allow'|'block'
     * @param string[] $countryList
     * @param callable(string): ?array $geoResolver
     */
    public function __construct(string $mode, array $countryList, callable $geoResolver)
    {
        $this->mode = $mode;
        $this->countryList = $countryList;
        $this->geoResolver = $geoResolver;
    }

    public function id(): string
    {
        return 'country_blacklist';
    }

    public function check(Submission $submission): ?Violation
    {
        if ($this->countryList === []) {
            return null;
        }

        $geo = ($this->geoResolver)($submission->ip);
        if ($geo === null) {
            return null; // fail-open, as v2
        }

        $asn = isset($geo['asnOrganization']) ? $geo['asnOrganization'] : '';
        if (is_string($asn) && stripos($asn, 'cloudflare') !== false) {
            return null; // Cloudflare edge - skip to avoid false positives, as v2
        }

        $countryCode = ! empty($geo['countryCode']) ? $geo['countryCode'] : 'Unknown';
        $continentCode = ! empty($geo['continentCode']) ? $geo['continentCode'] : 'Unknown';

        $countries = [];
        $continents = [];
        foreach ($this->countryList as $item) {
            if (strpos($item, 'Continent:') === 0) {
                $continents[] = substr($item, strlen('Continent:'));
            } else {
                $countries[] = $item;
            }
        }

        $listed = in_array($countryCode, $countries, true)
            || in_array($continentCode, $continents, true);

        if ($this->mode === 'block' && $listed) {
            return new Violation(
                $this->id(),
                "Country code $countryCode or continent $continentCode is blacklisted (block)",
                '',
                $countryCode
            );
        }

        if ($this->mode === 'allow' && ! $listed) {
            return new Violation(
                $this->id(),
                "Country code $countryCode or continent $continentCode is not in the whitelist (allow)",
                '',
                $countryCode
            );
        }

        return null;
    }
}
