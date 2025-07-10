/**
 * All of the JavaScript for your admin-facing functionality should be
 * included in this file.
 *
 * @package Online_Texas_Core
 * @since 1.0.0
 */

(function($) {
    'use strict';

    /**
     * Admin functionality for Online Texas Core plugin
     */
    const OnlineTexasCore = {
        
        /**
         * Initialize admin functionality
         */
        init: function() {
            this.bindEvents();
            this.initTooltips();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Sync all vendors button
            $(document).on('click', '#otc-sync-all-vendors', this.syncAllVendors);
            
            // Sync individual vendor buttons
            $(document).on('click', '.otc-sync-vendor', this.syncIndividualVendor);
            
            // Clear debug log button
            $(document).on('click', '#otc-clear-debug-log', this.clearDebugLog);
            
            // Form validation
            $(document).on('submit', 'form[action*="online-texas-core"]', this.validateForm);
            
            // Auto-save settings (if enabled)
            $(document).on('change', '.otc-auto-save', this.autoSaveSettings);
        },

        /**
         * Initialize tooltips and help text
         */
        initTooltips: function() {
            // Add tooltips to status indicators
            $('.otc-status').each(function() {
                const $this = $(this);
                const title = $this.hasClass('otc-status-active') ? 
                    'Status: Active' : 'Status: Inactive';
                $this.attr('title', title);
            });

            // Add help text toggle functionality
            $('.otc-help-toggle').on('click', function(e) {
                e.preventDefault();
                $(this).next('.otc-help-text').slideToggle();
            });
        },

        /**
         * Sync all vendors
         */
        syncAllVendors: function(e) {
            e.preventDefault();
            
            const confirmMessage = typeof otc_ajax !== 'undefined' ? 
                'This will create missing products for all vendors. Continue?' :
                'This will create missing products for all vendors. Continue?';
                
            if (!confirm(confirmMessage)) {
                return;
            }

            const $button = $(this);
            const originalText = $button.text();
            
            OnlineTexasCore.showLoading();
            $button.prop('disabled', true).text('Syncing...');

            const ajaxData = {
                action: 'otc_manual_vendor_sync',
                vendor_id: 'all',
                nonce: typeof otc_ajax !== 'undefined' ? otc_ajax.nonce : ''
            };

            $.post(ajaxurl, ajaxData)
                .done(function(response) {
                    if (response.success) {
                        OnlineTexasCore.showNotice(response.data.message, 'success');
                        // Optionally reload the page to show updated stats
                        setTimeout(function() {
                            location.reload();
                        }, 2000);
                    } else {
                        OnlineTexasCore.showNotice('Error: ' + response.data.message, 'error');
                    }
                })
                .fail(function() {
                    OnlineTexasCore.showNotice('Request failed. Please try again.', 'error');
                })
                .always(function() {
                    OnlineTexasCore.hideLoading();
                    $button.prop('disabled', false).text(originalText);
                });
        },

        /**
         * Sync individual vendor
         */
        syncIndividualVendor: function(e) {
            e.preventDefault();
            
            const $button = $(this);
            const vendorId = $button.data('vendor-id');
            const originalText = $button.text();
            
            if (!vendorId) {
                OnlineTexasCore.showNotice('Invalid vendor ID', 'error');
                return;
            }

            $button.prop('disabled', true).text('Syncing...');

            const ajaxData = {
                action: 'otc_manual_vendor_sync',
                vendor_id: vendorId,
                nonce: typeof otc_ajax !== 'undefined' ? otc_ajax.nonce : ''
            };

            $.post(ajaxurl, ajaxData)
                .done(function(response) {
                    if (response.success) {
                        OnlineTexasCore.showNotice(response.data.message, 'success');
                        // Update the product count in the table if possible
                        OnlineTexasCore.updateVendorProductCount($button, vendorId);
                    } else {
                        OnlineTexasCore.showNotice('Error: ' + response.data.message, 'error');
                    }
                })
                .fail(function() {
                    OnlineTexasCore.showNotice('Request failed. Please try again.', 'error');
                })
                .always(function() {
                    $button.prop('disabled', false).text(originalText);
                });
        },

        /**
         * Clear debug log
         */
        clearDebugLog: function(e) {
            e.preventDefault();
            
            const confirmMessage = 'Are you sure you want to clear the debug log?';
            if (!confirm(confirmMessage)) {
                return;
            }

            const $button = $(this);
            const originalText = $button.text();
            
            $button.prop('disabled', true).text('Clearing...');

            const ajaxData = {
                action: 'otc_clear_debug_log',
                nonce: typeof otc_ajax !== 'undefined' ? otc_ajax.nonce : ''
            };

            $.post(ajaxurl, ajaxData)
                .done(function(response) {
                    if (response.success) {
                        OnlineTexasCore.showNotice('Debug log cleared successfully', 'success');
                        // Clear the debug log display
                        $('.otc-debug-log').html('<p>Debug log cleared.</p>');
                    } else {
                        OnlineTexasCore.showNotice('Failed to clear debug log', 'error');
                    }
                })
                .fail(function() {
                    OnlineTexasCore.showNotice('Request failed. Please try again.', 'error');
                })
                .always(function() {
                    $button.prop('disabled', false).text(originalText);
                });
        },

        /**
         * Validate forms before submission
         */
        validateForm: function(e) {
            const $form = $(this);
            let isValid = true;
            
            // Remove previous error messages
            $form.find('.otc-error-message').remove();
            
            // Validate required fields
            $form.find('[required]').each(function() {
                const $field = $(this);
                if (!$field.val().trim()) {
                    isValid = false;
                    $field.addClass('error');
                    $field.after('<span class="otc-error-message">This field is required.</span>');
                } else {
                    $field.removeClass('error');
                }
            });
            
            // Validate email fields
            $form.find('input[type="email"]').each(function() {
                const $field = $(this);
                const email = $field.val().trim();
                if (email && !OnlineTexasCore.isValidEmail(email)) {
                    isValid = false;
                    $field.addClass('error');
                    $field.after('<span class="otc-error-message">Please enter a valid email address.</span>');
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                OnlineTexasCore.showNotice('Please correct the errors below.', 'error');
                // Scroll to first error
                const $firstError = $form.find('.error').first();
                if ($firstError.length) {
                    $('html, body').animate({
                        scrollTop: $firstError.offset().top - 100
                    }, 500);
                }
            }
            
            return isValid;
        },

        /**
         * Auto-save settings
         */
        autoSaveSettings: function() {
            const $field = $(this);
            const $form = $field.closest('form');
            
            // Add loading indicator
            $field.after('<span class="otc-auto-save-indicator">Saving...</span>');
            
            // Simulate auto-save (implement actual AJAX if needed)
            setTimeout(function() {
                $('.otc-auto-save-indicator').text('Saved').fadeOut(2000, function() {
                    $(this).remove();
                });
            }, 1000);
        },

        /**
         * Show loading overlay
         */
        showLoading: function() {
            let $overlay = $('#otc-loading-overlay');
            if (!$overlay.length) {
                $overlay = $('<div id="otc-loading-overlay">' +
                    '<div class="otc-loading-spinner"></div>' +
                    '<div class="otc-loading-text">Processing...</div>' +
                    '</div>');
                $('body').append($overlay);
            }
            $overlay.show();
        },

        /**
         * Hide loading overlay
         */
        hideLoading: function() {
            $('#otc-loading-overlay').hide();
        },

        /**
         * Show admin notice
         */
        showNotice: function(message, type) {
            type = type || 'info';
            
            const $notice = $('<div class="notice notice-' + type + ' is-dismissible">' +
                '<p>' + message + '</p>' +
                '<button type="button" class="notice-dismiss">' +
                '<span class="screen-reader-text">Dismiss this notice.</span>' +
                '</button>' +
                '</div>');
            
            // Insert after page title
            const $target = $('.wrap h1').first();
            if ($target.length) {
                $target.after($notice);
            } else {
                $('.wrap').prepend($notice);
            }
            
            // Auto-dismiss success messages
            if (type === 'success') {
                setTimeout(function() {
                    $notice.fadeOut(function() {
                        $(this).remove();
                    });
                }, 5000);
            }
            
            // Handle dismiss button
            $notice.on('click', '.notice-dismiss', function() {
                $notice.fadeOut(function() {
                    $(this).remove();
                });
            });
        },

        /**
         * Update vendor product count in table
         */
        updateVendorProductCount: function($button, vendorId) {
            // This would require additional AJAX to get updated count
            // For now, just show a visual indicator
            const $row = $button.closest('tr');
            const $countCell = $row.find('td').eq(2); // Assuming product count is in 3rd column
            
            $countCell.addClass('otc-updated');
            setTimeout(function() {
                $countCell.removeClass('otc-updated');
            }, 2000);
        },

        /**
         * Validate email address
         */
        isValidEmail: function(email) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return emailRegex.test(email);
        },

        /**
         * Format numbers with commas
         */
        formatNumber: function(num) {
            return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
        },

        /**
         * Debounce function for search inputs
         */
        debounce: function(func, wait, immediate) {
            let timeout;
            return function executedFunction() {
                const context = this;
                const args = arguments;
                const later = function() {
                    timeout = null;
                    if (!immediate) func.apply(context, args);
                };
                const callNow = immediate && !timeout;
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
                if (callNow) func.apply(context, args);
            };
        }
    };

    /**
     * Initialize when document is ready
     */
    $(document).ready(function() {
        OnlineTexasCore.init();
        
        // Add fade-in animation to cards
        $('.card').each(function(index) {
            $(this).css('opacity', '0').delay(index * 100).animate({
                opacity: 1
            }, 500);
        });
        
        // Initialize responsive tables
        if ($.fn.DataTable) {
            $('.wp-list-table').DataTable({
                responsive: true,
                pageLength: 25,
                order: [],
                columnDefs: [
                    { orderable: false, targets: -1 } // Disable sorting on last column (actions)
                ]
            });
        }
    });

    /**
     * Handle window resize for responsive design
     */
    $(window).resize(OnlineTexasCore.debounce(function() {
        // Handle responsive adjustments if needed
        $('.otc-stats-grid, .otc-actions-grid').each(function() {
            const $grid = $(this);
            if ($(window).width() < 768) {
                $grid.addClass('otc-mobile');
            } else {
                $grid.removeClass('otc-mobile');
            }
        });
    }, 250));

    /**
     * Export OnlineTexasCore for use in other scripts
     */
    window.OnlineTexasCore = OnlineTexasCore;

})(jQuery);