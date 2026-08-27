<?php

declare(strict_types=1);

namespace Maspik\Infrastructure\Matrix;

use Maspik\Domain\Model\Submission;
use Maspik\Infrastructure\Logging\LayerStatus;
use Maspik\Infrastructure\Settings\Settings;
use Maspik\Premium\License;
use Maspik\Premium\ProGate;

/**
 * Maspik Matrix (InputGate) cloud client — talks to ipapi.wpmaspik.com/check,
 * the same endpoint and request shape the production plugin uses.
 *
 * Auth: a licensed site sends its DLM key + per-site token (HMAC-signs the body
 * with the token); everyone else uses the free-beta credentials (100 checks a
 * month, metered server-side).
 *
 * Privacy-first: mode "2" sends only the IP (no form content leaves the site);
 * modes "3"/"4" include the submitted fields.
 *
 * Fail-open by contract: any error, non-200, or malformed reply returns null,
 * so a submission is never blocked because the cloud was unreachable.
 */
final class MatrixClient
{
    private const ENDPOINT = 'https://ipapi.wpmaspik.com/check';
    private const USAGE_ENDPOINT = 'https://ipapi.wpmaspik.com/usage';
    private const FREE_CREDENTIAL = 'try_free_as_beta';
    private const FREE_MONTHLY_LIMIT = 100;

    /** @var Settings */
    private $settings;

    /** @var License */
    private $license;

    /** @var ProGate */
    private $pro;

    public function __construct(Settings $settings, License $license, ProGate $pro)
    {
        $this->settings = $settings;
        $this->license = $license;
        $this->pro = $pro;
    }

    /**
     * Pull the current monthly usage straight from the server — no spam-check
     * side effect, so this can (and should) run right after the license state
     * changes, instead of leaving the meter at a stale/zeroed count until the
     * next real submission happens to trigger check(). Fail-open: any error
     * just leaves the last cached numbers as they were.
     */
    public function refreshUsage(): void
    {
        $license = $this->license->token() !== '' ? $this->license->key() : self::FREE_CREDENTIAL;
        $token = $this->license->token() !== '' ? $this->license->token() : self::FREE_CREDENTIAL;

        $url = self::USAGE_ENDPOINT . '?site=' . rawurlencode(home_url());
        $response = wp_remote_get($url, [
            'timeout' => 7,
            'sslverify' => true,
            'headers' => [
                'Authorization' => 'Bearer ' . $license,
                'X-Maspik-Token' => $token,
            ],
        ]);
        if (is_wp_error($response) || (int) wp_remote_retrieve_response_code($response) !== 200) {
            return;
        }

        $json = json_decode((string) wp_remote_retrieve_body($response), true);
        if (! is_array($json)) {
            return;
        }

        $this->storeUsage($json);
    }

    /**
     * @return callable(Submission): ?array{is_spam: bool, reason: string}
     */
    public function resolver(string $mode): callable
    {
        return function (Submission $submission) use ($mode): ?array {
            return $this->check($submission, $mode);
        };
    }

    /**
     * @return array{is_spam: bool, reason: string}|null
     */
    private function check(Submission $submission, string $mode): ?array
    {
        // Free tier: don't spend a call once the month is used up (server also
        // enforces this; this just avoids a pointless round-trip). Pro is
        // effectively unlimited.
        if (! $this->pro->supports('pro') && $this->monthlyRemaining() <= 0) {
            return null;
        }

        $license = $this->license->token() !== '' ? $this->license->key() : self::FREE_CREDENTIAL;
        $token = $this->license->token() !== '' ? $this->license->token() : self::FREE_CREDENTIAL;

        $payload = [
            // Mode 2 is IP-only: no form content is sent.
            'fields' => $mode === '2' ? [] : $this->prepareFields($submission),
            'context' => [
                'business_info' => mb_substr($this->settings->raw('maspik_ai_context'), 0, 170),
                'site_url' => home_url(),
                'plugin_version' => defined('MASPIK_VERSION') ? MASPIK_VERSION : 'dev',
                'site_title_and_tagline' => trim(get_bloginfo('name') . ' ' . get_bloginfo('description')),
                'client_ip' => $submission->ip !== '' ? $submission->ip : '127.0.0.1',
                'site_language' => get_locale(),
                'form_type' => $submission->sourceLabel,
                'mode' => (int) $mode,
                // 1 = low suspicion, 9 = high. Raised by the direct-POST signal
                // so Matrix can weigh a request that carries no page context.
                'plugin_spam_likelihood' => DirectPostSignal::floor(),
            ],
            // Where the submission came from, or the 'no_referrer' sentinel.
            'maspik_referrer' => DirectPostSignal::referrerFor($submission),
        ];

        $body = wp_json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (! is_string($body)) {
            return null;
        }

        $response = wp_remote_post(self::ENDPOINT, [
            'timeout' => 7,
            'sslverify' => true,
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $license,
                'X-Maspik-Token' => $token,
                'X-Maspik-Signature' => base64_encode(hash_hmac('sha256', $body, $token, true)),
            ],
            'body' => $body,
        ]);
        if (is_wp_error($response)) {
            LayerStatus::record('ai_spam_check', LayerStatus::TIMEOUT, 'Matrix unavailable');

            return null;
        }
        if ((int) wp_remote_retrieve_response_code($response) !== 200) {
            LayerStatus::record('ai_spam_check', LayerStatus::ERROR, 'Matrix error');

            return null;
        }

        $json = json_decode((string) wp_remote_retrieve_body($response), true);
        if (! is_array($json) || ! isset($json['is_spam'])) {
            LayerStatus::record('ai_spam_check', LayerStatus::ERROR, 'Matrix invalid response');

            return null;
        }

        $this->storeUsage($json);

        return [
            'is_spam' => (bool) $json['is_spam'],
            'reason' => isset($json['user_reason']) && is_string($json['user_reason']) && $json['user_reason'] !== ''
                ? $json['user_reason']
                : 'InputGate flagged this submission',
        ];
    }

    /**
     * Flatten submission fields to a name→value map, dropping guard/technical
     * fields and capping each value (mirrors v2 maspik_prepare_fields_for_ai).
     *
     * @return array<string, string>
     */
    private function prepareFields(Submission $submission): array
    {
        $skip = ['hidden', 'action', 'nonce', 'submit', 'referrer', 'time', 'key', 'url', 'redirect', 'link', 'ref', 'hash', 'maspik', 'honeypot', 'token', 'captcha', 'recaptcha'];
        $out = [];
        foreach ($submission->fields as $field) {
            $name = (string) $field->name;
            if ($name === '' || strpos($name, '_') === 0) {
                continue;
            }
            $lower = strtolower($name);
            foreach ($skip as $term) {
                if (strpos($lower, $term) !== false) {
                    continue 2;
                }
            }
            $value = is_array($field->value) ? implode(', ', array_map('strval', $field->value)) : (string) $field->value;
            $value = trim($value);
            if ($value === '') {
                continue;
            }
            $out[$name] = mb_substr($value, 0, 500);
        }

        return $out;
    }

    /**
     * Persist the server-reported monthly usage so the meter is accurate.
     * Pro/unlimited responses send `monthly_limit`/`monthly_remaining` as
     * null (licensed accounts aren't metered) — isset() is false for those,
     * so they're simply left unset rather than coerced to a misleading 0;
     * StatsController already derives "unlimited" from ProGate, not from
     * these cached values.
     */
    private function storeUsage(array $json): void
    {
        if (isset($json['monthly_used'])) {
            update_option('maspik_matrix_used_' . current_time('Y-m'), (int) $json['monthly_used'], false);
        }
        if (isset($json['monthly_remaining'])) {
            update_option('maspik_matrix_remaining_' . current_time('Y-m'), (int) $json['monthly_remaining'], false);
        }
    }

    private function monthlyRemaining(): int
    {
        $remaining = get_option('maspik_matrix_remaining_' . current_time('Y-m'), null);
        if ($remaining === null) {
            // No call yet this month — assume the full free allowance is available.
            return self::FREE_MONTHLY_LIMIT;
        }

        return (int) $remaining;
    }
}
