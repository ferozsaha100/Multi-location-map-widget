/**
 * Tests for ave-office-map-editor.js against mocked Elementor ($e, channels,
 * repeater containers), jQuery and fetch. Usage: node tests/js/editor.test.js <path-to-ave-office-map-editor.js>
 */
'use strict';
const fs = require( 'fs' );
const vm = require( 'vm' );
const src = fs.readFileSync( process.argv[ 2 ], 'utf8' );

let fails = 0, passes = 0;
function check( c, l ) { if ( c ) { passes++; console.log( 'PASS ' + l ); } else { fails++; console.log( 'FAIL ' + l ); } }

function makeModel( attrs ) {
	return { a: Object.assign( {}, attrs ), get( k ) { return this.a[ k ]; }, set( v ) { Object.assign( this.a, v ); this.sets = ( this.sets || 0 ) + 1; } };
}
function makeEl() {
	const status = { _text: '', _css: {}, length: 0 };
	return {
		children: [],
		find( sel ) { return sel === '.ave-om-editor-status' && this.children.length ? this.children[ 0 ] : { length: 0 }; },
		append( child ) { child.length = 1; this.children.push( child ); },
		length: 1,
		statusText() { return this.children.length ? this.children[ 0 ]._text : ''; },
		statusColor() { return this.children.length ? this.children[ 0 ]._css.color : ''; },
	};
}
function jq( arg ) {
	if ( 'string' === typeof arg && arg.trim().startsWith( '<' ) ) {
		const node = { _text: '', _css: {}, length: 1, css( k, v ) { this._css[ k ] = v; return this; }, text( t ) { this._text = t; return this; } };
		return node;
	}
	return { on() {} };
}

function boot( fetchImpl, withE ) {
	const handlers = {};
	const runs = [];
	const ctx = {
		jQuery: jq, console,
		Promise, JSON, String, Number, isNaN, Math, Array, Error, parseInt,
		setTimeout, clearTimeout,
		fetch: fetchImpl,
		elementor: { channels: { editor: { on( ev, fn ) { handlers[ ev ] = fn; } } }, saver: { setFlagEditorChange() { ctx.flagged = true; } } },
		aveOfficeMapEditor: { restUrl: 'https://x/wp-json/ave-mlm/v1/geocode', nonce: 'N123', i18n: {} },
	};
	if ( withE ) {
		ctx.$e = { run( cmd, args ) { runs.push( { cmd, args } ); args.container.settings.set( args.settings ); } };
	}
	ctx.window = ctx;
	vm.runInNewContext( src, ctx );
	return { handlers, runs, ctx };
}

function response( status, body, delay ) {
	return new Promise( ( res ) => setTimeout( () => res( { ok: status >= 200 && status < 300, status, json: () => Promise.resolve( body ) } ), delay || 0 ) );
}

( async () => {
	/* E1: single row */
	let calls = [];
	let env = boot( ( url, opts ) => { calls.push( { url, opts } ); return response( 200, { lat: 27.77276, lng: -82.63596, label: 'St. Petersburg, FL' } ); }, true );
	check( 'function' === typeof env.handlers[ 'aveOfficeMap:findPin' ] && 'function' === typeof env.handlers[ 'aveOfficeMap:findAll' ], 'E0: both button events registered on elementor.channels.editor' );
	let row = { settings: makeModel( { office_name: 'Downtown', address: '100 2nd Ave N, St. Petersburg, FL', lat: '', lng: '' } ) };
	let view = { elementSettingsModel: row.settings, container: row, $el: makeEl() };
	await env.handlers[ 'aveOfficeMap:findPin' ]( view );
	const body = JSON.parse( calls[ 0 ].opts.body );
	check( 1 === calls.length && 'POST' === calls[ 0 ].opts.method && 'N123' === calls[ 0 ].opts.headers[ 'X-WP-Nonce' ] && '100 2nd Ave N, St. Petersburg, FL' === body.address, 'E1: POSTs the row address with the REST nonce' );
	check( 1 === env.runs.length && 'document/elements/settings' === env.runs[ 0 ].cmd && env.runs[ 0 ].args.container === row && true === env.runs[ 0 ].args.options.external, 'E1: writes through $e.run(document/elements/settings) on the row container, external:true' );
	check( '27.77276' === row.settings.get( 'lat' ) && '-82.63596' === row.settings.get( 'lng' ), 'E1: Latitude / Longitude controls filled' );
	check( /Pin placed: St\. Petersburg, FL/.test( view.$el.statusText() ), 'E1: status line shows what was found' );

	/* E2: no address */
	calls = [];
	row = { settings: makeModel( { address: '  ' } ) };
	view = { elementSettingsModel: row.settings, container: row, $el: makeEl() };
	await env.handlers[ 'aveOfficeMap:findPin' ]( view );
	check( 0 === calls.length && /Enter an address first/.test( view.$el.statusText() ), 'E2: empty address → message, no request' );

	/* E3: not found */
	env = boot( () => response( 404, { code: 'ave_mlm_not_found', message: 'OpenStreetMap could not find this address.', data: { status: 404 } } ), true );
	row = { settings: makeModel( { address: 'zzz' } ) };
	view = { elementSettingsModel: row.settings, container: row, $el: makeEl() };
	await env.handlers[ 'aveOfficeMap:findPin' ]( view );
	check( 0 === env.runs.length && /could not find/.test( view.$el.statusText() ) && '#b32d2e' === view.$el.statusColor(), 'E3: not found → red message, nothing written' );

	/* E4: find all — sequential, skips rows that already have pins or no address */
	let inFlight = 0, maxInFlight = 0;
	const asked = [];
	env = boot( ( url, opts ) => {
		const a = JSON.parse( opts.body ).address;
		asked.push( a );
		inFlight++; maxInFlight = Math.max( maxInFlight, inFlight );
		const r = a.includes( 'Atlantis' )
			? response( 404, { code: 'ave_mlm_not_found', message: 'no' }, 15 )
			: response( 200, { lat: 1 + asked.length, lng: -80 - asked.length, label: a }, 15 );
		return r.then( ( x ) => { inFlight--; return x; } );
	}, true );
	const rows = [
		{ office_name: 'A', address: '1 A St', lat: '27.1', lng: '-82.1' },
		{ office_name: 'B', address: '2 B St', lat: '', lng: '' },
		{ office_name: 'C', address: '', lat: '', lng: '' },
		{ office_name: 'D', address: '4 D St', lat: 'abc', lng: '-82' },
		{ office_name: 'Lost', address: '5 Atlantis Way', lat: '', lng: '' },
		{ office_name: 'F', address: '6 F St', lat: '27,5', lng: '-82,4' },
	].map( ( a ) => ( { settings: makeModel( a ) } ) );
	const widgetView = { container: { repeaters: { offices: { children: rows } } }, $el: makeEl() };
	await env.handlers[ 'aveOfficeMap:findAll' ]( widgetView );
	check( '2 B St|4 D St|5 Atlantis Way' === asked.join( '|' ), 'E4: only rows with an address and no valid pin are looked up (comma decimals count as valid)' );
	check( 1 === maxInFlight, 'E4: lookups run one at a time' );
	check( 2 === env.runs.length && '' !== rows[ 1 ].settings.get( 'lat' ) && '27.1' === rows[ 0 ].settings.get( 'lat' ), 'E4: found rows written, existing pins untouched' );
	check( /Found 2 of 3\. Not found: Lost/.test( widgetView.$el.statusText() ), 'E4: summary names the office that was not found (' + widgetView.$el.statusText() + ')' );

	/* E5: rate limit stops the batch */
	let n = 0;
	env = boot( () => ( ++n === 2 ? response( 429, { code: 'ave_mlm_rate_limited', message: 'OpenStreetMap is limiting requests.' } ) : response( 200, { lat: 1, lng: 2, label: 'x' } ) ), true );
	const rows5 = [ 1, 2, 3, 4 ].map( ( i ) => ( { settings: makeModel( { office_name: 'O' + i, address: i + ' St', lat: '', lng: '' } ) } ) );
	const v5 = { container: { repeaters: { offices: { children: rows5 } } }, $el: makeEl() };
	await env.handlers[ 'aveOfficeMap:findAll' ]( v5 );
	check( 2 === n && /Found 1 of 4\. OpenStreetMap is limiting/.test( v5.$el.statusText() ), 'E5: a rate-limit stops the batch and keeps what was found' );

	/* E6: all set */
	env = boot( () => { throw new Error( 'should not fetch' ); }, true );
	const v6 = { container: { repeaters: { offices: { children: [ { settings: makeModel( { address: 'x', lat: '1', lng: '2' } ) } ] } } }, $el: makeEl() };
	await env.handlers[ 'aveOfficeMap:findAll' ]( v6 );
	check( /Every office already has a pin/.test( v6.$el.statusText() ), 'E6: nothing to do → says so' );

	/* E7: older Elementor without $e / containers */
	env = boot( () => response( 200, { lat: 5, lng: 6, label: 'y' } ), false );
	const m = makeModel( { address: '9 Old St', lat: '', lng: '' } );
	const v7 = { elementSettingsModel: makeModel( { offices: { models: [ m ] } } ), $el: makeEl() };
	await env.handlers[ 'aveOfficeMap:findAll' ]( v7 );
	check( '5' === m.get( 'lat' ) && '6' === m.get( 'lng' ) && env.ctx.flagged, 'E7: fallback writes the model directly and marks the page as changed' );

	console.log( fails ? `\n${ fails } FAILED, ${ passes } passed` : `\nALL ${ passes } PASSED` );
	process.exit( fails ? 1 : 0 );
} )();
