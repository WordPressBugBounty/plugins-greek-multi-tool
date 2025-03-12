<?php
/**
 * Date Localization Tab UI
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
$localize_dates = get_option('grmlt_localize_dates', 'on');
$date_format = get_option('grmlt_date_format', 'default');
$custom_date_format = get_option('grmlt_custom_date_format', '');

// Date format examples
$greek_date_formats = array(
    'default' => __('Default (1 Ιανουαρίου 2023)', 'greek-multi-tool'),
    'full' => __('Full (Δευτέρα, 1 Ιανουαρίου 2023)', 'greek-multi-tool'),
    'short' => __('Short (1/1/2023)', 'greek-multi-tool'),
    'with_time' => __('With time (1 Ιανουαρίου 2023, 13:45)', 'greek-multi-tool'),
    'archive' => __('Archive (Ιανουαρίου 2023)', 'greek-multi-tool'),
    'custom' => __('Custom format', 'greek-multi-tool')
);
?>

<h6><?php _e('GREEK DATE LOCALIZATION', 'greek-multi-tool'); ?></h6>
<hr>
<strong class="mb-0"><?php _e('Greek Date Format Settings', 'greek-multi-tool'); ?></strong>
<p><?php _e('Properly display dates in Greek format with Greek month and day names', 'greek-multi-tool'); ?></p>

<div class="list-group mb-5 shadow">
    <div class="list-group-item">
        <div class="row align-items-center">
            <div class="col">
                <strong class="mb-0"><?php _e('Enable Greek Date Localization:', 'greek-multi-tool'); ?></strong>
                <p class="text-muted mb-0"><?php _e('Display dates with Greek month and day names throughout the site.', 'greek-multi-tool'); ?></p>
            </div>
            <div class="col-auto">
                <div class="custom-control custom-switch">
                    <label class="switch">
                        <input type="checkbox" id="grmlt_localize_dates" name="grmlt_localize_dates" 
                            <?php checked($localize_dates, 'on'); ?> />
                        <span class="slider round"></span>
                    </label>
                </div>
            </div>
        </div>
    </div>
    
    <div class="list-group-item">
        <div class="row">
            <div class="col">
                <strong class="mb-0"><?php _e('Date Format:', 'greek-multi-tool'); ?></strong>
                <p class="text-muted mb-0"><?php _e('Select how dates should be formatted throughout the site.', 'greek-multi-tool'); ?></p>
                
                <div class="mt-3">
                    <?php foreach ($greek_date_formats as $key => $label) : ?>
                        <div class="form-check">
                            <input type="radio" class="form-check-input" id="grmlt_date_format_<?php echo esc_attr($key); ?>" 
                                name="grmlt_date_format" value="<?php echo esc_attr($key); ?>"
                                <?php checked($date_format, $key); ?> />
                            <label class="form-check-label" for="grmlt_date_format_<?php echo esc_attr($key); ?>">
                                <?php echo esc_html($label); ?>
                            </label>
                        </div>
                    <?php endforeach; ?>
                    
                    <div id="grmlt_custom_date_format_container" class="mt-2 <?php echo ($date_format === 'custom' ? '' : 'd-none'); ?>">
                        <input type="text" class="form-control" id="grmlt_custom_date_format" name="grmlt_custom_date_format" 
                            value="<?php echo esc_attr($custom_date_format); ?>" 
                            placeholder="<?php _e('e.g., j F Y', 'greek-multi-tool'); ?>" />
                        <small class="form-text text-muted">
                            <?php _e('Use PHP date format. For example, "j F Y" outputs "1 Ιανουαρίου 2023".', 'greek-multi-tool'); ?>
                            <a href="https://www.php.net/manual/en/datetime.format.php" target="_blank"><?php _e('Reference', 'greek-multi-tool'); ?></a>
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    $('input[name="grmlt_date_format"]').on('change', function() {
        if ($(this).val() === 'custom') {
            $('#grmlt_custom_date_format_container').removeClass('d-none');
        } else {
            $('#grmlt_custom_date_format_container').addClass('d-none');
        }
    });
});
</script>