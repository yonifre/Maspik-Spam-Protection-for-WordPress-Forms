<?php

declare(strict_types=1);

namespace Maspik\Integrations;

use Maspik\Application\SpamGate;
use Maspik\Infrastructure\ClientIp;
use Maspik\Infrastructure\Settings\Settings;
use Maspik\Integrations\Forms\BitForm;
use Maspik\Integrations\Forms\Breakdance;
use Maspik\Integrations\Forms\Bricks;
use Maspik\Integrations\Forms\BuddyPress;
use Maspik\Integrations\Forms\ContactForm7;
use Maspik\Integrations\Forms\CustomForm;
use Maspik\Integrations\Forms\Divi;
use Maspik\Integrations\Forms\Elementor;
use Maspik\Integrations\Forms\ElementorAtomic;
use Maspik\Integrations\Forms\AffiliateWP;
use Maspik\Integrations\Forms\EasyDigitalDownloads;
use Maspik\Integrations\Forms\EverestForms;
use Maspik\Integrations\Forms\FunnelKit;
use Maspik\Integrations\Forms\MemberPress;
use Maspik\Integrations\Forms\SureForms;
use Maspik\Integrations\Forms\WSForm;
use Maspik\Integrations\Forms\FluentForms;
use Maspik\Integrations\Forms\Formidable;
use Maspik\Integrations\Forms\Forminator;
use Maspik\Integrations\Forms\GravityForms;
use Maspik\Integrations\Forms\HelloPlus;
use Maspik\Integrations\Forms\JetFormBuilder;
use Maspik\Integrations\Forms\MetForm;
use Maspik\Integrations\Forms\NinjaForms;
use Maspik\Integrations\Forms\WooCommerceCheckout;
use Maspik\Integrations\Forms\WooCommerceRegistration;
use Maspik\Integrations\Forms\WordPressComments;
use Maspik\Integrations\Forms\WordPressRegistration;
use Maspik\Integrations\Forms\WpForms;
use Maspik\Kernel\Container;
use Maspik\Kernel\ServiceProvider;
use Maspik\Premium\ProGate;

if (! defined('ABSPATH')) {
    exit;
}


final class IntegrationsProvider implements ServiceProvider
{
    public function register(Container $c): void
    {
        $c->set(Registry::class, static function (Container $c): Registry {
            $registry = new Registry(
                $c->get(Settings::class),
                $c->get(SpamGate::class),
                $c->get(ProGate::class)
            );

            // One line per integration. The Registry only activates those whose
            // plugin is installed (isAvailable) and whose toggle is on.
            $ip = $c->get(ClientIp::class);
            $registry->add(new ContactForm7($ip));
            $registry->add(new Divi($ip));
            $registry->add(new Elementor($ip));
            $registry->add(new ElementorAtomic($ip));
            $registry->add(new WpForms($ip));
            $registry->add(new GravityForms($ip));
            $registry->add(new Forminator($ip));
            $registry->add(new Formidable($ip));
            $registry->add(new NinjaForms($ip));
            $registry->add(new FluentForms($ip));
            $registry->add(new EverestForms($ip));
            $registry->add(new SureForms($ip));
            $registry->add(new MemberPress($ip));
            $registry->add(new AffiliateWP($ip));
            $registry->add(new EasyDigitalDownloads($ip));
            $registry->add(new FunnelKit($ip));
            $registry->add(new WSForm($ip));
            $registry->add(new Bricks($ip));
            $registry->add(new Breakdance($ip));
            $registry->add(new BitForm($ip));
            $registry->add(new BuddyPress($ip));
            $registry->add(new HelloPlus($ip));
            $registry->add(new JetFormBuilder($ip));
            $registry->add(new MetForm($ip));
            $registry->add(new WordPressComments($ip));
            $registry->add(new WordPressRegistration($ip));
            // WooCommerce checkout is Pro-only and off by default, so it also
            // needs Settings + ProGate (the whitelist / opt-in gating).
            $registry->add(new WooCommerceCheckout($ip, $c->get(Settings::class), $c->get(ProGate::class)));
            $registry->add(new WooCommerceRegistration($ip, $c->get(Settings::class), $c->get(ProGate::class)));
            // Last on purpose: the UI renders this list in registration order,
            // and this is the only entry that is not a plugin we detect but a
            // filter a developer wires up themselves. It belongs after the real
            // plugins rather than among them.
            $registry->add(new CustomForm($ip));

            return $registry;
        });
    }

    public function boot(Container $c): void
    {
        // Priority 0, not the default 10.
        //
        // An integration can only intercept a submission if its hooks are
        // attached before the other plugin processes the request, and some
        // plugins process on `init` themselves: AffiliateWP dispatches its
        // registration form at `init` priority 9, one step ahead of the
        // default. Registering at 10 meant its hook had already run and the
        // adapter never saw a single signup — a whole integration silently
        // dead, with nothing anywhere to say so.
        //
        // Nothing here needs `init` to have progressed: availability is decided
        // by constants and classes the other plugin defines when it loads, well
        // before this point. Attaching earlier is only ever safer, because a
        // hook added before it fires still fires.
        add_action('init', \Maspik\Kernel\Guard::wrap(static function () use ($c): void {
            $registry = $c->get(Registry::class);

            /** Third parties may register custom integrations. */
            do_action('maspik/register_integrations', $registry);

            $registry->activateEnabled();
        }), 0);
    }
}
