<?php
/**
 * Admin Panel Class
 * 
 * Handles WordPress admin interface for domain mapping
 */

if (!defined('ABSPATH')) {
    exit;
}

class Dokan_Domain_Mapper_Admin {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('init', array($this, 'setup_admin_interface'));
    }

    /**
     * Setup admin interface
     */
    public function setup_admin_interface() {
        // Add admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Add admin scripts and styles
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
        // Add AJAX handlers
        add_action('wp_ajax_dokan_admin_approve_domain', array($this, 'process_ajax_approve_domain'));
        add_action('wp_ajax_dokan_admin_reject_domain', array($this, 'process_ajax_reject_domain'));
        add_action('wp_ajax_dokan_admin_delete_domain', array($this, 'process_ajax_delete_domain'));
        
        // Add settings
        add_action('admin_init', array($this, 'register_settings'));
        
        // Add admin notices
        add_action('admin_notices', array($this, 'display_admin_notices'));
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'dokan',
            __('Domain Mapping', 'dokan-vendor-domain-mapper'),
            __('Domain Mapping', 'dokan-vendor-domain-mapper'),
            'manage_options',
            'dokan-domain-mapping',
            array($this, 'display_domain_mapping_page')
        );

        add_submenu_page(
            'dokan',
            __('Domain Mapping Settings', 'dokan-vendor-domain-mapper'),
            __('Domain Settings', 'dokan-vendor-domain-mapper'),
            'manage_options',
            'dokan-domain-mapping-settings',
            array($this, 'display_settings_page')
        );
    }

    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'dokan-domain-mapping') === false) {
            return;
        }

        wp_enqueue_script(
            'dokan-domain-mapper-admin',
            DOKAN_DOMAIN_MAPPER_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            DOKAN_DOMAIN_MAPPER_VERSION,
            true
        );

        wp_enqueue_style(
            'dokan-domain-mapper-admin',
            DOKAN_DOMAIN_MAPPER_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            DOKAN_DOMAIN_MAPPER_VERSION
        );

        wp_localize_script('dokan-domain-mapper-admin', 'dokanDomainMapper', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('dokan_domain_mapper_admin_nonce'),
            'strings' => array(
                'confirm_approve' => __('Are you sure you want to approve this domain?', 'dokan-vendor-domain-mapper'),
                'confirm_reject' => __('Are you sure you want to reject this domain?', 'dokan-vendor-domain-mapper'),
                'confirm_delete' => __('Are you sure you want to delete this domain? This action cannot be undone.', 'dokan-vendor-domain-mapper'),
                'processing' => __('Processing...', 'dokan-vendor-domain-mapper'),
                'success' => __('Operation completed successfully.', 'dokan-vendor-domain-mapper'),
                'error' => __('An error occurred. Please try again.', 'dokan-vendor-domain-mapper')
            )
        ));
    }

    /**
     * Display domain mapping admin page
     */
    public function display_domain_mapping_page() {
        $domain_mapper = new Dokan_Domain_Mapper();
        
        // Handle bulk actions
        if (isset($_POST['action']) && isset($_POST['domain_ids'])) {
            $this->handle_bulk_actions();
        }

        // Get domain mappings
        $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
        $vendor_filter = isset($_GET['vendor_id']) ? intval($_GET['vendor_id']) : '';
        
        $args = array(
            'status' => $status_filter,
            'vendor_id' => $vendor_filter,
            'per_page' => 20,
            'page' => isset($_GET['paged']) ? intval($_GET['paged']) : 1
        );

        $domain_mappings = $domain_mapper->get_all_domain_mappings($args);
        
        // Get total count for pagination
        global $wpdb;
        $where_clause = '';
        $where_values = array();
        
        if (!empty($status_filter)) {
            $where_clause .= ' WHERE status = %s';
            $where_values[] = $status_filter;
        }
        
        if (!empty($vendor_filter)) {
            $where_clause .= empty($where_clause) ? ' WHERE' : ' AND';
            $where_clause .= ' vendor_id = %d';
            $where_values[] = $vendor_filter;
        }
        
        $total_query = "SELECT COUNT(*) FROM {$wpdb->prefix}dokan_domain_mappings" . $where_clause;
        if (!empty($where_values)) {
            $total_query = $wpdb->prepare($total_query, $where_values);
        }
        
        $total_mappings = $wpdb->get_var($total_query);
        $total_pages = ceil($total_mappings / $args['per_page']);

        include DOKAN_DOMAIN_MAPPER_PLUGIN_DIR . 'templates/admin/domain-mappings.php';
    }

    /**
     * Display settings page
     */
    public function display_settings_page() {
        include DOKAN_DOMAIN_MAPPER_PLUGIN_DIR . 'templates/admin/settings.php';
    }

    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('dokan_domain_mapper_settings', 'dokan_domain_mapper_enable_domain_mapping');
        register_setting('dokan_domain_mapper_settings', 'dokan_domain_mapper_require_dns_verification');
        register_setting('dokan_domain_mapper_settings', 'dokan_domain_mapper_require_admin_approval');
        register_setting('dokan_domain_mapper_settings', 'dokan_domain_mapper_max_domains_per_vendor');
        register_setting('dokan_domain_mapper_settings', 'dokan_domain_mapper_ssl_provider');
        register_setting('dokan_domain_mapper_settings', 'dokan_domain_mapper_proxy_server_enabled');
        register_setting('dokan_domain_mapper_settings', 'dokan_domain_mapper_proxy_server_url');
        register_setting('dokan_domain_mapper_settings', 'dokan_domain_mapper_cloudflare_integration');
        register_setting('dokan_domain_mapper_settings', 'dokan_domain_mapper_lets_encrypt_enabled');
        register_setting('dokan_domain_mapper_settings', 'dokan_domain_mapper_auto_ssl_renewal');
    }

    /**
     * Process bulk domain actions
     */
    private function process_bulk_domain_actions() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Access denied.', 'dokan-vendor-domain-mapper'));
        }

        check_admin_referer('dokan_domain_mapper_bulk_action', 'dokan_domain_mapper_nonce');

        $action = sanitize_text_field($_POST['action']);
        $domain_ids = array_map('intval', $_POST['domain_ids']);
        $domain_mapper = new Dokan_Domain_Mapper();

        $processed = 0;
        $errors = array();

        foreach ($domain_ids as $domain_id) {
            switch ($action) {
                case 'approve':
                    $result = $domain_mapper->approve_domain($domain_id);
                    if (is_wp_error($result)) {
                        $errors[] = sprintf(__('Failed to approve domain ID %d: %s', 'dokan-vendor-domain-mapper'), $domain_id, $result->get_error_message());
                    } else {
                        $processed++;
                    }
                    break;

                case 'reject':
                    $reason = isset($_POST['reject_reason']) ? sanitize_textarea_field($_POST['reject_reason']) : '';
                    $result = $domain_mapper->reject_domain($domain_id, $reason);
                    if (is_wp_error($result)) {
                        $errors[] = sprintf(__('Failed to reject domain ID %d: %s', 'dokan-vendor-domain-mapper'), $domain_id, $result->get_error_message());
                    } else {
                        $processed++;
                    }
                    break;

                case 'delete':
                    $result = $domain_mapper->delete_domain($domain_id);
                    if (is_wp_error($result)) {
                        $errors[] = sprintf(__('Failed to delete domain ID %d: %s', 'dokan-vendor-domain-mapper'), $domain_id, $result->get_error_message());
                    } else {
                        $processed++;
                    }
                    break;
            }
        }

        // Set admin notice
        $notice_type = empty($errors) ? 'success' : 'error';
        $notice_message = '';

        if ($processed > 0) {
            $notice_message .= sprintf(__('%d domain(s) processed successfully.', 'dokan-vendor-domain-mapper'), $processed);
        }

        if (!empty($errors)) {
            $notice_message .= ' ' . implode(' ', $errors);
        }

        set_transient('dokan_domain_mapper_admin_notice', array(
            'type' => $notice_type,
            'message' => $notice_message
        ), 30);

        // Redirect to prevent form resubmission
        wp_redirect(admin_url('admin.php?page=dokan-domain-mapping'));
        exit;
    }

    /**
     * Process AJAX request for approving domain
     */
    public function process_ajax_approve_domain() {
        check_ajax_referer('dokan_domain_mapper_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Access denied.', 'dokan-vendor-domain-mapper'));
        }

        $domain_id = intval($_POST['domain_id']);
        $domain_mapper = new Dokan_Domain_Mapper();

        $result = $domain_mapper->approve_domain($domain_id);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success($result);
        }
    }

    /**
     * Process AJAX request for rejecting domain
     */
    public function process_ajax_reject_domain() {
        check_ajax_referer('dokan_domain_mapper_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Access denied.', 'dokan-vendor-domain-mapper'));
        }

        $domain_id = intval($_POST['domain_id']);
        $reason = isset($_POST['reason']) ? sanitize_textarea_field($_POST['reason']) : '';
        $domain_mapper = new Dokan_Domain_Mapper();

        $result = $domain_mapper->reject_domain($domain_id, $reason);

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
        check_ajax_referer('dokan_domain_mapper_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Access denied.', 'dokan-vendor-domain-mapper'));
        }

        $domain_id = intval($_POST['domain_id']);
        $domain_mapper = new Dokan_Domain_Mapper();

        $result = $domain_mapper->delete_domain($domain_id);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success($result);
        }
    }

    /**
     * Display admin notices
     */
    public function display_admin_notices() {
        $notice = get_transient('dokan_domain_mapper_admin_notice');
        
        if ($notice) {
            $class = $notice['type'] === 'success' ? 'notice-success' : 'notice-error';
            ?>
            <div class="notice <?php echo esc_attr($class); ?> is-dismissible">
                <p><?php echo esc_html($notice['message']); ?></p>
            </div>
            <?php
            delete_transient('dokan_domain_mapper_admin_notice');
        }
    }

    /**
     * Get status badge HTML
     */
    public function get_status_badge($status) {
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
     * Get vendor name
     */
    public function get_vendor_name($vendor_id) {
        $vendor = dokan_get_vendor_by_id($vendor_id);
        if ($vendor) {
            return $vendor->get_name();
        }
        return __('Unknown Vendor', 'dokan-vendor-domain-mapper');
    }

    /**
     * Get vendor email
     */
    public function get_vendor_email($vendor_id) {
        $vendor = dokan_get_vendor_by_id($vendor_id);
        if ($vendor) {
            return $vendor->get_email();
        }
        return '';
    }

    /**
     * Get vendor store URL
     */
    public function get_vendor_store_url($vendor_id) {
        $vendor = dokan_get_vendor_by_id($vendor_id);
        if ($vendor) {
            return dokan_get_store_url($vendor_id);
        }
        return '';
    }

    /**
     * Get domain mapping details
     */
    public function get_domain_details($domain_mapping) {
        $details = array(
            'id' => $domain_mapping->id,
            'domain' => $domain_mapping->domain,
            'vendor_id' => $domain_mapping->vendor_id,
            'vendor_name' => $this->get_vendor_name($domain_mapping->vendor_id),
            'vendor_email' => $this->get_vendor_email($domain_mapping->vendor_id),
            'store_url' => $this->get_vendor_store_url($domain_mapping->vendor_id),
            'status' => $domain_mapping->status,
            'ssl_status' => $domain_mapping->ssl_status,
            'created_at' => $domain_mapping->created_at,
            'updated_at' => $domain_mapping->updated_at
        );

        return $details;
    }

    /**
     * Get domain mapping statistics
     */
    public function get_domain_statistics() {
        global $wpdb;

        $stats = array(
            'total' => 0,
            'pending' => 0,
            'verified' => 0,
            'approved' => 0,
            'rejected' => 0,
            'live' => 0
        );

        $results = $wpdb->get_results("
            SELECT status, COUNT(*) as count 
            FROM {$wpdb->prefix}dokan_domain_mappings 
            GROUP BY status
        ");

        foreach ($results as $result) {
            $stats[$result->status] = intval($result->count);
            $stats['total'] += intval($result->count);
        }

        return $stats;
    }

    /**
     * Export domain mappings
     */
    public function export_domain_mappings($format = 'csv') {
        if (!current_user_can('manage_options')) {
            wp_die(__('Access denied.', 'dokan-vendor-domain-mapper'));
        }

        $domain_mapper = new Dokan_Domain_Mapper();
        $mappings = $domain_mapper->get_all_domain_mappings(array('per_page' => 1000));

        if ($format === 'csv') {
            $this->export_csv($mappings);
        } elseif ($format === 'json') {
            $this->export_json($mappings);
        }
    }

    /**
     * Export as CSV
     */
    private function export_csv($mappings) {
        $filename = 'dokan-domain-mappings-' . date('Y-m-d') . '.csv';
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        
        // CSV headers
        fputcsv($output, array(
            'ID',
            'Domain',
            'Vendor ID',
            'Vendor Name',
            'Vendor Email',
            'Store URL',
            'Status',
            'SSL Status',
            'Created At',
            'Updated At'
        ));

        // CSV data
        foreach ($mappings as $mapping) {
            $details = $this->get_domain_details($mapping);
            fputcsv($output, array(
                $details['id'],
                $details['domain'],
                $details['vendor_id'],
                $details['vendor_name'],
                $details['vendor_email'],
                $details['store_url'],
                $details['status'],
                $details['ssl_status'],
                $details['created_at'],
                $details['updated_at']
            ));
        }

        fclose($output);
        exit;
    }

    /**
     * Export as JSON
     */
    private function export_json($mappings) {
        $filename = 'dokan-domain-mappings-' . date('Y-m-d') . '.json';
        
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $data = array();
        foreach ($mappings as $mapping) {
            $data[] = $this->get_domain_details($mapping);
        }
        
        echo json_encode($data, JSON_PRETTY_PRINT);
        exit;
    }
} 