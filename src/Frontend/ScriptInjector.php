<?php

declare(strict_types=1);

namespace Maspik\Frontend;

use Maspik\Domain\Check\VerificationKeyCheck;
use Maspik\Domain\Check\HoneypotCheck;
use Maspik\Infrastructure\Settings\Settings;
use Maspik\Integrations\Forms\ElementorAtomic;

/**
 * Enqueues the front-end guard script (honeypot + advanced key injection).
 * Replaces v2's inline wp_footer echo with a cacheable static file; the DOM
 * behavior (field names, search/GET-form exclusions) is unchanged.
 */
final class ScriptInjector
{
    /** @var Settings */
    private $settings;

    public function __construct(Settings $settings)
    {
        $this->settings = $settings;
    }

    public function enqueue(): void
    {
        $honeypot = $this->settings->boolEffective('maspikHoneypot');
        $advancedKey = $this->settings->boolEffective('verification_key');

        if (! $honeypot && ! $advancedKey) {
            return;
        }

        wp_enqueue_script(
            'maspik-guard',
            MASPIK_URL . 'assets/js/maspik-guard.js',
            [],
            MASPIK_VERSION,
            true
        );

        wp_add_inline_script('maspik-guard', 'window.maspikGuardConfig = ' . wp_json_encode([
            'honeypot' => $honeypot,
            'honeypotName' => HoneypotCheck::FIELD_NAME,
            'honeypotLabel' => __('Leave this field empty', 'contact-forms-anti-spam'),
            'advancedKey' => $advancedKey,
            'keyName' => VerificationKeyCheck::FIELD_NAME,
            'keyValue' => $advancedKey ? $this->settings->spamKey() : '',
            // Elementor Atomic (e-form) forms carry the guard fields as
            // data-interaction-id pseudo-fields, under distinct ids.
            'atomicHpId' => ElementorAtomic::HP_ID,
            'atomicKeyId' => ElementorAtomic::KEY_ID,
        ]) . ';', 'before');

        wp_register_style('maspik-guard', false, [], MASPIK_VERSION);
        wp_enqueue_style('maspik-guard');
        // display:none does the hiding whenever this rule reaches the page, and
        // the rest is what keeps the field harmless if it does not - an
        // optimisation plugin that defers or strips inline CSS leaves the
        // element with only the styles maspik-guard.js sets on it directly.
        // Nothing here offsets the field: the old left:-99999px turned into
        // ~100000px of real scrollable overflow on right-to-left pages, where
        // the inline start is the right edge (see hideField() in the guard
        // script). Clipping in place cannot overflow in any writing direction.
        wp_add_inline_style(
            'maspik-guard',
            '.maspik-field{display:none!important;pointer-events:none!important;opacity:0!important;'
            . 'position:absolute!important;width:1px!important;height:1px!important;padding:0!important;'
            . 'border:0!important;overflow:hidden!important;clip:rect(0,0,0,0)!important;'
            . 'clip-path:inset(50%)!important;white-space:nowrap!important;}'
        );
    }
}
