<?php
/**
 * First-run setup: copies the settings of WP Sitemap Page (wsp_*) when present,
 * otherwise stores the defaults.
 *
 * Runs from the activation hook and again on init, because an update installed
 * from a zip — or a plugin switched on before this code existed — never fires
 * the activation hook. It only fills settings that are missing and never
 * deletes the original plugin's options.
 *
 * @package Accessible_HTML_Sitemap
 */

if (!defined('ABSPATH')) {
	exit;
}

class Accessible_Sitemap_Migration
{
	/**
	 * Stored once setup has run. Bump DATA_VERSION when a release adds a setting,
	 * so existing sites get it stored with its default:
	 * 1 — first release; 2 — accessible_sitemap_buddhist_era; 3 — accessible_sitemap_posts_per_category.
	 */
	const VERSION_OPTION = 'accessible_sitemap_data_version';
	const DATA_VERSION = '3';

	public static function maybe_run()
	{
		$version = get_option(self::VERSION_OPTION, false);
		if ($version === self::DATA_VERSION) {
			return;
		}

		// WP Sitemap Page's settings are imported on the first run only; later
		// versions just add the settings that did not exist yet.
		$legacy = false === $version ? self::legacy_settings() : null;
		foreach (($legacy ?? array()) + Accessible_Sitemap_Settings::defaults() as $name => $value) {
			// Explicit default: once register_setting() has run (admin_init, before the
			// activation hook), get_option() without one returns the registered default
			// and a missing option would look present.
			if (null === get_option($name, null)) {
				add_option($name, $value);
			}
		}
		if (false === $version) {
			add_option('accessible_sitemap_migrated_from', $legacy ? 'wp-sitemap-page' : 'defaults');
		}
		update_option(self::VERSION_OPTION, self::DATA_VERSION);
	}

	/**
	 * Settings of WP Sitemap Page converted to this plugin's options, or null when
	 * it left none (it deletes them when deactivated, keeping only wsp_version_key).
	 */
	private static function legacy_settings()
	{
		global $wpdb;
		// Every wsp_* option at once (their names vary by post type and taxonomy); runs once per site.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare("SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like('wsp_') . '%'),
			OBJECT_K
		);
		unset($rows['wsp_version_key']);
		if (!$rows) {
			return null;
		}

		$is_on = function ($name) use ($rows) {
			return isset($rows[$name]) && '1' === (string) $rows[$name]->option_value;
		};

		// Exclusions were one option per section, content type and taxonomy.
		$sections = array();
		$post_types = array();
		$taxonomies = array();
		foreach (array_keys($rows) as $name) {
			if (!$is_on($name)) {
				continue;
			}
			if (preg_match('/^wsp_exclude_cpt_(.+)$/', $name, $matches)) {
				if (in_array($matches[1], Accessible_Sitemap_Settings::SECTIONS, true)) {
					$sections[] = $matches[1];
				} else {
					$post_types[] = $matches[1];
				}
			} elseif (preg_match('/^wsp_exclude_taxonomy_(.+)$/', $name, $matches)) {
				$taxonomies[] = $matches[1];
			}
		}

		$template = isset($rows['wsp_posts_by_category']) ? (string) $rows['wsp_posts_by_category']->option_value : '';

		return array(
			'accessible_sitemap_excluded_sections' => Accessible_Sitemap_Settings::sanitize_sections($sections),
			'accessible_sitemap_excluded_post_types' => Accessible_Sitemap_Settings::sanitize_keys($post_types),
			'accessible_sitemap_excluded_taxonomies' => Accessible_Sitemap_Settings::sanitize_keys($taxonomies),
			'accessible_sitemap_exclude_pages' => Accessible_Sitemap_Settings::sanitize_id_list(isset($rows['wsp_exclude_pages']) ? $rows['wsp_exclude_pages']->option_value : ''),
			'accessible_sitemap_exclude_password_protected' => $is_on('wsp_is_exclude_password_protected'),
			'accessible_sitemap_add_nofollow' => $is_on('wsp_add_nofollow'),
			'accessible_sitemap_display_post_multiple_times' => $is_on('wsp_is_display_post_multiple_time'),
			'accessible_sitemap_post_template' => Accessible_Sitemap_Settings::sanitize_post_template($template),
		);
	}
}
