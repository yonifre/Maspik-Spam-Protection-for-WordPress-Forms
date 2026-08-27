<?php

declare(strict_types=1);

namespace Maspik\Application;

use Maspik\Domain\AllowList;
use Maspik\Domain\GuardPolicy;
use Maspik\Domain\Model\FieldType;
use Maspik\Domain\Model\Submission;
use Maspik\Infrastructure\Geo\FreeIpApiResolver;
use Maspik\Infrastructure\Matrix\MatrixClient;
use Maspik\Infrastructure\Reputation\IpReputationResolver;
use Maspik\Infrastructure\Settings\Settings;
use Maspik\Premium\ProGate;

/**
 * Translates effective settings (local ⊕ dashboard, v2 precedence) into an
 * EngineConfig, then delegates ordered pipeline assembly to PipelineBuilder.
 *
 * This class is the WordPress-facing boundary (it reads settings and the geo
 * client); the behavior-defining part — which checks run and in what order —
 * lives in the pure PipelineBuilder, so it can be parity-tested without WP.
 */
final class CheckFactory
{
    /** @var Settings */
    private $settings;

    /** @var FreeIpApiResolver */
    private $geo;

    /** @var ProGate */
    private $pro;

    /** @var IpReputationResolver */
    private $reputation;

    /** @var MatrixClient */
    private $matrix;

    public function __construct(Settings $settings, FreeIpApiResolver $geo, ProGate $pro, IpReputationResolver $reputation, MatrixClient $matrix)
    {
        $this->settings = $settings;
        $this->geo = $geo;
        $this->pro = $pro;
        $this->reputation = $reputation;
        $this->matrix = $matrix;
    }

    public function pipelineFor(Submission $submission): CheckPipeline
    {
        $config = $this->configFor($submission);

        $submissionChecks = apply_filters(
            'maspik/submission_checks',
            PipelineBuilder::submissionChecks($config),
            $submission
        );
        $fieldChecks = apply_filters(
            'maspik/field_checks',
            PipelineBuilder::fieldChecks($config)
        );
        $lateChecks = apply_filters(
            'maspik/late_checks',
            PipelineBuilder::lateChecks($config),
            $submission
        );

        return new CheckPipeline($submissionChecks, $fieldChecks, $lateChecks);
    }

    /**
     * The canonical check order for the site's current effective settings, as
     * check ids — the SAME construction pipelineFor() uses, just flattened to
     * ids. Built from a generic guard-carrying submission, since order doesn't
     * depend on submitted values (only inclusion for a few Pro/enabled checks
     * does, which this still reflects correctly).
     *
     * The single source of truth the Logs UI reads to reconstruct "what ran
     * before/after" for a stored block, instead of keeping its own copy that
     * can silently drift from the real engine.
     *
     * @return string[]
     */
    public function pipelineOrder(): array
    {
        $generic = new Submission([], 'generic', 'Generic', '');
        $config = $this->configFor($generic);

        return PipelineBuilder::checkOrder($config);
    }

    /** User-curated allow list (Logs actions). Checked before the pipeline. */
    public function allowList(): AllowList
    {
        return new AllowList(
            $this->settings->list('ip_whitelist'),
            $this->settings->list('emails_whitelist')
        );
    }

    /** Build the WordPress-free description of the active checks. */
    private function configFor(Submission $submission): EngineConfig
    {
        $s = $this->settings;
        $config = new EngineConfig();

        // Honeypot / advanced key — skipped for sources that can't carry them.
        if (GuardPolicy::carriesGuardFields($submission->source)) {
            $config->honeypot = $s->boolEffective('maspikHoneypot');
            if ($s->boolEffective('verification_key')) {
                $config->advancedKey = $s->spamKey();
            }
        }

        // Country (Pro).
        if ($this->pro->supports('country_location')) {
            list($mode, $list) = $s->countryRules();
            if ($list !== []) {
                $config->country = [
                    'mode' => $mode,
                    'list' => $list,
                    'resolver' => $this->geo->resolver(),
                ];
            }
        }

        $config->ipBlocklist = $s->list('ip_blacklist', true);

        // External IP reputation (AbuseIPDB then Proxycheck). Each runs only
        // with an API key and a threshold above the v2 safety floor of 10.
        $abuseKey = $s->stringEffective('abuseipdb_api');
        $abuseScore = (int) $s->stringEffective('abuseipdb_score');
        if ($abuseKey !== '' && $abuseScore > 10) {
            $config->reputation[] = [
                'resolver' => $this->reputation->abuseipdb($abuseKey),
                'threshold' => $abuseScore,
                'checkId' => 'abuseipdb_api',
                'label' => 'AbuseIPDB',
            ];
        }
        $proxyKey = $s->stringEffective('proxycheck_io_api');
        $proxyRisk = (int) $s->stringEffective('proxycheck_io_risk');
        if ($proxyKey !== '' && $proxyRisk > 10) {
            $config->reputation[] = [
                'resolver' => $this->reputation->proxycheck($proxyKey),
                'threshold' => $proxyRisk,
                'checkId' => 'proxycheck_io_api',
                'label' => 'Proxycheck.io',
            ];
        }

        // Field checks.
        $config->text = $s->list('text_blacklist', true);
        $config->email = $s->list('emails_blacklist', true);
        $config->url = $s->list('url_blacklist', true);

        // Each limit runs when the site switched it on, OR when the Dashboard
        // supplies a value for it. v2 gated these the same way
        // (maspik_is_contain_api): a limit pushed centrally was meant to apply
        // without every site also having to flip its own toggle, which is the
        // whole point of managing rules from one place.
        if ($s->bool('text_limit_toggle')
            || $s->dashboardProvides(['MinCharactersInTextField', 'MaxCharactersInTextField'])) {
            $config->charLimits[] = [
                'type' => FieldType::TEXT,
                'min' => $s->intOrNull('MinCharactersInTextField'),
                'max' => $s->intOrNull('MaxCharactersInTextField'),
            ];
        }
        if ($s->bool('textarea_limit_toggle')
            || $s->dashboardProvides(['MinCharactersInTextAreaField', 'MaxCharactersInTextAreaField'])) {
            $config->charLimits[] = [
                'type' => FieldType::TEXTAREA,
                'min' => $s->intOrNull('MinCharactersInTextAreaField'),
                'max' => $s->intOrNull('MaxCharactersInTextAreaField'),
            ];
        }
        if ($s->bool('tel_limit_toggle')
            || $s->dashboardProvides(['MinCharactersInPhoneField', 'MaxCharactersInPhoneField'])) {
            $config->charLimits[] = [
                'type' => FieldType::TEL,
                'min' => $s->intOrNull('MinCharactersInPhoneField'),
                'max' => $s->intOrNull('MaxCharactersInPhoneField'),
            ];
        }

        if (($s->bool('textarea_link_limit_toggle') || $s->dashboardProvides(['contain_links']))
            && $s->intOrNull('contain_links') !== null) {
            $config->linkLimit = (int) $s->intOrNull('contain_links');
        }

        $config->emoji = $s->bool('emoji_check');

        if ($this->pro->supports('country_location')) {
            $required = $s->list('lang_needed', true);
            $forbidden = $s->list('lang_forbidden', true);
            if ($required !== [] || $forbidden !== []) {
                $config->language = ['required' => $required, 'forbidden' => $forbidden];
            }
        }

        // Phone is always present (empty formats => everything valid, as v2).
        $config->phone = [
            'formats' => $s->list('tel_formats', true),
        ];

        // Maspik Matrix (InputGate) — cloud check, only when enabled. Appended
        // last by PipelineBuilder so it runs after every cheaper local check.
        if ($s->boolEffective('maspik_ai_enabled')) {
            $config->matrix = [
                'resolver' => $this->matrix->resolver($s->matrixMode()),
            ];
        }

        return $config;
    }
}
