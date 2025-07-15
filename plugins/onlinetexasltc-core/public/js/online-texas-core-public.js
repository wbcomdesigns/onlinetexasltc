(function($) {
    'use strict';

    // Global flag to prevent multiple initializations
    if (window.onlineTexasCoreLoaded) {
        return;
    }
    window.onlineTexasCoreLoaded = true;

    $(document).ready(function() {
        console.log('Online Texas Core: Initializing...');

        // Single event delegation for duplicate buttons
        $(document).on('click', '.duplicate-btn', function(e) {
            e.preventDefault();
            e.stopImmediatePropagation();
            
            const $button = $(this);
            
            // Prevent multiple clicks on same button
            if ($button.hasClass('processing') || $button.prop('disabled')) {
                console.log('Button already processing, ignoring click');
                return false;
            }
            
            const productId = $button.data('product-id');
            const productName = $button.data('product-name') || 'this product';
            
            console.log('Duplicate button clicked for product:', productId);
            
            // Single confirmation dialog
            if (!confirm('Are you sure you want to duplicate "' + productName + '"?')) {
                return false;
            }
            
            // Immediately mark as processing
            $button.addClass('processing').prop('disabled', true);
            
            duplicateProduct(productId, $button);
            return false;
        });

        // Search functionality  
        $(document).on('keyup', '.admin-products-search', function() {
            const searchTerm = $(this).val().toLowerCase();
            filterProducts(searchTerm);
        });

        // Pagination
        $(document).on('click', '.dokan-pagination a', function(e) {
            e.preventDefault();
            e.stopImmediatePropagation();
            
            const $link = $(this);
            const page = $link.text().trim();
            
            // Only process numeric pages
            if (!/^\d+$/.test(page)) {
                return false;
            }
            
            if ($('.dokan-pagination').hasClass('loading')) {
                return false;
            }
            
            $('.dokan-pagination').addClass('loading');
            loadProductsPage(page);
            return false;
        });

        console.log('Online Texas Core: Initialized successfully');
    });

    function duplicateProduct(productId, $button) {
        const originalHtml = $button.html();
        
        console.log('Starting duplication for product:', productId);
        
        // Update button immediately
        $button.html('<i class="fas fa-spinner fa-spin"></i> Duplicating...')
               .removeClass('dokan-btn-theme')
               .addClass('dokan-btn-secondary');
        
        // Single AJAX request with proper error handling
        $.ajax({
            url: online_texas.ajaxurl,
            type: 'POST',
            timeout: 30000,
            data: {
                action: 'duplicate_admin_product',
                product_id: productId,
                nonce: online_texas.nonce
            },
            success: function(response) {
                console.log('AJAX Response:', response);
                
                if (response && response.success) {
                    showNotification('Product duplicated successfully!', 'success');
                    
                    // Update button to success state
                    $button.removeClass('dokan-btn-secondary')
                           .addClass('dokan-btn-success')
                           .html('<i class="fas fa-check"></i> Duplicated')
                           .prop('disabled', true);
                    
                    // Update the action cell to show "Already Duplicated"
                    $button.closest('td').html('<span class="dokan-text-muted">Already Duplicated</span>');
                    
                } else {
                    const errorMsg = response && response.data ? response.data : 'Unknown error';
                    showNotification('Error: ' + errorMsg, 'error');
                    resetButton($button, originalHtml);
                }
            },
            error: function(xhr, status, error) {
                console.log('AJAX Error:', {xhr, status, error});
                
                let errorMsg = 'Request failed. Please try again.';
                if (status === 'timeout') {
                    errorMsg = 'Request timed out. Please try again.';
                } else if (xhr.responseJSON && xhr.responseJSON.data) {
                    errorMsg = xhr.responseJSON.data;
                }
                
                showNotification(errorMsg, 'error');
                resetButton($button, originalHtml);
            },
            complete: function() {
                console.log('AJAX request completed');
                // Always remove processing class
                $button.removeClass('processing');
            }
        });
    }

    function loadProductsPage(page) {
        $.ajax({
            url: online_texas.ajaxurl,
            type: 'POST',
            timeout: 15000,
            data: {
                action: 'fetch_products_lists',
                page: page,
                nonce: online_texas.nonce
            },
            success: function(response) {
                if (response && response.success) {
                    $('.dokan-admin-products-listing').html(response.data.products_listing);
                    $('.dokan-pagination').html(response.data.pagination_html);
                } else {
                    showNotification('Failed to load products', 'error');
                }
            },
            error: function() {
                showNotification('Failed to load products. Please refresh the page.', 'error');
            },
            complete: function() {
                $('.dokan-pagination').removeClass('loading');
            }
        });
    }

    function resetButton($button, originalHtml) {
        $button.removeClass('processing dokan-btn-secondary dokan-btn-success')
               .addClass('dokan-btn-theme')
               .html(originalHtml)
               .prop('disabled', false);
    }

    function filterProducts(searchTerm) {
        $('.admin-products-table tbody tr').each(function() {
            const $row = $(this);
            const productName = $row.find('.product-name').text().toLowerCase();
            const categories = $row.find('.product-category').text().toLowerCase();
            
            if (productName.includes(searchTerm) || categories.includes(searchTerm)) {
                $row.show();
            } else {
                $row.hide();
            }
        });
    }

    function showNotification(message, type) {
        // Remove existing notifications
        $('.dokan-alert').remove();
        
        const alertClass = type === 'success' ? 'dokan-alert-success' : 'dokan-alert-danger';
        const $notification = $(
            '<div class="dokan-alert ' + alertClass + '">' +
            '<button type="button" class="dokan-close">&times;</button>' +
            message +
            '</div>'
        );
        
        $('.dokan-admin-products-wrap').prepend($notification);
        
        // Auto-hide after 5 seconds
        setTimeout(function() {
            $notification.fadeOut(500, function() {
                $(this).remove();
            });
        }, 5000);
        
        // Manual close
        $notification.find('.dokan-close').on('click', function() {
            $notification.fadeOut(500, function() {
                $(this).remove();
            });
        });
    }

})(jQuery);