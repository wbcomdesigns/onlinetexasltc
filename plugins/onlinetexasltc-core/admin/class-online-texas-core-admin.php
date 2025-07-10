<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://https://wbcomdesigns.com/
 * @since      1.0.0
 *
 * @package    Online_Texas_Core
 * @subpackage Online_Texas_Core/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Online_Texas_Core
 * @subpackage Online_Texas_Core/admin
 * @author     Wbcom <admin@wbcomdesigns.com>
 */
class Online_Texas_Core_Admin {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of this plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;

	}

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Online_Texas_Core_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Online_Texas_Core_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/online-texas-core-admin.css', array(), $this->version, 'all' );

	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts($hook) {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Online_Texas_Core_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Online_Texas_Core_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */
		if ($hook === 'product_page_course-product-manager') {
            wp_enqueue_script('jquery');
        }
		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/online-texas-core-admin.js', array( 'jquery' ), $this->version, false );

	}

    /**
     * Using WooCommerce function (if WooCommerce is active)
     */
    private function is_wc_product_id($post_id) {
        if (function_exists('wc_get_product')) {
            $product = wc_get_product($post_id);
            return $product !== false;
        }
        return false;
    }

    private function fetch_course_from_product( $post_id ){
         // Check if course is linked
        $linked_course = ( get_post_meta($post_id, '_related_course', true) ) ? get_post_meta($post_id, '_related_course', true) : array();

        $linked_groups = get_post_meta($post_id, '_related_group', true);
        // Process linked groups and get their courses
        if (!empty($linked_groups) && is_array($linked_groups)) {
            foreach ($linked_groups as $key => $group_id) {
                // Get courses enrolled in this LearnDash group
                $group_courses = learndash_group_enrolled_courses($group_id);
                
                // If group has courses, add them to linked_course array
                if (!empty($group_courses)) {
                    if (is_array($group_courses)) {
                        $linked_course = array_merge($linked_course, $group_courses);
                    } else {
                        $linked_course[] = $group_courses;
                    }
                }
            }
            
            // Remove duplicates and empty values
            $linked_course = array_unique(array_filter($linked_course));
        }

        return $linked_course;
    }

    /**
     * Handle product save - create vendor products
     */
    public function online_texas_handle_product_save($post_id, $post) {
        // Skip if this is an autosave or revision
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if( ! $this->is_wc_product_id( $post_id ) ){
            return;
        }
        // Skip if this is a revision
        if (wp_is_post_revision($post_id)) {
            return;
        }
        // Only proceed for admin-created products
        if (!$this->online_texas_is_admin_product($post_id)) {
            return;
        }
        
        // Check if course is linked
       $linked_course = $this->fetch_course_from_product( $post_id );

        if (!$linked_course) {
            return;
        }
         
        if ($post->post_status == 'publish') {
            // Check if we've already processed this product
            $already_processed = get_post_meta($post_id, '_dli_vendor_products_created', true);

            if (!$already_processed) {
                $this->online_texas_create_vendor_products($post_id, $linked_course);
                update_post_meta($post_id, '_dli_vendor_products_created', time());
            }else{
                 $this->online_texas_sync_vendor_products($post_id, $post);
            }
        }
    }
    
    /**
     * Check if product is created by admin (not vendor)
     */
    private function online_texas_is_admin_product($product_id) {
        // Check if product has parent_product_id (vendor product)
        $parent_id = get_post_meta($product_id, '_parent_product_id', true);
        if ($parent_id) {
            return false;
        }
        
        // Check if author is admin
        $product = get_post($product_id);
        $author = get_user_by('ID', $product->post_author);
        
        return user_can($author, 'manage_options');
    }
    
    /**
     * Create vendor products for all active vendors
     */
    private function online_texas_create_vendor_products($admin_product_id, $course_ids) {
        // Get all active vendors
        $vendors = dokan_get_sellers();
        
        foreach ($vendors['users'] as $vendor) {
            // Check if vendor product already exists
            $existing_product = $this->online_texas_get_vendor_product($admin_product_id, $vendor->ID);
            if ($existing_product) {
                continue;
            }
            
            $this->online_texas_create_single_vendor_product($admin_product_id, $vendor->ID, $course_ids);
        }
    }
    
    /**
     * Create a single vendor product
     */
    private function online_texas_create_single_vendor_product($admin_product_id, $vendor_id, $course_ids) {
        $admin_product = wc_get_product($admin_product_id);
        
        if (!$admin_product) {
            return false;
        }
        
        // Get vendor info
        $vendor = get_user_by('ID', $vendor_id);
        $store_info = dokan_get_store_info($vendor_id);
        $store_name = isset($store_info['store_name']) ? $store_info['store_name'] : $vendor->display_name;
        
        // Duplicate the product using WooCommerce native function
        $duplicated_product = $this->online_texas_duplicate_product($admin_product_id);
        if (is_wp_error($duplicated_product)) {
            $this->online_texas_log_error('Failed to duplicate product: ' . $duplicated_product->get_error_message());
            return false;
        }
        
        // Update the duplicated product
        $vendor_product_title = $store_name . ' - ' . $admin_product->get_name();
        
        wp_update_post(array(
            'ID' => $duplicated_product->get_id(),
            'post_title' => $vendor_product_title,
            'post_name' => sanitize_title($vendor_product_title),
            'post_status' => 'draft',
            'post_author' => $vendor_id
        ));
        
        // Clear pricing to make it editable by vendor
        $duplicated_product->set_regular_price('');
        $duplicated_product->set_sale_price('');
        $duplicated_product->save();
        
        // Create LearnDash group
        $group_id = $this->online_texas_create_learndash_group($vendor_id, $course_ids, $vendor_product_title);
        
        // Save metadata
        update_post_meta($duplicated_product->get_id(), '_parent_product_id', $admin_product_id);
        update_post_meta($duplicated_product->get_id(), '_linked_ld_group_id', $group_id);
        update_post_meta($duplicated_product->get_id(), '_vendor_product_original_title', $admin_product->get_name());
        update_post_meta($duplicated_product->get_id(), '_cloned_on', current_time('mysql'));
        
        // Link product to LearnDash group
        if ($group_id) {
            update_post_meta($duplicated_product->get_id(), 'learndash_group_enrolled_groups', array($group_id));
        }
        
        $this->online_texas_debug_log("Created vendor product ID: {$duplicated_product->get_id()} for vendor: {$vendor_id}");
        
        return $duplicated_product->get_id();
    }
    
    /**
     * Duplicate product using WooCommerce native function
     */
    private function online_texas_duplicate_product($product_id) {
        $product = wc_get_product($product_id);
        if (!$product) {
            return new WP_Error('invalid_product', 'Invalid product ID');
        }
        
        // Use WooCommerce duplicate functionality
        if (class_exists('WC_Admin_Duplicate_Product')) {
            $duplicate_handler = new WC_Admin_Duplicate_Product();
            $duplicated_product = $duplicate_handler->product_duplicate($product);
            return $duplicated_product;
        } else {
            // Fallback manual duplication
            return $this->online_texas_manual_product_duplicate($product);
        }
    }
    
    /**
     * Manual product duplication fallback
     */
    private function online_texas_manual_product_duplicate($original_product) {
        $duplicate_data = array(
            'post_title' => $original_product->get_name(),
            'post_content' => $original_product->get_description(),
            'post_excerpt' => $original_product->get_short_description(),
            'post_status' => 'draft',
            'post_type' => 'product',
            'ping_status' => 'closed',
            'comment_status' => 'closed'
        );
        
        $duplicate_id = wp_insert_post($duplicate_data);
        
        if (is_wp_error($duplicate_id)) {
            return $duplicate_id;
        }
        
        // Copy product meta
        $meta_keys = get_post_meta($original_product->get_id());
        foreach ($meta_keys as $key => $values) {
            foreach ($values as $value) {
                add_post_meta($duplicate_id, $key, maybe_unserialize($value));
            }
        }
        
        return wc_get_product($duplicate_id);
    }
    
    /**
     * Handle new vendor creation
     */
    public function online_texas_handle_new_vendor($user_id, $dokan_settings) {
        $this->online_texas_create_products_for_new_vendor($user_id);
    }
    
    /**
     * Handle vendor being enabled/activated
     */
    public function online_texas_handle_vendor_enabled($seller_id) {
        $this->online_texas_create_products_for_new_vendor($seller_id);
    }
    
    /**
     * Create products for a new vendor from all existing admin products
     */
    private function online_texas_create_products_for_new_vendor($vendor_id) {
        // Get all admin products with linked courses
        $admin_products = get_posts(array(
            'post_type' => 'product',
            'meta_query' => array(
                array(
                    'key' => '_related_course',
                    'compare' => 'EXISTS'
                )
            ),
            'posts_per_page' => -1
        ));
        
        foreach ($admin_products as $admin_product) {
            // Check if this vendor already has a product for this admin product
            $existing_product = $this->online_texas_get_vendor_product($admin_product->ID, $vendor_id);
            if ($existing_product) {
                continue; // Skip if already exists
            }
            
            // Check if admin product author is actually an admin
            if (!$this->online_texas_is_admin_product($admin_product->ID)) {
                continue;
            }
            
            $course_ids = get_post_meta($admin_product->ID, '_related_course', true);
            if ($course_ids) {
                $this->online_texas_create_single_vendor_product($admin_product->ID, $vendor_id, $course_ids);
            }
        }
        
        $this->online_texas_debug_log("Created products for new vendor ID: {$vendor_id}");
    }
    
    /**
     * Sync all vendors with existing admin products
     */
    private function online_texas_sync_all_new_vendors() {
        $vendors = dokan_get_sellers();
        
        foreach ($vendors['users'] as $vendor) {
            $this->online_texas_create_products_for_new_vendor($vendor->ID);
        }
        
        $this->online_texas_debug_log("Synced products for all vendors");
    }
    
    private function online_texas_create_learndash_group($vendor_id, $course_ids, $vendor_product_title) {
        $course = get_post($course_ids);
        if (!$course) {
            return false;
        }
        
        $group_title = $vendor_product_title;
        
        // Create LearnDash group
        $group_data = array(
            'post_title' => $group_title,
            'post_type' => 'groups',
            'post_status' => 'publish',
            'post_author' => $vendor_id
        );
        
        $group_id = wp_insert_post($group_data);
        
        if (is_wp_error($group_id)) {
            $this->online_texas_log_error('Failed to create LearnDash group: ' . $group_id->get_error_message());
            return false;
        }
        
        // Link group to course
        $course_list = $course_ids;
        learndash_set_group_enrolled_courses($group_id, $course_list);
        
        // Set vendor as group leader
        $this->online_texas_set_group_leader($group_id, $vendor_id);
        
        $this->online_texas_debug_log("Created LearnDash group ID: {$group_id} for vendor: {$vendor_id}");
        
        return $group_id;
    }
    
    /**
     * Get existing vendor product
     */
    private function online_texas_get_vendor_product($admin_product_id, $vendor_id) {
        $args = array(
            'post_type' => 'product',
            'author' => $vendor_id,
            'meta_query' => array(
                array(
                    'key' => '_parent_product_id',
                    'value' => $admin_product_id,
                    'compare' => '='
                )
            ),
            'posts_per_page' => 1
        );
        
        $products = get_posts($args);
        return !empty($products) ? $products[0] : false;
    }
    
    /**
     * Sync vendor products when admin product is updated
     */
    public function online_texas_sync_vendor_products($post_id, $post) {
        
         // Skip if this is an autosave or revision
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        if (wp_is_post_revision($post_id)) {
            return;
        }

        if (!$this->online_texas_is_admin_product($post_id)) {
            return;
        }
    
        $this->online_texas_sync_all_vendor_products($post_id);

    }
    
    /**
     * Sync all vendor products for an admin product
     */
    private function online_texas_sync_all_vendor_products($admin_product_id) {
        // Get all vendor products
        $vendor_products = get_posts(array(
            'post_type' => 'product',
            'post_status' => 'any',
            'meta_query' => array(
                array(
                    'key' => '_parent_product_id',
                    'value' => $admin_product_id,
                    'compare' => '='
                )
            ),
            'posts_per_page' => -1
        ));
        
        $admin_product = wc_get_product($admin_product_id);
        if (!$admin_product) {
            return;
        }
            
        foreach ($vendor_products as $vendor_product_post) {
            $this->online_texas_sync_single_vendor_product($vendor_product_post->ID, $admin_product);
        }
    }
    
    /**
     * Sync a single vendor product
     */
    private function online_texas_sync_single_vendor_product($vendor_product_id, $admin_product) {
        $vendor_product = wc_get_product($vendor_product_id);

        if (!$vendor_product || ! $admin_product instanceof WC_Product) {
            return;
        }

        // Fields you do NOT want to sync
        $excluded_fields = array(
            'name', 'regular_price', 'sale_price', 'price', 'date_on_sale_from',
            'date_on_sale_to', 'total_sales', 'id', 'parent_id', 'sku',
            '_related_course', // custom meta
        );

        // Get all available setter methods from WC_Product
        $vendor_methods = get_class_methods($vendor_product);
        foreach ($vendor_methods as $method) {
            if (strpos($method, 'set_') === 0) {
                $field = substr($method, 4); // get field name after 'set_'
                if (in_array($field, $excluded_fields, true)) {
                    continue;
                }

                $getter = "get_{$field}";
                if (method_exists($admin_product, $getter)) {
                    $value = $admin_product->$getter();

                    // Sync the value
                    $vendor_product->$method($value);
                }
            }
        }

        // Sync course association (custom logic)
        $admin_courses = get_post_meta($admin_product->get_id(), '_related_course', true);
        if ($admin_courses) {
            $this->online_texas_update_vendor_course_association($vendor_product_id, $admin_courses);
        }

        // Save synced product
        $vendor_product->save();

        $this->online_texas_debug_log("Synced vendor product ID: {$vendor_product_id}");
    }
    
    /**
     * Update vendor course association
     */
    private function online_texas_update_vendor_course_association($vendor_product_id, $new_course_ids) {
        $group_id = get_post_meta($vendor_product_id, '_linked_ld_group_id', true);
        if ($group_id) {
            // Update group course association
            $course_list = $new_course_ids;
            learndash_set_group_enrolled_courses($group_id, $course_list);
        }
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'dokan',
            'LearnDash Integration',
            'LearnDash Integration',
            'manage_options',
            'dokan-learndash-integration',
            array($this, 'online_texas_admin_page')
        );
    }
    
    /**
     * Admin page
     */
    public function online_texas_admin_page() {
        ?>
        <div class="wrap">
            <h1>Dokan LearnDash Integration</h1>
            <h2>Debug Information</h2>
            
            <?php
            // Show statistics
            $admin_products = get_posts(array(
                'post_type' => 'product',
                'meta_query' => array(
                    array(
                        'key' => '_related_course',
                        'compare' => 'EXISTS'
                    )
                ),
                'posts_per_page' => -1
            ));
            
            $vendor_products = get_posts(array(
                'post_type' => 'product',
                'meta_query' => array(
                    array(
                        'key' => '_parent_product_id',
                        'compare' => 'EXISTS'
                    )
                ),
                'posts_per_page' => -1
            ));
            
            $groups = get_posts(array(
                'post_type' => 'groups',
                'posts_per_page' => -1
            ));
            ?>
            
            <div class="notice notice-info">
                <p><strong>Statistics:</strong></p>
                <ul>
                    <li>Admin Products with LearnDash Courses: <?php echo count($admin_products); ?></li>
                    <li>Vendor Products Generated: <?php echo count($vendor_products); ?></li>
                    <li>LearnDash Groups: <?php echo count($groups); ?></li>
                </ul>
            </div>
            
            <h3>Recent Activity</h3>
            <div id="debug-log">
                <?php $this->online_texas_show_debug_log(); ?>
            </div>
        </div>
        <?php
    }
    
    private function online_texas_log_error($message) {
        $log = get_option('dli_debug_log', array());
        $log[] = array(
            'timestamp' => current_time('mysql'),
            'message' => $message,
            'type' => 'error'
        );
        
        // Keep only last 50 entries
        if (count($log) > 50) {
            $log = array_slice($log, -50);
        }
        
        update_option('dli_debug_log', $log);
    }
    
    private function online_texas_show_debug_log() {
        $log = get_option('dli_debug_log', array());
        if (empty($log)) {
            echo '<p>No debug information available.</p>';
            return;
        }
        
        echo '<ul>';
        foreach (array_reverse($log) as $entry) {
            $class = $entry['type'] == 'error' ? 'error' : 'info';
            echo '<li class="' . $class . '">';
            echo '<strong>' . $entry['timestamp'] . ':</strong> ' . esc_html($entry['message']);
            echo '</li>';
        }
        echo '</ul>';
    }
    
    /**
     * Debug info in footer for admins
     */
    public function online_texas_debug_info() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        if (is_admin() && isset($_GET['page']) && $_GET['page'] == 'dokan-learndash-integration') {
            ?>
            <style>
                .error { color: red; }
                .info { color: blue; }
            </style>
            <?php
        }
    }

	  /**
     * Add product columns
     */
    public function online_texas_add_product_columns($columns) {
        $columns['course_link'] = 'Course Link';
        $columns['vendor_info'] = 'Vendor Info';
        return $columns;
    }
    
    /**
     * Populate product columns
     */
    public function online_texas_populate_product_columns($column, $post_id) {
        switch ($column) {
            case 'course_link':
                $course_ids = $this->fetch_course_from_product( $post_id );
                if ($course_ids) {
                    foreach ($course_ids as $key => $course_id) {
                        $course = get_post($course_id);
                        echo $course ? $course->post_title : 'Course not found';
                        echo '<br>';
                    }
                } else {
                    echo '—';
                }
                break;
                
            case 'vendor_info':
                $parent_id = get_post_meta($post_id, '_parent_product_id', true);
                if ($parent_id) {
                    $parent = get_post($parent_id);
                    echo 'Vendor Product<br>';
                    echo '<small>Parent: ' . ($parent ? $parent->post_title : 'Not found') . '</small>';
                } else {
                    $author_id = get_post_field('post_author', $post_id);
                    $vendor_products = $this->online_texas_get_vendor_product($post_id, $author_id);
                    if (!empty($vendor_products)) {
                        echo count($vendor_products) . ' vendor products';
                    } else {
                        echo '—';
                    }
                }
                break;
        }
    }

    /**
     * Debug logging
     */
    private function online_texas_debug_log($message) {
        $log = get_option('dli_debug_log', array());
        $log[] = array(
            'timestamp' => current_time('mysql'),
            'message' => $message,
            'type' => 'debug'
        );
        
        // Keep only last 50 entries
        if (count($log) > 50) {
            $log = array_slice($log, -50);
        }
        
        update_option('dli_debug_log', $log);
    }

    private function online_texas_set_group_leader($group_id, $user_id) {
        // Set user meta
        $current_leaders = learndash_get_groups_administrators_users($user_id);
        if (!is_array($current_leaders)) {
            $current_leaders = array();
        }
        
        if (!in_array($group_id, $current_leaders)) {
            $current_leaders[] = $group_id;
            update_user_meta($user_id, 'learndash_group_leaders_' . $group_id, $group_id);
        }
    }
}
