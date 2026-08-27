<?php

declare(strict_types=1);

namespace Maspik\Domain\Model;

/**
 * A normalized form submission: what every integration adapter produces and
 * the only thing the engine ever sees. Immutable by convention.
 */
final class Submission
{
    /** @var Field[] */
    public $fields;

    /** @var string integration id, e.g. 'cf7', 'elementor' */
    public $source;

    /** @var string human label logged as spam_source, e.g. 'Contact form 7' */
    public $sourceLabel;

    /** @var string */
    public $ip;

    /** @var array<string, string> raw protective fields (honeypot, spam key) */
    public $hidden;

    /** @var string|null */
    public $referrer;

    /**
     * Everything the form actually submitted, flattened to name => value.
     *
     * Deliberately separate from $fields. $fields is what the engine scans, and
     * it is filtered by type on purpose - a submit button or an acceptance
     * checkbox is not text worth scanning. But the log has a different job: it
     * has to let a site owner look at a blocked submission and decide whether it
     * was a real customer. Judging that from the text fields alone is guesswork
     * when the form also asked which service, which branch, and on what date.
     *
     * Every form plugin exposes its data differently, so this is captured from
     * the request as a whole rather than per plugin.
     *
     * @var array<string, string>
     */
    public $raw;

    /**
     * @param Field[]               $fields
     * @param array<string, string> $hidden
     * @param array<string, string> $raw
     */
    public function __construct(
        array $fields,
        string $source,
        string $sourceLabel,
        string $ip,
        array $hidden = [],
        ?string $referrer = null,
        array $raw = []
    ) {
        $this->fields = $fields;
        $this->source = $source;
        $this->sourceLabel = $sourceLabel;
        $this->ip = $ip;
        $this->hidden = $hidden;
        $this->referrer = $referrer;
        $this->raw = $raw;
    }

    public function hiddenField(string $name): ?string
    {
        return isset($this->hidden[$name]) ? $this->hidden[$name] : null;
    }

    /**
     * @param string $type one of FieldType::ALL
     * @return Field[]
     */
    public function fieldsOfType(string $type): array
    {
        return array_values(array_filter(
            $this->fields,
            static function (Field $f) use ($type): bool {
                return $f->type === $type;
            }
        ));
    }
}
