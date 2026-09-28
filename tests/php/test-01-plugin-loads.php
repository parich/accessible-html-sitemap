<?php
/**
 * Phase 1: the plugin loads, registers its block and activates next to WP Sitemap Page.
 *
 * @package Parich_Accessible_Sitemap
 */

require __DIR__ . '/../bootstrap.php';

pas_test_section('Plugin loads');
pas_test_assert('main plugin function is defined', function_exists('parich_sitemap_register_block'));

$block = WP_Block_Type_Registry::get_instance()->get_registered('parich-accessible-sitemap/sitemap');
pas_test_assert('block parich-accessible-sitemap/sitemap is registered', $block instanceof WP_Block_Type);
pas_test_assert('block has a server-side render callback', $block && is_callable($block->render_callback));
// Core reads textdomain from the metadata to translate title/description; it is not copied onto WP_Block_Type.
$manifest = require PAS_TEST_PLUGIN_DIR . '/build/blocks-manifest.php';
pas_test_assert(
	'block metadata uses the parich-accessible-sitemap text domain',
	($manifest['sitemap']['textdomain'] ?? null) === 'parich-accessible-sitemap'
);
pas_test_assert('rendered by parich_sitemap_render_block (returns the markup; no render.php template echoing it)', 'parich_sitemap_render_block' === $block->render_callback && !isset($manifest['sitemap']['render']) && !file_exists(PAS_TEST_PLUGIN_DIR . '/build/sitemap/render.php'));

pas_test_section('Plugin header');
$header = get_plugin_data(PAS_TEST_PLUGIN_FILE, false, false);
pas_test_assert('Name is "Parich Accessible Sitemap"', $header['Name'] === 'Parich Accessible Sitemap', $header['Name']);
pas_test_assert('Version is 1.0.0', $header['Version'] === '1.0.0', $header['Version']);
pas_test_assert('Text Domain is parich-accessible-sitemap', $header['TextDomain'] === 'parich-accessible-sitemap');
$main = (string) file_get_contents(PAS_TEST_PLUGIN_FILE);
pas_test_assert(
	'translations from translate.wordpress.org: no Domain Path, load_plugin_textdomain() or custom script translation path',
	'' === $header['DomainPath'] && false === strpos($main, 'load_plugin_textdomain') && false === strpos($main, 'wp_set_script_translations')
);

pas_test_section('Activation next to WP Sitemap Page (rolled back afterwards)');
$legacy_active = is_plugin_active('wp-sitemap-page/wp-sitemap-page.php');
echo '  (wp-sitemap-page is ' . ($legacy_active ? 'active' : 'inactive') . ")\n";
if (is_plugin_active(PAS_TEST_PLUGIN_BASENAME)) {
	pas_test_assert('plugin is already active on the site', true);
} else {
	ob_start();
	$result = activate_plugin(PAS_TEST_PLUGIN_BASENAME);
	$output = ob_get_clean();
	pas_test_assert('activate_plugin() returns no error', !is_wp_error($result), is_wp_error($result) ? $result->get_error_message() : '');
	pas_test_assert('activation prints no unexpected output', $output === '', $output);
	pas_test_assert('plugin is active after activation', is_plugin_active(PAS_TEST_PLUGIN_BASENAME));
}
if ($legacy_active) {
	pas_test_assert('WP Sitemap Page is still active', is_plugin_active('wp-sitemap-page/wp-sitemap-page.php'));
	pas_test_assert(
		'legacy shortcode [wp_sitemap_page] still belongs to WP Sitemap Page',
		($GLOBALS['shortcode_tags']['wp_sitemap_page'] ?? null) === 'wsp_wp_sitemap_page_main_func'
	);
}

pas_test_done();
