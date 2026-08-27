<?php

declare(strict_types=1);

namespace Maspik\Domain\Check;

use Maspik\Domain\Model\Submission;
use Maspik\Domain\Model\Violation;

/**
 * Honeypot: the injected hidden field must stay empty.
 * Field name and reason string are frozen v2 contract (cached pages!).
 */
final class HoneypotCheck implements SubmissionCheck
{
    public const FIELD_NAME = 'full-name-maspik-hp';

    public function id(): string
    {
        return 'maspikHoneypot';
    }

    public function check(Submission $submission): ?Violation
    {
        $value = $submission->hiddenField(self::FIELD_NAME);
        if ($value !== null && $value !== '') {
            return new Violation($this->id(), 'Honeypot field is not empty');
        }

        return null;
    }
}
