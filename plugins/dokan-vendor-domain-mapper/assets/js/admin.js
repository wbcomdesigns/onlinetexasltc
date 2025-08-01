/**
 * Admin JavaScript for Dokan Domain Mapper
 */

jQuery(document).ready(function($) {
    'use strict';

    // Global variables
    let currentDomainId = null;
    let currentRejectReason = '';

    // Initialize
    init();

    function init() {
        bindEvents();
        setupBulkActions();
    }

    function bindEvents() {
        // Approve domain
        $(document).on('click', '.approve-domain', function(e) {
            e.preventDefault();
            const domainId = $(this).data('domain-id');
            approveDomain(domainId);
        });

        // Reject domain
        $(document).on('click', '.reject-domain', function(e) {
            e.preventDefault();
            const domainId = $(this).data('domain-id');
            showRejectModal(domainId);
        });

        // Delete domain
        $(document).on('click', '.delete-domain', function(e) {
            e.preventDefault();
            const domainId = $(this).data('domain-id');
            deleteDomain(domainId);
        });

        // View details
        $(document).on('click', '.view-details', function(e) {
            e.preventDefault();
            const domainId = $(this).data('domain-id');
            viewDomainDetails(domainId);
        });

        // Generate config
        $(document).on('click', '.generate-config', function(e) {
            e.preventDefault();
            const domainId = $(this).data('domain-id');
            generateProxyConfig(domainId);
        });

        // Modal events
        $(document).on('click', '.close-modal', function(e) {
            e.preventDefault();
            closeAllModals();
        });

        $(document).on('click', '.confirm-reject', function(e) {
            e.preventDefault();
            confirmReject();
        });

        $(document).on('click', '.cancel-reject', function(e) {
            e.preventDefault();
            closeAllModals();
        });

        // Close modal when clicking outside
        $(document).on('click', '.modal', function(e) {
            if (e.target === this) {
                closeAllModals();
            }
        });

        // Bulk action form submission
        $('#domain-mappings-form').on('submit', function(e) {
            const action = $('select[name="action"]').val();
            const action2 = $('select[name="action2"]').val();
            const selectedAction = action !== '-1' ? action : action2;
            
            if (selectedAction === '-1') {
                e.preventDefault();
                alert(dokanDomainMapper.strings.error);
                return false;
            }

            if (selectedAction === 'reject') {
                e.preventDefault();
                showBulkRejectModal();
                return false;
            }

            if (!confirm(dokanDomainMapper.strings['confirm_' + selectedAction])) {
                e.preventDefault();
                return false;
            }
        });
    }

    function setupBulkActions() {
        // Select all checkbox
        $('#cb-select-all-1').on('change', function() {
            $('input[name="domain_ids[]"]').prop('checked', this.checked);
        });

        // Update select all when individual checkboxes change
        $(document).on('change', 'input[name="domain_ids[]"]', function() {
            const totalCheckboxes = $('input[name="domain_ids[]"]').length;
            const checkedCheckboxes = $('input[name="domain_ids[]"]:checked').length;
            
            if (checkedCheckboxes === 0) {
                $('#cb-select-all-1').prop('indeterminate', false).prop('checked', false);
            } else if (checkedCheckboxes === totalCheckboxes) {
                $('#cb-select-all-1').prop('indeterminate', false).prop('checked', true);
            } else {
                $('#cb-select-all-1').prop('indeterminate', true);
            }
        });
    }

    function approveDomain(domainId) {
        if (!confirm(dokanDomainMapper.strings.confirm_approve)) {
            return;
        }

        const button = $(`.approve-domain[data-domain-id="${domainId}"]`);
        const originalText = button.text();
        
        button.prop('disabled', true).text(dokanDomainMapper.strings.processing);

        $.ajax({
            url: dokanDomainMapper.ajax_url,
            type: 'POST',
            data: {
                action: 'dokan_admin_approve_domain',
                domain_id: domainId,
                nonce: dokanDomainMapper.nonce
            },
            success: function(response) {
                if (response.success) {
                    showNotice('success', response.data.message);
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    showNotice('error', response.data);
                }
            },
            error: function() {
                showNotice('error', dokanDomainMapper.strings.error);
            },
            complete: function() {
                button.prop('disabled', false).text(originalText);
            }
        });
    }

    function showRejectModal(domainId) {
        currentDomainId = domainId;
        $('#reject-reason').val('');
        $('#reject-domain-modal').show();
    }

    function confirmReject() {
        const reason = $('#reject-reason').val();
        
        if (!reason.trim()) {
            alert('Please provide a reason for rejection.');
            return;
        }

        const button = $('.confirm-reject');
        const originalText = button.text();
        
        button.prop('disabled', true).text(dokanDomainMapper.strings.processing);

        $.ajax({
            url: dokanDomainMapper.ajax_url,
            type: 'POST',
            data: {
                action: 'dokan_admin_reject_domain',
                domain_id: currentDomainId,
                reason: reason,
                nonce: dokanDomainMapper.nonce
            },
            success: function(response) {
                if (response.success) {
                    showNotice('success', response.data.message);
                    closeAllModals();
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    showNotice('error', response.data);
                }
            },
            error: function() {
                showNotice('error', dokanDomainMapper.strings.error);
            },
            complete: function() {
                button.prop('disabled', false).text(originalText);
            }
        });
    }

    function deleteDomain(domainId) {
        if (!confirm(dokanDomainMapper.strings.confirm_delete)) {
            return;
        }

        const button = $(`.delete-domain[data-domain-id="${domainId}"]`);
        const originalText = button.text();
        
        button.prop('disabled', true).text(dokanDomainMapper.strings.processing);

        $.ajax({
            url: dokanDomainMapper.ajax_url,
            type: 'POST',
            data: {
                action: 'dokan_admin_delete_domain',
                domain_id: domainId,
                nonce: dokanDomainMapper.nonce
            },
            success: function(response) {
                if (response.success) {
                    showNotice('success', response.data.message);
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    showNotice('error', response.data);
                }
            },
            error: function() {
                showNotice('error', dokanDomainMapper.strings.error);
            },
            complete: function() {
                button.prop('disabled', false).text(originalText);
            }
        });
    }

    function viewDomainDetails(domainId) {
        const button = $(`.view-details[data-domain-id="${domainId}"]`);
        const originalText = button.text();
        
        button.prop('disabled', true).text(dokanDomainMapper.strings.processing);

        $.ajax({
            url: dokanDomainMapper.ajax_url,
            type: 'POST',
            data: {
                action: 'dokan_get_domain_details',
                domain_id: domainId,
                nonce: dokanDomainMapper.nonce
            },
            success: function(response) {
                if (response.success) {
                    $('#domain-details-content').html(response.data.html);
                    $('#domain-details-modal').show();
                } else {
                    showNotice('error', response.data);
                }
            },
            error: function() {
                showNotice('error', dokanDomainMapper.strings.error);
            },
            complete: function() {
                button.prop('disabled', false).text(originalText);
            }
        });
    }

    function generateProxyConfig(domainId) {
        const button = $(`.generate-config[data-domain-id="${domainId}"]`);
        const originalText = button.text();
        
        button.prop('disabled', true).text(dokanDomainMapper.strings.processing);

        $.ajax({
            url: dokanDomainMapper.ajax_url,
            type: 'POST',
            data: {
                action: 'dokan_generate_proxy_config',
                domain_id: domainId,
                nonce: dokanDomainMapper.nonce
            },
            success: function(response) {
                if (response.success) {
                    displayProxyConfig(response.data);
                    $('#proxy-config-modal').show();
                } else {
                    showNotice('error', response.data);
                }
            },
            error: function() {
                showNotice('error', dokanDomainMapper.strings.error);
            },
            complete: function() {
                button.prop('disabled', false).text(originalText);
            }
        });
    }

    function displayProxyConfig(config) {
        let html = '<div class="proxy-config-tabs">';
        
        // NGINX Config
        html += '<div class="config-tab">';
        html += '<h4>NGINX Configuration</h4>';
        html += '<pre><code>' + escapeHtml(config.configs.nginx) + '</code></pre>';
        html += '<button type="button" class="button copy-config" data-config="nginx">Copy to Clipboard</button>';
        html += '</div>';
        
        // Apache Config
        html += '<div class="config-tab">';
        html += '<h4>Apache Configuration</h4>';
        html += '<pre><code>' + escapeHtml(config.configs.apache) + '</code></pre>';
        html += '<button type="button" class="button copy-config" data-config="apache">Copy to Clipboard</button>';
        html += '</div>';
        
        // Cloudflare Config
        html += '<div class="config-tab">';
        html += '<h4>Cloudflare Workers</h4>';
        html += '<pre><code>' + escapeHtml(config.configs.cloudflare) + '</code></pre>';
        html += '<button type="button" class="button copy-config" data-config="cloudflare">Copy to Clipboard</button>';
        html += '</div>';
        
        html += '</div>';
        
        $('#proxy-config-content').html(html);
        
        // Bind copy events
        $('.copy-config').on('click', function() {
            const configType = $(this).data('config');
            const configText = config.configs[configType];
            copyToClipboard(configText);
            showNotice('success', 'Configuration copied to clipboard!');
        });
    }

    function showBulkRejectModal() {
        const selectedDomains = $('input[name="domain_ids[]"]:checked');
        
        if (selectedDomains.length === 0) {
            alert('Please select at least one domain.');
            return;
        }

        currentRejectReason = '';
        $('#reject-reason').val('');
        $('#reject-domain-modal').show();
        
        // Override confirm reject for bulk action
        $('.confirm-reject').off('click').on('click', function(e) {
            e.preventDefault();
            confirmBulkReject();
        });
    }

    function confirmBulkReject() {
        const reason = $('#reject-reason').val();
        
        if (!reason.trim()) {
            alert('Please provide a reason for rejection.');
            return;
        }

        const selectedDomains = $('input[name="domain_ids[]"]:checked');
        const domainIds = selectedDomains.map(function() {
            return $(this).val();
        }).get();

        const button = $('.confirm-reject');
        const originalText = button.text();
        
        button.prop('disabled', true).text(dokanDomainMapper.strings.processing);

        // Submit the form with the reason
        $('<input>').attr({
            type: 'hidden',
            name: 'reject_reason',
            value: reason
        }).appendTo('#domain-mappings-form');
        
        $('select[name="action"]').val('reject');
        $('#domain-mappings-form').submit();
    }

    function closeAllModals() {
        $('.modal').hide();
        currentDomainId = null;
        currentRejectReason = '';
    }

    function showNotice(type, message) {
        const noticeClass = type === 'success' ? 'notice-success' : 'notice-error';
        const notice = $(`<div class="notice ${noticeClass} is-dismissible"><p>${message}</p></div>`);
        
        $('.wrap h1').after(notice);
        
        // Auto-dismiss after 5 seconds
        setTimeout(function() {
            notice.fadeOut();
        }, 5000);
    }

    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }

    function copyToClipboard(text) {
        const textArea = document.createElement('textarea');
        textArea.value = text;
        document.body.appendChild(textArea);
        textArea.select();
        document.execCommand('copy');
        document.body.removeChild(textArea);
    }

    // Add CSS for better styling
    $('<style>')
        .prop('type', 'text/css')
        .html(`
            .proxy-config-tabs {
                max-height: 400px;
                overflow-y: auto;
            }
            
            .config-tab {
                margin-bottom: 20px;
                padding: 15px;
                border: 1px solid #ddd;
                border-radius: 5px;
            }
            
            .config-tab h4 {
                margin: 0 0 10px 0;
                color: #333;
            }
            
            .config-tab pre {
                background: #f5f5f5;
                padding: 10px;
                border-radius: 3px;
                overflow-x: auto;
                margin-bottom: 10px;
            }
            
            .config-tab code {
                font-family: 'Courier New', monospace;
                font-size: 12px;
                line-height: 1.4;
            }
            
            .copy-config {
                margin-top: 10px;
            }
            
            .notice {
                margin: 20px 0;
            }
        `)
        .appendTo('head');
}); 