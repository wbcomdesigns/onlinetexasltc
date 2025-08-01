/**
 * Admin Permissions JavaScript
 * 
 * Handles AJAX requests for vendor code approval/rejection
 * with proper nonce verification and error handling
 */
jQuery(document).ready(function($) {
    // Handle approve requests
    $(document).on('click', '.approve-request', function(e) {
        e.preventDefault();
        
        var button = $(this);
        var requestId = button.data('request-id');
        
        if (confirm(otc_admin_permissions.confirm_approve)) {
            button.prop('disabled', true).text(otc_admin_permissions.approving_text);
            
            $.ajax({
                url: otc_admin_permissions.ajax_url,
                type: 'POST',
                data: {
                    action: 'approve_vendor_code_request',
                    request_id: requestId,
                    nonce: otc_admin_permissions.approve_nonce
                },
                success: function(response) {
                    if (response.success) {
                        alert(response.data || 'Request approved successfully!');
                        location.reload();
                    } else {
                        alert('Error: ' + (response.data || 'Unknown error'));
                        button.prop('disabled', false).text(otc_admin_permissions.approve_text);
                    }
                },
                error: function(xhr, status, error) {
                    alert(otc_admin_permissions.error_text);
                    button.prop('disabled', false).text(otc_admin_permissions.approve_text);
                }
            });
        }
    });
    
    // Handle reject requests
    $(document).on('click', '.reject-request', function(e) {
        e.preventDefault();
        
        var button = $(this);
        var requestId = button.data('request-id');
        
        if (confirm(otc_admin_permissions.confirm_reject)) {
            button.prop('disabled', true).text(otc_admin_permissions.rejecting_text);
            
            $.ajax({
                url: otc_admin_permissions.ajax_url,
                type: 'POST',
                data: {
                    action: 'reject_vendor_code_request',
                    request_id: requestId,
                    nonce: otc_admin_permissions.reject_nonce
                },
                success: function(response) {
                    if (response.success) {
                        alert(response.data || 'Request rejected successfully!');
                        location.reload();
                    } else {
                        alert('Error: ' + (response.data || 'Unknown error'));
                        button.prop('disabled', false).text(otc_admin_permissions.reject_text);
                    }
                },
                error: function(xhr, status, error) {
                    alert(otc_admin_permissions.error_text);
                    button.prop('disabled', false).text(otc_admin_permissions.reject_text);
                }
            });
        }
    });
});