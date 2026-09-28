<?php
/**
 * Settings → Parich Accessible Sitemap: stored settings, their sanitizing, and the admin page.
 *
 * @package Parich_Accessible_Sitemap
 */

if (!defined('ABSPATH')) {
	exit;
}

class Parich_Sitemap_Settings
{
	const PAGE = 'parich-accessible-sitemap';
	const GROUP = 'parich_sitemap';

	/** Parts of the full sitemap that can be switched off. */
	const SECTIONS = array('page', 'post', 'archive', 'author');

	public static function init()
	{
		add_action('admin_menu', array(__CLASS__, 'add_page'));
		add_action('admin_init', array(__CLASS__, 'register'));
		add_filter('plugin_action_links_' . plugin_basename(PARICH_SITEMAP_FILE), array(__CLASS__, 'action_links'));
		add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue'));
	}

	/**
	 * The settings screen's stylesheet, on that screen only.
	 *
	 * @param string $hook_suffix Screen being loaded.
	 */
	public static function enqueue($hook_suffix)
	{
		if ('settings_page_' . self::PAGE !== $hook_suffix) {
			return;
		}
		$file = dirname(PARICH_SITEMAP_FILE) . '/admin/settings.css';
		wp_enqueue_style('parich-sitemap-settings', plugins_url('admin/settings.css', PARICH_SITEMAP_FILE), array('dashicons'), (string) filemtime($file));
	}

	/**
	 * Every stored setting and its default. Archives and authors are hidden by
	 * default, as in WP Sitemap Page.
	 */
	public static function defaults()
	{
		return array(
			'parich_sitemap_excluded_sections' => array('archive', 'author'),
			'parich_sitemap_excluded_post_types' => array(),
			'parich_sitemap_excluded_taxonomies' => array(),
			'parich_sitemap_exclude_pages' => '',
			'parich_sitemap_exclude_password_protected' => false,
			'parich_sitemap_add_nofollow' => false,
			'parich_sitemap_display_post_multiple_times' => false,
			'parich_sitemap_post_template' => Parich_Sitemap_Renderer::DEFAULT_POST_TEMPLATE,
			// Thai sites show years in the Buddhist Era (พ.ศ.) unless this is switched off.
			'parich_sitemap_buddhist_era' => 0 === strpos(get_locale(), 'th'),
			// 0 lists every post; otherwise the first N per category and a link to the rest.
			'parich_sitemap_posts_per_category' => 0,
		);
	}

	/**
	 * Each setting with its type, its sanitizer and its default.
	 */
	public static function register()
	{
		$defaults = self::defaults();

		register_setting(self::GROUP, 'parich_sitemap_excluded_sections', array(
			'type' => 'array',
			'sanitize_callback' => array(__CLASS__, 'sanitize_sections'),
			'default' => $defaults['parich_sitemap_excluded_sections'],
		));
		register_setting(self::GROUP, 'parich_sitemap_excluded_post_types', array(
			'type' => 'array',
			'sanitize_callback' => array(__CLASS__, 'sanitize_keys'),
			'default' => $defaults['parich_sitemap_excluded_post_types'],
		));
		register_setting(self::GROUP, 'parich_sitemap_excluded_taxonomies', array(
			'type' => 'array',
			'sanitize_callback' => array(__CLASS__, 'sanitize_keys'),
			'default' => $defaults['parich_sitemap_excluded_taxonomies'],
		));
		register_setting(self::GROUP, 'parich_sitemap_exclude_pages', array(
			'type' => 'string',
			'sanitize_callback' => array(__CLASS__, 'sanitize_id_list'),
			'default' => $defaults['parich_sitemap_exclude_pages'],
		));
		register_setting(self::GROUP, 'parich_sitemap_exclude_password_protected', array(
			'type' => 'boolean',
			'sanitize_callback' => array(__CLASS__, 'sanitize_checkbox'),
			'default' => $defaults['parich_sitemap_exclude_password_protected'],
		));
		register_setting(self::GROUP, 'parich_sitemap_add_nofollow', array(
			'type' => 'boolean',
			'sanitize_callback' => array(__CLASS__, 'sanitize_checkbox'),
			'default' => $defaults['parich_sitemap_add_nofollow'],
		));
		register_setting(self::GROUP, 'parich_sitemap_display_post_multiple_times', array(
			'type' => 'boolean',
			'sanitize_callback' => array(__CLASS__, 'sanitize_checkbox'),
			'default' => $defaults['parich_sitemap_display_post_multiple_times'],
		));
		register_setting(self::GROUP, 'parich_sitemap_post_template', array(
			'type' => 'string',
			'sanitize_callback' => array(__CLASS__, 'sanitize_post_template'),
			'default' => $defaults['parich_sitemap_post_template'],
		));
		register_setting(self::GROUP, 'parich_sitemap_buddhist_era', array(
			'type' => 'boolean',
			'sanitize_callback' => array(__CLASS__, 'sanitize_checkbox'),
			'default' => $defaults['parich_sitemap_buddhist_era'],
		));
		register_setting(self::GROUP, 'parich_sitemap_posts_per_category', array(
			'type' => 'integer',
			'sanitize_callback' => array(__CLASS__, 'sanitize_posts_per_category'),
			'default' => $defaults['parich_sitemap_posts_per_category'],
		));
	}

	/** Only an explicit "1" (a ticked checkbox) is true; a missing field arrives as null. */
	public static function sanitize_checkbox($value)
	{
		return in_array($value, array(true, 1, '1'), true);
	}

	/** 0 (all) to 1000. */
	public static function sanitize_posts_per_category($value)
	{
		return is_numeric($value) ? max(0, min(1000, (int) $value)) : 0;
	}

	/** "1, 2,abc,3" → "1,2,3" */
	public static function sanitize_id_list($value)
	{
		return is_scalar($value) ? implode(',', array_filter(wp_parse_id_list((string) $value))) : '';
	}

	public static function sanitize_sections($value)
	{
		return array_values(array_intersect(self::SECTIONS, array_filter((array) $value, 'is_string')));
	}

	/** Post type / taxonomy names. */
	public static function sanitize_keys($value)
	{
		return array_values(array_unique(array_filter(array_map('sanitize_key', array_filter((array) $value, 'is_string')))));
	}

	/** Same HTML allow-list the renderer applies; an empty template falls back to the default. */
	public static function sanitize_post_template($value)
	{
		$value = is_string($value) ? trim(wp_kses($value, Parich_Sitemap_Renderer::POST_TEMPLATE_TAGS)) : '';
		return $value !== '' ? $value : Parich_Sitemap_Renderer::DEFAULT_POST_TEMPLATE;
	}

	/**
	 * @return string|false Hook suffix, or false when the user may not manage options.
	 */
	public static function add_page()
	{
		return add_options_page(
			__('Parich Accessible Sitemap', 'parich-accessible-sitemap'),
			// Short enough for one line in the admin menu; the page title keeps the full name.
			/* translators: admin menu label, a short form of the plugin name */
			__('Accessible Sitemap', 'parich-accessible-sitemap'),
			'manage_options',
			self::PAGE,
			array(__CLASS__, 'render_page')
		);
	}

	public static function action_links($links)
	{
		array_unshift($links, sprintf(
			'<a href="%s">%s</a>',
			esc_url(admin_url('options-general.php?page=' . self::PAGE)),
			esc_html__('Settings', 'parich-accessible-sitemap')
		));
		return $links;
	}

	private static function section_labels()
	{
		return array(
			'page' => __('Pages', 'parich-accessible-sitemap'),
			'post' => __('Posts by category', 'parich-accessible-sitemap'),
			'archive' => __('Archives', 'parich-accessible-sitemap'),
			'author' => __('Authors', 'parich-accessible-sitemap'),
		);
	}

	public static function render_page()
	{
		if (!current_user_can('manage_options')) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only selects which tab to show.
		$tab = isset($_GET['tab']) && sanitize_key(wp_unslash($_GET['tab'])) === 'usage' ? 'usage' : 'settings';
		$base = admin_url('options-general.php?page=' . self::PAGE);
		$tabs = array(
			'settings' => array(__('Settings', 'parich-accessible-sitemap'), $base),
			'usage' => array(__('How to use', 'parich-accessible-sitemap'), add_query_arg('tab', 'usage', $base)),
		);
		?>
		<div class="wrap">
			<h1><?php esc_html_e('Parich Accessible Sitemap', 'parich-accessible-sitemap'); ?></h1>
			<nav class="nav-tab-wrapper wp-clearfix" aria-label="<?php esc_attr_e('Parich Accessible Sitemap pages', 'parich-accessible-sitemap'); ?>">
				<?php foreach ($tabs as $key => $item) : ?>
					<a href="<?php echo esc_url($item[1]); ?>" class="nav-tab<?php echo $key === $tab ? ' nav-tab-active' : ''; ?>"<?php echo $key === $tab ? ' aria-current="page"' : ''; ?>><?php echo esc_html($item[0]); ?></a>
				<?php endforeach; ?>
			</nav>
			<?php
			if ($tab === 'usage') {
				self::render_usage();
			} else {
				self::render_form();
			}
			?>
		</div>
		<?php
	}

	private static function render_form()
	{
		$defaults = self::defaults();
		$value = function ($name) use ($defaults) {
			return get_option($name, $defaults[$name]);
		};
		$post_types = get_post_types(array('public' => true, '_builtin' => false), 'objects');
		$taxonomies = get_taxonomies(array('public' => true, '_builtin' => false), 'objects');
		$placeholders = array(
			'{title}' => __('post title', 'parich-accessible-sitemap'),
			'{permalink}' => __('post URL', 'parich-accessible-sitemap'),
			'{date}' => __('date in the site\'s date format (Settings → General), e.g. 27 September 2026', 'parich-accessible-sitemap'),
			'{year}' => __('year, e.g. 2026', 'parich-accessible-sitemap'),
			'{monthnum}' => __('month, e.g. 09', 'parich-accessible-sitemap'),
			'{day}' => __('day of the month, e.g. 27', 'parich-accessible-sitemap'),
			'{hour}' => __('hour, e.g. 15', 'parich-accessible-sitemap'),
			'{minute}' => __('minute, e.g. 43', 'parich-accessible-sitemap'),
			'{second}' => __('second, e.g. 33', 'parich-accessible-sitemap'),
			'{post_id}' => __('post ID, e.g. 423', 'parich-accessible-sitemap'),
			'{category}' => __('name of the first category', 'parich-accessible-sitemap'),
		);
		?>
		<form method="post" action="options.php">
			<?php settings_fields(self::GROUP); ?>

			<h2><?php esc_html_e('Posts', 'parich-accessible-sitemap'); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="parich_sitemap_post_template"><?php esc_html_e('How to display each post', 'parich-accessible-sitemap'); ?></label></th>
					<td>
						<textarea id="parich_sitemap_post_template" name="parich_sitemap_post_template" rows="3" class="large-text code" aria-describedby="parich_sitemap_post_template-help"><?php echo esc_textarea($value('parich_sitemap_post_template')); ?></textarea>
						<div id="parich_sitemap_post_template-help" class="description">
							<p><?php esc_html_e('Placeholders:', 'parich-accessible-sitemap'); ?></p>
							<ul>
								<?php foreach ($placeholders as $placeholder => $meaning) : ?>
									<li><code><?php echo esc_html($placeholder); ?></code> — <?php echo esc_html($meaning); ?></li>
								<?php endforeach; ?>
							</ul>
							<p>
								<?php
								/* translators: %s: default template */
								printf(esc_html__('Allowed HTML: a, strong, em, b, i, span, br. Default: %s', 'parich-accessible-sitemap'), '<code>' . esc_html(Parich_Sitemap_Renderer::DEFAULT_POST_TEMPLATE) . '</code>');
								?>
							</p>
						</div>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e('Year', 'parich-accessible-sitemap'); ?></th>
					<td>
						<fieldset>
							<legend class="screen-reader-text"><?php esc_html_e('Year', 'parich-accessible-sitemap'); ?></legend>
							<label for="parich_sitemap_buddhist_era">
								<input type="checkbox" id="parich_sitemap_buddhist_era" name="parich_sitemap_buddhist_era" value="1" aria-describedby="parich_sitemap_buddhist_era-help" <?php checked($value('parich_sitemap_buddhist_era')); ?>>
								<?php esc_html_e('Show years in the Buddhist Era (B.E.)', 'parich-accessible-sitemap'); ?>
							</label>
							<p id="parich_sitemap_buddhist_era-help" class="description"><?php esc_html_e('Applies to {date}, {year} and the archive list: 2026 is shown as 2569. On by default when the site language is Thai.', 'parich-accessible-sitemap'); ?></p>
						</fieldset>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="parich_sitemap_posts_per_category"><?php esc_html_e('Posts per category', 'parich-accessible-sitemap'); ?></label></th>
					<td>
						<input type="number" id="parich_sitemap_posts_per_category" name="parich_sitemap_posts_per_category" class="small-text" min="0" max="1000" step="1" value="<?php echo esc_attr((int) $value('parich_sitemap_posts_per_category')); ?>" aria-describedby="parich_sitemap_posts_per_category-help">
						<p id="parich_sitemap_posts_per_category-help" class="description"><?php esc_html_e('0 lists every post. Otherwise the first posts of each category are listed, followed by a link to the category page for the rest — recommended for sites with thousands of posts.', 'parich-accessible-sitemap'); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e('Posts in several categories', 'parich-accessible-sitemap'); ?></th>
					<td>
						<fieldset>
							<legend class="screen-reader-text"><?php esc_html_e('Posts in several categories', 'parich-accessible-sitemap'); ?></legend>
							<label for="parich_sitemap_display_post_multiple_times">
								<input type="checkbox" id="parich_sitemap_display_post_multiple_times" name="parich_sitemap_display_post_multiple_times" value="1" <?php checked($value('parich_sitemap_display_post_multiple_times')); ?>>
								<?php esc_html_e('List a post under every category it belongs to', 'parich-accessible-sitemap'); ?>
							</label>
							<p class="description"><?php esc_html_e('Otherwise a post is listed once, under its first category.', 'parich-accessible-sitemap'); ?></p>
						</fieldset>
					</td>
				</tr>
			</table>

			<h2><?php esc_html_e('Exclude from the full sitemap', 'parich-accessible-sitemap'); ?></h2>
			<p><?php esc_html_e('These settings apply to a sitemap without the "only" attribute. A sitemap with only="…" always shows that content.', 'parich-accessible-sitemap'); ?></p>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e('Hide sections', 'parich-accessible-sitemap'); ?></th>
					<td>
						<?php self::render_checkbox_group('parich_sitemap_excluded_sections', __('Hide sections', 'parich-accessible-sitemap'), self::section_labels(), (array) $value('parich_sitemap_excluded_sections')); ?>
					</td>
				</tr>
				<?php if ($post_types) : ?>
					<tr>
						<th scope="row"><?php esc_html_e('Hide content types', 'parich-accessible-sitemap'); ?></th>
						<td>
							<?php self::render_checkbox_group('parich_sitemap_excluded_post_types', __('Hide content types', 'parich-accessible-sitemap'), wp_list_pluck($post_types, 'label', 'name'), (array) $value('parich_sitemap_excluded_post_types')); ?>
						</td>
					</tr>
				<?php endif; ?>
				<?php if ($taxonomies) : ?>
					<tr>
						<th scope="row"><?php esc_html_e('Hide taxonomies', 'parich-accessible-sitemap'); ?></th>
						<td>
							<?php self::render_checkbox_group('parich_sitemap_excluded_taxonomies', __('Hide taxonomies', 'parich-accessible-sitemap'), wp_list_pluck($taxonomies, 'label', 'name'), (array) $value('parich_sitemap_excluded_taxonomies')); ?>
						</td>
					</tr>
				<?php endif; ?>
				<tr>
					<th scope="row"><label for="parich_sitemap_exclude_pages"><?php esc_html_e('Hide pages and posts', 'parich-accessible-sitemap'); ?></label></th>
					<td>
						<input type="text" id="parich_sitemap_exclude_pages" name="parich_sitemap_exclude_pages" class="regular-text code" inputmode="numeric" value="<?php echo esc_attr($value('parich_sitemap_exclude_pages')); ?>" aria-describedby="parich_sitemap_exclude_pages-help">
						<p id="parich_sitemap_exclude_pages-help" class="description"><?php esc_html_e('IDs separated by commas, e.g. 12,45,78.', 'parich-accessible-sitemap'); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e('Password-protected content', 'parich-accessible-sitemap'); ?></th>
					<td>
						<fieldset>
							<legend class="screen-reader-text"><?php esc_html_e('Password-protected content', 'parich-accessible-sitemap'); ?></legend>
							<label for="parich_sitemap_exclude_password_protected">
								<input type="checkbox" id="parich_sitemap_exclude_password_protected" name="parich_sitemap_exclude_password_protected" value="1" <?php checked($value('parich_sitemap_exclude_password_protected')); ?>>
								<?php esc_html_e('Hide pages, posts and other content protected by a password', 'parich-accessible-sitemap'); ?>
							</label>
						</fieldset>
					</td>
				</tr>
			</table>

			<h2><?php esc_html_e('Links', 'parich-accessible-sitemap'); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e('Search engines', 'parich-accessible-sitemap'); ?></th>
					<td>
						<fieldset>
							<legend class="screen-reader-text"><?php esc_html_e('Search engines', 'parich-accessible-sitemap'); ?></legend>
							<label for="parich_sitemap_add_nofollow">
								<input type="checkbox" id="parich_sitemap_add_nofollow" name="parich_sitemap_add_nofollow" value="1" aria-describedby="parich_sitemap_add_nofollow-help" <?php checked($value('parich_sitemap_add_nofollow')); ?>>
								<?php esc_html_e('Add rel="nofollow" to the links', 'parich-accessible-sitemap'); ?>
							</label>
							<p id="parich_sitemap_add_nofollow-help" class="description"><?php esc_html_e('Usually best left off: it tells search engines not to follow the sitemap links.', 'parich-accessible-sitemap'); ?></p>
						</fieldset>
					</td>
				</tr>
			</table>

			<?php submit_button(); ?>
		</form>
		<?php
	}

	/**
	 * Checkboxes saved as one array option, grouped in a fieldset (WCAG 1.3.1).
	 *
	 * @param string   $name     Option name.
	 * @param string   $legend   Group name for screen readers (the row heading shows it visually).
	 * @param string[] $choices  value => label.
	 * @param string[] $selected Checked values.
	 */
	private static function render_checkbox_group($name, $legend, array $choices, array $selected)
	{
		?>
		<fieldset>
			<legend class="screen-reader-text"><?php echo esc_html($legend); ?></legend>
			<?php foreach ($choices as $choice => $label) : ?>
				<?php $id = $name . '-' . sanitize_html_class($choice); ?>
				<label for="<?php echo esc_attr($id); ?>">
					<input type="checkbox" id="<?php echo esc_attr($id); ?>" name="<?php echo esc_attr($name); ?>[]" value="<?php echo esc_attr($choice); ?>" <?php checked(in_array($choice, $selected, true)); ?>>
					<?php echo esc_html($label); ?>
				</label><br>
			<?php endforeach; ?>
		</fieldset>
		<?php
	}

	private static function render_usage()
	{
		$rows = array(
			'[parich_sitemap]' => __('The full sitemap: pages, posts by category, other content types and taxonomies (plus archives and authors unless hidden in the settings).', 'parich-accessible-sitemap'),
			'[parich_sitemap only="page"]' => __('Only one kind of content. Also: post, category, tag, archive, author, or the name of a public content type or taxonomy (listed below).', 'parich-accessible-sitemap'),
			'[parich_sitemap only="page" sort="menu_order"]' => __('Order pages by: post_title, menu_order, post_date, post_modified, ID, post_author, post_name.', 'parich-accessible-sitemap'),
			'[parich_sitemap only="post" sort="post_name" order="ASC"]' => __('Order categories and their posts; order is ASC or DESC. Categories also accept: id, name, slug, count, term_group.', 'parich-accessible-sitemap'),
			'[parich_sitemap only="author" sort="post_count"]' => __('Order authors by: name, email, url, registered, id, user_login, post_count.', 'parich-accessible-sitemap'),
			'[parich_sitemap heading_level="3"]' => __('Heading level of each section (2–6, default 2). Pick the level that follows the headings above the sitemap on the page.', 'parich-accessible-sitemap'),
			'[parich_sitemap toc="false"]' => __('Hide the "On this page" links shown when there are two or more sections.', 'parich-accessible-sitemap'),
			'[parich_sitemap display_title="false"]' => __('Hide the section headings (the "On this page" links are hidden too).', 'parich-accessible-sitemap'),
			'[parich_sitemap display_category_title_wording="false"]' => __('Hide the word "Category:" before category names.', 'parich-accessible-sitemap'),
			'[parich_sitemap style="list"]' => __('Plain nested lists instead of the tree with connecting lines (the default). For the block, pick the "List" style.', 'parich-accessible-sitemap'),
			'[parich_sitemap only="post" collapsible="true"]' => __('Categories can be folded away, expanded at first; collapsible="closed" starts them folded.', 'parich-accessible-sitemap'),
			'[parich_sitemap only="post" posts_per_category="20"]' => __('At most 20 posts per category, then a link to the category page. Overrides the setting; 0 lists every post.', 'parich-accessible-sitemap'),
			'[parich_sitemap pages_title="หน้าเว็บไซต์"]' => __('Rename a section. Also: posts_title, categories_title, tags_title, archives_title, authors_title.', 'parich-accessible-sitemap'),
			'[parich_sitemap only="page" only_private="true"]' => __('Private pages only. Shown only to users who can read private pages.', 'parich-accessible-sitemap'),
		);
		$types = array_merge(
			wp_list_pluck(get_post_types(array('public' => true, '_builtin' => false), 'objects'), 'label', 'name'),
			wp_list_pluck(get_taxonomies(array('public' => true, '_builtin' => false), 'objects'), 'label', 'name')
		);
		?>
		<h2><?php esc_html_e('Shortcode', 'parich-accessible-sitemap'); ?></h2>
		<p><?php esc_html_e('Add the "Parich Accessible Sitemap" block, or one of these shortcodes, to any page, post or template.', 'parich-accessible-sitemap'); ?></p>
		<table class="widefat striped">
			<caption class="screen-reader-text"><?php esc_html_e('Shortcode examples', 'parich-accessible-sitemap'); ?></caption>
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e('Shortcode', 'parich-accessible-sitemap'); ?></th>
					<th scope="col"><?php esc_html_e('Shows', 'parich-accessible-sitemap'); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($rows as $shortcode => $meaning) : ?>
					<tr>
						<td><code><?php echo esc_html($shortcode); ?></code></td>
						<td><?php echo esc_html($meaning); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<?php if ($types) : ?>
			<h2><?php esc_html_e('Content types and taxonomies on this site', 'parich-accessible-sitemap'); ?></h2>
			<ul>
				<?php foreach ($types as $name => $label) : ?>
					<li><code>[parich_sitemap only="<?php echo esc_html($name); ?>"]</code> — <?php echo esc_html($label); ?></li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>

		<h2><?php esc_html_e('Coming from WP Sitemap Page', 'parich-accessible-sitemap'); ?></h2>
		<p><?php esc_html_e('Existing [wp_sitemap_page] shortcodes keep working once WP Sitemap Page is deactivated: they are shown by this plugin with the same attributes. Its settings are copied the first time this plugin runs.', 'parich-accessible-sitemap'); ?></p>

		<div class="parich-sitemap-support">
			<span class="parich-sitemap-support__icon dashicons dashicons-heart" aria-hidden="true"></span>
			<div>
				<h2><?php esc_html_e('Support this plugin', 'parich-accessible-sitemap'); ?></h2>
				<p><?php esc_html_e('Parich Accessible Sitemap is free, with every feature for everyone. If it is useful to you, you can support its development:', 'parich-accessible-sitemap'); ?></p>
				<p><a class="button button-primary button-hero" href="<?php echo esc_url(PARICH_SITEMAP_DONATE_URL); ?>"><?php esc_html_e('Donate', 'parich-accessible-sitemap'); ?></a></p>
				<p class="description"><?php esc_html_e('Secure payment by Stripe.', 'parich-accessible-sitemap'); ?></p>
			</div>
		</div>
		<?php
	}
}
