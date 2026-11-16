<?php
/**
 * Plugin Name:       AVE Multi-Location Map
 * Description:       Elementor widgets for multi-office maps. Office Map (new in 2.0): unlimited pins found once in the editor, two layouts (hover popup, or office list beside the map), Leaflet + CARTO or Google Maps. Includes the original Multi-Location Map widget.
 * Plugin URI:         https://agentviewdigital.com
 * Version:            2.4.1
 * Requires at least:  6.0
 * Requires PHP:       7.4
 * Author:             Agent View Digital
 * Author URI:         https://agentviewdigital.com
 * License:            GPL-2.0+
 * License URI:        https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:        ave-mlm
 * Elementor tested up to: 3.26.0
 * Elementor Pro tested up to: 3.26.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // No direct access.
}

define( 'AVE_MLM_VERSION', '2.4.1' );
define( 'AVE_MLM_FILE', __FILE__ );
define( 'AVE_MLM_PATH', plugin_dir_path( __FILE__ ) );
define( 'AVE_MLM_URL', plugin_dir_url( __FILE__ ) );
define( 'AVE_MLM_LEAFLET_VERSION', '1.9.4' );

require_once AVE_MLM_PATH . 'includes/class-ave-mlm-settings.php';
require_once AVE_MLM_PATH . 'includes/class-ave-mlm-nominatim.php';
require_once AVE_MLM_PATH . 'includes/class-ave-mlm-rest.php';

/**
 * Bootstraps the plugin once Elementor is confirmed active and loaded.
 */
final class AVE_Multi_Location_Map_Plugin {

	const MINIMUM_ELEMENTOR_VERSION = '3.5.0';
	const MINIMUM_PHP_VERSION       = '7.4';

	private static $instance = null;

	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function __construct() {
		AVE_MLM_Settings::init();
		AVE_MLM_REST::init();
		add_action( 'plugins_loaded', [ $this, 'on_plugins_loaded' ] );
	}

	public function on_plugins_loaded() {

		if ( ! did_action( 'elementor/loaded' ) ) {
			add_action( 'admin_notices', [ $this, 'admin_notice_missing_elementor' ] );
			return;
		}

		if ( ! version_compare( ELEMENTOR_VERSION, self::MINIMUM_ELEMENTOR_VERSION, '>=' ) ) {
			add_action( 'admin_notices', [ $this, 'admin_notice_minimum_elementor_version' ] );
			return;
		}

		if ( version_compare( PHP_VERSION, self::MINIMUM_PHP_VERSION, '<' ) ) {
			add_action( 'admin_notices', [ $this, 'admin_notice_minimum_php_version' ] );
			return;
		}

		add_action( 'elementor/widgets/register', [ $this, 'register_widgets' ] );
		add_action( 'elementor/elements/categories_registered', [ $this, 'register_category' ] );
		add_action( 'elementor/frontend/after_register_scripts', [ $this, 'register_scripts' ] );
		add_action( 'elementor/frontend/after_register_styles', [ $this, 'register_styles' ] );
		add_action( 'elementor/editor/after_enqueue_scripts', [ $this, 'register_scripts' ] );
		add_action( 'elementor/editor/after_enqueue_scripts', [ $this, 'enqueue_editor_scripts' ] );
	}

	public function register_category( $elements_manager ) {
		$elements_manager->add_category(
			'ave-widgets',
			[
				'title' => __( 'Agent View', 'ave-mlm' ),
				'icon'  => 'fa fa-plug',
			]
		);
	}

	public function register_scripts() {
		wp_register_script(
			'ave-multi-location-map',
			AVE_MLM_URL . 'assets/js/ave-multi-location-map.js',
			[ 'elementor-frontend' ],
			AVE_MLM_VERSION,
			true
		);
		// Office Map runtime. Leaflet / Google load lazily from inside it.
		wp_register_script(
			'ave-office-map',
			AVE_MLM_URL . 'assets/js/ave-office-map.js',
			[],
			AVE_MLM_VERSION,
			true
		);
	}

	public function register_styles() {
		wp_register_style(
			'ave-multi-location-map',
			AVE_MLM_URL . 'assets/css/ave-multi-location-map.css',
			[],
			AVE_MLM_VERSION
		);
		wp_register_style(
			'ave-office-map',
			AVE_MLM_URL . 'assets/css/ave-office-map.css',
			[],
			AVE_MLM_VERSION
		);
	}

	/**
	 * "Find on map" / "Find all missing pins" buttons in the Elementor panel.
	 */
	public function enqueue_editor_scripts() {
		wp_enqueue_script(
			'ave-office-map-editor',
			AVE_MLM_URL . 'assets/js/ave-office-map-editor.js',
			[ 'jquery' ],
			AVE_MLM_VERSION,
			true
		);
		wp_localize_script(
			'ave-office-map-editor',
			'aveOfficeMapEditor',
			[
				'restUrl' => esc_url_raw( rest_url( AVE_MLM_REST::NAMESPACE_V1 . '/geocode' ) ),
				'nonce'   => wp_create_nonce( 'wp_rest' ),
				'i18n'    => [
					'finding'     => __( 'Finding…', 'ave-mlm' ),
					'noAddress'   => __( 'Enter an address first.', 'ave-mlm' ),
					'found'       => __( 'Pin placed:', 'ave-mlm' ),
					/* translators: 1: current row, 2: total rows */
					'progress'    => __( 'Finding %1$d of %2$d…', 'ave-mlm' ),
					/* translators: 1: rows found, 2: rows attempted */
					'doneSummary' => __( 'Found %1$d of %2$d.', 'ave-mlm' ),
					'notFound'    => __( 'Not found:', 'ave-mlm' ),
					'allSet'      => __( 'Every office already has a pin.', 'ave-mlm' ),
					'failed'      => __( 'Lookup failed. Try again.', 'ave-mlm' ),
				],
			]
		);
	}

	public function register_widgets( $widgets_manager ) {
		require_once AVE_MLM_PATH . 'includes/class-ave-mlm-geocoder.php';
		require_once AVE_MLM_PATH . 'widgets/class-ave-multi-location-map-widget.php';
		require_once AVE_MLM_PATH . 'widgets/class-ave-office-map-widget.php';
		$widgets_manager->register( new \AVE_Multi_Location_Map_Widget() );
		$widgets_manager->register( new \AVE_Office_Map_Widget() );
	}

	public function admin_notice_missing_elementor() {
		if ( isset( $_GET['activate'] ) ) {
			unset( $_GET['activate'] );
		}

		$message = sprintf(
			/* translators: 1: Plugin name 2: Elementor */
			esc_html__( '"%1$s" requires "%2$s" to be installed and activated.', 'ave-mlm' ),
			'<strong>' . esc_html__( 'AVE Multi-Location Map', 'ave-mlm' ) . '</strong>',
			'<strong>' . esc_html__( 'Elementor', 'ave-mlm' ) . '</strong>'
		);

		printf( '<div class="notice notice-warning is-dismissible"><p>%1$s</p></div>', $message );
	}

	public function admin_notice_minimum_elementor_version() {
		if ( isset( $_GET['activate'] ) ) {
			unset( $_GET['activate'] );
		}

		$message = sprintf(
			/* translators: 1: Plugin name 2: Elementor 3: Required version */
			esc_html__( '"%1$s" requires "%2$s" version %3$s or greater.', 'ave-mlm' ),
			'<strong>' . esc_html__( 'AVE Multi-Location Map', 'ave-mlm' ) . '</strong>',
			'<strong>' . esc_html__( 'Elementor', 'ave-mlm' ) . '</strong>',
			self::MINIMUM_ELEMENTOR_VERSION
		);

		printf( '<div class="notice notice-warning is-dismissible"><p>%1$s</p></div>', $message );
	}

	public function admin_notice_minimum_php_version() {
		if ( isset( $_GET['activate'] ) ) {
			unset( $_GET['activate'] );
		}

		$message = sprintf(
			/* translators: 1: Plugin name 2: PHP 3: Required version */
			esc_html__( '"%1$s" requires "%2$s" version %3$s or greater.', 'ave-mlm' ),
			'<strong>' . esc_html__( 'AVE Multi-Location Map', 'ave-mlm' ) . '</strong>',
			'<strong>' . esc_html__( 'PHP', 'ave-mlm' ) . '</strong>',
			self::MINIMUM_PHP_VERSION
		);

		printf( '<div class="notice notice-warning is-dismissible"><p>%1$s</p></div>', $message );
	}
}

AVE_Multi_Location_Map_Plugin::instance();
