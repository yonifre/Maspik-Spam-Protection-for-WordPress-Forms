<?php

declare(strict_types=1);

namespace Maspik\Domain\Check;

use Maspik\Domain\Check\Support\TextMatcher;
use Maspik\Domain\Model\Field;
use Maspik\Domain\Model\FieldType;
use Maspik\Domain\Model\Violation;

/**
 * Email blacklist: substrings, wildcards (* ?), and /regex/ rules.
 *
 * v2 quirk preserved: values that don't pass FILTER_VALIDATE_EMAIL are not
 * Maspik's problem — they pass (the form plugin's own validation handles them).
 */
final class EmailBlacklistCheck implements FieldCheck
{
    /** @var string[] */
    private $blacklist;

    /** @param string[] $blacklist */
    public function __construct(array $blacklist)
    {
        $this->blacklist = $blacklist;
    }

    public function id(): string
    {
        return 'emails_blacklist';
    }

    public function supports(string $type): bool
    {
        return $type === FieldType::EMAIL;
    }

    public function check(Field $field): ?Violation
    {
        $value = strtolower(trim($field->value));

        if ($value === '' || ! filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        foreach ($this->blacklist as $rule) {
            if (trim($rule) === '') {
                continue;
            }

            $ruleLower = trim(strtolower($rule));

            if (TextMatcher::isRegexRule($ruleLower)) {
                if (! TextMatcher::isValidRegex($ruleLower)) {
                    continue; // invalid regex rules are skipped silently, as v2
                }
                if (preg_match($ruleLower, $value)) {
                    return new Violation(
                        $this->id(),
                        "Email *!$value!* is blocked because regular expression pattern *!$rule!* is in the blacklist",
                        $rule,
                        $value,
                        $field->name
                    );
                }
            } elseif (strpbrk($ruleLower, '*?') !== false) {
                if (TextMatcher::matchesWildcard($ruleLower, $value)) {
                    return new Violation(
                        $this->id(),
                        "Email *!$value!* is blocked because wildcard pattern *!$rule!* is in the blacklist",
                        $rule,
                        $value,
                        $field->name
                    );
                }
            } elseif (TextMatcher::containsSubstring($ruleLower, $value)) {
                return new Violation(
                    $this->id(),
                    "Email *!$value!* is blocked because email *!$rule!* is in the blacklist",
                    $rule,
                    $value,
                    $field->name
                );
            }
        }

        return null;
    }
}
