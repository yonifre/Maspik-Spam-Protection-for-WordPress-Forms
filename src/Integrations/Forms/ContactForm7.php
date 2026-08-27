<?php

declare(strict_types=1);

namespace Maspik\Integrations\Forms;

use Maspik\Application\SpamGate;
use Maspik\Domain\Model\FieldType;
use Maspik\Infrastructure\Matrix\DirectPostSignal;
use Maspik\Integrations\AbstractFormIntegration;

/**
 * Contact Form 7 adapter — the reference implementation.
 * Hook: wpcf7_validate ($result, $tags). Reject: $result->invalidate(tag, msg).
 */
final class ContactForm7 extends AbstractFormIntegration
{
    /** CF7 basetype => FieldType. */
    public static function typeMap(): array
    {
        return [
            'text' => FieldType::TEXT,
            'email' => FieldType::EMAIL,
            'url' => FieldType::URL,
            'tel' => FieldType::TEL,
            'textarea' => FieldType::TEXTAREA,
        ];
    }

    public function id(): string
    {
        return 'cf7';
    }

    public function label(): string
    {
        return 'Contact form 7';
    }

    public function toggleKey(): string
    {
        return 'maspik_support_cf7';
    }

    public function isAvailable(): bool
    {
        return defined('WPCF7_VERSION');
    }

    public function register(SpamGate $gate): void
    {
        // Direct-POST evidence: a real CF7 submit always carries the `_wpcf7`
        // form id. Signal only — never blocks on its own.
        //
        // Scored 7, matching v2 (includes/forms/cf7.php). CF7 is not as
        // categorical as Elementor here: the id can be missing in legitimate
        // flows, so this is strong evidence rather than proof, and sending the
        // top score would push borderline real submissions over InputGate's
        // line.
        add_filter('maspik/direct_post_score', static function ($score, $submission = null) {
            return self::directPostSentinel($submission) === null
                ? $score
                : max((int) $score, DirectPostSignal::CF7);
        }, 10, 2);

        // The matching sentinel, forwarded as `maspik_referrer` (v2: no_cf7_id).
        add_filter('maspik/direct_post_referrer', static function ($sentinel, $submission = null) {
            return self::directPostSentinel($submission) ?? $sentinel;
        }, 10, 2);

        add_filter('wpcf7_validate', function ($result, $tags) use ($gate) {
            $formId = isset($_POST['_wpcf7']) ? (int) $_POST['_wpcf7'] : 0;
            if (apply_filters('maspik_disable_cf7_spam_check', false, $formId)) {
                return $result;
            }

            $raw = [];
            foreach ($tags as $tag) {
                $name = (string) $tag->name;
                if ($name === '' || ! isset($_POST[$name])) {
                    continue;
                }
                $value = wp_unslash($_POST[$name]);
                $raw[] = ['name' => $name, 'type' => (string) $tag->basetype, 'value' => is_array($value) ? $value : (string) $value];
            }

            $verdict = $gate->evaluate($this->submissionFrom($raw, self::typeMap()));
            if ($verdict->isSpam) {
                $fieldName = $verdict->violation !== null ? $verdict->violation->fieldName : null;
                $tag = $this->tagByName($tags, $fieldName);
                $result->invalidate($tag !== null ? $tag : (isset($tags[0]) ? $tags[0] : ''), $gate->errorMessage($verdict));
            }

            return $result;
        }, 10, 2);
    }

    /** @param array<int, object> $tags */
    private function tagByName(array $tags, ?string $name): ?object
    {
        if ($name === null) {
            return null;
        }
        foreach ($tags as $tag) {
            if ((string) $tag->name === $name) {
                return $tag;
            }
        }

        return null;
    }

    /**
     * Which page-context marker is missing, or null when the post looks genuine.
     * One place, so the score filter and the sentinel filter can never disagree
     * about whether this submission is suspicious.
     *
     * @param mixed $submission
     */
    private static function directPostSentinel($submission): ?string
    {
        if ($submission === null || $submission->source !== 'cf7') {
            return null;
        }

        return empty($_POST['_wpcf7']) ? 'no_cf7_id' : null;
    }
}
