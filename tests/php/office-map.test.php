<?php
/**
 * Tests for AVE Multi-Location Map 2.0 (Office Map widget, Nominatim, REST, settings).
 * Usage: php tests/php/office-map.test.php <plugin-dir> <out-dir> <asset-base-url>
 */
namespace {
	$GLOBALS['plugin'] = rtrim( $argv[1], '/' );
	$GLOBALS['out']    = rtrim( $argv[2], '/' );
	$GLOBALS['base']   = rtrim( $argv[3], '/' ) . '/';

	define( 'ABSPATH', '/tmp/wp/' );
	define( 'MINUTE_IN_SECONDS', 60 );
	define( 'HOUR_IN_SECONDS', 3600 );
	define( 'DAY_IN_SECONDS', 86400 );
	define( 'YEAR_IN_SECONDS', 31536000 );
	define( 'AVE_MLM_VERSION', '2.0.0' );
	define( 'AVE_MLM_FILE', '/tmp/wp/wp-content/plugins/ave-multi-location-map/ave-multi-location-map.php' );
	define( 'AVE_MLM_PATH', $GLOBALS['plugin'] . '/' );
	define( 'AVE_MLM_URL', $GLOBALS['base'] );
	define( 'AVE_MLM_LEAFLET_VERSION', '1.9.4' );

	$GLOBALS['T'] = []; $GLOBALS['O'] = []; $GLOBALS['HTTP'] = []; $GLOBALS['HTTP_Q'] = [];
	$GLOBALS['CAN'] = true; $GLOBALS['FILTERS'] = []; $GLOBALS['ROUTES'] = [];

	class WP_Error {
		public $code; public $message; public $data;
		function __construct( $c = '', $m = '', $d = null ) { $this->code = $c; $this->message = $m; $this->data = $d; }
		function get_error_message() { return $this->message; }
		function get_error_code() { return $this->code; }
		function get_error_data() { return $this->data; }
	}
	class WP_REST_Request { private $p; function __construct( $p ) { $this->p = $p; } function get_param( $k ) { return isset( $this->p[ $k ] ) ? $this->p[ $k ] : null; } }
	function is_wp_error( $x ) { return $x instanceof WP_Error; }
	function get_transient( $k ) { return isset( $GLOBALS['T'][ $k ] ) ? $GLOBALS['T'][ $k ][0] : false; }
	function set_transient( $k, $v, $ttl ) { $GLOBALS['T'][ $k ] = [ $v, $ttl ]; return true; }
	function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['O'] ) ? $GLOBALS['O'][ $k ] : $d; }
	function update_option( $k, $v, $a = null ) { $GLOBALS['O'][ $k ] = $v; return true; }
	function apply_filters( $tag, $v ) { return isset( $GLOBALS['FILTERS'][ $tag ] ) ? $GLOBALS['FILTERS'][ $tag ]( $v ) : $v; }
	function add_action() {} function add_filter() {}
	function plugin_basename( $f ) { return 'ave-multi-location-map/ave-multi-location-map.php'; }
	function home_url( $p = '' ) { return 'https://example-realty.com' . $p; }
	function admin_url( $p = '' ) { return 'https://example-realty.com/wp-admin/' . $p; }
	function get_locale() { return 'en_US'; }
	function current_user_can( $c ) { return $GLOBALS['CAN']; }
	function register_rest_route( $ns, $route, $args ) { $GLOBALS['ROUTES'][ $ns . $route ] = $args; }
	function rest_ensure_response( $d ) { return $d; }
	function sanitize_email( $e ) { return filter_var( $e, FILTER_VALIDATE_EMAIL ) ? $e : ''; }
	function sanitize_text_field( $s ) { return trim( strip_tags( $s ) ); }
	function __( $s ) { return $s; }
	function _n( $a, $b, $n ) { return 1 == $n ? $a : $b; }
	function esc_html__( $s ) { return htmlspecialchars( $s, ENT_QUOTES ); }
	function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
	function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
	function esc_url_raw( $u ) { $u = trim( (string) $u ); if ( preg_match( '#^(javascript|data|vbscript):#i', $u ) ) return ''; return $u; }
	function esc_url( $u ) { return htmlspecialchars( esc_url_raw( $u ), ENT_QUOTES ); }
	function wp_strip_all_tags( $s ) { return trim( strip_tags( (string) $s ) ); }
	function wp_json_encode( $d ) { return json_encode( $d ); }
	function sanitize_html_class( $c ) { return preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $c ); }
	function wp_list_pluck( $list, $field ) { return array_map( function ( $x ) use ( $field ) { return $x[ $field ]; }, $list ); }
	function wp_remote_retrieve_body( $r ) { return $r['body']; }
	function wp_remote_retrieve_response_code( $r ) { return $r['response']['code']; }
	function wp_remote_get( $url, $args ) {
		$GLOBALS['HTTP'][] = [ 'url' => $url, 'args' => $args, 't' => microtime( true ) ];
		$next = array_shift( $GLOBALS['HTTP_Q'] );
		if ( null === $next ) throw new Exception( 'Unexpected HTTP request: ' . $url );
		return is_callable( $next ) ? $next( $url ) : $next;
	}
	function osm_hit( $lat, $lon, $label = 'Somewhere, FL' ) { return [ 'response' => [ 'code' => 200 ], 'body' => json_encode( [ [ 'lat' => (string) $lat, 'lon' => (string) $lon, 'display_name' => $label ] ] ) ]; }
	function osm_miss() { return [ 'response' => [ 'code' => 200 ], 'body' => '[]' ]; }
	function osm_code( $c ) { return [ 'response' => [ 'code' => $c ], 'body' => '' ]; }
}

namespace Elementor {
	class Controls_Manager {
		const TEXT = 'text'; const SELECT = 'select'; const SWITCHER = 'switcher'; const REPEATER = 'repeater'; const BUTTON = 'button';
		const RAW_HTML = 'raw_html'; const URL = 'url'; const NUMBER = 'number'; const SLIDER = 'slider'; const DIMENSIONS = 'dimensions';
		const COLOR = 'color'; const TAB_STYLE = 'style'; const TEXTAREA = 'textarea'; const HEADING = 'heading'; const MEDIA = 'media';
	}
	class Group_Control_Border { static function get_type() { return 'border'; } }
	class Group_Control_Typography { static function get_type() { return 'typography'; } }
	class Repeater { public $c = []; function add_control( $n, $a ) { $this->c[ $n ] = $a; } function get_controls() { return $this->c; } }
	class Widget_Base {
		public $settings = []; public $controls = []; public $open = null;
		function get_settings_for_display() { return $this->settings; }
		function get_id() { return 'w1'; }
		function start_controls_section( $n, $a = [] ) { if ( $this->open ) throw new \Exception( "section $n opened inside {$this->open}" ); $this->open = $n; }
		function end_controls_section() { if ( ! $this->open ) throw new \Exception( 'end without start' ); $this->open = null; }
		function add_control( $n, $a ) { if ( ! $this->open ) throw new \Exception( "control $n outside section" ); if ( isset( $this->controls[ $n ] ) ) throw new \Exception( "duplicate control $n" ); $this->controls[ $n ] = $a; }
		function add_responsive_control( $n, $a ) { $this->add_control( $n, $a ); }
		function add_group_control( $t, $a ) { $this->add_control( $a['name'], $a ); }
		function start_controls_tabs( $n ) {} function end_controls_tabs() {} function start_controls_tab( $n, $a ) {} function end_controls_tab() {}
	}
	class Mode { public $on = false; function is_edit_mode() { return $this->on; } function is_preview_mode() { return $this->on; } }
	class Plugin { public static $instance; public $editor; public $preview; }
	Plugin::$instance = new Plugin(); Plugin::$instance->editor = new Mode(); Plugin::$instance->preview = new Mode();
}

namespace Tests {
	$plugin = $GLOBALS['plugin']; $out = $GLOBALS['out'];
	require $plugin . '/includes/class-ave-mlm-settings.php';
	require $plugin . '/includes/class-ave-mlm-nominatim.php';
	require $plugin . '/includes/class-ave-mlm-rest.php';
	require $plugin . '/widgets/class-ave-office-map-widget.php';

	$fails = 0; $passes = 0;
	function check( $cond, $label ) { global $fails, $passes; if ( $cond ) { $passes++; echo "PASS $label\n"; } else { $fails++; echo "FAIL $label\n"; } }
	function reset_all() { $GLOBALS['T'] = []; $GLOBALS['O'] = [ 'admin_email' => 'office@example-realty.com' ]; $GLOBALS['HTTP'] = []; $GLOBALS['HTTP_Q'] = []; $GLOBALS['CAN'] = true; $GLOBALS['FILTERS'] = [ 'ave_mlm_nominatim_interval' => function () { return 0.0; } ]; }

	/* ================= Nominatim ================= */
	reset_all();
	$GLOBALS['HTTP_Q'] = [ osm_hit( 27.7727601, -82.6359601, 'St. Petersburg, Pinellas County, Florida' ) ];
	$r = \AVE_MLM_Nominatim::geocode( '100 2nd Ave N,  St. Petersburg, FL 33701' );
	check( is_array( $r ) && 27.77276 === $r['lat'] && -82.63596 === $r['lng'] && false === $r['cached'], 'N1: geocode returns rounded lat/lng' );
	$req = $GLOBALS['HTTP'][0];
	parse_str( parse_url( $req['url'], PHP_URL_QUERY ), $q );
	check( 0 === strpos( $req['url'], 'https://nominatim.openstreetmap.org/search?' ) && '100 2nd Ave N, St. Petersburg, FL 33701' === $q['q'] && 'jsonv2' === $q['format'] && '1' === (string) $q['limit'], 'N1: request goes to Nominatim /search with q, jsonv2, limit=1' );
	check( false !== strpos( $req['args']['user-agent'], 'AVE-Multi-Location-Map/2.0.0' ) && false !== strpos( $req['args']['user-agent'], 'office@example-realty.com' ) && false !== strpos( $req['args']['user-agent'], 'https://example-realty.com/' ), 'N1: identifying User-Agent (version, site, contact email)' );
	check( YEAR_IN_SECONDS === end( $GLOBALS['T'] )[1], 'N1: hit cached for a year' );

	$GLOBALS['HTTP'] = [];
	$r = \AVE_MLM_Nominatim::geocode( '100 2ND AVE N, st. petersburg, fl 33701' );
	check( 0 === count( $GLOBALS['HTTP'] ) && true === $r['cached'], 'N2: same address (case/spacing) served from cache' );

	reset_all();
	$GLOBALS['HTTP_Q'] = [ osm_miss(), osm_hit( 27.95, -82.51 ) ];
	$r = \AVE_MLM_Nominatim::geocode( '4200 W Cypress St Suite 200, Tampa, FL 33607' );
	parse_str( parse_url( $GLOBALS['HTTP'][1]['url'], PHP_URL_QUERY ), $q2 );
	check( is_array( $r ) && 2 === count( $GLOBALS['HTTP'] ) && '4200 W Cypress St, Tampa, FL 33607' === $q2['q'], 'N3: retries without "Suite 200" when the full address misses' );

	reset_all();
	$GLOBALS['HTTP_Q'] = [ osm_miss() ];
	$r = \AVE_MLM_Nominatim::geocode( 'Nowhere Lane, Atlantis' );
	check( is_wp_error( $r ) && 'ave_mlm_not_found' === $r->code && 404 === $r->data['status'] && 1 === count( $GLOBALS['HTTP'] ), 'N4: no match → 404 (no pointless retry when nothing to strip)' );
	$GLOBALS['HTTP'] = [];
	$r = \AVE_MLM_Nominatim::geocode( 'Nowhere Lane, Atlantis' );
	check( is_wp_error( $r ) && 0 === count( $GLOBALS['HTTP'] ), 'N4: miss cached briefly (no repeat request)' );

	reset_all();
	$GLOBALS['HTTP_Q'] = [ osm_code( 429 ), osm_hit( 1, 2 ) ];
	$r = \AVE_MLM_Nominatim::geocode( '1 Main St, Tampa, FL' );
	check( is_wp_error( $r ) && 'ave_mlm_rate_limited' === $r->code && 429 === $r->data['status'], 'N5: HTTP 429 → rate-limited error' );
	$r = \AVE_MLM_Nominatim::geocode( '1 Main St, Tampa, FL' );
	check( is_array( $r ) && 2 === count( $GLOBALS['HTTP'] ), 'N5: rate limit not cached — retry hits the network and succeeds' );

	reset_all();
	$GLOBALS['FILTERS']['ave_mlm_nominatim_interval'] = function () { return 0.3; };
	$GLOBALS['HTTP_Q'] = [ osm_hit( 1, 1 ), osm_hit( 2, 2 ), osm_hit( 3, 3 ) ];
	\AVE_MLM_Nominatim::geocode( 'A st' ); \AVE_MLM_Nominatim::geocode( 'B st' ); \AVE_MLM_Nominatim::geocode( 'C st' );
	$gaps = [ $GLOBALS['HTTP'][1]['t'] - $GLOBALS['HTTP'][0]['t'], $GLOBALS['HTTP'][2]['t'] - $GLOBALS['HTTP'][1]['t'] ];
	check( min( $gaps ) >= 0.29, sprintf( 'N6: requests spaced by the throttle interval (gaps %.2fs, %.2fs)', $gaps[0], $gaps[1] ) );

	$cases = [
		'100 Main St Suite 200, Tampa, FL 33607' => '100 Main St, Tampa, FL 33607',
		'100 Main St, Ste. 4B, Tampa, FL 33607'  => '100 Main St, Tampa, FL 33607',
		'250 Oak Ave #105, Clearwater, FL 33755'  => '250 Oak Ave, Clearwater, FL 33755',
		'9 Elm Rd Unit 3, Sarasota, FL 34236'     => '9 Elm Rd, Sarasota, FL 34236',
		'Tampa, FL 33607'                          => 'Tampa, FL 33607',
		'12 Fleet St, London EC4Y 1AA, UK'         => '12 Fleet St, London EC4Y 1AA, UK',
	];
	$ok = true;
	foreach ( $cases as $in => $want ) { $got = \AVE_MLM_Nominatim::simplify( $in ); if ( $got !== $want ) { $ok = false; echo "   simplify('$in') = '$got' (want '$want')\n"; } }
	check( $ok, 'N7: simplify() strips suite/unit/# but keeps state codes like FL and UK postcodes' );

	reset_all();
	$r = \AVE_MLM_Nominatim::geocode( '   ' );
	check( is_wp_error( $r ) && 400 === $r->data['status'] && 0 === count( $GLOBALS['HTTP'] ), 'N8: empty address → 400, no request' );

	/* ================= REST ================= */
	reset_all();
	\AVE_MLM_REST::register_routes();
	$route = $GLOBALS['ROUTES']['ave-mlm/v1/geocode'] ?? null;
	check( $route && 'POST' === $route['methods'] && ! empty( $route['args']['address']['required'] ), 'R1: POST ave-mlm/v1/geocode registered, address required' );
	$GLOBALS['CAN'] = false;
	check( false === call_user_func( $route['permission_callback'] ), 'R1: visitors / subscribers are refused' );
	$GLOBALS['CAN'] = true;
	check( true === call_user_func( $route['permission_callback'] ), 'R1: editors are allowed' );
	$GLOBALS['HTTP_Q'] = [ osm_hit( 40.7, -74.0, 'New York' ) ];
	$res = call_user_func( $route['callback'], new \WP_REST_Request( [ 'address' => '1 Broadway, New York, NY' ] ) );
	check( is_array( $res ) && 40.7 === $res['lat'] && 'New York' === $res['label'] && false === $res['cached'], 'R2: returns lat/lng/label' );
	$GLOBALS['HTTP_Q'] = [ osm_miss() ];
	$res = call_user_func( $route['callback'], new \WP_REST_Request( [ 'address' => 'zzz' ] ) );
	check( is_wp_error( $res ) && 404 === $res->data['status'], 'R2: not found passes through as WP_Error 404' );

	/* ================= Settings ================= */
	reset_all();
	$s = \AVE_MLM_Settings::sanitize( [ 'carto_key' => ' abc-123"<script>', 'google_key' => 'AIza_x.y', 'contact_email' => 'bad' ] );
	check( 'abc-123script' === $s['carto_key'] && 'AIza_x.y' === $s['google_key'] && '' === $s['contact_email'], 'S1: keys sanitized to safe characters, bad email dropped' );
	$GLOBALS['O']['ave_maps_settings'] = [ 'carto_key' => 'FROM_AVE' ];
	check( 'FROM_AVE' === \AVE_MLM_Settings::get( 'carto_key' ), 'S2: falls back to Agent View Extension keys' );
	$GLOBALS['O']['ave_mlm_maps'] = [ 'carto_key' => 'OWN' ];
	check( 'OWN' === \AVE_MLM_Settings::get( 'carto_key' ), 'S2: own key wins over AVE key' );
	check( 'office@example-realty.com' === \AVE_MLM_Settings::get( 'contact_email' ), 'S3: contact email defaults to admin email' );

	/* ================= Widget ================= */
	function offices( $n, $opts = [] ) {
		$rows = [];
		$names = [ 'Downtown St. Pete', 'Tampa Westshore', 'Clearwater Beach', 'Brandon', 'Sarasota', 'Wesley Chapel', 'Lakewood Ranch', 'Palm Harbor', 'Seminole Heights', 'Bradenton' ];
		for ( $i = 0; $i < $n; $i++ ) {
			$lat = 27.35 + fmod( $i * 0.137, 0.9 );
			$lng = -82.85 + fmod( $i * 0.211, 0.75 );
			$rows[] = [
				'office_name' => $names[ $i % 10 ] . ( $i >= 10 ? ' ' . ( intdiv( $i, 10 ) + 1 ) : '' ),
				'address'     => ( 100 + $i * 7 ) . ' ' . [ 'Main St', 'Bay Dr', 'Gulf Blvd', 'Central Ave' ][ $i % 4 ] . ', ' . [ 'Tampa', 'St. Petersburg', 'Clearwater', 'Sarasota' ][ $i % 4 ] . ', FL',
				'lat'         => sprintf( '%.6f', $lat ),
				'lng'         => sprintf( '%.6f', $lng ),
				'link'        => [ 'url' => ( $i % 3 === 2 ) ? '' : '#office-' . ( $i + 1 ), 'is_external' => '', 'nofollow' => '' ],
				'link_text'   => 'View Details',
				'phones'      => ( 0 === $i % 2 )
					? sprintf( "Jonathon Kyes | Office Manager | (727) 555-%04d\nFront Desk | (727) 555-%04d", 100 + $i, 200 + $i )
					: ( 1 === $i % 4 ? sprintf( '(813) 555-%04d', 300 + $i ) : '' ),
			];
		}
		return $rows;
	}
	function render_widget( array $settings, $editor = false ) {
		\Elementor\Plugin::$instance->editor->on = $editor;
		\Elementor\Plugin::$instance->preview->on = $editor;
		$w = new \AVE_Office_Map_Widget();
		$w->settings = $settings + [ 'layout' => 'popup', 'engine' => 'leaflet', 'leaflet_style' => 'light', 'google_style' => 'standard', 'number_pins' => 'yes', 'zoom_controls' => 'yes', 'scroll_zoom' => '', 'max_zoom' => 15 ];
		$GLOBALS['HTTP'] = [];
		$m = new \ReflectionMethod( $w, 'render' ); $m->setAccessible( true );
		ob_start(); $m->invoke( $w ); $html = ob_get_clean();
		preg_match( '/data-ave-om="([^"]*)"/', $html, $mm );
		return [ $html, $mm ? json_decode( html_entity_decode( $mm[1], ENT_QUOTES ), true ) : null ];
	}

	reset_all();
	$w = new \AVE_Office_Map_Widget();
	$m = new \ReflectionMethod( $w, 'register_controls' ); $m->setAccessible( true );
	try { $m->invoke( $w ); $ctl_ok = true; } catch ( \Exception $e ) { $ctl_ok = false; echo '   ' . $e->getMessage() . "\n"; }
	$need = [ 'layout', 'number_pins', 'offices', 'find_all', 'engine', 'leaflet_style', 'google_style', 'max_zoom', 'zoom_controls', 'scroll_zoom', 'map_height', 'pin_color', 'pin_active_color', 'popup_bg', 'btn_bg', 'btn_bg_hover', 'list_width', 'list_active_bg' ];
	check( $ctl_ok && ! array_diff( $need, array_keys( $w->controls ) ), 'W1: controls register cleanly (sections balanced, no duplicates)' );
	$fields = $w->controls['offices']['fields'];
	check( isset( $fields['office_name'], $fields['address'], $fields['find_pin'], $fields['lat'], $fields['lng'], $fields['link'], $fields['link_text'] ) && 'aveOfficeMap:findPin' === $fields['find_pin']['event'] && 'aveOfficeMap:findAll' === $w->controls['find_all']['event'], 'W1: office row has name/address/Find on map/lat/lng/link/button text; button events wired' );

	$GLOBALS['O']['ave_mlm_maps'] = [ 'carto_key' => 'CARTO123', 'google_key' => 'GKEY' ];
	[ $html, $cfg ] = render_widget( [ 'layout' => 'popup', 'offices' => offices( 45 ) ] );
	check( 45 === count( $cfg['offices'] ) && 0 === count( $GLOBALS['HTTP'] ), 'W2: popup layout, 45 offices in config, zero network calls at render' );
	check( false !== strpos( $cfg['tiles']['url'], 'basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png?key=CARTO123' ) && 'abcd' === $cfg['tiles']['subdomains'] && false !== strpos( $cfg['tiles']['attribution'], 'OpenStreetMap' ), 'W2: CARTO tile URL with key, subdomains and attribution' );
	check( false === strpos( $html, 'ave-om__item' ) && false === strpos( $html, 'ave-om-notice' ) && false === $cfg['disabled'], 'W2: no list markup, no notice on the live page' );
	check( false === strpos( $html, 'GKEY' ), 'W2: Google key not output when engine is Leaflet' );
	file_put_contents( "$out/popup-45.frag.html", $html );

	[ $html, $cfg ] = render_widget( [ 'layout' => 'list', 'offices' => offices( 45 ) ] );
	preg_match_all( '/<li class="ave-om__item" data-index="(\d+)"/', $html, $li );
	preg_match_all( '/<span class="ave-om__num" aria-hidden="true">(\d+)<\/span>/', $html, $nums );
	check( 45 === count( $li[1] ) && '1' === $nums[1][0] && '45' === end( $nums[1] ) && true === $cfg['numbers'], 'W3: list layout renders 45 numbered offices as HTML (crawlable)' );
	check( 30 === substr_count( $html, 'class="ave-om__btn"' ), 'W3: buttons only on offices with a link (30 of 45)' );
	file_put_contents( "$out/list-45.frag.html", $html );

	$rows = offices( 3 );
	$rows[0]['office_name'] = '<img src=x onerror=alert(1)>Evil "Office"';
	$rows[1]['link']['url'] = 'javascript:alert(1)';
	$rows[2]['link'] = [ 'url' => 'https://example.com/o', 'is_external' => 'on', 'nofollow' => 'on' ];
	[ $html, $cfg ] = render_widget( [ 'layout' => 'list', 'offices' => $rows ] );
	check( false === strpos( $html, '<img' ) && 'Evil "Office"' === $cfg['offices'][0]['name'], 'W4: HTML stripped from names; quotes survive in JSON and are escaped in markup' );
	check( '' === $cfg['offices'][1]['url'] && false === strpos( $html, 'javascript:' ), 'W4: javascript: links dropped' );
	check( false !== strpos( $html, 'target="_blank" rel="noopener nofollow"' ) && $cfg['offices'][2]['external'] && $cfg['offices'][2]['nofollow'], 'W4: new-tab + nofollow respected' );

	$rows = offices( 6 );
	$rows[1]['lat'] = ''; $rows[3]['lng'] = 'abc'; $rows[4]['lat'] = '91';
	$rows[5]['lat'] = '27,5'; $rows[5]['lng'] = '-82,4';
	[ $html, $cfg ] = render_widget( [ 'layout' => 'list', 'offices' => $rows ], true );
	check( 3 === count( $cfg['offices'] ) && 27.5 === $cfg['offices'][2]['lat'], 'W5: rows without valid lat/lng are left off; "27,5" accepted' );
	check( false !== strpos( $html, '3 offices have no pin yet' ) && false !== strpos( $html, 'Tampa Westshore' ) && false !== strpos( $html, 'Find all missing pins' ), 'W5: editor notice names the offices missing pins' );
	[ $html ] = render_widget( [ 'layout' => 'list', 'offices' => $rows ], false );
	check( false === strpos( $html, 'ave-om-notice' ), 'W5: notice never shown to visitors' );

	$GLOBALS['O']['ave_mlm_maps'] = [];
	[ $html, $cfg ] = render_widget( [ 'layout' => 'popup', 'offices' => offices( 4 ) ], true );
	check( true === $cfg['disabled'] && false !== strpos( $html, 'No CARTO key yet' ), 'W6: Leaflet without CARTO key → map disabled, editor told why' );
	[ $html, $cfg ] = render_widget( [ 'layout' => 'popup', 'engine' => 'google', 'offices' => offices( 4 ) ], true );
	check( false === $cfg['disabled'] && '' === $cfg['googleKey'] && false !== strpos( $html, 'No Google Maps key' ), 'W6: Google without key → editor warning (may still work if Google is already on the page)' );
	$GLOBALS['O']['ave_mlm_maps'] = [ 'google_key' => 'GKEY' ];
	[ $html, $cfg ] = render_widget( [ 'layout' => 'list', 'engine' => 'google', 'google_style' => 'silver', 'offices' => offices( 45 ) ] );
	check( 'GKEY' === $cfg['googleKey'] && 'silver' === $cfg['style'] && ! isset( $cfg['tiles'] ), 'W7: Google engine config (key, style)' );
	file_put_contents( "$out/google-list-45.frag.html", $html );

	$GLOBALS['O']['ave_mlm_maps'] = [ 'carto_key' => 'CARTO123' ];
	[ $html, $cfg ] = render_widget( [ 'layout' => 'list', 'offices' => offices( 120 ), 'leaflet_style' => 'voyager' ] );
	check( 120 === count( $cfg['offices'] ) && false !== strpos( $cfg['tiles']['url'], 'rastertiles/voyager' ), 'W8: 120 offices, Voyager tiles' );
	file_put_contents( "$out/list-120.frag.html", $html );
	[ $html ] = render_widget( [ 'layout' => 'popup', 'offices' => offices( 1 ) ] );
	file_put_contents( "$out/popup-1.frag.html", $html );
	$GLOBALS['O']['ave_mlm_maps'] = [];
	[ $html ] = render_widget( [ 'layout' => 'list', 'offices' => offices( 5 ) ] );
	file_put_contents( "$out/nokey-list-5.frag.html", $html );

	check( null === \AVE_Office_Map_Widget::coord( '', 90 ) && null === \AVE_Office_Map_Widget::coord( '-181', 180 ) && -82.5 === \AVE_Office_Map_Widget::coord( ' -82.5 ', 180 ), 'W9: coord() parsing and range checks' );

	/* ================= Phone numbers (2.1.0) ================= */
	$p = \AVE_Office_Map_Widget::parse_phones( "Main Office | (727) 555-0100\r\n(727) 555-0101 | Office Manager\nOffice Manager: (813) 555-0199\n\n+1 (813) 555-0142\nFax | 727.555.0102 ext. 12\nDirect | call us\n<b>Sales</b> | 555-0100 x7\nJonathon Kyes | Office Manager | (727) 555-0110\n(727) 555-0111 | Maria Lopez | Broker\nAna Ruiz |  | 727-555-0112\n | Office Manager | 727-555-0113" );
	$want = [
		[ '', 'Main Office', '(727) 555-0100', '7275550100' ],
		[ '', 'Office Manager', '(727) 555-0101', '7275550101' ],
		[ '', 'Office Manager', '(813) 555-0199', '8135550199' ],
		[ '', '', '+1 (813) 555-0142', '+18135550142' ],
		[ '', 'Fax', '727.555.0102 ext. 12', '7275550102,12' ],
		[ '', 'Sales', '555-0100 x7', '5550100,7' ],
		[ 'Jonathon Kyes', 'Office Manager', '(727) 555-0110', '7275550110' ],
		[ 'Maria Lopez', 'Broker', '(727) 555-0111', '7275550111' ],
		[ 'Ana Ruiz', '', '727-555-0112', '7275550112' ],
		[ '', 'Office Manager', '727-555-0113', '7275550113' ],
	];
	$got = array_map( function ( $x ) { return [ $x['name'], $x['label'], $x['number'], $x['tel'] ]; }, $p );
	if ( $got !== $want ) { echo '   got: ' . json_encode( $got ) . "\n"; }
	check( $got === $want, 'P1: parses "Name | Role | Number" (any order for the number), 2.1.0 "Role | Number", "Role: Number", plain numbers, extensions; skips lines without a number; strips HTML' );
	check( 10 === count( \AVE_Office_Map_Widget::parse_phones( str_repeat( "Line | 555-0100\n", 14 ) ) ), 'P1: at most 10 numbers per office' );

	reset_all();
	$GLOBALS['O']['ave_mlm_maps'] = [ 'carto_key' => 'CARTO123' ];
	[ $html, $cfg ] = render_widget( [ 'layout' => 'list', 'offices' => offices( 4 ) ] );
	check( false !== strpos( $html, 'class="ave-om ave-om--list ave-om--numbered ave-om--label-below ave-om--label-text ave-om--phone-icon"' ), 'P2: defaults → role label below the number, small caps text, icon on' );
	check( 2 === count( $cfg['offices'][0]['phones'] ) && 'Jonathon Kyes' === $cfg['offices'][0]['phones'][0]['name'] && 'Office Manager' === $cfg['offices'][0]['phones'][0]['label'] && 'Front Desk' === $cfg['offices'][0]['phones'][1]['label'] && '' === $cfg['offices'][0]['phones'][1]['name'] && 1 === count( $cfg['offices'][1]['phones'] ) && [] === $cfg['offices'][3]['phones'], 'P2: name + role + number in the popup config per office' );
	check( preg_match( '#<div class="ave-om-phones ave-om-phones--people"><div class="ave-om-phone"><span class="ave-om-phone__name">Jonathon Kyes</span><svg class="ave-om-phone__icon"[^>]*>.*?</svg><span class="ave-om-phone__body"><a class="ave-om-phone__number" href="tel:7275550100">\(727\) 555-0100</a><span class="ave-om-phone__label">Office Manager</span></span></div>#', $html ) && false !== strpos( $html, '<div class="ave-om-phones">' ), 'P2: list markup → name, icon, number, role; "--people" spacing only where a name exists' );
	check( 5 === substr_count( $html, 'class="ave-om-phone"' ) && false !== strpos( $html, 'href="tel:7275550100"' ) && false !== strpos( $html, '<span class="ave-om-phone__label">Office Manager</span>' ), 'P2: list renders tap-to-call numbers with labels (5 numbers across 4 offices)' );
	check( preg_match( '#</button>\s*<div class="ave-om-phones">#', $html ) && preg_match( '#</div>\s*<a class="ave-om__btn"#', $html ), 'P2: phones sit between the address and the button, outside the select button' );

	[ $html ] = render_widget( [ 'layout' => 'popup', 'offices' => offices( 2 ), 'phone_label_position' => 'below', 'phone_label_style' => 'text', 'phone_icon' => '' ] );
	check( false !== strpos( $html, 'ave-om--label-below ave-om--label-text"' ) && false === strpos( $html, 'ave-om--phone-icon' ), 'P3: position/style/icon settings become classes' );
	[ $html ] = render_widget( [ 'layout' => 'popup', 'offices' => offices( 2 ), 'phone_label_position' => 'sideways"><script>' ] );
	check( false !== strpos( $html, 'ave-om--label-below' ) && false === strpos( $html, 'sideways' ), 'P3: unknown position value falls back to below' );

	$rows = offices( 1 );
	$rows[0]['phones'] = "<i>Jo</i> \"JJ\" O'Neil | <script>alert(1)</script>Boss \"Lady\" | 555-0100";
	[ $html, $cfg ] = render_widget( [ 'layout' => 'list', 'offices' => $rows ] );
	check( false === strpos( $html, '<script>alert' ) && false === strpos( $html, '<i>' ) && 'alert(1)Boss "Lady"' === $cfg['offices'][0]['phones'][0]['label'] && 'Jo "JJ" O\'Neil' === $cfg['offices'][0]['phones'][0]['name'] && false !== strpos( $html, 'Boss &quot;Lady&quot;' ) && false !== strpos( $html, 'Jo &quot;JJ&quot; O&#039;Neil' ), 'P4: names and role labels are stripped and escaped' );

	$w = new \AVE_Office_Map_Widget();
	$m = new \ReflectionMethod( $w, 'register_controls' ); $m->setAccessible( true ); $m->invoke( $w );
	check( isset( $w->controls['offices']['fields']['phones'], $w->controls['phone_label_position'], $w->controls['phone_label_style'], $w->controls['phone_icon'], $w->controls['phone_number_color'], $w->controls['phone_pill_bg'], $w->controls['phone_label_typography'], $w->controls['phone_name_color'], $w->controls['phone_name_typography'] ) && ! isset( $w->controls['phone_gap']['default'] ) && 'below' === $w->controls['phone_label_position']['default'] && 'text' === $w->controls['phone_label_style']['default'] && 'textarea' === $w->controls['offices']['fields']['phones']['type'], 'P5: Phone numbers field + label position/style/icon + style controls registered' );

	// Fragments for the browser test: every position × style.
	foreach ( [ 'right-top', 'right-bottom', 'above', 'below' ] as $pos ) {
		foreach ( [ 'pill', 'text' ] as $sty ) {
			[ $html ] = render_widget( [ 'layout' => 'list', 'offices' => offices( 12 ), 'phone_label_position' => $pos, 'phone_label_style' => $sty ] );
			file_put_contents( "$out/phones-$pos-$sty.frag.html", $html );
		}
	}
	[ $html ] = render_widget( [ 'layout' => 'popup', 'offices' => offices( 45 ) ] );
	file_put_contents( "$out/popup-45.frag.html", $html );
	[ $html ] = render_widget( [ 'layout' => 'list', 'offices' => offices( 45 ) ] );
	file_put_contents( "$out/list-45.frag.html", $html );

	/* ================= Number toggles (2.3.0) ================= */
	$GLOBALS['O']['ave_mlm_maps'] = [ 'carto_key' => 'CARTO123' ];
	$combos = [ [ 'yes', 'yes' ], [ '', 'yes' ], [ 'yes', '' ], [ '', '' ] ];
	$ok = true;
	foreach ( $combos as $c ) {
		[ $html, $cfg ] = render_widget( [ 'layout' => 'list', 'offices' => offices( 6 ), 'number_pins' => $c[0], 'list_numbers' => $c[1] ] );
		$badges = substr_count( $html, 'class="ave-om__num"' );
		$want_b = 'yes' === $c[1] ? 6 : 0;
		$want_p = 'yes' === $c[0];
		if ( $badges !== $want_b || $cfg['numbers'] !== $want_p ) { $ok = false; echo "   pins={$c[0]} list={$c[1]} → badges $badges, numbers " . var_export( $cfg['numbers'], true ) . "\n"; }
		file_put_contents( "$out/nums-" . ( $c[0] ? 'pins' : 'nopins' ) . '-' . ( $c[1] ? 'list' : 'nolist' ) . '.frag.html', $html );
	}
	check( $ok, 'T1: "Numbers on pins" and "Numbers in office list" toggle independently (all 4 combinations)' );
	[ $html, $cfg ] = render_widget( [ 'layout' => 'popup', 'offices' => offices( 3 ), 'number_pins' => 'yes', 'list_numbers' => 'yes' ] );
	check( false === $cfg['numbers'] && false === strpos( $html, 'ave-om__num' ), 'T2: Layout 1 never numbers pins' );
	$w = new \AVE_Office_Map_Widget();
	$m = new \ReflectionMethod( $w, 'register_controls' ); $m->setAccessible( true ); $m->invoke( $w );
	check( 'yes' === $w->controls['number_pins']['default'] && 'yes' === $w->controls['list_numbers']['default'] && [ 'layout' => 'list' ] === $w->controls['list_numbers']['condition'] && 'Show' === $w->controls['list_numbers']['label_on'], 'T3: both switches default to Show and only appear for Layout 2' );

	/* ================= Per-office pin colour / icon (2.4.0) ================= */
	$w = new \AVE_Office_Map_Widget();
	$m = new \ReflectionMethod( $w, 'register_controls' ); $m->setAccessible( true ); $m->invoke( $w );
	$f = $w->controls['offices']['fields'];
	$order = array_keys( $f );
	$at = array_search( 'link', $order, true );
	check( [ 'pin_heading', 'pin_color', 'pin_icon' ] === array_slice( $order, $at - 3, 3 ), 'I1: "Pin for this office" (colour, icon) sits just before Button link: ' . implode( ' > ', $order ) );
	check( 'color' === $f['pin_color']['type'] && '--ave-om-pin: {{VALUE}}; --ave-om-pin-active: {{VALUE}};' === ( $f['pin_color']['selectors']['{{WRAPPER}} {{CURRENT_ITEM}}'] ?? '' ) && empty( $f['pin_color']['default'] ), 'I1: pin colour has no default and is written per office via {{CURRENT_ITEM}}' );
	check( 'media' === $f['pin_icon']['type'] && in_array( 'svg', $f['pin_icon']['media_types'], true ) && isset( $w->controls['icon_size'] ) && 40 === $w->controls['icon_size']['default']['size'], 'I1: pin icon is a media upload (image/SVG); Custom icon height defaults to 40px' );

	reset_all();
	$GLOBALS['O']['ave_mlm_maps'] = [ 'carto_key' => 'CARTO123' ];
	$rows = offices( 8 );
	foreach ( $rows as $i => &$r ) { $r['_id'] = 'r' . $i; } unset( $r );
	$rows[1]['pin_color'] = '#E11D48';
	$rows[2]['pin_icon'] = [ 'url' => 'http://127.0.0.1:8765/media/logo.svg', 'id' => 55 ];
	$rows[3]['pin_icon'] = [ 'url' => 'http://127.0.0.1:8765/media/logo.svg', 'id' => 55 ];
	$rows[3]['pin_color'] = '#059669';
	$rows[4]['pin_icon'] = [ 'url' => 'http://127.0.0.1:8765/media/missing.png', 'id' => 56 ];
	$rows[5]['__globals__'] = [ 'pin_color' => 'globals/colors?id=accent' ];
	$rows[6]['pin_icon'] = [ 'url' => 'javascript:alert(1)', 'id' => '' ];
	$rows[7]['_id'] = 'r7"><script>';
	[ $html, $cfg ] = render_widget( [ 'layout' => 'list', 'offices' => $rows ] );
	$o = $cfg['offices'];
	check( 'elementor-repeater-item-r0' === $o[0]['item'] && false === $o[0]['ownColor'] && '' === $o[0]['icon'], 'I2: plain office → item class only, no own colour, no icon' );
	check( true === $o[1]['ownColor'] && true === $o[3]['ownColor'] && true === $o[5]['ownColor'] && false === $o[2]['ownColor'], 'I2: own colour detected for a picked colour and for a global kit colour' );
	check( 'http://127.0.0.1:8765/media/logo.svg' === $o[2]['icon'] && '' === $o[6]['icon'], 'I2: icon URL passed through; javascript: URL dropped' );
	check( 'elementor-repeater-item-r7script' === $o[7]['item'] && false === strpos( $html, '<script>' ), 'I3: odd _id values are sanitized into a safe class name' );
	check( false !== strpos( $html, '<li class="ave-om__item elementor-repeater-item-r1" data-index="1">' ), 'I4: list item carries the office class, so its number badge takes the office colour' );
	file_put_contents( "$out/pins-list.frag.html", $html );
	[ $html ] = render_widget( [ 'layout' => 'popup', 'offices' => $rows ] );
	file_put_contents( "$out/pins-popup.frag.html", $html );
	[ $html ] = render_widget( [ 'layout' => 'list', 'engine' => 'google', 'offices' => $rows ] );
	file_put_contents( "$out/pins-google.frag.html", $html );

	echo $fails ? "\n$fails FAILED, $passes passed\n" : "\nALL $passes PASSED\n";
	exit( $fails ? 1 : 0 );
}
