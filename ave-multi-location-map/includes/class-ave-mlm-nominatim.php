<?php
/**
 * Address → coordinates via OpenStreetMap Nominatim, used ONLY by the
 * editor's "Find on map" buttons. Nothing here runs on a visitor's page view.
 *
 * Follows the Nominatim usage policy: an identifying User-Agent, at most one
 * request per second (1.1 s spacing, enforced server-side), results cached,
 * and lookups only on an explicit editor action (no autocomplete).
 * https://operations.osmfoundation.org/policies/nominatim/
 *
 * @package AVE_Multi_Location_Map
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // No direct access.
}

class AVE_MLM_Nominatim {

	const ENDPOINT     = 'https://nominatim.openstreetmap.org/search';
	const CACHE_PREFIX = 'ave_mlm_osm_';
	const LAST_OPTION  = 'ave_mlm_osm_last_request';

	/**
	 * Geocode one address.
	 *
	 * @param string $address Address as typed in the widget.
	 * @return array|WP_Error { lat, lng, label, cached } on success. WP_Error
	 *                        codes: ave_mlm_empty (400), ave_mlm_not_found (404),
	 *                        ave_mlm_rate_limited (429), ave_mlm_lookup_failed (502).
	 */
	public static function geocode( $address ) {
		$address = trim( preg_replace( '/\s+/u', ' ', (string) $address ) );
		if ( '' === $address ) {
			return new WP_Error( 'ave_mlm_empty', __( 'Enter an address first.', 'ave-mlm' ), [ 'status' => 400 ] );
		}

		$cache_key = self::cache_key( $address );
		$hit       = get_transient( $cache_key );
		if ( is_array( $hit ) ) {
			return isset( $hit['lat'], $hit['lng'] ) ? $hit + [ 'cached' => true ] : self::not_found();
		}

		// Nominatim doesn't index suite/unit numbers, so retry once without them.
		foreach ( array_unique( [ $address, self::simplify( $address ) ] ) as $query ) {
			if ( '' === $query ) {
				continue;
			}
			$found = self::request( $query );
			if ( is_wp_error( $found ) ) {
				return $found; // Network / rate limit: don't cache, let the editor retry.
			}
			if ( $found ) {
				// OSM data (ODbL) may be stored; refresh yearly in case the map improves.
				set_transient( $cache_key, $found, YEAR_IN_SECONDS );
				return $found + [ 'cached' => false ];
			}
		}

		set_transient( $cache_key, [ 'miss' => 1 ], HOUR_IN_SECONDS );
		return self::not_found();
	}

	/**
	 * Strip unit designators ("Suite 200", "Ste. 4B", "Unit 3", "Apt 2", "#105").
	 *
	 * @param string $address Address.
	 * @return string
	 */
	public static function simplify( $address ) {
		$s = preg_replace( '/[,\s]*\b(?:suite|ste\.?|unit|apt\.?|apartment)\s*#?\s*[\w\-]+/iu', '', (string) $address );
		$s = preg_replace( '/[,\s]*#\s*[\w\-]+/u', '', $s );
		$s = preg_replace( '/\s*,(\s*,)+/u', ',', $s );
		return trim( preg_replace( '/\s+/u', ' ', $s ), " ,\t" );
	}

	/**
	 * @param string $address Address.
	 * @return string
	 */
	public static function cache_key( $address ) {
		$normalized = preg_replace( '/\s+/u', ' ', trim( (string) $address ) );
		$normalized = function_exists( 'mb_strtolower' ) ? mb_strtolower( $normalized, 'UTF-8' ) : strtolower( $normalized );
		return self::CACHE_PREFIX . md5( $normalized );
	}

	/**
	 * One HTTP request. Returns the first match, null for no match, WP_Error
	 * for transport/HTTP problems.
	 *
	 * @param string $query Query string.
	 * @return array|null|WP_Error
	 */
	private static function request( $query ) {
		self::throttle();

		$params = (array) apply_filters(
			'ave_mlm_nominatim_params',
			[
				'format'         => 'jsonv2',
				'limit'          => 1,
				'addressdetails' => 0,
			],
			$query
		);
		$url    = self::ENDPOINT . '?' . http_build_query( array_merge( $params, [ 'q' => $query ] ), '', '&', PHP_QUERY_RFC3986 );

		$response = wp_remote_get(
			$url,
			[
				'timeout'    => 10,
				'user-agent' => self::user_agent(),
				'headers'    => [
					'Accept'          => 'application/json',
					'Accept-Language' => substr( (string) get_locale(), 0, 2 ),
					'Referer'         => home_url( '/' ),
				],
			]
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'ave_mlm_lookup_failed',
				/* translators: %s: transport error message */
				sprintf( __( 'OpenStreetMap lookup failed: %s', 'ave-mlm' ), $response->get_error_message() ),
				[ 'status' => 502 ]
			);
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 429 === $code || 403 === $code ) {
			return new WP_Error( 'ave_mlm_rate_limited', __( 'OpenStreetMap is limiting requests from this server. Wait a minute, then try again.', 'ave-mlm' ), [ 'status' => 429 ] );
		}
		if ( 200 !== $code ) {
			/* translators: %d: HTTP status code */
			return new WP_Error( 'ave_mlm_lookup_failed', sprintf( __( 'OpenStreetMap returned HTTP %d. Try again shortly.', 'ave-mlm' ), $code ), [ 'status' => 502 ] );
		}

		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) ) {
			return new WP_Error( 'ave_mlm_lookup_failed', __( 'OpenStreetMap sent an unreadable response. Try again shortly.', 'ave-mlm' ), [ 'status' => 502 ] );
		}
		if ( ! isset( $body[0]['lat'], $body[0]['lon'] ) || ! is_numeric( $body[0]['lat'] ) || ! is_numeric( $body[0]['lon'] ) ) {
			return null;
		}

		$lat = (float) $body[0]['lat'];
		$lng = (float) $body[0]['lon'];
		if ( $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180 ) {
			return null;
		}

		return [
			'lat'   => round( $lat, 6 ),
			'lng'   => round( $lng, 6 ),
			'label' => isset( $body[0]['display_name'] ) ? (string) $body[0]['display_name'] : '',
		];
	}

	/**
	 * Keep request starts at least 1.1 s apart across the whole site.
	 */
	private static function throttle() {
		$interval = (float) apply_filters( 'ave_mlm_nominatim_interval', 1.1 );
		$wait     = (float) get_option( self::LAST_OPTION, 0 ) + $interval - microtime( true );
		if ( $wait > 0 ) {
			usleep( (int) ( min( $wait, $interval ) * 1000000 ) );
		}
		update_option( self::LAST_OPTION, microtime( true ), false );
	}

	private static function user_agent() {
		return sprintf( 'AVE-Multi-Location-Map/%s (+%s; %s)', AVE_MLM_VERSION, home_url( '/' ), AVE_MLM_Settings::get( 'contact_email' ) );
	}

	private static function not_found() {
		return new WP_Error( 'ave_mlm_not_found', __( 'OpenStreetMap could not find this address. Check the spelling, or enter latitude and longitude yourself.', 'ave-mlm' ), [ 'status' => 404 ] );
	}
}
