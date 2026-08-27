<?php

declare(strict_types=1);

namespace Maspik\Admin;

use Maspik\Admin\Rest\DashboardController;
use Maspik\Admin\Rest\IntegrationsController;
use Maspik\Admin\Rest\LicenseController;
use Maspik\Admin\Rest\LogsController;
use Maspik\Admin\Rest\PlaygroundController;
use Maspik\Admin\Rest\RuleTesterController;
use Maspik\Admin\Rest\SettingsController;
use Maspik\Admin\Rest\StatsController;
use Maspik\Application\CheckFactory;
use Maspik\Application\ImportExport;
use Maspik\Application\Playground;
use Maspik\Kernel\Upgrade;
use Maspik\Infrastructure\ClientIp;
use Maspik\Infrastructure\Logging\LogRepository;
use Maspik\Infrastructure\Settings\Settings;
use Maspik\Integrations\Registry;
use Maspik\Kernel\Container;
use Maspik\Kernel\ServiceProvider;
use Maspik\Premium\License;
use Maspik\Premium\ProGate;

final class AdminProvider implements ServiceProvider
{
    public function register(Container $c): void
    {
        // The preloaders let Menu embed the same payloads the REST routes return,
        // so React paints saved settings on the first frame instead of defaults.
        // Closures keep the controllers unbuilt on admin screens that never ask.
        $c->set(Menu::class, static fn (Container $c) => new Menu($c->get(LogRepository::class), [
            'settings' => static fn () => $c->get(SettingsController::class)->index()->get_data(),
            'license' => static fn () => $c->get(LicenseController::class)->index()->get_data(),
            'integrations' => static fn () => $c->get(IntegrationsController::class)->index()->get_data(),
        ]));
        $c->set(ImportExport::class, static fn (Container $c) => new ImportExport(
            $c->get(Settings::class)
        ));
        $c->set(SettingsController::class, static fn (Container $c) => new SettingsController(
            $c->get(Settings::class),
            $c->get(ImportExport::class)
        ));
        $c->set(LogsController::class, static fn (Container $c) => new LogsController(
            $c->get(LogRepository::class),
            $c->get(Settings::class),
            $c->get(CheckFactory::class)
        ));
        $c->set(PlaygroundController::class, static fn (Container $c) => new PlaygroundController(
            $c->get(Playground::class),
            $c->get(ClientIp::class)
        ));
        $c->set(StatsController::class, static fn (Container $c) => new StatsController(
            $c->get(LogRepository::class),
            $c->get(ProGate::class)
        ));
        $c->set(RuleTesterController::class, static fn () => new RuleTesterController($c->get(Settings::class)));
        $c->set(DashboardController::class, static fn (Container $c) => new DashboardController(
            $c->get(Settings::class),
            $c->get(ProGate::class)
        ));
        $c->set(IntegrationsController::class, static fn (Container $c) => new IntegrationsController(
            $c->get(Registry::class),
            $c->get(Settings::class)
        ));
        $c->set(License::class, static fn () => new License());
        $c->set(LicenseController::class, static fn (Container $c) => new LicenseController(
            $c->get(ProGate::class),
            $c->get(License::class)
        ));
    }

    public function boot(Container $c): void
    {
        add_action('admin_menu', static function () use ($c): void {
            $c->get(Menu::class)->register();
        });

        // Keep other plugins' promo/upsell notices off MASPIK's own pages.
        NoticeFilter::boot();

        // Ask IP-only sites, once, whether they want the stronger full check.
        (new FullModeNudge($c->get(Settings::class)))->register();

        add_action(DashboardController::CRON_HOOK, static function () use ($c): void {
            $c->get(DashboardController::class)->sync();
        });

        add_action(License::CRON_HOOK, static function () use ($c): void {
            $c->get(License::class)->recheck();
        });

        // Daily age-based log pruning (no-op unless spam_log_max_age_days is set).
        add_action('maspik_log_prune', static function () use ($c): void {
            $c->get(LogRepository::class)->pruneByAge();
        });
        if (! wp_next_scheduled('maspik_log_prune')) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', 'maspik_log_prune');
        }

        add_action('rest_api_init', static function () use ($c): void {
            $c->get(SettingsController::class)->registerRoutes();
            $c->get(LogsController::class)->registerRoutes();
            $c->get(PlaygroundController::class)->registerRoutes();
            $c->get(StatsController::class)->registerRoutes();
            $c->get(RuleTesterController::class)->registerRoutes();
            $c->get(DashboardController::class)->registerRoutes();
            $c->get(IntegrationsController::class)->registerRoutes();
            $c->get(LicenseController::class)->registerRoutes();
        });
    }
}
