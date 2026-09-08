<?php

declare(strict_types=1);

namespace Maspik\Integrations;

use Maspik\Application\SpamGate;

if (! defined('ABSPATH')) {
    exit;
}


/**
 * One adapter per form plugin. An adapter's whole job:
 *  1. hook the plugin's validation point
 *  2. extract fields into a Submission (typed via FieldType)
 *  3. call SpamGate::evaluate()
 *  4. map a spam Verdict onto the plugin's own error mechanism
 * No detection logic ever lives in an adapter.
 */
interface FormIntegration
{
    /** Stable id, e.g. 'cf7'. Used for source tagging and the support toggle. */
    public function id(): string;

    /** Human label shown in the admin and logged as spam_source. */
    public function label(): string;

    /** The v2 support-toggle option key, e.g. 'maspik_support_cf7'. */
    public function toggleKey(): string;

    /** Is the target form plugin installed and active? */
    public function isAvailable(): bool;

    /** Whether this integration requires a MASPIK Pro license to run. */
    public function pro(): bool;

    /**
     * Whether this integration is opt-in: off until explicitly enabled, rather
     * than the usual "on unless disabled". Used for checkout-critical flows.
     */
    public function optIn(): bool;

    /**
     * Whether the screen should ask the owner to confirm a real submission
     * still completes after switching this on.
     *
     * For adapters that stand between a visitor and an account or a payment and
     * have not been proven against a live install. Getting one of those wrong
     * costs more than a missed spam message, and the person best placed to
     * check is the one who just enabled it.
     */
    public function needsVerification(): bool;

    /** Attach hooks. Only called when available + enabled (+ Pro when required). */
    public function register(SpamGate $gate): void;
}
