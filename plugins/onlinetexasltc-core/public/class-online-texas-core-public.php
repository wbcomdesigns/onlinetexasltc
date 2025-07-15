<?php
/**
 * The public-facing functionality of the plugin.
 *
 * @link       https://wbcomdesigns.com/
 * @since      1.0.0
 *
 * @package    Online_Texas_Core
 * @subpackage Online_Texas_Core/public
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and hooks for how to
 * enqueue the public-facing stylesheet and JavaScript.
 *
 * @package    Online_Texas_Core
 * @subpackage Online_Texas_Core/public
 * @author     Wbcom Designs <admin@wbcomdesigns.com>
 */
class Online_Texas_Core_Public {

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
	 * @param    string    $plugin_name       The name of the plugin.
	 * @param    string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version = $version;
		
		// Ensure required functions are loaded
		$this->load_required_functions();
	}

	/**
	 * Load all required functions to ensure AJAX compatibility.
	 *
	 * @since 1.1.0
	 */
	private function load_required_functions() {
		if (!function_exists('create_single_vendor_product')) {
			$functions_file = ONLINE_TEXAS_CORE_PATH . 'includes/online-texas-general-functions.php';
			if (file_exists($functions_file)) {
				require_once $functions_file;
			}
		}
	}

	/**
	 * Register the stylesheets for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {
		wp_enqueue_style( 
			$this->plugin_name, 
			ONLINE_TEXAS_CORE_URL . 'public/css/online-texas-core-public.css', 
			array(), 
			$this->version, 
			'all' 
		);
	}

	/**
	 * Register the JavaScript for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {
		wp_enqueue_script( 
			$this->plugin_name, 
			ONLINE_TEXAS_CORE_URL . 'public/js/online-texas-core-public.js', 
			array( 'jquery' ), 
			$this->version, 
			false 
		);
		wp_localize_script( $this->plugin_name, 'online_texas',
			array( 
				'ajaxurl' => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce('duplicate_admin_product_nonce'),
			)
		);
	}

	/**
	 * Check if user has admin role.
	 *
	 * @since 1.1.0
	 * @param int $user_id User ID to check (optional, defaults to current user).
	 * @return bool True if user has admin role, false otherwise.
	 */
	private function user_has_admin_role($user_id = null) {
		if (!$user_id) {
			$user_id = get_current_user_id();
		}
		
		$user = get_user_by('ID', $user_id);
		if (!$user) {
			return false;
		}
		
		return in_array('administrator', $user->roles);
	}

	/**
	 * Check if user should see vendor features.
	 * Only show for sellers who are NOT administrators.
	 *
	 * @since 1.1.0
	 * @return bool True if user should see vendor features, false otherwise.
	 */
	private function should_show_vendor_features() {
		// Must be logged in
		if (!is_user_logged_in()) {
			return false;
		}
		
		// Must NOT be an administrator
		if ($this->user_has_admin_role()) {
			return false;
		}
		
		// Must be a Dokan seller
		if (!function_exists('dokan_is_user_seller') || !dokan_is_user_seller(get_current_user_id())) {
			return false;
		}
		
		return true;
	}

	/**
	 * Add the custom tab to vendor dashboard endpoint.
	 *
	 * @since 1.0.0
	 * @param array $query_vars Current query vars.
	 * @return array Modified query vars.
	 */
	public function add_admin_products_endpoint($query_vars) {
		if (!$this->should_show_vendor_features()) {
			return $query_vars;
		}

		$query_vars['source'] = 'source';
		$query_vars['paged'] = 'paged';
		return $query_vars;
	}

	/**
	 * Add the tab to dashboard navigation.
	 *
	 * @since 1.0.0
	 * @param array $urls Current dashboard URLs.
	 * @return array Modified dashboard URLs.
	 */
	public function add_admin_products_tab($urls) {
		if (!$this->should_show_vendor_features()) {
			return $urls;
		}

		$urls['source'] = array(
			'title' => __('Source', 'online-texas-core'),
			'icon'  => '<i class="fas fa-shopping-bag"></i>',
			'url'   => dokan_get_navigation_url('source'),
			'pos'   => 20
		);
		
		return $urls;
	}

	/**
	 * Add rewrite rule for the endpoint.
	 *
	 * @since 1.0.0
	 */
	public function add_admin_products_rewrite_rule() {
		add_rewrite_endpoint('source', EP_PAGES);
	}

	/**
	 * Handle the template for admin products page.
	 *
	 * @since 1.0.0
	 * @param array $query_vars Current query vars.
	 */
	public function load_admin_products_template( $query_vars ) {
		if ( isset( $query_vars['source'] ) ) {
			// Security check
			if (!$this->should_show_vendor_features()) {
				wp_die(__('Access denied - This feature is for vendors only, not administrators.', 'online-texas-core'));
			}

			// Ensure functions are loaded
			$this->load_required_functions();

        	require_once ONLINE_TEXAS_CORE_PATH . 'public/partials/online-texas-core-dokan-admin-products-template.php';
        }
	}

	/**
	 * Handle AJAX request for duplicating products.
	 *
	 * @since 1.0.0
	 */
	public function handle_duplicate_admin_product() {
		// Ensure functions are loaded
		$this->load_required_functions();
		
		// Security check
		if (!$this->should_show_vendor_features()) {
			wp_send_json_error('Access denied - This feature is for vendors only, not administrators');
		}

		// Verify nonce
		if (!wp_verify_nonce($_POST['nonce'], 'duplicate_admin_product_nonce')) {
			wp_send_json_error('Security check failed');
		}
		
		$product_id = intval($_POST['product_id']);
		$current_vendor_id = get_current_user_id();
		
		// Validate product exists
		if (!$product_id || !get_post($product_id)) {
			wp_send_json_error('Invalid product ID');
		}
		
		// CRITICAL: Check for recent duplicate attempts (prevent double-click/rapid requests)
		$duplicate_lock_key = 'duplicate_lock_' . $current_vendor_id . '_' . $product_id;
		if (get_transient($duplicate_lock_key)) {
			wp_send_json_error('Please wait before attempting to duplicate this product again');
		}
		
		// Set a 10-second lock to prevent rapid duplicate attempts
		set_transient($duplicate_lock_key, true, 10);
		
		$original_product = wc_get_product($product_id);
		
		if (!$original_product) {
			delete_transient($duplicate_lock_key); // Clean up lock on error
			wp_send_json_error('Product not found');
		}
		
		// Check if product belongs to admin (using role-based check)
		$product_author = get_post_field('post_author', $product_id);
		$product_author_user = get_user_by('ID', $product_author);
		
		$is_admin_product = false;
		if ($product_author == 0) {
			$is_admin_product = true;
		} elseif ($product_author_user && in_array('administrator', $product_author_user->roles)) {
			$is_admin_product = true;
		}
		
		if (!$is_admin_product) {
			delete_transient($duplicate_lock_key);
			wp_send_json_error('This is not an admin product');
		}

		// CRITICAL: Check if THIS VENDOR already duplicated this product
		$existing_duplicate = get_posts(array(
			'post_type' => 'product',
			'author' => $current_vendor_id,
			'meta_key' => '_parent_product_id',
			'meta_value' => $product_id,
			'posts_per_page' => 1,
			'fields' => 'ids',
			'post_status' => 'any' // Check all statuses
		));

		if (!empty($existing_duplicate)) {
			delete_transient($duplicate_lock_key);
			wp_send_json_error('You have already duplicated this product');
		}

		// Check product availability for vendor
		$available_for_vendors = get_post_meta($product_id, '_available_for_vendors', true);
		$restricted_vendors = get_post_meta($product_id, '_restricted_vendors', true);

		// If availability is not set, default based on course presence
		if (empty($available_for_vendors)) {
			$course_ids = $this->get_product_courses($product_id);
			$available_for_vendors = !empty($course_ids) ? 'yes' : 'no';
		}

		// Check if vendor is allowed to duplicate this product
		$can_duplicate = false;
		switch ($available_for_vendors) {
			case 'yes':
				$can_duplicate = true;
				break;
			case 'selective':
				$can_duplicate = is_array($restricted_vendors) && in_array($current_vendor_id, $restricted_vendors);
				break;
			case 'no':
			default:
				$can_duplicate = false;
				break;
		}

		if (!$can_duplicate) {
			delete_transient($duplicate_lock_key);
			wp_send_json_error('This product is not available for duplication');
		}

		// Get course IDs
		$course_ids = $this->get_product_courses($product_id);
		
		if (empty($course_ids)) {
			delete_transient($duplicate_lock_key);
			wp_send_json_error('No courses found for this product');
		}
		
		// FINAL CHECK: Double-check for duplicates just before creation
		$final_duplicate_check = get_posts(array(
			'post_type' => 'product',
			'author' => $current_vendor_id,
			'meta_key' => '_parent_product_id',
			'meta_value' => $product_id,
			'posts_per_page' => 1,
			'fields' => 'ids',
			'post_status' => 'any'
		));

		if (!empty($final_duplicate_check)) {
			delete_transient($duplicate_lock_key);
			wp_send_json_error('Product was already duplicated during this request');
		}
		
		// Duplicate the product
		try {
			$duplicated_product_id = create_single_vendor_product($product_id, $current_vendor_id, $course_ids);
			
			if ($duplicated_product_id) {
				// Success - extend the lock to prevent immediate re-duplication
				set_transient($duplicate_lock_key, true, 60); // 1 minute lock after success
				
				wp_send_json_success(array(
					'message' => 'Product duplicated successfully',
					'product_id' => $duplicated_product_id,
					'edit_url' => admin_url('post.php?post=' . $duplicated_product_id . '&action=edit')
				));
			} else {
				delete_transient($duplicate_lock_key);
				wp_send_json_error('Failed to duplicate product');
			}
		} catch (Exception $e) {
			delete_transient($duplicate_lock_key);
			wp_send_json_error('Failed to duplicate product: ' . $e->getMessage());
		}
	}

	/**
	 * Get courses linked to a product.
	 *
	 * @since 1.0.0
	 * @param int $post_id The product ID.
	 * @return array Array of course IDs.
	 */
	private function get_product_courses($post_id) {
		$linked_course = get_post_meta($post_id, '_related_course', true);
		if (!is_array($linked_course)) {
			$linked_course = !empty($linked_course) ? array($linked_course) : array();
		}

		$linked_groups = get_post_meta($post_id, '_related_group', true);
		
		if (!empty($linked_groups) && is_array($linked_groups)) {
			foreach ($linked_groups as $group_id) {
				if (function_exists('learndash_group_enrolled_courses')) {
					$group_courses = learndash_group_enrolled_courses($group_id);
					
					if (!empty($group_courses)) {
						if (is_array($group_courses)) {
							$linked_course = array_merge($linked_course, $group_courses);
						} else {
							$linked_course[] = $group_courses;
						}
					}
				}
			}
			
			$linked_course = array_unique(array_filter($linked_course));
		}

		return array_map('intval', array_filter($linked_course));
	}

	/**
	 * Handle AJAX request for fetching products list.
	 *
	 * @since 1.0.0
	 */
	public function fetch_products_lists_callback() {
		// Ensure functions are loaded
		$this->load_required_functions();
		
		// Security check
		if (!$this->should_show_vendor_features()) {
			wp_send_json_error('Access denied - This feature is for vendors only, not administrators');
		}

		// Verify nonce
		if (!wp_verify_nonce($_POST['nonce'], 'duplicate_admin_product_nonce')) {
			wp_send_json_error('Security check failed');
		}
		
		$page = ( isset( $_POST['page'] ) ) ? intval( sanitize_text_field( wp_unslash( $_POST['page'] ) ) ) : 1;
		$per_page = 20;
		
		// Get products using available function or inline method
		if (function_exists('get_admin_products_for_vendor')) {
			$products = get_admin_products_for_vendor($page, $per_page);
		} else {
			$products = $this->get_admin_products_inline($page, $per_page);
		}
		
		ob_start();
        require_once ONLINE_TEXAS_CORE_PATH . 'public/partials/online-texas-products-html.php';
		$products_listing = ob_get_clean();
		$pagination_html = '';
		$total_pages = $products->max_num_pages;
		if ($total_pages > 1) :
			$pagination_args = array(
				'base' => add_query_arg('paged', '%#%'),
				'format' => '',
				'current' => $page,
				'total' => $total_pages,
				'prev_text' => '<i class="fas fa-chevron-left"></i>',
				'next_text' => '<i class="fas fa-chevron-right"></i>',
				'type' => 'list',
				'end_size' => 3,
				'mid_size' => 3,
				'show_all' => false,
				'prev_next' => true,
			);

			$pagination_html = paginate_links($pagination_args);
		endif;

		if ($products && $products->have_posts()) {
			wp_send_json_success(array(
				'products_listing' => $products_listing,
				'pagination_html' => $pagination_html
			));
		}else{
			wp_send_json_error('No products found');
		}                          
	}

	/**
	 * Fallback method to get admin products for vendors.
	 *
	 * @since 1.1.0
	 * @param int $paged Page number for pagination.
	 * @param int $per_page Number of products per page.
	 * @return WP_Query Query object with filtered products.
	 */
	private function get_admin_products_inline($paged = 1, $per_page = 20) {
		// Get admin users
		$admin_users = get_users(array('role' => 'administrator', 'fields' => 'ID'));
		$admin_user_ids = !empty($admin_users) ? $admin_users : array(1);
		
		$args = array(
			'post_type' => 'product',
			'post_status' => 'publish',
			'author__in' => $admin_user_ids,
			'posts_per_page' => $per_page,
			'paged' => $paged,
			'meta_query' => array(
				'relation' => 'OR',
				array(
					'key' => '_related_course',
					'compare' => 'EXISTS'
				),
				array(
					'key' => '_related_group',
					'compare' => 'EXISTS'
				)
			)
		);

		return new WP_Query($args);
	}
}