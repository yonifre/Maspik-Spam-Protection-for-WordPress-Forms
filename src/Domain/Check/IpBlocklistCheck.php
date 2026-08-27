<?php

declare(strict_types=1);

namespace Maspik\Domain\Check;

use Maspik\Domain\Check\Support\CidrMatcher;
use Maspik\Domain\Model\Submission;
use Maspik\Domain\Model\Violation;

/**
 * Exact-IP and CIDR blocklist.
 */
final class IpBlocklistCheck implements SubmissionCheck
{
    /** @var string[] IPs and/or CIDR ranges, one entry each */
    private $blocklist;

    /**
     * @param string[] $blocklist
     */
    public function __construct(array $blocklist)
    {
        $this->blocklist = $blocklist;
    }

    public function id(): string
    {
        return 'ip_blacklist';
    }

    public function check(Submission $submission): ?Violation
    {
        $ip = $submission->ip;

        if (in_array($ip, $this->blocklist, true)) {
            return new Violation($this->id(), "IP $ip is blacklisted", $ip, $ip);
        }

        foreach ($this->blocklist as $entry) {
            if (CidrMatcher::isCidr($entry) && CidrMatcher::matches($ip, $entry)) {
                return new Violation($this->id(), "IP $ip is in CIDR: $entry", $entry, $ip);
            }
        }

        return null;
    }
}
