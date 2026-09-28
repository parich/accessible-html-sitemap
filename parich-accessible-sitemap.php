<?php
/**
 * Plugin Name:       Parich Accessible Sitemap
 * Plugin URI:        https://github.com/parich/parich-accessible-sitemap
 * Description:       Accessible (WCAG 2.1 AA) HTML sitemap with Thai language support. Use the Parich Accessible Sitemap block or the [parich_sitemap] shortcode.
 * Version:           1.0.0
 * Requires at least: 6.7
 * Requires PHP:      7.4
 * Author:            Mr.Parich Suriya
 * Author URI:        https://github.com/parich
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       parich-accessible-sitemap
 *
 * Forked from "WP Sitemap Page" 1.9.6 by Tony Archambeau
 * (https://wordpress.org/plugins/wp-sitemap-page/), released under GPLv2 or later.
 *
 * @package Parich_Accessible_Sitemap
 */

if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly.
}

define('PARICH_SITEMAP_FILE', __FILE__);

// Donation page, linked from the Plugins screen and the "How to use" tab
// (never from the sitemap on the site itself).
define('PARICH_SITEMAP_DONATE_URL', 'https://buy.stripe.com/3cIaEW9699pf3dyeAfaMU00');

require_once __DIR__ . '/includes/class-parich-sitemap-thai-sort.php';
require_once __DIR__ . '/includes/class-parich-sitemap-renderer.php';
require_once __DIR__ . '/includes/class-parich-sitemap-settings.php';
require_once __DIR__ . '/includes/class-parich-sitemap-migration.php';

Parich_Sitemap_Settings::init();

// Translations (Thai included) come as language packs from translate.wordpress.org:
// WordPress loads them when needed, and sets the editor script's text domain from block.json.

// Copy WP Sitemap Page's settings (or store defaults) on activation, and on the first
// load after an update or when the plugin was switched on before this existed.
// There is deliberately no deactivation hook: settings are removed by uninstall.php only.
register_activation_hook(__FILE__, array('Parich_Sitemap_Migration', 'maybe_run'));
add_action('init', array('Parich_Sitemap_Migration', 'maybe_run'));

// Cached sitemaps are rebuilt after any change to posts, terms, post-term links or
// users (author names). Settings and language are part of the cache key already.
foreach (array('clean_post_cache', 'clean_term_cache', 'clean_object_term_cache', 'profile_update', 'user_register', 'deleted_user') as $parich_sitemap_hook) {
	add_action($parich_sitemap_hook, array('Parich_Sitemap_Renderer', 'flush_cache'), 10, 0);
}

/**
 * Registers the block(s) from `build/blocks-manifest.php`.
 *
 * @see https://make.wordpress.org/core/2025/03/13/more-efficient-block-type-registration-in-6-8/
 */
function parich_sitemap_register_block()
{
	// WordPress 6.8+
	if (function_exists('wp_register_block_types_from_metadata_collection')) {
		wp_register_block_types_from_metadata_collection(__DIR__ . '/build', __DIR__ . '/build/blocks-manifest.php');
		return;
	}

	// WordPress 6.7
	if (function_exists('wp_register_block_metadata_collection')) {
		wp_register_block_metadata_collection(__DIR__ . '/build', __DIR__ . '/build/blocks-manifest.php');
	}
	$manifest_data = require __DIR__ . '/build/blocks-manifest.php';
	foreach (array_keys($manifest_data) as $block_type) {
		register_block_type(__DIR__ . "/build/{$block_type}");
	}
}
add_action('init', 'parich_sitemap_register_block');

/**
 * Front-end markup for the block, with the same options as the shortcode taken
 * from the block's camelCase attributes. Core has already checked them against
 * block.json (a value of the wrong type is replaced by its default); the renderer
 * validates the rest and escapes every value as it builds the markup.
 *
 * @param array $attributes Block attributes.
 * @return string
 */
function parich_sitemap_render_block($attributes)
{
	$args = array(
		'only' => $attributes['only'] ?? '',
		'sort' => $attributes['sort'] ?? '',
		'order' => $attributes['order'] ?? '',
		'display_title' => $attributes['displayTitle'] ?? true,
		'display_category_title_wording' => $attributes['displayCategoryTitleWording'] ?? true,
		'heading_level' => $attributes['headingLevel'] ?? 2,
		'toc' => $attributes['showToc'] ?? true,
		'only_private' => $attributes['onlyPrivate'] ?? false,
		// Not set: the setting applies.
		'posts_per_category' => $attributes['postsPerCategory'] ?? '',
		'collapsible' => $attributes['collapsible'] ?? '',
		// The tree/list choice is a block style: core adds is-style-list to the wrapper.
	);
	// sectionTitles: { page: "…", post: "…" } → pages_title="…", posts_title="…"
	foreach (Parich_Sitemap_Renderer::TITLE_ATTRIBUTES as $key => $attribute) {
		$args[$attribute] = $attributes['sectionTitles'][$key] ?? '';
	}
	return Parich_Sitemap_Renderer::render($args, get_block_wrapper_attributes(array('class' => 'parich-sitemap')));
}

/**
 * The block is dynamic: rendered by parich_sitemap_render_block(), which returns
 * the markup like the shortcode does (block.json has no "render" template).
 */
function parich_sitemap_block_type_args($args, $block_type)
{
	if ('parich-accessible-sitemap/sitemap' === $block_type) {
		$args['render_callback'] = 'parich_sitemap_render_block';
	}
	return $args;
}
add_filter('register_block_type_args', 'parich_sitemap_block_type_args', 10, 2);

/**
 * A "Donate" link under the plugin's description on the Plugins screen.
 */
function parich_sitemap_row_meta($links, $file)
{
	if (plugin_basename(__FILE__) === $file) {
		$links[] = '<a href="' . esc_url(PARICH_SITEMAP_DONATE_URL) . '">' . esc_html__('Donate', 'parich-accessible-sitemap') . '</a>';
	}
	return $links;
}
add_filter('plugin_row_meta', 'parich_sitemap_row_meta', 10, 2);

/**
 * Public content types and taxonomies for the block's "Show" setting. Printed from
 * PHP because the REST API leaves out types registered without show_in_rest,
 * although the sitemap lists them.
 */
function parich_sitemap_editor_data()
{
	$choices = function ($objects) {
		$list = array();
		foreach ($objects as $object) {
			$list[] = array('value' => $object->name, 'label' => $object->label);
		}
		return $list;
	};
	wp_add_inline_script(
		generate_block_asset_handle('parich-accessible-sitemap/sitemap', 'editorScript'),
		'window.parichSitemapEditor = ' . wp_json_encode(array(
			'postTypes' => $choices(get_post_types(array('public' => true, '_builtin' => false), 'objects')),
			'taxonomies' => $choices(get_taxonomies(array('public' => true, '_builtin' => false), 'objects')),
			'postsPerCategory' => (int) get_option('parich_sitemap_posts_per_category', 0),
		)) . ';',
		'before'
	);
}
add_action('enqueue_block_editor_assets', 'parich_sitemap_editor_data');

/**
 * [parich_sitemap] — same output as the block (both use Parich_Sitemap_Renderer).
 */
function parich_sitemap_shortcode($atts)
{
	// Enqueued here (not by scanning post_content) so it also works in block templates, widgets and archives.
	wp_enqueue_style(generate_block_asset_handle('parich-accessible-sitemap/sitemap', 'style'));

	// The tree is the default look; style="list" is the block's "List" style.
	$class = 'parich-sitemap';
	if (is_array($atts) && isset($atts['style']) && 'list' === strtolower(trim((string) $atts['style']))) {
		$class .= ' is-style-list';
	}
	return Parich_Sitemap_Renderer::render($atts, 'class="' . esc_attr($class) . '"');
}
add_shortcode('parich_sitemap', 'parich_sitemap_shortcode');

/**
 * Keeps existing [wp_sitemap_page] shortcodes working once WP Sitemap Page is
 * deactivated. That plugin registers the tag while loading, so while it is active
 * the tag already exists here and is left alone.
 */
function parich_sitemap_register_legacy_shortcode()
{
	if (!shortcode_exists('wp_sitemap_page')) {
		add_shortcode('wp_sitemap_page', 'parich_sitemap_shortcode');
	}
}
add_action('init', 'parich_sitemap_register_legacy_shortcode', 20);
