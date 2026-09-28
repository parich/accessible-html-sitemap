/**
 * Reflow check (WCAG 1.4.10) in a real browser: every sitemap page in
 * tests/a11y/out/ is laid out 320px wide and must have no element past either
 * edge — no horizontal scrolling. Settings pages are skipped (the admin UI is
 * styled by WordPress, not by this plugin).
 *
 * Uses Edge or Chrome in headless mode: env BROWSER_BIN, "browser" in
 * tests/local-config.json, or the usual install paths. Without a browser the
 * check is skipped (exit 0) with a message.
 *
 * Usage: npm run test:reflow [-- <file.html> …]
 */
const fs = require( 'fs' );
const os = require( 'os' );
const path = require( 'path' );
const { execFile } = require( 'child_process' );
const findBrowser = require( './browser' );

const WIDTH = 320;
const CONCURRENCY = 4;

const browser = findBrowser();

// Headless browsers will not lay out a window narrower than ~500px, so the page
// itself is pinned to 320px; the script lists elements that stick out.
const probe = `<style>html { width: ${ WIDTH }px !important; }</style>
<script>
addEventListener( 'load', () => {
	const out = [];
	for ( const el of document.querySelectorAll( 'body *' ) ) {
		const box = el.getBoundingClientRect();
		if ( box.width && ( box.right > ${ WIDTH } + 0.5 || box.left < -0.5 ) ) {
			out.push( el.tagName.toLowerCase() + ( el.className ? '.' + String( el.className ).trim().split( /\\s+/ ).join( '.' ) : '' ) + ' [' + Math.round( box.left ) + '–' + Math.round( box.right ) + 'px] ' + ( el.textContent || '' ).trim().slice( 0, 40 ) );
		}
	}
	const result = document.createElement( 'pre' );
	result.id = 'reflow-result';
	result.textContent = JSON.stringify( out );
	document.body.appendChild( result );
} );
</script>`;

function check( file ) {
	const copy = path.join(
		os.tmpdir(),
		`ahs-reflow-${ process.pid }-${ path.basename( file ) }`
	);
	fs.writeFileSync(
		copy,
		fs.readFileSync( file, 'utf8' ).replace( '</head>', probe + '</head>' )
	);
	return new Promise( ( resolve ) => {
		execFile(
			browser,
			[
				'--headless=new',
				'--disable-gpu',
				'--virtual-time-budget=5000',
				'--dump-dom',
				'file:///' + copy.replace( /\\/g, '/' ),
			],
			{ maxBuffer: 64 * 1024 * 1024 },
			( error, stdout ) => {
				fs.unlinkSync( copy );
				const match = /<pre id="reflow-result">([\s\S]*?)<\/pre>/.exec(
					stdout || ''
				);
				if ( ! match ) {
					resolve( {
						file,
						error: error
							? error.message
							: 'no result from the page',
					} );
					return;
				}
				const json = match[ 1 ]
					.replace( /&lt;/g, '<' )
					.replace( /&gt;/g, '>' )
					.replace( /&amp;/g, '&' );
				resolve( { file, overflow: JSON.parse( json ) } );
			}
		);
	} );
}

async function main() {
	if ( ! browser ) {
		// eslint-disable-next-line no-console
		console.log(
			'Reflow check skipped: no Edge/Chrome found (set BROWSER_BIN).'
		);
		return;
	}
	const outDir = path.join( __dirname, 'out' );
	let files = process.argv.slice( 2 );
	if ( ! files.length && fs.existsSync( outDir ) ) {
		files = fs
			.readdirSync( outDir )
			.filter(
				( file ) =>
					file.endsWith( '.html' ) && ! file.includes( 'settings' )
			)
			.sort()
			.map( ( file ) => path.join( outDir, file ) );
	}

	const results = [];
	for ( let i = 0; i < files.length; i += CONCURRENCY ) {
		results.push(
			...( await Promise.all(
				files.slice( i, i + CONCURRENCY ).map( check )
			) )
		);
	}

	let failed = 0;
	for ( const result of results ) {
		const name = path.relative( process.cwd(), result.file );
		if ( result.error || result.overflow.length ) {
			failed++;
			// eslint-disable-next-line no-console
			console.log( `[FAIL] ${ name }` );
			for ( const line of result.error
				? [ result.error ]
				: result.overflow.slice( 0, 5 ) ) {
				// eslint-disable-next-line no-console
				console.log( `    ${ line }` );
			}
		} else {
			// eslint-disable-next-line no-console
			console.log( `[PASS] ${ name }` );
		}
	}
	// eslint-disable-next-line no-console
	console.log(
		`\n${ results.length - failed }/${
			results.length
		} pages reflow at ${ WIDTH }px without horizontal scrolling`
	);
	process.exit( failed ? 1 : 0 );
}

main();
