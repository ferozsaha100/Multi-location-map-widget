/**
 * 2.3.0: pin/list number toggles in a real browser.
 * Usage: node tests/browser/numbers.test.js <plugin-dir> <frag-dir> <shots-dir>
 */
'use strict';
const fs = require( 'fs' );
const path = require( 'path' );
const { chromium } = require( 'playwright' );
const [ , , pluginDir, fragDir, shotDir ] = process.argv;
const BASE = 'http://127.0.0.1:8765/';
let fails = 0, passes = 0;
const check = ( c, l ) => { if ( c ) { passes++; console.log( 'PASS ' + l ); } else { fails++; console.log( 'FAIL ' + l ); } };
const page = ( frag ) => `<!doctype html><html><head><meta charset="utf-8"><link rel="stylesheet" href="${ BASE }plugin/assets/css/ave-office-map.css">
<style>body{margin:0;padding:20px;font-family:"Liberation Sans",Arial,sans-serif;background:#fafafa}.w{max-width:900px}.ave-om{--ave-om-height:340px}</style></head>
<body><div class="w">${ frag }</div><script src="${ BASE }plugin/assets/js/ave-office-map.js"></script></body></html>`;
( async () => {
	const browser = await chromium.launch();
	const ctx = await browser.newContext( { viewport: { width: 960, height: 400 } } );
	await ctx.route( '**/*', ( route ) => {
		const url = route.request().url();
		if ( url.startsWith( BASE + 'pages/' ) ) return route.fulfill( { contentType: 'text/html', body: page( fs.readFileSync( path.join( fragDir, url.slice( ( BASE + 'pages/' ).length ) + '.frag.html' ), 'utf8' ) ) } );
		if ( url.startsWith( BASE + 'plugin/' ) ) { const f = path.join( pluginDir, url.slice( ( BASE + 'plugin/' ).length ).split( '?' )[ 0 ] ); return route.fulfill( { contentType: f.endsWith( '.js' ) ? 'application/javascript' : 'text/css', body: fs.readFileSync( f ) } ); }
		if ( /basemaps\.cartocdn\.com/.test( url ) ) return route.fulfill( { contentType: 'image/svg+xml', body: '<svg xmlns="http://www.w3.org/2000/svg" width="256" height="256"><rect width="256" height="256" fill="#f2f1ee"/><path d="M0 90H256M120 0V256" stroke="#fff" stroke-width="6"/></svg>' } );
		return route.abort();
	} );
	const errors = [];
	for ( const [ pins, list ] of [ [ 1, 1 ], [ 0, 1 ], [ 1, 0 ], [ 0, 0 ] ] ) {
		const name = `nums-${ pins ? 'pins' : 'nopins' }-${ list ? 'list' : 'nolist' }`;
		const p = await ctx.newPage();
		p.on( 'pageerror', ( e ) => errors.push( name + ': ' + e.message ) );
		await p.goto( BASE + 'pages/' + name );
		await p.waitForSelector( '.ave-om--ready', { timeout: 8000 } );
		const r = await p.evaluate( () => {
			const labels = Array.from( document.querySelectorAll( '.ave-om-pin__label' ) );
			const item = document.querySelector( '.ave-om__item' ), text = item.querySelector( '.ave-om__text' );
			return {
				pinText: labels.map( ( l ) => l.textContent ).join( ',' ),
				dots: labels.filter( ( l ) => l.classList.contains( 'ave-om-pin__label--dot' ) ).length,
				badges: document.querySelectorAll( '.ave-om__num' ).length,
				textInset: Math.round( text.getBoundingClientRect().left - item.getBoundingClientRect().left ),
			};
		} );
		check( pins ? '1,2,3,4,5,6' === r.pinText && 0 === r.dots : '' === r.pinText.replace( /,/g, '' ) && 6 === r.dots, `${ name }: pins ${ pins ? 'numbered 1–6' : 'show dots' }` );
		check( list ? 6 === r.badges && r.textInset > 40 : 0 === r.badges && r.textInset === 18, `${ name }: list ${ list ? 'has number badges' : 'has no badges and text starts at the padding (' + r.textInset + 'px)' }` );
		await p.locator( '.ave-om__item' ).nth( 1 ).hover();
		await p.waitForTimeout( 80 );
		check( await p.locator( '.ave-om-pin' ).nth( 1 ).evaluate( ( e ) => e.classList.contains( 'is-active' ) ), `${ name }: list ↔ pin highlight still works` );
		await p.screenshot( { path: path.join( shotDir, name + '.png' ) } );
		await p.close();
	}
	check( 0 === errors.length, 'No page errors' + ( errors.length ? ': ' + errors.join( ' | ' ) : '' ) );
	await browser.close();
	console.log( fails ? `\n${ fails } FAILED, ${ passes } passed` : `\nALL ${ passes } PASSED` );
	process.exit( fails ? 1 : 0 );
} )().catch( ( e ) => { console.error( e ); process.exit( 2 ); } );
