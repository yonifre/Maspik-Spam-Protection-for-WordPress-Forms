<?php

declare(strict_types=1);

namespace Maspik\Domain\Model;

/**
 * One row of the Playground / log explanation: what a single check decided.
 * Immutable by convention.
 */
final class TraceEntry
{
    public const PASSED = 'passed';
    public const HIT = 'hit';
    public const SKIPPED = 'skipped';
    /** A check applicable to this submission that never ran (a cheaper check blocked first). */
    public const NOT_REACHED = 'not_reached';
    /** A field check with no field of its type in the submission (e.g. phone check, no phone field). */
    public const NOT_APPLICABLE = 'not_applicable';

    /** @var string */
    public $checkId;

    /** @var string one of PASSED | HIT | SKIPPED */
    public $outcome;

    /** @var Violation|null */
    public $violation;

    /** @var string|null why a check was skipped: 'disabled', 'pro', 'empty-field', … */
    public $skipReason;

    /** @var string|null */
    public $fieldName;

    public function __construct(
        string $checkId,
        string $outcome,
        ?Violation $violation = null,
        ?string $skipReason = null,
        ?string $fieldName = null
    ) {
        $this->checkId = $checkId;
        $this->outcome = $outcome;
        $this->violation = $violation;
        $this->skipReason = $skipReason;
        $this->fieldName = $fieldName;
    }
}
