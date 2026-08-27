<?php

declare(strict_types=1);

namespace Maspik\Application;

use Maspik\Infrastructure\ClientIp;
use Maspik\Infrastructure\Geo\FreeIpApiResolver;
use Maspik\Infrastructure\Matrix\MatrixClient;
use Maspik\Infrastructure\Telemetry\TelemetryCollector;
use Maspik\Infrastructure\Telemetry\TelemetryReporter;
use Maspik\Infrastructure\Reputation\IpReputationResolver;
use Maspik\Infrastructure\Logging\LogRepository;
use Maspik\Premium\License;
use Maspik\Infrastructure\Settings\Settings;
use Maspik\Kernel\Container;
use Maspik\Kernel\ServiceProvider;
use Maspik\Premium\ProGate;

final class EngineProvider implements ServiceProvider
{
    public function register(Container $c): void
    {
        $this->registerTelemetry($c);

        $c->set(Settings::class, static fn () => new Settings());
        $c->set(ClientIp::class, static fn () => new ClientIp());
        $c->set(ProGate::class, static fn () => new ProGate());
        $c->set(FreeIpApiResolver::class, static fn () => new FreeIpApiResolver());
        $c->set(IpReputationResolver::class, static fn () => new IpReputationResolver());
        $c->set(License::class, static fn () => new License());
        $c->set(MatrixClient::class, static fn (Container $c) => new MatrixClient(
            $c->get(Settings::class),
            $c->get(License::class),
            $c->get(ProGate::class)
        ));
        $c->set(LogRepository::class, static fn (Container $c) => new LogRepository(
            $c->get(Settings::class),
            $c->get(FreeIpApiResolver::class)
        ));
        $c->set(CheckFactory::class, static fn (Container $c) => new CheckFactory(
            $c->get(Settings::class),
            $c->get(FreeIpApiResolver::class),
            $c->get(ProGate::class),
            $c->get(IpReputationResolver::class),
            $c->get(MatrixClient::class)
        ));
        $c->set(SpamGate::class, static fn (Container $c) => new SpamGate(
            $c->get(CheckFactory::class),
            $c->get(LogRepository::class),
            $c->get(Settings::class)
        ));
        $c->set(Playground::class, static fn (Container $c) => new Playground(
            $c->get(CheckFactory::class),
            $c->get(Settings::class)
        ));
    }


    /**
     * @param Container $c
     */
    private function registerTelemetry(Container $c): void
    {
        $c->set(TelemetryCollector::class, static fn (Container $c) => new TelemetryCollector(
            $c->get(\Maspik\Infrastructure\Settings\Settings::class),
            $c->get(\Maspik\Infrastructure\Logging\LogRepository::class),
            $c->get(\Maspik\Integrations\Registry::class),
            $c->get(\Maspik\Premium\ProGate::class)
        ));

        $c->set(TelemetryReporter::class, static fn (Container $c) => new TelemetryReporter(
            $c->get(\Maspik\Infrastructure\Settings\Settings::class),
            $c->get(TelemetryCollector::class)
        ));
    }

    public function boot(Container $c): void
    {
        // Refresh the Matrix usage meter whenever license state changes
        // (activate/migrate/deactivate/the twice-daily recheck) — decoupled via
        // a hook rather than injecting MatrixClient into License, which would
        // be circular (MatrixClient already depends on License for its token).
        add_action('maspik/license_changed', static function () use ($c): void {
            $c->get(MatrixClient::class)->refreshUsage();
        });

        // Opt-in telemetry. Registered here rather than in AdminProvider because
        // it runs on cron, which has no admin context.
        $c->get(TelemetryReporter::class)->register();
    }
}
