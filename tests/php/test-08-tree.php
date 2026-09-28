<?php
/**
 * Phase 9: tree display (default) and list style, collapsible categories.
 * Pages written to tests/a11y/out/08-*.html are checked by axe (npm run test:a11y)
 * and for reflow at 320px in a real browser (npm run test:reflow).
 *
 * @package Parich_Accessible_Sitemap
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../fixtures.php';

$f = pas_test_create_fixtures();
pas_test_apply_settings();
pas_test_a11y_reset('08');

wp_set_current_user($f['admin']);
wp_insert_post(array(
	'post_type' => 'post',
	'post_status' => 'publish',
	'post_title' => 'การแข่งขันกีฬามหาวิทยาลัยแห่งประเทศไทยครั้งที่ห้าสิบเอ็ดณสนามกีฬากลางจังหวัดมหาสารคามชื่อยาวไม่มีช่องว่าง',
	'post_category' => array($f['cat_a1a']),
));
wp_set_current_user(0);

$nav = function ($html) {
	$processor = new WP_HTML_Tag_Processor($html);
	$processor->next_tag('nav');
	return $processor;
};

/**
 * Collapsible categories: one entry per <details>.
 */
$details = function ($html) {
	$doc = new DOMDocument();
	libxml_use_internal_errors(true);
	$doc->loadHTML('<?xml encoding="utf-8" ?><div>' . $html . '</div>');
	libxml_clear_errors();
	$xpath = new DOMXPath($doc);
	$result = array();
	foreach ($xpath->query('//li[contains(concat(" ", normalize-space(@class), " "), " parich-sitemap__category ")]') as $li) {
		$detail = $xpath->query('./details', $li)->item(0);
		$first = $detail ? $xpath->query('./ul/li[1]', $detail)->item(0) : null;
		$result[] = array(
			'details' => (bool) $detail,
			'open' => $detail && $detail->hasAttribute('open'),
			'summary' => $detail ? trim($xpath->evaluate('string(./summary)', $detail)) : '',
			'links_in_summary' => $detail ? $xpath->query('./summary//a', $detail)->length : 0,
			'first_class' => $first ? $first->getAttribute('class') : '',
			'first_href' => $first ? $xpath->evaluate('string(./a/@href)', $first) : '',
			'first_text' => $first ? trim($first->textContent) : '',
		);
	}
	return $result;
};

pas_test_section('Tree is the default, List is a style');
$type = WP_Block_Type_Registry::get_instance()->get_registered('parich-accessible-sitemap/sitemap');
$styles = array();
foreach ((array) $type->styles as $style) {
	$styles[$style['name']] = !empty($style['isDefault']);
}
pas_test_assert('block styles: Tree (default) and List', $styles === array('tree' => true, 'list' => false), wp_json_encode($type->styles));
$html = do_shortcode('[parich_sitemap]');
pas_test_assert('shortcode: tree by default (no is-style-list)', !$nav($html)->has_class('is-style-list') && $nav($html)->has_class('parich-sitemap'));
pas_test_write_a11y_page('08-tree-default', $html);
$html = do_shortcode('[parich_sitemap style=" LIST "]');
pas_test_assert('shortcode style="list" → is-style-list', $nav($html)->has_class('is-style-list'));
pas_test_write_a11y_page('08-list-style', $html);
pas_test_assert('block: tree by default', !$nav(do_blocks('<!-- wp:parich-accessible-sitemap/sitemap /-->'))->has_class('is-style-list'));
pas_test_assert('block "List" style → is-style-list', $nav(do_blocks('<!-- wp:parich-accessible-sitemap/sitemap {"className":"is-style-list"} /-->'))->has_class('is-style-list'));
$css = (string) file_get_contents(PAS_TEST_PLUGIN_DIR . '/build/sitemap/style-index.css');
pas_test_assert('stylesheet draws the tree unless is-style-list', strpos($css, '.parich-sitemap:not(.is-style-list) .parich-sitemap__section li:before') !== false && strpos($css, 'border-inline-start:1px solid currentcolor') !== false);
pas_test_assert('stylesheet has no role/ARIA tricks: markup is unchanged', pas_test_hrefs(do_shortcode('[parich_sitemap]')) === pas_test_hrefs(do_shortcode('[parich_sitemap style="list"]')));

pas_test_section('Collapsible categories');
$plain = Parich_Sitemap_Renderer::render(array('only' => 'post'));
$open = Parich_Sitemap_Renderer::render(array('only' => 'post', 'collapsible' => 'true'));
$d = $details($open);
$all_ok = count($d) > 3;
foreach ($d as $category) {
	$all_ok = $all_ok && $category['details'] && $category['open'] && $category['links_in_summary'] === 0
		&& $category['first_class'] === 'parich-sitemap__category-link' && strpos($category['first_text'], 'All posts in ') === 0;
}
pas_test_assert('every category is an open <details>, summary without links, category link first', $all_ok, wp_json_encode($d, JSON_UNESCAPED_UNICODE));
pas_test_assert('summary shows the name and the number of posts', (bool) preg_match('/^Category: Demo Cat B \(\d+ posts?\)$/', $d[array_search('Category: Demo Cat B', array_map(function ($c) {
	return preg_replace('/ \(.*$/', '', $c['summary']);
}, $d), true)]['summary'] ?? ''));
pas_test_assert('same links in the same order as without collapsing', pas_test_hrefs($open) === pas_test_hrefs($plain));
$a1 = current(array_filter($d, function ($c) {
	return strpos($c['summary'], 'Category: Demo Cat A1 ') === 0;
}));
pas_test_assert('a category with only sub-categories counts their posts ("Demo Cat A1" is not "0 posts")', $a1 && !preg_match('/\(0 posts\)$/', $a1['summary']), $a1 ? $a1['summary'] : 'not found');
pas_test_write_a11y_page('08-collapsible-open', $open);

$closed = Parich_Sitemap_Renderer::render(array('only' => 'post', 'collapsible' => 'closed'));
pas_test_assert('collapsible="closed" starts folded', !array_filter(array_column($details($closed), 'open')) && count($details($closed)) === count($d));
pas_test_write_a11y_page('08-collapsible-closed', $closed);
pas_test_assert('no collapsing by default', !array_filter(array_column($details($plain), 'details')));
pas_test_assert('unknown collapsible value: no collapsing', !array_filter(array_column($details(Parich_Sitemap_Renderer::render(array('only' => 'post', 'collapsible' => 'sideways'))), 'details')));
$no_prefix = $details(Parich_Sitemap_Renderer::render(array('only' => 'post', 'collapsible' => 'true', 'display_category_title_wording' => 'false')));
pas_test_assert('display_category_title_wording=false also applies to the summary', strpos($no_prefix[0]['summary'], 'Category:') === false);

$limited = Parich_Sitemap_Renderer::render(array('only' => 'post', 'collapsible' => 'true', 'posts_per_category' => '1'));
pas_test_assert('with a per-category limit: same links as without collapsing', pas_test_hrefs($limited) === pas_test_hrefs(Parich_Sitemap_Renderer::render(array('only' => 'post', 'posts_per_category' => '1'))));
pas_test_write_a11y_page('08-collapsible-limit', $limited);

$block = do_blocks('<!-- wp:parich-accessible-sitemap/sitemap {"only":"post","collapsible":"closed"} /-->');
pas_test_assert('block collapsible "closed"', count($details($block)) === count($d) && !array_filter(array_column($details($block), 'open')));
pas_test_assert('block collapsible with a value outside the list: no collapsing', !array_filter(array_column($details(do_blocks('<!-- wp:parich-accessible-sitemap/sitemap {"only":"post","collapsible":"yes"} /-->')), 'details')));

pas_test_section('Thai');
switch_to_locale('th');
$html = Parich_Sitemap_Renderer::render(array('collapsible' => 'true'));
pas_test_assert('summary, count and category link in Thai', (bool) preg_match('/หมวดหมู่: Demo Cat B<\/span> <span class="parich-sitemap__count">\(\d+ เรื่อง\)/u', $html) && strpos($html, 'เรื่องทั้งหมดในหมวด Demo Cat B') !== false);
pas_test_write_a11y_page('08-tree-th', Parich_Sitemap_Renderer::render(), '<h1>แผนผังเว็บไซต์</h1>', 'th');
pas_test_write_a11y_page('08-collapsible-th', $html, '<h1>แผนผังเว็บไซต์</h1>', 'th');
restore_previous_locale();

pas_test_done();
