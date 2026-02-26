=== Greek Multi Tool - Ultimate Greek Language Toolkit for WordPress ===
Contributors: bigdropgr, aivazidis
Author: bigdropgr, aivazidis
Committers: bigdropgr, aivazidis
Tags: greek, greeklish, permalinks, accent remover, seo
Requires at least: 6.2
Stable tag: 3.3.1
Tested up to: 6.9.1
Requires PHP: 7.4
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

The comprehensive WordPress plugin for Greek websites - fixes permalinks, converts media file names, handles accents, enhances search, localizes dates and more! Fully compatible with WP Bakery, Elementor, Gutenberg, and Yoast SEO.

== Description ==
**Greek Multi Tool 3.3** transforms how WordPress handles the Greek language. This all-in-one solution tackles every Greek-specific challenge your website faces - from URL structure to media file names to search functionality to content optimization.

Our plugin is meticulously designed for Greek website owners who need professional-grade tools that understand the unique characteristics of the Greek language. Whether you're running a blog, business site, or e-commerce store, Greek Multi Tool solves problems other plugins can't even detect.

= Full Page Builder & SEO Plugin Compatibility =

Greek Multi Tool works seamlessly with the most popular WordPress page builders and SEO plugins:

* **WP Bakery Page Builder** - Full compatibility. Text analysis, excerpt generation, uppercase accent removal, and all other features work correctly with WP Bakery content blocks (vc_column_text, vc_row, etc.). Content is properly extracted from WP Bakery shortcodes for analysis and SEO.
* **Elementor** - Full compatibility. The plugin reads Elementor widget data directly from post meta, ensuring text analysis and excerpt generation capture all your content - including text editors, headings, tabs, accordions, and other Elementor widgets.
* **WordPress Gutenberg (Block Editor)** - Full compatibility. All block types are properly parsed and their content extracted for analysis, excerpt generation, and search.
* **WordPress Classic Editor** - Full compatibility. Works with both TinyMCE visual and text modes.
* **Yoast SEO** - Full compatibility. Greek Multi Tool provides clean, rendered content to Yoast's analysis engine when page builder shortcodes are detected, ensuring Yoast can properly analyze your Greek content for SEO optimization.
* **Rank Math & All in One SEO** - Compatible. Uses standard WordPress hooks that work alongside all major SEO plugins.
* **Divi Builder, Beaver Builder, Avada/Fusion Builder** - Compatible. Page builder shortcodes are properly stripped for text analysis and excerpt generation.

= Why Greek Multi Tool Is Essential for Your Greek Website =

* **Solve Greek URL Problems Once and For All** - Convert complicated Greek character URLs to clean, SEO-friendly Latin permalinks automatically
* **Clean Up Greek Media File Names** - Automatically convert Greek file names during upload so your images and documents have proper Latin file names for maximum compatibility and SEO
* **Full ACF Compatibility** - Our smart context-aware transliteration knows when to convert and when to leave things alone, so Advanced Custom Fields and other plugins work perfectly alongside
* **Works With Any Page Builder** - Text analysis, excerpt generation, uppercase accent removal, and search all work perfectly whether you build pages with WP Bakery, Elementor, Gutenberg, or the Classic Editor
* **Enhance Greek Content SEO** - Our specialized tools ensure search engines properly understand and index your Greek content. Provides clean text to Yoast SEO for accurate analysis of page builder content
* **Create Professional Greek Typography** - Remove unsightly uppercase accents and ensure consistent, beautiful Greek text display, even on dynamically loaded page builder content
* **Boost Greek Search Accuracy** - Improve internal search with accent-insensitive, diphthong-aware algorithms built specifically for Greek
* **Display Proper Greek Dates** - Show dates in proper Greek format with correct month and day names
* **Generate Perfect Greek Excerpts** - Create proper excerpts that respect Greek word boundaries and linguistic rules, with full support for extracting text from WP Bakery, Elementor, and Gutenberg content
* **Analyze Greek Text Quality** - Check for proper accent usage and text readability with our Greek-specific analysis tools that understand page builder content

Unlike generic WordPress plugins, Greek Multi Tool was built from the ground up specifically for Greek language websites, addressing peculiarities and challenges that non-specialized tools simply can't handle.

And it does all this while maintaining excellent performance - our lightweight, optimized code ensures your site stays fast and responsive.

== Features ==

= Core Features =

1. **Automatic Greek to Latin URL Conversion** - Transform Greek characters in permalinks to clean, SEO-friendly Latin characters with our intuitive Greeklish converter.
2. **Smart Greek Diphthong Handling** - Choose between simple and advanced diphthong conversion methods to create the most readable URLs.
3. **Uppercase Accent Removal** - Automatically remove accents from uppercase Greek text for professional typography.
4. **Legacy URL Management** - Convert existing permalinks with a single click and manage 301 redirects to maintain SEO value.
5. **Automatic Menu Builder** - Generate menus following the hierarchy of WordPress Post and WooCommerce Product categories with a single click.
6. **Custom URL Optimization** - Remove one- and two-letter words from slugs and exclude custom stopwords for cleaner URLs.

= New in Version 3.0.0 =

7. **Greek Text Analysis** - Analyze your content for proper Greek accent rules and ensure linguistic correctness.
8. **Enhanced Greek Excerpts** - Generate proper excerpts for Greek content that respect word boundaries and provide better reading flow.
9. **Greek-Optimized Search** - Dramatically improve WordPress search for Greek content by handling accents, diphthongs, and Greek-specific linguistic variations.
10. **Greek Date Localization** - Display dates in proper Greek format with correct month and day names throughout your site.
11. **User Feedback System** - Help us improve with the integrated feedback system.
12. **Full Internationalization** - Complete internationalization with Greek translations included.

= New in Version 3.1.0 =
13. **Accent-Insensitive Seach** - Improve search accuracy by ignoring accents on Greek characters. This ensures users can find content regardless of whether they type accented or unaccented Greek letters.
14. **Advanced Greek Search Options** - Now with separate toggles for enhanced search and accent-insensitive search, giving you full control over how search works for Greek text.
15. **Added Toggle Control for enabling/disabling Greek Text Analysis** - Analyze your content for proper Greek accent rules with easy on/off control. Ensure linguistic correctness with just a click.

= New in Version 3.3.0 =
16. **Greek Media File Name Conversion** - Automatically convert Greek characters in uploaded media file names (images, documents, PDFs, etc.) to clean, SEO-friendly Latin equivalents during upload. No more broken image URLs or encoding headaches - "φωτογραφία-προϊόντος.jpg" becomes "fotografia-proiontos.jpg" automatically.
17. **ACF (Advanced Custom Fields) Compatibility Fix** - Our transliteration engine is now context-aware. It intelligently detects when Advanced Custom Fields is generating internal field names and keys, and skips transliteration to prevent ACF field corruption. Your ACF field definitions stay exactly as they should be.
18. **Attachment Slug Conversion** - Media attachment slugs (URLs) are now automatically converted to Latin, just like posts and pages. This applies both on new uploads and through the bulk "Convert Old Permalinks" tool which now includes attachments and media items.

== Compatibility ==

= Page Builders - Full Support =
Greek Multi Tool provides deep, tested compatibility with all major page builders. Every feature of the plugin - text analysis, excerpt generation, uppercase accent removal, search, and permalink conversion - works correctly regardless of which page builder you use:

* **WP Bakery Page Builder (Visual Composer)** - Full support. The plugin extracts text content from all WP Bakery elements (vc_column_text, vc_row, custom text blocks, etc.) both client-side and server-side. If client-side extraction isn't possible (e.g., WP Bakery's backend editor mode), the plugin automatically falls back to server-side content extraction from the saved post. The uppercase accent remover also works on WP Bakery's dynamically rendered frontend content.
* **Elementor** - Full support. The plugin reads Elementor's widget data directly from post meta (_elementor_data), extracting text from all widget types including text editors, headings, tabs, accordions, testimonials, and more. Accent removal works on Elementor's frontend-rendered elements via MutationObserver.
* **WordPress Gutenberg (Block Editor)** - Full support. Block content is properly parsed using WordPress core functions (excerpt_remove_blocks) and the Gutenberg data API.
* **WordPress Classic Editor** - Full support. Works with TinyMCE visual mode and plain text mode.
* **Divi Builder** - Compatible. Divi shortcodes (et_*) are properly stripped for content extraction.
* **Beaver Builder** - Compatible. Beaver Builder shortcodes (fl_*) are properly handled.
* **Avada / Fusion Builder** - Compatible. Fusion shortcodes (fusion_*) are properly handled.

= SEO Plugins =
* **Yoast SEO** - Full support. Greek Multi Tool filters content through Yoast's analysis hooks (wpseo_pre_analysis_post_content), providing clean rendered text when page builder shortcodes are detected. This ensures Yoast can accurately analyze your Greek content for readability and SEO, even when using WP Bakery or other shortcode-based builders.
* **Rank Math** - Compatible. Uses standard WordPress hooks.
* **All in One SEO (AIOSEO)** - Compatible. Uses standard WordPress hooks.

= Other Plugins =
* **WordPress core** (tested up to 6.9.1)
* **WooCommerce** - Full support for product permalinks, search, and media
* **Advanced Custom Fields (ACF)** - Full compatibility since v3.3.0 with context-aware transliteration

The plugin has been extensively tested for compatibility issues and will not conflict with other well-coded plugins.

== Translations ==
Greek Multi Tool is fully translatable with included translations for:
* English – default
* Greek – complete

Want to see your language included? Send us your translation files (po/mo) via our <a href="https://bigdrop.gr/contact-us/">contact page</a> and we'll include them in the next update.

== Installation ==
**AUTOMATIC INSTALLATION (RECOMMENDED)**
1. Visit the plugins page within your dashboard and select 'Add New'
2. Search for 'Greek Multi Tool'
3. Activate Greek Multi Tool from your Plugins page
4. That's it! The plugin works out of the box with recommended settings

**MANUAL INSTALLATION**
1. Upload the 'greek-multi-tool' folder to the /wp-content/plugins/ directory
2. Activate the Greek Multi Tool plugin through the 'Plugins' menu in WordPress
3. No additional setup needed - the plugin works with optimal default settings

**CONFIGURATION (OPTIONAL)**
While Greek Multi Tool works perfectly with default settings, you can customize its behavior through the dedicated settings page:
1. Navigate to "Greek Multi Tool" in your WordPress admin sidebar
2. Configure individual features to match your specific needs
3. Save your settings to apply changes

== Frequently Asked Questions ==
= Is this plugin free? =
Yes! Greek Multi Tool is completely free with all features available at no cost.

= Does this plugin work with WooCommerce? =
Absolutely! Greek Multi Tool seamlessly integrates with WooCommerce to handle Greek permalinks, accents, and all other features on your product pages.

= Does this plugin work with ACF (Advanced Custom Fields)? =
Yes! Since version 3.3.0, Greek Multi Tool is fully compatible with ACF. Our context-aware transliteration engine automatically detects ACF internal operations and skips transliteration, so your field names, keys, and definitions remain untouched.

= Does it convert media/image file names? =
Yes! Since version 3.3.0, the plugin can automatically convert Greek characters in media file names during upload. Enable the "Media File Name Conversion" toggle in the Permalink Settings tab. For example, "εικόνα-προϊόντος.jpg" becomes "eikona-proiontos.jpg" automatically.

= Does it convert attachment (media) slugs? =
Yes! Media attachment slugs are automatically converted to Latin, just like regular posts and pages. The bulk "Convert Old Permalinks" tool also includes existing media attachments.

= Will this plugin slow down my website? =
No. Greek Multi Tool is built with performance in mind, using efficient code that makes minimal database queries. The impact on site speed is negligible.

= How do I convert old permalinks? =
Simply navigate to Greek Multi Tool → Convert Old Permalinks in your WordPress dashboard and click the "CONVERT" button. The plugin handles everything automatically, including posts, pages, custom post types, media attachments, taxonomy terms, and setting up proper 301 redirects.

= Will this break my SEO? =
Not at all! In fact, Greek Multi Tool enhances your SEO by creating more search-engine friendly URLs while maintaining proper 301 redirects from old URLs. Your search rankings should improve, not decline.

= How do the Greek search enhancements work? =
Our plugin implements specialized search algorithms that understand Greek linguistic patterns, including handling accented characters, diphthongs, and various word forms. This dramatically improves the accuracy of internal WordPress searches.

= Does this plugin work with WP Bakery Page Builder? =
Yes! Greek Multi Tool is fully compatible with WP Bakery (Visual Composer). All features work correctly - text analysis extracts content from WP Bakery text blocks, excerpt generation properly handles vc_ shortcodes, and the uppercase accent remover works on WP Bakery's dynamically loaded frontend content. If you had issues with "No content to analyze" when using WP Bakery text blocks, this has been resolved with the page builder compatibility layer.

= Does this plugin work with Elementor? =
Yes! Full Elementor support is included. The plugin reads Elementor widget data directly from post meta to extract text content from all widget types. Text analysis, excerpt generation, and all other features work perfectly with Elementor-built pages.

= Is the Text Analysis tool compatible with page builders? =
Yes! The Text Analysis tool works with all major editors and page builders including WordPress Gutenberg (Block Editor), Classic Editor (TinyMCE), WP Bakery Page Builder, and Elementor. The plugin uses multiple content extraction strategies and includes a server-side fallback that reads saved post content when client-side extraction isn't possible.

= Does this plugin work with Yoast SEO? =
Yes! Greek Multi Tool enhances Yoast SEO compatibility by providing clean, rendered text content to Yoast's analysis engine. When your content uses page builder shortcodes (WP Bakery, Divi, etc.), Yoast may not be able to analyze the raw shortcode content properly. Greek Multi Tool intercepts Yoast's content analysis and provides the fully rendered, clean text for accurate SEO analysis of your Greek content.

= What PHP version do I need? =
Greek Multi Tool requires PHP 7.4 or greater, but we recommend using the latest PHP version for optimal performance.

= How can I report bugs or suggest features? =
Use our new integrated feedback system on the Feedback tab in the plugin settings, or contact us through our <a href="https://bigdrop.gr/contact-us/">website</a>.

= Can I use this plugin on non-Greek websites? =
While designed specifically for Greek language websites, some features like the menu builder are language-agnostic and can be useful for any site.

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
= 3.3.0 =
* **New Feature**: Greek Media File Name Conversion - Automatically convert Greek characters in uploaded media file names (images, documents, etc.) to SEO-friendly Latin equivalents during upload
* **New Feature**: Attachment Slug Conversion - Media attachment slugs are now converted to Latin both automatically on upload and via the bulk "Convert Old Permalinks" tool
* **New Feature**: Page Builder Compatibility Layer - New shared content extraction engine that properly handles content from WP Bakery, Elementor, Gutenberg, Divi, Beaver Builder, and Avada/Fusion Builder
* **Bug Fix**: ACF (Advanced Custom Fields) Compatibility - Transliteration engine is now context-aware and skips ACF internal operations, preventing field name/key corruption (e.g., 'acf-data-weight' no longer becomes 'acf-dedomena-varos')
* **Bug Fix**: Text Analysis now works with WP Bakery Page Builder - Fixed "No content to analyze" error when content is inside WP Bakery text blocks. The plugin now uses multiple content extraction strategies (WP Bakery JS API, visual editor scraping, hidden textarea) with an automatic server-side fallback
* **Bug Fix**: Text Analysis now works with Elementor - Content is extracted directly from Elementor's widget data stored in post meta
* **Improvement**: Excerpt generation now uses the page builder compatibility layer to extract clean text from any page builder content, including Elementor widget data
* **Improvement**: Uppercase accent remover now uses MutationObserver for dynamically loaded content from page builders (WP Bakery frontend editor, Elementor frontend rendering)
* **Improvement**: Uppercase accent remover listens for WP Bakery and Elementor frontend events to apply accent removal on newly rendered content
* **Improvement**: sanitize_title callbacks now accept all 3 WordPress filter arguments ($title, $raw_title, $context) and only run in 'save' context, preventing unintended transliteration during display and query operations
* **Improvement**: Bulk "Convert Old Permalinks" now includes media attachments (post_status 'inherit') alongside posts, pages, and custom post types
* **Improvement**: Translator functions refactored with shared helper functions (grmlt_get_greek_expressions, grmlt_transliterate_greek, grmlt_apply_diphthongs_simple/advanced) for better code reuse and maintainability
* **Improvement**: Smart 301 redirect creation that skips attachments (whose URLs are served from /wp-content/uploads/ and don't use slugs in the same way)
* **Compatibility**: Full WP Bakery Page Builder support - text analysis, excerpt generation, uppercase accent removal, and content extraction all work with WP Bakery elements
* **Compatibility**: Full Elementor support - reads Elementor widget data directly from post meta for comprehensive content extraction
* **Compatibility**: Full Gutenberg Block Editor support - proper block parsing via WordPress core functions
* **Compatibility**: Yoast SEO integration - provides clean rendered content to Yoast's analysis engine via wpseo_pre_analysis_post_content filter, ensuring accurate SEO analysis of page builder content
* **Compatibility**: Full ACF (Advanced Custom Fields) compatibility - detects ACF AJAX actions, ACF post types, and ACF admin screens
* **Compatibility**: Updated plugin description to reflect all current features

= 3.2.0 =
* **Critical Fix**: Fixed 301 redirect feature not working (redirect was hooked incorrectly to `init` inside an `init` callback and never fired)
* **Security**: Added nonce verification to old permalink conversion and menu builder forms
* **Security**: Fixed XSS vulnerabilities — all database output is now properly escaped with `esc_html()` / `esc_attr()`
* **Security**: Replaced raw `header()` redirect with `wp_redirect()` for proper WordPress redirect handling
* **Security**: Fixed SQL injection risk in database table creation and uninstall routines
* **Security**: Removed inline CDN loading of Font Awesome — now properly enqueued via `wp_enqueue_style()`
* **Compatibility**: Fixed PHP 8.2+ fatal error caused by nested function declaration in uppercase accent remover
* **Compatibility**: Fixed `strpos()` null deprecation warnings on PHP 8.1+ when screen object is null
* **Compatibility**: Fixed version constant mismatch (was stuck at 2.4.0, now correctly reflects plugin version)
* **Compliance**: Fixed Text Domain mismatch (`grmlt-plugin` vs `greek-multi-tool`) — i18n now loads correctly
* **Compliance**: Replaced short PHP open tags (`<?`) with full `<?php` tags for server compatibility
* **Compliance**: Fixed dynamic string passed to `_e()` — now uses `printf()` with `esc_html__()` for proper i18n
* **Compliance**: Settings link is now translatable and uses `esc_url()` / `esc_html__()`
* **Compliance**: Removed hardcoded `WP_PLUGIN_DIR` paths — now uses `plugin_dir_path()` for portability
* **Compliance**: Added version parameters to all enqueued scripts and styles
* **Cleanup**: Complete uninstall now removes all plugin options including search, date, excerpt, and analysis settings

= 3.1.0 =
* Added toggle control for Greek Text Analysis tool
* Improved search functionality with separate toggles for enhanced search and accent-insensitive search
* Enhanced user control over how searches handle Greek accents
* Optimized settings UI for a more intuitive experience
* Fixed various minor bugs and performance issues
* Updated language translations with new feature strings

= 3.0.0 =
* **Major Update**: Comprehensive overhaul with five powerful new features
* Added Greek Text Analysis tool for accent rule compliance checking
* Added Enhanced Greek Excerpts generator for proper Greek word boundary handling
* Added Greek-Optimized Search with accent and diphthong awareness
* Added Greek Date Localization for proper display of Greek dates
* Added Feedback system for easier feature requests and bug reports
* Improved plugin architecture with better separation of concerns
* Enhanced internationalization with updated translations
* Fixed various minor bugs and performance issues
* Improved compatibility with latest WordPress version

= 2.3.2 =
* Critical security update: Fixed a vulnerability related to broken access control.
* Added proper user permission verification to all administrative actions.
* Enhanced security for all AJAX operations with nonce verification.
* Improved data validation and sanitization throughout the plugin.
* Fixed potential security issues in the permalink management system.

= 2.3.1 =
* Fixed bug where on New Posts/Pages it wouldn't Translate the Slugs for Gutenberg/Elementor page editors
* Fixed bug of 404 error page for old translated posts/pages when permalink was filled with html entities
* Minor security update for direct database access requests

= 2.3.0 =
* Added Automated Menu Creation for `Posts` and `Woocommerce Products` categories with correct hierarchy positions.
* Fixed Translation Issues with .mo and .po files.
* Fixed PHP Warning: Undefined array key "HTTP_HOST" in...

= 2.2.0 =
* Minor Security updates.
* Speed Optimization Fixes.
* Global 301 Redirect Error on database record fixed.

= 2.1.6 =
* Added new list for existing 301 redirections made by the plugin where you can edit/delete them.
* Fixed where sometimes the plugin wouldn't automatically turn text transliteration on upon activation.
* Fixed Options on plugins deletion where they would not get deleted from the database.
* Fixed an error occuring when a permalink exceeded a length of more than 500 characters.
* Fixed a visual error on mobile view which made the Admin Tool Bar on the top of the page to drop 20px lower.
* Fixed the way the plugin loads both CSS/JS and restricted it to the settings page, avoiding further conflicts in the Admin Area.

= 2.1.5 =
* Fixed bug where 301 redirect url would construct incorrectly incase the post title included a non alphanumeric ( A-Z or 0-9 ) character.

= 2.1.4 =
* Fix compatibility with WP 6.1.1

= 2.1.3 =
* Fixed bug where on plugin first time activation the global translator was deactivated.

= 2.1.2 =
* Fixed the issue with the convertion of old URLs.
* If you are facing any error 404 with mass converted old URLs, please visit the Admin > Greek Multi Tool > Convert Old Permalinks and hit the Convert Button.

= 2.1.1 =
* Load Bootstrap css only on plugin settings page
* Add link to settings page from the plugins list

= 2.1.0 =
* Re-structured plugin's php to fully Object-Oriented
* Style changes on admin settings page of plugin
* Revamped Utilities such as plugin screenshots, css/js files, etc...

= 2.0.1 =
* Minor bug fixes

= 2.0.0 =
* Core re-structure
* Added 301 Redirections for Old Converted Permalinks
* Improved performance when updating newly created posts

= 1.3.0 =
* Added new functionality, You can now exclude stop words from permalinks.
* We now support only WordPress version that are higher than 5.4!

= 1.2.4 =
* Minor bug fixes

= 1.2.3 =
* Minor bug fixes

= 1.2.2 =
* Minor bug fixes

= 1.2.1 =
* Minor bug fixes

= 1.2.0 =
* Added new functionality, You can now select to remove One or Two letter words from the posts permalinks

= 1.1.1 =
* Minor translation fixes

= 1.1.0 =
* Added new functionality. Update old permalinks from posts that existed before the plugin was installed

= 1.0.5 =
* Minor bug fixes and security updates

= 1.0.4 =
* Add the ability to choose how to save the diphthongs

= 1.0.3 =
* Minor fixes on the settings page

= 1.0.2 =
* Settings page redesign

= 1.0.1 =
* Banner Updates
* Add the installation information

= 1.0.0 =
* Plugin released.

== Upgrade Notice ==
= 3.3.0 =
New features: Automatic Greek media file name conversion on upload, attachment slug conversion, and full ACF compatibility. Full page builder support: text analysis, excerpts, and accent removal now work perfectly with WP Bakery, Elementor, and Gutenberg. Yoast SEO integration provides clean content for accurate SEO analysis. Recommended update for all Greek websites!

= 3.2.0 =
Critical security and bug fix release. Fixes 301 redirects not working, XSS vulnerabilities, SQL injection risks, and PHP 8.2+ compatibility issues. All users should update immediately.

= 3.1.0 =
Major enhancement: New accent-insensitive search allows finding content without exact accent matching (e.g., "πενσα" will match "πένσα"). Also adds convenient toggle switches for both search features and text analysis. Essential update for Greek websites!

= 3.0.0 =
Major update with five powerful new features: Greek Text Analysis, Enhanced Excerpts, Greek-Optimized Search, Date Localization, and Feedback System. All existing functionality has been improved and optimized.