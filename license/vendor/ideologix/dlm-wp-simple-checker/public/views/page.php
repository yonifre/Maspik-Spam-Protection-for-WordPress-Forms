<?php
/* @var \IdeoLogix\DigitalLicenseManagerSimpleChecker\Configuration $configuration */
/* @var \IdeoLogix\DigitalLicenseManagerSimpleChecker\License $license */

$statusCode = $license->getStatus();
$statusText = $statusCode === $license::STATUS_MISSING_LICENSE_KEY ? __( 'Not Active' ) : ucwords( str_replace( [ '_', '-' ], ' ', $statusCode ) );
?>

<div id="dlm_license_form" class="2">
    <div class="dlm_license_form_head">
        <div id="dlm_license_branding">
        <div class= "maspik-setting-header">
            <div class="notice-pointer"><h2></h2></div>
                <?php 
                echo "<div class='upsell-btn " . maspik_add_pro_class() . "'>";
                maspik_get_pro();
                echo "</div>";
                            
                ?>
                <div class="maspik-setting-header-wrap">
            <h1 class="maspik-title">MASPIK.</h1>
            <?php
                echo '<h3 class="maspik-protag '. maspik_add_pro_class() .'">Pro</h3>';
            ?>
        </div> 
    
</div>
        </div>
        <div id="dlm_license_status">
            <span class="label"><?php esc_html_e( 'License Status' ); ?></span>
            <span class="value <?php echo esc_attr( $statusCode ); ?>"><?php echo esc_html( $statusText ); ?></span>
        </div>
    </div>
    <div class="dlm_license_form_content">
		<?php if ( $license::STATUS_MISSING_TOKEN === $statusCode ): ?><?php
			$licenseData = $license->queryValidateLicenseExpiration();

            $full_license_key = $license->getLicenseKey(); // Yoni
            $first_part = substr( $full_license_key, 0, 3 ); // Yoni
            $last_part = substr( $full_license_key, -3 ); // Yoni
            $hidden_license_key = $first_part . '************************' . $last_part; // Yoni

			?>
            <form method="POST" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <fieldset id="dlm_activate_plugin">
                    <div class="dlm_license_key ">
                        <p class="label"><?php esc_html_e( 'License Key' ); ?></p>
                        <p class="field"><input id="license_key" name="license_key" readonly type="text" value="<?php echo esc_attr( $hidden_license_key ); ?>"></p>
                    </div>
                    <div class="dlm_license_email ">
                        <p class="label"><?php esc_html_e( 'Select activation token' ); ?></p>
                        <p class="field">
                            <select name="activation_token" id="activation_token">
                                <option value="new"><?php esc_html_e( 'Create new activation token' ); ?></option>
								<?php if ( ! empty( $licenseData['activations'] ) ): ?>

									<?php foreach ( $licenseData['activations'] as $activation ): ?>

										<?php if ( !empty($activation['deactivated_at']) ) {
											continue;
										}; ?>
                                        <option value="<?php echo esc_attr( $activation['token'] ); ?>"><?php echo !empty($activation['label']) ? sprintf('%s -> %s', $activation['label'], $activation['token']) : esc_html( $activation['token'] ); ?></option>
									<?php endforeach; ?><?php endif; ?>
                            </select>
                        </p>
                    </div>
                </fieldset>
                <div id="dlm_license_actions">
                    <fieldset class="dlm_license_links"></fieldset>
                    <fieldset class="dlm_license_button">
                        <input type="hidden" name="action" value="<?php echo esc_attr( $this->configuration->prefix . 'activator' ); ?>"/>
                        <input type="hidden" name="type" value="update_token"/>
						<?php echo wp_nonce_field( 'activate_nonce' ); ?>
                        <input type="submit" class="dlm_btn__prim dlm_lmac_btn" name="save" value="<?php esc_html_e( 'Save' ); ?>">
                        <input type="submit" class="dlm_btn__prim dlm_lmdac_btn" name="delete" onclick="return confirm('<?php esc_html_e( "Are you sure? This action cannot be reverted." ); ?>')" value="<?php esc_html_e( 'Delete' ); ?>">
                    </fieldset>
                </div>
            </form>
		<?php elseif ( $license::STATUS_EXPIRED === $statusCode ):

                        $full_license_key = $license->getLicenseKey(); // Yoni
                        $first_part = substr( $full_license_key, 0, 3 ); // Yoni
                        $last_part = substr( $full_license_key, -3 ); // Yoni
                        $hidden_license_key = $first_part . '************************' . $last_part; // Yoni
             ?>

            <form method="POST" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <fieldset id="dlm_activate_plugin">
                    <div class="dlm_license_key ">
                        <p class="label"><?php esc_html_e( 'License Key' ); ?></p>
                        <p class="field"><input id="license_key" name="license_key" readonly type="text" value="<?php echo esc_attr( $hidden_license_key ); ?>"></p>
                    </div>
                </fieldset>
                <div id="dlm_license_actions">
                    <fieldset class="dlm_license_links"></fieldset>
                    <fieldset class="dlm_license_button">
                        <input type="hidden" name="action" value="<?php echo esc_attr( $this->configuration->prefix . 'activator' ); ?>"/>
						<?php echo wp_nonce_field( 'activate_nonce' ); ?>
                        <input type="submit" class="dlm_btn__prim dlm_lmdac_btn" name="delete" onclick="return confirm('<?php esc_html_e( "Are you sure? This action cannot be reverted." ); ?>')" value="<?php esc_html_e( 'Delete' ); ?>">
                    </fieldset>
                </div>
            </form>


		<?php elseif ( $license::STATUS_DISABLED === $statusCode ): 
            
            $full_license_key = $license->getLicenseKey(); // Yoni
            $first_part = substr( $full_license_key, 0, 3 ); // Yoni
            $last_part = substr( $full_license_key, -3 ); // Yoni
            $hidden_license_key = $first_part . '************************' . $last_part; // Yoni

            ?>

            <form method="POST" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <fieldset id="dlm_activate_plugin">
                    <div class="dlm_license_key ">
                        <p class="label"><?php esc_html_e( 'License Key' ); ?></p>
                        <p class="field"><input id="license_key" name="license_key" readonly type="text" value="<?php echo esc_attr( $hidden_license_key ); ?>"></p>
                    </div>
                </fieldset>
                <div id="dlm_license_actions">
                    <fieldset class="dlm_license_links"></fieldset>
                    <fieldset class="dlm_license_button">
                        <input type="hidden" name="action" value="<?php echo esc_attr( $this->configuration->prefix . 'activator' ); ?>"/>
						<?php echo wp_nonce_field( 'activate_nonce' ); ?>
                        <input type="submit" class="dlm_btn__prim dlm_lmac_btn" name="reactivate" value="<?php esc_html_e( 'Reactivate' ); ?>">
                        <input type="submit" class="dlm_btn__prim dlm_lmdac_btn" name="delete" onclick="return confirm('<?php esc_html_e( "Are you sure? This action cannot be reverted." ); ?>')" value="<?php esc_html_e( 'Delete' ); ?>">
                    </fieldset>
                </div>
            </form>

		<?php elseif ( $license::STATUS_ACTIVE === $statusCode ): 
            
            $full_license_key = $license->getLicenseKey(); // Yoni
            $first_part = substr( $full_license_key, 0, 3 ); // Yoni
            $last_part = substr( $full_license_key, -3 ); // Yoni
            $hidden_license_key = $first_part . '************************' . $last_part; // Yoni

?>

            <form method="POST" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <fieldset id="dlm_activate_plugin">
                    <div class="dlm_license_key ">
                        <p class="label"><?php esc_html_e( 'License Key' ); ?></p>
                        <p class="field"><input id="license_key" name="license_key" readonly type="text" value="<?php echo esc_attr( $hidden_license_key ); ?>"></p>
                    </div>
                </fieldset>
                <div id="dlm_license_actions">
                    <fieldset class="dlm_license_links"></fieldset>
                    <fieldset class="dlm_license_button">
                        <input type="hidden" name="action" value="<?php echo esc_attr( $this->configuration->prefix . 'activator' ); ?>"/>
						<?php echo wp_nonce_field( 'activate_nonce' ); ?>
                        <input type="submit" class="dlm_btn__prim dlm_lmac_btn" name="deactivate" value="<?php esc_html_e( 'Deactivate' ); ?>">
                        <input type="submit" class="dlm_btn__prim dlm_lmdac_btn" name="delete" onclick="return confirm('<?php esc_html_e( "Are you sure? This action cannot be reverted." ); ?>')" value="<?php esc_html_e( 'Delete' ); ?>">
                    </fieldset>
                </div>
            </form>

		<?php elseif ( $license::STATUS_MISSING_LICENSE_KEY === $statusCode ): ?>

            <form method="POST" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <fieldset id="dlm_activate_plugin">
                    <div class="dlm_license_key ">
                        <p class="label"><?php esc_html_e( 'License Key' ); ?></p>
                        <p class="field"><input id="license_key" name="license_key" type="text" value=""></p>
                    </div>
                </fieldset>
                <div id="dlm_license_actions">
                    <fieldset class="dlm_license_links"></fieldset>
                    <fieldset class="dlm_license_button">
                        <input type="hidden" name="action" value="<?php echo esc_attr( $this->configuration->prefix . 'activator' ); ?>"/>
						<?php echo wp_nonce_field( 'activate_nonce' ); ?>
                        <input type="submit" class="dlm_btn__prim dlm_lmac_btn" name="activate" value="<?php esc_html_e( 'Activate' ); ?>">
                    </fieldset>
                </div>
            </form>

		<?php endif; ?>
    </div>
</div>


