/**
 * Phones and short maps: a tapped office's popup must be fully visible (the map
 * pans to fit it), while desktop hover never moves the map.
 * Usage: node tests/browser/mobile.test.js <plugin-dir> <frag-dir> <shots-dir>
 */
'use strict';
const fs = require( 'fs' );
const path = require( 'path' );
const { chromium, devices } = require( 'playwright' );
const [ , , pluginDir, fragDir, shotDir ] = process.argv;
const BASE = 'http://127.0.0.1:8765/';
let fails = 0, passes = 0;
const check = ( c, l ) => { if ( c ) { passes++; console.log( 'PASS ' + l ); } else { fails++; console.log( 'FAIL ' + l ); } };

const page = ( frag, height ) => `<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="stylesheet" href="${ BASE }plugin/assets/css/ave-office-map.css">
<style>body{margin:0;padding:16px;font-family:"Liberation Sans",Arial,sans-serif}.ave-om{--ave-om-height:${ height }px}</style></head>
<body>${ frag }<script src="${ BASE }plugin/assets/js/ave-office-map.js"></script></body></html>`;

async function context( browser, opts, height ) {
	const ctx = await browser.newContext( opts );
	await ctx.route( '**/*', ( route ) => {
		const url = route.request().url();
		if ( url.startsWith( BASE + 'pages/' ) ) return route.fulfill( { contentType: 'text/html', body: page( fs.readFileSync( path.join( fragDir, url.slice( ( BASE + 'pages/' ).length ) + '.frag.html' ), 'utf8' ), height ) } );
		if ( url.startsWith( BASE + 'plugin/' ) ) { const f = path.join( pluginDir, url.slice( ( BASE + 'plugin/' ).length ).split( '?' )[ 0 ] ); return route.fulfill( { contentType: f.endsWith( '.js' ) ? 'application/javascript' : 'text/css', body: fs.readFileSync( f ) } ); }
		if ( /basemaps\.cartocdn\.com/.test( url ) ) return route.fulfill( { contentType: 'image/svg+xml', body: '<svg xmlns="http://www.w3.org/2000/svg" width="256" height="256"><rect width="256" height="256" fill="#f2f1ee"/><path d="M0 90H256M120 0V256" stroke="#fff" stroke-width="6"/></svg>' } );
		return route.abort();
	} );
	return ctx;
}

const popupInsideMap = ( p ) => p.evaluate( () => {
	const m = document.querySelector( '.ave-om__map' ).getBoundingClientRect();
	const pop = document.querySelector( '.ave-om-pop' );
	if ( ! pop ) return { open: false };
	const r = pop.getBoundingClientRect();
	const z = document.querySelector( '.leaflet-control-zoom' );
	const c = z ? z.getBoundingClientRect() : null;
	const underZoom = !! c && r.left < c.right && r.right > c.left && r.top < c.bottom && r.bottom > c.top;
	const k = document.querySelector( '.ave-om-pin.is-active .ave-om-pin__marker' ).getBoundingClientRect();
	const pinVisible = k.top >= m.top - 1 && k.bottom <= m.bottom + 1;
	return { open: true, inside: r.top >= m.top - 1 && r.bottom <= m.bottom + 1 && r.left >= m.left - 1 && r.right <= m.right + 1 && ! underZoom && pinVisible, underZoom, pinVisible, top: Math.round( r.top - m.top ), bottom: Math.round( m.bottom - r.bottom ) };
} );

( async () => {
	const browser = await chromium.launch();
	const errors = [];

	/* ---- Phone: 300px-tall map, popups with two phone numbers ---- */
	const phone = await context( browser, { ...devices[ 'iPhone 13' ] }, 300 );
	let p = await phone.newPage();
	p.on( 'pageerror', ( e ) => errors.push( e.message ) );
	await p.goto( BASE + 'pages/list-45' );
	await p.waitForSelector( '.ave-om--ready', { timeout: 8000 } );
	await p.waitForTimeout( 400 );

	// Tap the pin of an office that has two phone numbers (tall popup).
	const idx = await p.evaluate( () => {
		const m = document.querySelector( '.ave-om__map' ).getBoundingClientRect();
		const pins = Array.from( document.querySelectorAll( '.ave-om-pin__marker' ) );
		// Pick the lowest visible pin of an even-numbered office (they carry two phones).
		let best = -1, y = -1;
		pins.forEach( ( b, i ) => { const r = b.getBoundingClientRect(); if ( 0 === i % 2 && r.top > m.top && r.bottom < m.bottom && r.bottom > y ) { y = r.bottom; best = i; } } );
		return best;
	} );
	await p.locator( '.ave-om-pin__marker' ).nth( idx ).tap();
	await p.waitForTimeout( 700 );
	let g = await popupInsideMap( p );
	check( g.open && g.inside, `T1: tapping a low pin (office ${ idx + 1 }) pans the map so the whole popup and its pin are visible, clear of the zoom buttons ${ JSON.stringify( g ) }` );
	await p.screenshot( { path: path.join( shotDir, 'mobile-tap.png' ) } );

	// Tap a list item whose pin is out of view.
	await p.locator( '.ave-om-pin__marker' ).nth( idx ).tap(); // close (toggle)
	await p.waitForTimeout( 200 );
	for ( let z = 0; z < 2; z++ ) { // Zoom in so some offices are off-screen.
		await p.locator( '.leaflet-control-zoom-in' ).tap();
		await p.waitForTimeout( 450 );
	}
	const far = await p.evaluate( () => {
		const m = document.querySelector( '.ave-om__map' ).getBoundingClientRect();
		const pins = Array.from( document.querySelectorAll( '.ave-om-pin__marker' ) );
		return pins.findIndex( ( b, i ) => { const r = b.getBoundingClientRect(); return 0 === i % 2 && ( r.bottom < m.top || r.top > m.bottom || r.right < m.left || r.left > m.right ); } );
	} );
	if ( far > -1 ) {
		await p.locator( '.ave-om__item' ).nth( far ).locator( '.ave-om__select' ).tap();
		await p.waitForTimeout( 1200 );
		g = await popupInsideMap( p );
		check( g.open && g.inside, `T2: tapping office ${ far + 1 } in the list brings its pin and full popup into view ${ JSON.stringify( g ) }` );
	} else {
		check( true, 'T2: (every office already visible at this zoom — skipped)' );
	}
	await p.close();

	/* ---- Desktop: hover must not move the map ---- */
	const desk = await context( browser, { viewport: { width: 1200, height: 800 } }, 300 );
	p = await desk.newPage();
	p.on( 'pageerror', ( e ) => errors.push( e.message ) );
	await p.goto( BASE + 'pages/popup-45' );
	await p.waitForSelector( '.ave-om--ready', { timeout: 8000 } );
	await p.waitForTimeout( 400 );
	const pos = () => p.locator( '.ave-om-pin' ).nth( 3 ).evaluate( ( e ) => { const r = e.getBoundingClientRect(); return Math.round( r.left ) + ',' + Math.round( r.top ); } );
	const before = await pos();
	for ( let i = 0; i < 10; i++ ) {
		await p.locator( '.ave-om-pin__marker' ).nth( i ).hover( { force: true } );
		await p.waitForTimeout( 60 );
	}
	await p.waitForTimeout( 400 );
	check( before === await pos(), 'T3: hovering pins on desktop never pans the map (no flicker)' );

	// Desktop click on a pin near the top edge with a tall popup → map pans to fit.
	await p.mouse.move( 2, 2 );
	await p.waitForTimeout( 350 );
	const top = await p.evaluate( () => {
		const m = document.querySelector( '.ave-om__map' ).getBoundingClientRect();
		const pins = Array.from( document.querySelectorAll( '.ave-om-pin__marker' ) );
		let best = -1, y = 1e9;
		pins.forEach( ( b, i ) => { const r = b.getBoundingClientRect(); if ( 0 === i % 2 && r.top > m.top && r.bottom < m.bottom && r.left > m.left + 40 && r.top < y ) { y = r.top; best = i; } } );
		return best;
	} );
	await p.locator( '.ave-om-pin__marker' ).nth( top ).click( { force: true } );
	await p.waitForTimeout( 700 );
	g = await popupInsideMap( p );
	check( g.open && g.inside, `T4: clicking a pin on a short desktop map also fits its popup ${ JSON.stringify( g ) }` );
	await p.close();

	check( 0 === errors.length, 'No page errors' + ( errors.length ? ': ' + errors.join( ' | ' ) : '' ) );
	await browser.close();
	console.log( fails ? `\n${ fails } FAILED, ${ passes } passed` : `\nALL ${ passes } PASSED` );
	process.exit( fails ? 1 : 0 );
} )().catch( ( e ) => { console.error( e ); process.exit( 2 ); } );
