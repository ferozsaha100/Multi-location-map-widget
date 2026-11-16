<?php
/**
 * Server-side address geocoding for AVE Multi-Location Map.
 *
 * Why this exists: the browser's google.maps.Geocoder is rate limited per
 * visitor session, which caps a map at roughly 20 address-only pins per visit
 * no matter how the requests are retried. Google's guidance for "a fixed set of
 * addresses" is to geocode them server-side with the Geocoding API web service.
 *
 * Each address is looked up once and the coordinates are cached as a transient
 * for 27-30 days (Google's Geocoding API terms allow caching lat/lng for up to
 * 30 consecutive days). The jitter spreads refreshes out so a map's addresses
 * don't all expire on the same page view.
 *
 * @package AVE_Multi_Location_Map
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // No direct access.
}

class AVE_MLM_Geocoder {

	const ENDPOINT      = 'https://maps.googleapis.com/maps/api/geocode/json';
	const CACHE_PREFIX  = 'ave_mlm_geo_';
	const DENIED_PREFIX = 'ave_mlm_geo_denied_';

	/** Per-request HTTP timeout, seconds. */
	const REQUEST_TIMEOUT = 5;

	/** Statuses that mean the key itself cannot be used — stop sending requests. */
	const KEY_ERRORS = [ 'REQUEST_DENIED', 'OVER_DAILY_LIMIT' ];

	/**
	 * Resolve a list of addresses to coordinates.
	 *
	 * @param string[] $addresses Raw addresses (as typed in the widget).
	 * @param string   $api_key   Key with the Geocoding API enabled and NO
	 *                            HTTP-referrer restriction.
	 * @param array    $args      'fresh' => true skips the short "key was
	 *                            denied" back-off (used in the Elementor editor
	 *                            so fixes to the key show up immediately).
	 * @return array {
	 *     @type array  $coords    address => [ 'lat' => float, 'lng' => float ].
	 *     @type array  $failed    address => Google status (e.g. ZERO_RESULTS).
	 *     @type string $key_error '' when fine, 'NO_KEY', or "STATUS: message".
	 *     @type int    $pending   Addresses not attempted this time (time budget
	 *                             or key error) — the browser handles them.
	 *     @type int    $cached    Addresses served from cache.
	 *     @type int    $requests  HTTP requests sent to Google.
	 * }
	 */
	public static function lookup( array $addresses, $api_key, array $args = [] ) {
		$result = [
			'coords'    => [],
			'failed'    => [],
			'key_error' => '',
			'pending'   => 0,
			'cached'    => 0,
			'requests'  => 0,
		];

		$todo = [];
		foreach ( array_unique( array_map( 'strval', $addresses ) ) as $address ) {
			if ( '' === trim( $address ) ) {
				continue;
			}
			$hit = get_transient( self::cache_key( $address ) );
			if ( is_array( $hit ) && isset( $hit['lat'], $hit['lng'] ) ) {
				$result['coords'][ $address ] = [
					'lat' => (float) $hit['lat'],
					'lng' => (float) $hit['lng'],
				];
				$result['cached']++;
			} elseif ( is_array( $hit ) && ! empty( $hit['status'] ) ) {
				$result['failed'][ $address ] = (string) $hit['status']; // Recent "not found".
			} else {
				$todo[] = $address;
			}
		}

		if ( ! $todo ) {
			return $result;
		}

		$api_key = trim( (string) $api_key );
		if ( '' === $api_key ) {
			$result['key_error'] = 'NO_KEY';
			$result['pending']   = count( $todo );
			return $result;
		}

		$denied_key = self::DENIED_PREFIX . md5( $api_key );
		if ( empty( $args['fresh'] ) ) {
			$denied = get_transient( $denied_key );
			if ( $denied ) {
				$result['key_error'] = (string) $denied;
				$result['pending']   = count( $todo );
				return $result;
			}
		}

		/**
		 * Max seconds one page render may spend on uncached lookups. Anything
		 * left over is handed to the browser and picked up server-side on the
		 * next render. A typical lookup takes 0.1-0.3s.
		 */
		$budget       = (float) apply_filters( 'ave_mlm_geocode_time_budget', 10.0 );
		$extra_params = (array) apply_filters( 'ave_mlm_geocode_request_params', [] ); // e.g. [ 'region' => 'us' ].
		$started      = microtime( true );
		$net_failures = 0;
		$count        = count( $todo );

		foreach ( $todo as $i => $address ) {
			if ( ( microtime( true ) - $started ) > $budget || $net_failures >= 3 ) {
				$result['pending'] = $count - $i;
				break;
			}

			$url = self::ENDPOINT . '?' . http_build_query(
				array_merge( $extra_params, [ 'address' => $address, 'key' => $api_key ] ),
				'',
				'&',
				PHP_QUERY_RFC3986
			);

			$response = wp_remote_get( $url, [ 'timeout' => self::REQUEST_TIMEOUT ] );
			$result['requests']++;

			if ( is_wp_error( $response ) ) {
				$net_failures++;
				$result['failed'][ $address ] = 'HTTP_ERROR: ' . $response->get_error_message();
				continue;
			}

			$body   = json_decode( (string) wp_remote_retrieve_body( $response ), true );
			$status = ( is_array( $body ) && isset( $body['status'] ) )
				? (string) $body['status']
				: 'HTTP_' . wp_remote_retrieve_response_code( $response );

			if ( 'OK' === $status && isset( $body['results'][0]['geometry']['location']['lat'], $body['results'][0]['geometry']['location']['lng'] ) ) {
				$lat = (float) $body['results'][0]['geometry']['location']['lat'];
				$lng = (float) $body['results'][0]['geometry']['location']['lng'];
				set_transient( self::cache_key( $address ), [ 'lat' => $lat, 'lng' => $lng ], self::ttl() );
				$result['coords'][ $address ] = [ 'lat' => $lat, 'lng' => $lng ];
				continue;
			}

			if ( in_array( $status, self::KEY_ERRORS, true ) ) {
				$message = $status . ( ! empty( $body['error_message'] ) ? ': ' . $body['error_message'] : '' );
				set_transient( $denied_key, $message, 10 * MINUTE_IN_SECONDS );
				$result['key_error'] = $message;
				$result['pending']   = $count - $i;
				break;
			}

			if ( 'ZERO_RESULTS' === $status ) {
				// Don't re-ask Google about a bad address on every page view.
				set_transient( self::cache_key( $address ), [ 'status' => $status ], DAY_IN_SECONDS );
			}
			$result['failed'][ $address ] = $status;
		}

		return $result;
	}

	/**
	 * Cache key for an address (case/whitespace-insensitive).
	 *
	 * @param string $address Address.
	 * @return string
	 */
	public static function cache_key( $address ) {
		$normalized = preg_replace( '/\s+/u', ' ', trim( (string) $address ) );
		$normalized = function_exists( 'mb_strtolower' ) ? mb_strtolower( $normalized, 'UTF-8' ) : strtolower( $normalized );
		return self::CACHE_PREFIX . md5( $normalized );
	}

	/**
	 * 27-30 days: never longer than Google's 30-day limit, jittered so a
	 * map's addresses refresh gradually.
	 *
	 * @return int
	 */
	private static function ttl() {
		return 30 * DAY_IN_SECONDS - wp_rand( 0, 3 * DAY_IN_SECONDS );
	}
}
