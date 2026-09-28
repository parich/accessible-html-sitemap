<?php
/**
 * Phase 6: Thai — translations (PHP and editor), dates with Thai month names and
 * the Buddhist Era, renamed sections, Thai permalinks and typography.
 *
 * @package Parich_Accessible_Sitemap
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../fixtures.php';
require_once ABSPATH . 'wp-admin/includes/template.php';
require_once ABSPATH . WPINC . '/pomo/po.php'; // WordPress loads only the .mo reader by default.

$f = pas_test_create_fixtures();
pas_test_apply_settings();
pas_test_a11y_reset('06');

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

pas_test_section('Translation files');
$po = new PO();
$po->import_from_file(PAS_TEST_PLUGIN_DIR . '/languages/parich-accessible-sitemap-th.po');
$untranslated = array();
foreach ($po->entries as $entry) {
	if (!isset($entry->translations[0]) || trim($entry->translations[0]) === '') {
		$untranslated[] = $entry->singular;
	}
}
pas_test_assert('every string in parich-accessible-sitemap-th.po is translated (' . count($po->entries) . ')', count($po->entries) > 50 && !$untranslated, implode(' | ', $untranslated));
$pot = new PO();
$pot->import_from_file(PAS_TEST_PLUGIN_DIR . '/languages/parich-accessible-sitemap.pot');
pas_test_assert('.po has every string of the .pot (run npm run i18n after changing strings)', !array_diff(array_keys($pot->entries), array_keys($po->entries)), implode(' | ', array_diff(array_keys($pot->entries), array_keys($po->entries))));
pas_test_assert('.mo is not older than the .po', filemtime(PAS_TEST_PLUGIN_DIR . '/languages/parich-accessible-sitemap-th.mo') >= filemtime(PAS_TEST_PLUGIN_DIR . '/languages/parich-accessible-sitemap-th.po'));

pas_test_section('Thai front end');
pas_test_assert('switch to Thai (WordPress Thai language pack installed)', switch_to_locale('th'));
$html = Parich_Sitemap_Renderer::render();
$m = pas_test_markup($html);
pas_test_assert('landmark label is Thai', $m['navs'] === array('แผนผังเว็บไซต์'), implode(' | ', $m['navs']));
foreach (array('ในหน้านี้:', '>หน้าเว็บไซต์</h2>', '>เรื่องตามหมวดหมู่</h2>', 'หมวดหมู่: <a', sprintf('#%d (ไม่มีชื่อเรื่อง)', $f['post_untitled'])) as $needle) {
	pas_test_assert("contains “{$needle}”", strpos($html, $needle) !== false);
}
$m = pas_test_markup(Parich_Sitemap_Renderer::render(array('only' => 'page')));
pas_test_assert('single-section label is Thai', $m['navs'] === array('แผนผังเว็บไซต์: หน้าเว็บไซต์'), implode(' | ', $m['navs']));
pas_test_write_a11y_page('06-th-default', $html, '<h1>แผนผังเว็บไซต์</h1>', 'th');

pas_test_section('Dates');
update_option('date_format', 'j F Y');
$template = '<a href="{permalink}">{title}</a> ({date}) {year}';
pas_test_apply_settings(array('post_template' => $template));
delete_option('parich_sitemap_buddhist_era'); // Not set: follows the site language.
$html = Parich_Sitemap_Renderer::render(array('only' => 'post'));
pas_test_assert('Thai site: Buddhist Era by default → (27 กันยายน 2569) 2569', strpos($html, 'Demo Dated Post</a> (27 กันยายน 2569) 2569') !== false);
pas_test_write_a11y_page('06-th-dates', $html, '<h1>แผนผังเว็บไซต์</h1>', 'th');
add_option('parich_sitemap_buddhist_era', false); // Stored as it is after unticking it in the settings.
pas_test_assert('Buddhist Era off → (27 กันยายน 2026) 2026', strpos(Parich_Sitemap_Renderer::render(array('only' => 'post')), 'Demo Dated Post</a> (27 กันยายน 2026) 2026') !== false);
pas_test_assert('Buddhist Era off: archive list "กันยายน 2026"', strpos(Parich_Sitemap_Renderer::render(array('only' => 'archive')), 'กันยายน 2026</a>') !== false);
update_option('parich_sitemap_buddhist_era', true);
$archives = Parich_Sitemap_Renderer::render(array('only' => 'archive'));
pas_test_assert(
	'archive list: Buddhist Era in the link text, the real year in the URL',
	strpos($archives, 'กันยายน 2026</a>') === false && (bool) preg_match('#href="[^"]*(?:/2026/09|m=202609)[^"]*"[^>]*>กันยายน 2569</a>#u', $archives),
	$archives
);
foreach (array('d/m/y' => '27/09/69', 'j F Y \\Y' => '27 กันยายน 2569 Y', 'F j, Y' => 'กันยายน 27, 2569') as $format => $expected) {
	update_option('date_format', $format);
	pas_test_assert("date format \"{$format}\" → {$expected}", strpos(Parich_Sitemap_Renderer::render(array('only' => 'post')), "({$expected})") !== false);
}
restore_previous_locale();
update_option('date_format', 'F j, Y');
delete_option('parich_sitemap_buddhist_era');
pas_test_assert(
	'English site: Gregorian year by default → (September 27, 2026) 2026',
	strpos(Parich_Sitemap_Renderer::render(array('only' => 'post')), 'Demo Dated Post</a> (September 27, 2026) 2026') !== false
);
pas_test_assert('English site: English labels', strpos(Parich_Sitemap_Renderer::render(), 'aria-label="Sitemap"') !== false);
pas_test_apply_settings();

pas_test_section('Renamed sections');
$m = pas_test_markup($html = do_shortcode('[parich_sitemap pages_title="หน้าหลัก" posts_title="ข่าว <b>ล่าสุด</b>"]'));
pas_test_assert('pages_title renames the heading and its jump link', substr_count($html, '>หน้าหลัก</') === 2);
pas_test_assert('HTML in a title is removed', strpos($html, '>ข่าว ล่าสุด</') !== false && strpos($html, '<b>') === false);
$m = pas_test_markup(do_shortcode('[parich_sitemap only="page" pages_title="หน้าหลัก"]'));
pas_test_assert('single-section label uses the new title', $m['navs'] === array('Sitemap: หน้าหลัก'), implode(' | ', $m['navs']));
$block = do_blocks('<!-- wp:parich-accessible-sitemap/sitemap {"sectionTitles":{"page":"หน้าหลัก","author":"ผู้เขียนข่าว"}} /-->');
pas_test_assert('block sectionTitles renames sections', substr_count($block, '>หน้าหลัก</') === 2);

pas_test_section('Editor translations');
$handle = generate_block_asset_handle('parich-accessible-sitemap/sitemap', 'editorScript');
$script = wp_scripts()->registered[$handle] ?? null;
pas_test_assert('editor script uses the parich-accessible-sitemap text domain', $script && $script->textdomain === 'parich-accessible-sitemap');
pas_test_assert('editor script translations come from language packs (no custom path)', $script && empty($script->translations_path));
switch_to_locale('th');
$json = load_script_textdomain($handle, 'parich-accessible-sitemap', PAS_TEST_PLUGIN_DIR . '/languages');
pas_test_assert('Thai JSON is found for build/sitemap/index.js', is_string($json) && strpos($json, json_encode('ทั้งหมด (แผนผังเว็บไซต์แบบเต็ม)')) !== false, is_string($json) ? substr($json, 0, 80) : var_export($json, true));
pas_test_assert('plugin description is translated', __('Accessible (WCAG 2.1 AA) HTML sitemap with Thai language support. Use the Parich Accessible Sitemap block or the [parich_sitemap] shortcode.', 'parich-accessible-sitemap') !== 'Accessible (WCAG 2.1 AA) HTML sitemap with Thai language support. Use the Parich Accessible Sitemap block or the [parich_sitemap] shortcode.');

pas_test_section('Thai settings page');
wp_set_current_user($f['admin']);
ob_start();
Parich_Sitemap_Settings::render_page();
$page = ob_get_clean();
pas_test_assert('settings page is Thai', strpos($page, 'แสดงปีเป็นพุทธศักราช (พ.ศ.)') !== false && strpos($page, 'ซ่อนจากแผนผังเว็บไซต์แบบเต็ม') !== false);
pas_test_write_a11y_page('06-th-settings', $page, '', 'th');
wp_set_current_user(0);
restore_previous_locale();

pas_test_section('Thai permalinks and typography');
$hrefs = pas_test_hrefs(Parich_Sitemap_Renderer::render(array('only' => 'page')));
$thai_link = get_permalink($f['page_thai']);
pas_test_assert('Thai slug link is listed', in_array($thai_link, $hrefs, true), $thai_link);
pas_test_assert('Thai slug is percent-encoded in href', (bool) preg_match('/^[\x21-\x7e]+$/', $thai_link) && stripos($thai_link, '%e0%b8') !== false, $thai_link);
$css = (string) file_get_contents(PAS_TEST_PLUGIN_DIR . '/build/sitemap/style-index.css');
pas_test_assert('stylesheet: line-height 1.6 for Thai marks', strpos($css, 'line-height:1.6') !== false);
pas_test_assert('stylesheet: long words without spaces can wrap', strpos($css, 'overflow-wrap:anywhere') !== false);
pas_test_assert('stylesheet: no font-family (theme font is kept)', stripos($css, 'font-family') === false);

pas_test_done();
