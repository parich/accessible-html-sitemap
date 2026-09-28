/**
 * Runs axe-core (WCAG 2.0 / 2.1, levels A and AA) against HTML files in jsdom.
 *
 * 1. Self-test: fixtures/good.html must have no violations and fixtures/bad.html
 *    must be caught, so a broken harness can never report a false "0 violations".
 * 2. Checks every tests/a11y/out/*.html written by the PHP tests
 *    (or only the files passed as arguments).
 *
 * color-contrast is disabled: jsdom does not render, so it cannot compute colors.
 * Contrast is checked manually (docs/a11y-checklist.md).
 */
const fs = require( 'fs' );
const path = require( 'path' );
const { JSDOM } = require( 'jsdom' );

const axeSource = fs.readFileSync(
	require.resolve( 'axe-core/axe.min.js' ),
	'utf8'
);

const RUN_OPTIONS = {
	runOnly: {
		type: 'tag',
		values: [ 'wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa' ],
	},
	rules: {
		'color-contrast': { enabled: false },
	},
	resultTypes: [ 'violations' ],
};

async function getViolations( file ) {
	const dom = new JSDOM( fs.readFileSync( file, 'utf8' ), {
		runScripts: 'outside-only',
	} );
	dom.window.eval( axeSource );
	const results = await dom.window.axe.run(
		dom.window.document,
		RUN_OPTIONS
	);
	dom.window.close();
	return results.violations;
}

function printViolations( violations ) {
	for ( const violation of violations ) {
		// eslint-disable-next-line no-console
		console.log(
			`    - ${ violation.id } (${ violation.impact }): ${ violation.help }`
		);
		for ( const node of violation.nodes.slice( 0, 5 ) ) {
			// eslint-disable-next-line no-console
			console.log( `        ${ node.target.join( ' ' ) }` );
		}
	}
}

async function selfTest() {
	const fixtures = path.join( __dirname, 'fixtures' );
	const good = await getViolations( path.join( fixtures, 'good.html' ) );
	const bad = await getViolations( path.join( fixtures, 'bad.html' ) );
	const badIds = bad.map( ( violation ) => violation.id );
	const expected = [ 'link-name', 'image-alt', 'html-has-lang' ];
	const missing = expected.filter( ( id ) => ! badIds.includes( id ) );

	if ( good.length || missing.length ) {
		// eslint-disable-next-line no-console
		console.log( '[FAIL] axe self-test' );
		printViolations( good );
		if ( missing.length ) {
			// eslint-disable-next-line no-console
			console.log(
				`    bad.html did not report: ${ missing.join( ', ' ) }`
			);
		}
		return false;
	}
	// eslint-disable-next-line no-console
	console.log(
		`[PASS] axe self-test (good.html clean, bad.html caught: ${ expected.join( ', ' ) })`
	);
	return true;
}

async function main() {
	if ( ! ( await selfTest() ) ) {
		process.exit( 1 );
	}

	const outDir = path.join( __dirname, 'out' );
	let files = process.argv.slice( 2 );
	if ( ! files.length && fs.existsSync( outDir ) ) {
		files = fs
			.readdirSync( outDir )
			.filter( ( file ) => file.endsWith( '.html' ) )
			.sort()
			.map( ( file ) => path.join( outDir, file ) );
	}
	if ( ! files.length ) {
		// eslint-disable-next-line no-console
		console.log( 'No rendered HTML to check (tests/a11y/out/ is empty).' );
		return;
	}

	let failed = 0;
	for ( const file of files ) {
		const violations = await getViolations( file );
		const name = path.relative( process.cwd(), file );
		if ( violations.length ) {
			failed++;
			// eslint-disable-next-line no-console
			console.log( `[FAIL] ${ name }` );
			printViolations( violations );
		} else {
			// eslint-disable-next-line no-console
			console.log( `[PASS] ${ name }` );
		}
	}
	// eslint-disable-next-line no-console
	console.log(
		`\n${ files.length - failed }/${ files.length } files without WCAG A/AA violations`
	);
	process.exit( failed ? 1 : 0 );
}

main().catch( ( error ) => {
	// eslint-disable-next-line no-console
	console.error( error );
	process.exit( 2 );
} );
