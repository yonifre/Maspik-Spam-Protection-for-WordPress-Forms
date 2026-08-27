<?php

declare(strict_types=1);

namespace Maspik\Domain\Model;

/**
 * The five field types the v2.9.x engine distinguishes. Adapters map each
 * form plugin's own type names onto these constants.
 *
 * (PHP 7.4 compatibility: this is a constants class, not an 8.1 enum.
 * Values are the wire format used in logs and the REST API.)
 */
final class FieldType
{
    public const TEXT = 'text';
    public const EMAIL = 'email';
    public const URL = 'url';
    public const TEL = 'tel';
    public const TEXTAREA = 'textarea';

    public const ALL = [self::TEXT, self::EMAIL, self::URL, self::TEL, self::TEXTAREA];

    public static function isValid(string $type): bool
    {
        return in_array($type, self::ALL, true);
    }

    private function __construct()
    {
    }
}
