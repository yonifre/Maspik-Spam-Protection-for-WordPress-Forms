<?php

declare(strict_types=1);

namespace Maspik\Kernel;

if (! defined('ABSPATH')) {
    exit;
}


/**
 * Wraps every callback this plugin hands to WordPress, so that when our code
 * breaks it breaks on its own and not on the site.
 *
 * The problem it answers came in as support reports - a white screen straight
 * after logging in, and every REST request on a site returning 500 - and was
 * then measured. Deleting each of this plugin's files in turn, the way a
 * half-finished update leaves an install, 36 of them took something down with
 * them: 3 took every page on the site, 17 took all of wp-admin (the Dashboard
 * widget builds the engine, so any engine class missing killed the first page
 * after login), and the rest fatalled the host plugin's form on submit.
 *
 * None of those died inside Plugin::boot(), which maspik.php already guards.
 * They died later, in callbacks boot() had registered - on init, admin_menu,
 * wp_dashboard_setup, cron, and the form plugins' own validation hooks. Each is
 * a hook shared with the rest of the site, so an uncaught error in our callback
 * aborts that hook for everyone.
 *
 * WHAT IS CAUGHT, AND WHAT IS NOT
 *
 * Only \Error. A class that failed to load, a TypeError, an ArgumentCountError:
 * those are defects, ours or the environment's, and the site should outlive
 * them.
 *
 * \Exception is deliberately left alone. Some host plugins take an exception as
 * their answer - JetFormBuilder's Request_Exception and the WooCommerce Store
 * API's RouteException are how the integrations for those plugins refuse a
 * submission. Catching those would turn "blocked" into "delivered", silently,
 * which is the one failure worse than a crash for a spam filter.
 *
 * WHAT A CAUGHT FAILURE DOES
 *
 * The callback returns its first argument unchanged. For a filter that is the
 * value WordPress would have had without us; for an action the return value is
 * ignored. Either way the site behaves as if this plugin were not installed at
 * that point, and the failure is recorded so the site owner is told.
 *
 * The wrapper itself is safe to rely on at the moment it matters: it is created
 * while boot() registers hooks, so this class is already in memory by the time
 * any wrapped callback runs. Only the code it calls can be missing by then.
 */
final class Guard
{
    /** wp_options key shared with maspik.php, which reads it without loading this class. */
    public const OPTION = 'maspik_failure';

    public static function wrap(callable $callback): \Closure
    {
        return static function (...$args) use ($callback) {
            try {
                return $callback(...$args);
            } catch (\Error $e) {
                self::record($e);

                return $args[0] ?? null;
            }
        };
    }

    /**
     * Remember the failure so maspik.php can show it on the next admin page.
     *
     * A broken install fails on every request, and one option write per request
     * is a cost nobody asked for, so a failure already on record is left alone.
     * Except once a day: the notice expires a week after the last time it was
     * seen, and it needs to know the problem is still happening.
     */
    public static function record(\Throwable $e): void
    {
        $detail = sprintf(
            '%s: %s (%s:%d)',
            get_class($e),
            $e->getMessage(),
            $e->getFile(),
            $e->getLine()
        );

        $stored = get_option(self::OPTION);
        if (
            is_array($stored)
            && ($stored['detail'] ?? '') === $detail
            && time() - (int) ($stored['time'] ?? 0) < DAY_IN_SECONDS
        ) {
            return;
        }

        update_option(self::OPTION, [
            'stage' => 'runtime',
            'detail' => $detail,
            'time' => time(),
        ], false);
    }
}
