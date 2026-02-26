<?php
/**
 * Enhanced Greek-Friendly Excerpt Generator
 *
 * @link       https://bigdrop.gr
 * @since      2.4.0
 *
 * @package    Grmlt_Plugin
 * @subpackage Grmlt_Plugin/admin/functions
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Adds settings fields for Greek excerpt options to WordPress Settings
 */
function grmlt_add_excerpt_settings() {
    // Add a new section for excerpt options
    add_settings_section(
        'grmlt_excerpt_settings_section',
        __('Greek Excerpt Settings', 'greek-multi-tool'),
        'grmlt_excerpt_settings_section_callback',
        'writing'
    );
    
    // Add settings fields
    add_settings_field(
        'grmlt_enable_excerpts',
        __('Enable Greek Excerpts', 'greek-multi-tool'),
        'grmlt_enable_excerpts_callback',
        'writing',
        'grmlt_excerpt_settings_section'
    );
    
    add_settings_field(
        'grmlt_excerpt_length',
        __('Greek Excerpt Length', 'greek-multi-tool'),
        'grmlt_excerpt_length_callback',
        'writing',
        'grmlt_excerpt_settings_section'
    );
    
    add_settings_field(
        'grmlt_excerpt_more',
        __('Greek Read More Text', 'greek-multi-tool'),
        'grmlt_excerpt_more_callback',
        'writing',
        'grmlt_excerpt_settings_section'
    );
    
    // Register the settings
    register_setting('writing', 'grmlt_enable_excerpts', array(
        'type' => 'boolean',
        'default' => false,
        'sanitize_callback' => 'rest_sanitize_boolean'
    ));
    
    register_setting('writing', 'grmlt_excerpt_length', array(
        'type' => 'integer',
        'default' => 55,
        'sanitize_callback' => 'absint'
    ));
    
    register_setting('writing', 'grmlt_excerpt_more', array(
        'type' => 'string',
        'default' => '&hellip;',
        'sanitize_callback' => 'sanitize_text_field'
    ));
}
add_action('admin_init', 'grmlt_add_excerpt_settings');

/**
 * Callback for excerpt settings section
 */
function grmlt_excerpt_settings_section_callback() {
    echo '<p>' . __('Configure how excerpts are generated for Greek text.', 'greek-multi-tool') . '</p>';
}

/**
 * Callback for enable excerpts setting
 */
function grmlt_enable_excerpts_callback() {
    $enabled = get_option('grmlt_enable_excerpts', false);
    echo '<input type="checkbox" id="grmlt_enable_excerpts" name="grmlt_enable_excerpts" value="1" ' . checked(1, $enabled, false) . '/>';
    echo '<p class="description">' . __('Enable Greek-friendly excerpt generation. When disabled, WordPress will use its default excerpt generation.', 'greek-multi-tool') . '</p>';
}

/**
 * Callback for excerpt length setting
 */
function grmlt_excerpt_length_callback() {
    $excerpt_length = get_option('grmlt_excerpt_length', 55);
    echo '<input type="number" id="grmlt_excerpt_length" name="grmlt_excerpt_length" value="' . esc_attr($excerpt_length) . '" class="small-text" min="1" max="1000" />';
    echo '<p class="description">' . __('Number of words to show in Greek excerpts (default WordPress value is 55).', 'greek-multi-tool') . '</p>';
}

/**
 * Callback for excerpt more setting
 */
function grmlt_excerpt_more_callback() {
    $excerpt_more = get_option('grmlt_excerpt_more', '&hellip;');
    echo '<input type="text" id="grmlt_excerpt_more" name="grmlt_excerpt_more" value="' . esc_attr($excerpt_more) . '" class="regular-text" />';
    echo '<p class="description">' . __('Text to append after truncated excerpts (default is "…").', 'greek-multi-tool') . '</p>';
}

/**
 * Filter the excerpt length
 *
 * @param int $length Default excerpt length
 * @return int Modified excerpt length
 */
function grmlt_custom_excerpt_length($length) {
    // Check if feature is enabled
    if (!get_option('grmlt_enable_excerpts', false)) {
        return $length;
    }
    
    $custom_length = get_option('grmlt_excerpt_length', 55);
    return $custom_length;
}
add_filter('excerpt_length', 'grmlt_custom_excerpt_length', 999);

/**
 * Filter the excerpt "read more" string
 *
 * @param string $more "Read more" excerpt string
 * @return string Modified "read more" excerpt string
 */
function grmlt_custom_excerpt_more($more) {
    // Check if feature is enabled
    if (!get_option('grmlt_enable_excerpts', false)) {
        return $more;
    }
    
    $custom_more = get_option('grmlt_excerpt_more', '&hellip;');
    return $custom_more;
}
add_filter('excerpt_more', 'grmlt_custom_excerpt_more', 999);

/**
 * Create a Greek-friendly excerpt
 *
 * Supports content from all major page builders including WP Bakery,
 * Elementor, Gutenberg Blocks, Divi, Beaver Builder, and Avada.
 *
 * @param string $text Text to create excerpt from
 * @param int $length Optional. Length of excerpt in words. Default 55.
 * @param string $more Optional. Text to append to the end of the excerpt. Default '&hellip;'.
 * @return string Generated excerpt
 */
function grmlt_greek_excerpt($text, $length = null, $more = null) {
    if ($length === null) {
        $length = get_option('grmlt_excerpt_length', 55);
    }

    if ($more === null) {
        $more = get_option('grmlt_excerpt_more', '&hellip;');
    }

    // Use the page builder compatibility layer for clean text extraction
    if (function_exists('grmlt_extract_clean_text')) {
        $text = grmlt_extract_clean_text($text);
    } else {
        // Fallback if compat layer isn't loaded yet
        $text = strip_shortcodes($text);
        $text = preg_replace('/\[\/?vc_[^\]]*\]/s', '', $text);
        $text = preg_replace('/\[\/?et_[^\]]*\]/s', '', $text);
        $text = preg_replace('/\[\/?fl_[^\]]*\]/s', '', $text);
        $text = preg_replace('/\[\/?fusion_[^\]]*\]/s', '', $text);
        $text = preg_replace('/\[\/?elementor[^\]]*\]/s', '', $text);
        $text = preg_replace('/\[\/?[^\]]+\]/', '', $text);
        if (function_exists('excerpt_remove_blocks')) {
            $text = excerpt_remove_blocks($text);
        }
        $text = wp_strip_all_tags($text);
        $text = preg_replace('/\s+/', ' ', $text);
        $text = trim($text);
    }
    
    // Detect if text contains Greek characters
    $has_greek = preg_match('/\p{Greek}/u', $text);
    
    if (!$has_greek) {
        // If not Greek, use standard WordPress functionality
        $words = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        if (count($words) > $length) {
            return implode(' ', array_slice($words, 0, $length)) . $more;
        }
        return $text;
    }
    
    // For Greek text, use mb_* functions for proper Unicode handling
    // This regex specifically handles Greek word boundaries correctly
    $words = preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
    
    if (count($words) > $length) {
        $excerpt = implode(' ', array_slice($words, 0, $length));
        
        // Make sure we're not cutting in the middle of a Unicode character
        if (mb_check_encoding($excerpt, 'UTF-8')) {
            return $excerpt . $more;
        }
        
        // Fallback if encoding is messed up
        return mb_substr($text, 0, mb_strlen($excerpt, 'UTF-8'), 'UTF-8') . $more;
    }
    
    return $text;
}

/**
 * Override the default get_the_excerpt function with Greek-friendly version
 *
 * Supports all major page builders (WP Bakery, Elementor, Gutenberg, etc.)
 * by using the page builder compatibility layer for content extraction.
 *
 * @param string $post_excerpt The post excerpt
 * @param WP_Post $post The post object
 * @return string The modified excerpt
 */
function grmlt_filter_get_the_excerpt($post_excerpt, $post) {
    // Check if feature is enabled
    if (!get_option('grmlt_enable_excerpts', false)) {
        return $post_excerpt;
    }

    // If an explicit excerpt is set, return it unchanged
    if (!empty($post_excerpt)) {
        return $post_excerpt;
    }

    // Use the page builder compat layer for best content extraction
    if (function_exists('grmlt_get_post_clean_text') && !empty($post->ID)) {
        $clean_text = grmlt_get_post_clean_text($post->ID);
        if (!empty($clean_text)) {
            // Apply excerpt length and "more" text to the clean content
            $length = get_option('grmlt_excerpt_length', 55);
            $more = get_option('grmlt_excerpt_more', '&hellip;');
            $words = preg_split('/\s+/u', $clean_text, -1, PREG_SPLIT_NO_EMPTY);
            if (count($words) > $length) {
                return implode(' ', array_slice($words, 0, $length)) . $more;
            }
            return $clean_text;
        }
    }

    // Fallback to standard excerpt generation
    return grmlt_greek_excerpt($post->post_content);
}
add_filter('get_the_excerpt', 'grmlt_filter_get_the_excerpt', 10, 2);

/**
 * Add Greek excerpt meta box to post editor
 */
function grmlt_add_excerpt_meta_box() {
    // Add metabox only if feature is enabled
    if (!get_option('grmlt_enable_excerpts', false)) {
        return;
    }
    
    $screens = array('post', 'page');
    
    foreach ($screens as $screen) {
        add_meta_box(
            'grmlt_greek_excerpt',
            __('Greek-Friendly Excerpt', 'greek-multi-tool'),
            'grmlt_excerpt_meta_box_callback',
            $screen,
            'normal',
            'high'
        );
    }
}
add_action('add_meta_boxes', 'grmlt_add_excerpt_meta_box');

/**
 * Callback for excerpt meta box
 */
function grmlt_excerpt_meta_box_callback($post) {
    wp_nonce_field('grmlt_excerpt_meta_box', 'grmlt_excerpt_meta_box_nonce');
    
    // Get content from post
    $content = $post->post_content;
    $current_excerpt = $post->post_excerpt;
    
    // Generate excerpt
    $generated_excerpt = grmlt_greek_excerpt($content);
    
    $excerpt_length = get_option('grmlt_excerpt_length', 55);
    
    ?>
    <p><?php _e('Greek-friendly excerpt generator creates proper excerpts for Greek text, ensuring words are not cut off incorrectly.', 'greek-multi-tool'); ?></p>
    
    <?php if (!empty($current_excerpt)) : ?>
        <p><strong><?php _e('Current Excerpt:', 'greek-multi-tool'); ?></strong></p>
        <div class="grmlt-current-excerpt" style="margin-bottom: 15px; padding: 10px; background: #f8f8f8; border: 1px solid #ddd;">
            <?php echo esc_html($current_excerpt); ?>
        </div>
    <?php endif; ?>
    
    <p><strong><?php _e('Generated Greek-Friendly Excerpt:', 'greek-multi-tool'); ?></strong></p>
    <div class="grmlt-generated-excerpt" style="margin-bottom: 15px; padding: 10px; background: #f8f8f8; border: 1px solid #ddd;">
        <?php echo esc_html($generated_excerpt); ?>
    </div>
    
    <div class="grmlt-excerpt-editor" style="margin-bottom: 15px;">
        <p><strong><?php _e('Manual Edit:', 'greek-multi-tool'); ?></strong></p>
        <textarea id="grmlt-manual-excerpt" style="width: 100%; height: 120px;" placeholder="<?php esc_attr_e('Edit excerpt manually...', 'greek-multi-tool'); ?>"><?php echo esc_textarea(!empty($current_excerpt) ? $current_excerpt : $generated_excerpt); ?></textarea>
    </div>
    
    <div class="grmlt-excerpt-actions">
        <button type="button" id="grmlt-refresh-excerpt" class="button"><?php _e('Refresh Generated Excerpt', 'greek-multi-tool'); ?></button>
        <button type="button" id="grmlt-use-generated" class="button"><?php _e('Use Generated Excerpt', 'greek-multi-tool'); ?></button>
        <button type="button" id="grmlt-use-manual" class="button button-primary"><?php _e('Save Manual Excerpt', 'greek-multi-tool'); ?></button>
        
        <div style="margin-top: 10px;">
            <label for="grmlt-custom-length"><?php _e('Custom Length:', 'greek-multi-tool'); ?></label>
            <input type="number" id="grmlt-custom-length" value="<?php echo esc_attr($excerpt_length); ?>" min="1" max="1000" style="width: 70px;">
            <button type="button" id="grmlt-apply-length" class="button"><?php _e('Apply', 'greek-multi-tool'); ?></button>
        </div>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        // Refresh excerpt
        $('#grmlt-refresh-excerpt').on('click', function() {
            var length = $('#grmlt-custom-length').val();
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'grmlt_refresh_excerpt',
                    post_id: <?php echo esc_js($post->ID); ?>,
                    length: length,
                    nonce: '<?php echo wp_create_nonce('grmlt_refresh_excerpt_nonce'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        $('.grmlt-generated-excerpt').html(response.data);
                    } else {
                        alert(response.data);
                    }
                },
                error: function() {
                    alert('<?php echo esc_js(__('Error refreshing excerpt', 'greek-multi-tool')); ?>');
                }
            });
        });
        
        // Apply custom length
        $('#grmlt-apply-length').on('click', function() {
            $('#grmlt-refresh-excerpt').trigger('click');
        });
        
        // Use generated excerpt
        $('#grmlt-use-generated').on('click', function() {
            var generatedExcerpt = $('.grmlt-generated-excerpt').text();
            $('#grmlt-manual-excerpt').val(generatedExcerpt);
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'grmlt_use_excerpt',
                    post_id: <?php echo esc_js($post->ID); ?>,
                    excerpt: generatedExcerpt,
                    nonce: '<?php echo wp_create_nonce('grmlt_use_excerpt_nonce'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        alert('<?php echo esc_js(__('Generated excerpt successfully applied to post!', 'greek-multi-tool')); ?>');
                        // If excerpt editor exists, update it
                        if ($('#excerpt').length) {
                            $('#excerpt').val(response.data);
                        }
                        // Update current excerpt display
                        $('.grmlt-current-excerpt').html(response.data).show();
                    } else {
                        alert(response.data);
                    }
                },
                error: function() {
                    alert('<?php echo esc_js(__('Error applying excerpt', 'greek-multi-tool')); ?>');
                }
            });
        });
        
        // Use manual excerpt
        $('#grmlt-use-manual').on('click', function() {
            var manualExcerpt = $('#grmlt-manual-excerpt').val();
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'grmlt_use_excerpt',
                    post_id: <?php echo esc_js($post->ID); ?>,
                    excerpt: manualExcerpt,
                    nonce: '<?php echo wp_create_nonce('grmlt_use_excerpt_nonce'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        alert('<?php echo esc_js(__('Manual excerpt successfully saved!', 'greek-multi-tool')); ?>');
                        // If excerpt editor exists, update it
                        if ($('#excerpt').length) {
                            $('#excerpt').val(response.data);
                        }
                        // Update current excerpt display
                        $('.grmlt-current-excerpt').html(response.data).show();
                    } else {
                        alert(response.data);
                    }
                },
                error: function() {
                    alert('<?php echo esc_js(__('Error saving excerpt', 'greek-multi-tool')); ?>');
                }
            });
        });
    });
    </script>
    <?php
}

/**
 * AJAX handler for refreshing the excerpt
 */
function grmlt_ajax_refresh_excerpt() {
    // Check nonce for security
    check_ajax_referer('grmlt_refresh_excerpt_nonce', 'nonce');
    
    // Get post ID
    $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
    $length = isset($_POST['length']) ? intval($_POST['length']) : null;
    
    if (!$post_id) {
        wp_send_json_error(__('Invalid post ID', 'greek-multi-tool'));
    }
    
    // Get post content
    $post = get_post($post_id);

    if (!$post) {
        wp_send_json_error(__('Post not found', 'greek-multi-tool'));
    }

    // Use page builder compat layer for best content extraction
    if (function_exists('grmlt_get_post_clean_text')) {
        $clean_text = grmlt_get_post_clean_text($post_id);
        if (!empty($clean_text)) {
            $more = get_option('grmlt_excerpt_more', '&hellip;');
            $words = preg_split('/\s+/u', $clean_text, -1, PREG_SPLIT_NO_EMPTY);
            $actual_length = ($length !== null) ? $length : get_option('grmlt_excerpt_length', 55);
            if (count($words) > $actual_length) {
                $excerpt = implode(' ', array_slice($words, 0, $actual_length)) . $more;
            } else {
                $excerpt = $clean_text;
            }
            wp_send_json_success($excerpt);
            return;
        }
    }

    // Fallback: Generate excerpt from raw post_content
    $excerpt = grmlt_greek_excerpt($post->post_content, $length);
    
    // Send results
    wp_send_json_success($excerpt);
}
add_action('wp_ajax_grmlt_refresh_excerpt', 'grmlt_ajax_refresh_excerpt');

/**
 * AJAX handler for using the excerpt
 */
function grmlt_ajax_use_excerpt() {
    // Check nonce for security
    check_ajax_referer('grmlt_use_excerpt_nonce', 'nonce');
    
    // Get post ID and excerpt
    $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
    $excerpt = isset($_POST['excerpt']) ? sanitize_textarea_field($_POST['excerpt']) : '';
    
    if (!$post_id) {
        wp_send_json_error(__('Invalid post ID', 'greek-multi-tool'));
    }
    
    // Update post excerpt
    $updated = wp_update_post(array(
        'ID' => $post_id,
        'post_excerpt' => $excerpt
    ));
    
    if (is_wp_error($updated)) {
        wp_send_json_error(__('Error updating post excerpt', 'greek-multi-tool'));
    }
    
    // Send results
    wp_send_json_success($excerpt);
}
add_action('wp_ajax_grmlt_use_excerpt', 'grmlt_ajax_use_excerpt');

/**
 * AJAX handler for syncing settings between plugin page and WP Settings
 */
function grmlt_ajax_sync_settings() {
    // Check nonce for security
    check_ajax_referer('grmlt_sync_excerpt_settings_nonce', 'nonce');
    
    // Get field and value
    $field = isset($_POST['field']) ? sanitize_text_field($_POST['field']) : '';
    $value = isset($_POST['value']) ? $_POST['value'] : '';
    $is_checkbox = isset($_POST['is_checkbox']) ? (bool)$_POST['is_checkbox'] : false;
    
    if (empty($field)) {
        wp_send_json_error(__('Invalid field', 'greek-multi-tool'));
    }
    
    // Process value based on field type
    if ($is_checkbox) {
        $value = (bool)$value;
    } elseif ($field === 'grmlt_excerpt_length') {
        $value = absint($value);
    } else {
        $value = sanitize_text_field($value);
    }
    
    // Update option
    update_option($field, $value);
    
    // Send success
    wp_send_json_success();
}
add_action('wp_ajax_grmlt_sync_excerpt_settings', 'grmlt_ajax_sync_settings');

/**
 * Add Greek excerpt generator tab to the plugin settings page
 */
function grmlt_add_excerpt_tab($tabs) {
    $tabs['excerpts'] = __('Excerpts', 'greek-multi-tool');
    return $tabs;
}
add_filter('grmlt_settings_tabs', 'grmlt_add_excerpt_tab');

/**
 * Display Excerpt generator tab content with mirrored settings from WordPress Writing Settings
 */
function grmlt_display_excerpt_tab_content() {
    // Include the tab content file
    include plugin_dir_path(dirname(dirname(__FILE__))) . 'admin/partials/settings-page/greek-excerpts-tab.php';
}
add_action('grmlt_settings_tab_excerpts', 'grmlt_display_excerpt_tab_content');