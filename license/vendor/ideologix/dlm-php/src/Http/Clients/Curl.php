<?php

namespace IdeoLogix\DigitalLicenseManagerClient\Http\Clients;

use IdeoLogix\DigitalLicenseManagerClient\Http\Responses\Base as BaseResponse;
use IdeoLogix\DigitalLicenseManagerClient\Http\Responses\Error;
use IdeoLogix\DigitalLicenseManagerClient\Http\Responses\Result;

use IdeoLogix\DigitalLicenseManagerClient\Utils\FileSystem;

class Curl extends Base {

	/**
	 * The ID
	 * @var string
	 */
	protected $id = 'cURL';

	/**
	 * The current version
	 * @var string
	 */
	protected $version = '1.0';

	/**
	 * HTTP GET implementation
	 *
	 * @param $path
	 * @param $data
	 *
	 * @return BaseResponse
	 */
	public function get( $path, $data = array() ) {

		$url = $this->url( $path, $data );

		@set_time_limit( $this->get_timeout() + 30 );

		$ch = curl_init();
		curl_setopt( $ch, CURLOPT_URL, $url );
		curl_setopt( $ch, CURLOPT_USERAGENT, $this->user_agent );
		curl_setopt( $ch, CURLOPT_POST, 0 );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
		curl_setopt( $ch, CURLOPT_FAILONERROR, 0 );
		curl_setopt( $ch, CURLOPT_TIMEOUT, $this->get_timeout() );
		curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
		curl_setopt( $ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1 );

		// Setup headers
		$this->set_headers($ch);

		// Finalize request
		$response = curl_exec( $ch );
		if ( false === $response ) {
			$curl_error = curl_error( $ch );
			$curl_errno = curl_errno( $ch );
			curl_close( $ch );

			return new Error( $curl_errno, $curl_error, array() );
		}
		curl_close( $ch );
		$result = @json_decode( $response, true );

		return $this->result( $result );
	}

	/**
	 * HTTP POST implementation
	 *
	 * @param $path
	 * @param array $data
	 * @param array $files
	 *
	 * @return BaseResponse
	 */
	public function post( $path, $data = array(), $files = array() ) {

		$url = $this->url( $path, $data );

		@set_time_limit( $this->get_timeout() + 30 );
		$ch = curl_init();
		if ( ! empty( $files ) ) {
			foreach ( $files as $key => $file ) {
				if ( file_exists( $file ) ) {
					$data[ $key ] = $this->to_curl_file( $file );
				}
			}
		}
		curl_setopt( $ch, CURLOPT_URL, $url );
		curl_setopt( $ch, CURLOPT_USERAGENT, $this->user_agent );
		curl_setopt( $ch, CURLOPT_POST, 1 );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
		curl_setopt( $ch, CURLOPT_POSTFIELDS, $data );
		curl_setopt( $ch, CURLOPT_FAILONERROR, 0 );
		curl_setopt( $ch, CURLOPT_TIMEOUT, $this->get_timeout() );
		curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
		curl_setopt( $ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1 );

		// Setup headers
		$this->set_headers($ch);

		// Finalize request
		$response = curl_exec( $ch );
		if ( false === $response ) {
			$curl_error = curl_error( $ch );
			$curl_errno = curl_errno( $ch );
			curl_close( $ch );

			return new Error( $curl_errno, $curl_error, array() );
		}
		curl_close( $ch );
		$result = @json_decode( $response, true );

		return $this->result( $result );

	}

	/**
	 * HTTP PUT implementation
	 *
	 * @param $path
	 * @param array $data
	 * @param array $files
	 *
	 * @return BaseResponse
	 */
	public function put( $path, $data = array(), $files = array() ) {
		$url = $this->url( $path, $data );

		@set_time_limit( $this->get_timeout() + 30 );
		$ch = curl_init();
		if ( ! empty( $files ) ) {
			foreach ( $files as $key => $file ) {
				if ( file_exists( $file ) ) {
					$data[ $key ] = $this->to_curl_file( $file );
				}
			}
		}
		curl_setopt( $ch, CURLOPT_URL, $url );
		curl_setopt( $ch, CURLOPT_USERAGENT, $this->user_agent );
		curl_setopt( $ch, CURLOPT_PUT, 1 );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
		curl_setopt( $ch, CURLOPT_POSTFIELDS, $data );
		curl_setopt( $ch, CURLOPT_FAILONERROR, 0 );
		curl_setopt( $ch, CURLOPT_TIMEOUT, $this->get_timeout() );
		curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
		curl_setopt( $ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1 );

		// Setup headers
		$this->set_headers($ch);

		// Finalize request
		$response = curl_exec( $ch );
		if ( false === $response ) {
			$curl_error = curl_error( $ch );
			$curl_errno = curl_errno( $ch );
			curl_close( $ch );

			return new Error( $curl_errno, $curl_error, array() );
		}
		curl_close( $ch );
		$result = @json_decode( $response, true );

		return $this->result( $result );
	}

	/**
	 * HTTP DELETE implementation
	 *
	 * @param $path
	 *
	 * @return BaseResponse
	 */
	public function delete( $path ) {
		$url = $this->url( $path );

		@set_time_limit( $this->get_timeout() + 30 );

		$ch = curl_init();
		curl_setopt( $ch, CURLOPT_URL, $url );
		curl_setopt( $ch, CURLOPT_USERAGENT, $this->user_agent );
		curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, "DELETE" );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
		curl_setopt( $ch, CURLOPT_FAILONERROR, 0 );
		curl_setopt( $ch, CURLOPT_TIMEOUT, $this->get_timeout() );
		curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
		curl_setopt( $ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1 );

		// Setup headers
		$this->set_headers($ch);

		// Finalize request
		$response = curl_exec( $ch );
		if ( false === $response ) {
			$curl_error = curl_error( $ch );
			$curl_errno = curl_errno( $ch );
			curl_close( $ch );

			return new Error( $curl_errno, $curl_error, array() );
		}
		curl_close( $ch );
		$result = @json_decode( $response, true );

		return $this->result( $result );
	}

	/**
	 * Download specific url to file path
	 *
	 * @param $path
	 * @param $save_dir
	 * @param null $save_filename
	 * @param array $data
	 *
	 * @return BaseResponse
	 */
	public function download( $path, $save_dir, $save_filename = null, $data = array() ) {

		$url = $this->url( $path, $data );

		@set_time_limit( $this->get_timeout() + 30 );

		$ch = curl_init();
		curl_setopt( $ch, CURLOPT_URL, $url );
		curl_setopt( $ch, CURLOPT_USERAGENT, $this->user_agent );
		curl_setopt( $ch, CURLOPT_POST, 0 );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
		curl_setopt( $ch, CURLOPT_FAILONERROR, 0 );
		curl_setopt( $ch, CURLOPT_TIMEOUT, $this->get_timeout() );
		curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
		curl_setopt( $ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1 );

		// Setup headers
		$this->set_headers($ch);

		// Finalize request
		$response = curl_exec( $ch );
		if ( false === $response ) {
			$curl_error = curl_error( $ch );
			$curl_errno = curl_errno( $ch );
			curl_close( $ch );

			return new Error( $curl_errno, $curl_error, array() );
		}
		curl_close( $ch );
		$result = @json_decode( $response, true );
		if ( is_array( $result ) ) {
			return $this->result( $result );
		}

		// Save file...
		if ( ! FileSystem::mkdir_p( $save_dir ) ) {
			return new Error( 500, 'Unable to create the save directory.', array() );
		}
		$save_filename = $this->ensure_filename( $response, $save_filename );
		$save_path     = rtrim( $save_dir, '/' ) . DIRECTORY_SEPARATOR . $save_filename;

		$fp = fopen( $save_path, 'w+' );
		if ( ! is_writable( $save_path ) ) {
			return new Error( '400', sprintf( 'The path %s is not writable.', $save_path ) );
		}
		fwrite( $fp, $response );
		fclose( $fp );

		return new Result( true, [ 'path' => $save_path ] );
	}

	/**
	 * Set the headers.
	 *
	 * @param $ch
	 *
	 * @return void
	 */
	private function set_headers( &$ch ) {
		curl_setopt( $ch, CURLOPT_HTTPHEADER, [
			sprintf(
				'Authorization: Basic %s',
				base64_encode( sprintf( '%s:%s', $this->consumer_key, $this->consumer_secret ) )
			),
			'User-Agent: ' . $this->user_agent,
		] );
	}

	/**
	 * Check if $filename is set, if it is set, return it. Otherwise try to find it from the Content-Disposition header.
	 * If it is still not present generate one based on the content type.
	 *
	 * @param $response
	 * @param $filename
	 *
	 * @return string
	 */
	private function ensure_filename( $response, $filename ) {
		if ( ! empty( $filename ) ) {
			return $filename;
		}
		$header = ! empty( $response->headers['Content-Disposition'] ) ? $response->headers['Content-Disposition'] : '';
		if ( preg_match( '~filename=(?|"([^"]*)"|\'([^\']*)\'|([^;]*))~', $header, $match ) ) {
			$filename = $match[1];
		} else {
			$filename = md5( $response->url ) . time();
		}

		return $filename;
	}

	/**
	 * Return formatted result
	 *
	 * @param array $result
	 *
	 * @return Error|Result
	 */
	private function result( $result ) {

		if ( isset( $result['success'] ) ) {
			$success = (bool) $result['success'];;
			$data = array();
			if ( isset( $result['data'] ) ) {
				$data = (array) $result['data'];
			}

			return ( new Result( $success, $data ) );
		} else {
			$code    = isset( $result['code'] ) ? $result['code'] : 'server_error';
			$message = isset( $result['message'] ) ? $result['message'] : '';
			$data    = isset( $result['data'] ) ? (array) $result['data'] : array();

			// WordPress REST often returns code/message only inside "data" (or nested envelope).
			if ( is_array( $data ) ) {
				if ( isset( $data['code'] ) ) {
					$code = $data['code'];
				}
				if ( isset( $data['message'] ) && '' !== trim( (string) $data['message'] ) ) {
					$message = $data['message'];
				}
			}
			if ( '' === trim( (string) $message ) ) {
				$message = 'Unknown error.';
			}

			return new Error( $code, $message, $data );
		}
	}

	/**
	 * Convert $resource to CURLFile, also backwards compatible with
	 * version lower than 5.5
	 *
	 * @param $resource
	 *
	 * @return \CURLFile|string
	 */
	private function to_curl_file( $resource ) {
		if ( ! class_exists( 'CURLFile' ) ) {
			return '@' . $resource;
		} else {
			return new \CURLFile( $resource );
		}
	}

	/**
	 * Returns the timeout
	 * @return int
	 */
	private function get_timeout() {
		return defined( "DLM_HTTP_CURL_TIMEOUT" ) ? DLM_HTTP_CURL_TIMEOUT : 60;
	}

	/**
	 * Is supported?
	 * @return bool
	 */
	public function is_supported() {
		return function_exists( 'curl_version' );
	}
}
