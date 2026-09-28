<?php
/**
 * Phase 1: the plugin loads, registers its block and activates next to WP Sitemap Page.
 *
 * @package Accessible_HTML_Sitemap
 */

require __DIR__ . '/../bootstrap.php';

ahs_test_section('Plugin loads');
ahs_test_assert('main plugin function is defined', function_exists('accessible_sitemap_register_block'));

$block = WP_Block_Type_Registry::get_instance()->get_registered('accessible-html-sitemap/sitemap');
ahs_test_assert('block accessible-html-sitemap/sitemap is registered', $block instanceof WP_Block_Type);
ahs_test_assert('block has a server-side render callback', $block && is_callable($block->render_callback));
// Core reads textdomain from the metadata to translate title/description; it is not copied onto WP_Block_Type.
$manifest = require AHS_TEST_PLUGIN_DIR . '/build/blocks-manifest.php';
ahs_test_assert(
	'block metadata uses the accessible-html-sitemap text domain',
	($manifest['sitemap']['textdomain'] ?? null) === 'accessible-html-sitemap'
);
ahs_test_assert('rendered by accessible_sitemap_render_block (returns the markup; no render.php template echoing it)', 'accessible_sitemap_render_block' === $block->render_callback && !isset($manifest['sitemap']['render']) && !file_exists(AHS_TEST_PLUGIN_DIR . '/build/sitemap/render.php'));

ahs_test_section('Plugin header');
$header = get_plugin_data(AHS_TEST_PLUGIN_FILE, false, false);
ahs_test_assert('Name is "Accessible HTML Sitemap"', $header['Name'] === 'Accessible HTML Sitemap', $header['Name']);
ahs_test_assert('Version is 1.0.0', $header['Version'] === '1.0.0', $header['Version']);
ahs_test_assert('Text Domain is accessible-html-sitemap', $header['TextDomain'] === 'accessible-html-sitemap');
ahs_test_assert('Domain Path is /languages', $header['DomainPath'] === '/languages');

ahs_test_section('Activation next to WP Sitemap Page (rolled back afterwards)');
$legacy_active = is_plugin_active('wp-sitemap-page/wp-sitemap-page.php');
echo '  (wp-sitemap-page is ' . ($legacy_active ? 'active' : 'inactive') . ")\n";
if (is_plugin_active(AHS_TEST_PLUGIN_BASENAME)) {
	ahs_test_assert('plugin is already active on the site', true);
} else {
	ob_start();
	$result = activate_plugin(AHS_TEST_PLUGIN_BASENAME);
	$output = ob_get_clean();
	ahs_test_assert('activate_plugin() returns no error', !is_wp_error($result), is_wp_error($result) ? $result->get_error_message() : '');
	ahs_test_assert('activation prints no unexpected output', $output === '', $output);
	ahs_test_assert('plugin is active after activation', is_plugin_active(AHS_TEST_PLUGIN_BASENAME));
}
if ($legacy_active) {
	ahs_test_assert('WP Sitemap Page is still active', is_plugin_active('wp-sitemap-page/wp-sitemap-page.php'));
	ahs_test_assert(
		'legacy shortcode [wp_sitemap_page] still belongs to WP Sitemap Page',
		($GLOBALS['shortcode_tags']['wp_sitemap_page'] ?? null) === 'wsp_wp_sitemap_page_main_func'
	);
}

ahs_test_done();
