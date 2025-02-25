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
    global $wpdb;

    $posts = $wpdb->get_results("SELECT ID, post_name, post_title FROM {$wpdb->posts} WHERE post_name REGEXP('[^A-Za-z0-9\-]+') AND post_status IN ('publish', 'future', 'private')");
    foreach ( (array) $posts as $post ) {
        $sanitized_name_var = grmlt_old_sanitizer($post->post_title);
        $sanitized_name = preg_replace("/[^A-Za-z0-9-]+/","",$sanitized_name_var);
        if ( $post->post_name != $sanitized_name ) {
            $wpdb->update($wpdb->posts, array( 'post_name' => $sanitized_name ), array( 'ID' => $post->ID ));
            clean_post_cache($post->ID );

            // Check if redirect option is ON/OFF.
            $red_option = get_option( 'grmlt_redirect' );
            if ( $red_option == 1 ){
            	// Convertion for old_permalink variable values.
	            $old_permalink = $post->post_title;
	            $old_permalink = mb_strtolower("$old_permalink");
	           	$old_permalink = str_replace(" ", "-", $old_permalink);

	           	$old_permalink = get_site_url()."/".$old_permalink."/";
				$new_permalink = get_site_url()."/".$sanitized_name."/";
	            $wpdb->insert($wpdb->prefix.'grmlt', array(
	            	'post_id' => "$post->ID",
				    'old_permalink' => "$old_permalink",
				    'new_permalink' => "$new_permalink",
				));
            }
        }
    }
    $terms = $wpdb->get_results("SELECT term_id, slug FROM {$wpdb->terms} WHERE slug REGEXP('[^A-Za-z0-9\-]+') ");
    foreach ( (array) $terms as $term ) {
        $sanitized_slug = grmlt_old_sanitizer(urldecode($term->slug));
        if ( $term->slug != $sanitized_slug ) {
            $wpdb->update($wpdb->terms, array( 'slug' => $sanitized_slug ), array( 'term_id' => $term->term_id ));
        }
    }
}

grmlt_trans_old_call();