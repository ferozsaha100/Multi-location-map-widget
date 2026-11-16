/**
 * 2.4.0: per-office pin colour and custom pin icon/logo, in a real browser.
 * Elementor's generated {{CURRENT_ITEM}} CSS is simulated with an inline rule.
 * Usage: node tests/browser/pins.test.js <plugin-dir> <frag-dir> <shots-dir>
 */
'use strict';
const fs = require( 'fs' );
const path = require( 'path' );
const { chromium } = require( 'playwright' );
const [ , , pluginDir, fragDir, shotDir ] = process.argv;
const BASE = 'http://127.0.0.1:8765/';
let fails = 0, passes = 0;
const check = ( c, l ) => { if ( c ) { passes++; console.log( 'PASS ' + l ); } else { fails++; console.log( 'FAIL ' + l ); } };

// What Elementor would write for the two offices given their own colour.
const ELEMENTOR_ITEM_CSS = `.elementor-element .elementor-repeater-item-r1{--ave-om-pin:#E11D48;--ave-om-pin-active:#E11D48}
.elementor-element .elementor-repeater-item-r3{--ave-om-pin:#059669;--ave-om-pin-active:#059669}
.elementor-element .elementor-repeater-item-r5{--ave-om-pin:#D97706;--ave-om-pin-active:#D97706}`;

const LOGO = `<svg xmlns="http://www.w3.org/2000/svg" width="80" height="40" viewBox="0 0 80 40">
<path d="M6 0h68a6 6 0 0 1 6 6v20a6 6 0 0 1-6 6H48l-8 8-8-8H6a6 6 0 0 1-6-6V6a6 6 0 0 1 6-6z" fill="#0f172a"/>
<text x="40" y="21" font-family="Arial" font-size="13" font-weight="700" fill="#fbbf24" text-anchor="middle">AVD</text></svg>`;

const GOOGLE_MOCK = `<script>(function(){
function LatLng(a,b){this.a=a;this.b=b;} LatLng.prototype.lat=function(){return this.a}; LatLng.prototype.lng=function(){return this.b};
function B(){this.s=90;this.n=-90;this.w=180;this.e=-180;} B.prototype.extend=function(l){this.s=Math.min(this.s,l.lat());this.n=Math.max(this.n,l.lat());this.w=Math.min(this.w,l.lng());this.e=Math.max(this.e,l.lng());}; B.prototype.contains=function(){return true;};
function M(el,o){this.el=el;this.opts=o;this.z=o.zoom;el.style.position='relative';el.style.background='#e8eaed';}
M.prototype.fitBounds=function(b){this.b=b;(this.ov||[]).forEach(function(o){o.draw();});}; M.prototype.getBounds=function(){return this.b;}; M.prototype.getZoom=function(){return this.z;}; M.prototype.setZoom=function(z){this.z=z;}; M.prototype.setCenter=function(){}; M.prototype.panTo=function(){}; M.prototype.panBy=function(x,y){window.__panBy=[x,y];}; M.prototype.addListener=function(){};
function O(){} O.preventMapHitsAndGesturesFrom=function(){};
O.prototype.setMap=function(m){this.map=m;(m.ov=m.ov||[]).push(this);this.pane=document.createElement('div');this.pane.style.cssText='position:absolute;inset:0';m.el.appendChild(this.pane);this.onAdd();this.draw();};
O.prototype.getPanes=function(){return{overlayMouseTarget:this.pane};};
O.prototype.getProjection=function(){var m=this.map,b=m.b;if(!b)return null;var W=m.el.clientWidth,H=m.el.clientHeight,P=60;return{fromLatLngToDivPixel:function(l){return{x:P+(l.lng()-b.w)/((b.e-b.w)||1)*(W-2*P),y:P+(b.n-l.lat())/((b.n-b.s)||1)*(H-2*P)};}};};
window.google={maps:{LatLng:LatLng,LatLngBounds:B,Map:M,OverlayView:O,event:{addListenerOnce:function(m,e,f){setTimeout(f,0);}}}};})();</script>`;

const page = ( frag, google ) => `<!doctype html><html><head><meta charset="utf-8">
<link rel="stylesheet" href="${ BASE }plugin/assets/css/ave-office-map.css"><style>${ ELEMENTOR_ITEM_CSS }
body{margin:0;padding:20px;font-family:"Liberation Sans",Arial,sans-serif;background:#fafafa}.ave-om{--ave-om-height:430px}</style>${ google ? GOOGLE_MOCK : '' }</head>
<body><div class="elementor-element" style="max-width:1000px">${ frag }</div><script src="${ BASE }plugin/assets/js/ave-office-map.js"></script></body></html>`;

( async () => {
	const browser = await chromium.launch();
	const ctx = await browser.newContext( { viewport: { width: 1040, height: 500 } } );
	await ctx.route( '**/*', ( route ) => {
		const url = route.request().url();
		if ( url.startsWith( BASE + 'pages/' ) ) {
			const name = url.slice( ( BASE + 'pages/' ).length );
			return route.fulfill( { contentType: 'text/html', body: page( fs.readFileSync( path.join( fragDir, name + '.frag.html' ), 'utf8' ), /google/.test( name ) ) } );
		}
		if ( url === BASE + 'media/logo.svg' ) return route.fulfill( { contentType: 'image/svg+xml', body: LOGO } );
		if ( url.startsWith( BASE + 'media/' ) ) return route.fulfill( { status: 404, body: '' } );
		if ( url.startsWith( BASE + 'plugin/' ) ) { const f = path.join( pluginDir, url.slice( ( BASE + 'plugin/' ).length ).split( '?' )[ 0 ] ); return route.fulfill( { contentType: f.endsWith( '.js' ) ? 'application/javascript' : 'text/css', body: fs.readFileSync( f ) } ); }
		if ( /basemaps\.cartocdn\.com/.test( url ) ) return route.fulfill( { contentType: 'image/svg+xml', body: '<svg xmlns="http://www.w3.org/2000/svg" width="256" height="256"><rect width="256" height="256" fill="#f2f1ee"/><path d="M0 90H256M120 0V256" stroke="#fff" stroke-width="6"/></svg>' } );
		return route.abort();
	} );
	const errors = [];
	const open = async ( name ) => {
		const p = await ctx.newPage();
		p.on( 'pageerror', ( e ) => errors.push( name + ': ' + e.message ) );
		await p.goto( BASE + 'pages/' + name );
		await p.waitForSelector( '.ave-om--ready', { timeout: 8000 } );
		await p.waitForTimeout( 300 );
		return p;
	};
	const shapeBg = ( p, i ) => p.locator( '.ave-om-pin' ).nth( i ).evaluate( ( e ) => { const s = e.querySelector( '.ave-om-pin__shape' ); return s ? getComputedStyle( s ).backgroundColor : null; } );

	/* ---- Layout 2 ---- */
	let p = await open( 'pins-list' );
	check( 'rgb(31, 41, 55)' === await shapeBg( p, 0 ) && 'rgb(225, 29, 72)' === await shapeBg( p, 1 ) && 'rgb(217, 119, 6)' === await shapeBg( p, 5 ), 'C1: offices without a colour use the global pin colour; office 2 and 6 use their own' );
	const badge2 = await p.locator( '.ave-om__item' ).nth( 1 ).locator( '.ave-om__num' ).evaluate( ( e ) => getComputedStyle( e ).backgroundColor );
	check( 'rgb(225, 29, 72)' === badge2, 'C2: the list number for office 2 matches its pin colour' );
	await p.locator( '.ave-om__item' ).nth( 1 ).hover();
	await p.waitForTimeout( 200 );
	const act = await p.locator( '.ave-om-pin' ).nth( 1 ).evaluate( ( e ) => { const s = e.querySelector( '.ave-om-pin__shape' ); return { bg: getComputedStyle( s ).backgroundColor, filter: getComputedStyle( s ).filter, active: e.classList.contains( 'is-active' ) }; } );
	check( act.active && 'rgb(225, 29, 72)' === act.bg && /brightness\(0\.85\)/.test( act.filter ), 'C3: highlighted office keeps its own colour, a shade darker ' + JSON.stringify( act ) );
	await p.locator( '.ave-om__item' ).nth( 0 ).hover();
	await p.waitForTimeout( 200 );
	check( 'rgb(11, 34, 199)' === await shapeBg( p, 0 ), 'C4: highlighted plain office still uses the global highlight colour' );

	const ic = await p.locator( '.ave-om-pin' ).nth( 2 ).evaluate( ( pin ) => {
		const img = pin.querySelector( 'img.ave-om-pin__img' ), a = pin.getBoundingClientRect(), r = img.getBoundingClientRect();
		const badge = pin.querySelector( '.ave-om-pin__badge' );
		return { loaded: img.complete && img.naturalWidth > 0, w: Math.round( r.width ), h: Math.round( r.height ), dx: Math.abs( ( r.left + r.right ) / 2 - a.left ), dy: Math.abs( r.bottom - a.top ), shape: !! pin.querySelector( '.ave-om-pin__shape' ), badge: badge && badge.textContent };
	} );
	check( ic.loaded && 40 === ic.h && 80 === ic.w && ! ic.shape, 'I1: office 3 shows its logo instead of the pin, 40px tall, width from the image (80px) ' + JSON.stringify( ic ) );
	check( ic.dx <= 1 && ic.dy <= 1, 'I2: the logo\'s bottom centre sits exactly on the office location' );
	check( '3' === ic.badge, 'I3: Layout 2 number badge on the logo (3)' );
	const ic4 = await p.locator( '.ave-om-pin' ).nth( 3 ).evaluate( ( pin ) => getComputedStyle( pin.querySelector( '.ave-om-pin__badge' ) ).backgroundColor );
	check( 'rgb(5, 150, 105)' === ic4, 'I4: logo + own colour → badge uses the office colour' );

	await p.mouse.move( 5, 5 );
	await p.waitForTimeout( 350 ); // Let office 1's popup close so it can't cover the logo.
	check( 0 === await p.locator( '.ave-om-pop' ).count(), 'I5a: previous popup closed' );
	await p.locator( '.ave-om-pin__marker' ).nth( 2 ).hover();
	await p.waitForTimeout( 200 );
	const clear = await p.evaluate( () => { const pin = document.querySelectorAll( '.ave-om-pin' )[ 2 ]; const img = pin.querySelector( 'img' ).getBoundingClientRect(), pop = pin.querySelector( '.ave-om-pop' ).getBoundingClientRect(); return { pop: pop.bottom, img: img.top, below: pin.querySelector( '.ave-om-pop' ).classList.contains( 'is-below' ), imgBottom: img.bottom }; } );
	check( clear.below ? clear.pop >= clear.imgBottom : clear.pop <= clear.img - 4, 'I5: popup clears the (enlarged) logo ' + JSON.stringify( clear ) );

	const broken = await p.locator( '.ave-om-pin' ).nth( 4 ).evaluate( ( pin ) => ( { img: !! pin.querySelector( 'img' ), shape: !! pin.querySelector( '.ave-om-pin__shape' ), label: ( pin.querySelector( '.ave-om-pin__label' ) || {} ).textContent } ) );
	check( ! broken.img && broken.shape && '5' === broken.label, 'I6: broken icon URL falls back to the standard numbered pin' );
	await p.mouse.move( 5, 5 );
	await p.waitForTimeout( 350 );
	await p.locator( '.ave-om__item' ).nth( 3 ).hover();
	await p.waitForTimeout( 150 );
	await p.screenshot( { path: path.join( shotDir, 'pins-list.png' ) } );
	await p.close();

	/* ---- Layout 1 ---- */
	p = await open( 'pins-popup' );
	const l1 = await p.evaluate( () => ( { badges: document.querySelectorAll( '.ave-om-pin__badge' ).length, logos: document.querySelectorAll( '.ave-om-pin__img' ).length, dot: !! document.querySelectorAll( '.ave-om-pin' )[ 1 ].querySelector( '.ave-om-pin__label--dot' ) } ) );
	check( 0 === l1.badges && 2 === l1.logos && l1.dot, 'L1: Layout 1 → logos without number badges; coloured pins keep their dot' );
	await p.locator( '.ave-om-pin__marker' ).nth( 3 ).hover( { force: true } );
	await p.waitForTimeout( 150 );
	await p.screenshot( { path: path.join( shotDir, 'pins-popup.png' ) } );
	await p.close();

	/* ---- Google engine ---- */
	p = await open( 'pins-google' );
	const g = await p.evaluate( () => ( { logos: document.querySelectorAll( '.ave-om-anchor--google .ave-om-pin__img' ).length, red: getComputedStyle( document.querySelectorAll( '.ave-om-anchor--google .ave-om-pin' )[ 1 ].querySelector( '.ave-om-pin__shape' ) ).backgroundColor } ) );
	check( 2 === g.logos && 'rgb(225, 29, 72)' === g.red, 'G1: Google engine shows the same logos and per-office colours' );
	await p.close();

	check( 0 === errors.length, 'No page errors' + ( errors.length ? ': ' + errors.join( ' | ' ) : '' ) );
	await browser.close();
	console.log( fails ? `\n${ fails } FAILED, ${ passes } passed` : `\nALL ${ passes } PASSED` );
	process.exit( fails ? 1 : 0 );
} )().catch( ( e ) => { console.error( e ); process.exit( 2 ); } );
