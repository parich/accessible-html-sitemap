<?php
/**
 * Phase 2: the renderer lists the same links as WP Sitemap Page, escapes stored
 * content and fixes the original's bugs.
 *
 * @package Parich_Accessible_Sitemap
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../fixtures.php';

$f = pas_test_create_fixtures();
$link = function ($id) {
	return get_permalink($id);
};

/*
 * Parity with the original plugin (link targets, in order).
 */
if (!function_exists('wsp_wp_sitemap_page_main_func')) {
	$legacy_file = WP_PLUGIN_DIR . '/wp-sitemap-page/wp-sitemap-page.php';
	if (file_exists($legacy_file)) {
		require_once $legacy_file;
	}
}
if (!function_exists('wsp_wp_sitemap_page_main_func')) {
	echo "\n  [SKIP] parity checks — WP Sitemap Page is not installed\n";
} else {
	pas_test_section('Parity — all sections enabled');
	pas_test_apply_settings();
	$cases = array(
		'default' => array(),
		'only=page' => array('only' => 'page'),
		'only=post' => array('only' => 'post'),
		'only=category' => array('only' => 'category'),
		'only=tag' => array('only' => 'tag'),
		'only=archive' => array('only' => 'archive'),
		'only=author' => array('only' => 'author'),
		'only=<custom post type>' => array('only' => 'pas_test_book'),
		'only=<custom taxonomy>' => array('only' => 'pas_test_genre'),
		'only=page sort=post_title' => array('only' => 'page', 'sort' => 'post_title'),
		'only=page sort=menu_order' => array('only' => 'page', 'sort' => 'menu_order'),
		'only=post sort=count' => array('only' => 'post', 'sort' => 'count'),
		'only=post sort=post_name order=ASC' => array('only' => 'post', 'sort' => 'post_name', 'order' => 'ASC'),
		'only=post sort=post_date order=DESC' => array('only' => 'post', 'sort' => 'post_date', 'order' => 'DESC'),
		'only=category sort=count' => array('only' => 'category', 'sort' => 'count'),
		'only=author sort=post_count' => array('only' => 'author', 'sort' => 'post_count'),
		'only=<custom post type> sort=post_title' => array('only' => 'pas_test_book', 'sort' => 'post_title'),
		'default sort=post_title order=DESC' => array('sort' => 'post_title', 'order' => 'DESC'),
		'display_title=false' => array('display_title' => 'false'),
		'display_category_title_wording=false' => array('display_category_title_wording' => 'false'),
	);
	foreach ($cases as $label => $atts) {
		pas_test_parity($label, $atts);
	}

	pas_test_section('Parity — exclusions, password-protected hidden, posts in every category');
	$excluding = array(
		'excluded_sections' => array('archive', 'author'),
		'excluded_post_types' => array('pas_test_book'),
		'excluded_taxonomies' => array('pas_test_genre'),
		'exclude_ids' => array($f['page_excluded'], $f['post_a']),
		'exclude_password_protected' => true,
		'post_multiple_times' => true,
	);
	pas_test_apply_settings($excluding);
	foreach (array(
		'default' => array(),
		'only=page' => array('only' => 'page'),
		'only=post' => array('only' => 'post'),
		'only=<excluded custom post type> (only wins)' => array('only' => 'pas_test_book'),
		'only=<excluded custom taxonomy> (only wins)' => array('only' => 'pas_test_genre'),
	) as $label => $atts) {
		pas_test_parity($label, $atts);
	}

	pas_test_section('Parity — private pages for an administrator');
	pas_test_apply_settings();
	wp_set_current_user($f['admin']);
	pas_test_parity('only=page only_private=true', array('only' => 'page', 'only_private' => 'true'));
	wp_set_current_user(0);
}

/*
 * Behaviour checks (independent of the original plugin).
 */
pas_test_section('Exclusions and post placement');
pas_test_apply_settings($excluding ?? array());
$hrefs = pas_test_hrefs(Parich_Sitemap_Renderer::render());
pas_test_assert('excluded page is not listed', !in_array($link($f['page_excluded']), $hrefs, true));
pas_test_assert('password-protected page is not listed', !in_array($link($f['page_protected']), $hrefs, true));
pas_test_assert('password-protected post is not listed', !in_array($link($f['post_protected']), $hrefs, true));
pas_test_assert('excluded custom post type is not listed', !in_array($link($f['book_alpha']), $hrefs, true));
pas_test_assert('post in two categories is listed twice when "multiple times" is on', count(array_keys($hrefs, $link($f['post_multi']), true)) === 2);

pas_test_apply_settings();
$hrefs = pas_test_hrefs(Parich_Sitemap_Renderer::render());
pas_test_assert('post in two categories is listed once by default', count(array_keys($hrefs, $link($f['post_multi']), true)) === 1);
pas_test_assert('empty category is not listed', !in_array(get_category_link($f['cat_empty']), $hrefs, true));
pas_test_assert('custom post type is listed', in_array($link($f['book_alpha']), $hrefs, true));
pas_test_assert('custom taxonomy term is listed', in_array(get_term_link($f['genre'], 'pas_test_genre'), $hrefs, true));

pas_test_section('Escaping of stored content');
global $wpdb;
$wpdb->update($wpdb->posts, array('post_title' => '<script>alert("x")</script>Tom & "Jerry"'), array('ID' => $f['page_second']));
$wpdb->update($wpdb->posts, array('post_title' => '" onmouseover="alert(1)'), array('ID' => $f['post_b']));
$wpdb->update($wpdb->posts, array('post_title' => '<img src=x onerror=alert(1)>Book'), array('ID' => $f['book_alpha']));
$wpdb->update($wpdb->terms, array('name' => '<b onmouseover=alert(1)>Cat B</b>'), array('term_id' => $f['cat_b']));
foreach (array($f['page_second'], $f['post_b'], $f['book_alpha']) as $id) {
	clean_post_cache($id);
}
clean_term_cache($f['cat_b'], 'category');
pas_test_apply_settings(array('post_template' => '<a href="{permalink}" title="{title}">{title}</a>'));

$html = Parich_Sitemap_Renderer::render(array()) . Parich_Sitemap_Renderer::render(array('only' => 'category'));
$processor = new WP_HTML_Tag_Processor($html);
$unsafe_tags = array();
$event_attributes = array();
while ($processor->next_tag()) {
	if (in_array($processor->get_tag(), array('SCRIPT', 'IMG', 'B'), true)) {
		$unsafe_tags[] = $processor->get_tag();
	}
	foreach ((array) $processor->get_attribute_names_with_prefix('on') as $name) {
		$event_attributes[] = $name;
	}
}
pas_test_assert('no <script>, <img> or <b> from stored titles/names', !$unsafe_tags, implode(',', $unsafe_tags));
pas_test_assert('no event-handler attributes (title="{title}" cannot break out)', !$event_attributes, implode(',', $event_attributes));
// wptexturize (the_title) turns "&" into "&#038;"; either entity is fine, a bare "&" is not.
pas_test_assert('ampersand in a page title is escaped', preg_match('/Tom &(amp|#038);/', $html) === 1 && strpos($html, 'Tom & ') === false);
pas_test_assert('category name is shown as text', strpos($html, '&lt;b onmouseover=alert(1)&gt;Cat B&lt;/b&gt;') !== false);

pas_test_section('nofollow');
pas_test_apply_settings(array('nofollow' => true));
$processor = new WP_HTML_Tag_Processor(Parich_Sitemap_Renderer::render());
$links = 0;
$without = 0;
while ($processor->next_tag('a')) {
	if (strpos((string) $processor->get_attribute('href'), '#') === 0) {
		continue; // In-page table-of-contents links are not "followed" by crawlers.
	}
	$links++;
	if (!in_array('nofollow', preg_split('/\s+/', (string) $processor->get_attribute('rel')), true)) {
		$without++;
	}
}
pas_test_assert("every link has rel=\"nofollow\" ({$links} links)", $links > 10 && $without === 0, "{$without} without nofollow");
pas_test_assert(
	'a later wp_list_pages() call is not affected',
	strpos((string) wp_list_pages(array('echo' => 0, 'title_li' => '')), 'nofollow') === false
);

pas_test_section('Private pages');
pas_test_apply_settings();
wp_set_current_user(0);
pas_test_assert('visitor: only=page only_private=true lists nothing', Parich_Sitemap_Renderer::render(array('only' => 'page', 'only_private' => 'true')) === '');
pas_test_assert(
	'visitor: private page never appears in the full sitemap',
	!in_array($link($f['page_private']), pas_test_hrefs(Parich_Sitemap_Renderer::render(array('only_private' => 'true'))), true)
);
wp_set_current_user($f['admin']);
pas_test_assert(
	'administrator: private page is listed',
	in_array($link($f['page_private']), pas_test_hrefs(Parich_Sitemap_Renderer::render(array('only' => 'page', 'only_private' => 'true'))), true)
);
wp_set_current_user(0);

pas_test_section('Invalid attributes');
$baseline = pas_test_hrefs(Parich_Sitemap_Renderer::render(array('only' => 'post')));
pas_test_assert(
	'unknown sort/order values are ignored',
	pas_test_hrefs(Parich_Sitemap_Renderer::render(array('only' => 'post', 'sort' => 'DROP TABLE wp_posts', 'order' => 'sideways'))) === $baseline
);
pas_test_assert('sort=rand is ignored', pas_test_hrefs(Parich_Sitemap_Renderer::render(array('only' => 'post', 'sort' => 'rand'))) === $baseline);
pas_test_assert('only=<unknown> renders nothing', Parich_Sitemap_Renderer::render(array('only' => 'no_such_type')) === '');
pas_test_assert('only=<non-public type> renders nothing', Parich_Sitemap_Renderer::render(array('only' => 'wp_template')) === '');
pas_test_assert('only=<non-public taxonomy> renders nothing', Parich_Sitemap_Renderer::render(array('only' => 'nav_menu')) === '');
pas_test_assert('only=<public type without posts> renders nothing', Parich_Sitemap_Renderer::render(array('only' => 'pas_test_empty')) === '');

pas_test_section('Post without a category');
$no_category = function ($categories, $post_id) use ($f) {
	return (int) $post_id === (int) $f['post_deep'] ? array() : $categories;
};
add_filter('get_the_categories', $no_category, 10, 2);
pas_test_apply_settings(array('post_template' => '<a href="{permalink}">{title}</a> ({category})'));
$hrefs = pas_test_hrefs(Parich_Sitemap_Renderer::render(array('only' => 'post')));
pas_test_assert('listed once by default: skipped without a notice', !in_array($link($f['post_deep']), $hrefs, true));
pas_test_apply_settings(array('post_template' => '<a href="{permalink}">{title}</a> ({category})', 'post_multiple_times' => true));
$html = Parich_Sitemap_Renderer::render(array('only' => 'post'));
pas_test_assert('listed in every category: {category} is empty without a notice', strpos($html, 'Demo Deep Post</a> ()') !== false);
remove_filter('get_the_categories', $no_category, 10);

pas_test_section('Nothing to list');
pas_test_apply_settings(array(
	'excluded_sections' => array('page', 'post', 'archive', 'author'),
	'excluded_post_types' => array('pas_test_book', 'pas_test_empty'),
	'excluded_taxonomies' => array('pas_test_genre'),
));
pas_test_assert('every section excluded: renders an empty string', Parich_Sitemap_Renderer::render() === '');

pas_test_section('Shortcode and block');
pas_test_apply_settings();
// Each render gets its own id prefix (parich-sitemap-N) so ids stay unique on the page.
$same_ids = function ($html) {
	return preg_replace('/parich-sitemap-\d+/', 'parich-sitemap-N', $html);
};
pas_test_assert(
	'[parich_sitemap only="page"] matches the renderer',
	$same_ids(do_shortcode('[parich_sitemap only="page"]')) === $same_ids(Parich_Sitemap_Renderer::render(array('only' => 'page')))
);
$block_html = do_blocks('<!-- wp:parich-accessible-sitemap/sitemap /-->');
pas_test_assert('block output has the block wrapper class', strpos($block_html, 'wp-block-parich-accessible-sitemap-sitemap') !== false);
pas_test_assert('block lists the same links as the renderer', pas_test_hrefs($block_html) === pas_test_hrefs(Parich_Sitemap_Renderer::render()));

pas_test_done();
