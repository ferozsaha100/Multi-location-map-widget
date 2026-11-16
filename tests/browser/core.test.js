/**
 * Real-browser tests for the Office Map runtime (Leaflet bundled in the plugin,
 * mocked Google Maps). Usage: node tests/browser/core.test.js <plugin-dir> <frag-dir> <shots-dir>
 */
'use strict';
const fs = require( 'fs' );
const path = require( 'path' );
const { chromium } = require( 'playwright' );

const [ , , pluginDir, fragDir, shotDir ] = process.argv;
const BASE = 'http://127.0.0.1:8765/';
let fails = 0, passes = 0;
function check( cond, label ) {
	if ( cond ) { passes++; console.log( 'PASS ' + label ); } else { fails++; console.log( 'FAIL ' + label ); }
}

const GOOGLE_MOCK = `
<script>
(function(){
  function LatLng(a,b){ this.a=a; this.b=b; } LatLng.prototype.lat=function(){return this.a}; LatLng.prototype.lng=function(){return this.b};
  function LatLngBounds(){ this.s=90; this.n=-90; this.w=180; this.e=-180; }
  LatLngBounds.prototype.extend=function(ll){ this.s=Math.min(this.s,ll.lat()); this.n=Math.max(this.n,ll.lat()); this.w=Math.min(this.w,ll.lng()); this.e=Math.max(this.e,ll.lng()); };
  LatLngBounds.prototype.contains=function(ll){ return ll.lat()>=this.s&&ll.lat()<=this.n&&ll.lng()>=this.w&&ll.lng()<=this.e; };
  function Map(el,opts){ this.el=el; this.opts=opts; this.z=opts.zoom; this.l={}; el.style.position='relative'; el.style.background='#e8eaed'; window.__gmap=this; }
  Map.prototype.fitBounds=function(b){ this.b=b; this.redraw(); };
  Map.prototype.getBounds=function(){ return this.b; };
  Map.prototype.getZoom=function(){ return this.z; }; Map.prototype.setZoom=function(z){ this.z=z; };
  Map.prototype.setCenter=function(){}; Map.prototype.panTo=function(){}; Map.prototype.panBy=function(x,y){window.__panBy=[x,y];};
  Map.prototype.addListener=function(ev,fn){ this.el.addEventListener(ev,function(e){ if(e.target===this) fn(); }.bind(this.el)); };
  Map.prototype.redraw=function(){ (this.ov||[]).forEach(function(o){ o.draw(); }); };
  function OverlayView(){}
  OverlayView.preventMapHitsAndGesturesFrom=function(){ window.__gmPrevented=(window.__gmPrevented||0)+1; };
  OverlayView.prototype.setMap=function(map){ this.map=map; (map.ov=map.ov||[]).push(this); this.pane=document.createElement('div'); this.pane.className='gm-pane'; this.pane.style.cssText='position:absolute;inset:0;'; map.el.appendChild(this.pane); this.onAdd(); this.draw(); };
  OverlayView.prototype.getPanes=function(){ return { overlayMouseTarget: this.pane }; };
  OverlayView.prototype.getProjection=function(){ var m=this.map, b=m.b; if(!b) return null; var W=m.el.clientWidth, H=m.el.clientHeight, P=56;
    return { fromLatLngToDivPixel:function(ll){ return { x: P+(ll.lng()-b.w)/((b.e-b.w)||1)*(W-2*P), y: P+(b.n-ll.lat())/((b.n-b.s)||1)*(H-2*P) }; } }; };
  window.google={ maps:{ LatLng:LatLng, LatLngBounds:LatLngBounds, Map:Map, OverlayView:OverlayView, event:{ addListenerOnce:function(m,ev,fn){ setTimeout(fn,0); } } } };
})();
</script>`;

function page( frag, extraHead, spacer ) {
	return `<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="stylesheet" href="${ BASE }plugin/assets/css/ave-office-map.css">
<style>body{margin:0;padding:24px;font-family:"Liberation Sans",Arial,sans-serif;background:#fafafa} .elementor-widget{max-width:1100px;margin:0 auto}</style>${ extraHead || '' }
</head><body>${ spacer ? '<div style="height:2600px">spacer</div>' : '' }<div class="elementor-widget">${ frag }</div>
<script src="${ BASE }plugin/assets/js/ave-office-map.js"></script></body></html>`;
}

function tileSvg( url ) {
	const m = url.match( /\/(\d+)\/(\d+)\/(\d+)(@2x)?\.png/ ) || [ 0, 0, 0, 0 ];
	const x = +m[ 2 ], y = +m[ 3 ];
	const water = ( x * 7 + y * 3 ) % 11 === 0;
	return `<svg xmlns="http://www.w3.org/2000/svg" width="256" height="256"><rect width="256" height="256" fill="${ water ? '#d4e4f1' : '#f2f1ee' }"/>
<g stroke="#ffffff" stroke-width="6"><path d="M0 ${ 40 + ( x % 5 ) * 30 } H256"/><path d="M${ 50 + ( y % 4 ) * 40 } 0 V256"/></g>
<g stroke="#e2e0da" stroke-width="2"><path d="M0 ${ 200 - ( y % 3 ) * 20 } H256"/><path d="M${ 180 - ( x % 3 ) * 30 } 0 V256"/></g></svg>`;
}

( async () => {
	const browser = await chromium.launch();
	const ctx = await browser.newContext( { viewport: { width: 1200, height: 760 }, deviceScaleFactor: 1 } );
	const tileHits = [];
	await ctx.route( '**/*', async ( route ) => {
		const url = route.request().url();
		if ( url.startsWith( BASE + 'pages/' ) ) {
			const name = url.slice( ( BASE + 'pages/' ).length ).split( '?' )[ 0 ];
			const [ frag, mock ] = name.split( '~' );
			const html = page( fs.readFileSync( path.join( fragDir, frag + '.frag.html' ), 'utf8' ), mock === 'google' ? GOOGLE_MOCK : '', mock === 'spacer' );
			return route.fulfill( { contentType: 'text/html', body: html } );
		}
		if ( url.startsWith( BASE + 'plugin/' ) ) {
			const file = path.join( pluginDir, url.slice( ( BASE + 'plugin/' ).length ).split( '?' )[ 0 ] );
			const type = file.endsWith( '.js' ) ? 'application/javascript' : file.endsWith( '.css' ) ? 'text/css' : 'image/png';
			return fs.existsSync( file ) ? route.fulfill( { contentType: type, body: fs.readFileSync( file ) } ) : route.fulfill( { status: 404 } );
		}
		if ( /basemaps\.cartocdn\.com/.test( url ) ) {
			tileHits.push( url );
			return route.fulfill( { contentType: 'image/svg+xml', body: tileSvg( url ) } );
		}
		return route.abort();
	} );

	const errors = [];
	async function open( name, viewport ) {
		const p = await ctx.newPage();
		if ( viewport ) await p.setViewportSize( viewport );
		p.on( 'pageerror', ( e ) => errors.push( name + ': ' + e.message ) );
		p.on( 'console', ( m ) => { if ( 'error' === m.type() ) errors.push( name + ' console: ' + m.text() ); } );
		await p.goto( BASE + 'pages/' + name );
		return p;
	}
	const popupBox = ( p ) => p.$eval( '.ave-om-pop', ( e ) => { const r = e.getBoundingClientRect(); return { l: r.left, t: r.top, r: r.right, b: r.bottom }; } );
	const mapBox = ( p ) => p.$eval( '.ave-om__map', ( e ) => { const r = e.getBoundingClientRect(); return { l: r.left, t: r.top, r: r.right, b: r.bottom }; } );

	/* ---------- Layout 1: popup, 45 pins ---------- */
	let p = await open( 'popup-45' );
	await p.waitForSelector( '.ave-om--ready', { timeout: 8000 } );
	await p.waitForTimeout( 400 );
	check( 45 === await p.locator( '.ave-om-pin' ).count(), 'L1: 45 pins drawn on the Leaflet map' );
	check( 0 === await p.locator( '.ave-om-pop' ).count(), 'L1: no popup until hover' );
	check( tileHits.length > 0 && tileHits.every( ( u ) => /\/light_all\/\d+\/\d+\/\d+(@2x)?\.png\?key=CARTO123$/.test( u ) ), 'L1: CARTO tiles requested with the key (' + tileHits.length + ' tiles)' );

	// Every pin is inside the map viewport after fit.
	const outside = await p.$$eval( '.ave-om-pin__marker', ( els ) => {
		const m = document.querySelector( '.ave-om__map' ).getBoundingClientRect();
		return els.filter( ( e ) => { const r = e.getBoundingClientRect(); return r.left < m.left || r.right > m.right || r.top < m.top || r.bottom > m.bottom; } ).length;
	} );
	check( 0 === outside, 'L1: auto-fit shows all 45 pins inside the map' );

	const marker = p.locator( '.ave-om-pin__marker' ).nth( 6 );
	await marker.hover();
	await p.waitForTimeout( 80 );
	const popText = await p.locator( '.ave-om-pop' ).innerText();
	check( 1 === await p.locator( '.ave-om-pop' ).count() && /Lakewood Ranch/.test( popText ) && /FL/.test( popText ) && /View Details/.test( popText ), 'L1: hover opens popup with name, address and button' );
	check( 'true' === await marker.getAttribute( 'aria-expanded' ), 'L1: pin reports aria-expanded=true' );

	// Move from the pin up onto the popup button: popup must stay open.
	const btn = p.locator( '.ave-om-pop .ave-om__btn' );
	const bb = await btn.boundingBox();
	await p.mouse.move( bb.x + bb.width / 2, bb.y + bb.height / 2, { steps: 12 } );
	await p.waitForTimeout( 450 );
	check( 1 === await p.locator( '.ave-om-pop' ).count(), 'L1: popup stays open while the cursor travels to its button' );
	const hit = await p.evaluate( ( [ x, y ] ) => { const e = document.elementFromPoint( x, y ); return e && e.className; }, [ bb.x + bb.width / 2, bb.y + bb.height / 2 ] );
	check( /ave-om__btn/.test( hit ), 'L1: the popup button is on top and clickable (hit-test: ' + hit + ')' );
	await btn.click();
	check( '#office-7' === await p.evaluate( () => location.hash ), 'L1: clicking the popup button follows its link' );
	await p.screenshot( { path: path.join( shotDir, 'layout1-popup-hover.png' ) } );

	// Leave to an empty part of the page: popup closes after the delay.
	await p.mouse.move( 5, 5, { steps: 5 } );
	await p.waitForTimeout( 450 );
	check( 0 === await p.locator( '.ave-om-pop' ).count(), 'L1: popup closes after the cursor leaves' );

	// Edge handling: hover every pin, popup must stay inside the map box.
	let escaped = 0;
	const n = await p.locator( '.ave-om-pin__marker' ).count();
	for ( let i = 0; i < n; i++ ) {
		await p.locator( '.ave-om-pin__marker' ).nth( i ).hover( { force: true } );
		const pb = await popupBox( p ), mb = await mapBox( p );
		if ( pb.l < mb.l - 1 || pb.r > mb.r + 1 || pb.t < mb.t - 1 || pb.b > mb.b + 1 ) escaped++;
	}
	check( 0 === escaped, 'L1: for all 45 pins the popup stays inside the map (flips below / shifts sideways at edges)' );
	await p.mouse.move( 5, 5 );
	await p.waitForTimeout( 350 );

	// Tap behaviour: click pins sticky-open; map click closes.
	await p.locator( '.ave-om-pin__marker' ).nth( 2 ).click( { force: true } );
	await p.mouse.move( 5, 5 );
	await p.waitForTimeout( 450 );
	check( 1 === await p.locator( '.ave-om-pop' ).count(), 'L1: click/tap keeps the popup open after the cursor leaves' );
	const mb = await mapBox( p );
	await p.mouse.click( mb.l + 12, mb.b - 40 );
	await p.waitForTimeout( 100 );
	check( 0 === await p.locator( '.ave-om-pop' ).count(), 'L1: clicking the map closes it' );

	// Keyboard: focus a pin → popup; Escape → closed, focus stays on the pin.
	await p.locator( '.ave-om-pin__marker' ).nth( 0 ).focus();
	await p.waitForTimeout( 50 );
	const focusedOpen = await p.locator( '.ave-om-pop' ).count();
	await p.keyboard.press( 'Escape' );
	await p.waitForTimeout( 50 );
	const afterEsc = await p.locator( '.ave-om-pop' ).count();
	const stillFocused = await p.evaluate( () => document.activeElement && document.activeElement.classList.contains( 'ave-om-pin__marker' ) );
	check( 1 === focusedOpen && 0 === afterEsc && stillFocused, 'L1: keyboard focus opens the popup, Esc closes it and keeps focus on the pin' );
	await p.close();

	/* ---------- Layout 2: list, 45 ---------- */
	p = await open( 'list-45' );
	await p.waitForSelector( '.ave-om--ready', { timeout: 8000 } );
	await p.waitForTimeout( 300 );
	check( 45 === await p.locator( '.ave-om-pin' ).count() && 45 === await p.locator( '.ave-om__item' ).count(), 'L2: 45 pins and 45 list items' );
	const labels = await p.$$eval( '.ave-om-pin__label', ( e ) => e.map( ( x ) => x.textContent ) );
	check( '1' === labels[ 0 ] && '45' === labels[ 44 ], 'L2: pins are numbered 1–45 to match the list' );
	const geo = await p.evaluate( () => {
		const l = document.querySelector( '.ave-om__list' ).getBoundingClientRect(), m = document.querySelector( '.ave-om__map' ).getBoundingClientRect();
		return { listLeft: l.left, listRight: l.right, mapLeft: m.left, listW: l.width, listH: l.height, mapH: m.height };
	} );
	check( geo.listRight <= geo.mapLeft + 1 && Math.round( geo.listW ) === 340 && Math.abs( geo.listH - geo.mapH ) < 2, 'L2: list sits on the left (340px), same height as the map' );

	await p.locator( '.ave-om__item' ).nth( 9 ).hover();
	await p.waitForTimeout( 80 );
	check( await p.locator( '.ave-om-pin' ).nth( 9 ).evaluate( ( e ) => e.classList.contains( 'is-active' ) ) && /Bradenton/.test( await p.locator( '.ave-om-pop' ).innerText() ), 'L2: hovering list item 10 highlights pin 10 and opens its popup' );
	await p.screenshot( { path: path.join( shotDir, 'layout2-list-hover.png' ) } );

	await p.locator( '.ave-om-pin__marker' ).nth( 40 ).hover( { force: true } );
	await p.waitForTimeout( 120 );
	const sync = await p.evaluate( () => {
		const list = document.querySelector( '.ave-om__list' ), item = document.querySelectorAll( '.ave-om__item' )[ 40 ];
		const l = list.getBoundingClientRect(), r = item.getBoundingClientRect();
		return { active: item.classList.contains( 'is-active' ), visible: r.top >= l.top - 1 && r.bottom <= l.bottom + 1, scrolled: list.scrollTop, pageY: window.scrollY };
	} );
	check( sync.active && sync.visible && sync.scrolled > 0 && 0 === sync.pageY, 'L2: hovering pin 41 highlights item 41 and scrolls only the list to it (list scrollTop ' + sync.scrolled + ', page did not scroll)' );

	await p.locator( '.ave-om__item' ).nth( 3 ).locator( '.ave-om__select' ).click();
	await p.mouse.move( 5, 5 );
	await p.waitForTimeout( 450 );
	check( 1 === await p.locator( '.ave-om-pop' ).count() && await p.locator( '.ave-om__item' ).nth( 3 ).evaluate( ( e ) => e.classList.contains( 'is-active' ) ), 'L2: clicking an office keeps it selected and its popup open' );
	await p.locator( '.ave-om__item' ).nth( 0 ).locator( '.ave-om__btn' ).click();
	check( '#office-1' === await p.evaluate( () => location.hash ), 'L2: list button follows its link' );
	await p.close();

	/* ---------- Mobile ---------- */
	p = await open( 'list-45', { width: 390, height: 844 } );
	await p.waitForSelector( '.ave-om--ready', { timeout: 8000 } );
	const mob = await p.evaluate( () => {
		const l = document.querySelector( '.ave-om__list' ).getBoundingClientRect(), m = document.querySelector( '.ave-om__map' ).getBoundingClientRect();
		return { listTop: l.top, mapBottom: m.bottom, listW: l.width, mapW: m.width, overflow: document.documentElement.scrollWidth > window.innerWidth };
	} );
	check( mob.listTop >= mob.mapBottom - 1 && Math.abs( mob.listW - mob.mapW ) < 2 && ! mob.overflow, 'M1: on a 390px phone the list stacks under the map, full width, no sideways scroll' );
	await p.screenshot( { path: path.join( shotDir, 'layout2-mobile.png' ), fullPage: false } );
	await p.close();

	/* ---------- 120 offices ---------- */
	p = await open( 'list-120' );
	await p.waitForSelector( '.ave-om--ready', { timeout: 8000 } );
	check( 120 === await p.locator( '.ave-om-pin' ).count() && 120 === await p.locator( '.ave-om__item' ).count(), 'X1: 120 offices → 120 pins and 120 list items' );
	await p.close();

	/* ---------- Single office ---------- */
	p = await open( 'popup-1' );
	await p.waitForSelector( '.ave-om--ready', { timeout: 8000 } );
	const z = await p.evaluate( () => document.querySelector( '.ave-om__map' ).querySelector( '.leaflet-tile' ).src.match( /\/(\d+)\/\d+\/\d+/ )[ 1 ] );
	check( 1 === await p.locator( '.ave-om-pin' ).count() && '15' === z, 'X2: one office → one pin at the "closest zoom" setting (15)' );
	await p.close();

	/* ---------- No CARTO key ---------- */
	p = await open( 'nokey-list-5' );
	await p.waitForTimeout( 500 );
	const nk = await p.evaluate( () => ( { map: getComputedStyle( document.querySelector( '.ave-om__map' ) ).display, items: document.querySelectorAll( '.ave-om__item' ).length, leaflet: !! window.L } ) );
	check( 'none' === nk.map && 5 === nk.items && ! nk.leaflet, 'X3: no CARTO key → no broken map, list still shown, Leaflet not even loaded' );
	await p.close();

	/* ---------- Lazy load ---------- */
	const before = tileHits.length;
	p = await open( 'popup-45~spacer' );
	await p.waitForTimeout( 600 );
	const early = await p.evaluate( () => ( { L: !! window.L, ready: !! document.querySelector( '.ave-om--ready' ) } ) );
	await p.evaluate( () => document.querySelector( '.ave-om' ).scrollIntoView() );
	await p.waitForSelector( '.ave-om--ready', { timeout: 8000 } );
	check( ! early.L && ! early.ready && tileHits.length > before, 'X4: map below the fold loads nothing until scrolled near, then draws' );
	await p.close();

	/* ---------- Google engine (mocked google.maps) ---------- */
	p = await open( 'google-list-45~google' );
	await p.waitForSelector( '.ave-om--ready', { timeout: 8000 } );
	const g = await p.evaluate( () => ( {
		anchors: document.querySelectorAll( '.gm-pane .ave-om-anchor--google' ).length,
		positioned: Array.from( document.querySelectorAll( '.gm-pane .ave-om-anchor--google' ) ).filter( ( a ) => a.style.left && a.style.top ).length,
		prevented: window.__gmPrevented,
		styles: window.__gmap.opts.styles.length,
		gesture: window.__gmap.opts.gestureHandling,
	} ) );
	check( 45 === g.anchors && 45 === g.positioned && 45 === g.prevented, 'G1: Google engine draws 45 HTML pins in the overlay, map gestures blocked on pins' );
	check( g.styles > 5 && 'cooperative' === g.gesture, 'G1: Silver style applied, cooperative gestures (scroll zoom off)' );
	await p.locator( '.ave-om__item' ).nth( 4 ).hover();
	await p.waitForTimeout( 80 );
	check( /Sarasota/.test( await p.locator( '.ave-om-pop' ).innerText() ) && await p.locator( '.ave-om-pin' ).nth( 4 ).evaluate( ( e ) => e.classList.contains( 'is-active' ) ), 'G2: list ↔ pin sync works on the Google engine too' );
	await p.close();

	check( 0 === errors.length, 'No page errors or console errors' + ( errors.length ? ': ' + errors.join( ' | ' ) : '' ) );

	await browser.close();
	console.log( fails ? `\n${ fails } FAILED, ${ passes } passed` : `\nALL ${ passes } PASSED` );
	process.exit( fails ? 1 : 0 );
} )().catch( ( e ) => { console.error( e ); process.exit( 2 ); } );
