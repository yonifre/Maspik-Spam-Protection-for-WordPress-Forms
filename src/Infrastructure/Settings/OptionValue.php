<?php

declare(strict_types=1);

namespace Maspik\Infrastructure\Settings;

if (! defined('ABSPATH')) {
    exit;
}


/**
 * Reads a wp_options value as the type we stored, whatever is actually there.
 *
 * get_option() hands back whatever the row unserialises to, and a row is not
 * always what we wrote. A site migration that breaks serialised data returns a
 * __PHP_Incomplete_Class; another plugin can overwrite an option by name. Every
 * string option here was read as (string) get_option(...), which is fine for
 * ints, booleans and even arrays, and a fatal "could not be converted to string"
 * for an object - found by storing hostile values in every option we read and
 * driving everything that reads them. On the version option that meant the
 * upgrade routine failing on every request.
 *
 * A value of the wrong shape is treated as absent, which each caller already
 * handles: an empty licence key is "not licensed", an empty stored version is
 * "run the upgrade", which then writes a clean value back.
 */
final class OptionValue
{
    /** @param mixed $value */
    public static function string($value, string $default = ''): string
    {
        return is_scalar($value) ? (string) $value : $default;
    }
}
