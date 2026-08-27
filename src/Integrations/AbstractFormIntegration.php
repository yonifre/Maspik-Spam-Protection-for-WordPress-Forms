<?php

declare(strict_types=1);

namespace Maspik\Integrations;

use Maspik\Domain\Check\VerificationKeyCheck;
use Maspik\Domain\Check\HoneypotCheck;
use Maspik\Domain\Model\Field;
use Maspik\Domain\Model\Submission;
use Maspik\Integrations\Support\RawPayload;
use Maspik\Infrastructure\ClientIp;
use Maspik\Integrations\Support\FieldMapper;

/**
 * Shared plumbing for form adapters. A concrete adapter only has to:
 *   1. declare id / label / toggleKey / isAvailable,
 *   2. bind its plugin's validation hook,
 *   3. extract the plugin's fields into rawFields (name/type/value) and call
 *      submissionFrom(), then map a spam Verdict onto the plugin's error API.
 *
 * The guard-field, client-IP and referrer extraction — identical for every
 * plugin — lives here so it is written and reasoned about once.
 */
abstract class AbstractFormIntegration implements FormIntegration
{
    /** @var ClientIp */
    protected $clientIp;

    public function __construct(ClientIp $clientIp)
    {
        $this->clientIp = $clientIp;
    }

    /** Free by default; Pro-only integrations override this to true. */
    public function pro(): bool
    {
        return false;
    }

    /** Enabled by default; opt-in integrations override this to true. */
    public function optIn(): bool
    {
        return false;
    }

    /**
     * Build a Submission from raw plugin fields + a plugin type map.
     *
     * @param array<int, array{name: string, type: string, value: string|array}> $rawFields
     * @param array<string, string> $typeMap
     */
    protected function submissionFrom(array $rawFields, array $typeMap): Submission
    {
        return $this->submission(FieldMapper::map($rawFields, $typeMap));
    }

    /**
     * Build a Submission from already-normalized Field[] (core WP forms with
     * fixed field sets use this directly).
     *
     * @param Field[] $fields
     */
    protected function submission(array $fields): Submission
    {
        $post = isset($_POST) ? (array) wp_unslash($_POST) : [];

        // Searched for rather than read from a fixed key. Our script adds both
        // fields to the form, but not every plugin posts a form as fields:
        // Fluent Forms serialises the whole thing into $_POST['data'], so the
        // key was missing from where we looked and every genuine submission
        // was rejected for not having one.
        $hidden = [
            HoneypotCheck::FIELD_NAME => RawPayload::findField($post, HoneypotCheck::FIELD_NAME),
            VerificationKeyCheck::FIELD_NAME => RawPayload::findField($post, VerificationKeyCheck::FIELD_NAME),
        ];

        return $this->submissionWithHidden($fields, $hidden);
    }

    /**
     * Build a Submission with explicit guard-field values, for adapters whose
     * plugin carries the honeypot/key inside its own payload rather than $_POST
     * (e.g. Elementor Atomic Forms). $hidden must be keyed by
     * HoneypotCheck::FIELD_NAME / VerificationKeyCheck::FIELD_NAME.
     *
     * @param Field[]               $fields
     * @param array<string, string> $hidden
     */
    protected function submissionWithHidden(array $fields, array $hidden): Submission
    {
        $referrer = isset($_SERVER['HTTP_REFERER'])
            ? esc_url_raw(wp_unslash($_SERVER['HTTP_REFERER'])) : null;

        // Captured here rather than per adapter: every plugin shapes its data
        // differently, but they all arrive in the same request.
        //
        // The verification key is handed in so the capture can redact it. The
        // adapter has already picked it out of whatever shape its plugin used,
        // which is the whole point: RawPayload drops the key when it arrives
        // under its own name, but Elementor Atomic carries it inside its field
        // envelope as form_fields[6][value], where nothing about the key's name
        // gives it away. It was being stored in the log, and being the longest
        // value on the row it was shown to the admin as the visitor's message.
        $secret = isset($hidden[VerificationKeyCheck::FIELD_NAME])
            ? (string) $hidden[VerificationKeyCheck::FIELD_NAME] : '';
        $raw = RawPayload::capture(isset($_POST) ? (array) wp_unslash($_POST) : [], $secret);

        return new Submission(
            $fields,
            $this->id(),
            $this->label(),
            $this->clientIp->get(),
            $hidden,
            $referrer,
            $raw
        );
    }
}
