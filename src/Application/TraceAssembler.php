<?php

declare(strict_types=1);

namespace Maspik\Application;

use Maspik\Domain\Model\TraceEntry;

/**
 * Turns a raw pipeline trace into the per-layer status list stored with a log
 * row and rendered in the Logs UI.
 *
 * Three inputs merge:
 *  - the layers that actually ran (from the Verdict trace) → PASS / BLOCKED /
 *    NOT_APPLICABLE / NOT_REACHED;
 *  - the canonical layer catalog → any layer that didn't run is DISABLED;
 *  - the LayerStatus side-channel (written by the external resolvers) →
 *    upgrades a PASS to TIMEOUT / ERROR / SKIPPED with a reason.
 *
 * Output is a compact, label-agnostic array (statuses + reasons keyed by layer
 * id); the frontend maps ids to localized names.
 *
 * @phpstan-type TraceRow array{layer: string, status: string, reason: string}
 */
final class TraceAssembler
{
    public const PASS = 'PASS';
    public const BLOCKED = 'BLOCKED';
    public const DISABLED = 'DISABLED';
    public const SKIPPED = 'SKIPPED';
    public const NOT_APPLICABLE = 'NOT_APPLICABLE';
    public const TIMEOUT = 'TIMEOUT';
    public const ERROR = 'ERROR';
    public const NOT_REACHED = 'NOT_REACHED';

    /**
     * @param TraceEntry[] $recorded
     * @param array<string, array{status: string, reason: string}> $layerStatus
     * @return array<int, array{layer: string, status: string, reason: string}>
     */
    public static function assemble(array $recorded, array $layerStatus): array
    {
        $byId = [];
        foreach ($recorded as $entry) {
            $byId[$entry->checkId] = $entry;
        }

        $rows = [];
        foreach (PipelineBuilder::layerCatalog() as $id) {
            if (! isset($byId[$id])) {
                $rows[] = ['layer' => $id, 'status' => self::DISABLED, 'reason' => ''];
                continue;
            }
            $rows[] = self::rowFor($id, $byId[$id], isset($layerStatus[$id]) ? $layerStatus[$id] : null);
        }

        return $rows;
    }

    /**
     * @param array{status: string, reason: string}|null $external
     * @return array{layer: string, status: string, reason: string}
     */
    private static function rowFor(string $id, TraceEntry $entry, ?array $external): array
    {
        switch ($entry->outcome) {
            case TraceEntry::HIT:
                return ['layer' => $id, 'status' => self::BLOCKED, 'reason' => $entry->violation !== null ? $entry->violation->reason : ''];
            case TraceEntry::NOT_APPLICABLE:
                return ['layer' => $id, 'status' => self::NOT_APPLICABLE, 'reason' => ''];
            case TraceEntry::NOT_REACHED:
                return ['layer' => $id, 'status' => self::NOT_REACHED, 'reason' => ''];
            case TraceEntry::SKIPPED:
                return ['layer' => $id, 'status' => self::SKIPPED, 'reason' => (string) $entry->skipReason];
            default:
                // PASS — but an external layer may actually have timed out / been
                // skipped; the resolver-recorded status is the truth in that case.
                if ($external !== null) {
                    return ['layer' => $id, 'status' => strtoupper($external['status']), 'reason' => $external['reason']];
                }

                return ['layer' => $id, 'status' => self::PASS, 'reason' => ''];
        }
    }
}
