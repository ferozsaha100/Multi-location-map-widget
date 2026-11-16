/**
 * AVE Office Map — frontend runtime (AVE Multi-Location Map 2.0).
 *
 * Every pin arrives with saved lat/lng, so nothing is geocoded here and there
 * is no pin limit. The engine library (bundled Leaflet, or Google Maps) loads
 * only when the map scrolls near the viewport.
 *
 * Pins are plain HTML on both engines: an engine-positioned zero-size anchor
 * holds .ave-om-pin, and the popup is a child of that pin, so hovering the
 * popup counts as hovering the pin (it stays open while you reach the button).
 */
( function () {
	'use strict';

	var CLOSE_DELAY = 260;
	var reducedMotion = !! ( window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches );
	var scripts = {};

	/* ------------------------------------------------------------------ *
	 * Loaders
	 * ------------------------------------------------------------------ */

	function loadScript( url ) {
		if ( ! scripts[ url ] ) {
			scripts[ url ] = new Promise( function ( resolve, reject ) {
				var s = document.createElement( 'script' );
				s.src = url;
				s.async = true;
				s.onload = resolve;
				s.onerror = function () {
					reject( new Error( 'Could not load ' + url ) );
				};
				document.head.appendChild( s );
			} );
		}
		return scripts[ url ];
	}

	function loadCss( url ) {
		if ( document.querySelector( 'link[data-ave-om-css="' + url + '"]' ) ) {
			return;
		}
		var l = document.createElement( 'link' );
		l.rel = 'stylesheet';
		l.href = url;
		l.setAttribute( 'data-ave-om-css', url );
		document.head.appendChild( l );
	}

	function loadLeaflet( cfg ) {
		if ( window.L && window.L.map ) {
			return Promise.resolve( window.L );
		}
		loadCss( cfg.leaflet.css );
		return loadScript( cfg.leaflet.js ).then( function () {
			if ( ! window.L || ! window.L.map ) {
				throw new Error( 'Leaflet did not initialise' );
			}
			return window.L;
		} );
	}

	var googlePromise = null;
	function googleReady() {
		return !! ( window.google && window.google.maps && window.google.maps.Map && window.google.maps.OverlayView );
	}

	function loadGoogle( key ) {
		if ( googleReady() ) {
			return Promise.resolve( window.google.maps );
		}
		if ( googlePromise ) {
			return googlePromise;
		}
		googlePromise = new Promise( function ( resolve, reject ) {
			var tries = 0;
			function poll() {
				if ( googleReady() ) {
					resolve( window.google.maps );
				} else if ( ++tries > 100 ) {
					reject( new Error( 'Google Maps did not load (check the key in Settings > AVE Maps).' ) );
				} else {
					setTimeout( poll, 150 );
				}
			}
			// Another plugin or the theme may already be loading Google Maps.
			if ( window.google && window.google.maps ) {
				poll();
				return;
			}
			if ( ! key ) {
				reject( new Error( 'No Google Maps key in Settings > AVE Maps.' ) );
				return;
			}
			window.__aveOmGoogleReady = poll;
			loadScript( 'https://maps.googleapis.com/maps/api/js?key=' + encodeURIComponent( key ) + '&loading=async&callback=__aveOmGoogleReady' ).catch( reject );
		} );
		return googlePromise;
	}

	// Google calls this on an invalid/restricted key.
	var previousAuthFailure = window.gm_authFailure;
	window.gm_authFailure = function () {
		console.error( 'AVE Office Map: Google Maps rejected the API key. Check it in Settings > AVE Maps (Maps JavaScript API enabled, this domain allowed).' );
		var roots = document.querySelectorAll( '.ave-om[data-ave-om-engine="google"]' );
		for ( var i = 0; i < roots.length; i++ ) {
			roots[ i ].classList.add( 'ave-om--no-map' );
		}
		if ( 'function' === typeof previousAuthFailure ) {
			previousAuthFailure();
		}
	};

	/* ------------------------------------------------------------------ *
	 * Engine adapters — same contract on both engines
	 *   addPins( anchors )   fit()   isVisible( i )   panTo( i, done )   panBy( dx, dy )
	 *   setActive( i, on )   onMapClick( fn )
	 * ------------------------------------------------------------------ */

	function leafletAdapter( L, el, cfg ) {
		var map = L.map( el, {
			zoomControl: cfg.zoomControl,
			scrollWheelZoom: cfg.scrollZoom,
			attributionControl: true,
			maxZoom: 19,
		} );
		map.attributionControl.setPrefix( false );
		L.tileLayer( cfg.tiles.url, {
			subdomains: cfg.tiles.subdomains,
			attribution: cfg.tiles.attribution,
			maxZoom: 19,
		} ).addTo( map );

		var markers = [];
		var latlngs = cfg.offices.map( function ( o ) {
			return L.latLng( o.lat, o.lng );
		} );

		return {
			addPins: function ( anchors ) {
				anchors.forEach( function ( anchor, i ) {
					L.DomEvent.disableClickPropagation( anchor );
					L.DomEvent.disableScrollPropagation( anchor );
					var icon = L.divIcon( { html: anchor, className: 'ave-om-anchor', iconSize: [ 0, 0 ], iconAnchor: [ 0, 0 ] } );
					markers.push( L.marker( latlngs[ i ], { icon: icon, interactive: false, keyboard: false } ).addTo( map ) );
				} );
			},
			fit: function () {
				if ( 1 === latlngs.length ) {
					map.setView( latlngs[ 0 ], cfg.maxZoom );
				} else {
					map.fitBounds( L.latLngBounds( latlngs ), { padding: [ 56, 56 ], maxZoom: cfg.maxZoom } );
				}
			},
			isVisible: function ( i ) {
				return map.getBounds().pad( -0.08 ).contains( latlngs[ i ] );
			},
			panTo: function ( i, done ) {
				if ( done ) {
					map.once( 'moveend', done );
				}
				map.panTo( latlngs[ i ], { animate: ! reducedMotion } );
			},
			panBy: function ( dx, dy ) {
				map.panBy( [ dx, dy ], { animate: ! reducedMotion } );
			},
			setActive: function ( i, on ) {
				markers[ i ].setZIndexOffset( on ? 10000 : 0 );
			},
			onMapClick: function ( fn ) {
				map.on( 'click', fn );
			},
		};
	}

	var GOOGLE_STYLES = {
		standard: [],
		silver: [
			{ elementType: 'geometry', stylers: [ { color: '#f5f5f5' } ] },
			{ elementType: 'labels.icon', stylers: [ { visibility: 'off' } ] },
			{ elementType: 'labels.text.fill', stylers: [ { color: '#616161' } ] },
			{ elementType: 'labels.text.stroke', stylers: [ { color: '#f5f5f5' } ] },
			{ featureType: 'poi', elementType: 'geometry', stylers: [ { color: '#eeeeee' } ] },
			{ featureType: 'poi', elementType: 'labels.text.fill', stylers: [ { color: '#757575' } ] },
			{ featureType: 'poi.park', elementType: 'geometry', stylers: [ { color: '#e5e5e5' } ] },
			{ featureType: 'road', elementType: 'geometry', stylers: [ { color: '#ffffff' } ] },
			{ featureType: 'road.arterial', elementType: 'labels.text.fill', stylers: [ { color: '#757575' } ] },
			{ featureType: 'road.highway', elementType: 'geometry', stylers: [ { color: '#dadada' } ] },
			{ featureType: 'road.highway', elementType: 'labels.text.fill', stylers: [ { color: '#616161' } ] },
			{ featureType: 'road.local', elementType: 'labels.text.fill', stylers: [ { color: '#9e9e9e' } ] },
			{ featureType: 'transit.line', elementType: 'geometry', stylers: [ { color: '#e5e5e5' } ] },
			{ featureType: 'water', elementType: 'geometry', stylers: [ { color: '#c9c9c9' } ] },
			{ featureType: 'water', elementType: 'labels.text.fill', stylers: [ { color: '#9e9e9e' } ] },
		],
		dark: [
			{ elementType: 'geometry', stylers: [ { color: '#242f3e' } ] },
			{ elementType: 'labels.text.stroke', stylers: [ { color: '#242f3e' } ] },
			{ elementType: 'labels.text.fill', stylers: [ { color: '#746855' } ] },
			{ featureType: 'administrative.locality', elementType: 'labels.text.fill', stylers: [ { color: '#d59563' } ] },
			{ featureType: 'poi', elementType: 'labels.text.fill', stylers: [ { color: '#d59563' } ] },
			{ featureType: 'poi.park', elementType: 'geometry', stylers: [ { color: '#263c3f' } ] },
			{ featureType: 'road', elementType: 'geometry', stylers: [ { color: '#38414e' } ] },
			{ featureType: 'road', elementType: 'geometry.stroke', stylers: [ { color: '#212a37' } ] },
			{ featureType: 'road', elementType: 'labels.text.fill', stylers: [ { color: '#9ca5b3' } ] },
			{ featureType: 'road.highway', elementType: 'geometry', stylers: [ { color: '#746855' } ] },
			{ featureType: 'road.highway', elementType: 'labels.text.fill', stylers: [ { color: '#f3d19c' } ] },
			{ featureType: 'transit', elementType: 'geometry', stylers: [ { color: '#2f3948' } ] },
			{ featureType: 'water', elementType: 'geometry', stylers: [ { color: '#17263c' } ] },
			{ featureType: 'water', elementType: 'labels.text.fill', stylers: [ { color: '#515c6d' } ] },
		],
	};

	function googleAdapter( gm, el, cfg ) {
		var latlngs = cfg.offices.map( function ( o ) {
			return new gm.LatLng( o.lat, o.lng );
		} );
		var map = new gm.Map( el, {
			center: latlngs[ 0 ],
			zoom: cfg.maxZoom,
			disableDefaultUI: true,
			zoomControl: cfg.zoomControl,
			gestureHandling: cfg.scrollZoom ? 'greedy' : 'cooperative',
			clickableIcons: false,
			styles: GOOGLE_STYLES[ cfg.style ] || [],
		} );

		var anchors = [];
		var overlay = new gm.OverlayView();
		overlay.onAdd = function () {
			var pane = this.getPanes().overlayMouseTarget;
			anchors.forEach( function ( a ) {
				pane.appendChild( a );
				if ( gm.OverlayView.preventMapHitsAndGesturesFrom ) {
					gm.OverlayView.preventMapHitsAndGesturesFrom( a );
				}
			} );
		};
		overlay.draw = function () {
			var proj = this.getProjection();
			if ( ! proj ) {
				return;
			}
			anchors.forEach( function ( a, i ) {
				var p = proj.fromLatLngToDivPixel( latlngs[ i ] );
				a.style.left = p.x + 'px';
				a.style.top = p.y + 'px';
			} );
		};
		overlay.onRemove = function () {
			anchors.forEach( function ( a ) {
				if ( a.parentNode ) {
					a.parentNode.removeChild( a );
				}
			} );
		};

		return {
			addPins: function ( list ) {
				list.forEach( function ( a ) {
					a.classList.add( 'ave-om-anchor', 'ave-om-anchor--google' );
					anchors.push( a );
				} );
				overlay.setMap( map );
			},
			fit: function () {
				if ( 1 === latlngs.length ) {
					map.setCenter( latlngs[ 0 ] );
					map.setZoom( cfg.maxZoom );
					return;
				}
				var b = new gm.LatLngBounds();
				latlngs.forEach( function ( ll ) {
					b.extend( ll );
				} );
				map.fitBounds( b, 56 );
				gm.event.addListenerOnce( map, 'idle', function () {
					if ( map.getZoom() > cfg.maxZoom ) {
						map.setZoom( cfg.maxZoom );
					}
				} );
			},
			isVisible: function ( i ) {
				var b = map.getBounds();
				return ! b || b.contains( latlngs[ i ] );
			},
			panTo: function ( i, done ) {
				if ( done ) {
					// 'idle' may not fire if nothing moved; the timer is the fallback.
					var called = false;
					var once = function () {
						if ( ! called ) {
							called = true;
							done();
						}
					};
					gm.event.addListenerOnce( map, 'idle', once );
					setTimeout( once, 700 );
				}
				map.panTo( latlngs[ i ] );
			},
			panBy: function ( dx, dy ) {
				map.panBy( dx, dy );
			},
			setActive: function ( i, on ) {
				anchors[ i ].style.zIndex = on ? '1000' : '';
			},
			onMapClick: function ( fn ) {
				map.addListener( 'click', fn );
			},
		};
	}

	/* ------------------------------------------------------------------ *
	 * DOM helpers
	 * ------------------------------------------------------------------ */

	function el( tag, cls, text ) {
		var node = document.createElement( tag );
		if ( cls ) {
			node.className = cls;
		}
		if ( null != text ) {
			node.textContent = text;
		}
		return node;
	}

	function pinShape( index, numbered ) {
		var shape = el( 'span', 'ave-om-pin__shape' );
		shape.appendChild( el( 'span', 'ave-om-pin__label' + ( numbered ? '' : ' ave-om-pin__label--dot' ), numbered ? String( index + 1 ) : '' ) );
		return shape;
	}

	function buildPin( office, index, numbered ) {
		var anchor = el( 'div' );
		var cls = 'ave-om-pin';
		if ( office.item ) {
			cls += ' ' + String( office.item ).replace( /[^A-Za-z0-9_-]/g, '' ); // Per-office colour (Elementor CSS).
		}
		if ( office.ownColor ) {
			cls += ' ave-om-pin--own-color';
		}
		var pin = el( 'div', cls );
		pin.setAttribute( 'data-index', index );
		var btn = el( 'button', 'ave-om-pin__marker' );
		btn.type = 'button';
		btn.setAttribute( 'aria-label', office.name + ( office.address ? ', ' + office.address : '' ) );
		btn.setAttribute( 'aria-expanded', 'false' );

		if ( office.icon ) {
			// Custom icon / logo: bottom centre sits on the location.
			pin.classList.add( 'ave-om-pin--icon' );
			btn.classList.add( 'ave-om-pin__marker--icon' );
			var img = el( 'img', 'ave-om-pin__img' );
			img.alt = '';
			img.decoding = 'async';
			img.draggable = false;
			img.onerror = function () {
				// Broken image URL: fall back to the standard pin instead of an invisible marker.
				pin.classList.remove( 'ave-om-pin--icon' );
				btn.classList.remove( 'ave-om-pin__marker--icon' );
				btn.innerHTML = '';
				btn.appendChild( pinShape( index, numbered ) );
			};
			img.src = office.icon;
			btn.appendChild( img );
			if ( numbered ) {
				btn.appendChild( el( 'span', 'ave-om-pin__badge', String( index + 1 ) ) );
			}
		} else {
			btn.appendChild( pinShape( index, numbered ) );
		}

		pin.appendChild( btn );
		anchor.appendChild( pin );
		return { anchor: anchor, pin: pin, marker: btn };
	}

	function buildPopup( office ) {
		var pop = el( 'div', 'ave-om-pop' );
		pop.setAttribute( 'role', 'group' );
		pop.setAttribute( 'aria-label', office.name );
		pop.appendChild( el( 'div', 'ave-om-pop__name', office.name ) );
		if ( office.address ) {
			pop.appendChild( el( 'div', 'ave-om-pop__address', office.address ) );
		}
		if ( office.phones && office.phones.length ) {
			pop.appendChild( buildPhones( office.phones ) );
		}
		if ( office.url ) {
			var a = el( 'a', 'ave-om__btn', office.linkText );
			a.href = office.url;
			var rel = [];
			if ( office.external ) {
				a.target = '_blank';
				rel.push( 'noopener' );
			}
			if ( office.nofollow ) {
				rel.push( 'nofollow' );
			}
			if ( rel.length ) {
				a.rel = rel.join( ' ' );
			}
			pop.appendChild( a );
		}
		return pop;
	}

	/** Static handset icon (Tabler Icons "phone", MIT) — same as the PHP list markup. */
	var PHONE_ICON = '<svg class="ave-om-phone__icon" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M5 4h4l2 5l-2.5 1.5a11 11 0 0 0 5 5l1.5 -2.5l5 2v4a2 2 0 0 1 -2 2a16 16 0 0 1 -15 -15a2 2 0 0 1 2 -2"/></svg>';

	/** Phone rows; label position/style come from classes on the widget root. */
	function buildPhones( phones ) {
		var people = phones.some( function ( p ) {
			return !! p.name;
		} );
		var wrap = el( 'div', 'ave-om-phones' + ( people ? ' ave-om-phones--people' : '' ) );
		phones.forEach( function ( phone ) {
			var row = el( 'div', 'ave-om-phone' );
			if ( phone.name ) {
				row.appendChild( el( 'span', 'ave-om-phone__name', phone.name ) );
			}
			row.insertAdjacentHTML( 'beforeend', PHONE_ICON ); // Static markup only — user text goes in via textContent.
			var body = el( 'span', 'ave-om-phone__body' );
			var a = el( 'a', 'ave-om-phone__number', phone.number );
			a.href = 'tel:' + String( phone.tel ).replace( /[^0-9+,]/g, '' );
			body.appendChild( a );
			if ( phone.label ) {
				body.appendChild( el( 'span', 'ave-om-phone__label', phone.label ) );
			}
			row.appendChild( body );
			wrap.appendChild( row );
		} );
		return wrap;
	}

	/** Keep the popup inside the map without moving the map (no hover flicker). */
	function placePopup( pop, marker, mapEl, maxWidth ) {
		pop.classList.remove( 'is-below' );
		pop.style.removeProperty( '--ave-om-shift' );
		var m = mapEl.getBoundingClientRect();
		var k = marker.getBoundingClientRect();
		pop.style.maxWidth = ( maxWidth || Math.max( 160, m.width - 16 ) ) + 'px';
		var p = pop.getBoundingClientRect();
		if ( p.top < m.top + 8 && ( m.bottom - k.bottom ) > ( k.top - m.top ) ) {
			pop.classList.add( 'is-below' );
			p = pop.getBoundingClientRect();
		}
		var shift = 0;
		if ( p.left < m.left + 8 ) {
			shift = m.left + 8 - p.left;
		} else if ( p.right > m.right - 8 ) {
			shift = m.right - 8 - p.right;
		}
		if ( shift ) {
			pop.style.setProperty( '--ave-om-shift', Math.round( shift ) + 'px' );
		}
	}

	/* ------------------------------------------------------------------ *
	 * Widget controller
	 * ------------------------------------------------------------------ */

	function OfficeMap( root, cfg ) {
		this.root = root;
		this.cfg = cfg;
		this.mapEl = root.querySelector( '.ave-om__map' );
		this.items = Array.prototype.slice.call( root.querySelectorAll( '.ave-om__item' ) );
		this.pins = [];
		this.active = -1;
		this.sticky = false;
		this.timer = null;
		this.popup = null;
	}

	OfficeMap.prototype.start = function () {
		var self = this;
		var cfg = this.cfg;
		var engine = 'google' === cfg.engine
			? loadGoogle( cfg.googleKey ).then( function ( gm ) {
				return googleAdapter( gm, self.mapEl, cfg );
			} )
			: loadLeaflet( cfg ).then( function ( L ) {
				return leafletAdapter( L, self.mapEl, cfg );
			} );

		return engine.then( function ( adapter ) {
			self.adapter = adapter;
			self.pins = cfg.offices.map( function ( o, i ) {
				return buildPin( o, i, cfg.numbers );
			} );
			adapter.addPins( self.pins.map( function ( p ) {
				return p.anchor;
			} ) );
			adapter.fit();
			self.bind();
			self.root.classList.add( 'ave-om--ready' );
		} ).catch( function ( err ) {
			console.error( 'AVE Office Map: ' + ( err && err.message ? err.message : err ) );
			self.root.classList.add( 'ave-om--no-map' );
		} );
	};

	OfficeMap.prototype.bind = function () {
		var self = this;

		this.pins.forEach( function ( p, i ) {
			p.pin.addEventListener( 'mouseenter', function () {
				self.open( i, { source: 'pin' } );
			} );
			p.pin.addEventListener( 'mouseleave', function () {
				self.scheduleClose();
			} );
			p.marker.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				if ( self.active === i && self.sticky ) {
					self.close();
				} else {
					self.open( i, { source: 'pin', sticky: true } );
				}
			} );
			p.marker.addEventListener( 'focus', function () {
				if ( ! self.quietFocus ) {
					self.open( i, { source: 'pin' } );
				}
			} );
			p.pin.addEventListener( 'focusout', function ( e ) {
				if ( ! p.pin.contains( e.relatedTarget ) && ! self.sticky ) {
					self.scheduleClose();
				}
			} );
		} );

		this.items.forEach( function ( item, i ) {
			item.addEventListener( 'mouseenter', function () {
				self.open( i, { source: 'list' } );
			} );
			item.addEventListener( 'mouseleave', function () {
				self.scheduleClose();
			} );
			item.addEventListener( 'click', function ( e ) {
				if ( e.target.closest && e.target.closest( 'a' ) ) {
					return; // Let the office link work normally.
				}
				self.open( i, { source: 'list', sticky: true, pan: true } );
			} );
		} );

		this.adapter.onMapClick( function () {
			self.close();
		} );

		this.root.addEventListener( 'keydown', function ( e ) {
			if ( 'Escape' === e.key && self.active > -1 ) {
				var marker = self.pins[ self.active ].marker;
				self.close();
				self.quietFocus = true; // Return focus to the pin without reopening it.
				marker.focus( { preventScroll: true } );
				self.quietFocus = false;
			}
		} );
	};

	OfficeMap.prototype.open = function ( i, opts ) {
		opts = opts || {};
		clearTimeout( this.timer );
		if ( this.active === i ) {
			// On touch, the emulated hover opens the popup first; the tap then makes it sticky.
			var becameSticky = !! opts.sticky && ! this.sticky;
			this.sticky = this.sticky || !! opts.sticky;
			if ( becameSticky || opts.pan ) {
				this.reveal( i, opts );
			}
			return;
		}
		this.close();

		var p = this.pins[ i ];
		this.active = i;
		this.sticky = !! opts.sticky;

		p.pin.classList.add( 'is-active' );
		p.marker.setAttribute( 'aria-expanded', 'true' );
		this.adapter.setActive( i, true );
		this.popup = buildPopup( this.cfg.offices[ i ] );
		p.pin.appendChild( this.popup );
		placePopup( this.popup, p.marker, this.mapEl );

		if ( opts.sticky || opts.pan ) {
			this.reveal( i, opts );
		}

		var item = this.items[ i ];
		if ( item ) {
			item.classList.add( 'is-active' );
			if ( 'list' !== opts.source ) {
				this.scrollListTo( item );
			}
		}
	};

	/**
	 * Click / tap / list click: bring the pin into view if needed, then pan
	 * just enough that the whole popup is visible. (Hover never moves the map,
	 * which would slide the pin out from under the cursor.)
	 */
	OfficeMap.prototype.reveal = function ( i, opts ) {
		var self = this;
		var pop = this.popup;
		var marker = this.pins[ i ].marker;
		var fit = function () {
			if ( ! pop || self.popup !== pop ) {
				return;
			}
			placePopup( pop, marker, self.mapEl );
			self.fitPopup( pop, marker );
		};
		if ( opts.pan && ! this.adapter.isVisible( i ) ) {
			this.adapter.panTo( i, fit );
		} else {
			fit();
		}
	};

	OfficeMap.prototype.fitPopup = function ( pop, marker ) {
		var pad = 10;
		var m = this.mapEl.getBoundingClientRect();
		var maxBottom = m.bottom - pad;
		var top = m.top + pad;
		var dx = 0;
		var dy = 0;

		// Popup and its pin move together; fit them as one block.
		var block = function () {
			var r = pop.getBoundingClientRect();
			var k = marker.getBoundingClientRect();
			var t = Math.min( r.top, k.top );
			var b = Math.max( r.bottom, k.bottom );
			return { left: r.left, right: r.right, top: t, bottom: b, height: b - t };
		};
		var u = block();

		// Map controls are always drawn above map content, so treat the zoom
		// buttons as an obstacle: go below them, or beside them if the block
		// is too tall for that (short phone maps).
		var ctl = this.mapEl.querySelector( '.leaflet-control-zoom' );
		if ( ctl ) {
			var c = ctl.getBoundingClientRect();
			if ( u.left < c.right + 8 && u.right > c.left ) {
				if ( u.height <= maxBottom - ( c.bottom + 8 ) ) {
					top = Math.max( top, c.bottom + 8 );
				} else {
					var beside = ( m.right - pad ) - ( c.right + 8 );
					if ( beside >= 180 ) {
						placePopup( pop, marker, this.mapEl, Math.min( beside, u.right - u.left ) );
						u = block();
						if ( u.left < c.right + 8 ) {
							dx = u.left - ( c.right + 8 );
						}
					}
				}
			}
		}

		if ( u.height > maxBottom - top || u.top < top ) {
			dy = u.top - top; // Taller than the space: show its top.
		} else if ( u.bottom > maxBottom ) {
			dy = u.bottom - maxBottom;
		}
		if ( Math.abs( dx ) >= 1 || Math.abs( dy ) >= 1 ) {
			this.adapter.panBy( Math.round( dx ), Math.round( dy ) );
		}
	};

	OfficeMap.prototype.scheduleClose = function () {
		var self = this;
		clearTimeout( this.timer );
		if ( this.sticky ) {
			return;
		}
		this.timer = setTimeout( function () {
			self.close();
		}, CLOSE_DELAY );
	};

	OfficeMap.prototype.close = function () {
		clearTimeout( this.timer );
		if ( this.active < 0 ) {
			return;
		}
		var p = this.pins[ this.active ];
		p.pin.classList.remove( 'is-active' );
		p.marker.setAttribute( 'aria-expanded', 'false' );
		this.adapter.setActive( this.active, false );
		if ( this.popup && this.popup.parentNode ) {
			this.popup.parentNode.removeChild( this.popup );
		}
		if ( this.items[ this.active ] ) {
			this.items[ this.active ].classList.remove( 'is-active' );
		}
		this.popup = null;
		this.active = -1;
		this.sticky = false;
	};

	/** Scroll only the list column, never the page. */
	OfficeMap.prototype.scrollListTo = function ( item ) {
		var list = item.closest( '.ave-om__list' );
		if ( ! list || list.scrollHeight <= list.clientHeight ) {
			return;
		}
		var top = item.offsetTop;
		var bottom = top + item.offsetHeight;
		if ( top < list.scrollTop ) {
			list.scrollTop = top - 8;
		} else if ( bottom > list.scrollTop + list.clientHeight ) {
			list.scrollTop = bottom - list.clientHeight + 8;
		}
	};

	/* ------------------------------------------------------------------ *
	 * Boot
	 * ------------------------------------------------------------------ */

	function init( root ) {
		if ( ! root || root.getAttribute( 'data-ave-om-init' ) ) {
			return;
		}
		root.setAttribute( 'data-ave-om-init', '1' );

		var cfg;
		try {
			cfg = JSON.parse( root.getAttribute( 'data-ave-om' ) );
		} catch ( e ) {
			console.error( 'AVE Office Map: invalid config', e );
			return;
		}
		root.setAttribute( 'data-ave-om-engine', cfg.engine );

		if ( ! cfg.offices || ! cfg.offices.length || cfg.disabled ) {
			root.classList.add( 'ave-om--no-map' );
			return;
		}

		var widget = new OfficeMap( root, cfg );
		root.aveOfficeMap = widget;

		if ( ! ( 'IntersectionObserver' in window ) ) {
			widget.start();
			return;
		}
		var io = new IntersectionObserver( function ( entries ) {
			if ( entries.some( function ( e ) {
				return e.isIntersecting;
			} ) ) {
				io.disconnect();
				widget.start();
			}
		}, { rootMargin: '200px 0px' } );
		io.observe( root );
	}

	function initAll() {
		var roots = document.querySelectorAll( '.ave-om[data-ave-om]' );
		for ( var i = 0; i < roots.length; i++ ) {
			init( roots[ i ] );
		}
	}

	function hookElementor() {
		window.elementorFrontend.hooks.addAction( 'frontend/element_ready/ave-office-map.default', function ( $scope ) {
			init( $scope[ 0 ].querySelector( '.ave-om[data-ave-om]' ) );
		} );
	}

	window.aveOfficeMapInit = initAll;

	if ( window.elementorFrontend && window.elementorFrontend.hooks ) {
		hookElementor();
	} else if ( window.jQuery ) {
		window.jQuery( window ).on( 'elementor/frontend/init', hookElementor );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', initAll );
	} else {
		initAll();
	}
} )();
