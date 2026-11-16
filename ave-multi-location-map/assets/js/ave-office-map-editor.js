/**
 * AVE Office Map — Elementor editor buttons.
 *
 *  "Find on map"          (per office row)  → looks up that row's address.
 *  "Find all missing pins" (widget level)   → every row without lat/lng, one
 *                                              at a time (the server spaces
 *                                              OpenStreetMap calls 1.1 s apart).
 *
 * Results are written into the row's Latitude / Longitude controls, so they
 * are saved with the page and visitors never trigger a lookup.
 */
( function ( $ ) {
	'use strict';

	var data = window.aveOfficeMapEditor || {};
	var i18n = data.i18n || {};

	function t( key, fallback ) {
		return i18n[ key ] || fallback;
	}

	function format( str ) {
		var args = Array.prototype.slice.call( arguments, 1 );
		var n = 0;
		return String( str ).replace( /%(\d+\$)?d/g, function ( m, pos ) {
			var idx = pos ? parseInt( pos, 10 ) - 1 : n++;
			return String( args[ idx ] );
		} );
	}

	function isCoord( value, limit ) {
		var v = String( null == value ? '' : value ).trim().replace( ',', '.' );
		if ( '' === v || isNaN( Number( v ) ) ) {
			return false;
		}
		return Math.abs( Number( v ) ) <= limit;
	}

	function hasPin( settings ) {
		return isCoord( settings.get( 'lat' ), 90 ) && isCoord( settings.get( 'lng' ), 180 );
	}

	/* ---------- Server call ---------- */

	function lookup( address ) {
		return window.fetch( data.restUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': data.nonce,
			},
			body: JSON.stringify( { address: address } ),
		} ).then( function ( res ) {
			return res.json().catch( function () {
				return {};
			} ).then( function ( body ) {
				if ( res.ok && body && 'number' === typeof body.lat ) {
					return body;
				}
				var err = new Error( ( body && body.message ) || t( 'failed', 'Lookup failed. Try again.' ) );
				err.status = res.status;
				err.code = body && body.code;
				throw err;
			} );
		} );
	}

	/* ---------- Writing lat/lng into a repeater row ---------- */

	function writeCoords( rowContainer, rowSettings, lat, lng ) {
		var values = { lat: String( lat ), lng: String( lng ) };
		if ( rowContainer && window.$e && 'function' === typeof window.$e.run ) {
			window.$e.run( 'document/elements/settings', {
				container: rowContainer,
				settings: values,
				options: { external: true },
			} );
			return;
		}
		// Older Elementor: set the model directly and flag the document as changed.
		rowSettings.set( values );
		if ( window.elementor && elementor.saver && elementor.saver.setFlagEditorChange ) {
			elementor.saver.setFlagEditorChange( true );
		}
	}

	/** Row containers (Elementor 3.x) or bare row models (fallback). */
	function getRows( view ) {
		var container = view.container || ( view.options && view.options.container );
		if ( container && container.repeaters && container.repeaters.offices ) {
			return container.repeaters.offices.children.map( function ( child ) {
				return { container: child, settings: child.settings };
			} );
		}
		var collection = view.elementSettingsModel && view.elementSettingsModel.get( 'offices' );
		if ( collection && collection.models ) {
			return collection.models.map( function ( model ) {
				return { container: null, settings: model };
			} );
		}
		return [];
	}

	/* ---------- Status line under a button control ---------- */

	function status( view, text, kind ) {
		var $el = view.$el;
		if ( ! $el || ! $el.length ) {
			return;
		}
		var $s = $el.find( '.ave-om-editor-status' );
		if ( ! $s.length ) {
			$s = $( '<div class="ave-om-editor-status" style="margin-top:6px;font-size:11px;line-height:1.4;"></div>' );
			$el.append( $s );
		}
		var colors = { ok: '#2e7d32', error: '#b32d2e', busy: '#6d7882' };
		$s.css( 'color', colors[ kind ] || colors.busy ).text( text );
	}

	/* ---------- Handlers ---------- */

	var busy = false;

	function findPin( view ) {
		var settings = view.elementSettingsModel;
		var container = view.container || ( view.options && view.options.container ) || null;
		var address = String( settings.get( 'address' ) || '' ).trim();

		if ( ! address ) {
			status( view, t( 'noAddress', 'Enter an address first.' ), 'error' );
			return Promise.resolve();
		}
		status( view, t( 'finding', 'Finding…' ), 'busy' );

		return lookup( address ).then( function ( res ) {
			writeCoords( container, settings, res.lat, res.lng );
			status( view, t( 'found', 'Pin placed:' ) + ' ' + ( res.label || res.lat + ', ' + res.lng ), 'ok' );
		} ).catch( function ( err ) {
			status( view, err.message, 'error' );
		} );
	}

	function findAll( view ) {
		if ( busy ) {
			return Promise.resolve();
		}
		var todo = getRows( view ).filter( function ( row ) {
			return String( row.settings.get( 'address' ) || '' ).trim() && ! hasPin( row.settings );
		} );

		if ( ! todo.length ) {
			status( view, t( 'allSet', 'Every office already has a pin.' ), 'ok' );
			return Promise.resolve();
		}

		busy = true;
		var found = 0;
		var missed = [];
		var stopped = null;

		var chain = todo.reduce( function ( prev, row, index ) {
			return prev.then( function () {
				if ( stopped ) {
					return;
				}
				status( view, format( t( 'progress', 'Finding %1$d of %2$d…' ), index + 1, todo.length ), 'busy' );
				return lookup( String( row.settings.get( 'address' ) ).trim() ).then( function ( res ) {
					writeCoords( row.container, row.settings, res.lat, res.lng );
					found++;
				} ).catch( function ( err ) {
					if ( 404 === err.status ) {
						missed.push( row.settings.get( 'office_name' ) || row.settings.get( 'address' ) );
					} else {
						stopped = err; // Rate limit, expired session, server down: stop, keep what we have.
					}
				} );
			} );
		}, Promise.resolve() );

		return chain.then( function () {
			busy = false;
			var msg = format( t( 'doneSummary', 'Found %1$d of %2$d.' ), found, todo.length );
			if ( missed.length ) {
				msg += ' ' + t( 'notFound', 'Not found:' ) + ' ' + missed.join( ', ' );
			}
			if ( stopped ) {
				msg += ' ' + stopped.message;
			}
			status( view, msg, missed.length || stopped ? 'error' : 'ok' );
		} );
	}

	function register() {
		if ( ! window.elementor || ! elementor.channels || ! elementor.channels.editor ) {
			return false;
		}
		elementor.channels.editor.on( 'aveOfficeMap:findPin', findPin );
		elementor.channels.editor.on( 'aveOfficeMap:findAll', findAll );
		return true;
	}

	if ( ! register() ) {
		$( window ).on( 'elementor:init', register );
	}
} )( jQuery );
