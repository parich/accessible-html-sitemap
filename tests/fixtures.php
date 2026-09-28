<?php
/**
 * Shared fixtures and helpers for the PHP tests. Everything is created inside
 * the bootstrap's transaction and rolled back by pas_test_done().
 *
 * @package Parich_Accessible_Sitemap
 */

/**
 * Creates a small but complete site: nested pages, three levels of categories,
 * a post in two categories, password-protected and private content, tags,
 * a second author, a public custom post type and a public custom taxonomy.
 *
 * @return array Named IDs.
 */
function pas_test_create_fixtures()
{
	$admins = get_users(array('role' => 'administrator', 'number' => 1, 'fields' => 'ID'));
	$f = array('admin' => (int) $admins[0]);
	wp_set_current_user($f['admin']);

	$f['author'] = wp_insert_user(array(
		'user_login' => 'pas_test_author',
		'user_pass' => wp_generate_password(),
		'role' => 'author',
		'display_name' => 'Demo Test Author',
	));

	$page = function ($title, $extra = array()) {
		return wp_insert_post(array_merge(array('post_type' => 'page', 'post_status' => 'publish', 'post_title' => $title), $extra));
	};
	$f['page_parent'] = $page('Demo Parent Page', array('menu_order' => 2));
	$f['page_child'] = $page('Demo Child Page', array('post_parent' => $f['page_parent']));
	$f['page_grandchild'] = $page('Demo Grandchild Page', array('post_parent' => $f['page_child']));
	$f['page_second'] = $page('Demo Another Page', array('menu_order' => 1));
	$f['page_protected'] = $page('Demo Protected Page', array('post_password' => 'secret'));
	$f['page_private'] = $page('Demo Private Page', array('post_status' => 'private'));
	$f['page_excluded'] = $page('Demo Excluded Page');

	$term = function ($name, $taxonomy, $parent = 0) {
		$result = wp_insert_term($name, $taxonomy, array('parent' => $parent));
		return (int) $result['term_id'];
	};
	$f['cat_a'] = $term('Demo Cat A', 'category');
	$f['cat_a1'] = $term('Demo Cat A1', 'category', $f['cat_a']);
	$f['cat_a1a'] = $term('Demo Cat A1a', 'category', $f['cat_a1']);
	$f['cat_b'] = $term('Demo Cat B', 'category');
	$f['cat_empty'] = $term('Demo Cat Empty', 'category');

	$post = function ($title, $date, array $categories, $extra = array()) use ($f) {
		return wp_insert_post(array_merge(array(
			'post_type' => 'post',
			'post_status' => 'publish',
			'post_title' => $title,
			'post_date' => $date,
			'post_category' => $categories,
			'post_author' => $f['admin'],
		), $extra));
	};
	$f['post_deep'] = $post('Demo Deep Post', '2025-01-10 09:00:00', array($f['cat_a1a']), array('tags_input' => array('demo-tag-one')));
	$f['post_multi'] = $post('Demo Multi Post', '2025-02-11 09:00:00', array($f['cat_a'], $f['cat_b']), array('post_author' => $f['author']));
	$f['post_b'] = $post('Demo B Post', '2025-02-20 09:00:00', array($f['cat_b']), array('tags_input' => array('demo-tag-two')));
	$f['post_protected'] = $post('Demo Protected Post', '2025-02-21 09:00:00', array($f['cat_b']), array('post_password' => 'secret'));
	$f['post_a'] = $post('Demo A Post', '2024-12-05 09:00:00', array($f['cat_a']));

	// Registered for this process only.
	register_post_type('pas_test_book', array('public' => true, 'label' => 'Demo Books'));
	register_post_type('pas_test_empty', array('public' => true, 'label' => 'Demo Empty Type'));
	register_taxonomy('pas_test_genre', 'pas_test_book', array('public' => true, 'label' => 'Demo Genres'));

	$f['book_zeta'] = wp_insert_post(array('post_type' => 'pas_test_book', 'post_status' => 'publish', 'post_title' => 'Demo Book Zeta', 'post_date' => '2025-01-01 09:00:00'));
	$f['book_alpha'] = wp_insert_post(array('post_type' => 'pas_test_book', 'post_status' => 'publish', 'post_title' => 'Demo Book Alpha', 'post_date' => '2025-01-02 09:00:00'));
	$f['genre'] = $term('Demo Genre Fiction', 'pas_test_genre');
	wp_set_object_terms($f['book_zeta'], array($f['genre']), 'pas_test_genre');

	wp_set_current_user(0);
	return $f;
}

/**
 * Writes the same settings for the original plugin (wsp_*) and for this one (parich_sitemap_*).
 */
function pas_test_apply_settings(array $settings = array())
{
	$s = array_merge(array(
		'excluded_sections' => array(),
		'excluded_post_types' => array(),
		'excluded_taxonomies' => array(),
		'exclude_ids' => array(),
		'exclude_password_protected' => false,
		'nofollow' => false,
		'post_multiple_times' => false,
		'post_template' => '<a href="{permalink}">{title}</a>',
	), $settings);
	$flag = function ($on) {
		return $on ? '1' : '';
	};

	foreach (array('page', 'post', 'archive', 'author') as $section) {
		update_option("wsp_exclude_cpt_{$section}", $flag(in_array($section, $s['excluded_sections'], true)));
	}
	foreach (get_post_types(array('public' => true, '_builtin' => false)) as $post_type) {
		update_option("wsp_exclude_cpt_{$post_type}", $flag(in_array($post_type, $s['excluded_post_types'], true)));
	}
	foreach (get_taxonomies(array('public' => true, '_builtin' => false)) as $taxonomy) {
		update_option("wsp_exclude_taxonomy_{$taxonomy}", $flag(in_array($taxonomy, $s['excluded_taxonomies'], true)));
	}
	update_option('wsp_exclude_pages', implode(',', $s['exclude_ids']));
	update_option('wsp_is_exclude_password_protected', $flag($s['exclude_password_protected']));
	update_option('wsp_add_nofollow', $flag($s['nofollow']));
	update_option('wsp_is_display_post_multiple_time', $flag($s['post_multiple_times']));
	update_option('wsp_posts_by_category', $s['post_template']);

	update_option('parich_sitemap_excluded_sections', $s['excluded_sections']);
	update_option('parich_sitemap_excluded_post_types', $s['excluded_post_types']);
	update_option('parich_sitemap_excluded_taxonomies', $s['excluded_taxonomies']);
	update_option('parich_sitemap_exclude_pages', implode(',', $s['exclude_ids']));
	update_option('parich_sitemap_exclude_password_protected', $s['exclude_password_protected']);
	update_option('parich_sitemap_add_nofollow', $s['nofollow']);
	update_option('parich_sitemap_display_post_multiple_times', $s['post_multiple_times']);
	update_option('parich_sitemap_post_template', $s['post_template']);
}

/**
 * @return string[] Every link target in document order (character references decoded),
 *                  without in-page "#…" links (the table of contents).
 */
function pas_test_hrefs($html)
{
	$processor = new WP_HTML_Tag_Processor((string) $html);
	$hrefs = array();
	while ($processor->next_tag('a')) {
		$href = (string) $processor->get_attribute('href');
		if ($href === '' || $href[0] !== '#') {
			$hrefs[] = $href;
		}
	}
	return $hrefs;
}

/**
 * Makes the given query the "current page" (is_page(), get_queried_object_id() …).
 * Call with no argument to go back to a neutral query.
 */
function pas_test_view($query_args = null)
{
	$query = $query_args === null ? new WP_Query() : new WP_Query($query_args);
	$GLOBALS['wp_query'] = $query;
	$GLOBALS['wp_the_query'] = $query;
}

/**
 * Removes the HTML pages a test file wrote earlier (files named "<prefix>-*.html").
 */
function pas_test_a11y_reset($prefix)
{
	$dir = PAS_TEST_PLUGIN_DIR . '/tests/a11y/out';
	wp_mkdir_p($dir);
	foreach ((array) glob("{$dir}/{$prefix}-*.html") as $file) {
		unlink($file);
	}
}

/**
 * Wraps a sitemap fragment in a complete page for the axe-core check (npm run test:a11y).
 *
 * @param string $name     File name without extension, e.g. "03-default".
 * @param string $fragment Sitemap HTML.
 * @param string $before   Page content before the sitemap (the page outline).
 * @param string $lang     Page language.
 */
function pas_test_write_a11y_page($name, $fragment, $before = '<h1>Sitemap</h1>', $lang = 'en')
{
	// The built stylesheet is included, so the browser checks (reflow) see the real
	// tree layout; the font stands in for a theme's (Thai-capable on Windows).
	$css = (string) file_get_contents(PAS_TEST_PLUGIN_DIR . '/build/sitemap/style-index.css');
	$html = "<!doctype html>\n<html lang=\"" . esc_attr($lang) . "\">\n<head>\n<meta charset=\"utf-8\">\n"
		. '<meta name="viewport" content="width=device-width, initial-scale=1">' . "\n"
		. '<title>' . esc_html($name) . "</title>\n"
		. "<style>body { font-family: \"Leelawadee UI\", Tahoma, sans-serif; margin: 16px; }\n{$css}</style>\n"
		. "</head>\n<body>\n<main>\n{$before}\n{$fragment}\n</main>\n</body>\n</html>\n";
	file_put_contents(PAS_TEST_PLUGIN_DIR . "/tests/a11y/out/{$name}.html", $html);
}

/**
 * Structure of rendered markup, for assertions.
 */
function pas_test_markup($html)
{
	$summary = array(
		'ids' => array(),
		'headings' => array(),
		'fragment_links' => array(),
		'labelledby' => array(),
		'navs' => array(),
		'strong' => 0,
		'current' => array(),
	);
	$processor = new WP_HTML_Tag_Processor((string) $html);
	while ($processor->next_tag()) {
		$tag = $processor->get_tag();
		$id = $processor->get_attribute('id');
		if (is_string($id)) {
			$summary['ids'][] = $id;
		}
		$labelledby = $processor->get_attribute('aria-labelledby');
		if (is_string($labelledby)) {
			$summary['labelledby'][] = $labelledby;
		}
		if (preg_match('/^H([1-6])$/', $tag, $matches)) {
			$summary['headings'][] = (int) $matches[1];
		} elseif ($tag === 'NAV') {
			$summary['navs'][] = (string) $processor->get_attribute('aria-label');
		} elseif ($tag === 'STRONG') {
			$summary['strong']++;
		} elseif ($tag === 'A') {
			$href = (string) $processor->get_attribute('href');
			if ($href !== '' && $href[0] === '#') {
				$summary['fragment_links'][] = substr($href, 1);
			}
			if ($processor->get_attribute('aria-current') === 'page') {
				$summary['current'][] = $href;
			}
		}
	}
	return $summary;
}

/**
 * Compares the link sequence of the original plugin with this renderer.
 */
function pas_test_parity($label, array $atts)
{
	$old = pas_test_hrefs(wsp_wp_sitemap_page_main_func($atts));
	$new = pas_test_hrefs(Parich_Sitemap_Renderer::render($atts));

	$detail = '';
	if ($old !== $new) {
		$index = 0;
		while (isset($old[$index], $new[$index]) && $old[$index] === $new[$index]) {
			$index++;
		}
		$detail = sprintf(
			'original %d links, new %d; first difference at #%d: original=%s new=%s',
			count($old),
			count($new),
			$index,
			$old[$index] ?? '(none)',
			$new[$index] ?? '(none)'
		);
	}
	return pas_test_assert("parity: {$label} (" . count($new) . ' links)', $old === $new && count($new) > 0, $detail);
}
