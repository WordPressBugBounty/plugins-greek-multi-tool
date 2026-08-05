=== Greek Multi Tool – Greeklish Slugs, Permalinks & Transliteration ===
Contributors: bigdropgr, aivazidis
Author: bigdropgr, aivazidis
Committers: bigdropgr, aivazidis
Tags: greeklish slugs, permalinks, transliteration, greek search, seo
Requires at least: 6.2
Stable tag: 3.4.1
Tested up to: 7.0.2
Requires PHP: 7.4
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

The only lightweight plugin you need for Greek WordPress sites. Auto-convert Greeklish slugs, optimize permalinks, and enhance search without bloat.

== Description ==
Welcome to **Greek Multi Tool**, the modern, all-in-one solution that transforms how WordPress handles the Greek language. If you are worried about plugin bloat, don't be! Our architecture is completely **modular**. You can enable only the features you actually need—like just the Greek Slugs conversion—and leave the rest turned off. This ensures zero impact on your site's speed while giving you professional-grade tools.

Backed by a **5-star rating** and meticulously tested up to the latest WordPress version (7.0.2), Greek Multi Tool solves the complex language problems that older, legacy plugins simply cannot handle.

= Why Install 5 Plugins When You Only Need One? =

Stop cluttering your WordPress dashboard with outdated, single-purpose tools. Greek Multi Tool replaces 4-5 different plugins by combining everything into one incredibly optimized package:

* **SEO-Friendly URLs (Greeklish Slugs):** Automatically convert complex Greek characters in your Permalinks into clean, search-engine-ready Latin text using our smart Transliteration engine.
* **Bulletproof SEO Protection:** Changing your existing URLs? Our built-in 301 redirect manager automatically maps your old links to your new ones, protecting your hard-earned SEO rankings.
* **Flawless WooCommerce Greek Integration:** Seamlessly handles product URLs, media file names, and internal product queries for your e-commerce store.
* **Intelligent Accent Removal:** Automatically strip unsightly uppercase accents for beautiful, professional typography across any page builder.
* **Advanced Greek Search:** Dramatically upgrade your internal site search with algorithms that understand Greek diphthongs and ignore accents entirely.

= Full Page Builder & SEO Plugin Compatibility =

Greek Multi Tool works seamlessly out of the box with the most popular WordPress tools:

* **Page Builders:** Full text extraction, excerpt generation, and typography support for WP Bakery, Elementor, Gutenberg (Block Editor), Divi, Beaver Builder, and Avada/Fusion Builder.
* **SEO Plugins:** Deep integration with Yoast SEO, Rank Math, and All in One SEO. We feed clean, rendered text directly into their analysis engines so your Greek content gets graded accurately.
* **Advanced Custom Fields (ACF):** Our transliteration is context-aware. It intelligently detects ACF internal operations and skips them, meaning your field names and keys are never corrupted.

= Core Features & Modules =

1. **Automatic Greek to Latin URL Conversion:** Transform Greek characters in Permalinks to clean Latin characters.
2. **Media File Name Conversion:** Automatically convert Greek characters in uploaded media (images, PDFs) to Latin equivalents. ("φωτογραφία.jpg" becomes "fotografia.jpg").
3. **Smart Diphthong Handling:** Choose between simple and advanced diphthong transliteration.
4. **Uppercase Accent Removal:** Perfect Greek typography for dynamically loaded page builder content.
5. **Legacy URL Management:** Convert existing permalinks with a single click (safeguarded by automatic 301 redirects).
6. **Automatic Menu Builder:** Generate menus following WordPress Post and WooCommerce Product category hierarchies instantly.
7. **Greek Text Analysis:** Check your content for proper Greek accent rules and linguistic correctness.
8. **Greek Date Localization:** Display dates in proper Greek format with correct month/day names site-wide.

== Features ==
*(Note: See Description for the complete, benefit-driven feature list).*

== Compatibility ==

= Page Builders - Full Support =
Greek Multi Tool provides deep, tested compatibility with all major page builders. Every feature works correctly regardless of how you build your pages:
* **WP Bakery Page Builder (Visual Composer):** Full support, including client-side and server-side extraction and dynamically rendered frontend content.
* **Elementor:** Full support. Reads Elementor widget data directly from post meta.
* **WordPress Gutenberg (Block Editor) & Classic Editor:** Full support.
* **Divi, Beaver Builder, Avada / Fusion Builder:** Fully compatible.

= SEO Plugins =
* **Yoast SEO:** Full support. Provides clean rendered text when page builder shortcodes are detected for accurate readability and SEO analysis.
* **Rank Math & AIOSEO:** Compatible via standard WordPress hooks.

== Translations ==
Greek Multi Tool is fully translatable:
* English – default
* Greek – complete

Want to see your language included? Send us your translation files (po/mo) via our <a href="https://bigdrop.gr/contact-us/">contact page</a>.

== Installation ==
**AUTOMATIC INSTALLATION (RECOMMENDED)**
1. Visit the plugins page within your dashboard and select 'Add New'
2. Search for 'Greek Multi Tool'
3. Activate Greek Multi Tool from your Plugins page
4. That's it! The plugin works out of the box with recommended settings.

**MANUAL INSTALLATION**
1. Upload the 'greek-multi-tool' folder to the /wp-content/plugins/ directory
2. Activate the plugin through the 'Plugins' menu in WordPress

**CONFIGURATION (OPTIONAL)**
Our modular design means you control everything:
1. Navigate to "Greek Multi Tool" in your WordPress admin sidebar.
2. Toggle on/off individual features to match your exact needs (e.g., enable only Greeklish Slugs).
3. Save your settings to apply changes.

== Frequently Asked Questions ==
= Is this plugin free? =
Yes! Greek Multi Tool is completely free with all features available at no cost.

= Will this plugin slow down my website? =
No. Because Greek Multi Tool is highly modular, you can turn off any features you don't need. It uses efficient code that makes minimal database queries, ensuring the impact on site speed is practically zero.

= Will converting my old URLs break my SEO? =
Not at all! Greek Multi Tool actually enhances your SEO by creating search-engine-friendly URLs, while our automatic 301 redirect system ensures your old URLs forward correctly. Your search rankings are protected.

= Does this plugin work with WooCommerce? =
Absolutely! Greek Multi Tool seamlessly integrates with WooCommerce to handle Greek permalinks, media, accents, and search for your product pages.

= Does this plugin work with ACF (Advanced Custom Fields)? =
Yes. The transliteration engine detects ACF internal operations and skips them, and since 3.4.1 the bulk permalink converter is restricted to publicly visible post types, so it can never touch ACF fields, groups or options pages. Versions before 3.4.1 had a critical bug here - if you were affected, see the recovery FAQ below.

= Does it convert media/image file names? =
Yes! Enable the "Media File Name Conversion" toggle to automatically translate uploaded files. For example, "εικόνα-προϊόντος.jpg" becomes "eikona-proiontos.jpg".

= How do I convert old permalinks? =
Navigate to Greek Multi Tool → Convert Old Permalinks. Back up your database, click "PREVIEW" to see exactly which slugs would change without writing anything, then click "CONVERT". The plugin handles posts, pages, publicly visible custom post types, media attachments, taxonomy terms, and sets up 301 redirects.

= My ACF fields broke after "Convert All Old Permalinks" - what do I do? =
First update to 3.4.1 or later, so it cannot happen again. The damaged field keys can usually be restored without a backup: the converter only changed the key stored on the field itself, while every post that ever saved the field still holds the original key. With WP-CLI, run `wp grmlt acf-restore-keys` for a dry-run report, then add `--write` to apply it. If your theme has an `acf-json` folder, prefer ACF → Field Groups → Sync instead: it also restores field group keys, which the command cannot. Fields that were never used on any post cannot be recovered this way. Always back up your database first.

= How does the Greek Search work? =
Our specialized search algorithms understand Greek linguistic patterns. It handles accented characters, diphthongs, and various word forms to drastically improve internal WordPress search accuracy.

= My page builder pages lost their styling after installing the plugin. What happened? =
This was a bug in versions 3.3.0 and 3.3.1, fixed in 3.4.0. Please update. If you cannot update immediately, setting a featured image on the affected pages avoids the problem, because the Yoast SEO code path that triggered it only runs on pages that have none.

= Why does Yoast SEO no longer find an image on my page builder pages? =
It never really could. Content images built by page builder shortcodes are not visible to Yoast on the front end, in any plugin, because the shortcodes have not run yet when Yoast looks. Set a featured image, which is the supported way to give Yoast, Google and social networks an image for the page.

= Can I get the old behaviour back, where shortcodes were rendered on the front end? =
Yes, but only do this if you know your theme and plugins tolerate it, since it is what caused missing styles in 3.3.x. Add to your child theme's functions.php:

`add_filter( 'grmlt_render_shortcodes_on_frontend', '__return_true' );`

The filter also receives the post ID as a second argument, so you can enable it for specific posts only:

`add_filter( 'grmlt_render_shortcodes_on_frontend', function ( $render, $post_id ) { return $post_id === 123; }, 10, 2 );`

== Screenshots ==
1. Plugin's Main Settings Dashboard
2. Greeklish Permalink Configuration
3. Remove Uppercase Accents Option
4. Convert Old Permalinks Tool
5. 301 Redirect Settings
6. Menu Builder Tool
7. Greek Text Analysis Tool
8. Enhanced Greek Excerpts Generator
9. Greek-Optimized Search Configuration
10. Greek Date Localization Settings
11. Enhanced Greek Excerpts And Greek Text Analysis Tool On Post Page
12. Enhanced Greek Excerpts Generator Settings On Writtings Settings Page
13. Feedback System

== Changelog ==
= 3.4.1 =
* **Security**: AJAX endpoints now verify the capabilities of the requesting user instead of relying on a nonce alone. The excerpt endpoints require permission to edit the post they touch (and excerpt writes are limited to posts and pages), the excerpt settings sync requires administrator capabilities and is limited to the three options that screen owns, and server-side text analysis requires permission to edit the post being analyzed. All users should update immediately.
* **Critical Fix**: "Convert All Old Permalinks" destroyed Advanced Custom Fields configuration. The bulk converter ran a direct database query against every post in the site with no post type restriction at all. ACF stores each field as a hidden `acf-field` post whose slug **is** the field key (`field_5f8a1b2c...`), so the converter overwrote those keys with a slug built from the field's label. Every `get_field()` call then returned nothing. Field groups, ACF post types, ACF taxonomies and ACF options pages were affected the same way, in every language, including fields with English labels. The converter is now restricted to publicly visible post types and can never touch ACF or any other plugin's internal post types. Term slug conversion is likewise restricted to public taxonomies.
* **New**: Recovery tool for sites already affected. The WP-CLI command `wp grmlt acf-restore-keys` reconstructs damaged ACF field keys from data the converter never touched. It is a dry run by default and only writes with an explicit `--write` flag. See the FAQ.
* **New**: PREVIEW button on the permalink converter. It lists every slug that would change, and writes nothing. The page now also warns to back up first.
* **Bug Fix**: The converter no longer rewrites slugs that merely contain an underscore. An underscore is a valid WordPress slug character, so slugs such as `my_page` were being renamed unnecessarily.
* **Bug Fix**: The converter can no longer write an empty slug. Titles that reduce to nothing (for example when made up entirely of stop words) previously left the post with no permalink at all.
* **Bug Fix**: Slug changes now record `_wp_old_slug`, so WordPress can still serve the old URL, and an undo snapshot is stored in the `grmlt_last_conversion_undo` option before the first write.
* **Bug Fix**: 301 redirects created by the converter now point from the URL that was actually in use. They were previously built from the post title, which had never been part of the permalink.
* **Bug Fix**: The 301 redirect matcher now recognizes percent-encoded Greek URLs - the form every browser actually sends. The incoming URL was previously passed through a sanitizer that stripped the encoded characters, so redirects for Greek URLs could never fire.
* **Bug Fix**: Removed an admin stylesheet enqueue that pointed to a file missing from the plugin package, causing a 404 request on every admin page since 3.2.0. The affected icons now use WordPress Dashicons.

= 3.4.0 =
* **Critical Fix**: Missing CSS and broken layouts on page builder pages. Since 3.3.0 the Yoast SEO integration ran the whole page content through `do_shortcode()` inside `wp_head`. Yoast calls that filter on the front end too, not only in the editor, whenever a singular page has **no featured image**. Themes that load per-element CSS on demand (Woodmart, Flatsome, Porto and similar) marked each CSS part as already delivered during that hidden first render, then skipped it during the real one, so the stylesheet never reached the browser. Symptoms: pages that look unstyled or partly unstyled, elements stacked or full width, styling that appears correctly again as soon as the plugin is deactivated. Affected every singular page builder page without a featured image. The content is no longer rendered on the front end.
* **Bug Fix**: Duplicate Slider Revolution modules. The hidden render produced a second module with the same ID and a second init script on every page view. Sliders that failed to start, or started twice, now behave normally.
* **Bug Fix**: Yoast SEO can find content images again. The old filter handed Yoast tag-stripped plain text, so the `<img>` tags it looks for had already been removed and its schema and og:image lookup could never succeed. Yoast now receives the real content.
* **Performance**: Page builder pages no longer execute their shortcodes twice per request. On a representative WP Bakery page this halves the shortcode executions and database round-trips per page view, and removes all of that work from `wp_head`.
* **New Filter**: `grmlt_render_shortcodes_on_frontend` restores the previous behaviour for anyone who depends on it. See the FAQ.
* **Compatibility**: Tested up to WordPress 7.0.2. No deprecated or removed WordPress 7.0 APIs are used, and the plugin runs clean under PHP 8.2, 8.3 and 8.4.
* **Housekeeping**: The internal version constant was out of sync with the plugin header (3.3.0 against 3.3.1), which meant cache-busting version strings on the plugin's own CSS and JS were stale. Both now read 3.4.0.

= 3.3.1 =
* Maintenance release: fixes a fatal error introduced in 3.3.0.

= 3.3.0 =
* **New Feature**: Greek Media File Name Conversion - Automatically convert Greek characters in uploaded media file names to SEO-friendly Latin equivalents during upload.
* **New Feature**: Attachment Slug Conversion - Media attachment slugs are now converted to Latin automatically.
* **New Feature**: Page Builder Compatibility Layer - New shared content extraction engine for WP Bakery, Elementor, Gutenberg, Divi, Beaver Builder, and Avada.
* **Bug Fix**: ACF Compatibility - Transliteration engine is now context-aware and skips ACF internal operations.
* **Bug Fix**: Text Analysis now works perfectly with WP Bakery Page Builder and Elementor.
* **Improvement**: Smart 301 redirect creation now skips attachments for better performance.
* **Compatibility**: Yoast SEO integration provides clean rendered content for accurate analysis.

= 3.2.0 =
* **Critical Fix**: Fixed 301 redirect feature.
* **Security**: Added nonce verification, fixed XSS vulnerabilities, and removed inline CDN loading of Font Awesome.
* **Compatibility**: Fixed PHP 8.2+ fatal errors and PHP 8.1+ null deprecation warnings.

= 3.1.0 =
* Added toggle control for Greek Text Analysis tool.
* Improved search functionality with separate toggles for enhanced search and accent-insensitive search.

= 3.0.0 =
* **Major Update**: Added Greek Text Analysis, Enhanced Greek Excerpts, Greek-Optimized Search, Greek Date Localization, and Feedback system.

*(Previous changelog entries truncated for brevity, but all previous versions remain supported and secure).*

== Upgrade Notice ==
= 3.4.1 =
If ACF fields suddenly returned nothing after "Convert All Old Permalinks", 3.4.1 fixes the cause and adds a WP-CLI command that restores the damaged field keys. This is also a security release: AJAX endpoints now verify user permissions. All users should update immediately.

= 3.4.0 =
Fixes missing CSS and broken layouts on page builder pages with no featured image, caused by 3.3.0/3.3.1 when Yoast SEO is active. If your pages look unstyled and deactivating this plugin fixes them, update now. Also stops duplicate Slider Revolution modules.

= 3.3.0 =
New features: Automatic Greek media file name conversion on upload, attachment slug conversion, and full ACF compatibility! Full page builder support for WP Bakery and Elementor, plus Yoast SEO integration. Highly recommended update for all Greek websites!

= 3.2.0 =
Critical security and bug fix release. Fixes 301 redirects, XSS vulnerabilities, SQL injection risks, and PHP 8.2+ compatibility issues. All users should update immediately.