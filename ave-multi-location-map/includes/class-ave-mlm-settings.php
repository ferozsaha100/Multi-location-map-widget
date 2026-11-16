<?php
/**
 * Settings > AVE Maps — site-wide map keys for the Office Map widget.
 *
 * Falls back to Agent View Extension's `ave_maps_settings` option when the
 * same keys are already configured there, so a site running both plugins only
 * enters them once.
 *
 * @package AVE_Multi_Location_Map
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // No direct access.
}

class AVE_MLM_Settings {

	const OPTION     = 'ave_mlm_maps';
	const GROUP      = 'ave_mlm_maps_group';
	const PAGE       = 'ave-mlm-maps';
	const AVE_OPTION = 'ave_maps_settings';

	public static function init() {
		add_action( 'admin_menu', [ __CLASS__, 'add_page' ] );
		add_action( 'admin_init', [ __CLASS__, 'register' ] );
		add_filter( 'plugin_action_links_' . plugin_basename( AVE_MLM_FILE ), [ __CLASS__, 'action_link' ] );
	}

	/**
	 * Read one setting: own option → Agent View Extension option → default.
	 *
	 * @param string $key carto_key | google_key | contact_email.
	 * @return string
	 */
	public static function get( $key ) {
		$own = get_option( self::OPTION, [] );
		if ( is_array( $own ) && ! empty( $own[ $key ] ) ) {
			return (string) $own[ $key ];
		}

		$ave = get_option( self::AVE_OPTION, [] );
		if ( is_array( $ave ) && ! empty( $ave[ $key ] ) ) {
			return (string) $ave[ $key ];
		}

		return 'contact_email' === $key ? (string) get_option( 'admin_email' ) : '';
	}

	public static function settings_url() {
		return admin_url( 'options-general.php?page=' . self::PAGE );
	}

	public static function add_page() {
		add_options_page(
			__( 'AVE Maps', 'ave-mlm' ),
			__( 'AVE Maps', 'ave-mlm' ),
			'manage_options',
			self::PAGE,
			[ __CLASS__, 'render_page' ]
		);
	}

	public static function register() {
		register_setting(
			self::GROUP,
			self::OPTION,
			[
				'type'              => 'array',
				'sanitize_callback' => [ __CLASS__, 'sanitize' ],
				'default'           => [],
			]
		);
	}

	/**
	 * @param mixed $input Raw form input.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$input = is_array( $input ) ? $input : [];
		$key   = function ( $value ) {
			return preg_replace( '/[^A-Za-z0-9_\-\.]/', '', (string) $value );
		};

		return [
			'carto_key'     => isset( $input['carto_key'] ) ? $key( $input['carto_key'] ) : '',
			'google_key'    => isset( $input['google_key'] ) ? $key( $input['google_key'] ) : '',
			'contact_email' => isset( $input['contact_email'] ) ? sanitize_email( $input['contact_email'] ) : '',
		];
	}

	public static function action_link( $links ) {
		array_unshift( $links, '<a href="' . esc_url( self::settings_url() ) . '">' . esc_html__( 'Map settings', 'ave-mlm' ) . '</a>' );
		return $links;
	}

	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$own    = get_option( self::OPTION, [] );
		$own    = is_array( $own ) ? $own : [];
		$fields = [
			'carto_key'     => [
				'label' => __( 'CARTO basemaps key', 'ave-mlm' ),
				'help'  => __( 'Required for the Leaflet + CARTO engine (CARTO stopped serving tiles without a key on 25 September 2026). Free, no CARTO account needed. Use one key per client site. The "OpenStreetMap / CARTO" credit stays visible on the map.', 'ave-mlm' ),
				'link'  => 'https://carto.com/basemaps/apikey',
				'type'  => 'text',
			],
			'google_key'    => [
				'label' => __( 'Google Maps API key', 'ave-mlm' ),
				'help'  => __( 'Required only for widgets set to the Google Maps engine. Enable "Maps JavaScript API" and restrict the key to this website (HTTP referrers). Geocoding is not needed: pins are found with OpenStreetMap in the editor.', 'ave-mlm' ),
				'link'  => 'https://console.cloud.google.com/google/maps-apis/credentials',
				'type'  => 'text',
			],
			'contact_email' => [
				'label' => __( 'Contact email for OpenStreetMap lookups', 'ave-mlm' ),
				'help'  => __( 'Sent with "Find on map" requests, as OpenStreetMap Nominatim\'s usage policy requires. Defaults to the site admin email.', 'ave-mlm' ),
				'link'  => 'https://operations.osmfoundation.org/policies/nominatim/',
				'type'  => 'email',
			],
		];
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'AVE Maps', 'ave-mlm' ); ?></h1>
			<p><?php esc_html_e( 'Keys used by the Office Map widget on every page of this site.', 'ave-mlm' ); ?></p>
			<form method="post" action="options.php">
				<?php settings_fields( self::GROUP ); ?>
				<table class="form-table" role="presentation">
					<?php foreach ( $fields as $name => $field ) : ?>
						<?php
						$value    = isset( $own[ $name ] ) ? (string) $own[ $name ] : '';
						$fallback = '' === $value ? self::get( $name ) : '';
						?>
						<tr>
							<th scope="row"><label for="ave-mlm-<?php echo esc_attr( $name ); ?>"><?php echo esc_html( $field['label'] ); ?></label></th>
							<td>
								<input type="<?php echo esc_attr( $field['type'] ); ?>" class="regular-text" id="ave-mlm-<?php echo esc_attr( $name ); ?>"
									name="<?php echo esc_attr( self::OPTION . '[' . $name . ']' ); ?>"
									value="<?php echo esc_attr( $value ); ?>" autocomplete="off"
									<?php if ( $fallback ) : ?>placeholder="<?php echo esc_attr( 'contact_email' === $name ? $fallback : __( 'Using the key from Agent View Extension', 'ave-mlm' ) ); ?>"<?php endif; ?>>
								<p class="description"><?php echo esc_html( $field['help'] ); ?> <a href="<?php echo esc_url( $field['link'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Learn more', 'ave-mlm' ); ?></a></p>
							</td>
						</tr>
					<?php endforeach; ?>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
