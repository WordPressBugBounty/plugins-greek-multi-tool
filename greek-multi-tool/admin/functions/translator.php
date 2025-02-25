<?php 
function grmlt_title_sanitizer($text) {
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