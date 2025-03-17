<?php
/**
 * Text Analysis Tab UI
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
$text_analysis_enabled = get_option('grmlt_enable_text_analysis', '0');
?>

<h6><?php _e('TEXT ANALYSIS SETTINGS', 'greek-multi-tool'); ?></h6>
<hr>
<strong class="mb-0"><?php _e('Greek Accent Rules Analysis', 'greek-multi-tool'); ?></strong>
<p><?php _e('Analyze your content for proper Greek accent rule usage', 'greek-multi-tool'); ?></p>

<div class="list-group mb-5 shadow">
    <div class="list-group-item">
        <div class="row align-items-center">
            <div class="col">
                <strong class="mb-0"><?php _e('Enable Greek Text Analysis:', 'greek-multi-tool'); ?></strong>
                <p class="text-muted mb-0"><?php _e('Adds a metabox to post and page editors to check Greek text for accent rule compliance.', 'greek-multi-tool'); ?></p>
            </div>
            <div class="col-auto">
                <div class="custom-control custom-switch">
                    <label class="switch">
                        <input type="checkbox" id="grmlt_enable_text_analysis" name="grmlt_enable_text_analysis" 
                            value="1" <?php checked($text_analysis_enabled, '1'); ?> />
                        <span class="slider round"></span>
                    </label>
                </div>
            </div>
        </div>
    </div>
    
    <div class="list-group-item">
        <div class="row">
            <div class="col">
                <strong class="mb-0"><?php _e('Accent Rules Checked:', 'greek-multi-tool'); ?></strong>
                <p class="text-muted mb-0"><?php _e('This feature adds a metabox to your post and page editor that analyzes Greek text for accent rule compliance.', 'greek-multi-tool'); ?></p>
                <ul class="mt-2">
                    <li><?php _e('All words with two or more syllables must have an accent', 'greek-multi-tool'); ?></li>
                    <li><?php _e('Words that appear monosyllabic after elision or apocope keep their accent', 'greek-multi-tool'); ?></li>
                    <li><?php _e('Words that have lost their accent through aphaeresis are exceptions', 'greek-multi-tool'); ?></li>
                    <li><?php _e('Monosyllabic words generally do not take accents, with specific exceptions', 'greek-multi-tool'); ?></li>
                    <li><?php _e('Common Greek words like "Όλα", "Ένα", "Όταν" are recognized as correctly accented', 'greek-multi-tool'); ?></li>
                    <li><?php _e('Certain words like "μου", "σου", "του", "το", "τα", "για", "πιο", "ναι", "και", "οι", etc. never take accents', 'greek-multi-tool'); ?></li>
                    <li><?php _e('The disjunctive conjunction "ή" always takes an accent', 'greek-multi-tool'); ?></li>
                    <li><?php _e('Interrogative "πού" and "πώς" always take accents in questions', 'greek-multi-tool'); ?></li>
                    <li><?php _e('Special cases for "πού" and "πώς" in specific phrases', 'greek-multi-tool'); ?></li>
                    <li><?php _e('All-caps words should not have accents (except for "Ή")', 'greek-multi-tool'); ?></li>
                </ul>
                <p class="text-muted"><?php _e('The text analysis metabox will appear in the sidebar of your post and page editors when the feature is enabled.', 'greek-multi-tool'); ?></p>
            </div>
        </div>
    </div>
</div>