/**
 * Runs every tests/php/*.php file with PHP and reports a summary.
 *
 * The PHP binary, its arguments and extra env vars come from environment
 * variables (PHP_BIN, PHP_ARGS) or tests/local-config.json
 * (see tests/local-config.example.json).
 *
 * Usage: npm run test:php [-- <filter>]   e.g. npm run test:php -- renderer
 *        node tests/run-php-tests.js tests/bench.php   (one file outside tests/php)
 */
const { spawnSync } = require( 'child_process' );
const fs = require( 'fs' );
const path = require( 'path' );

const configFile = path.join( __dirname, 'local-config.json' );
const config = fs.existsSync( configFile )
	? JSON.parse( fs.readFileSync( configFile, 'utf8' ) )
	: {};

const phpBin = process.env.PHP_BIN || config.phpBin || 'php';
const phpArgs = process.env.PHP_ARGS
	? process.env.PHP_ARGS.split( ' ' ).filter( Boolean )
	: config.phpArgs || [];
const env = { ...process.env, ...( config.env || {} ) };

// LocalWP's php.ini lists extensions that are not shipped for CLI; hide that startup noise only.
const STARTUP_NOISE =
	/PHP Startup: Unable to load dynamic library|Failed loading Zend extension/;

const filter = process.argv[ 2 ];
let testDir = path.join( __dirname, 'php' );
let files;
if ( filter && filter.endsWith( '.php' ) && fs.existsSync( filter ) ) {
	testDir = path.dirname( path.resolve( filter ) );
	files = [ path.basename( filter ) ];
} else {
	files = fs
		.readdirSync( testDir )
		.filter( ( file ) => file.endsWith( '.php' ) )
		.filter( ( file ) => ! filter || file.includes( filter ) )
		.sort();
}

if ( ! files.length ) {
	// eslint-disable-next-line no-console
	console.log( 'No PHP tests found.' );
	process.exit( 0 );
}

let failed = 0;
for ( const file of files ) {
	// eslint-disable-next-line no-console
	console.log( `\n=== ${ file } ===` );
	const result = spawnSync(
		phpBin,
		[ ...phpArgs, path.join( testDir, file ) ],
		{
			env,
			encoding: 'utf8',
		}
	);
	if ( result.error ) {
		// eslint-disable-next-line no-console
		console.error( `Could not run ${ phpBin }: ${ result.error.message }` );
		process.exit( 2 );
	}
	process.stdout.write( result.stdout );
	const stderr = result.stderr
		.split( /\r?\n/ )
		.filter( ( line ) => line && ! STARTUP_NOISE.test( line ) )
		.join( '\n' );
	if ( stderr ) {
		process.stderr.write( stderr + '\n' );
	}
	if ( result.status !== 0 ) {
		failed++;
		// eslint-disable-next-line no-console
		console.log( `FAILED: ${ file } (exit code ${ result.status })` );
	}
}

// eslint-disable-next-line no-console
console.log(
	`\n${ files.length - failed }/${ files.length } PHP test files passed`
);
process.exit( failed ? 1 : 0 );
