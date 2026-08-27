<?php

declare(strict_types=1);

namespace Maspik\Domain;

use Maspik\Domain\Check\Support\CidrMatcher;
use Maspik\Domain\Model\FieldType;
use Maspik\Domain\Model\Submission;

/**
 * User-curated allow list, populated from the Logs "Not spam" / "Whitelist"
 * actions. Checked BEFORE the pipeline: a match short-circuits the whole
 * submission to clean, so a wrongly-blocked visitor is never blocked again.
 *
 * Behavior-safe by construction: empty lists (the default) allow nothing,
 * so existing detection is unchanged until the user explicitly whitelists.
 */
final class AllowList
{
    /** @var string[] IPs and/or CIDR ranges */
    private $ips;

    /** @var string[] email addresses (lowercased on compare) */
    private $emails;

    /**
     * @param string[] $ips
     * @param string[] $emails
     */
    public function __construct(array $ips, array $emails)
    {
        $this->ips = $ips;
        $this->emails = $emails;
    }

    public function allows(Submission $submission): bool
    {
        if ($this->ipAllowed($submission->ip)) {
            return true;
        }

        foreach ($submission->fields as $field) {
            if ($field->type === FieldType::EMAIL && $this->emailAllowed($field->value)) {
                return true;
            }
        }

        return false;
    }

    private function ipAllowed(string $ip): bool
    {
        foreach ($this->ips as $entry) {
            $entry = trim($entry);
            if ($entry === '') {
                continue;
            }
            if ($entry === $ip) {
                return true;
            }
            if (CidrMatcher::isCidr($entry) && CidrMatcher::matches($ip, $entry)) {
                return true;
            }
        }

        return false;
    }

    private function emailAllowed(string $value): bool
    {
        $value = strtolower(trim($value));
        if ($value === '') {
            return false;
        }
        foreach ($this->emails as $entry) {
            if (strtolower(trim($entry)) === $value) {
                return true;
            }
        }

        return false;
    }
}
