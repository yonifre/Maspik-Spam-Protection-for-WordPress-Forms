<?php

declare(strict_types=1);

namespace Maspik\Domain\Check;

use Maspik\Domain\Check\Support\TextMatcher;
use Maspik\Domain\Model\Field;
use Maspik\Domain\Model\FieldType;
use Maspik\Domain\Model\Violation;

/**
 * URL blacklist: wildcards and substrings.
 */
final class UrlBlacklistCheck implements FieldCheck
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
        return 'url_blacklist';
    }

    public function supports(string $type): bool
    {
        return $type === FieldType::URL;
    }

    public function check(Field $field): ?Violation
    {
        $value = strtolower(trim($field->value));
        if ($value === '') {
            return null;
        }

        foreach ($this->blacklist as $rule) {
            $ruleLower = strtolower(trim($rule));
            if ($ruleLower === '') {
                continue;
            }

            if (strpbrk($ruleLower, '*?') !== false) {
                if (TextMatcher::matchesWildcard($ruleLower, $value)) {
                    return new Violation(
                        $this->id(),
                        "URL *!$value!* is blocked because wildcard pattern *!$rule!* is in the blacklist",
                        $rule,
                        $value,
                        $field->name
                    );
                }
            } elseif (strpos($value, $ruleLower) !== false) {
                return new Violation(
                    $this->id(),
                    "URL *!$value!* is blocked because *!$rule!* is in the blacklist",
                    $rule,
                    $value,
                    $field->name
                );
            }
        }

        return null;
    }
}
