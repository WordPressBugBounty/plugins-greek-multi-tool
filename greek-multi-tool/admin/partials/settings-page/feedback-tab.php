<?php
/**
 * Feedback Tab UI
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
?>

<h6><?php _e('FEEDBACK & SUGGESTIONS', 'greek-multi-tool'); ?></h6>
<hr>
<strong class="mb-0"><?php _e('Help Us Improve Greek Multi-Tool', 'greek-multi-tool'); ?></strong>
<p><?php _e('We value your feedback and suggestions for enhancing our tools for Greek websites.', 'greek-multi-tool'); ?></p>

<div class="list-group mb-5 shadow">
    <div class="list-group-item">
        <div class="row align-items-center">
            <div class="col">
                <h5><?php _e('We\'d Love to Hear From You!', 'greek-multi-tool'); ?></h5>
                <p><?php _e('Have ideas for new features? Found a bug? Have suggestions for improving existing tools? Let us know!', 'greek-multi-tool'); ?></p>
                
                <div class="feedback-options">
                    <div class="card mb-4">
                        <div class="card-body">
                            <h6><?php _e('Contact Options', 'greek-multi-tool'); ?></h6>
                            <div class="mt-3">
                                <a href="https://bigdrop.gr/contact-us/" target="_blank" class="button button-primary"><?php _e('Contact Us via Our Website', 'greek-multi-tool'); ?></a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>