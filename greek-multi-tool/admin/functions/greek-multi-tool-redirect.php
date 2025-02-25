<?php
/**
 * Include this function to wordpress `init` to create a bridge of URL identification for 301 Redirections.
 */

function grmlt_redirect() {

	global $wpdb;

	$url = (isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] === 'on' || $_SERVER['HTTPS'] === '1') ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
	$table = $wpdb->prefix."grmlt";
	$result = $wpdb->get_results ( "SELECT `old_permalink`,`new_permalink` FROM $table WHERE 1" );
	foreach ( $result as $permalinks )
	{
		$old_permalink = $permalinks->old_permalink;
	   	$new_permalink = $permalinks->new_permalink;
		if ( $old_permalink == $url ){
			header ('HTTP/1.1 301 Moved Permanently');
			header ("Location: $new_permalink");
			exit();
		}
	}
}