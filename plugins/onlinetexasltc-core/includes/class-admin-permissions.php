<?php
/**
 * Admin Permissions for Vendor Code Generation
 *
 * @package    Online_Texas_Core
 * @subpackage Online_Texas_Core/includes
 * @since      1.0.0
 */

// Don't load this file directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin Permissions Class
 *
 * Handles admin controls for vendor code generation permissions
 */
class Online_Texas_Admin_Permissions {

    /**
     * Instance of this class
     */
    private static $instance = null;

    /**
     * Get single instance of this class
     */
    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        add_action('add_meta_boxes', array($this, 'add_vendor_permissions_meta_box'));
        add_action('save_post', array($this, 'save_vendor_permissions'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('wp_ajax_approve_vendor_code_request', array($this, 'approve_vendor_code_request'));
        add_action('wp_ajax_reject_vendor_code_request', array($this, 'reject_vendor_code_request'));
    }

    /**
     * Add meta box for vendor permissions
     */
    public function add_vendor_permissions_meta_box() {
        global $post;
        
        // Only show for automator_codes products
        if ($post && $post->post_type === 'product') {
            $product = wc_get_product($post->ID);
            if ($product && $product->get_type() === 'automator_codes') {
                add_meta_box(
                    'vendor_code_permissions',
                    __('Vendor Code Generation Permissions', 'online-texas-core'),
                    array($this, 'render_vendor_permissions_meta_box'),
                    'product',
                    'side',
                    'default'
                );
            }
        }
    }

    /**
     * Render vendor permissions meta box
     */
    public function render_vendor_permissions_meta_box($post) {
        wp_nonce_field('vendor_permissions_nonce', 'vendor_permissions_nonce');
        
        $max_codes = get_post_meta($post->ID, '_vendor_max_codes_per_product', true);
        $approval_required = get_post_meta($post->ID, '_vendor_code_approval_required', true);
        
        // Default values
        $max_codes = $max_codes ?: '50';
        $approval_required = $approval_required ?: 'no';
        
        ?>
        <div class="vendor-permissions-container">
            
            <p>
                <label for="vendor_max_codes_per_product">
                    <?php esc_html_e('Max codes per vendor:', 'online-texas-core'); ?>
                    <input type="number" id="vendor_max_codes_per_product" 
                           name="vendor_max_codes_per_product" value="<?php echo esc_attr($max_codes); ?>" 
                           min="1" max="1000" style="width: 100%;">
                </label>
            </p>
            
            <p>
                <label for="vendor_code_approval_required">
                    <input type="checkbox" id="vendor_code_approval_required" 
                           name="vendor_code_approval_required" value="yes" 
                           <?php checked($approval_required, 'yes'); ?>>
                    <?php esc_html_e('Require admin approval for code generation', 'online-texas-core'); ?>
                </label>
            </p>
            
            <div class="vendor-stats">
                <h4><?php esc_html_e('Vendor Statistics', 'online-texas-core'); ?></h4>
                <?php
                $vendor_stats = $this->get_vendor_stats_for_product($post->ID);
                if (!empty($vendor_stats)) {
                    echo '<ul>';
                    foreach ($vendor_stats as $vendor_id => $stats) {
                        $vendor = get_user_by('ID', $vendor_id);
                        $vendor_name = $vendor ? $vendor->display_name : 'Unknown Vendor';
                        echo '<li>';
                        echo '<strong>' . esc_html($vendor_name) . '</strong>: ';
                        echo esc_html($stats['codes_generated']) . '/' . esc_html($stats['max_codes']) . ' codes';
                        echo '</li>';
                    }
                    echo '</ul>';
                } else {
                    echo '<p>' . esc_html__('No vendors have generated codes for this product yet.', 'online-texas-core') . '</p>';
                }
                ?>
            </div>
        </div>
        <?php
    }

    /**
     * Save vendor permissions
     */
    public function save_vendor_permissions($post_id) {
        // Security checks
        if (!isset($_POST['vendor_permissions_nonce']) || 
            !wp_verify_nonce($_POST['vendor_permissions_nonce'], 'vendor_permissions_nonce')) {
            return;
        }
        
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Save permissions
        $enabled = isset($_POST['vendor_code_generation_enabled']) ? 'yes' : 'no';
        $max_codes = isset($_POST['vendor_max_codes_per_product']) ? 
            max(1, min(1000, intval($_POST['vendor_max_codes_per_product']))) : 50;
        $approval_required = isset($_POST['vendor_code_approval_required']) ? 'yes' : 'no';

        update_post_meta($post_id, '_vendor_max_codes_per_product', $max_codes);
        update_post_meta($post_id, '_vendor_code_approval_required', $approval_required);
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'woocommerce',
            __('Vendor Code Requests', 'online-texas-core'),
            __('Vendor Codes', 'online-texas-core'),
            'manage_woocommerce',
            'vendor-code-requests',
            array($this, 'render_admin_page')
        );
    }

    /**
     * Render admin page
     */
    public function render_admin_page() {
        $pending_requests = $this->get_pending_code_requests();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Vendor Code Generation Requests', 'online-texas-core'); ?></h1>
            
            <?php if (!empty($pending_requests)) : ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Vendor', 'online-texas-core'); ?></th>
                            <th><?php esc_html_e('Product', 'online-texas-core'); ?></th>
                            <th><?php esc_html_e('Codes Requested', 'online-texas-core'); ?></th>
                            <th><?php esc_html_e('Request Date', 'online-texas-core'); ?></th>
                            <th><?php esc_html_e('Actions', 'online-texas-core'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pending_requests as $request) : ?>
                            <tr>
                                <td><?php echo esc_html($request['vendor_name']); ?></td>
                                <td><?php echo esc_html($request['product_name']); ?></td>
                                <td><?php echo esc_html($request['codes_requested']); ?></td>
                                <td><?php echo esc_html($request['request_date']); ?></td>
                                <td>
                                    <button class="button button-primary approve-request" 
                                            data-request-id="<?php echo esc_attr($request['id']); ?>">
                                        <?php esc_html_e('Approve', 'online-texas-core'); ?>
                                    </button>
                                    <button class="button button-secondary reject-request" 
                                            data-request-id="<?php echo esc_attr($request['id']); ?>">
                                        <?php esc_html_e('Reject', 'online-texas-core'); ?>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else : ?>
                <div class="notice notice-info">
                    <p><?php esc_html_e('No pending code generation requests.', 'online-texas-core'); ?></p>
                </div>
            <?php endif; ?>
        </div>
        
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Handle approve requests
            $(document).on('click', '.approve-request', function(e) {
                e.preventDefault();
                
                var button = $(this);
                var requestId = button.data('request-id');
                
                if (confirm('<?php esc_html_e('Are you sure you want to approve this request?', 'online-texas-core'); ?>')) {
                    button.prop('disabled', true).text('<?php esc_html_e('Approving...', 'online-texas-core'); ?>');
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'approve_vendor_code_request',
                            request_id: requestId,
                            nonce: '<?php echo wp_create_nonce('approve_vendor_code_request'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                alert('<?php esc_html_e('Request approved and codes generated successfully!', 'online-texas-core'); ?>');
                                location.reload();
                            } else {
                                alert('<?php esc_html_e('Error:', 'online-texas-core'); ?> ' + response.data);
                                button.prop('disabled', false).text('<?php esc_html_e('Approve', 'online-texas-core'); ?>');
                            }
                        },
                        error: function(xhr, status, error) {
                            alert('<?php esc_html_e('An error occurred. Please try again.', 'online-texas-core'); ?>');
                            button.prop('disabled', false).text('<?php esc_html_e('Approve', 'online-texas-core'); ?>');
                        }
                    });
                }
            });
            
            // Handle reject requests
            $(document).on('click', '.reject-request', function(e) {
                e.preventDefault();
                
                var button = $(this);
                var requestId = button.data('request-id');
                
                if (confirm('<?php esc_html_e('Are you sure you want to reject this request?', 'online-texas-core'); ?>')) {
                    button.prop('disabled', true).text('<?php esc_html_e('Rejecting...', 'online-texas-core'); ?>');
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'reject_vendor_code_request',
                            request_id: requestId,
                            nonce: '<?php echo wp_create_nonce('reject_vendor_code_request'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                alert('<?php esc_html_e('Request rejected successfully!', 'online-texas-core'); ?>');
                                location.reload();
                            } else {
                                alert('<?php esc_html_e('Error:', 'online-texas-core'); ?> ' + response.data);
                                button.prop('disabled', false).text('<?php esc_html_e('Reject', 'online-texas-core'); ?>');
                            }
                        },
                        error: function(xhr, status, error) {
                            alert('<?php esc_html_e('An error occurred. Please try again.', 'online-texas-core'); ?>');
                            button.prop('disabled', false).text('<?php esc_html_e('Reject', 'online-texas-core'); ?>');
                        }
                    });
                }
            });
        });
        </script>
        <?php
    }

    /**
     * Get vendor stats for a product
     */
    public function get_vendor_stats_for_product( $product_id ) {
        global $wpdb;
        
        $stats = array();
        
        // Get all vendors who have duplicated this product
        $vendor_products = $wpdb->get_results( $wpdb->prepare(
            "SELECT p.post_author, p.ID 
             FROM {$wpdb->prefix}posts p 
             INNER JOIN {$wpdb->prefix}postmeta pm ON p.ID = pm.post_id 
             WHERE p.post_type = 'product' 
             AND pm.meta_key = '_parent_product_id' 
             AND pm.meta_value = %d",
            $product_id
        ) );
        
        foreach ( $vendor_products as $vendor_product ) {
            $vendor_id = $vendor_product->post_author;
            $codes_generated = get_post_meta( $vendor_product->ID, '_vendor_codes_generated', true ) ?: 0;
            $max_codes = get_post_meta( $product_id, '_vendor_max_codes_per_product', true ) ?: 50;
            
            $stats[$vendor_id] = array(
                'codes_generated' => $codes_generated,
                'max_codes' => $max_codes
            );
        }
        
        return $stats;
    }

    /**
     * Get pending code requests
     */
    public function get_pending_code_requests() {
        $requests = array();
        
        // Get stored requests from wp_options
        $stored_requests = get_option( 'vendor_code_requests', array() );
        
        // Debug: Log what we found
        error_log( 'Admin interface - Found ' . count( $stored_requests ) . ' stored requests' );
        error_log( 'Admin interface - Stored requests: ' . print_r( $stored_requests, true ) );
        
        if ( ! empty( $stored_requests ) ) {
            foreach ( $stored_requests as $request_id => $request_data ) {
                // Only show pending requests
                if ( $request_data['status'] === 'pending' ) {
                    // Get vendor name
                    $vendor = get_user_by( 'ID', $request_data['vendor_id'] );
                    $vendor_name = $vendor ? $vendor->display_name : 'Unknown Vendor';
                    
                    // Get product name
                    $product = wc_get_product( $request_data['product_id'] );
                    $product_name = $product ? $product->get_name() : 'Unknown Product';
                    
                    $requests[] = array(
                        'id' => $request_id,
                        'vendor_id' => $request_data['vendor_id'],
                        'vendor_name' => $vendor_name,
                        'product_id' => $request_data['product_id'],
                        'product_name' => $product_name,
                        'codes_requested' => $request_data['codes_requested'],
                        'expiry_date' => $request_data['expiry_date'],
                        'code_type' => $request_data['code_type'],
                        'request_date' => $request_data['request_date'],
                        'status' => $request_data['status']
                    );
                }
            }
        }
        

        return $requests;
    }

    /**
     * Get all code requests (for history)
     */
    public function get_all_code_requests() {
        $requests = array();
        
        // Get stored requests from wp_options
        $stored_requests = get_option( 'vendor_code_requests', array() );
        
        if ( ! empty( $stored_requests ) ) {
            foreach ( $stored_requests as $request_id => $request_data ) {
                // Get vendor name
                $vendor = get_user_by( 'ID', $request_data['vendor_id'] );
                $vendor_name = $vendor ? $vendor->display_name : 'Unknown Vendor';
                
                // Get product name
                $product = wc_get_product( $request_data['product_id'] );
                $product_name = $product ? $product->get_name() : 'Unknown Product';
                
                $requests[] = array(
                    'id' => $request_id,
                    'vendor_id' => $request_data['vendor_id'],
                    'vendor_name' => $vendor_name,
                    'product_id' => $request_data['product_id'],
                    'product_name' => $product_name,
                    'codes_requested' => $request_data['codes_requested'],
                    'expiry_date' => $request_data['expiry_date'],
                    'code_type' => $request_data['code_type'],
                    'request_date' => $request_data['request_date'],
                    'status' => $request_data['status'],
                    'approved_date' => isset( $request_data['approved_date'] ) ? $request_data['approved_date'] : '',
                    'rejected_date' => isset( $request_data['rejected_date'] ) ? $request_data['rejected_date'] : ''
                );
            }
        }
        
        return $requests;
    }

    /**
     * Approve vendor code request
     */
    public function approve_vendor_code_request() {
        // Security check
        if (!wp_verify_nonce($_POST['nonce'], 'approve_vendor_code_request')) {
            wp_send_json_error('Security check failed');
        }
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $request_id = sanitize_text_field($_POST['request_id']);
        
        // Get stored requests
        $stored_requests = get_option('vendor_code_requests', array());
        
        if (!isset($stored_requests[$request_id])) {
            wp_send_json_error('Request not found');
        }
        
        $request_data = $stored_requests[$request_id];
        
        // Generate codes for the vendor
        if (class_exists('Online_Texas_Vendor_Codes')) {
            $vendor_id = $request_data['vendor_id'];
            $admin_product_id = $request_data['product_id'];
            $codes_to_generate = $request_data['codes_requested'];
            $expiry_date = $request_data['expiry_date'];
            $code_type = $request_data['code_type'];
            
            // Get vendor product ID
            $vendor_product_id = self::get_vendor_product_id($vendor_id, $admin_product_id);
            
            if ($vendor_product_id) {
                // Debug: Log approval details
                error_log( 'Approving code request - Vendor: ' . $vendor_id . ', Product: ' . $vendor_product_id . ', Codes: ' . $codes_to_generate . ', Type: ' . $code_type . ', Expiry: ' . $expiry_date );
                
                try {
                    // Generate codes using the static method that handles expiry and code type
                    $result = Online_Texas_Vendor_Codes::generate_codes_for_vendor_static(
                        $vendor_id,
                        $vendor_product_id,
                        $codes_to_generate,
                        $expiry_date,
                        $code_type
                    );
                    

                    
                    if ($result) {
                        // Mark request as approved
                        $stored_requests[$request_id]['status'] = 'approved';
                        $stored_requests[$request_id]['approved_date'] = current_time('mysql');
                        $stored_requests[$request_id]['approved_by'] = get_current_user_id();
                        
                        update_option('vendor_code_requests', $stored_requests);
                        
                        // Send notification to vendor (optional)
                        $this->notify_vendor_of_approval($vendor_id, $request_data);
                        
                        wp_send_json_success('Request approved and codes generated successfully');
                    } else {
                        wp_send_json_error('Failed to generate codes - check error logs');
                    }
                } catch (Exception $e) {
                    wp_send_json_error('Error during approval: ' . $e->getMessage());
                }
            } else {
                wp_send_json_error('Vendor product not found');
            }
        } else {
            wp_send_json_error('Vendor codes system not available');
        }
    }

    /**
     * Reject vendor code request
     */
    public function reject_vendor_code_request() {
        // Security check
        if (!wp_verify_nonce($_POST['nonce'], 'reject_vendor_code_request')) {
            wp_send_json_error('Security check failed');
        }
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $request_id = sanitize_text_field($_POST['request_id']);
        
        // Get stored requests
        $stored_requests = get_option('vendor_code_requests', array());
        
        if (!isset($stored_requests[$request_id])) {
            wp_send_json_error('Request not found');
        }
        
        // Mark request as rejected
        $stored_requests[$request_id]['status'] = 'rejected';
        $stored_requests[$request_id]['rejected_date'] = current_time('mysql');
        $stored_requests[$request_id]['rejected_by'] = get_current_user_id();
        
        update_option('vendor_code_requests', $stored_requests);
        
        // Send notification to vendor (optional)
        $this->notify_vendor_of_rejection($stored_requests[$request_id]['vendor_id'], $stored_requests[$request_id]);
        
        wp_send_json_success('Request rejected');
    }

    /**
     * Notify vendor of approval
     */
    private function notify_vendor_of_approval($vendor_id, $request_data) {
        // TODO: Implement vendor notification (email, dashboard notification, etc.)
    }

    /**
     * Notify vendor of rejection
     */
    private function notify_vendor_of_rejection($vendor_id, $request_data) {
        // TODO: Implement vendor notification (email, dashboard notification, etc.)
    }

    /**
     * Check if vendor can generate codes for a product
     */
    public static function vendor_can_generate_codes($vendor_id, $product_id) {
        
        // Check if vendor has reached their limit
        $max_codes = get_post_meta($product_id, '_vendor_max_codes_per_product', true) ?: 50;
        
        // Get vendor's duplicated product
        $vendor_product_id = self::get_vendor_product_id($vendor_id, $product_id);
        
        if (!$vendor_product_id) {
            return false;
        }
        
        $codes_generated = get_post_meta($vendor_product_id, '_vendor_codes_generated', true) ?: 0;
        
        return $codes_generated < $max_codes;
    }

    /**
     * Get vendor's duplicated product ID
     */
    public static function get_vendor_product_id($vendor_id, $admin_product_id) {
        global $wpdb;
        
        $vendor_product_id = $wpdb->get_var($wpdb->prepare(
            "SELECT p.ID FROM {$wpdb->prefix}posts p 
             INNER JOIN {$wpdb->prefix}postmeta pm ON p.ID = pm.post_id 
             WHERE p.post_author = %d AND p.post_type = 'product' 
             AND pm.meta_key = '_parent_product_id' AND pm.meta_value = %d",
            $vendor_id, $admin_product_id
        ));
        
        return $vendor_product_id;
    }

    /**
     * Check if approval is required for a product
     */
    public static function approval_required($product_id) {
        return get_post_meta($product_id, '_vendor_code_approval_required', true) === 'yes';
    }

    /**
     * Get max codes allowed for a product
     */
    public static function get_max_codes($product_id) {
        return get_post_meta($product_id, '_vendor_max_codes_per_product', true) ?: 50;
    }
}

// Initialize the class
Online_Texas_Admin_Permissions::instance(); 