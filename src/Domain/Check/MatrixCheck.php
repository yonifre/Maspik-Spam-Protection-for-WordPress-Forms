<?php

declare(strict_types=1);

namespace Maspik\Domain\Check;

use Maspik\Domain\Model\Submission;
use Maspik\Domain\Model\Violation;

/**
 * Maspik Matrix (InputGate) — the cloud verdict, run LAST in the submission
 * chain so a bot caught by a cheaper local check never triggers a network call.
 *
 * The cloud lookup is injected as a callable so this class stays pure:
 *   fn (Submission): ?array{is_spam: bool, reason: string}
 *
 * Fail-open: a null result (cloud unreachable / over quota / disabled) passes.
 */
final class MatrixCheck implements SubmissionCheck
{
    /** @var callable(Submission): ?array */
    private $resolver;

    /**
     * @param callable(Submission): ?array $resolver
     */
    public function __construct(callable $resolver)
    {
        $this->resolver = $resolver;
    }

    public function id(): string
    {
        return 'ai_spam_check';
    }

    public function check(Submission $submission): ?Violation
    {
        $result = ($this->resolver)($submission);
        if ($result === null || empty($result['is_spam'])) {
            return null;
        }

        $reason = isset($result['reason']) && is_string($result['reason']) && $result['reason'] !== ''
            ? $result['reason']
            : 'InputGate flagged this submission';

        return new Violation('ai_spam_check', $reason, '', $submission->ip);
    }
}
