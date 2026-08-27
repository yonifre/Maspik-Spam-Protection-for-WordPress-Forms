<?php

declare(strict_types=1);

namespace Maspik\Domain\Check;

use Maspik\Domain\Check\Support\TextMatcher;
use Maspik\Domain\Model\Field;
use Maspik\Domain\Model\FieldType;
use Maspik\Domain\Model\Violation;

/**
 * The word/phrase blacklist for text and textarea fields.
 *
 * v2 applied *different* wildcard semantics per field type
 * (validateTextField vs checkTextareaForSpam); both are preserved here
 * explicitly instead of being scattered across two copy-pasted functions.
 */
final class TextBlacklistCheck implements FieldCheck
{
    /** @var string[] merged local + dashboard rules */
    private $blacklist;

    /**
     * @param string[] $blacklist
     */
    public function __construct(array $blacklist)
    {
        $this->blacklist = $blacklist;
    }

    public function id(): string
    {
        return 'text_blacklist';
    }

    public function supports(string $type): bool
    {
        return $type === FieldType::TEXT || $type === FieldType::TEXTAREA;
    }

    public function check(Field $field): ?Violation
    {
        $value = strtolower($field->value);

        foreach ($this->blacklist as $rule) {
            $rule = trim(strtolower($rule));
            if ($rule === '') {
                continue;
            }

            if ($field->type === FieldType::TEXT) {
                if (strpos($rule, '*') !== false) {
                    // v2 text fields: pattern used as-is against the full value.
                    if (TextMatcher::matchesWildcard($rule, $value)) {
                        return new Violation(
                            $this->id(),
                            "Input *!$value!* is blocked by wildcard pattern",
                            $rule,
                            $value,
                            $field->name
                        );
                    }
                } elseif (TextMatcher::containsWord($rule, $value)) {
                    return new Violation(
                        $this->id(),
                        "Forbidden input *!$value!*, because *!$rule!* is blocked",
                        $rule,
                        $value,
                        $field->name
                    );
                }
            } else { // textarea
                if (strpbrk($rule, '*?') !== false) {
                    // v2 textareas: force *pattern* wrapping, chunked fnmatch.
                    if (TextMatcher::matchesWildcardChunked($rule, $value)) {
                        return new Violation(
                            $this->id(),
                            "field value matches pattern *!$rule!*",
                            $rule,
                            $value,
                            $field->name
                        );
                    }
                } elseif (TextMatcher::containsWord($rule, $value)) {
                    return new Violation(
                        $this->id(),
                        "field value includes *!$rule!*",
                        $rule,
                        $value,
                        $field->name
                    );
                }
            }
        }

        return null;
    }
}
