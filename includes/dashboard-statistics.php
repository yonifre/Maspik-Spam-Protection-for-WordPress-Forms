<?php
// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}
// file name: dashboard-statistics.php

function maspik_format_reason_value($text) {
    // If no pattern found, return original text
    if (strpos($text, '*!') === false || strpos($text, '!*') === false) {
        return esc_html($text);
    }

    $result = '';
    $current_pos = 0;
    $text_length = strlen($text);

    while ($current_pos < $text_length) {
        // Find next occurrence of *!
        $start_pos = strpos($text, '*!', $current_pos);
        if ($start_pos === false) {
            // No more patterns, add remaining text
            $result .= esc_html(substr($text, $current_pos));
            break;
        }

        // Add text before the pattern
        $result .= esc_html(substr($text, $current_pos, $start_pos - $current_pos));

        // Find the matching !*
        $end_pos = strpos($text, '!*', $start_pos);
        if ($end_pos === false) {
            // No matching end, add remaining text
            $result .= esc_html(substr($text, $current_pos));
            break;
        }

        // Extract and format the text between *! and !*
        $bold_text = esc_html(substr($text, $start_pos + 2, $end_pos - $start_pos - 2));
        $result .= '<b style="color: #F48722; font-weight: bold;">' . $bold_text . '</b>';

        // Move position after the current pattern
        $current_pos = $end_pos + 2;
    }

    return $result;
}

function maspik_get_monthly_stats() {
    // Try to get cached data first
    $cached_data = get_transient('maspik_monthly_stats');
    if (false !== $cached_data) {
        return $cached_data;
    }

    // Check if WordPress database object exists
    if (!isset($GLOBALS['wpdb'])) {
        return array(
            'error' => 'db_error',
            'message' => __('WordPress database object not found.', 'contact-forms-anti-spam')
        );
    }

    // Verify table name is not empty
    $table = maspik_get_logtable();
    if (empty($table)) {
        return array(
            'error' => 'table_error',
            'message' => __('Invalid table name.', 'contact-forms-anti-spam')
        );
    }

    if (!maspik_get_settings("maspik_Store_log") == 'yes') {
        return array(
            'error' => 'no_data',
            'message' => __('Please enable the spam log storage option in the settings to view statistics.', 'contact-forms-anti-spam')
        );
    }

    global $wpdb;
    
    // Check if table exists
    if (!maspik_logtable_exists()) {
        return array(
            'error' => 'no_table',
            'message' => __('The spam log table does not exist.', 'contact-forms-anti-spam')
        );
    }

    try {
        // Get stats for the last 30 days
        $stats = $wpdb->get_results("
            SELECT 
                DATE(spam_date) as date,
                COUNT(*) as count,
                spam_type,
                spam_tag,
                spam_value
            FROM $table
            WHERE spam_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY DATE(spam_date), spam_type, spam_tag, spam_value
            ORDER BY date DESC
            LIMIT 2000"
        );

        // Check for database errors
        if ($wpdb->last_error) {
            return array(
                'error' => 'db_error',
                'message' => __('Database error occurred while fetching statistics.', 'contact-forms-anti-spam')
            );
        }

        // Get top 10 spam reasons
        $top_reasons = $wpdb->get_results("
            SELECT 
                spam_value,
                COUNT(*) as count
            FROM $table
            WHERE spam_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                AND spam_value IS NOT NULL
                AND spam_value != ''
            GROUP BY spam_value
            ORDER BY count DESC
            LIMIT 10"
        );

        // If no data found
        if (empty($stats)) {
            return array(
                'error' => 'no_data',
                'message' => __('No spam data available for the last 30 days.', 'contact-forms-anti-spam')
            );
        }

        $data = array(
            'stats' => $stats,
            'top_reasons' => $top_reasons
        );

        // Cache the results for 1 hour
        set_transient('maspik_monthly_stats', $data, HOUR_IN_SECONDS);
        return $data;

    } catch (Exception $e) {
        return array(
            'error' => 'exception',
            'message' => __('An error occurred while processing statistics.', 'contact-forms-anti-spam')
        );
    }
}

function maspik_render_dashboard_widget() {
    $data = maspik_get_monthly_stats();
    
    if (isset($data['error']) || empty($data['stats'])) {
        ?>
        <div class="maspik-dashboard-widget">
            <div class="maspik-notice">
                <p><?php esc_html_e('Statistics are currently unavailable. You can still view the spam log for detailed information.', 'contact-forms-anti-spam'); ?></p>
            </div>
            <div class="maspik-footer">
                <a href="<?php echo esc_url(admin_url('admin.php?page=maspik-log.php')); ?>" class="button button-primary">
                    <?php esc_html_e('View Spam Log', 'contact-forms-anti-spam'); ?>
                </a>
            </div>
        </div>
        <?php
        return;
    }

    $stats = $data['stats'];
    $top_reasons = $data['top_reasons'];
    
    // Process stats for display
    $daily_totals = array();
    $type_totals = array();
    $total_blocked = 0;
    
    foreach ($stats as $stat) {
        $date = isset($stat->date) ? $stat->date : date('Y-m-d');
        $count = isset($stat->count) ? (int)$stat->count : 0;
        $type = isset($stat->spam_type) ? $stat->spam_type : __('Unknown', 'contact-forms-anti-spam');
        
        // Daily totals
        if (!isset($daily_totals[$date])) {
            $daily_totals[$date] = 0;
        }
        $daily_totals[$date] += $count;
        
        // Type totals
        if (!isset($type_totals[$type])) {
            $type_totals[$type] = 0;
        }
        $type_totals[$type] += $count;
        
        // Overall total
        $total_blocked += $count;
    }
    
    // Calculate daily average over 30 days
    $daily_average = $total_blocked / 30;
    
    // Sort type totals by count
    arsort($type_totals);
    
    // Fill in missing dates with zeros for the last 30 days
    $all_dates = array();
    $end_date = new DateTime();
    $start_date = clone $end_date;
    $start_date->modify('-29 days');

    while ($start_date <= $end_date) {
        $date_str = $start_date->format('Y-m-d');
        $display_date = $start_date->format('d/m');
        $all_dates[$date_str] = [
            'value' => isset($daily_totals[$date_str]) ? $daily_totals[$date_str] : 0,
            'display' => $display_date
        ];
        $start_date->modify('+1 day');
    }

    // Get max value for Y axis and calculate nice step size
    $max_value = max(array_column($all_dates, 'value'));
    $nice_max = max(1, ceil($max_value)); // Prevent division by zero, minimum 1
    
    // If there's no data, skip the calculation
    if ($nice_max <= 1) {
        $step_size = 1;
    } else {
        $step_count = 5; // We want approximately 5 steps
        $rough_step = $nice_max / $step_count;
        $magnitude = pow(10, floor(log10($rough_step)));
        
        // Prevent division by zero
        if ($magnitude > 0) {
            $step_size = ceil($rough_step / $magnitude) * $magnitude;
            if ($step_size * $step_count < $nice_max) {
                $sub_magnitude = $magnitude / 2;
                if ($sub_magnitude > 0) {
                    $step_size = ceil($rough_step / $sub_magnitude) * $sub_magnitude;
                }
            }
        } else {
            $step_size = max(1, ceil($rough_step));
        }
    }

    // Select evenly spaced dates for X axis
    $dates_array = array_keys($all_dates);
    $date_count = count($dates_array);
    $display_count = 8; // Number of dates to display
    
    // Prevent division by zero
    $step = ($date_count > 1) ? max(1, floor($date_count / max(1, $display_count - 1))) : 1;
    $display_dates = array();

    for ($i = 0; $i < $date_count; $i += $step) {
        $display_dates[$dates_array[$i]] = true;
    }
    // Ensure the last date is included
    if ($date_count > 0) {
        $display_dates[$dates_array[$date_count - 1]] = true;
    }

    ?>
    <div class="maspik-dashboard-widget">
        <div class="maspik-stats-overview">
            <div class="maspik-stat-box">
                <h3><?php esc_html_e('Total Blocked (30 Days)', 'contact-forms-anti-spam'); ?></h3>
                <div class="stat-number"><?php echo number_format($total_blocked); ?></div>
            </div>
            
            <div class="maspik-stat-box">
                <h3><?php esc_html_e('Daily Average', 'contact-forms-anti-spam'); ?></h3>
                <div class="stat-number">
                    <?php echo number_format($daily_average, 1); ?>
                </div>
            </div>
        </div>

        <?php if (!empty($top_reasons)): ?>
        <!-- Top 10 Spam Reasons -->
        <div class="maspik-chart-container">
            <h3><?php esc_html_e('Top 10 Spam Reasons', 'contact-forms-anti-spam'); ?></h3>
            <div class="maspik-top-reasons">
                <table class="maspik-reasons-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Reason', 'contact-forms-anti-spam'); ?></th>
                            <th><?php esc_html_e('Count', 'contact-forms-anti-spam'); ?></th>
                            <th><?php esc_html_e('Percentage', 'contact-forms-anti-spam'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($top_reasons as $reason): ?>
                            <?php 
                            $reason_value = !empty($reason->spam_value) ? $reason->spam_value : __('Unknown', 'contact-forms-anti-spam');
                            $reason_count = (int)$reason->count;
                            ?>
                            <tr>
                                <td class="reason-value"><?php echo maspik_format_reason_value($reason_value); ?></td>
                                <td><?php echo number_format($reason_count); ?></td>
                                <td><?php
    if ($total_blocked > 0) {
        echo number_format(($reason_count / $total_blocked) * 100, 1);
    } else {
        echo '0.0';
    }
?>%</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <?php if (count($daily_totals) > 1): ?>
        <div class="maspik-chart-container">
            <canvas id="maspikDailyChart"></canvas>
        </div>
        <?php endif; ?>

        <?php if (count($type_totals) > 1): ?>
        <div class="maspik-type-breakdown">
            <h3><?php esc_html_e('Spam Types Breakdown', 'contact-forms-anti-spam'); ?></h3>
            <div class="type-chart-container">
                <canvas id="maspikTypeChart"></canvas>
            </div>
        </div>
        <?php endif; ?>

        <div class="maspik-footer">
            <a target="_blank" href="<?php echo esc_url(admin_url('admin.php?page=maspik-log.php')); ?>" class="button button-primary">
                <?php esc_html_e('View Full Spam Log', 'contact-forms-anti-spam'); ?>
            </a>
        </div>
    </div>

    <style>
        .maspik-dashboard-widget {
            padding: 15px;
        }
        .maspik-stats-overview {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
        }
        .maspik-stat-box {
            background: #fff;
            padding: 15px;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            flex: 1;
            margin: 0 10px;
            text-align: center;
        }
        .stat-number {
            font-size: 24px;
            font-weight: bold;
            color: #F48722;
        }
        .maspik-chart-container {
            background: #fff;
            padding: 15px;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .type-chart-container {
            height: 300px;
        }
        .maspik-reasons-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        .maspik-reasons-table th,
        .maspik-reasons-table td {
            padding: 8px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        .maspik-reasons-table th {
            background-color: #f8f8f8;
            font-weight: 600;
        }
        .maspik-reasons-table tr:hover {
            background-color: #f5f5f5;
        }
        .maspik-footer {
            margin-top: 20px;
            padding-top: 10px;
            border-top: 1px solid #eee;
            text-align: right;
        }
    </style>

    <?php if (count($daily_totals) > 1 || count($type_totals) > 1): ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        (function() {
            try {
                document.addEventListener('DOMContentLoaded', function() {
                    if (!window.Chart) {
                        console.error('Chart.js not loaded');
                        return;
                    }
                    // Daily Chart
                    const dailyData = <?php echo maspik_safe_json_encode($all_dates); ?>;
                    new Chart(document.getElementById('maspikDailyChart'), {
                        type: 'line',
                        data: {
                            labels: Object.keys(dailyData).map(date => dailyData[date].display),
                            datasets: [{
                                label: '<?php echo esc_js(__('Blocked Spam', 'contact-forms-anti-spam')); ?>',
                                data: Object.keys(dailyData).map(date => dailyData[date].value),
                                borderColor: '#F48722',
                                backgroundColor: '#F48722',
                                tension: 0.1,
                                fill: false,
                                pointRadius: 3,
                                pointHoverRadius: 5
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            scales: {
                                x: {
                                    ticks: {
                                        callback: function(val, index) {
                                            const allLabels = Object.keys(dailyData).map(date => dailyData[date].display);
                                            const label = allLabels[val];
                                            return <?php echo json_encode($display_dates); ?>[Object.keys(dailyData)[val]] ? label : '';
                                        },
                                        maxRotation: 0,
                                        minRotation: 0,
                                        font: {
                                            size: 11
                                        },
                                        padding: 5
                                    },
                                    grid: {
                                        display: false
                                    }
                                },
                                y: {
                                    beginAtZero: true,
                                    suggestedMax: <?php echo $nice_max; ?>,
                                    ticks: {
                                        stepSize: <?php echo $step_size; ?>,
                                        precision: 0,
                                        padding: 10
                                    },
                                    grid: {
                                        color: '#f0f0f0',
                                        drawBorder: false
                                    }
                                }
                            },
                            plugins: {
                                tooltip: {
                                    callbacks: {
                                        title: function(tooltipItems) {
                                            const dateKey = Object.keys(dailyData)[tooltipItems[0].dataIndex];
                                            return new Date(dateKey).toLocaleDateString();
                                        }
                                    }
                                },
                                legend: {
                                    display: true,
                                    position: 'top',
                                    align: 'end',
                                    labels: {
                                        boxWidth: 12,
                                        usePointStyle: true,
                                        pointStyle: 'circle'
                                    }
                                }
                            }
                        }
                    });

                    <?php if (count($type_totals) > 1): ?>
                    // Type Breakdown Chart
                    const typeData = <?php echo json_encode($type_totals); ?>;
                    new Chart(document.getElementById('maspikTypeChart'), {
                        type: 'doughnut',
                        data: {
                            labels: Object.keys(typeData),
                            datasets: [{
                                data: Object.values(typeData),
                                backgroundColor: [
                                    '#F48722',
                                    '#e14d43',
                                    '#46b450',
                                    '#ffb900',
                                    '#826eb4'
                                ]
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false
                        }
                    });
                    <?php endif; ?>
                });
            } catch (e) {
                // Log error but don't break the page
                console.error('Maspik chart error:', e);
                // Hide chart containers on error
                document.querySelectorAll('.maspik-chart-container').forEach(function(container) {
                    container.style.display = 'none';
                });
            }
        })();
    </script>
    <?php endif; ?>
    <?php
}

function maspik_add_dashboard_statistics_widget() {
    wp_add_dashboard_widget(
        'maspik_statistics_widget',
        __('Maspik Spam Blocked Statistics', 'contact-forms-anti-spam'),
        'maspik_render_dashboard_widget'
    );
}
add_action('wp_dashboard_setup', 'maspik_add_dashboard_statistics_widget');

// Safe JSON encoding
function maspik_safe_json_encode($data) {
    return wp_json_encode($data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
}