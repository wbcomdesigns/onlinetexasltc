<?php
/**
 * Domain Transfer Class
 * 
 * Handles domain transfer functionality between vendors
 */

if (!defined('ABSPATH')) {
    exit;
}

class Dokan_Domain_Transfer {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('init', array($this, 'setup_domain_transfer_functionality'));
    }

    /**
     * Setup domain transfer functionality
     */
    public function setup_domain_transfer_functionality() {
        // Add AJAX handlers
        add_action('wp_ajax_dokan_transfer_domain', array($this, 'process_ajax_transfer_domain'));
        add_action('wp_ajax_dokan_request_domain_transfer', array($this, 'process_ajax_request_transfer'));
        add_action('wp_ajax_dokan_approve_domain_transfer', array($this, 'process_ajax_approve_transfer'));
        add_action('wp_ajax_dokan_reject_domain_transfer', array($this, 'process_ajax_reject_transfer'));
        
        // Add admin menu
        add_action('admin_menu', array($this, 'add_transfer_menu'));
    }

    /**
     * Add transfer menu
     */
    public function add_transfer_menu() {
        add_submenu_page(
            'dokan',
            __('Domain Transfers', 'dokan-vendor-domain-mapper'),
            __('Domain Transfers', 'dokan-vendor-domain-mapper'),
            'manage_options',
            'dokan-domain-transfers',
            array($this, 'transfer_page')
        );
    }

    /**
     * Transfer domain between vendors
     */
    public function transfer_domain($domain_id, $new_vendor_id, $reason = '') {
        global $wpdb;

        // Get domain mapping
        $domain_mapping = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}dokan_domain_mappings WHERE id = %d",
            $domain_id
        ));

        if (!$domain_mapping) {
            return new WP_Error('domain_not_found', __('Domain mapping not found.', 'dokan-vendor-domain-mapper'));
        }

        $old_vendor_id = $domain_mapping->vendor_id;

        // Check if new vendor exists
        $new_vendor = get_user_by('id', $new_vendor_id);
        if (!$new_vendor) {
            return new WP_Error('vendor_not_found', __('New vendor not found.', 'dokan-vendor-domain-mapper'));
        }

        // Check if new vendor is a Dokan vendor
        if (!dokan_is_seller_enabled($new_vendor_id)) {
            return new WP_Error('not_vendor', __('New owner must be a Dokan vendor.', 'dokan-vendor-domain-mapper'));
        }

        // Check domain limit for new vendor
        $domain_mapper = new Dokan_Domain_Mapper();
        if (!$domain_mapper->can_vendor_add_domain($new_vendor_id)) {
            return new WP_Error('limit_exceeded', __('New vendor has reached their domain limit.', 'dokan-vendor-domain-mapper'));
        }

        // Start transaction
        $wpdb->query('START TRANSACTION');

        try {
            // Update domain mapping
            $result = $wpdb->update(
                $wpdb->prefix . 'dokan_domain_mappings',
                array(
                    'vendor_id' => $new_vendor_id,
                    'updated_at' => current_time('mysql')
                ),
                array('id' => $domain_id),
                array('%d', '%s'),
                array('%d')
            );

            if ($result === false) {
                throw new Exception(__('Failed to update domain mapping.', 'dokan-vendor-domain-mapper'));
            }

            // Log transfer
            $this->log_transfer($domain_id, $old_vendor_id, $new_vendor_id, $reason);

            // Commit transaction
            $wpdb->query('COMMIT');

            // Trigger notifications
            do_action('dokan_domain_transferred', $domain_id, $old_vendor_id, $new_vendor_id);

            return array(
                'success' => true,
                'message' => sprintf(__('Domain transferred successfully to %s.', 'dokan-vendor-domain-mapper'), $new_vendor->display_name),
                'old_vendor_id' => $old_vendor_id,
                'new_vendor_id' => $new_vendor_id
            );

        } catch (Exception $e) {
            // Rollback transaction
            $wpdb->query('ROLLBACK');
            return new WP_Error('transfer_failed', $e->getMessage());
        }
    }

    /**
     * Request domain transfer
     */
    public function request_transfer($domain_id, $requesting_vendor_id, $reason = '') {
        global $wpdb;

        // Get domain mapping
        $domain_mapping = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}dokan_domain_mappings WHERE id = %d",
            $domain_id
        ));

        if (!$domain_mapping) {
            return new WP_Error('domain_not_found', __('Domain mapping not found.', 'dokan-vendor-domain-mapper'));
        }

        // Check if requesting vendor is different from current owner
        if ($domain_mapping->vendor_id == $requesting_vendor_id) {
            return new WP_Error('same_vendor', __('Cannot request transfer to same vendor.', 'dokan-vendor-domain-mapper'));
        }

        // Check if transfer request already exists
        $existing_request = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}dokan_domain_transfer_requests 
            WHERE domain_id = %d AND requesting_vendor_id = %d AND status = 'pending'",
            $domain_id,
            $requesting_vendor_id
        ));

        if ($existing_request) {
            return new WP_Error('request_exists', __('Transfer request already exists.', 'dokan-vendor-domain-mapper'));
        }

        // Insert transfer request
        $result = $wpdb->insert(
            $wpdb->prefix . 'dokan_domain_transfer_requests',
            array(
                'domain_id' => $domain_id,
                'current_vendor_id' => $domain_mapping->vendor_id,
                'requesting_vendor_id' => $requesting_vendor_id,
                'reason' => sanitize_textarea_field($reason),
                'status' => 'pending',
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ),
            array('%d', '%d', '%d', '%s', '%s', '%s', '%s')
        );

        if ($result === false) {
            return new WP_Error('request_failed', __('Failed to create transfer request.', 'dokan-vendor-domain-mapper'));
        }

        $request_id = $wpdb->insert_id;

        // Send notifications
        $this->notify_transfer_request($request_id);

        return array(
            'success' => true,
            'request_id' => $request_id,
            'message' => __('Transfer request submitted successfully.', 'dokan-vendor-domain-mapper')
        );
    }

    /**
     * Approve transfer request
     */
    public function approve_transfer_request($request_id) {
        global $wpdb;

        // Get transfer request
        $request = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}dokan_domain_transfer_requests WHERE id = %d",
            $request_id
        ));

        if (!$request) {
            return new WP_Error('request_not_found', __('Transfer request not found.', 'dokan-vendor-domain-mapper'));
        }

        if ($request->status !== 'pending') {
            return new WP_Error('invalid_status', __('Transfer request is not pending.', 'dokan-vendor-domain-mapper'));
        }

        // Perform transfer
        $result = $this->transfer_domain($request->domain_id, $request->requesting_vendor_id, $request->reason);

        if (is_wp_error($result)) {
            return $result;
        }

        // Update request status
        $wpdb->update(
            $wpdb->prefix . 'dokan_domain_transfer_requests',
            array(
                'status' => 'approved',
                'updated_at' => current_time('mysql')
            ),
            array('id' => $request_id),
            array('%s', '%s'),
            array('%d')
        );

        return array(
            'success' => true,
            'message' => __('Transfer request approved and domain transferred.', 'dokan-vendor-domain-mapper')
        );
    }

    /**
     * Reject transfer request
     */
    public function reject_transfer_request($request_id, $rejection_reason = '') {
        global $wpdb;

        // Get transfer request
        $request = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}dokan_domain_transfer_requests WHERE id = %d",
            $request_id
        ));

        if (!$request) {
            return new WP_Error('request_not_found', __('Transfer request not found.', 'dokan-vendor-domain-mapper'));
        }

        if ($request->status !== 'pending') {
            return new WP_Error('invalid_status', __('Transfer request is not pending.', 'dokan-vendor-domain-mapper'));
        }

        // Update request status
        $result = $wpdb->update(
            $wpdb->prefix . 'dokan_domain_transfer_requests',
            array(
                'status' => 'rejected',
                'rejection_reason' => sanitize_textarea_field($rejection_reason),
                'updated_at' => current_time('mysql')
            ),
            array('id' => $request_id),
            array('%s', '%s', '%s'),
            array('%d')
        );

        if ($result === false) {
            return new WP_Error('update_failed', __('Failed to update transfer request.', 'dokan-vendor-domain-mapper'));
        }

        // Send rejection notification
        $this->notify_transfer_rejection($request_id, $rejection_reason);

        return array(
            'success' => true,
            'message' => __('Transfer request rejected.', 'dokan-vendor-domain-mapper')
        );
    }

    /**
     * Get transfer requests
     */
    public function get_transfer_requests($args = array()) {
        global $wpdb;

        $defaults = array(
            'status' => '',
            'vendor_id' => 0,
            'limit' => 20,
            'offset' => 0
        );

        $args = wp_parse_args($args, $defaults);

        $where_conditions = array('1=1');
        $where_values = array();

        if ($args['status']) {
            $where_conditions[] = 'status = %s';
            $where_values[] = $args['status'];
        }

        if ($args['vendor_id']) {
            $where_conditions[] = '(current_vendor_id = %d OR requesting_vendor_id = %d)';
            $where_values[] = $args['vendor_id'];
            $where_values[] = $args['vendor_id'];
        }

        $where_clause = implode(' AND ', $where_conditions);

        $query = $wpdb->prepare(
            "SELECT tr.*, 
                    dm.domain,
                    cv.display_name as current_vendor_name,
                    rv.display_name as requesting_vendor_name
             FROM {$wpdb->prefix}dokan_domain_transfer_requests tr
             LEFT JOIN {$wpdb->prefix}dokan_domain_mappings dm ON tr.domain_id = dm.id
             LEFT JOIN {$wpdb->users} cv ON tr.current_vendor_id = cv.id
             LEFT JOIN {$wpdb->users} rv ON tr.requesting_vendor_id = rv.id
             WHERE {$where_clause}
             ORDER BY tr.created_at DESC
             LIMIT %d OFFSET %d",
            array_merge($where_values, array($args['limit'], $args['offset']))
        );

        return $wpdb->get_results($query);
    }

    /**
     * Log transfer
     */
    private function log_transfer($domain_id, $old_vendor_id, $new_vendor_id, $reason = '') {
        global $wpdb;

        $wpdb->insert(
            $wpdb->prefix . 'dokan_domain_transfer_logs',
            array(
                'domain_id' => $domain_id,
                'old_vendor_id' => $old_vendor_id,
                'new_vendor_id' => $new_vendor_id,
                'reason' => sanitize_textarea_field($reason),
                'transferred_by' => get_current_user_id(),
                'created_at' => current_time('mysql')
            ),
            array('%d', '%d', '%d', '%s', '%d', '%s')
        );
    }

    /**
     * Notify transfer request
     */
    private function notify_transfer_request($request_id) {
        global $wpdb;

        $request = $wpdb->get_row($wpdb->prepare(
            "SELECT tr.*, dm.domain, cv.user_email as current_vendor_email, rv.user_email as requesting_vendor_email
             FROM {$wpdb->prefix}dokan_domain_transfer_requests tr
             LEFT JOIN {$wpdb->prefix}dokan_domain_mappings dm ON tr.domain_id = dm.id
             LEFT JOIN {$wpdb->users} cv ON tr.current_vendor_id = cv.id
             LEFT JOIN {$wpdb->users} rv ON tr.requesting_vendor_id = rv.id
             WHERE tr.id = %d",
            $request_id
        ));

        if (!$request) {
            return;
        }

        // Notify current vendor
        $current_vendor_subject = sprintf(__('Domain Transfer Request: %s', 'dokan-vendor-domain-mapper'), $request->domain);
        $current_vendor_message = sprintf(
            __('Hello,

You have received a domain transfer request for %s.

Domain: %s
Requesting Vendor: %s
Reason: %s

Please review and approve or reject this request.

Best regards,
%s', 'dokan-vendor-domain-mapper'),
            $request->domain,
            $request->domain,
            get_user_by('id', $request->requesting_vendor_id)->display_name,
            $request->reason,
            get_bloginfo('name')
        );

        wp_mail($request->current_vendor_email, $current_vendor_subject, $current_vendor_message);
    }

    /**
     * Notify transfer rejection
     */
    private function notify_transfer_rejection($request_id, $rejection_reason) {
        global $wpdb;

        $request = $wpdb->get_row($wpdb->prepare(
            "SELECT tr.*, dm.domain, rv.user_email as requesting_vendor_email
             FROM {$wpdb->prefix}dokan_domain_transfer_requests tr
             LEFT JOIN {$wpdb->prefix}dokan_domain_mappings dm ON tr.domain_id = dm.id
             LEFT JOIN {$wpdb->users} rv ON tr.requesting_vendor_id = rv.id
             WHERE tr.id = %d",
            $request_id
        ));

        if (!$request) {
            return;
        }

        $subject = sprintf(__('Domain Transfer Request Rejected: %s', 'dokan-vendor-domain-mapper'), $request->domain);
        $message = sprintf(
            __('Hello,

Your domain transfer request for %s has been rejected.

Domain: %s
Rejection Reason: %s

Best regards,
%s', 'dokan-vendor-domain-mapper'),
            $request->domain,
            $request->domain,
            $rejection_reason,
            get_bloginfo('name')
        );

        wp_mail($request->requesting_vendor_email, $subject, $message);
    }

    /**
     * Transfer page
     */
    public function transfer_page() {
        $transfer_requests = $this->get_transfer_requests(array('status' => 'pending'));
        include DOKAN_DOMAIN_MAPPER_PLUGIN_DIR . 'templates/admin/domain-transfers.php';
    }

    /**
     * Process AJAX request for transferring domain
     */
    public function process_ajax_transfer_domain() {
        check_ajax_referer('dokan_domain_mapper_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions.', 'dokan-vendor-domain-mapper'));
        }

        $domain_id = intval($_POST['domain_id']);
        $new_vendor_id = intval($_POST['new_vendor_id']);
        $reason = sanitize_textarea_field($_POST['reason'] ?? '');

        $result = $this->transfer_domain($domain_id, $new_vendor_id, $reason);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success($result);
    }

    /**
     * Process AJAX request for requesting transfer
     */
    public function process_ajax_request_transfer() {
        check_ajax_referer('dokan_domain_mapper_vendor_nonce', 'nonce');

        $domain_id = intval($_POST['domain_id']);
        $requesting_vendor_id = dokan_get_current_user_id();
        $reason = sanitize_textarea_field($_POST['reason'] ?? '');

        $result = $this->request_transfer($domain_id, $requesting_vendor_id, $reason);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success($result);
    }

    /**
     * Process AJAX request for approving transfer
     */
    public function process_ajax_approve_transfer() {
        check_ajax_referer('dokan_domain_mapper_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions.', 'dokan-vendor-domain-mapper'));
        }

        $request_id = intval($_POST['request_id']);

        $result = $this->approve_transfer_request($request_id);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success($result);
    }

    /**
     * Process AJAX request for rejecting transfer
     */
    public function process_ajax_reject_transfer() {
        check_ajax_referer('dokan_domain_mapper_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions.', 'dokan-vendor-domain-mapper'));
        }

        $request_id = intval($_POST['request_id']);
        $rejection_reason = sanitize_textarea_field($_POST['rejection_reason'] ?? '');

        $result = $this->reject_transfer_request($request_id, $rejection_reason);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success($result);
    }
} 