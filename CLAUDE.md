# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**Parich Accessible Sitemap** is a human-readable (HTML) sitemap plugin, forked from [WP Sitemap Page](https://wordpress.org/plugins/wp-sitemap-page/) 1.9.6 by Tony Archambeau (GPLv2+). Goals: WCAG 2.1 AA markup, Thai language support, and a standard `@wordpress/scripts` build and release workflow.

Phased plans (made with `/plan`) live in `docs/plans/`, which is gitignored: local working notes, not part of the repository. When one exists, read it first and keep its checkboxes up to date.

## Conventions

- Slug / text domain: `parich-accessible-sitemap`; PHP prefix `parich_sitemap_` (functions and options); classes `Parich_Sitemap_*`
- Block name `parich-accessible-sitemap/sitemap`; shortcode `[parich_sitemap]`
- Never reuse the `wsp_` prefix or the `wp-sitemap-page` slug — WordPress.org would offer the original plugin as an update and overwrite this one
- PHP style: tabs, braces on a new line for functions and classes; JS follows `@wordpress/scripts` lint rules

## Commands

```bash
npm run build       # src/ -> build/ (commit build/)
npm start           # watch mode
npm run lint:js
npm run lint:css
npm run plugin-zip  # parich-accessible-sitemap.zip from the "files" list in package.json (main file, uninstall.php, readme.txt, admin/, build/, includes/, src/) plus package.json — no translation files
npm run i18n        # after `npm run build`: .pot → update .po → .mo + editor JSON; lists untranslated strings
npm run bench       # performance on a generated site (BENCH_POSTS=5000 npm run bench), rolled back afterwards
npm test            # test:php, test:a11y, test:reflow, then test:media
npm run test:php [-- <filter>]
npm run test:a11y
npm run test:reflow
npm run test:media
```

## Rendering and cache

- The block is dynamic through `parich_sitemap_render_block()` (a `render_callback` added with the `register_block_type_args` filter; block.json has no `render` file), so the markup is returned, never echoed — the renderer escapes every value as it builds it
- `Parich_Sitemap_Renderer` builds the sections (cached), then adds ids, the table of contents and `aria-current` on every render
- Posts are read with one ID query, then in batches of 500 without `post_content` (partial posts live in the object cache only while a batch is processed; with a persistent object cache full posts are loaded instead)
- Cache: one transient per set of options (each section's markup gzip-compressed and hex-encoded; the transients API stores the array — no serialize/base64/JSON of our own), invalidated by `parich_sitemap_cache_version`, which rises on post/term/relationship/user changes; settings, language, date format and permalinks are part of the key. `only_private` is never cached. Filters: `parich_sitemap_use_cache`, `parich_sitemap_cache_ttl`
- Placement rules must keep link-for-link parity with WP Sitemap Page (tests/php/test-02-renderer.php)

## Translations

- Source strings are English; Thai lives in `languages/parich-accessible-sitemap-th.po` (edit it directly or with Poedit)
- `npm run i18n` reads strings from `build/` (not `src/`) so the editor JSON is named after `build/sitemap/index.js`, the file WordPress loads; it uses `--no-purge` so the .po keeps the editor strings too
- Nothing in `languages/` ships: WordPress.org review asked for translations through translate.wordpress.org, so there is no `load_plugin_textdomain()`, no `Domain Path` and no custom script translation path (core sets the editor script's text domain from block.json). After approval the Thai `.po` is imported at translate.wordpress.org, which builds the language packs
- `tests/bootstrap.php` registers `languages/` as the text domain's path (what an installed language pack provides), so the Thai tests keep running
- New settings need a bump of `Parich_Sitemap_Migration::DATA_VERSION` so existing sites store their default

## Tests

- `tests/php/*.php` run against the real local WordPress through `tests/bootstrap.php`:
  - loads the plugin at `muplugins_loaded` even when it is not activated
  - wraps each file in a DB transaction that is always rolled back (aborts if a core table is not InnoDB)
  - counts PHP notices and `_doing_it_wrong` / deprecation notices raised from this plugin as failures
  - every test file ends with `pas_test_done()`
- `tests/run-php-tests.js` reads the PHP binary and arguments from env (`PHP_BIN`, `PHP_ARGS`) or `tests/local-config.json` (gitignored; copy `tests/local-config.example.json`)
- `tests/a11y/run-axe.js` runs axe-core (WCAG 2.0/2.1 A+AA, `color-contrast` off because jsdom cannot render) on `tests/a11y/out/*.html`, after a self-test on `tests/a11y/fixtures/`
- `tests/a11y/run-reflow.js` (`npm run test:reflow`, part of `npm test`) lays the same pages out 320px wide in headless Edge/Chrome and fails on any element past the edge; the pages embed the built stylesheet, so the tree layout is what gets checked
- `tests/a11y/run-media.js` (`npm run test:media`, part of `npm test`) drives headless Edge/Chrome over the DevTools protocol (Node.js 22+ for `WebSocket`) to emulate forced colors, `prefers-contrast: more` and print on `tests/a11y/out/10-*.html`
- Both browser checks find the browser through `tests/a11y/browser.js` (env `BROWSER_BIN`, `"browser"` in `tests/local-config.json`, or the usual install paths) and skip with a message when there is none

## Display

- Tree with connecting lines is the default (CSS only, on the unchanged nested lists — no ARIA tree widget); the "List" block style / `style="list"` adds `is-style-list`
- `collapsible` wraps categories in `<details>`/`<summary>`: the summary is plain text with the post count of the whole sub-tree, the category link becomes the first list item (same link order as without it)
- With a table of contents (2+ sections, titles and `toc` on), each section ends with a "Back to contents" link to it
- Stylesheet: tree lines at full strength under `forced-colors: active` / `prefers-contrast: more`; `@media print` hides the table of contents and back links and prints collapsed categories open (`::details-content`)

## WordPress.org

- `.wordpress-org/` holds the directory assets (icon, banners, screenshot-1…5 matching readme.txt); it is not in the zip
- Plugin Check (plugin installed on the local site) on exactly what ships: extract the zip, then `wp plugin check <extracted folder> --slug=parich-accessible-sitemap` (add `--include-experimental`). Every remaining `phpcs:ignore` has its reason on the line above
- The donation link (Stripe only: `PARICH_SITEMAP_DONATE_URL` in the main file) appears only in the readme (`Donate link`), the Plugins screen row and the end of the "How to use" tab — never in the front-end sitemap (guideline 10) and never as admin notices (guideline 11); `.github/FUNDING.yml` has the same link
- `admin/settings.css` (plain CSS, not built; in the zip through `"files"`) styles the settings screen only — enqueued by `Parich_Sitemap_Settings::enqueue()` for `settings_page_parich-accessible-sitemap`
- The Buddhist Era setting also rewrites the years in the archive list's link texts (URLs keep the real year)

## Thai order

- Databases sort Thai by code point, so words starting with a leading vowel (เ แ โ ใ ไ) end up last. `Parich_Sitemap_Thai_Sort` re-sorts lists that contain Thai into dictionary order: PHP intl's `Collator` (`th_TH`) when available, otherwise `fallback_key()`, which gives the same order (checked in tests/php/test-09-thai-order.php). Lists without Thai keep the database order, so parity with WP Sitemap Page is unchanged
- Applied where WordPress sorts by name/title: pages (default `menu_order, post_title`, or `sort=post_title|title`, through a `get_pages` filter added only around `wp_list_pages()`), terms ordered by name (default, and any `sort` not in `TERM_SORT_NOT_BY_NAME`), posts and custom types with `sort=title|post_title` (direction kept). Authors are not re-sorted
- Filters: `parich_sitemap_thai_sort` (false turns it off), `parich_sitemap_collator` (a `Collator`, or null for the fallback)

## Local environment

- Machine-specific tool paths (PHP binary, php.ini, WP-CLI) are kept outside this repository, in the CLAUDE.md of the WordPress installation it is developed in; tests read theirs from the gitignored `tests/local-config.json`
