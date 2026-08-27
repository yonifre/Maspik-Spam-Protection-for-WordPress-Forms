<?php

declare(strict_types=1);

namespace Maspik\Domain\Check;

use Maspik\Domain\Check\Support\LinkCounter;
use Maspik\Domain\Model\Field;
use Maspik\Domain\Model\FieldType;
use Maspik\Domain\Model\Violation;

/**
 * Maximum number of links in text/textarea fields. max = 0 means no links
 * allowed at all.
 */
final class LinkLimitCheck implements FieldCheck
{
    /** @var int */
    private $maxLinks;

    public function __construct(int $maxLinks)
    {
        $this->maxLinks = $maxLinks;
    }

    public function id(): string
    {
        return 'contain_links';
    }

    public function supports(string $type): bool
    {
        return $type === FieldType::TEXT || $type === FieldType::TEXTAREA;
    }

    public function check(Field $field): ?Violation
    {
        $count = LinkCounter::count(strtolower($field->value));

        $blocked = ($this->maxLinks === 0 && $count > 0)
            || ($this->maxLinks > 0 && $count > $this->maxLinks);

        if ($blocked) {
            $reason = $this->maxLinks === 0
                ? 'Links are not allowed'
                : "Contains *!more than {$this->maxLinks} links!*";

            return new Violation($this->id(), $reason, (string) $this->maxLinks, (string) $count, $field->name);
        }

        return null;
    }
}
