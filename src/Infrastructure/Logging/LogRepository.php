<?php

declare(strict_types=1);

namespace Maspik\Infrastructure\Logging;

use Maspik\Domain\Model\Field;
use Maspik\Domain\Model\Submission;
use Maspik\Domain\Model\Violation;
use Maspik\Infrastructure\Geo\FreeIpApiResolver;
use Maspik\Infrastructure\Settings\Settings;

/**
 * The only class that touches wp_maspik_spam_logs. Schema unchanged from v2.
 *
 * The log record is optimized for the Logs "inbox": enough to answer
 * "was this a real visitor blocked by mistake?" in seconds. Submitted fields
 * and the page URL live in a JSON envelope in spam_detail; country is resolved
 * (cached) into the existing spam_country column.
 */
final class LogRepository
{
    /** Highest log id the Logs screen has shown (shared, not per user). */
    private const LAST_SEEN_OPTION = 'maspik_logs_last_seen_id';

    /** Short cache for the menu badge — the menu is built on every request. */
    private const UNSEEN_TRANSIENT = 'maspik_logs_unseen';

    /** @var Settings */
    private $settings;

    /** @var FreeIpApiResolver */
    private $geo;

    public function __construct(Settings $settings, FreeIpApiResolver $geo)
    {
        $this->settings = $settings;
        $this->geo = $geo;
    }

    /** Back-compat entry point: a blocked submission with no captured trace. */
    public function recordBlocked(Submission $submission, Violation $violation): void
    {
        $this->record($submission, $violation, 'blocked', []);
    }

    /**
     * Store one submission — blocked or passed. The outcome lives in the
     * existing (previously unused) spam_tag column, and the per-layer trace in
     * the spam_detail JSON envelope, so no table migration is needed and legacy
     * rows (spam_tag '') keep reading as blocked.
     *
     * Only blocked rows count toward the lifetime/monthly totals and trigger a
     * geo lookup; passed rows (debug mode) skip both to keep the legit-submit
     * path light.
     *
     * @param array<int, array{layer: string, status: string, reason: string}> $trace
     */
    public function record(Submission $submission, ?Violation $violation, string $outcome, array $trace = []): void
    {
        $blocked = $outcome === 'blocked';

        global $wpdb;

        // Everything that was submitted, not just what the engine scanned. The
        // owner reading this row has to decide whether a real customer was
        // turned away, and a form's select, date and checkbox answers are often
        // the part that makes that obvious. Scanned fields are layered on top so
        // they are always present even when the raw capture came back empty.
        $fields = $submission->raw;
        foreach ($submission->fields as $field) {
            $value = $field->value;

            // The adapter understood the form, so its field name is the readable
            // one. Where the raw capture holds the same value under a transport
            // path (Ninja Forms posts everything inside a `formData` blob, so it
            // surfaces as formData[fields][5][value]), drop the duplicate and
            // keep the name a human can act on.
            if (trim($value) !== '') {
                foreach ($fields as $key => $existing) {
                    if ($key !== $field->name && $existing === $value) {
                        unset($fields[$key]);
                    }
                }
            }

            if (! isset($fields[$field->name]) || trim((string) $fields[$field->name]) === '') {
                $fields[$field->name] = $value;
            }
        }

        $detail = wp_json_encode([
            'fields' => $fields,
            'url' => $submission->referrer,
            'trace' => $trace,
        ]);

        $agent = isset($_SERVER['HTTP_USER_AGENT'])
            ? mb_substr(sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])), 0, 191)
            : '';

        // Geo is an external call — worth it to enrich a block, skipped on the
        // high-volume passed path.
        $country = '';
        if ($blocked) {
            $geo = $this->geo->lookup($submission->ip);
            $country = $geo !== null ? $geo['countryCode'] : '';
        }

        $wpdb->insert($wpdb->prefix . 'maspik_spam_logs', [
            'spam_type' => $violation !== null ? $violation->checkId : '',
            'spam_value' => $violation !== null ? mb_substr($violation->matchedValue, 0, 191) : '',
            'spam_detail' => $detail ? $detail : '',
            'spam_ip' => $submission->ip,
            'spam_country' => $country,
            'spam_agent' => $agent,
            'spam_date' => current_time('mysql'),
            'spam_source' => $submission->sourceLabel,
            'spamsrc_label' => $violation !== null ? mb_substr($violation->matchedRule, 0, 191) : '',
            'spamsrc_val' => $violation !== null ? mb_substr($violation->reason, 0, 191) : '',
            'spam_tag' => $blocked ? 'blocked' : 'clean',
        ]);

        if ($blocked) {
            $this->bumpCounter();
            // New spam arrived — let the menu badge pick it up immediately
            // instead of waiting out the cache window.
            delete_transient(self::UNSEEN_TRANSIENT);
        }
        $this->enforceLimit();
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        global $wpdb;

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}maspik_spam_logs WHERE id = %d",
            $id
        ), ARRAY_A);

        return is_array($row) ? self::normaliseRow($row) : null;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private static function normaliseRow(array $row): array
    {
        $row['spam_detail'] = self::normaliseDetail((string) ($row['spam_detail'] ?? ''));

        return $row;
    }

    /**
     * Present every log row's detail as the 3.0 JSON envelope, whatever wrote it.
     *
     * v2 stored `serialize($_POST)`; 3.0 stores JSON. Everything downstream —
     * the log list, the detail drawer, the dashboard, and the whitelist action —
     * calls json_decode/JSON.parse, which simply fails on a serialized string and
     * yields no fields at all. The submission was never lost; it just stopped
     * being readable, and the log became useless for the one job it has: letting
     * an owner decide whether a block was a false positive.
     *
     * Converting on read rather than migrating the table keeps this reversible
     * and costs nothing for the 3.0 rows, which are returned untouched.
     */
    private static function normaliseDetail(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }

        // Already a 3.0 envelope (or any JSON) - leave it exactly as stored.
        if ($raw[0] === '{' || $raw[0] === '[') {
            return $raw;
        }

        // Log rows are attacker-influenced data, so this must never be allowed to
        // instantiate objects: unserializing a crafted payload into a class with
        // a destructor is the classic PHP object-injection route to RCE.
        $data = @unserialize($raw, ['allowed_classes' => false]);
        if (! is_array($data)) {
            return '';
        }

        $fields = [];
        foreach ($data as $key => $value) {
            $name = (string) $key;
            if ($name === '') {
                continue;
            }
            // v2 logged the raw POST, so values arrive nested (WPForms) as well
            // as flat. Field::flatten is the same reducer the engine uses.
            $fields[$name] = is_scalar($value) ? (string) $value : Field::flatten($value);
        }

        $json = wp_json_encode([
            'fields' => $fields,
            // v2 kept the page URL in spam_source ("Label|||URL"), not here; the
            // admin already reads it from there for these rows.
            'url' => null,
            // v2 predates per-layer tracing, so there is nothing to show.
            'trace' => [],
        ]);

        return is_string($json) ? $json : '';
    }

    public function delete(int $id): bool
    {
        global $wpdb;

        return (bool) $wpdb->delete($wpdb->prefix . 'maspik_spam_logs', ['id' => $id]);
    }

    /**
     * Empty the log, honouring the outcome the screen is showing.
     *
     * Scoped rather than a blanket TRUNCATE because "blocked" and "passed" are
     * different material: clearing a reviewed pile of spam must not also throw
     * away the passed submissions someone kept for debugging, and the reverse
     * is just as true. The caller passes the same outcome the list and the CSV
     * export already use, so what disappears is exactly what was on screen.
     *
     * The lifetime counter behind totalBlocked() is deliberately left alone.
     * It counts blocks that happened, not rows retained — pruneByAge() and the
     * row cap already delete rows without touching it, and zeroing a lifetime
     * statistic as a side effect of tidying a list would be a surprise.
     *
     * @param string $outcome 'all' | 'blocked' | 'clean'
     * @return int rows removed
     */
    public function deleteAll(string $outcome = 'all'): int
    {
        global $wpdb;
        $table = $wpdb->prefix . 'maspik_spam_logs';

        // Legacy rows have spam_tag '' and count as blocked, exactly as in
        // recent(). No caller value reaches the SQL: the outcome only selects
        // which of these three fixed statements runs.
        if ($outcome === 'clean') {
            $deleted = $wpdb->query("DELETE FROM $table WHERE spam_tag = 'clean'");
        } elseif ($outcome === 'blocked') {
            $deleted = $wpdb->query("DELETE FROM $table WHERE spam_tag <> 'clean'");
        } else {
            $deleted = $wpdb->query("DELETE FROM $table");
        }

        return $deleted === false ? 0 : (int) $deleted;
    }

    /**
     * Re-label a blocked entry as legitimate, keeping the row.
     *
     * A false positive is the one log entry the site owner most needs to read:
     * it holds the name, email and message of a real person the site turned
     * away, and answering them is only possible while those details still
     * exist. So pardoning an entry changes its outcome instead of deleting it —
     * it leaves the blocked view and stops counting as a block, but the
     * submission itself is still there. Deleting stays a separate, explicit
     * action.
     */
    public function markNotSpam(int $id): bool
    {
        global $wpdb;

        return (bool) $wpdb->update(
            $wpdb->prefix . 'maspik_spam_logs',
            ['spam_tag' => 'clean'],
            ['id' => $id]
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    /**
     * @param string $outcome 'all' | 'blocked' | 'clean'
     * @return array<int, array<string, mixed>>
     */
    public function recent(int $limit = 50, int $offset = 0, string $outcome = 'all'): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'maspik_spam_logs';

        // Legacy rows have spam_tag '' → treated as blocked.
        if ($outcome === 'clean') {
            $sql = $wpdb->prepare("SELECT * FROM $table WHERE spam_tag = 'clean' ORDER BY id DESC LIMIT %d OFFSET %d", $limit, $offset);
        } elseif ($outcome === 'blocked') {
            $sql = $wpdb->prepare("SELECT * FROM $table WHERE spam_tag <> 'clean' ORDER BY id DESC LIMIT %d OFFSET %d", $limit, $offset);
        } else {
            $sql = $wpdb->prepare("SELECT * FROM $table ORDER BY id DESC LIMIT %d OFFSET %d", $limit, $offset);
        }

        $rows = $wpdb->get_results($sql, ARRAY_A);

        return is_array($rows) ? array_map([self::class, 'normaliseRow'], $rows) : [];
    }

    /**
     * How many rows the log actually holds for an outcome.
     *
     * Distinct from totalBlocked(), which is a lifetime counter of blocks that
     * happened. This counts rows still stored, so the screen can say how many
     * there are to page through rather than reporting the size of the first
     * page as though it were the whole log.
     *
     * @param string $outcome 'all' | 'blocked' | 'clean'
     */
    public function countStored(string $outcome = 'all'): int
    {
        global $wpdb;
        $table = $wpdb->prefix . 'maspik_spam_logs';

        // Legacy rows have spam_tag '' and count as blocked, as in recent().
        if ($outcome === 'clean') {
            return (int) $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE spam_tag = 'clean'");
        }
        if ($outcome === 'blocked') {
            return (int) $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE spam_tag <> 'clean'");
        }

        return (int) $wpdb->get_var("SELECT COUNT(*) FROM $table");
    }

    public function totalBlocked(): int
    {
        return (int) get_option('maspik_spam_count', 0);
    }

    /**
     * Per-check counts with last-hit time (spam_type == Violation::checkId).
     *
     * @return array<int, array{type: string, count: int, last: string}>
     */
    public function countsByType(): array
    {
        global $wpdb;

        $rows = $wpdb->get_results(
            "SELECT spam_type AS type, COUNT(*) AS count, MAX(spam_date) AS last
             FROM {$wpdb->prefix}maspik_spam_logs
             WHERE spam_tag <> 'clean'
             GROUP BY spam_type ORDER BY count DESC",
            ARRAY_A
        );

        return is_array($rows) ? $rows : [];
    }

    /**
     * @return array<int, array{source: string, count: int}>
     */
    public function countsBySource(): array
    {
        global $wpdb;

        $rows = $wpdb->get_results(
            "SELECT spam_source AS source, COUNT(*) AS count
             FROM {$wpdb->prefix}maspik_spam_logs
             WHERE spam_tag <> 'clean'
             GROUP BY spam_source ORDER BY count DESC",
            ARRAY_A
        );

        return is_array($rows) ? $rows : [];
    }

    /**
     * Daily counts for the last N days. spam_date is a varchar holding
     * current_time('mysql') — SUBSTR(…,1,10) yields the Y-m-d day (v2 schema,
     * unchanged; a real datetime column is the logged schema-v2 backlog item).
     *
     * @return array<int, array{day: string, count: int}>
     */
    public function countsByDay(int $days = 30): array
    {
        global $wpdb;

        $since = gmdate('Y-m-d', time() - $days * DAY_IN_SECONDS);
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT SUBSTR(spam_date, 1, 10) AS day, COUNT(*) AS count
             FROM {$wpdb->prefix}maspik_spam_logs
             WHERE spam_tag <> 'clean' AND SUBSTR(spam_date, 1, 10) >= %s
             GROUP BY day ORDER BY day ASC",
            $since
        ), ARRAY_A);

        return is_array($rows) ? $rows : [];
    }

    /**
     * @return array<string, mixed>|null newest log row, or null when empty
     */
    public function lastBlocked(): ?array
    {
        global $wpdb;

        $row = $wpdb->get_row(
            "SELECT * FROM {$wpdb->prefix}maspik_spam_logs ORDER BY id DESC LIMIT 1",
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }

    /** Bump the lifetime block counter without storing a row (logging off). */
    public function incrementBlockedCounter(): void
    {
        $this->bumpCounter();
    }

    /**
     * How many blocked submissions arrived since the Logs screen was last
     * opened — the number shown on the admin menu badge.
     *
     * Tracked by row id rather than date: ids are monotonic, so this is immune
     * to timezone handling and clock skew (spam_date is a local-time varchar).
     * The result is cached briefly because the admin menu is rebuilt on every
     * single wp-admin request.
     */
    public function unseenCount(): int
    {
        $cached = get_transient(self::UNSEEN_TRANSIENT);
        if ($cached !== false) {
            return (int) $cached;
        }

        global $wpdb;
        $count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}maspik_spam_logs
             WHERE id > %d AND spam_tag <> 'clean'",
            $this->lastSeenId()
        ));

        set_transient(self::UNSEEN_TRANSIENT, $count, MINUTE_IN_SECONDS);

        return $count;
    }

    /**
     * Mark everything currently logged as seen. Site-wide by design: one
     * shared marker, not per-user — whoever opens the Logs screen clears it
     * for everyone.
     */
    public function markSeen(): void
    {
        global $wpdb;

        $maxId = (int) $wpdb->get_var("SELECT MAX(id) FROM {$wpdb->prefix}maspik_spam_logs");
        update_option(self::LAST_SEEN_OPTION, $maxId, false);
        delete_transient(self::UNSEEN_TRANSIENT);
    }

    /**
     * Treat everything already stored as seen. Called on install/upgrade so a
     * site updating with thousands of historical rows doesn't suddenly show a
     * huge badge for spam the owner has already dealt with.
     */
    public function baselineSeen(): void
    {
        if (get_option(self::LAST_SEEN_OPTION, null) === null) {
            $this->markSeen();
        }
    }

    private function lastSeenId(): int
    {
        return (int) get_option(self::LAST_SEEN_OPTION, 0);
    }

    private function bumpCounter(): void
    {
        update_option('maspik_spam_count', $this->totalBlocked() + 1, false);
    }

    /**
     * Keep the table under the configured row limit. When trimming, the oldest
     * *passed* rows go first so the more valuable blocked-spam history is
     * preserved for as long as possible.
     */
    private function enforceLimit(): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'maspik_spam_logs';

        $limit = $this->settings->intOrNull('spam_log_limit');
        $limit = $limit !== null ? $limit : 1000;
        if ($limit <= 0) {
            return;
        }

        $count = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table");
        $over = $count - $limit;
        if ($over <= 0) {
            return;
        }

        $deleted = (int) $wpdb->query($wpdb->prepare(
            "DELETE FROM $table WHERE spam_tag = 'clean' ORDER BY id ASC LIMIT %d",
            $over
        ));
        if ($over - $deleted > 0) {
            $wpdb->query($wpdb->prepare(
                "DELETE FROM $table ORDER BY id ASC LIMIT %d",
                $over - $deleted
            ));
        }
    }

    /**
     * Delete rows older than the configured age (spam_log_max_age_days; 0/unset
     * = off). Run from a daily cron — never on the submission path. spam_date is
     * a site-local 'Y-m-d H:i:s' string, so the cutoff is computed in site time.
     */
    public function pruneByAge(): void
    {
        global $wpdb;

        $days = $this->settings->intOrNull('spam_log_max_age_days');
        if ($days === null || $days <= 0) {
            return;
        }

        $cutoff = gmdate('Y-m-d H:i:s', current_time('timestamp') - $days * DAY_IN_SECONDS);
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}maspik_spam_logs WHERE spam_date < %s",
            $cutoff
        ));
    }
}
