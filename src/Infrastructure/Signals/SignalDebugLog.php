<?php

declare(strict_types=1);

namespace Maspik\Infrastructure\Signals;

use Maspik\Domain\Model\Submission;
use Maspik\Domain\Model\Verdict;
use Maspik\Integrations\Support\UnknownFieldTypes;

if (! defined('ABSPATH')) {
    exit;
}


/**
 * A readable record of what each submission produced and what left the site.
 *
 * Development tool. Off unless MASPIK_SIGNALS_DEBUG is defined, which is a
 * constant rather than a setting on purpose: this writes the outbound cloud
 * payload verbatim, and a switch in the admin screen is a switch somebody
 * leaves on.
 *
 *     define( 'MASPIK_SIGNALS_DEBUG', true );            // default location
 *     define( 'MASPIK_SIGNALS_DEBUG', '/tmp/sig.log' );  // somewhere specific
 *
 * One entry per submission, whether or not the cloud call happened. That is the
 * point of writing it here rather than inside the client: the cloud check runs
 * last and only if every local layer passed, so a form caught by the honeypot
 * never reaches it. Logging only at the call would show nothing at all for the
 * submissions most worth looking at, and reading that as "the signals did not
 * arrive" would be the wrong conclusion twice over.
 *
 * Never throws and never blocks. A debugging aid that can break a form is worse
 * than no debugging aid.
 */
final class SignalDebugLog
{
    /** Rotated at this size so a long session cannot fill a disk. */
    private const MAX_BYTES = 2097152;

    /** @var array<string, mixed> */
    private static $cloud = ['state' => 'not_reached'];

    public static function reset(): void
    {
        self::$cloud = ['state' => 'not_reached'];
    }

    public static function enabled(): bool
    {
        return defined('MASPIK_SIGNALS_DEBUG') && MASPIK_SIGNALS_DEBUG !== false;
    }

    /**
     * The request body on its way to the cloud check, exactly as sent.
     *
     * Credentials are not stripped here because they are not in the body: the
     * licence key, token and signature travel as headers and never reach this.
     *
     * @param array<string, mixed> $payload
     */
    public static function noteRequest(array $payload): void
    {
        if (! self::enabled()) {
            return;
        }

        self::$cloud = ['state' => 'sent', 'request' => $payload];
    }

    /** @param mixed $body */
    public static function noteResponse(int $status, $body): void
    {
        if (! self::enabled()) {
            return;
        }

        self::$cloud['response'] = ['status' => $status, 'body' => $body];
    }

    /**
     * The call was reached but deliberately not made — no quota left, or the
     * layer is switched off. Worth recording: "nothing was sent" and "nothing
     * was collected" look identical in a log that omits this.
     */
    public static function noteSkipped(string $reason): void
    {
        if (! self::enabled()) {
            return;
        }

        self::$cloud = ['state' => 'skipped', 'reason' => $reason];
    }

    /** One entry, written once the verdict is known. */
    public static function write(Submission $submission, Verdict $verdict): void
    {
        if (! self::enabled()) {
            return;
        }

        try {
            $path = self::path();
            if ($path === '') {
                return;
            }

            $entry = [
                'time' => gmdate('c'),
                'form' => $submission->sourceLabel . ' (' . $submission->source . ')',
                'ip' => $submission->ip,
                'verdict' => $verdict->isSpam ? 'BLOCKED' : 'clean',
                'blocked_by' => $verdict->violation !== null ? $verdict->violation->checkId : null,
                'reason' => $verdict->violation !== null ? $verdict->violation->reason : null,
                // What the signal field produced. Present even when the cloud
                // call never ran, so a honeypot catch still shows the payload
                // that would have gone out.
                'observed_signals' => ObservedSignals::forApi(),
                'cloud_call' => self::$cloud,
            ];

            // Field types this integration's map does not know. Present only
            // when there were any, and the single most useful line in the file
            // when adding an adapter: it names the strings the plugin actually
            // sends, which is not something its documentation reliably says.
            $unknown = UnknownFieldTypes::all();
            if ($unknown !== []) {
                $entry['unmapped_field_types'] = $unknown;
            }

            // When the field did not arrive intact, say what did. "Where are my
            // signals?" is otherwise a question only guesswork answers, and the
            // usual cause - a page rendered before the collector was enqueued,
            // or a script an optimisation plugin held back - looks identical
            // from the server to a field that was deliberately stripped.
            // Names only, never values: the submitted content is already above,
            // and this is meant to show the shape of the request.
            if (ObservedSignals::integrity() !== 'ok' && ObservedSignals::integrity() !== '') {
                $entry['request_keys'] = isset($_POST) && is_array($_POST)
                    ? array_map('strval', array_keys($_POST))
                    : [];
            }

            $json = function_exists('wp_json_encode')
                ? wp_json_encode($entry, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                : json_encode($entry, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            if (! is_string($json)) {
                return;
            }

            self::rotate($path);

            $header = "\n===== " . gmdate('Y-m-d H:i:s') . ' UTC · ' . $submission->source
                . ' · ' . ($verdict->isSpam ? 'BLOCKED' : 'clean') . " =====\n";

            file_put_contents($path, $header . $json . "\n", FILE_APPEND | LOCK_EX);
        } catch (\Throwable $e) {
            // A debugging aid must never surface on a visitor's screen.
        }
    }

    /** The active log path, or '' when it cannot be written. */
    public static function path(): string
    {
        if (! self::enabled()) {
            return '';
        }

        if (is_string(MASPIK_SIGNALS_DEBUG) && MASPIK_SIGNALS_DEBUG !== '') {
            return MASPIK_SIGNALS_DEBUG;
        }

        if (! function_exists('wp_upload_dir')) {
            return '';
        }

        $uploads = wp_upload_dir();
        if (! empty($uploads['error']) || empty($uploads['basedir'])) {
            return '';
        }

        $dir = rtrim((string) $uploads['basedir'], '/') . '/maspik-debug';
        if (! is_dir($dir) && ! wp_mkdir_p($dir)) {
            return '';
        }

        self::protect($dir);

        // The uploads directory is public, so the name carries a per-site
        // secret. Belt and braces alongside the deny rules: a server that
        // ignores .htaccess still does not serve a file nobody can name.
        $suffix = substr(hash_hmac('sha256', 'signal-debug-log', (string) wp_salt('nonce')), 0, 12);

        return $dir . '/signals-' . $suffix . '.log';
    }

    /** Deny rules and an index, for the two common server configurations. */
    private static function protect(string $dir): void
    {
        if (! file_exists($dir . '/index.php')) {
            file_put_contents($dir . '/index.php', "<?php // Silence is golden.\n");
        }

        if (! file_exists($dir . '/.htaccess')) {
            file_put_contents($dir . '/.htaccess', "Require all denied\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n");
        }
    }

    private static function rotate(string $path): void
    {
        if (! file_exists($path) || filesize($path) < self::MAX_BYTES) {
            return;
        }

        @rename($path, $path . '.1');
    }
}
