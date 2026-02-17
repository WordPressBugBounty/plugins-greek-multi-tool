<!-- CONVERT OLD PERMALINKS -->

<h6><?php esc_html_e( 'MANAGE OLD PERMALINKS', 'greek-multi-tool' ); ?></h6>
<hr>
<strong class="mb-0"><?php esc_html_e( 'Convert All Old Permalinks', 'greek-multi-tool' ); ?></strong>
<p><?php esc_html_e( 'Press the button bellow to initialize the conversion of all old permalinks', 'greek-multi-tool' ); ?></p>
<div class="mt-3">
	<form method="post">
		<?php wp_nonce_field( 'grmlt_convert_old_permalinks', 'grmlt_convert_nonce' ); ?>
		<input class="btn btn-warning text-bold" type="submit" name="oldpermalinks" id="oldpermalinks" value="<?php esc_attr_e( 'CONVERT', 'greek-multi-tool' ); ?>" /><br/>
	</form>
</div>
<hr>
<strong class="mb-0"><?php esc_html_e( 'List of old permalinks', 'greek-multi-tool' ); ?></strong>
<p><?php esc_html_e( 'In the list below you can view/manage the old converted permalinks', 'greek-multi-tool' ); ?></p>
<div class="mt-3">
    <!-- Start of Listing ROWS -->
    <div class="list-group">
    <?php

    // Call WPDB Global variable.
    global $wpdb;

    $table_name = $wpdb->prefix . 'grmlt';

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $results = $wpdb->get_results(
        "SELECT * FROM `{$table_name}` WHERE 1",
        OBJECT
    );

    // Check if there are any results
    if ( $results ) {
        // Loop through the results and display the data
        foreach ( $results as $result ) {
            $pid = absint( $result->permalink_id );
            $post_id = absint( $result->post_id );
            $old_link = esc_url( $result->old_permalink );
            $new_link = esc_url( $result->new_permalink );
            ?>
            <!-- Actual LIST BLOCK DESKTOP-->
            <div class="d-lg-block d-none">
                <div class="list-group-item">
                    <div class="row align-items-center justify-content-between">
                        <div class="col-11">
                            <div class="row">
                                <div class="col-6">
                                    <div class="d-flex align-items-start">
                                        <strong><?php esc_html_e( 'ID:', 'greek-multi-tool' ); ?></strong>
                                        <span><?php echo esc_html( $pid ); ?></span>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="d-flex align-items-start">
                                        <strong><?php esc_html_e( 'Post ID:', 'greek-multi-tool' ); ?></strong>
                                        <span><?php echo esc_html( $post_id ); ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-6">
                                    <div class="d-flex align-items-start">
                                        <strong><?php esc_html_e( 'Old Permalink:', 'greek-multi-tool' ); ?></strong>
                                        <span class="text-break"><?php echo esc_html( $old_link ); ?></span>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="d-flex align-items-start">
                                        <strong><?php esc_html_e( 'New Permalink:', 'greek-multi-tool' ); ?></strong>
                                        <span class="text-break"><?php echo esc_html( $new_link ); ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-1">
                            <div class="row">
                                <a href="#" class="blue-square-edit" data-toggle="modal" data-target="#fullscreenModal<?php echo esc_attr( $pid ); ?>">
                                    <i class="fas fa-pencil-alt"></i>
                                </a>
                                <a href="#" class="red-square-delete mx-2" data-toggle="modal" data-target="#fullscreenModalDelete<?php echo esc_attr( $pid ); ?>">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- List BLOCK MOBILE -->
            <div class="d-lg-none">
                <a href="#" class="list-group-item list-group-item-action" data-toggle="collapse" data-target="#item<?php echo esc_attr( $pid ); ?>">
                    <div class="row">
                        <div class="col-6">
                            <div class="d-flex align-items-start">
                                <strong><?php esc_html_e( 'ID:', 'greek-multi-tool' ); ?></strong>
                                <span><?php echo esc_html( $pid ); ?></span>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="d-flex align-items-start">
                                <strong><?php esc_html_e( 'Post ID:', 'greek-multi-tool' ); ?></strong>
                                <span><?php echo esc_html( $post_id ); ?></span>
                            </div>
                        </div>
                    </div>
                </a>
                <div id="item<?php echo esc_attr( $pid ); ?>" class="collapse">
                    <div class="card card-body">
                        <div class="row">
                            <div class="d-flex flex-column align-items-start">
                                <strong><?php esc_html_e( 'Old Permalink:', 'greek-multi-tool' ); ?></strong>
                                <span class="text-break"><?php echo esc_html( $old_link ); ?></span>
                            </div>
                        </div>
                        <div class="row mt-2">
                            <div class="d-flex flex-column align-items-start">
                                <strong><?php esc_html_e( 'New Permalink:', 'greek-multi-tool' ); ?></strong>
                                <span class="text-break"><?php echo esc_html( $new_link ); ?></span>
                            </div>
                        </div>
                        <div class="row mt-2">
                            <div class="d-flex justify-content-center">
                                <a href="#" class="blue-square-edit mx-2" data-toggle="modal" data-target="#fullscreenModal<?php echo esc_attr( $pid ); ?>">
                                    <i class="fas fa-pencil-alt"></i>
                                </a>
                                <a href="#" class="red-square-delete mx-2" data-toggle="modal" data-target="#fullscreenModalDelete<?php echo esc_attr( $pid ); ?>">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- EDIT Record Popup -->
            <div class="modal fade" id="fullscreenModal<?php echo esc_attr( $pid ); ?>" tabindex="-1" role="dialog" aria-labelledby="fullscreenModalLabel<?php echo esc_attr( $pid ); ?>" aria-hidden="true">
                <div class="modal-dialog modal-fullscreen-lg-down modal-dialog-centered modal-dialog-scrollable modal">
                    <div class="modal-content d-flex align-items-center justify-content-center">
                        <div class="modal-header">
                            <h5 class="modal-title" id="fullscreenModalLabel<?php echo esc_attr( $pid ); ?>"><?php esc_html_e( 'Edit Redirection', 'greek-multi-tool' ); ?></h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="<?php esc_attr_e( 'Close', 'greek-multi-tool' ); ?>">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <div class="row mb-1">
                                <h6><?php esc_html_e( 'Make sure you are making the correct changes, as editing redirection permalinks while redirection is still active may result in broken URLs or Loops', 'greek-multi-tool' ); ?></h6>
                            </div>
                            <div class="row mb-2">
                                <div class="d-flex flex-column align-items-between">
                                    <span class="old-permalink-el">
                                        <?php esc_html_e( 'Old Permalink:', 'greek-multi-tool' ); ?>
                                        <input class="w-100" type="text" name="old-permalink" value="<?php echo esc_attr( $old_link ); ?>">
                                    </span>
                                    <span class="new-permalink-el">
                                        <?php esc_html_e( 'New Permalink:', 'greek-multi-tool' ); ?>
                                        <input class="w-100" type="text" name="new-permalink" value="<?php echo esc_attr( $new_link ); ?>">
                                    </span>
                                </div>
                            </div>
                            <div class="row">
                                <div class="d-flex align-items-center justify-content-start">
                                    <button type="button" class="btn btn-secondary mx-2" data-dismiss="modal" aria-label="<?php esc_attr_e( 'Close', 'greek-multi-tool' ); ?>"><?php esc_html_e( 'Cancel', 'greek-multi-tool' ); ?></button>
                                    <button data-post-id="<?php echo esc_attr( $pid ); ?>" type="button" class="btn btn-success mx-2 confirm-edit-button-grmlt-301"><?php esc_html_e( 'Save Changes', 'greek-multi-tool' ); ?></button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- DELETE Record Popup -->
            <div class="modal fade" id="fullscreenModalDelete<?php echo esc_attr( $pid ); ?>" tabindex="-1" role="dialog" aria-labelledby="fullscreenModalLabelDelete<?php echo esc_attr( $pid ); ?>" aria-hidden="true">
                <div class="modal-dialog modal-fullscreen-lg-down modal-dialog-centered modal-dialog-scrollable modal">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="fullscreenModalLabelDelete<?php echo esc_attr( $pid ); ?>"><?php esc_html_e( 'Delete Redirection', 'greek-multi-tool' ); ?></h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="<?php esc_attr_e( 'Close', 'greek-multi-tool' ); ?>">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <h6><?php esc_html_e( 'Are you sure you want to delete this redirect record?', 'greek-multi-tool' ); ?></h6>
                            <button type="button" class="btn btn-secondary m-auto" data-dismiss="modal" aria-label="<?php esc_attr_e( 'Close', 'greek-multi-tool' ); ?>"><?php esc_html_e( 'Cancel', 'greek-multi-tool' ); ?></button>
                            <button data-post-id="<?php echo esc_attr( $pid ); ?>" type="button" class="btn btn-danger m-auto confirm-deletion-button-grmlt-301"><?php esc_html_e( 'Delete', 'greek-multi-tool' ); ?></button>
                        </div>
                    </div>
                </div>
            </div>

            <script type="text/javascript">
                jQuery(document).ready(function($) {

                    // Script for when EDIT button is clicked
                    $('.confirm-edit-button-grmlt-301').off('click').on('click', function() {
                        var record__id = $(this).attr('data-post-id');
                        var record__oldPermalinkValue = jQuery(this).closest('.row').prev('.row').find('.old-permalink-el input').val();
                        var record__newPermalinkValue = jQuery(this).closest('.row').prev('.row').find('.new-permalink-el input').val();

                        $.ajax({
                            url: grmlt_vars.ajaxurl,
                            method: 'POST',
                            data: {
                                action: 'grmlt_database_301_redirect_edit_handler',
                                record_id: record__id,
                                record_oldPermalinkValue: record__oldPermalinkValue,
                                record_newPermalinkValue: record__newPermalinkValue,
                                security_nonce: grmlt_vars.permalink_edit_nonce
                            },
                            success: function(response) {
                                if (response.success) {
                                    alert('Permalink updated successfully');
                                    location.reload();
                                } else {
                                    alert('Error: ' + (response.data || 'Failed to update permalink'));
                                }
                            },
                            error: function(xhr, status, error) {
                                alert('Error: ' + error);
                            }
                        });
                    });

                    // Script for when DELETE button is clicked
                    $('.confirm-deletion-button-grmlt-301').off('click').on('click', function() {
                        var record__id = $(this).attr('data-post-id');

                        $.ajax({
                            url: grmlt_vars.ajaxurl,
                            method: 'POST',
                            data: {
                                action: 'grmlt_database_301_redirect_deletion_handler',
                                record_id: record__id,
                                security_nonce: grmlt_vars.permalink_delete_nonce
                            },
                            success: function(response) {
                                if (response.success) {
                                    location.reload();
                                } else {
                                    alert('Error: ' + (response.data || 'Failed to delete permalink'));
                                }
                            },
                            error: function(xhr, status, error) {
                                alert('Error: ' + error);
                            }
                        });
                    });

                }); // Document.ready END
            </script>
            <?php
        }
    } else {
        ?>
        <h4><?php esc_html_e( 'No Permalinks Found', 'greek-multi-tool' ); ?></h4>
        <?php
    }
    ?>
    </div> <!-- End of Listing ROWS -->
</div>
