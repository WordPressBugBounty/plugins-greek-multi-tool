<?php
/**
 * Search Settings Tab UI
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

// Get current settings
$search_enabled = get_option('grmlt_enhance_search', 'on');
$selected_post_types = get_option('grmlt_search_post_types', array('post', 'page'));

// Get available post types
$post_types = get_post_types(array('public' => true), 'objects');
?>

<h6><?php _e('GREEK SEARCH SETTINGS', 'greek-multi-tool'); ?></h6>
<hr>
<strong class="mb-0"><?php _e('Enhanced Greek Search', 'greek-multi-tool'); ?></strong>
<p><?php _e('Improve WordPress search for Greek content by handling accents and diphthongs', 'greek-multi-tool'); ?></p>

<div class="list-group mb-5 shadow">
    <div class="list-group-item">
        <div class="row align-items-center">
            <div class="col">
                <strong class="mb-0"><?php _e('Enable Enhanced Greek Search:', 'greek-multi-tool'); ?></strong>
                <p class="text-muted mb-0"><?php _e('Improve search results for Greek content by handling accents and diphthongs.', 'greek-multi-tool'); ?></p>
            </div>
            <div class="col-auto">
                <div class="custom-control custom-switch">
                    <label class="switch">
                        <input type="checkbox" id="grmlt_enhance_search" name="grmlt_enhance_search" 
                            <?php checked($search_enabled, 'on'); ?> />
                        <span class="slider round"></span>
                    </label>
                </div>
            </div>
        </div>
    </div>
    
    <div class="list-group-item">
        <div class="row">
            <div class="col">
                <strong class="mb-0"><?php _e('Post Types to Include in Search:', 'greek-multi-tool'); ?></strong>
                <p class="text-muted mb-0"><?php _e('Select which post types should be included in the enhanced search.', 'greek-multi-tool'); ?></p>
                
                <div class="mt-3">
                    <?php foreach ($post_types as $post_type) : ?>
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="grmlt_search_post_type_<?php echo esc_attr($post_type->name); ?>" 
                                name="grmlt_search_post_types[]" value="<?php echo esc_attr($post_type->name); ?>"
                                <?php checked(in_array($post_type->name, $selected_post_types)); ?> />
                            <label class="form-check-label" for="grmlt_search_post_type_<?php echo esc_attr($post_type->name); ?>">
                                <?php echo esc_html($post_type->labels->name); ?>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>