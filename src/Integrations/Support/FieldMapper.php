<?php

declare(strict_types=1);

namespace Maspik\Integrations\Support;

use Maspik\Domain\Model\Field;

/**
 * Turns a form plugin's fields into the engine's normalized Field[].
 *
 * This is the one genuinely per-plugin, bug-prone concern — mapping each
 * plugin's field-type names onto our five FieldTypes — isolated as pure code
 * so every adapter's type table is unit-testable without WordPress or the
 * plugin installed. Types not in the map (submit buttons, hidden, html,
 * acceptance, …) are dropped, exactly as v2 ignored them.
 */
final class FieldMapper
{
    /**
     * @param array<int, array{name: string, type: string, value: string|array}> $rawFields
     * @param array<string, string> $typeMap plugin field type => FieldType constant
     * @return Field[]
     */
    public static function map(array $rawFields, array $typeMap): array
    {
        $fields = [];
        foreach ($rawFields as $raw) {
            $type = isset($raw['type']) ? (string) $raw['type'] : '';
            if (! isset($typeMap[$type])) {
                continue;
            }
            $fields[] = new Field((string) $raw['name'], $typeMap[$type], $raw['value']);
        }

        return $fields;
    }

    /**
     * Flatten a submitted value to a single scanable string.
     *
     * Form plugins routinely hand us arrays, not strings: WPForms name fields
     * arrive as ['first' => 'John', 'last' => 'Doe'], addresses and checkbox
     * groups are arrays too, and some nest further. Dropping those (or running
     * a scalar sanitiser over them) loses the data silently — the text never
     * gets scanned for spam and shows up blank in the log.
     *
     * Recurses to any depth, keeps only scalar leaves, and joins with spaces so
     * the result reads naturally to the text checks.
     *
     * @param mixed $value
     */
    public static function flatten($value): string
    {
        return Field::flatten($value);
    }
}
