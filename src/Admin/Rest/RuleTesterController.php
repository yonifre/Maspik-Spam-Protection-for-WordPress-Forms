<?php

declare(strict_types=1);

namespace Maspik\Admin\Rest;

use Maspik\Domain\Check\EmailBlacklistCheck;
use Maspik\Domain\Check\IpBlocklistCheck;
use Maspik\Domain\Check\PhoneCheck;
use Maspik\Domain\Check\TextBlacklistCheck;
use Maspik\Domain\Check\UrlBlacklistCheck;
use Maspik\Domain\Model\Field;
use Maspik\Domain\Model\FieldType;
use Maspik\Domain\Model\Submission;
use Maspik\Domain\Model\Violation;
use Maspik\Infrastructure\Settings\Settings;
use WP_REST_Request;
use WP_REST_Response;

/**
 * POST /maspik/v1/test-rule — "would this value be blocked?" for the rule
 * editors. Runs the *real* domain check against the rules the user is editing
 * (draft, unsaved), so the answer always matches what the engine would do — no
 * matching logic is reimplemented in the browser.
 *
 * The rules posted are the editor's draft — unsaved, so a rule can be tried
 * before committing to it. The Dashboard's own rules are added here rather than
 * sent by the browser, because they are not part of any draft: they always
 * apply and cannot be edited on this screen. Leaving them out made the tester
 * contradict itself, reporting "would be allowed" for an address printed in the
 * synced list directly beneath the answer.
 *
 * Body: { type: 'text'|'email'|'url'|'ip'|'phone', rules: string[], value: string }
 */
final class RuleTesterController
{
    /**
     * Which settings key holds the Dashboard's rules for each tester type.
     * Mirrors what CheckFactory reads when it builds the same checks for real.
     */
    private const DASHBOARD_KEYS = [
        'text' => 'text_blacklist',
        'email' => 'emails_blacklist',
        'url' => 'url_blacklist',
        'ip' => 'ip_blacklist',
        'phone' => 'tel_formats',
    ];

    /** @var Settings */
    private $settings;

    public function __construct(Settings $settings)
    {
        $this->settings = $settings;
    }

    public function registerRoutes(): void
    {
        register_rest_route('maspik/v1', '/test-rule', [
            'methods' => 'POST',
            'callback' => [$this, 'test'],
            'permission_callback' => static function (): bool {
                return current_user_can('manage_options');
            },
        ]);
    }

    public function test(WP_REST_Request $request): WP_REST_Response
    {
        $params = (array) $request->get_json_params();
        $type = isset($params['type']) ? (string) $params['type'] : '';
        $value = isset($params['value']) ? (string) $params['value'] : '';
        $rules = [];
        if (isset($params['rules']) && is_array($params['rules'])) {
            foreach ($params['rules'] as $rule) {
                if (is_string($rule) && trim($rule) !== '') {
                    $rules[] = $rule;
                }
            }
        }

        if ($value === '') {
            return new WP_REST_Response(['blocked' => false, 'reason' => null]);
        }

        // The engine merges both sources (Settings::list($key, true)); the
        // tester has to do the same or it answers a question nobody asked.
        $fromDashboard = isset(self::DASHBOARD_KEYS[$type])
            ? $this->settings->dashboardList(self::DASHBOARD_KEYS[$type])
            : [];
        $all = array_values(array_unique(array_merge($rules, $fromDashboard)));

        $violation = $this->run($type, $all, $value);

        return new WP_REST_Response([
            'blocked' => $violation !== null,
            'reason' => $violation !== null ? $violation->reason : null,
            // So the UI can say *where* the rule came from: a value blocked by a
            // rule the admin cannot find in the box in front of them is its own
            // small mystery.
            'from_dashboard' => $violation !== null && ! $this->wouldBlock($type, $rules, $value),
        ]);
    }

    /**
     * True when the local draft alone would block the value — used to tell a
     * Dashboard-only hit apart from one the admin's own rules already cover.
     *
     * @param string[] $rules
     */
    private function wouldBlock(string $type, array $rules, string $value): bool
    {
        return $rules !== [] && $this->run($type, $rules, $value) !== null;
    }

    /** @param string[] $rules */
    private function run(string $type, array $rules, string $value): ?Violation
    {
        switch ($type) {
            case 'text':
                // The text blacklist protects both text and message fields; the
                // value is caught if either would block it.
                $check = new TextBlacklistCheck($rules);
                $violation = $check->check(new Field('test', FieldType::TEXT, $value));

                return $violation !== null
                    ? $violation
                    : $check->check(new Field('test', FieldType::TEXTAREA, $value));

            case 'email':
                return (new EmailBlacklistCheck($rules))->check(new Field('test', FieldType::EMAIL, $value));

            case 'url':
                return (new UrlBlacklistCheck($rules))->check(new Field('test', FieldType::URL, $value));

            case 'phone':
                return (new PhoneCheck($rules))->check(new Field('test', FieldType::TEL, $value));

            case 'ip':
                return (new IpBlocklistCheck($rules))->check(new Submission([], 'test', 'Test', $value));

            default:
                return null;
        }
    }
}
