<?php

declare(strict_types=1);

namespace Maspik\Admin;

use Maspik\Infrastructure\Logging\LogRepository;

/**
 * Native WordPress admin pages — one submenu entry per screen, each mounting
 * its own React bundle. WordPress owns routing/navigation; React owns
 * everything inside the page (architecture decision, docs/07-decision-log.md).
 */
final class Menu
{
    /** @var LogRepository */
    private $logs;

    /**
     * React Query cache key => callable returning that endpoint's payload.
     * Closures so nothing is built unless we are actually rendering one of our
     * screens; see bootstrapFor().
     *
     * @var array<string, callable>
     */
    private $preloaders;

    /**
     * @param array<string, callable> $preloaders
     */
    public function __construct(LogRepository $logs, array $preloaders = [])
    {
        $this->logs = $logs;
        $this->preloaders = $preloaders;
    }

    /**
     * page key => [menu label, per-page JS entry]. The key is also the
     * admin page slug suffix and the build entry name.
     */
    private const PAGES = [
        'dashboard' => 'Dashboard',
        'protection' => 'Protection',
        'logs' => 'Logs',
        'analytics' => 'Analytics',
        'playground' => 'Playground',
        'advanced' => 'Advanced',
        'license' => 'Pro',
    ];

    /**
     * Registered pages incl. the dev-only styleguide.
     *
     * @return array<string, string>
     */
    private function pages(): array
    {
        $pages = self::PAGES;
        if (self::includeDesignPage()) {
            $pages['design'] = 'Design System';
        }

        return $pages;
    }

    /**
     * Whether to register the internal style-guide page. Gated on a constant
     * a developer defines locally (`define('MASPIK_DEV_MODE', true);` in
     * wp-config.php) — never on WP_DEBUG, which plenty of real production
     * sites legitimately enable just to log PHP errors, and which previously
     * leaked this dev-only page onto real sites running WP_DEBUG.
     */
    private static function includeDesignPage(): bool
    {
        return defined('MASPIK_DEV_MODE') && MASPIK_DEV_MODE;
    }

    /**
     * The "new spam" bubble for the menu, or '' when there is nothing new.
     *
     * Uses core's own markup (the same classes as the Comments count), so it
     * inherits WordPress styling, dark-mode colours and RTL placement instead
     * of us re-implementing them. Counts blocked submissions only: in debug
     * mode passed submissions are logged too, and letting those inflate the
     * badge would make the number meaningless.
     */
    private function unseenBadge(): string
    {
        // On the Logs screen itself the count is about to be cleared, so never
        // show a stale bubble on the very page that clears it.
        $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
        if ($page === 'maspik-logs') {
            return '';
        }

        $count = $this->logs->unseenCount();
        if ($count < 1) {
            return '';
        }

        $label = $count > 99 ? '99+' : (string) $count;

        return sprintf(
            ' <span class="awaiting-mod update-plugins count-%1$d"><span class="pending-count" aria-hidden="true">%2$s</span><span class="screen-reader-text">%3$s</span></span>',
            $count,
            esc_html($label),
            esc_html(sprintf(
                /* translators: %s: number of newly blocked submissions. */
                _n('%s new blocked submission', '%s new blocked submissions', $count, 'contact-forms-anti-spam'),
                number_format_i18n($count)
            ))
        );
    }

    public function register(): void
    {
        $capability = 'manage_options';
        $badge = $this->unseenBadge();

        add_menu_page(
            'MASPIK',
            'MASPIK' . $badge,
            $capability,
            'maspik',
            [$this, 'render'],
            'dashicons-shield',
            81
        );

        foreach ($this->pages() as $key => $label) {
            add_submenu_page(
                'maspik',
                'MASPIK - ' . $label,
                $key === 'logs' ? $label . $badge : $label,
                $capability,
                $key === 'dashboard' ? 'maspik' : 'maspik-' . $key,
                [$this, 'render']
            );
        }

        add_action('admin_enqueue_scripts', [$this, 'maybeEnqueue']);
    }

    public function render(): void
    {
        // Opening the Logs screen marks everything currently logged as seen,
        // whichever outcome filter happens to be active.
        if ($this->currentPageKey() === 'logs') {
            $this->logs->markSeen();
        }

        echo '<div class="wrap"><div id="maspik-root" data-page="' . esc_attr($this->currentPageKey()) . '"></div></div>';
    }

    public function maybeEnqueue(): void
    {
        $page = $this->currentPageKey();
        if ($page === '') {
            return;
        }

        $assetFile = MASPIK_DIR . '/admin-app/build/' . $page . '.asset.php';
        if (! is_readable($assetFile)) {
            add_action('admin_notices', static function (): void {
                echo '<div class="notice notice-warning"><p>MASPIK admin app not built. Run <code>npm run build</code> in <code>admin-app/</code>.</p></div>';
            });

            return;
        }

        $asset = require $assetFile;

        $handle = 'maspik-admin-' . $page;
        wp_enqueue_script(
            $handle,
            MASPIK_URL . 'admin-app/build/' . $page . '.js',
            $asset['dependencies'],
            $asset['version'],
            true
        );

        // Load JS translations. Every bundle shares the same string catalog, so
        // one JSON per locale is enough; a filter maps this handle to that
        // predictable file (avoids WordPress's per-script md5 filename, which
        // varies by install path / core version).
        if (in_array('wp-i18n', (array) $asset['dependencies'], true)) {
            add_filter('load_script_translation_file', [self::class, 'resolveTranslationFile'], 10, 3);
            wp_set_script_translations($handle, 'contact-forms-anti-spam', MASPIK_DIR . '/languages');
        }

        // Shared styles (tokens + components) are emitted once per entry by wp-scripts.
        $cssFile = MASPIK_DIR . '/admin-app/build/' . $page . '.css';
        if (is_readable($cssFile)) {
            wp_enqueue_style(
                'maspik-admin-' . $page,
                MASPIK_URL . 'admin-app/build/' . $page . '.css',
                [],
                $asset['version']
            );

            // wp-scripts emits a mirrored {page}-rtl.css next to each bundle.
            // 'replace' tells WordPress to serve that file instead on RTL
            // locales (Hebrew, Arabic) — without it those admins get a
            // left-to-right layout under right-to-left text.
            wp_style_add_data('maspik-admin-' . $page, 'rtl', 'replace');
        }

        wp_add_inline_script('maspik-admin-' . $page, 'window.maspikAdmin = ' . wp_json_encode([
            'restUrl' => esc_url_raw(rest_url('maspik/v1')),
            'nonce' => wp_create_nonce('wp_rest'),
            'version' => MASPIK_VERSION,
            'isRtl' => is_rtl(),
            'page' => $page,
            'pages' => $this->pageUrls(),
            'bootstrap' => $this->bootstrapFor($page),
        ]) . ';', 'before');
    }

    /**
     * REST payloads for the current screen, preloaded so React paints saved
     * values on the first frame instead of rendering control defaults and
     * correcting itself a moment later.
     *
     * Each preloader calls the very same controller method the REST route runs
     * and returns its response body, so the payload cannot drift from the live
     * endpoint the way a hand-built duplicate would.
     *
     * Deliberately NOT rest_do_request(): that calls rest_get_server(), which
     * fires rest_api_init and makes every installed plugin register its routes.
     * Measured on the test site (15 plugins, 815 routes) that costs ~206ms,
     * against ~1ms to invoke the controllers directly — it would add a fifth of
     * a second to every admin page to save a fetch that no longer blocks paint.
     * The trade is skipping REST filters, which none of these routes use.
     *
     * This is purely an optimisation: anything missing here is fetched over
     * HTTP exactly as before, and the app revalidates every entry on mount.
     *
     * @return array<string, mixed> React Query cache key => response body
     */
    private function bootstrapFor(string $page): array
    {
        // Mirrors the routes' own permission_callback. The screen already
        // requires this capability, so this only guards against future callers.
        if (! current_user_can('manage_options')) {
            return [];
        }

        // Only option-backed endpoints belong here. Anything doing real DB
        // aggregation (stats, logs) or network I/O would move that cost into
        // page render, trading one problem for a slower screen.
        $needs = [
            'dashboard' => ['settings', 'license', 'integrations'],
            'protection' => ['settings', 'license'],
            'advanced' => ['settings', 'integrations'],
            'license' => ['settings', 'license'],
            'logs' => ['settings'],
        ];

        if (! isset($needs[$page])) {
            return [];
        }

        $bootstrap = [];
        foreach ($needs[$page] as $key) {
            if (! isset($this->preloaders[$key])) {
                continue;
            }
            try {
                $data = ($this->preloaders[$key])();
                if (is_array($data)) {
                    $bootstrap[$key] = $data;
                }
            } catch (\Throwable $e) {
                // Never let preloading break the page it is meant to speed up.
                continue;
            }
        }

        return $bootstrap;
    }

    /**
     * Map any of our admin bundles to a single per-locale JSON catalog
     * (languages/contact-forms-anti-spam-{locale}.json). Only overrides our own
     * handles; everything else keeps WordPress's default resolution.
     *
     * @param string|false $file
     * @return string|false
     */
    public static function resolveTranslationFile($file, string $handle, string $domain)
    {
        if ($domain !== 'contact-forms-anti-spam' || strpos($handle, 'maspik-admin-') !== 0) {
            return $file;
        }

        $locale = determine_locale();
        $candidate = MASPIK_DIR . '/languages/contact-forms-anti-spam-' . $locale . '.json';

        return is_readable($candidate) ? $candidate : $file;
    }

    /** '' when the current admin screen is not one of ours. */
    private function currentPageKey(): string
    {
        $slug = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
        if (! self::isMaspikPageSlug($slug)) {
            return '';
        }
        if ($slug === 'maspik') {
            return 'dashboard';
        }

        return substr($slug, strlen('maspik-'));
    }

    /**
     * Whether a `?page=` slug is one of ours. Static/self-contained so other
     * concerns (e.g. NoticeFilter) can reuse the exact same page-detection
     * logic instead of keeping their own copy that could drift from it.
     */
    public static function isMaspikPageSlug(string $slug): bool
    {
        if ($slug === 'maspik') {
            return true;
        }
        if (strpos($slug, 'maspik-') !== 0) {
            return false;
        }

        $pages = self::PAGES;
        if (self::includeDesignPage()) {
            $pages['design'] = 'Design System';
        }
        $key = substr($slug, strlen('maspik-'));

        return isset($pages[$key]);
    }

    /**
     * Admin URLs per page, so React components can link across screens
     * (e.g. a Dashboard stat linking into filtered Logs).
     *
     * @return array<string, string>
     */
    private function pageUrls(): array
    {
        $urls = [];
        foreach ($this->pages() as $key => $label) {
            $slug = $key === 'dashboard' ? 'maspik' : 'maspik-' . $key;
            $urls[$key] = admin_url('admin.php?page=' . $slug);
        }

        return $urls;
    }
}
