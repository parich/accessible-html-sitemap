<?php
/**
 * Test bootstrap: loads WordPress with this plugin and gives each test file
 * simple assertions plus a DB transaction, so fixtures never persist.
 *
 * Usage (first line of a test file): require __DIR__ . '/../bootstrap.php';
 * Finish the file with ahs_test_done(), which rolls back and sets the exit code.
 *
 * Environment:
 *   WP_LOAD_PATH  path to wp-load.php (default: four directories up from this folder)
 *   WP_TEST_HOST  host name used for $_SERVER['HTTP_HOST'] (default: localhost)
 *
 * @package Accessible_HTML_Sitemap
 */

$_SERVER['HTTP_HOST'] = getenv('WP_TEST_HOST') ?: 'localhost';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['SERVER_NAME'] = $_SERVER['HTTP_HOST'];

define('WP_USE_THEMES', false);
define('WP_DEBUG', true);
// Keep wp-load.php from spawning a cron request while tests run.
define('DISABLE_WP_CRON', true);

define('AHS_TEST_PLUGIN_DIR', dirname(__DIR__));
define('AHS_TEST_PLUGIN_FILE', AHS_TEST_PLUGIN_DIR . '/accessible-html-sitemap.php');
define('AHS_TEST_PLUGIN_BASENAME', 'accessible-html-sitemap/accessible-html-sitemap.php');

$GLOBALS['ahs_test_failures'] = array();
$GLOBALS['ahs_test_passes'] = 0;

/**
 * Records PHP notices/warnings/deprecations raised from this plugin's own files.
 * The handler is called regardless of error_reporting(), so nothing slips through.
 */
set_error_handler(function ($errno, $errstr, $errfile, $errline) {
	if (strpos(ahs_test_normalize_path($errfile), ahs_test_normalize_path(AHS_TEST_PLUGIN_DIR) . '/') === 0) {
		$GLOBALS['ahs_test_failures'][] = sprintf('PHP error %d: %s (%s:%d)', $errno, $errstr, $errfile, $errline);
	}
	return false; // Let PHP handle (print) it as usual.
});

// wp_normalize_path() is not available yet when early errors happen.
function ahs_test_normalize_path($path)
{
	return str_replace('\\', '/', (string) $path);
}

/**
 * Hooks registered before WordPress loads (same technique as tests_add_filter()
 * in the WordPress PHPUnit suite).
 */
function ahs_test_add_early_hook($hook, $callback, $priority = 10, $accepted_args = 1)
{
	$GLOBALS['wp_filter'][$hook][$priority][] = array(
		'function' => $callback,
		'accepted_args' => $accepted_args,
	);
}

/**
 * Everything from here on runs inside one transaction that ahs_test_done() rolls back,
 * including what plugins write during init (e.g. this plugin's settings migration).
 * Refuse to run if a table cannot roll back (MyISAM would keep the fixtures).
 */
ahs_test_add_early_hook('muplugins_loaded', function () {
	global $wpdb;
	foreach (array($wpdb->posts, $wpdb->postmeta, $wpdb->options, $wpdb->terms, $wpdb->term_taxonomy, $wpdb->term_relationships, $wpdb->users, $wpdb->usermeta) as $table) {
		$engine = $wpdb->get_var($wpdb->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s', $table));
		if (strtolower((string) $engine) !== 'innodb') {
			fwrite(STDERR, "Table {$table} uses engine '{$engine}', fixtures could not be rolled back. Aborting.\n");
			exit(2);
		}
	}
	$wpdb->query('SET autocommit = 0');
	$wpdb->query('START TRANSACTION');
}, 1, 0);

// Tests assert English text whatever the site language is (AHS_TEST_LOCALE overrides);
// Thai output is tested with switch_to_locale('th'), which takes precedence over this filter.
ahs_test_add_early_hook('locale', function () {
	return getenv('AHS_TEST_LOCALE') ?: 'en_US';
}, 10, 0);

// Load the plugin even when it is not activated on the site, early enough for its init hooks.
ahs_test_add_early_hook('muplugins_loaded', function () {
	$active = (array) get_option('active_plugins', array());
	if (!in_array(AHS_TEST_PLUGIN_BASENAME, $active, true)) {
		require AHS_TEST_PLUGIN_FILE;
	}
}, 10, 0);

// _doing_it_wrong() / deprecation notices triggered from this plugin's code count as failures.
$ahs_test_wp_notice = function ($function_name, $message = '') {
	foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS) as $frame) {
		if (isset($frame['file']) && strpos(ahs_test_normalize_path($frame['file']), ahs_test_normalize_path(AHS_TEST_PLUGIN_DIR) . '/') === 0) {
			$GLOBALS['ahs_test_failures'][] = sprintf('WordPress notice from %s: %s', $function_name, wp_strip_all_tags((string) $message));
			return;
		}
	}
};
foreach (array('doing_it_wrong_run', 'deprecated_function_run', 'deprecated_argument_run', 'deprecated_hook_run') as $ahs_test_hook) {
	ahs_test_add_early_hook($ahs_test_hook, $ahs_test_wp_notice, 10, 2);
}

$ahs_test_wp_load = getenv('WP_LOAD_PATH') ?: dirname(__DIR__, 4) . '/wp-load.php';
if (!file_exists($ahs_test_wp_load)) {
	fwrite(STDERR, "wp-load.php not found at {$ahs_test_wp_load} (set WP_LOAD_PATH)\n");
	exit(2);
}
require $ahs_test_wp_load;
require_once ABSPATH . 'wp-admin/includes/plugin.php';

function ahs_test_section($title)
{
	echo "\n{$title}\n";
}

function ahs_test_assert($label, $condition, $detail = '')
{
	if ($condition) {
		$GLOBALS['ahs_test_passes']++;
		echo "  [PASS] {$label}\n";
		return true;
	}
	$GLOBALS['ahs_test_failures'][] = $label . ($detail !== '' ? " — {$detail}" : '');
	echo "  [FAIL] {$label}" . ($detail !== '' ? " — {$detail}" : '') . "\n";
	return false;
}

/**
 * Rolls back every fixture, prints the summary and exits non-zero on any failure.
 */
function ahs_test_done()
{
	global $wpdb;
	$wpdb->query('ROLLBACK');
	$wpdb->query('SET autocommit = 1');
	wp_cache_flush();

	$failures = $GLOBALS['ahs_test_failures'];
	echo "\n" . $GLOBALS['ahs_test_passes'] . ' passed, ' . count($failures) . " failed\n";
	foreach ($failures as $failure) {
		echo "  - {$failure}\n";
	}
	exit($failures ? 1 : 0);
}
