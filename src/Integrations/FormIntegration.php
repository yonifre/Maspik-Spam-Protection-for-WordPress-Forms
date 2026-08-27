<?php

declare(strict_types=1);

namespace Maspik\Integrations;

use Maspik\Application\SpamGate;

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

    /** Attach hooks. Only called when available + enabled (+ Pro when required). */
    public function register(SpamGate $gate): void;
}
