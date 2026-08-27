<?php

declare(strict_types=1);

namespace Maspik\Domain\Model;

/**
 * One submitted form field, normalized. Array values (checkbox groups etc.)
 * are joined with spaces before checks run — same as v2.9.x implode(" ", ...).
 *
 * Immutable by convention: properties are public for read access and must
 * never be written after construction.
 */
final class Field
{
    /** @var string */
    public $name;

    /** @var string one of FieldType::ALL */
    public $type;

    /** @var string */
    public $value;

    /**
     * @param string                          $name
     * @param string                          $type     one of FieldType::ALL
     * @param string|array<int|string, mixed> $rawValue
     */
    public function __construct(string $name, string $type, $rawValue)
    {
        $this->name = $name;
        $this->type = $type;
        $this->value = self::flatten($rawValue);
    }

    /**
     * Reduce a submitted value to one scanable string.
     *
     * Form plugins hand over arrays as readily as strings — a WPForms name is
     * ['first' => …, 'last' => …], checkbox groups are lists, and some plugins
     * nest deeper still. This is the single choke point every adapter funnels
     * through, so flattening correctly here protects every integration at once.
     *
     * Recurses to any depth and keeps only scalar leaves. A plain
     * implode/strval would emit the literal "Array" (plus a PHP warning) the
     * moment a value nests, and dropping arrays outright loses text that should
     * have been scanned for spam.
     *
     * @param mixed $rawValue
     */
    public static function flatten($rawValue): string
    {
        if (is_scalar($rawValue)) {
            return is_bool($rawValue) ? ($rawValue ? '1' : '') : (string) $rawValue;
        }
        if (! is_array($rawValue)) {
            return '';   // objects, null, resources - nothing to scan
        }

        $parts = [];
        array_walk_recursive($rawValue, static function ($leaf) use (&$parts): void {
            if (! is_scalar($leaf)) {
                return;
            }
            $text = is_bool($leaf) ? ($leaf ? '1' : '') : trim((string) $leaf);
            if ($text !== '') {
                $parts[] = $text;
            }
        });

        return implode(' ', $parts);
    }

    public function isEmpty(): bool
    {
        return trim($this->value) === '';
    }
}
