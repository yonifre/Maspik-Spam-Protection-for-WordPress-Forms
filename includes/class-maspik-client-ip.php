<?php
/**
 * Client IP detection with proxy awareness (Cloudflare, Sucuri, etc.)
 *
 * @package Maspik
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Maspik_Client_Ip {

	const OPTION_PROXY_CACHE = 'maspik_proxy_ip_ranges_cache';
	const CRON_HOOK_REFRESH  = 'maspik_refresh_proxy_ip_ranges';

	/**
	 * Main function to get the client's real IP.
	 */
	public static function get_client_ip(): string {
		$remote = self::normalize_ip( $_SERVER['REMOTE_ADDR'] ?? '' );

		// If we couldn't get a valid REMOTE_ADDR, return localhost as default
		if ( ! $remote || ! self::is_valid_ip( $remote ) ) {
			return '127.0.0.1';
		}

		// Check if the connecting server is a known proxy (Cloudflare, Sucuri, etc.)
		$proxy_ranges    = self::get_proxy_ranges_cached();
		$remote_is_proxy = self::ip_in_any_cidr( $remote, $proxy_ranges );

		if ( $remote_is_proxy ) {
			$candidates = self::collect_forwarded_candidates();
			foreach ( $candidates as $ip ) {
				// Return the first valid public IP found in headers
				if ( self::is_valid_public_ip( $ip ) ) {
					return $ip;
				}
			}
		}

		// If not a known proxy, or no valid IP found in headers, use REMOTE_ADDR
		return $remote;
	}

	/**
	 * Collect potential IPs from the most common forwarded headers.
	 */
	private static function collect_forwarded_candidates(): array {
		$candidates = array();

		// Priority order: Cloudflare first
		$headers = array(
			'HTTP_CF_CONNECTING_IP',
			'HTTP_X_REAL_IP',
			'HTTP_X_FORWARDED_FOR',
			'HTTP_CLIENT_IP',
		);

		foreach ( $headers as $k ) {
			if ( ! empty( $_SERVER[ $k ] ) ) {
				$val   = (string) $_SERVER[ $k ];
				$parts = explode( ',', $val );
				foreach ( $parts as $p ) {
					$p = self::normalize_ip( $p );
					if ( $p !== '' ) {
						$candidates[] = $p;
					}
				}
			}
		}

		return array_values( array_unique( $candidates ) );
	}

	/**
	 * Fetch IP ranges from cache or built-in list.
	 */
	public static function get_proxy_ranges_cached(): array {
		$cache      = get_option( self::OPTION_PROXY_CACHE, array() );
		$updated_at = isset( $cache['updated_at'] ) ? (int) $cache['updated_at'] : 0;

		// If cache is empty, use the hardcoded fallback list
		$ranges = ( ! empty( $cache['ranges'] ) ) ? $cache['ranges'] : self::built_in_fallback_ranges();

		// Allow developers to add custom proxy ranges (see README for CDN/proxy other than Cloudflare/Sucuri)
		$ranges = apply_filters( 'maspik_proxy_ip_ranges', $ranges );

		// Filter out invalid entries to prevent errors
		$ranges = array_values( array_filter( (array) $ranges, array( __CLASS__, 'is_valid_cidr' ) ) );

		// If cache is older than 7 days, schedule background refresh
		if ( ( time() - $updated_at ) > ( 7 * DAY_IN_SECONDS ) ) {
			self::schedule_refresh();
		}

		return $ranges;
	}

	/**
	 * Check if a string is a valid CIDR range (e.g. 192.168.0.0/24). Invalid entries are skipped silently.
	 */
	private static function is_valid_cidr( $cidr ): bool {
		if ( ! is_string( $cidr ) || $cidr === '' ) {
			return false;
		}
		$parts = explode( '/', trim( $cidr ), 2 );
		if ( count( $parts ) !== 2 ) {
			return false;
		}
		if ( @inet_pton( trim( $parts[0] ) ) === false ) {
			return false;
		}
		$mask = (int) $parts[1];
		return $mask >= 0 && $mask <= 128;
	}

	/**
	 * Hardcoded fallback IP ranges for full backup.
	 */
	private static function built_in_fallback_ranges(): array {
		return array(
			// Cloudflare (IPv4)
			'173.245.48.0/20',
			'103.21.244.0/22',
			'103.22.200.0/22',
			'103.31.4.0/22',
			'141.101.64.0/18',
			'108.162.192.0/18',
			'190.93.240.0/20',
			'188.114.96.0/20',
			'197.234.240.0/22',
			'198.41.128.0/17',
			'162.158.0.0/15',
			'104.16.0.0/13',
			'104.24.0.0/14',
			'172.64.0.0/13',
			'131.0.72.0/22',
			// Sucuri
			'192.88.134.0/23',
			'185.93.228.0/22',
			'66.248.200.0/22',
			'208.109.0.0/22',
			'192.124.249.0/24',
			'2a02:fe80::/29',
		);
	}

	/**
	 * Sources for automatic update. Sucuri ranges are in built-in fallback (no public API).
	 */
	private static function providers(): array {
		return array(
			array(
				'name' => 'cloudflare',
				'type' => 'plain_list',
				'urls' => array(
					'https://www.cloudflare.com/ips-v4',
					'https://www.cloudflare.com/ips-v6',
				),
			),
		);
	}

	/**
	 * Refresh proxy IP ranges from providers.
	 */
	public static function refresh_proxy_ranges(): void {
		$all_ranges = array();
		foreach ( self::providers() as $provider ) {
			foreach ( $provider['urls'] as $url ) {
				$resp = wp_remote_get( $url, array( 'timeout' => 10 ) );
				if ( is_wp_error( $resp ) ) {
					continue;
				}

				$body  = wp_remote_retrieve_body( $resp );
				$lines = preg_split( '/\r\n|\r|\n/', $body );
				foreach ( $lines as $line ) {
					$line = trim( $line );
					if ( $line !== '' && self::is_valid_cidr( $line ) ) {
						$all_ranges[] = $line;
					}
				}
			}
		}

		$all_ranges = array_values( array_unique( array_filter( $all_ranges ) ) );
		if ( ! empty( $all_ranges ) ) {
			update_option(
				self::OPTION_PROXY_CACHE,
				array(
					'updated_at' => time(),
					'ranges'     => $all_ranges,
				),
				false
			);
		}
	}

	/**
	 * Schedule background refresh of proxy ranges.
	 */
	public static function schedule_refresh(): void {
		if ( ! wp_next_scheduled( self::CRON_HOOK_REFRESH ) ) {
			wp_schedule_event( time() + 300, 'daily', self::CRON_HOOK_REFRESH );
		}
	}

	/**
	 * Normalize IP string (strip port, brackets, quotes).
	 */
	private static function normalize_ip( $ip ): string {
		$ip = trim( (string) $ip );
		if ( preg_match( '/^\[(.+)\]:(\d+)$/', $ip, $m ) ) {
			$ip = $m[1];
		} // IPv6 with port
		if ( preg_match( '/^(\d{1,3}(?:\.\d{1,3}){3}):\d+$/', $ip, $m ) ) {
			$ip = $m[1];
		} // IPv4 with port
		return trim( $ip, "\"' " );
	}

	/**
	 * Check if string is a valid IP.
	 */
	private static function is_valid_ip( $ip ): bool {
		return (bool) filter_var( $ip, FILTER_VALIDATE_IP );
	}

	/**
	 * Check if string is a valid public (non-private, non-reserved) IP.
	 */
	private static function is_valid_public_ip( $ip ): bool {
		return (bool) filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE );
	}

	/**
	 * Check if IP is within any of the given CIDR ranges.
	 */
	private static function ip_in_any_cidr( $ip, $cidrs ): bool {
		foreach ( $cidrs as $cidr ) {
			if ( self::ip_in_cidr( $ip, $cidr ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Check if IP is within a single CIDR range.
	 */
	private static function ip_in_cidr( $ip, $cidr ): bool {
		$ip_bin = @inet_pton( $ip );
		$parts  = explode( '/', $cidr );
		$subnet = $parts[0];
		$mask_bits = isset( $parts[1] ) ? (int) $parts[1] : 0;
		$sub_bin   = @inet_pton( $subnet );
		if ( ! $ip_bin || ! $sub_bin || strlen( $ip_bin ) !== strlen( $sub_bin ) ) {
			return false;
		}

		$bytes = (int) ( $mask_bits / 8 );
		$bits  = $mask_bits % 8;
		for ( $i = 0; $i < $bytes; $i++ ) {
			if ( $ip_bin[ $i ] !== $sub_bin[ $i ] ) {
				return false;
			}
		}
		if ( $bits === 0 ) {
			return true;
		}
		$mask = chr( ( 0xFF << ( 8 - $bits ) ) & 0xFF );
		return ( $ip_bin[ $bytes ] & $mask ) === ( $sub_bin[ $bytes ] & $mask );
	}
}

add_action( 'maspik_refresh_proxy_ip_ranges', array( 'Maspik_Client_Ip', 'refresh_proxy_ranges' ) );
