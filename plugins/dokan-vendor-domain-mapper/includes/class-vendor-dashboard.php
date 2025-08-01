<?php
/**
 * Vendor Dashboard Class
 * 
 * Handles vendor dashboard interface for domain mapping
 */

if (!defined('ABSPATH')) {
    exit;
}

class Dokan_Domain_Mapper_Vendor_Dashboard {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('init', array($this, 'setup_vendor_interface'));
    }

    /**
     * Setup vendor interface
     */
    public function setup_vendor_interface() {
        // Add vendor dashboard menu
        add_filter('dokan_get_dashboard_nav', array($this, 'add_dashboard_nav'));
        
        // Add vendor dashboard content
        add_action('dokan_load_custom_template', array($this, 'load_vendor_template'));
        
        // Add vendor scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        
        // Add AJAX handlers
        add_action('wp_ajax_dokan_vendor_add_domain', array($this, 'process_ajax_add_domain'));
        add_action('wp_ajax_dokan_vendor_verify_domain', array($this, 'process_ajax_verify_domain'));
        add_action('wp_ajax_dokan_vendor_delete_domain', array($this, 'process_ajax_delete_domain'));
        add_action('wp_ajax_dokan_vendor_check_dns', array($this, 'process_ajax_check_dns'));
    }

    /**
     * Add dashboard navigation
     */
    public function add_dashboard_nav($navs) {
        if (!dokan_is_seller_enabled(get_current_user_id())) {
            return $navs;
        }

        $navs['domain-mapping'] = array(
            'title' => __('Store Domain', 'dokan-vendor-domain-mapper'),
            'icon' => '<i class="fas fa-globe"></i>',
            'url' => dokan_get_navigation_url('domain-mapping'),
            'pos' => 50
        );

        return $navs;
    }

    /**
     * Load vendor template
     */
    public function load_vendor_template($query_vars) {
        if (isset($query_vars['domain-mapping'])) {
            $this->display_vendor_domain_page();
        }
    }

    /**
     * Display vendor domain mapping page
     */
    public function display_vendor_domain_page() {
        $domain_mapper = new Dokan_Domain_Mapper();
        $vendor_id = dokan_get_current_user_id();
        $domains = $domain_mapper->get_vendor_domains($vendor_id);
        
        // Get vendor statistics
        $stats = $this->get_vendor_domain_stats($vendor_id);
        
        // Get DNS provider instructions
        $dns_verifier = new Dokan_DNS_Verifier();
        $dns_instructions = $dns_verifier->get_dns_provider_instructions();
        
        include DOKAN_DOMAIN_MAPPER_PLUGIN_DIR . 'templates/vendor/domain-mapping.php';
    }

    /**
     * Enqueue scripts and styles
     */
    public function enqueue_scripts() {
        if (!dokan_is_seller_dashboard()) {
            return;
        }

        wp_enqueue_script(
            'dokan-domain-mapper-vendor',
            DOKAN_DOMAIN_MAPPER_PLUGIN_URL . 'assets/js/vendor.js',
            array('jquery'),
            DOKAN_DOMAIN_MAPPER_VERSION,
            true
        );

        wp_enqueue_style(
            'dokan-domain-mapper-vendor',
            DOKAN_DOMAIN_MAPPER_PLUGIN_URL . 'assets/css/vendor.css',
            array(),
            DOKAN_DOMAIN_MAPPER_VERSION
        );

        wp_localize_script('dokan-domain-mapper-vendor', 'dokanDomainMapper', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('dokan_domain_mapper_nonce'),
            'strings' => array(
                'confirm_delete' => __('Are you sure you want to delete this domain?', 'dokan-vendor-domain-mapper'),
                'processing' => __('Processing...', 'dokan-vendor-domain-mapper'),
                'success' => __('Operation completed successfully.', 'dokan-vendor-domain-mapper'),
                'error' => __('An error occurred. Please try again.', 'dokan-vendor-domain-mapper'),
                'domain_required' => __('Please enter a domain name.', 'dokan-vendor-domain-mapper'),
                'invalid_domain' => __('Please enter a valid domain name.', 'dokan-vendor-domain-mapper')
            )
        ));
    }

    /**
     * Process AJAX request for adding domain
     */
    public function process_ajax_add_domain() {
        check_ajax_referer('dokan_domain_mapper_nonce', 'nonce');

        if (!current_user_can('dokan_is_seller')) {
            wp_die(__('Access denied.', 'dokan-vendor-domain-mapper'));
        }

        $domain = sanitize_text_field($_POST['domain']);
        $vendor_id = dokan_get_current_user_id();

        $domain_mapper = new Dokan_Domain_Mapper();
        $result = $domain_mapper->add_domain($vendor_id, $domain);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success($result);
        }
    }

    /**
     * Process AJAX request for verifying domain
     */
    public function process_ajax_verify_domain() {
        check_ajax_referer('dokan_domain_mapper_nonce', 'nonce');

        if (!current_user_can('dokan_is_seller')) {
            wp_die(__('Access denied.', 'dokan-vendor-domain-mapper'));
        }

        $domain_id = intval($_POST['domain_id']);
        $vendor_id = dokan_get_current_user_id();

        $domain_mapper = new Dokan_Domain_Mapper();
        
        // Verify ownership
        $domain_mapping = $domain_mapper->get_domain_mapping($domain_id);
        if (!$domain_mapping || $domain_mapping->vendor_id != $vendor_id) {
            wp_send_json_error(__('Access denied.', 'dokan-vendor-domain-mapper'));
        }

        $result = $domain_mapper->verify_domain($domain_id);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success($result);
        }
    }

    /**
     * Process AJAX request for deleting domain
     */
    public function process_ajax_delete_domain() {
        check_ajax_referer('dokan_domain_mapper_nonce', 'nonce');

        if (!current_user_can('dokan_is_seller')) {
            wp_die(__('Access denied.', 'dokan-vendor-domain-mapper'));
        }

        $domain_id = intval($_POST['domain_id']);
        $vendor_id = dokan_get_current_user_id();

        $domain_mapper = new Dokan_Domain_Mapper();
        
        // Verify ownership
        $domain_mapping = $domain_mapper->get_domain_mapping($domain_id);
        if (!$domain_mapping || $domain_mapping->vendor_id != $vendor_id) {
            wp_send_json_error(__('Access denied.', 'dokan-vendor-domain-mapper'));
        }

        $result = $domain_mapper->delete_domain($domain_id);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success($result);
        }
    }

    /**
     * Process AJAX request for checking DNS
     */
    public function process_ajax_check_dns() {
        check_ajax_referer('dokan_domain_mapper_nonce', 'nonce');

        if (!current_user_can('dokan_is_seller')) {
            wp_die(__('Access denied.', 'dokan-vendor-domain-mapper'));
        }

        $domain = sanitize_text_field($_POST['domain']);
        $token = sanitize_text_field($_POST['token']);

        $dns_verifier = new Dokan_DNS_Verifier();
        $result = $dns_verifier->verify_domain($domain, $token);

        wp_send_json_success($result);
    }

    /**
     * Get vendor domain statistics
     */
    public function get_vendor_domain_stats($vendor_id) {
        global $wpdb;

        $stats = array(
            'total' => 0,
            'pending' => 0,
            'verified' => 0,
            'approved' => 0,
            'rejected' => 0,
            'live' => 0
        );

        $results = $wpdb->get_results($wpdb->prepare("
            SELECT status, COUNT(*) as count 
            FROM {$wpdb->prefix}dokan_domain_mappings 
            WHERE vendor_id = %d
            GROUP BY status
        ", $vendor_id));

        foreach ($results as $result) {
            $stats[$result->status] = intval($result->count);
            $stats['total'] += intval($result->count);
        }

        return $stats;
    }

    /**
     * Get domain status badge
     */
    public function get_domain_status_badge($status) {
        $status_classes = array(
            'pending' => 'status-pending',
            'verified' => 'status-verified',
            'approved' => 'status-approved',
            'rejected' => 'status-rejected',
            'live' => 'status-live'
        );

        $status_labels = array(
            'pending' => __('Pending', 'dokan-vendor-domain-mapper'),
            'verified' => __('Verified', 'dokan-vendor-domain-mapper'),
            'approved' => __('Approved', 'dokan-vendor-domain-mapper'),
            'rejected' => __('Rejected', 'dokan-vendor-domain-mapper'),
            'live' => __('Live', 'dokan-vendor-domain-mapper')
        );

        $class = isset($status_classes[$status]) ? $status_classes[$status] : 'status-unknown';
        $label = isset($status_labels[$status]) ? $status_labels[$status] : $status;

        return sprintf('<span class="status-badge %s">%s</span>', esc_attr($class), esc_html($label));
    }

    /**
     * Get domain status description
     */
    public function get_domain_status_description($status) {
        $descriptions = array(
            'pending' => __('Your domain is pending DNS verification. Please add the TXT record to your DNS settings.', 'dokan-vendor-domain-mapper'),
            'verified' => __('Your domain has been verified. Waiting for admin approval.', 'dokan-vendor-domain-mapper'),
            'approved' => __('Your domain has been approved and is being configured.', 'dokan-vendor-domain-mapper'),
            'rejected' => __('Your domain request has been rejected. Please contact support for more information.', 'dokan-vendor-domain-mapper'),
            'live' => __('Your domain is live and accessible.', 'dokan-vendor-domain-mapper')
        );

        return isset($descriptions[$status]) ? $descriptions[$status] : '';
    }

    /**
     * Get domain action buttons
     */
    public function get_domain_action_buttons($domain_mapping) {
        $buttons = array();

        switch ($domain_mapping->status) {
            case 'pending':
                $buttons[] = sprintf(
                    '<button type="button" class="button button-primary verify-domain" data-domain-id="%d" data-domain="%s" data-token="%s">%s</button>',
                    $domain_mapping->id,
                    esc_attr($domain_mapping->domain),
                    esc_attr($domain_mapping->verification_token),
                    __('Verify Domain', 'dokan-vendor-domain-mapper')
                );
                $buttons[] = sprintf(
                    '<button type="button" class="button delete-domain" data-domain-id="%d">%s</button>',
                    $domain_mapping->id,
                    __('Delete', 'dokan-vendor-domain-mapper')
                );
                break;

            case 'verified':
                $buttons[] = sprintf(
                    '<span class="button disabled">%s</span>',
                    __('Waiting for Approval', 'dokan-vendor-domain-mapper')
                );
                $buttons[] = sprintf(
                    '<button type="button" class="button delete-domain" data-domain-id="%d">%s</button>',
                    $domain_mapping->id,
                    __('Delete', 'dokan-vendor-domain-mapper')
                );
                break;

            case 'approved':
                $buttons[] = sprintf(
                    '<span class="button disabled">%s</span>',
                    __('Being Configured', 'dokan-vendor-domain-mapper')
                );
                break;

            case 'rejected':
                $buttons[] = sprintf(
                    '<button type="button" class="button delete-domain" data-domain-id="%d">%s</button>',
                    $domain_mapping->id,
                    __('Delete', 'dokan-vendor-domain-mapper')
                );
                break;

            case 'live':
                $buttons[] = sprintf(
                    '<a href="https://%s" target="_blank" class="button button-primary">%s</a>',
                    esc_attr($domain_mapping->domain),
                    __('Visit Site', 'dokan-vendor-domain-mapper')
                );
                break;
        }

        return implode(' ', $buttons);
    }

    /**
     * Get verification instructions HTML
     */
    public function get_verification_instructions_html($domain, $token) {
        $dns_verifier = new Dokan_DNS_Verifier();
        $instructions = $dns_verifier->get_dns_provider_instructions();
        
        $html = '<div class="verification-instructions">';
        $html .= '<h4>' . __('DNS Verification Instructions', 'dokan-vendor-domain-mapper') . '</h4>';
        
        // TXT Record details
        $html .= '<div class="txt-record-details">';
        $html .= '<h5>' . __('TXT Record Details', 'dokan-vendor-domain-mapper') . '</h5>';
        $html .= '<table class="txt-record-table">';
        $html .= '<tr><td><strong>' . __('Name:', 'dokan-vendor-domain-mapper') . '</strong></td><td>' . esc_html($domain) . '</td></tr>';
        $html .= '<tr><td><strong>' . __('Type:', 'dokan-vendor-domain-mapper') . '</strong></td><td>TXT</td></tr>';
        $html .= '<tr><td><strong>' . __('Value:', 'dokan-vendor-domain-mapper') . '</strong></td><td><code>' . esc_html($token) . '</code></td></tr>';
        $html .= '</table>';
        $html .= '</div>';
        
        // General steps
        $html .= '<div class="verification-steps">';
        $html .= '<h5>' . __('Steps to Follow', 'dokan-vendor-domain-mapper') . '</h5>';
        $html .= '<ol>';
        foreach ($instructions['general']['steps'] as $step) {
            $html .= '<li>' . esc_html($step) . '</li>';
        }
        $html .= '</ol>';
        $html .= '</div>';
        
        // Provider-specific instructions
        $html .= '<div class="provider-instructions">';
        $html .= '<h5>' . __('Provider-Specific Instructions', 'dokan-vendor-domain-mapper') . '</h5>';
        $html .= '<div class="provider-tabs">';
        
        foreach ($instructions as $provider => $provider_instructions) {
            if ($provider === 'general') continue;
            
            $html .= '<div class="provider-tab">';
            $html .= '<h6>' . esc_html($provider_instructions['title']) . '</h6>';
            $html .= '<ol>';
            foreach ($provider_instructions['steps'] as $step) {
                $html .= '<li>' . esc_html($step) . '</li>';
            }
            $html .= '</ol>';
            $html .= '</div>';
        }
        
        $html .= '</div>';
        $html .= '</div>';
        
        $html .= '</div>';
        
        return $html;
    }

    /**
     * Get domain limit information
     */
    public function get_domain_limit_info($vendor_id) {
        $max_domains = get_option('dokan_domain_mapper_max_domains_per_vendor', 1);
        $current_domains = count($this->get_vendor_domains($vendor_id));
        $remaining = max(0, $max_domains - $current_domains);

        return array(
            'max' => $max_domains,
            'current' => $current_domains,
            'remaining' => $remaining,
            'can_add' => $remaining > 0
        );
    }

    /**
     * Get vendor domains (helper method)
     */
    private function get_vendor_domains($vendor_id) {
        $domain_mapper = new Dokan_Domain_Mapper();
        return $domain_mapper->get_vendor_domains($vendor_id);
    }

    /**
     * Get SSL status information
     */
    public function get_ssl_status_info($domain_mapping) {
        $ssl_manager = new Dokan_SSL_Manager();
        return $ssl_manager->get_ssl_status($domain_mapping);
    }

    /**
     * Get domain health check
     */
    public function get_domain_health_check($domain) {
        $dns_verifier = new Dokan_DNS_Verifier();
        return $dns_verifier->check_domain_accessibility($domain);
    }

    /**
     * Get domain DNS information
     */
    public function get_domain_dns_info($domain) {
        $dns_verifier = new Dokan_DNS_Verifier();
        return $dns_verifier->get_domain_dns_info($domain);
    }

    /**
     * Get domain validation
     */
    public function get_domain_validation($domain) {
        $dns_verifier = new Dokan_DNS_Verifier();
        return $dns_verifier->validate_dns_configuration($domain);
    }
} 