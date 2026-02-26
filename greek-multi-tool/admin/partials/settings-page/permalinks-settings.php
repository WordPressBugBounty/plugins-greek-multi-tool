<!-- GREEKLISH GLOBALCONVERTOR -->
<?php 
    // This variable is used as a bucket which is set to the value of the option named 'grmlt_text' which is simply the output of a checkbox.
    $grmlt_new_permalink_convert = get_option( 'grmlt_text' );
    if ( $grmlt_new_permalink_convert == 'on' ){
        $grmlt_new_permalink_convert = true;
    }
?>

<h6><?php _e('PERMALINK SETTINGS', 'greek-multi-tool'); ?></h6>
<hr>
<strong class="mb-0"><?php _e('Convert the permalinks', 'greek-multi-tool'); ?></strong>
<p><?php _e('Enable the permalinks convert sitewide', 'greek-multi-tool') ?></p>
<div class="list-group mb-5 shadow">
    <div class="list-group-item">
        <div class="row align-items-center">
            <div class="col">
                <strong class="mb-0"><?php _e('Enable Greeklish Permalinks Convert: ', 'greek-multi-tool') ?></strong>
                <p class="text-muted mb-0"><?php _e('Automatically convert the greek characters to latin in all permalinks in posts, pages, custom post types, media attachments and terms. Fully compatible with ACF (Advanced Custom Fields).', 'greek-multi-tool') ?></p>
            </div>
            <div class="col-auto">
                <div class="custom-control custom-switch">
                    <label class="switch">
                        <input type="checkbox" class="custom-control-input" id="alert1 grmlt_text" name="grmlt_text" <?php echo checked( $grmlt_new_permalink_convert, 1, 0 ); ?> />
                        <span class="slider round"></span>
                    </label>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- DITHPTHONGS SETTINGS -->
<?php 
    // This variable is used as a bucket which is set to the value of the option named 'grmlt_text' which is simply the output of a checkbox.
    $grmlt_dipthongs = get_option( 'grmlt_diphthongs' );
    $grmlt_dipthongs_simple = '';
    $grmlt_dipthongs_advanced = '';
    if ( $grmlt_dipthongs == 'advanced' ){
        $grmlt_dipthongs_advanced = true;
    } else {
        $grmlt_dipthongs_simple = true;
    }
?>

<hr class="my-4" />
<strong class="mb-0"><?php _e('Diphthongs Settings', 'greek-multi-tool'); ?></strong>
<p><?php _e('Select how you want the dipthongs to be converted', 'greek-multi-tool'); ?></p>
<div class="list-group mb-5 shadow">
    <div class="list-group-item">
        <div class="row align-items-center">
            <div class="col">
                <strong class="mb-0"><?php _e('Simple Conversion', 'greek-multi-tool'); ?></strong>
                <p class="text-muted mb-0"><?php _e('For example "ει" becomes "ei", "οι" becomes "οi", "μπ" becomes "mp" etc', 'greek-multi-tool'); ?></p>
            </div>
            <div class="col-auto">
                <div class="custom-control custom-switch">
                    <input type="radio" id="grmlt_diphthongs_s" name="grmlt_diphthongs" value="simple" <?php echo checked( $grmlt_dipthongs_simple, 1, 0 ); ?> />
                </div>
            </div>
        </div>
    </div>
    <div class="list-group-item">
        <div class="row align-items-center">
            <div class="col">
                <strong class="mb-0"><?php _e('Advance  Conversion', 'greek-multi-tool'); ?></strong>
                <p class="text-muted mb-0"><?php _e('For example "ει", "οι" becomes "i", "μπ" becomes "b" etc', 'greek-multi-tool'); ?></p>
            </div>
            <div class="col-auto">
            <div class="custom-control custom-switch">
                <input type="radio" id="grmlt_diphthongs_a" name="grmlt_diphthongs" value="advanced" <?php echo checked( $grmlt_dipthongs_advanced, 1, 0 ); ?> />
                </div>
            </div>
        </div>
    </div>
</div>

<!-- REMOVE ONE/TWO LETTER WORDS -->
<hr class="my-4" />
<strong class="mb-0"><?php _e('Remove One/Two Letter Words', 'greek-multi-tool'); ?></strong>
<p><?php _e('Select which of the following word options you want to remove from the posts urls', 'greek-multi-tool'); ?></p>
<div class="list-group mb-5 shadow">

<!-- ONE LETTER OPTION -->
<?php
    $one_letter_options = get_option( 'grmlt_one_letter_words' );
    $oneletter = esc_attr( $one_letter_options );
?>

<div class="list-group-item">
    <div class="row align-items-center">
        <div class="col">
            <strong class="mb-0"><?php _e('Remove One Letter Words', 'greek-multi-tool'); ?></strong>
        </div>
        <div class="col-auto">
            <div class="custom-control custom-switch">
            <input type="checkbox" name="grmlt_one_letter_words" id="grmlt_one_letter_words" value="1"
           <?php echo checked( $oneletter, 1, 0 ) ?>/>
            </div>
        </div>
    </div>
</div>

<!-- TWO LETTER OPTION -->
<?php
    $two_letter_options = get_option( 'grmlt_two_letter_words' );
    $twoletter = esc_attr( $two_letter_options );
?>

<div class="list-group-item">
    <div class="row align-items-center">
        <div class="col">
            <strong class="mb-0"><?php _e('Remove Two Letter Words', 'greek-multi-tool'); ?></strong>
        </div>
        <div class="col-auto">
            <div class="custom-control custom-switch">
                <input type="checkbox" name="grmlt_two_letter_words" id="grmlt_two_letter_words" value="1"
                <?php echo checked( $twoletter, 1, 0 ) ?>/>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MEDIA FILE NAME CONVERSION -->
<?php
    $grmlt_media_file_name = get_option( 'grmlt_media_file_name' );
    if ( $grmlt_media_file_name == 'on' ){
        $grmlt_media_file_name = true;
    }
?>

<hr class="my-4" />
<strong class="mb-0"><?php _e('Media File Name Conversion', 'greek-multi-tool'); ?></strong>
<p><?php _e('Convert Greek characters in media file names to Latin during upload', 'greek-multi-tool'); ?></p>
<div class="list-group mb-5 shadow">
    <div class="list-group-item">
        <div class="row align-items-center">
            <div class="col">
                <strong class="mb-0"><?php _e('Enable Media File Name Conversion: ', 'greek-multi-tool'); ?></strong>
                <p class="text-muted mb-0"><?php _e('Automatically convert Greek characters in uploaded media file names (images, documents, etc.) to clean, SEO-friendly Latin equivalents. For example "φωτογραφία.jpg" becomes "fotografia.jpg".', 'greek-multi-tool'); ?></p>
            </div>
            <div class="col-auto">
                <div class="custom-control custom-switch">
                    <label class="switch">
                        <input type="checkbox" class="custom-control-input" id="grmlt_media_file_name" name="grmlt_media_file_name" <?php echo checked( $grmlt_media_file_name, 1, 0 ); ?> />
                        <span class="slider round"></span>
                    </label>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- STOPWORDS START -->

<?php
    $textarea_stwords = get_option( 'grmlt_stwords' );
?>

<hr class="my-4" />
<strong class="mb-0"><?php _e('Exclude Stopwords From Permalinks', 'greek-multi-tool'); ?></strong>
<p><?php _e('Type the words you want to exclude from permalinks seperated by a comma!', 'greek-multi-tool'); ?></p>
<div class="list-group mb-5 shadow">  
    <textarea name="grmlt_stwords" id="grmlt_stwords" cols="60" rows="4">
        <?php echo esc_textarea( $textarea_stwords ); ?>
    </textarea>
</div>