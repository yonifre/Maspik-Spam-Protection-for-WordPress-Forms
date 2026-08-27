<?php

declare(strict_types=1);

namespace Maspik\Domain\Check;

use Maspik\Domain\Model\Field;
use Maspik\Domain\Model\FieldType;
use Maspik\Domain\Model\Violation;

/**
 * Required / forbidden language (Unicode script) rules for textarea fields (Pro).
 * Each rule is a regex fragment like \p{Latin} or \p{Han}, matched with /u.
 */
final class LanguageCheck implements FieldCheck
{
    /** @var string[] at least one must match, if non-empty */
    private $required;

    /** @var string[] none may match */
    private $forbidden;

    /**
     * @param string[] $required
     * @param string[] $forbidden
     */
    public function __construct(array $required, array $forbidden)
    {
        $this->required = self::normalizedList($required);
        $this->forbidden = self::normalizedList($forbidden);
    }

    /**
     * Recovers the usable patterns from a stored list, dropping whatever
     * cannot be recovered.
     *
     * The list handed in is trusted input — from Settings, the Dashboard sync,
     * or migrated legacy data — not something the engine controls. v2 stored
     * more than one script choice on a single line, space-separated
     * ("\p{Hebrew} [A-Za-z]"), and read it back the same way; 3.0 reads the
     * same option key one pattern per line, so an entry a v2 site never
     * re-saved through the 3.0 UI arrives here as ONE line still holding both
     * choices. Splitting that line back into its pieces and validating each
     * one restores the site's original rule — required-Hebrew-or-Latin stays
     * required-Hebrew-or-Latin — without needing to know the value came from
     * v2 at all: the same split-and-validate step runs on every entry, so a
     * typo or a future storage change gets the identical treatment.
     *
     * This is also called directly by Upgrade::repairLanguageLists() to fix
     * the *stored* value once, so the admin's Language screen shows the
     * recovered scripts as separate, recognized chips instead of one
     * unrecognized line — the same logic, run once against the database
     * instead of on every request.
     *
     * @param string[] $entries
     * @return string[]
     */
    public static function normalizedList(array $entries): array
    {
        $out = [];
        foreach ($entries as $entry) {
            foreach (self::candidatesOf($entry) as $candidate) {
                $candidate = self::stripStrayQuotes($candidate);
                if (self::isValidPattern($candidate) && ! in_array($candidate, $out, true)) {
                    $out[] = $candidate;
                }
            }
        }

        return $out;
    }

    /**
     * One stored entry, split into the pattern(s) it might contain.
     *
     * A pattern never legitimately contains whitespace itself (see
     * isValidPattern), so whitespace inside an entry is unambiguous evidence
     * that v2 wrote more than one choice onto this line — never a sign that
     * this is one pattern that happens to need a literal space.
     *
     * @return string[]
     */
    private static function candidatesOf(string $entry): array
    {
        $entry = trim($entry);
        if ($entry === '') {
            return [];
        }

        return preg_match('/\s/', $entry) === 1 ? preg_split('/\s+/', $entry) : [$entry];
    }

    /**
     * Removes quote characters that wrap a script pattern by accident.
     *
     * Real Dashboards hold entries like `'\p{Thai}` and `'[А-Яа-яЁё]`, where a
     * stray leading apostrophe survived however the value was entered or
     * exported. Those still *compile* — the pattern simply demands a literal
     * apostrophe before the script character — so validation alone cannot catch
     * them. They just never match, and the rule quietly does nothing: a
     * "required language" nobody can satisfy, or a "forbidden language" that
     * never fires.
     *
     * Only wrapping quotes go. Every script pattern this feature offers is a
     * `\p{…}` escape or a character class, and none of them legitimately begins
     * or ends with a quote, so nothing real is lost.
     */
    public static function stripStrayQuotes(string $pattern): string
    {
        return trim(trim($pattern), "'\"");
    }

    /** A pattern is usable if it is non-empty, has no whitespace, and compiles under /u. */
    public static function isValidPattern(string $pattern): bool
    {
        if ($pattern === '' || preg_match('/\s/', $pattern) === 1) {
            return false;
        }

        return @preg_match("/$pattern/u", '') !== false;
    }

    public function id(): string
    {
        return 'lang_needed';
    }

    public function supports(string $type): bool
    {
        return $type === FieldType::TEXTAREA;
    }

    public function check(Field $field): ?Violation
    {
        $value = $field->value;

        if ($this->required !== [] && $this->detect($this->required, $value) === '') {
            $list = implode(', ', $this->required);

            return new Violation(
                'lang_needed',
                "Needed language is missing ($list)",
                $list,
                $value,
                $field->name
            );
        }

        if ($this->forbidden !== []) {
            $found = $this->detect($this->forbidden, $value);
            if ($found !== '') {
                return new Violation(
                    'lang_forbidden',
                    "Forbidden language *!$found!* exists",
                    $found,
                    $value,
                    $field->name
                );
            }
        }

        return null;
    }

    /** @param string[] $langs */
    private function detect(array $langs, string $value): string
    {
        if ($value === '') {
            return '';
        }
        foreach ($langs as $lang) {
            if ($lang !== '' && @preg_match("/$lang/u", $value) === 1) {
                return $lang;
            }
        }

        return '';
    }
}
