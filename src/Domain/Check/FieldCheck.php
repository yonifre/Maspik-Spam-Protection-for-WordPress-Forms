<?php

declare(strict_types=1);

namespace Maspik\Domain\Check;

use Maspik\Domain\Model\Field;
use Maspik\Domain\Model\Violation;

/**
 * A check that looks at a single normalized field value.
 */
interface FieldCheck
{
    public function id(): string;

    /** @param string $type one of FieldType::ALL */
    public function supports(string $type): bool;

    public function check(Field $field): ?Violation;
}
