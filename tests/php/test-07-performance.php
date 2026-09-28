<?php
/**
 * Phase 8: performance — one pass over the posts, partial posts that never leak,
 * the sitemap cache and its invalidation, and the per-category limit.
 *
 * @package Accessible_HTML_Sitemap
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../fixtures.php';

$f = ahs_test_create_fixtures();
ahs_test_apply_settings(array('post_template' => '<a href="{permalink}">{title}</a> ({date})'));
ahs_test_a11y_reset('07');

global $wpdb;
$render = function (array $atts = array()) {
	return Accessible_Sitemap_Renderer::render($atts);
};
$cold = function (array $atts = array()) {
	add_filter('accessible_sitemap_use_cache', '__return_false');
	$html = Accessible_Sitemap_Renderer::render($atts);
	remove_filter('accessible_sitemap_use_cache', '__return_false');
	return $html;
};
$queries = function (callable $fn) use ($wpdb) {
	$before = $wpdb->num_queries;
	$fn();
	return $wpdb->num_queries - $before;
};
$same_ids = function ($html) {
	return preg_replace('/accessible-sitemap-\d+/', 'accessible-sitemap-N', $html);
};
$transients = function () use ($wpdb) {
	return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_accessible\\_sitemap\\_%'");
};

/**
 * Posts listed directly under each category: category URL => [posts, "more" text, "more" URL].
 */
$by_category = function ($html) {
	$doc = new DOMDocument();
	libxml_use_internal_errors(true);
	$doc->loadHTML('<?xml encoding="utf-8" ?><div>' . $html . '</div>');
	libxml_clear_errors();
	$xpath = new DOMXPath($doc);
	$result = array();
	foreach ($xpath->query('//li[contains(@class,"accessible-sitemap__category")]') as $li) {
		$url = $xpath->evaluate('string(./span/a/@href)', $li);
		$more = $xpath->query('./ul/li[contains(@class,"accessible-sitemap__more")]/a', $li)->item(0);
		$result[$url] = array(
			'posts' => $xpath->query('./ul/li[contains(@class,"accessible-sitemap__post")]', $li)->length,
			'more' => $more ? $more->textContent : null,
			'more_url' => $more ? $more->getAttribute('href') : null,
		);
	}
	return $result;
};

ahs_test_section('Queries do not grow with the number of categories');
$before = $queries(function () use ($cold) {
	$cold(array('only' => 'post'));
});
wp_set_current_user($f['admin']);
for ($i = 1; $i <= 25; $i++) {
	$term = wp_insert_term("Demo Extra Cat {$i}", 'category');
	wp_insert_post(array('post_title' => "Demo Extra Post {$i}", 'post_status' => 'publish', 'post_category' => array($term['term_id']), 'post_date' => '2025-03-10 09:00:00'));
}
wp_set_current_user(0);
$after = $queries(function () use ($cold) {
	$cold(array('only' => 'post'));
});
ahs_test_assert("30 categories cost no more than 5 (queries: {$before} → {$after})", $after <= $before + 2);

ahs_test_section('Partial posts never leak');
wp_cache_flush();
$cold(array('only' => 'post'));
ahs_test_assert('posts loaded for the sitemap are dropped from the object cache', false === wp_cache_get($f['post_b'], 'posts'));
wp_set_current_user($f['admin']);
$with_content = wp_insert_post(array('post_title' => 'Demo Content Post', 'post_content' => 'Full body text', 'post_status' => 'publish', 'post_category' => array($f['cat_a'])));
wp_set_current_user(0);
wp_cache_flush();
$cold(array('only' => 'post'));
ahs_test_assert('a later get_post() returns the full post, content included', get_post($with_content)->post_content === 'Full body text');
wp_cache_flush();
get_post($with_content); // Cached by the page before the sitemap renders.
$cold(array('only' => 'post'));
ahs_test_assert('a post cached before the sitemap stays cached', false !== wp_cache_get($with_content, 'posts'));
$partial_html = $cold();
wp_using_ext_object_cache(true); // A persistent object cache: full posts are loaded instead.
$full_html = $cold();
wp_using_ext_object_cache(false);
ahs_test_assert('same sitemap with partial posts and with full posts', $same_ids($partial_html) === $same_ids($full_html));

ahs_test_section('Cache');
Accessible_Sitemap_Renderer::bump_cache_version();
$first = $render();
$warm_queries = $queries(function () use ($render, &$second) {
	$second = $render();
});
ahs_test_assert("a cached sitemap costs at most 3 queries ({$warm_queries})", $warm_queries <= 3);
ahs_test_assert('cached sitemap is identical', $same_ids($first) === $same_ids($second));
ahs_test_assert('ids stay unique across cached instances', $first !== $second);
$stored = get_transient('accessible_sitemap_' . md5((string) wp_json_encode(array()))) ?: null;
// The newest row is the one just written (the site may hold older ones).
$row = $wpdb->get_var("SELECT option_value FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_accessible\\_sitemap\\_%' ORDER BY option_id DESC LIMIT 1");
ahs_test_assert('stored compressed (each section\'s markup as gz: + hex, no raw HTML in the INSERT)', is_string($row) && (bool) preg_match('/"gz:[0-9a-f]+"/', $row) && strpos($row, '<li') === false);
$unpack = new ReflectionMethod('Accessible_Sitemap_Renderer', 'unpack');
$unpack->setAccessible(true);
$sections = array(array('key' => 'page', 'title' => 'หน้า "ทดสอบ"', 'items' => "<li><a href=\"/x\">ไก่ &amp; ไข่</a></li>\n"));
$pack = new ReflectionMethod('Accessible_Sitemap_Renderer', 'pack');
$pack->setAccessible(true);
ahs_test_assert('pack/unpack round trip (Thai, quotes, HTML)', $unpack->invoke(null, $pack->invoke(null, $sections)) === $sections);
$with_items = function ($items) use ($sections) {
	return array(array_merge($sections[0], array('items' => $items)));
};
ahs_test_assert('unpack: plain markup (no zlib), and broken data → null without a notice', $unpack->invoke(null, $with_items('html:' . $sections[0]['items'])) === $sections
	&& null === $unpack->invoke(null, $with_items('gz:zz')) && null === $unpack->invoke(null, $with_items('gz:abc')) && null === $unpack->invoke(null, $with_items('<li>no format</li>'))
	&& null === $unpack->invoke(null, 'gz:' . bin2hex(gzcompress('x'))) && null === $unpack->invoke(null, array(array('key' => 'page'))));
$count = $transients();
$render();
$render(array('only' => 'post'));
$render(array('only' => 'post'));
ahs_test_assert('one stored copy per set of options', $transients() === $count + 1);

$wpdb->query("UPDATE {$wpdb->options} SET option_value = REPLACE(option_value, 'gz:', 'gz:broken') WHERE option_name LIKE '\\_transient\\_accessible\\_sitemap\\_%'");
wp_cache_flush();
ahs_test_assert('unreadable cached data is rebuilt', $same_ids($render()) === $same_ids($first));

add_filter('accessible_sitemap_use_cache', '__return_false');
$count = $transients();
$render(array('only' => 'tag'));
ahs_test_assert('accessible_sitemap_use_cache=false stores nothing', $transients() === $count);
remove_filter('accessible_sitemap_use_cache', '__return_false');

ahs_test_section('Cache is rebuilt after changes');
$hrefs = function ($atts = array()) use ($render) {
	return ahs_test_hrefs($render($atts));
};
$render(); // Cached.
wp_set_current_user($f['admin']);
$new = wp_insert_post(array('post_title' => 'Demo Fresh Post', 'post_status' => 'publish', 'post_category' => array($f['cat_b'])));
ahs_test_assert('new post appears', in_array(get_permalink($new), $hrefs(), true));
wp_update_post(array('ID' => $new, 'post_title' => 'Demo Renamed Post'));
ahs_test_assert('edited title appears', strpos($render(), 'Demo Renamed Post') !== false);
wp_set_post_categories($new, array($f['cat_a1']));
$categories = $by_category($render(array('only' => 'post')));
ahs_test_assert('post moved to another category', strpos($render(array('only' => 'post')), 'Demo Renamed Post') !== false && $categories[get_category_link($f['cat_a1'])]['posts'] >= 1);
wp_trash_post($new);
ahs_test_assert('trashed post disappears', !in_array(get_permalink($new), $hrefs(), true));
wp_update_term($f['cat_b'], 'category', array('name' => 'Demo Cat B Renamed'));
ahs_test_assert('renamed category appears', strpos($render(), 'Demo Cat B Renamed') !== false);
wp_update_user(array('ID' => $f['author'], 'display_name' => 'Demo Renamed Author'));
ahs_test_assert('renamed author appears', strpos($render(array('only' => 'author')), 'Demo Renamed Author') !== false);
wp_set_current_user(0);
update_option('date_format', 'Y/m/d');
ahs_test_assert('date format change appears', strpos($render(array('only' => 'post')), '(2025/02/11)') !== false);
update_option('date_format', 'F j, Y');
ahs_test_apply_settings(array('post_template' => '<a href="{permalink}">{title}</a> #{post_id}'));
ahs_test_assert('settings change appears', strpos($render(array('only' => 'post')), '</a> #' . $f['post_b']) !== false);
switch_to_locale('th');
ahs_test_assert('language change appears', strpos($render(), 'aria-label="แผนผังเว็บไซต์"') !== false);
restore_previous_locale();

ahs_test_section('Once per request');
$version = (int) get_option(Accessible_Sitemap_Renderer::CACHE_VERSION_OPTION);
$render();
clean_post_cache($f['post_a']);
ahs_test_assert('a change after a render raises the version at once', (int) get_option(Accessible_Sitemap_Renderer::CACHE_VERSION_OPTION) === $version + 1);
clean_post_cache($f['post_b']);
clean_post_cache($f['post_a']);
ahs_test_assert('further changes without a render in between do not raise it again', (int) get_option(Accessible_Sitemap_Renderer::CACHE_VERSION_OPTION) === $version + 1);
ahs_test_assert('… but once more at the end of the request', (bool) has_action('shutdown', array('Accessible_Sitemap_Renderer', 'bump_cache_version')));
$render();
clean_post_cache($f['post_b']);
ahs_test_assert('a change after the next render raises it at once again', (int) get_option(Accessible_Sitemap_Renderer::CACHE_VERSION_OPTION) === $version + 2);

ahs_test_section('Per-user and per-page output');
wp_set_current_user($f['admin']);
$render(array('only' => 'page', 'only_private' => 'true'));
wp_set_current_user(0);
ahs_test_assert('private pages are never served from the cache to visitors', $render(array('only' => 'page', 'only_private' => 'true')) === '');
$render(array('only' => 'page')); // Cached without a current page.
ahs_test_view(array('page_id' => $f['page_child']));
$m = ahs_test_markup($render(array('only' => 'page')));
ahs_test_assert('aria-current is added to cached output', $m['current'] === array(get_permalink($f['page_child'])));
ahs_test_view();

ahs_test_section('Posts per category');
ahs_test_apply_settings();
$all = $by_category($cold(array('only' => 'post')));
update_option('accessible_sitemap_posts_per_category', 1);
$html = $render(array('only' => 'post'));
$limited = $by_category($html);
$ok = true;
$details = array();
foreach ($all as $url => $category) {
	$expected_more = $category['posts'] > 1 ? $category['posts'] - 1 : 0;
	$got = $limited[$url] ?? null;
	$more_ok = $expected_more
		? $got && strpos((string) $got['more'], (string) $expected_more) === 0 && $got['more_url'] === $url
		: $got && null === $got['more'];
	if (!$got || $got['posts'] !== min(1, $category['posts']) || !$more_ok) {
		$ok = false;
		$details[] = $url;
	}
}
ahs_test_assert('setting 1: one post per category and "N more in …" linking to the category', $ok && count($all) > 3, implode(', ', $details));
ahs_test_assert('"more" link names the category', (bool) preg_match('/>\d+ more in Demo Cat A</', $html));
ahs_test_write_a11y_page('07-posts-per-category', $html);
$two = $by_category($render(array('only' => 'post', 'posts_per_category' => '2')));
ahs_test_assert('attribute posts_per_category="2" overrides the setting', max(array_column($two, 'posts')) === 2);
$every = $by_category($render(array('only' => 'post', 'posts_per_category' => '0')));
ahs_test_assert('posts_per_category="0" lists every post', array_column($every, 'posts') === array_column($all, 'posts') && !array_filter(array_column($every, 'more')));
$block = $by_category(do_blocks('<!-- wp:accessible-html-sitemap/sitemap {"only":"post","postsPerCategory":2} /-->'));
ahs_test_assert('block postsPerCategory 2', max(array_column($block, 'posts')) === 2);
switch_to_locale('th');
ahs_test_assert('"more" link is translated', (bool) preg_match('/>อีก \d+ เรื่องในหมวด Demo Cat A</', $render(array('only' => 'post'))));
restore_previous_locale();
update_option('accessible_sitemap_posts_per_category', 0);

ahs_test_section('Uninstall removes the cache');
$render();
ahs_test_assert('cached sitemaps exist', $transients() > 0);
define('WP_UNINSTALL_PLUGIN', AHS_TEST_PLUGIN_BASENAME);
include AHS_TEST_PLUGIN_DIR . '/uninstall.php';
ahs_test_assert('uninstall removes cached sitemaps and their timeouts', $transients() === 0 && !$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_timeout\\_accessible\\_sitemap\\_%'"));

ahs_test_done();
