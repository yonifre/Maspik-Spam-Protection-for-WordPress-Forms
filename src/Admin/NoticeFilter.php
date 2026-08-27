<?php

declare(strict_types=1);

namespace Maspik\Admin;

/**
 * Keeps MASPIK's own admin pages free of every notice that is not ours.
 *
 * The rule is deliberately absolute: on our screens we keep only notices whose
 * callback is defined inside this plugin, and drop everything else — other
 * plugins, themes, and WordPress core alike. Earlier versions of this class
 * spared core on the theory that its update and security messages are too
 * important to hide. In practice they are not hidden, only relocated: core
 * repeats them on every other admin screen, including the Dashboard and
 * Updates, so nothing is actually lost by keeping this one page clean.
 *
 * A callback whose source file cannot be resolved counts as foreign. Every
 * notice MASPIK registers is defined in this plugin's own files and resolves
 * fine, so "unidentifiable" reliably means "not ours".
 *
 * v2 precedent: `maspik_is_maspik_page()` wiped `admin_notices` wholesale on
 * `admin_init`, which also killed MASPIK's own notices — they had nowhere left
 * to hook. This keeps ours by checking where each callback is actually defined
 * (Reflection), and runs on `in_admin_header` (right before notices render) so
 * it also catches notices registered late, e.g. from a `load-{page}` hook,
 * which fires after admin_init.
 */
final class NoticeFilter
{
    private const HOOKS = ['user_admin_notices', 'admin_notices', 'all_admin_notices', 'network_admin_notices'];

    public static function boot(): void
    {
        add_action('in_admin_header', [self::class, 'stripForeignNotices'], PHP_INT_MAX);
    }

    public static function stripForeignNotices(): void
    {
        $slug = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
        if (! Menu::isMaspikPageSlug($slug)) {
            return;
        }

        global $wp_filter;
        foreach (self::HOOKS as $hookName) {
            if (! isset($wp_filter[$hookName]) || ! ($wp_filter[$hookName] instanceof \WP_Hook)) {
                continue;
            }

            foreach ($wp_filter[$hookName]->callbacks as $priority => $callbacks) {
                foreach ($callbacks as $registered) {
                    if (self::isForeignPlugin($registered['function'])) {
                        remove_filter($hookName, $registered['function'], $priority);
                    }
                }
            }
        }
    }

    /**
     * True for anything that is not MASPIK's own notice.
     *
     * Only the plugin's own directory is spared, so no source of noise needs
     * enumerating: plugins, themes, mu-plugins, drop-ins and core are all
     * simply "not ours". An unresolvable callback is foreign too — see the
     * class docblock for why that is safe.
     *
     * @param callable $callback
     */
    private static function isForeignPlugin($callback): bool
    {
        $file = self::declaringFile($callback);
        if ($file === null) {
            return true;
        }

        return strpos(wp_normalize_path($file), wp_normalize_path(MASPIK_DIR)) !== 0;
    }

    /**
     * @param callable $callback
     */
    private static function declaringFile($callback): ?string
    {
        try {
            if ($callback instanceof \Closure) {
                $ref = new \ReflectionFunction($callback);
            } elseif (is_array($callback) && count($callback) === 2) {
                $ref = new \ReflectionMethod($callback[0], $callback[1]);
            } elseif (is_string($callback) && strpos($callback, '::') !== false) {
                $ref = new \ReflectionMethod($callback);
            } elseif (is_string($callback) && function_exists($callback)) {
                $ref = new \ReflectionFunction($callback);
            } elseif (is_object($callback) && method_exists($callback, '__invoke')) {
                $ref = new \ReflectionMethod($callback, '__invoke');
            } else {
                return null;
            }
        } catch (\ReflectionException $e) {
            return null;
        }

        $file = $ref->getFileName();

        return is_string($file) ? $file : null;
    }
}
