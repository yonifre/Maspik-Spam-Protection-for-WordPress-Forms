<?php
// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}
/**
 * Provide a admin area view for the plugin
 */
$spamcounter = maspik_spam_count();
//$errorlog = get_option( 'errorlog' ) ? get_option( 'errorlog' )  : "Empty";

if(isset($_POST['clear_log'])){
  // Verify nonce to prevent CSRF attacks
  if (!isset($_POST['cfes_clear_log_nonce']) || !wp_verify_nonce($_POST['cfes_clear_log_nonce'], 'cfes_clear_log_action')) {
    wp_die(__('Security check failed. Please try again.', 'contact-forms-anti-spam'));
  }
  
  // Check if user has permission to clear logs (admin only)
  if (!current_user_can('manage_options')) {
    wp_die(__('You do not have sufficient permissions to access this page.', 'contact-forms-anti-spam'));
  }

  global $wpdb;
  
  $table = maspik_get_logtable();
        
  $wpdb->query("DELETE FROM $table");

  // Redirect to the same page to avoid resubmission
  wp_redirect(admin_url('admin.php?page=maspik-log.php'));
  exit;
}

?>

<?php


function maspik_spam_item_option($row_id, $spam_value, $spam_type) {
    // Build the Not Spam button only if there is a spam_type
    $not_spam_btn = "";
    if ($spam_type != "") {
        $not_spam_btn = "<button class='entry-action-btn not-spam-action filter-delete-button' data-row-id='" . esc_attr($row_id) . "' data-spam-value='" . esc_attr($spam_value) . "' data-spam-type='" . esc_attr($spam_type) . "'>
            <span class='dashicons dashicons-flag'></span>
            " . esc_html__('Not Spam', 'contact-forms-anti-spam') . "
        </button>";
    }

    // Build the Delete button
    return "<div class='entry-actions'>
        <button class='entry-action-btn delete-action spam-delete-button' data-row-id='" . esc_attr($row_id) . "' data-spam-value='" . esc_attr($spam_value) . "' data-spam-type='" . esc_attr($spam_type) . "'>
            <span class='dashicons dashicons-trash'></span>
            " . esc_html__('Delete', 'contact-forms-anti-spam') . "
        </button>
        $not_spam_btn
    </div>";
}


function processArray($array, &$form_data, $parent_key = '') {
  foreach ($array as $key => $value) {
      // Building the full key
      $full_key = $parent_key === '' ? $key : $parent_key . '_' . $key;

      if (is_array($value)) {
          // If the value is an array, go over it
          processArray($value, $form_data, $full_key);
      } else {
          // Adding a row to the table with the full key and value
          $form_data .= '<tr style="border-bottom: 1px solid #eee;">';
          $form_data .= '<td style="padding: 8px; border: 1px solid #ddd; font-weight: 600; background-color: #f9f9f9;">' . esc_html($full_key) . '</td>';
          
          // Handle different value types
          if (is_null($value)) {
              $form_data .= '<td style="padding: 8px; border: 1px solid #ddd; color: #999;">' . esc_html__('(empty)', 'contact-forms-anti-spam') . '</td>';
          } elseif (is_bool($value)) {
              $form_data .= '<td style="padding: 8px; border: 1px solid #ddd;">' . ($value ? 'true' : 'false') . '</td>';
          } elseif (is_numeric($value)) {
              $form_data .= '<td style="padding: 8px; border: 1px solid #ddd;">' . esc_html($value) . '</td>';
          } else {
              // For strings, check if they're long and format accordingly
              $display_value = esc_html($value);
              if (strlen($value) > 100) {
                  $display_value = '<div style="max-height: 100px; overflow-y: auto;">' . $display_value . '</div>';
              }
              $form_data .= '<td style="padding: 8px; border: 1px solid #ddd; word-break: break-word;">' . $display_value . '</td>';
          }
          
          $form_data .= '</tr>';
      }
  }
}


function cfes_build_table() {
    global $wpdb;
    if (!maspik_logtable_exists()) {
        echo "<p>" . esc_html__('The spam log table does not exist.', 'contact-forms-anti-spam') . "</p>";
        return;
    }
    
    if (maspik_logtable_exists()) {
        $table = maspik_get_logtable();
        
        // Debug: Check table name
        if (empty($table)) {
            echo "<p>Error: Table name is empty</p>";
            return;
        }
        
        $sql = "SELECT * FROM $table ORDER BY id DESC";
        $results = $wpdb->get_results($sql, ARRAY_A);
        
        // Check for database errors
        if ($wpdb->last_error) {
            echo "<p>Database error: " . esc_html($wpdb->last_error) . "</p>";
            return;
        }
        
        // Debug: Check number of results
        if ($results === false) {
            echo "<p>Error: Query failed</p>";
            return;
        }
        
        if (empty($results)) {
            echo "<p>" . esc_html__('No spam attempts were recorded and stored in the spam log during the selected time period.', 'contact-forms-anti-spam') . "</p>";
            echo "<p>Debug: Found " . count($results) . " results</p>";
            return;
        }
        
        ?>
        <button class="button log-expand maspik-btn" type="submit" name="expand-all" id="expand-all"><?php esc_html_e('Expand all', 'contact-forms-anti-spam' ); ?></button>
        <?php
        echo "<table class='maspik-log-table'>";
        echo "<tr class='header-row'>
                <th class='maspik-log-column column-type'>" . esc_html__('Type', 'contact-forms-anti-spam') . "</th>
                <th class='maspik-log-column column-value'>" . esc_html__('Data & Reason', 'contact-forms-anti-spam') . "</th>
                <th class='maspik-log-column column-ip'>" . esc_html__('IP', 'contact-forms-anti-spam') . "</th>
                <th class='maspik-log-column column-country'>" . esc_html__('Country', 'contact-forms-anti-spam') . "</th>
                <th class='maspik-log-column column-agent'>" . esc_html__('User Agent', 'contact-forms-anti-spam') . "</th>
                <th class='maspik-log-column column-date'>" . esc_html__('Date', 'contact-forms-anti-spam') . "</th>
                <th class='maspik-log-column column-source'>" . esc_html__('Source', 'contact-forms-anti-spam') . "</th>
            </tr>";

        $row_count = 0;
        foreach ($results as $row) {
            $row_class = ($row_count % 2 == 0) ? 'even' : 'odd';
            $row_id = isset($row['id']) ? $row['id'] : '';
            $spam_value = isset($row['spamsrc_val']) ? esc_html($row['spamsrc_val']) : '';
            $spam_type = isset($row['spam_type']) ? esc_html($row['spam_type']) : '';
            $spam_date = isset($row['spam_date']) && $row['spam_date'] ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($row['spam_date'])) : '';
            $spam_val_intext = isset($row['spamsrc_label']) && !empty($row['spamsrc_label']) ? esc_html(maspik_get_field_display_name($row['spamsrc_label'])) : '';
            $not_spam_tag = isset($row['spam_tag']) && esc_html($row['spam_tag']) == "not spam" ? " not-a-spam" : "";
            $spamsrc_label = isset($row['spamsrc_label']) ? esc_html($row['spamsrc_label']) : '';
            
            // Process form data
            $form_data = maspik_process_form_data(isset($row['spam_detail']) ? $row['spam_detail'] : '');

            // Process source
            $spam_source = maspik_process_spam_source(isset($row['spam_source']) ? $row['spam_source'] : '');

            // Show all rows regardless of spam_tag
            if (isset($row['spam_tag']) && $row['spam_tag'] != "spam") {
                echo "<tr class='row-entries row-$row_class $not_spam_tag'>
                        <td class='column-type column-entries'>
                            " . $spam_type . "
                            " . maspik_spam_item_option($row_id, $spam_value, $spam_type) . "

                        </td>
                        <td class='column-value column-entries'>
                            <div class='value-content-container'>
                                <div class='spam-value-text'>" . (isset($row['spam_value']) ? esc_html($row['spam_value']) : '') . "</div>
                                <button class='details-toggle-btn' aria-expanded='false'>
                                    <span class='dashicons dashicons-arrow-down details-icon'></span>
                                    <span class='details-text'>" . esc_html__('Show Details', 'contact-forms-anti-spam') . "</span>
                                </button>
                                <div class='details-panel'>
                                    " . $form_data . "
                                </div>
                            </div>
                        </td>
                        <td class='column-ip column-entries'>" . (isset($row['spam_ip']) ? esc_html($row['spam_ip']) : '') . "</td>
                        <td class='column-country column-entries'>" . (isset($row['spam_country']) ? esc_html($row['spam_country']) : '') . "</td>
                        <td class='column-agent column-entries'>" . (isset($row['spam_agent']) ? esc_html($row['spam_agent']) : '') . "</td>
                        <td class='column-date column-entries'>" . $spam_date . "</td>
                        <td class='column-source column-entries'>" . $spam_source . "</td>
                    </tr>";
            }
            $row_count++;
        }
        echo "</table>";
    }
}

// Helper function to process form data
function maspik_process_form_data($raw_data) {
    if (empty($raw_data)) {
        return '<p>No form data available.</p>';
    }
    
    $unserialize_array = @unserialize($raw_data);
    if (is_array($unserialize_array)) {
        $form_data = '<table class="details-table" style="width: 100%; border-collapse: collapse; margin-top: 10px;">';
        $form_data .= '<thead><tr style="background-color: #f8f8f8;">';
        $form_data .= '<th style="padding: 8px; border: 1px solid #ddd; text-align: left;">' . esc_html__('Field', 'contact-forms-anti-spam') . '</th>';
        $form_data .= '<th style="padding: 8px; border: 1px solid #ddd; text-align: left;">' . esc_html__('Value', 'contact-forms-anti-spam') . '</th>';
        $form_data .= '</tr></thead><tbody>';
        processArray($unserialize_array, $form_data);
        $form_data .= '</tbody></table>';
        return $form_data;
    }
    
    // If it's not an array, try to decode JSON
    $json_data = json_decode($raw_data, true);
    if (is_array($json_data)) {
        $form_data = '<table class="details-table" style="width: 100%; border-collapse: collapse; margin-top: 10px;">';
        $form_data .= '<thead><tr style="background-color: #f8f8f8;">';
        $form_data .= '<th style="padding: 8px; border: 1px solid #ddd; text-align: left;">' . esc_html__('Field', 'contact-forms-anti-spam') . '</th>';
        $form_data .= '<th style="padding: 8px; border: 1px solid #ddd; text-align: left;">' . esc_html__('Value', 'contact-forms-anti-spam') . '</th>';
        $form_data .= '</tr></thead><tbody>';
        processArray($json_data, $form_data);
        $form_data .= '</tbody></table>';
        return $form_data;
    }
    
    // If it's just a string, display it as is
    return "<pre style='background: #f8f8f8; padding: 10px; border: 1px solid #ddd; border-radius: 3px; overflow-x: auto;'>" . esc_html($raw_data) . "</pre>";
}

// Helper function to process spam source
function maspik_process_spam_source($source) {
    if (empty($source)) {
        return '';
    }
    
    if (strpos($source, '|||') !== false) {
        list($source_type, $url) = explode('|||', $source);
        $url = htmlspecialchars($url);
        $back_id = url_to_postid($url);
        $title = $back_id > 0 ? get_the_title($back_id) : "Page";
        return esc_html($source_type) . ' <br> <a target="_blank" href="' . esc_url($url) . '">' . esc_html($title) . '</a>';
    }
    return esc_html($source);
}

?>

<div class="wrap spam-log-wrap">

<div class= "maspik-setting-header">
  <div class="notice-pointer"><h2></h2></div>

    <?php 
      echo "<div class='upsell-btn " . maspik_add_pro_class() . "'>";
      maspik_get_pro();
      maspik_activate_license();
      echo "</div>";
                
    ?>
    <div class="maspik-setting-header-wrap">
      <h1 class="maspik-title">MASPIK.</h1>
        <?php
          echo '<h3 class="maspik-protag '. maspik_add_pro_class() .'">Pro</h3>';
        ?>
    </div> 
    
</div>

<div class="maspik-spam-head">
                
  <h2 class='maspik-header maspik-spam-header'><?php esc_html_e('Spam Log', 'contact-forms-anti-spam'); ?></h2>
    <p>
      <?php esc_html_e("Whenever a bot/person tries to spam your contact forms and MASPIK blocks the spam, you will see a new line below showing the details. The log containing these details resides on your database and you can reset it at any time. Resetting the log doesn't change anything – it just removes the history.", 'contact-forms-anti-spam' ); ?>
    </p>

    <div class='spam-log-button-wrapper'>
      <form method="post" onsubmit="return confirm('Are you sure you want to clear the Spam log? This action cannot be undone.')">
        <?php wp_nonce_field( 'cfes_clear_log_action', 'cfes_clear_log_nonce' ); ?>
        <button class="button log-reset maspik-btn" type="submit" name="clear_log" id="clear_log"><?php esc_html_e('Reset Log', 'contact-forms-anti-spam' ); ?></button>
      </form>

      <?php echo maspik_Download_log_btn(); ?>

      <a href="<?php echo esc_url(admin_url('admin.php?page=maspik-statistics')); ?>" class="maspik-btn-self maspik-btn maspik-stats-btn">
        <span class="dashicons dashicons-chart-bar"></span>
        <?php esc_html_e('View Statistics', 'contact-forms-anti-spam'); ?>
      </a>
    </div>

    <style>
    .spam-log-button-wrapper {
        display: flex;
        gap: 10px;
        align-items: center;
        margin-bottom: 20px;
    }
    .maspik-stats-btn .dashicons {
        font-size: 18px;
        width: 18px;
        height: 18px;
    }
    </style>

      <p><?php echo "<b>".maspik_spam_count()."</b> ";  esc_html_e('spam entries currently stored in the Spam Log', 'contact-forms-anti-spam' ); 

  if( get_option("spamcounter") ){ ?>
    <?php echo ", <b>".get_option("spamcounter")."</b> ";  esc_html_e('spam attempts blocked since installing', 'contact-forms-anti-spam' ); ?></p>
  <?php } 
  ?>

</div>

<!-- Delete Confirmation Modal -->
<div id="confirmation-modal" class="modal">
    <div class="modal-content">
      <div class= "modal-content-inner">
        <span class="close-button">&times;</span>
        <p id="confirmation-message">Are you sure you want to delete this row?</p>
        <button id="confirm-delete" class="del-spam-button del-spam-button-primary">Yes, Delete</button>
        <button id="cancel-delete" class="del-spam-button del-spam-button-secondary">Cancel</button>
      </div>
    </div> 
</div>


<!-- Filter Confirmation Modal -->
<div id="filter-delete-modal" class="modal">
    <div class="modal-content">
      <div class= "modal-content-inner">
        <span class="close-button">&times;</span>
        <p id="filter-type">Delete this filter?</p>
        <button id="confirm-del-filter" class="del-filter-button del-filter-button-primary">Yes, Delete</button>
        <button id="cancel-del-filter" class="del-filter-button del-filter-button-secondary">Cancel</button>
      </div>
    </div> 
</div>

<!-- False Positive Report Modal -->
<div id="false-positive-modal" class="modal">
    <div class="modal-content fp-modal-content">
      <div class="modal-content-inner fp-modal-inner">
        <span class="close-button">&times;</span>
        <div class="fp-modal-header">
          <span class="dashicons dashicons-flag fp-icon"></span>
          <h3 class="fp-modal-title"><?php esc_html_e('Mark as Not Spam', 'contact-forms-anti-spam'); ?></h3>
        </div>
        <div class="fp-modal-body">
          <p class="fp-main-message"><?php esc_html_e('This entry was incorrectly flagged as spam. Help us improve Maspik by reporting this false positive.', 'contact-forms-anti-spam'); ?></p>
          <p class="fp-sub-message"><?php esc_html_e('Your report will help us improve our spam detection algorithms and prevent similar false positives in the future.', 'contact-forms-anti-spam'); ?></p>
        </div>
        <div class="fp-modal-actions">
          <button id="fp-send-report" class="fp-button fp-button-primary">
            <span class="dashicons dashicons-yes-alt"></span>
            <?php esc_html_e('Yes, Send Report', 'contact-forms-anti-spam'); ?>
          </button>
          <button id="fp-skip-report" class="fp-button fp-button-secondary">
            <span class="dashicons dashicons-flag"></span>
            <?php esc_html_e('Mark as Not Spam Only', 'contact-forms-anti-spam'); ?>
          </button>
          <button id="fp-cancel" class="fp-button fp-button-link"><?php esc_html_e('Cancel', 'contact-forms-anti-spam'); ?></button>
        </div>
        <div id="fp-modal-feedback" class="fp-modal-feedback" style="display:none;">
          <span class="dashicons dashicons-yes-alt fp-feedback-icon"></span>
          <p id="fp-modal-feedback-text"></p>
        </div>
      </div>
    </div>
</div>

<style>
.fp-modal-content {
  max-width: 500px;
}

.fp-modal-inner {
  padding: 30px;
  text-align: center;
}

.fp-modal-header {
  margin-bottom: 20px;
}

.fp-icon {
  font-size: 48px;
  width: 48px;
  height: 48px;
  color: #2271b1;
  margin-bottom: 10px;
  display: block;
}

.fp-modal-title {
  margin: 0;
  font-size: 22px;
  font-weight: 600;
  color: #1d2327;
}

.fp-modal-body {
  margin-bottom: 25px;
  text-align: left;
}

.fp-main-message {
  font-size: 15px;
  line-height: 1.6;
  color: #1d2327;
  margin: 0 0 12px 0;
}

.fp-sub-message {
  font-size: 13px;
  line-height: 1.5;
  color: #646970;
  margin: 0;
}

.fp-modal-feedback {
  text-align: center;
  padding: 20px 0;
}
.fp-modal-feedback .fp-feedback-icon {
  font-size: 48px;
  width: 48px;
  height: 48px;
  color: #00a32a;
  display: block;
  margin: 0 auto 12px;
}
.fp-modal-feedback p {
  margin: 0;
  font-size: 1.05em;
  color: #1d2327;
}

.fp-modal-actions {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.fp-button {
  padding: 12px 20px;
  font-size: 14px;
  font-weight: 500;
  border: none;
  border-radius: 4px;
  cursor: pointer;
  transition: all 0.2s ease;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  text-decoration: none;
}

.fp-button .dashicons {
  font-size: 18px;
  width: 18px;
  height: 18px;
}

.fp-button-primary {
  background-color: #2271b1;
  color: #fff;
}

.fp-button-primary:hover {
  background-color: #135e96;
  color: #fff;
}

.fp-button-secondary {
  background-color: #f0f0f1;
  color: #2c3338;
}

.fp-button-secondary:hover {
  background-color: #dcdcde;
  color: #2c3338;
}

.fp-button-link {
  background: transparent;
  color: #646970;
  padding: 8px;
  font-weight: 400;
}

.fp-button-link:hover {
  color: #2271b1;
  background: transparent;
}
</style>


          
  <div id="icon-themes" class="icon32"></div>   
  <?php settings_errors(); ?>  
  <div class="log-warp">
      
    <?php
    if(maspik_spam_count()){
      echo cfes_build_table();
    } else {
      echo "<div class='spam-empty-log'><h4>Empty log</h4></div>";
    }
      
  ?>
  </div>
  </div>
<?php echo get_maspik_footer(); ?>
    <style>
    .log-warp tbody {
        max-width: 100%;
    }
    </style>


<script>

<?php
  wp_enqueue_script('maspik-spamlog', plugin_dir_url(__FILE__) . '../js/maspik-spamlog.js', array('jquery'), MASPIK_VERSION, true);
  wp_localize_script('maspik-spamlog', 'maspikAdmin', array(
      'nonce' => wp_create_nonce('maspik_delete_action'),
      'ajaxurl' => admin_url('admin-ajax.php')
  ));
?>

//Accordion Script - START

var acc = document.getElementsByClassName("detail-show");
var toggleAllBtn = document.getElementById("expand-all");
var allExpanded = false;  // Track whether all sections are expanded or not

// Existing code for individual accordion toggle
for (var i = 0; i < acc.length; i++) {
    acc[i].addEventListener("click", function() {
        this.classList.toggle("active");
        var panel = this.parentElement.parentElement.nextElementSibling;
        if (panel.style.maxHeight) {
            panel.style.maxHeight = null;
            this.parentElement.parentElement.parentElement.classList.remove("expanded");
        } else {
            panel.style.maxHeight = (panel.scrollHeight) + 'px';
            this.parentElement.parentElement.parentElement.classList.add("expanded");
        }
    });
}

// Function to toggle all accordion sections
toggleAllBtn.addEventListener("click", function() {
    if (allExpanded) {
        // Collapse all sections
        for (var i = 0; i < acc.length; i++) {
            var panel = acc[i].parentElement.parentElement.nextElementSibling;
            acc[i].classList.remove("active");
            panel.style.maxHeight = null;
            acc[i].parentElement.parentElement.parentElement.classList.remove("expanded");
        }
        toggleAllBtn.textContent = "Expand All";  // Change button text
    } else {
        // Expand all sections
        for (var i = 0; i < acc.length; i++) {
            var panel = acc[i].parentElement.parentElement.nextElementSibling;
            acc[i].classList.add("active");
            panel.style.maxHeight = panel.scrollHeight + 'px';
            acc[i].parentElement.parentElement.parentElement.classList.add("expanded");
        }
        toggleAllBtn.textContent = "Collapse All";  // Change button text
    }
    allExpanded = !allExpanded;  // Toggle the state
});

//Accordion Script -- END

// Replace asterisks with proper opening and closing <u> tags
document.querySelectorAll('.spam-value-text').forEach(element => {
    let text = element.innerHTML;

    // First replace *!text!* format
    text = text.replace(/\*!(.*?)!\*/g, '<u>$1</u>');
    
    // to support old format, will be removed in the future
    text = text.replace(/\*(.*?)\*/g, '<u>$1</u>');
    
    element.innerHTML = text;
});

document.addEventListener('DOMContentLoaded', function() {
    let rowIdToDelete = null; // Add a global variable
    let spamValueToDelete = null;
    let spamTypeToDelete = null;
    const filterDeleteModal = document.getElementById('filter-delete-modal');
    const fpReportModal = document.getElementById('false-positive-modal');
    const fpSendBtn = document.getElementById('fp-send-report');
    const fpSkipBtn = document.getElementById('fp-skip-report');
    const fpCancelBtn = document.getElementById('fp-cancel');
    const ajaxUrl = (typeof maspikAdmin !== 'undefined' && (maspikAdmin.ajax_url || maspikAdmin.ajaxurl)) ? (maspikAdmin.ajax_url || maspikAdmin.ajaxurl) : (typeof ajaxurl !== 'undefined' ? ajaxurl : '');

    const fpModalInner = fpReportModal ? fpReportModal.querySelector('.fp-modal-inner') : null;
    const fpModalHeader = fpReportModal ? fpReportModal.querySelector('.fp-modal-header') : null;
    const fpModalBody = fpReportModal ? fpReportModal.querySelector('.fp-modal-body') : null;
    const fpModalActions = fpReportModal ? fpReportModal.querySelector('.fp-modal-actions') : null;
    const fpModalFeedback = document.getElementById('fp-modal-feedback');
    const fpModalFeedbackText = document.getElementById('fp-modal-feedback-text');

    function showFpFeedback(message) {
        if (!fpModalFeedback || !fpModalFeedbackText) return;
        if (fpModalHeader) fpModalHeader.style.display = 'none';
        if (fpModalBody) fpModalBody.style.display = 'none';
        if (fpModalActions) fpModalActions.style.display = 'none';
        fpModalFeedbackText.textContent = message;
        fpModalFeedback.style.display = 'block';
    }

    function resetFpModalContent() {
        if (fpModalHeader) fpModalHeader.style.display = '';
        if (fpModalBody) fpModalBody.style.display = '';
        if (fpModalActions) fpModalActions.style.display = '';
        if (fpModalFeedback) fpModalFeedback.style.display = 'none';
    }

    function closeFpModal() {
        if (fpReportModal) {
            fpReportModal.style.display = 'none';
            resetFpModalContent();
        }
    }

    function closeFilterModal() {
        if (filterDeleteModal) {
            filterDeleteModal.style.display = 'none';
        }
    }

    function markRowNotSpamInDom(rowId) {
        const rowToUpdate = document.querySelector(`[data-row-id="${rowId}"]`)?.closest('tr');
        if (rowToUpdate) {
            rowToUpdate.classList.add('not-a-spam');
        }
    }

    function sendNotSpamRequest(sendReport) {
        // Ensure spam_type is set
        var spamType = spamTypeToDelete || '';
        
        jQuery.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'maspik_not_spam',
                nonce: maspikAdmin.nonce,
                row_id: rowIdToDelete,
                spam_value: spamValueToDelete,
                spam_type: spamType,
                send_report: sendReport ? 1 : 0
            },
            success: function(response) {
                if (response && response.success) {
                    markRowNotSpamInDom(rowIdToDelete);
                }
            }
        });
    }

    // Handle Delete Action - handled by maspik-spamlog.js (spam-delete-button)

    // Handle Not Spam Action
    document.querySelectorAll('.not-spam-action').forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation(); // Stop event from bubbling to jQuery handlers
            e.stopImmediatePropagation(); // Stop other handlers on the same element
            // Save the data in variables
            rowIdToDelete = this.dataset.rowId;
            spamValueToDelete = this.dataset.spamValue;
            // Get spam_type from data attribute (data-spam-type becomes dataset.spamType in JavaScript)
            spamTypeToDelete = this.dataset.spamType || this.getAttribute('data-spam-type') || '';

            // Always show false positive report modal for all spam types
            if (fpReportModal) {
                fpReportModal.style.display = 'block';

                if (fpSendBtn) {
                    fpSendBtn.onclick = function() {
                        showFpFeedback('<?php echo esc_js(__('Report sent. Thank you!', 'contact-forms-anti-spam')); ?>');
                        sendNotSpamRequest(true);
                        setTimeout(closeFpModal, 1500);
                    };
                }

                if (fpSkipBtn) {
                    fpSkipBtn.onclick = function() {
                        showFpFeedback('<?php echo esc_js(__('Marked as not spam.', 'contact-forms-anti-spam')); ?>');
                        sendNotSpamRequest(false);
                        setTimeout(closeFpModal, 1500);
                    };
                }

                if (fpCancelBtn) {
                    fpCancelBtn.onclick = function() {
                        closeFpModal();
                    };
                }
            }
        });
    });

    // Handle Details Toggle
    document.querySelectorAll('.details-toggle-btn').forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Toggle active state on button
            this.classList.toggle('active');
            
            // Find the details panel
            const detailsPanel = this.closest('.value-content-container')
                                   .querySelector('.details-panel');
            
            // Toggle panel visibility
            detailsPanel.classList.toggle('active');
            
            // Update button text and icon
            const buttonText = this.classList.contains('active') ? 
                '<?php echo esc_js(__('Hide Details', 'contact-forms-anti-spam')); ?>' : 
                '<?php echo esc_js(__('Show Details', 'contact-forms-anti-spam')); ?>';
            
            this.innerHTML = `
                <span class='dashicons dashicons-arrow-down details-icon'></span>
                ${buttonText}
            `;
        });
    });

    // Handle Expand All functionality
    const toggleAllBtn = document.getElementById('expand-all');
    let allExpanded = false;

    toggleAllBtn.addEventListener('click', function() {
        const detailButtons = document.querySelectorAll('.details-toggle-btn');
        const newState = !allExpanded;
        
        detailButtons.forEach(button => {
            const detailsPanel = button.closest('.value-content-container')
                                     .querySelector('.details-panel');
            
            if (newState) {
                button.classList.add('active');
                detailsPanel.classList.add('active');
                button.innerHTML = `
                    <span class='dashicons dashicons-arrow-down details-icon'></span>
                    <?php echo esc_js(__('Hide Details', 'contact-forms-anti-spam')); ?>
                `;
            } else {
                button.classList.remove('active');
                detailsPanel.classList.remove('active');
                button.innerHTML = `
                    <span class='dashicons dashicons-arrow-down details-icon'></span>
                    <?php echo esc_js(__('Show Details', 'contact-forms-anti-spam')); ?>
                `;
            }
        });
        
        allExpanded = newState;
        this.textContent = allExpanded ? 
            '<?php echo esc_js(__('Collapse All', 'contact-forms-anti-spam')); ?>' : 
            '<?php echo esc_js(__('Expand All', 'contact-forms-anti-spam')); ?>';
    });
});

</script>

