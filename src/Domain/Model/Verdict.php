<?php

declare(strict_types=1);

namespace Maspik\Domain\Model;

/**
 * The engine's answer for one submission. Immutable by convention.
 */
final class Verdict
{
    /** @var bool */
    public $isSpam;

    /** @var Violation|null */
    public $violation;

    /** @var TraceEntry[] every check that ran (only populated in trace mode) */
    public $trace;

    /**
     * @param TraceEntry[] $trace
     */
    private function __construct(bool $isSpam, ?Violation $violation, array $trace = [])
    {
        $this->isSpam = $isSpam;
        $this->violation = $violation;
        $this->trace = $trace;
    }

    /** @param TraceEntry[] $trace */
    public static function clean(array $trace = []): self
    {
        return new self(false, null, $trace);
    }

    /** @param TraceEntry[] $trace */
    public static function spam(Violation $violation, array $trace = []): self
    {
        return new self(true, $violation, $trace);
    }
}
