<!-- CONVERT OLD PERMALINKS -->

<h6><?php _e('MANAGE OLD PERMALINKS', 'greek-multi-tool'); ?></h6>
<hr>
<strong class="mb-0"><?php _e('Convert All Old Permalinks', 'greek-multi-tool'); ?></strong>
<p><?php _e('Press the button bellow to initialize the conversion of all old permalinks', 'greek-multi-tool'); ?></p>
<div class="mt-3">
	<form method="post">
      <input class="btn btn-warning text-bold" type="submit" name="oldpermalinks" id="oldpermalinks" value="<?php _e('CONVERT', 'greek-multi-tool'); ?>" /><br/>
   </form>
</div>
<hr>
<strong class="mb-0"><?php _e('List of old permalinks', 'greek-multi-tool'); ?></strong>
<p><?php _e('In the list below you can view/manage the old converted permalinks', 'greek-multi-tool'); ?></p>
<div class="mt-3">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <!-- Start of Listing ROWS -->
    <div class="list-group">
    <?php

    // Call WPDB Global variable.
    global $wpdb;

    // Replace 'table_name' with the actual name of your database table
    $table_name = $wpdb->prefix . 'grmlt';

    // Run the query
    $results = $wpdb->get_results(
        "SELECT * FROM $table_name WHERE 1",
        OBJECT
    );

    // Check if there are any results
    if ($results) {
        // Loop through the results and display the data
        foreach ($results as $result) {
            ?>
            <!-- Actual LIST BLOCK DESKTOP-->
            <div class="d-lg-block d-none">
                <div class="list-group-item">
                    <div class="row align-items-center justify-content-between">
                        <div class="col-11">
                            <div class="row">
                                <div class="col-6">
                                    <div class="d-flex align-items-start">
                                        <strong><?php _e('ID:', 'greek-multi-tool'); ?></strong>
                                        <span><?= $result->permalink_id; ?></span>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="d-flex align-items-start">
                                        <strong><?php _e('Post ID:', 'greek-multi-tool'); ?></strong>
                                        <span><?= $result->post_id; ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-6">
                                    <div class="d-flex align-items-start">
                                        <strong><?php _e('Old Permalink:', 'greek-multi-tool'); ?></strong>
                                        <span class="text-break"><?= $result->old_permalink; ?></span>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="d-flex align-items-start">
                                        <strong><?php _e('New Permalink:', 'greek-multi-tool'); ?></strong>
                                        <span class="text-break"><?= $result->new_permalink; ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-1">
                            <div class="row">
                                <a href="#" class="blue-square-edit" data-toggle="modal" data-target="#fullscreenModal<?= $result->permalink_id; ?>">
                                    <i class="fas fa-pencil-alt"></i>
                                </a>
                                <a href="#" class="red-square-delete mx-2" data-toggle="modal" data-target="#fullscreenModalDelete<?= $result->permalink_id; ?>">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </div>
                            
                        </div>
                    </div>
                </div>
            </div>
            <!-- List BLOCK MOBILE -->
            <div class="d-lg-none">
                <a href="#" class="list-group-item list-group-item-action" data-toggle="collapse" data-target="#item<?= $result->permalink_id; ?>">
                    <div class="row">
                        <div class="col-6">
                            <div class="d-flex align-items-start">
                                <strong><?php _e('ID:', 'greek-multi-tool'); ?></strong>
                                <span><?= $result->permalink_id; ?></span>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="d-flex align-items-start">
                                <strong><?php _e('Post ID:', 'greek-multi-tool'); ?></strong>
                                <span><?= $result->post_id; ?></span>
                            </div>
                        </div>
                    </div>
                </a>
                <div id="item<?= $result->permalink_id; ?>" class="collapse">
                    <div class="card card-body">
                        <div class="row">
                            <div class="d-flex flex-column align-items-start">
                                <strong><?php _e('Old Permalink:', 'greek-multi-tool'); ?></strong>
                                <span class="text-break"><?= $result->old_permalink; ?></span>
                            </div>
                        </div>
                        <div class="row mt-2">
                            <div class="d-flex flex-column align-items-start">
                                <strong><?php _e('New Permalink:', 'greek-multi-tool'); ?></strong>
                                <span class="text-break"><?= $result->new_permalink; ?></span>
                            </div>
                        </div>
                        <div class="row mt-2">
                            <div class="d-flex justify-content-center">
                                <a href="#" class="blue-square-edit mx-2" data-toggle="modal" data-target="#fullscreenModal<?= $result->permalink_id; ?>">
                                    <i class="fas fa-pencil-alt"></i>
                                </a>
                                <a href="#" class="red-square-delete mx-2" data-toggle="modal" data-target="#fullscreenModalDelete<?= $result->permalink_id; ?>">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- EDIT Record Popup -->
            <div class="modal fade" id="fullscreenModal<?= $result->permalink_id; ?>" tabindex="-1" role="dialog" aria-labelledby="fullscreenModalLabel<?= $result->permalink_id; ?>" aria-hidden="true">
                <div class="modal-dialog modal-fullscreen-lg-down modal-dialog-centered modal-dialog-scrollable modal">
                    <div class="modal-content d-flex align-items-center justify-content-center">
                        <div class="modal-header">
                            <h5 class="modal-title" id="fullscreenModalLabel<?= $result->permalink_id; ?>"><?php _e('Edit Redirection', 'greek-multi-tool');?></h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <!-- Add your content here -->
                            <div class="row mb-1">
                                <h6><?php _e('Make sure you are making the correct changes, as editting redirection permalinks while redirection is still active may result in broken URLs or Loops', 'greek-multi-tool');?></h6>
                            </div>
                            <div class="row mb-2">
                                <div class="d-flex flex-column align-items-between">
                                    <span class="old-permalink-el">
                                        <?php _e('Old Permalink:', 'greek-multi-tool');?>
                                        <input class="w-100"  type="text" name="old-permalink" value="<?= $result->old_permalink; ?>">
                                    </span>
                                    <span class="new-permalink-el">
                                        <?php _e('New Permalink:', 'greek-multi-tool');?>
                                        <input class="w-100" type="text" name="new-permalink" value="<?= $result->new_permalink; ?>">
                                    </span>
                                </div>
                            </div>
                            <div class="row">
                                <div class="d-flex align-items-center justify-content-start">
                                    <button type="button" class="btn btn-secondary mx-2" data-dismiss="modal" aria-label="Close"><?php _e('Cancel', 'greek-multi-tool');?></button>
                                    <button data-post-id="<?= $result->permalink_id; ?>" type="button" class="btn btn-success mx-2 confirm-edit-button-grmlt-301"><?php _e('Save Changes', 'greek-multi-tool');?></button>
                                </div>
                            </div>
                            
                        </div>
                    </div>
                </div>
            </div>

            <!-- DELETE Record Popup -->
            <div class="modal fade" id="fullscreenModalDelete<?= $result->permalink_id; ?>" tabindex="-1" role="dialog" aria-labelledby="fullscreenModalLabelDelete<?= $result->permalink_id; ?>" aria-hidden="true">
                <div class="modal-dialog modal-fullscreen-lg-down modal-dialog-centered modal-dialog-scrollable modal">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="fullscreenModalLabelDelete<?= $result->permalink_id; ?>"><?php _e('Delete Redirection', 'greek-multi-tool'); ?></h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <!-- Add your content here -->
                            <h6><?php _e('Are you sure you want to delete this redirect record?', 'greek-multi-tool'); ?></h6>
                            <button type="button" class="btn btn-secondary m-auto" data-dismiss="modal" aria-label="Close"><?php _e('Cancel', 'greek-multi-tool'); ?></button>
                            <button data-post-id="<?= $result->permalink_id; ?>" type="button" class="btn btn-danger m-auto confirm-deletion-button-grmlt-301"><?php _e('Delete', 'greek-multi-tool'); ?></button>
                        </div>
                    </div>
                </div>
            </div>

            <script type="text/javascript">
                jQuery(document).ready(function($) {

                    // Script for when EDIT button is clicked
                    $('.confirm-edit-button-grmlt-301').off('click').on('click', function() {

                        // Variable for ID of record in DB to be deleted
                        var record__id = $(this).attr('data-post-id');
                        var record__oldPermalinkValue = jQuery(this).closest('.row').prev('.row').find('.old-permalink-el input').val();
                        var record__newPermalinkValue = jQuery(this).closest('.row').prev('.row').find('.new-permalink-el input').val();
                        var action = 'grmlt_database_301_redirect_edit_handler';

                        // Send an AJAX request to the PHP file for database deletion
                        $.ajax({
                            url: <?= "'".admin_url('admin-ajax.php')."'"; ?>,
                            method: 'POST',
                            data: {
                              action: action, // The action to identify the request in the PHP file
                              record_id: record__id, // ID of DB record
                              record_newPermalinkValue: record__newPermalinkValue, // Value of new permalink text to update
                              record_oldPermalinkValue: record__oldPermalinkValue // Value of old permalink text to update 
                            },
                            success: function(response) {
                              // Handle the success response
                              location.reload();
                            }
                        });
                    });

                    // Script for when DELETE button is clicked
                    $('.confirm-deletion-button-grmlt-301').off('click').on('click', function() {
                        // Variable for ID of record in DB to be deleted
                        var record__id = $(this).attr('data-post-id');
                        var action = 'grmlt_database_301_redirect_deletion_handler';

                        // Send an AJAX request to the PHP file for database deletion
                        $.ajax({
                            url: <?= "'".admin_url('admin-ajax.php')."'"; ?>,
                            method: 'POST',
                            data: {
                              action: action, // The action to identify the request in the PHP file
                              record_id: record__id // Replace with the appropriate record ID
                            },
                            success: function(response) {
                              // Handle the success response
                              location.reload();
                            }
                        });
                    });

                }); // Document.ready END
            </script>
            <?php
        }
    } else {
        // No Permalinks Found.
        ?>
        <h4><?= __('No Permalinks Found', 'greek-multi-tool'); ?></h4>
        <?php
    }
    ?>
    </div> <!-- End of Listing ROWS -->
</div>