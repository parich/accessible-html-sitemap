<?php
/**
 * Phase 11: "Back to contents" after each section, and the stylesheet's high
 * contrast and print rules. Pages written to tests/a11y/out/10-*.html are checked
 * by axe, for reflow at 320px, and in forced colors / print by npm run test:media.
 *
 * @package Accessible_HTML_Sitemap
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../fixtures.php';

$f = ahs_test_create_fixtures();
ahs_test_apply_settings();
ahs_test_a11y_reset('10');

/**
 * Back links: href and text of every a.accessible-sitemap__back link, and whether
 * each one ends a section (right after the section's list).
 */
$back = function ($html) {
	$doc = new DOMDocument();
	libxml_use_internal_errors(true);
	$doc->loadHTML('<?xml encoding="utf-8" ?><div>' . $html . '</div>');
	libxml_clear_errors();
	$xpath = new DOMXPath($doc);
	$links = array();
	foreach ($xpath->query('//p[@class="accessible-sitemap__back"]/a') as $a) {
		$p = $a->parentNode;
		$previous = $p->previousSibling;
		while ($previous && XML_ELEMENT_NODE !== $previous->nodeType) {
			$previous = $previous->previousSibling;
		}
		$links[] = array(
			'href' => $a->getAttribute('href'),
			'rel' => $a->getAttribute('rel'),
			'text' => trim($a->textContent),
			'after_list' => $previous && 'ul' === $previous->nodeName,
			'in_section' => false !== strpos(' ' . $p->parentNode->getAttribute('class') . ' ', ' accessible-sitemap__section '),
			'last_in_section' => null === $p->nextSibling || '' === trim((string) $p->nextSibling->textContent) && null === $p->nextSibling->nextSibling,
		);
	}
	return $links;
};

ahs_test_section('Back to contents');
$html = Accessible_Sitemap_Renderer::render();
$m = ahs_test_markup($html);
$links = $back($html);
ahs_test_assert('one link per section', count($links) === count($m['headings']) && count($links) > 1, count($links) . ' links, ' . count($m['headings']) . ' sections');
$ok = true;
foreach ($links as $link) {
	$ok = $ok && $link['href'] === '#accessible-sitemap-1-toc' && $link['text'] === 'Back to contents' && $link['after_list'] && $link['in_section'] && $link['last_in_section'];
}
ahs_test_assert('each ends its section, after the list, and goes to the table of contents', $ok, wp_json_encode($links));
ahs_test_assert('the target is the table of contents label', in_array('accessible-sitemap-1-toc', $m['ids'], true) && strpos($html, '<p class="accessible-sitemap__toc-label" id="accessible-sitemap-1-toc">') !== false);
ahs_test_assert('links to posts and pages are unchanged', ahs_test_hrefs($html) === ahs_test_hrefs(Accessible_Sitemap_Renderer::render(array('toc' => 'false'))));
ahs_test_write_a11y_page('10-back-links', $html);

$first = Accessible_Sitemap_Renderer::render();
$second = Accessible_Sitemap_Renderer::render();
$own = function ($html) use ($back) {
	$hrefs = array_unique(array_column($back($html), 'href'));
	return count($hrefs) === 1 && in_array(substr($hrefs[0], 1), ahs_test_markup($html)['ids'], true) ? $hrefs[0] : null;
};
ahs_test_assert('two sitemaps on a page: each goes back to its own table of contents', $own($first) && $own($second) && $own($first) !== $own($second), $own($first) . ' / ' . $own($second));

ahs_test_assert('no table of contents, no back links: one section', !$back(Accessible_Sitemap_Renderer::render(array('only' => 'page'))));
ahs_test_assert('no table of contents, no back links: toc="false"', !$back(Accessible_Sitemap_Renderer::render(array('toc' => 'false'))));
ahs_test_assert('no table of contents, no back links: display_title="false"', !$back(Accessible_Sitemap_Renderer::render(array('display_title' => 'false'))));
ahs_test_assert('block: back links with the table of contents, none with showToc off', count($back(do_blocks('<!-- wp:accessible-html-sitemap/sitemap /-->'))) > 1 && !$back(do_blocks('<!-- wp:accessible-html-sitemap/sitemap {"showToc":false} /-->')));
$collapsible = Accessible_Sitemap_Renderer::render(array('collapsible' => 'closed'));
ahs_test_assert('collapsible categories: back links still there', count($back($collapsible)) === count($links));
ahs_test_write_a11y_page('10-back-links-collapsible', $collapsible);

ahs_test_apply_settings(array('nofollow' => true));
$nofollow = Accessible_Sitemap_Renderer::render();
ahs_test_assert('with nofollow on: in-page links get none', !array_filter(array_column($back($nofollow), 'rel')) && strpos($nofollow, 'rel="nofollow"') !== false);
ahs_test_apply_settings();

switch_to_locale('th');
$thai = Accessible_Sitemap_Renderer::render();
ahs_test_assert('Thai: "กลับไปที่สารบัญ"', array_unique(array_column($back($thai), 'text')) === array('กลับไปที่สารบัญ'), wp_json_encode(array_unique(array_column($back($thai), 'text')), JSON_UNESCAPED_UNICODE));
ahs_test_write_a11y_page('10-back-links-th', $thai, '<h1>แผนผังเว็บไซต์</h1>', 'th');
restore_previous_locale();

ahs_test_section('Stylesheet: high contrast and print');
$css = (string) file_get_contents(AHS_TEST_PLUGIN_DIR . '/build/sitemap/style-index.css');
ahs_test_assert('forced colors and "increase contrast": tree lines at full strength', (bool) preg_match('/@media\s*\(forced-colors:\s*active\),\s*\(prefers-contrast:\s*more\)\{[^}]*li:after,[^}]*li:before\{opacity:1\}/', $css), $css);
ahs_test_assert('print: table of contents and back links hidden', (bool) preg_match('/@media print\{\.accessible-sitemap__back,\.accessible-sitemap__toc,\.accessible-sitemap__toc-label\{display:none\}/', $css) || (bool) preg_match('/@media print\{[^@]*\.accessible-sitemap__toc-label[^{]*\{display:none\}/', $css));
ahs_test_assert('print: collapsed categories open', strpos($css, 'details::details-content{content-visibility:visible;display:block}') !== false || strpos($css, 'details::details-content{display:block;content-visibility:visible}') !== false);

ahs_test_done();
