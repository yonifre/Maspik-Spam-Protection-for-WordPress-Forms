<?php
// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

?>
<div class="wrap" id="exp-imp-settings">
    
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

        <h2 class='maspik-header maspik-spam-header'><?php esc_html_e('Maspik Import/Export Settings', 'contact-forms-anti-spam'); ?></h2>

            <p>
                <?php echo esc_html__('Please note that importing/exporting settings will affect most of the Maspik configuration.', 'contact-forms-anti-spam'); ?><br>
                <?php echo esc_html__('Imported settings will override existing ones, and there is no option to undo the imported settings.', 'contact-forms-anti-spam'); ?><br>
                <?php echo esc_html__('Use this feature with caution.', 'contact-forms-anti-spam'); ?>
            </p>
    </div>
    <hr>
    <!-- Export form -->
    <h2><?php echo esc_html__('Export Settings', 'contact-forms-anti-spam'); ?></h2>
    <form id="export-settings-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        
        <input type="hidden" name="action" value="Maspik_export_settings">
        
        <?php wp_nonce_field('Maspik_export_settings_nonce', 'Maspik_export_settings_nonce_field'); ?>
        
        <?php submit_button(__('Export Settings', 'contact-forms-anti-spam'),'export-import-btn'); ?>
    </form>
    <hr>

    <!-- Import form -->
    <h2><?php echo esc_html__('Import Settings', 'contact-forms-anti-spam'); ?></h2>
    <form id="import-settings-form" method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <input type="hidden" name="action" value="Maspik_import_settings">
        <?php wp_nonce_field('Maspik_import_settings_nonce', 'Maspik_import_settings_nonce_field'); ?>
        <input type="file" name="maspik-settings">
        <?php submit_button(__('Import Settings', 'contact-forms-anti-spam'),'export-import-btn'); ?>
    </form>
</div>
<?php echo get_maspik_footer(); ?>


<?php