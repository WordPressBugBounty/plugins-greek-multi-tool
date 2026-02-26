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
 * Extract clean plain text from post content that may contain page builder markup.
 *
 * Handles WP Bakery, Elementor, Gutenberg blocks, Divi, Beaver Builder,
 * and Avada/Fusion Builder shortcodes. Renders shortcodes to HTML first,
 * then strips all tags and normalizes whitespace.
 *
 * @since 3.3.0
 * @param string $content Raw post_content (may contain shortcodes, blocks, HTML).
 * @return string Clean plain text suitable for analysis or excerpt generation.
 */
function grmlt_extract_clean_text($content) {
    if (empty($content)) {
        return '';
    }

    // 1. Remove Gutenberg block comments but keep inner content
    if (function_exists('excerpt_remove_blocks')) {
        $content = excerpt_remove_blocks($content);
    }
    // Also strip any remaining block comments <!-- wp:... -->
    $content = preg_replace('/<!--\s*\/?wp:.*?-->/s', '', $content);

    // 2. Render all shortcodes to HTML (this processes WP Bakery, Divi, etc.)
    // We need global $post to be set for shortcodes that depend on it
    if (function_exists('do_shortcode')) {
        $content = do_shortcode($content);
    }

    // 3. Strip any remaining unregistered shortcodes (page builder shortcodes
    //    that aren't registered because the builder isn't active on this request)
    $content = strip_shortcodes($content);

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
 * @param int $post_id The post ID to get content from.
 * @return string Clean plain text from the post.
 */
function grmlt_get_post_clean_text($post_id) {
    $post = get_post($post_id);
    if (!$post) {
        return '';
    }

    $content = $post->post_content;

    // Check if Elementor is used and get its data for richer content extraction
    if (grmlt_is_elementor_post($post_id)) {
        $elementor_content = grmlt_get_elementor_text($post_id);
        if (!empty($elementor_content)) {
            // Use Elementor content if it has more text than raw post_content
            $clean_elementor = grmlt_extract_clean_text($elementor_content);
            $clean_regular = grmlt_extract_clean_text($content);

            if (mb_strlen($clean_elementor) > mb_strlen($clean_regular)) {
                return $clean_elementor;
            }
        }
    }

    return grmlt_extract_clean_text($content);
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
 * @param string $content The post content for analysis.
 * @param object $post    The post object.
 * @return string Cleaned content for Yoast analysis.
 */
function grmlt_yoast_content_filter($content, $post) {
    if (empty($content) || !is_object($post)) {
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
