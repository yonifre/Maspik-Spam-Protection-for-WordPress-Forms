<?php
// If this file is called directly, abort.
if (!defined('ABSPATH')) exit; 


/*
* Main function
*/

/**
 * Administrators — change plugin settings, import/export, clear log, statistics actions.
 */
function maspik_user_can_manage_settings() {
    return current_user_can( 'manage_options' );
}

/**
 * Editors and above — view spam log, download CSV, view statistics (read-only).
 */
function maspik_user_can_view_spam_log() {
    return current_user_can( 'edit_pages' );
}

function maspik_get_field_display_name($field_id) {
    global $MASPIK_FIELD_DISPLAY_NAMES;
    return isset($MASPIK_FIELD_DISPLAY_NAMES[$field_id]) ? $MASPIK_FIELD_DISPLAY_NAMES[$field_id] : $field_id;
}


function maspik_delete_filter() {
    global $wpdb;

    // Add nonce verification
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'maspik_delete_action')) {
        wp_send_json_error('Invalid security token.');
        return;
    }

    if (!current_user_can('manage_options')) {
        wp_send_json_error('You do not have permission to perform this action.');
        return;
    }

    $row_id = intval($_POST['row_id']);
    $logtable = maspik_get_logtable();

    // Get the value of "option_name" from the row with the given id
    $spam_label = $wpdb->get_var( $wpdb->prepare(
        "SELECT spamsrc_label FROM $logtable WHERE id = %d",
        $row_id
    ));

    $spam_val = $wpdb->get_var( $wpdb->prepare(
        "SELECT spamsrc_val FROM $logtable WHERE id = %d",
        $row_id
    ));

    if ($spam_label) {

        $update_data = array('spam_tag' => 'not spam');
        $where = array('id' => $row_id);

        // Convert textarea_field to text_blacklist (merged fields)
        // textarea_blacklist has been merged into text_blacklist
        if ($spam_label === "textarea_field" || $spam_label === "textarea_blacklist") {
            $spam_label = "text_blacklist";
        }

        if($spam_label == "text_blacklist" || $spam_label == "emails_blacklist" || $spam_label == "ip_blacklist"){
            
            $option_arval = efas_makeArray(maspik_get_settings($spam_label));

            if (is_array($option_arval)) {
                // Initialize an empty array to hold the filtered values
                $filtered_list = array();
                
                // Convert $spam_val to lowercase for case-insensitive comparison
                $spam_val_lower = strtolower($spam_val);
                
                // Iterate through the array and add values that are not equal to $spam_val (case-insensitive) to $filtered_list
                foreach ($option_arval as $val) {
                    $val = strtolower(rtrim($val));

                    if ($val !== $spam_val_lower) {
                        $filtered_list[] = $val;
                    }
                }
            
                // Convert the filtered list to a string with each item separated by a newline
                $filtered_list_string = implode("\n", $filtered_list);
                
                // Save the filtered list as a newline-separated string
                if(maspik_get_settings($spam_label) != $filtered_list_string ){
                    if (maspik_save_settings($spam_label, $filtered_list_string)) {
                        $wpdb->update($logtable, $update_data, $where);
                        wp_send_json_success(array('spam_label' => $spam_label));
                    } else {
                        wp_send_json_error('Failed to save settings.');
                    }
                }else{
                    wp_send_json_error('Failed to save settings.');
                }
            
            } else {

                if(maspik_get_settings($spam_label) != ""){
                    // If it's not an array, save an empty string
                    if (maspik_save_settings($spam_label, "")) {
                        $wpdb->update($logtable, $update_data, $where);
                        wp_send_json_success(array('spam_label' => $spam_label));
                    } else {
                        wp_send_json_error('Failed to save empty settings.');
                    }
                } else { 
                    wp_send_json_error('No settings found.');
                } 
            }

        } else { 
            if(maspik_get_settings($spam_label)!=""){
                // If it's not an array, save an empty string
                if (maspik_save_settings($spam_label, "")) {
                    $wpdb->update($logtable, $update_data, $where);
                    wp_send_json_success(array('spam_label' => $spam_label));
                } else {
                    wp_send_json_error('Failed to save empty settings.');
                }
            } else { 
                wp_send_json_error('No settings found.');
            } 
        } 
    } // Added closing brace for the outer `if ($spam_label)`

} // Closing brace for the function

    add_action('wp_ajax_delete_filter', 'maspik_delete_filter');


    function maspik_delete_row() {
        global $wpdb;

        // Add nonce verification
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'maspik_delete_action')) {
            wp_send_json_error([
                'message' => 'Invalid security token.',
                'code' => 'invalid_nonce'
            ]);
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error([
                'message' => 'You do not have permission to perform this action.',
                'code' => 'insufficient_permissions'
            ]);
            return;
        }

        $row_id = isset($_POST['row_id']) ? absint($_POST['row_id']) : 0;
        if (!$row_id) {
            wp_send_json_error([
                'message' => 'Invalid row ID.',
                'code' => 'invalid_row_id'
            ]);
            return;
        }

        $table = maspik_get_logtable();
        $result = $wpdb->delete(
            $table,
            ['id' => $row_id],
            ['%d']
        );

        if ($result === false) {
            wp_send_json_error([
                'message' => 'Failed to delete row from database.',
                'code' => 'delete_failed',
                'wpdb_last_error' => $wpdb->last_error
            ]);
            return;
        }

        wp_send_json_success([
            'message' => 'Row deleted successfully.',
            'row_id' => $row_id
        ]);
    }
    add_action('wp_ajax_maspik_delete_row', 'maspik_delete_row');

/**
 * Mark log entry as not spam and optionally report AI false positive.
 * Always fail-open: marking succeeds even if the report call fails.
 */
function maspik_not_spam() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Permission denied'));
    }

    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'maspik_delete_action')) {
        wp_send_json_error(array('message' => 'Invalid security token'));
    }

    $row_id      = isset($_POST['row_id']) ? absint($_POST['row_id']) : 0;
    $send_report = !empty($_POST['send_report']);

    if (!$row_id) {
        wp_send_json_error(array('message' => 'Invalid row ID.'));
    }

    global $wpdb;
    $table = maspik_get_logtable();
    $row   = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $row_id), ARRAY_A);

    if (!$row) {
        wp_send_json_error(array('message' => 'Row not found.'));
    }
    
    // Get spam_type from row data (source of truth)
    $spam_type = '';
    if (isset($row['spam_type']) && !empty($row['spam_type'])) {
        $spam_type = sanitize_text_field(trim($row['spam_type']));
    }
    
    // Fallback to POST value if row doesn't have it
    if (empty($spam_type)) {
        $spam_type = isset($_POST['spam_type']) ? sanitize_text_field(wp_unslash($_POST['spam_type'])) : '';
    }
    
    // Final fallback
    if (empty($spam_type)) {
        $spam_type = 'mark_not_spam';
    }

    $update = $wpdb->update(
        $table,
        array('spam_tag' => 'not spam'),
        array('id' => $row_id),
        array('%s'),
        array('%d')
    );

    if ($update === false) {
        wp_send_json_error(array('message' => 'Failed to update row.', 'wpdb_error' => $wpdb->last_error));
    }

    $report_sent  = false;
    $report_error = null;

    // Always send report if user requested it, regardless of spam type
    if ($send_report) {
        try {
            $server_ip = isset($_SERVER['SERVER_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['SERVER_ADDR'])) : '';
            if (!$server_ip && function_exists('gethostbyname')) {
                $server_ip = gethostbyname(parse_url(home_url(), PHP_URL_HOST));
            }

            $payload = array(
                'plugin_report'  => true,
                'site_url'       => home_url(),
                'server_ip'      => $server_ip,
                'server_host'    => isset($_SERVER['HTTP_HOST']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST'])) : '',
                'wp_version'     => get_bloginfo('version'),
                'plugin_version' => defined('MASPIK_VERSION') ? MASPIK_VERSION : '',
                'marked_at'      => current_time('mysql'),
                'action'         => $spam_type,
                'log_entry'      => $row,
            );

            $headers = array(
                'Content-Type'  => 'application/json',
                'plugin_report' => 'true',
            );

            $body_json = wp_json_encode($payload);
            
            // Validate JSON encoding
            if ($body_json === false || json_last_error() !== JSON_ERROR_NONE) {
                $report_error = 'JSON encoding failed';
            } else {
                $response = wp_remote_post(
                    'https://ipapi.wpmaspik.com/report',
                    array(
                        'headers'   => $headers,
                        'body'      => $body_json,
                        'timeout'   => 5,
                        'sslverify' => true,
                    )
                );

                if (is_wp_error($response)) {
                    $report_error = $response->get_error_message();
                } else {
                    $code = wp_remote_retrieve_response_code($response);
                    $response_body = wp_remote_retrieve_body($response);
                    if ($code >= 200 && $code < 300) {
                        $report_sent = true;
                    } else {
                        $report_error = 'HTTP ' . $code . ( !empty($response_body) ? ': ' . substr($response_body, 0, 200) : '' );
                    }
                }
            }
        } catch ( Exception $e ) {
            // On exception, don't break the site - just log error and continue
            $report_error = 'Exception: ' . $e->getMessage();
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Maspik False Positive Report Exception: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            }
        } catch ( Error $e ) {
            // On fatal error, don't break the site - just log error and continue
            $report_error = 'Fatal Error: ' . $e->getMessage();
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Maspik False Positive Report Fatal Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            }
        }
    }

    wp_send_json_success(
        array(
            'row_id'       => $row_id,
            'report_sent'  => $report_sent,
            'report_error' => $report_error,
        )
    );
}
add_action('wp_ajax_maspik_not_spam', 'maspik_not_spam');

//Spam log delete functions -- END

//check if table exists
    function maspik_table_exists($rowtocheck = false) {
        static $table_exists = null;     
        static $row_exists = array();    
        
        // First time $table_exists will be null
        if ($table_exists === null) {
            global $wpdb;
            $table_name = $wpdb->prefix . 'maspik_options';
            // Check and save the result
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") == $table_name;
        }
        
        // if the table doesn't exist, return false
        if (!$table_exists) {
            return false;
        }
        
        // if we want to check text_blacklist
        if ($rowtocheck === 'text_blacklist') {
            // if we didn't check this row yet
            if (!isset($row_exists['text_blacklist'])) {
                global $wpdb; 
                $table_name = $wpdb->prefix . 'maspik_options';
                
                // check if the row exists
                $exists = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$table_name} WHERE option_name = %s",
                    'text_blacklist'
                ));
                
                // save the result
                $row_exists['text_blacklist'] = ($exists > 0);
            }
            
            // return the saved result
            return $row_exists['text_blacklist'];
        }
        
        // if we didn't ask to check a specific row, return if the table exists
        return $table_exists;
    }

    function maspik_logtable_exists() {
        static $table_exists = null;
        
        if ($table_exists === null) {
            global $wpdb;
            $table_name = $wpdb->prefix . 'maspik_spam_logs';
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") == $table_name;
        }
        
        return $table_exists;
    }

//check if table exists - END

// Save to DB Function 
    function maspik_save_settings($col_name, $new_value) {
        // check if the values are valid
        if (empty($col_name) || $col_name === '0' || $col_name === 0 ) {
            return ;
        }
        // Check if value is empty (handle both strings and arrays)
        if (is_string($new_value)) {
            if (empty(trim($new_value)) && $new_value !== '0') {
                $new_value = '';
            }
        } elseif (is_array($new_value)) {
            if (empty($new_value)) {
                $new_value = [];
            }
        } elseif (empty($new_value) && $new_value !== 0) {
            $new_value = '';
        }


        global $wpdb;
        $table = maspik_get_dbtable();
        $setting_value = maspik_get_dbvalue();
        $setting_label = maspik_get_dblabel();

            // sanitize the values
        $col_name = sanitize_text_field($col_name);
        
        // Handle different value types for sanitization
        if (is_numeric($new_value)) {
            $new_value = intval($new_value);
        } elseif (is_array($new_value)) {
            // For arrays (like AI logs), we'll store them as JSON
            $new_value = wp_json_encode($new_value);
        } elseif (is_string($new_value)) {
            $new_value = wp_strip_all_tags($new_value);
        }

        // check if the row exists
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE $setting_label = %s",
            $col_name
        ));

        if ($exists) {
            $result = $wpdb->update(
                $table,
                array($setting_value => $new_value),
                array($setting_label => $col_name),
                array('%s'), // always use %s because the value has already been sanitized
                array('%s')
            );
        } else {
            $result = $wpdb->insert(
                $table,
                array(
                    $setting_label => $col_name,
                    $setting_value => $new_value
                ),
                array('%s', '%s')
            );
        }

        return ($result !== false) ? "success" : $wpdb->last_error;
    }
// Save to DB Function - END

//Set DB table variables

    function maspik_get_logtable(){
        global $wpdb;
        
        $table = $wpdb->prefix . 'maspik_spam_logs';
        return $table;
    }

    function maspik_get_dbtable() {
        global $wpdb;
        $table = $wpdb->prefix . 'maspik_options';
        
        // if the table doesn't exist, create it
        if (!maspik_table_exists()) {
            create_maspik_table();
        }
        
        return $table;
    }

    function maspik_get_dbvalue(){
        $setting_value = 'option_value'; //variable for row where values are

        return $setting_value;
    }

    function maspik_get_dblabel(){
        $setting_label = 'option_name'; //variable for column name for setting label

        return $setting_label;
    }
//Set DB table variables - END

//Get data from DB

function maspik_get_settings($data_name, $type = '', $table_var = 'new'){
    if (!maspik_table_exists()) {
        return '';
    }

    global $wpdb;
    if($table_var == 'old'){
        $table = $wpdb->prefix . 'options';
        $setting_label = 'option_name';
        $setting_value = 'option_value';
    } else {
        $table = maspik_get_dbtable();
        $setting_label = maspik_get_dblabel();
        $setting_value = maspik_get_dbvalue();
    }

    $results = $wpdb->get_results(
        $wpdb->prepare("SELECT * FROM $table WHERE $setting_label = %s", $data_name)
    );

    // Check if there are any results
    if ($results) {
        $result = $results[0];
        $value = $result->$setting_value;
    
        if($type == "toggle"){
            return $value == 1 ? 'checked' : '';
        } 
        elseif($type == "form-toggle"){
            if (!$value){
                return 1;
            }
            return $value == 'yes' ? 'yes' : 'no';
        } 
        elseif($type == "select"){
            return $results;
        } 
        else {
            // for everything else
            if ($value === null || $value == '') {
                return '';
            }
            
            // Try to decode JSON if it looks like JSON
            if (is_string($value) && (strpos($value, '{') === 0 || strpos($value, '[') === 0)) {
                $decoded = json_decode($value, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    return $decoded;
                }
            }
            
            return $value;
        }
    }
    
    return null;
}
//Get data from DB - END

// New table management functions

//check if data is in the new table
function maspik_check_table($value) {
    global $wpdb;
    $table_name =$wpdb->prefix . 'maspik_options';

    $column_name = maspik_get_dblabel(); 
    $specific_data = $value;

    $query = $wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE $column_name = %s", $specific_data);
    $count = $wpdb->get_var($query);

    if($count == 0) {
        return false;
    }else{
        return true;
    }
}

//make new main table
function create_maspik_table() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'maspik_options';
    
    // check if the table already exists
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name) {
        return; // the table already exists, no need to create it
    }
    
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        option_name varchar(191) NOT NULL,
        option_value longtext NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);        
}

function maspik_limit_log_size() {
    global $wpdb;

    $max_logs = maspik_get_settings('spam_log_limit') ? maspik_get_settings('spam_log_limit') : 1000;


    $table = maspik_get_logtable();

    // Count the current number of records
    $current_count = $wpdb->get_var("SELECT COUNT(*) FROM $table");

    if ($current_count > $max_logs) {
        // Calculate the number of records to delete
        $entries_to_delete = $current_count - $max_logs + intval($max_logs * 0.1); // 10% more to avoid deleting too many times

        // Delete the oldest records
        $wpdb->query("
            DELETE FROM $table
            ORDER BY id ASC
            LIMIT $entries_to_delete
        ");
    }
}

//Save Error logs to table
function efas_add_to_log($type = '', $input = '', $post = null, $source = "Elementor forms", $spamsrc_name = "", $spamsrc_val = "") {
    $spamcounter = get_option('spamcounter', 0);
    $spamcounter++;
    update_option('spamcounter', $spamcounter);
    
    // Sanitize and escape user inputs
    if (maspik_get_settings("maspik_Store_log") == 'yes') {
        // Check if the post is an array
        if (is_array($post)) {
            // Loop through the post array
            foreach ($post as $key => $value) {
                // Check if the key contains the word password or pass
                if (stripos($key, 'password') !== false || stripos($key, 'pass') !== false) {
                    $post[$key] = '********'; // Replace the password with asterisks
                }
            }
        }
        
        $serialize_data = is_array($post) ? serialize(array_map('sanitize_text_field', $post)) : '';

        $ip = maspik_get_real_ip();
        $countryName = "Other (Unknown)";
        
        $response = wp_remote_get( 'https://free.freeipapi.com/api/json/' . rawurlencode( $ip ) );
        if ( !is_wp_error($response) && wp_remote_retrieve_response_code($response) == 200 ) {
            $body = wp_remote_retrieve_body($response);
            $geoData = json_decode($body, true);
            $asnOrganization = isset($geoData['asnOrganization']) ? $geoData['asnOrganization'] : '';
            if (is_string($asnOrganization) && stripos($asnOrganization, 'cloudflare') !== false) {
                $realCountry = isset($geoData['countryName']) && !empty($geoData['countryName'])
                    ? sanitize_text_field($geoData['countryName'])
                    : 'Unknown';
                $countryName = sprintf('Cloudflare edge (%s)', $realCountry);
            } else {
                if ( isset($geoData['countryName']) && !empty($geoData['countryName']) ) {
                    $countryName = sanitize_text_field($geoData['countryName']);
                }
            }
        }
        
        $spamsrc_val = substr(wp_slash(sanitize_textarea_field($spamsrc_val)), 0, 190);
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
        $browser_name = maspik_get_browser_name($user_agent);
        $date = current_time('mysql'); // timestamp
        $result = maspik_save_log(
            substr(wp_slash(sanitize_text_field($type)), 0, 190),
            substr(wp_slash(sanitize_text_field($input)), 0, 190),
            $serialize_data,
            sanitize_text_field($ip),
            sanitize_text_field($countryName),
            sanitize_text_field($browser_name),
            sanitize_text_field($date),
            substr(wp_slash(sanitize_text_field($source)), 0, 190),
            substr(wp_slash(sanitize_text_field($spamsrc_name)), 0, 190),
            substr(wp_slash(sanitize_text_field($spamsrc_val)), 0, 190)
        );
        
        if ($result !== "success") {
            // Handle the error
            //error_log("Failed to save spam log: " . $result);
        }

    }
}

// Save Error logs to table
function maspik_save_log($type, $value, $detail, $ip, $country, $agent, $date, $source, $spamsrc_name, $spamsrc_val) {
    global $wpdb;
    global $wp;

    if (maspik_logtable_exists()) {

        $table = maspik_get_logtable();
        $url = isset( $_SERVER['HTTP_REFERER'] )
            ? esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) )
            : '';

        $spam_source = $url ? "$source|||$url" : $source;

        $data = array(
            'spam_type'    => $type,
            'spam_value'   => $value,
            'spam_detail'  => $detail,
            'spam_ip'      => $ip,
            'spam_country' => $country,
            'spam_agent'   => $agent,
            'spam_date'    => $date,
            'spam_source'  => $spam_source,
            'spamsrc_label' => $spamsrc_name, 
            'spamsrc_val'  => $spamsrc_val, 
        );

        // Insert data into the database
        $format = array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'); // Format for each field

        $result = $wpdb->insert($table, $data, $format);
   
        if ($result) {
            maspik_limit_log_size();
            return "success";
        } else {
            return $wpdb->last_error;
        }
    }

    return "Table does not exist";
}
//Output current spam count
    function maspik_spam_count(){
        global $wpdb;

        if(maspik_logtable_exists()){

            $table = maspik_get_logtable();

            $sql = "SELECT COUNT(*) AS total FROM $table";
            $result = $wpdb->get_var($sql);
            
            
            return $result;
        }

    }

//Output spam count since install
    function maspik_spam_log_total(){
        global $wpdb;

        if(maspik_logtable_exists()){
            $table = maspik_get_logtable();

            $sql = "SELECT id FROM $table ORDER BY id DESC LIMIT 1";
            $last_id = $wpdb->get_var($sql);
            
            
            return $last_id;
        }
    }
//Save Error logs to table - END


function maspik_Download_log_btn(){
        ?><form method="post" class="downloadform" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <?php wp_nonce_field('maspik_download_csv_action', 'maspik_download_csv_nonce'); ?>
        <input type="hidden" name="action" value="Maspik_spamlog_download_csv">
        <input type="submit" value="Download CSV" class="maspik-btn">
    </form><?php
}


function maspik_get_real_ip() {
	return Maspik_Client_Ip::get_client_ip();
}


// Make Array
function efas_makeArray($string,$type="") {
    if (!$string || is_array($string)) {
        return is_array($string) ? $string : [];
    }
    if ($type = "select") {
        $array  = explode("\n", str_replace("\r", "", $string));
        return  array_filter($array); //removes all null values
    }

    // Split first, then lowercase each item separately
    // This approach is better for non-ASCII characters (e.g., Cyrillic, Arabic)
    // as it preserves encoding better than doing strtolower() on the entire string
    $array = explode("\n", str_replace("\r", "", $string));
    $array = array_map('strtolower', $array);
    return $array;
}

// Check if field value exists in string
function maspik_is_field_value_exist_in_string($bad_string, $field_value, $make_space = 1) {
    // Return false if either string is empty
    if (!$bad_string || !$field_value) {
        return false;
    }

    // Convert both strings to lowercase and trim whitespace
    $bad_string_lower = strtolower(trim($bad_string));
    $field_value_lower = strtolower(trim($field_value));
    
    // If make_space is 1, check for word boundaries and optional punctuation
    if ($make_space == 1) {
        $bad_string_lower = preg_quote($bad_string_lower, '/');
        return preg_match("/(?:^|\s)" . $bad_string_lower . "[.,!?]?(?:$|\s)/i", $field_value_lower);
    }
    
    // Otherwise, check if string exists anywhere in the text
    return strpos($field_value_lower, $bad_string_lower) !== false;
}

// Check if field value is equal to string
function maspik_is_field_value_equal_to_string($string, $field_value) {
    if ($string === "" || $field_value === "") {
        return false;
    }
    $string = trim(strtolower($string));
    $field_value = trim(strtolower($field_value));

    return $string === $field_value ? true : false;
}

function efas_get_spam_api($field = "text_field",$type = "array") {
    $spamapi_option = get_option('spamapi');
    $spamapi_option = is_array($spamapi_option) ? $spamapi_option : array();

    global $MASPIK_SYNC_BOOL_API_TO_LOCAL;
    if ( ! empty( $MASPIK_SYNC_BOOL_API_TO_LOCAL ) && $type === 'bool' && isset( $MASPIK_SYNC_BOOL_API_TO_LOCAL[ $field ] ) ) {
        $local_key = $MASPIK_SYNC_BOOL_API_TO_LOCAL[ $field ];
        $local_val = maspik_get_settings( $local_key );
        $api_val   = false;
        if ( ( maspik_get_settings('private_file_id') || maspik_get_settings("popular_spam") ) && is_array($spamapi_option) && cfes_is_supporting("api") && isset( $spamapi_option[ $field ] ) ) {
            $raw = $spamapi_option[ $field ];
            $api_val = is_array( $raw ) ? $raw[0] : $raw;
        }
        $local_truthy = !empty( $local_val ) && $local_val !== '0' && $local_val !== 0;
        $api_truthy   = !empty( $api_val ) && $api_val !== '0' && $api_val !== 0;
        return $local_truthy || $api_truthy;
    }
    // Support local key for honeypot/time so callers can use maspikHoneypot / maspikTimeCheck (field is value, not API key)
    if ( ! empty( $MASPIK_SYNC_BOOL_API_TO_LOCAL ) && $type === 'bool' && in_array( $field, $MASPIK_SYNC_BOOL_API_TO_LOCAL, true ) && ! isset( $MASPIK_SYNC_BOOL_API_TO_LOCAL[ $field ] ) ) {
        $api_key   = array_search( $field, $MASPIK_SYNC_BOOL_API_TO_LOCAL, true );
        $local_val = maspik_get_settings( $field );
        $api_val   = false;
        if ( ( maspik_get_settings('private_file_id') || maspik_get_settings("popular_spam") ) && is_array($spamapi_option) && cfes_is_supporting("api") && isset( $spamapi_option[ $api_key ] ) ) {
            $raw = $spamapi_option[ $api_key ];
            $api_val = is_array( $raw ) ? $raw[0] : $raw;
        }
        $local_truthy = !empty( $local_val ) && $local_val !== '0' && $local_val !== 0;
        $api_truthy   = !empty( $api_val ) && $api_val !== '0' && $api_val !== 0;
        return $local_truthy || $api_truthy;
    }
   
    if ((!maspik_get_settings('private_file_id') && !maspik_get_settings("popular_spam") ) || !is_array($spamapi_option) || !cfes_is_supporting("api") || !isset($spamapi_option[$field])) {
        return false;
    }

    $api_field = $spamapi_option[$field];

    if ($type != "array") {
            // Keep the field value if it's not an array
            $api_field = is_array($spamapi_option[$field]) ? $spamapi_option[$field][0] : $spamapi_option[$field] ;
            // If the value is 0, return it as a number
            if ($api_field === "0" || $api_field === 0) {
                return 0;
            }
            // Note: We don't use sanitize_text_field here because:
            // 1. Data from WordPress (text_blacklist) doesn't go through sanitize_text_field after retrieval
            // 2. sanitize_text_field can corrupt non-ASCII characters (e.g., Cyrillic, Arabic)
            // 3. Data is displayed with esc_html() in admin, which is sufficient for XSS protection
            // 4. Data is only used for string comparison, not direct output to users
            $clean = trim($api_field);
            return $clean;
    } else {
        // Convert non-array fields to an array using efas_makeArray 
        $api_field = efas_makeArray($spamapi_option[$field],$type);

        // Note: We don't use sanitize_text_field here because:
        // 1. Data from WordPress (text_blacklist) doesn't go through sanitize_text_field after retrieval
        // 2. sanitize_text_field can corrupt non-ASCII characters (e.g., Cyrillic, Arabic)
        //    This causes mismatches when comparing API data vs WordPress data
        // 3. Data is displayed with esc_html() in admin (maspik_spam_api_list), which is sufficient for XSS protection
        // 4. Data is only used for string comparison (maspik_is_field_value_exist_in_string), not direct output
        // 5. WordPress automatically escapes data stored in options table
        // Just trim whitespace to match how WordPress data is processed
        $clean = array_map('trim', $api_field);

        // Remove empty values from Array
        $clean = array_filter($clean, function($value) {
            return !empty($value);
        });

    }

    return $clean ? $clean : false;
}

function maspik_is_contain_api($array) {
    $spamapi_option = get_option('spamapi');
    if ( !is_array($spamapi_option) ||  !cfes_is_supporting("api") || !is_array($array) ) {
        return false;
    }
    // Check if any of the fields in the array are set in the spam API option
    foreach ($array as $field) {
        if (!empty($spamapi_option[$field])) {
            return true; // Found a match, return early
        }
    }
    return false; // No matches found
}

function maspik_detect_language_in_string($langs, $string) {
    if (!is_array($langs) || empty($string)) {
        return '';
    }

    // fix for old versions added in version 2.2.3
    $langs = maybe_unserialize($langs);

    foreach ($langs as $lang) {
        if (preg_match("/$lang/u", $string)) {
            return $lang;
        }
    }
    return '';
}



  
function maspik_is_plugin_active( $plugin ) {
	require_once( ABSPATH . '/wp-admin/includes/plugin.php' );
	return in_array( $plugin, (array) get_option( 'active_plugins', array() ), true ) || is_plugin_active_for_network( $plugin );
}

function efas_array_supports_plugin(){
  $info = cfes_is_supporting("plugin") ? "" : "Pro"; 
  return array(
    'Contact form 7' => 0,
    'Elementor pro' => 0,
    'Hello Plus' => 0,
    'Wordpress Comments' => 0,
    'Wordpress Registration' => 0,
    'Formidable' => 0,
    'Forminator' => 0,
    'Fluentforms' => 0,
    'Bricks' => 0,
    'Ninjaforms'=> 0,
    'Jetforms'=> 0,
    'Everestforms'=> 0,
    'Buddypress' => 0,
    'Custom PHP Forms' => 0,
    'MetForm' => 0,
    'BitForm' => 0,
    'Divi' => 0,
    'Woocommerce Review' => $info,
    'Woocommerce Registration' => $info,
    'Woocommerce Orders' => $info,
    'Wpforms' => $info,
    'Gravityforms' => $info,
  );
} 

function maspik_proform_togglecheck($plugin){
    
    foreach ( efas_array_supports_plugin() as $key => $value) {
    
        if($key == $plugin){
            //echo $key;
            if($value == "Pro"){  
                return 0;
            }else{
                return 1;
            } 
        }
    }

    
}

// Check if WooCommerce support is enabled
function maspik_if_woo_support_is_enabled() {
    return cfes_is_supporting("plugin") && class_exists('WooCommerce') && maspik_get_settings("maspik_support_Woocommerce_registration") != "no";
}



function maspik_if_plugin_is_active($plugin) {
    global $MASPIK_PLUGIN_MAP;
    
    if (!isset($MASPIK_PLUGIN_MAP[$plugin])) {
        return 0;
    }
    
    if ($plugin === 'Wordpress Comments') {
        return 1;
    }
    
    return efas_if_plugin_is_active($MASPIK_PLUGIN_MAP[$plugin]);
}

function efas_if_plugin_is_affective($plugin , $status = "no"){
	if($plugin == 'Elementor pro'){
      return efas_if_plugin_is_active('elementor-pro') && maspik_get_settings( "maspik_support_Elementor_forms", 'form-toggle' ) != $status ;
    }else if($plugin == 'Contact form 7'){
      return  efas_if_plugin_is_active('contact-form-7') && maspik_get_settings( "maspik_support_cf7", 'form-toggle' ) != $status ;
    }else if($plugin == 'Hello Plus'){
      return efas_if_plugin_is_active('hello-plus') && maspik_get_settings( "maspik_support_helloplus_forms", 'form-toggle' ) != $status ;
    }else if($plugin == 'Buddypress'){
      return efas_if_plugin_is_active('buddypress') && maspik_get_settings( "maspik_support_buddypress_forms", 'form-toggle' ) != $status ;
    }else if($plugin == 'Woocommerce Review'){
      return efas_if_plugin_is_active('woocommerce') && cfes_is_supporting("plugin") && maspik_get_settings( "maspik_support_woocommerce_review", 'form-toggle' ) != $status ;
    }else if($plugin == 'Woocommerce Registration'){
      return efas_if_plugin_is_active('woocommerce') && cfes_is_supporting("plugin") && maspik_get_settings( "maspik_support_Woocommerce_registration", 'form-toggle' ) != $status;
    }else if($plugin == 'Woocommerce Orders'){
      return efas_if_plugin_is_active('woocommerce') && cfes_is_supporting("plugin") && maspik_get_settings( "maspik_support_woocommerce_orders", 'form-toggle' ) != $status;
    }else if($plugin == 'Wpforms'){
      return  efas_if_plugin_is_active('wpforms') && cfes_is_supporting("plugin") && maspik_get_settings( "maspik_support_Wpforms", 'form-toggle' ) != $status  ;
    }else if($plugin == 'Gravityforms'){
      return efas_if_plugin_is_active('gravityforms') && cfes_is_supporting("plugin") && maspik_get_settings( "maspik_support_gravity_forms", 'form-toggle' ) != $status ;
    }else if($plugin == 'Formidable'){
      return efas_if_plugin_is_active('formidable')  && maspik_get_settings( "maspik_support_formidable_forms", 'form-toggle' ) != $status ;
    }else if($plugin == 'Fluentforms'){
      return efas_if_plugin_is_active('fluentforms')  && maspik_get_settings( "maspik_support_fluentforms_forms", 'form-toggle' ) != $status ;
    }else if($plugin == 'Bricks'){
      return efas_if_plugin_is_active('bricks')  && maspik_get_settings( "maspik_support_bricks_forms", 'form-toggle' ) != $status ;
    }else if($plugin == 'Breakdance'){
      return efas_if_plugin_is_active('breakdance')  && maspik_get_settings( "maspik_support_breakdance_forms", 'form-toggle' ) != $status ;
    }else if($plugin == 'Forminator'){
      return efas_if_plugin_is_active('forminator')  && maspik_get_settings( "maspik_support_forminator_forms", 'form-toggle' ) != $status ;
    }else if($plugin == 'Wordpress Registration'){
      return efas_if_plugin_is_active('Wordpress Registration') && maspik_get_settings( "maspik_support_registration", 'form-toggle' ) != $status ;
    }else if($plugin == 'Ninjaforms'){
        return efas_if_plugin_is_active('ninjaforms') && maspik_get_settings( "maspik_support_ninjaforms", 'form-toggle' ) != $status ;
    }else if($plugin == 'Jetforms'){
        return efas_if_plugin_is_active('jetforms') && maspik_get_settings( "maspik_support_jetforms", 'form-toggle' ) != $status ;
    }else if($plugin == 'Everestforms'){
        return efas_if_plugin_is_active('everestforms') && maspik_get_settings( "maspik_support_everestforms", 'form-toggle' ) != $status ;
    }else if($plugin == 'MetForm'){
        return efas_if_plugin_is_active('metform') && maspik_get_settings( "maspik_support_metform_forms", 'form-toggle' ) != $status ;
    }else if($plugin == 'BitForm'){
        return efas_if_plugin_is_active('bitform') && maspik_get_settings( "maspik_support_bitform_forms", 'form-toggle' ) != $status ;
    }else if($plugin == 'Divi'){
        return efas_if_plugin_is_active('divi') && maspik_get_settings( "maspik_support_divi_forms", 'form-toggle' ) != $status ;
    }else if($plugin == 'Wordpress Comments'){
      return maspik_get_settings( "maspik_support_wp_comment", 'form-toggle' ) != $status ;
    }else if($plugin == 'Custom PHP Forms'){
      return maspik_get_settings( "maspik_support_custom_forms", 'form-toggle' ) == "yes" ;
    }else{
      return 1;
    }
}

function efas_if_plugin_is_active($plugin){
	if($plugin == 'elementor-pro'){
      return class_exists( '\ElementorPro\Plugin' );
    }else if($plugin == 'contact-form-7'){
      return maspik_is_plugin_active( 'contact-form-7/wp-contact-form-7.php' );
    }else if($plugin == 'hello-plus'){
      return maspik_is_plugin_active( 'hello-plus/hello-plus.php' );
    }else if($plugin == 'woocommerce'){
      return maspik_is_plugin_active( 'woocommerce/woocommerce.php');
    }else if($plugin == 'buddypress'){
      return maspik_is_plugin_active( 'buddypress/bp-loader.php');
    }else if($plugin == 'wpforms'){
	  return ( maspik_is_plugin_active('wpforms-lite/wpforms.php') || maspik_is_plugin_active('wpforms/wpforms.php') );
    }else if($plugin == 'gravityforms'){
      return maspik_is_plugin_active('gravityforms/gravityforms.php');
    }else if($plugin == 'forminator'){
      return maspik_is_plugin_active('forminator/forminator.php');
    }else if($plugin == 'formidable'){
      return maspik_is_plugin_active('formidable/formidable.php');
    }else if($plugin == 'bricks'){
      return maspik_if_bricks_exist();
    }else if($plugin == 'fluentforms'){
      return maspik_is_plugin_active('fluentform/fluentform.php');
    }else if($plugin == 'ninjaforms'){
        return maspik_is_plugin_active('ninja-forms/ninja-forms.php');
    }else if($plugin == 'jetforms'){
        return maspik_is_plugin_active('jetformbuilder/jet-form-builder.php');
    }else if($plugin == 'everestforms'){
        return maspik_is_plugin_active('everest-forms/everest-forms.php');
    }else if($plugin == 'Wordpress Registration'){
      return get_option('users_can_register') == 1;
    }else if($plugin == 'bitform'){
        return maspik_is_plugin_active('bit-form/bitforms.php');
    }else if($plugin == 'metform'){
        return maspik_is_plugin_active('metform/metform.php');
    }else if($plugin == 'breakdance'){
        return maspik_is_plugin_active('breakdance/plugin.php');
    }else if($plugin == 'divi'){
        return function_exists( 'maspik_is_divi_active' ) && maspik_is_divi_active();
    }
    else{
      return 1;
    }
}

//Display only on maspik pages
function maspik_is_maspik_page() {
    // Check if we're in admin and if the page parameter contains 'maspik'
    if (!is_admin() || !isset($_GET['page'])) {
        return;
    }

    // Check if we're on a Maspik page
    if (strpos($_GET['page'], 'maspik') !== false) {
        // Hide all admin notices
        global $wp_filter;
        remove_all_actions('user_admin_notices');
        remove_all_actions('admin_notices');
        if (isset($wp_filter['admin_notices'])) {
            // Remove all actions hooked to the 'admin_notices' hook
            unset($wp_filter['admin_notices']);
        }

        // Change the footer text
        add_filter('admin_footer_text', 'maspik_change_footer_admin');
        
        // Add script to footer admin to open external links in new tab
        add_action('admin_footer', function() {
            ?>
            <script>
            // Open external links in new tab By Maspik 
            document.addEventListener('DOMContentLoaded', function() {
                var links = document.querySelectorAll('#toplevel_page_maspik li a');
                for (var i = 0; i < links.length; i++) {
                    if (links[i].href.startsWith('https://') && !links[i].href.includes(window.location.hostname)) {
                        links[i].target = '_blank';
                        if(links[i].href.includes('upgrade')) {
                            links[i].style.color = '#f48623';
                            links[i].style.fontWeight = 'bold';
                        }
                    }
                }
            });
            </script>
            <?php
        });
    }
}
add_action('admin_init', 'maspik_is_maspik_page', 99999);

function maspik_change_footer_admin () {
    echo '<p id="footer-left" class="alignleft">
		<strong>Maspik</strong> is helping you block spam? Please leave us a <a href="https://wordpress.org/support/plugin/contact-forms-anti-spam/reviews/#new-post" target="_blank">★★★★★</a> rating. We really appreciate your support!</p>';
}


function mergePerKey($array1, $array2) {
    $result = array();

    foreach ($array1 as $key => $value) {
        $value = is_array($value) ? $value : efas_makeArray($value);

        if (array_key_exists($key, $array2)) {
            $value2 = efas_makeArray($array2[$key]);
            $mergedValues = array_merge($value, $value2);
            $uniqueValues = array_unique($mergedValues);
            $result[$key] = $uniqueValues;
        }
    }

    return $result;
}

// Schedule an action if it's not already scheduled
if ( ! wp_next_scheduled( 'cfas_daily_api_refresh' ) ) {
 wp_schedule_event( time(), 'daily', 'cfas_daily_api_refresh' );
}
// Hook into that action that'll fire daily
add_action( 'cfas_daily_api_refresh', 'cfas_refresh_api' );


function cfes_is_supporting($type = "") {

	if ( function_exists( 'maspik_license_checker' ) ) {
		try {
			if ( maspik_license_checker()->license()->isLicenseValid() ) {
				return 1;
			}
		} catch ( \Exception $e ) {
			//error_log( 'Error happened: ' . $e->getMessage() );
		}
	}

	return 0;
}
add_action('after_setup_theme', 'cfes_is_supporting');

function cfas_refresh_api($type = 'regular') {
    if (!cfes_is_supporting("api")) {
        return;
    }

    $private_file_id = (int)maspik_get_settings('private_file_id');
    $domain = isset($_SERVER['SERVER_NAME']) ? sanitize_text_field(wp_unslash($_SERVER['SERVER_NAME'])) : '';

    // Initialize $file as an empty array
    $file = array();

    // Check if the first API is available and fetch data
    if (!empty($private_file_id)) {
        $Api_file = "https://wpmaspik.com/wp-json/acf/v3/apis/$private_file_id";
        
        $response = wp_remote_get($Api_file);
        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
            $content = wp_remote_retrieve_body($response);
            $file = json_decode($content, true);
            $file = $file['acf'] ?? array();
        }
    }

    // Initialize $combinedAPI with the data from the first API
    $combinedAPI = $file;

    // Check if the second API should be accessed
    $popular_spam = maspik_get_settings("popular_spam"); 
    if ($popular_spam) {
        $Api_popular_spam_file = "https://wpmaspik.com/wp-json/acf/v3/options/public_api?num=234442&site=$domain";
        
        $response = wp_remote_get($Api_popular_spam_file);
        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
            $popularSpamContent = wp_remote_retrieve_body($response);
            $popularSpamFile = json_decode($popularSpamContent, true);
            $popularSpamFile = $popularSpamFile['acf'] ?? array();

            // Combine "text_field", "email_field", "textarea_field", and "contain_links" values from both APIs
            if (isset($popularSpamFile['text_field'])) {
                if (isset($combinedAPI['text_field'])) {
                    $combinedAPI['text_field'] .= "\r\n" . $popularSpamFile['text_field'];
                } else {
                    $combinedAPI['text_field'] = $popularSpamFile['text_field'];
                }
            }
            if (isset($popularSpamFile['email_field'])) {
                if (isset($combinedAPI['email_field'])) {
                    $combinedAPI['email_field'] .= "\r\n" . $popularSpamFile['email_field'];
                } else {
                    $combinedAPI['email_field'] = $popularSpamFile['email_field'];
                }
            }
            if (isset($popularSpamFile['textarea_field'])) {
                if (isset($combinedAPI['textarea_field'])) {
                    $combinedAPI['textarea_field'] .= "\r\n" . $popularSpamFile['textarea_field'];
                } else {
                    $combinedAPI['textarea_field'] = $popularSpamFile['textarea_field'];
                }
            }
            if (isset($popularSpamFile['contain_links'])) {
                $combinedAPI['contain_links'] = $combinedAPI['contain_links'] ?? $popularSpamFile['contain_links'];
            }
        }
    }

    // Update your option with the combined API result
    $previousAPI = get_option('spamapi') ?? array();
    $newAPI = $combinedAPI;
    
    if ($newAPI == $previousAPI) {
        if ($type == 'regular') {
            echo "<script>alert('You have the most new version already.');</script>";
        }
    } else {
        update_option('spamapi', $newAPI); 
        if ($type == 'regular') {
            echo "<script>alert('New version applied successfully.');</script>";
        }
    }
}

// Get the toggle match
function maspik_toggle_match($data) {
    global $MASPIK_TOGGLE_MAP;
    return isset($MASPIK_TOGGLE_MAP[$data]) ? $MASPIK_TOGGLE_MAP[$data] : '';
}



function cfas_get_error_text($field = "error_message") {
    $default_text = esc_html__('This looks like spam. Try to rephrase, or contact us in an alternative way.', 'contact-forms-anti-spam');

    // Fetch texts in the order of priority
    $textAPI_specific = efas_get_spam_api("custom_error_message_$field", "text");
    $textAPI_general = efas_get_spam_api('error_message', "text");
    $text_general = maspik_get_settings("error_message");

    // Check if specific field has a toggle enabled and fetch the appropriate text
    if (maspik_check_table("custom_error_message_$field") && maspik_get_settings(maspik_toggle_match($field)) == 1) {
        $text_specific = maspik_get_settings("custom_error_message_$field");
    } else {
        $text_specific = null;
    }

    // Determine the text to use based on the order of priority
    $text = $text_specific ? $text_specific : ($textAPI_specific ? $textAPI_specific : ($text_general ? $text_general : ($textAPI_general ? $textAPI_general : $default_text)));

    return sanitize_text_field($text);
}

function get_maspik_footer(){
    ?> 
    <footer class="maspik-footer">
        <h3><?php esc_html_e('Is Maspik helping you block spam?', 'contact-forms-anti-spam'); ?></h3>
        <p><?php echo esc_html__('We would be incredibly grateful if you could ', 'contact-forms-anti-spam') . '<a href="https://wordpress.org/support/plugin/contact-forms-anti-spam/reviews/#new-post" target="_blank">' . esc_html__('leave us a 5-star review', 'contact-forms-anti-spam') . '</a>. ' . esc_html__('Your feedback not only helps others discover our plugin but also fuels our passion to keep enhancing it. It helps us grow and continue improving. Thank you for your support!', 'contact-forms-anti-spam'); ?></p>
        <h4><?php esc_html_e('Join Our Facebook Community!', 'contact-forms-anti-spam'); ?></h4>
        <p><?php echo esc_html__('Ask questions, share spam examples, get ideas on how to block them, share feedback, and suggest new features. Join us at ', 'contact-forms-anti-spam') . '<a href="https://www.facebook.com/groups/maspik" target="_blank">' . esc_html__('WP Maspik Community - Stopping Spam Together', 'contact-forms-anti-spam') . '</a>.'; ?></p>
        <h4><?php esc_html_e('Contact Us', 'contact-forms-anti-spam'); ?></h4>
        <p><?php esc_html_e('Need support? Do you have ideas on how to improve MASPIK?', 'contact-forms-anti-spam'); ?><br>
        <?php echo esc_html__('We would love to hear from you at', 'contact-forms-anti-spam') . ' <a href="mailto:hello@wpmaspik.com" target="_blank">hello@wpmaspik.com</a>'; ?></p>

    </footer>
    <?php
}

add_filter( 'admin_body_class', 'cfas_admin_classes' );
function cfas_admin_classes( $classes ) {
      $screen = get_current_screen()->id;
    if ( strpos($screen, 'maspik') !== false ){
        $classes .=  cfes_is_supporting() ? " maspik-pro " : false;
    }
    return $classes;
}

//AbuseIPDB (Thanks to @josephcy95)
function check_abuseipdb($ip){
  $apikey = maspik_get_settings( 'abuseipdb_api' );
  // By Default use RapidAPI
  $apiEndpoint = 'https://api.abuseipdb.com/api/v2/check?ipAddress=' . rawurlencode( $ip ) . '&maxAgeInDays=90';
  $headers = array(
    'content-type' => 'application/json',
    'accept' => 'application/json',
    'Key' => $apikey
  );

  $args = array(
    'headers' => $headers,
    'timeout' => 20
  );

  $jsonreply = wp_remote_get($apiEndpoint, $args);
  $jsonreply = wp_remote_retrieve_body($jsonreply);
  $jsonreply = json_decode($jsonreply, TRUE);

  return isset( $jsonreply["data"]["abuseConfidenceScore"] ) ? (int)$jsonreply["data"]["abuseConfidenceScore"] : false ;
}

function check_proxycheckio($ip){
    $apikey = maspik_get_settings('proxycheck_io_api');

    // By Default use RapidAPI
    $apiEndpoint = 'https://proxycheck.io/v2/' . rawurlencode( $ip ) . '?key=' . rawurlencode( $apikey ) . '&risk=1&vpn=1';
    $headers = array(
        'content-type' => 'application/json',
        'accept' => 'application/json',
        'Key' => $apikey
    );

    $args = array(
        'headers' => $headers,
        'timeout' => 20
    );

    $jsonreply = wp_remote_get($apiEndpoint, $args);

    // Check if $jsonreply is not null and is a successful response
    if (!is_wp_error($jsonreply) && wp_remote_retrieve_response_code($jsonreply) === 200) {
        $jsonreply = wp_remote_retrieve_body($jsonreply);
        $jsonreply = json_decode($jsonreply, TRUE);

        // Check if $jsonreply is not null and if the IP address exists as a key
        if ($jsonreply !== null && isset($jsonreply[$ip])) {
            return (int)$jsonreply[$ip]["risk"];
        }
    }

    // Return a default risk value or handle the case where the response is not as expected
    return -1;
} 

function cidr_match($ip, $cidr){
    $cidr_parts = explode('/', $cidr);

    // Check if $cidr_parts contains at least two elements
    if (count($cidr_parts) < 2) {
        // Handle the case where $cidr is not in the expected format
        return false;
    }

    list($subnet, $bits) = $cidr_parts;

    if ($bits === null) {
        $bits = 32;
    }
    $ip = ip2long($ip);
    $subnet = ip2long($subnet);
    $mask = -1 << (32 - $bits);
    $subnet &= $mask; # nb: in case the supplied subnet wasn't correctly aligned
    return ($ip & $mask) == $subnet;
} 

function ip_is_cidr($ip) {
    // CIDR notation validation pattern
    $pattern = '/^(\d{1,3}\.){3}\d{1,3}(\/(\d|[1-2]\d|3[0-2]))?$/';
    return preg_match($pattern, $ip) ? $ip : false;
}



function Maspik_admin_notice() {
    // Check if the user has 'manage_options' capability
    if (!current_user_can('manage_options')) {
        return;
    }

    // Check if the notice has been dismissed
    if (!get_transient('Mapik_dismissed_shereing_notice') && (maspik_get_settings('shere_data') == "")) {
        
        ?>
        <div class="notice notice-warning is-dismissible">
            <p>
                <?php esc_html_e('Maspik: Help us improve spam blocking! Please allow us to collect non-sensitive information.', 'contact-forms-anti-spam'); ?>
                <button id="allow-sharing-button" class="button button-primary"> <?php esc_html_e('of course!', 'contact-forms-anti-spam'); ?></button>
            </p>
        </div>
        <script>
            jQuery(document).ready(function($) {
                $('#allow-sharing-button').on('click', function(e) {
                    e.preventDefault();
                    // AJAX call to update wp_options upon button click
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'Maspik_allow_sharing_action',
                            allow_sharing: true,
                            security: '<?php echo wp_create_nonce("maspik_allow_sharing_nonce"); ?>',
                        },
                        success: function(response) {
                            // Reload the page or perform any other action
                            location.reload();
                        },
                        error: function(error) {
                            // Error handled silently
                        }
                    });
                });

                // Dismiss notice on close button click
                $('.notice.is-dismissible').on('click', '.notice-dismiss', function() {
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'Maspik_dismiss_notice_action',
                            nonce: '<?php echo esc_js( wp_create_nonce( 'maspik_dismiss_notice' ) ); ?>'
                        },
                        success: function(response) {
                            // Hide the notice
                            $('.notice.is-dismissible').remove();
                        },
                        error: function(error) {
                            // Error handled silently
                        }
                    });
                });
            });
        </script>
        <?php
    }else{
        
    }
}
add_action('admin_notices', 'Maspik_admin_notice');

// AJAX callback function to update wp_options for allowing sharing
add_action('wp_ajax_Maspik_allow_sharing_action', 'Maspik_allow_sharing_callback');
function Maspik_allow_sharing_callback() {
    // Check nonce
    check_ajax_referer('maspik_allow_sharing_nonce', 'security');

    // Check user capabilities
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('Permission error', 'contact-forms-anti-spam'));
    }

    // Update option
    //update_option('shere_data', 1);
    maspik_save_settings('shere_data', 1);
    

    wp_die(); // Always use wp_die() at the end of an AJAX callback
}

// AJAX callback function to dismiss the notice
add_action('wp_ajax_Maspik_dismiss_notice_action', 'Maspik_dismiss_notice_callback');
function Maspik_dismiss_notice_callback() {
    check_ajax_referer( 'maspik_dismiss_notice', 'nonce' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( -1 );
    }

    set_transient( 'Mapik_dismissed_shereing_notice', true, MONTH_IN_SECONDS );
    wp_die();
}

// Maspik if bricks exist
add_action('init', 'maspik_if_bricks_exist');
function maspik_if_bricks_exist(){
  $theme = wp_get_theme( get_template() );
  $theme_name = $theme['Name'];
  return $theme_name === 'Bricks';
}

/**
 * Whether Divi Builder / theme is available (Contact Form module).
 *
 * @return bool
 */
function maspik_is_divi_active() {
	if ( defined( 'ET_BUILDER_VERSION' ) ) {
		return true;
	}
	if ( function_exists( 'wp_get_theme' ) ) {
		$theme = wp_get_theme();
		if ( $theme && $theme->exists() ) {
			$template = strtolower( (string) $theme->get_template() );
			$stylesheet = strtolower( (string) $theme->get_stylesheet() );
			if ( in_array( 'divi', array( $template, $stylesheet ), true ) ) {
				return true;
			}
			$parent = $theme->parent();
			if ( $parent && $parent->exists() ) {
				$pt = strtolower( (string) $parent->get_template() );
				if ( 'divi' === $pt ) {
					return true;
				}
			}
		}
	}
	return maspik_is_plugin_active( 'divi-builder/divi-builder.php' );
}


//
// Handle export settings
add_action('admin_post_Maspik_export_settings', 'Maspik_export_settings');

function Maspik_export_settings() {
    // Check nonce
    if (!isset($_POST['Maspik_export_settings_nonce_field']) || !wp_verify_nonce($_POST['Maspik_export_settings_nonce_field'], 'Maspik_export_settings_nonce')) {
        wp_die(
            esc_html( __( 'Security check failed.', 'contact-forms-anti-spam' ) ),
            esc_html( __( 'Export', 'contact-forms-anti-spam' ) ),
            array( 'response' => 403 )
        );
    }
    
    // Check if user has permission to access admin area
    if (!current_user_can('manage_options')) {
        wp_die(
            esc_html( __( 'You do not have sufficient permissions to access this page.', 'contact-forms-anti-spam' ) ),
            esc_html( __( 'Export', 'contact-forms-anti-spam' ) ),
            array( 'response' => 403 )
        );
    }

    // Get Maspik settings
    global $wpdb;
    $table_name = $wpdb->prefix . 'maspik_options';
    
    // Fetch all settings from the database
    $results = $wpdb->get_results("SELECT * FROM $table_name", ARRAY_A);
    
    // Initialize $maspik_settings array
    $maspik_settings = array();
    
    // Populate $maspik_settings with all data from the table.
    // Do not use sanitize_text_field() on values: it strips newlines (breaks line-based blacklists on import).
    foreach ($results as $setting) {
        $option_name = sanitize_text_field($setting['option_name']);
        if ($option_name === '') {
            continue;
        }
        if ( 'maspik_ai_logs' === $option_name || 'maspik_ai_client_secret' === $option_name ) {
            continue;
        }
        $maspik_settings[ $option_name ] = isset( $setting['option_value'] ) ? $setting['option_value'] : '';
    }
    
    // Add system information directly to the $maspik_settings array
    $maspik_settings['wordpress_version'] = get_bloginfo('version');
    $maspik_settings['plugin_version'] = MASPIK_VERSION; 
    $maspik_settings['wordpress_language'] = get_bloginfo('language');
    $maspik_settings['php_version'] = phpversion();
    $maspik_settings['theme_name'] = wp_get_theme()->get('Name');
    $maspik_settings['spamcounter'] = get_option('spamcounter');
    $maspik_settings['shere_data'] = get_option('shere_data');
    $maspik_settings['maspik_api_requests'] = get_option('maspik_api_requests');
   
    // Get domain name of the site
    $domain_name = get_site_url();

    // Line 1: plugin version marker (replacing legacy static string) for reliable import validation.
    $export_header_line = MASPIK_VERSION;

    // Convert settings array to JSON
    $json_data = wp_json_encode($maspik_settings);

    $exported_data = $export_header_line . "\n\n" . $domain_name . "\n\n" . $json_data;

    nocache_headers();

    // Set headers for file download
    header('Content-Description: File Transfer');
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="maspik-settings.json"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . strlen($exported_data));

    // Output JSON data
    echo $exported_data;
    exit;
}

/**
 * Option keys whose stored values may contain line breaks (blacklists, messages, context).
 * These must be sanitized with sanitize_textarea_field on import, not sanitize_text_field.
 *
 * @return string[]
 */
function maspik_import_multiline_option_keys() {
    return array(
        'text_blacklist',
        'emails_blacklist',
        'url_blacklist',
        'ip_blacklist',
        'tel_formats',
        'maspik_ai_context',
        'custom_error_message_MaxCharactersInTextField',
        'custom_error_message_MaxCharactersInTextAreaField',
        'custom_error_message_emoji_check',
        'custom_error_message_MaxCharactersInPhoneField',
        'custom_error_message_tel_formats',
        'custom_error_message_lang_needed',
        'custom_error_message_lang_forbidden',
        'custom_error_message_country_blacklist',
        'error_message',
        'maspik_woo_orders_error_message',
    );
}

/**
 * Normalize and sanitize one setting value from an export file before saving.
 *
 * @param string $option_key Option name.
 * @param mixed  $value      Raw value from JSON decode.
 * @return mixed Sanitized value for maspik_save_settings().
 */
function maspik_sanitize_import_setting_value( $option_key, $value ) {
    if ( is_array( $value ) ) {
        return array_map( 'sanitize_text_field', wp_unslash( $value ) );
    }
    if ( is_bool( $value ) ) {
        return $value ? 1 : 0;
    }
    if ( is_int( $value ) || is_float( $value ) ) {
        return $value;
    }

    $value = wp_unslash( (string) $value );

    static $multiline_keys_flip = null;
    if ( null === $multiline_keys_flip ) {
        $multiline_keys_flip = array_flip( maspik_import_multiline_option_keys() );
    }

    // Legacy exports replaced newlines with ",,," before sanitizing; restore for multiline fields.
    if ( isset( $multiline_keys_flip[ $option_key ] ) ) {
        $value = str_replace( ',,,', "\n", $value );
        return sanitize_textarea_field( $value );
    }

    return sanitize_text_field( $value );
}

/** Legacy export file marker (pre–2.8.0 header line). */
function maspik_import_legacy_export_header_marker() {
    return 'OnlyYouKnowWhatIsGoodForYou';
}

/**
 * Minimal Maspik version required so export/import preserves line-based lists and all options correctly.
 *
 * @return string Semver fragment, e.g. '2.8.0'.
 */
function maspik_import_minimum_export_plugin_version() {
    return '2.8.0';
}

/**
 * Extract comparable semver prefixes from export header line and decoded JSON payload.
 *
 * @param string               $header_line     First line of the export file (trim applied inside).
 * @param array<string, mixed>|null $settings_array Decoded JSON object.
 * @return array{0:string, 1:string} From header (or ''), from plugin_version key (or '').
 */
function maspik_import_parse_export_versions( $header_line, $settings_array ) {
    $header_line = trim( (string) $header_line );
    $from_header = '';
    if ( preg_match( '/^(\d+(?:\.\d+){0,3})/', $header_line, $m ) ) {
        $from_header = $m[1];
    }
    $from_json = '';
    if ( is_array( $settings_array ) && ! empty( $settings_array['plugin_version'] ) ) {
        $pv = trim( (string) $settings_array['plugin_version'] );
        if ( preg_match( '/^(\d+(?:\.\d+){0,3})/', $pv, $m ) ) {
            $from_json = $m[1];
        }
    }
    return array( $from_header, $from_json );
}

/**
 * Max upload size for settings import (DoS / memory guard). JSON exports are typically tiny.
 *
 * @return int Bytes.
 */
function maspik_settings_import_max_bytes() {
    return defined( 'MB_IN_BYTES' ) ? ( 2 * MB_IN_BYTES ) : 2097152;
}

/**
 * JSON decode max depth for imports (stack / complexity guard).
 *
 * @return int Positive integer.
 */
function maspik_settings_import_json_max_depth() {
    return 64;
}

/**
 * Export file gate: structural / version checks with no side effects.
 *
 * @param string               $header_line     First segment of split file (line 1).
 * @param array<string, mixed> $settings_array Decoded JSON object.
 * @return 'allow'|'reject_deprecated'|'invalid'
 */
function maspik_import_export_file_gate( $header_line, array $settings_array ) {
    $header_line = trim( (string) $header_line );

    if ( $header_line === maspik_import_legacy_export_header_marker() ) {
        return 'reject_deprecated';
    }

    list( $vh, $vj ) = maspik_import_parse_export_versions( $header_line, $settings_array );

    if ( $vh === '' && $vj === '' ) {
        return 'invalid';
    }

    $min_found = '';
    foreach ( array( $vh, $vj ) as $cand ) {
        if ( $cand === '' ) {
            continue;
        }
        if ( $min_found === '' || version_compare( $cand, $min_found, '<' ) ) {
            $min_found = $cand;
        }
    }

    if ( $min_found === '' ) {
        return 'invalid';
    }

    if ( version_compare( $min_found, maspik_import_minimum_export_plugin_version(), '<' ) ) {
        return 'reject_deprecated';
    }

    return 'allow';
}

// Handle import settings
add_action('admin_post_Maspik_import_settings', 'Maspik_import_settings');

function Maspik_import_settings() {
    // Check nonce
    if (!isset($_POST['Maspik_import_settings_nonce_field']) || !wp_verify_nonce($_POST['Maspik_import_settings_nonce_field'], 'Maspik_import_settings_nonce')) {
        wp_die(
            esc_html( __( 'Security check failed.', 'contact-forms-anti-spam' ) ),
            esc_html( __( 'Import', 'contact-forms-anti-spam' ) ),
            array( 'response' => 403 )
        );
    }
    
    // Check if user has permission to access admin area
    if (!current_user_can('manage_options')) {
        wp_die(
            esc_html( __( 'You do not have sufficient permissions to access this page.', 'contact-forms-anti-spam' ) ),
            esc_html( __( 'Import', 'contact-forms-anti-spam' ) ),
            array( 'response' => 403 )
        );
    }

    // Check if a file was uploaded
    if (!isset($_FILES['maspik-settings']) || $_FILES['maspik-settings']['error'] !== UPLOAD_ERR_OK) {
        wp_die(
            esc_html( __( 'Invalid file upload.', 'contact-forms-anti-spam' ) ),
            esc_html( __( 'Import', 'contact-forms-anti-spam' ) ),
            array( 'response' => 400 )
        );
    }

    $uploaded_file = $_FILES['maspik-settings'];

    // Extension from client-supplied filename (cheap first check).
    $upload_extension = strtolower( pathinfo( $uploaded_file['name'], PATHINFO_EXTENSION ) );
    if ( 'json' !== $upload_extension ) {
        wp_die(
            esc_html( __( 'Invalid file type.', 'contact-forms-anti-spam' ) ),
            esc_html( __( 'Import', 'contact-forms-anti-spam' ) ),
            array( 'response' => 400 )
        );
    }

    $mime_mimes       = array( 'json' => 'application/json' );
    $checked_filetype = wp_check_filetype_and_ext( $uploaded_file['tmp_name'], $uploaded_file['name'], $mime_mimes );
    if ( empty( $checked_filetype['ext'] ) || strtolower( $checked_filetype['ext'] ) !== 'json' ) {
        wp_die(
            esc_html( __( 'Invalid file type.', 'contact-forms-anti-spam' ) ),
            esc_html( __( 'Import', 'contact-forms-anti-spam' ) ),
            array( 'response' => 400 )
        );
    }

    $tmp_path = $uploaded_file['tmp_name'];
    $fsize    = filesize( $tmp_path );
    if ( false === $fsize || $fsize < 1 || $fsize > maspik_settings_import_max_bytes() ) {
        wp_die(
            esc_html( __( 'The uploaded file is empty or too large.', 'contact-forms-anti-spam' ) ),
            esc_html( __( 'Import', 'contact-forms-anti-spam' ) ),
            array( 'response' => 400 )
        );
    }

    // Read file (bounded size above).
    $json_data = file_get_contents( $tmp_path );
    if ( false === $json_data ) {
        wp_die(
            esc_html( __( 'Could not read the uploaded file.', 'contact-forms-anti-spam' ) ),
            esc_html( __( 'Import', 'contact-forms-anti-spam' ) ),
            array( 'response' => 400 )
        );
    }

    // Separate header, source URL, JSON payload.
    $parts = explode( "\n\n", $json_data, 3 );
    if ( count( $parts ) !== 3 ) {
        wp_die(
            esc_html( __( 'Invalid file format.', 'contact-forms-anti-spam' ) ),
            esc_html( __( 'Import', 'contact-forms-anti-spam' ) ),
            array( 'response' => 400 )
        );
    }

    // Line 1: legacy static marker OR plugin version (2.8.0+). Line 2: source site URL (informational). Line 3: JSON.
    $header_line  = $parts[0];
    $payload_part = $parts[2];
    unset( $json_data );

    $maspik_settings = json_decode( $payload_part, true, maspik_settings_import_json_max_depth() );

    if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $maspik_settings ) ) {
        wp_die(
            esc_html( __( 'Invalid JSON data.', 'contact-forms-anti-spam' ) ),
            esc_html( __( 'Import', 'contact-forms-anti-spam' ) ),
            array( 'response' => 400 )
        );
    }

    $gate = maspik_import_export_file_gate( $header_line, $maspik_settings );
    if ( 'invalid' === $gate ) {
        wp_die(
            esc_html( __( 'This file is not a valid Maspik settings export.', 'contact-forms-anti-spam' ) ),
            esc_html( __( 'Import', 'contact-forms-anti-spam' ) ),
            array( 'response' => 400 )
        );
    }
    if ( 'reject_deprecated' === $gate ) {
        wp_safe_redirect(
            admin_url(
                'admin.php?page=maspik-import-export.php&maspik_import_deprecated=1'
            )
        );
        exit;
    }

    $sanitized_data = array();
    foreach ( $maspik_settings as $raw_key => $raw_val ) {
        $key = sanitize_text_field( (string) $raw_key );
        if ( $key === '' || is_numeric( $key ) ) {
            continue;
        }
        // Skip export metadata and WP options bundled into the file (not maspik_options rows).
        if ( in_array( $key, array( 'wordpress_version', 'plugin_version', 'wordpress_language', 'php_version', 'theme_name', 'spamcounter', 'maspik_api_requests' ), true ) ) {
            continue;
        }
        $sanitized_data[ $key ] = maspik_sanitize_import_setting_value( $key, $raw_val );
    }

    global $MASPIK_IMPORT_OPTIONS;

    // textarea_blacklist is deprecated and no longer used.
    if ( isset( $sanitized_data['textarea_blacklist'] ) ) {
        unset( $sanitized_data['textarea_blacklist'] );
    }

    foreach ( $MASPIK_IMPORT_OPTIONS as $option ) {
        if ( $option === 'textarea_blacklist' ) {
            continue;
        }
        // Use array_key_exists (not !empty): PHP empty('0') is true — link count 0 and off-toggles must import.
        if ( ! array_key_exists( $option, $sanitized_data ) ) {
            continue;
        }

        $val = $sanitized_data[ $option ];
        if ( $option === 'maspik_matrix_api_mode' ) {
            $m   = absint( $val );
            $val = in_array( $m, array( 2, 3, 4 ), true ) ? $m : 4;
        }

        maspik_save_settings( $option, $val );
    }

    // Redirect after import
    wp_safe_redirect( admin_url( 'admin.php?page=maspik&imported=1' ) );
    exit;
}

function maspik_array_to_html_table($array) {
    if (empty($array)) {
        return '<p>No data available.</p>';
    }
    
    $html = '<table border="1" cellpadding="5" cellspacing="0" style="border-collapse: collapse; width: 100%;">';
    $html .= '<thead><tr>';

    // Table headers
    foreach (array_keys($array) as $key) {
        $html .= '<th>' . htmlspecialchars($key) . '</th>';
    }

    $html .= '</tr></thead>';
    $html .= '<tbody><tr>';

    // Table data
    foreach ($array as $value) {
        $html .= '<td>' . htmlspecialchars($value) . '</td>';
    }

    $html .= '</tr></tbody>';
    $html .= '</table>';

    return $html;
}


add_action('admin_post_Maspik_spamlog_download_csv', 'Maspik_spamlog_download_csv');

function Maspik_spamlog_download_csv() {
    // Check if user has permission to access admin area (same as spam log page)
    if ( ! maspik_user_can_view_spam_log() ) {
        wp_die( __( 'You do not have sufficient permissions to access this page.', 'contact-forms-anti-spam' ) );
    }

    // Verify nonce to prevent CSRF attacks
    if (!isset($_POST['maspik_download_csv_nonce']) || !wp_verify_nonce($_POST['maspik_download_csv_nonce'], 'maspik_download_csv_action')) {
        wp_die(__('Security check failed. Please try again.', 'contact-forms-anti-spam'));
    }
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'maspik_spam_logs';

    $results = $wpdb->get_results("SELECT * FROM $table_name", ARRAY_A);

    if (empty($results)) {
        wp_die('No data found.');
    }

    $filename = 'spam_log_export_' . date('Y-m-d') . '.csv';

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $filename);

    $output = fopen('php://output', 'w');

    fputcsv($output, array_keys($results[0]));

    foreach ($results as $row) {

        if (isset($row['spam_detail'])) {
            $spam_detail = @maybe_unserialize($row['spam_detail']);
            $row['spam_detail'] = is_array($spam_detail) ? json_encode($spam_detail, JSON_UNESCAPED_UNICODE) : $row['spam_detail'];
        }
        
        fputcsv($output, $row);
    }

    fclose($output);
    exit();
}

/**
 * Free-plan Matrix monthly checks limit.
 *
 * Source-of-truth precedence:
 *   1. Server-reported `monthly_limit` cached locally from the latest API response.
 *      The Matrix server controls the actual cap (env var `FREE_PLUGIN_MONTHLY_LIMIT`, default 200);
 *      the plugin keeps a local mirror so the UI stays accurate when the cap is changed mid-cycle.
 *   2. Default fallback (100) — matches the server-side default in case no response has been seen yet.
 *
 * @return int
 */
function maspik_matrix_monthly_limit() {
    if ( function_exists( 'maspik_matrix_get_server_quota_info' ) ) {
        $info = maspik_matrix_get_server_quota_info();
        if ( isset( $info['limit'] ) && (int) $info['limit'] > 0 ) {
            return (int) $info['limit'];
        }
    }
    return 100;
}

/**
 * Whether Matrix monthly free-plan limit is reached.
 * Pro plans are always treated as unlimited.
 *
 * @return bool
 */
function maspik_matrix_is_monthly_limit_reached() {
    if ( function_exists( 'cfes_is_supporting' ) && cfes_is_supporting() ) {
        return false;
    }

    $limit = max( 1, (int) maspik_matrix_monthly_limit() );
    $ym    = date( 'Ym' );

    $metrics = maspik_get_settings( 'maspik_ai_metrics' );
    if ( ! is_array( $metrics ) || ! isset( $metrics['by_month'] ) || ! is_array( $metrics['by_month'] ) ) {
        return false;
    }

    $month_data = isset( $metrics['by_month'][ $ym ] ) && is_array( $metrics['by_month'][ $ym ] )
        ? $metrics['by_month'][ $ym ]
        : array( 'checks' => 0 );

    $checks_used_this_month = max( 0, (int) ( $month_data['checks'] ?? 0 ) );
    return $checks_used_this_month >= $limit;
}

/**
 * AI metrics (MASPIK Matrix): one read + one write per submission.
 * Call once per request with deltas (e.g. after you know sent=1, spam=0|1).
 * Retrieve with maspik_get_settings('maspik_ai_metrics').
 * Structure: [ 'by_month' => [ 'YYYYMM' => [ 'checks' => n, 'spam' => n, 'limit_skipped' => n ], ... ], 'total_checks' => n, 'total_spam' => n ]
 * - checks: Matrix API calls actually sent (each counts toward the Free monthly cap).
 * - spam: responses where Matrix blocked (is_spam).
 * - limit_skipped: submissions where Matrix was on and fields were ready, but the Free monthly cap blocked sending (Pro: always 0).
 */
function maspik_ai_metrics_record( $sent_delta = 0, $spam_delta = 0 ) {
    if ( ( (int) $sent_delta ) === 0 && ( (int) $spam_delta ) === 0 ) {
        return;
    }
    if ( ! maspik_table_exists() ) {
        return;
    }
    $metrics = maspik_get_settings( 'maspik_ai_metrics' );
    if ( ! is_array( $metrics ) ) {
        $metrics = array( 'by_month' => array(), 'total_checks' => 0, 'total_spam' => 0 );
    }
    if ( ! isset( $metrics['by_month'] ) || ! is_array( $metrics['by_month'] ) ) {
        $metrics['by_month'] = array();
    }
    $metrics['total_checks'] = isset( $metrics['total_checks'] ) ? (int) $metrics['total_checks'] : 0;
    $metrics['total_spam']   = isset( $metrics['total_spam'] ) ? (int) $metrics['total_spam'] : 0;

    $ym = date( 'Ym' );
    if ( ! isset( $metrics['by_month'][ $ym ] ) || ! is_array( $metrics['by_month'][ $ym ] ) ) {
        $metrics['by_month'][ $ym ] = array( 'checks' => 0, 'spam' => 0, 'limit_skipped' => 0 );
    }
    if ( ! isset( $metrics['by_month'][ $ym ]['limit_skipped'] ) ) {
        $metrics['by_month'][ $ym ]['limit_skipped'] = 0;
    }
    $sent_delta = (int) $sent_delta;
    $spam_delta = (int) $spam_delta;
    $metrics['by_month'][ $ym ]['checks'] = (int) $metrics['by_month'][ $ym ]['checks'] + $sent_delta;
    $metrics['by_month'][ $ym ]['spam']   = (int) $metrics['by_month'][ $ym ]['spam'] + $spam_delta;
    $metrics['total_checks'] += $sent_delta;
    $metrics['total_spam']   += $spam_delta;

    $metrics['by_month'] = array_slice( $metrics['by_month'], -12, 12, true );
    maspik_save_settings( 'maspik_ai_metrics', $metrics );
}

/**
 * Record a Matrix check that was skipped because the Free monthly limit was already reached.
 *
 * @param int $delta Usually 1.
 */
function maspik_ai_metrics_record_limit_skip( $delta = 1 ) {
    $delta = (int) $delta;
    if ( $delta <= 0 ) {
        return;
    }
    if ( ! maspik_table_exists() ) {
        return;
    }
    $metrics = maspik_get_settings( 'maspik_ai_metrics' );
    if ( ! is_array( $metrics ) ) {
        $metrics = array( 'by_month' => array(), 'total_checks' => 0, 'total_spam' => 0 );
    }
    if ( ! isset( $metrics['by_month'] ) || ! is_array( $metrics['by_month'] ) ) {
        $metrics['by_month'] = array();
    }

    $ym = date( 'Ym' );
    if ( ! isset( $metrics['by_month'][ $ym ] ) || ! is_array( $metrics['by_month'][ $ym ] ) ) {
        $metrics['by_month'][ $ym ] = array( 'checks' => 0, 'spam' => 0, 'limit_skipped' => 0 );
    }
    if ( ! isset( $metrics['by_month'][ $ym ]['limit_skipped'] ) ) {
        $metrics['by_month'][ $ym ]['limit_skipped'] = 0;
    }
    $metrics['by_month'][ $ym ]['limit_skipped'] = (int) $metrics['by_month'][ $ym ]['limit_skipped'] + $delta;

    $metrics['by_month'] = array_slice( $metrics['by_month'], -12, 12, true );
    maspik_save_settings( 'maspik_ai_metrics', $metrics );
}

// Set default values for various settings
function maspik_save_default_values() {
    global $MASPIK_DEFAULT_SETTINGS;
    
    foreach ($MASPIK_DEFAULT_SETTINGS as $setting => $value) {
        maspik_save_settings($setting, $value);
    }
}

function maspik_pointer_scripts() {
    // Check if the user has already dismissed the pointer
    $dismissed = get_user_meta( get_current_user_id(), 'maspik_pointer_dismissed', true );
    if ( $dismissed ) {
        return;
    }

    // Enqueue WP Pointer scripts and styles
    wp_enqueue_style( 'wp-pointer' );
    wp_enqueue_script( 'wp-pointer' );

    add_action( 'admin_footer', 'maspik_pointer_footer_script' );
}
add_action( 'admin_enqueue_scripts', 'maspik_pointer_scripts' );

function maspik_pointer_footer_script() {
    ?>
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        var content = '<h3>' + <?php echo wp_json_encode(__('Welcome to Maspik Advanced Spam Protection', 'contact-forms-anti-spam')); ?> + '</h3>';
        content += "<p>" + <?php echo wp_json_encode(__("Maspik offers a wide range of options to protect your website from getting spam. In the settings page, you'll find easy-to-use tools for setting the desired level of protection.", 'contact-forms-anti-spam')); ?> + "</p>";
        content += '<p><a class="button button-primary maspik-settings-button" href="<?php echo admin_url('admin.php?page=maspik'); ?>">' + <?php echo wp_json_encode(__('Go to Settings', 'contact-forms-anti-spam')); ?> + '</a></p>';

        // Use a more general selector
        var element = $('#toplevel_page_maspik').first();

        if (element.length) {
            var pointer = element.pointer({
                content: content,
                position: {
                    edge: '<?php echo is_rtl() ? 'right' : 'left'; ?>',
                    align: 'center'
                },
                close: function() {
                    dismissPointer();
                }
            }).pointer('open');

            // Add click event to the settings button
            $('.maspik-settings-button').on('click', function(e) {
                dismissPointer();
                // The default action (following the link) will still occur
            });
        }

        function dismissPointer() {
            $.post(ajaxurl, {
                action: 'maspik_dismiss_pointer',
                security: '<?php echo wp_create_nonce("maspik_dismiss_pointer_nonce"); ?>'
            });
        }
    });
    </script>
    <?php
}

function maspik_dismiss_pointer() {
    check_ajax_referer( 'maspik_dismiss_pointer_nonce', 'security' );
    
    $user_id = get_current_user_id();
    update_user_meta( $user_id, 'maspik_pointer_dismissed', true );
    
    wp_die();
}
add_action( 'wp_ajax_maspik_dismiss_pointer', 'maspik_dismiss_pointer' );

// IP Verification popup content
function IP_Verification_popup_content() {
    // Start output buffering
    ob_start();
    ?>
    <div class="maspik-popup-content">
        <h2><?php esc_html_e('What is IP Verification?', 'contact-forms-anti-spam'); ?></h2>

        <p><?php esc_html_e('IP Verification checks if the sender\'s IP address is flagged as spam in the Maspik database.', 'contact-forms-anti-spam'); ?></p>

        <h4><?php esc_html_e('Your IP Verification Activity', 'contact-forms-anti-spam'); ?></h4>
        <table class="maspik-stats-table">
            <thead>
                <tr>
                    <th><?php esc_html_e('Month', 'contact-forms-anti-spam'); ?></th>
                    <th><?php esc_html_e('IP Checks Attempted', 'contact-forms-anti-spam'); ?></th>
                    <th><?php esc_html_e('API Calls Made', 'contact-forms-anti-spam'); ?></th>
                    <th><?php esc_html_e('IPs Blocked', 'contact-forms-anti-spam'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php
                $api_data = get_option('maspik_api_requests', array('months' => array()));
                if (!isset($api_data['months']) || empty($api_data['months'])) {
                    echo '<tr><td colspan="4">' . esc_html__('No data available. Please wait for some submissions.', 'contact-forms-anti-spam') . '</td></tr>';
                } else {
                    // Sort the months in descending order
                    krsort($api_data['months']);
                    $months_displayed = 0;
                    $max_requests = cfes_is_supporting("ip_verification") ? 10000 : 100;
                    foreach ($api_data['months'] as $month => $data) {
                        if ($months_displayed >= 6) break; // Limit to last 12 months
                        // Convert $month from 'YYYYMM' to a readable format
                        $dateObj = DateTime::createFromFormat('Ym', $month);
                        if ($dateObj) {
                            $monthName = $dateObj->format('F Y');
                        } else {
                            $monthName = esc_html($month);
                        }
                        $actual_calls = intval($data['actual_calls']);
                        if ( $actual_calls > $max_requests ) {
                            $max_requests = $max_requests . ' ' . esc_html__('(Reached Limit)', 'contact-forms-anti-spam');
                        }
                        echo '<tr>';
                        echo '<td>' . esc_html($monthName) . '</td>';
                        echo '<td>' . intval($data['attempts']) . '</td>';
                        echo "<td> " . esc_html("$actual_calls/$max_requests") . " </td>";
                        echo '<td>' . intval($data['blocks']) . '</td>';
                        echo '</tr>';
                        $months_displayed++;
                    }
                }
                ?>
            </tbody>
        </table>

        <h4><?php esc_html_e('Understanding the Data', 'contact-forms-anti-spam'); ?></h4>
        <ul>
            <li><strong><?php esc_html_e('IP Checks Attempted', 'contact-forms-anti-spam'); ?></strong>: <?php esc_html_e('The total number of times your site tried to verify an IP address.', 'contact-forms-anti-spam'); ?></li>
            <li><strong><?php esc_html_e('API Calls Made', 'contact-forms-anti-spam'); ?></strong>: <?php esc_html_e('The number of IP checks sent to Maspik\'s servers (counts toward your monthly limit).', 'contact-forms-anti-spam'); ?></li>
            <li><strong><?php esc_html_e('IPs Blocked', 'contact-forms-anti-spam'); ?></strong>: <?php esc_html_e('The number of IP addresses identified and blocked as spam.', 'contact-forms-anti-spam'); ?></li>
        </ul>
        <p><em><?php esc_html_e('Note: The number of IPs Blocked can be higher than API Calls Made because Maspik caches the results of the last 10 IP verifications. If an IP was recently checked and is in the cache, it doesn\'t count against your API limit but still helps in blocking spam.', 'contact-forms-anti-spam'); ?></em></p>
        <?php if ( !cfes_is_supporting("ip_verification") ) { ?>
            <hr>
            <h4><?php esc_html_e('Need More API Requests?', 'contact-forms-anti-spam'); ?></h4>
            <p><?php esc_html_e('Upgrade to Maspik Pro to get up to 10,000 API requests per month and improve your site\'s spam protection!', 'contact-forms-anti-spam'); ?></p>
            <?php maspik_get_pro(); ?>
        <?php } ?>
    </div>
    <?php
    // Output the content
    echo ob_get_clean();
}


function maspik_handle_activation_popup() {

    if (!current_user_can('manage_options')) {
        return;
    }

    $page = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : '';
    $status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
    $popup = isset($_GET['popup']) ? absint($_GET['popup']) : 0;
    
    $maspik_api_id = get_option("maspik_api_id");
    if (empty($maspik_api_id)) {
        return;
    }

    if ($page !== 'maspik_activator' || 
        $status !== 'success' || 
        $popup !== 1) {
        return;
    }

    $api_ids = array_map('trim', explode(',', $maspik_api_id));
    $first_maspik_api_id = $api_ids[0];
    
    if (!is_numeric($first_maspik_api_id)) {
        return;
    }

    $dashboard_url = esc_url('https://wpmaspik.com/?page_id=' . absint($first_maspik_api_id));

    $nonce = wp_create_nonce('maspik_activation_popup_nonce');
    
    $select_options = '';
    foreach ($api_ids as $id) {
        if (is_numeric(trim($id))) {
            $id = absint(trim($id));
            $select_options .= sprintf(
                '<option value="%1$d">%1$d</option>',
                $id
            );
        }
    }
    
    ?>
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        var popup_content = '<h2><?php echo esc_js(__("License Activated Successfully!", "contact-forms-anti-spam")); ?></h2>' +
                            '<p><?php echo esc_js(__("We found a control panel ID associated with this license, would you like us to automatically assign it to this site?", "contact-forms-anti-spam")); ?></p>' +
                            '<div class="warp">' +
                            '<div class="select-wrapper">' +
                            '<label for="dashboard_id_select"><?php echo esc_js(__("Select Dashboard ID:", "contact-forms-anti-spam")); ?></label>' +
                            '<select id="dashboard_id_select" class="dashboard-select"><?php echo $select_options; ?></select>' +
                            '</div>' +
                            '<div class="buttons-wrapper">' +
                            '<button id="add_dashboard_id" class="button button-primary" data-nonce="<?php echo esc_attr($nonce); ?>"><?php echo esc_js(__("Add Dashboard ID", "contact-forms-anti-spam")); ?></button>' +
                            '<a target="_blank" href="<?php echo esc_js($dashboard_url); ?>" class="button button-secondary"><?php echo esc_js(__("Open Dashboard", "contact-forms-anti-spam")); ?></a>' +
                            '</div>' +
                            '</div>' +
                            '<button class="close-popup">&times;</button>';

        var $popup = $('<div id="maspik_activation_popup">').html(popup_content).appendTo('body').css({
            'position': 'fixed',
            'top': '50%',
            'left': '50%',
            'transform': 'translate(-50%, -50%)',
            'background': 'white',
            'padding': '20px',
            'border': '1px solid #ccc',
            'box-shadow': '0 0 10px rgba(0,0,0,0.1)',
            'z-index': '9999',
            'width': '400px'
        });

        // Add overlay
        var $overlay = $('<div id="maspik_popup_overlay">').appendTo('body').css({
            'position': 'fixed',
            'top': 0,
            'left': 0,
            'right': 0,
            'bottom': 0,
            'background': 'rgba(0,0,0,0.5)',
            'z-index': 9998
        });

        // Close popup function
        function closePopup() {
            $popup.remove();
            $overlay.remove();
        }

        // Close button click handler
        $('.close-popup').on('click', closePopup);

        // Close on overlay click
        $overlay.on('click', closePopup);

        // Close on ESC key
        $(document).on('keydown', function(e) {
            if (e.keyCode === 27) { // ESC key
                closePopup();
            }
        });

        // Update dashboard URL when select changes
        $(document).on('change', '#dashboard_id_select', function() {
            var selectedId = $(this).val();
            var newUrl = 'https://wpmaspik.com/?page_id=' + selectedId;
            $('.button-secondary').attr('href', newUrl);
        });

        // Update AJAX call to use selected ID
        $('#add_dashboard_id').on('click', function() {
            var selectedId = $('#dashboard_id_select').val();
            
            // Get WordPress admin URL from PHP
            var adminUrl = '<?php echo admin_url("admin.php"); ?>';
            
            // Create URL object
            var url = new URL(adminUrl);
            
            // Add parameters
            url.searchParams.set('page', 'maspik');
            url.searchParams.set('private_file_id', selectedId);
            
            // Redirect
            window.location.href = url.toString();
        });
    });
    </script>
    <style>
    #maspik_activation_popup {
        position: relative;
    }
    #maspik_activation_popup .warp {
        display: flex;
        flex-direction: column;
        gap: 20px;
    }
    #maspik_activation_popup .select-wrapper {
        margin-bottom: 15px;
    }
    #maspik_activation_popup .dashboard-select {
        width: 100%;
        padding: 8px;
        margin-top: 5px;
    }
    #maspik_activation_popup .buttons-wrapper {
        display: flex;
        justify-content: space-between;
    }
    #maspik_activation_popup .buttons-wrapper > * {
        width: 48%;
        text-align: center;
    }
    #maspik_activation_popup .close-popup {
        position: absolute;
        top: 10px;
        right: 10px;
        font-size: 24px;
        color: #666;
        cursor: pointer;
        background: none;
        border: none;
    }
    #maspik_activation_popup .close-popup:hover {
        color: #000;
    }
    </style>
    <?php
}

add_action('admin_footer', 'maspik_handle_activation_popup');

/**
 * Retrieves the spam key (generating if needed).
 *
 * @return string The unique spam key.
 */
function maspik_get_spam_key() {
    // Retrieve the key from the plugin settings or generate one if it doesn't exist
    $key = get_option( 'maspik_spam_key' );

    if ( ! $key ) {
        // If no key exists, generate one and save it
        $key = wp_generate_password( 64, false, false );
        update_option( 'maspik_spam_key', $key, false );
    }

    return $key;
}

function maspik_get_browser_name($user_agent) {
    // If there is no user agent, return a suspicious message
    if (empty($user_agent)) {
        return '[Suspicious] Empty UA';
    }

    // Clean the user agent and convert to lowercase
    $t = strtolower(trim($user_agent));
    
    // Add a space at the beginning to prevent false positives with strpos
    $t = " " . $t;

    // Array of trusted browsers
    $trusted_browsers = [
        'instagram' => '[Trusted] Instagram App',
        'fb_iab'    => '[Trusted] Facebook App',
        'fbav'      => '[Trusted] Facebook App',
        'whatsapp'  => '[Trusted] WhatsApp',
        'telegram'  => '[Trusted] Telegram',
        'line/'     => '[Trusted] LINE'
    ];

    // Array of suspicious browsers
    $suspicious_browsers = [
        'headless'  => '[Suspicious] Headless',
        'phantomjs' => '[Suspicious] PhantomJS',
        'selenium'  => '[Suspicious] Selenium',
        'puppet'    => '[Suspicious] Puppeteer'
    ];

    // Array of regular browsers
    $regular_browsers = [
        'chrome'    => 'Chrome',
        'firefox'   => 'Firefox',
        'safari'    => 'Safari',
        'edge'      => 'Edge',
        'opera'     => 'Opera',
        'opr/'      => 'Opera'
    ];

    // Array of known bots
    $known_bots = [
        'google'    => '[Bot] Googlebot',
        'bing'      => '[Bot] Bingbot',
        'yandex'    => '[Bot] Yandex'
    ];

    // Array of suspicious tools
    $suspicious_tools = [
        'curl'      => '[Suspicious] Curl',
        'wget'      => '[Suspicious] Wget',
        'python'    => '[Suspicious] Python',
        'ruby'      => '[Suspicious] Ruby',
        'perl'      => '[Suspicious] Perl'
    ];

    // Check for short User Agent
    if (strlen($t) < 30) {
        return '[Suspicious] Short UA';
    }

    // Check for trusted browsers
    foreach ($trusted_browsers as $key => $value) {
        if (strpos($t, $key) !== false) {
            return $value;
        }
    }

    // Check for suspicious browsers
    foreach ($suspicious_browsers as $key => $value) {
        if (strpos($t, $key) !== false) {
            return $value;
        }
    }

    // Check for regular browsers
    foreach ($regular_browsers as $key => $value) {
        if (strpos($t, $key) !== false) {
            return $value;
        }
    }

    // Check for known bots
    foreach ($known_bots as $key => $value) {
        if (strpos($t, $key) !== false) {
            return $value;
        }
    }

    // Check for suspicious tools
    foreach ($suspicious_tools as $key => $value) {
        if (strpos($t, $key) !== false) {
            return $value;
        }
    }

    // Check for generic bot patterns
    $bot_patterns = ['bot', 'crawler', 'spider', 'http'];
    foreach ($bot_patterns as $pattern) {
        if (strpos($t, $pattern) !== false) {
            return '[Bot] Generic';
        }
    }

    // If no match found, return the first 50 characters of the UA
    return '[Unknown] ' . substr($user_agent, 0, 50);
}

function maspik_is_contains_emoji($text) {
    $pattern = '/[\x{1F600}-\x{1F64F}\x{1F300}-\x{1F5FF}\x{1F680}-\x{1F6FF}\x{1F700}-\x{1F77F}\x{1F780}-\x{1F7FF}\x{1F800}-\x{1F8FF}\x{1F900}-\x{1F9FF}\x{1FA70}-\x{1FAFF}\x{2600}-\x{26FF}\x{2700}-\x{27BF}]/u';
    return preg_match($pattern, $text) === 1;
}

// Add this to your functions.php or relevant file
function maspik_handle_reset_settings() {

    // Verify nonce
    if (!isset($_POST['nonce'])) {
        //error_log('Maspik reset - Nonce not set in request');
        wp_send_json_error(array('message' => __('Security check failed - nonce not set', 'contact-forms-anti-spam')));
        return;
    }

    if (!wp_verify_nonce($_POST['nonce'], 'maspik_save_settings_action')) {
        //error_log('Maspik reset - Nonce verification failed for action: maspik_save_settings_action');
        wp_send_json_error(array('message' => __('Security check failed - invalid nonce', 'contact-forms-anti-spam')));
        return;
    }

    // Check user capabilities
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => __('You do not have permission to perform this action', 'contact-forms-anti-spam')));
        return;
    }

    global $wpdb;
    $tables = array(
       // Only drop the maspik_options table, not the spam_logs table
       // $wpdb->prefix . 'maspik_spam_logs',
        $wpdb->prefix . 'maspik_options'
    );

    // Drop tables
    foreach ($tables as $table) {
        $wpdb->query("DROP TABLE IF EXISTS $table");
    }

    // Delete all plugin options
    $options = array(
        'maspik_run_once',
        'maspik_spam_key',
        'spamapi'
    );

    foreach ($options as $option) {
        delete_option($option);
    }

    // Create tables first
   // create_maspik_log_table();
    create_maspik_table();

    // Set default settings
    maspik_save_default_values();

    wp_send_json_success(array('message' => __('Settings reset successfully', 'contact-forms-anti-spam')));
}
add_action('wp_ajax_maspik_reset_settings', 'maspik_handle_reset_settings');


function maspik_handle_load_template() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'maspik_save_settings_action')) {
        wp_send_json_error(['message' => 'Security check failed']);
        return;
    }

    // Verify user permissions
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Permission denied']);
        return;
    }
    global $MASPIK_TEMPLATES;
    $template_type = sanitize_text_field($_POST['template_type']);
    $template_settings = isset(MASPIK_TEMPLATES[$template_type]) ? MASPIK_TEMPLATES[$template_type] : false;

    if (!$template_settings) {
        wp_send_json_error(['message' => 'Invalid template type']);
        return;
    }

    global $wpdb;
    $table = $wpdb->prefix . 'maspik_options';
    $success = true;
    $messages = [];

    // Begin transaction
    $wpdb->query('START TRANSACTION');

    try {
        // Delete existing settings
        //$wpdb->query("DELETE FROM $table");

        // Merge textarea_blacklist into text_blacklist if both exist in template
        if (isset($template_settings['textarea_blacklist']) && !empty($template_settings['textarea_blacklist'])) {
            $text_blacklist = isset($template_settings['text_blacklist']) ? $template_settings['text_blacklist'] : '';
            $textarea_blacklist = $template_settings['textarea_blacklist'];
            
            // Convert both to arrays
            $text_array = !empty($text_blacklist) ? efas_makeArray($text_blacklist) : array();
            $textarea_array = efas_makeArray($textarea_blacklist);
            
            // Merge arrays, removing duplicates (case-insensitive)
            foreach ($textarea_array as $item) {
                $item_trimmed = trim($item);
                if (!empty($item_trimmed)) {
                    // Check if item already exists (case-insensitive)
                    $exists = false;
                    foreach ($text_array as $existing_item) {
                        if (strtolower(trim($existing_item)) === strtolower($item_trimmed)) {
                            $exists = true;
                            break;
                        }
                    }
                    if (!$exists) {
                        $text_array[] = $item_trimmed;
                    }
                }
            }
            
            // Update template settings
            $template_settings['text_blacklist'] = implode("\n", $text_array);
            // Remove textarea_blacklist from template settings
            unset($template_settings['textarea_blacklist']);
        }

        // Insert new template settings
        foreach ($template_settings as $key => $value) {
            // Skip textarea_blacklist if it still exists (shouldn't happen after merge above)
            if ($key === 'textarea_blacklist') {
                continue;
            }
            
            $result = $wpdb->replace(
                $table,
                array(
                    'option_name' => $key,
                    'option_value' => $value
                ),
                array('%s', '%s')
            );

            if ($result === false) {
                throw new Exception("Failed to replace setting: $key");
            }
        }

        // Commit transaction
        $wpdb->query('COMMIT');
        wp_send_json_success(['message' => 'Template loaded successfully']);

    } catch (Exception $e) {
        // Rollback on error
        $wpdb->query('ROLLBACK');
        wp_send_json_error([
            'message' => 'Failed to load template',
            'error' => $e->getMessage()
        ]);
    }
}
add_action('wp_ajax_maspik_load_template', 'maspik_handle_load_template');

function maspik_enqueue_admin_scripts() {
    // Check if we're on the spam log page
    if (isset($_GET['page']) && $_GET['page'] == 'maspik-log.php') {
        wp_enqueue_script('maspik-spamlog', plugin_dir_url(__FILE__) . '../admin/js/maspik-spamlog.js', array('jquery'), MASPIK_VERSION, true);
        
        wp_localize_script('maspik-spamlog', 'maspikAdmin', array(
            'nonce'    => wp_create_nonce('maspik_delete_action'),
            // Provide both keys for compatibility; JS will prefer ajax_url
            'ajax_url' => admin_url('admin-ajax.php'),
            'ajaxurl'  => admin_url('admin-ajax.php'),
        ));
    }
}
add_action('admin_enqueue_scripts', 'maspik_enqueue_admin_scripts');

// Check if the current version is the latest version
function maspik_check_version_status() {
    // Get the transient first
    $version_info = get_transient('maspik_version_info');
    
    if (false === $version_info) {
        // If no transient, check the WordPress.org API
        $response = wp_remote_get(
            'https://api.wordpress.org/plugins/info/1.0/contact-forms-anti-spam.json'
        );

        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
            $data = json_decode(wp_remote_retrieve_body($response));
            if ($data && isset($data->version)) {
                $version_info = array(
                    'latest_version' => $data->version,
                    'is_latest' => version_compare(MASPIK_VERSION, $data->version, '>=')
                    //'is_latest' => version_compare('2.2.2', $data->version, '>=')
                );
                // Cache for 12 hours
                set_transient('maspik_version_info', $version_info, 12 * HOUR_IN_SECONDS);
            }
        }
    }

    // Default values if API check fails
    if (!$version_info) {
        $version_info = array(
            'latest_version' => MASPIK_VERSION,
            'is_latest' => true
        );
    }

    return $version_info;
}

/**
 * AJAX handler for generating new AI client secret
 */
function maspik_handle_generate_ai_secret() {
    // Verify nonce
    if ( empty( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'maspik_ajax_nonce' ) ) {
        wp_send_json_error('Invalid nonce');
        return;
    }
    
    // Check user capabilities
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
        return;
    }
    
    try {
        // Generate new secret
        $new_secret = maspik_generate_ai_client_secret();
        
        wp_send_json_success([
            'secret' => $new_secret,
            'message' => 'New secret generated successfully'
        ]);
        
    } catch (Exception $e) {
        wp_send_json_error('Failed to generate secret: ' . $e->getMessage());
    }
}
add_action('wp_ajax_maspik_generate_ai_secret', 'maspik_handle_generate_ai_secret');

/**
 * AJAX handler for clearing AI logs
 */
function maspik_handle_clear_ai_logs() {
    // Verify nonce
    if ( empty( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'maspik_clear_ai_logs' ) ) {
        wp_die('Security check failed');
    }
    
    // Check user capabilities
    if ( !current_user_can('manage_options') ) {
        wp_die('Insufficient permissions');
    }
    
    // Clear AI logs
    $cleared = maspik_save_settings('maspik_ai_logs', []);
    
    if ( $cleared ) {
        wp_send_json_success('AI logs cleared successfully');
    } else {
        wp_send_json_error('Failed to clear AI logs');
    }
}
add_action('wp_ajax_maspik_clear_ai_logs', 'maspik_handle_clear_ai_logs');

/**
 * Merge textarea_blacklist into text_blacklist
 * This function runs once on admin_init to migrate existing data
 */
function maspik_merge_textarea_blacklist() {
    // Check if migration has already been done
    $migration_done = get_option('maspik_blacklist_merged', false);
    if ($migration_done) {
        return;
    }

    // Get current values
    $text_blacklist = maspik_get_settings('text_blacklist');
    $textarea_blacklist = maspik_get_settings('textarea_blacklist');

    // Check if textarea_blacklist has content
    if (!empty($textarea_blacklist)) {
        // Convert both to arrays
        $text_array = !empty($text_blacklist) ? efas_makeArray($text_blacklist) : array();
        $textarea_array = efas_makeArray($textarea_blacklist);

        // Merge arrays, removing duplicates (case-insensitive)
        $merged_array = $text_array;
        foreach ($textarea_array as $item) {
            $item_trimmed = trim($item);
            if (!empty($item_trimmed)) {
                // Check if item already exists (case-insensitive)
                $exists = false;
                foreach ($merged_array as $existing_item) {
                    if (strtolower(trim($existing_item)) === strtolower($item_trimmed)) {
                        $exists = true;
                        break;
                    }
                }
                if (!$exists) {
                    $merged_array[] = $item_trimmed;
                }
            }
        }

        // Convert back to newline-separated string
        $merged_string = implode("\n", $merged_array);

        // Save merged content to text_blacklist
        maspik_save_settings('text_blacklist', $merged_string);

        // Set flag to show notice
        update_option('maspik_blacklist_merge_notice', true);
    }

    // Mark migration as done
    update_option('maspik_blacklist_merged', time());
}
add_action('admin_init', 'maspik_merge_textarea_blacklist', 1);

/**
 * Matrix defaults live in $MASPIK_DEFAULT_SETTINGS (new installs: Matrix on, API mode 2 — IP only).
 * No auto-migration on plugin update; existing sites keep their saved options.
 */
function maspik_enable_matrix_by_default() {
    return;
}
add_action('admin_init', 'maspik_enable_matrix_by_default', 2);


/**
 * Show admin notice informing users that Maspik Matrix is now enabled.
 *
 * Shown when Matrix was auto-enabled, notice not dismissed, and within ~30 days.
 */
function maspik_show_matrix_enabled_notice() {
    $notice_set = get_option('maspik_matrix_enabled_notice', false);
    if (!$notice_set) {
        return;
    }
    if (get_option('maspik_matrix_enabled_notice_dismissed', false)) {
        return;
    }
    
    $ai_enabled = efas_get_spam_api('maspik_ai_enabled', 'bool');
    
    // Only show notice if Matrix is enabled
    if (!$ai_enabled) {
        delete_option('maspik_matrix_enabled_notice');
        return;
    }
    
    // Only show notice if it was auto-enabled (check flag in maspik table)
    $was_auto_enabled = maspik_get_settings('maspik_matrix_auto_enabled');
    if (!$was_auto_enabled) {
        delete_option('maspik_matrix_enabled_notice');
        return;
    }
    
    $set_at = is_numeric($notice_set) ? (int) $notice_set : 0;
    if ($set_at && (time() - $set_at) > 30 * DAY_IN_SECONDS) {
        delete_option('maspik_matrix_enabled_notice');
        return;
    }

    $settings_url = admin_url('admin.php?page=maspik');
    $spam_log_url = admin_url('admin.php?page=maspik-log.php');
    ?>
    <div class="notice notice-success is-dismissible maspik-matrix-enabled-notice" style="position: relative;">
        <p>
            <strong><?php esc_html_e('Maspik', 'contact-forms-anti-spam'); ?>:</strong>
            <?php esc_html_e('Maspik Matrix is now enabled by default for better spam protection. You can turn it off from the Maspik settings page if needed.', 'contact-forms-anti-spam'); ?>
        </p>
        <p>
            <a href="<?php echo esc_url($settings_url); ?>" class="button button-primary">
                <?php esc_html_e('Go to Maspik settings', 'contact-forms-anti-spam'); ?>
            </a>
            <a href="<?php echo esc_url($spam_log_url); ?>" class="button">
                <?php esc_html_e('Check Spam Log', 'contact-forms-anti-spam'); ?>
            </a>
            <button type="button" class="button maspik-matrix-notice-dismiss">
                <?php esc_html_e('Dismiss', 'contact-forms-anti-spam'); ?>
            </button>
        </p>
    </div>
    <script>
    jQuery(document).ready(function($) {
        $(document).on('click', '.maspik-matrix-enabled-notice .notice-dismiss, .maspik-matrix-enabled-notice .maspik-matrix-notice-dismiss', function(e) {
            e.preventDefault();
            var $notice = $('.maspik-matrix-enabled-notice');
            $notice.slideUp();
            $.post(ajaxurl, {
                action: 'maspik_dismiss_matrix_enabled_notice',
                nonce: '<?php echo wp_create_nonce('maspik_dismiss_matrix_enabled_notice'); ?>'
            });
        });
    });
    </script>
    <?php
}
add_action('admin_notices', 'maspik_show_matrix_enabled_notice');

/**
 * AJAX handler to dismiss the Maspik Matrix enabled notice (permanent).
 */
function maspik_dismiss_matrix_enabled_notice_handler() {
    check_ajax_referer('maspik_dismiss_matrix_enabled_notice', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error();
        return;
    }
    update_option('maspik_matrix_enabled_notice_dismissed', true);
    delete_option('maspik_matrix_enabled_notice');
    wp_send_json_success();
}
add_action('wp_ajax_maspik_dismiss_matrix_enabled_notice', 'maspik_dismiss_matrix_enabled_notice_handler');

/**
 * Show admin notice when MASPIK Matrix is disabled (visible in admin panel / dashboard).
 */
function maspik_show_matrix_disabled_notice() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    if ( get_option( 'maspik_matrix_disabled_notice_dismissed', false ) ) {
        return;
    }
    $ai_effective = efas_get_spam_api( 'maspik_ai_enabled', 'bool' );
    if ( $ai_effective ) {
        return;
    }
    $settings_url = admin_url( 'admin.php?page=maspik' );
    ?>
    <div class="notice notice-warning is-dismissible maspik-matrix-disabled-admin-notice" style="position: relative;">
        <p>
            <strong><?php esc_html_e( 'MASPIK:', 'contact-forms-anti-spam' ); ?></strong>
            <?php esc_html_e( 'MASPIK Matrix is currently disabled, so advanced spam detection (including IP reputation, behavior patterns, and AI scoring) is not active. Enable it in settings for stronger protection.', 'contact-forms-anti-spam' ); ?>
        </p>
        <p>
            <a href="<?php echo esc_url( $settings_url ); ?>" class="button button-primary"><?php esc_html_e( 'Go to Maspik settings', 'contact-forms-anti-spam' ); ?></a>
            <button type="button" class="button button-small maspik-matrix-disabled-notice-dismiss" style="margin-left: 0.5em;"><?php esc_html_e( "Don't show again", 'contact-forms-anti-spam' ); ?></button>
        </p>
    </div>
    <script>
    jQuery(document).ready(function($) {
        $(document).on('click', '.maspik-matrix-disabled-admin-notice .maspik-matrix-disabled-notice-dismiss', function(e) {
            e.preventDefault();
            var $notice = $('.maspik-matrix-disabled-admin-notice');
            $notice.slideUp();
            $.post(ajaxurl, {
                action: 'maspik_dismiss_matrix_disabled_notice',
                nonce: '<?php echo esc_js( wp_create_nonce( 'maspik_dismiss_matrix_disabled_notice' ) ); ?>'
            });
        });
    });
    </script>
    <?php
}
add_action( 'admin_notices', 'maspik_show_matrix_disabled_notice', 11 );

/**
 * AJAX handler to dismiss the "Matrix disabled" admin notice (permanent).
 */
function maspik_dismiss_matrix_disabled_notice_handler() {
    check_ajax_referer( 'maspik_dismiss_matrix_disabled_notice', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error();
        return;
    }
    update_option( 'maspik_matrix_disabled_notice_dismissed', true );
    wp_send_json_success();
}
add_action( 'wp_ajax_maspik_dismiss_matrix_disabled_notice', 'maspik_dismiss_matrix_disabled_notice_handler' );

/**
 * Returns the current What's New popup version. Bump MASPIK_WHATS_NEW_VERSION in consts.php when updating the popup content.
 */
function maspik_whats_new_version() {
    return defined('MASPIK_WHATS_NEW_VERSION') ? MASPIK_WHATS_NEW_VERSION : '1';
}

/**
 * AJAX handler: mark What's New popup as seen for the current user (so it won't auto-open again until version is bumped).
 */
function maspik_whats_new_seen_handler() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field($_POST['nonce']), 'maspik_whats_new_seen')) {
        wp_send_json_error();
        return;
    }
    if (!current_user_can('manage_options')) {
        wp_send_json_error();
        return;
    }
    $version = isset($_POST['version']) ? sanitize_text_field($_POST['version']) : '';
    if ($version === '') {
        wp_send_json_error();
        return;
    }
    update_user_meta(get_current_user_id(), 'maspik_whats_new_seen_version', $version);
    wp_send_json_success();
}
add_action('wp_ajax_maspik_whats_new_seen', 'maspik_whats_new_seen_handler');

/**
 * AJAX handler to enable Maspik Matrix (AI) from the rollout notice.
 */
function maspik_enable_matrix_from_notice_handler() {
    check_ajax_referer('maspik_enable_matrix_from_notice', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error();
        return;
    }

    // Turn on Maspik Matrix for this site.
    maspik_save_settings('maspik_ai_enabled', '1');

    // Also stop showing the notice.
    update_option('maspik_matrix_enabled_notice_dismissed', true);
    delete_option('maspik_matrix_enabled_notice');

    wp_send_json_success();
}
add_action('wp_ajax_maspik_enable_matrix_from_notice', 'maspik_enable_matrix_from_notice_handler');

/**
 * Dashboard widget: nudge to enable Maspik Matrix when it's off.
 * Can be hidden forever by the user.
 */
function maspik_matrix_dashboard_widget_render() {
    // This function only renders when Matrix is off (widget is only added when off)
    // Double-check: if somehow Matrix got enabled (site or dashboard), don't render
    $ai_enabled = efas_get_spam_api('maspik_ai_enabled', 'bool');
    if ($ai_enabled) {
        return;
    }
    $settings_url = admin_url('admin.php?page=maspik');
    $nonce_activate = wp_create_nonce('maspik_enable_matrix_from_notice');
    $nonce_hide    = wp_create_nonce('maspik_hide_matrix_widget');
    ?>
    <p><?php esc_html_e('Maspik Matrix (spam protection engine) is off. Turn it on for much stronger spam and bot blocking.', 'contact-forms-anti-spam'); ?></p>
    <p>
        <button type="button" class="button button-primary maspik-dashboard-widget-enable-matrix" data-nonce="<?php echo esc_attr($nonce_activate); ?>">
            <?php esc_html_e('Enable Maspik Matrix', 'contact-forms-anti-spam'); ?>
        </button>
        <a href="<?php echo esc_url($settings_url); ?>" class="button"><?php esc_html_e('Open settings', 'contact-forms-anti-spam'); ?></a>
    </p>
    <p class="maspik-widget-hide-wrap" style="margin-bottom:0;">
        <a href="#" class="maspik-dashboard-widget-hide-forever" data-nonce="<?php echo esc_attr($nonce_hide); ?>"><?php esc_html_e('Hide this widget forever', 'contact-forms-anti-spam'); ?></a>
    </p>
    <script>
    jQuery(document).ready(function($) {
        $('.maspik-dashboard-widget-enable-matrix').on('click', function() {
            var $w = $('#maspik_matrix_widget').closest('.postbox');
            var n = $(this).data('nonce');
            $(this).prop('disabled', true);
            $.post(ajaxurl, { action: 'maspik_enable_matrix_from_notice', nonce: n }).done(function() {
                $w.find('.inside').html('<p><?php echo esc_js(__('Maspik Matrix is enabled. Your forms are protected.', 'contact-forms-anti-spam')); ?></p>');
            }).fail(function() { $('.maspik-dashboard-widget-enable-matrix').prop('disabled', false); });
        });
        $('.maspik-dashboard-widget-hide-forever').on('click', function(e) {
            e.preventDefault();
            var $w = $('#maspik_matrix_widget').closest('.postbox');
            $.post(ajaxurl, { action: 'maspik_hide_matrix_widget', nonce: $(this).data('nonce') }).done(function() {
                $w.slideUp();
            });
        });
    });
    </script>
    <?php
}

/**
 * Whether to show the “IP-only Matrix → Full Matrix” reminder (dashboard widget + admin notice).
 * Shown when depth is IP-only (mode 2), even if the Matrix toggle is off — the CTA can enable Matrix + full mode.
 */
function maspik_matrix_full_mode_nudge_should_show() {
    if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
        return false;
    }
    if ( get_option( 'maspik_matrix_full_mode_nudge_hidden_v4', false ) ) {
        return false;
    }
    if ( ! function_exists( 'maspik_matrix_api_mode_int' ) ) {
        return false;
    }
    return maspik_matrix_api_mode_int() === 2;
}

/**
 * Shared copy + actions for the Full Matrix nudge (widget and admin notice).
 *
 * @param string $context 'widget'|'notice' — dismiss control markup differs slightly.
 */
function maspik_matrix_full_mode_nudge_render_body( $context = 'widget' ) {
    $settings_matrix_url = admin_url( 'admin.php?page=maspik#maspik-matrix-section' );
    $nonce_activate      = wp_create_nonce( 'maspik_set_matrix_full_mode_from_nudge' );
    $context             = ( 'notice' === $context ) ? 'notice' : 'widget';
    ?>
    <p style="margin:0.5em 0;line-height:1.6;">
        <strong><?php esc_html_e( 'Maspik:', 'contact-forms-anti-spam' ); ?></strong>
        <?php esc_html_e( 'Matrix is in IP-only mode, switch to Full Matrix in settings for stronger blocking.', 'contact-forms-anti-spam' ); ?>
        <button type="button" class="button button-primary maspik-matrix-full-mode-nudge-activate" data-nonce="<?php echo esc_attr( $nonce_activate ); ?>" style="margin:0 0.35em 0 0.75em;vertical-align:baseline;"><?php esc_html_e( 'Use Full Matrix Check', 'contact-forms-anti-spam' ); ?></button>
        <a href="<?php echo esc_url( $settings_matrix_url ); ?>" class="button" style="vertical-align:baseline;"><?php esc_html_e( 'Settings', 'contact-forms-anti-spam' ); ?></a>
        <a href="#" class="maspik-matrix-full-mode-nudge-dismiss" style="margin-left:0.6em;font-size:11px;text-decoration:none;color:#646970;vertical-align:baseline;"><?php esc_html_e( 'dismiss', 'contact-forms-anti-spam' ); ?></a>
    </p>
    <?php
}

/**
 * Admin notice (all admin screens): IP-only Matrix depth — suggest Full Matrix (Matrix may be off until user confirms).
 */
function maspik_show_matrix_full_mode_nudge_admin_notice() {
    if ( ! maspik_matrix_full_mode_nudge_should_show() ) {
        return;
    }
    ?>
    <div class="notice notice-info maspik-matrix-full-mode-nudge-notice" style="position:relative;">
        <?php maspik_matrix_full_mode_nudge_render_body( 'notice' ); ?>
    </div>
    <?php
}

/**
 * Register the notice after Maspik settings pages clear admin_notices (maspik_is_maspik_page, priority 99999).
 */
function maspik_matrix_full_mode_nudge_register_admin_notice() {
    if ( ! maspik_matrix_full_mode_nudge_should_show() ) {
        return;
    }
    add_action( 'admin_notices', 'maspik_show_matrix_full_mode_nudge_admin_notice', 12 );
}
add_action( 'admin_init', 'maspik_matrix_full_mode_nudge_register_admin_notice', 100000 );

/**
 * Single dismiss handler script for widget + notice (avoids duplicate AJAX if both appear).
 */
function maspik_matrix_full_mode_nudge_print_dismiss_script() {
    static $printed = false;
    if ( $printed || ! maspik_matrix_full_mode_nudge_should_show() ) {
        return;
    }
    $printed = true;
    $nonce   = wp_create_nonce( 'maspik_hide_matrix_full_mode_nudge' );
    ?>
    <script>
    jQuery(function($) {
        $(document).on('click', '.maspik-matrix-full-mode-nudge-activate', function(e) {
            e.preventDefault();
            var $btn = $(this);
            $btn.prop('disabled', true);
            $.post(ajaxurl, {
                action: 'maspik_set_matrix_full_mode_from_nudge',
                nonce: $btn.data('nonce')
            }).done(function() {
                $('.maspik-matrix-full-mode-nudge-notice').slideUp();
                $('#maspik_matrix_full_mode_widget').closest('.postbox').slideUp();
            }).fail(function() {
                $btn.prop('disabled', false);
            });
        });
        $(document).on('click', '.maspik-matrix-full-mode-nudge-dismiss', function(e) {
            e.preventDefault();
            $.post(ajaxurl, {
                action: 'maspik_hide_matrix_full_mode_nudge',
                nonce: <?php echo wp_json_encode( $nonce ); ?>
            }).done(function() {
                $('.maspik-matrix-full-mode-nudge-notice').slideUp();
                $('#maspik_matrix_full_mode_widget').closest('.postbox').slideUp();
            });
        });
    });
    </script>
    <?php
}
add_action( 'admin_footer', 'maspik_matrix_full_mode_nudge_print_dismiss_script' );

/**
 * Dashboard widget: IP-only Matrix depth — suggest Full Matrix (mode 4); CTA can enable Matrix if it was off.
 */
function maspik_matrix_dashboard_widget_render_full_mode_nudge() {
    if ( ! maspik_matrix_full_mode_nudge_should_show() ) {
        return;
    }
    maspik_matrix_full_mode_nudge_render_body( 'widget' );
}

function maspik_add_matrix_dashboard_widget() {
    if (!current_user_can('manage_options')) {
        return;
    }

    if ( maspik_matrix_full_mode_nudge_should_show() ) {
        wp_add_dashboard_widget(
            'maspik_matrix_full_mode_widget',
            __('Maspik – Catch more spam', 'contact-forms-anti-spam'),
            'maspik_matrix_dashboard_widget_render_full_mode_nudge'
        );
        return;
    }

    $ai_enabled = efas_get_spam_api('maspik_ai_enabled', 'bool');

    if (!$ai_enabled) {
        if (!get_option('maspik_matrix_widget_hidden', false)) {
            wp_add_dashboard_widget(
                'maspik_matrix_widget',
                __('Maspik – Enable Matrix', 'contact-forms-anti-spam'),
                'maspik_matrix_dashboard_widget_render'
            );
        }
        return;
    }
}
add_action('wp_dashboard_setup', 'maspik_add_matrix_dashboard_widget');

/**
 * AJAX: hide the Maspik Matrix dashboard widget forever.
 */
function maspik_hide_matrix_widget_handler() {
    check_ajax_referer('maspik_hide_matrix_widget', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error();
        return;
    }
    update_option('maspik_matrix_widget_hidden', true);
    wp_send_json_success();
}
add_action('wp_ajax_maspik_hide_matrix_widget', 'maspik_hide_matrix_widget_handler');

/**
 * AJAX: hide the “switch to Full Matrix” dashboard reminder.
 */
function maspik_hide_matrix_full_mode_nudge_handler() {
    check_ajax_referer('maspik_hide_matrix_full_mode_nudge', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error();
        return;
    }
    update_option( 'maspik_matrix_full_mode_nudge_hidden_v4', true );
    wp_send_json_success();
}
add_action('wp_ajax_maspik_hide_matrix_full_mode_nudge', 'maspik_hide_matrix_full_mode_nudge_handler');

/**
 * AJAX: switch Matrix mode from IP-only to Full Matrix from nudge CTA.
 */
function maspik_set_matrix_full_mode_from_nudge_handler() {
    check_ajax_referer( 'maspik_set_matrix_full_mode_from_nudge', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error();
        return;
    }

    $matrix_off = false;
    if ( function_exists( 'efas_get_spam_api' ) ) {
        $matrix_off = ! efas_get_spam_api( 'maspik_ai_enabled', 'bool' );
    } elseif ( function_exists( 'maspik_get_settings' ) ) {
        $matrix_off = empty( maspik_get_settings( 'maspik_ai_enabled' ) );
    }
    if ( $matrix_off ) {
        maspik_save_settings( 'maspik_ai_enabled', '1' );
    }

    maspik_save_settings( 'maspik_matrix_api_mode', '4' );

    wp_send_json_success();
}
add_action( 'wp_ajax_maspik_set_matrix_full_mode_from_nudge', 'maspik_set_matrix_full_mode_from_nudge_handler' );
function maspik_show_blacklist_merge_notice() {
    // Check if we should show the notice
    $show_notice = get_option('maspik_blacklist_merge_notice', false);
    if (!$show_notice) {
        return;
    }

    // Only show on Maspik admin pages
    if (!isset($_GET['page']) || strpos($_GET['page'], 'maspik') === false) {
        return;
    }

    // Check if notice was dismissed
    $dismissed = get_transient('maspik_blacklist_merge_notice_dismissed');
    if ($dismissed) {
        return;
    }

    ?>
    <div class="notice notice-success is-dismissible maspik-blacklist-merge-notice">
        <p>
            <strong><?php esc_html_e('Maspik: Blacklist Fields Merged', 'contact-forms-anti-spam'); ?></strong><br>
            <?php esc_html_e('The textarea blacklist field has been merged into the text blacklist field. All keywords from the textarea field have been added to the text field, and both fields now use the same unified blacklist. You can manage all keywords in the "Text Fields" section.', 'contact-forms-anti-spam'); ?>
        </p>
    </div>
    <script>
    jQuery(document).ready(function($) {
        $(document).on('click', '.maspik-blacklist-merge-notice .notice-dismiss', function() {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'maspik_dismiss_blacklist_merge_notice',
                    nonce: '<?php echo wp_create_nonce('maspik_dismiss_blacklist_merge_notice'); ?>'
                }
            });
        });
    });
    </script>
    <?php
}
add_action('admin_notices', 'maspik_show_blacklist_merge_notice');

/**
 * AJAX handler to dismiss the blacklist merge notice
 */
function maspik_dismiss_blacklist_merge_notice_handler() {
    check_ajax_referer('maspik_dismiss_blacklist_merge_notice', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permission denied');
        return;
    }

    // Set transient to dismiss notice for 30 days
    set_transient('maspik_blacklist_merge_notice_dismissed', true, 30 * DAY_IN_SECONDS);
    
    // Clear the flag
    delete_option('maspik_blacklist_merge_notice');
    
    wp_send_json_success();
}
add_action('wp_ajax_maspik_dismiss_blacklist_merge_notice', 'maspik_dismiss_blacklist_merge_notice_handler');

// ============================================================
// Spam Log — shared rendering helpers (used by log page + AJAX)
// ============================================================

function maspik_spam_item_option( $row_id, $spam_value, $spam_type ) {
    $not_spam_btn = '';
    if ( $spam_type !== '' ) {
        $not_spam_btn = "<button class='entry-action-btn not-spam-action filter-delete-button'
            data-row-id='" . esc_attr( $row_id ) . "'
            data-spam-value='" . esc_attr( $spam_value ) . "'
            data-spam-type='" . esc_attr( $spam_type ) . "'>
            <span class='dashicons dashicons-flag'></span>
            " . esc_html__( 'Not Spam', 'contact-forms-anti-spam' ) . "
        </button>";
    }
    return "<div class='entry-actions'>
        <button class='entry-action-btn delete-action spam-delete-button'
            data-row-id='" . esc_attr( $row_id ) . "'
            data-spam-value='" . esc_attr( $spam_value ) . "'
            data-spam-type='" . esc_attr( $spam_type ) . "'>
            <span class='dashicons dashicons-trash'></span>
            " . esc_html__( 'Delete', 'contact-forms-anti-spam' ) . "
        </button>
        $not_spam_btn
    </div>";
}

function maspik_process_log_array( $array, &$form_data, $parent_key = '' ) {
    foreach ( $array as $key => $value ) {
        $full_key = $parent_key === '' ? $key : $parent_key . '_' . $key;
        if ( is_array( $value ) ) {
            maspik_process_log_array( $value, $form_data, $full_key );
        } else {
            $form_data .= '<tr style="border-bottom:1px solid #eee;">';
            $form_data .= '<td style="padding:8px;border:1px solid #ddd;font-weight:600;background:#f9f9f9;">' . esc_html( $full_key ) . '</td>';
            if ( is_null( $value ) ) {
                $form_data .= '<td style="padding:8px;border:1px solid #ddd;color:#999;">' . esc_html__( '(empty)', 'contact-forms-anti-spam' ) . '</td>';
            } elseif ( is_bool( $value ) ) {
                $form_data .= '<td style="padding:8px;border:1px solid #ddd;">' . ( $value ? 'true' : 'false' ) . '</td>';
            } else {
                $display = esc_html( (string) $value );
                if ( strlen( (string) $value ) > 100 ) {
                    $display = '<div style="max-height:100px;overflow-y:auto;">' . $display . '</div>';
                }
                $form_data .= '<td style="padding:8px;border:1px solid #ddd;word-break:break-word;">' . $display . '</td>';
            }
            $form_data .= '</tr>';
        }
    }
}

function maspik_process_form_data( $raw_data ) {
    if ( empty( $raw_data ) ) {
        return '<p>' . esc_html__( 'No form data available.', 'contact-forms-anti-spam' ) . '</p>';
    }
    $th = '<th style="padding:8px;border:1px solid #ddd;text-align:left;">';
    $table_open = '<table class="details-table" style="width:100%;border-collapse:collapse;margin-top:10px;">'
        . '<thead><tr style="background:#f8f8f8;">'
        . $th . esc_html__( 'Field', 'contact-forms-anti-spam' ) . '</th>'
        . $th . esc_html__( 'Value', 'contact-forms-anti-spam' ) . '</th>'
        . '</tr></thead><tbody>';

    $unserialized = @unserialize( $raw_data );
    if ( is_array( $unserialized ) ) {
        $form_data = $table_open;
        maspik_process_log_array( $unserialized, $form_data );
        return $form_data . '</tbody></table>';
    }

    $json_data = json_decode( $raw_data, true );
    if ( is_array( $json_data ) ) {
        $form_data = $table_open;
        maspik_process_log_array( $json_data, $form_data );
        return $form_data . '</tbody></table>';
    }

    return '<pre style="background:#f8f8f8;padding:10px;border:1px solid #ddd;border-radius:3px;overflow-x:auto;">'
        . esc_html( $raw_data ) . '</pre>';
}

function maspik_process_spam_source( $source ) {
    if ( empty( $source ) ) {
        return '';
    }
    if ( strpos( $source, '|||' ) !== false ) {
        list( $source_type, $url ) = explode( '|||', $source );
        $back_id = url_to_postid( $url );
        $title   = $back_id > 0 ? get_the_title( $back_id ) : 'Page';
        return esc_html( $source_type ) . '<br><a target="_blank" href="' . esc_url( $url ) . '">' . esc_html( $title ) . '</a>';
    }
    return esc_html( $source );
}

/**
 * Build <tr> HTML for an array of log rows. Used by both the initial PHP render and the AJAX handler.
 */
function maspik_build_log_rows( $results ) {
    if ( empty( $results ) ) {
        return '<tr><td colspan="7" class="maspik-log-empty">'
            . esc_html__( 'No spam entries match your filters.', 'contact-forms-anti-spam' )
            . '</td></tr>';
    }

    $output    = '';
    $row_count = 0;

    foreach ( $results as $row ) {
        if ( isset( $row['spam_tag'] ) && $row['spam_tag'] === 'spam' ) {
            $row_count++;
            continue;
        }

        $row_class    = ( $row_count % 2 === 0 ) ? 'even' : 'odd';
        $row_id       = isset( $row['id'] ) ? $row['id'] : '';
        $spam_value   = isset( $row['spamsrc_val'] ) ? esc_html( $row['spamsrc_val'] ) : '';
        $spam_type    = isset( $row['spam_type'] )   ? esc_html( $row['spam_type'] )   : '';
        $not_spam_tag = ( isset( $row['spam_tag'] ) && $row['spam_tag'] === 'not spam' ) ? ' not-a-spam' : '';
        $spam_date    = '';
        if ( ! empty( $row['spam_date'] ) ) {
            $spam_date = date_i18n(
                get_option( 'date_format' ) . ' ' . get_option( 'time_format' ),
                strtotime( $row['spam_date'] )
            );
        }

        $form_data   = maspik_process_form_data( isset( $row['spam_detail'] ) ? $row['spam_detail'] : '' );
        $spam_source = maspik_process_spam_source( isset( $row['spam_source'] ) ? $row['spam_source'] : '' );

        $output .= "<tr class='row-entries row-{$row_class}{$not_spam_tag}'>
            <td class='column-type column-entries'>
                {$spam_type}
                " . maspik_spam_item_option( $row_id, $spam_value, $spam_type ) . "
            </td>
            <td class='column-value column-entries'>
                <div class='value-content-container'>
                    <div class='spam-value-text'>" . ( isset( $row['spam_value'] ) ? esc_html( $row['spam_value'] ) : '' ) . "</div>
                    <button class='details-toggle-btn' aria-expanded='false'>
                        <span class='dashicons dashicons-arrow-down details-icon'></span>
                        <span class='details-text'>" . esc_html__( 'Show Details', 'contact-forms-anti-spam' ) . "</span>
                    </button>
                    <div class='details-panel'>
                        {$form_data}
                    </div>
                </div>
            </td>
            <td class='column-ip column-entries'>" . ( isset( $row['spam_ip'] )      ? esc_html( $row['spam_ip'] )      : '' ) . "</td>
            <td class='column-country column-entries'>" . ( isset( $row['spam_country'] ) ? esc_html( $row['spam_country'] ) : '' ) . "</td>
            <td class='column-agent column-entries'>" . ( isset( $row['spam_agent'] )   ? esc_html( $row['spam_agent'] )   : '' ) . "</td>
            <td class='column-date column-entries'>{$spam_date}</td>
            <td class='column-source column-entries'>{$spam_source}</td>
        </tr>";

        $row_count++;
    }

    return $output ?: '<tr><td colspan="7" class="maspik-log-empty">'
        . esc_html__( 'No spam entries match your filters.', 'contact-forms-anti-spam' )
        . '</td></tr>';
}

/**
 * AJAX handler: server-side paginated, filtered, sorted spam log.
 */
function maspik_ajax_get_spam_log() {
    check_ajax_referer( 'maspik_spamlog_nonce', 'nonce' );

    if ( ! maspik_user_can_view_spam_log() ) {
        wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'contact-forms-anti-spam' ) ), 403 );
    }

    if ( ! maspik_logtable_exists() ) {
        wp_send_json_success( array(
            'rows'        => '<tr><td colspan="7">' . esc_html__( 'No spam log table found.', 'contact-forms-anti-spam' ) . '</td></tr>',
            'total'       => 0,
            'page'        => 1,
            'per_page'    => 200,
            'total_pages' => 0,
        ) );
    }

    global $wpdb;
    $table = maspik_get_logtable();

    // Whitelist sort columns — never pass user input directly into ORDER BY.
    $allowed_sort_cols = array(
        'type'    => 'spam_type',
        'value'   => 'spam_value',
        'ip'      => 'spam_ip',
        'country' => 'spam_country',
        'agent'   => 'spam_agent',
        'date'    => 'spam_date',
        'source'  => 'spam_source',
        'id'      => 'id',
    );

    $sort_key = isset( $_POST['sort_col'] ) ? sanitize_key( wp_unslash( $_POST['sort_col'] ) ) : 'id';
    $sort_col = isset( $allowed_sort_cols[ $sort_key ] ) ? $allowed_sort_cols[ $sort_key ] : 'id';
    $sort_raw = isset( $_POST['sort_dir'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_POST['sort_dir'] ) ) ) : 'DESC';
    $sort_dir = ( $sort_raw === 'ASC' ) ? 'ASC' : 'DESC';

    // Whitelist per_page values.
    $allowed_per_page = array( 50, 100, 200, 500, -1 );
    $per_page_raw     = isset( $_POST['per_page'] ) ? intval( $_POST['per_page'] ) : 200;
    if ( ! in_array( $per_page_raw, $allowed_per_page, true ) ) {
        $per_page_raw = 200;
    }
    $show_all = ( $per_page_raw === -1 );
    $per_page = $show_all ? 0 : $per_page_raw;
    $page     = isset( $_POST['page'] ) ? max( 1, intval( $_POST['page'] ) ) : 1;
    $offset   = ( $page - 1 ) * max( 1, $per_page );

    // Sanitize filter inputs.
    $filter_type    = isset( $_POST['filter_type'] )      ? sanitize_text_field( wp_unslash( $_POST['filter_type'] ) )      : '';
    $filter_ip      = isset( $_POST['filter_ip'] )        ? sanitize_text_field( wp_unslash( $_POST['filter_ip'] ) )        : '';
    $filter_country = isset( $_POST['filter_country'] )   ? sanitize_text_field( wp_unslash( $_POST['filter_country'] ) )   : '';
    $filter_source  = isset( $_POST['filter_source'] )    ? sanitize_text_field( wp_unslash( $_POST['filter_source'] ) )    : '';
    $filter_from    = isset( $_POST['filter_date_from'] ) ? sanitize_text_field( wp_unslash( $_POST['filter_date_from'] ) ) : '';
    $filter_to      = isset( $_POST['filter_date_to'] )   ? sanitize_text_field( wp_unslash( $_POST['filter_date_to'] ) )   : '';

    // Strict date validation — only YYYY-MM-DD accepted.
    $filter_from = ( $filter_from && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $filter_from ) ) ? $filter_from : '';
    $filter_to   = ( $filter_to   && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $filter_to ) )   ? $filter_to   : '';

    // Build WHERE clause with prepare args.
    $where_parts  = array();
    $prepare_args = array();

    if ( $filter_type !== '' ) {
        $where_parts[]  = 'spam_type = %s';
        $prepare_args[] = $filter_type;
    }
    if ( $filter_ip !== '' ) {
        $where_parts[]  = 'spam_ip LIKE %s';
        $prepare_args[] = '%' . $wpdb->esc_like( $filter_ip ) . '%';
    }
    if ( $filter_country !== '' ) {
        $where_parts[]  = 'spam_country LIKE %s';
        $prepare_args[] = '%' . $wpdb->esc_like( $filter_country ) . '%';
    }
    if ( $filter_source !== '' ) {
        $where_parts[]  = 'spam_source LIKE %s';
        $prepare_args[] = '%' . $wpdb->esc_like( $filter_source ) . '%';
    }
    if ( $filter_from !== '' ) {
        $where_parts[]  = 'spam_date >= %s';
        $prepare_args[] = $filter_from . ' 00:00:00';
    }
    if ( $filter_to !== '' ) {
        $where_parts[]  = 'spam_date <= %s';
        $prepare_args[] = $filter_to . ' 23:59:59';
    }

    $where_sql = $where_parts ? ( 'WHERE ' . implode( ' AND ', $where_parts ) ) : '';

    // COUNT query.
    if ( $where_parts ) {
        $total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table $where_sql", $prepare_args ) );
    } else {
        $total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table" );
    }

    // DATA query.
    if ( $show_all ) {
        $sql = "SELECT * FROM $table $where_sql ORDER BY $sort_col $sort_dir";
        $results = $where_parts
            ? $wpdb->get_results( $wpdb->prepare( $sql, $prepare_args ), ARRAY_A )
            : $wpdb->get_results( $sql, ARRAY_A );
    } else {
        $sql       = "SELECT * FROM $table $where_sql ORDER BY $sort_col $sort_dir LIMIT %d OFFSET %d";
        $data_args = array_merge( $prepare_args, array( $per_page, $offset ) );
        $results   = $wpdb->get_results( $wpdb->prepare( $sql, $data_args ), ARRAY_A );
    }

    $total_pages = $show_all ? 1 : (int) ceil( $total / max( 1, $per_page ) );

    wp_send_json_success( array(
        'rows'        => maspik_build_log_rows( $results ),
        'total'       => $total,
        'page'        => $page,
        'per_page'    => $per_page_raw,
        'total_pages' => $total_pages,
    ) );
}
add_action( 'wp_ajax_maspik_get_spam_log', 'maspik_ajax_get_spam_log' );
