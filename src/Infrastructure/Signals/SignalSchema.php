<?php

declare(strict_types=1);

namespace Maspik\Infrastructure\Signals;

if (! defined('ABSPATH')) {
    exit;
}


/**
 * The shape of the client signal payload, and the only way it is allowed into
 * the plugin.
 *
 * Everything here arrives from a hidden form field, which means it arrives from
 * whoever submitted the form. It is treated exactly like any other request
 * input: nothing is trusted, nothing is passed through unexamined. clamp() is a
 * whitelist — a key that is not described below does not survive, whatever it
 * contains, so a crafted payload cannot smuggle extra data into the log row or
 * the outbound request by inventing field names.
 *
 * Numbers are range-clamped rather than rejected. A browser that reports a
 * hardware concurrency of 4 and one that reports 4000000 are both telling us
 * something; the second is telling us it is lying, and that is worth keeping as
 * "at the ceiling" instead of throwing the whole submission's signals away.
 *
 * Pure: no WordPress, no state, so the clamping can be tested against the
 * shapes real browsers produce and the shapes an attacker would try.
 */
final class SignalSchema
{
    /** Current payload version. Bumped when the field set changes shape. */
    public const VERSION = 1;

    /** Automation tool names the collector is allowed to report. */
    private const AUTOMATION = [
        'chromedriver',
        'selenium',
        'playwright',
        'puppeteer',
        'phantom',
        'nightmare',
        'cypress',
    ];

    /**
     * Environment fields: what the browser says about itself.
     *
     * @var array<string, array{0: string, 1?: mixed, 2?: mixed}>
     */
    private const ENV = [
        'webdriver' => ['bool'],
        'automation' => ['enum'],
        'headless_ua' => ['bool'],
        'client_hints_hl' => ['bool'],
        'cdp_stack' => ['bool'],
        'cdp_console' => ['bool'],
        'inner_eq_outer' => ['bool'],
        'no_outer_dims' => ['bool'],
        'chrome_rt_missing' => ['bool'],
        'no_plugins' => ['bool'],
        'no_languages' => ['bool'],
        'screen_odd' => ['bool'],
        'tz' => ['str', 64],
        'tz_offset' => ['int', -900, 900],
        'lang' => ['str', 35],
        'langs_n' => ['int', 0, 50],
        'cores' => ['int', 0, 1024],
        'mem' => ['int', 0, 1024],
        'dpr' => ['float', 0.0, 16.0],
        'screen' => ['dims'],
        'viewport' => ['dims'],
        'touch_points' => ['int', 0, 32],
    ];

    /**
     * Behavioural fields: aggregates over what happened on the page.
     *
     * Aggregates rather than raw event arrays, deliberately. The distribution of
     * a person's keystroke intervals is what distinguishes them from a script;
     * the intervals themselves are a recording of how somebody types, which is
     * both far larger to carry and more than this needs to know.
     *
     * @var array<string, array{0: string, 1?: mixed, 2?: mixed}>
     */
    private const BEH = [
        // Milliseconds. The ceiling is a day: anything longer is a stale tab,
        // not a form fill, and the exact figure stops meaning anything.
        'fill_ms' => ['int', 0, 86400000],
        'page_ms' => ['int', 0, 86400000],
        'keys' => ['int', 0, 100000],
        'key_iqr' => ['int', 0, 3600000],
        'key_var' => ['int', 0, 1000000000],
        'key_min' => ['int', 0, 3600000],
        'key_burst' => ['float', 0.0, 1.0],
        'corrections' => ['int', 0, 100000],
        'modifiers' => ['int', 0, 100000],
        'paste_n' => ['int', 0, 10000],
        'pointer_n' => ['int', 0, 1000000],
        'pointer_eff' => ['float', 0.0, 1.0],
        'touch_n' => ['int', 0, 1000000],
        'focus_n' => ['int', 0, 100000],
        'blur_n' => ['int', 0, 100000],
        'scroll_n' => ['int', 0, 1000000],
        'fields_n' => ['int', 0, 1000],
        'order_seq' => ['bool'],
        // 0 = untouched, 1 = typed into, 2 = already populated on attach.
        'honeypot' => ['int', 0, 2],
    ];

    /**
     * Rendering-anomaly flags, collected only when the extra layer is enabled.
     *
     * These are the verdicts of the rendering probes, not their output. A
     * boolean "this canvas rendered nothing plausible" carries the detection
     * value; the image hash behind it would be a stable identifier for the
     * visitor's device across every site that collected it, which is not a
     * trade this plugin makes. The one correlating value, `corr`, is salted per
     * site and per day before it leaves — see ObservedSignals::forApi().
     *
     * @var array<string, array{0: string, 1?: mixed, 2?: mixed}>
     */
    private const FP = [
        'canvas_odd' => ['bool'],
        'webgl_odd' => ['bool'],
        'webgl_vendor' => ['str', 64],
        'corr' => ['hex', 64],
    ];

    /**
     * How many declared fields arrived with a value the collector could not
     * have produced.
     *
     * Reset by each clamp() call. Worth reporting rather than silently
     * discarding: the collector only ever emits values that pass this schema,
     * so a rejection means the payload was altered between the browser and
     * here. Nothing else in the block distinguishes a modified payload from an
     * honest one, and a count above zero is difficult to produce by accident.
     *
     * @var int
     */
    private static $rejected = 0;

    /** Rejections from the most recent clamp() call. */
    public static function rejectedCount(): int
    {
        return self::$rejected;
    }

    /**
     * Coerce a decoded payload into the declared shape, dropping everything
     * undeclared.
     *
     * Walks the schema rather than the input, so a payload padded with ten
     * thousand junk keys costs no more to validate than an honest one.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function clamp(array $data): array
    {
        self::$rejected = 0;
        $out = [];

        $sid = self::hex($data['sid'] ?? null, 32);
        if ($sid !== null) {
            $out['sid'] = $sid;
        }

        $age = self::int($data['age'] ?? null, 0, 86400);
        if ($age !== null) {
            $out['age'] = $age;
        }

        foreach (['env' => self::ENV, 'beh' => self::BEH, 'fp' => self::FP] as $group => $spec) {
            if (! isset($data[$group]) || ! is_array($data[$group])) {
                continue;
            }
            $clamped = self::group($data[$group], $spec);
            if ($clamped !== []) {
                $out[$group] = $clamped;
            }
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $values
     * @param array<string, array{0: string, 1?: mixed, 2?: mixed}> $spec
     * @return array<string, mixed>
     */
    private static function group(array $values, array $spec): array
    {
        $out = [];

        foreach ($spec as $key => $rule) {
            if (! array_key_exists($key, $values)) {
                continue;
            }

            $raw = $values[$key];
            $type = $rule[0];

            switch ($type) {
                case 'bool':
                    $value = self::bool($raw);
                    break;
                case 'int':
                    $value = self::int($raw, $rule[1], $rule[2]);
                    break;
                case 'float':
                    $value = self::float($raw, $rule[1], $rule[2]);
                    break;
                case 'str':
                    $value = self::str($raw, $rule[1]);
                    break;
                case 'hex':
                    $value = self::hex($raw, $rule[1]);
                    break;
                case 'dims':
                    $value = self::dims($raw);
                    break;
                case 'enum':
                    $value = self::enum($raw);
                    break;
                default:
                    $value = null;
            }

            if ($value !== null) {
                $out[$key] = $value;
            } else {
                // Present in the payload but unusable: the collector cannot
                // emit this, so something rewrote it on the way.
                self::$rejected++;
            }
        }

        return $out;
    }

    /**
     * JSON booleans only. A string "1" here would mean the payload did not come
     * from the collector, and guessing at intent is how a null turns into a
     * false that reads as evidence.
     *
     * @param mixed $value
     */
    private static function bool($value): ?bool
    {
        return is_bool($value) ? $value : null;
    }

    /** @param mixed $value */
    private static function int($value, int $min, int $max): ?int
    {
        if (! is_int($value) && ! is_float($value)) {
            return null;
        }
        if (is_float($value) && ! is_finite($value)) {
            return null;
        }

        return max($min, min($max, (int) $value));
    }

    /** @param mixed $value */
    private static function float($value, float $min, float $max): ?float
    {
        if (! is_int($value) && ! is_float($value)) {
            return null;
        }
        if (is_float($value) && ! is_finite($value)) {
            return null;
        }

        return round(max($min, min($max, (float) $value)), 4);
    }

    /** @param mixed $value */
    private static function str($value, int $maxLength): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        // Printable ASCII only. Every field typed as a string here is a browser
        // constant - a locale, a time zone id, a GPU vendor - so anything
        // outside that range is not a truncated value, it is a different kind
        // of payload wearing the field's name.
        $clean = (string) preg_replace('/[^\x20-\x7E]/', '', $value);

        return $clean === '' ? null : substr($clean, 0, $maxLength);
    }

    /** @param mixed $value */
    private static function hex($value, int $maxLength): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }
        if (preg_match('/^[0-9a-f]{1,' . $maxLength . '}$/', $value) !== 1) {
            return null;
        }

        return $value;
    }

    /** Screen and viewport, as the collector formats them: "1512x982". */
    private static function dims($value): ?string
    {
        if (! is_string($value) || preg_match('/^\d{1,6}x\d{1,6}$/', $value) !== 1) {
            return null;
        }

        return $value;
    }

    /** @param mixed $value */
    private static function enum($value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        return in_array($value, self::AUTOMATION, true) ? $value : null;
    }
}
