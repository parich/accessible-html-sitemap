<?php
/**
 * Phase 4: settings (sanitizing, admin page), migration from WP Sitemap Page,
 * no [wp_sitemap_page] alias (moving from that plugin means renaming the shortcode), and uninstall.
 *
 * @package Parich_Accessible_Sitemap
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../fixtures.php';
require_once ABSPATH . 'wp-admin/includes/template.php'; // submit_button(), used by the settings page.

$f = pas_test_create_fixtures(); // Registers a public custom post type and taxonomy for the settings page.
pas_test_a11y_reset('04');

global $wpdb;
$options_like = function ($prefix) use ($wpdb) {
	return $wpdb->get_col($wpdb->prepare("SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like($prefix) . '%'));
};
$forget_all = function () use ($options_like) {
	foreach (array_merge($options_like('parich_sitemap_'), $options_like('wsp_')) as $name) {
		delete_option($name);
	}
};

pas_test_section('Sanitizing');
$S = 'Parich_Sitemap_Settings';
$S::register();
$registered = array_intersect_key(get_registered_settings(), $S::defaults());
$complete = count($registered) === count($S::defaults());
foreach ($registered as $name => $args) {
	$complete = $complete && ($args['group'] ?? '') === $S::GROUP && in_array($args['type'] ?? '', array('array', 'string', 'boolean', 'integer'), true)
		&& is_callable($args['sanitize_callback'] ?? null) && $args['sanitize_callback'] !== 'sanitize_text_field';
}
pas_test_assert('every setting is registered with a type and its own sanitize_callback', $complete, wp_json_encode(array_map(function ($args) {
	return array($args['type'] ?? null, is_array($args['sanitize_callback'] ?? null) ? $args['sanitize_callback'][1] : ($args['sanitize_callback'] ?? null));
}, $registered)));
pas_test_assert('IDs "1, 2,abc,3" → "1,2,3"', $S::sanitize_id_list('1, 2,abc,3') === '1,2,3', $S::sanitize_id_list('1, 2,abc,3'));
pas_test_assert('IDs from an array → ""', $S::sanitize_id_list(array('1')) === '');
$template = $S::sanitize_post_template('<a href="{permalink}" onclick="steal()">{title}</a><script>alert(1)</script> <a href="javascript:alert(2)">x</a>');
pas_test_assert('template: <script> removed', stripos($template, '<script') === false, $template);
pas_test_assert('template: onclick removed', stripos($template, 'onclick') === false, $template);
pas_test_assert('template: javascript: URL removed', stripos($template, 'javascript:') === false, $template);
pas_test_assert('template: {permalink} placeholder kept in href', strpos($template, 'href="{permalink}"') !== false, $template);
pas_test_assert('empty template → default', $S::sanitize_post_template('   ') === Parich_Sitemap_Renderer::DEFAULT_POST_TEMPLATE);
pas_test_assert('checkbox "1" → true', $S::sanitize_checkbox('1') === true);
foreach (array('unchecked (null)' => null, '"yes please"' => 'yes please', 'array' => array('1'), '"0"' => '0') as $label => $input) {
	pas_test_assert("checkbox {$label} → false", $S::sanitize_checkbox($input) === false);
}
pas_test_assert('sections keep only known values', $S::sanitize_sections(array('page', 'evil', 'archive', array('x'))) === array('page', 'archive'));
pas_test_assert('sections: nothing ticked (null) → []', $S::sanitize_sections(null) === array());
pas_test_assert('type names are sanitized keys', $S::sanitize_keys(array('pas_test_book', 'Bad Key!', '', array('x'))) === array('pas_test_book', 'badkey'));

pas_test_section('Settings API wiring');
$S::register();
pas_test_assert('options.php path sanitizes the ID list', sanitize_option('parich_sitemap_exclude_pages', ' 4 ,x, 9') === '4,9');
pas_test_assert('unticked checkbox saves false', sanitize_option('parich_sitemap_add_nofollow', null) === false);
pas_test_assert('no sections ticked saves []', sanitize_option('parich_sitemap_excluded_sections', null) === array());

pas_test_section('Migration from WP Sitemap Page');
$forget_all();
$legacy = array(
	'wsp_exclude_cpt_page' => '1',
	'wsp_exclude_cpt_post' => '',
	'wsp_exclude_cpt_archive' => '',
	'wsp_exclude_cpt_author' => '1',
	'wsp_exclude_cpt_pas_test_book' => '1',
	'wsp_exclude_taxonomy_pas_test_genre' => '1',
	'wsp_exclude_pages' => '5,7',
	'wsp_is_exclude_password_protected' => '1',
	'wsp_add_nofollow' => '1',
	'wsp_is_display_post_multiple_time' => '1',
	'wsp_posts_by_category' => '<a href="{permalink}">{title}</a> ({day}/{monthnum}/{year})',
	'wsp_version_key' => '1.3.0',
);
foreach ($legacy as $name => $value) {
	add_option($name, $value);
}
Parich_Sitemap_Migration::maybe_run();
$expected = array(
	'parich_sitemap_excluded_sections' => array('page', 'author'),
	'parich_sitemap_excluded_post_types' => array('pas_test_book'),
	'parich_sitemap_excluded_taxonomies' => array('pas_test_genre'),
	'parich_sitemap_exclude_pages' => '5,7',
	'parich_sitemap_exclude_password_protected' => true,
	'parich_sitemap_add_nofollow' => true,
	'parich_sitemap_display_post_multiple_times' => true,
	'parich_sitemap_post_template' => '<a href="{permalink}">{title}</a> ({day}/{monthnum}/{year})',
);
foreach ($expected as $name => $value) {
	// Booleans are stored as "1" / "" by the options table.
	$stored = get_option($name);
	$same = is_bool($value) ? (bool) $stored === $value : $stored === $value;
	pas_test_assert("{$name} copied", $same, var_export($stored, true));
}
pas_test_assert('recorded as migrated from wp-sitemap-page', get_option('parich_sitemap_migrated_from') === 'wp-sitemap-page');
pas_test_assert('original options are kept', get_option('wsp_add_nofollow') === '1' && get_option('wsp_exclude_pages') === '5,7');
pas_test_assert('sitemap uses the migrated settings (archive shown, author hidden)', (function () {
	$html = Parich_Sitemap_Renderer::render();
	return strpos($html, 'parich-sitemap__section--archive') !== false && strpos($html, 'parich-sitemap__section--author') === false;
})());

update_option('parich_sitemap_add_nofollow', false);
Parich_Sitemap_Migration::maybe_run();
pas_test_assert('running again does not overwrite a changed setting', !get_option('parich_sitemap_add_nofollow'));
delete_option(Parich_Sitemap_Migration::VERSION_OPTION);
Parich_Sitemap_Migration::maybe_run();
pas_test_assert('re-activation (setup runs again) does not overwrite it either', !get_option('parich_sitemap_add_nofollow'), var_export(get_option('parich_sitemap_add_nofollow'), true));
pas_test_assert('activation hook is registered', (bool) has_action('activate_' . PAS_TEST_PLUGIN_BASENAME));
pas_test_assert('no deactivation hook (settings survive deactivation)', !has_action('deactivate_' . PAS_TEST_PLUGIN_BASENAME));

pas_test_section('Defaults when there is nothing to copy');
$forget_all();
Parich_Sitemap_Migration::maybe_run();
pas_test_assert('fresh install: archives and authors hidden', get_option('parich_sitemap_excluded_sections') === array('archive', 'author'));
pas_test_assert('fresh install: default post template', get_option('parich_sitemap_post_template') === Parich_Sitemap_Renderer::DEFAULT_POST_TEMPLATE);
pas_test_assert('fresh install: recorded as defaults', get_option('parich_sitemap_migrated_from') === 'defaults');
$forget_all();
add_option('wsp_version_key', '1.3.0'); // What WP Sitemap Page leaves behind after deactivation.
Parich_Sitemap_Migration::maybe_run();
pas_test_assert('only wsp_version_key left: defaults', get_option('parich_sitemap_migrated_from') === 'defaults' && get_option('parich_sitemap_excluded_sections') === array('archive', 'author'));

pas_test_section('Upgrade from data version 1 (before the Buddhist Era setting)');
update_option(Parich_Sitemap_Migration::VERSION_OPTION, '1');
delete_option('parich_sitemap_buddhist_era');
update_option('parich_sitemap_add_nofollow', true);
update_option('parich_sitemap_migrated_from', 'wp-sitemap-page');
Parich_Sitemap_Migration::maybe_run();
pas_test_assert('new setting is stored with its default', null !== get_option('parich_sitemap_buddhist_era', null));
pas_test_assert('existing settings are untouched', (bool) get_option('parich_sitemap_add_nofollow') && get_option('parich_sitemap_migrated_from') === 'wp-sitemap-page');
pas_test_assert('data version is now current', get_option(Parich_Sitemap_Migration::VERSION_OPTION) === Parich_Sitemap_Migration::DATA_VERSION);
$was = get_option('parich_sitemap_buddhist_era');
update_option('parich_sitemap_buddhist_era', !$was);
pas_test_assert('the stored setting can now be switched off and on', (bool) get_option('parich_sitemap_buddhist_era') === !$was);

pas_test_section('No [wp_sitemap_page] alias');
// wp_ is reserved for WordPress core and the tag belongs to WP Sitemap Page: this plugin never registers it.
pas_test_assert(
	'[wp_sitemap_page] is not registered by this plugin',
	!function_exists('parich_sitemap_register_legacy_shortcode') && ($GLOBALS['shortcode_tags']['wp_sitemap_page'] ?? null) !== 'parich_sitemap_shortcode',
	(string) ($GLOBALS['shortcode_tags']['wp_sitemap_page'] ?? '')
);
pas_test_apply_settings();

pas_test_section('Settings page');
$GLOBALS['submenu'] = array();
$GLOBALS['_wp_submenu_nopriv'] = array();
wp_set_current_user($f['author']);
pas_test_assert('an author cannot add the page', false === Parich_Sitemap_Settings::add_page());
ob_start();
Parich_Sitemap_Settings::render_page();
pas_test_assert('an author gets no page content', ob_get_clean() === '');

wp_set_current_user($f['admin']);
pas_test_assert('an administrator gets the page', is_string(Parich_Sitemap_Settings::add_page()));
$entry = current(array_filter($GLOBALS['submenu']['options-general.php'] ?? array(), function ($item) {
	return 'parich-accessible-sitemap' === ($item[2] ?? '');
}));
pas_test_assert(
	'menu label "Accessible Sitemap" (one line), page title "Parich Accessible Sitemap"',
	$entry && 'Accessible Sitemap' === $entry[0] && 'Parich Accessible Sitemap' === $entry[3],
	wp_json_encode($entry)
);
ob_start();
Parich_Sitemap_Settings::render_page();
$page = ob_get_clean();
pas_test_assert('form posts to options.php with the settings nonce', strpos($page, 'action="options.php"') !== false && strpos($page, "name='option_page' value='parich_sitemap'") !== false && strpos($page, 'name="_wpnonce"') !== false);

$processor = new WP_HTML_Tag_Processor($page);
$controls = array();
$label_for = array();
$fieldsets = 0;
$legends = 0;
while ($processor->next_tag()) {
	$tag = $processor->get_tag();
	if (($tag === 'INPUT' && !in_array($processor->get_attribute('type'), array('hidden', 'submit'), true)) || $tag === 'TEXTAREA') {
		$controls[] = (string) $processor->get_attribute('id');
	} elseif ($tag === 'LABEL') {
		$label_for[] = (string) $processor->get_attribute('for');
	} elseif ($tag === 'FIELDSET') {
		$fieldsets++;
	} elseif ($tag === 'LEGEND') {
		$legends++;
	}
}
pas_test_assert('every field has an id and a <label for>', count($controls) > 5 && !in_array('', $controls, true) && !array_diff($controls, $label_for), implode(',', array_diff($controls, $label_for)));
pas_test_assert('field ids are unique', count($controls) === count(array_unique($controls)));
pas_test_assert('checkbox groups are fieldsets with a legend', $fieldsets >= 4 && $fieldsets === $legends, "{$fieldsets} fieldsets, {$legends} legends");
pas_test_assert('custom post type and taxonomy can be hidden', strpos($page, 'parich_sitemap_excluded_post_types-pas_test_book') !== false && strpos($page, 'parich_sitemap_excluded_taxonomies-pas_test_genre') !== false);
pas_test_assert('no donate or external links', stripos($page, 'paypal') === false && stripos($page, 'donate') === false);
pas_test_write_a11y_page('04-settings', $page, '');

$_GET['tab'] = 'usage';
ob_start();
Parich_Sitemap_Settings::render_page();
$usage = ob_get_clean();
unset($_GET['tab']);
pas_test_assert('"How to use" documents heading_level, toc and how to move from [wp_sitemap_page]', strpos($usage, 'heading_level') !== false && strpos($usage, 'toc=') !== false && strpos($usage, 'Replace [wp_sitemap_page] shortcodes with [parich_sitemap]') !== false);
pas_test_assert('"How to use" tab is marked current', strpos($usage, 'nav-tab-active" aria-current="page">How to use') !== false);
pas_test_assert(
	'"How to use" ends with a support box: decorative heart, Donate button (Stripe only)',
	strpos($usage, '>Support this plugin</h2>') !== false
		&& strpos($usage, '<span class="parich-sitemap-support__icon dashicons dashicons-heart" aria-hidden="true"></span>') !== false
		&& strpos($usage, '<a class="button button-primary button-hero" href="' . PARICH_SITEMAP_DONATE_URL . '">Donate</a>') !== false
		&& stripos($usage, 'paypal') === false
);
Parich_Sitemap_Settings::enqueue('index.php');
$loaded_elsewhere = wp_style_is('parich-sitemap-settings', 'enqueued');
Parich_Sitemap_Settings::enqueue('settings_page_parich-accessible-sitemap');
$style = wp_styles()->registered['parich-sitemap-settings'] ?? null;
pas_test_assert(
	'settings stylesheet: only on this screen, a local file, with Dashicons',
	!$loaded_elsewhere && wp_style_is('parich-sitemap-settings', 'enqueued') && $style
		&& 0 === strpos($style->src, plugins_url('admin/settings.css', PAS_TEST_PLUGIN_FILE)) && in_array('dashicons', $style->deps, true)
		&& file_exists(PAS_TEST_PLUGIN_DIR . '/admin/settings.css')
);
$donate = function ($file) {
	return preg_grep('#buy\.stripe\.com#', apply_filters('plugin_row_meta', array(), $file, array(), 'all'));
};
pas_test_assert('Plugins screen: a "Donate" link in this plugin\'s row only', 1 === count($donate(PAS_TEST_PLUGIN_BASENAME)) && !$donate('hello.php'));
pas_test_assert('no donation links in the sitemap on the site (guideline 10)', !preg_match('#stripe|paypal#i', Parich_Sitemap_Renderer::render() . do_blocks('<!-- wp:parich-accessible-sitemap/sitemap /-->')));
pas_test_write_a11y_page('04-settings-usage', $usage, '');

$links = apply_filters('plugin_action_links_' . PAS_TEST_PLUGIN_BASENAME, array());
pas_test_assert('Settings link on the Plugins screen', isset($links[0]) && strpos($links[0], 'options-general.php?page=parich-accessible-sitemap') !== false);

pas_test_section('Uninstall');
Parich_Sitemap_Migration::maybe_run();
update_option('wsp_add_nofollow', '1'); // Already created ('') by pas_test_apply_settings() above.
pas_test_assert('before: settings exist', count($options_like('parich_sitemap_')) > 5);
define('WP_UNINSTALL_PLUGIN', PAS_TEST_PLUGIN_BASENAME);
include PAS_TEST_PLUGIN_DIR . '/uninstall.php';
wp_cache_flush();
pas_test_assert('uninstall removes every parich_sitemap_* option', $options_like('parich_sitemap_') === array(), implode(',', $options_like('parich_sitemap_')));
pas_test_assert('uninstall leaves WP Sitemap Page options alone', get_option('wsp_add_nofollow') === '1', var_export(get_option('wsp_add_nofollow'), true));
pas_test_assert('uninstall leaves other options alone', get_option('blogname') !== false);

pas_test_done();
