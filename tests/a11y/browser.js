/**
 * Edge or Chrome for the browser checks (reflow, media): env BROWSER_BIN,
 * "browser" in tests/local-config.json, or the usual install paths.
 *
 * @return {string|undefined} Path to the browser, or undefined when none is found.
 */
const fs = require( 'fs' );
const path = require( 'path' );

module.exports = function findBrowser() {
	const configFile = path.join( __dirname, '..', 'local-config.json' );
	const config = fs.existsSync( configFile )
		? JSON.parse( fs.readFileSync( configFile, 'utf8' ) )
		: {};
	return [
		process.env.BROWSER_BIN,
		config.browser,
		'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
		'C:\\Program Files\\Microsoft\\Edge\\Application\\msedge.exe',
		'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
		'/usr/bin/google-chrome',
		'/usr/bin/chromium',
		'/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
	].find( ( candidate ) => candidate && fs.existsSync( candidate ) );
};
