<?php

declare(strict_types=1);

namespace Maspik\Admin;

use Maspik\Admin\Rest\DashboardController;
use Maspik\Admin\Rest\IntegrationsController;
use Maspik\Admin\Rest\LicenseController;
use Maspik\Admin\Rest\LogsController;
use Maspik\Infrastructure\Feedback\FalsePositiveReporter;
use Maspik\Admin\Rest\PlaygroundController;
use Maspik\Admin\DashboardWidget;
use Maspik\Admin\Rest\RuleTesterController;
use Maspik\Admin\Rest\SettingsController;
use Maspik\Admin\Rest\StatsController;
use Maspik\Application\CheckFactory;
use Maspik\Application\ImportExport;
use Maspik\Application\Playground;
use Maspik\Kernel\Upgrade;
use Maspik\Infrastructure\ClientIp;
use Maspik\Infrastructure\Logging\LogRepository;
use Maspik\Infrastructure\Privacy\PersonalData;
use Maspik\Infrastructure\Settings\Settings;
use Maspik\Integrations\Registry;
use Maspik\Kernel\Container;
use Maspik\Kernel\ServiceProvider;
use Maspik\Premium\License;
use Maspik\Premium\ProGate;

if (! defined('ABSPATH')) {
    exit;
}


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
            $c->get(CheckFactory::class),
            new FalsePositiveReporter()
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
        $c->set(DashboardWidget::class, static fn (Container $c) => new DashboardWidget(
            $c->get(LogRepository::class),
            $c->get(Settings::class),
            $c->get(CheckFactory::class)
        ));
        $c->set(DashboardController::class, static fn (Container $c) => new DashboardController(
            $c->get(Settings::class),
            $c->get(ProGate::class)
        ));
        $c->set(IntegrationsController::class, static fn (Container $c) => new IntegrationsController(
            $c->get(Registry::class),
            $c->get(Settings::class)
        ));
        $c->set(PersonalData::class, static fn (Container $c) => new PersonalData(
            $c->get(LogRepository::class)
        ));
        $c->set(License::class, static fn () => new License());
        $c->set(LicenseController::class, static fn (Container $c) => new LicenseController(
            $c->get(ProGate::class),
            $c->get(License::class)
        ));
    }

    public function boot(Container $c): void
    {
        add_action('admin_menu', \Maspik\Kernel\Guard::wrap(static function () use ($c): void {
            $c->get(Menu::class)->register();
        }));

        // Only fires on index.php, so nothing here is built on other screens.
        add_action('wp_dashboard_setup', \Maspik\Kernel\Guard::wrap(static function () use ($c): void {
            $c->get(DashboardWidget::class)->register();
        }));

        // Keep other plugins' promo/upsell notices off MASPIK's own pages.
        NoticeFilter::boot();

        // Ask IP-only sites, once, whether they want the stronger full check.
        (new FullModeNudge($c->get(Settings::class)))->register();

        // Puts the spam log inside Tools → Export/Erase Personal Data. Hooked on
        // every admin request rather than the tool screen alone, because the
        // export and erasure themselves run in batches over admin-ajax.
        $c->get(PersonalData::class)->register();

        add_action(DashboardController::CRON_HOOK, \Maspik\Kernel\Guard::wrap(static function () use ($c): void {
            $c->get(DashboardController::class)->sync();
        }));

        add_action(License::CRON_HOOK, \Maspik\Kernel\Guard::wrap(static function () use ($c): void {
            $c->get(License::class)->recheck();
        }));

        // Daily age-based log pruning (no-op unless spam_log_max_age_days is set).
        add_action('maspik_log_prune', \Maspik\Kernel\Guard::wrap(static function () use ($c): void {
            $c->get(LogRepository::class)->pruneByAge();
        }));
        if (! wp_next_scheduled('maspik_log_prune')) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', 'maspik_log_prune');
        }

        // rest_api_init runs for every REST request WordPress serves, ours and
        // everyone else's. An exception thrown here is not contained to our
        // routes: it aborts rest_get_server(), so the block editor, Elementor's
        // editor and every other consumer of the API get a 500 as well. That is
        // exactly how a missing class file on one site turned into "Elementor
        // stopped loading" in a bug report.
        //
        // Registering routes is not worth that blast radius. If building a
        // controller fails, our endpoints are simply absent - the admin screens
        // that call them show their own errors, and the rest of the site's REST
        // API is untouched.
        add_action('rest_api_init', \Maspik\Kernel\Guard::wrap(static function () use ($c): void {
            $controllers = [
                SettingsController::class,
                LogsController::class,
                PlaygroundController::class,
                StatsController::class,
                RuleTesterController::class,
                DashboardController::class,
                IntegrationsController::class,
                LicenseController::class,
            ];

            foreach ($controllers as $controller) {
                try {
                    $c->get($controller)->registerRoutes();
                } catch (\Throwable $e) {
                    // Per controller, so one broken endpoint does not take the
                    // other seven with it.
                    continue;
                }
            }
        }));
    }
}
