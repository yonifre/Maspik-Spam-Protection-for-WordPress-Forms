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
        <?php if ( ! empty( $flashMessage ) && is_array( $flashMessage ) ): ?>
            <?php
            $flashCode = ! empty( $flashMessage['code'] ) ? sanitize_key( $flashMessage['code'] ) : 'info';
            $flashText = ! empty( $flashMessage['message'] ) ? $flashMessage['message'] : __( 'Something went wrong. Please try again.', 'contact-forms-anti-spam' );
            $isError   = in_array( $flashCode, array( 'error', 'expired', 'disabled' ), true );
            $alertType = $isError ? 'error' : 'success';
            ?>
            <div class="dlm-inline-alert dlm-inline-alert--<?php echo esc_attr( $alertType ); ?>" role="status" aria-live="polite">
                <span class="dlm-inline-alert__icon dashicons <?php echo esc_attr( $isError ? 'dashicons-warning' : 'dashicons-yes-alt' ); ?>" aria-hidden="true"></span>
                <p><?php echo esc_html( $flashText ); ?></p>
            </div>
        <?php endif; ?>

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
                       <?php /* Yoni - Deactivate button removed */ 
                       /* <input type="submit" class="dlm_btn__prim dlm_lmac_btn" name="deactivate" value="<?php esc_html_e( 'Deactivate' ); ?>"> */ ?>
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

    <div class="dlm-license-faq-wrap">
        <div class="dlm-license-faq" aria-label="<?php echo esc_attr__( 'License activation help', 'contact-forms-anti-spam' ); ?>">
            <h3><?php echo esc_html__( 'License Help & FAQ', 'contact-forms-anti-spam' ); ?></h3>

            <details class="dlm-license-faq-item">
                <summary><?php echo esc_html__( 'My license is marked as invalid, but it should be valid. What can I do?', 'contact-forms-anti-spam' ); ?></summary>
                <p><?php echo esc_html__( 'Delete the current key, paste it again, and make sure there are no extra spaces before or after the key.', 'contact-forms-anti-spam' ); ?></p>
            </details>

            <details class="dlm-license-faq-item">
                <summary><?php echo esc_html__( 'Where can I see my license status?', 'contact-forms-anti-spam' ); ?></summary>
                <p>
                    <?php
                    printf(
                        wp_kses(
                            __( 'Log in to <a href="%1$s" target="_blank" rel="noopener noreferrer">My Account</a> to view all your licenses and their current status.', 'contact-forms-anti-spam' ),
                            array(
                                'a' => array(
                                    'href'   => array(),
                                    'target' => array(),
                                    'rel'    => array(),
                                ),
                            )
                        ),
                        esc_url( 'https://wpmaspik.com/my-account/' )
                    );
                    ?>
                </p>
            </details>

            <details class="dlm-license-faq-item">
                <summary><?php echo esc_html__( 'How do I renew my license?', 'contact-forms-anti-spam' ); ?></summary>
                <p>
                    <?php
                    printf(
                        wp_kses(
                            __( 'In most cases your subscription renews automatically. If a card charge fails or there is a billing issue, renewal may not complete and your license can appear as Expired. Log in to <a href="%1$s" target="_blank" rel="noopener noreferrer">My Account</a> on the WP Maspik site—there you can renew your license, update your payment method, or complete a manual payment.', 'contact-forms-anti-spam' ),
                            array(
                                'a' => array(
                                    'href'   => array(),
                                    'target' => array(),
                                    'rel'    => array(),
                                ),
                            )
                        ),
                        esc_url( 'https://wpmaspik.com/my-account/' )
                    );
                    ?>
                </p>
            </details>

            <details class="dlm-license-faq-item">
                <summary><?php echo esc_html__( 'Where can I purchase a new license?', 'contact-forms-anti-spam' ); ?></summary>
                <p>
                    <?php
                    printf(
                        wp_kses(
                            __( 'You can purchase a new license on the official WP Maspik website: <a href="%1$s" target="_blank" rel="noopener noreferrer">wpmaspik.com</a>.', 'contact-forms-anti-spam' ),
                            array(
                                'a' => array(
                                    'href'   => array(),
                                    'target' => array(),
                                    'rel'    => array(),
                                ),
                            )
                        ),
                        esc_url( 'https://wpmaspik.com/' )
                    );
                    ?>
                </p>
            </details>

            <details class="dlm-license-faq-item">
                <summary><?php echo esc_html__( 'I activated my license, but this site still shows Not Active. What should I check?', 'contact-forms-anti-spam' ); ?></summary>
                <p><?php echo esc_html__( 'Refresh this page, then try Delete the license and Activate again.', 'contact-forms-anti-spam' ); ?></p>
            </details>
        </div>
    </div>
</div>

<style>
    .dlm-submit-loader {
        display: inline-block;
        width: 14px;
        height: 14px;
        margin-left: 8px;
        border: 2px solid rgba(255, 255, 255, 0.5);
        border-top-color: #fff;
        border-radius: 50%;
        animation: dlm-spin 0.8s linear infinite;
        vertical-align: middle;
    }

    @keyframes dlm-spin {
        to {
            transform: rotate(360deg);
        }
    }

    .dlm-submit-loader.is-hidden {
        display: none;
    }

    .dlm_btn__prim.is-submitting {
        opacity: 0.8;
        cursor: wait;
    }
 </style>
<script>
    (function() {
        var activateButtons = document.querySelectorAll(
            '#dlm_license_form input[type="submit"][name="activate"], #dlm_license_form input[type="submit"][name="reactivate"]'
        );

        if (!activateButtons.length) {
            return;
        }

        activateButtons.forEach(function(button) {
            var form = button.form;

            if (!form) {
                return;
            }

            var spinner = document.createElement('span');
            spinner.className = 'dlm-submit-loader is-hidden';
            spinner.setAttribute('aria-hidden', 'true');
            button.insertAdjacentElement('afterend', spinner);

            form.addEventListener('submit', function(event) {
                if (form.getAttribute('data-dlm-submitting') === '1') {
                    event.preventDefault();
                    return;
                }

                form.setAttribute('data-dlm-submitting', '1');
                button.classList.add('is-submitting');
                button.style.pointerEvents = 'none';
                button.setAttribute('aria-disabled', 'true');
                button.value = <?php echo wp_json_encode( __( 'Activating...', 'contact-forms-anti-spam' ) ); ?>;
                spinner.classList.remove('is-hidden');
            });
        });
    })();
</script>

