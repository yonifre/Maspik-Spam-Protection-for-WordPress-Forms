<?php

declare(strict_types=1);

namespace Maspik\Admin;

use Maspik\Infrastructure\Settings\Settings;

/**
 * Invitation to move Maspik Matrix from IP-only checking to the full check.
 *
 * New installs default to mode 2 (IP only) because that is the privacy-safe
 * choice to make on someone's behalf — form content never leaves the site
 * unless they ask for it. But mode 2 blocks noticeably less, and left alone a
 * site stays there forever without ever learning the stronger option exists.
 * So we ask once, in plain terms, and remember the answer.
 *
 * Deliberately shown only while Matrix is switched ON: when it is off the
 * dashboard already reports that as a problem, and two notices about one layer
 * is worse than one.
 */
final class FullModeNudge
{
    /**
     * The answer lives in the plugin's own settings table, so the React admin
     * can dismiss it through the settings endpoint it already uses and both
     * surfaces read one value.
     */
    public const DISMISSED_KEY = 'maspik_full_protection_nudge_dismissed';

    /**
     * Where v2 stored the same answer. Upgrade carries it into DISMISSED_KEY
     * once, so nothing here has to read two places and the admin app cannot
     * disagree with this notice about whether the question was already asked.
     */
    public const LEGACY_DISMISSED_OPTION = 'maspik_matrix_full_mode_nudge_hidden_v4';

    /** Documentation for the layer this notice offers to switch on. */
    private const LEARN_MORE_URL = 'https://wpmaspik.com/documentation/ai-spam-check/';

    private const ACTION = 'maspik_matrix_nudge';
    private const NONCE = 'maspik_matrix_nudge';

    /** @var Settings */
    private $settings;

    public function __construct(Settings $settings)
    {
        $this->settings = $settings;
    }

    public function register(): void
    {
        add_action('admin_init', [$this, 'handleAction']);
        add_action('admin_notices', [$this, 'render']);
    }

    /**
     * Both actions are plain nonce-protected links handled here, rather than
     * REST or admin-ajax: this notice renders on every admin screen, where our
     * REST nonce is not localised — and REST is exactly what hosts and security
     * plugins are most likely to block.
     */
    public function handleAction(): void
    {
        $action = isset($_GET[self::ACTION]) ? sanitize_key(wp_unslash($_GET[self::ACTION])) : '';
        if ($action === '' || ! current_user_can('manage_options')) {
            return;
        }

        $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';
        if (! wp_verify_nonce($nonce, self::NONCE)) {
            return;
        }

        if ($action === 'full') {
            $this->settings->save('maspik_matrix_api_mode', '4');
            // The notice only shows while Matrix is on, but save it explicitly
            // so the button can never leave the mode set and the layer off.
            $this->settings->save('maspik_ai_enabled', '1');
            // No dismissal flag here on purpose: the notice stops showing
            // because the mode changed, so if the site ever returns to IP-only
            // the invitation is still available.
        } elseif ($action === 'dismiss') {
            $this->settings->save(self::DISMISSED_KEY, '1');
        }

        wp_safe_redirect(remove_query_arg([self::ACTION, '_wpnonce']));
        exit;
    }

    public function render(): void
    {
        if (! $this->shouldShow()) {
            return;
        }

        $activate = wp_nonce_url(add_query_arg(self::ACTION, 'full'), self::NONCE);
        $dismiss = wp_nonce_url(add_query_arg(self::ACTION, 'dismiss'), self::NONCE);
        ?>
        <div class="notice notice-info">
            <p>
                <strong><?php esc_html_e('Improve your spam protection for free', 'contact-forms-anti-spam'); ?></strong>
            </p>
            <p>
                <?php esc_html_e('Maspik is currently protecting your forms mainly by checking the sender’s IP address.', 'contact-forms-anti-spam'); ?>
            </p>
            <p>
                <?php esc_html_e('We recommend enabling Full Protection. It’s completely free and adds additional layers of spam protection by analyzing submitted content, user behavior, and other spam signals to detect more spam.', 'contact-forms-anti-spam'); ?>
            </p>
            <p>
                <a href="<?php echo esc_url($activate); ?>" class="button button-primary">
                    <?php esc_html_e('Enable Full Protection', 'contact-forms-anti-spam'); ?>
                </a>
                <?php // Opens the feature's documentation, so "what am I turning on?"
                      // is answerable without leaving the decision. External, so it
                      // gets the same new-tab + noopener treatment as every other
                      // outbound link in the admin. ?>
                <a href="<?php echo esc_url(self::LEARN_MORE_URL); ?>" class="button" target="_blank" rel="noopener noreferrer">
                    <?php esc_html_e('Learn More', 'contact-forms-anti-spam'); ?>
                </a>
                <a href="<?php echo esc_url($dismiss); ?>" style="margin-inline-start:0.75em;color:#646970;">
                    <?php esc_html_e('Not Now', 'contact-forms-anti-spam'); ?>
                </a>
            </p>
        </div>
        <?php
    }

    private function shouldShow(): bool
    {
        if (! current_user_can('manage_options')) {
            return false;
        }

        // On MASPIK's own screens the admin app renders this invitation itself,
        // in the plugin's design. A WordPress notice there lands inside our page
        // layout and reads as something broken rather than something offered.
        $slug = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
        if (Menu::isMaspikPageSlug($slug)) {
            return false;
        }

        if ($this->settings->bool(self::DISMISSED_KEY)) {
            return false;
        }

        if (! $this->settings->bool('maspik_ai_enabled')) {
            return false;
        }

        return $this->settings->matrixMode() === '2';
    }
}
