<?php

declare(strict_types=1);

namespace Maspik\Infrastructure\Signals;

use Maspik\Domain\Model\Submission;
use Maspik\Infrastructure\Settings\Settings;

if (! defined('ABSPATH')) {
    exit;
}


/**
 * Client signals, observed and reported — never acted on.
 *
 * The guard script measures how a submission was produced (what the browser
 * reports about itself, and what happened on the page before submit) and sends
 * it back in one hidden field. This class decodes that field, holds it for the
 * request, and hands it to InputGate under its own key.
 *
 * It does not decide anything, and it is built so that it cannot start to by
 * accident:
 *
 *  - capture() returns void. There is no value it could return that another
 *    layer might read as a verdict.
 *  - It does not implement SubmissionCheck, so PipelineBuilder cannot assemble
 *    it into a pipeline even if someone tried.
 *  - It does not touch DirectPostSignal. That floor *is* weighed by InputGate,
 *    so feeding signals into it would change verdicts; the whole point of this
 *    class is that they do not.
 *  - forApi() returns null unless capture() ran and found something, and
 *    MatrixClient omits the key entirely on null — so a site that has this
 *    switched off sends byte for byte the request it sent before.
 *
 * Request-scoped and static for the same reason as DirectPostSignal and
 * LayerStatus: this is ephemeral metadata about one submission, and threading a
 * collector through every adapter constructor would add noise for no benefit.
 */
final class ObservedSignals
{
    /**
     * The hidden field the guard script writes into.
     *
     * Frozen contract. Pages are cached, sometimes for months, so a rename
     * silently stops collecting from every cached page until it is purged.
     */
    public const FIELD_NAME = 'maspik_signals';

    /**
     * Hard ceiling on the encoded field, well above what the collector produces
     * (~1.2 KB). Anything larger did not come from the collector.
     */
    private const MAX_BYTES = 4096;

    /** Field present and understood. */
    private const OK = 'ok';

    /** Field absent from the request. */
    private const ABSENT = 'absent';

    /** Field present but blank. */
    private const EMPTY_FIELD = 'empty';

    /** Field present but not the shape this version reads. */
    private const MALFORMED = 'malformed';

    /** Field from an older schema — a page cached before an upgrade. */
    private const STALE = 'stale_version';

    /** Field larger than any the collector emits. */
    private const OVERSIZE = 'oversize';

    /**
     * The transport cannot carry a form field at all, so its absence says
     * nothing. A REST request with a JSON body has no $_POST to put the field
     * in; reporting that as "absent" alongside genuinely missing fields would
     * make the whole integrity figure unreadable.
     */
    private const UNSUPPORTED = 'unsupported_transport';

    /** @var bool set by capture(); nothing is reported unless it ran */
    private static $captured = false;

    /** @var array<string, mixed>|null */
    private static $parsed = null;

    /** @var string one of the state constants above */
    private static $integrity = self::ABSENT;

    /** @var string */
    private static $source = '';

    /** @var int fields that arrived but failed validation; see SignalSchema */
    private static $rejected = 0;

    /** @var callable():array<int, string>|null resolved lazily, see layers() */
    private static $layerResolver = null;

    public static function reset(): void
    {
        self::$captured = false;
        self::$parsed = null;
        self::$integrity = self::ABSENT;
        self::$source = '';
        self::$rejected = 0;
        self::$layerResolver = null;
    }

    /**
     * Read and validate the signal field for this submission.
     *
     * Silent on every failure. Observation is not worth a single visitor seeing
     * a warning, and there is nothing here a site could act on. Same reasoning
     * as TelemetryReporter::send().
     *
     * @param callable():array<int, string> $layerResolver returns the check ids
     *        active for this request; invoked only if the payload is actually
     *        sent, so a submission that never reaches the cloud call does not
     *        pay for building the list.
     */
    public static function capture(
        Submission $submission,
        Settings $settings,
        callable $layerResolver
    ): void {
        try {
            if (! $settings->bool('client_signals_observe')) {
                return;
            }

            self::$captured = true;
            self::$source = $submission->source;
            self::$layerResolver = $layerResolver;

            if (self::transportCannotCarryFields()) {
                self::$integrity = self::UNSUPPORTED;

                return;
            }

            $raw = (string) $submission->hiddenField(self::FIELD_NAME);

            if ($raw === '') {
                // No script ran, or a caching or optimisation plugin held it
                // back, or the visitor has JavaScript off. This is an
                // infrastructure state, not evidence about the submission.
                self::$integrity = self::ABSENT;

                return;
            }

            if (strlen($raw) > self::MAX_BYTES) {
                self::$integrity = self::OVERSIZE;

                return;
            }

            // Depth is bounded as well as length. The block nests three
            // levels at most, so anything deeper is not a payload this
            // collector produced, and refusing it early costs less than
            // building the structure to find that out.
            $data = json_decode($raw, true, 8);
            if (! is_array($data)) {
                self::$integrity = self::EMPTY_FIELD;

                return;
            }

            $version = isset($data['v']) && is_int($data['v']) ? $data['v'] : 0;
            if ($version !== SignalSchema::VERSION) {
                // A page cached before the collector changed shape. Recorded as
                // its own state so it never gets counted as tampering.
                self::$integrity = self::STALE;

                return;
            }

            $clamped = SignalSchema::clamp($data);
            if ($clamped === []) {
                self::$integrity = self::MALFORMED;

                return;
            }

            self::$parsed = $clamped;
            self::$rejected = SignalSchema::rejectedCount();
            self::$integrity = self::OK;
        } catch (\Throwable $e) {
            // Deliberately silent. See the docblock.
        }
    }

    /**
     * The state of the signal field on this request: 'ok', or one of the
     * reasons it was not usable. Exposed for the debug log, which reports what
     * did arrive whenever this is not 'ok'.
     */
    public static function integrity(): string
    {
        return self::$captured ? self::$integrity : '';
    }

    /**
     * The block to send, or null to send nothing at all.
     *
     * Absent signals are still reported. Whether a missing field means "this
     * site serves cached pages" or "this one request had the field stripped" is
     * not answerable here — it is answerable from the history of the site, and
     * only the receiver has that. Dropping the row would throw the question
     * away instead of asking it.
     *
     * @return array<string, mixed>|null
     */
    public static function forApi(): ?array
    {
        try {
            if (! self::$captured) {
                return null;
            }

            $out = self::$parsed ?? [];
            $out['v'] = SignalSchema::VERSION;
            $out['ctx'] = self::context();

            if (isset($out['fp']['corr'])) {
                $salted = self::saltCorrelation((string) $out['fp']['corr']);
                if ($salted === null) {
                    unset($out['fp']['corr']);
                } else {
                    $out['fp']['corr'] = $salted;
                }
            }

            return $out;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Context the browser cannot supply, and the receiver cannot infer.
     *
     * `layers` is the one that is easy to leave out and expensive to add later.
     * By the time the cloud call runs, every earlier layer has passed — but
     * "passed" on a site running three layers and "passed" on a site running
     * fourteen are not the same statement, and without the list every row looks
     * alike. A thin configuration produces a great deal of "clean" that only
     * means "barely examined".
     *
     * @return array<string, mixed>
     */
    private static function context(): array
    {
        $context = [
            'integrity' => self::$integrity,
            'layers' => self::layers(),
            'source' => self::$source,
        ];

        // Only reported when it happened, so an ordinary submission carries
        // nothing extra.
        if (self::$rejected > 0) {
            $context['rejected'] = self::$rejected;
        }

        return $context;
    }

    /** @return array<int, string> */
    private static function layers(): array
    {
        if (self::$layerResolver === null) {
            return [];
        }

        try {
            $layers = (self::$layerResolver)();

            return is_array($layers) ? array_values(array_filter($layers, 'is_string')) : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Turn a device-stable value into one that correlates within this site and
     * this day, and nowhere else.
     *
     * The raw value the collector derives is stable per device, which is what
     * makes it useful — a hundred submissions carrying it are one machine, not
     * a hundred visitors. It is also what would make it a tracking identifier
     * if it were sent as-is: the same browser would be recognisable across every
     * site running this plugin, for as long as the device lasted.
     *
     * Salting with a per-site secret and the current UTC date keeps the part
     * that is useful and removes the part that is not. Two submissions from the
     * same machine to the same site on the same day still match; the same
     * machine on a different site, or the same site tomorrow, does not.
     */
    private static function saltCorrelation(string $value): ?string
    {
        if (! function_exists('wp_salt')) {
            return null;
        }

        // wp_salt() is generated per install and never leaves the site, which is
        // what makes the result site-specific. A licence token would not do:
        // sites without one share a single free credential, so every install
        // would salt identically and the value would once again be a
        // cross-site identifier - the exact thing this exists to prevent.
        $secret = (string) wp_salt('nonce');
        if ($secret === '') {
            return null;
        }

        return substr(hash_hmac('sha256', $value, $secret . gmdate('Y-m-d')), 0, 16);
    }

    /**
     * True when the request body cannot carry form-encoded fields.
     *
     * A REST request with a JSON body never populates $_POST, so the field is
     * missing by design rather than by circumstance. Narrowed by content type
     * rather than by REST_REQUEST alone: some form plugins post multipart bodies
     * to REST routes, and PHP does populate $_POST for those, so those requests
     * carry the field normally and must still be read.
     */
    private static function transportCannotCarryFields(): bool
    {
        if (! defined('REST_REQUEST') || ! REST_REQUEST) {
            return false;
        }

        $contentType = isset($_SERVER['CONTENT_TYPE'])
            ? strtolower((string) wp_unslash($_SERVER['CONTENT_TYPE']))
            : '';

        return $contentType !== '' && strpos($contentType, 'application/json') === 0;
    }
}
