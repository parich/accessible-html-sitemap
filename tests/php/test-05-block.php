<?php
/**
 * Phase 5: the accessible-html-sitemap/sitemap block — attributes, editor preview endpoint, and the
 * mapping of block attributes to the renderer.
 *
 * @package Accessible_HTML_Sitemap
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../fixtures.php';

$f = ahs_test_create_fixtures();
ahs_test_apply_settings();
ahs_test_a11y_reset('05');

$block = function ($attributes = array()) {
	$json = $attributes ? ' ' . wp_json_encode($attributes) : '';
	return do_blocks("<!-- wp:accessible-html-sitemap/sitemap{$json} /-->");
};

ahs_test_section('Block type');
$type = WP_Block_Type_Registry::get_instance()->get_registered('accessible-html-sitemap/sitemap');
$expected_defaults = array(
	'only' => '',
	'sort' => '',
	'order' => '',
	'displayTitle' => true,
	'displayCategoryTitleWording' => true,
	'headingLevel' => 2,
	'showToc' => true,
	'onlyPrivate' => false,
);
foreach ($expected_defaults as $name => $default) {
	ahs_test_assert("attribute {$name} (default " . var_export($default, true) . ')', isset($type->attributes[$name]) && $type->attributes[$name]['default'] === $default);
}
ahs_test_assert('supports wide/full alignment and spacing', ($type->supports['align'] ?? null) === array('wide', 'full') && !empty($type->supports['spacing']['margin']));
ahs_test_assert('description is not the scaffold text', strpos((string) $type->description, 'scaffolded') === false && $type->description !== '');

ahs_test_section('Editor data');
accessible_sitemap_editor_data();
$handle = generate_block_asset_handle('accessible-html-sitemap/sitemap', 'editorScript');
$inline = implode("\n", (array) wp_scripts()->get_data($handle, 'before'));
ahs_test_assert('editor script receives the public content types and taxonomies', strpos($inline, 'window.accessibleSitemapEditor') !== false && strpos($inline, '"ahs_test_book"') !== false && strpos($inline, '"ahs_test_genre"') !== false, $inline);
ahs_test_assert('hooked to enqueue_block_editor_assets', (bool) has_action('enqueue_block_editor_assets', 'accessible_sitemap_editor_data'));

ahs_test_section('Editor preview (REST block renderer)');
$preview = function ($attributes) {
	$request = new WP_REST_Request('GET', '/wp/v2/block-renderer/accessible-html-sitemap/sitemap');
	$request->set_param('context', 'edit');
	$request->set_param('attributes', $attributes);
	return rest_do_request($request);
};
$editor = wp_insert_user(array('user_login' => 'ahs_test_editor', 'user_pass' => wp_generate_password(), 'role' => 'editor'));
wp_set_current_user($editor);
$response = $preview(array('only' => 'page'));
$data = $response->get_data();
ahs_test_assert('editor gets HTTP 200', $response->get_status() === 200, (string) $response->get_status());
ahs_test_assert(
	'preview lists the same links as [accessible_sitemap only="page"]',
	isset($data['rendered']) && ahs_test_hrefs($data['rendered']) === ahs_test_hrefs(Accessible_Sitemap_Renderer::render(array('only' => 'page')))
);
ahs_test_assert('invalid attribute type is rejected by the endpoint (400)', $preview(array('headingLevel' => 'abc'))->get_status() === 400);
wp_set_current_user(0);
ahs_test_assert('visitor cannot use the preview endpoint', in_array($preview(array())->get_status(), array(401, 403), true));

ahs_test_section('Block attributes → sitemap');
$html = $block();
ahs_test_assert('default block lists the same links as the shortcode', ahs_test_hrefs($html) === ahs_test_hrefs(do_shortcode('[accessible_sitemap]')));
$wrapper = new WP_HTML_Tag_Processor($html);
$wrapper->next_tag();
ahs_test_assert(
	'wrapper is the labelled <nav> with the block class',
	$wrapper->get_tag() === 'NAV' && $wrapper->has_class('wp-block-accessible-html-sitemap-sitemap') && $wrapper->has_class('accessible-sitemap') && $wrapper->get_attribute('aria-label') === 'Sitemap',
	substr($html, 0, 120)
);
ahs_test_write_a11y_page('05-block-default', $html);

$html = $block(array('headingLevel' => 3));
$m = ahs_test_markup($html);
ahs_test_assert('headingLevel 3 → h3 headings', $m['headings'] && array_unique($m['headings']) === array(3));
ahs_test_write_a11y_page('05-block-h3', $html, '<h1>University</h1><h2>Sitemap</h2>');

$m = ahs_test_markup($block(array('displayTitle' => false)));
ahs_test_assert('displayTitle false → no headings, no jump links', !$m['headings'] && !$m['fragment_links']);
$m = ahs_test_markup($block(array('showToc' => false)));
ahs_test_assert('showToc false → headings without jump links', $m['headings'] && !$m['fragment_links']);
ahs_test_assert('displayCategoryTitleWording false → no "Category:"', strpos($block(array('only' => 'post', 'displayCategoryTitleWording' => false)), 'Category:') === false);
ahs_test_assert(
	'only/sort/order match the shortcode',
	ahs_test_hrefs($block(array('only' => 'post', 'sort' => 'post_name', 'order' => 'ASC'))) === ahs_test_hrefs(do_shortcode('[accessible_sitemap only="post" sort="post_name" order="ASC"]'))
);
ahs_test_assert('onlyPrivate: visitor sees nothing', trim($block(array('only' => 'page', 'onlyPrivate' => true))) === '');
wp_set_current_user($f['admin']);
ahs_test_assert('onlyPrivate: administrator sees the private page', in_array(get_permalink($f['page_private']), ahs_test_hrefs($block(array('only' => 'page', 'onlyPrivate' => true))), true));
wp_set_current_user(0);

$html = $block(array('align' => 'wide', 'style' => array('spacing' => array('padding' => array('top' => '2rem')))));
ahs_test_assert('alignment class is applied', strpos($html, 'alignwide') !== false);
ahs_test_assert('spacing style is applied', strpos($html, 'padding-top:2rem') !== false, substr($html, 0, 200));

ahs_test_section('Attributes of the wrong type');
$html = $block(array('headingLevel' => 'abc', 'displayTitle' => 'yes', 'only' => 7, 'showToc' => 'no'));
$m = ahs_test_markup($html);
ahs_test_assert('falls back to the defaults (same links as the default block)', ahs_test_hrefs($html) === ahs_test_hrefs($block()));
ahs_test_assert('falls back to h2 headings with jump links', array_unique($m['headings']) === array(2) && $m['fragment_links']);

ahs_test_done();
