<?php

declare(strict_types=1);

namespace Maspik\Application;

use Maspik\Domain\Check\VerificationKeyCheck;
use Maspik\Domain\Check\CharacterLimitCheck;
use Maspik\Domain\Check\CountryCheck;
use Maspik\Domain\Check\EmailBlacklistCheck;
use Maspik\Domain\Check\EmojiCheck;
use Maspik\Domain\Check\HoneypotCheck;
use Maspik\Domain\Check\IpBlocklistCheck;
use Maspik\Domain\Check\IpReputationCheck;
use Maspik\Domain\Check\MatrixCheck;
use Maspik\Domain\Check\LanguageCheck;
use Maspik\Domain\Check\LinkLimitCheck;
use Maspik\Domain\Check\PhoneCheck;
use Maspik\Domain\Check\TextBlacklistCheck;
use Maspik\Domain\Check\UrlBlacklistCheck;
use Maspik\Domain\Model\FieldType;

/**
 * Assembles a CheckPipeline from an EngineConfig. Pure (no WordPress) so it is
 * fully exercised by the parity harness.
 *
 * THE ORDER DEFINED HERE IS THE DETECTION BEHAVIOR. It mirrors v2.9.x:
 * submission-level checks (honeypot → advanced key → country → IP) first, then
 * per-field checks (blacklists → length limits → links → emoji → language →
 * phone). First violation wins.
 */
final class PipelineBuilder
{
    public static function build(EngineConfig $config): CheckPipeline
    {
        return new CheckPipeline(
            self::submissionChecks($config),
            self::fieldChecks($config)
        );
    }

    /** @return \Maspik\Domain\Check\SubmissionCheck[] */
    public static function submissionChecks(EngineConfig $config): array
    {
        $checks = [];

        if ($config->honeypot) {
            $checks[] = new HoneypotCheck();
        }
        if ($config->advancedKey !== null) {
            $checks[] = new VerificationKeyCheck($config->advancedKey);
        }
        if ($config->country !== null) {
            $checks[] = new CountryCheck(
                $config->country['mode'],
                $config->country['list'],
                $config->country['resolver']
            );
        }
        $checks[] = new IpBlocklistCheck($config->ipBlocklist);

        // External IP reputation (AbuseIPDB, then Proxycheck), as ordered by
        // CheckFactory.
        foreach ($config->reputation as $rep) {
            $checks[] = new IpReputationCheck(
                $rep['resolver'],
                $rep['threshold'],
                $rep['checkId'],
                $rep['label']
            );
        }

        return $checks;
    }

    /**
     * Checks that run after field checks — the Maspik Matrix cloud call, kept
     * last so the paid round-trip only fires once every local check has passed.
     *
     * @return \Maspik\Domain\Check\SubmissionCheck[]
     */
    public static function lateChecks(EngineConfig $config): array
    {
        $checks = [];
        if ($config->matrix !== null) {
            $checks[] = new MatrixCheck($config->matrix['resolver']);
        }

        return $checks;
    }

    /** @return \Maspik\Domain\Check\FieldCheck[] */
    public static function fieldChecks(EngineConfig $config): array
    {
        $checks = [];

        $checks[] = new TextBlacklistCheck($config->text);
        $checks[] = new EmailBlacklistCheck($config->email);
        $checks[] = new UrlBlacklistCheck($config->url);

        foreach ($config->charLimits as $limit) {
            $checks[] = new CharacterLimitCheck($limit['type'], $limit['min'], $limit['max']);
        }

        if ($config->linkLimit !== null) {
            $checks[] = new LinkLimitCheck($config->linkLimit);
        }
        if ($config->emoji) {
            $checks[] = new EmojiCheck();
        }
        if ($config->language !== null) {
            $checks[] = new LanguageCheck($config->language['required'], $config->language['forbidden']);
        }
        if ($config->phone !== null) {
            $checks[] = new PhoneCheck($config->phone['formats']);
        }

        return $checks;
    }

    /** Field-type constants, re-exported for callers assembling char limits. */
    public static function fieldTypes(): array
    {
        return [FieldType::TEXT, FieldType::TEXTAREA, FieldType::TEL];
    }

    /**
     * Every protection layer that *can* exist, in pipeline order — regardless of
     * whether it's active for a given request. The trace assembler diffs this
     * against the layers that actually ran to mark the rest DISABLED.
     *
     * @return string[]
     */
    public static function layerCatalog(): array
    {
        return [
            'maspikHoneypot',
            'verification_key',
            'country_blacklist',
            'ip_blacklist',
            'abuseipdb_api',
            'proxycheck_io_api',
            'text_blacklist',
            'emails_blacklist',
            'url_blacklist',
            'MaxCharactersInTextField',
            'MaxCharactersInTextAreaField',
            'MaxCharactersInPhoneField',
            'contain_links',
            'emoji_check',
            'lang_needed',
            'tel_formats',
            'ai_spam_check',
        ];
    }

    /**
     * The canonical check order for the given config, as check ids — the same
     * construction this class uses to build the real pipeline, just flattened
     * to ids instead of instances. THE single source of truth for "what order
     * do checks run in": callers (the Logs REST response, today) read this
     * instead of keeping their own copy, so it cannot drift from the engine.
     *
     * @return string[]
     */
    public static function checkOrder(EngineConfig $config): array
    {
        $checks = array_merge(
            self::submissionChecks($config),
            self::fieldChecks($config),
            self::lateChecks($config)
        );

        $ids = [];
        foreach ($checks as $check) {
            $id = $check->id();
            if (! in_array($id, $ids, true)) {
                $ids[] = $id;
            }
        }

        return $ids;
    }
}
