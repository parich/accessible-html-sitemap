<?php
/**
 * Phase 11: Thai titles and names in dictionary order — a leading vowel (เ แ โ ใ ไ)
 * is read after the consonant it is written before — with PHP intl's Collator and
 * with the built-in fallback. Lists without Thai keep the database's order.
 *
 * @package Accessible_HTML_Sitemap
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../fixtures.php';

$f = ahs_test_create_fixtures();
ahs_test_apply_settings();
ahs_test_a11y_reset('09');

// Built fresh every time, so switching the collator or the filter shows at once.
add_filter('accessible_sitemap_use_cache', '__return_false');

$sort = function (array $names, $descending = false) {
	return Accessible_Sitemap_Thai_Sort::sort($names, function ($name) {
		return $name;
	}, $descending);
};
$without_intl = function (callable $callback) {
	add_filter('accessible_sitemap_collator', '__return_null');
	try {
		return $callback();
	} finally {
		remove_filter('accessible_sitemap_collator', '__return_null');
	}
};

/**
 * Link texts in document order, optionally only those in $only.
 */
$texts = function ($html, array $only = array()) {
	preg_match_all('/<a [^>]*>([^<]*)<\/a>/u', (string) $html, $matches);
	$texts = array_map(function ($text) {
		return html_entity_decode($text, ENT_QUOTES, 'UTF-8');
	}, $matches[1]);
	return $only ? array_values(array_intersect($texts, $only)) : $texts;
};
$show = function (array $list) {
	return implode(' | ', $list);
};

ahs_test_section('Sorter');
// Royal Institute dictionary order (and ICU's Thai rules): digits, Thai, then Latin.
$dictionary = array(
	'2024 report', 'กก', 'กะ', 'กัก', 'กา', 'ก่า', 'ก้า', 'กำ', 'กิน', 'เก', 'เก่ง', 'เก็บ', 'เกม', 'แก', 'แก้ว', 'โก', 'ใกล้', 'ไก่', 'ไกล',
	'ขนม', 'ข่าว ๒๕๖๙', 'ข่าวสาร', 'คน', 'จัน', 'จันทน์', 'จันทร์', 'ใจ', 'โต๊ะ', 'ปลา', 'ป่า', 'เปล', 'เปา', 'มหาวิทยาลัย', 'มหาสารคาม',
	'แมว', 'รถ', 'ฤดู', 'ฤๅษี', 'ลม', 'หน่วยงาน', 'หนังสือ', 'อ่าน', 'เอกสาร', 'ฮา', 'Apple', 'banana', 'Zebra',
);
$shuffled = $dictionary;
mt_srand(11);
shuffle($shuffled);
if (class_exists('Collator')) {
	$result = $sort($shuffled);
	ahs_test_assert('intl: dictionary order', $result === $dictionary, $show($result));
} else {
	echo "  [SKIP] intl: PHP has no intl extension here (the fallback is checked below)\n";
}
$result = $without_intl(function () use ($sort, $shuffled) {
	return $sort($shuffled);
});
ahs_test_assert('fallback (no intl): same dictionary order', $result === $dictionary, $show($result));
ahs_test_assert('descending', $sort(array('กา', 'เกม', 'ขนม')) === array('กา', 'เกม', 'ขนม') && $sort(array('ขนม', 'กา', 'เกม'), true) === array('ขนม', 'เกม', 'กา'));
ahs_test_assert('no Thai: order left as given', $sort(array('Zeta', 'alpha', 'Beta', '10', '9')) === array('Zeta', 'alpha', 'Beta', '10', '9'));

$item = function ($id, $name, $group = 0) {
	return (object) array('id' => $id, 'name' => $name, 'group' => $group);
};
$ids = function (array $items) {
	return wp_list_pluck($items, 'id');
};
$by_name = function ($item) {
	return $item->name;
};
$by_group = function ($item) {
	return $item->group;
};
ahs_test_assert('equal names keep the order they came in', $ids(Accessible_Sitemap_Thai_Sort::sort(array($item(1, 'กา'), $item(2, 'เกม'), $item(3, 'กา')), $by_name)) === array(1, 3, 2)
	&& $ids(Accessible_Sitemap_Thai_Sort::sort(array($item(3, 'กา'), $item(2, 'เกม'), $item(1, 'กา')), $by_name)) === array(3, 1, 2));
ahs_test_assert('group first (menu order), then the name', $ids(Accessible_Sitemap_Thai_Sort::sort(array($item(1, 'กา', 2), $item(2, 'ไก่', 0), $item(3, 'เกม', 0), $item(4, 'ขนม', 1)), $by_name, false, $by_group)) === array(3, 2, 4, 1));
ahs_test_assert('keys are not kept (a list comes back)', array_keys($sort(array(5 => 'เกม', 9 => 'กา'))) === array(0, 1));
add_filter('accessible_sitemap_thai_sort', '__return_false');
ahs_test_assert('filter accessible_sitemap_thai_sort=false: order left as given', $sort(array('เกม', 'กา')) === array('เกม', 'กา'));
remove_filter('accessible_sitemap_thai_sort', '__return_false');
$broken = array("\xE0\xB8", 'กา', "\xFF");
ahs_test_assert('text that is not valid UTF-8: no notice, nothing lost', count($sort($broken)) === 3 && count($without_intl(function () use ($sort, $broken) {
	return $sort($broken);
})) === 3);

ahs_test_section('Thai content in the sitemap');
wp_set_current_user($f['admin']);
$add_category = function ($name, $parent = 0) {
	$result = wp_insert_term($name, 'category', array('parent' => $parent));
	return (int) $result['term_id'];
};
$add_post = function ($title, $date, array $categories, array $tags = array()) {
	return wp_insert_post(array('post_type' => 'post', 'post_status' => 'publish', 'post_title' => $title, 'post_date' => $date, 'post_category' => $categories, 'tags_input' => $tags));
};
// Created in database order ≠ dictionary order, so the tests can tell them apart.
$sport = $add_category('กีฬา');
$farm = $add_category('เกษตร');
$cock = $add_category('ไก่ชน');
$transport = $add_category('ขนส่ง');
$football = $add_category('ฟุตบอล', $sport);
$tennis = $add_category('เทนนิส', $sport);
$badminton = $add_category('แบดมินตัน', $sport);
$add_post('ฟุตบอลโลก', '2025-04-01 09:00:00', array($football), array('ห้องสมุด'));
$add_post('เทนนิสเยาวชน', '2025-04-02 09:00:00', array($tennis), array('การเงิน'));
$add_post('แบดมินตันหญิง', '2025-04-03 09:00:00', array($badminton), array('เอกสาร'));
$add_post('สนามไก่', '2025-04-04 09:00:00', array($cock), array('แนะแนว'));
$add_post('รถไฟ', '2025-04-05 09:00:00', array($transport));
// Date order (newest first: เก็บเกี่ยว, ไถนา, ข้าวหอมมะลิ) differs from both title orders.
$add_post('ไถนา', '2025-03-02 09:00:00', array($farm));
$add_post('ข้าวหอมมะลิ', '2025-03-01 09:00:00', array($farm));
$add_post('เก็บเกี่ยว', '2025-03-03 09:00:00', array($farm));

$add_page = function ($title, $date, $menu_order = 0) {
	return wp_insert_post(array('post_type' => 'page', 'post_status' => 'publish', 'post_title' => $title, 'post_date' => $date, 'menu_order' => $menu_order));
};
$add_page('ใบสมัคร', '2025-01-01 09:00:00');
$add_page('กิจกรรม', '2025-01-02 09:00:00');
$add_page('เกี่ยวกับเรา', '2025-01-03 09:00:00');
$add_page('ติดต่อ', '2025-01-04 09:00:00');
$add_page('อาคารสถานที่', '2025-01-05 09:00:00', 3);

$add_book = function ($title, $genre) {
	$id = wp_insert_post(array('post_type' => 'ahs_test_book', 'post_status' => 'publish', 'post_title' => $title));
	$term = term_exists($genre, 'ahs_test_genre') ?: wp_insert_term($genre, 'ahs_test_genre');
	wp_set_object_terms($id, array((int) $term['term_id']), 'ahs_test_genre');
	return $id;
};
$add_book('เกมชีวิต', 'นิยาย');
$add_book('ขวัญใจ', 'สารคดี');
$add_book('แมวเหมียว', 'ไซไฟ');
wp_set_current_user(0);

$thai_categories = array('กีฬา', 'เทนนิส', 'แบดมินตัน', 'ฟุตบอล', 'เกษตร', 'ไก่ชน', 'ขนส่ง');
$html = Accessible_Sitemap_Renderer::render(array('only' => 'post'));
$names = $texts($html, $thai_categories);
ahs_test_assert('posts by category: categories and sub-categories in dictionary order', $names === $thai_categories, $show($names));
$all = $texts($html);
ahs_test_assert('Thai categories before Latin ones (as in the Thai rules)', array_search('ขนส่ง', $all, true) < array_search('Demo Cat A', $all, true));
$farm_posts = array('ไถนา', 'ข้าวหอมมะลิ', 'เก็บเกี่ยว');
$result = $texts($html, $farm_posts);
ahs_test_assert('posts keep date order by default (newest first)', $result === array('เก็บเกี่ยว', 'ไถนา', 'ข้าวหอมมะลิ'), $show($result));
$result = $texts(Accessible_Sitemap_Renderer::render(array('only' => 'post', 'sort' => 'title')), $farm_posts);
ahs_test_assert('sort="title": posts by title, newest-first direction (DESC) kept', $result === array('ไถนา', 'ข้าวหอมมะลิ', 'เก็บเกี่ยว'), $show($result));
$result = $texts(Accessible_Sitemap_Renderer::render(array('only' => 'post', 'sort' => 'post_title', 'order' => 'ASC')), $farm_posts);
ahs_test_assert('sort="post_title" order="ASC"', $result === array('เก็บเกี่ยว', 'ข้าวหอมมะลิ', 'ไถนา'), $show($result));
$result = $texts(Accessible_Sitemap_Renderer::render(array('only' => 'post', 'sort' => 'title')), $thai_categories);
ahs_test_assert('sort="title": categories by name too', $result === $thai_categories, $show($result));

$flat = array('กีฬา', 'เกษตร', 'ไก่ชน', 'ขนส่ง', 'เทนนิส', 'แบดมินตัน', 'ฟุตบอล');
$result = $texts(Accessible_Sitemap_Renderer::render(array('only' => 'category')), $flat);
ahs_test_assert('only="category": dictionary order', $result === $flat, $show($result));
$result = $texts(Accessible_Sitemap_Renderer::render(array('only' => 'category', 'sort' => 'term_id')), $flat);
ahs_test_assert('only="category" sort="term_id": creation order, not re-sorted', $result === array('กีฬา', 'เกษตร', 'ไก่ชน', 'ขนส่ง', 'ฟุตบอล', 'เทนนิส', 'แบดมินตัน'), $show($result));
$result = $texts(Accessible_Sitemap_Renderer::render(array('only' => 'tag')), array('ห้องสมุด', 'การเงิน', 'เอกสาร', 'แนะแนว'));
ahs_test_assert('only="tag": dictionary order', $result === array('การเงิน', 'แนะแนว', 'ห้องสมุด', 'เอกสาร'), $show($result));
$result = $texts(Accessible_Sitemap_Renderer::render(array('only' => 'ahs_test_genre')), array('นิยาย', 'สารคดี', 'ไซไฟ'));
ahs_test_assert('custom taxonomy: dictionary order', $result === array('ไซไฟ', 'นิยาย', 'สารคดี'), $show($result));
$result = $texts(Accessible_Sitemap_Renderer::render(array('only' => 'ahs_test_book', 'sort' => 'title')), array('เกมชีวิต', 'ขวัญใจ', 'แมวเหมียว'));
ahs_test_assert('custom post type sort="title" (DESC)', $result === array('แมวเหมียว', 'ขวัญใจ', 'เกมชีวิต'), $show($result));

$get_pages_callbacks = function () {
	global $wp_filter;
	return isset($wp_filter['get_pages']) ? count($wp_filter['get_pages']->callbacks, COUNT_RECURSIVE) : 0;
};
$callbacks_before = $get_pages_callbacks();
$thai_pages = array('ใบสมัคร', 'กิจกรรม', 'เกี่ยวกับเรา', 'ติดต่อ', 'อาคารสถานที่', 'Demo Another Page', 'Demo Parent Page');
$result = $texts(Accessible_Sitemap_Renderer::render(array('only' => 'page')), $thai_pages);
ahs_test_assert('pages by default: menu order first, then titles in dictionary order', $result === array('กิจกรรม', 'เกี่ยวกับเรา', 'ติดต่อ', 'ใบสมัคร', 'Demo Another Page', 'Demo Parent Page', 'อาคารสถานที่'), $show($result));
$result = $texts(Accessible_Sitemap_Renderer::render(array('only' => 'page', 'sort' => 'post_title')), $thai_pages);
ahs_test_assert('pages sort="post_title": titles only', $result === array('กิจกรรม', 'เกี่ยวกับเรา', 'ติดต่อ', 'ใบสมัคร', 'อาคารสถานที่', 'Demo Another Page', 'Demo Parent Page'), $show($result));
$result = $texts(Accessible_Sitemap_Renderer::render(array('only' => 'page', 'sort' => 'post_date')), array_slice($thai_pages, 0, 5));
ahs_test_assert('pages sort="post_date": date order, not re-sorted', $result === array('ใบสมัคร', 'กิจกรรม', 'เกี่ยวกับเรา', 'ติดต่อ', 'อาคารสถานที่'), $show($result));
ahs_test_assert('the get_pages filter is removed again after the sitemap (other page lists untouched)', $get_pages_callbacks() === $callbacks_before);

ahs_test_section('Fallback, filter and cache');
$result = $without_intl(function () use ($texts, $thai_categories) {
	return $texts(Accessible_Sitemap_Renderer::render(array('only' => 'post')), $thai_categories);
});
ahs_test_assert('without intl: the same order in the sitemap', $result === $thai_categories, $show($result));
add_filter('accessible_sitemap_thai_sort', '__return_false');
$result = $texts(Accessible_Sitemap_Renderer::render(array('only' => 'category')), $flat);
$database = array_values(array_intersect(wp_list_pluck(get_categories(), 'name'), $flat));
ahs_test_assert('accessible_sitemap_thai_sort=false: the database order (leading vowels last)', $result === $database && $result !== $flat, $show($result));
remove_filter('accessible_sitemap_thai_sort', '__return_false');
remove_filter('accessible_sitemap_use_cache', '__return_false');
$first = $texts(Accessible_Sitemap_Renderer::render(array('only' => 'post')), $thai_categories);
$cached = $texts(Accessible_Sitemap_Renderer::render(array('only' => 'post')), $thai_categories);
ahs_test_assert('cached sitemap keeps the order', $first === $thai_categories && $cached === $thai_categories);
ahs_test_assert('cache schema raised, so sitemaps cached before this version are rebuilt', Accessible_Sitemap_Renderer::CACHE_SCHEMA >= 2);

switch_to_locale('th');
ahs_test_write_a11y_page('09-thai-order', Accessible_Sitemap_Renderer::render(), '<h1>แผนผังเว็บไซต์</h1>', 'th');
restore_previous_locale();

ahs_test_done();
