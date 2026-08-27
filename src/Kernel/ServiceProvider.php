<?php

declare(strict_types=1);

namespace Maspik\Kernel;

interface ServiceProvider
{
    /** Register factories into the container. No side effects, no hooks. */
    public function register(Container $container): void;

    /** Attach WordPress hooks. Runs on plugins_loaded. */
    public function boot(Container $container): void;
}
