<?php
/**
 * Greek Excerpts Tab UI
 *
 * @link       https://bigdrop.gr
 * @since      2.4.0
 *
 * @package    Grmlt_Plugin
 * @subpackage Grmlt_Plugin/admin/partials/settings-page
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Check if settings form was submitted
if (isset($_POST['grmlt_excerpt_settings_nonce']) && wp_verify_nonce($_POST['grmlt_excerpt_settings_nonce'], 'grmlt_excerpt_settings')) {
    // Update settings
    $enable_excerpts = isset($_POST['grmlt_enable_excerpts']) ? 1 : 0;
    $excerpt_length = isset($_POST['grmlt_excerpt_length']) ? absint($_POST['grmlt_excerpt_length']) : 55;
    $excerpt_more = isset($_POST['grmlt_excerpt_more']) ? sanitize_text_field($_POST['grmlt_excerpt_more']) : '&hellip;';
    
    update_option('grmlt_enable_excerpts', $enable_excerpts);
    update_option('grmlt_excerpt_length', $excerpt_length);
    update_option('grmlt_excerpt_more', $excerpt_more);
    
    echo '<div class="notice notice-success is-dismissible"><p>' . __('Settings saved.', 'greek-multi-tool') . '</p></div>';
}

// Get current settings
$feature_enabled = get_option('grmlt_enable_excerpts', false);
$excerpt_length = get_option('grmlt_excerpt_length', 55);
$excerpt_more = get_option('grmlt_excerpt_more', '&hellip;');
?>

<h6><?php _e('GREEK EXCERPT GENERATOR', 'greek-multi-tool'); ?></h6>
<hr>
<strong class="mb-0"><?php _e('Greek-Friendly Excerpt Generator', 'greek-multi-tool'); ?></strong>
<p><?php _e('Fix WordPress excerpt issues with Greek text', 'greek-multi-tool'); ?></p>

<div class="list-group mb-5 shadow">
    <div class="list-group-item">
        <div class="row align-items-center">
            <div class="col">
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Enable Greek Excerpts', 'greek-multi-tool'); ?></th>
                        <td>
                            <fieldset>
                                <legend class="screen-reader-text"><span><?php _e('Enable Greek Excerpts', 'greek-multi-tool'); ?></span></legend>
                                <label for="grmlt_enable_excerpts">
                                    <input name="grmlt_enable_excerpts" type="checkbox" id="grmlt_enable_excerpts" value="1" <?php checked(1, $feature_enabled); ?>>
                                    <?php _e('Enable Greek-friendly excerpt generation', 'greek-multi-tool'); ?>
                                </label>
                                <p class="description"><?php _e('When disabled, WordPress will use its default excerpt generation.', 'greek-multi-tool'); ?></p>
                            </fieldset>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="grmlt_excerpt_length"><?php _e('Greek Excerpt Length', 'greek-multi-tool'); ?></label></th>
                        <td>
                            <input name="grmlt_excerpt_length" type="number" id="grmlt_excerpt_length" value="<?php echo esc_attr($excerpt_length); ?>" class="small-text" min="1" max="1000">
                            <p class="description"><?php _e('Number of words to show in Greek excerpts (default WordPress value is 55).', 'greek-multi-tool'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="grmlt_excerpt_more"><?php _e('Greek Read More Text', 'greek-multi-tool'); ?></label></th>
                        <td>
                            <input name="grmlt_excerpt_more" type="text" id="grmlt_excerpt_more" value="<?php echo esc_attr($excerpt_more); ?>" class="regular-text">
                            <p class="description"><?php _e('Text to append after truncated excerpts (default is "…").', 'greek-multi-tool'); ?></p>
                        </td>
                    </tr>
                </table>
                
                <input type="hidden" name="grmlt_excerpt_settings_nonce" value="<?php echo wp_create_nonce('grmlt_excerpt_settings'); ?>">
                
                <strong class="mb-0 mt-4"><?php _e('Features:', 'greek-multi-tool'); ?></strong>
                <p class="text-muted mb-0"><?php _e('This feature fixes how WordPress generates excerpts for Greek text.', 'greek-multi-tool'); ?></p>
                <ul class="mt-2">
                    <li><?php _e('Properly handles Greek word boundaries', 'greek-multi-tool'); ?></li>
                    <li><?php _e('Ensures Greek words are not cut off mid-character', 'greek-multi-tool'); ?></li>
                    <li><?php _e('Removes shortcodes and page builder elements', 'greek-multi-tool'); ?></li>
                    <li><?php _e('Customizable excerpt length specifically for Greek text', 'greek-multi-tool'); ?></li>
                    <li><?php _e('Custom "read more" text', 'greek-multi-tool'); ?></li>
                    <li><?php _e('Adds a meta box in the post editor for generating and previewing excerpts', 'greek-multi-tool'); ?></li>
                    <li><?php _e('Manual editing capability for fine-tuning excerpts', 'greek-multi-tool'); ?></li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Sync settings between this page and WordPress Writing Settings
    $('#grmlt_enable_excerpts, #grmlt_excerpt_length, #grmlt_excerpt_more').on('change', function() {
        var fieldId = $(this).attr('id');
        var isCheckbox = $(this).attr('type') === 'checkbox';
        var value = isCheckbox ? ($(this).is(':checked') ? 1 : 0) : $(this).val();
        
        // Update WordPress Writing Settings via AJAX
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'grmlt_sync_excerpt_settings',
                field: fieldId,
                value: value,
                is_checkbox: isCheckbox ? 1 : 0,
                nonce: '<?php echo wp_create_nonce('grmlt_sync_excerpt_settings_nonce'); ?>'
            }
        });
    });
});
</script>