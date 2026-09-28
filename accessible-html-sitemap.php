<?php
/**
 * Plugin Name:       Accessible HTML Sitemap
 * Plugin URI:        https://github.com/parich/accessible-html-sitemap
 * Description:       Accessible (WCAG 2.1 AA) HTML sitemap with Thai language support. Use the Accessible HTML Sitemap block or the [accessible_sitemap] shortcode.
 * Version:           1.0.0
 * Requires at least: 6.7
 * Requires PHP:      7.4
 * Author:            Mr.Parich Suriya
 * Author URI:        https://github.com/parich
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       accessible-html-sitemap
 * Domain Path:       /languages
 *
 * Forked from "WP Sitemap Page" 1.9.6 by Tony Archambeau
 * (https://wordpress.org/plugins/wp-sitemap-page/), released under GPLv2 or later.
 *
 * @package Accessible_HTML_Sitemap
 */

if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly.
}

define('ACCESSIBLE_SITEMAP_FILE', __FILE__);

// Donation page, linked from the Plugins screen and the "How to use" tab
// (never from the sitemap on the site itself).
define('ACCESSIBLE_SITEMAP_DONATE_URL', 'https://buy.stripe.com/3cIaEW9699pf3dyeAfaMU00');

require_once __DIR__ . '/includes/class-accessible-sitemap-thai-sort.php';
require_once __DIR__ . '/includes/class-accessible-sitemap-renderer.php';
require_once __DIR__ . '/includes/class-accessible-sitemap-settings.php';
require_once __DIR__ . '/includes/class-accessible-sitemap-migration.php';

Accessible_Sitemap_Settings::init();

/**
 * Translations ship in this plugin's languages/ folder (Thai: accessible-html-sitemap-th.mo).
 * A language pack from translate.wordpress.org, when installed, still takes precedence.
 * Priority 0: before the block is registered, as its title and description are
 * translated during registration.
 */
function accessible_sitemap_load_textdomain()
{
	// Needed for the bundled Thai translation (sites without a language pack, installs from GitHub).
	load_plugin_textdomain('accessible-html-sitemap', false, dirname(plugin_basename(__FILE__)) . '/languages'); // phpcs:ignore PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound
}
add_action('init', 'accessible_sitemap_load_textdomain', 0);

/**
 * Core sets the block editor script's text domain but looks for its JSON
 * translations in wp-content/languages/plugins; point it at languages/ here.
 */
function accessible_sitemap_script_translations()
{
	wp_set_script_translations(generate_block_asset_handle('accessible-html-sitemap/sitemap', 'editorScript'), 'accessible-html-sitemap', __DIR__ . '/languages');
}
add_action('init', 'accessible_sitemap_script_translations', 20);

// Copy WP Sitemap Page's settings (or store defaults) on activation, and on the first
// load after an update or when the plugin was switched on before this existed.
// There is deliberately no deactivation hook: settings are removed by uninstall.php only.
register_activation_hook(__FILE__, array('Accessible_Sitemap_Migration', 'maybe_run'));
add_action('init', array('Accessible_Sitemap_Migration', 'maybe_run'));

// Cached sitemaps are rebuilt after any change to posts, terms, post-term links or
// users (author names). Settings and language are part of the cache key already.
foreach (array('clean_post_cache', 'clean_term_cache', 'clean_object_term_cache', 'profile_update', 'user_register', 'deleted_user') as $accessible_sitemap_hook) {
	add_action($accessible_sitemap_hook, array('Accessible_Sitemap_Renderer', 'flush_cache'), 10, 0);
}

/**
 * Registers the block(s) from `build/blocks-manifest.php`.
 *
 * @see https://make.wordpress.org/core/2025/03/13/more-efficient-block-type-registration-in-6-8/
 */
function accessible_sitemap_register_block()
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
add_action('init', 'accessible_sitemap_register_block');

/**
 * Front-end markup for the block, with the same options as the shortcode taken
 * from the block's camelCase attributes. Core has already checked them against
 * block.json (a value of the wrong type is replaced by its default); the renderer
 * validates the rest and escapes every value as it builds the markup.
 *
 * @param array $attributes Block attributes.
 * @return string
 */
function accessible_sitemap_render_block($attributes)
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
	foreach (Accessible_Sitemap_Renderer::TITLE_ATTRIBUTES as $key => $attribute) {
		$args[$attribute] = $attributes['sectionTitles'][$key] ?? '';
	}
	return Accessible_Sitemap_Renderer::render($args, get_block_wrapper_attributes(array('class' => 'accessible-sitemap')));
}

/**
 * The block is dynamic: rendered by accessible_sitemap_render_block(), which returns
 * the markup like the shortcode does (block.json has no "render" template).
 */
function accessible_sitemap_block_type_args($args, $block_type)
{
	if ('accessible-html-sitemap/sitemap' === $block_type) {
		$args['render_callback'] = 'accessible_sitemap_render_block';
	}
	return $args;
}
add_filter('register_block_type_args', 'accessible_sitemap_block_type_args', 10, 2);

/**
 * A "Donate" link under the plugin's description on the Plugins screen.
 */
function accessible_sitemap_row_meta($links, $file)
{
	if (plugin_basename(__FILE__) === $file) {
		$links[] = '<a href="' . esc_url(ACCESSIBLE_SITEMAP_DONATE_URL) . '">' . esc_html__('Donate', 'accessible-html-sitemap') . '</a>';
	}
	return $links;
}
add_filter('plugin_row_meta', 'accessible_sitemap_row_meta', 10, 2);

/**
 * Public content types and taxonomies for the block's "Show" setting. Printed from
 * PHP because the REST API leaves out types registered without show_in_rest,
 * although the sitemap lists them.
 */
function accessible_sitemap_editor_data()
{
	$choices = function ($objects) {
		$list = array();
		foreach ($objects as $object) {
			$list[] = array('value' => $object->name, 'label' => $object->label);
		}
		return $list;
	};
	wp_add_inline_script(
		generate_block_asset_handle('accessible-html-sitemap/sitemap', 'editorScript'),
		'window.accessibleSitemapEditor = ' . wp_json_encode(array(
			'postTypes' => $choices(get_post_types(array('public' => true, '_builtin' => false), 'objects')),
			'taxonomies' => $choices(get_taxonomies(array('public' => true, '_builtin' => false), 'objects')),
			'postsPerCategory' => (int) get_option('accessible_sitemap_posts_per_category', 0),
		)) . ';',
		'before'
	);
}
add_action('enqueue_block_editor_assets', 'accessible_sitemap_editor_data');

/**
 * [accessible_sitemap] — same output as the block (both use Accessible_Sitemap_Renderer).
 */
function accessible_sitemap_shortcode($atts)
{
	// Enqueued here (not by scanning post_content) so it also works in block templates, widgets and archives.
	wp_enqueue_style(generate_block_asset_handle('accessible-html-sitemap/sitemap', 'style'));

	// The tree is the default look; style="list" is the block's "List" style.
	$class = 'accessible-sitemap';
	if (is_array($atts) && isset($atts['style']) && 'list' === strtolower(trim((string) $atts['style']))) {
		$class .= ' is-style-list';
	}
	return Accessible_Sitemap_Renderer::render($atts, 'class="' . esc_attr($class) . '"');
}
add_shortcode('accessible_sitemap', 'accessible_sitemap_shortcode');

/**
 * Keeps existing [wp_sitemap_page] shortcodes working once WP Sitemap Page is
 * deactivated. That plugin registers the tag while loading, so while it is active
 * the tag already exists here and is left alone.
 */
function accessible_sitemap_register_legacy_shortcode()
{
	if (!shortcode_exists('wp_sitemap_page')) {
		add_shortcode('wp_sitemap_page', 'accessible_sitemap_shortcode');
	}
}
add_action('init', 'accessible_sitemap_register_legacy_shortcode', 20);
