<?php

declare(strict_types=1);

namespace Maspik\Kernel;

use Maspik\Admin\AdminProvider;
use Maspik\Application\EngineProvider;
use Maspik\Frontend\FrontendProvider;
use Maspik\Integrations\IntegrationsProvider;

/**
 * Plugin entry point. The only global state in the codebase.
 */
final class Plugin
{
    private static ?Plugin $instance = null;

    private Container $container;

    /** @var ServiceProvider[] */
    private array $providers;

    private function __construct()
    {
        $this->container = new Container();
        $this->providers = [
            new EngineProvider(),
            new IntegrationsProvider(),
            new FrontendProvider(),
            new AdminProvider(),
        ];
    }

    public static function boot(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
            self::$instance->run();
        }

        return self::$instance;
    }

    public static function container(): Container
    {
        return self::boot()->container;
    }

    private function run(): void
    {
        // Migrations run on every kind of request, not just wp-admin. An
        // in-place wp.org update fires no activation hook, so until this runs
        // the site is on v3 code with unmigrated v2 state — and the front end
        // is exactly where that hurts: Pro is not detected yet, so premium
        // integrations are skipped and those forms go unprotected. Hooked on
        // `init` so options, $wpdb and the textdomain are all ready, and ahead
        // of any form submission, which can only arrive later in the request.
        add_action('init', [Upgrade::class, 'maybeRun'], 1);

        foreach ($this->providers as $provider) {
            $provider->register($this->container);
        }
        foreach ($this->providers as $provider) {
            $provider->boot($this->container);
        }

        /**
         * Fires when MASPIK is fully booted. Third parties can register
         * custom checks / integrations here.
         *
         * @param Container $container
         */
        do_action('maspik/booted', $this->container);
    }
}
