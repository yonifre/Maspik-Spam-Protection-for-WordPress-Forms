<?php

declare(strict_types=1);

namespace Maspik\Domain\Check;

use Maspik\Domain\Model\Submission;
use Maspik\Domain\Model\Violation;

/**
 * A check that looks at the whole submission (honeypot, IP, country, AI…).
 * Implementations are pure: all configuration arrives via the constructor,
 * all IO (geo lookups, reputation APIs) arrives as injected callables/clients.
 */
interface SubmissionCheck
{
    public function id(): string;

    public function check(Submission $submission): ?Violation;
}
