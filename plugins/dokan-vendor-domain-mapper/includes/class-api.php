<?php
/**
 * API Class
 * 
 * Handles REST API endpoints for domain mapping
 */

if (!defined('ABSPATH')) {
    exit;
}

class Dokan_Domain_Mapper_API {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('init', array($this, 'setup_api_functionality'));
    }

    /**
     * Setup API functionality
     */
    public function setup_api_functionality() {
        add_action('rest_api_init', array($this, 'register_routes'));
    }

    /**
     * Register REST API routes
     */
    public function register_routes() {
        // Vendor routes
        register_rest_route('dokan/v1', '/domains', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_vendor_domains'),
                'permission_callback' => array($this, 'vendor_permission_callback'),
            ),
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'add_vendor_domain'),
                'permission_callback' => array($this, 'vendor_permission_callback'),
            ),
        ));

        register_rest_route('dokan/v1', '/domains/(?P<id>\d+)', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_vendor_domain'),
                'permission_callback' => array($this, 'vendor_permission_callback'),
            ),
            array(
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => array($this, 'delete_vendor_domain'),
                'permission_callback' => array($this, 'vendor_permission_callback'),
            ),
        ));

        register_rest_route('dokan/v1', '/domains/(?P<id>\d+)/verify', array(
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'verify_vendor_domain'),
                'permission_callback' => array($this, 'vendor_permission_callback'),
            ),
        ));

        register_rest_route('dokan/v1', '/domains/(?P<id>\d+)/ssl', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_domain_ssl_status'),
                'permission_callback' => array($this, 'vendor_permission_callback'),
            ),
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'setup_domain_ssl'),
                'permission_callback' => array($this, 'vendor_permission_callback'),
            ),
        ));

        register_rest_route('dokan/v1', '/domains/(?P<id>\d+)/proxy', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_domain_proxy_config'),
                'permission_callback' => array($this, 'vendor_permission_callback'),
            ),
        ));

        // Admin routes
        register_rest_route('dokan/v1', '/admin/domains', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_all_domains'),
                'permission_callback' => array($this, 'admin_permission_callback'),
            ),
        ));

        register_rest_route('dokan/v1', '/admin/domains/(?P<id>\d+)/approve', array(
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'approve_domain'),
                'permission_callback' => array($this, 'admin_permission_callback'),
            ),
        ));

        register_rest_route('dokan/v1', '/admin/domains/(?P<id>\d+)/reject', array(
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'reject_domain'),
                'permission_callback' => array($this, 'admin_permission_callback'),
            ),
        ));

        // Public routes
        register_rest_route('dokan/v1', '/domains/check/(?P<domain>[a-zA-Z0-9.-]+)', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'check_domain_availability'),
                'permission_callback' => '__return_true',
            ),
        ));

        register_rest_route('dokan/v1', '/domains/validate/(?P<domain>[a-zA-Z0-9.-]+)', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'validate_domain'),
                'permission_callback' => '__return_true',
            ),
        ));
    }

    /**
     * Vendor permission callback
     */
    public function vendor_permission_callback($request) {
        return current_user_can('dokan_is_seller');
    }

    /**
     * Admin permission callback
     */
    public function admin_permission_callback($request) {
        return current_user_can('manage_options');
    }

    /**
     * Get vendor domains
     */
    public function get_vendor_domains($request) {
        $vendor_id = dokan_get_current_user_id();
        $domain_mapper = new Dokan_Domain_Mapper();
        $domains = $domain_mapper->get_vendor_domains($vendor_id);

        $formatted_domains = array();
        foreach ($domains as $domain) {
            $formatted_domains[] = $this->format_domain_response($domain);
        }

        return rest_ensure_response($formatted_domains);
    }

    /**
     * Add vendor domain
     */
    public function add_vendor_domain($request) {
        $vendor_id = dokan_get_current_user_id();
        $domain = sanitize_text_field($request->get_param('domain'));

        if (empty($domain)) {
            return new WP_Error('missing_domain', __('Domain is required.', 'dokan-vendor-domain-mapper'), array('status' => 400));
        }

        $domain_mapper = new Dokan_Domain_Mapper();
        $result = $domain_mapper->add_domain($vendor_id, $domain);

        if (is_wp_error($result)) {
            return new WP_Error('domain_error', $result->get_error_message(), array('status' => 400));
        }

        return rest_ensure_response($this->format_domain_response($result));
    }

    /**
     * Get vendor domain
     */
    public function get_vendor_domain($request) {
        $domain_id = intval($request->get_param('id'));
        $vendor_id = dokan_get_current_user_id();

        $domain_mapper = new Dokan_Domain_Mapper();
        $domain_mapping = $domain_mapper->get_domain_mapping($domain_id);

        if (!$domain_mapping || $domain_mapping->vendor_id != $vendor_id) {
            return new WP_Error('not_found', __('Domain not found.', 'dokan-vendor-domain-mapper'), array('status' => 404));
        }

        return rest_ensure_response($this->format_domain_response($domain_mapping));
    }

    /**
     * Delete vendor domain
     */
    public function delete_vendor_domain($request) {
        $domain_id = intval($request->get_param('id'));
        $vendor_id = dokan_get_current_user_id();

        $domain_mapper = new Dokan_Domain_Mapper();
        $domain_mapping = $domain_mapper->get_domain_mapping($domain_id);

        if (!$domain_mapping || $domain_mapping->vendor_id != $vendor_id) {
            return new WP_Error('not_found', __('Domain not found.', 'dokan-vendor-domain-mapper'), array('status' => 404));
        }

        $result = $domain_mapper->delete_domain($domain_id);

        if (is_wp_error($result)) {
            return new WP_Error('delete_error', $result->get_error_message(), array('status' => 400));
        }

        return rest_ensure_response($result);
    }

    /**
     * Verify vendor domain
     */
    public function verify_vendor_domain($request) {
        $domain_id = intval($request->get_param('id'));
        $vendor_id = dokan_get_current_user_id();

        $domain_mapper = new Dokan_Domain_Mapper();
        $domain_mapping = $domain_mapper->get_domain_mapping($domain_id);

        if (!$domain_mapping || $domain_mapping->vendor_id != $vendor_id) {
            return new WP_Error('not_found', __('Domain not found.', 'dokan-vendor-domain-mapper'), array('status' => 404));
        }

        $result = $domain_mapper->verify_domain($domain_id);

        if (is_wp_error($result)) {
            return new WP_Error('verification_error', $result->get_error_message(), array('status' => 400));
        }

        return rest_ensure_response($result);
    }

    /**
     * Get domain SSL status
     */
    public function get_domain_ssl_status($request) {
        $domain_id = intval($request->get_param('id'));
        $vendor_id = dokan_get_current_user_id();

        global $wpdb;
        $domain_mapping = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}dokan_domain_mappings WHERE id = %d",
            $domain_id
        ));

        if (!$domain_mapping || $domain_mapping->vendor_id != $vendor_id) {
            return new WP_Error('not_found', __('Domain not found.', 'dokan-vendor-domain-mapper'), array('status' => 404));
        }

        $ssl_manager = new Dokan_SSL_Manager();
        $ssl_status = $ssl_manager->get_ssl_status($domain_mapping);

        return rest_ensure_response($ssl_status);
    }

    /**
     * Setup domain SSL
     */
    public function setup_domain_ssl($request) {
        $domain_id = intval($request->get_param('id'));
        $provider = sanitize_text_field($request->get_param('provider'));
        $vendor_id = dokan_get_current_user_id();

        global $wpdb;
        $domain_mapping = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}dokan_domain_mappings WHERE id = %d",
            $domain_id
        ));

        if (!$domain_mapping || $domain_mapping->vendor_id != $vendor_id) {
            return new WP_Error('not_found', __('Domain not found.', 'dokan-vendor-domain-mapper'), array('status' => 404));
        }

        $ssl_manager = new Dokan_SSL_Manager();
        $result = $ssl_manager->setup_ssl($domain_id, $provider);

        if (is_wp_error($result)) {
            return new WP_Error('ssl_error', $result->get_error_message(), array('status' => 400));
        }

        return rest_ensure_response($result);
    }

    /**
     * Get domain proxy config
     */
    public function get_domain_proxy_config($request) {
        $domain_id = intval($request->get_param('id'));
        $vendor_id = dokan_get_current_user_id();

        global $wpdb;
        $domain_mapping = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}dokan_domain_mappings WHERE id = %d",
            $domain_id
        ));

        if (!$domain_mapping || $domain_mapping->vendor_id != $vendor_id) {
            return new WP_Error('not_found', __('Domain not found.', 'dokan-vendor-domain-mapper'), array('status' => 404));
        }

        $proxy_manager = new Dokan_Proxy_Manager();
        $config = $proxy_manager->generate_config($domain_mapping);

        if (is_wp_error($config)) {
            return new WP_Error('config_error', $config->get_error_message(), array('status' => 400));
        }

        return rest_ensure_response($config);
    }

    /**
     * Get all domains (admin)
     */
    public function get_all_domains($request) {
        $domain_mapper = new Dokan_Domain_Mapper();
        
        $args = array(
            'status' => $request->get_param('status'),
            'vendor_id' => $request->get_param('vendor_id'),
            'per_page' => $request->get_param('per_page') ?: 20,
            'page' => $request->get_param('page') ?: 1
        );

        $domains = $domain_mapper->get_all_domain_mappings($args);

        $formatted_domains = array();
        foreach ($domains as $domain) {
            $formatted_domains[] = $this->format_domain_response($domain, true);
        }

        return rest_ensure_response($formatted_domains);
    }

    /**
     * Approve domain (admin)
     */
    public function approve_domain($request) {
        $domain_id = intval($request->get_param('id'));
        $domain_mapper = new Dokan_Domain_Mapper();

        $result = $domain_mapper->approve_domain($domain_id);

        if (is_wp_error($result)) {
            return new WP_Error('approval_error', $result->get_error_message(), array('status' => 400));
        }

        return rest_ensure_response($result);
    }

    /**
     * Reject domain (admin)
     */
    public function reject_domain($request) {
        $domain_id = intval($request->get_param('id'));
        $reason = sanitize_textarea_field($request->get_param('reason'));
        $domain_mapper = new Dokan_Domain_Mapper();

        $result = $domain_mapper->reject_domain($domain_id, $reason);

        if (is_wp_error($result)) {
            return new WP_Error('rejection_error', $result->get_error_message(), array('status' => 400));
        }

        return rest_ensure_response($result);
    }

    /**
     * Check domain availability
     */
    public function check_domain_availability($request) {
        $domain = sanitize_text_field($request->get_param('domain'));
        
        if (empty($domain)) {
            return new WP_Error('missing_domain', __('Domain is required.', 'dokan-vendor-domain-mapper'), array('status' => 400));
        }

        global $wpdb;
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}dokan_domain_mappings WHERE domain = %s",
            $domain
        ));

        return rest_ensure_response(array(
            'domain' => $domain,
            'available' => $exists == 0,
            'message' => $exists > 0 ? __('Domain is already in use.', 'dokan-vendor-domain-mapper') : __('Domain is available.', 'dokan-vendor-domain-mapper')
        ));
    }

    /**
     * Validate domain
     */
    public function validate_domain($request) {
        $domain = sanitize_text_field($request->get_param('domain'));
        
        if (empty($domain)) {
            return new WP_Error('missing_domain', __('Domain is required.', 'dokan-vendor-domain-mapper'), array('status' => 400));
        }

        $dns_verifier = new Dokan_DNS_Verifier();
        $validation = $dns_verifier->validate_dns_configuration($domain);

        return rest_ensure_response($validation);
    }

    /**
     * Format domain response
     */
    private function format_domain_response($domain, $include_vendor = false) {
        $response = array(
            'id' => $domain->id,
            'domain' => $domain->domain,
            'status' => $domain->status,
            'ssl_status' => $domain->ssl_status,
            'created_at' => $domain->created_at,
            'updated_at' => $domain->updated_at
        );

        if ($include_vendor) {
            $vendor = dokan_get_vendor_by_id($domain->vendor_id);
            $response['vendor'] = array(
                'id' => $domain->vendor_id,
                'name' => $vendor ? $vendor->get_name() : __('Unknown Vendor', 'dokan-vendor-domain-mapper'),
                'email' => $vendor ? $vendor->get_email() : '',
                'store_url' => $vendor ? dokan_get_store_url($domain->vendor_id) : ''
            );
        }

        // Add status-specific information
        switch ($domain->status) {
            case 'pending':
                $response['verification_token'] = $domain->verification_token;
                $response['verification_instructions'] = $this->get_verification_instructions($domain->domain, $domain->verification_token);
                break;
            
            case 'verified':
                $response['message'] = __('Domain verified. Waiting for admin approval.', 'dokan-vendor-domain-mapper');
                break;
            
            case 'approved':
                $response['message'] = __('Domain approved. Configuration in progress.', 'dokan-vendor-domain-mapper');
                break;
            
            case 'live':
                $response['message'] = __('Domain is live and accessible.', 'dokan-vendor-domain-mapper');
                $response['live_url'] = "https://{$domain->domain}";
                break;
            
            case 'rejected':
                $response['message'] = __('Domain request was rejected.', 'dokan-vendor-domain-mapper');
                break;
        }

        return $response;
    }

    /**
     * Get verification instructions
     */
    private function get_verification_instructions($domain, $token) {
        $dns_verifier = new Dokan_DNS_Verifier();
        $instructions = $dns_verifier->get_dns_provider_instructions();
        
        return array(
            'txt_record' => array(
                'name' => $domain,
                'type' => 'TXT',
                'value' => $token
            ),
            'steps' => $instructions['general']['steps'],
            'providers' => array_keys(array_diff_key($instructions, array('general' => true)))
        );
    }

    /**
     * Get API documentation
     */
    public function get_api_documentation() {
        return array(
            'endpoints' => array(
                'vendor' => array(
                    'GET /wp-json/dokan/v1/domains' => 'Get vendor domains',
                    'POST /wp-json/dokan/v1/domains' => 'Add new domain',
                    'GET /wp-json/dokan/v1/domains/{id}' => 'Get specific domain',
                    'DELETE /wp-json/dokan/v1/domains/{id}' => 'Delete domain',
                    'POST /wp-json/dokan/v1/domains/{id}/verify' => 'Verify domain',
                    'GET /wp-json/dokan/v1/domains/{id}/ssl' => 'Get SSL status',
                    'POST /wp-json/dokan/v1/domains/{id}/ssl' => 'Setup SSL',
                    'GET /wp-json/dokan/v1/domains/{id}/proxy' => 'Get proxy config'
                ),
                'admin' => array(
                    'GET /wp-json/dokan/v1/admin/domains' => 'Get all domains',
                    'POST /wp-json/dokan/v1/admin/domains/{id}/approve' => 'Approve domain',
                    'POST /wp-json/dokan/v1/admin/domains/{id}/reject' => 'Reject domain'
                ),
                'public' => array(
                    'GET /wp-json/dokan/v1/domains/check/{domain}' => 'Check domain availability',
                    'GET /wp-json/dokan/v1/domains/validate/{domain}' => 'Validate domain'
                )
            ),
            'authentication' => array(
                'vendor' => 'Requires dokan_is_seller capability',
                'admin' => 'Requires manage_options capability',
                'public' => 'No authentication required'
            ),
            'response_format' => array(
                'success' => 'JSON object with data',
                'error' => 'JSON object with error message and status code'
            )
        );
    }
} 