<!-- UPPERCASE ACCENT REMOVER SETTINGS -->
<?php 
    $grmlt_uar = get_option( 'grmlt_uar_js' ); 
    if ( $grmlt_uar == 'on' ){
        $grmlt_uar = true;
    }
?>

<h6><?php _e('UPPERCASE ACCENT REMOVER SETTINGS', 'greek-multi-tool'); ?></h6>
<hr>
<strong class="mb-0"><?php _e('Remove Uppercase Accents', 'greek-multi-tool'); ?></strong>
<p><?php _e('Enable the uppercase accent remover sitewide', 'greek-multi-tool'); ?></p>
<div class="list-group mb-5 shadow">
    <div class="list-group-item">
        <div class="row align-items-center">
            <div class="col">
                <strong class="mb-0"><?php _e('Enable Uppercase Accents Remover: ', 'greek-multi-tool'); ?></strong>
                <p class="text-muted mb-0"><?php _e('Automatically remove accented characters from elements having their text content uppercase transformed through CSS.', 'greek-multi-tool'); ?></p>
            </div>
            <div class="col-auto">
                <div class="custom-control custom-switch">
                    <label class="switch">
						<input type="checkbox" class="custom-control-input" id="grmlt_uar_js" name="grmlt_uar_js" <?php echo checked( $grmlt_uar, 1, 0 ); ?> />
						<span class="slider round"></span>
					</label>
                </div>
            </div>
        </div>
    </div>
</div>