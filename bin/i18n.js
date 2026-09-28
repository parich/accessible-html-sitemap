/**
 * Rebuilds the translation files with WP-CLI:
 *   languages/accessible-html-sitemap.pot   ← PHP, block.json and the built editor script
 *   languages/*.po              ← updated with new strings (translate them, then run again)
 *   languages/*.mo              ← PHP translations
 *   languages/*-<md5>.json      ← editor script translations
 * and lists strings that are still untranslated.
 *
 * Run `npm run build` first: strings are read from build/, so the JSON file names
 * match the script WordPress loads (build/sitemap/index.js).
 *
 * PHP and WP-CLI come from env (PHP_BIN, PHP_ARGS, WP_CLI) or tests/local-config.json.
 * Usage: npm run i18n
 */
const { spawnSync } = require( 'child_process' );
const fs = require( 'fs' );
const path = require( 'path' );

const root = path.join( __dirname, '..' );
const languages = path.join( root, 'languages' );
const configFile = path.join( root, 'tests', 'local-config.json' );
const config = fs.existsSync( configFile )
	? JSON.parse( fs.readFileSync( configFile, 'utf8' ) )
	: {};

const phpBin = process.env.PHP_BIN || config.phpBin || 'php';
const phpArgs = process.env.PHP_ARGS
	? process.env.PHP_ARGS.split( ' ' ).filter( Boolean )
	: config.phpArgs || [];
const wpCli = process.env.WP_CLI || config.wpCli;
if ( ! wpCli ) {
	// eslint-disable-next-line no-console
	console.error( 'Set WP_CLI or "wpCli" in tests/local-config.json' );
	process.exit( 2 );
}

function wp( ...args ) {
	// eslint-disable-next-line no-console
	console.log( `> wp ${ args.join( ' ' ) }` );
	const result = spawnSync( phpBin, [ ...phpArgs, wpCli, ...args ], {
		cwd: root,
		encoding: 'utf8',
	} );
	const output = `${ result.stdout }${ result.stderr }`
		.split( /\r?\n/ )
		.filter(
			( line ) =>
				line &&
				! /PHP Startup: Unable to load|Failed loading Zend/.test( line )
		)
		.join( '\n' );
	if ( output ) {
		// eslint-disable-next-line no-console
		console.log( output );
	}
	if ( result.status !== 0 ) {
		process.exit( result.status || 1 );
	}
}

/**
 * @param {string} file .po file.
 * @return {string[]} msgids with an empty msgstr (the header entry excluded).
 */
function untranslated( file ) {
	const entries = fs.readFileSync( file, 'utf8' ).split( /\r?\n\r?\n/ );
	const missing = [];
	for ( const entry of entries ) {
		const id = entry.match( /^msgid "(.*)"$/m );
		// Plural entries have msgstr[0], msgstr[1] …; Thai has one form.
		const str = entry.match( /^msgstr(?:\[0\])? "(.*)"$/m );
		if ( id && id[ 1 ] !== '' && str && str[ 1 ] === '' ) {
			missing.push( id[ 1 ] );
		}
	}
	return missing;
}

fs.mkdirSync( languages, { recursive: true } );
wp(
	'i18n',
	'make-pot',
	'.',
	'languages/accessible-html-sitemap.pot',
	'--domain=accessible-html-sitemap',
	'--exclude=src,node_modules,tests,docs,bin,vendor',
	'--package-name=Accessible HTML Sitemap'
);

const poFiles = fs
	.readdirSync( languages )
	.filter( ( file ) => file.endsWith( '.po' ) );
if ( poFiles.length ) {
	wp(
		'i18n',
		'update-po',
		'languages/accessible-html-sitemap.pot',
		'languages/'
	);
	wp( 'i18n', 'make-mo', 'languages/' );
	// --no-purge keeps the editor strings in the .po as well, so it stays the one file to translate.
	wp( 'i18n', 'make-json', 'languages/', '--no-purge' );
}

let incomplete = false;
for ( const file of poFiles ) {
	const missing = untranslated( path.join( languages, file ) );
	// eslint-disable-next-line no-console
	console.log(
		`${ file }: ${
			missing.length ? `${ missing.length } untranslated` : 'complete'
		}`
	);
	for ( const id of missing ) {
		// eslint-disable-next-line no-console
		console.log( `  - ${ id }` );
	}
	incomplete = incomplete || missing.length > 0;
}
process.exit( incomplete ? 1 : 0 );
