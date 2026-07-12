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

use DateTime;
use IdeoLogix\DigitalLicenseManagerClient\Http\Responses\Base;
use IdeoLogix\DigitalLicenseManagerClient\Http\Responses\Error;
use IdeoLogix\DigitalLicenseManagerClient\Service;

class License {

	const STATUS_ACTIVE = 'active';
	const STATUS_DISABLED = 'disabled';
	const STATUS_EXPIRED = 'expired';
	const STATUS_MISSING_TOKEN = 'missing_token';
	const STATUS_MISSING_LICENSE_KEY = 'missing_license_key';

	/**
	 * The cached data
	 * @var array
	 */
	protected $data;

	/**
	 * The configuration
	 * @var Configuration
	 */
	protected $configuration;

	/**
	 * The HTTP client
	 * @var Service
	 */
	protected $client;

	/**
	 * Constructor
	 *
	 * @param  Configuration  $configuration
	 *
	 * @throws \Exception
	 */
	public function __construct( $configuration ) {
		$this->configuration = $configuration;
		$this->client        = new Service( $this->configuration->api_url, $this->configuration->consumer_key, $this->configuration->consumer_secret );
		$this->loadData();
	}

	/**
	 * Returns the activation token
	 * @return mixed|string
	 */
	public function getActivationToken() {
		return isset($this->data['token']) ? $this->data['token'] : '';
	}

	/**
	 * Returns the license key
	 * @return mixed|string
	 */
	public function getLicenseKey() {
		return isset($this->data['key']) ? $this->data['key'] : '';
	}

	/**
	 * Returns the expires at property
	 * @return mixed|string
	 */
	public function getExpiresAt() {
		return isset($this->data['expires_at']) ? $this->data['expires_at'] : '';
	}

	/**
	 * Returns the deactivated at property
	 * @return mixed|string
	 */
	public function getDeactivatedAt() {
		return isset($this->data['deactivated_at']) ? $this->data['deactivated_at'] : '';
	}

	/**
	 * Returns the status
	 * @return string
	 */
	public function getStatus() {

		$isExpired = false;
		if(!empty($this->getExpiresAt())) {
			$expires = \DateTime::createFromFormat('Y-m-d H:i:s', $this->getExpiresAt());
			$currently = new \DateTime();
			$isExpired = $expires <= $currently;
		}
		if($isExpired) {
			return self::STATUS_EXPIRED;
		} else if(empty($this->getLicenseKey())) {
			return self::STATUS_MISSING_LICENSE_KEY;
		} else if(empty($this->getActivationToken())) {
			return self::STATUS_MISSING_TOKEN;
		} else if(!empty($this->getDeactivatedAt())) {
			return self::STATUS_DISABLED;
		} else {
			return self::STATUS_ACTIVE;
		}
	}

	/**
	 * Is the license key set?
	 * @return bool
	 */
	public function isLicenseKeySet() {
		return !empty($this->getLicenseKey());
	}

	/**
	 * Is license valid?
	 * @return mixed|string
	 */
	public function isLicenseValid() {

		$expiresAt = $this->getExpiresAt();

		if ( is_null( $expiresAt ) ) {
			$validity = 1; // Permanent activation
		} elseif ( $expiresAt === '' ) {
			$validity = 0; // Not set? Deactivated.
		} else {
			// Timestamp, check.
			$now     = new DateTime();
			$expires = DateTime::createFromFormat( 'Y-m-d H:i:s', $expiresAt );
			if ( $now > $expires ) {
				$validity = 0; // Expired.
			} else {
				$validity = 1; // Valid.
			}
		}

		return $validity;
	}

	/**
	 * Is activation token set according to the local data?
	 * @return bool
	 */
	public function isActivationTokenSet() {
		return ! empty( $this->data['token'] );
	}


	/**
	 * Is activation token enabled according to the local data?
	 * @return bool
	 */
	public function isActivationTokenEnabled() {
		return empty( $this->data['deactivated_at'] );
	}

	/**
	 * Queries the API to validate the activation token
	 * @return void
	 */
	public function queryValidateActivationToken() {
		$result = $this->client->licenses()->validate( $this->getActivationToken() );
		return $this->response($result);
	}

	/**
	 *
	 * @return array|mixed|\WP_Error|null
	 */
	public function queryValidateLicenseExpiration() {
		$result = $this->client->licenses()->find( $this->getLicenseKey() );
		return $this->response($result);
	}

	/**
	 * Queries the API to disable the activation token
	 * @return void
	 */
	public function queryDisableActivationToken() {
		$result = $this->client->licenses()->deactivate( $this->getActivationToken() );
		return $this->response($result);
	}

	/**
	 * Activates a license:
	 *  1. Queries the API to activate license
	 *  2. Stores activation token
	 *  3. Stores license key
	 *
	 * @param $key
	 *
	 * @return bool|\WP_Error
	 */
	public function activate($key) {

		global $wp_version;
		$result = $this->client->licenses()->activate($key, [
			'label'    => home_url(),
			'meta'     => array(
				'wp_version'  => $wp_version,
				'php_version' => PHP_VERSION,
				'web_server'  => isset( $_SERVER['SERVER_SOFTWARE'] ) ? $_SERVER['SERVER_SOFTWARE'] : null,
			)
		]);

		if($result->is_error()) {
			return new \WP_Error( $result->get_code(), $result->get_message(), $result->get_data() );
		}

		$data = $result->get_data();

		
		$user_first_api_post_id = isset($data['user_first_api_post_id']) ? sanitize_text_field($data['user_first_api_post_id']) : 0;
		// Add optin to database with $user_first_api_post_id
		update_option('maspik_api_id', $user_first_api_post_id);

		$this->updateData([
			'key' => $key,
			'token' => $data['token'],
			'user_first_api_post_id' => $user_first_api_post_id,
			'error' => null,
			'expires_at' => $data['license']['expires_at'],
			'deactivated_at' => null,
			'checked_at' => null,
		]);

		return true;
	}

	/**
	 * Deactivates a license:
	 *  1. Queries the API to disable the activation token
	 *  2. Removes the activation token
	 *  3. Removes the license data
	 *
	 * @return bool
	 */
	public function deactivate() {

		$key = $this->getLicenseKey();
		$token = $this->getActivationToken();

		if(empty($token)) {
			return true;
		}

		$response = $this->client->licenses()->deactivate($token);
		if(!$response->is_error()) {
			$this->updateData([
				'token' => '',
			]);
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Updates the cached data
	 *
	 * @param $arr
	 *
	 * @return void
	 */
	public function updateData( $arr ) {
		$this->data = wp_parse_args( $arr, $this->data );
		update_option( $this->getDataKey(), $this->data );
	}

	/**
	 * Deletes license data
	 * @return void
	 */
	public function deleteData() {
		$this->data = [];
		delete_option($this->getDataKey());
	}

	/**
	 * Load cached data from the database
	 * @return void
	 */
	public function loadData() {
		if ( empty( $this->data ) ) {
			$this->data = get_option( $this->getDataKey() );
		}
	}

	/**
	 * Returns license data key
	 * @return string
	 */
	protected function getDataKey() {
		return sprintf( '%s%s', $this->configuration->prefix, 'dlm_license' );
	}

	/**
	 * Returns the response
	 * @param  Base  $response
	 *
	 * @return array|mixed|\WP_Error|null
	 */
	protected function response(Base $response) {
		if($response instanceof Error) {
			return new \WP_Error($response->get_code(), $response->get_message(), []);
		} else {
			return $response->get_data();
		}
	}

}