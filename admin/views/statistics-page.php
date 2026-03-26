<?php if (!defined('WPINC')) die; ?>

<div class="wrap">
    <div class="maspik-setting-header">
        <div class="notice-pointer">
            <h2></h2>
        </div>
        <?php 
        if ( !cfes_is_supporting() ) {
            echo "<div class='upsell-btn " . esc_attr(maspik_add_pro_class()) . "'>";
            maspik_get_pro();
            maspik_activate_license();
            echo "</div>";
        }
        ?>
        <div class="maspik-setting-header-wrap">
            <h1 class="maspik-title">MASPIK.</h1>
            <?php if(cfes_is_supporting()): ?>
                <h3 class="maspik-protag <?php echo esc_attr(maspik_add_pro_class("country_location")); ?>">Pro</h3>
            <?php endif; ?>
        </div>          
    </div>


    <!-- Spam Blocked Summary -->
    <div class="maspik-spam-overview">
        <div class="maspik-overview-card">
            <h3><?php esc_html_e('Spam Blocked Summary', 'contact-forms-anti-spam'); ?></h3>
            <div class="overview-content">
                <p>
                    <span class="overview-number"><?php echo number_format(maspik_spam_count()); ?></span>
                    <?php esc_html_e('spam entries currently stored in the Spam Log', 'contact-forms-anti-spam'); ?>
                    <?php if (get_option("spamcounter")): ?>
                        <br><span class="overview-number"><?php echo number_format(get_option("spamcounter")); ?></span>
                        <?php esc_html_e('spam attempts blocked since installing', 'contact-forms-anti-spam'); ?>
                    <?php endif; ?>
                </p>
                <div class="overview-actions">
                    <a target="_blank" href="<?php echo esc_url(admin_url('admin.php?page=maspik-log.php')); ?>" class="button button-secondary">
                        <span class="dashicons dashicons-list-view"></span>
                        <?php esc_html_e('View Spam Log', 'contact-forms-anti-spam'); ?>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Debug Info -->
    <?php if (WP_DEBUG): ?>
    <div class="notice notice-info">
        <p>Debug Info:</p>
        <pre><?php 
            echo "Timeline Data Count: " . count($stats['timeline_data']) . "\n";
            echo "Countries Data Count: " . count($stats['countries_data']) . "\n";
            echo "Sources Data Count: " . count($stats['sources_data']) . "\n";
        ?></pre>
    </div>
    <?php endif; ?>

    <!-- Time Range Selector -->
    <div class="nav-tab-wrapper">
        <?php
        $ranges = [
            'day' => __('Last 24 Hours', 'contact-forms-anti-spam'),
            'week' => __('Last 7 Days', 'contact-forms-anti-spam'),
            'month' => __('Last 30 Days', 'contact-forms-anti-spam'),
            'year' => __('Last Year', 'contact-forms-anti-spam'),
            'custom' => __('Custom Range', 'contact-forms-anti-spam')
        ];
        foreach ($ranges as $range => $label) {
            $active = ($time_range === $range) ? 'nav-tab-active' : '';
            $url = add_query_arg('range', $range);
            echo '<a href="' . esc_url($url) . '" class="nav-tab ' . $active . '" data-range="' . esc_attr($range) . '">' . esc_html($label) . '</a>';
        }
        ?>
    </div>

    <!-- Custom Date Range Picker -->
    <div id="custom-date-range" class="custom-date-range" style="<?php echo ($time_range === 'custom') ? 'display:block;' : 'display:none;'; ?>">
        <form method="get" action="">
            <input type="hidden" name="page" value="maspik-statistics">
            <input type="hidden" name="range" value="custom">
            <div class="date-inputs">
                <div class="date-input-group">
                    <label for="start_date"><?php esc_html_e('Start Date:', 'contact-forms-anti-spam'); ?></label>
                    <input type="date" id="start_date" name="start_date" value="<?php echo isset($_GET['start_date']) ? esc_attr($_GET['start_date']) : date('Y-m-d', strtotime('-30 days')); ?>">
                </div>
                <div class="date-input-group">
                    <label for="end_date"><?php esc_html_e('End Date:', 'contact-forms-anti-spam'); ?></label>
                    <input type="date" id="end_date" name="end_date" value="<?php echo isset($_GET['end_date']) ? esc_attr($_GET['end_date']) : date('Y-m-d'); ?>">
                </div>
                <button type="submit" class="button button-primary"><?php esc_html_e('Apply', 'contact-forms-anti-spam'); ?></button>
            </div>
        </form>
    </div>

    <!-- Statistics Overview with Trend Indicators -->
    <div class="stats-overview">
        <div class="stat-card">
            <h3><?php esc_html_e('Total Blocked', 'contact-forms-anti-spam'); ?></h3>
            <div class="stat-number">
                <?php echo number_format($stats['total_blocked']); ?>
                <?php if ($stats['trend_direction'] !== 'none'): ?>
                    <span class="trend-indicator trend-<?php echo $stats['trend_direction']; ?>">
                        <?php echo $stats['trend_direction'] === 'up' ? '↑' : '↓'; ?>
                        <?php echo abs($stats['trend_percentage']); ?>%
                    </span>
                <?php endif; ?>
            </div>
        </div>
        <!-- Additional stat cards can be added here -->
    </div>

    <!-- Charts -->
    <div class="maspik-charts-grid">
        <!-- Timeline Chart -->
        <div class="stats-section">
            <h3><?php esc_html_e('Spam Timeline', 'contact-forms-anti-spam'); ?></h3>
            <?php if (empty($stats['timeline_data'])): ?>
                <div class="empty-state-message">
                    <span class="dashicons dashicons-chart-bar empty-state-icon"></span>
                    <h4 class="empty-state-title"><?php esc_html_e('No Data Available', 'contact-forms-anti-spam'); ?></h4>
                    <p class="empty-state-description">
                        <?php esc_html_e('No spam attempts were recorded and stored in the spam log during the selected time period.', 'contact-forms-anti-spam'); ?>
                    </p>
                </div>
            <?php else: ?>
                <div class="chart-container">
                    <canvas id="timelineChart"></canvas>
                </div>
            <?php endif; ?>
        </div>

        <!-- Countries Chart -->
        <div class="stats-section">
            <h3><?php esc_html_e('Top Countries', 'contact-forms-anti-spam'); ?></h3>
            <div class="chart-container">
                <canvas id="countriesChart"></canvas>
            </div>
        </div>
    </div>

    <div class="maspik-charts-grid">
        <!-- Sources Chart -->
        <div class="stats-section">
            <h3><?php esc_html_e('Spam Sources', 'contact-forms-anti-spam'); ?></h3>
            <div class="chart-container">
                <canvas id="sourcesChart"></canvas>
            </div>
        </div>
        
        <!-- World Map Chart -->
        <div class="stats-section">
            <h3><?php esc_html_e('Geographical Distribution', 'contact-forms-anti-spam'); ?></h3>
            <div class="chart-container">
                <div id="worldMapChart"></div>
            </div>
            
            <!-- מקרא המפה -->
            <div class="map-legend">
                <div class="map-legend-item">
                    <span class="map-legend-color map-legend-low"></span>
                    <span><?php esc_html_e('Low', 'contact-forms-anti-spam'); ?></span>
                </div>
                <div class="map-legend-item">
                    <span class="map-legend-color map-legend-medium"></span>
                    <span><?php esc_html_e('Medium', 'contact-forms-anti-spam'); ?></span>
                </div>
                <div class="map-legend-item">
                    <span class="map-legend-color map-legend-high"></span>
                    <span><?php esc_html_e('High', 'contact-forms-anti-spam'); ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Top IP Addresses Table -->
    <div class="stats-section">
        <div class="section-header">
            <h3><?php esc_html_e('Top IP Addresses', 'contact-forms-anti-spam'); ?></h3>
            <div class="section-actions">
                <button id="block-selected-ips" class="button button-secondary"><?php esc_html_e('Block Selected', 'contact-forms-anti-spam'); ?></button>
            </div>
        </div>
        <table class="wp-list-table widefat fixed striped maspik-table">
            <thead>
                <tr>
                    <th class="check-column"><input type="checkbox" id="select-all-ips"></th>
                    <th><?php esc_html_e('IP Address', 'contact-forms-anti-spam'); ?></th>
                    <th><?php esc_html_e('Country', 'contact-forms-anti-spam'); ?></th>
                    <th><?php esc_html_e('Spam Count', 'contact-forms-anti-spam'); ?></th>
                    <th><?php esc_html_e('Last Attempt', 'contact-forms-anti-spam'); ?></th>
                    <th><?php esc_html_e('Actions', 'contact-forms-anti-spam'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($stats['top_ips'] as $ip): ?>
                <tr>
                    <td class="check-column"><input type="checkbox" name="selected_ips[]" value="<?php echo esc_attr($ip->ip); ?>"></td>
                    <td><?php echo esc_html($ip->ip); ?></td>
                    <td><?php echo esc_html($ip->country); ?></td>
                    <td><?php echo number_format($ip->count); ?></td>
                    <td><?php echo esc_html($ip->last_attempt); ?></td>
                    <td>
                        <button class="button button-small block-ip" data-ip="<?php echo esc_attr($ip->ip); ?>">
                            <?php esc_html_e('Block', 'contact-forms-anti-spam'); ?>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Email Domains Analysis -->
    <div class="stats-section">
        <div class="section-header">
            <h3><?php esc_html_e('Most Common Spam Email Domains', 'contact-forms-anti-spam'); ?></h3>
            <div class="section-actions">
                <button id="block-selected-domains" class="button button-secondary"><?php esc_html_e('Block Selected', 'contact-forms-anti-spam'); ?></button>
            </div>
        </div>
        <table class="wp-list-table widefat fixed striped maspik-table">
            <thead>
                <tr>
                    <th class="check-column"><input type="checkbox" id="select-all-domains"></th>
                    <th><?php esc_html_e('Domain', 'contact-forms-anti-spam'); ?></th>
                    <th><?php esc_html_e('Count', 'contact-forms-anti-spam'); ?></th>
                    <th><?php esc_html_e('Percentage', 'contact-forms-anti-spam'); ?></th>
                    <th><?php esc_html_e('Actions', 'contact-forms-anti-spam'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($stats['email_domains'] as $domain): ?>
                <tr>
                    <td class="check-column"><input type="checkbox" name="selected_domains[]" value="<?php echo esc_attr($domain->domain); ?>"></td>
                    <td><?php echo esc_html($domain->domain); ?></td>
                    <td><?php echo number_format($domain->count); ?></td>
                    <td><?php echo round(($domain->count / $stats['total_blocked']) * 100, 1); ?>%</td>
                    <td>
                        <button class="button button-small block-domain" data-domain="<?php echo esc_attr($domain->domain); ?>">
                            <?php esc_html_e('Block', 'contact-forms-anti-spam'); ?>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Top Countries Table -->
    <div class="stats-section">
        <div class="section-header">
            <h3>
                <?php esc_html_e('Top Countries', 'contact-forms-anti-spam'); ?>
                <?php 
                // Show current setting mode
                $AllowedOrBlockCountries = maspik_get_settings('AllowedOrBlockCountries');
                if (cfes_is_supporting("country_location")) {
                    if ($AllowedOrBlockCountries == 'block') {
                        echo '<span class="setting-mode block-mode">' . __('Blocking Mode', 'contact-forms-anti-spam') . '</span>';
                    } else {
                        echo '<span class="setting-mode allow-mode">' . __('Allow Mode', 'contact-forms-anti-spam') . '</span>';
                    }
                }
                ?>
            </h3>
            <div class="section-actions">
                <?php if (cfes_is_supporting("country_location")): ?>
                    <?php if (maspik_get_settings('AllowedOrBlockCountries') == 'block'): ?>
                        <button id="block-selected-countries" class="button button-secondary"><?php esc_html_e('Block Selected', 'contact-forms-anti-spam'); ?></button>
                    <?php else: ?>
                        <a href="<?php echo admin_url('admin.php?page=maspik&tab=geolocation'); ?>" class="button button-secondary">
                            <?php esc_html_e('Configure in Settings', 'contact-forms-anti-spam'); ?>
                        </a>
                    <?php endif; ?>
                <?php else: ?>
                    <button id="block-selected-countries" class="button button-secondary" disabled><?php esc_html_e('Block Selected', 'contact-forms-anti-spam'); ?> <span class="dashicons dashicons-lock" style="font-size: 14px; vertical-align: middle; margin-left: 2px;"></span></button>
                <?php endif; ?>
            </div>
        </div>
        <table class="wp-list-table widefat fixed striped maspik-table">
            <thead>
                <tr>
                    <th class="check-column">
                        <?php if (cfes_is_supporting("country_location") && maspik_get_settings('AllowedOrBlockCountries') == 'block'): ?>
                            <input type="checkbox" id="select-all-countries">
                        <?php else: ?>
                            <input type="checkbox" id="select-all-countries" disabled>
                        <?php endif; ?>
                    </th>
                    <th><?php esc_html_e('Country', 'contact-forms-anti-spam'); ?></th>
                    <th><?php esc_html_e('Spam Count', 'contact-forms-anti-spam'); ?></th>
                    <th><?php esc_html_e('Percentage', 'contact-forms-anti-spam'); ?></th>
                    <th><?php esc_html_e('Last Attempt', 'contact-forms-anti-spam'); ?></th>
                    <th><?php esc_html_e('Actions', 'contact-forms-anti-spam'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php 
                // Create a mapping of country codes to country names
                global $MASPIK_COUNTRIES_LIST;
                
                foreach ($stats['top_countries'] as $country): 
                    // Get country code using our function - with error checking
                    $country_code = '';
                    if (method_exists($this, 'get_country_code')) {
                        $country_code = $this->get_country_code($country->country);
                    }
                    // If we didn't get a code, use the country name
                    if (empty($country_code)) {
                        $country_code = $country->country;
                    }
                ?>
                <tr>
                    <td class="check-column">
                        <?php if (cfes_is_supporting("country_location") && maspik_get_settings('AllowedOrBlockCountries') == 'block'): ?>
                            <input type="checkbox" name="selected_countries[]" value="<?php echo esc_attr($country_code); ?>">
                        <?php else: ?>
                            <input type="checkbox" name="selected_countries[]" value="<?php echo esc_attr($country_code); ?>" disabled>
                        <?php endif; ?>
                    </td>
                    <td><?php echo esc_html($country->country); ?></td>
                    <td><?php echo number_format($country->count); ?></td>
                    <td><?php echo number_format($country->percentage, 1); ?>%</td>
                    <td><?php echo esc_html($country->last_attempt); ?></td>
                    <td>
                        <?php if (cfes_is_supporting("country_location")): ?>
                            <?php if (1 || maspik_get_settings('AllowedOrBlockCountries') == 'block'): ?>
                                <button class="button button-small block-country" data-country="<?php echo esc_attr($country_code); ?>">
                                    <?php esc_html_e('Block', 'contact-forms-anti-spam'); ?>
                                </button>
                            <?php else: ?>
                                <a href="<?php echo admin_url('admin.php?page=maspik&tab=geolocation'); ?>" class="button button-small">
                                    <?php esc_html_e('Settings', 'contact-forms-anti-spam'); ?>
                                </a>
                            <?php endif; ?>
                        <?php else: ?>
                            <button class="button button-small block-country" data-country="<?php echo esc_attr($country_code); ?>">
                                <?php esc_html_e('Block', 'contact-forms-anti-spam'); ?> <span class="dashicons dashicons-lock" style="font-size: 14px; vertical-align: text-top;"></span>
                            </button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Top Pages Table -->
    <div class="stats-section">
        <div class="section-header">
            <h3><?php esc_html_e('Top Pages Attacked', 'contact-forms-anti-spam'); ?></h3>
        </div>
        <table class="wp-list-table widefat fixed striped maspik-table">
            <thead>
                <tr>
                    <th><?php esc_html_e('Page URL', 'contact-forms-anti-spam'); ?></th>
                    <th><?php esc_html_e('Title', 'contact-forms-anti-spam'); ?></th>
                    <th><?php esc_html_e('Spam Count', 'contact-forms-anti-spam'); ?></th>
                    <th><?php esc_html_e('Percentage', 'contact-forms-anti-spam'); ?></th>
                    <th><?php esc_html_e('Actions', 'contact-forms-anti-spam'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($stats['top_pages'] as $page): ?>
                <tr>
                    <td>
                        <a href="<?php echo esc_url($page->page_url); ?>" target="_blank">
                            <?php echo esc_html(substr($page->page_url, 0, 50) . (strlen($page->page_url) > 50 ? '...' : '')); ?>
                        </a>
                    </td>
                    <td><?php echo esc_html($page->title); ?></td>
                    <td><?php echo number_format($page->count); ?></td>
                    <td><?php echo number_format($page->percentage, 1); ?>%</td>
                    <td>
                        <a href="<?php echo esc_url($page->page_url); ?>" target="_blank" class="button button-small">
                            <?php esc_html_e('View Page', 'contact-forms-anti-spam'); ?>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Top Spam Types Table -->
    <div class="stats-section">
        <div class="section-header">
            <h3><?php esc_html_e('Top Spam Types', 'contact-forms-anti-spam'); ?></h3>
        </div>
        <table class="wp-list-table widefat fixed striped maspik-table">
            <thead>
                <tr>
                    <th><?php esc_html_e('Spam Type', 'contact-forms-anti-spam'); ?></th>
                    <th><?php esc_html_e('Count', 'contact-forms-anti-spam'); ?></th>
                    <th><?php esc_html_e('Percentage', 'contact-forms-anti-spam'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($stats['top_types'] as $type): ?>
                <tr>
                    <td><?php echo esc_html($type->type); ?></td>
                    <td><?php echo number_format($type->count); ?></td>
                    <td><?php echo number_format($type->percentage, 1); ?>%</td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Top Spam Reasons Table -->
    <div class="stats-section">
        <div class="section-header">
            <h3><?php esc_html_e('Top Data & Reasons', 'contact-forms-anti-spam'); ?></h3>
        </div>
        <table class="wp-list-table widefat fixed striped maspik-table">
            <thead>
                <tr>
                    <th><?php esc_html_e('Spam Reason', 'contact-forms-anti-spam'); ?></th>
                    <th><?php esc_html_e('Count', 'contact-forms-anti-spam'); ?></th>
                    <th><?php esc_html_e('Percentage', 'contact-forms-anti-spam'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($stats['top_reasons'] as $reason): ?>
                <tr>
                    <td><?php echo maspik_format_reason_value($reason->reason); ?></td>
                    <td><?php echo number_format($reason->count); ?></td>
                    <td><?php echo number_format($reason->percentage, 1); ?>%</td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- User Agent Analysis -->
    <div class="stats-section">
        <h3><?php esc_html_e('User-Agent Analysis', 'contact-forms-anti-spam'); ?></h3>
        <table class="wp-list-table widefat fixed striped maspik-table">
            <thead>
                <tr>
                    <th><?php esc_html_e('User Agent', 'contact-forms-anti-spam'); ?></th>
                    <th><?php esc_html_e('Count', 'contact-forms-anti-spam'); ?></th>
                    <th><?php esc_html_e('Percentage', 'contact-forms-anti-spam'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($stats['user_agents'] as $agent): ?>
                <tr>
                    <td><?php echo esc_html($agent->agent); ?></td>
                    <td><?php echo number_format($agent->count); ?></td>
                    <td><?php echo round(($agent->count / $stats['total_blocked']) * 100, 1); ?>%</td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php echo get_maspik_footer(); ?>
</div> 