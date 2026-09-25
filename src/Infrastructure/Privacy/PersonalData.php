<?php

declare(strict_types=1);

namespace Maspik\Infrastructure\Privacy;

use Maspik\Infrastructure\Logging\LogRepository;

if (! defined('ABSPATH')) {
    exit;
}


/**
 * Answers WordPress' personal-data export and erasure requests.
 *
 * The spam log holds what a visitor typed, the address they sent it from and
 * the browser they used. That is their data, and WordPress already gives a site
 * owner one place to export or delete everything a site holds about a person -
 * Tools → Export/Erase Personal Data. A plugin that keeps records outside that
 * mechanism leaves the owner unable to answer a request honestly, because they
 * cannot see what they are holding.
 *
 * HOW A ROW IS MATCHED TO A PERSON
 *
 * WordPress identifies the subject by email address, so that is the only handle
 * available. A row belongs to that address when the address is one of the values
 * the form captured, or is the value a rule matched on.
 *
 * Matching is exact against whole field values, not a search through the text.
 * An address written inside a message ("write to me at …") is somebody else's
 * submission that happens to mention this person, and erasing it on that basis
 * would delete another visitor's data to satisfy this request. The cost of that
 * choice is that a mention in prose is not found; the alternative cost is
 * deleting the wrong record, which is worse and cannot be undone.
 *
 * Rows recorded against an IP address with no email in them cannot be matched to
 * a person at all. Nothing here can change that - there is no identifier to
 * match on - and the site owner can still delete any row by hand.
 */
final class PersonalData
{
    /** Rows examined per page. WordPress calls back until `done` is true. */
    private const PAGE_SIZE = 100;

    /**
     * How long the eraser's place in the table is remembered between the
     * batches WordPress runs. Long enough that a slow queue does not lose it,
     * short enough that an abandoned request leaves nothing behind.
     */
    private const CURSOR_TTL = 3600;

    /** @var LogRepository */
    private $logs;

    public function __construct(LogRepository $logs)
    {
        $this->logs = $logs;
    }

    public function register(): void
    {
        add_filter('wp_privacy_personal_data_exporters', \Maspik\Kernel\Guard::wrap([$this, 'registerExporter']));
        add_filter('wp_privacy_personal_data_erasers', \Maspik\Kernel\Guard::wrap([$this, 'registerEraser']));
    }

    /**
     * @param array<string, mixed> $exporters
     * @return array<string, mixed>
     */
    public function registerExporter(array $exporters): array
    {
        $exporters['maspik'] = [
            'exporter_friendly_name' => __('Maspik spam log', 'contact-forms-anti-spam'),
            'callback' => [$this, 'export'],
        ];

        return $exporters;
    }

    /**
     * @param array<string, mixed> $erasers
     * @return array<string, mixed>
     */
    public function registerEraser(array $erasers): array
    {
        $erasers['maspik'] = [
            'eraser_friendly_name' => __('Maspik spam log', 'contact-forms-anti-spam'),
            'callback' => [$this, 'erase'],
        ];

        return $erasers;
    }

    /**
     * @return array{data: array<int, array<string, mixed>>, done: bool}
     */
    public function export(string $email, int $page = 1): array
    {
        // Reading changes nothing, so the rows stay where they were and a plain
        // offset walks them in order.
        $rows = $this->page($email, 0, (max(1, $page) - 1) * self::PAGE_SIZE);

        $data = [];
        foreach ($rows['matched'] as $row) {
            $data[] = [
                'group_id' => 'maspik-spam-log',
                'group_label' => __('Form submissions blocked as spam', 'contact-forms-anti-spam'),
                'item_id' => 'maspik-' . (int) $row['id'],
                'data' => $this->exportItems($row),
            ];
        }

        return ['data' => $data, 'done' => $rows['done']];
    }

    /**
     * @return array{items_removed: bool, items_retained: bool, messages: array<int, string>, done: bool}
     */
    /**
     * An offset cannot be used here, because this call changes what it is
     * counting. Delete a hundred rows on page one and the hundred-and-first row
     * is now the first; asking for `OFFSET 100` then steps over a hundred rows
     * that were never examined, and WordPress reports the erasure complete with
     * those rows still in the table.
     *
     * So the walk moves by id instead. Every batch starts after the highest id
     * it has already looked at, which is stable whether or not the rows behind
     * it still exist. Rows that matched are gone; rows that only looked like a
     * match under the LIKE are behind the cursor and are not examined twice.
     *
     * @return array{items_removed: bool, items_retained: bool, messages: array<int, string>, done: bool}
     */
    public function erase(string $email, int $page = 1): array
    {
        // WordPress restarts every eraser at page one, so that is where a stale
        // cursor from an abandoned request gets discarded.
        $after = max(1, $page) === 1 ? 0 : $this->cursor($email);

        $rows = $this->page($email, $after, 0);

        $removed = false;
        foreach ($rows['matched'] as $row) {
            // The whole row goes, not just the address. The message, the IP and
            // the user agent are the same person's data; blanking one column
            // would leave the rest and answer the request only in part.
            if ($this->logs->delete((int) $row['id'])) {
                $removed = true;
            }
        }

        if ($rows['done']) {
            $this->clearCursor($email);
        } else {
            $this->setCursor($email, $rows['lastId']);
        }

        return [
            'items_removed' => $removed,
            'items_retained' => false,
            'messages' => [],
            'done' => $rows['done'],
        ];
    }

    /**
     * One page of rows that belong to this address.
     *
     * The query narrows with LIKE because the values live inside a JSON column,
     * then every candidate is confirmed field by field - LIKE would also match
     * an address quoted inside a longer string, which is exactly the case this
     * must not act on.
     *
     * `done` describes the walk through the table, not the matches: a full page
     * whose candidates all turn out to belong to somebody else still has rows
     * behind it. Reporting completion there would end an erasure early.
     *
     * A malformed address stops before the query. `%` and `@` are not addresses,
     * but as a LIKE they would select most of the log, and this is a code path
     * that deletes what it selects.
     *
     * @return array{matched: array<int, array<string, mixed>>, done: bool, lastId: int}
     */
    private function page(string $email, int $afterId, int $offset): array
    {
        global $wpdb;

        $email = strtolower(trim($email));
        if ($email === '' || ! is_email($email)) {
            return ['matched' => [], 'done' => true, 'lastId' => 0];
        }

        $table = $wpdb->prefix . 'maspik_spam_logs';
        $like = '%' . $wpdb->esc_like($email) . '%';

        $candidates = $wpdb->get_results(
            $wpdb->prepare(
                // The OR is parenthesised deliberately: without it `AND id > %d`
                // would bind to the second branch only, and the first branch
                // would ignore the cursor entirely.
                "SELECT * FROM `$table`
                 WHERE (spam_detail LIKE %s OR LOWER(spam_value) = %s)
                   AND id > %d
                 ORDER BY id ASC
                 LIMIT %d OFFSET %d",
                $like,
                $email,
                $afterId,
                self::PAGE_SIZE,
                $offset
            ),
            ARRAY_A
        );

        $candidates = is_array($candidates) ? $candidates : [];

        $matched = [];
        $lastId = $afterId;
        foreach ($candidates as $row) {
            $lastId = max($lastId, (int) $row['id']);
            if ($this->belongsTo($row, $email)) {
                $matched[] = $row;
            }
        }

        return [
            'matched' => $matched,
            'done' => count($candidates) < self::PAGE_SIZE,
            'lastId' => $lastId,
        ];
    }

    private function cursor(string $email): int
    {
        return (int) get_transient($this->cursorKey($email));
    }

    private function setCursor(string $email, int $id): void
    {
        set_transient($this->cursorKey($email), $id, self::CURSOR_TTL);
    }

    private function clearCursor(string $email): void
    {
        delete_transient($this->cursorKey($email));
    }

    /** Hashed: the key is stored in the options table, and this is an address. */
    private function cursorKey(string $email): string
    {
        return 'maspik_erase_' . md5(strtolower(trim($email)));
    }

    /**
     * True when this address is one the form actually captured on this row.
     *
     * @param array<string, mixed> $row
     */
    private function belongsTo(array $row, string $email): bool
    {
        if (strtolower(trim((string) ($row['spam_value'] ?? ''))) === $email) {
            return true;
        }

        foreach ($this->fieldsOf($row) as $value) {
            if (strtolower(trim($value)) === $email) {
                return true;
            }
        }

        return false;
    }

    /**
     * The submitted values on a row, whatever shape they were stored in.
     *
     * Rows written by version 2 hold a serialized array rather than the JSON
     * used since; the repository normalises both to the same structure on read,
     * so a request covers a site's whole history rather than only what it
     * recorded after upgrading.
     *
     * @param array<string, mixed> $row
     * @return array<string, string>
     */
    private function fieldsOf(array $row): array
    {
        // Through the repository's converter, not json_decode: rows written by
        // version 2 hold a serialized array, so decoding them as JSON found no
        // fields at all. Every one of this person's pre-upgrade submissions was
        // invisible to both the export and the erasure, and the erasure still
        // reported itself complete.
        $detail = json_decode(LogRepository::normaliseDetail((string) ($row['spam_detail'] ?? '')), true);
        if (! is_array($detail)) {
            return [];
        }

        $fields = isset($detail['fields']) && is_array($detail['fields']) ? $detail['fields'] : $detail;

        $out = [];
        foreach ($fields as $name => $value) {
            if (is_scalar($value)) {
                $out[(string) $name] = (string) $value;
            }
        }

        return $out;
    }

    /**
     * A row rendered as the export file shows it: what they sent, and the
     * technical details recorded alongside it.
     *
     * The reason and the rule that matched are deliberately included. Someone
     * asking what a site holds about them is usually asking because something
     * they sent did not arrive, and "which rule stopped it" is the part that
     * actually answers them.
     *
     * @param array<string, mixed> $row
     * @return array<int, array{name: string, value: string}>
     */
    private function exportItems(array $row): array
    {
        $items = [
            ['name' => __('Date', 'contact-forms-anti-spam'), 'value' => (string) ($row['spam_date'] ?? '')],
            ['name' => __('Form', 'contact-forms-anti-spam'), 'value' => (string) ($row['spam_source'] ?? '')],
            ['name' => __('Outcome', 'contact-forms-anti-spam'), 'value' => $this->outcome($row)],
            ['name' => __('Reason', 'contact-forms-anti-spam'), 'value' => (string) ($row['spamsrc_val'] ?? '')],
            ['name' => __('IP address', 'contact-forms-anti-spam'), 'value' => (string) ($row['spam_ip'] ?? '')],
            ['name' => __('Country', 'contact-forms-anti-spam'), 'value' => (string) ($row['spam_country'] ?? '')],
            ['name' => __('Browser', 'contact-forms-anti-spam'), 'value' => (string) ($row['spam_agent'] ?? '')],
        ];

        foreach ($this->fieldsOf($row) as $name => $value) {
            $items[] = ['name' => $name, 'value' => $value];
        }

        return array_values(array_filter($items, static function (array $item): bool {
            return $item['value'] !== '';
        }));
    }

    /** @param array<string, mixed> $row */
    private function outcome(array $row): string
    {
        $tag = (string) ($row['spam_tag'] ?? '');

        if ($tag === 'clean') {
            return __('Allowed', 'contact-forms-anti-spam');
        }

        if ($tag === 'confirmed') {
            return __('Blocked, confirmed as spam by the site owner', 'contact-forms-anti-spam');
        }

        return __('Blocked', 'contact-forms-anti-spam');
    }
}
