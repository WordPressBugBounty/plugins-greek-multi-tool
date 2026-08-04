<?php
/**
 * Page Builder Compatibility Layer
 *
 * Provides shared helper functions for extracting clean text content from posts
 * that use page builders (WP Bakery, Elementor, Gutenberg Blocks) and ensures
 * compatibility with Yoast SEO.
 *
 * @link       https://bigdrop.gr
 * @since      3.3.0
 *
 * @package    Grmlt_Plugin
 * @subpackage Grmlt_Plugin/admin/functions
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Determine whether the current request is a back end (editing) request.
 *
 * Shortcode rendering is only ever appropriate in a context where an editor is
 * asking for an analysis of the content. A plain `is_admin()` check is not
 * enough: it returns false for REST requests, which is exactly where the block
 * editor runs its SEO/readability analysis.
 *
 * WP-CLI and cron are deliberately NOT treated as editing contexts. Nothing
 * there consumes rendered content, and executing third party shortcodes outside
 * a real page request is precisely the class of side effect this guard exists
 * to prevent.
 *
 * @since 3.4.0
 * @return bool True when shortcode rendering is safe and expected.
 */
function grmlt_is_backend_request() {
    if ((defined('WP_CLI') && WP_CLI) || wp_doing_cron()) {
        return false;
    }

    return is_admin()
        || wp_doing_ajax()
        || (defined('REST_REQUEST') && REST_REQUEST);
}

/**
 * Extract clean plain text from post content that may contain page builder markup.
 *
 * Handles WP Bakery, Elementor, Gutenberg blocks, Divi, Beaver Builder,
 * and Avada/Fusion Builder shortcodes.
 *
 * In back end (editing) contexts the shortcodes are rendered to HTML first so
 * that the analysis sees the same text a visitor sees. On the front end the
 * shortcodes are never executed - only their tags are removed, keeping the
 * inner text intact. See grmlt_is_backend_request() and the comment above the
 * do_shortcode() call below for why.
 *
 * @since 3.3.0
 * @since 3.4.0 Added the optional $post_id parameter and the front end guard.
 * @param string $content Raw post_content (may contain shortcodes, blocks, HTML).
 * @param int    $post_id Optional. Post the content belongs to, passed to the
 *                        grmlt_render_shortcodes_on_frontend filter. Default 0.
 * @return string Clean plain text suitable for analysis or excerpt generation.
 */
function grmlt_extract_clean_text($content, $post_id = 0) {
    if (empty($content) || !is_string($content)) {
        return '';
    }

    // Guards against grmlt_extract_clean_text() being reached again from inside
    // a shortcode callback that we are already rendering.
    static $is_rendering = false;

    // 1. Remove Gutenberg block comments but keep inner content
    if (function_exists('excerpt_remove_blocks')) {
        $content = excerpt_remove_blocks($content);
    }
    // Also strip any remaining block comments <!-- wp:... -->
    $content = preg_replace('/<!--\s*\/?wp:.*?-->/s', '', $content);

    $render_shortcodes = grmlt_is_backend_request();

    if (!$render_shortcodes) {
        /**
         * Filters whether page builder shortcodes may be executed on the front end.
         *
         * Rendering the whole post content on a front end request re-runs every
         * shortcode on the page a second time. Only enable this if you know the
         * theme and plugins on the site tolerate it. See the comment on the
         * do_shortcode() call below.
         *
         * @since 3.4.0
         * @param bool $render  Whether to execute shortcodes. Default false.
         * @param int  $post_id The post being processed, or 0 when unknown.
         */
        $render_shortcodes = (bool) apply_filters('grmlt_render_shortcodes_on_frontend', false, (int) $post_id);
    }

    /*
     * 2. Render all shortcodes to HTML (this processes WP Bakery, Divi, etc.)
     *
     * DO NOT run this on front end requests. Regression fixed in 3.4.0:
     * Yoast SEO calls wpseo_pre_analysis_post_content from inside wp_head (via
     * WPSEO_Content_Images, when a page has no featured image). Rendering the
     * post content there executes every shortcode on the page a second time,
     * before the real the_content render. Themes that emit per-element CSS on
     * demand (Woodmart, Flatsome, Porto, ...) mark each CSS part as "already
     * emitted" during that first render, and the markup they returned is then
     * thrown away by wp_strip_all_tags() at the end of this function. The real
     * render is skipped by the theme's dedupe guard, so the CSS never reaches
     * the browser and the page loads unstyled. Sliders emit duplicate module
     * IDs for the same reason.
     *
     * The front end never needed this anyway: this function strips all HTML,
     * so the <img> tags Yoast is looking for cannot survive it in any case.
     */
    if ($render_shortcodes && !$is_rendering && function_exists('do_shortcode')) {
        $is_rendering = true;

        try {
            $content = do_shortcode($content);

            /*
             * 3. Strip any remaining unregistered shortcodes (page builder
             *    shortcodes that aren't registered because the builder isn't
             *    active on this request).
             *
             * This is only safe AFTER do_shortcode(). WordPress implements
             * strip_shortcodes() via strip_shortcode_tag(), which returns
             * $m[1] . $m[6] and therefore discards the ENCLOSED CONTENT as
             * well as the tag. On WP Bakery content, where every paragraph
             * lives inside [vc_column_text]...[/vc_column_text], running it on
             * unrendered content returns an empty string. Steps 4 and 5 below
             * remove leftover tags without touching the text between them.
             */
            $content = strip_shortcodes($content);
        } finally {
            $is_rendering = false;
        }
    }

    // 4. Remove specific page builder shortcode patterns that may survive
    //    (covers cases where builders aren't active but shortcodes remain in content)
    $builder_patterns = array(
        '/\[\/?vc_[^\]]*\]/s',           // WP Bakery (Visual Composer)
        '/\[\/?et_[^\]]*\]/s',           // Divi Builder
        '/\[\/?fl_[^\]]*\]/s',           // Beaver Builder
        '/\[\/?fusion_[^\]]*\]/s',       // Fusion Builder (Avada)
        '/\[\/?elementor[^\]]*\]/s',     // Elementor shortcodes
        '/\[\/?cs_[^\]]*\]/s',           // Cornerstone (X Theme)
        '/\[\/?av_[^\]]*\]/s',           // Enfold/Avia Builder
        '/\[\/?mk_[^\]]*\]/s',           // Jupiter Theme
        '/\[\/?bt_[^\]]*\]/s',           // Bold Builder
        '/\[\/?tatsu_[^\]]*\]/s',        // Flavor/Flavor Builder
        '/\[\/?porto_[^\]]*\]/s',        // Porto Theme
        '/\[\/?ux_[^\]]*\]/s',           // Flatsome
    );
    $content = preg_replace($builder_patterns, '', $content);

    // 5. Remove any remaining shortcode-like patterns [something]...[/something]
    //    but preserve their inner content
    $content = preg_replace('/\[[^\]]+\]/', '', $content);

    // 6. Strip all HTML tags
    $content = wp_strip_all_tags($content);

    // 7. Decode HTML entities
    $content = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    // 8. Normalize whitespace
    $content = preg_replace('/\s+/', ' ', $content);
    $content = trim($content);

    return $content;
}

/**
 * Get the full rendered content for a post, including page builder data.
 *
 * Attempts to get content from multiple sources:
 * 1. Elementor data (stored in post meta)
 * 2. Regular post_content (which contains WP Bakery, Gutenberg, etc.)
 *
 * @since 3.3.0
 * @since 3.4.0 Results are memoized for the duration of the request.
 * @param int $post_id The post ID to get content from.
 * @return string Clean plain text from the post.
 */
function grmlt_get_post_clean_text($post_id) {
    // Per request cache. get_the_excerpt and the Yoast analysis can both ask for
    // the same post several times in one request; the extraction is the most
    // expensive thing this file does, so it should only ever run once per post.
    // Keyed by render mode as well, because the same post yields different text
    // depending on whether shortcodes were executed.
    static $cache = array();

    $post = get_post($post_id);
    if (!$post) {
        return '';
    }

    $cache_key = (int) $post->ID . (grmlt_is_backend_request() ? '|b' : '|f');
    if (isset($cache[$cache_key])) {
        return $cache[$cache_key];
    }

    $content = $post->post_content;

    // Check if Elementor is used and get its data for richer content extraction
    if (grmlt_is_elementor_post($post->ID)) {
        $elementor_content = grmlt_get_elementor_text($post->ID);
        if (!empty($elementor_content)) {
            // Use Elementor content if it has more text than raw post_content
            $clean_elementor = grmlt_extract_clean_text($elementor_content, $post->ID);
            $clean_regular = grmlt_extract_clean_text($content, $post->ID);

            if (mb_strlen($clean_elementor) > mb_strlen($clean_regular)) {
                $cache[$cache_key] = $clean_elementor;
                return $cache[$cache_key];
            }
        }
    }

    $cache[$cache_key] = grmlt_extract_clean_text($content, $post->ID);

    return $cache[$cache_key];
}

/**
 * Check if a post was built with Elementor.
 *
 * @since 3.3.0
 * @param int $post_id The post ID.
 * @return bool True if Elementor was used, false otherwise.
 */
function grmlt_is_elementor_post($post_id) {
    return !empty(get_post_meta($post_id, '_elementor_edit_mode', true));
}

/**
 * Extract text content from Elementor data stored in post meta.
 *
 * Elementor stores its widget data as serialized arrays in post meta
 * under the key '_elementor_data'. This function recursively walks
 * the data tree to extract all text content.
 *
 * @since 3.3.0
 * @param int $post_id The post ID.
 * @return string Concatenated text from all Elementor widgets.
 */
function grmlt_get_elementor_text($post_id) {
    $elementor_data = get_post_meta($post_id, '_elementor_data', true);

    if (empty($elementor_data)) {
        return '';
    }

    // Elementor data can be a JSON string or already decoded
    if (is_string($elementor_data)) {
        $elementor_data = json_decode($elementor_data, true);
    }

    if (!is_array($elementor_data)) {
        return '';
    }

    $texts = array();
    grmlt_walk_elementor_data($elementor_data, $texts);

    return implode(' ', $texts);
}

/**
 * Recursively walk Elementor data to extract text content.
 *
 * @since 3.3.0
 * @param array $elements Elementor data elements.
 * @param array &$texts   Collected text strings (passed by reference).
 */
function grmlt_walk_elementor_data($elements, &$texts) {
    if (!is_array($elements)) {
        return;
    }

    foreach ($elements as $element) {
        if (!is_array($element)) {
            continue;
        }

        // Extract text from widget settings
        if (isset($element['settings']) && is_array($element['settings'])) {
            $text_fields = array(
                'editor',           // Text editor widget
                'title',            // Heading widget
                'description',      // Various widgets
                'text',             // Text widget
                'content',          // Content fields
                'html',             // HTML widget
                'inner_text',       // Inner text fields
                'item_description', // List/item descriptions
                'tab_content',      // Tab content
                'accordion_content', // Accordion content
                'alert_description', // Alert widget
                'testimonial_content', // Testimonial widget
                'blockquote_content', // Blockquote
                'caption',          // Image caption
            );

            foreach ($text_fields as $field) {
                if (!empty($element['settings'][$field])) {
                    $value = $element['settings'][$field];
                    if (is_string($value)) {
                        $texts[] = wp_strip_all_tags($value);
                    }
                }
            }

            // Handle repeater fields (like tabs, accordions, etc.)
            if (isset($element['settings']['tabs']) && is_array($element['settings']['tabs'])) {
                foreach ($element['settings']['tabs'] as $tab) {
                    if (!empty($tab['tab_content'])) {
                        $texts[] = wp_strip_all_tags($tab['tab_content']);
                    }
                }
            }
        }

        // Recurse into child elements
        if (isset($element['elements']) && is_array($element['elements'])) {
            grmlt_walk_elementor_data($element['elements'], $texts);
        }
    }
}

/**
 * Check if a post was built with WP Bakery.
 *
 * Detects WP Bakery content by looking for vc_ shortcodes in post_content.
 *
 * @since 3.3.0
 * @param int $post_id The post ID.
 * @return bool True if WP Bakery shortcodes are found, false otherwise.
 */
function grmlt_is_wpbakery_post($post_id) {
    $post = get_post($post_id);
    if (!$post) {
        return false;
    }
    return (bool) preg_match('/\[vc_/', $post->post_content);
}

/**
 * Filter for Yoast SEO compatibility.
 *
 * Provides clean text content to Yoast when it analyzes page builder content.
 * Hooks into wpseo_pre_analysis_post_content to give Yoast the rendered text
 * instead of raw shortcodes.
 *
 * @since 3.3.0
 * @since 3.4.0 Front end requests are passed through untouched.
 * @param string $content The post content for analysis.
 * @param object $post    The post object.
 * @return string Cleaned content for Yoast analysis.
 */
function grmlt_yoast_content_filter($content, $post) {
    if (empty($content) || !is_object($post)) {
        return $content;
    }

    $post_id = isset($post->ID) ? (int) $post->ID : 0;

    /*
     * CRITICAL - do not remove this guard without reading what it prevents.
     *
     * wpseo_pre_analysis_post_content is NOT an editor-only filter. Yoast also
     * applies it on the front end, inside wp_head:
     *
     *   wp_head
     *    -> Yoast Front_End_Integration::present_head
     *       -> Schema WebPage::add_image
     *          -> Meta_Tags_Context::generate_main_image_url   (no featured image)
     *             -> WPSEO_Content_Images::get_post_content
     *                -> apply_filters( 'wpseo_pre_analysis_post_content', ... )
     *
     * Before 3.4.0 we answered that call with fully rendered content, which
     * executed every shortcode on the page inside wp_head, ahead of the real
     * the_content render. Themes with on demand per-element CSS (Woodmart,
     * Flatsome, Porto and similar) recorded each CSS part as already emitted
     * during that first pass, then skipped it during the real render, and the
     * page was served with a large part of its stylesheet missing.
     *
     * The front end gains nothing from filtering here: Yoast is looking for
     * <img> tags, and grmlt_extract_clean_text() strips all HTML, so it could
     * never have found one. Passing $content through unchanged restores plain
     * WordPress behaviour - Yoast reads the raw post_content and finds whatever
     * literal <img> tags it contains. For page builder pages the supported way
     * to give Yoast an image is to set a featured image.
     *
     * Editor analysis (admin screens, REST, admin-ajax) still gets the fully
     * rendered text, which is what this integration was added for in 3.3.0.
     */
    if (!grmlt_is_backend_request()
        && !apply_filters('grmlt_render_shortcodes_on_frontend', false, $post_id)) {
        return $content;
    }

    // Only process if content contains page builder shortcodes
    $has_builder_content = (
        preg_match('/\[vc_/', $content) ||       // WP Bakery
        preg_match('/\[et_/', $content) ||        // Divi
        preg_match('/\[fl_/', $content) ||        // Beaver Builder
        preg_match('/\[fusion_/', $content) ||    // Avada
        grmlt_is_elementor_post($post->ID)
    );

    if ($has_builder_content) {
        $clean = grmlt_get_post_clean_text($post->ID);
        if (!empty($clean)) {
            return $clean;
        }
    }

    return $content;
}

// Hook into Yoast SEO's content analysis filter if Yoast is active
add_action('init', function() {
    // Yoast SEO (free and premium)
    if (defined('WPSEO_VERSION') || defined('WPSEO_PREMIUM_VERSION')) {
        add_filter('wpseo_pre_analysis_post_content', 'grmlt_yoast_content_filter', 10, 2);
    }
}, 20);
