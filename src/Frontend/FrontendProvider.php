<?php

declare(strict_types=1);

namespace Maspik\Frontend;

use Maspik\Infrastructure\Settings\Settings;
use Maspik\Kernel\Container;
use Maspik\Kernel\ServiceProvider;

final class FrontendProvider implements ServiceProvider
{
    public function register(Container $c): void
    {
        $c->set(ScriptInjector::class, static fn (Container $c) => new ScriptInjector(
            $c->get(Settings::class)
        ));
    }

    public function boot(Container $c): void
    {
        add_action('wp_enqueue_scripts', static function () use ($c): void {
            $c->get(ScriptInjector::class)->enqueue();
        });
        // v2 also injected on the login/registration screen.
        add_action('login_enqueue_scripts', static function () use ($c): void {
            $c->get(ScriptInjector::class)->enqueue();
        });
    }
}
