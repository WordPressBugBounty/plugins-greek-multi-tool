<?php

function grmlt_old_sanitizer($text) {
    $expressions = array(

        '/[αάΑΆ]/u'   => 'a',
        '/[βΒ]/u'     => 'v',
        '/[γΓ]/u'     => 'g',
        '/[δΔ]/u'     => 'd',
        '/[εέΕΈ]/u'   => 'e',
        '/[ζΖ]/u'     => 'z',
        '/[ηήΗΉ]/u'   => 'i',
        '/[θΘ]/u'     => 'th',
        '/[ιίϊΙΊΪ]/u' => 'i',
        '/[κΚ]/u'     => 'k',
        '/[λΛ]/u'     => 'l',
        '/[μΜ]/u'     => 'm',
        '/[νΝ]/u'     => 'n',
        '/[ξΞ]/u'     => 'x',
        '/[οόΟΌ]/u'   => 'o',
        '/[πΠ]/u'     => 'p',
        '/[ρΡ]/u'     => 'r',
        '/[σςΣ]/u'    => 's',
        '/[τΤ]/u'     => 't',
        '/[υύϋΥΎΫ]/u' => 'y',
        '/[φΦ]/iu'    => 'f',
        '/[χΧ]/u'     => 'ch',
        '/[ψΨ]/u'     => 'ps',
        '/[ωώ]/iu'    => 'o',
        '/[ ]/'       => '-',
        '/[--]/'      => '-',
        '/[.]/'       => '',

    );

    // Stop Words functionality
    $stop_words = get_option( 'grmlt_stwords' );
    if ( isset( $stop_words ) &&  $stop_words != '') {
        $stop_words = explode( ',' , $stop_words );
        $text = str_replace( $stop_words, '', $text );
    }

    $text = preg_replace( array_keys($expressions), array_values($expressions), $text );

    // Remove one letter words
    if ( get_option( 'grmlt_one_letter_words' ) == 1 ) {
        $text = preg_replace('/\s+\D{1}(?!\S)|(?<!\S)\D{1}\s+/', '', $text);
    }

    // Remove two letter words
    if ( get_option( 'grmlt_two_letter_words' ) == 1 ) {
        $text = preg_replace('/\s+\D{2}(?!\S)|(?<!\S)\D{2}\s+/', '', $text);
    }

    return $text;
        
}

/**
 * Translate all old permalinks.
 */
function grmlt_trans_old_call() {
    // Check if the user has administrative permissions
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.', 'greek-multi-tool'));
    }
    
    // Verify nonce if called via POST (not included in this version but should be added)
    
    global $wpdb;

    // Get posts with non-Latin characters in post_name
    $posts = $wpdb->get_results("SELECT ID, post_name, post_title FROM {$wpdb->posts} WHERE post_name REGEXP('[^A-Za-z0-9\-]+') AND post_status IN ('publish', 'future', 'private')");
    
    foreach ((array) $posts as $post) {
        $sanitized_name_var = grmlt_old_sanitizer($post->post_title);
        $sanitized_name = preg_replace("/[^A-Za-z0-9-]+/", "", $sanitized_name_var);
        
        if ($post->post_name != $sanitized_name) {
            // Sanitize the post data before database operation
            $wpdb->update(
                $wpdb->posts, 
                array('post_name' => $sanitized_name), 
                array('ID' => absint($post->ID))
            );
            clean_post_cache(absint($post->ID));

            // Check if redirect option is ON/OFF.
            $red_option = get_option('grmlt_redirect');
            if ($red_option == 1) {
                // Prepare old permalink - sanitize
                $old_permalink = $post->post_title;
                $old_permalink = mb_strtolower("$old_permalink");
                $old_permalink = str_replace(" ", "-", $old_permalink);

                // Build full URLs with proper escaping
                $old_permalink = esc_url_raw(get_site_url() . "/" . $old_permalink . "/");
                $new_permalink = esc_url_raw(get_site_url() . "/" . $sanitized_name . "/");
                
                // Insert into database with proper sanitization
                $wpdb->insert(
                    $wpdb->prefix . 'grmlt', 
                    array(
                        'post_id' => absint($post->ID),
                        'redirect_type' => '301',
                        'old_permalink' => $old_permalink,
                        'new_permalink' => $new_permalink,
                    )
                );
            }
        }
    }
    
    // Fix term slugs too
    $terms = $wpdb->get_results("SELECT term_id, slug FROM {$wpdb->terms} WHERE slug REGEXP('[^A-Za-z0-9\-]+') ");
    foreach ((array) $terms as $term) {
        $sanitized_slug = grmlt_old_sanitizer(urldecode($term->slug));
        if ($term->slug != $sanitized_slug) {
            // Sanitize term data before database operation
            $wpdb->update(
                $wpdb->terms, 
                array('slug' => $sanitized_slug), 
                array('term_id' => absint($term->term_id))
            );
        }
    }
}

// Check user permissions before execution - for direct access case
if (isset($_POST['oldpermalinks']) && current_user_can('manage_options')) {
    grmlt_trans_old_call();
}