<?php

declare(strict_types=1);

namespace Maspik\Domain\Model;

/**
 * A single failed check. Field names mirror what v2.9.x carried in its loose
 * result arrays so logs and custom error messages stay compatible:
 *  - $checkId  == the old 'message'/'label' key (e.g. 'text_blacklist')
 *  - $reason   == the old human string, including *!highlight!* markers
 *
 * Immutable by convention.
 */
final class Violation
{
    /** @var string */
    public $checkId;

    /** @var string */
    public $reason;

    /** @var string */
    public $matchedRule;

    /** @var string */
    public $matchedValue;

    /** @var string|null */
    public $fieldName;

    public function __construct(
        string $checkId,
        string $reason,
        string $matchedRule = '',
        string $matchedValue = '',
        ?string $fieldName = null
    ) {
        $this->checkId = $checkId;
        $this->reason = $reason;
        $this->matchedRule = $matchedRule;
        $this->matchedValue = $matchedValue;
        $this->fieldName = $fieldName;
    }
}
