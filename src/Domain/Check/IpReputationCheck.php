<?php

declare(strict_types=1);

namespace Maspik\Domain\Check;

use Maspik\Domain\Model\Submission;
use Maspik\Domain\Model\Violation;

/**
 * External IP reputation (AbuseIPDB / Proxycheck.io). Blocks when a provider's
 * score for the submitter's IP is at or above the configured threshold.
 *
 * The provider lookup is injected as a callable so this class stays pure:
 *   fn (string $ip): ?int   // score, or null on failure / no config
 *
 * v2 behaviour preserved:
 *  - thresholds of 10 or below are treated as "off" (safety floor);
 *  - a lookup failure (null) passes — fail-open, never block on an API error.
 */
final class IpReputationCheck implements SubmissionCheck
{
    /** @var callable(string): ?int */
    private $resolver;

    /** @var int */
    private $threshold;

    /** @var string 'abuseipdb_api' | 'proxycheck_io_api' */
    private $checkId;

    /** @var string human provider name for the log reason */
    private $label;

    /**
     * @param callable(string): ?int $resolver
     */
    public function __construct(callable $resolver, int $threshold, string $checkId, string $label)
    {
        $this->resolver = $resolver;
        $this->threshold = $threshold;
        $this->checkId = $checkId;
        $this->label = $label;
    }

    public function id(): string
    {
        return $this->checkId;
    }

    public function check(Submission $submission): ?Violation
    {
        // v2 required the threshold to be above 10 before ever calling out.
        if ($this->threshold <= 10) {
            return null;
        }

        $ip = $submission->ip;
        if ($ip === '') {
            return null;
        }

        $score = ($this->resolver)($ip);
        if ($score === null || $score < $this->threshold) {
            return null; // no data / below threshold ⇒ pass (fail-open)
        }

        return new Violation(
            $this->checkId,
            $this->label . ' Risk: ' . $score,
            (string) $this->threshold,
            $ip
        );
    }
}
