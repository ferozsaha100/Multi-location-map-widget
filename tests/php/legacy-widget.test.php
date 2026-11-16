<?php
/**
 * Render tests for the widget with mocked WordPress + Elementor + Google Geocoding API.
 * Tests for the original Multi-Location Map widget (server-side geocoding).
 * Usage: php tests/php/legacy-widget.test.php <plugin-dir> <out-dir>
 */

namespace {
$plugin = rtrim( $argv[1], '/' );
$out    = rtrim( $argv[2], '/' );

define( 'ABSPATH', '/tmp/wp/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'DAY_IN_SECONDS', 86400 );

/* ---------------- WordPress mocks ---------------- */
$GLOBALS['T']        = [];    // transients: key => [value, ttl]
$GLOBALS['HTTP_LOG'] = [];
$GLOBALS['HTTP_MODE']    = 'ok';  // ok | denied | daily
$GLOBALS['ZERO']         = [];    // addresses that return ZERO_RESULTS
$GLOBALS['LATENCY_US']   = 0;
$GLOBALS['FILTERS']      = [];
$GLOBALS['CAN_EDIT']     = false;
$GLOBALS['ENQUEUED']     = [];

function get_transient( $k ) { return isset( $GLOBALS['T'][ $k ] ) ? $GLOBALS['T'][ $k ][0] : false; }
function set_transient( $k, $v, $ttl ) { $GLOBALS['T'][ $k ] = [ $v, $ttl ]; return true; }
function apply_filters( $tag, $value ) { return isset( $GLOBALS['FILTERS'][ $tag ] ) ? $GLOBALS['FILTERS'][ $tag ]( $value ) : $value; }
function wp_rand( $a, $b ) { return random_int( $a, $b ); }
function is_wp_error( $x ) { return $x instanceof WP_Error; }
class WP_Error { private $m; function __construct( $c, $m ) { $this->m = $m; } function get_error_message() { return $this->m; } }
function wp_remote_retrieve_body( $r ) { return $r['body']; }
function wp_remote_retrieve_response_code( $r ) { return $r['response']['code']; }
function wp_remote_get( $url, $args ) {
	if ( $GLOBALS['LATENCY_US'] ) usleep( $GLOBALS['LATENCY_US'] );
	$GLOBALS['HTTP_LOG'][] = $url;
	if ( 0 !== strpos( $url, 'https://maps.googleapis.com/maps/api/geocode/json?' ) ) throw new Exception( 'bad endpoint ' . $url );
	parse_str( parse_url( $url, PHP_URL_QUERY ), $q );
	if ( empty( $q['key'] ) || empty( $q['address'] ) ) throw new Exception( 'missing params ' . $url );
	if ( 'denied' === $GLOBALS['HTTP_MODE'] ) {
		return [ 'response' => [ 'code' => 200 ], 'body' => json_encode( [ 'status' => 'REQUEST_DENIED', 'error_message' => 'API keys with referer restrictions cannot be used with this API.', 'results' => [] ] ) ];
	}
	if ( in_array( $q['address'], $GLOBALS['ZERO'], true ) ) {
		return [ 'response' => [ 'code' => 200 ], 'body' => json_encode( [ 'status' => 'ZERO_RESULTS', 'results' => [] ] ) ];
	}
	$h = crc32( $q['address'] );
	return [ 'response' => [ 'code' => 200 ], 'body' => json_encode( [ 'status' => 'OK', 'results' => [ [ 'geometry' => [ 'location' => [ 'lat' => 27 + ( $h % 1000 ) / 1e4, 'lng' => -82 - ( ( $h >> 10 ) % 1000 ) / 1e4 ] ] ] ] ] ) ];
}
function current_user_can( $c ) { return $GLOBALS['CAN_EDIT']; }
function __( $s ) { return $s; }
function esc_html__( $s ) { return htmlspecialchars( $s, ENT_QUOTES ); }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_url_raw( $s ) { return $s; }
function wp_strip_all_tags( $s ) { return trim( strip_tags( $s ) ); }
function wp_json_encode( $d ) { return json_encode( $d ); }
function wp_script_is( $h ) { return in_array( $h, $GLOBALS['ENQUEUED'], true ); }
function wp_enqueue_script( $h, $src ) { $GLOBALS['ENQUEUED'][] = $h; $GLOBALS['ENQUEUED_SRC'][ $h ] = $src; }

}
/* ---------------- Elementor mocks ---------------- */
namespace Elementor {
class Widget_Base { public $settings = []; function get_settings_for_display() { return $this->settings; } function get_id() { return 'abc123'; } }
class ModeFlag { public $on = false; function is_edit_mode() { return $this->on; } function is_preview_mode() { return $this->on; } }
class Plugin { public static $instance; public $editor; public $preview; }
Plugin::$instance          = new Plugin();
Plugin::$instance->editor  = new ModeFlag();
Plugin::$instance->preview = new ModeFlag();

}
namespace Tests {
$plugin = $GLOBALS['plugin']; $out = $GLOBALS['out'];

require $plugin . '/includes/class-ave-mlm-geocoder.php';
require $plugin . '/widgets/class-ave-multi-location-map-widget.php';

$fails = 0;
function check( $cond, $label ) { global $fails; echo ( $cond ? 'PASS ' : 'FAIL ' ) . $label . "\n"; if ( ! $cond ) $fails++; }
function locations( $n, $prefix = 'Office' ) {
	$l = [];
	for ( $i = 1; $i <= $n; $i++ ) $l[] = [ 'office_name' => "$prefix $i", 'address' => ( 100 + $i ) . " Main St, City $i, FL", 'lat' => '', 'lng' => '', 'phone' => '', 'website_url' => [ 'url' => '' ], 'link_text' => 'View Details', 'custom_marker' => [ 'url' => '' ] ];
	return $l;
}
function render( array $settings, $editor = false ) {
	\Elementor\Plugin::$instance->editor->on  = $editor;
	\Elementor\Plugin::$instance->preview->on = $editor;
	$w           = new \AVE_Multi_Location_Map_Widget();
	$w->settings = $settings + [ 'api_key' => '', 'geocode_api_key' => '', 'auto_fit_bounds' => 'yes', 'zoom' => 12, 'open_first_infowindow' => '', 'map_style_json' => '' ];
	$GLOBALS['HTTP_LOG'] = [];
	$m = new \ReflectionMethod( $w, 'render' );
	$m->setAccessible( true );
	ob_start();
	$m->invoke( $w );
	$html = ob_get_clean();
	preg_match( "/data-config='([^']*)'/", $html, $mm );
	$cfg = $mm ? json_decode( html_entity_decode( $mm[1], ENT_QUOTES ), true ) : null;
	return [ $html, $cfg, count( $GLOBALS['HTTP_LOG'] ) ];
}
function with_coords( $cfg ) { return count( array_filter( $cfg['locations'], function ( $l ) { return is_float( $l['lat'] ) || is_int( $l['lat'] ); } ) ); }
function reset_state() { $GLOBALS['T'] = []; $GLOBALS['HTTP_MODE'] = 'ok'; $GLOBALS['ZERO'] = []; $GLOBALS['FILTERS'] = []; $GLOBALS['CAN_EDIT'] = false; $GLOBALS['LATENCY_US'] = 0; }

/* A. 45 address-only locations, working key */
reset_state();
[ $html, $cfg, $req ] = render( [ 'api_key' => 'BROWSER_KEY', 'locations' => locations( 45 ) ] );
check( 45 === count( $cfg['locations'] ), 'A: 45 locations in payload' );
check( 45 === with_coords( $cfg ), 'A: all 45 have server lat/lng' );
check( 45 === $req, 'A: 45 geocoding requests on first render' );
check( false === strpos( $html, 'ave-mlm-notice' ), 'A: no notice for visitors' );
check( ! isset( $cfg['serverGeo'] ), 'A: no serverGeo diagnostics for visitors' );
check( false === strpos( $html, 'BROWSER_KEY"' ) && false === strpos( $html, 'SERVER_KEY' ), 'A: key not in markup' );
file_put_contents( "$out/cfg-server-45.json", json_encode( $cfg ) );
$ttls = array_map( function ( $t ) { return $t[1]; }, $GLOBALS['T'] );
check( max( $ttls ) <= 30 * DAY_IN_SECONDS && min( $ttls ) >= 27 * DAY_IN_SECONDS, 'A: cache TTL within 27-30 days' );

/* B. second render served from cache */
[ $html, $cfg, $req ] = render( [ 'api_key' => 'BROWSER_KEY', 'locations' => locations( 45 ) ] );
check( 0 === $req && 45 === with_coords( $cfg ), 'B: second render = 0 requests, 45 pins' );
// Same address with different case/spacing hits the same cache entry.
$l = locations( 1 ); $l[0]['address'] = '  101 MAIN st,   City 1, FL ';
[ , $cfg, $req ] = render( [ 'api_key' => 'BROWSER_KEY', 'locations' => $l ] );
check( 0 === $req && 1 === with_coords( $cfg ), 'B: normalized address reuses cache' );

/* C. 120 locations */
reset_state();
[ , $cfg, $req ] = render( [ 'api_key' => 'K', 'locations' => locations( 120, 'Branch' ) ] );
check( 120 === with_coords( $cfg ), 'C: 120 of 120 resolved' );

/* D. referrer-restricted key → REQUEST_DENIED */
reset_state();
$GLOBALS['HTTP_MODE'] = 'denied';
$GLOBALS['CAN_EDIT']  = true;
[ $html, $cfg, $req ] = render( [ 'api_key' => 'REFERRER_KEY', 'locations' => locations( 45 ) ], true );
file_put_contents( "$out/notice-denied.html", $html );
check( 1 === $req, 'D: stops after first REQUEST_DENIED (1 request)' );
check( 0 === with_coords( $cfg ) && 45 === count( $cfg['locations'] ), 'D: all 45 left for browser fallback' );
check( false !== strpos( $html, 'referer restrictions' ) && false !== strpos( $html, 'Server Geocoding Key' ), 'D: editor notice explains referrer restriction' );
check( isset( $cfg['serverGeo'] ) && 45 === $cfg['serverGeo']['pending'], 'D: serverGeo pending=45 for editors' );
file_put_contents( "$out/cfg-denied-45.json", json_encode( $cfg ) );
[ $html, , $req ] = render( [ 'api_key' => 'REFERRER_KEY', 'locations' => locations( 45 ) ], false );
check( 0 === $req, 'D: denied key backed off on visitor render (0 requests)' );
// Adding a separate server key fixes it immediately.
$GLOBALS['HTTP_MODE'] = 'ok';
[ $html, $cfg, $req ] = render( [ 'api_key' => 'REFERRER_KEY', 'geocode_api_key' => 'SERVER_KEY', 'locations' => locations( 45 ) ], true );
check( 45 === with_coords( $cfg ), 'D: with Server Geocoding Key all 45 resolve' );
check( false !== strpos( $GLOBALS['HTTP_LOG'][0], 'key=SERVER_KEY' ), 'D: server key used for requests' );
check( false === strpos( $html, 'SERVER_KEY' ), 'D: server key never printed' );
check( false !== strpos( $html, '45 of 45 address(es) resolved' ), 'D: editor shows 45 of 45 resolved' );

/* E. no key at all */
reset_state();
$GLOBALS['CAN_EDIT'] = true;
[ $html, $cfg, $req ] = render( [ 'locations' => locations( 45 ) ], true );
check( 0 === $req && 0 === with_coords( $cfg ), 'E: no key → no requests, browser fallback' );
check( false !== strpos( $html, 'no API key is set' ), 'E: editor notice about missing key' );

/* F. one bad address */
reset_state();
$GLOBALS['CAN_EDIT'] = true;
$locs = locations( 45 );
$GLOBALS['ZERO'] = [ $locs[9]['address'] ];
[ $html, $cfg, $req ] = render( [ 'api_key' => 'K', 'locations' => $locs ], true );
file_put_contents( "$out/notice-zero.html", $html );
check( 44 === with_coords( $cfg ), 'F: 44 resolved, 1 ZERO_RESULTS' );
check( false !== strpos( $html, 'Could not geocode &quot;Office 10&quot; (ZERO_RESULTS)' ), 'F: notice names the failing office' );
[ , , $req ] = render( [ 'api_key' => 'K', 'locations' => $locs ] );
check( 0 === $req, 'F: bad address negatively cached (0 requests on next render)' );

/* G. manual lat/lng override untouched */
reset_state();
$locs = locations( 3 );
$locs[1]['lat'] = '40.5'; $locs[1]['lng'] = '-74.25';
[ , $cfg, $req ] = render( [ 'api_key' => 'K', 'locations' => $locs ] );
check( 2 === $req && 40.5 === $cfg['locations'][1]['lat'] && -74.25 === $cfg['locations'][1]['lng'], 'G: manual lat/lng kept, not geocoded' );

/* H. time budget: partial now, rest next render */
reset_state();
$GLOBALS['LATENCY_US'] = 20000; // 20ms per request
$GLOBALS['FILTERS']['ave_mlm_geocode_time_budget'] = function () { return 0.2; };
[ , $cfg, $req1 ] = render( [ 'api_key' => 'K', 'locations' => locations( 45 ) ] );
$first = with_coords( $cfg );
check( $first > 0 && $first < 45, "H: budget stops early ($first resolved first render)" );
$total = $first;
for ( $i = 0; $i < 10 && $total < 45; $i++ ) { [ , $cfg ] = render( [ 'api_key' => 'K', 'locations' => locations( 45 ) ] ); $total = with_coords( $cfg ); }
check( 45 === $total, "H: all 45 resolved after " . ( $i + 1 ) . " renders" );

/* I. wp-config constant wins */
reset_state();
define( 'AVE_MLM_GEOCODE_API_KEY', 'CONST_KEY' );
render( [ 'api_key' => 'K', 'geocode_api_key' => 'FIELD', 'locations' => locations( 1 ) ] );
check( false !== strpos( $GLOBALS['HTTP_LOG'][0], 'key=CONST_KEY' ), 'I: AVE_MLM_GEOCODE_API_KEY constant takes priority' );

echo $fails ? "\n$fails FAILED\n" : "\nALL PASSED\n";
exit( $fails ? 1 : 0 );
}
