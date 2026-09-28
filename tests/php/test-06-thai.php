<?php
/**
 * Phase 6: Thai — translations (PHP and editor), dates with Thai month names and
 * the Buddhist Era, renamed sections, Thai permalinks and typography.
 *
 * @package Accessible_HTML_Sitemap
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../fixtures.php';
require_once ABSPATH . 'wp-admin/includes/template.php';
require_once ABSPATH . WPINC . '/pomo/po.php'; // WordPress loads only the .mo reader by default.

$f = ahs_test_create_fixtures();
ahs_test_apply_settings();
ahs_test_a11y_reset('06');

wp_set_current_user($f['admin']);
$f['post_dated'] = wp_insert_post(array(
	'post_type' => 'post',
	'post_status' => 'publish',
	'post_title' => 'Demo Dated Post',
	'post_date' => '2026-09-27 10:00:00',
	'post_category' => array($f['cat_a']),
));
$f['post_untitled'] = wp_insert_post(array(
	'post_type' => 'post',
	'post_status' => 'publish',
	'post_title' => '',
	'post_content' => 'Untitled on purpose.',
	'post_category' => array($f['cat_b']),
));
$f['page_thai'] = wp_insert_post(array(
	'post_type' => 'page',
	'post_status' => 'publish',
	'post_title' => 'หน้าทดสอบภาษาไทยที่มีชื่อยาวมากโดยไม่มีช่องว่างเลยสักแห่งเพื่อทดสอบการตัดบรรทัด',
	'post_name' => 'หน้าทดสอบ',
));
wp_set_current_user(0);

ahs_test_section('Translation files');
$po = new PO();
$po->import_from_file(AHS_TEST_PLUGIN_DIR . '/languages/accessible-html-sitemap-th.po');
$untranslated = array();
foreach ($po->entries as $entry) {
	if (!isset($entry->translations[0]) || trim($entry->translations[0]) === '') {
		$untranslated[] = $entry->singular;
	}
}
ahs_test_assert('every string in accessible-html-sitemap-th.po is translated (' . count($po->entries) . ')', count($po->entries) > 50 && !$untranslated, implode(' | ', $untranslated));
$pot = new PO();
$pot->import_from_file(AHS_TEST_PLUGIN_DIR . '/languages/accessible-html-sitemap.pot');
ahs_test_assert('.po has every string of the .pot (run npm run i18n after changing strings)', !array_diff(array_keys($pot->entries), array_keys($po->entries)), implode(' | ', array_diff(array_keys($pot->entries), array_keys($po->entries))));
ahs_test_assert('.mo is not older than the .po', filemtime(AHS_TEST_PLUGIN_DIR . '/languages/accessible-html-sitemap-th.mo') >= filemtime(AHS_TEST_PLUGIN_DIR . '/languages/accessible-html-sitemap-th.po'));

ahs_test_section('Thai front end');
ahs_test_assert('switch to Thai (WordPress Thai language pack installed)', switch_to_locale('th'));
$html = Accessible_Sitemap_Renderer::render();
$m = ahs_test_markup($html);
ahs_test_assert('landmark label is Thai', $m['navs'] === array('แผนผังเว็บไซต์'), implode(' | ', $m['navs']));
foreach (array('ในหน้านี้:', '>หน้าเว็บไซต์</h2>', '>เรื่องตามหมวดหมู่</h2>', 'หมวดหมู่: <a', sprintf('#%d (ไม่มีชื่อเรื่อง)', $f['post_untitled'])) as $needle) {
	ahs_test_assert("contains “{$needle}”", strpos($html, $needle) !== false);
}
$m = ahs_test_markup(Accessible_Sitemap_Renderer::render(array('only' => 'page')));
ahs_test_assert('single-section label is Thai', $m['navs'] === array('แผนผังเว็บไซต์: หน้าเว็บไซต์'), implode(' | ', $m['navs']));
ahs_test_write_a11y_page('06-th-default', $html, '<h1>แผนผังเว็บไซต์</h1>', 'th');

ahs_test_section('Dates');
update_option('date_format', 'j F Y');
$template = '<a href="{permalink}">{title}</a> ({date}) {year}';
ahs_test_apply_settings(array('post_template' => $template));
delete_option('accessible_sitemap_buddhist_era'); // Not set: follows the site language.
$html = Accessible_Sitemap_Renderer::render(array('only' => 'post'));
ahs_test_assert('Thai site: Buddhist Era by default → (27 กันยายน 2569) 2569', strpos($html, 'Demo Dated Post</a> (27 กันยายน 2569) 2569') !== false);
ahs_test_write_a11y_page('06-th-dates', $html, '<h1>แผนผังเว็บไซต์</h1>', 'th');
add_option('accessible_sitemap_buddhist_era', false); // Stored as it is after unticking it in the settings.
ahs_test_assert('Buddhist Era off → (27 กันยายน 2026) 2026', strpos(Accessible_Sitemap_Renderer::render(array('only' => 'post')), 'Demo Dated Post</a> (27 กันยายน 2026) 2026') !== false);
ahs_test_assert('Buddhist Era off: archive list "กันยายน 2026"', strpos(Accessible_Sitemap_Renderer::render(array('only' => 'archive')), 'กันยายน 2026</a>') !== false);
update_option('accessible_sitemap_buddhist_era', true);
$archives = Accessible_Sitemap_Renderer::render(array('only' => 'archive'));
ahs_test_assert(
	'archive list: Buddhist Era in the link text, the real year in the URL',
	strpos($archives, 'กันยายน 2026</a>') === false && (bool) preg_match('#href="[^"]*(?:/2026/09|m=202609)[^"]*"[^>]*>กันยายน 2569</a>#u', $archives),
	$archives
);
foreach (array('d/m/y' => '27/09/69', 'j F Y \\Y' => '27 กันยายน 2569 Y', 'F j, Y' => 'กันยายน 27, 2569') as $format => $expected) {
	update_option('date_format', $format);
	ahs_test_assert("date format \"{$format}\" → {$expected}", strpos(Accessible_Sitemap_Renderer::render(array('only' => 'post')), "({$expected})") !== false);
}
restore_previous_locale();
update_option('date_format', 'F j, Y');
delete_option('accessible_sitemap_buddhist_era');
ahs_test_assert(
	'English site: Gregorian year by default → (September 27, 2026) 2026',
	strpos(Accessible_Sitemap_Renderer::render(array('only' => 'post')), 'Demo Dated Post</a> (September 27, 2026) 2026') !== false
);
ahs_test_assert('English site: English labels', strpos(Accessible_Sitemap_Renderer::render(), 'aria-label="Sitemap"') !== false);
ahs_test_apply_settings();

ahs_test_section('Renamed sections');
$m = ahs_test_markup($html = do_shortcode('[accessible_sitemap pages_title="หน้าหลัก" posts_title="ข่าว <b>ล่าสุด</b>"]'));
ahs_test_assert('pages_title renames the heading and its jump link', substr_count($html, '>หน้าหลัก</') === 2);
ahs_test_assert('HTML in a title is removed', strpos($html, '>ข่าว ล่าสุด</') !== false && strpos($html, '<b>') === false);
$m = ahs_test_markup(do_shortcode('[accessible_sitemap only="page" pages_title="หน้าหลัก"]'));
ahs_test_assert('single-section label uses the new title', $m['navs'] === array('Sitemap: หน้าหลัก'), implode(' | ', $m['navs']));
$block = do_blocks('<!-- wp:accessible-html-sitemap/sitemap {"sectionTitles":{"page":"หน้าหลัก","author":"ผู้เขียนข่าว"}} /-->');
ahs_test_assert('block sectionTitles renames sections', substr_count($block, '>หน้าหลัก</') === 2);

ahs_test_section('Editor translations');
$handle = generate_block_asset_handle('accessible-html-sitemap/sitemap', 'editorScript');
$script = wp_scripts()->registered[$handle] ?? null;
ahs_test_assert('editor script uses the accessible-html-sitemap text domain', $script && $script->textdomain === 'accessible-html-sitemap');
ahs_test_assert('editor script reads translations from languages/', $script && wp_normalize_path((string) $script->translations_path) === wp_normalize_path(AHS_TEST_PLUGIN_DIR . '/languages'));
switch_to_locale('th');
$json = load_script_textdomain($handle, 'accessible-html-sitemap', AHS_TEST_PLUGIN_DIR . '/languages');
ahs_test_assert('Thai JSON is found for build/sitemap/index.js', is_string($json) && strpos($json, json_encode('ทั้งหมด (แผนผังเว็บไซต์แบบเต็ม)')) !== false, is_string($json) ? substr($json, 0, 80) : var_export($json, true));
ahs_test_assert('plugin description is translated', __('Accessible (WCAG 2.1 AA) HTML sitemap with Thai language support. Use the Accessible HTML Sitemap block or the [accessible_sitemap] shortcode.', 'accessible-html-sitemap') !== 'Accessible (WCAG 2.1 AA) HTML sitemap with Thai language support. Use the Accessible HTML Sitemap block or the [accessible_sitemap] shortcode.');

ahs_test_section('Thai settings page');
wp_set_current_user($f['admin']);
ob_start();
Accessible_Sitemap_Settings::render_page();
$page = ob_get_clean();
ahs_test_assert('settings page is Thai', strpos($page, 'แสดงปีเป็นพุทธศักราช (พ.ศ.)') !== false && strpos($page, 'ซ่อนจากแผนผังเว็บไซต์แบบเต็ม') !== false);
ahs_test_write_a11y_page('06-th-settings', $page, '', 'th');
wp_set_current_user(0);
restore_previous_locale();

ahs_test_section('Thai permalinks and typography');
$hrefs = ahs_test_hrefs(Accessible_Sitemap_Renderer::render(array('only' => 'page')));
$thai_link = get_permalink($f['page_thai']);
ahs_test_assert('Thai slug link is listed', in_array($thai_link, $hrefs, true), $thai_link);
ahs_test_assert('Thai slug is percent-encoded in href', (bool) preg_match('/^[\x21-\x7e]+$/', $thai_link) && stripos($thai_link, '%e0%b8') !== false, $thai_link);
$css = (string) file_get_contents(AHS_TEST_PLUGIN_DIR . '/build/sitemap/style-index.css');
ahs_test_assert('stylesheet: line-height 1.6 for Thai marks', strpos($css, 'line-height:1.6') !== false);
ahs_test_assert('stylesheet: long words without spaces can wrap', strpos($css, 'overflow-wrap:anywhere') !== false);
ahs_test_assert('stylesheet: no font-family (theme font is kept)', stripos($css, 'font-family') === false);

ahs_test_done();
