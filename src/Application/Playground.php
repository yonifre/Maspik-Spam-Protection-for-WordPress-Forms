<?php

declare(strict_types=1);

namespace Maspik\Application;

use Maspik\Domain\Check\HoneypotCheck;
use Maspik\Domain\Check\VerificationKeyCheck;
use Maspik\Domain\Model\Field;
use Maspik\Domain\Model\FieldType;
use Maspik\Domain\Model\Submission;
use Maspik\Domain\Model\TraceEntry;
use Maspik\Infrastructure\Settings\Settings;

/**
 * The Playground: runs the real pipeline in trace mode against a simulated
 * submission. No duplicated detection logic — this IS the engine.
 *
 * The simulated submission carries an empty honeypot and the site's real
 * verification key, as a genuine visitor's browser would — the Playground
 * tests content rules (blacklists, formats, limits, Matrix), not whether the
 * tester itself is a bot, so those two behavioral checks are made to pass
 * rather than swallowing every result.
 */
final class Playground
{
    /** @var CheckFactory */
    private $checkFactory;

    /** @var Settings */
    private $settings;

    public function __construct(CheckFactory $checkFactory, Settings $settings)
    {
        $this->checkFactory = $checkFactory;
        $this->settings = $settings;
    }

    /**
     * @param array{name?: string, email?: string, tel?: string, url?: string, message?: string} $input
     * @return array{blocked: bool, verdict: ?string, trace: array<int, array<string, mixed>>}
     */
    public function simulate(array $input, string $ip): array
    {
        $fields = [];
        $map = [
            'name' => FieldType::TEXT,
            'email' => FieldType::EMAIL,
            'tel' => FieldType::TEL,
            'url' => FieldType::URL,
            'message' => FieldType::TEXTAREA,
        ];
        foreach ($map as $key => $type) {
            if (isset($input[$key]) && $input[$key] !== '') {
                $fields[] = new Field($key, $type, $input[$key]);
            }
        }

        $hidden = [
            HoneypotCheck::FIELD_NAME => '',
            VerificationKeyCheck::FIELD_NAME => $this->settings->spamKey(),
        ];
        $submission = new Submission($fields, 'playground', 'Playground', $ip, $hidden);

        $verdict = $this->checkFactory->pipelineFor($submission)->trace($submission);
        $matrixMode = $this->settings->matrixMode();

        return [
            'blocked' => $verdict->isSpam,
            'verdict' => $verdict->violation !== null ? $verdict->violation->reason : null,
            'trace' => array_map(
                // The depth InputGate ran at is part of what this layer decided,
                // so it travels with the entry rather than being looked up in the
                // browser: read from settings at render time it would describe
                // the current setting, not the run being shown.
                function (TraceEntry $entry) use ($matrixMode): array {
                    return [
                        'check' => $entry->checkId,
                        'field' => $entry->fieldName,
                        'outcome' => $entry->outcome,
                        'reason' => $entry->violation !== null ? $entry->violation->reason : null,
                        'matchedRule' => $entry->violation !== null ? $entry->violation->matchedRule : null,
                        'skipReason' => $entry->skipReason,
                        'matrixMode' => $entry->checkId === 'ai_spam_check' ? $matrixMode : null,
                    ];
                },
                $verdict->trace
            ),
        ];
    }
}
