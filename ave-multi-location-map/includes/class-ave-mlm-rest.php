<?php
/**
 * Editor-only REST endpoint behind the "Find on map" buttons.
 *
 * POST /wp-json/ave-mlm/v1/geocode  { address }  →  { lat, lng, label, cached }
 * Requires a logged-in user who can edit posts; the editor sends the standard
 * wp_rest nonce (X-WP-Nonce), so cookie auth is CSRF-protected by core.
 *
 * @package AVE_Multi_Location_Map
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // No direct access.
}

class AVE_MLM_REST {

	const NAMESPACE_V1 = 'ave-mlm/v1';

	public static function init() {
		add_action( 'rest_api_init', [ __CLASS__, 'register_routes' ] );
	}

	public static function register_routes() {
		register_rest_route(
			self::NAMESPACE_V1,
			'/geocode',
			[
				'methods'             => 'POST',
				'callback'            => [ __CLASS__, 'geocode' ],
				'permission_callback' => [ __CLASS__, 'can_edit' ],
				'args'                => [
					'address' => [
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					],
				],
			]
		);
	}

	public static function can_edit() {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function geocode( $request ) {
		$result = AVE_MLM_Nominatim::geocode( (string) $request->get_param( 'address' ) );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response(
			[
				'lat'    => $result['lat'],
				'lng'    => $result['lng'],
				'label'  => $result['label'],
				'cached' => ! empty( $result['cached'] ),
			]
		);
	}
}
