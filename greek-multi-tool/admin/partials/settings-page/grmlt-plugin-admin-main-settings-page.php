<?php 
/**
 * Create the plugin's main settings page
 *
 *
 * @link       https://bigdrop.gr
 * @since      1.0.0
 *
 * @package    Grmlt_Plugin
 * @subpackage Grmlt_Plugin/admin/partials/settings-page
 */

/**
 * Here we create all the settings required for the plugin
 * 
 * @since 1.0.0
 */

// It registers settings for Greeklish Permalinks Converter.
register_setting(
    'grmlt_settings', // settings group name
    'grmlt_text', // option name
    'sanitize_text_field' // sanitization function
);

// It registers settings for Greeklish Permalink Dipthongs Converter.
register_setting(
    'grmlt_settings', // settings group name
    'grmlt_diphthongs', // option name
    'sanitize_text_field' // sanitization function
);

// It registers settings for Remove Uppercase Accents.
register_setting(
    'grmlt_settings', // settings group name
    'grmlt_uar_js', // option name
    'sanitize_text_field' // sanitization function
);

// It registers settings for One/Two letter words removal.
register_setting(
    'grmlt_settings', // settings group name
    'grmlt_one_letter_words', // option name
    'sanitize_text_field' // sanitization function
);

// It registers settings for One/Two letter words removal.
register_setting(
    'grmlt_settings', // settings group name
    'grmlt_two_letter_words', // option name
    'sanitize_text_field' // sanitization function
);

// It registers settings for 301 redirects.
register_setting(
    'grmlt_settings', // settings group name
    'grmlt_redirect', // option name
    'sanitize_text_field' // sanitization function
);

// It registers settings for Stopwords.
register_setting(
    'grmlt_settings', // settings group name
    'grmlt_stwords', // option name
    'sanitize_text_field' // sanitization function
);

// Settings for enhanced search
register_setting(
    'grmlt_settings',
    'grmlt_enhance_search',
    'sanitize_text_field'
);

register_setting(
    'grmlt_settings', 
    'grmlt_search_post_types', 
    array(
        'type' => 'array',
        'sanitize_callback' => function($post_types) {
            if (!is_array($post_types)) {
                return array('post', 'page');
            }
            return array_map('sanitize_text_field', $post_types);
        },
        'default' => array('post', 'page'),
    )
);

// Settings for date localization
register_setting(
    'grmlt_settings',
    'grmlt_localize_dates',
    'sanitize_text_field'
);

register_setting(
    'grmlt_settings',
    'grmlt_date_format',
    'sanitize_text_field'
);

register_setting(
    'grmlt_settings',
    'grmlt_custom_date_format',
    'sanitize_text_field'
);

add_settings_section(
    'grmlt-settings-section', // section ID
    '', // title (if needed)
    '', // callback function (if needed)
    'grmlt-main-settings' // page slug
);

add_settings_field(
    'grmlt_text',
    '',
    'grmlt_text_field_html', // function which prints the field
    'grmlt-main-settings', // page slug
    'grmlt-settings-section', // section ID
    array( 
        'label_for' => 'grmlt_text',
        'class' => 'gpc-class', // for <tr> element
    ),
);

function grmlt_main_page() {

    echo "<div class='container-fluid'>";
    echo "<form method='post' action='options.php'>";

        // Settings fields used in the settings-page.
        settings_fields( 'grmlt_settings' ); // settings group name
        do_settings_sections( 'grmlt-main-settings' ); // just a page slug
    echo '</form></div>';
}

function grmlt_text_field_html() {
    ?>
    <!-- Breadcrumb -->
    <div class="mb-3">
        <img srcset="<?php echo plugins_url( 'images/icon-256x256.png 2x', dirname(__DIR__)); ?>" src="<?php echo plugins_url( 'images/icon-128x128.png', dirname(__DIR__)); ?>" class="img-responsive grmlt-logo" style="width: 32px;height: 32px;vertical-align: sub;" alt="Greek Multi Tool Logo">
        <span class="grmlt-title"><?php _e('Greek Multi Tool Settings Page', 'greek-multi-tool'); ?></span>    
    </div>
    <!-- /Breadcrumb -->
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-sm-7 float-start order-2 order-md-1">
                                <img class="d-none d-md-inline-block" src="<?php echo plugins_url( 'images/grmlt-wave.png', dirname(__DIR__)); ?>" style="width: 32px;height: 32px;vertical-align: sub;" alt="Welcome waving hand">
                                <h1 class="p-0 welcotitle">
                                    <?php _e("Welcome, ". wp_get_current_user()->nickname ."!", 'greek-multi-tool'); ?>
                                </h1>
                                <p class="wpdt-text wpdt-font"> <?php _e( 'Congratulations! You are about to use the most powerful WordPress plugin for Greek Language Users -  Greek Multi Tool is designed to make the process of using the Greek Language in WordPress as ease as possible.', 'greek-multi-tool'); ?></p>
                                <a href="https://wordpress.org/support/plugin/greek-multi-tool/" class="btn btn-primary mt-4">
                                    <?php _e( 'Support Forum', 'greek-multi-tool'); ?></a>
                                <a href="https://wordpress.org/support/plugin/greek-multi-tool/reviews/#new-post" class="btn btn-outline-primary mt-4 ms-2">
                                        <?php _e( 'Review Plugin', 'greek-multi-tool'); ?></a>
                            </div>
                            <div class="col-sm-5 float-end order-1 order-md-2">
                                <img src="<?php echo plugins_url( 'images/grmlt-welcome.png', dirname(__DIR__)); ?>" class="img-fluid float-end wdt-welcome-img" alt="Welcome message">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="row">
            <div id="sidebar" class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <ul class="nav nav-pills flex-column">
                            <?php
                            // Define original core tabs - these have direct file includes
                            $core_tabs = array(
                                'permalinks' => __('Permalinks Settings', 'greek-multi-tool'),
                                'uppercaseaccents' => __('Uppercase Accent Remover Settings', 'greek-multi-tool'),
                                'oldpermalinks' => __('Manage Old Permalinks', 'greek-multi-tool'),
                                'grmlt_redirect' => __('301 Redirect Settings', 'greek-multi-tool'),
                                'menu_builder' => __('Menu Builder', 'greek-multi-tool')
                            );
                            
                            // Allow other features to add tabs
                            $tabs = apply_filters('grmlt_settings_tabs', $core_tabs);
                            
                            // Output tabs
                            $first_tab = true;
                            foreach ($tabs as $tab_id => $tab_name) :
                            ?>
                                <li class="nav-item">
                                    <a href="#<?php echo esc_attr($tab_id); ?>" data-toggle="tab" 
                                       class="fs-5 nav-link nav-link-faded<?php echo ($first_tab ? ' active' : ''); ?>">
                                        <?php echo esc_html($tab_name); ?>
                                    </a>
                                </li>
                            <?php
                                if ($first_tab) $first_tab = false;
                            endforeach;
                            ?>
                        </ul>
                    </div>
                </div>
            </div> <!-- /SIDEBAR -->
            <div id="cards" class="col-md-9">
                <div class="card">
                    <div class="card-body tab-content">
                        <!-- PERMALINKS SETTINGS OPTION -->
                        <div class="tab-pane active" id="permalinks">
                            <?php require_once plugin_dir_path( dirname( __FILE__ ) ) . 'settings-page/permalinks-settings.php'; ?>
                        </div>

                        <!-- UPPERCASE ACCENTS REMOVER SETTINGS -->
                        <div class="tab-pane" id="uppercaseaccents">
                            <?php require_once plugin_dir_path( dirname( __FILE__ ) ) . 'settings-page/uppercase-accent-remover-settings.php'; ?>
                        </div>

                        <!-- CONVERT OLD PERMALINKS -->
                        <div class="tab-pane" id="oldpermalinks">
                            <?php require_once plugin_dir_path( dirname( __FILE__ ) ) . 'settings-page/convert-old-permalinks.php'; ?>
                        </div>

                        <!-- 301 REDIRECTS -->
                        <div class="tab-pane" id="grmlt_redirect">
                            <?php require_once plugin_dir_path( dirname( __FILE__ ) ) . 'settings-page/301-redirect.php'; ?>
                        </div>

                        <!-- Woocommerce Menu Builder -->
                        <div class="tab-pane" id="menu_builder">
                            <?php require_once plugin_dir_path( dirname( __FILE__ ) ) . 'settings-page/menu-builder.php'; ?>
                        </div>
                        
                        <!-- Dynamic tabs from other features -->
                        <?php foreach ($tabs as $tab_id => $tab_name) : ?>
                            <?php if (!array_key_exists($tab_id, $core_tabs)) : ?>
                                <div class="tab-pane" id="<?php echo esc_attr($tab_id); ?>">
                                    <?php do_action('grmlt_settings_tab_' . $tab_id); ?>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div> <!-- /CARDS -->
        </div> <!-- /ROW -->
        <?php submit_button( __("Save Settings", 'greek-multi-tool'), "btn btn-primary float-end mt-5", '', false); ?>
    </div> <!-- /CONTAINER -->
    <?php
    // Remove default elements of wordpress on settings page initialization.
    echo '<script>
            var selected_item = document.querySelector(".gpc-class th");
            selected_item.remove();

            jQuery( document ).ready(function() {
                jQuery( ".btn-primary" ).removeClass( "button" );
            });
        </script>';
    echo '<script>
    jQuery(document).ready(function($) {
        // Store original tab click handler
        var originalTabClick = $(".nav-link").attr("onclick");
        
        // Override tab behavior to use our custom approach
        $(".nav-link").on("click", function(e) {
            e.preventDefault();
            
            // Get the tab ID
            var tabId = $(this).attr("href").substring(1);
            
            // Hide all tab panes
            $(".tab-pane").removeClass("active").css("display", "none");
            
            // Show the selected tab pane
            $("#" + tabId).addClass("active").css("display", "block");
            
            // Remove active class from all tabs
            $(".nav-link").removeClass("active");
            
            // Add active class to clicked tab
            $(this).addClass("active");
            
            // Call original handler if it exists
            if (originalTabClick) {
                eval(originalTabClick);
            }
            
            return false;
        });
        
        // When the form is submitted, make all form fields visible
        $("form").on("submit", function() {
            // Show all tab panes before submission to ensure all fields are included
            $(".tab-pane").css({"visibility": "hidden", "display": "block", "height": "0", "overflow": "hidden", "position": "absolute"});
            
            // After a short delay to allow the form to be processed, restore the tabs
            setTimeout(function() {
                // Hide non-active tab panes
                $(".tab-pane:not(.active)").css({"display": "none", "visibility": "visible", "height": "auto", "overflow": "visible", "position": "static"});
                
                // Show active tab pane properly
                $(".tab-pane.active").css({"visibility": "visible", "height": "auto", "overflow": "visible", "position": "static"});
            }, 100);
        });
    });
</script>';
}