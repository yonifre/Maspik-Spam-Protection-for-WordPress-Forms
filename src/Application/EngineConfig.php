<?php

declare(strict_types=1);

namespace Maspik\Application;

/**
 * A plain, WordPress-free description of which checks are active for one
 * request and with what parameters. CheckFactory produces it from settings;
 * PipelineBuilder turns it into an ordered CheckPipeline.
 *
 * The split exists so the exact detection behavior (which checks, in which
 * order) can be exercised by the parity harness without WordPress.
 */
final class EngineConfig
{
    /** @var bool */
    public $honeypot = false;

    /** @var string|null expected advanced-key value; null = check disabled */
    public $advancedKey = null;

    /** @var array{mode: string, list: string[], resolver: callable}|null */
    public $country = null;

    /** @var string[] IPs and/or CIDR ranges */
    public $ipBlocklist = [];

    /**
     * External IP-reputation checks (AbuseIPDB, Proxycheck), in order.
     *
     * @var array<int, array{resolver: callable, threshold: int, checkId: string, label: string}>
     */
    public $reputation = [];

    /** @var string[] */
    public $text = [];

    /** @var string[] */
    public $email = [];

    /** @var string[] */
    public $url = [];

    /**
     * Active character-limit checks, in order.
     *
     * @var array<int, array{type: string, min: int|null, max: int|null}>
     */
    public $charLimits = [];

    /** @var int|null max links; null = check disabled */
    public $linkLimit = null;

    /** @var bool */
    public $emoji = false;

    /** @var array{required: string[], forbidden: string[]}|null */
    public $language = null;

    /** @var array{formats: string[]}|null */
    public $phone = null;

    /** @var array{resolver: callable}|null Maspik Matrix cloud check; null = off */
    public $matrix = null;
}
