<?php
// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/* The admin-specific functionality of the plugin.
 *
 *
 */

    define('MASPIK_API_KEY', 'KVJS5BDFFYabnZkQ3Svty6z6CIsxp3YG5ny4lrFQ');


    // run the default values function only once if necessary
    function maspik_check_if_need_to_run_once() {
        // check if already run
        static $already_run = false;
        // already run, or not in admin, return
        if ($already_run || !is_admin()) {
            //return;
        }

        $maspik_run_once = get_option('maspik_run_once');
        
        // if the option doesn't exist, set it to 0
        if ($maspik_run_once === false) {
            add_option('maspik_run_once', 0);
            $maspik_run_once = 0;
        }

        if ($maspik_run_once < 2) {
            if (!maspik_table_exists('text_blacklist')) { 
                create_maspik_log_table();
                create_maspik_table();
            }    
            maspik_save_default_values();
            update_option('maspik_run_once', ++$maspik_run_once); // update to 2 to prevent reruns
        }

        $already_run = true;
    }
    add_action( 'init', 'maspik_check_if_need_to_run_once' , 20);

     //Check for PRO -addclass- 
        function maspik_add_pro_class($type = ""){
            if(cfes_is_supporting($type)){
                return "maspik-pro";
            }
            else{
                return "maspik-not-pro";
            }
        }
    //Check for PRO - END

    //Small buttons

        function maspik_tooltip($message){
            echo "<div class='maspik-tooltip'>
            <span class='dashicons dashicons-info'></span>
            <span class='maspik-tooltiptxt'>". esc_html($message) ."</span></div>";
        }

        function maspik_popup($data="", $subject="", $label="", $icon=""){

            
            if($icon == 'visibility'){
                $popuptype = "example";
            }else{
                $popuptype = "shortcode";
            }
            if(!$data){
                $popuptype = "ip-verification";
            }

            echo "<div class='maspik-small-btn btns'>
                <a class='your-button-class' 
                data-array='". esc_attr($data) . "' 
                data-title ='" .esc_attr($subject) . "' 
                href='#' 
                data-popup-id='pop-up-".  esc_attr($popuptype) ."'>
                <span class='dashicons dashicons-". esc_attr($icon) ."'></span>". 
                esc_html($label) .
                "</a> </div>";
        }

        function maspik_get_pro(){    

            echo "<div class='maspik-small-btn btns get-pro'>
                <a class='maspik-get-pro-a' href='https://wpmaspik.com/?ref=inpluginad' target='_blank'><span class='dashicons dashicons-star-empty'></span> Get Maspik PRO</a> </div>";
        }

        function maspik_activate_license(){    

            echo "<div class='maspik-small-btn btns get-pro activate-license'>
                <a class='maspik-get-pro-a' href='". get_site_url() ."/wp-admin/admin.php?page=maspik_activator' target='self'><span class='dashicons dashicons-admin-network'></span> Activate License</a> </div>";
        }


    //Small buttons - END

    // Generate Elements

        function maspik_simple_dropdown($name, $class , $array, $attr = ""){
            $dbresult = maspik_get_settings($name);
                
            $dropdown= "  <select name=". esc_attr($name) ." class=". esc_attr($class) ."  $attr >";
                foreach($array as $entries => $value){
                    $dropdown .="<option value='". esc_attr($value) . "'";
                    if(  $dbresult == $value){
                        $dropdown .= " selected='select'";
                    }
                    $dropdown .= ">". esc_html($entries) ."</option>";   
                }

            
            $dropdown .= "</select>";

            return $dropdown;

        }

function maspik_toggle_button($name, $id, $dbrow_name, $class, $type = "", $manual_switch = "", $api_array = false){
    toggle_ready_check($dbrow_name); //make db row if there's none yet

    if($type == "form-toggle"){
        $checked = maspik_get_settings($dbrow_name, 'form-toggle') == "yes" ? 'checked': "";
    }
    elseif($type == "yes-no"){
        $checked = maspik_get_settings($dbrow_name) == 'yes' ? 'checked': "";

    } elseif($type == "other_options"){
        $checked = maspik_get_settings($dbrow_name, '', 'old') ? 'checked': "";
    } else {
        $checked = maspik_get_settings($dbrow_name, 'toggle');
        $checked = maspik_is_contain_api($api_array) ? 'checked' : $checked ;
    }

    if($manual_switch === 0 ){
        $checked = "";
    } elseif($manual_switch && maspik_get_settings($dbrow_name) == ""){
        $checked = "checked";
    }

    $toggle= " <label class='maspik-toggle' >
                <input type='checkbox' id=". esc_attr($id) ." name='". esc_attr($name) . "' " . esc_attr($checked) . " class='". esc_attr($class) ."'> 
                <span class='maspik-toggle-slider'></span>
                </label>";
    $apitext = __('Dashboard rules', 'contact-forms-anti-spam');
    if (maspik_is_contain_api($api_array)) {
        $toggle .= "<span class='limit-api-chip'>
                        <span class='limit-api-label'>$apitext</span>
                    </span>";
    }
    return $toggle;
}


        function maspik_save_button_show( $label = 'Save', $add_class = '', $name = 'maspik-save-btn' ) {
            echo '<div class="maspik-save-submit-wrap">';
            echo '<div class="submit maspik-save-submit--with-progress">';
            echo "<input type='submit' name='" . esc_attr( $name ) . "' value='" . esc_attr( $label ) . "' id='submit' class='" . esc_attr( $add_class ) . "'>";
            echo '</div>';
            echo '</div>';
        } 

        function create_maspik_textarea($name, $rows = 4, $cols = 50, $class = '', $pholder = "", $maxlength = 0) { 

        
            if($pholder == "error-message"){
                $txtplaceholder = maspik_get_settings( "error_message" ) ? maspik_get_settings( "error_message" ) : __('This looks like spam. Try to rephrase, or contact us in an alternative way.', 'contact-forms-anti-spam');
            } else{
                $txtplaceholder = $pholder;
            }
            
            $data = maspik_get_settings($name);

            $class_attr = !empty($class) ? ' class="' . esc_attr($class) . '"' : '';
            $textarea = '<textarea name="' . esc_attr($name) . '" rows="' . esc_attr($rows) . '" cols="' . esc_attr($cols) . '"' . $class_attr . '"';
            if($txtplaceholder!= ""){
                $textarea .= ' placeholder="' . esc_attr($txtplaceholder) . '"';
            }
            if($maxlength > 0){
                $textarea .= ' maxlength="' . esc_attr($maxlength) . '"';
            }
            $textarea .= '>' . esc_html($data) . '</textarea>';

            


            return $textarea;
        }

        function create_maspik_input($name, $class = '', $mode = "text", $placeholder = "") {      
            
            $data = ( $mode === "number" && maspik_get_settings($name) ) ? (int)maspik_get_settings($name) : maspik_get_settings($name);

            $class_attr = !empty($class) ? ' class="' . esc_attr($class . " is-". $mode) . '"' : '';
            $input = "<input  name='" . esc_attr($name) . "' id='". esc_attr($name) . " '" . $class_attr . " type='" . $mode . "' value='". esc_attr($data) ."' placeholder='". esc_attr($placeholder) ."'></input>";


            return $input;
        }

        function create_maspik_numbox($id, $name, $class, $label, $default = '', $min = 2, $max = 10000) {      
            $data = maspik_get_settings($name);
            // Check the API value
            $api_value = null;
            if(is_array(efas_get_spam_api($name,"bool"))){
                $api_value = efas_get_spam_api($name, "bool")[0];
            } else {
                $api_value = efas_get_spam_api($name, "bool");
            }
            // Check the original value
            $value = '';
            if ($data === '' || $data === null) {  // If the value is completely empty
                if ($default > 0) {
                    $value = intval($default);    
                }
            } else {  // If there is a value (including 0)
                $value = intval($data);
            }

            $numbox = "<div class='maspik-numbox-wrap'>
                <label for='" . esc_attr($id) . "'>" . esc_html($label) . ":</label>
                <input type='number' 
                    id='" . esc_attr($id) . "' 
                    name='" . esc_attr($name) . "' 
                    class='" . esc_attr($class) . "' 
                    min='" . esc_attr($min) . "' 
                    max='" . esc_attr($max) . "' 
                    step='1' 
                    value='" . esc_attr($value) . "'>";

            // Add Dashboard value (API value) if it exists and has a value (including 0)
            if($api_value !== null && $api_value !== '' && trim($api_value) !== '') {
                $numbox .= "<div class='limit-api-wrap'>
                    <span class='limit-api-chip'>
                        <span class='dashicons dashicons-cloud limit-api-icon' aria-hidden='true'></span>
                        <span class='limit-api-label'>" . esc_html__('Dashboard value', 'contact-forms-anti-spam') . "</span>
                        <span class='limit-api-value'>" . esc_html($api_value) . "</span>
                    </span>
                </div>";
            }
            
            $numbox .= "</div>";
                    
            return $numbox;
        }

        function create_maspik_select($name, $class, $array, $attr="", $multiple = true) {      
            
            $the_array = $array;
            $setting_value = maspik_get_dbvalue();
            
            $results = $data = maspik_get_settings($name, 'select');
            $class_attr = !empty($class) ? ' class="js-states form-control maspik-select ' . esc_attr($class) . '"' : '';

            $result_array = array();
            if (is_array($results) || is_object($results)) {
                foreach ($results as $result) {
                    $result_array = explode(" ", $result->$setting_value);
                }
            }
            $multiple = $multiple ? "multiple='multiple'" : "";
            $select =  '<select '. $class_attr .' '.$multiple.' '.$attr.' name="'.esc_attr($name).'[]" id="'.esc_attr($name).'"  >';
            foreach ($the_array as $key => $value) {
                $select .=  ' <option value="'.esc_attr($key).'" ';
                foreach ($result_array as $aresult) {
                    if ($key == preg_replace('/\s+/', '', $aresult)) {
                        $select .=  ' selected="selected"';
                    }

                }
                $select .= '>'. esc_html($value) .'</option>';
            }

            $select .= "</select>";
                       
            return $select;
            
        }

         
    // Generate Elements - END ---

    //Check if DB has toggle rows, if none, make them
    function toggle_ready_check($name){
        global $wpdb;
            
        $table = maspik_get_dbtable();
        $setting_label = maspik_get_dblabel();
        $setting_value = maspik_get_dbvalue();

        // Check DB if data exists
        $toggle_lim_exists = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table WHERE $setting_label = %s", $name ) );

        if ( $toggle_lim_exists == 0 ) {
            // If the row doesn't exist, insert a new row
            $wpdb->insert(
                $table,
                array(
                    $setting_label => $name, 
                    $setting_value => 0,
                )
            );
    
        }


    }
    //Check if DB has toggle rows, if none, make them - END --

    // Helper: mask sensitive dashboard values (API keys, tokens, etc.)
        function maspik_mask_dashboard_value( $value, $field_name ) {
            $sensitive_fields = array(
                'abuseipdb_api',
                'proxycheck_io_api',
                'numverify_api',
            );

            $value = trim( (string) $value );

            if ( $value === '' ) {
                return $value;
            }

            // Only mask specific sensitive fields
            if ( ! in_array( $field_name, $sensitive_fields, true ) ) {
                return $value;
            }

            $len = strlen( $value );

            // For very short values, just replace everything with asterisks
            if ( $len <= 8 ) {
                return str_repeat( '*', $len );
            }

            // Keep first 6 and last 4 characters, mask the rest
            $start = substr( $value, 0, 6 );
            $end   = substr( $value, -4 );
            $mask_length = max( 3, $len - 10 );

            return $start . str_repeat( '•', $mask_length ) . $end;
        }

    //Maspik API
        function maspik_spam_api_list($name, $array = "") {
            $api = efas_get_spam_api($name);
 
            if (!$api) {
                return;
            }

            $toggle_id = 'maspik-api-toggle-' . esc_attr($name);

            echo '<div class="maspik-form-api-list">';
            echo '<input type="checkbox" id="' . $toggle_id . '" class="maspik-api-toggle" hidden>';
            echo '<label for="' . $toggle_id . '" class="maspik-api-chip">';
            echo '<span class="dashicons dashicons-cloud maspik-api-icon" aria-hidden="true"></span>';
            echo '<span class="maspik-api-chip-text">' . esc_html__('Dashboard rules', 'contact-forms-anti-spam') . '</span>';
            echo '<span class="dashicons dashicons-arrow-down-alt2 maspik-api-chip-caret" aria-hidden="true"></span>';
            echo '</label>';
            
            // Convert string to array if needed
            if (!is_array($api)) {
                $api = explode("\n", str_replace("\r", "", $api));
            }

            echo '<div class="maspik-api-text-wrap">';
            echo '<div class="maspik-api-text' . (!is_array($array) ? ' maspik-custom-scroll' : '') . '">';

            if (is_array($api)) {
                if (is_array($array)) {
                    // Handle associative array case
                    foreach ($api as $line) {
                        $key = preg_replace('/\s+/', '', $line);
                        if (isset($array[$key])) {
                            $display_value = maspik_mask_dashboard_value( $array[$key], $name );
                            echo '<span class="api-entry">' . esc_html( $display_value ) . '</span>';
                        }
                    }
                } else {
                    // Handle simple array case
                    echo '<ul class="api-entries-list">';
                    foreach ($api as $line) {
                        $line = trim($line);
                        if (!empty($line)) {
                            $display_value = maspik_mask_dashboard_value( $line, $name );
                            echo '<li>' . esc_html( $display_value ) . '</li>';
                        }
                    }
                    echo '</ul>';
                }
            } else {
                // Handle string case
                echo '<p>' . esc_html($api) . '</p>';
            }

            echo '</div>'; // Close maspik-api-text
            echo '</div>'; // Close maspik-api-text-wrap
            echo '</div>'; // Close maspik-form-api-list
        }
    //Maspik API - END

    //Maspik API status checker
        function check_maspik_api_values(){
            if(
                efas_get_spam_api("text_field") ||
                efas_get_spam_api("email_field") ||
                efas_get_spam_api("textarea_field") 
            ){
                return true;
            }
        }
    //Maspik API status checker - END

    
class Maspik_Admin {

	/**
	 * The ID of this plugin.
	 *
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 */
	private $version;


	/**
	 * Initialize the class and set its properties.
	 *
	 */
	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version = $version;
		add_action('admin_menu', array( $this, 'Maspik_addPluginAdminMenu' ), 9);   
		//add_action('admin_init', array( $this, 'registerAndBuildFields' ));
        
        // Add AJAX handler for feedback form
        add_action('wp_ajax_maspik_submit_feedback', array($this, 'maspik_handle_feedback_submission'));
	}

    
	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    1.0.0
	 */ 
    public function enqueue_styles() {
        $screen = get_current_screen();
        if ( false !== strpos($screen->id, 'maspik') ) { 
            wp_enqueue_style( "maspik-admin-style", plugin_dir_url(__DIR__) . 'admin/css/admin-style.css', array(), MASPIK_VERSION, 'all' ); 
        }
    }

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 */

		//wp_enqueue_script( "js_select2_".$this->plugin_name, 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', array( 'jquery' ), $this->version, false );

	}
    
    public function Maspik_addPluginAdminMenu() {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg"  viewBox="0 0 800 800"><path fill="#a7abae" d="M659.46,2.52H140.54C65.13,2.52,4,63.65,4,139.06v521.88c0,75.41,61.13,136.54,136.54,136.54h518.92c75.41,0,136.54-61.13,136.54-136.54V139.06c0-75.41-61.13-136.54-136.54-136.54ZM524.27,653.8l-10.19-231.45-4.27-92.38c-1.54,17.75-3.29,34.68-5.26,50.79-1.97,16.11-4.39,31.72-7.23,46.85l-43.07,226.19h-111.78l-36.82-226.52c-2.19-13.15-5.15-35.72-8.88-67.72-.44-4.82-1.43-14.68-2.96-29.59l-3.29,93.7-12.16,230.13h-145.97l59.18-507.61h170.63l28.6,170.96c2.41,14.03,4.55,29.26,6.41,45.7,1.86,16.44,3.56,34.3,5.1,53.59,2.85-32.22,6.79-61.04,11.84-86.46l34.85-183.78h169.31l49.31,507.61h-143.34Z"/></svg>';
        $base64 = base64_encode($svg);
        $icon_url = 'data:image/svg+xml;base64,' . $base64;

        add_menu_page($this->plugin_name, 'Maspik Spam', 'administrator', $this->plugin_name, array($this, 'displayPluginAdminDashboard'), $icon_url, 85);

        $numlogspam = maspik_spam_count() ? "(" . maspik_spam_count() . ")" : false;

        add_submenu_page($this->plugin_name, 'Main settings', 'Main settings', 'administrator', $this->plugin_name, array($this, 'displayPluginAdminDashboard'), 10);

        add_submenu_page($this->plugin_name, 'Spam Log', 'Spam Log ' . $numlogspam, 'edit_pages', $this->plugin_name . '-log.php', array($this, 'displayPluginAdminSettings'), 20);
        
        add_submenu_page($this->plugin_name, 'Import/Export', 'Import/Export', 'administrator', $this->plugin_name . '-import-export.php', array($this, 'Maspik_import_export_settings_page'), 40);

        if ( cfes_is_supporting()) {
            $first_maspik_api_id = maspik_get_settings('private_file_id');
            $dashboard_url = 'https://wpmaspik.com/?page_id=' . esc_attr($first_maspik_api_id . '&ref=plugin-menue&my-account=1');
            $url = $first_maspik_api_id ? $dashboard_url : 'https://wpmaspik.com/my-account?ref=plugin-menue';
            $title = 'Maspik dashboard';
        }else{
            $url = 'https://wpmaspik.com/?ref=upgrade-to-PRO-plugin-menue';
            $title = 'Upgrade to PRO';
        }
        add_submenu_page(
            $this->plugin_name,
            $title,
            $title,
            'edit_pages',
            $url,
            '',
            60
        );


    }

    public function displayPluginAdminDashboard() {
        require_once 'partials/' . $this->plugin_name . '-admin-display.php';
    }

    public function displayPluginAdminSettings() {
        $active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'general';
        if (isset($_GET['error_message'])) {
            add_action('admin_notices', array($this, 'settingsPageSettingsMessages'));
            do_action('admin_notices', $_GET['error_message']);
        }
        require_once 'partials/' . $this->plugin_name . '-log.php';
    }

    

    public function displayPluginAdminPro() {
        $active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'general';
        if (isset($_GET['error_message'])) {
            add_action('admin_notices', array($this, 'settingsPageSettingsMessages'));
            do_action('admin_notices', $_GET['error_message']);
        }
        require_once 'partials/' . $this->plugin_name . '-api.php';
    }

    public function displayPluginAdminOptions() {
        $active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'general';
        if (isset($_GET['error_message'])) {
            add_action('admin_notices', array($this, 'settingsPageSettingsMessages'));
            do_action('admin_notices', $_GET['error_message']);
        }
        require_once 'partials/' . $this->plugin_name . '-options.php';
    }

    public function Maspik_import_export_settings_page() {
        $active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'general';
        if (isset($_GET['error_message'])) {
            add_action('admin_notices', array($this, 'settingsPageSettingsMessages'));
            do_action('admin_notices', $_GET['error_message']);
        }
        require_once 'partials/' . $this->plugin_name . '-import-export.php';
    }


    public function settingsPageSettingsMessages($error_message) {
        switch ($error_message) {
            case '1':
                $message = __('There was an error adding this setting. Please try again. If this persists, shoot us an email.', 'contact-forms-anti-spam');
                $err_code = esc_attr('Error');
                $setting_field = 'Error';
                break;
        }
        $type = 'error';
        add_settings_error($setting_field, $err_code, $message, $type);
    }
         
    /**
     * Handle feedback form submission
     */
    public function maspik_handle_feedback_submission() {
        // Enable error logging
        if (!defined('WP_DEBUG_LOG')) {
            define('WP_DEBUG_LOG', true);
        }
        
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'maspik_feedback_nonce')) {
            wp_send_json_error('Invalid nonce');
            return;
        }

        // Get and sanitize form data
        $feedback_type = isset($_POST['feedback_type']) ? sanitize_text_field($_POST['feedback_type']) : '';
        $feedback_message = isset($_POST['feedback_message']) ? sanitize_textarea_field($_POST['feedback_message']) : '';
        $feedback_email = isset($_POST['feedback_email']) ? sanitize_email($_POST['feedback_email']) : '';

        // Log received data

        // Validate required fields
        if (empty($feedback_type) || empty($feedback_message)) {
            wp_send_json_error('Required fields are missing');
            return;
        }

        // Prepare email content
        $site_url = get_site_url();
        $subject = sprintf('[Maspik Feedback] %s from %s', ucfirst($feedback_type), $site_url);
        
        $message = "Feedback Type: " . $feedback_type . "\n\n";
        $message .= "Message:\n" . $feedback_message . "\n\n";
        $message .= "Site URL: " . $site_url . "\n";
        if (!empty($feedback_email)) {
            $message .= "User Email: " . $feedback_email . "\n";
        }

        // Set up email headers with proper From address
        $site_name = get_bloginfo('name');
        $domain = parse_url(get_site_url(), PHP_URL_HOST);
        
        // Use the site's own domain for sending
        $from_email = 'noreply@' . $domain;
        if (!is_email($from_email)) {
            // If the domain is not valid, use a fallback
            $from_email = 'noreply@' . $_SERVER['HTTP_HOST'];
            if (!is_email($from_email)) {
                $from_email = 'noreply@wpmaspik.com';
            }
        }
        $from_name = $site_name ? $site_name : 'WordPress';
        
        // Add additional headers for better deliverability
        $headers = array(
            'Content-Type: text/plain; charset=UTF-8',
            'From: ' . $from_name . ' <' . $from_email . '>',
            'Reply-To: ' . (!empty($feedback_email) ? $feedback_email : $from_email),
            'X-Mailer: PHP/' . phpversion(),
            'X-Sender: ' . $from_email,
            'X-Auth-User: ' . $from_email,
            'List-Unsubscribe: <mailto:' . $from_email . '?subject=unsubscribe>',
            'Return-Path: ' . $from_email
        );

        $to = 'hello@wpmaspik.com';
        
        // Debug information
        $debug_info = array(
            'to' => $to,
            'from_email' => $from_email,
            'from_name' => $from_name,
            'subject' => $subject,
            'message' => $message,
            'headers' => $headers,
            'site_url' => $site_url,
            'php_version' => PHP_VERSION,
            'wordpress_version' => get_bloginfo('version'),
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            'mail_function_exists' => function_exists('mail'),
            'wp_mail_function_exists' => function_exists('wp_mail')
        );

        // Try to send email with error handling
        $sent = false;
        $error_message = '';
        
        try {
            // First attempt with wp_mail
            $sent = wp_mail($to, $subject, $message, $headers);
            
            // If wp_mail fails, try direct mail() function
            if (!$sent && function_exists('mail')) {
                $header_string = implode("\r\n", $headers);
                $sent = mail($to, $subject, $message, $header_string);
            }
            
            if (!$sent) {
                $error_message = 'Failed to send email using both wp_mail and mail()';
            }
        } catch (Exception $e) {
            $error_message = $e->getMessage();
        }
        
        if ($sent) {
            wp_send_json_success(array(
                'message' => 'Feedback sent successfully',
                'debug_info' => $debug_info
            ));
        } else {
            wp_send_json_error(array(
                'message' => 'Failed to send feedback: ' . $error_message,
                'debug_info' => $debug_info
            ));
        }
    }

        // AI logs clearing is handled in includes/functions.php
}

