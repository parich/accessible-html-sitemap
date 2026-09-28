/**
 * High contrast and print check in a real browser: Edge or Chrome in headless
 * mode, driven over the DevTools protocol, which can emulate forced colors,
 * "increase contrast" and print media.
 *
 * - Forced colors (Windows contrast themes) and prefers-contrast: more — the
 *   tree lines are drawn at full strength (50% otherwise).
 * - Print — the table of contents and the "Back to contents" links are hidden,
 *   collapsed categories are printed open, without the toggle arrow.
 *
 * Checks pages written by the PHP tests (tests/a11y/out/10-*.html), so run
 * npm run test:php first. Without a browser, or on a Node.js without WebSocket
 * (before 22), the check is skipped (exit 0) with a message.
 *
 * Usage: npm run test:media
 */
const fs = require( 'fs' );
const os = require( 'os' );
const path = require( 'path' );
const { spawn } = require( 'child_process' );
const { pathToFileURL } = require( 'url' );
const findBrowser = require( './browser' );

const OUT = path.join( __dirname, 'out' );
const TREE_PAGE = path.join( OUT, '10-back-links.html' );
const COLLAPSIBLE_PAGE = path.join( OUT, '10-back-links-collapsible.html' );

// The first tree line: its ::before is the trunk, drawn with a border.
const TREE_LINE = `( () => {
	const li = document.querySelector( '.parich-sitemap__section li' );
	const line = getComputedStyle( li, '::before' );
	return {
		opacity: line.opacity,
		border: line.borderInlineStartStyle,
		sameColorAsText: line.borderInlineStartColor === getComputedStyle( li ).color,
		forced: matchMedia( '(forced-colors: active)' ).matches,
		contrast: matchMedia( '(prefers-contrast: more)' ).matches,
	};
} )()`;

// What a reader sees of the table of contents, back links and folded categories.
const PRINTED = `( () => {
	const visible = ( el ) => !! el && el.checkVisibility();
	const closed = [ ...document.querySelectorAll( '.parich-sitemap details:not([open])' ) ];
	const inside = closed.map( ( details ) => details.querySelector( 'ul a' ) ).filter( Boolean );
	return {
		print: matchMedia( 'print' ).matches,
		toc: visible( document.querySelector( '.parich-sitemap__toc' ) ) || visible( document.querySelector( '.parich-sitemap__toc-label' ) ),
		back: [ ...document.querySelectorAll( '.parich-sitemap__back' ) ].some( visible ),
		backLinks: document.querySelectorAll( '.parich-sitemap__back' ).length,
		closed: closed.length,
		visibleInClosed: inside.filter( visible ).length,
		arrow: closed.length ? getComputedStyle( closed[ 0 ].querySelector( 'summary' ) ).listStyleType : '',
	};
} )()`;

let failed = 0;
let passed = 0;

function assert( label, condition, detail ) {
	if ( condition ) {
		passed++;
		// eslint-disable-next-line no-console
		console.log( `[PASS] ${ label }` );
	} else {
		failed++;
		// eslint-disable-next-line no-console
		console.log(
			`[FAIL] ${ label }${
				detail ? ' — ' + JSON.stringify( detail ) : ''
			}`
		);
	}
}

/**
 * Starts the browser and returns a DevTools session on a blank page.
 *
 * @param {string} browser Path to Edge or Chrome.
 * @param {string} profile Empty folder for the browser profile.
 */
async function open( browser, profile ) {
	const child = spawn(
		browser,
		[
			'--headless=new',
			'--disable-gpu',
			'--no-first-run',
			'--no-default-browser-check',
			'--remote-debugging-port=0',
			`--user-data-dir=${ profile }`,
			'about:blank',
		],
		{ stdio: [ 'ignore', 'ignore', 'pipe' ] }
	);
	const endpoint = await new Promise( ( resolve, reject ) => {
		let log = '';
		const timer = setTimeout(
			() => reject( new Error( 'the browser did not start' ) ),
			30000
		);
		child.stderr.on( 'data', ( chunk ) => {
			log += chunk;
			const match = /DevTools listening on (ws:\/\/\S+)/.exec( log );
			if ( match ) {
				clearTimeout( timer );
				resolve( match[ 1 ] );
			}
		} );
		child.on( 'exit', () =>
			reject( new Error( 'the browser exited: ' + log.trim() ) )
		);
	} );

	const socket = new WebSocket( endpoint );
	await new Promise( ( resolve, reject ) => {
		socket.onopen = resolve;
		socket.onerror = () => reject( new Error( 'no DevTools connection' ) );
	} );

	let lastId = 0;
	const pending = new Map();
	const events = [];
	socket.onmessage = ( message ) => {
		const data = JSON.parse( message.data );
		if ( data.id && pending.has( data.id ) ) {
			const { resolve, reject } = pending.get( data.id );
			pending.delete( data.id );
			if ( data.error ) {
				reject( new Error( data.error.message ) );
			} else {
				resolve( data.result );
			}
		} else {
			events.forEach( ( listener ) => listener( data ) );
		}
	};
	const send = ( method, params = {}, sessionId ) =>
		new Promise( ( resolve, reject ) => {
			const id = ++lastId;
			pending.set( id, { resolve, reject } );
			socket.send( JSON.stringify( { id, method, params, sessionId } ) );
		} );

	const { targetId } = await send( 'Target.createTarget', {
		url: 'about:blank',
	} );
	const { sessionId } = await send( 'Target.attachToTarget', {
		targetId,
		flatten: true,
	} );
	await send( 'Page.enable', {}, sessionId );

	return {
		async load( file ) {
			const loaded = new Promise( ( resolve ) => {
				const listener = ( data ) => {
					if (
						data.sessionId === sessionId &&
						data.method === 'Page.loadEventFired'
					) {
						events.splice( events.indexOf( listener ), 1 );
						resolve();
					}
				};
				events.push( listener );
			} );
			await send(
				'Page.navigate',
				{ url: pathToFileURL( file ).href },
				sessionId
			);
			await loaded;
		},
		media( media, features = [] ) {
			return send(
				'Emulation.setEmulatedMedia',
				{ media, features },
				sessionId
			);
		},
		async evaluate( expression ) {
			const { result, exceptionDetails } = await send(
				'Runtime.evaluate',
				{ expression, returnByValue: true },
				sessionId
			);
			if ( exceptionDetails ) {
				throw new Error(
					exceptionDetails.exception?.description ||
						exceptionDetails.text
				);
			}
			return result.value;
		},
		close() {
			socket.close();
			const exited = new Promise( ( resolve ) =>
				null === child.exitCode
					? child.once( 'exit', resolve )
					: resolve()
			);
			child.kill();
			return exited;
		},
	};
}

async function main() {
	const browser = findBrowser();
	if ( ! browser ) {
		// eslint-disable-next-line no-console
		console.log(
			'Media check skipped: no Edge/Chrome found (set BROWSER_BIN).'
		);
		return;
	}
	if ( typeof WebSocket === 'undefined' ) {
		// eslint-disable-next-line no-console
		console.log(
			'Media check skipped: this Node.js has no WebSocket (needs 22+).'
		);
		return;
	}
	const missing = [ TREE_PAGE, COLLAPSIBLE_PAGE ].filter(
		( file ) => ! fs.existsSync( file )
	);
	if ( missing.length ) {
		// eslint-disable-next-line no-console
		console.log(
			`Missing ${ missing
				.map( ( file ) => path.basename( file ) )
				.join( ', ' ) }: run npm run test:php first.`
		);
		process.exit( 1 );
	}

	const profile = fs.mkdtempSync( path.join( os.tmpdir(), 'pas-media-' ) );
	const page = await open( browser, profile );
	try {
		await page.load( TREE_PAGE );
		let line = await page.evaluate( TREE_LINE );
		assert(
			'screen: tree lines at half strength',
			line.opacity === '0.5' && line.border === 'solid',
			line
		);

		await page.media( '', [ { name: 'forced-colors', value: 'active' } ] );
		line = await page.evaluate( TREE_LINE );
		assert(
			'forced colors: tree lines at full strength, in the text color',
			line.forced &&
				line.opacity === '1' &&
				line.border === 'solid' &&
				line.sameColorAsText,
			line
		);

		await page.media( '', [ { name: 'prefers-contrast', value: 'more' } ] );
		line = await page.evaluate( TREE_LINE );
		assert(
			'increase contrast: tree lines at full strength',
			line.contrast && line.opacity === '1',
			line
		);

		await page.media( '' );
		await page.load( COLLAPSIBLE_PAGE );
		let printed = await page.evaluate( PRINTED );
		assert(
			'screen: table of contents, back links, categories folded',
			! printed.print &&
				printed.toc &&
				printed.back &&
				printed.closed > 0 &&
				printed.visibleInClosed === 0,
			printed
		);

		await page.media( 'print' );
		printed = await page.evaluate( PRINTED );
		assert(
			'print: no table of contents and no back links',
			printed.print &&
				! printed.toc &&
				! printed.back &&
				printed.backLinks > 0,
			printed
		);
		assert(
			'print: folded categories printed open, without the arrow',
			printed.closed > 0 &&
				printed.visibleInClosed === printed.closed &&
				printed.arrow === 'none',
			printed
		);
	} finally {
		await page.close();
		try {
			// Helper processes may hold the profile for a moment after the browser exits.
			fs.rmSync( profile, {
				recursive: true,
				force: true,
				maxRetries: 10,
				retryDelay: 200,
			} );
		} catch {
			// eslint-disable-next-line no-console
			console.log( `(temporary profile left in ${ profile })` );
		}
	}

	// eslint-disable-next-line no-console
	console.log(
		`\n${ passed }/${
			passed + failed
		} high contrast and print checks passed`
	);
	process.exit( failed ? 1 : 0 );
}

main().catch( ( error ) => {
	// eslint-disable-next-line no-console
	console.error( error );
	process.exit( 1 );
} );
