(function( $ ) {
    'use strict';
    
    $(function() {
        // Document ready - attach event listeners
        
        // Attach click handler to delete permalink buttons
        $(document).on('click', '.delete-permalink-button', function(e) {
            e.preventDefault();
            
            var recordId = $(this).data('record-id');
            var confirmDelete = confirm(grmlt_vars.delete_confirm_text || 'Are you sure you want to delete this permalink?');
            
            if (confirmDelete) {
                $.ajax({
                    url: grmlt_vars.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'grmlt_database_301_redirect_deletion_handler',
                        record_id: recordId,
                        security_nonce: grmlt_vars.permalink_delete_nonce
                    },
                    beforeSend: function() {
                        // Show loading indicator if you have one
                    },
                    success: function(response) {
                        if (response.success) {
                            // Remove the deleted row or refresh the table
                            $('#permalink-row-' + recordId).fadeOut(400, function() {
                                $(this).remove();
                                
                                // If no rows left, show "No Permalinks Found" message
                                if ($('.permalink-table-row').length === 0) {
                                    $('.permalink-table').after('<p>No Permalinks Found</p>');
                                }
                            });
                        } else {
                            alert('Error: ' + (response.data || 'Failed to delete permalink'));
                        }
                    },
                    error: function(xhr, status, error) {
                        alert('Error: ' + error);
                    },
                    complete: function() {
                        // Hide loading indicator if you have one
                    }
                });
            }
        });
        
        // Attach click handler to edit permalink buttons (if they exist)
        $(document).on('click', '.edit-permalink-button', function(e) {
            e.preventDefault();
            
            var recordId = $(this).data('record-id');
            var oldPermalink = $('#old-permalink-' + recordId).val();
            var newPermalink = $('#new-permalink-' + recordId).val();
            
            if (!oldPermalink || !newPermalink) {
                alert('Both old and new permalinks are required');
                return;
            }
            
            $.ajax({
                url: grmlt_vars.ajaxurl,
                type: 'POST',
                data: {
                    action: 'grmlt_database_301_redirect_edit_handler',
                    record_id: recordId,
                    record_oldPermalinkValue: oldPermalink,
                    record_newPermalinkValue: newPermalink,
                    security_nonce: grmlt_vars.permalink_edit_nonce
                },
                beforeSend: function() {
                    // Show loading indicator if you have one
                },
                success: function(response) {
                    if (response.success) {
                        alert('Permalink updated successfully');
                    } else {
                        alert('Error: ' + (response.data || 'Failed to update permalink'));
                    }
                },
                error: function(xhr, status, error) {
                    alert('Error: ' + error);
                },
                complete: function() {
                    // Hide loading indicator if you have one
                }
            });
        });
        
        // Any other admin JS functionality you need here
    });
})( jQuery );