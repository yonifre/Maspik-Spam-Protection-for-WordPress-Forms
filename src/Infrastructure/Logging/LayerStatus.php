<?php

declare(strict_types=1);

namespace Maspik\Infrastructure\Logging;

/**
 * Request-scoped side-channel for the *outcome* of external protection layers
 * (Matrix, IP reputation, geo) — timeout / error / skipped — which the pure
 * domain checks can't express (they only return "block or pass").
 *
 * The infrastructure resolvers record here as they call out; SpamGate resets it
 * at the start of each evaluation and reads it when assembling the stored trace.
 * Deliberately static: this is ephemeral per-request diagnostic metadata, not
 * application state, so threading a collector through every resolver
 * constructor would add noise for no benefit.
 */
final class LayerStatus
{
    public const TIMEOUT = 'timeout';
    public const ERROR = 'error';
    public const SKIPPED = 'skipped';

    /** @var array<string, array{status: string, reason: string}> */
    private static $statuses = [];

    public static function reset(): void
    {
        self::$statuses = [];
    }

    public static function record(string $layerId, string $status, string $reason = ''): void
    {
        self::$statuses[$layerId] = ['status' => $status, 'reason' => $reason];
    }

    /** @return array<string, array{status: string, reason: string}> */
    public static function all(): array
    {
        return self::$statuses;
    }
}
