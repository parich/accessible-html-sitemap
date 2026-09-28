<?php
/**
 * Deleting the plugin removes its settings (and only its settings).
 * Deactivating keeps them.
 *
 * @package Parich_Accessible_Sitemap
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
	exit;
}

global $wpdb;
// Names by prefix: cached sitemaps are one transient per set of options. Runs once, on uninstall.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$parich_sitemap_options = $wpdb->get_col(
	$wpdb->prepare(
		"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s",
		$wpdb->esc_like('parich_sitemap_') . '%',
		$wpdb->esc_like('_transient_parich_sitemap_') . '%',        // Cached sitemaps…
		$wpdb->esc_like('_transient_timeout_parich_sitemap_') . '%' // …and their expiry.
	)
);
foreach ($parich_sitemap_options as $parich_sitemap_option) {
	delete_option($parich_sitemap_option);
}
