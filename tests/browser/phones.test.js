/**
 * Browser checks for 2.1.0 phone numbers: label geometry for every position ×
 * style, tap-to-call links, phones inside popups, plus a comparison sheet.
 * Usage: node tests/browser/phones.test.js <plugin-dir> <frag-dir> <shots-dir>
 */
'use strict';
const fs = require( 'fs' );
const path = require( 'path' );
const { chromium } = require( 'playwright' );

const [ , , pluginDir, fragDir, shotDir ] = process.argv;
const BASE = 'http://127.0.0.1:8765/';
let fails = 0, passes = 0;
const check = ( c, l ) => { if ( c ) { passes++; console.log( 'PASS ' + l ); } else { fails++; console.log( 'FAIL ' + l ); } };

const page = ( frag ) => `<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="stylesheet" href="${ BASE }plugin/assets/css/ave-office-map.css">
<style>body{margin:0;padding:24px;font-family:"Liberation Sans",Arial,sans-serif;background:#fafafa}.w{max-width:1100px;margin:0 auto}</style>
</head><body><div class="w">${ frag }</div><script src="${ BASE }plugin/assets/js/ave-office-map.js"></script></body></html>`;

const tile = () => '<svg xmlns="http://www.w3.org/2000/svg" width="256" height="256"><rect width="256" height="256" fill="#f2f1ee"/><path d="M0 90H256M120 0V256" stroke="#fff" stroke-width="6"/></svg>';

( async () => {
	const browser = await chromium.launch();
	const ctx = await browser.newContext( { viewport: { width: 1200, height: 760 } } );
	await ctx.route( '**/*', ( route ) => {
		const url = route.request().url();
		if ( url.startsWith( BASE + 'pages/' ) ) {
			return route.fulfill( { contentType: 'text/html', body: page( fs.readFileSync( path.join( fragDir, url.slice( ( BASE + 'pages/' ).length ) + '.frag.html' ), 'utf8' ) ) } );
		}
		if ( url.startsWith( BASE + 'sheet' ) ) {
			return route.fulfill( { contentType: 'text/html', body: fs.readFileSync( path.join( shotDir, 'sheet.html' ), 'utf8' ) } );
		}
		if ( url.startsWith( BASE + 'shots/' ) ) {
			return route.fulfill( { contentType: 'image/png', body: fs.readFileSync( path.join( shotDir, url.slice( ( BASE + 'shots/' ).length ) ) ) } );
		}
		if ( url.startsWith( BASE + 'plugin/' ) ) {
			const f = path.join( pluginDir, url.slice( ( BASE + 'plugin/' ).length ).split( '?' )[ 0 ] );
			return fs.existsSync( f ) ? route.fulfill( { contentType: f.endsWith( '.js' ) ? 'application/javascript' : 'text/css', body: fs.readFileSync( f ) } ) : route.fulfill( { status: 404 } );
		}
		if ( /basemaps\.cartocdn\.com/.test( url ) ) return route.fulfill( { contentType: 'image/svg+xml', body: tile() } );
		return route.abort();
	} );

	const errors = [];
	const geometry = ( scope ) => scope.evaluate( ( root ) => {
		const ph = root.querySelector( '.ave-om-phone' );
		const n = ph.querySelector( '.ave-om-phone__number' ).getBoundingClientRect();
		const l = ph.querySelector( '.ave-om-phone__label' ).getBoundingClientRect();
		const i = ph.querySelector( '.ave-om-phone__icon' ).getBoundingClientRect();
		const nm = ph.querySelector( '.ave-om-phone__name' );
		const a = nm ? nm.getBoundingClientRect() : null;
		return { n: { l: n.left, r: n.right, t: n.top, b: n.bottom }, l: { l: l.left, r: l.right, t: l.top, b: l.bottom, h: l.height }, icon: { t: i.top, b: i.bottom, w: i.width, r: i.right }, name: a && { l: a.left, t: a.top, b: a.bottom, text: nm.textContent } };
	} );
	const rules = {
		'right-top': ( g ) => g.l.l >= g.n.r - 1 && g.l.t <= g.n.t + 1 && g.l.b < g.n.b - 2,
		'right-bottom': ( g ) => g.l.l >= g.n.r - 1 && g.l.b >= g.n.b - 4 && g.l.t > g.n.t + 2,
		above: ( g ) => g.l.b <= g.n.t + 1 && Math.abs( g.l.l - g.n.l ) <= 1,
		below: ( g ) => g.l.t >= g.n.b - 1 && Math.abs( g.l.l - g.n.l ) <= 1,
	};

	const crops = [];
	for ( const pos of Object.keys( rules ) ) {
		for ( const sty of [ 'pill', 'text' ] ) {
			const p = await ctx.newPage();
			p.on( 'pageerror', ( e ) => errors.push( pos + sty + ': ' + e.message ) );
			await p.goto( BASE + `pages/phones-${ pos }-${ sty }` );
			await p.waitForSelector( '.ave-om--ready', { timeout: 8000 } );
			const item = await p.$( '.ave-om__item' );
			const g = await geometry( item );
			check( rules[ pos ]( g ), `L-${ pos }-${ sty }: list label placed ${ pos } of the number ${ JSON.stringify( { n: g.n, l: g.l } ) }` );
			const iconOk = g.icon.w > 10 && ( 'above' === pos ? Math.abs( g.icon.b - g.n.b ) <= 5 : Math.abs( g.icon.t - g.n.t ) <= 5 );
			check( iconOk, `L-${ pos }-${ sty }: icon lines up with the number (not the name)` );
			const top = Math.min( g.n.t, g.l.t );
			check( g.name && 'Jonathon Kyes' === g.name.text && g.name.b <= top + 1 && Math.abs( g.name.l - g.n.l ) <= 1 && g.icon.t >= g.name.b - 1, `L-${ pos }-${ sty }: name sits above the number, left-aligned with it ${ JSON.stringify( g.name ) }` );
			if ( 'pill' === sty ) {
				const bg = await item.$eval( '.ave-om-phone__label', ( e ) => getComputedStyle( e ).backgroundColor );
				const rowBg = await item.evaluate( ( e ) => getComputedStyle( e ).backgroundColor );
				check( 'rgba(11, 34, 199, 0.08)' === bg && bg !== rowBg, `L-${ pos }-pill: pill tint differs from the highlighted row (${ bg } on ${ rowBg })` );
			}

			await p.locator( '.ave-om-pin__marker' ).nth( 0 ).hover( { force: true } );
			await p.waitForTimeout( 80 );
			const pop = await p.$( '.ave-om-pop' );
			const pg = await geometry( pop );
			check( rules[ pos ]( pg ) && pg.name && pg.name.b <= Math.min( pg.n.t, pg.l.t ) + 1 && Math.abs( pg.name.l - pg.n.l ) <= 1, `P-${ pos }-${ sty }: popup → name above, role label ${ pos } of the number` );

			fs.mkdirSync( path.join( shotDir, 'crops' ), { recursive: true } );
			const file = `crops/${ pos }-${ sty }.png`;
			await item.screenshot( { path: path.join( shotDir, file ) } );
			crops.push( { pos, sty, file } );
			await p.close();
		}
	}

	// Popup content + tap-to-call on the default layout.
	const p = await ctx.newPage();
	p.on( 'pageerror', ( e ) => errors.push( 'popup: ' + e.message ) );
	await p.goto( BASE + 'pages/popup-45' );
	await p.waitForSelector( '.ave-om--ready', { timeout: 8000 } );
	await p.locator( '.ave-om-pin__marker' ).nth( 4 ).hover( { force: true } );
	await p.waitForTimeout( 80 );
	const info = await p.$eval( '.ave-om-pop', ( pop ) => ( {
		phones: Array.from( pop.querySelectorAll( '.ave-om-phone' ) ).map( ( r ) => ( { name: ( r.querySelector( '.ave-om-phone__name' ) || {} ).textContent || '', num: r.querySelector( 'a' ).textContent, href: r.querySelector( 'a' ).getAttribute( 'href' ), label: ( r.querySelector( '.ave-om-phone__label' ) || {} ).textContent || '' } ) ),
		people: pop.querySelector( '.ave-om-phones' ).classList.contains( 'ave-om-phones--people' ),
		order: Array.from( pop.children ).map( ( c ) => c.className ),
	} ) );
	check( 2 === info.phones.length && 'Jonathon Kyes' === info.phones[ 0 ].name && 'Office Manager' === info.phones[ 0 ].label && 'tel:7275550104' === info.phones[ 0 ].href && '' === info.phones[ 1 ].name && 'Front Desk' === info.phones[ 1 ].label && info.people, 'POP: popup shows name, number (tel: link) and role; entry without a name shows number + role ' + JSON.stringify( info.phones ) );
	check( 'ave-om-pop__name|ave-om-pop__address|ave-om-phones ave-om-phones--people|ave-om__btn' === info.order.join( '|' ), 'POP: order is name, address, phones, button' );
	const inside = await p.evaluate( () => { const m = document.querySelector( '.ave-om__map' ).getBoundingClientRect(), r = document.querySelector( '.ave-om-pop' ).getBoundingClientRect(); return r.top >= m.top - 1 && r.bottom <= m.bottom + 1; } );
	check( inside, 'POP: taller popup (with phones) still fits inside the map' );
	const stack = await p.$eval( '.ave-om-pop .ave-om-phone', ( r ) => { const nm = r.querySelector( '.ave-om-phone__name' ).getBoundingClientRect(), n = r.querySelector( 'a' ).getBoundingClientRect(), l = r.querySelector( '.ave-om-phone__label' ).getBoundingClientRect(); return nm.bottom <= n.top + 1 && l.top >= n.bottom - 1 && Math.abs( nm.left - n.left ) <= 1 && Math.abs( l.left - n.left ) <= 1; } );
	check( stack, 'POP: default layout reads Jonathon Kyes / (727) 555-0104 / OFFICE MANAGER, stacked and left-aligned' );
	const gap = await p.$eval( '.ave-om-pop .ave-om-phones', ( w ) => getComputedStyle( w ).rowGap );
	check( '12px' === gap, 'POP: entries with names get 12px between them (got ' + gap + ')' );
	const pb = await p.locator( '.ave-om-pop .ave-om-phone__number' ).nth( 1 ).boundingBox();
	await p.mouse.move( pb.x + 5, pb.y + pb.height / 2, { steps: 10 } );
	await p.waitForTimeout( 400 );
	const hit = await p.evaluate( ( [ x, y ] ) => document.elementFromPoint( x, y ).className, [ pb.x + 5, pb.y + pb.height / 2 ] );
	check( 1 === await p.locator( '.ave-om-pop' ).count() && /ave-om-phone__number/.test( hit ), 'POP: cursor can reach the second number; popup stays open and the link is clickable' );
	await p.screenshot( { path: path.join( shotDir, 'popup-phones.png' ), clip: await p.$eval( '.ave-om-pop', ( e ) => { const r = e.getBoundingClientRect(); return { x: r.left - 30, y: r.top - 30, width: r.width + 60, height: r.height + 90 }; } ) } );

	// Single-office list click must not fire when tapping a phone link.
	await p.close();
	const l = await ctx.newPage();
	await l.goto( BASE + 'pages/list-45' );
	await l.waitForSelector( '.ave-om--ready', { timeout: 8000 } );
	await l.evaluate( () => document.querySelectorAll( '.ave-om__item a.ave-om-phone__number' ).forEach( ( a ) => a.addEventListener( 'click', ( e ) => { e.preventDefault(); window.__called = a.getAttribute( 'href' ); } ) ) );
	await l.locator( '.ave-om__item' ).nth( 2 ).locator( '.ave-om-phone__number' ).first().click();
	const st = await l.evaluate( () => ( { called: window.__called, sticky: document.querySelector( '.ave-om' ).aveOfficeMap.sticky } ) );
	check( 'tel:7275550102' === st.called && false === st.sticky, 'LIST: tapping a phone number dials it and does not lock the office selection ' + JSON.stringify( st ) );
	await l.close();

	check( 0 === errors.length, 'No page errors' + ( errors.length ? ': ' + errors.join( ' | ' ) : '' ) );

	// Comparison sheet of all 8 variants.
	const cell = ( c ) => `<figure><img src="${ BASE }shots/${ c.file }"><figcaption>${ { 'right-top': 'Right of number, top', 'right-bottom': 'Right of number, bottom', above: 'Above number', below: 'Below number' }[ c.pos ] } · ${ 'pill' === c.sty ? 'Pill' : 'Small caps text' }</figcaption></figure>`;
	fs.writeFileSync( path.join( shotDir, 'sheet.html' ), `<!doctype html><html><head><meta charset="utf-8"><style>body{margin:0;padding:24px;background:#f3f4f6;font:13px/1.4 "Liberation Sans",Arial,sans-serif;color:#374151}
.g{display:grid;grid-template-columns:repeat(2,340px);gap:18px 24px}figure{margin:0}img{display:block;width:340px;border:1px solid #e5e7eb;background:#fff}figcaption{margin-top:6px;font-weight:600}</style></head>
<body><div class="g">${ crops.map( cell ).join( '' ) }</div></body></html>` );
	const s = await ctx.newPage();
	await s.setViewportSize( { width: 780, height: 400 } );
	await s.goto( BASE + 'sheet' );
	await s.waitForTimeout( 300 );
	await s.screenshot( { path: path.join( shotDir, 'phone-label-options.png' ), fullPage: true } );

	await browser.close();
	console.log( fails ? `\n${ fails } FAILED, ${ passes } passed` : `\nALL ${ passes } PASSED` );
	process.exit( fails ? 1 : 0 );
} )().catch( ( e ) => { console.error( e ); process.exit( 2 ); } );
