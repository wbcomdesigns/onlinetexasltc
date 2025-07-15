(function( $ ) {
	'use strict';

	/**
	 * All of the code for your public-facing JavaScript source
	 * should reside in this file.
	 *
	 * Note: It has been assumed you will write jQuery code here, so the
	 * $ function reference has been prepared for usage within the scope
	 * of this function.
	 *
	 * This enables you to define handlers, for when the DOM is ready:
	 *
	 * $(function() {
	 *
	 * });
	 *
	 * When the window is loaded:
	 *
	 * $( window ).load(function() {
	 *
	 * });
	 *
	 * ...and/or other possibilities.
	 *
	 * Ideally, it is not considered best practise to attach more than a
	 * single DOM-ready or window-load handler for a particular page.
	 * Although scripts in the WordPress core, Plugins and Themes may be
	 * practising this, we should strive to set a better example in our own work.
	 */


	/**
 * JavaScript for Admin Products functionality
 * Save this file as: admin-products.js
 */

jQuery(document).ready(function($) {
    // Handle duplicate button clicks
    $(document).on('click','.duplicate-btn', function(e) {
        e.preventDefault();
        
        const $button = $(this);
        const productId = $button.data('product-id');
        const productName = $button.data('product-name');
        
        // Show confirmation dialog
        if (!confirm('Are you sure you want to duplicate"' + productName + '"?')) {
            return;
        }
        
        duplicateProduct(productId, $button);
    });
    
    // Search functionality
    $('.admin-products-search').on('keyup', function() {
        const searchTerm = $(this).val().toLowerCase();
        filterProducts(searchTerm);
    });
    
    $('.admin-products-search-btn').on('click', function() {
        const searchTerm = $('.admin-products-search').val().toLowerCase();
        filterProducts(searchTerm);
    });

	 // Handle pagination clicks
    $(document).on('click','.dokan-pagination a', function(e) {
        e.preventDefault();
        var page = $(this).html();
		var $this = $(this);
         $.ajax({
			url: dokan.ajaxurl,
			type: 'POST',
			data: {
				action: 'fetch_products_lists',
				page: page,
				nonce: online_texas.nonce,
			},
			success: function(response) {
				if (response.success) {
					$('.dokan-admin-products-listing').html(response.data.products_listing);
					$('.dokan-pagination').html(response.data.pagination_html);
				} else {
					dokan_show_error_message('Error: ' + response.data);
					resetButton($button, originalHtml);
				}
			},
			error: function() {
				dokan_show_error_message('An error occurred. Please try again.');
				resetButton($button, originalHtml);
			}
		});
    });

});

function duplicateProduct(productId, $button) {
    const originalHtml = $button.html();
    
    // Update button state
    $button.prop('disabled', true)
           .html('<i class="fas fa-spinner fa-spin"></i>Duplicating...');
    
    // Make AJAX request
    $.ajax({
        url: dokan.ajaxurl,
        type: 'POST',
        data: {
            action: 'duplicate_admin_product',
            product_id: productId,
            nonce: online_texas.nonce,
        },
        success: function(response) {
            if (response.success) {
                // Show success message
                dokan_show_success_message('Product duplicated successfully! Check your Products page.');
                
                // Update button
                $button.removeClass('dokan-btn-theme')
                       .addClass('dokan-btn-success')
                       .html('<i class="fas fa-check"></i>Duplicated');
                
                // Reset button after 3 seconds
                
            } else {
                dokan_show_error_message('Error: ' + response.data);
                resetButton($button, originalHtml);
            }
        },
        error: function() {
            dokan_show_error_message('An error occurred. Please try again.');
            resetButton($button, originalHtml);
        }
    });
}

function resetButton($button, originalHtml) {
    $button.prop('disabled', false).html(originalHtml);
}

function filterProducts(searchTerm) {
    $('.admin-products-table tbody tr').each(function() {
        const productName = $(this).find('.product-name').text().toLowerCase();
        const categories = $(this).find('.product-category').text().toLowerCase();
        
        if (productName.includes(searchTerm) || categories.includes(searchTerm)) {
            $(this).show();
        } else {
            $(this).hide();
        }
    });
}

// Helper functions for notifications (using Dokan's notification system)
function dokan_show_success_message(message) {
    $('.dokan-admin-products-wrap').prepend(
        '<div class="dokan-alert dokan-alert-success">' +
        '<button type="button" class="dokan-close" data-dismiss="alert">&times;</button>' +
        message +
        '</div>'
    );
    
    // Auto-hide after 5 seconds
    setTimeout(function() {
        $('.dokan-alert-success').fadeOut();
    }, 5000);
}

function dokan_show_error_message(message) {
    $('.dokan-admin-products-wrap').prepend(
        '<div class="dokan-alert dokan-alert-danger">' +
        '<button type="button" class="dokan-close" data-dismiss="alert">&times;</button>' +
        message +
        '</div>'
    );
    
    // Auto-hide after 5 seconds
    setTimeout(function() {
        $('.dokan-alert-danger').fadeOut();
    }, 5000);
}

})( jQuery );
