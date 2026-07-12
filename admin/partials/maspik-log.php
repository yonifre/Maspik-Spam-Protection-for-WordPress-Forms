<?php
// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

if ( isset( $_POST['clear_log'] ) ) {
    if ( ! isset( $_POST['cfes_clear_log_nonce'] ) || ! wp_verify_nonce( $_POST['cfes_clear_log_nonce'], 'cfes_clear_log_action' ) ) {
        wp_die( __( 'Security check failed. Please try again.', 'contact-forms-anti-spam' ) );
    }
    if ( ! maspik_user_can_manage_settings() ) {
        wp_die( __( 'You do not have sufficient permissions to access this page.', 'contact-forms-anti-spam' ) );
    }

    global $wpdb;
    $table = maspik_get_logtable();
    $wpdb->query( "DELETE FROM $table" );

    wp_safe_redirect( admin_url( 'admin.php?page=maspik-log.php' ) );
    exit;
}

// Helper functions (maspik_spam_item_option, maspik_process_form_data,
// maspik_process_spam_source, maspik_build_log_rows) are defined in includes/functions.php.

function cfes_build_table() {
    global $wpdb;

    if ( ! maspik_logtable_exists() ) {
        echo '<p>' . esc_html__( 'The spam log table does not exist.', 'contact-forms-anti-spam' ) . '</p>';
        return;
    }

    $table = maspik_get_logtable();

    // Distinct spam types for the filter dropdown.
    $spam_types = $wpdb->get_col( "SELECT DISTINCT spam_type FROM $table WHERE spam_type != '' ORDER BY spam_type ASC" );

    // Initial PHP render: page 1, 200 per page, newest first.
    $default_per_page = 200;
    $total            = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table" );
    $results          = $wpdb->get_results(
        $wpdb->prepare( "SELECT * FROM $table ORDER BY id DESC LIMIT %d OFFSET %d", $default_per_page, 0 ),
        ARRAY_A
    );
    $total_pages = (int) ceil( $total / $default_per_page );
    $showing_end = min( $default_per_page, $total );

    ?>
    <div class="maspik-log-controls">

        <div class="maspik-log-controls-top">
            <div class="maspik-log-filters">

                <div class="maspik-filter-group">
                    <label class="maspik-filter-label" for="maspik-filter-type">
                        <span class="dashicons dashicons-category"></span>
                        <?php esc_html_e( 'Type', 'contact-forms-anti-spam' ); ?>
                    </label>
                    <select id="maspik-filter-type" class="maspik-filter-input">
                        <option value=""><?php esc_html_e( 'All Types', 'contact-forms-anti-spam' ); ?></option>
                        <?php foreach ( $spam_types as $type ) : ?>
                            <option value="<?php echo esc_attr( $type ); ?>"><?php echo esc_html( $type ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="maspik-filter-group">
                    <label class="maspik-filter-label" for="maspik-filter-ip">
                        <span class="dashicons dashicons-networking"></span>
                        <?php esc_html_e( 'IP', 'contact-forms-anti-spam' ); ?>
                    </label>
                    <input type="text" id="maspik-filter-ip" class="maspik-filter-input"
                        placeholder="<?php esc_attr_e( 'e.g. 192.168…', 'contact-forms-anti-spam' ); ?>">
                </div>

                <div class="maspik-filter-group">
                    <label class="maspik-filter-label" for="maspik-filter-country">
                        <span class="dashicons dashicons-location"></span>
                        <?php esc_html_e( 'Country', 'contact-forms-anti-spam' ); ?>
                    </label>
                    <input type="text" id="maspik-filter-country" class="maspik-filter-input">
                </div>

                <div class="maspik-filter-group">
                    <label class="maspik-filter-label" for="maspik-filter-source">
                        <span class="dashicons dashicons-admin-links"></span>
                        <?php esc_html_e( 'Source', 'contact-forms-anti-spam' ); ?>
                    </label>
                    <input type="text" id="maspik-filter-source" class="maspik-filter-input">
                </div>

                <fieldset class="maspik-filter-group maspik-filter-group--date">
                    <legend class="maspik-filter-label">
                        <span class="dashicons dashicons-calendar-alt"></span>
                        <?php esc_html_e( 'Date range', 'contact-forms-anti-spam' ); ?>
                    </legend>
                    <div class="maspik-date-range">
                        <input type="date" id="maspik-filter-date-from" class="maspik-filter-input maspik-date-input"
                            aria-label="<?php esc_attr_e( 'From date', 'contact-forms-anti-spam' ); ?>">
                        <span class="maspik-date-sep">→</span>
                        <input type="date" id="maspik-filter-date-to" class="maspik-filter-input maspik-date-input"
                            aria-label="<?php esc_attr_e( 'To date', 'contact-forms-anti-spam' ); ?>">
                    </div>
                </fieldset>

            </div>

            <button id="maspik-clear-filters" class="maspik-clear-btn" title="<?php esc_attr_e( 'Clear all filters', 'contact-forms-anti-spam' ); ?>">
                <span class="dashicons dashicons-dismiss"></span>
                <?php esc_html_e( 'Clear', 'contact-forms-anti-spam' ); ?>
            </button>
        </div>

        <div class="maspik-log-toolbar">
            <button class="log-expand maspik-btn" id="expand-all">
                <span class="dashicons dashicons-arrow-down-alt2"></span>
                <?php esc_html_e( 'Expand All', 'contact-forms-anti-spam' ); ?>
            </button>

            <div class="maspik-toolbar-right">
                <label class="maspik-per-page-label" for="maspik-per-page">
                    <span class="dashicons dashicons-list-view"></span>
                    <?php esc_html_e( 'Rows per page:', 'contact-forms-anti-spam' ); ?>
                </label>
                <select id="maspik-per-page" class="maspik-per-page-select">
                    <option value="50">50</option>
                    <option value="100">100</option>
                    <option value="200" selected>200</option>
                    <option value="500">500</option>
                    <option value="-1"><?php esc_html_e( 'All', 'contact-forms-anti-spam' ); ?></option>
                </select>
            </div>
        </div>

    </div>

    <div id="maspik-log-loading" class="maspik-log-loading" style="display:none;" aria-live="polite" aria-label="<?php esc_attr_e( 'Loading…', 'contact-forms-anti-spam' ); ?>">
        <span class="maspik-spinner"></span>
    </div>

    <div id="maspik-log-table-wrap">
        <table class="maspik-log-table">
            <thead>
                <tr class="header-row">
                    <th class="maspik-log-column column-type sortable" data-col="type">
                        <?php esc_html_e( 'Type', 'contact-forms-anti-spam' ); ?>
                        <span class="sort-indicator" aria-hidden="true"></span>
                    </th>
                    <th class="maspik-log-column column-value">
                        <?php esc_html_e( 'Data & Reason', 'contact-forms-anti-spam' ); ?>
                    </th>
                    <th class="maspik-log-column column-ip sortable" data-col="ip">
                        <?php esc_html_e( 'IP', 'contact-forms-anti-spam' ); ?>
                        <span class="sort-indicator" aria-hidden="true"></span>
                    </th>
                    <th class="maspik-log-column column-country sortable" data-col="country">
                        <?php esc_html_e( 'Country', 'contact-forms-anti-spam' ); ?>
                        <span class="sort-indicator" aria-hidden="true"></span>
                    </th>
                    <th class="maspik-log-column column-agent sortable" data-col="agent">
                        <?php esc_html_e( 'User Agent', 'contact-forms-anti-spam' ); ?>
                        <span class="sort-indicator" aria-hidden="true"></span>
                    </th>
                    <th class="maspik-log-column column-date sortable sort-active sort-desc" data-col="date">
                        <?php esc_html_e( 'Date', 'contact-forms-anti-spam' ); ?>
                        <span class="sort-indicator" aria-hidden="true">▼</span>
                    </th>
                    <th class="maspik-log-column column-source sortable" data-col="source">
                        <?php esc_html_e( 'Source', 'contact-forms-anti-spam' ); ?>
                        <span class="sort-indicator" aria-hidden="true"></span>
                    </th>
                </tr>
            </thead>
            <tbody id="maspik-log-tbody">
                <?php echo maspik_build_log_rows( $results ); ?>
            </tbody>
        </table>
    </div>

    <div id="maspik-log-pagination" class="maspik-log-pagination">
        <div class="maspik-pagination-info">
            <span id="maspik-pagination-count">
                <?php
                printf(
                    /* translators: 1: first entry number, 2: last entry number, 3: total entries */
                    esc_html__( 'Showing %1$d–%2$d of %3$d entries', 'contact-forms-anti-spam' ),
                    $total > 0 ? 1 : 0,
                    $showing_end,
                    $total
                );
                ?>
            </span>
        </div>
        <div class="maspik-pagination-controls">
            <button id="maspik-first-page" class="button maspik-page-btn" <?php echo ( $total_pages <= 1 ) ? 'disabled' : ''; ?>>
                «
            </button>
            <button id="maspik-prev-page" class="button maspik-page-btn" <?php echo ( $total_pages <= 1 ) ? 'disabled' : ''; ?>>
                ‹ <?php esc_html_e( 'Prev', 'contact-forms-anti-spam' ); ?>
            </button>
            <span class="maspik-page-info">
                <input type="number" id="maspik-page-input" value="1" min="1"
                    max="<?php echo esc_attr( max( 1, $total_pages ) ); ?>"
                    class="maspik-page-input" aria-label="<?php esc_attr_e( 'Page number', 'contact-forms-anti-spam' ); ?>">
                / <span id="maspik-total-pages"><?php echo esc_html( max( 1, $total_pages ) ); ?></span>
            </span>
            <button id="maspik-next-page" class="button maspik-page-btn" <?php echo ( $total_pages <= 1 ) ? 'disabled' : ''; ?>>
                <?php esc_html_e( 'Next', 'contact-forms-anti-spam' ); ?> ›
            </button>
            <button id="maspik-last-page" class="button maspik-page-btn" <?php echo ( $total_pages <= 1 ) ? 'disabled' : ''; ?>>
                »
            </button>
        </div>
    </div>
    <?php
}

?>

<div class="wrap spam-log-wrap">

<div class="maspik-setting-header">
    <div class="notice-pointer"><h2></h2></div>
    <?php
    echo "<div class='upsell-btn " . maspik_add_pro_class() . "'>";
    maspik_get_pro();
    maspik_activate_license();
    echo "</div>";
    ?>
    <div class="maspik-setting-header-wrap">
        <h1 class="maspik-title">MASPIK.</h1>
        <?php echo '<h3 class="maspik-protag ' . maspik_add_pro_class() . '">Pro</h3>'; ?>
    </div>
</div>

<div class="maspik-spam-head">
    <h2 class="maspik-header maspik-spam-header"><?php esc_html_e( 'Spam Log', 'contact-forms-anti-spam' ); ?></h2>
    <p><?php esc_html_e( "Whenever a bot/person tries to spam your contact forms and MASPIK blocks the spam, you will see a new line below showing the details. The log containing these details resides on your database and you can reset it at any time. Resetting the log doesn't change anything – it just removes the history.", 'contact-forms-anti-spam' ); ?></p>

    <div class="spam-log-button-wrapper">
        <?php if ( maspik_user_can_manage_settings() ) : ?>
        <form method="post" id="reset-log-form">
            <?php wp_nonce_field( 'cfes_clear_log_action', 'cfes_clear_log_nonce' ); ?>
            <input type="hidden" name="clear_log" value="1">
            <button class="button log-reset maspik-btn" type="button" id="reset-log-btn">
                <?php esc_html_e( 'Reset Log', 'contact-forms-anti-spam' ); ?>
            </button>
        </form>
        <?php endif; ?>
        <?php echo maspik_Download_log_btn(); ?>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=maspik-statistics' ) ); ?>" class="maspik-btn-self maspik-btn maspik-stats-btn">
            <span class="dashicons dashicons-chart-bar"></span>
            <?php esc_html_e( 'View Statistics', 'contact-forms-anti-spam' ); ?>
        </a>
    </div>

    <p>
        <?php
        echo '<b>' . maspik_spam_count() . '</b> ';
        esc_html_e( 'spam entries currently stored in the Spam Log', 'contact-forms-anti-spam' );
        if ( get_option( 'spamcounter' ) ) {
            echo ', <b>' . get_option( 'spamcounter' ) . '</b> ';
            esc_html_e( 'spam attempts blocked since installing', 'contact-forms-anti-spam' );
        }
        ?>
    </p>
</div>

<!-- Delete Confirmation Modal -->
<div id="confirmation-modal" class="modal" role="dialog" aria-modal="true" aria-labelledby="confirmation-message">
    <div class="modal-content">
        <div class="modal-content-inner">
            <span class="close-button" role="button" tabindex="0" aria-label="<?php esc_attr_e( 'Close', 'contact-forms-anti-spam' ); ?>">&times;</span>
            <p id="confirmation-message"><?php esc_html_e( 'Are you sure you want to delete this row?', 'contact-forms-anti-spam' ); ?></p>
            <button id="confirm-delete" class="del-spam-button del-spam-button-primary"><?php esc_html_e( 'Yes, Delete', 'contact-forms-anti-spam' ); ?></button>
            <button id="cancel-delete" class="del-spam-button del-spam-button-secondary"><?php esc_html_e( 'Cancel', 'contact-forms-anti-spam' ); ?></button>
        </div>
    </div>
</div>

<!-- Filter Confirmation Modal -->
<div id="filter-delete-modal" class="modal" role="dialog" aria-modal="true" aria-labelledby="filter-type">
    <div class="modal-content">
        <div class="modal-content-inner">
            <span class="close-button" role="button" tabindex="0" aria-label="<?php esc_attr_e( 'Close', 'contact-forms-anti-spam' ); ?>">&times;</span>
            <p id="filter-type"><?php esc_html_e( 'Delete this filter?', 'contact-forms-anti-spam' ); ?></p>
            <button id="confirm-del-filter" class="del-filter-button del-filter-button-primary"><?php esc_html_e( 'Yes, Delete', 'contact-forms-anti-spam' ); ?></button>
            <button id="cancel-del-filter" class="del-filter-button del-filter-button-secondary"><?php esc_html_e( 'Cancel', 'contact-forms-anti-spam' ); ?></button>
        </div>
    </div>
</div>

<!-- Reset Log Confirmation Modal -->
<div id="reset-log-modal" class="modal" role="dialog" aria-modal="true" aria-labelledby="reset-log-modal-msg">
    <div class="modal-content">
        <div class="modal-content-inner">
            <span class="close-button" role="button" tabindex="0" aria-label="<?php esc_attr_e( 'Close', 'contact-forms-anti-spam' ); ?>">&times;</span>
            <p id="reset-log-modal-msg">
                <strong><?php esc_html_e( 'Reset the entire Spam Log?', 'contact-forms-anti-spam' ); ?></strong><br>
                <span style="font-size:13px;color:#646970;"><?php esc_html_e( 'This will permanently delete all log entries. Your spam protection settings are not affected.', 'contact-forms-anti-spam' ); ?></span>
            </p>
            <button id="confirm-reset-log" class="del-spam-button del-spam-button-primary"><?php esc_html_e( 'Yes, Reset', 'contact-forms-anti-spam' ); ?></button>
            <button id="cancel-reset-log" class="del-spam-button del-spam-button-secondary"><?php esc_html_e( 'Cancel', 'contact-forms-anti-spam' ); ?></button>
        </div>
    </div>
</div>

<!-- False Positive Report Modal -->
<div id="false-positive-modal" class="modal">
    <div class="modal-content fp-modal-content">
        <div class="modal-content-inner fp-modal-inner">
            <div class="fp-modal-header">
                <span class="dashicons dashicons-flag fp-icon"></span>
                <h3 class="fp-modal-title"><?php esc_html_e( 'Mark as Not Spam', 'contact-forms-anti-spam' ); ?></h3>
            </div>
            <div class="fp-modal-body">
                <p class="fp-main-message"><?php esc_html_e( 'This entry was incorrectly flagged as spam? Help us improve MASPIK by reporting this false positive.', 'contact-forms-anti-spam' ); ?></p>
                <p class="fp-sub-message"><?php esc_html_e( 'Your report will help us improve our spam detection algorithms and prevent similar false positives in the future.', 'contact-forms-anti-spam' ); ?></p>
            </div>
            <div class="fp-modal-actions">
                <button id="fp-send-report" class="fp-button fp-button-primary">
                    <span class="dashicons dashicons-yes-alt"></span>
                    <?php esc_html_e( 'Yes, Send Report', 'contact-forms-anti-spam' ); ?>
                </button>
                <button id="fp-skip-report" class="fp-button fp-button-secondary">
                    <span class="dashicons dashicons-flag"></span>
                    <?php esc_html_e( 'Mark as Not Spam Only', 'contact-forms-anti-spam' ); ?>
                </button>
                <button id="fp-cancel" class="fp-button fp-button-link"><?php esc_html_e( 'Cancel', 'contact-forms-anti-spam' ); ?></button>
            </div>
            <div id="fp-modal-feedback" class="fp-modal-feedback" style="display:none;">
                <span class="dashicons dashicons-yes-alt fp-feedback-icon"></span>
                <p id="fp-modal-feedback-text"></p>
            </div>
        </div>
    </div>
</div>

<div id="icon-themes" class="icon32"></div>
<?php settings_errors(); ?>

<div class="log-warp">
    <?php
    if ( maspik_spam_count() ) {
        cfes_build_table();
    } else {
        echo "<div class='spam-empty-log'><h4>" . esc_html__( 'Empty log', 'contact-forms-anti-spam' ) . "</h4></div>";
    }
    ?>
</div>

</div><!-- .wrap.spam-log-wrap -->

<?php echo get_maspik_footer(); ?>

<?php
wp_enqueue_script( 'maspik-spamlog', plugin_dir_url( __FILE__ ) . '../js/maspik-spamlog.js', array( 'jquery' ), MASPIK_VERSION, true );
wp_localize_script( 'maspik-spamlog', 'maspikAdmin', array(
    'nonce'         => wp_create_nonce( 'maspik_delete_action' ),
    'spamlogNonce'  => wp_create_nonce( 'maspik_spamlog_nonce' ),
    'ajaxurl'       => admin_url( 'admin-ajax.php' ),
    'i18n'          => array(
        'showDetails'  => __( 'Show Details', 'contact-forms-anti-spam' ),
        'hideDetails'  => __( 'Hide Details', 'contact-forms-anti-spam' ),
        'expandAll'    => __( 'Expand All', 'contact-forms-anti-spam' ),
        'collapseAll'  => __( 'Collapse All', 'contact-forms-anti-spam' ),
        'showing'      => __( 'Showing %1$d–%2$d of %3$d entries', 'contact-forms-anti-spam' ),
        'reportSent'        => __( 'Report sent. Thank you!', 'contact-forms-anti-spam' ),
        'notSpamDone'       => __( 'Marked as not spam.', 'contact-forms-anti-spam' ),
        'deleteFailed'      => __( 'Failed to delete row.', 'contact-forms-anti-spam' ),
        'serverError'       => __( 'Server error. Please try again.', 'contact-forms-anti-spam' ),
        'filterDeleted'     => __( 'Filter deleted successfully!', 'contact-forms-anti-spam' ),
        'filterDeleteFailed'=> __( 'This filter cannot be deleted automatically. It is either already deleted or comes from the Maspik API Dashboard — try deleting it manually.', 'contact-forms-anti-spam' ),
    ),
    'initialState'  => array(
        'sortCol'     => 'id',
        'sortDir'     => 'DESC',
        'perPage'     => 200,
        'totalPages'  => (int) ceil( maspik_spam_count() / 200 ),
        'total'       => (int) maspik_spam_count(),
    ),
) );
?>
