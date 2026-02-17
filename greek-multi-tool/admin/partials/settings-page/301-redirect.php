<!-- 301 REDIRECTS -->
<?php
	$grmlt_redirect = get_option( 'grmlt_redirect' );
	$grmlt_redirect = esc_attr( $grmlt_redirect );
?>

<h6><?php esc_html_e( '301 REDIRECT SETTINGS', 'greek-multi-tool' ); ?></h6>
<hr>
<div class="list-group-item">
		<div class="row align-items-center">
    	<div class="col">
	    	<strong class="mb-0"><?php esc_html_e( 'Enable Automatic 301 Redirect', 'greek-multi-tool' ); ?></strong>
	    	<p><?php esc_html_e( 'Enabling the automatic 301 redirect option setting will create dynamic redirects for every old permalink being converted via the `Convert Old Permalinks` functionality.', 'greek-multi-tool' ); ?></p>
	    </div>
	    <div class="col-auto">
	        <div class="custom-control custom-switch">
	            <label class="switch">
	            	<input type="checkbox" name="grmlt_redirect" id="grmlt_redirect" value="1"
			    	<?php checked( $grmlt_redirect, 1 ); ?>/>
				  	<span class="slider round"></span>
				</label>
	        </div>
	    </div>
	</div>
</div>
