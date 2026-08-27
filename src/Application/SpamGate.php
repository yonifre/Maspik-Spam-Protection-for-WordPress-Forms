<?php

declare(strict_types=1);

namespace Maspik\Application;

use Maspik\Domain\Model\Submission;
use Maspik\Domain\Model\Verdict;
use Maspik\Infrastructure\Logging\LayerStatus;
use Maspik\Infrastructure\Logging\LogRepository;
use Maspik\Infrastructure\Matrix\DirectPostSignal;
use Maspik\Infrastructure\Settings\Settings;

/**
 * The single entry point every form integration calls.
 * Evaluate → log → count → notify. Nothing else lives here.
 */
final class SpamGate
{
    /** @var CheckFactory */
    private $checkFactory;

    /** @var LogRepository */
    private $logs;

    /** @var Settings */
    private $settings;

    public function __construct(CheckFactory $checkFactory, LogRepository $logs, Settings $settings)
    {
        $this->checkFactory = $checkFactory;
        $this->logs = $logs;
        $this->settings = $settings;
    }

    public function evaluate(Submission $submission): Verdict
    {
        /**
         * Pre-check bypass, successor of the per-plugin filters
         * (maspik_disable_cf7_spam_check et al., which keep firing via the
         * legacy bridge in each adapter).
         */
        // Fresh side-channel for external-layer statuses (timeout/skipped) that
        // the resolvers record during this evaluation.
        LayerStatus::reset();
        DirectPostSignal::reset();

        if (apply_filters('maspik/skip_check', false, $submission)) {
            return Verdict::clean();
        }

        // "Direct POST attack": raise the Matrix suspicion floor when the
        // request carries no page context. Always on — it never blocks on its
        // own, it only scores, so there is nothing for a user to tune. Adapters
        // may raise it further before the Matrix call.
        DirectPostSignal::inspect($submission);

        /**
         * Form-specific direct-POST evidence. Adapters return a 1–9 score when
         * their plugin's own page-context field is missing (Elementor's
         * `referrer`, CF7's `_wpcf7` id, …). Runs after reset() so the score
         * survives into the Matrix payload.
         *
         * @param int        $score      1 = no suspicion
         * @param Submission $submission the submission being evaluated
         */
        $score = (int) apply_filters('maspik/direct_post_score', 1, $submission);
        if ($score > 1) {
            /**
             * The sentinel that goes out as `maspik_referrer` alongside the
             * score. The score says how suspicious and which integration; the
             * sentinel says which specific marker was missing, so the server can
             * tell `no_state_token` from `no_gform_submit` without guessing.
             * v2 sent both; sending only the number loses that detail.
             *
             * @param string|null $sentinel   e.g. 'no_cf7_id'
             * @param Submission  $submission the submission being evaluated
             */
            $sentinel = apply_filters('maspik/direct_post_referrer', null, $submission);
            DirectPostSignal::raise($score, is_string($sentinel) && $sentinel !== '' ? $sentinel : null);
        }

        // User-whitelisted senders (from the Logs "Not spam" action) never block.
        if ($this->checkFactory->allowList()->allows($submission)) {
            return Verdict::clean();
        }

        $verdict = $this->checkFactory->pipelineFor($submission)->evaluate($submission);
        $mode = $this->settings->logMode();

        if ($verdict->isSpam && $verdict->violation !== null) {
            if ($mode === 'none') {
                // Still count the block for the dashboard total, just don't store it.
                $this->logs->incrementBlockedCounter();
            } else {
                $this->logs->record($submission, $verdict->violation, 'blocked', $this->trace($verdict));
            }

            /** Fires after a submission was blocked. */
            do_action('maspik/blocked', $submission, $verdict);
        } elseif ($mode === 'all') {
            // Debug mode: log the passed submission so the user can see why it
            // wasn't caught (which layers ran, which were disabled/skipped).
            $this->logs->record($submission, null, 'clean', $this->trace($verdict));

            /** Fires after a submission passed every check (debug logging mode). */
            do_action('maspik/passed', $submission, $verdict);
        }

        return $verdict;
    }

    /**
     * @return array<int, array{layer: string, status: string, reason: string}>
     */
    private function trace(Verdict $verdict): array
    {
        return TraceAssembler::assemble($verdict->trace, LayerStatus::all());
    }

    /** Error message shown to the sender, honoring per-check custom messages. */
    public function errorMessage(Verdict $verdict): string
    {
        $checkId = $verdict->violation !== null ? $verdict->violation->checkId : '';
        $custom = $this->settings->customErrorMessage($checkId);

        return $custom !== '' ? $custom : $this->settings->defaultErrorMessage();
    }
}
