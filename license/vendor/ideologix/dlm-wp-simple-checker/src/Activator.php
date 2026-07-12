<?php
/**
 * This file comes from the "Digital License Manager" WordPress plugin.
 * https://darkog.com/p/digital-license-manager/
 *
 * Copyright (C) 2020-2024  Darko Gjorgjijoski. All Rights Reserved.
 * Copyright (C) 2020-2024  IDEOLOGIX MEDIA DOOEL. All Rights Reserved.
 *
 * Digital License Manager is free software; you can redistribute it
 * and/or modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * Digital License Manager program is distributed in the hope that it
 * will be useful,but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License v3
 * along with this program;
 *
 * If not, see: https://www.gnu.org/licenses/gpl-3.0.en.html
 *
 * Code written, maintained by Darko Gjorgjijoski (https://darkog.com)
 */

namespace IdeoLogix\DigitalLicenseManagerSimpleChecker;

use IdeoLogix\DigitalLicenseManagerSimpleChecker\Abstracts\AbstractActivator;

class Activator extends AbstractActivator {

	/**
	 * The configuration
	 * @var Configuration
	 */
	public $configuration;

	/**
	 * Activaton form
	 *
	 * @param  Configuration  $configuration
	 *
	 * @throws \Exception
	 */
	public function __construct( $configuration ) {
		$this->configuration = $configuration;
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueueScripts' ] );
		add_action( 'admin_menu', [ $this, 'adminMenu' ] );
		add_action( 'admin_post_' . $this->configuration->prefix . 'activator', [ $this, 'handleActivation' ] );
		add_action( 'admin_notices', [ $this, 'adminNotices' ] );
		//add_action('admin_body_class', [$this, 'adminBodyClass']);
	}

	/**
	 * Enqueues the required scripts
	 * @return void
	 */
	public function enqueueScripts() {

		if ( $this->configuration->isActivationPage() ) {
			//return; // yoni comment this
		}
        
        if( isset($_GET['page']) && $_GET['page'] === $this->configuration->prefix .'activator'){ // yoni add this check

            wp_enqueue_style(
                $this->configuration->prefix . 'activator',
                trailingslashit( $this->configuration->public_url ) . 'assets/style.css',
                [],
                filemtime( trailingslashit( $this->configuration->public_path ) . 'assets/style.css' ),
                'all'
            );
            wp_enqueue_style(
                $this->configuration->prefix . 'activator',
                trailingslashit( $this->configuration->public_path ) . 'assets/script.js',
                [],
                filemtime( trailingslashit( $this->configuration->public_path ) . 'assets/script.js' ),
                'all'
            );
        }// yoni add this end
	}

	/**
	 * Registers the menu
	 * @return void
	 */
	public function adminMenu() {

		if ( empty( $this->configuration->menu['parent_slug'] ) ) {
			add_menu_page(
				! empty( $this->configuration->menu['page_title'] ) ? $this->configuration->menu['page_title'] : esc_html__( 'License Activation' ),
				! empty( $this->configuration->menu['menu_title'] ) ? $this->configuration->menu['menu_title'] : esc_html__( 'License Activation' ),
				! empty( $this->configuration->menu['capability'] ) ? $this->configuration->menu['capability'] : 'manage_woocommerce',
				! empty( $this->configuration->menu['menu_slug'] ) ? ! empty( $this->configuration->menu['menu_slug'] ) : $this->configuration->prefix . 'activator',
				[ $this, 'renderPage' ],
				! empty( $this->configuration->menu['icon_url'] ) ? ! empty( $this->configuration->menu['icon_url'] ) : 'dashicons-lock',
				! empty( $this->configuration->menu['position'] ) ? ! empty( $this->configuration->menu['position'] ) : 5
			);
		} else {
			add_submenu_page(
				! empty( $this->configuration->menu['parent_slug'] ) ? $this->configuration->menu['parent_slug'] : 'options-general.php',
				! empty( $this->configuration->menu['page_title'] ) ? $this->configuration->menu['page_title'] : esc_html__( 'License Activation' ),
				! empty( $this->configuration->menu['menu_title'] ) ? $this->configuration->menu['menu_title'] : esc_html__( 'License Activation' ),
				! empty( $this->configuration->menu['capability'] ) ? $this->configuration->menu['capability'] : 'manage_options',
				! empty( $this->configuration->menu['menu_slug'] ) ? ! empty( $this->configuration->menu['menu_slug'] ) : $this->configuration->prefix . 'activator',
				[ $this, 'renderPage' ]
			);
		}
	}


	/**
	 * Show the admin notices
	 * @return void
	 */
	public function adminNotices() {

		// Activation page flashes are rendered inline in the page template.
		// This avoids relying on WordPress admin notices that may be hidden.
		if ( $this->configuration->isActivationPage() ) {
			return;
		}

		$result = $this->getFlashedMessage( true );
		if ( ! empty( $result ) ) {
			$class   = ! empty( $result['code'] ) && $result['code'] === 'error' ? 'notice notice-error' : 'notice notice-success';
			$message = ! empty( $result['message'] ) ? $result['message'] : __( 'Irks! An error has occurred.' );
			printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $message ) );

			return;
		}

	}

	/**
	 * Renders the activation page
	 * @return void
	 */
	public function renderPage() {

		$configuration = $this->configuration;
		try {
			$license = new License( $configuration );
			$flashMessage = get_transient( $configuration->prefix . 'flash' );
			if ( ! empty( $flashMessage ) ) {
				delete_transient( $configuration->prefix . 'flash' );
			}
			include( $configuration->public_path . 'views/page.php' );
		} catch ( \Exception $e ) {
			echo '<h1>Error.</h1>';
			echo '<p>Unable to load license activation page.</p>';
			echo '<p><strong>Error</strong>:' . $e->getMessage() . '</p>';
		}
	}

	/**
	 * Handles the activation
	 * @return void
	 */
	public function handleActivation() {

		if ( empty( $_POST ) || ! check_admin_referer( 'activate_nonce', '_wpnonce' ) ) {
			return;
		}

		$capability = isset( $this->configuration->menu['capability'] ) ? $this->configuration->menu['capability'] : 'manage_options';

		if ( ! current_user_can( $capability ) ) {
			wp_die( 'Access denied.' );
		}

		try {
			$license = new License( $this->configuration );
		} catch ( \Exception $e ) {
			wp_die( $e->getMessage() );
		}

		$type = isset( $_POST['type'] ) ? sanitize_text_field( $_POST['type'] ) : '';

		if ( ! empty( $_POST['save'] ) ) {
			if ( empty( $_POST['activation_token'] ) ) {
				$this->flashMessage( [ 'code' => 'error', 'message' => __( 'Unablle to save the selected token.' ) ] );
				$this->redirectBack( 'error' );
			} else {
				if('new' === $_POST['activation_token']) {
					$licenseKey = $license->getLicenseKey();
					if ( empty( $licenseKey ) ) {
						$this->flashMessage( [ 'code' => 'success', 'message' => __( 'Missing license key!' ) ] );
						$this->redirectBack( 'error' );
					} else {
						$resposne   = $license->activate( $licenseKey );
						if ( is_wp_error( $resposne ) ) {
							$this->flash_wp_error( $resposne );
							$this->redirectBack( 'error' );
						} else {
							$this->flashMessage( [ 'code' => 'success', 'message' => __( 'The license has been activated successfully' ) ] );
							$this->redirectBack( 'success' );
						}
					}
				} else {
					$license->updateData( [ 'token' => sanitize_text_field( $_POST['activation_token'] ) ] );
					$this->flashMessage( [ 'code' => 'success', 'message' => __( 'The token has been saved successfully.' ) ] );
					$this->redirectBack( 'success' );
				}
			}
			exit;
		} elseif ( ! empty( $_POST['delete'] ) ) {
			if ( ! $license->deactivate() ) {
				$this->flashMessage( [ 'code' => 'error', 'message' => __( 'Unable to delete and deactivate licnese.' ) ] );
				$this->redirectBack( 'error' );
			} else {
				$license->deleteData();
				$this->flashMessage( [ 'code' => 'deleted', 'message' => __( 'The license has been deleted and deactivated from this site.' ) ] );
				$this->redirectBack( 'success' );
			}
			$license->deleteData();
			exit;
		} elseif ( ! empty( $_POST['activate'] ) ) {
			if ( empty( $_POST['license_key'] ) ) {
				$this->flashMessage( [ 'code' => 'success', 'message' => __( 'Missing license key!' ) ] );
				$this->redirectBack( 'error' );
			} else {
				$licenseKey = sanitize_text_field( $_POST['license_key'] );
				$response   = $license->activate( $licenseKey );
				if ( is_wp_error( $response ) ) {
					$this->flash_wp_error( $response );
					$this->redirectBack( 'error' );
				} else {
					// Check if the response contains the user_first_api_post_id
					$user_first_api_post_id = isset($response['user_first_api_post_id']) ? $response['user_first_api_post_id'] : 'no';
					$user_first_api_post_id = is_array($user_first_api_post_id) ? implode(',', $user_first_api_post_id) : $user_first_api_post_id;
					$user_first_api_post_id = sanitize_text_field($user_first_api_post_id);
					if (!empty($user_first_api_post_id)) {
						$license->updateData(['user_first_api_post_id' => $user_first_api_post_id]);
					}
					$this->flashMessage( [ 'code' => 'success', 'message' => __( 'The license has been activated successfully' ) ] );
					$this->redirectBack( 'success&popup=1' );
				}
			}
			exit;
		} elseif ( ! empty( $_POST['reactivate'] ) ) {
			$licenseKey = $license->getLicenseKey();
			if ( empty( $licenseKey ) ) {
				$this->flashMessage( [ 'code' => 'error', 'message' => __( 'The license key is missing.' ) ] );
				$this->redirectBack( 'error' );
			} else {
				$reactivate_result = $license->activate( $licenseKey );
				if ( is_wp_error( $reactivate_result ) ) {
					$this->flash_wp_error( $reactivate_result );
					$this->redirectBack( 'error' );
				}
				$this->flashMessage( [ 'code' => 'success', 'message' => __( 'The license has been reactivated successfully' ) ] );
				$this->redirectBack( 'success' );
			}
			exit;
		} elseif ( $_POST['deactivate'] ) {
			if ( ! $license->deactivate() ) {
				$this->flashMessage( [ 'code' => 'error', 'message' => __( 'Unable to deactivate licnese.' ) ] );
				$this->redirectBack( 'error' );
			} else {
				$this->flashMessage( [ 'code' => 'success', 'message' => __( 'The license has been deactivated, however the license data is still saved and can be reactivated at anytime.' ) ] );
				$this->redirectBack( 'success' );
			}
			exit;
		} else {
			wp_die( 'Unknown action.' );
		}
	}

	/**
	 * Flashes a message
	 *
	 * @param $message
	 *
	 * @return void
	 */
	protected function flashMessage( $message ) {
		set_transient( $this->configuration->prefix . 'flash', $message, 60 * 10 );
	}

	/**
	 * Redirects back
	 *
	 * @param $status
	 *
	 * @return void
	 */
	protected function redirectBack( $status ) {
		wp_redirect( add_query_arg( [ 'status' => $status ], $this->configuration->getActivationPageUrl() ) );
	}

	/**
	 * Returns the flashed message and purges it from the database.
	 *
	 * @param $purge
	 *
	 * @return mixed
	 */
	protected function getFlashedMessage( $purge = true ) {
		$message = get_transient( $this->configuration->prefix . 'flash' );
		if ( $purge ) {
			delete_transient( $this->configuration->prefix . 'flash' );
		}

		return $message;
	}

	/**
	 * Store activation API error for inline display on the license page.
	 *
	 * @param \WP_Error $error
	 *
	 * @return void
	 */
	protected function flash_wp_error( \WP_Error $error ) {
		$message = $error->get_error_message();
		$data    = $error->get_error_data();
		$code    = $error->get_error_code();

		if ( is_array( $data ) ) {
			if ( isset( $data['message'] ) && is_string( $data['message'] ) && '' !== trim( $data['message'] ) ) {
				$message = $data['message'];
			}
			if ( isset( $data['data']['message'] ) && is_string( $data['data']['message'] ) && '' !== trim( $data['data']['message'] ) ) {
				$message = $data['data']['message'];
			}
		}

		$is_unclear = '' === trim( (string) $message ) || 'Unknown error.' === $message;

		if ( in_array( $code, array( 'server_error', 'invalid_json', 'http_error' ), true ) ) {
			$is_unclear = true;
		}

		if ( is_string( $message ) ) {
			$technical_fragments = array(
				'Response was not valid JSON',
				'cURL error',
				'HTTP request failed',
			);
			foreach ( $technical_fragments as $fragment ) {
				if ( false !== stripos( $message, $fragment ) ) {
					$is_unclear = true;
					break;
				}
			}
		}

		if ( $is_unclear ) {
			$message = __( 'License key not found. It may be expired.', 'contact-forms-anti-spam' );
		}

		$payload = [
			'code'    => 'error',
			'message' => $message,
		];

		if ( current_user_can( 'manage_options' ) ) {
			$payload['debug'] = $this->build_license_error_debug( $error );
		}

		$this->flashMessage( $payload );
	}

	/**
	 * Full WP_Error dump for admin debugging (which line to show to users).
	 *
	 * @param \WP_Error $error
	 *
	 * @return array
	 */
	protected function build_license_error_debug( \WP_Error $error ) {
		$by_code   = array();
		$http_flat = null;
		foreach ( $error->get_error_codes() as $code ) {
			$data = $error->get_error_data( $code );
			$by_code[ $code ] = array(
				'message' => $error->get_error_message( $code ),
				'data'    => $data,
			);
			if ( null !== $http_flat || ! is_array( $data ) ) {
				continue;
			}
			if ( isset( $data['_dlm_http_raw'] ) ) {
				$http_flat = $data['_dlm_http_raw'];
				continue;
			}
			// invalid_json path: WordPress client merges raw fields directly into Error data (no _dlm_http_raw).
			if ( isset( $data['response_body_raw'] ) ) {
				$http_flat = array(
					'http_status'        => isset( $data['http_status'] ) ? $data['http_status'] : null,
					'response_body_raw'  => $data['response_body_raw'],
					'response_headers'   => isset( $data['response_headers'] ) ? $data['response_headers'] : array(),
					'json_decode_error'  => isset( $data['json_error'] ) ? $data['json_error'] : null,
				);
			}
		}

		return array(
			'server_http_raw'  => $http_flat,
			'wp_error_by_code' => $by_code,
			'hint'             => 'If server_http_raw shows HTML (nginx/405) while your server log shows JSON, the site HTTP stack (proxy/WAF/URL length) is returning an error page to the plugin before WordPress REST runs. Fix routing or use an origin URL for the API.',
		);
	}

}
