<?php

declare(strict_types=1);

namespace Maspik\Integrations\Support;

use Maspik\Domain\Model\Field;

if (! defined('ABSPATH')) {
    exit;
}


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
    /**
     * Turn an adapter's raw rows into typed fields.
     *
     * A type the map does not know is recorded and then either dropped (the
     * behaviour this has always had) or, when $inferUnknown is on, scanned as
     * text. Dropping is silent by nature — nothing warns, nothing logs, the
     * field simply never reaches the engine — so the recording happens either
     * way: it is how the maps get corrected.
     *
     * @param array<int, array{name?: string, type?: string, value?: mixed}> $rawFields
     * @param array<string, string> $typeMap
     * @param string $source integration id, for the record of unknown types
     * @return Field[]
     */
    public static function map(
        array $rawFields,
        array $typeMap,
        bool $inferUnknown = false,
        string $source = ''
    ): array {
        $fields = [];
        foreach ($rawFields as $raw) {
            $type = isset($raw['type']) ? (string) $raw['type'] : '';
            $name = isset($raw['name']) ? (string) $raw['name'] : '';
            $value = $raw['value'] ?? '';

            if (isset($typeMap[$type])) {
                $fields[] = new Field($name, $typeMap[$type], $value);
                continue;
            }

            UnknownFieldTypes::record($source, $type);

            if (! $inferUnknown) {
                continue;
            }

            // Guessed from the value, and only ever TEXT or TEXTAREA. See
            // FieldTypeGuesser for why the other three are off limits.
            $flat = Field::flatten($value);
            if ($flat === '') {
                continue;
            }
            $fields[] = new Field($name, FieldTypeGuesser::guess($flat), $value);
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
