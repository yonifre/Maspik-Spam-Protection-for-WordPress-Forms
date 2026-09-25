<?php

declare(strict_types=1);

namespace Maspik\Integrations\Forms;

use Maspik\Application\SpamGate;
use Maspik\Domain\Model\FieldType;
use Maspik\Integrations\AbstractFormIntegration;
use Maspik\Integrations\Support\FieldTypeGuesser;

if (! defined('ABSPATH')) {
    exit;
}


/**
 * FunnelKit opt-in form adapter.
 *
 * Hook: FunnelKit's own AJAX endpoint,
 * `wp_ajax(_nopriv)_wffn_submit_custom_optin_form`, at priority 1 so this runs
 * before its handler. There is no validation hook to sit behind — the endpoint
 * receives the form and acts on it — so intercepting the action itself is the
 * only place a submission can be stopped before FunnelKit sends the lead to a
 * CRM, fires its webhooks and emails it out.
 *
 * WHY THE FIELD NAMES ARE HANDLED THE WAY THEY ARE
 *
 * FunnelKit's opt-in fields are built in its page editor and stored per page,
 * so beyond the stock three (`wfop_optin_first_name`, `wfop_optin_email`,
 * `wfop_optin_phone`) the names are whatever the site owner created. A fixed
 * map would therefore be right on a default form and blind on a customised one.
 *
 * So the known names are mapped exactly, and anything else posted under the
 * `wfop_optin_` prefix is classified from its content by FieldTypeGuesser,
 * which only ever answers text or long text. That means a custom field still
 * gets the blocklists and the length and link limits, while the checks that can
 * refuse a legitimate visitor on a wrong guess — the phone format check, the
 * allow list — stay reserved for names we actually recognise.
 */
final class FunnelKit extends AbstractFormIntegration
{
    public static function typeMap(): array
    {
        return [
            'text' => FieldType::TEXT,
            'email' => FieldType::EMAIL,
            'tel' => FieldType::TEL,
            'textarea' => FieldType::TEXTAREA,
        ];
    }

    /** The stock fields, whose meaning is known rather than guessed. */
    private const KNOWN = [
        'wfop_optin_email' => 'email',
        'wfop_optin_phone' => 'tel',
        'wfop_optin_first_name' => 'text',
        'wfop_optin_last_name' => 'text',
        'wfop_optin_name' => 'text',
    ];

    /**
     * Posted alongside the phone field by FunnelKit's country widget. They are
     * a dial code and an ISO country code, not anything a visitor typed.
     */
    private const NOT_VISITOR_INPUT = [
        'wfop_optin_phone_dialcode',
        'wfop_optin_phone_countrycode',
    ];

    public function id(): string
    {
        return 'funnelkit';
    }

    public function label(): string
    {
        return 'FunnelKit';
    }

    public function toggleKey(): string
    {
        return 'maspik_support_funnelkit';
    }

    public function isAvailable(): bool
    {
        return defined('WFFN_VERSION') || class_exists('\WFFN_Core');
    }

    public function register(SpamGate $gate): void
    {
        // Priority 1: FunnelKit's own handler is on the default 10, and once it
        // runs the lead has already gone to the CRM.
        foreach (['wp_ajax_wffn_submit_custom_optin_form', 'wp_ajax_nopriv_wffn_submit_custom_optin_form'] as $hook) {
            add_action($hook, \Maspik\Kernel\Guard::wrap(function () use ($gate) {
                if (apply_filters('maspik_disable_funnelkit_spam_check', false)) {
                    return;
                }

                $raw = $this->fields();
                if ($raw === []) {
                    return;
                }

                $verdict = $gate->evaluate($this->submissionFrom($raw, self::typeMap()));
                if (! $verdict->isSpam) {
                    return;
                }

                // The shape FunnelKit uses for its own reCAPTCHA rejection.
                // Its front-end script reads `next_url` and `mapped` and does
                // nothing with `message`, so the visitor sees the form stop
                // rather than a reason — the same thing that happens when
                // FunnelKit refuses a submission itself. Matching its behaviour
                // is the honest option here; inventing a different response
                // shape would not reach the page either.
                wp_send_json(['message' => $gate->errorMessage($verdict)]);
            }), 1);
        }
    }

    /**
     * @return array<int, array{name: string, type: string, value: string}>
     */
    private function fields(): array
    {
        $post = isset($_POST) ? (array) wp_unslash($_POST) : [];

        $rows = [];
        foreach ($post as $name => $value) {
            $name = (string) $name;

            if (strpos($name, 'wfop_optin_') !== 0 || ! is_scalar($value)) {
                continue;
            }
            if (in_array($name, self::NOT_VISITOR_INPUT, true)) {
                continue;
            }
            // The editor posts a preview copy of each field alongside the real
            // one; scanning both would double every value.
            if (substr($name, -8) === '_preview') {
                continue;
            }

            $value = trim((string) $value);
            if ($value === '') {
                continue;
            }

            if (isset(self::KNOWN[$name])) {
                $type = self::KNOWN[$name];
            } else {
                // A field the site owner invented. Classified by content, and
                // only ever as text or long text.
                $type = FieldTypeGuesser::guess($value) === FieldType::TEXTAREA ? 'textarea' : 'text';
            }

            $rows[] = [
                'name' => $name,
                'type' => $type,
                'value' => $type === 'email' ? sanitize_email($value) : sanitize_textarea_field($value),
            ];
        }

        return $rows;
    }
}
