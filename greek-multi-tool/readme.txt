=== Greek Multi Tool ===
Contributors: bigdropgr, aivazidis
Tags: greek, permalinks, transliteration, greeklish, redirects
Requires at least: 6.2
Tested up to: 7.1
Stable tag: 3.4.1
Requires PHP: 7.4
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Latin permalinks and media file names for Greek sites (ELOT 743), one-click conversion of old Greek URLs with 301 redirects, and an accent checker.

== Description ==

Greek Multi Tool does a small number of things that a Greek WordPress site needs and WordPress itself does not do, and it does them with the official Greek transliteration standard.

= Greek permalinks =

New posts, pages, custom post types, terms and attachments get Latin permalinks as they are created: `Οι καλύτερες ιδέες` becomes `/oi-kalyteres-idees/` instead of `/%ce%bf%ce%b9-...`. Choose the standard:

* **ELOT 743** (default for new installations): the official Greek transliteration standard (ELOT 743:2001, identical to ISO 843:1997), the one used in passports and road signs, as adopted by the United Nations. `μπλούζα → blouza`, `αυτοκίνητο → aftokinito`, `σύγχρονος → synchronos`.
* **Phonetic**: closer to pronunciation (`αι → e`, `ει/οι → i`, `μπ → b`).
* **3.x compatibility**: existing installations keep exactly the permalinks version 3.x produced (`μπλούζα → mplouza`). It is offered only while it is selected, so you can switch to ELOT 743 whenever you want; existing permalinks never change by themselves.

Optionally remove one- and two-letter words from permalinks (numbers are never removed) and keep a list of excluded words, matched regardless of accents and letter case.

= Media file names =

Uploaded files with Greek names get Latin names with the same standard: `φωτογραφία.jpg → fotografia.jpg`. Files that are already uploaded are not renamed.

= Convert old permalinks, with 301 redirects =

Content published before the plugin keeps its Greek permalinks until you convert it. The converter:

* shows a preview first (counts per content type, a sample of old and new URLs, how many will need a numeric suffix) and writes nothing until you confirm you have a backup,
* works in batches, so it is safe on large sites; an interrupted run can be continued or discarded, and what was converted until then can still be undone,
* changes only the slug: content, dates and revisions are untouched (no revision is created),
* keeps the new slug unique and lets WordPress remember the old one as well,
* records a 301 redirect for every old URL, including child pages and child terms whose URL changed because their parent did. Redirects are always recorded; the "301 redirects" switch only decides whether they are served,
* can be undone: the previous permalinks come back and the Latin addresses redirect to them (posts through WordPress' own old-slug redirect, pages, other hierarchical content and terms through redirects the undo records). It is also available in WP-CLI.

Redirects are served only for requests WordPress cannot resolve itself (404s, and feeds of addresses that no longer exist), so a live page is never hijacked. The query string of the old address is carried over. They follow the post or term, so they keep working even if you change the permalink structure later, and they cover paginated and feed URLs of the old address. Check them for missing destinations, chains and loops, edit or delete them, export and import CSV. A term whose Greek slug is changed by hand in wp-admin gets a redirect too, while saving a term or a post without touching its slug keeps the slug as it is, even when it is still Greek. Old Greek addresses of categories and tags that versions 2.x and 3.x converted (they only worked through on-the-fly transliteration) are found and redirected.

= Accent check =

A panel in the editor sidebar (and a meta box in the classic editor) points out words whose accent is probably wrong: multi-syllable words without an accent, monosyllables with one, accented capitals, interrogative πού/πώς, and more.

= Greek readiness in Site Health =

Checks that show up in Tools → Site Health, and in a card on the plugin screen:

* the site language is Greek when the content is,
* the theme declares `lang="el"` on the page (browsers then drop accents on capitals and screen readers use the right language),
* the database collation ignores accents in search (verified with a real query, never changed by the plugin),
* how many posts, terms and media files still have Greek names,
* whether any redirect is broken.

= Category menus =

Build a navigation menu that follows your category tree: choose the source (post categories, or WooCommerce product categories when WooCommerce is active), give the menu a name, and the plugin creates a WordPress menu with the same hierarchy, ready in Appearance → Menus. Added or moved categories? Build it again with the same name and the "replace" option, and the menu is rebuilt in place.

= Hooks and WP-CLI =

Filters: `grmlt_convertible_post_types`, `grmlt_convertible_taxonomies`, `grmlt_conversion_batch_size`, `grmlt_render_shortcodes_on_frontend`, `grmlt_settings_tabs`.
Actions: `grmlt_post_slug_converted( $post_id, $old, $new )`, `grmlt_term_slug_converted( $term_id, $taxonomy, $old, $new )`, `grmlt_conversion_finished( $state )`, `grmlt_settings_tab_{$id}`.
Functions for themes: `grmlt_slugify( $text, $args )`, `grmlt_transliterate_greek( $text )`.
WP-CLI: `wp grmlt convert [--dry-run]`, `wp grmlt undo-conversion`, `wp grmlt redirects check|export|import`, `wp grmlt acf-restore-keys [--write] [--regenerate-keys] [--undo=<file>]`.

== Installation ==

1. Install from Plugins → Add New (search for "Greek Multi Tool") or upload the folder to `/wp-content/plugins/`.
2. Activate it. New permalinks and uploads are converted right away with ELOT 743.
3. Open **Greek Multi Tool** in the admin menu to pick the standard, exclude words, and convert existing permalinks.

== Frequently Asked Questions ==

= Will my existing permalinks change when I update to 4.0? =

No. Existing permalinks only change when you run the converter: the transliteration never touches a slug that already exists, whether Greek or Latin, and saving a post or a term without changing its slug keeps it. Sites updating from 2.x or 3.x keep the transliteration they used for new content ("3.x compatibility" for the former Simple mode, "Phonetic" for Advanced), so nothing changes until you choose ELOT 743 in the settings.

= How do I switch to ELOT 743? =

Greek Multi Tool → Settings → Transliteration standard → ELOT 743 → Save. From then on new permalinks follow the standard. Existing ones stay as they are; convert them with Conversion & redirects if you want them to match.

= Where did Greek dates, uppercase accent removal, enhanced search and Greek excerpts go? =

They were removed in 4.0 because WordPress covers them when the site is set up correctly, and the plugin's versions were worse:

* **Dates**: with the site language set to Greek, WordPress prints Greek month and day names in the right grammatical case (`wp_date()`, `wp_maybe_decline_date()`).
* **Capitals without accents**: with `lang="el"` on the page, Chrome, Firefox, Safari and Opera remove the accents themselves when text is set in capitals with CSS.
* **Search**: the database collation decides whether `πενσα` finds `πένσα`; the plugin's own search never actually worked.
* **Excerpts**: WordPress trims Greek text by words correctly.

Site Health now tells you whether your site meets those conditions. Themes that called `grmlt_format_greek_date()` or `grmlt_greek_excerpt()` keep working (the functions are deprecated and now use the WordPress equivalents).

= What happens to the redirects if I deactivate or delete the plugin? =

While the plugin is deactivated the redirects do not run. If you delete the plugin, its tables and settings are removed. The old slugs the converter stored in WordPress' own `_wp_old_slug` meta stay, so WordPress itself keeps redirecting old post URLs.

= Does it work with ACF, WooCommerce and page builders? =

Yes. ACF internal post types are never converted and ACF operations are skipped by the transliteration. WooCommerce product and category permalinks are converted like any other public content. The accent check reads the text of page builder content (WPBakery, Elementor and others) and the Yoast SEO analysis receives clean text for it.

= My ACF fields broke after "Convert All Old Permalinks" in an old version. =

That bug was fixed in 3.4.1. Run `wp grmlt acf-restore-keys` for a report of every damaged field, sub field, field group, post type, taxonomy and options page, and add `--write` to restore the keys; a backup of the changed rows is kept in `wp-content/uploads/grmlt-acf-backups/`, a folder closed to the web, and `--undo` puts it back. Field keys come back from the values stored with them; the keys of groups, post types and taxonomies come back from the site's acf-json folder if it has one, otherwise `--write --regenerate-keys` gives them new keys. Back up the database first. See the Tools tab.

== Screenshots ==

1. Settings: choose the transliteration standard (ELOT 743 by default) and see a live preview of the permalink and file name.
2. Conversion: preview which old Greek URLs will change before converting.
3. Redirects: every converted URL gets a 301 redirect, checked for missing destinations, chains and loops.
4. Tools: build a menu from your category tree and run the WP-CLI commands.
5. Site Health: checks whether your site is set up for Greek.
6. Block editor: the accent check panel finds missing or wrong accents in titles and content.

== Changelog ==

= 4.0.0 =

* **High severity fix:** editing a page in the admin could load another post's title/slug/content when Yoast SEO and page-builder shortcodes with post loops were active; saving would then change the page's title and slug without warning.

**What's new in 4.0**: a redesign around the things the plugin does best. Transliteration now follows ELOT 743 on new installations, the converter has a preview, batches and undo, redirects are checked and managed, and Site Health tells you whether your site is set up for Greek.

*Added*

* ELOT 743 (ISO 843 type 2) transliteration standard, named and documented; "Phonetic" is the former "Advanced".
* Conversion preview, batch processing with progress and cancel, undo of the last conversion (UI and `wp grmlt undo-conversion`).
* Redirect check (missing destination, chain, loop), inline editing, bulk delete, CSV export/import, visit counters.
* Redirects for child pages and child terms whose URL changes when a parent slug is converted.
* Greek readiness checks in Site Health, a Site Health → Info section, and a readiness card on the plugin screen.
* Live preview of the permalink and file name a title would get, with unsaved settings.
* Accent check as a block editor sidebar panel (classic editor meta box kept as fallback).
* `wp grmlt acf-restore-keys` also restores sub field keys, detects damaged field groups, post types, taxonomies and options pages, restores their keys from acf-json, can give them new keys (`--regenerate-keys`), keeps a backup of every write in a folder closed to the web and can undo it (`--undo`). Works with ACF, ACF Pro and Secure Custom Fields.
* WP-CLI: `wp grmlt convert`, `wp grmlt undo-conversion`, `wp grmlt redirects check|export|import`.
* Hooks: `grmlt_post_slug_converted`, `grmlt_term_slug_converted`, `grmlt_conversion_finished`, `grmlt_conversion_batch_size`, `grmlt_hide_third_party_notices`.

*Changed*

* New admin screen built with WordPress components and the admin color scheme; tabs are separate URLs; every control is keyboard accessible and labelled.
* The bulk converter uses exactly the same code as new permalinks, builds the new slug from the existing slug (keeping manual edits), makes it unique, and records the real old URL for every permalink structure.
* Redirect lookup by indexed hash on a normalized path, only on 404 requests (and on feeds of addresses that no longer exist, which WordPress never flags as 404), with `wp_safe_redirect()`.
* Settings are registered once with proper defaults; "Settings saved" is shown after saving.
* The plugin screen no longer shows notices of other plugins, as WooCommerce and others do on theirs; WordPress notices about security and updates are still shown. The `grmlt_hide_third_party_notices` filter turns this off.
* Excluded words and short words are removed before transliteration, with Unicode word boundaries, regardless of accents and letter case; numbers are never removed.
* Database tables are created on activation and upgraded once, not checked on every request. The update from 2.x/3.x runs in short steps on the first requests after the update (a few seconds at most for a visitor), continues after an interruption, and never takes the site down: after an error it waits an hour, keeps the 3.x behaviour meanwhile and offers "Retry now".
* Settings are taken over exactly as 2.x/3.x read them.
* Minimum WordPress 6.2, PHP 7.4; tested up to WordPress 7.1.

*Fixed*

* Category, tag and other term archives whose slug was still Greek returned 404 while permalink transliteration was enabled, because WordPress transliterated the slug it was looking for.
* Old Greek addresses of terms converted by 2.x/3.x are redirected (they used to work only through on-the-fly transliteration).
* Redirects that 3.x recorded for ACF / Secure Custom Fields objects and other internal posts are not carried over: they could only send visitors to the home page. A redirect never targets the home page (an address without a destination gives 404), except the redirect of the page that is the static front page; a manual redirect to the home page is refused.
* Saving a category or tag whose slug is still Greek, without changing the slug, no longer transliterates it.
* After an undo, the Latin addresses of pages and terms redirect to the restored ones instead of returning 404, and a later conversion is redirected again even where an older redirect existed.
* Cancelling a conversion while a batch runs works with the first click, and what was converted can be undone.
* Letter variants and archaic letters of the Greek block (ϐ, ϑ, ϕ, ϛ, ϝ…) and polytonic text on servers without the intl extension are transliterated.
* Editing a post, or a term, no longer rewrites a Latin or percent-encoded slug when the short-word options are on.
* Capital ΑΥ/ΕΥ before Τ, Φ and Ρ were transliterated wrong (ΑΥΤΟΚΙΝΗΤΟ → avtokinito).
* ΐ and ΰ were left untransliterated in permalinks and file names.
* ηυ before a vowel or voiced consonant gave "iu" instead of "iv".
* Excluded words containing a diphthong (και, είναι, ούτε) were never removed, and short excluded words were removed from inside longer words.
* The "Preview" button of the converter did nothing.
* The Greek translation that comes with the plugin is used even when an older Greek language pack from translate.wordpress.org is installed: strings the pack does not have are no longer shown in English.
* The converter produced different slugs from new permalinks (ου → oy) and could create duplicate slugs.
* Date and time display, search and excerpt features no longer interfere with themes and other plugins (removed, see below).

*Security*

* Entity-encoded markup in post content could reach the editor through the excerpt preview (stored XSS by a contributor). Clean-text extraction now strips tags after decoding, and all editor output is rendered as text.
* Every input is unslashed and sanitized, every output escaped; no inline scripts; every REST route has a permission callback; redirects use `wp_safe_redirect()` and accept only paths on this site.
* Direct access guards in every file; uninstall removes every table, option, transient, user meta and ACF key backup on every site of a network.

*Removed*

* Greek date localization: WordPress with the site language set to Greek does it correctly, including the genitive case of month names.
* Uppercase accent removal: modern browsers remove accents from capitals when the page declares `lang="el"`.
* Enhanced search: it never changed the search query; accent-insensitive search is a database collation matter, which Site Health now checks.
* Greek excerpts: WordPress trims Greek text by words correctly; the plugin's generator never ran.
* Feedback tab, welcome banner, Bootstrap and Popper libraries, source maps, empty front-end assets. The plugin no longer loads anything on the front end.

= 3.4.1 =
* Security: AJAX endpoints verify capabilities. Critical fix: the bulk converter no longer touches ACF internal post types; recovery command `wp grmlt acf-restore-keys`. Preview for the converter, undo snapshot, `_wp_old_slug`, percent-encoded redirect matching, removed a 404 stylesheet request.

= 3.4.0 =
* Critical fix: missing CSS on page builder pages when Yoast SEO was active. Duplicate Slider Revolution modules. New filter `grmlt_render_shortcodes_on_frontend`.

= 3.3.1 =
* Fixes a fatal error introduced in 3.3.0.

= 3.3.0 =
* Media file name conversion, attachment slug conversion, page builder compatibility layer, ACF compatibility, Yoast SEO integration.

= 3.2.0 =
* Fixed 301 redirects, nonce verification, XSS fixes, PHP 8.2 compatibility.

== Upgrade Notice ==

= 4.0.0 =
High severity fix: with Yoast SEO and page builder shortcodes that list posts, saving a page could overwrite its title and slug with another post's. Major release: removes Greek dates, uppercase accent removal, enhanced search and excerpts. Permalinks are kept. Back up first.
