<?php
/**
 * Phase 3: accessible markup (WCAG 2.1 AA). Structural checks here; the pages
 * written to tests/a11y/out/03-*.html are checked by axe-core (npm run test:a11y).
 *
 * @package Parich_Accessible_Sitemap
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../fixtures.php';

$f = pas_test_create_fixtures();
pas_test_apply_settings();
pas_test_a11y_reset('03');

wp_set_current_user($f['admin']);
$f['post_untitled'] = wp_insert_post(array(
	'post_type' => 'post',
	'post_status' => 'publish',
	'post_title' => '',
	'post_content' => 'Untitled on purpose.',
	'post_category' => array($f['cat_b']),
	'post_date' => '2025-03-01 09:00:00',
));
wp_set_current_user(0);
pas_test_view();

$render = function (array $atts = array()) {
	return Parich_Sitemap_Renderer::render($atts);
};

pas_test_section('Landmark and table of contents (full sitemap)');
$html = $render();
$m = pas_test_markup($html);
pas_test_assert('one <nav> labelled "Sitemap"', $m['navs'] === array('Sitemap'), implode(' | ', $m['navs']));
pas_test_assert('one heading per section, all h2', count($m['headings']) > 1 && array_unique($m['headings']) === array(2), implode(',', $m['headings']));
$back_links = array_filter($m['fragment_links'], function ($id) {
	return substr($id, -4) === '-toc';
});
pas_test_assert('table of contents has one link per section', count($m['fragment_links']) - count($back_links) === count($m['headings']));
pas_test_assert('a "Back to contents" link after every section, to the table of contents', count($back_links) === count($m['headings']) && count(array_unique($back_links)) === 1 && substr_count($html, 'Back to contents</a>') === count($m['headings']));
pas_test_assert('every table-of-contents and back link points to an existing id', !array_diff($m['fragment_links'], $m['ids']));
pas_test_assert('every aria-labelledby points to an existing id', !array_diff($m['labelledby'], $m['ids']));
pas_test_assert('no <strong> used as a fake heading', $m['strong'] === 0);
pas_test_write_a11y_page('03-default', $html);

pas_test_section('Single section');
$m = pas_test_markup($render(array('only' => 'page')));
pas_test_assert('nav label names the section ("Sitemap: Pages")', $m['navs'] === array('Sitemap: Pages'), implode(' | ', $m['navs']));
pas_test_assert('no table of contents for a single section', !$m['fragment_links']);
foreach (array('page', 'post', 'category', 'tag', 'archive', 'author', 'pas_test_book', 'pas_test_genre') as $only) {
	pas_test_write_a11y_page("03-only-{$only}", $render(array('only' => $only)));
}

pas_test_section('Heading level');
$outline = '<h1>University</h1><h2>About</h2><h3>Sitemap</h3>';
$html = $render(array('heading_level' => 4));
$m = pas_test_markup($html);
pas_test_assert('heading_level=4 gives h4 headings', array_unique($m['headings']) === array(4));
pas_test_write_a11y_page('03-heading-level-4', $html, $outline);
foreach (array(0 => 2, 9 => 6, 'abc' => 2, '3' => 3) as $given => $expected) {
	$m = pas_test_markup($render(array('heading_level' => $given)));
	pas_test_assert("heading_level=\"{$given}\" gives h{$expected}", array_unique($m['headings']) === array($expected), implode(',', array_unique($m['headings'])));
}

pas_test_section('Titles and table of contents switched off');
$html = $render(array('display_title' => 'false'));
$m = pas_test_markup($html);
pas_test_assert('display_title=false: no headings', !$m['headings']);
pas_test_assert('display_title=false: no table of contents pointing at missing headings', !$m['fragment_links']);
pas_test_assert('display_title=false: still a labelled <nav>', $m['navs'] === array('Sitemap'));
pas_test_write_a11y_page('03-no-titles', $html);
$m = pas_test_markup($render(array('toc' => 'false')));
pas_test_assert('toc=false: headings but no table of contents', $m['headings'] && !$m['fragment_links']);

pas_test_section('Two sitemaps on one page');
$html = $render() . $render(array('only' => 'post'));
$m = pas_test_markup($html);
pas_test_assert('no duplicate ids', count($m['ids']) === count(array_unique($m['ids'])), implode(',', array_diff_assoc($m['ids'], array_unique($m['ids']))));
pas_test_assert('every fragment link still resolves', !array_diff($m['fragment_links'], $m['ids']));
pas_test_write_a11y_page('03-two-instances', $html);

pas_test_section('Current page (aria-current)');
pas_test_view(array('page_id' => $f['page_child']));
$m = pas_test_markup($render(array('only' => 'page')));
pas_test_assert('current page is marked (from wp_list_pages)', $m['current'] === array(get_permalink($f['page_child'])), implode(',', $m['current']));
pas_test_view(array('p' => $f['post_b']));
$html = $render();
$m = pas_test_markup($html);
pas_test_assert('current post is marked in the category list', $m['current'] === array(get_permalink($f['post_b'])), implode(',', $m['current']));
pas_test_write_a11y_page('03-current-post', $html);
pas_test_view(array('cat' => $f['cat_b']));
$m = pas_test_markup($render(array('only' => 'category')));
pas_test_assert('current category archive is marked', $m['current'] === array(get_category_link($f['cat_b'])), implode(',', $m['current']));
pas_test_view();
$m = pas_test_markup($render());
pas_test_assert('nothing is marked on an unrelated page', !$m['current']);

pas_test_section('Link names');
$needle = sprintf('#%d (no title)', $f['post_untitled']);
pas_test_assert('post without a title gets a link name', strpos($render(array('only' => 'post')), '>' . $needle . '</a>') !== false, $needle);

pas_test_section('nofollow keeps the markup valid');
pas_test_apply_settings(array('nofollow' => true));
pas_test_write_a11y_page('03-nofollow', $render());
pas_test_apply_settings();

pas_test_section('Stylesheet');
$handle = generate_block_asset_handle('parich-accessible-sitemap/sitemap', 'style');
wp_dequeue_style($handle);
do_shortcode('[parich_sitemap only="page"]');
pas_test_assert('shortcode enqueues the block stylesheet', wp_style_is($handle, 'enqueued'), $handle);

pas_test_done();
