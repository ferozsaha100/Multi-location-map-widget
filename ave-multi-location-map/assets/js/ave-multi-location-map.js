/**
 * AVE Multi-Location Map Widget — frontend init.
 *
 * Locations with lat/lng render immediately. Since 1.3.0 the server geocodes
 * addresses (cached) and sends lat/lng, so normally every pin is in that group.
 * google.maps.Geocoder is only the fallback for addresses the server could not
 * resolve — Google rate limits it per visit (~20 lookups), which was the
 * original "max 20 pins" ceiling.
 *
 * 1.3.0: fixed the geocode callback argument order. Google calls back with
 * ( results, status ); 1.2.2 read them as ( status, results ), so every
 * browser lookup was treated as a failure and no address-only pin appeared.
 *
 * 1.2.1 fixes for maps with many (20+) pins:
 *  - Geocode requests that hit OVER_QUERY_LIMIT / UNKNOWN_ERROR are retried
 *    with back-off instead of being silently dropped.
 *  - Resolved coordinates are cached in localStorage, so repeat visits place
 *    every pin instantly and send (almost) no geocoding requests.
 *  - The viewport is re-fitted progressively as pins arrive, instead of only
 *    after every single lookup has finished.
 *  - One bad location can no longer stall the queue or block the final fit.
 *  - Pins that resolve to the exact same coordinates (e.g. several offices in
 *    one building) are nudged apart slightly so none is hidden underneath
 *    another.
 */
( function () {
	'use strict';

	var BASE_DELAY_MS    = 250;   // Starting gap between lookups (adapts up on rate limiting).
	var MAX_DELAY_MS     = 3000;  // Ceiling for the adaptive gap.
	var MAX_PASSES       = 6;     // Extra passes over addresses that hit transient errors.
	var REQUEST_TIMEOUT  = 10000; // Give up waiting on a single lookup after 10s.
	var CACHE_PREFIX     = 'ave-mlm-geo:';
	var CACHE_TTL_MS     = 30 * 24 * 60 * 60 * 1000; // 30 days.
	var RETRYABLE        = { OVER_QUERY_LIMIT: 1, UNKNOWN_ERROR: 1 };

	/* ---------- Geocode cache (localStorage, fully optional) ---------- */

	function cacheKey( address ) {
		return CACHE_PREFIX + String( address ).trim().toLowerCase();
	}

	function cacheGet( address ) {
		try {
			var raw = window.localStorage.getItem( cacheKey( address ) );
			if ( ! raw ) {
				return null;
			}
			var data = JSON.parse( raw );
			if ( ! data || 'number' !== typeof data.lat || 'number' !== typeof data.lng ) {
				return null;
			}
			if ( Date.now() - data.t > CACHE_TTL_MS ) {
				window.localStorage.removeItem( cacheKey( address ) );
				return null;
			}
			return { lat: data.lat, lng: data.lng };
		} catch ( e ) {
			return null;
		}
	}

	function cacheSet( address, lat, lng ) {
		try {
			window.localStorage.setItem( cacheKey( address ), JSON.stringify( { lat: lat, lng: lng, t: Date.now() } ) );
		} catch ( e ) {
			/* Storage full / disabled — caching is a bonus, ignore. */
		}
	}

	/* ---------- Map ---------- */

	function initMap( canvas ) {
		if ( canvas.getAttribute( 'data-initialized' ) ) {
			return;
		}

		var config;
		try {
			config = JSON.parse( canvas.getAttribute( 'data-config' ) );
		} catch ( e ) {
			console.error( 'AVE Multi-Location Map: invalid config JSON', e );
			return;
		}

		if ( typeof google === 'undefined' || ! google.maps ) {
			console.error( 'AVE Multi-Location Map: Google Maps JS API not loaded.' );
			return;
		}

		if ( ! config.locations || ! config.locations.length ) {
			return;
		}

		canvas.setAttribute( 'data-initialized', '1' );

		var map = new google.maps.Map( canvas, {
			zoom: config.zoom || 12,
			center: { lat: 27.9506, lng: -82.4572 }, // temporary center until first pin resolves
			styles: config.styles && config.styles.length ? config.styles : undefined,
			mapTypeControl: false,
			streetViewControl: false,
			fullscreenControl: true,
		} );

		var bounds       = new google.maps.LatLngBounds();
		var infoWindow   = new google.maps.InfoWindow();
		var geocoder     = null;
		var placed       = 0;
		var usedSpots    = {};   // "lat,lng" -> number of pins already there.
		var userMoved    = false;
		var fitTimer     = null;
		var total        = config.locations.length;

		// Once the visitor pans/zooms, stop re-fitting so we never fight them.
		[ 'mousedown', 'touchstart', 'wheel' ].forEach( function ( evt ) {
			canvas.addEventListener( evt, function () {
				userMoved = true;
			}, { passive: true } );
		} );

		function buildInfoContent( loc ) {
			var contentParts = [ '<div class="ave-mlm-infowindow">' ];
			contentParts.push( '<div class="ave-mlm-iw-title">' + escapeHtml( loc.name ) + '</div>' );

			var bodyParts = [];
			if ( loc.address ) {
				bodyParts.push( '<div class="ave-mlm-iw-address">' + escapeHtml( loc.address ) + '</div>' );
			}
			if ( loc.phone ) {
				bodyParts.push( '<div class="ave-mlm-iw-phone"><a href="tel:' + escapeAttr( loc.phone ) + '">' + escapeHtml( loc.phone ) + '</a></div>' );
			}
			if ( loc.url ) {
				var linkAttrs = 'class="ave-mlm-iw-btn" href="' + escapeAttr( loc.url ) + '"';
				var relParts = [];

				if ( loc.urlExternal ) {
					linkAttrs += ' target="_blank"';
					relParts.push( 'noopener' );
				}
				if ( loc.urlNofollow ) {
					relParts.push( 'nofollow' );
				}
				if ( relParts.length ) {
					linkAttrs += ' rel="' + relParts.join( ' ' ) + '"';
				}

				var label = loc.linkText ? escapeHtml( loc.linkText ) : 'View Details';
				bodyParts.push( '<div class="ave-mlm-iw-link"><a ' + linkAttrs + '>' + label + '</a></div>' );
			}

			contentParts.push( '<div class="ave-mlm-iw-body">' + bodyParts.join( '' ) + '</div>' );
			contentParts.push( '</div>' );
			return contentParts.join( '' );
		}

		// Nudge pins that land on exactly the same spot so none hides another.
		function spreadPosition( lat, lng ) {
			var key   = lat.toFixed( 5 ) + ',' + lng.toFixed( 5 );
			var count = usedSpots[ key ] || 0;
			usedSpots[ key ] = count + 1;

			if ( 0 === count ) {
				return { lat: lat, lng: lng };
			}

			var ring   = Math.ceil( count / 6 );
			var angle  = ( count % 6 ) * ( Math.PI / 3 );
			var radius = 0.00018 * ring; // ~20 m per ring.
			return {
				lat: lat + radius * Math.sin( angle ),
				lng: lng + radius * Math.cos( angle ),
			};
		}

		function scheduleFit( immediate ) {
			if ( fitTimer ) {
				clearTimeout( fitTimer );
			}
			fitTimer = setTimeout( fitView, immediate ? 0 : 250 );
		}

		function fitView() {
			fitTimer = null;
			if ( ! placed || userMoved ) {
				return;
			}
			if ( config.autoFit && placed > 1 ) {
				map.fitBounds( bounds, 40 );
			} else {
				map.setCenter( bounds.getCenter() );
				map.setZoom( config.zoom || 14 );
			}
		}

		function placeMarker( loc, lat, lng, isFirst ) {
			try {
				var position = spreadPosition( lat, lng );

				var markerOptions = {
					position: position,
					map: map,
					title: loc.name,
				};

				if ( loc.icon ) {
					markerOptions.icon = {
						url: loc.icon,
						scaledSize: new google.maps.Size( 40, 40 ),
					};
				}

				var marker  = new google.maps.Marker( markerOptions );
				var content = buildInfoContent( loc );

				marker.addListener( 'click', function () {
					infoWindow.setContent( content );
					infoWindow.open( map, marker );
				} );

				bounds.extend( position );
				placed++;

				if ( isFirst && config.openFirst ) {
					infoWindow.setContent( content );
					infoWindow.open( map, marker );
				}

				scheduleFit( false );
			} catch ( e ) {
				console.error( 'AVE Multi-Location Map: could not place pin for "' + ( loc.name || loc.address ) + '"', e );
				recordFailure( loc, 'could not place pin' );
			}
		}

		/* ----- Split locations: coordinates / cached / needs geocoding ----- */

		var geocodeQueue = [];
		var failures     = {};   // name -> status, for the console / debug panel.
		var delay        = BASE_DELAY_MS;
		var debugBox     = null;

		if ( /[?&]avemlm_debug=1/.test( window.location.search ) ) {
			debugBox = document.createElement( 'div' );
			debugBox.style.cssText = 'position:absolute;left:8px;bottom:8px;z-index:9999;max-width:60%;max-height:50%;overflow:auto;background:rgba(0,0,0,.85);color:#fff;font:12px/1.4 monospace;padding:8px 10px;border-radius:4px;';
			if ( canvas.parentNode ) {
				canvas.parentNode.style.position = canvas.parentNode.style.position || 'relative';
				canvas.parentNode.appendChild( debugBox );
			}
		}

		function recordFailure( loc, status ) {
			failures[ loc.name || loc.address ] = status;
		}

		function updateDebug( finished ) {
			if ( ! debugBox ) {
				return;
			}
			var lines = [ 'AVE MLM debug: ' + placed + ' / ' + total + ' pins placed' + ( finished ? ' (done)' : ' (working...)' ) ];
			if ( config.skipped ) {
				lines.push( config.skipped + ' location(s) skipped: no address or lat/lng' );
			}
			if ( config.serverGeo ) {
				var sg = config.serverGeo;
				lines.push( 'Server geocoding: ' + sg.resolved + ' resolved (' + sg.cached + ' cached), ' + sg.pending + ' handed to browser' + ( sg.error ? ' — ' + sg.error : '' ) );
			}
			Object.keys( failures ).forEach( function ( k ) {
				lines.push( 'FAILED: ' + k + ' -> ' + failures[ k ] );
			} );
			debugBox.textContent = lines.join( '\n' );
			debugBox.style.whiteSpace = 'pre-wrap';
		}

		function allDone() {
			scheduleFit( true );
			var failedNames = Object.keys( failures );
			console.info( 'AVE Multi-Location Map: ' + placed + ' of ' + total + ' pins placed.' );
			if ( config.skipped ) {
				console.warn( 'AVE Multi-Location Map: ' + config.skipped + ' location(s) were skipped because they have no address and no lat/lng.' );
			}
			if ( failedNames.length ) {
				console.warn( 'AVE Multi-Location Map: not placed:', failures );
			}
			if ( config.serverGeo && config.serverGeo.error ) {
				console.warn( 'AVE Multi-Location Map: server geocoding unavailable (' + config.serverGeo.error + '). Browser fallback is limited by Google to ~20 lookups per visit.' );
			}
			updateDebug( true );
		}

		config.locations.forEach( function ( loc, index ) {
			var isFirst = ( 0 === index );

			if ( 'number' === typeof loc.lat && 'number' === typeof loc.lng ) {
				placeMarker( loc, loc.lat, loc.lng, isFirst );
			} else if ( loc.address ) {
				var cached = cacheGet( loc.address );
				if ( cached ) {
					placeMarker( loc, cached.lat, cached.lng, isFirst );
				} else {
					geocodeQueue.push( { loc: loc, isFirst: isFirst } );
				}
			} else {
				recordFailure( loc, 'no address and no lat/lng' );
			}
		} );

		updateDebug( false );

		if ( geocodeQueue.length ) {
			geocoder = new google.maps.Geocoder();
			runQueue( geocodeQueue, 0 );
		} else {
			allDone();
		}

		/**
		 * Walk a list of addresses one by one. Anything that hits a transient
		 * error (rate limit etc.) is deferred to a later pass instead of being
		 * dropped, and the gap between lookups grows while Google is throttling.
		 */
		function runQueue( items, pass ) {
			var deferred = [];
			var i = 0;

			function step() {
				if ( i >= items.length ) {
					afterPass( deferred, pass );
					return;
				}

				var item    = items[ i ];
				var settled = false;

				function finish( status, results ) {
					if ( settled ) {
						return;
					}
					settled = true;
					clearTimeout( watchdog );

					try {
						if ( 'OK' === status && results && results[ 0 ] ) {
							var pos = results[ 0 ].geometry.location;
							var lat = pos.lat();
							var lng = pos.lng();
							cacheSet( item.loc.address, lat, lng );
							placeMarker( item.loc, lat, lng, item.isFirst );
							delete failures[ item.loc.name || item.loc.address ];
							delay = Math.max( BASE_DELAY_MS, Math.round( delay * 0.9 ) );
						} else if ( 'OVER_QUERY_LIMIT' === status || 'UNKNOWN_ERROR' === status || 'TIMEOUT' === status ) {
							if ( 'OVER_QUERY_LIMIT' === status ) {
								delay = Math.min( MAX_DELAY_MS, delay * 2 );
							}
							recordFailure( item.loc, status + ' (will retry)' );
							deferred.push( item );
						} else {
							console.warn( 'AVE Multi-Location Map: geocode failed for "' + item.loc.address + '" (' + status + ')' );
							recordFailure( item.loc, status );
						}
					} catch ( e ) {
						console.error( 'AVE Multi-Location Map: geocode handler error', e );
						recordFailure( item.loc, 'script error' );
					}

					updateDebug( false );
					i++;
					setTimeout( step, delay );
				}

				var watchdog = setTimeout( function () {
					finish( 'TIMEOUT', null );
				}, REQUEST_TIMEOUT );

				try {
					// Google calls back as ( results, status ); finish() takes ( status, results ).
					// 1.2.2 passed finish directly, so every lookup was read as a failure.
					var pending = geocoder.geocode( { address: item.loc.address }, function ( results, status ) {
						finish( status, results );
					} );
					// Newer Maps JS versions also return a Promise that rejects on non-OK
					// statuses; the callback above already handles them.
					if ( pending && 'function' === typeof pending.catch ) {
						pending.catch( function () {} );
					}
				} catch ( e ) {
					console.error( 'AVE Multi-Location Map: geocode request error', e );
					finish( 'REQUEST_ERROR', null );
				}
			}

			step();
		}

		function afterPass( deferred, pass ) {
			if ( ! deferred.length ) {
				allDone();
				return;
			}
			if ( pass >= MAX_PASSES ) {
				deferred.forEach( function ( item ) {
					recordFailure( item.loc, 'still rate limited after ' + ( MAX_PASSES + 1 ) + ' passes' );
				} );
				allDone();
				return;
			}
			// Let Google's quota window recover, then try only the leftovers.
			setTimeout( function () {
				runQueue( deferred, pass + 1 );
			}, 2000 * ( pass + 1 ) );
		}
	}

	function escapeHtml( str ) {
		var div = document.createElement( 'div' );
		div.textContent = str || '';
		return div.innerHTML;
	}

	function escapeAttr( str ) {
		return ( str || '' ).replace( /"/g, '&quot;' );
	}

	function pollForGoogleMaps( callback ) {
		var tries = 0;
		var timer = setInterval( function () {
			tries++;
			if ( typeof google !== 'undefined' && google.maps ) {
				clearInterval( timer );
				callback();
			} else if ( tries > 100 ) {
				clearInterval( timer ); // ~15s timeout — API key likely missing/invalid.
			}
		}, 150 );
	}

	function initAll() {
		var canvases = document.querySelectorAll( '.ave-mlm-canvas' );
		canvases.forEach( function ( canvas ) {
			pollForGoogleMaps( function () {
				initMap( canvas );
			} );
		} );
	}

	if ( window.elementorFrontend && window.jQuery ) {
		jQuery( window ).on( 'elementor/frontend/init', function () {
			elementorFrontend.hooks.addAction( 'frontend/element_ready/ave-multi-location-map.default', function ( $scope ) {
				var canvas = $scope[ 0 ].querySelector( '.ave-mlm-canvas' );
				if ( canvas ) {
					pollForGoogleMaps( function () {
						initMap( canvas );
					} );
				}
			} );
		} );
	} else {
		document.addEventListener( 'DOMContentLoaded', initAll );
	}
} )();
