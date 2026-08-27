<?php

declare(strict_types=1);

namespace Maspik\Application;

use Maspik\Domain\Check\FieldCheck;
use Maspik\Domain\Check\SubmissionCheck;
use Maspik\Domain\Model\Submission;
use Maspik\Domain\Model\TraceEntry;
use Maspik\Domain\Model\Verdict;

/**
 * Runs checks in the exact v2.9.x order: submission-level checks first
 * (honeypot, key, country, ip, reputation), then field checks per field, then
 * late checks (Maspik Matrix cloud call) — so the paid round-trip only happens
 * once every cheaper local check has passed. First violation wins.
 *
 * ORDER IS BEHAVIOR. The check arrays passed in are ordered by CheckFactory;
 * changing that order changes verdicts on multi-issue submissions.
 */
final class CheckPipeline
{
    /** @var SubmissionCheck[] */
    private $submissionChecks;

    /** @var FieldCheck[] */
    private $fieldChecks;

    /** @var SubmissionCheck[] runs after field checks (e.g. Matrix) */
    private $lateChecks;

    /**
     * @param SubmissionCheck[] $submissionChecks
     * @param FieldCheck[]      $fieldChecks
     * @param SubmissionCheck[] $lateChecks
     */
    public function __construct(array $submissionChecks, array $fieldChecks, array $lateChecks = [])
    {
        $this->submissionChecks = $submissionChecks;
        $this->fieldChecks = $fieldChecks;
        $this->lateChecks = $lateChecks;
    }

    /**
     * Evaluate and record a per-layer trace in one pass. First violation wins
     * and still short-circuits — once something blocks, no further check runs
     * (so a honeypot-caught bot never triggers the paid Matrix call); the
     * remaining checks are recorded as NOT_REACHED. The trace rides on the
     * Verdict for logging; the verdict itself is identical to before.
     */
    public function evaluate(Submission $submission): Verdict
    {
        $trace = [];
        $blocked = null;

        foreach ($this->submissionChecks as $check) {
            if ($blocked !== null) {
                $trace[] = new TraceEntry($check->id(), TraceEntry::NOT_REACHED);
                continue;
            }
            $violation = $check->check($submission);
            $trace[] = new TraceEntry($check->id(), $violation !== null ? TraceEntry::HIT : TraceEntry::PASSED, $violation);
            if ($violation !== null) {
                $blocked = $violation;
            }
        }

        // Field phase — field-major, so the first offending *field* in submission
        // order wins (v2 parity), not the first check type. We track which
        // checks ran and the blocker to summarize per-layer afterwards.
        $ran = [];
        $blocker = null;
        $blockField = null;
        if ($blocked === null) {
            foreach ($submission->fields as $field) {
                if ($field->isEmpty()) {
                    continue;
                }
                foreach ($this->fieldChecks as $check) {
                    if (! $check->supports($field->type)) {
                        continue;
                    }
                    $ran[$check->id()] = true;
                    $violation = $check->check($field);
                    if ($violation !== null) {
                        $blocked = $violation;
                        $blocker = $check->id();
                        $blockField = $field->name;
                        break 2;
                    }
                }
            }
        }
        foreach ($this->fieldChecks as $check) {
            $id = $check->id();
            if ($id === $blocker) {
                $trace[] = new TraceEntry($id, TraceEntry::HIT, $blocked, null, $blockField);
            } elseif (isset($ran[$id])) {
                $trace[] = new TraceEntry($id, TraceEntry::PASSED);
            } elseif ($this->applies($check, $submission)) {
                // Applicable, but a cheaper field/check blocked before its turn.
                $trace[] = new TraceEntry($id, $blocked !== null ? TraceEntry::NOT_REACHED : TraceEntry::PASSED);
            } else {
                $trace[] = new TraceEntry($id, TraceEntry::NOT_APPLICABLE);
            }
        }

        foreach ($this->lateChecks as $check) {
            if ($blocked !== null) {
                $trace[] = new TraceEntry($check->id(), TraceEntry::NOT_REACHED);
                continue;
            }
            $violation = $check->check($submission);
            $trace[] = new TraceEntry($check->id(), $violation !== null ? TraceEntry::HIT : TraceEntry::PASSED, $violation);
            if ($violation !== null) {
                $blocked = $violation;
            }
        }

        return $blocked !== null ? Verdict::spam($blocked, $trace) : Verdict::clean($trace);
    }

    /** Whether a field check has any non-empty field of a type it supports. */
    private function applies(FieldCheck $check, Submission $submission): bool
    {
        foreach ($submission->fields as $field) {
            if (! $field->isEmpty() && $check->supports($field->type)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Trace mode: run everything, short-circuit nothing. Powers the
     * Playground, the log explanation view, and the parity harness.
     */
    public function trace(Submission $submission): Verdict
    {
        $trace = [];
        $first = null;

        foreach ($this->submissionChecks as $check) {
            $violation = $check->check($submission);
            $trace[] = new TraceEntry(
                $check->id(),
                $violation !== null ? TraceEntry::HIT : TraceEntry::PASSED,
                $violation
            );
            if ($first === null) {
                $first = $violation;
            }
        }

        foreach ($submission->fields as $field) {
            foreach ($this->fieldChecks as $check) {
                if (! $check->supports($field->type)) {
                    continue;
                }
                if ($field->isEmpty()) {
                    $trace[] = new TraceEntry($check->id(), TraceEntry::SKIPPED, null, 'empty-field', $field->name);
                    continue;
                }
                $violation = $check->check($field);
                $trace[] = new TraceEntry(
                    $check->id(),
                    $violation !== null ? TraceEntry::HIT : TraceEntry::PASSED,
                    $violation,
                    null,
                    $field->name
                );
                if ($first === null) {
                    $first = $violation;
                }
            }
        }

        foreach ($this->lateChecks as $check) {
            $violation = $check->check($submission);
            $trace[] = new TraceEntry(
                $check->id(),
                $violation !== null ? TraceEntry::HIT : TraceEntry::PASSED,
                $violation
            );
            if ($first === null) {
                $first = $violation;
            }
        }

        return $first !== null ? Verdict::spam($first, $trace) : Verdict::clean($trace);
    }
}
