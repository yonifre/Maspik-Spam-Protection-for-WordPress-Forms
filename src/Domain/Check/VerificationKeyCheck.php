<?php

declare(strict_types=1);

namespace Maspik\Domain\Check;

use Maspik\Domain\Model\Submission;
use Maspik\Domain\Model\Violation;

/**
 * Verification Key (v3 rename of v2's "Advanced Key" / setting id
 * "maspikTimeCheck"): a hidden field added by the front-end script must carry
 * the server-issued key. A submission that never loaded the real page — and
 * so never ran the script — arrives without it and is blocked.
 *
 * FIELD_NAME is a wire contract with already-rendered/cached pages and must
 * never change; only the class/setting/check-id names were renamed.
 */
final class VerificationKeyCheck implements SubmissionCheck
{
    public const FIELD_NAME = 'maspik_spam_key';

    /** @var string */
    private $expectedKey;

    public function __construct(string $expectedKey)
    {
        $this->expectedKey = $expectedKey;
    }

    public function id(): string
    {
        return 'verification_key';
    }

    public function check(Submission $submission): ?Violation
    {
        $submitted = $submission->hiddenField(self::FIELD_NAME);

        if ($submitted === null || $submitted === '') {
            return new Violation($this->id(), 'Spam key check failed (empty)');
        }

        if ($submitted !== $this->expectedKey) {
            return new Violation($this->id(), 'Spam key check failed (not match)');
        }

        return null;
    }
}
