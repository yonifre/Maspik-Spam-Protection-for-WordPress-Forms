<?php

declare(strict_types=1);

namespace Maspik\Domain\Check;

use Maspik\Domain\Model\Field;
use Maspik\Domain\Model\FieldType;
use Maspik\Domain\Model\Violation;

/**
 * Min/max character limits, parameterized per field type.
 *
 * v2 quirks preserved per type:
 *  - text/tel: limits only apply when max is numeric and > 3
 *  - textarea: max applies when > 2; min applies independently when > 0
 *  - mb_strlen throughout
 * The old per-type checkIds are kept so custom error messages keep working.
 */
final class CharacterLimitCheck implements FieldCheck
{
    /** @var string one of FieldType::ALL */
    private $type;

    /** @var int|null */
    private $min;

    /** @var int|null */
    private $max;

    public function __construct(string $type, ?int $min, ?int $max)
    {
        $this->type = $type;
        $this->min = $min;
        $this->max = $max;
    }

    public function id(): string
    {
        switch ($this->type) {
            case FieldType::TEXTAREA:
                return 'MaxCharactersInTextAreaField';
            case FieldType::TEL:
                return 'MaxCharactersInPhoneField';
            default:
                return 'MaxCharactersInTextField';
        }
    }

    private function minId(): string
    {
        return str_replace('Max', 'Min', $this->id());
    }

    public function supports(string $type): bool
    {
        return $type === $this->type;
    }

    public function check(Field $field): ?Violation
    {
        $length = mb_strlen($field->value);

        switch ($this->type) {
            case FieldType::TEXTAREA:
                $noun = ' in Text Area field.';
                break;
            case FieldType::TEL:
                $noun = ' in Phone Number';
                break;
            default:
                $noun = '';
        }

        if ($this->type === FieldType::TEXTAREA) {
            if ($this->max !== null && $this->max > 2 && $length > $this->max) {
                return new Violation($this->id(), "More than *!{$this->max}!* characters$noun", (string) $this->max, $field->value, $field->name);
            }
            if ($this->min !== null && $this->min > 0 && $length < $this->min) {
                return new Violation($this->minId(), "Less than *!{$this->min}!* characters$noun", (string) $this->min, $field->value, $field->name);
            }

            return null;
        }

        // text + tel: whole check gated on max > 3, min piggybacks — as v2.
        if ($this->max === null || $this->max <= 3) {
            return null;
        }
        if ($length > $this->max) {
            return new Violation($this->id(), "More than *!{$this->max}!* characters$noun", (string) $this->max, $field->value, $field->name);
        }
        if ($this->min !== null && $length < $this->min) {
            return new Violation($this->minId(), "Less than *!{$this->min}!* characters$noun", (string) $this->min, $field->value, $field->name);
        }

        return null;
    }
}
