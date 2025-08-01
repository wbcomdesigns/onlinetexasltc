<?php
/**
 * Domain Mapper Class
 * 
 * Handles core domain mapping functionality
 */

if (!defined('ABSPATH')) {
    exit;
}

class Dokan_Domain_Mapper {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('init', array($this, 'setup_domain_mapper'));
    }

    /**
     * Setup domain mapper functionality
     */
    public function setup_domain_mapper() {
        // Add AJAX handlers
        add_action('wp_ajax_dokan_add_domain', array($this, 'process_ajax_add_domain'));
        add_action('wp_ajax_dokan_verify_domain', array($this, 'process_ajax_verify_domain'));
        add_action('wp_ajax_dokan_delete_domain', array($this, 'process_ajax_delete_domain'));
        
        // Add REST API endpoints
        add_action('rest_api_init', array($this, 'register_rest_routes'));
    }

    /**
     * Add domain for vendor
     */
    public function add_domain($vendor_id, $domain) {
        global $wpdb;

        // Validate domain
        if (!$this->validate_domain($domain)) {
            return new WP_Error('invalid_domain', __('Invalid domain format.', 'dokan-vendor-domain-mapper'));
        }

        // Check if domain already exists
        if ($this->domain_exists($domain)) {
            return new WP_Error('domain_exists', __('Domain already exists.', 'dokan-vendor-domain-mapper'));
        }

        // Check vendor domain limit
        if (!$this->can_vendor_add_domain($vendor_id)) {
            return new WP_Error('limit_exceeded', __('Domain limit exceeded.', 'dokan-vendor-domain-mapper'));
        }

        // Generate verification token
        $verification_token = $this->generate_verification_token();

        // Insert domain mapping
        $result = $wpdb->insert(
            $wpdb->prefix . 'dokan_domain_mappings',
            array(
                'vendor_id' => $vendor_id,
                'domain' => sanitize_text_field($domain),
                'status' => 'pending',
                'verification_token' => $verification_token,
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ),
            array('%d', '%s', '%s', '%s', '%s', '%s')
        );

        if ($result === false) {
            return new WP_Error('database_error', __('Failed to add domain.', 'dokan-vendor-domain-mapper'));
        }

        $domain_id = $wpdb->insert_id;

        // Send notification to admin
        $this->notify_admin_new_domain($domain_id);

        return array(
            'id' => $domain_id,
            'domain' => $domain,
            'status' => 'pending',
            'verification_token' => $verification_token,
            'verification_instructions' => $this->get_verification_instructions($domain, $verification_token)
        );
    }

    /**
     * Verify domain DNS
     */
    public function verify_domain($domain_id) {
        global $wpdb;

        $domain_mapping = $this->get_domain_mapping($domain_id);
        if (!$domain_mapping) {
            return new WP_Error('domain_not_found', __('Domain mapping not found.', 'dokan-vendor-domain-mapper'));
        }

        $dns_verifier = new Dokan_DNS_Verifier();
        $verification_result = $dns_verifier->verify_domain($domain_mapping->domain, $domain_mapping->verification_token);

        if ($verification_result['verified']) {
            // Update status to verified
            $wpdb->update(
                $wpdb->prefix . 'dokan_domain_mappings',
                array(
                    'status' => 'verified',
                    'updated_at' => current_time('mysql')
                ),
                array('id' => $domain_id),
                array('%s', '%s'),
                array('%d')
            );

            return array(
                'verified' => true,
                'message' => __('Domain verified successfully.', 'dokan-vendor-domain-mapper')
            );
        } else {
            return array(
                'verified' => false,
                'message' => __('Domain verification failed. Please check your DNS settings.', 'dokan-vendor-domain-mapper')
            );
        }
    }

    /**
     * Approve domain
     */
    public function approve_domain($domain_id) {
        global $wpdb;

        $domain_mapping = $this->get_domain_mapping($domain_id);
        if (!$domain_mapping) {
            return new WP_Error('domain_not_found', __('Domain mapping not found.', 'dokan-vendor-domain-mapper'));
        }

        if ($domain_mapping->status !== 'verified') {
            return new WP_Error('invalid_status', __('Domain must be verified before approval.', 'dokan-vendor-domain-mapper'));
        }

        // Update status to approved
        $result = $wpdb->update(
            $wpdb->prefix . 'dokan_domain_mappings',
            array(
                'status' => 'approved',
                'updated_at' => current_time('mysql')
            ),
            array('id' => $domain_id),
            array('%s', '%s'),
            array('%d')
        );

        if ($result === false) {
            return new WP_Error('database_error', __('Failed to approve domain.', 'dokan-vendor-domain-mapper'));
        }

        // Generate proxy configuration
        $proxy_manager = new Dokan_Proxy_Manager();
        $proxy_config = $proxy_manager->generate_config($domain_mapping);

        // Notify vendor
        $this->notify_vendor_domain_approved($domain_mapping);

        return array(
            'approved' => true,
            'message' => __('Domain approved successfully.', 'dokan-vendor-domain-mapper'),
            'proxy_config' => $proxy_config
        );
    }

    /**
     * Reject domain
     */
    public function reject_domain($domain_id, $reason = '') {
        global $wpdb;

        $domain_mapping = $this->get_domain_mapping($domain_id);
        if (!$domain_mapping) {
            return new WP_Error('domain_not_found', __('Domain mapping not found.', 'dokan-vendor-domain-mapper'));
        }

        // Update status to rejected
        $result = $wpdb->update(
            $wpdb->prefix . 'dokan_domain_mappings',
            array(
                'status' => 'rejected',
                'updated_at' => current_time('mysql')
            ),
            array('id' => $domain_id),
            array('%s', '%s'),
            array('%d')
        );

        if ($result === false) {
            return new WP_Error('database_error', __('Failed to reject domain.', 'dokan-vendor-domain-mapper'));
        }

        // Notify vendor
        $this->notify_vendor_domain_rejected($domain_mapping, $reason);

        return array(
            'rejected' => true,
            'message' => __('Domain rejected successfully.', 'dokan-vendor-domain-mapper')
        );
    }

    /**
     * Delete domain
     */
    public function delete_domain($domain_id) {
        global $wpdb;

        $domain_mapping = $this->get_domain_mapping($domain_id);
        if (!$domain_mapping) {
            return new WP_Error('domain_not_found', __('Domain mapping not found.', 'dokan-vendor-domain-mapper'));
        }

        // Delete domain mapping
        $result = $wpdb->delete(
            $wpdb->prefix . 'dokan_domain_mappings',
            array('id' => $domain_id),
            array('%d')
        );

        if ($result === false) {
            return new WP_Error('database_error', __('Failed to delete domain.', 'dokan-vendor-domain-mapper'));
        }

        return array(
            'deleted' => true,
            'message' => __('Domain deleted successfully.', 'dokan-vendor-domain-mapper')
        );
    }

    /**
     * Get domain mapping
     */
    public function get_domain_mapping($domain_id) {
        global $wpdb;

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}dokan_domain_mappings WHERE id = %d",
            $domain_id
        ));
    }

    /**
     * Get vendor domains
     */
    public function get_vendor_domains($vendor_id) {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}dokan_domain_mappings WHERE vendor_id = %d ORDER BY created_at DESC",
            $vendor_id
        ));
    }

    /**
     * Get all domain mappings
     */
    public function get_all_domain_mappings($args = array()) {
        global $wpdb;

        $defaults = array(
            'status' => '',
            'vendor_id' => '',
            'per_page' => 20,
            'page' => 1,
            'orderby' => 'created_at',
            'order' => 'DESC'
        );

        $args = wp_parse_args($args, $defaults);
        $where = array();
        $where_values = array();

        if (!empty($args['status'])) {
            $where[] = 'status = %s';
            $where_values[] = $args['status'];
        }

        if (!empty($args['vendor_id'])) {
            $where[] = 'vendor_id = %d';
            $where_values[] = $args['vendor_id'];
        }

        $where_clause = '';
        if (!empty($where)) {
            $where_clause = 'WHERE ' . implode(' AND ', $where);
        }

        $order_clause = "ORDER BY {$args['orderby']} {$args['order']}";
        $limit_clause = "LIMIT " . (($args['page'] - 1) * $args['per_page']) . ", {$args['per_page']}";

        $query = "SELECT * FROM {$wpdb->prefix}dokan_domain_mappings {$where_clause} {$order_clause} {$limit_clause}";

        if (!empty($where_values)) {
            $query = $wpdb->prepare($query, $where_values);
        }

        return $wpdb->get_results($query);
    }

    /**
     * Validate domain format
     */
    private function validate_domain($domain) {
        // Remove protocol if present
        $domain = preg_replace('#^https?://#', '', $domain);
        
        // Remove trailing slash
        $domain = rtrim($domain, '/');
        
        // Basic domain validation
        if (!preg_match('/^[a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?(\.[a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?)*$/', $domain)) {
            return false;
        }

        // Check for valid TLD
        $parts = explode('.', $domain);
        if (count($parts) < 2) {
            return false;
        }

        return true;
    }

    /**
     * Check if domain exists
     */
    private function domain_exists($domain) {
        global $wpdb;

        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}dokan_domain_mappings WHERE domain = %s",
            $domain
        ));

        return $count > 0;
    }

    /**
     * Check if vendor can add domain
     */
    private function can_vendor_add_domain($vendor_id) {
        $max_domains = get_option('dokan_domain_mapper_max_domains_per_vendor', 1);
        $current_domains = count($this->get_vendor_domains($vendor_id));

        return $current_domains < $max_domains;
    }

    /**
     * Generate verification token
     */
    private function generate_verification_token() {
        return 'dokan-verification=' . wp_generate_password(32, false);
    }

    /**
     * Get verification instructions
     */
    private function get_verification_instructions($domain, $token) {
        $instructions = array(
            'txt_record' => array(
                'name' => $domain,
                'type' => 'TXT',
                'value' => $token
            ),
            'steps' => array(
                __('Log in to your domain registrar or DNS provider.', 'dokan-vendor-domain-mapper'),
                __('Add a new TXT record with the following details:', 'dokan-vendor-domain-mapper'),
                sprintf(__('Name: %s', 'dokan-vendor-domain-mapper'), $domain),
                __('Type: TXT', 'dokan-vendor-domain-mapper'),
                sprintf(__('Value: %s', 'dokan-vendor-domain-mapper'), $token),
                __('Wait 5-10 minutes for DNS propagation, then click "Verify Domain".', 'dokan-vendor-domain-mapper')
            )
        );

        return $instructions;
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

        $result = $this->add_domain($vendor_id, $domain);

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

        // Verify ownership
        $domain_mapping = $this->get_domain_mapping($domain_id);
        if (!$domain_mapping || $domain_mapping->vendor_id != $vendor_id) {
            wp_send_json_error(__('Access denied.', 'dokan-vendor-domain-mapper'));
        }

        $result = $this->verify_domain($domain_id);

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

        // Verify ownership
        $domain_mapping = $this->get_domain_mapping($domain_id);
        if (!$domain_mapping || $domain_mapping->vendor_id != $vendor_id) {
            wp_send_json_error(__('Access denied.', 'dokan-vendor-domain-mapper'));
        }

        $result = $this->delete_domain($domain_id);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success($result);
        }
    }

    /**
     * Register REST API routes
     */
    public function register_rest_routes() {
        register_rest_route('dokan/v1', '/domains', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'rest_get_domains'),
                'permission_callback' => array($this, 'rest_permission_callback'),
            ),
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'rest_add_domain'),
                'permission_callback' => array($this, 'rest_permission_callback'),
            ),
        ));

        register_rest_route('dokan/v1', '/domains/(?P<id>\d+)', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'rest_get_domain'),
                'permission_callback' => array($this, 'rest_permission_callback'),
            ),
            array(
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => array($this, 'rest_delete_domain'),
                'permission_callback' => array($this, 'rest_permission_callback'),
            ),
        ));

        register_rest_route('dokan/v1', '/domains/(?P<id>\d+)/verify', array(
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'rest_verify_domain'),
                'permission_callback' => array($this, 'rest_permission_callback'),
            ),
        ));
    }

    /**
     * REST API permission callback
     */
    public function rest_permission_callback($request) {
        return current_user_can('dokan_is_seller');
    }

    /**
     * REST API: Get domains
     */
    public function rest_get_domains($request) {
        $vendor_id = dokan_get_current_user_id();
        $domains = $this->get_vendor_domains($vendor_id);

        return rest_ensure_response($domains);
    }

    /**
     * REST API: Add domain
     */
    public function rest_add_domain($request) {
        $vendor_id = dokan_get_current_user_id();
        $domain = sanitize_text_field($request->get_param('domain'));

        $result = $this->add_domain($vendor_id, $domain);

        if (is_wp_error($result)) {
            return new WP_Error('domain_error', $result->get_error_message(), array('status' => 400));
        }

        return rest_ensure_response($result);
    }

    /**
     * REST API: Get domain
     */
    public function rest_get_domain($request) {
        $domain_id = intval($request->get_param('id'));
        $vendor_id = dokan_get_current_user_id();

        $domain_mapping = $this->get_domain_mapping($domain_id);
        if (!$domain_mapping || $domain_mapping->vendor_id != $vendor_id) {
            return new WP_Error('not_found', __('Domain not found.', 'dokan-vendor-domain-mapper'), array('status' => 404));
        }

        return rest_ensure_response($domain_mapping);
    }

    /**
     * REST API: Delete domain
     */
    public function rest_delete_domain($request) {
        $domain_id = intval($request->get_param('id'));
        $vendor_id = dokan_get_current_user_id();

        $domain_mapping = $this->get_domain_mapping($domain_id);
        if (!$domain_mapping || $domain_mapping->vendor_id != $vendor_id) {
            return new WP_Error('not_found', __('Domain not found.', 'dokan-vendor-domain-mapper'), array('status' => 404));
        }

        $result = $this->delete_domain($domain_id);

        if (is_wp_error($result)) {
            return new WP_Error('delete_error', $result->get_error_message(), array('status' => 400));
        }

        return rest_ensure_response($result);
    }

    /**
     * REST API: Verify domain
     */
    public function rest_verify_domain($request) {
        $domain_id = intval($request->get_param('id'));
        $vendor_id = dokan_get_current_user_id();

        $domain_mapping = $this->get_domain_mapping($domain_id);
        if (!$domain_mapping || $domain_mapping->vendor_id != $vendor_id) {
            return new WP_Error('not_found', __('Domain not found.', 'dokan-vendor-domain-mapper'), array('status' => 404));
        }

        $result = $this->verify_domain($domain_id);

        if (is_wp_error($result)) {
            return new WP_Error('verification_error', $result->get_error_message(), array('status' => 400));
        }

        return rest_ensure_response($result);
    }

    /**
     * Notify admin of new domain
     */
    private function notify_admin_new_domain($domain_id) {
        $domain_mapping = $this->get_domain_mapping($domain_id);
        if (!$domain_mapping) {
            return;
        }

        $admin_email = get_option('admin_email');
        $vendor = dokan_get_vendor_by_id($domain_mapping->vendor_id);
        $vendor_name = $vendor ? $vendor->get_name() : 'Unknown Vendor';

        $subject = sprintf(__('New Domain Request: %s', 'dokan-vendor-domain-mapper'), $domain_mapping->domain);
        $message = sprintf(
            __('A new domain mapping request has been submitted:

Vendor: %s
Domain: %s
Status: %s
Date: %s

Review the request at: %s', 'dokan-vendor-domain-mapper'),
            $vendor_name,
            $domain_mapping->domain,
            $domain_mapping->status,
            $domain_mapping->created_at,
            admin_url('admin.php?page=dokan-domain-mapping')
        );

        wp_mail($admin_email, $subject, $message);
    }

    /**
     * Notify vendor of domain approval
     */
    private function notify_vendor_domain_approved($domain_mapping) {
        $vendor = dokan_get_vendor_by_id($domain_mapping->vendor_id);
        if (!$vendor) {
            return;
        }

        $vendor_email = $vendor->get_email();
        $vendor_name = $vendor->get_name();

        $subject = sprintf(__('Domain Approved: %s', 'dokan-vendor-domain-mapper'), $domain_mapping->domain);
        $message = sprintf(
            __('Your domain mapping request has been approved!

Domain: %s
Status: %s
Date: %s

Your domain is now live and ready to use.', 'dokan-vendor-domain-mapper'),
            $domain_mapping->domain,
            $domain_mapping->status,
            $domain_mapping->updated_at
        );

        wp_mail($vendor_email, $subject, $message);
    }

    /**
     * Notify vendor of domain rejection
     */
    private function notify_vendor_domain_rejected($domain_mapping, $reason = '') {
        $vendor = dokan_get_vendor_by_id($domain_mapping->vendor_id);
        if (!$vendor) {
            return;
        }

        $vendor_email = $vendor->get_email();
        $vendor_name = $vendor->get_name();

        $subject = sprintf(__('Domain Rejected: %s', 'dokan-vendor-domain-mapper'), $domain_mapping->domain);
        $message = sprintf(
            __('Your domain mapping request has been rejected.

Domain: %s
Status: %s
Date: %s', 'dokan-vendor-domain-mapper'),
            $domain_mapping->domain,
            $domain_mapping->status,
            $domain_mapping->updated_at
        );

        if (!empty($reason)) {
            $message .= "\n\nReason: " . $reason;
        }

        wp_mail($vendor_email, $subject, $message);
    }
} 