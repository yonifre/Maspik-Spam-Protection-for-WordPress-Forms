<?php
/**
 * @wordpress-plugin
 * Plugin Name:       Maspik - Ultimate Spam Protection
 * Plugin URI:        https://wpmaspik.com/
 * Description:       The best spam protection plugin. Block spam using advanced filters, AI, blacklists, and IP verification and honeypot fields...
 * Version:           2.7.3
 * Author:            WpMaspik
 * Author URI:        https://wpmaspik.com/?readme
 * Text Domain:       contact-forms-anti-spam
 * Domain Path:       /languages
 * License:           GPL-3.0+
 * License URI:       http://www.gnu.org/licenses/gpl-3.0.txt
 * 
 * Maspik is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 * 
 * Any spam blocking action taken by this plugin is solely at the user's own risk and discretion.
 * The plugin developers and contributors cannot be held responsible for any false positives
 * or legitimate messages that may be blocked.
 *
 * You should have received a copy of the GNU General Public License
 * along with Maspik. If not, see <http://www.gnu.org/licenses/>.
 * 
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) exit; 

/**
 * Currently plugin version.
 */
define( 'MASPIK_VERSION', '2.7.3' );
define('MASPIK_PLUGIN_FILE', __FILE__);

/**
 * Load plugin text domain and initialize plugin
 */
require_once plugin_dir_path(__FILE__) . 'includes/consts.php';

function maspik_plugins_loaded() {
    // Load text domain
    load_plugin_textdomain(
        'contact-forms-anti-spam',
        false,
        dirname(plugin_basename(__FILE__)) . '/languages/'
    );

    // Initialize plugin
    require plugin_dir_path(__FILE__) . 'includes/class-maspik.php';
    require_once plugin_dir_path(__FILE__) . 'admin/maspik-statistics.php';
    require_once plugin_dir_path(__FILE__) . 'includes/dashboard-statistics.php';

    if (apply_filters('maspik_active_license_library', true)) {
        require plugin_dir_path(__FILE__) . 'license/license.php';
    }

    $plugin = new Maspik();
    $plugin->run();
}

// Hook into plugins_loaded which runs before init
add_action('init', 'maspik_plugins_loaded');

// Add plugin row meta
add_filter( 'plugin_row_meta', 'maspik_plugin_row_meta', 10, 2 );
function maspik_plugin_row_meta( $links, $file ) {
	if( strpos( $file, basename(__FILE__) ) ) {
		$maspik_links = array(
			'donat_link' => '<a href="https://wordpress.org/support/plugin/contact-forms-anti-spam/reviews/#new-post" target="_blank">'.__( 'Love it? Rate us 5⭐', 'contact-forms-anti-spam' ).'</a>',
			'settings' => '<a href="'.admin_url().'admin.php?page=maspik" target="_blank">'.__( 'Setting page', 'contact-forms-anti-spam' ).'</a>',
		);
		
		$links = array_merge( $links, $maspik_links );
	}
	
	return $links;
}

//make new log table
function create_maspik_log_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'maspik_spam_logs';
    
    // define the structure of the table
    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        spam_type varchar(191) NOT NULL,
        spam_value varchar(191) NOT NULL,
        spam_detail longtext NOT NULL,
        spam_ip varchar(191) NOT NULL,
        spam_country varchar(191) NOT NULL,
        spam_agent varchar(191) NOT NULL,
        spam_date varchar(191) NOT NULL,
        spam_source varchar(191) NOT NULL,
        spamsrc_label varchar(191) NOT NULL DEFAULT '',
        spamsrc_val varchar(191) NOT NULL DEFAULT '',
        spam_tag varchar(191) NOT NULL DEFAULT '',
        PRIMARY KEY  (id)
    ) " . $wpdb->get_charset_collate();

    // if the table doesn't exist or if we need to update the structure
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
    
    // mark the function as run successfully
    update_option('maspik_columns_last_check', '2');
}

function maspik_check_and_update_log_table($upgrader_object = null, $options = array()) {
    // Check if this is our plugin being updated
    if ($upgrader_object && !empty($options)) {
        if (!isset($options['plugin']) || strpos($options['plugin'], 'contact-forms-anti-spam') === false) {
            return;
        }
    }

    // Check user capabilities if not during activation/update
    if (!$upgrader_object && !current_user_can('manage_options')) {
        return;
    }

    // Check if required function exists
    if (!function_exists('create_maspik_log_table')) {
        error_log('Maspik DB Update Error: Required function create_maspik_log_table() not found');
        return;
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'maspik_spam_logs';
    $current_db_version = get_option('maspik_db_version', '1.0');
    $required_db_version = '2.0'; // start at version 2.5.2

    // Start transaction
    $transaction_started = $wpdb->query('START TRANSACTION');
    if ($transaction_started === false) {
        error_log('Maspik DB Update Error: Failed to start transaction - ' . $wpdb->last_error);
        if (!$upgrader_object) {
            wp_die('Failed to start database transaction: ' . $wpdb->last_error);
        }
        return;
    }

    try {
        // Only run if we need to update
        if (version_compare($current_db_version, $required_db_version, '<')) {
            // Check if table exists
            $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name));

            if (!$table_exists) {
                $result = create_maspik_log_table();
                if ($result === false) {
                    throw new Exception('Failed to create log table: ' . $wpdb->last_error);
                }
            } else {
                // Check if columns exist
                $columns = $wpdb->get_col("SHOW COLUMNS FROM $table_name");
                if ($columns === false) {
                    throw new Exception('Failed to get columns: ' . $wpdb->last_error);
                }
                
                // Add missing columns
                $new_columns = array(
                    'spam_tag' => "ALTER TABLE $table_name ADD COLUMN spam_tag varchar(191) NOT NULL DEFAULT ''",
                    'spamsrc_label' => "ALTER TABLE $table_name ADD COLUMN spamsrc_label varchar(191) NOT NULL DEFAULT ''",
                    'spamsrc_val' => "ALTER TABLE $table_name ADD COLUMN spamsrc_val varchar(191) NOT NULL DEFAULT ''"
                );
                
                foreach ($new_columns as $column => $sql) {
                    if (!in_array($column, $columns)) {
                        $result = $wpdb->query($sql);
                        if ($result === false) {
                            throw new Exception("Failed to add column $column: " . $wpdb->last_error);
                        }
                    }
                }
            }

            // Update the stored version
            $update_result = update_option('maspik_db_version', $required_db_version);
            if ($update_result === false) {
                throw new Exception('Failed to update version number');
            }
            
            // Commit transaction
            $commit_result = $wpdb->query('COMMIT');
            if ($commit_result === false) {
                throw new Exception('Failed to commit transaction: ' . $wpdb->last_error);
            }
        } else {
            // Commit transaction even if no update was needed
            $wpdb->query('COMMIT');
        }
    } catch (Exception $e) {
        // Rollback on error
        $wpdb->query('ROLLBACK');
        
        // If this is an activation, we should show error but not deactivate
        if (!$upgrader_object) {
            wp_die('Failed to create or update database tables: ' . $e->getMessage());
        }
    }
}

// Run during plugin update
add_action('upgrader_process_complete', 'maspik_check_and_update_log_table', 10, 2);


// Run during plugin activation
register_activation_hook(__FILE__, function() {
    try {
        maspik_check_and_update_log_table(null, array());
    } catch (Exception $e) {
        // Deactivate the plugin if database setup fails
        error_log('Maspik DB Activation Error: ' . $e->getMessage());

        deactivate_plugins(plugin_basename(__FILE__));
        wp_die('Plugin activation failed: ' . $e->getMessage());
    }
});

add_action('admin_footer-plugins.php', 'maspik_deactivation_survey');
function maspik_deactivation_survey() {
    $nonce = wp_create_nonce('maspik_deactivation_survey');
    ?>
    <div id="maspik-deactivation-survey" style="display: none;">
        <button type="button" class="maspik-close-button" aria-label="Close">×</button>
        <!-- Add loader HTML -->
        <div id="maspik-loader" style="display: none;">
            <div class="maspik-spinner"></div>
            <div class="maspik-loader-text">
                <?php esc_html_e('Sending feedback...', 'contact-forms-anti-spam'); ?>
                <div class="maspik-loader-subtext"><?php esc_html_e('Thank you for helping us improve!', 'contact-forms-anti-spam'); ?></div>
            </div>
        </div>

        <h3><?php esc_html_e('Quick Feedback', 'contact-forms-anti-spam'); ?></h3>
        <h4><?php esc_html_e('Moment of your time means meaningful improvement for everyone', 'contact-forms-anti-spam'); ?></h4>
        <form method="post" id="maspik-deactivation-form">
            <input type="hidden" name="nonce" value="<?php echo $nonce; ?>">
            
            <div class="maspik-survey-options">
                <label>
                    <input type="radio" name="maspik_deactivation_reason" value="blocked_legitimate">
                    <?php esc_html_e('It\'s blocking legitimate submissions', 'contact-forms-anti-spam'); ?>
                </label>
                <div class="reason-text-wrapper" data-reason="blocked_legitimate">
                    <p><?php esc_html_e('Before deactivating, try these solutions:', 'contact-forms-anti-spam'); ?></p>
                    <ul>
                        <li><?php esc_html_e('Lower the protection level in settings', 'contact-forms-anti-spam'); ?></li>
                        <li><?php esc_html_e('Check your spam log to understand why submissions are blocked', 'contact-forms-anti-spam'); ?></li>
                        <li><a href="https://wpmaspik.com/documentation/spam-log/" target="_blank"><?php esc_html_e('Read our guide about preventing false positives', 'contact-forms-anti-spam'); ?></a></li>
						<li><a href="https://wpmaspik.com/#support" target="_blank"><?php esc_html_e('Contact our support team', 'contact-forms-anti-spam'); ?></a></li>
					</ul>
                </div>

                <label>
                    <input type="radio" name="maspik_deactivation_reason" value="not_blocking_spam">
                    <?php esc_html_e('Not blocking enough spam', 'contact-forms-anti-spam'); ?>
                </label>
                <div class="reason-text-wrapper" data-reason="not_blocking_spam">
                    <p><?php esc_html_e('Try these steps to improve spam blocking:', 'contact-forms-anti-spam'); ?></p>
                    <ul>
                        <li><?php esc_html_e('Add some words to the blacklist by field type', 'contact-forms-anti-spam'); ?></li>
                        <li><?php esc_html_e('Enable additional spam filters', 'contact-forms-anti-spam'); ?></li>
                        <li><?php esc_html_e('Go through the options and make sure you have the right settings', 'contact-forms-anti-spam'); ?></li>
                        <li><a href="https://wpmaspik.com/documentation/getting-started/" target="_blank"><?php esc_html_e('Learn more about not blocking spam', 'contact-forms-anti-spam'); ?></a></li>
						<li><a href="https://wpmaspik.com/#support" target="_blank"><?php esc_html_e('Contact our support team', 'contact-forms-anti-spam'); ?></a></li>
					</ul>
                </div>

                <label>
                    <input type="radio" name="maspik_deactivation_reason" value="not_sure_how_to_use">
                    <?php esc_html_e('Not sure how to use it', 'contact-forms-anti-spam'); ?>
                </label>
                <div class="reason-text-wrapper" data-reason="not_sure_how_to_use">
                    <p><?php esc_html_e('We\'re here to help:', 'contact-forms-anti-spam'); ?></p>
                    <ul>
                        <li><a href="https://wpmaspik.com/documentation/getting-started/" target="_blank"><?php esc_html_e('Read our quick start guide', 'contact-forms-anti-spam'); ?></a></li>
                        <li><a href="https://wpmaspik.com/#support" target="_blank"><?php esc_html_e('Contact our support team', 'contact-forms-anti-spam'); ?></a></li>
                    </ul>
                </div>

                <label>
                    <input type="radio" name="maspik_deactivation_reason" value="found_better_plugin">
                    <?php esc_html_e('Switched to an alternative plugin', 'contact-forms-anti-spam'); ?>
                </label>
                <div class="reason-text-wrapper" data-reason="found_better_plugin">
                    <p><?php esc_html_e('Would you mind sharing which plugin? This helps us improve:', 'contact-forms-anti-spam'); ?></p>
                    <textarea name="other_reason" placeholder="<?php esc_html_e('Which plugin did you choose?', 'contact-forms-anti-spam'); ?>"></textarea>
                </div>

                <label>
                    <input type="radio" name="maspik_deactivation_reason" value="temporary">
                    <?php esc_html_e('Temporary deactivation - I\'m just debugging an issue', 'contact-forms-anti-spam'); ?>
                </label>
                <div class="reason-text-wrapper" data-reason="temporary">
                    <p><?php esc_html_e('Good luck with your debugging!', 'contact-forms-anti-spam'); ?></p>
                </div>

                <label>
                    <input type="radio" name="maspik_deactivation_reason" value="other">
                    <?php esc_html_e('Other', 'contact-forms-anti-spam'); ?>
                </label>
                <div class="reason-text-wrapper" data-reason="other">
                    <textarea name="other_reason" placeholder="<?php esc_html_e('Your feedback helps us improve! Share your thoughts in 5 seconds...', 'contact-forms-anti-spam'); ?>"></textarea>
                </div>
            </div>

            <div class="maspik-survey-buttons">
                <button type="button" class="button" id="maspik-skip-survey"><?php esc_html_e('Skip', 'contact-forms-anti-spam'); ?></button>
                <button type="submit" class="button button-primary" id="maspik-submit-survey">
                    <span class="dashicons dashicons-yes"></span>
                    <?php esc_html_e('Submit & Deactivate', 'contact-forms-anti-spam'); ?>
                </button>
            </div>
        </form>
    </div>

    <style>
        #maspik-deactivation-survey {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0,0,0,0.3);
            z-index: 999999;
            width: 500px;
        }

        .maspik-survey-options label {
            display: block;
            margin: 0;
            padding: 7px;
            border-radius: 4px;
        }

        .maspik-survey-options label:hover {
            background-color: #f8f8f8;
        }

        .maspik-survey-buttons {
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid #eee;
            text-align: right;
        }
        
        #maspik-submit-survey {
            margin-left: 10px;
        }
        
        #maspik-submit-survey .dashicons {
            margin: 4px 4px 0 0;
        }

        .reason-text-wrapper {
            display: none;
            margin: 10px 0 15px 25px;
            padding: 10px;
            background: #f8f8f8;
            border-left: 3px solid #ddd;
            border-radius: 2px;
        }
        
        .reason-text-wrapper ul {
            margin: 5px 0 5px 20px;
            list-style-type: disc;
        }
        
        .reason-text-wrapper textarea {
            width: 100%;
            min-height: 60px;
            margin-top: 5px;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        /* Loader styles */
        #maspik-loader {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.9);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            z-index: 100;
        }

        .maspik-spinner {
            width: 40px;
            height: 40px;
            border: 4px solid #f3f3f3;
            border-top: 4px solid #3498db;
            border-radius: 50%;
            animation: maspik-spin 1s linear infinite;
            margin-bottom: 10px;
        }

        .maspik-loader-text {
            margin-top: 15px;
            font-size: 14px;
            color: #444;
        }

        .maspik-loader-subtext {
            margin-top: 5px;
            font-size: 12px;
            color: #666;
        }

        @keyframes maspik-spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .maspik-close-button {
            position: absolute;
            right: 10px;
            top: 10px;
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #666;
            padding: 5px 10px;
            border-radius: 4px;
        }

        .maspik-close-button:hover {
            background: #f0f0f0;
            color: #000;
        }
    </style>

    <script>
    jQuery(document).ready(function($) {
        // check if jQuery is loaded
        if (typeof $ === 'undefined') {
            return;
        }

        var deactivateLink = $('tr[data-plugin="contact-forms-anti-spam/contact-forms-anti-spam.php"] .deactivate a');
        var isSubmitting = false; // prevent double submission

        deactivateLink.on('click', function(e) {
            e.preventDefault();
            $('#maspik-deactivation-survey').show();
            deactivationLink = $(this).attr('href');
        });

        // handle form submission
        $('#maspik-deactivation-form').on('submit', function(e) {
            e.preventDefault();

            if (isSubmitting) {
                return;
            }
            isSubmitting = true;

            $('#maspik-loader').fadeIn(200);

            // check if reason is selected
            var reason = $('input[name="maspik_deactivation_reason"]:checked').val();
            if (!reason) {
                alert('<?php esc_html_e('Please select a reason', 'contact-forms-anti-spam'); ?>');
                isSubmitting = false;
                $('#maspik-loader').fadeOut(200);
                return;
            }

            // check if text is entered if 'other' is selected
            if (reason === 'other' && !$('textarea[name="other_reason"]').val().trim()) {
                alert('<?php esc_html_e('Please provide more details', 'contact-forms-anti-spam'); ?>');
                isSubmitting = false;
                $('#maspik-loader').fadeOut(200);
                return;
            }

            var data = {
                reason: reason,
                other_reason:  $('textarea[name="other_reason"]').val().trim(),
                site_url: '<?php echo esc_js(get_site_url()); ?>',
                plugin_version: '<?php echo esc_js(MASPIK_VERSION); ?>',
                wp_version: '<?php echo esc_js(get_bloginfo("version")); ?>',
                php_version: '<?php echo esc_js(phpversion()); ?>',
                spam_count: '<?php echo esc_js(get_option("spamcounter", 0)); ?>'
            };

            //console.log('Sending data:', data);

            try {
                var ajaxTimeout = setTimeout(function() {
                    //console.log('Timeout reached - no response after 4 seconds');
                    isSubmitting = false;
                    $('#maspik-loader').fadeOut(200, function() {
                        proceedWithDeactivation();
                    });
                }, 4000);

                $.ajax({
                    url: 'https://receiver.wpmaspik.com/wp-json/statistics-maspik/v1/deactivation',
                    method: 'POST',
                    data: data,
                    timeout: 3500,
                    success: function(response) {
                    //    console.log('Success response:', response);
                        clearTimeout(ajaxTimeout);
                        $('#maspik-loader').fadeOut(200, function() {
                            proceedWithDeactivation();
                        });
                    },
                    error: function(xhr, status, error) {
                        /*console.log('Error details:', {
                            status: status,
                            error: error,
                            response: xhr.responseText,
                            statusCode: xhr.status
                        });*/
                        clearTimeout(ajaxTimeout);
                        $('#maspik-loader').fadeOut(200, function() {
                            proceedWithDeactivation();
                        });
                    }
                });
            } catch (e) {
                //console.log('Exception in AJAX:', e);
                $('#maspik-loader').fadeOut(200, function() {
                    proceedWithDeactivation();
                });
            }
        });

        // Skip survey
        $('#maspik-skip-survey').on('click', function() {
            proceedWithDeactivation();
        });

        // function to proceed with deactivation
        function proceedWithDeactivation() {
            if (deactivationLink) {
                window.location.href = deactivationLink;
            }
        }

        // Show/hide reason text based on selection
        $('input[name="maspik_deactivation_reason"]').on('change', function() {
            $('.reason-text-wrapper').slideUp(200);
            var selectedReason = $(this).val();
            $('[data-reason="' + selectedReason + '"]').slideDown(200);
        });

        $('.maspik-close-button').on('click', function() {
            $('#maspik-deactivation-survey').hide();
        });

        $(document).on('keyup', function(e) {
            if (e.key === "Escape") {
                $('#maspik-deactivation-survey').hide();
            }
        });
    });
    </script>
    <?php
}