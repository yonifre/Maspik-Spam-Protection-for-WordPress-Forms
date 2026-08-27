<?php

declare(strict_types=1);

namespace Maspik\Domain\Check;

use Maspik\Domain\Check\Support\TextMatcher;
use Maspik\Domain\Model\Field;
use Maspik\Domain\Model\FieldType;
use Maspik\Domain\Model\Violation;

/**
 * Phone format validation: the value must match at least one allowed format
 * (regex `/.../` or wildcard). Entries that can never match are ignored, and
 * if none are left the check is skipped — as with an empty list (v2).
 */
final class PhoneCheck implements FieldCheck
{
    /** @var string[] */
    private $formats;

    /**
     * @param string[] $formats
     */
    public function __construct(array $formats)
    {
        $this->formats = $formats;
    }

    public function id(): string
    {
        return 'tel_formats';
    }

    public function supports(string $type): bool
    {
        return $type === FieldType::TEL;
    }

    public function check(Field $field): ?Violation
    {
        $value = $field->value;

        // Only entries the matcher can actually apply count. An entry that is
        // neither a valid regex nor a wildcard cannot match anything, so if
        // nothing usable is left there is no rule to enforce and the check is
        // skipped — otherwise a list made solely of such entries would reject
        // every phone number on the site.
        $formats = array_values(array_filter(
            array_map('trim', $this->formats),
            [self::class, 'isUsableFormat']
        ));

        if ($formats === []) {
            return null;
        }

        foreach ($formats as $format) {
            if (TextMatcher::isRegexRule($format)) {
                if (preg_match($format, $value)) {
                    return null;
                }
            } elseif (TextMatcher::matchesWildcard($format, $value)) {
                return null;
            }
        }

        return new Violation(
            $this->id(),
            "Phone number *!$value!* does not meet the given format. ",
            implode("\n", $formats),
            $value,
            $field->name
        );
    }

    /**
     * Can this entry match anything at all? Only a valid /regex/ or a wildcard
     * can. A bare number is ignored rather than treated as an exact match:
     * whitelisting one specific number is not a real use case, and an entry
     * that silently rejects every other number is far more likely to be a
     * mistake than an intention. Invalid regexes are dropped here for the same
     * reason (v2 skipped them mid-loop, which left the same trap).
     */
    private static function isUsableFormat(string $format): bool
    {
        if ($format === '') {
            return false;
        }

        if (TextMatcher::isRegexRule($format)) {
            return TextMatcher::isValidRegex($format);
        }

        return strpbrk($format, '*?') !== false;
    }
}
