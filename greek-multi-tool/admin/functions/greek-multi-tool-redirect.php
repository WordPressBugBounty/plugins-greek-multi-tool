<?php
/**
 * Include this function to WordPress `init` to create a bridge of URL identification for 301 Redirections.
 */

function grmlt_redirect() {
    global $wpdb;

    // Get current URL with proper validation
    $url = esc_url_raw((isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] === 'on' || $_SERVER['HTTPS'] === '1') ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
    
    // Prepare the table name safely
    $table = $wpdb->prefix . "grmlt";
    
    // Use prepared statement to prevent SQL injection
    $result = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT `old_permalink`, `new_permalink` FROM %i WHERE 1",
            $table
        )
    );
    
    // If query fails, return early
    if (is_wp_error($result)) {
        return;
    }
    
    foreach ($result as $permalinks) {
        $old_permalink = $permalinks->old_permalink;
        $new_permalink = $permalinks->new_permalink;
        
        // Validate both URLs
        if (!empty($old_permalink) && !empty($new_permalink) && $old_permalink === $url) {
            // Do 301 redirect
            header('HTTP/1.1 301 Moved Permanently');
            header("Location: " . esc_url_raw($new_permalink));
            exit();
        }
    }
}