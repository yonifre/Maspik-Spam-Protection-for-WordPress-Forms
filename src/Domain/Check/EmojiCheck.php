<?php

declare(strict_types=1);

namespace Maspik\Domain\Check;

use Maspik\Domain\Check\Support\TextMatcher;
use Maspik\Domain\Model\Field;
use Maspik\Domain\Model\FieldType;
use Maspik\Domain\Model\Violation;

/**
 * Blocks emoji in text and textarea fields.
 */
final class EmojiCheck implements FieldCheck
{
    public function id(): string
    {
        return 'emoji_check';
    }

    public function supports(string $type): bool
    {
        return $type === FieldType::TEXT || $type === FieldType::TEXTAREA;
    }

    public function check(Field $field): ?Violation
    {
        if (TextMatcher::containsEmoji($field->value)) {
            return new Violation($this->id(), 'Emoji found in the field', '', $field->value, $field->name);
        }

        return null;
    }
}
