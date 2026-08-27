<?php

declare(strict_types=1);

namespace Maspik\Infrastructure\Matrix;

use Maspik\Domain\Model\Submission;

/**
 * "Direct POST attack" signal (v2's NeedPageurl / "Elementor Bot detector").
 *
 * A real submission travels through the site's own UI, so it arrives with the
 * page context that form plugin normally posts (Elementor's `referrer` field,
 * CF7's `_wpcf7` id, an HTTP referer). A scripted POST straight at the endpoint
 * usually has none of that.
 *
 * Exactly as in v2 this never blocks locally — a missing referrer is suggestive,
 * not proof, and blocking on it produced false positives. Instead it raises the
 * `plugin_spam_likelihood` floor (1–9) sent to Maspik Matrix, which weighs it
 * together with everything else. Adapters that know their plugin's own context
 * field call raise() during evaluation; MatrixClient reads the floor when it
 * builds the payload.
 *
 * Request-scoped static side-channel, like LayerStatus — keeps the domain layer
 * free of transport concerns.
 */
final class DirectPostSignal
{
    /**
     * Per-integration direct-POST scores.
     *
     * These are deliberately distinct values rather than a shared "high" and
     * "medium": the score is the only part of this signal that reaches
     * InputGate, so giving each integration its own number lets the cloud tell
     * which form type raised the suspicion without a separate field. Keep them
     * unique — two integrations sharing a value makes the source unreadable
     * server-side.
     *
     * They also still rank by confidence, highest first: Elementor carries no
     * page context at all when forged, while a missing comment post id is the
     * weakest of the five.
     */
    public const ELEMENTOR = 9;
    public const ELEMENTOR_ATOMIC = 8;
    public const CF7 = 7;
    public const GRAVITY_FORMS = 6;
    public const WP_COMMENTS = 5;

    /** @var int 1 = no suspicion (default) … 9 = high */
    private static $floor = 1;

    /** @var string|null the referrer label to report, when one was recorded */
    private static $referrer = null;

    public static function reset(): void
    {
        self::$floor = 1;
        self::$referrer = null;
    }

    /** Raise the floor (never lowers it — the most suspicious signal wins). */
    public static function raise(int $score, ?string $referrer = null): void
    {
        $score = max(1, min(9, $score));
        self::$floor = max(self::$floor, $score);
        if ($referrer !== null && $referrer !== '') {
            self::$referrer = $referrer;
        }
    }

    /** @return int the current floor, 1–9 */
    public static function floor(): int
    {
        return max(1, min(9, self::$floor));
    }

    /**
     * The value to report as `maspik_referrer`: a real URL when we have one,
     * otherwise the `no_referrer` sentinel (v2 used the same literal, and
     * deliberately never ran it through esc_url_raw, which would invent
     * "http://no_referrer").
     */
    public static function referrerFor(Submission $submission): string
    {
        if (self::$referrer !== null && self::$referrer !== '') {
            return self::$referrer;
        }
        if ($submission->referrer !== null && $submission->referrer !== '') {
            return $submission->referrer;
        }
        if (! empty($_SERVER['HTTP_REFERER'])) {
            $raw = esc_url_raw((string) wp_unslash($_SERVER['HTTP_REFERER']));
            if ($raw !== '') {
                return $raw;
            }
        }

        return 'no_referrer';
    }

    /**
     * Deliberately does nothing on its own.
     *
     * This used to raise the top score for any submission arriving without a
     * Referer header, which was wrong twice over. It is not what v2 did — there
     * the score came only from integrations, each with its own sentinel — and
     * plenty of real visitors send no Referer at all: browsers under a
     * `no-referrer` policy, privacy extensions, and some corporate proxies all
     * strip it. Those people would have been handed the maximum suspicion score
     * on every form.
     *
     * It also made the score unreadable in the cloud: a generic 9 is
     * indistinguishable from Elementor's 9, so the number could no longer say
     * which integration raised it.
     *
     * Kept as a hook point so SpamGate's call site stays meaningful if a
     * submission-wide signal is ever wanted again — with a score of its own.
     */
    public static function inspect(Submission $submission): void
    {
    }
}
