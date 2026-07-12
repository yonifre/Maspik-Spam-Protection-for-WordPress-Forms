<?php
// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

class Maspik_Statistics {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        add_action('admin_menu', array($this, 'add_menu_page'));
        
        // Register AJAX actions
        add_action('wp_ajax_maspik_block_ip', array($this, 'ajax_block_ip'));
        add_action('wp_ajax_maspik_block_multiple_ips', array($this, 'ajax_block_multiple_ips'));
        add_action('wp_ajax_maspik_block_country', array($this, 'ajax_block_country'));
        add_action('wp_ajax_maspik_block_domain', array($this, 'ajax_block_domain'));
        add_action('wp_ajax_maspik_block_multiple_domains', array($this, 'ajax_block_multiple_domains'));
        add_action('wp_ajax_maspik_block_multiple_countries', array($this, 'ajax_block_multiple_countries'));
    }

    public function add_menu_page() {
        add_submenu_page(
            'maspik',
            __('Spam Statistics', 'contact-forms-anti-spam'),
            __('Spam Statistics', 'contact-forms-anti-spam'),
            'edit_pages',
            'maspik-statistics',
            array($this, 'render_page'),
            25
        );
    }

    public function render_page() {
        if ( ! maspik_user_can_view_spam_log() ) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'contact-forms-anti-spam'));
        }

        // Get current stats for JavaScript
        $time_range = isset( $_GET['range'] ) ? sanitize_text_field( wp_unslash( $_GET['range'] ) ) : 'month';
        $start_date = isset( $_GET['start_date'] ) ? sanitize_text_field( wp_unslash( $_GET['start_date'] ) ) : '';
        $end_date   = isset( $_GET['end_date'] ) ? sanitize_text_field( wp_unslash( $_GET['end_date'] ) ) : '';

        $time_range = $this->sanitize_statistics_range( $time_range );
        if ( 'custom' === $time_range ) {
            $start_date = $this->sanitize_statistics_date( $start_date );
            $end_date   = $this->sanitize_statistics_date( $end_date );
            if ( ! $start_date || ! $end_date ) {
                $time_range = 'month';
                $start_date = '';
                $end_date   = '';
            }
        }

        $stats = $this->get_statistics_data( $time_range, $start_date, $end_date );

        if ( ! empty( $stats['error'] ) ) {
            echo '<div class="notice notice-error"><p>';
            echo esc_html( $stats['message'] ?? __( 'An error occurred while fetching statistics.', 'contact-forms-anti-spam' ) );
            echo '</p></div>';
            return;
        }

        $time_range = isset( $stats['time_range'] ) ? $stats['time_range'] : $time_range;

        // Prepare data for JavaScript and inline script
        $timeline_data = array_map(function($item) {
            return array(
                'period' => $item->period,
                'count' => (int)$item->count
            );
        }, $stats['timeline_data']);

        // Enqueue jQuery
        wp_enqueue_script('jquery');

        // Enqueue Chart.js
        wp_enqueue_script(
            'maspik-chartjs',
            'https://cdn.jsdelivr.net/npm/chart.js@3.7.0/dist/chart.min.js',
            array('jquery'),
            '3.7.0',
            true
        );

        // Enqueue jVectorMap
        wp_enqueue_script(
            'maspik-jvectormap-core',
            'https://cdn.jsdelivr.net/npm/jvectormap@2.0.4/jquery-jvectormap.min.js',
            array('jquery'),
            '2.0.4',
            true
        );
        
        // Enqueue world map for jVectorMap
        wp_enqueue_script(
            'maspik-jvectormap-world',
            'https://cdn.jsdelivr.net/npm/jvectormap@2.0.4/jquery-jvectormap-world-mill.js',
            array('jquery', 'maspik-jvectormap-core'),
            '2.0.4',
            true
        );

        // Enqueue Google Charts API
        wp_enqueue_script(
            'google-charts',
            'https://www.gstatic.com/charts/loader.js',
            array('jquery'),
            null,
            true
        );

        // Enqueue styles
        wp_enqueue_style(
            'maspik-statistics-style',
            plugin_dir_url(__FILE__) . 'css/maspik-statistics.css',
            array(),
            MASPIK_VERSION
        );

        // Enqueue our custom script
        wp_enqueue_script(
            'maspik-statistics',
            plugin_dir_url(__FILE__) . 'js/maspik-statistics.js',
            array('jquery', 'maspik-chartjs', 'maspik-jvectormap-core', 'maspik-jvectormap-world'),
            MASPIK_VERSION,
            true
        );

        // Add map data to maspikStats
        $map_data = array();
        $country_codes = $this->get_country_codes();

        foreach ($stats['countries_data'] as $data) {
            $code = isset($country_codes[$data->country]) ? strtolower($country_codes[$data->country]) : null;
            if ($code) {
                $map_data[$code] = (int)$data->count;
            }
        }

        // Create nonce for AJAX requests
        $ajax_nonce = wp_create_nonce('maspik_statistics_nonce');

        // Prepare countries data for JavaScript
        global $MASPIK_COUNTRIES_LIST;
        $countries_for_js = array();
        
        // If it doesn't exist or isn't an array, initialize it as an empty array
        if (!isset($MASPIK_COUNTRIES_LIST) || !is_array($MASPIK_COUNTRIES_LIST)) {
            $MASPIK_COUNTRIES_LIST = array();
        }
        
        if (isset($MASPIK_COUNTRIES_LIST) && is_array($MASPIK_COUNTRIES_LIST)) {
            foreach ($MASPIK_COUNTRIES_LIST as $country) {
                if (is_array($country) && isset($country['name']) && isset($country['code'])) {
                    $countries_for_js[$country['name']] = $country['code'];
                }
            }
        }
        
        if (empty($countries_for_js)) {
            $countries_for_js = $this->get_country_codes();
        }

        $json_flags = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE;
        $maspik_stats_payload = array(
            'timelineData'  => $timeline_data,
            'countriesData' => $stats['countries_data'],
            'sourcesData'   => $stats['sources_data'],
            'mapData'       => $map_data,
            'countryCodes'  => $countries_for_js,
            'nonce'         => $ajax_nonce,
            'translations'  => array(
                'blockedSpam'  => __( 'Blocked Spam', 'contact-forms-anti-spam' ),
                'spamAttempts' => __( 'Spam Attempts', 'contact-forms-anti-spam' ),
            ),
        );
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_json_encode with JSON_HEX_* prevents script breakout.
        echo '<script>var maspikStats = ' . wp_json_encode( $maspik_stats_payload, $json_flags ) . ';</script>';

        // Render the page
        include(plugin_dir_path(__FILE__) . 'views/statistics-page.php');
    }

    /** @var string[] */
    private static $statistics_allowed_ranges = array( 'day', 'week', 'month', 'year', 'custom' );

    /** @var array<string, string> */
    private static $statistics_allowed_intervals = array(
        'day'   => '1 DAY',
        'week'  => '7 DAY',
        'month' => '30 DAY',
        'year'  => '1 YEAR',
    );

    private function sanitize_statistics_range( $range ) {
        $range = sanitize_key( $range );
        return in_array( $range, self::$statistics_allowed_ranges, true ) ? $range : 'month';
    }

    private function sanitize_statistics_date( $date ) {
        $date = sanitize_text_field( (string) $date );
        return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ? $date : '';
    }

    /**
     * @return array{type: string, start?: string, end?: string, interval?: string, range_key?: string, date_format?: string}
     */
    private function resolve_period_filter( $time_range, $start_date, $end_date ) {
        $time_range = $this->sanitize_statistics_range( $time_range );

        if ( 'custom' === $time_range ) {
            $start = $this->sanitize_statistics_date( $start_date );
            $end   = $this->sanitize_statistics_date( $end_date );
            if ( $start && $end && strtotime( $start ) <= strtotime( $end ) ) {
                return array(
                    'type'  => 'between',
                    'start' => $start . ' 00:00:00',
                    'end'   => $end . ' 23:59:59',
                );
            }
            $time_range = 'month';
        }

        $range_key = $time_range;
        $interval  = self::$statistics_allowed_intervals[ $range_key ] ?? self::$statistics_allowed_intervals['month'];
        $formats   = array(
            'day'   => '%H:00',
            'week'  => '%Y-%m-%d',
            'month' => '%Y-%m-%d',
            'year'  => '%Y-%m',
        );

        return array(
            'type'        => 'interval',
            'range_key'   => $range_key,
            'interval'    => $interval,
            'date_format' => $formats[ $range_key ] ?? $formats['month'],
        );
    }

    /**
     * @param array $filter Current period from resolve_period_filter().
     * @return array
     */
    private function resolve_previous_period_filter( array $filter ) {
        if ( 'between' === $filter['type'] ) {
            $start_date = substr( $filter['start'], 0, 10 );
            $end_date   = substr( $filter['end'], 0, 10 );
            $date_diff  = max( 1, (int) round( ( strtotime( $end_date ) - strtotime( $start_date ) ) / 86400 ) );
            $prev_end   = gmdate( 'Y-m-d', strtotime( $start_date . ' -1 day' ) );
            $prev_start = gmdate( 'Y-m-d', strtotime( $prev_end . " -{$date_diff} days" ) );

            return array(
                'type'  => 'between',
                'start' => $prev_start . ' 00:00:00',
                'end'   => $prev_end . ' 23:59:59',
            );
        }

        $literal_by_range = array(
            'day'   => 'DATE(spam_date) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)',
            'week'  => 'spam_date BETWEEN DATE_SUB(DATE_SUB(NOW(), INTERVAL 7 DAY), INTERVAL 7 DAY) AND DATE_SUB(NOW(), INTERVAL 7 DAY)',
            'month' => 'spam_date BETWEEN DATE_SUB(DATE_SUB(NOW(), INTERVAL 30 DAY), INTERVAL 30 DAY) AND DATE_SUB(NOW(), INTERVAL 30 DAY)',
            'year'  => 'spam_date BETWEEN DATE_SUB(DATE_SUB(NOW(), INTERVAL 1 YEAR), INTERVAL 1 YEAR) AND DATE_SUB(NOW(), INTERVAL 1 YEAR)',
        );
        $range_key = $filter['range_key'] ?? 'month';

        return array(
            'type'      => 'literal',
            'where_sql' => $literal_by_range[ $range_key ] ?? $literal_by_range['month'],
        );
    }

    private function period_count( array $filter ) {
        global $wpdb;
        $table = maspik_get_logtable();

        if ( 'between' === $filter['type'] ) {
            return (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$table} WHERE spam_date BETWEEN %s AND %s",
                    $filter['start'],
                    $filter['end']
                )
            );
        }

        if ( 'literal' === $filter['type'] ) {
            return (int) $wpdb->get_var(
                "SELECT COUNT(*) FROM {$table} WHERE {$filter['where_sql']}"
            );
        }

        $interval = $filter['interval'];
        return (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$table} WHERE spam_date >= DATE_SUB(NOW(), INTERVAL {$interval})"
        );
    }

    /**
     * @param string $select_body Columns and aggregates (no SELECT keyword).
     * @param string $suffix      GROUP BY / ORDER BY / LIMIT (no leading WHERE).
     */
    private function period_select( array $filter, $select_body, $suffix = '' ) {
        global $wpdb;
        $table  = maspik_get_logtable();
        $suffix = $suffix ? ' ' . $suffix : '';

        if ( 'between' === $filter['type'] ) {
            return $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT {$select_body} FROM {$table} WHERE spam_date BETWEEN %s AND %s{$suffix}",
                    $filter['start'],
                    $filter['end']
                )
            );
        }

        if ( 'literal' === $filter['type'] ) {
            return $wpdb->get_results(
                "SELECT {$select_body} FROM {$table} WHERE {$filter['where_sql']}{$suffix}"
            );
        }

        $interval = $filter['interval'];
        return $wpdb->get_results(
            "SELECT {$select_body} FROM {$table} WHERE spam_date >= DATE_SUB(NOW(), INTERVAL {$interval}){$suffix}"
        );
    }

    private function period_timeline_interval( array $filter, $date_format ) {
        global $wpdb;
        $table    = maspik_get_logtable();
        $interval = $filter['interval'];

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT DATE_FORMAT(spam_date, %s) AS period, COUNT(*) AS count
                 FROM {$table}
                 WHERE spam_date >= DATE_SUB(NOW(), INTERVAL {$interval})
                 GROUP BY period
                 ORDER BY period ASC",
                $date_format
            )
        );
    }

    private function period_timeline_between( array $filter ) {
        global $wpdb;
        $table = maspik_get_logtable();

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT DATE(spam_date) AS period, COUNT(*) AS count
                 FROM {$table}
                 WHERE spam_date BETWEEN %s AND %s
                 GROUP BY DATE(spam_date)
                 ORDER BY period ASC",
                $filter['start'],
                $filter['end']
            )
        );
    }

    private function get_statistics_data($time_range, $start_date = '', $end_date = '') {
        if (!maspik_logtable_exists()) {
            return $this->statistics_error_result(
                __('The spam log table does not exist.', 'contact-forms-anti-spam'),
                $time_range
            );
        }

        $period_filter   = $this->resolve_period_filter( $time_range, $start_date, $end_date );
        $is_custom_range = ( 'between' === $period_filter['type'] );
        $display_range   = $is_custom_range ? 'custom' : ( $period_filter['range_key'] ?? 'month' );

        try {
            $total_blocked = $this->period_count( $period_filter );

            if ( $is_custom_range ) {
                $custom_start = substr( $period_filter['start'], 0, 10 );
                $custom_end   = substr( $period_filter['end'], 0, 10 );
                $timeline_data = $this->period_timeline_between( $period_filter );

                $filled_timeline = array();
                $current_date    = new DateTime( $custom_start );
                $end_date_obj    = new DateTime( $custom_end );

                while ( $current_date <= $end_date_obj ) {
                    $current_date_str = $current_date->format( 'Y-m-d' );
                    $found            = false;

                    foreach ( $timeline_data as $data ) {
                        if ( (string) $data->period === $current_date_str ) {
                            $filled_timeline[] = $data;
                            $found             = true;
                            break;
                        }
                    }

                    if ( ! $found ) {
                        $filled_timeline[] = (object) array(
                            'period' => $current_date_str,
                            'count'  => 0,
                        );
                    }

                    $current_date->modify( '+1 day' );
                }

                $timeline_data = $filled_timeline;
            } else {
                $timeline_data = $this->period_timeline_interval(
                    $period_filter,
                    $period_filter['date_format']
                );
            }

            $total_for_period = $total_blocked;

            $countries_data = $this->period_select(
                $period_filter,
                "COALESCE(spam_country, 'Unknown') AS country, COUNT(*) AS count",
                'GROUP BY country HAVING count > 0 ORDER BY count DESC LIMIT 10'
            );

            $sources_data = $this->period_select(
                $period_filter,
                "COALESCE(SUBSTRING_INDEX(spam_source, '|||', 1), 'Unknown') AS source, COUNT(*) AS count",
                'GROUP BY source HAVING count > 0 ORDER BY count DESC LIMIT 10'
            );

            $top_ips = $this->period_select(
                $period_filter,
                "spam_ip AS ip, COALESCE(spam_country, 'Unknown') AS country, COUNT(*) AS count, MAX(spam_date) AS last_attempt",
                'GROUP BY ip, country ORDER BY count DESC LIMIT 10'
            );

            $email_domains = $this->period_select(
                $period_filter,
                "SUBSTRING_INDEX(spamsrc_val, '@', -1) AS domain, COUNT(*) AS count",
                "AND spam_type = 'email' AND spamsrc_val LIKE '%@%' GROUP BY domain ORDER BY count DESC LIMIT 10"
            );

            $user_agents = $this->period_select(
                $period_filter,
                'SUBSTRING(spam_agent, 1, 50) AS agent, COUNT(*) AS count',
                "AND spam_agent IS NOT NULL AND spam_agent != '' GROUP BY agent ORDER BY count DESC LIMIT 10"
            );

            $previous_filter = $this->resolve_previous_period_filter( $period_filter );
            $previous_total  = $this->period_count( $previous_filter );
            
            $trend_percentage = 0;
            $trend_direction = 'none';
            
            if ($previous_total > 0) {
                $trend_percentage = round((($total_blocked - $previous_total) / $previous_total) * 100, 1);
                $trend_direction = $trend_percentage > 0 ? 'up' : ($trend_percentage < 0 ? 'down' : 'none');
            }

            $top_countries = $this->period_select(
                $period_filter,
                "COALESCE(spam_country, 'Unknown') AS country, COUNT(*) AS count, MAX(spam_date) AS last_attempt",
                'GROUP BY country ORDER BY count DESC LIMIT 10'
            );

            $top_pages = $this->period_select(
                $period_filter,
                "SUBSTRING_INDEX(spam_source, '|||', -1) AS page_url, COUNT(*) AS count",
                "AND spam_source LIKE '%|||%' GROUP BY page_url ORDER BY count DESC LIMIT 10"
            );

            $top_types = $this->period_select(
                $period_filter,
                "COALESCE(spam_type, 'Unknown') AS type, COUNT(*) AS count",
                'GROUP BY type ORDER BY count DESC LIMIT 10'
            );

            $top_reasons = $this->period_select(
                $period_filter,
                "COALESCE(spam_value, 'Unknown') AS reason, COUNT(*) AS count",
                "AND spam_value IS NOT NULL AND spam_value != '' GROUP BY reason ORDER BY count DESC LIMIT 10"
            );
            
            // Add percentages to all new metrics
            foreach ($top_countries as $data) {
                $data->percentage = $total_for_period > 0 ? round(($data->count / $total_for_period) * 100, 1) : 0;
            }
            
            foreach ($top_pages as $data) {
                $data->percentage = $total_for_period > 0 ? round(($data->count / $total_for_period) * 100, 1) : 0;
                
                // Try to get the post title
                $post_id = url_to_postid($data->page_url);
                $title = '';
                
                if ($post_id > 0) {
                    $title = get_the_title($post_id);
                }
                
                // If we have a title, use it, otherwise use the URL
                if (!empty($title)) {
                    $data->title = $title;
                } else {
                    // Format the URL to be more readable
                    $url = $data->page_url;
                    // Remove protocol and domain
                    $url = preg_replace('#^https?://[^/]+#', '', $url);
                    // Remove trailing slash
                    $url = rtrim($url, '/');
                    // If empty, show home page
                    $data->title = !empty($url) ? $url : __('Home Page', 'contact-forms-anti-spam');
                }
                
                // Always store the full URL for linking
                $data->url = $data->page_url;
            }
            
            foreach ($top_types as $data) {
                $data->percentage = $total_for_period > 0 ? round(($data->count / $total_for_period) * 100, 1) : 0;
            }
            
            foreach ($top_reasons as $data) {
                $data->percentage = $total_for_period > 0 ? round(($data->count / $total_for_period) * 100, 1) : 0;
            }

            return array(
                'total_blocked' => (int)$total_blocked,
                'timeline_data' => $timeline_data,
                'countries_data' => $countries_data,
                'sources_data' => $sources_data,
                'top_ips' => $top_ips,
                'email_domains' => $email_domains,
                'user_agents' => $user_agents,
                'top_countries' => $top_countries,
                'top_pages' => $top_pages,
                'top_types' => $top_types,
                'top_reasons' => $top_reasons,
                'time_range' => $display_range,
                'trend_percentage' => $trend_percentage,
                'trend_direction' => $trend_direction,
                'error' => false
            );

        } catch (Exception $e) {
            return $this->statistics_error_result(
                __('An error occurred while fetching statistics.', 'contact-forms-anti-spam'),
                isset( $display_range ) ? $display_range : $time_range
            );
        }
    }

    /**
     * @param string $message     User-facing error text.
     * @param string $time_range  Active range key for the UI.
     * @return array
     */
    private function statistics_error_result( $message, $time_range = 'month' ) {
        return array(
            'error'            => true,
            'message'          => $message,
            'total_blocked'    => 0,
            'timeline_data'    => array(),
            'countries_data'   => array(),
            'sources_data'     => array(),
            'top_ips'          => array(),
            'email_domains'    => array(),
            'user_agents'      => array(),
            'top_countries'    => array(),
            'top_pages'        => array(),
            'top_types'        => array(),
            'top_reasons'      => array(),
            'time_range'       => $this->sanitize_statistics_range( $time_range ),
            'trend_percentage' => 0,
            'trend_direction'  => 'none',
        );
    }

    private function get_country_codes() {
        // This is the single source of truth for country codes
        return array(
            'Afghanistan' => 'AF',
            'Albania' => 'AL',
            'Algeria' => 'DZ',
            'Andorra' => 'AD',
            'Angola' => 'AO',
            'Argentina' => 'AR',
            'Armenia' => 'AM',
            'Australia' => 'AU',
            'Austria' => 'AT',
            'Azerbaijan' => 'AZ',
            'Bahamas' => 'BS',
            'Bahrain' => 'BH',
            'Bangladesh' => 'BD',
            'Belarus' => 'BY',
            'Belgium' => 'BE',
            'Brazil' => 'BR',
            'Bulgaria' => 'BG',
            'Canada' => 'CA',
            'Chile' => 'CL',
            'China' => 'CN',
            'Colombia' => 'CO',
            'Croatia' => 'HR',
            'Cuba' => 'CU',
            'Cyprus' => 'CY',
            'Czech Republic' => 'CZ',
            'Denmark' => 'DK',
            'Egypt' => 'EG',
            'Estonia' => 'EE',
            'Finland' => 'FI',
            'France' => 'FR',
            'Georgia' => 'GE',
            'Germany' => 'DE',
            'Greece' => 'GR',
            'Hong Kong' => 'HK',
            'Hungary' => 'HU',
            'Iceland' => 'IS',
            'India' => 'IN',
            'Indonesia' => 'ID',
            'Iran' => 'IR',
            'Iraq' => 'IQ',
            'Ireland' => 'IE',
            'Israel' => 'IL',
            'Italy' => 'IT',
            'Japan' => 'JP',
            'Jordan' => 'JO',
            'Kazakhstan' => 'KZ',
            'Kuwait' => 'KW',
            'Latvia' => 'LV',
            'Lebanon' => 'LB',
            'Libya' => 'LY',
            'Lithuania' => 'LT',
            'Luxembourg' => 'LU',
            'Malaysia' => 'MY',
            'Malta' => 'MT',
            'Mexico' => 'MX',
            'Morocco' => 'MA',
            'Netherlands' => 'NL',
            'New Zealand' => 'NZ',
            'Nigeria' => 'NG',
            'Norway' => 'NO',
            'Pakistan' => 'PK',
            'Palestine' => 'PS',
            'Peru' => 'PE',
            'Philippines' => 'PH',
            'Poland' => 'PL',
            'Portugal' => 'PT',
            'Qatar' => 'QA',
            'Romania' => 'RO',
            'Russia' => 'RU',
            'Saudi Arabia' => 'SA',
            'Serbia' => 'RS',
            'Singapore' => 'SG',
            'Slovakia' => 'SK',
            'Slovenia' => 'SI',
            'South Africa' => 'ZA',
            'South Korea' => 'KR',
            'Spain' => 'ES',
            'Sweden' => 'SE',
            'Switzerland' => 'CH',
            'Syria' => 'SY',
            'Taiwan' => 'TW',
            'Thailand' => 'TH',
            'Tunisia' => 'TN',
            'Turkey' => 'TR',
            'Ukraine' => 'UA',
            'United Arab Emirates' => 'AE',
            'United Kingdom' => 'GB',
            'United States' => 'US',
            'Vietnam' => 'VN',
            'Unknown' => 'XX'
        );
    }

    private function get_country_code($country_name) {
        if (!is_string($country_name)) {
            return 'XX';
        }
        
        // If already a valid 2-letter country code
        if (preg_match('/^[A-Z]{2}$/', $country_name)) {
            return $country_name;
        }
        
        $country_codes = $this->get_country_codes();
        
        // Direct match (case-sensitive)
        if (isset($country_codes[$country_name])) {
            return $country_codes[$country_name];
        }
        
        // Case-insensitive search
        foreach ($country_codes as $name => $code) {
            if (strcasecmp($name, $country_name) === 0) {
                return $code;
            }
        }
        
        // Reverse lookup - if value is a country code
        $flipped = array_flip($country_codes);
        if (isset($flipped[$country_name])) {
            return $country_name;
        }
        
        return 'XX';
    }

    /**
     * AJAX handler for blocking a single IP
     */
    public function ajax_block_ip() {
        // Check nonce for security
        check_ajax_referer('maspik_statistics_nonce', 'nonce');
        
        // Check if user has permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('You do not have permission to perform this action.', 'contact-forms-anti-spam')));
            return;
        }
        
        // Get the IP to block
        $ip = isset($_POST['ip']) ? sanitize_text_field($_POST['ip']) : '';
        
        if (empty($ip)) {
            wp_send_json_error(array('message' => __('No IP address provided.', 'contact-forms-anti-spam')));
            return;
        }
        
        // Get the current blacklist
        $blacklist = maspik_get_settings('ip_blacklist') ? efas_makeArray(maspik_get_settings('ip_blacklist')) : array();
        
        // Check if IP is already blocked
        if (in_array($ip, $blacklist)) {
            wp_send_json_success(array('message' => __('IP is already blocked.', 'contact-forms-anti-spam')));
            return;
        }
        
        // Add IP to blacklist
        $blacklist[] = $ip;
        
        // Save the updated blacklist
        $result = maspik_save_settings('ip_blacklist', implode("\n", $blacklist));
        
        if ($result) {
            wp_send_json_success(array('message' => __('IP blocked successfully.', 'contact-forms-anti-spam')));
        } else {
            wp_send_json_error(array('message' => __('Failed to update blacklist.', 'contact-forms-anti-spam')));
        }
    }

    /**
     * AJAX handler for blocking multiple IPs
     */
    public function ajax_block_multiple_ips() {
        // Check nonce for security
        check_ajax_referer('maspik_statistics_nonce', 'nonce');
        
        // Check if user has permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('You do not have permission to perform this action.', 'contact-forms-anti-spam')));
            return;
        }
        
        // Get the IPs to block
        $ips = isset($_POST['ips']) ? $_POST['ips'] : array();
        
        if (empty($ips) || !is_array($ips)) {
            wp_send_json_error(array('message' => __('No IP addresses provided.', 'contact-forms-anti-spam')));
            return;
        }
        
        // Sanitize IPs
        $ips = array_map('sanitize_text_field', $ips);
        
        // Get the current blacklist
        $blacklist = maspik_get_settings('ip_blacklist') ? efas_makeArray(maspik_get_settings('ip_blacklist')) : array();
        
        // Add new IPs to blacklist (avoid duplicates)
        $added_count = 0;
        foreach ($ips as $ip) {
            if (!in_array($ip, $blacklist)) {
                $blacklist[] = $ip;
                $added_count++;
            }
        }
        
        if ($added_count === 0) {
            wp_send_json_success(array('message' => __('All IPs are already blocked.', 'contact-forms-anti-spam'), 'count' => 0));
            return;
        }
        
        // Save the updated blacklist
        $result = maspik_save_settings('ip_blacklist', implode("\n", $blacklist));
        
        if ($result) {
            wp_send_json_success(array('message' => __('IPs blocked successfully.', 'contact-forms-anti-spam'), 'count' => $added_count));
        } else {
            wp_send_json_error(array('message' => __('Failed to update blacklist.', 'contact-forms-anti-spam')));
        }
    }

    /**
     * AJAX handler for blocking a country
     */
    public function ajax_block_country() {
        // Check nonce for security
        check_ajax_referer('maspik_statistics_nonce', 'nonce');
        
        // Check if user has permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('You do not have permission to perform this action.', 'contact-forms-anti-spam')));
            return;
        }
        
        // Check if country blocking is supported
        if (!cfes_is_supporting("country_location")) {
            wp_send_json_error(array(
                'message' => __('Country blocking is a Pro feature.', 'contact-forms-anti-spam'),
                'is_pro' => false,
                'upgrade_url' => admin_url('admin.php?page=maspik&tab=license')
            ));
            return;
        }
        
        // Check if the AllowedOrBlockCountries setting is in block mode
        $AllowedOrBlockCountries = maspik_get_settings('AllowedOrBlockCountries');
        if ($AllowedOrBlockCountries != 'block') {
            wp_send_json_error(array(
                'message' => __('Cannot add to country blacklist. Your Geolocation restrictions is set to "Allow only specific countries". You can only add countries through Settings page.', 'contact-forms-anti-spam'),
                'wrong_mode' => true,
                'settings_url' => admin_url('admin.php?page=maspik&tab=geolocation')
            ));
            return;
        }
        
        // Get the country to block
        $country = isset($_POST['country']) ? sanitize_text_field($_POST['country']) : '';
        
        if (empty($country)) {
            wp_send_json_error(array('message' => __('No country code provided.', 'contact-forms-anti-spam')));
            return;
        }
        
        // Convert country name to country code if needed
        $country_code = $this->get_country_code($country);
        if (!$country_code) {
            wp_send_json_error(array('message' => __('Invalid country name.', 'contact-forms-anti-spam')));
            return;
        }
        
        // Get the current blacklist
        $opt_value = maspik_get_dbvalue();
        $country_blacklist_array = maspik_get_settings('country_blacklist', 'select');
        
        // Convert to standard array
        $country_blacklist = array();
        foreach($country_blacklist_array as $value){
            $cleanval = trim($value->$opt_value);
            if(!empty($cleanval)){
                $country_blacklist = explode(" ", $value->$opt_value);
            }
        }
        
        // Check if country is already blocked
        if (in_array($country_code, $country_blacklist)) {
            wp_send_json_success(array('message' => __('Country is already blocked.', 'contact-forms-anti-spam')));
            return;
        }
        
        // Add country code to blacklist
        $country_blacklist[] = $country_code;
        
        // Save the updated blacklist
        $result = maspik_save_settings('country_blacklist', implode(" ", $country_blacklist));
        
        if ($result) {
            wp_send_json_success(array('message' => __('Country blocked successfully.', 'contact-forms-anti-spam')));
        } else {
            wp_send_json_error(array('message' => __('Failed to update country blacklist.', 'contact-forms-anti-spam')));
        }
    }

    /**
     * AJAX handler for blocking an email domain
     */
    public function ajax_block_domain() {
        // Check nonce for security
        check_ajax_referer('maspik_statistics_nonce', 'nonce');
        
        // Check if user has permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('You do not have permission to perform this action.', 'contact-forms-anti-spam')));
            return;
        }
        
        // Get the domain to block
        $domain = isset($_POST['domain']) ? sanitize_text_field($_POST['domain']) : '';
        
        if (empty($domain)) {
            wp_send_json_error(array('message' => __('No domain provided.', 'contact-forms-anti-spam')));
            return;
        }
        
        // Format domain for blacklist (add @ prefix)
        $domain_pattern = '@' . $domain;
        
        // Get the current email blacklist
        $email_blacklist = maspik_get_settings('emails_blacklist') ? efas_makeArray(maspik_get_settings('emails_blacklist')) : array();
        
        // Check if domain is already blocked
        if (in_array($domain_pattern, $email_blacklist)) {
            wp_send_json_success(array('message' => __('Domain is already blocked.', 'contact-forms-anti-spam')));
            return;
        }
        
        // Add domain to blacklist
        $email_blacklist[] = $domain_pattern;
        
        // Save the updated blacklist
        $result = maspik_save_settings('emails_blacklist', implode("\n", $email_blacklist));
        
        if ($result) {
            wp_send_json_success(array('message' => __('Email domain blocked successfully.', 'contact-forms-anti-spam')));
        } else {
            wp_send_json_error(array('message' => __('Failed to update email blacklist.', 'contact-forms-anti-spam')));
        }
    }

    /**
     * AJAX handler for blocking multiple email domains
     */
    public function ajax_block_multiple_domains() {
        // Check nonce for security
        check_ajax_referer('maspik_statistics_nonce', 'nonce');
        
        // Check if user has permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('You do not have permission to perform this action.', 'contact-forms-anti-spam')));
            return;
        }
        
        // Get the domains to block
        $domains = isset($_POST['domains']) ? $_POST['domains'] : array();
        
        if (empty($domains) || !is_array($domains)) {
            wp_send_json_error(array('message' => __('No domains provided.', 'contact-forms-anti-spam')));
            return;
        }
        
        // Sanitize domains
        $domains = array_map('sanitize_text_field', $domains);
        
        // Format domains for blacklist (add @ prefix)
        $domain_patterns = array_map(function($domain) {
            return '@' . $domain;
        }, $domains);
        
        // Get the current email blacklist
        $email_blacklist = maspik_get_settings('emails_blacklist') ? efas_makeArray(maspik_get_settings('emails_blacklist')) : array();
        
        // Add new domains to blacklist (avoid duplicates)
        $added_count = 0;
        foreach ($domain_patterns as $domain_pattern) {
            if (!in_array($domain_pattern, $email_blacklist)) {
                $email_blacklist[] = $domain_pattern;
                $added_count++;
            }
        }
        
        if ($added_count === 0) {
            wp_send_json_success(array('message' => __('All domains are already blocked.', 'contact-forms-anti-spam'), 'count' => 0));
            return;
        }
        
        // Save the updated blacklist
        $result = maspik_save_settings('emails_blacklist', implode("\n", $email_blacklist));
        
        if ($result) {
            wp_send_json_success(array('message' => __('Email domains blocked successfully.', 'contact-forms-anti-spam'), 'count' => $added_count));
        } else {
            wp_send_json_error(array('message' => __('Failed to update email blacklist.', 'contact-forms-anti-spam')));
        }
    }

    /**
     * AJAX handler for blocking multiple countries
     */
    public function ajax_block_multiple_countries() {
        // Check nonce for security
        check_ajax_referer('maspik_statistics_nonce', 'nonce');
        
        // Check if user has permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('You do not have permission to perform this action.', 'contact-forms-anti-spam')));
            return;
        }
        
        // Check if user is a Pro user
        if (!cfes_is_supporting("country_location")) {
            wp_send_json_error(array(
                'message' => __('Country blocking is a Pro feature.', 'contact-forms-anti-spam'),
                'is_pro' => false,
                'upgrade_url' => admin_url('admin.php?page=maspik&tab=license')
            ));
            return;
        }
        
        // Check if the AllowedOrBlockCountries setting is in block mode
        $AllowedOrBlockCountries = maspik_get_settings('AllowedOrBlockCountries');
        if ($AllowedOrBlockCountries != 'block') {
            wp_send_json_error(array(
                'message' => __('Cannot add to country blacklist. Your Geolocation restrictions is set to "Allow only specific countries". You can only add countries through Settings page.', 'contact-forms-anti-spam'),
                'wrong_mode' => true,
                'settings_url' => admin_url('admin.php?page=maspik&tab=geolocation')
            ));
            return;
        }
        
        // Get the countries to block
        $countries = isset($_POST['countries']) ? $_POST['countries'] : array();
        
        if (empty($countries) || !is_array($countries)) {
            wp_send_json_error(array('message' => __('No countries provided.', 'contact-forms-anti-spam')));
            return;
        }
        
        // Sanitize countries and convert to country codes
        $country_codes = array();
        foreach ($countries as $country) {
            $country = sanitize_text_field($country);
            $code = $this->get_country_code($country);
            if ($code) {
                $country_codes[] = $code;
            }
        }
        
        if (empty($country_codes)) {
            wp_send_json_error(array('message' => __('No valid country codes found.', 'contact-forms-anti-spam')));
            return;
        }
        
        // Get the current blacklist
        $opt_value = maspik_get_dbvalue();
        $country_blacklist_array = maspik_get_settings('country_blacklist', 'select');
        
        // Convert to standard array
        $country_blacklist = array();
        foreach($country_blacklist_array as $value){
            $cleanval = trim($value->$opt_value);
            if(!empty($cleanval)){
                $country_blacklist = explode(" ", $value->$opt_value);
            }
        }
        
        // Add new countries to blacklist (avoid duplicates)
        $added_count = 0;
        foreach ($country_codes as $code) {
            if (!in_array($code, $country_blacklist)) {
                $country_blacklist[] = $code;
                $added_count++;
            }
        }
        
        if ($added_count === 0) {
            wp_send_json_success(array('message' => __('All countries are already blocked.', 'contact-forms-anti-spam'), 'count' => 0));
            return;
        }
        
        // Save the updated blacklist
        $result = maspik_save_settings('country_blacklist', implode(" ", $country_blacklist));
        
        if ($result) {
            wp_send_json_success(array('message' => __('Countries blocked successfully.', 'contact-forms-anti-spam'), 'count' => $added_count));
        } else {
            wp_send_json_error(array('message' => __('Failed to update country blacklist.', 'contact-forms-anti-spam')));
        }
    }
}

// Initialize the class
Maspik_Statistics::get_instance(); 