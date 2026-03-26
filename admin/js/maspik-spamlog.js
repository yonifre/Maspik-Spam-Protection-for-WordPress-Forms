jQuery(document).ready(function($) {
    var modal = $('#confirmation-modal');
    var confirmButton = $('#confirm-delete');
    var cancelButton = $('#cancel-delete');
    var closeButton = $('.close-button');
    var rowIdToDelete = null;

    // Show the modal when clicking the delete button
    $(document).on('click', '.spam-delete-button', function() {
        rowIdToDelete = $(this).data('row-id');
        modal.show();
    });

    // Close the modal
    function closeModal() {
        modal.hide();
        rowIdToDelete = null;
    }

    
    // Confirm delete
    confirmButton.on('click', function() {
        if (!rowIdToDelete) {
            return;
        }

        $.ajax({
            url: maspikAdmin.ajaxurl,
            type: 'POST',
            data: {
                action: 'maspik_delete_row',
                row_id: rowIdToDelete,
                nonce: maspikAdmin.nonce
            },
            beforeSend: function() {
                $('tr[class*="row-entries"]').each(function() {
                    if ($(this).find('.spam-delete-button').data('row-id') == rowIdToDelete) {
                        $(this).css('opacity', '0.5');
                    }
                });
            },
            success: function(response) {
                if (response.success) {
                    $('tr[class*="row-entries"]').each(function() {
                        if ($(this).find('.spam-delete-button').data('row-id') == rowIdToDelete) {
                            $(this).fadeOut(400, function() {
                                $(this).remove();
                                if ($('.row-entries').length === 0) {
                                    $('.log-warp').html("<div class='spam-empty-log'><h4>Empty log</h4></div>");
                                }
                            });
                        }
                    });
                } else {
                    alert(response.data.message || 'Failed to delete row.');
                    $('tr[class*="row-entries"]').each(function() {
                        if ($(this).find('.spam-delete-button').data('row-id') == rowIdToDelete) {
                            $(this).css('opacity', '1');
                        }
                    });
                }
            },
            error: function(xhr, status, error) {
                console.error('Error details:', {
                    status: status,
                    error: error,
                    response: xhr.responseText
                });
                alert('Server error occurred. Check console for details.');
                $('tr[class*="row-entries"]').each(function() {
                    if ($(this).find('.spam-delete-button').data('row-id') == rowIdToDelete) {
                        $(this).css('opacity', '1');
                    }
                });
            },
            complete: function() {
                closeModal();
            }
        });
    });

    // Cancel delete
    cancelButton.on('click', closeModal);
    closeButton.on('click', closeModal);

    // Close the modal when clicking outside of it
    $(window).on('click', function(event) {
        if ($(event.target).is(modal)) {
            closeModal();
        }
    });

    var fmodal = $('#filter-delete-modal');
    var fconfirmButton = $('#confirm-del-filter');
    var fcancelButton = $('#cancel-del-filter');
    var closeButton = $('.close-button');

    // Show the modal and set the confirmation message
    // Skip if it's a not-spam-action button (handled by the new false positive modal)
    $(document).on('click', '.row-entries:not(.not-a-spam) .filter-delete-button', function() {
        // Don't show old modal for not-spam-action buttons - they're handled by the false positive modal
        if ($(this).hasClass('not-spam-action')) {
            return; // Let the new handler in maspik-log.php handle it
        }
        
        rowIdToDelete = $(this).data('row-id');
        spamValue = $(this).data('spam-value'); // Assume spam_value is added to data attributes
        spamType = $(this).data('spam-type');   // Assume spam_type is added to data attributes

        if (spamType === 'Phone Format Field') {
            $('#filter-type').html("The phone number doesn't match any of the whitelisted formats. Would you like to remove all the existing whitelisted phone number formats?");
        }else if (spamValue == '1') {
            $('#filter-type').html("Do you want to disable the <pre>" + spamType + "</pre> option?");
        }else {
            $('#filter-type').html('Do you want to remove <pre>' + spamValue + '</pre> filter for <pre>' + spamType + '</pre>?');
        }
        fmodal.show();
    });

    // Close the modal and execute the callback function if provided
    function closeFModal(callback) {
        fmodal.hide();
        if (callback) {
            callback();
        }
    }


    // Confirm delete
    fconfirmButton.on('click', function() {
        closeFModal(function() {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'delete_filter',
                    row_id: rowIdToDelete,
                    nonce: maspikAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        alert("Filter deleted successfully!");
                        location.reload(); // Reload the page to reflect changes
                    } else {
                        alert("This filter cannot be deleted automatically, it is either already deleted or it comes from the Maspik API Dashboard, try to delete it manually.");                    }
                },
                error: function() {
                    alert('An error occurred.');
                }
            });
        });
    });

    // Cancel delete
    fcancelButton.on('click', function() {
        closeFModal(); // Close the modal when canceling
    });

    // Close the modal when the user clicks the close button
    closeButton.on('click', function() {
        closeFModal();
    });

    // Close the modal when clicking outside of it
    $(window).on('click', function(event) {
        if ($(event.target).is(fmodal)) {
            closeFModal();
        }
    });
});
