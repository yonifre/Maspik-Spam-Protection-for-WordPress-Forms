<?php

declare(strict_types=1);

namespace Maspik\Admin;

use Maspik\Application\CheckFactory;
use Maspik\Admin\FullModeNudge;
use Maspik\Infrastructure\Logging\LogRepository;
use Maspik\Infrastructure\Settings\Settings;

/**
 * The "At a glance" widget on the WordPress Dashboard.
 *
 * v2 shipped one too, but it pulled Chart.js from a CDN on every dashboard
 * load: a third-party request on an admin screen, for two charts nobody can act
 * on. This one answers the questions an owner actually has when they log in —
 * is it working, what did it stop, and is anything wrong — and draws its own
 * sparkline as inline SVG, so it costs no external request and no script.
 *
 * Everything here is read-only and runs only on index.php.
 */
final class DashboardWidget
{
    private const WIDGET_ID = 'maspik_at_a_glance';
    private const DAYS = 30;

    /** How many rows each ranked list shows. */
    private const TOP = 5;

    /** @var LogRepository */
    private $logs;

    /** @var Settings */
    private $settings;

    /** @var CheckFactory */
    private $checks;

    public function __construct(LogRepository $logs, Settings $settings, CheckFactory $checks)
    {
        $this->logs = $logs;
        $this->settings = $settings;
        $this->checks = $checks;
    }

    public function register(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        wp_add_dashboard_widget(
            self::WIDGET_ID,
            __('Maspik – Spam protection', 'contact-forms-anti-spam'),
            [$this, 'render']
        );
    }

    public function render(): void
    {
        // Re-checked at render: register() runs during wp_dashboard_setup, and a
        // widget already on a user's dashboard is rendered from stored meta.
        if (! current_user_can('manage_options')) {
            return;
        }

        $byDay = $this->logs->countsByDay(self::DAYS);
        $series = $this->series($byDay);
        $blocked = array_sum($series);
        $today = $series === [] ? 0 : (int) end($series);
        $mode = $this->settings->logMode();
        $layers = count($this->checks->pipelineOrder());
        $pages = [
            'logs' => admin_url('admin.php?page=maspik-logs'),
            'protection' => admin_url('admin.php?page=maspik-protection'),
            'advanced' => admin_url('admin.php?page=maspik-advanced'),
        ];

        $this->styles();
        echo '<div class="mkw">';

        $this->headline($blocked, $today, $series, $mode, $layers);

        if ($mode === 'none') {
            // The number above is the lifetime counter, which keeps rising with
            // logging off — but every breakdown below reads stored rows, so it
            // would read as "nothing was blocked". Say which it is.
            $this->notice(
                __('Logging is off, so there is nothing to break down here. Blocking itself is unaffected.', 'contact-forms-anti-spam'),
                $pages['advanced'],
                __('Turn on logging', 'contact-forms-anti-spam')
            );
        } elseif ($blocked === 0) {
            $this->notice(
                $layers > 0
                    ? __('Nothing blocked in the last 30 days. Your forms are quiet.', 'contact-forms-anti-spam')
                    : __('No protection layer is switched on yet.', 'contact-forms-anti-spam'),
                $layers > 0 ? $pages['logs'] : $pages['protection'],
                $layers > 0 ? __('Open the log', 'contact-forms-anti-spam') : __('Set up protection', 'contact-forms-anti-spam')
            );
        } else {
            $this->breakdown($blocked);
        }

        $this->inputGate();
        $this->footer($pages);
        echo '</div>';
    }

    /**
     * Daily counts filled out to one entry per day.
     *
     * countsByDay() returns only days that had a block, so a run of quiet days
     * would otherwise compress the sparkline and make a single spike look like
     * steady traffic.
     *
     * @param array<int, array{day: string, count: int}> $rows
     * @return array<string, int> date => count, oldest first
     */
    private function series(array $rows): array
    {
        $counts = [];
        foreach ($rows as $row) {
            $counts[(string) $row['day']] = (int) $row['count'];
        }

        $out = [];
        for ($i = self::DAYS - 1; $i >= 0; $i--) {
            $day = gmdate('Y-m-d', time() - $i * DAY_IN_SECONDS);
            $out[$day] = isset($counts[$day]) ? $counts[$day] : 0;
        }

        return $out;
    }

    /** @param array<string, int> $series */
    private function headline(int $blocked, int $today, array $series, string $mode, int $layers): void
    {
        $lifetime = $this->logs->totalBlocked();
        // With logging off there are no rows to count, so the only honest
        // number is the lifetime counter the engine keeps regardless.
        $number = $mode === 'none' ? $lifetime : $blocked;
        $caption = $mode === 'none'
            ? __('blocked in total', 'contact-forms-anti-spam')
            : __('blocked in the last 30 days', 'contact-forms-anti-spam');

        echo '<div class="mkw-top">';
        echo '<div class="mkw-figure">';
        echo '<span class="mkw-number">' . esc_html(number_format_i18n($number)) . '</span>';
        echo '<span class="mkw-caption">' . esc_html($caption) . '</span>';
        echo '</div>';
        if ($mode !== 'none' && $blocked > 0) {
            echo $this->sparkline(array_values($series)); // built here, all values cast to int
        }
        echo '</div>';

        echo '<p class="mkw-status">';
        printf(
            /* translators: %s: number of active protection layers */
            esc_html(_n('%s protection layer active', '%s protection layers active', $layers, 'contact-forms-anti-spam')),
            '<strong>' . esc_html(number_format_i18n($layers)) . '</strong>'
        );
        if ($mode !== 'none' && $blocked > 0) {
            // On the status line rather than under the number: appended to the
            // caption it pushed "blocked in the last 30 days" onto two lines in
            // a narrow column, and it belongs with the other secondary facts.
            // One decimal, because "3" hides the difference between a trickle
            // and a steady stream.
            echo ' <span class="mkw-sep">·</span> ';
            printf(
                /* translators: %s: average number of submissions blocked per day */
                esc_html__('%s a day', 'contact-forms-anti-spam'),
                '<strong>' . esc_html(number_format_i18n($blocked / self::DAYS, 1)) . '</strong>'
            );
        }
        if ($mode !== 'none' && $today > 0) {
            echo ' <span class="mkw-sep">·</span> ';
            printf(
                /* translators: %s: number blocked today */
                esc_html__('%s today', 'contact-forms-anti-spam'),
                '<strong>' . esc_html(number_format_i18n($today)) . '</strong>'
            );
        }
        echo '</p>';
    }

    /** The two things worth knowing after "how many": which rule, and which form. */
    private function breakdown(int $blocked): void
    {
        $types = $this->logs->topWithin('spam_type', self::DAYS, self::TOP);
        $values = $this->logs->topWithin('spam_value', self::DAYS, self::TOP);
        $sources = $this->logs->topWithin('spam_source', self::DAYS, self::TOP);
        $last = $this->logs->lastBlocked();

        // Which layer did the work, and which of your rules did. v2 answered
        // both with a pie chart and a ten-row table; five ranked bars say the
        // same thing in a glance and need no charting library to draw.
        $this->bars(__('Blocked by layer', 'contact-forms-anti-spam'), $types, $blocked, true);
        $this->bars(__('Most caught values', 'contact-forms-anti-spam'), $values, $blocked, false);

        echo '<ul class="mkw-facts">';

        if (isset($sources[0]['label'])) {
            $this->fact(
                __('Most targeted form', 'contact-forms-anti-spam'),
                (string) $sources[0]['label'],
                (int) $sources[0]['count']
            );
        }
        if ($last !== null && ! empty($last['spam_date'])) {
            $stamp = strtotime((string) $last['spam_date']);
            $this->fact(
                __('Last blocked', 'contact-forms-anti-spam'),
                $stamp
                    ? sprintf(
                        /* translators: %s: human time difference, e.g. "5 mins" */
                        __('%s ago', 'contact-forms-anti-spam'),
                        human_time_diff($stamp, time())
                    )
                    : (string) $last['spam_date'],
                null
            );
        }

        echo '</ul>';
    }

    /**
     * Human name for a check id, mirroring CHECK_NAMES in the admin app.
     *
     * The two lists have to agree — the same block is described here and on the
     * Logs screen — so this is the same mapping, kept short deliberately: a
     * widget line reads "Caught most by Text rules", not by "text_blacklist".
     * An id with no entry falls back to itself rather than to something vague,
     * so a new check shows up as a name to fix rather than as "Other".
     */
    private static function checkName(string $id): string
    {
        $names = [
            'maspikHoneypot' => __('Honeypot', 'contact-forms-anti-spam'),
            'verification_key' => __('Verification Key', 'contact-forms-anti-spam'),
            'ai_spam_check' => __('InputGate', 'contact-forms-anti-spam'),
            'text_blacklist' => __('Text rules', 'contact-forms-anti-spam'),
            'textarea_field' => __('Text rules', 'contact-forms-anti-spam'),
            'emails_blacklist' => __('Email rules', 'contact-forms-anti-spam'),
            'url_blacklist' => __('URL rules', 'contact-forms-anti-spam'),
            'country_blacklist' => __('Country rules', 'contact-forms-anti-spam'),
            'ip_blacklist' => __('IP blocklist', 'contact-forms-anti-spam'),
            'abuseipdb_api' => __('IP reputation', 'contact-forms-anti-spam'),
            'proxycheck_io_api' => __('IP reputation', 'contact-forms-anti-spam'),
            'tel_formats' => __('Phone format', 'contact-forms-anti-spam'),
            'contain_links' => __('Link limit', 'contact-forms-anti-spam'),
            'emoji_check' => __('Emoji', 'contact-forms-anti-spam'),
            'lang_needed' => __('Language required', 'contact-forms-anti-spam'),
            'lang_forbidden' => __('Language forbidden', 'contact-forms-anti-spam'),
            'MaxCharactersInTextField' => __('Length limit', 'contact-forms-anti-spam'),
            'MaxCharactersInTextAreaField' => __('Length limit', 'contact-forms-anti-spam'),
            'MaxCharactersInPhoneField' => __('Length limit', 'contact-forms-anti-spam'),
        ];

        return isset($names[$id]) && $id !== '' ? $names[$id] : ($id !== '' ? $id : __('Unknown', 'contact-forms-anti-spam'));
    }

    /**
     * InputGate's standing, and the one action that improves it.
     *
     * The cloud layer has three states and only one of them needs saying. Off
     * is the weakest configuration there is — the local rules only catch what
     * someone already thought to write down — and IP-only is the quiet one: the
     * layer reports as enabled while form content is never analysed, so a site
     * owner reasonably believes they have protection they do not have. Both are
     * fixed by the same nonce-protected link FullModeNudge already owns.
     *
     * Nothing is printed when the layer is fully on. A widget that congratulates
     * you every day for a setting you are not changing is a widget people learn
     * to skip, and then they skip the day it matters.
     */
    private function inputGate(): void
    {
        $enabled = $this->settings->boolEffective('maspik_ai_enabled');
        $ipOnly = $this->settings->matrixMode() === '2';

        if ($enabled && ! $ipOnly) {
            return;
        }

        $text = $enabled
            ? __('For your information: Maspik is checking the sender’s IP address only, not the content of the submission. Full Protection analyses the content too and catches more spam — it is free.', 'contact-forms-anti-spam')
            : __('InputGate is switched off. It is our cloud layer, and the one that catches the spam your own rules have not seen before. We recommend turning it on.', 'contact-forms-anti-spam');
        $action = $enabled
            ? __('Enable Full Protection', 'contact-forms-anti-spam')
            : __('Turn on InputGate', 'contact-forms-anti-spam');

        echo '<div class="mkw-gate">';
        echo '<p class="mkw-gate__text">' . esc_html($text) . '</p>';
        echo '<p class="mkw-gate__actions">';
        echo '<a class="button button-primary button-small" href="' . esc_url(FullModeNudge::activateUrl()) . '">'
            . esc_html($action) . '</a> ';
        echo '<a class="mkw-gate__learn" href="' . esc_url(FullModeNudge::LEARN_MORE_URL) . '" target="_blank" rel="noopener noreferrer">'
            . esc_html__('Learn more', 'contact-forms-anti-spam') . '</a>';
        echo '</p></div>';
    }

    /**
     * A ranked list with a proportion bar per row.
     *
     * The bar is scaled to the largest row, not to the total, because the
     * question these answer is "which of these dominates" — against the total,
     * a spread of five similar causes renders as five identical stubs. The
     * percentage next to it is of the total, so the absolute share is still
     * there to read.
     *
     * @param array<int, array{label: string, count: int}> $rows
     * @param bool $asCheckName true when the labels are check ids
     */
    private function bars(string $title, array $rows, int $total, bool $asCheckName): void
    {
        if ($rows === []) {
            return;
        }

        $max = 0;
        foreach ($rows as $row) {
            $max = max($max, (int) $row['count']);
        }
        if ($max <= 0) {
            return;
        }

        echo '<div class="mkw-rank"><h4 class="mkw-rank__title">' . esc_html($title) . '</h4>';
        foreach ($rows as $row) {
            $count = (int) $row['count'];
            $label = $asCheckName ? self::checkName((string) $row['label']) : (string) $row['label'];
            // A blocked value can be a whole spam paragraph; the row is a label,
            // not the evidence, and the log itself holds the full text.
            if (mb_strlen($label) > 32) {
                $label = mb_substr($label, 0, 32) . '…';
            }
            $share = $total > 0 ? round($count / $total * 100) : 0;
            $width = round($count / $max * 100);

            echo '<div class="mkw-rank__row">';
            echo '<span class="mkw-rank__label" title="' . esc_attr($asCheckName ? self::checkName((string) $row['label']) : (string) $row['label']) . '">'
                . esc_html($label) . '</span>';
            echo '<span class="mkw-rank__bar"><span style="width:' . esc_attr((string) $width) . '%"></span></span>';
            echo '<span class="mkw-rank__count">' . esc_html(number_format_i18n($count)) . '</span>';
            echo '<span class="mkw-rank__pct">' . esc_html(sprintf('%d%%', $share)) . '</span>';
            echo '</div>';
        }
        echo '</div>';
    }

    private function fact(string $label, string $value, ?int $count): void
    {
        echo '<li class="mkw-fact">';
        echo '<span class="mkw-fact__label">' . esc_html($label) . '</span>';
        echo '<span class="mkw-fact__value">' . esc_html($value);
        if ($count !== null) {
            echo ' <span class="mkw-fact__count">' . esc_html(number_format_i18n($count)) . '</span>';
        }
        echo '</span></li>';
    }

    private function notice(string $text, string $url, string $action): void
    {
        echo '<p class="mkw-notice">' . esc_html($text) . ' ';
        echo '<a href="' . esc_url($url) . '">' . esc_html($action) . '</a></p>';
    }

    /** @param array<string, string> $pages */
    private function footer(array $pages): void
    {
        echo '<p class="mkw-links">';
        echo '<a href="' . esc_url($pages['logs']) . '">' . esc_html__('View log', 'contact-forms-anti-spam') . '</a>';
        echo ' <span class="mkw-sep">·</span> ';
        echo '<a href="' . esc_url($pages['protection']) . '">' . esc_html__('Protection', 'contact-forms-anti-spam') . '</a>';
        echo '</p>';
    }

    /**
     * A 30-day sparkline as inline SVG.
     *
     * Drawn rather than charted: this is a shape, not a data table, and the one
     * question it answers — steady trickle or sudden spike — does not need axes,
     * a legend, or 70KB of charting library fetched from someone else's server.
     *
     * @param array<int, int> $values
     */
    private function sparkline(array $values): string
    {
        $count = count($values);
        if ($count < 2) {
            return '';
        }

        $width = 150;
        $height = 34;
        $max = max($values);
        $max = $max > 0 ? $max : 1;
        $step = $width / ($count - 1);

        $points = [];
        foreach (array_values($values) as $i => $value) {
            $x = round($i * $step, 2);
            // 1px of headroom top and bottom so a peak is not clipped by the
            // viewBox and a zero still sits on a visible baseline.
            $y = round($height - 1 - ((int) $value / $max) * ($height - 2), 2);
            $points[] = $x . ',' . $y;
        }

        $line = implode(' ', $points);
        $area = '0,' . $height . ' ' . $line . ' ' . $width . ',' . $height;

        return '<svg class="mkw-spark" viewBox="0 0 ' . $width . ' ' . $height . '" width="' . $width . '" height="' . $height . '"'
            . ' role="img" aria-label="' . esc_attr__('Blocked submissions per day over the last 30 days', 'contact-forms-anti-spam') . '">'
            . '<polygon points="' . esc_attr($area) . '" fill="currentColor" opacity="0.12"/>'
            . '<polyline points="' . esc_attr($line) . '" fill="none" stroke="currentColor" stroke-width="1.5"'
            . ' stroke-linejoin="round" stroke-linecap="round"/>'
            . '</svg>';
    }

    /**
     * Scoped to .mkw and printed once with the widget.
     *
     * Inline rather than enqueued because it is a few hundred bytes that only
     * this widget uses, and an extra stylesheet request on every dashboard load
     * costs more than the bytes it saves.
     */
    private function styles(): void
    {
        static $printed = false;
        if ($printed) {
            return;
        }
        $printed = true;

        echo '<style>
.mkw{color:#1d2327}
.mkw-top{display:flex;align-items:flex-end;justify-content:space-between;gap:16px}
.mkw-figure{display:flex;flex-direction:column;line-height:1.1}
.mkw-number{font-size:32px;font-weight:600;font-variant-numeric:tabular-nums}
.mkw-caption{color:#646970;font-size:12px;margin-top:4px}
.mkw-figure{min-width:0}
/* Shrinks rather than wrapping: dashboard columns run from about 250px
   to 500px wide, and a sparkline that drops onto its own line at the
   narrow end leaves the number sitting beside empty space. */
.mkw-spark{color:#d63638;width:150px;max-width:45%;height:34px;flex-shrink:1;min-width:70px}
.mkw-status{margin:12px 0 0;color:#646970;font-size:13px}
.mkw-status strong{color:#1d2327}
.mkw-sep{color:#c3c4c7}
.mkw-rank{margin:14px 0 0;padding:12px 0 0;border-top:1px solid #f0f0f1}
.mkw-rank__title{margin:0 0 8px;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.4px;color:#646970}
/* The bar is the flexible column, not the label: it is the part being
   compared, so it should get the room a wider widget provides. */
.mkw-rank__row{display:grid;grid-template-columns:minmax(0,7.5rem) minmax(24px,1fr) 2.1rem 2.1rem;align-items:center;gap:8px;padding:3px 0;font-size:13px}
.mkw-rank__label{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.mkw-rank__bar{background:#f0f0f1;border-radius:3px;height:7px;overflow:hidden;min-width:0}
.mkw-rank__bar>span{display:block;height:100%;background:#d63638;border-radius:2px}
.mkw-rank__count{text-align:right;font-variant-numeric:tabular-nums}
.mkw-rank__pct{text-align:right;color:#646970;font-variant-numeric:tabular-nums;font-size:12px}
.mkw-facts{margin:12px 0 0;padding:12px 0 0;border-top:1px solid #f0f0f1;list-style:none}
.mkw-fact{display:flex;justify-content:space-between;gap:12px;padding:4px 0;font-size:13px}
.mkw-fact__label{color:#646970;flex-shrink:0}
.mkw-fact__value{text-align:right;min-width:0;overflow-wrap:anywhere}
.mkw-fact__count{color:#646970;font-variant-numeric:tabular-nums}
.mkw-notice{margin:12px 0 0;padding:10px 12px;background:#f6f7f7;border-left:3px solid #dba617;font-size:13px;color:#50575e}
.mkw-gate{margin:12px 0 0;padding:12px;background:#f0f6fc;border-left:3px solid #72aee6;border-radius:2px}
.mkw-gate__text{margin:0 0 10px;font-size:13px;line-height:1.5;color:#1d2327}
.mkw-gate__actions{margin:0;display:flex;align-items:center;gap:12px;flex-wrap:wrap}
.mkw-gate__learn{font-size:12px}
.mkw-links{margin:12px 0 0;padding-top:12px;border-top:1px solid #f0f0f1;font-size:13px}
@media(prefers-color-scheme:dark){.mkw{color:inherit}}
</style>';
    }
}
