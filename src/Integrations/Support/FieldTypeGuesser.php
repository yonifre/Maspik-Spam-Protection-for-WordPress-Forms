<?php

declare(strict_types=1);

namespace Maspik\Integrations\Support;

use Maspik\Domain\Model\FieldType;

if (! defined('ABSPATH')) {
    exit;
}


/**
 * A last resort for a field whose type the adapter does not recognise.
 *
 * Every form plugin names its field types differently, and the names are not
 * discoverable from here: they are strings the plugin invents, they change
 * between versions, and add-ons introduce more. A hand-written map is therefore
 * always slightly behind, and the cost of being behind is invisible —
 * FieldMapper drops what it cannot place, so a missing name silently switches
 * off every content rule for that field.
 *
 * This turns that silence into a floor. When the type is unknown the field is
 * still scanned, classified by what it contains.
 *
 * WHAT IT DELIBERATELY WILL NOT DO
 *
 * It only ever answers TEXT or TEXTAREA. It will not guess EMAIL, URL or TEL,
 * and that restraint is the whole reason it is safe to run:
 *
 *  - EMAIL is an authority, not just a category. AllowList::allows() skips
 *    every check when an EMAIL field matches the allow list, so a guessed EMAIL
 *    would let anyone bypass the engine by typing an allow-listed address into
 *    any small text field.
 *  - TEL drives a format check. Applied to a field that is not a phone number —
 *    a house number, an order id, a year — it rejects submissions that are
 *    perfectly fine. Wrong coverage costs a miss; a wrong format check costs a
 *    customer.
 *  - URL is cheaper to get wrong, but it buys little: a link in an unknown
 *    field is already caught by the link limit and the text rules below.
 *
 * TEXT and TEXTAREA carry the blocklists, the length limits, the link limit,
 * the emoji rule and (for TEXTAREA) the language rule. That is most of the
 * value, and none of it can be turned against a legitimate visitor by a
 * misclassification: those rules fire on content the site owner listed, whatever
 * field it arrives in.
 *
 * Pure: no WordPress, no state.
 */
final class FieldTypeGuesser
{
    /**
     * Long enough that a single-line answer is not mistaken for prose.
     *
     * Chosen above the length of a street address or a long full name, and well
     * below any real message. The distinction matters because the language rule
     * only inspects TEXTAREA.
     */
    private const LONG_VALUE = 200;

    /**
     * @return string one of FieldType::TEXT or FieldType::TEXTAREA
     */
    public static function guess(string $value): string
    {
        // A line break is the one unambiguous mark of a multi-line control: no
        // single-line input can produce one.
        if (strpos($value, "\n") !== false || strpos($value, "\r") !== false) {
            return FieldType::TEXTAREA;
        }

        return mb_strlen($value) > self::LONG_VALUE ? FieldType::TEXTAREA : FieldType::TEXT;
    }
}
