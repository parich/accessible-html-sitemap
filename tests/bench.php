<?php
/**
 * Benchmark on a large site: BENCH_POSTS posts (default 3000) in 30 categories
 * (10 top-level, 2 children each), ~3 KB of content per post, all in the past.
 * Everything is created inside the test transaction and rolled back.
 *
 * Usage: npm run bench            (BENCH_POSTS=5000 npm run bench for more)
 *
 * @package Parich_Accessible_Sitemap
 */

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/fixtures.php';

$total = (int) (getenv('BENCH_POSTS') ?: 3000);
$admins = get_users(array('role' => 'administrator', 'number' => 1, 'fields' => 'ID'));
wp_set_current_user((int) $admins[0]);
wp_defer_term_counting(true);

$categories = array();
for ($i = 1; $i <= 10; $i++) {
	$parent = wp_insert_term("Bench Cat {$i}", 'category');
	$categories[] = $parent['term_id'];
	for ($j = 1; $j <= 2; $j++) {
		$child = wp_insert_term("Bench Cat {$i}.{$j}", 'category', array('parent' => $parent['term_id']));
		$categories[] = $child['term_id'];
	}
}
$content = str_repeat('เนื้อหาข่าวตัวอย่าง ', 150);
$oldest = time() - ($total + 10) * HOUR_IN_SECONDS;
mt_srand(42);
$start = microtime(true);
for ($i = 1; $i <= $total; $i++) {
	$cats = array($categories[mt_rand(0, 29)]);
	if (mt_rand(0, 3) === 0) {
		$cats[] = $categories[mt_rand(0, 29)];
	}
	wp_insert_post(array(
		'post_title' => "ข่าวทดสอบลำดับที่ {$i}",
		'post_content' => $content,
		'post_status' => 'publish',
		'post_date' => wp_date('Y-m-d H:i:s', $oldest + $i * HOUR_IN_SECONDS),
		'post_category' => $cats,
	));
}
wp_defer_term_counting(false);
wp_set_current_user(0);
printf("site: %d published posts in 30 categories (created in %.0fs)\n\n", wp_count_posts()->publish, microtime(true) - $start);

pas_test_apply_settings();

/**
 * @param bool $cold Clear every cache first (the first view after a change).
 */
$measure = function ($label, array $atts, $cold) {
	global $wpdb;
	if ($cold) {
		add_filter('parich_sitemap_use_cache', '__return_false');
	}
	wp_cache_flush(); // No persistent object cache: every request starts empty.
	if (function_exists('memory_reset_peak_usage')) {
		memory_reset_peak_usage();
	}
	$memory = memory_get_usage();
	$queries = $wpdb->num_queries;
	$start = microtime(true);
	$html = Parich_Sitemap_Renderer::render($atts);
	printf(
		"%-34s %7.3f s %5d queries %7.1f MB %6d links %6.0f KB\n",
		$label,
		microtime(true) - $start,
		$wpdb->num_queries - $queries,
		(memory_get_peak_usage() - $memory) / 1048576,
		substr_count($html, '<a '),
		strlen($html) / 1024
	);
	remove_filter('parich_sitemap_use_cache', '__return_false');
};

printf("%-34s %9s %13s %10s %12s %9s\n", '', 'time', '', 'peak mem', '', 'HTML');
$measure('posts by category — cold', array('only' => 'post'), true);
$measure('full sitemap — cold', array(), true);
Parich_Sitemap_Renderer::render(array('only' => 'post')); // Fill the cache.
Parich_Sitemap_Renderer::render();
$measure('posts by category — warm (cache)', array('only' => 'post'), false);
$measure('full sitemap — warm (cache)', array(), false);
$measure('20 posts per category — cold', array('only' => 'post', 'posts_per_category' => 20), true);

pas_test_done();
