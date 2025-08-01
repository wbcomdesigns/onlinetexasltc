/**
 * Vendor JavaScript for Dokan Domain Mapper
 */

jQuery(document).ready(function($) {
    'use strict';

    // Initialize
    init();

    function init() {
        bindEvents();
        setupFormValidation();
    }

    function bindEvents() {
        // Add domain form
        $('#add-domain-form').on('submit', function(e) {
            e.preventDefault();
            addDomain();
        });

        // Verify domain
        $(document).on('click', '.verify-domain', function(e) {
            e.preventDefault();
            const domainId = $(this).data('domain-id');
            const domain = $(this).data('domain');
            const token = $(this).data('token');
            verifyDomain(domainId, domain, token);
        });

        // Delete domain
        $(document).on('click', '.delete-domain', function(e) {
            e.preventDefault();
            const domainId = $(this).data('domain-id');
            deleteDomain(domainId);
        });

        // Show instructions
        $(document).on('click', '.show-instructions', function(e) {
            e.preventDefault();
            const domain = $(this).closest('.domain-card').find('h4').text();
            const token = $(this).closest('.domain-card').find('code').text();
            showVerificationInstructions(domain, token);
        });

        // Check health
        $(document).on('click', '.check-health', function(e) {
            e.preventDefault();
            const domain = $(this).closest('.domain-card').find('h4').text();
            checkDomainHealth(domain);
        });

        // Modal events
        $(document).on('click', '.close-modal', function(e) {
            e.preventDefault();
            closeAllModals();
        });

        // Close modal when clicking outside
        $(document).on('click', '.modal', function(e) {
            if (e.target === this) {
                closeAllModals();
            }
        });

        // Domain input validation
        $('#domain-input').on('input', function() {
            validateDomainInput($(this));
        });

        // Copy TXT record
        $(document).on('click', '.copy-txt-record', function(e) {
            e.preventDefault();
            const token = $(this).data('token');
            copyToClipboard(token);
            showNotice('success', 'TXT record copied to clipboard!');
        });
    }

    function setupFormValidation() {
        // Real-time domain validation
        $('#domain-input').on('blur', function() {
            const domain = $(this).val().trim();
            if (domain) {
                checkDomainAvailability(domain);
            }
        });
    }

    function addDomain() {
        const domain = $('#domain-input').val().trim();
        
        if (!domain) {
            showNotice('error', dokanDomainMapper.strings.domain_required);
            return;
        }

        if (!isValidDomain(domain)) {
            showNotice('error', dokanDomainMapper.strings.invalid_domain);
            return;
        }

        const form = $('#add-domain-form');
        const submitButton = form.find('button[type="submit"]');
        const originalText = submitButton.text();
        
        submitButton.prop('disabled', true).text(dokanDomainMapper.strings.processing);

        $.ajax({
            url: dokanDomainMapper.ajax_url,
            type: 'POST',
            data: {
                action: 'dokan_vendor_add_domain',
                domain: domain,
                nonce: dokanDomainMapper.nonce
            },
            success: function(response) {
                if (response.success) {
                    showNotice('success', 'Domain added successfully! Please complete DNS verification.');
                    form[0].reset();
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                } else {
                    showNotice('error', response.data);
                }
            },
            error: function() {
                showNotice('error', dokanDomainMapper.strings.error);
            },
            complete: function() {
                submitButton.prop('disabled', false).text(originalText);
            }
        });
    }

    function verifyDomain(domainId, domain, token) {
        const button = $(`.verify-domain[data-domain-id="${domainId}"]`);
        const originalText = button.text();
        
        button.prop('disabled', true).text(dokanDomainMapper.strings.processing);

        $.ajax({
            url: dokanDomainMapper.ajax_url,
            type: 'POST',
            data: {
                action: 'dokan_vendor_verify_domain',
                domain_id: domainId,
                nonce: dokanDomainMapper.nonce
            },
            success: function(response) {
                if (response.success) {
                    if (response.data.verified) {
                        showNotice('success', response.data.message);
                        setTimeout(function() {
                            location.reload();
                        }, 2000);
                    } else {
                        showNotice('error', response.data.message);
                    }
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
                action: 'dokan_vendor_delete_domain',
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

    function showVerificationInstructions(domain, token) {
        const vendorDashboard = new Dokan_Domain_Mapper_Vendor_Dashboard();
        const instructions = vendorDashboard.get_verification_instructions_html(domain, token);
        
        $('#verification-content').html(instructions);
        $('#verification-modal').show();
    }

    function checkDomainHealth(domain) {
        const button = $(`.check-health[data-domain="${domain}"]`);
        const originalText = button.text();
        
        button.prop('disabled', true).text(dokanDomainMapper.strings.processing);

        $.ajax({
            url: dokanDomainMapper.ajax_url,
            type: 'POST',
            data: {
                action: 'dokan_check_domain_health',
                domain: domain,
                nonce: dokanDomainMapper.nonce
            },
            success: function(response) {
                if (response.success) {
                    displayHealthResults(response.data);
                    $('#health-modal').show();
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

    function displayHealthResults(healthData) {
        let html = '<div class="health-results">';
        
        html += '<div class="health-item">';
        html += '<h4>HTTP Status</h4>';
        html += '<p>Status: ' + (healthData.http_accessible ? '✅ Accessible' : '❌ Not Accessible') + '</p>';
        html += '<p>Response Code: ' + healthData.http_response_code + '</p>';
        html += '</div>';
        
        html += '<div class="health-item">';
        html += '<h4>HTTPS Status</h4>';
        html += '<p>Status: ' + (healthData.https_accessible ? '✅ Accessible' : '❌ Not Accessible') + '</p>';
        html += '<p>Response Code: ' + healthData.https_response_code + '</p>';
        html += '</div>';
        
        if (healthData.ssl_info) {
            html += '<div class="health-item">';
            html += '<h4>SSL Certificate</h4>';
            html += '<p>Valid: ' + (healthData.ssl_info.valid ? '✅ Yes' : '❌ No') + '</p>';
            html += '<p>Issuer: ' + healthData.ssl_info.issuer + '</p>';
            html += '<p>Expires: ' + healthData.ssl_info.expiry + '</p>';
            html += '<p>Days Remaining: ' + healthData.ssl_info.days_remaining + '</p>';
            html += '</div>';
        }
        
        html += '</div>';
        
        $('#health-content').html(html);
    }

    function checkDomainAvailability(domain) {
        $.ajax({
            url: dokanDomainMapper.ajax_url,
            type: 'POST',
            data: {
                action: 'dokan_check_domain_availability',
                domain: domain,
                nonce: dokanDomainMapper.nonce
            },
            success: function(response) {
                if (response.success) {
                    if (!response.data.available) {
                        showInputError('This domain is already in use.');
                    } else {
                        clearInputError();
                    }
                }
            }
        });
    }

    function validateDomainInput(input) {
        const domain = input.val().trim();
        
        if (domain && !isValidDomain(domain)) {
            showInputError(dokanDomainMapper.strings.invalid_domain);
        } else {
            clearInputError();
        }
    }

    function isValidDomain(domain) {
        // Remove protocol if present
        domain = domain.replace(/^https?:\/\//, '');
        
        // Basic domain validation
        const domainRegex = /^[a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?(\.[a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?)*$/;
        
        if (!domainRegex.test(domain)) {
            return false;
        }
        
        // Check for valid TLD
        const parts = domain.split('.');
        if (parts.length < 2) {
            return false;
        }
        
        return true;
    }

    function showInputError(message) {
        const input = $('#domain-input');
        const errorDiv = input.siblings('.input-error');
        
        if (errorDiv.length === 0) {
            input.after('<div class="input-error" style="color: #dc3545; font-size: 12px; margin-top: 5px;">' + message + '</div>');
        } else {
            errorDiv.text(message);
        }
        
        input.addClass('error');
    }

    function clearInputError() {
        const input = $('#domain-input');
        const errorDiv = input.siblings('.input-error');
        
        errorDiv.remove();
        input.removeClass('error');
    }

    function closeAllModals() {
        $('.modal').hide();
    }

    function showNotice(type, message) {
        // Remove existing notices
        $('.dokan-notice').remove();
        
        const noticeClass = type === 'success' ? 'dokan-success' : 'dokan-error';
        const notice = $(`<div class="dokan-notice ${noticeClass}"><p>${message}</p></div>`);
        
        $('.dokan-dashboard-header').after(notice);
        
        // Auto-dismiss after 5 seconds
        setTimeout(function() {
            notice.fadeOut();
        }, 5000);
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
            .dokan-notice {
                padding: 15px;
                margin: 20px 0;
                border-radius: 5px;
                border-left: 4px solid;
            }
            
            .dokan-success {
                background: #d4edda;
                border-color: #28a745;
                color: #155724;
            }
            
            .dokan-error {
                background: #f8d7da;
                border-color: #dc3545;
                color: #721c24;
            }
            
            .input-error {
                color: #dc3545;
                font-size: 12px;
                margin-top: 5px;
            }
            
            .error {
                border-color: #dc3545 !important;
            }
            
            .health-results {
                max-height: 400px;
                overflow-y: auto;
            }
            
            .health-item {
                margin-bottom: 20px;
                padding: 15px;
                border: 1px solid #ddd;
                border-radius: 5px;
                background: #f8f9fa;
            }
            
            .health-item h4 {
                margin: 0 0 10px 0;
                color: #333;
            }
            
            .health-item p {
                margin: 5px 0;
                color: #666;
            }
            
            .verification-instructions {
                max-height: 400px;
                overflow-y: auto;
            }
            
            .txt-record-details {
                background: #f8f9fa;
                border: 1px solid #dee2e6;
                border-radius: 5px;
                padding: 15px;
                margin-bottom: 20px;
            }
            
            .txt-record-table {
                width: 100%;
                border-collapse: collapse;
            }
            
            .txt-record-table td {
                padding: 8px;
                border: 1px solid #dee2e6;
            }
            
            .txt-record-table td:first-child {
                font-weight: bold;
                background: #e9ecef;
            }
            
            .verification-steps ol {
                margin-left: 20px;
            }
            
            .verification-steps li {
                margin-bottom: 10px;
                color: #333;
            }
            
            .provider-instructions {
                margin-top: 20px;
            }
            
            .provider-tabs {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
                gap: 15px;
                margin-top: 15px;
            }
            
            .provider-tab {
                background: #fff;
                border: 1px solid #dee2e6;
                border-radius: 5px;
                padding: 15px;
            }
            
            .provider-tab h6 {
                margin: 0 0 10px 0;
                color: #333;
                font-size: 14px;
            }
            
            .provider-tab ol {
                margin: 0;
                padding-left: 20px;
            }
            
            .provider-tab li {
                margin-bottom: 5px;
                font-size: 12px;
                color: #666;
            }
            
            @media (max-width: 768px) {
                .provider-tabs {
                    grid-template-columns: 1fr;
                }
            }
        `)
        .appendTo('head');
}); 