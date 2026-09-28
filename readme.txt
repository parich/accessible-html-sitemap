=== Parich Accessible Sitemap ===
Contributors: parich
Donate link: https://buy.stripe.com/3cIaEW9699pf3dyeAfaMU00
Tags: sitemap, html sitemap, accessibility, wcag, thai
Requires at least: 6.7
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

An accessible (WCAG 2.1 AA) HTML sitemap block and shortcode: tree view, collapsible categories, Thai translation and Thai dictionary order.

== Description ==

Parich Accessible Sitemap adds a sitemap page that people can read: your pages, your posts by category, other content types and taxonomies, archives and authors. Add the **Parich Accessible Sitemap** block to any page, or use the `[parich_sitemap]` shortcode.

= Built for accessibility (WCAG 2.1 AA) =

* The sitemap is a labelled navigation landmark with real headings (you choose the level, 2–6, to fit your page) and nested lists that screen readers announce with their level. No custom widgets to learn.
* A table of contents jumps to each section, and a "Back to contents" link ends every section.
* Visible keyboard focus. Collapsible categories use native `<details>`/`<summary>`, so they work with the keyboard and screen readers and need no JavaScript.
* The link to the page being viewed is marked with `aria-current`.
* Reflows to a 320px-wide screen without horizontal scrolling, and is sized in `rem`/`em` for zoom and custom text spacing.
* Tree lines at full strength in Windows contrast themes and with "increase contrast"; a clean printout (no table of contents, collapsed categories printed open).
* The markup is tested with axe-core and in a real browser; the tests and a manual checklist (keyboard, NVDA, zoom) are in the GitHub repository.

Colors and fonts come from your theme, so text contrast is the theme's to get right.

= Thai language support =

* Thai translation of the sitemap, the settings page and the block editor, delivered as a language pack from translate.wordpress.org.
* Years in the Buddhist Era (พ.ศ.) on Thai sites, in any date format.
* Thai titles and names in dictionary order. Databases sort words that start with เ แ โ ใ ไ after all others; this plugin sorts them by their first consonant, as a Thai dictionary does (กา เกม ไก่ ขนม …).
* Line spacing that leaves room for Thai vowels and tone marks.

= Fast on large sites =

* The sitemap is cached and rebuilt automatically when posts, pages, categories or authors change.
* Posts are loaded in batches without their content, so memory stays low however many posts there are.
* Optionally list only the first posts of each category, with a link to the category page for the rest.

On a test site with 5,000 posts, building the sitemap took under a second and serving it from the cache a few milliseconds.

= Display =

* A tree with connecting lines (default), or plain nested lists (the block's "List" style, or `style="list"`).
* Collapsible categories, open or closed at first.
* Show everything, or only one kind of content: pages, posts, categories, tags, archives, authors, or any public content type or taxonomy.

= Coming from WP Sitemap Page =

This plugin started as a fork of [WP Sitemap Page](https://wordpress.org/plugins/wp-sitemap-page/) 1.9.6 by Tony Archambeau (GPLv2 or later). Its settings are copied the first time this plugin runs. Replace `[wp_sitemap_page]` shortcodes with `[parich_sitemap]`; they take the same attributes.

= Source code =

The complete source, including the block editor code in `src/`, ships with the plugin and is on GitHub: [github.com/parich/parich-accessible-sitemap](https://github.com/parich/parich-accessible-sitemap). Build it with `npm install` and `npm run build`.

== Installation ==

1. In your dashboard go to **Plugins → Add New Plugin**, search for "Parich Accessible Sitemap", then install and activate it. You can also upload the ZIP file under **Plugins → Add New Plugin → Upload Plugin**.
2. Edit the page that should show the sitemap and add the **Parich Accessible Sitemap** block, or the `[parich_sitemap]` shortcode.
3. Optional: choose what to hide and how posts are shown under **Settings → Parich Accessible Sitemap**. The **How to use** tab lists every shortcode option.

== Frequently Asked Questions ==

= Is this an XML sitemap for search engines? =

No. WordPress already publishes one for search engines at `/wp-sitemap.xml`. This plugin makes the sitemap page that visitors read.

= Which shortcode options are there? =

* `only` — one kind of content: `page`, `post`, `category`, `tag`, `archive`, `author`, or the name of a public content type or taxonomy.
* `sort` and `order` — for example `[parich_sitemap only="page" sort="menu_order"]` or `[parich_sitemap only="post" sort="post_title" order="ASC"]`.
* `heading_level` — 2 to 6 (default 2).
* `toc="false"` — no table of contents; `display_title="false"` — no section headings.
* `display_category_title_wording="false"` — no "Category:" before category names.
* `style="list"` — plain lists instead of the tree.
* `collapsible="true"` or `collapsible="closed"` — collapsible categories.
* `posts_per_category="20"` — at most 20 posts per category.
* `pages_title`, `posts_title`, `categories_title`, `tags_title`, `archives_title`, `authors_title` — rename a section.
* `only_private="true"` — private pages, shown only to users who can read them.

The block has the same options in its settings panel.

= My site has thousands of posts. Will the sitemap be slow? =

Only the first visit after a change builds it; later visits are served from the cache. To make the page itself shorter, set **Posts per category** in the settings (or `posts_per_category` in the shortcode).

= Does this make my whole site accessible? =

No. It makes the sitemap's markup accessible. Colors, contrast and the rest of the page come from your theme and your content.

= Can I turn off the Thai dictionary order? =

Yes: `add_filter( 'parich_sitemap_thai_sort', '__return_false' );`. Lists without any Thai are never re-sorted. The PHP intl extension is used when the server has it; otherwise a built-in method gives the same order.

= Which filters are there for developers? =

* `parich_sitemap_use_cache` — return false to build the sitemap on every view.
* `parich_sitemap_cache_ttl` — how long a cached sitemap is kept, in seconds (default one day).
* `parich_sitemap_thai_sort` — return false to keep the database order.
* `parich_sitemap_collator` — a `Collator` to sort Thai with, or null for the built-in method.
* `shortcode_atts_parich_sitemap` — WordPress's standard filter for the shortcode's attributes.

= How can I support the plugin? =

The plugin is free, with every feature for everyone. If it is useful to you, you can [make a donation](https://buy.stripe.com/3cIaEW9699pf3dyeAfaMU00). Thank you!

== Screenshots ==

1. The sitemap as a tree (the default), with its table of contents.
2. Collapsible categories.
3. The block in the editor, with its settings.
4. The settings page.
5. On a Thai site: Thai translation, Buddhist Era years and Thai dictionary order.

== Changelog ==

= 1.0.0 =
* First release, forked from WP Sitemap Page 1.9.6.
* Accessible markup (WCAG 2.1 AA): navigation landmark, table of contents, headings of your chosen level, nested lists, `aria-current`, "Back to contents" links.
* Block with a live preview in the editor, as well as the shortcode.
* Tree display and collapsible categories.
* Thai translation, Buddhist Era years and Thai dictionary order.
* Cache, lightweight loading and an optional limit of posts per category for large sites.
* Styles for Windows contrast themes, "increase contrast" and print.
* Settings copied from WP Sitemap Page; its shortcode attributes work unchanged in `[parich_sitemap]`.

== Upgrade Notice ==

= 1.0.0 =
First release.
