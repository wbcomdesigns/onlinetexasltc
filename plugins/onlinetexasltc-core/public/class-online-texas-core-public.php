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
		if (is_account_page()) {
			learndash_enqueue_course_grid_scripts();
		}
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
			if ($original_product && $original_product->get_type() === 'automator_codes') {
				$available_for_vendors = 'yes';
			} else {
				$course_ids = $this->get_product_courses($product_id);
				$available_for_vendors = !empty($course_ids) ? 'yes' : 'no';
			}
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
		if ($original_product && $original_product->get_type() === 'automator_codes') {
			$course_ids = [];
		} else {
			$course_ids = $this->get_product_courses($product_id);
			if (empty($course_ids)) {
				delete_transient($duplicate_lock_key);
				wp_send_json_error('No courses found for this product');
			}
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
				
				// Prompt for batch size if product is automator_codes
				$new_product_id = $duplicated_product_id;
				$original_product_id = $product_id;
				$product = wc_get_product( $new_product_id );
				if ( $product && $product->get_type() === 'automator_codes' ) {
					// For automator_codes products, create vendor-specific batch
					$original_product_id = $product_id; // This is the admin product ID
					$original_batch_id = get_post_meta( $original_product_id, 'codes_group_name', true );
					
					if ( $original_batch_id ) {
						// Create vendor-specific batch
						$batch_size = 10; // Default batch size for vendor
						if ( isset( $_POST['vendor_code_batch_size'] ) ) {
							$batch_size = max( 1, min( 20, intval( $_POST['vendor_code_batch_size'] ) ) );
						}
						
						// Create vendor batch
						$vendor_batch_id = Online_Texas_Vendor_Codes::create_vendor_batch( 
							$current_vendor_id, 
							$original_product_id, 
							$original_batch_id, 
							$batch_size 
						);
						
						if ( $vendor_batch_id ) {
							// Link the vendor's duplicated product to their own batch
							update_post_meta( $new_product_id, '_vendor_batch_id', $vendor_batch_id );
							
							// Generate initial codes for the vendor
							$codes_generated = Online_Texas_Vendor_Codes::generate_codes_for_vendor_batch( 
								$vendor_batch_id, 
								$batch_size, 
								$current_vendor_id 
							);
							
							if ( $codes_generated ) {
								// Update vendor's code count
								$current_count = get_post_meta( $new_product_id, '_vendor_codes_generated', true ) ?: 0;
								update_post_meta( $new_product_id, '_vendor_codes_generated', $current_count + $batch_size );
								
								// Trigger the parent batch recipe
								do_action( 'ulc_codes_group_generated', $vendor_batch_id, $batch_size );
							}
						}
					} else {
						// Fallback: if no batch exists on original product, create a new one
						$batch_size = 10;
						if ( isset( $_POST['vendor_code_batch_size'] ) ) {
							$batch_size = max( 1, min( 20, intval( $_POST['vendor_code_batch_size'] ) ) );
						}
						
						// Auto-generate a code group for this vendor product
						$vendor_id = get_current_user_id();
						// Validate vendor
						$vendor = get_user_by( 'ID', $vendor_id );
						$store_info = function_exists( 'dokan_get_store_info' ) ? dokan_get_store_info( $vendor_id ) : array();
						$store_name = ! empty( $store_info['store_name'] ) ? $store_info['store_name'] : $vendor->display_name;
						$group_args = [
							'group-name'            => $store_name . ' ' . current_time( 'mysql' ),
							'coupon-for'            => 'automator',
							'coupon-paid-unpaid'    => '',
							'coupon-prefix'         => '',
							'coupon-suffix'         => '',
							'coupon-amount'         => $batch_size,
							'coupon-max-usage'      => 1,
							'coupon-dash'           => '',
							'coupon-character-type' => [ 'numbers', 'uppercase-letters' ],
							'expiry-date'           => '',
							'expiry-time'           => '',
							'coupon-courses'        => [],
							'coupon-group'          => [],
							'product_id'            => $new_product_id,
						];
						$group_id = \uncanny_learndash_codes\Database::add_code_group_batch( $group_args );
						if ( $group_id ) {
							$codes = [];
							for ( $i = 0; $i < $batch_size; $i++ ) {
								$codes[] = strtoupper( wp_generate_password( 10, false, false ) );
							}
							$inserted = \uncanny_learndash_codes\Database::add_codes_to_batch( $group_id, $codes, [ 'generation_type' => 'manual', 'coupon_amount' => $batch_size ] );
							
							// Trigger the parent batch recipe with the same batch
							if ( $inserted ) {
								do_action( 'ulc_codes_group_generated', $group_id, $inserted );
							}
							
							// Link the group to the product (meta or as needed)
							update_post_meta( $new_product_id, '_vendor_batch_id', $group_id );
							// Assign _dokan_vendor_id meta to each code
							if ( class_exists( 'Online_Texas_Vendor_Codes' ) ) {
								$new_codes = \uncanny_learndash_codes\Database::get_coupons( $group_id );
								foreach ( $new_codes as $code_obj ) {
									Online_Texas_Vendor_Codes::add_code_meta( $code_obj->ID, '_dokan_vendor_id', $vendor_id );
								}
							}
						}
					}
				}

				wp_send_json_success(array(
					'message' => 'Product duplicated successfully' . 
    ( $product && $product->get_type() === 'automator_codes' ? 
        ' and automatically published for immediate code functionality' : '' ),
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
	 * Handle AJAX request for searching products by course.
	 *
	 * @since 1.0.0
	 */
	public function handle_search_products_by_course() {
		// Security check
		if (!$this->should_show_vendor_features()) {
			wp_send_json_error('Access denied - This feature is for vendors only, not administrators');
		}

		// Verify nonce
		if (!wp_verify_nonce($_POST['nonce'], 'search_products_by_course_nonce')) {
			wp_send_json_error('Security check failed');
		}
		
		$course_id = intval($_POST['course_id']);
		$current_vendor_id = get_current_user_id();
		
		// Validate course exists
		if (!$course_id || !get_post($course_id) || get_post_type($course_id) !== 'sfwd-courses') {
			wp_send_json_error('Invalid course ID');
		}
		
		try {
			// Get all admin products that are linked to this course
			$products = array();
			
			// Get admin user IDs
			$admin_users = get_users(array('role' => 'administrator'));
			$admin_user_ids = wp_list_pluck($admin_users, 'ID');
			$admin_user_ids[] = 0; // Include products with no author
			
			// Query for products linked to this course
			$args = array(
				'post_type' => 'product',
				'post_status' => 'publish',
				'author__in' => $admin_user_ids,
				'posts_per_page' => -1, // Get all products
				'meta_query' => array(
					'relation' => 'OR',
					array(
						'key' => '_related_course',
						'value' => $course_id,
						'compare' => 'LIKE'
					),
					array(
						'key' => '_related_group',
						'compare' => 'EXISTS'
					)
				)
			);
			
			$query = new WP_Query($args);
			
			if ($query->have_posts()) {
				while ($query->have_posts()) {
					$query->the_post();
					$product_id = get_the_ID();
					$product = wc_get_product($product_id);
					
					if ($product) {
						// Check if this product is actually linked to the course
						$linked_courses = fetch_course_from_product($product_id);
						
						if (in_array($course_id, $linked_courses)) {
							// Get course information for this product
							$courses = array();
							foreach ($linked_courses as $linked_course_id) {
								$course = get_post($linked_course_id);
								if ($course && get_post_type($linked_course_id) === 'sfwd-courses') {
									$courses[] = array(
										'id' => $linked_course_id,
										'name' => $course->post_title
									);
								}
							}
							
							// Check if vendor has already duplicated this product
							$already_duplicated = is_duplicated($product_id, $current_vendor_id);
							
							$products[] = array(
								'id' => $product_id,
								'name' => $product->get_name(),
								'type' => $product->get_type(),
								'price' => $product->get_regular_price() ? wc_price($product->get_regular_price()) : '',
								'sale_price' => $product->get_sale_price() ? wc_price($product->get_sale_price()) : '',
								'status' => ucfirst($product->get_status()),
								'short_description' => $product->get_short_description(),
								'thumbnail' => get_the_post_thumbnail_url($product_id, 'thumbnail'),
								'is_automator_codes' => $product->get_type() === 'automator_codes',
								'already_duplicated' => $already_duplicated,
								'courses' => $courses
							);
						}
					}
				}
				wp_reset_postdata();
			}
			
			// Sort products by name
			usort($products, function($a, $b) {
				return strcasecmp($a['name'], $b['name']);
			});
			
			wp_send_json_success(array(
				'products' => $products,
				'count' => count($products)
			));
			
		} catch (Exception $e) {
			wp_send_json_error('Failed to search products: ' . $e->getMessage());
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

	/**
	 * Function for adding the endpoint in the account page for my courses
	 *
	 * @return void
	 */
	public function add_learndash_courses_endpoint() {
		add_rewrite_endpoint('my-courses', EP_ROOT | EP_PAGES);
	}

	// Add My Courses to My Account menu
	public function add_learndash_courses_menu($items) {
		// Only show to users who are NOT admin, vendor, or instructor
		$user_id = get_current_user_id();
		$user = get_userdata($user_id);
		$roles = (array) ($user ? $user->roles : array());
		$is_admin = in_array('administrator', $roles);
		$is_vendor = in_array('seller', $roles) || in_array('vendor', $roles) || (function_exists('dokan_is_user_seller') && dokan_is_user_seller($user_id));
		$is_instructor = in_array('group_leader', $roles) || in_array('instructor', $roles);
		if ($is_admin || $is_vendor || $is_instructor) {
			return $items;
		}
		// Insert "My Courses" after "Dashboard" but before "Orders"
		$new_items = array();
		foreach($items as $key => $item) {
			$new_items[$key] = $item;
			if($key === 'dashboard') {
				$new_items['my-courses'] = __('My Courses', 'textdomain');
			}
		}
		return $new_items;
	}


	// Display LearnDash courses using shortcode
	public function learndash_courses_endpoint_content() {
		// Check if LearnDash is active
		if (!function_exists('learndash_user_get_enrolled_courses')) {
			echo '<div class="woocommerce-message woocommerce-message--info">';
			echo __('LearnDash is not active. Please activate LearnDash plugin.', 'textdomain');
			echo '</div>';
			return;
		}

		echo '<div class="learndash-my-courses-wrapper">';
		echo '<h3>' . __('My Courses', 'textdomain') . '</h3>';

		$user_id = get_current_user_id();
		$user = get_userdata($user_id);
		$roles = (array) ($user ? $user->roles : array());

		// Check user roles
		$is_admin = in_array('administrator', $roles);
		$is_vendor = in_array('seller', $roles) || in_array('vendor', $roles) ||
					(function_exists('dokan_is_user_seller') && dokan_is_user_seller($user_id));
		$is_instructor = in_array('group_leader', $roles) || in_array('instructor', $roles);

		// Admins, vendors, instructors can see all their authored courses
		if ($is_admin || $is_vendor || $is_instructor) {
			// Check if the user has authored courses
			$authored_courses = learndash_user_get_authored_courses($user_id);
			if (!empty($authored_courses)) {
				echo do_shortcode('[ld_course_list mycourses="true" progress_bar="true" num="6" show_thumbnail="true" col="3"]');
			} else {
				echo '<div class="woocommerce-info"<p style="margin-bottom: 0;">' . __('You haven\'t created any courses yet.', 'textdomain') . '</p></div>';
			}
		} else {
			// For students – show enrolled courses
			$enrolled_courses = learndash_user_get_enrolled_courses($user_id);
			if (!empty($enrolled_courses)) {
				echo do_shortcode('[ld_course_list mycourses="enrolled" progress_bar="true" num="6" show_thumbnail="true" col="3"]');
			} else {
				echo '<div class="woocommerce-info"><p style="margin-bottom: 0;">' . __('You are not enrolled in any courses yet.', 'textdomain') . '</p></div>';
			}
		}

		echo '</div>';
	}


	// Add custom query vars
	public function add_learndash_courses_query_vars($vars) {
		$vars[] = 'my-courses';
		return $vars;
	}
	
	/**
	 * Fetch vendor details for LearnDash notifications
	 * Usage: [ld_notifications field="vendor" show="name|email|phone|url|all"]
	 */
	public function wbcom_fetch_vendor_details($result, $atts, $data) {
		// Check if this is a vendor field request
		if (!isset($atts['field']) || 'vendor' !== $atts['field']) {
			return $result;
		}
		
		// Ensure we have required data
		if (!isset($data['course_id']) || !isset($data['user_id'])) {
			return $result;
		}
		
		$course_id = intval($data['course_id']);
		$user_id = intval($data['user_id']);
		
		// Get the specific group where user is enrolled AND contains the course
		$group_id = get_user_course_group($user_id, $course_id);
		if (empty($group_id)) {
			return $result;
		}
		
		// Get group leaders (vendors)
		$group_leaders = get_post_meta( $group_id, 'learndash_group_leaders', true );
		if (empty($group_leaders)) {
			return $result;
		}
		
		$vendor_id = $group_leaders[0]; // Use first leader as vendor
		$vendor_data = get_userdata($vendor_id);
		if (!$vendor_data) {
			return $result;
		}
		
		// Check what to show
		if (!isset($atts['show'])) {
			return $result;
		}
		
		$show = strtolower($atts['show']);
		
		switch ($show) {
			case 'name':
				$result = $vendor_data->display_name;
				break;
				
			case 'email':
				$result = $vendor_data->user_email;
				break;
				
			case 'phone':
				$phone = get_user_meta($vendor_id, 'phone', true);
				if (empty($phone)) {
					$phone = get_user_meta($vendor_id, 'billing_phone', true);
				}
				if (empty($phone)) {
					$phone = get_user_meta($vendor_id, 'user_phone', true);
				}
				
				// Check for vendor plugin specific phone fields
				if (empty($phone) && function_exists('dokan_get_store_info')) {
					$store_info = dokan_get_store_info($vendor_id);
					$phone = isset($store_info['phone']) ? $store_info['phone'] : '';
				}
				
				if (empty($phone) && defined('WCFM_TOKEN')) {
					$wcfm_profile = get_user_meta($vendor_id, 'wcfmmp_profile_settings', true);
					$phone = isset($wcfm_profile['phone']) ? $wcfm_profile['phone'] : '';
				}
				
				$result = !empty($phone) ? $phone : 'Not provided';
				break;
				
			case 'url':
			case 'website':
				$url = $vendor_data->user_url;
				if (empty($url)) {
					$url = get_user_meta($vendor_id, 'user_url', true);
				}
				if (empty($url)) {
					$url = get_user_meta($vendor_id, 'website', true);
				}
				
				// Check for vendor plugin specific URL fields
				if (empty($url) && function_exists('dokan_get_store_info')) {
					$url = dokan_get_store_url($vendor_id);
				}
				
				$result = !empty($url) ? $url : 'Not provided';
				break;
				
			case 'all':
			case 'info':
				$vendor_name = $vendor_data->display_name;
				$vendor_email = $vendor_data->user_email;
				
				// Get phone
				$vendor_phone = get_user_meta($vendor_id, 'phone', true);
				if (empty($vendor_phone)) {
					$vendor_phone = get_user_meta($vendor_id, 'billing_phone', true);
				}
				if (empty($vendor_phone)) {
					$vendor_phone = 'Not provided';
				}
				
				// Get URL
				$vendor_url = dokan_get_store_url($vendor_id);
				if (empty($vendor_url)) {
					$vendor_url = get_user_meta($vendor_id, 'website', true);
				}
				if (empty($vendor_url)) {
					$vendor_url = 'Not provided';
				}
				
				// Format output based on context (HTML or plain text)
				if (isset($atts['format']) && 'plain' === $atts['format']) {
					$result = "Vendor: {$vendor_name}\n";
					$result .= "Email: {$vendor_email}\n";
					$result .= "Phone: {$vendor_phone}\n";
					$result .= "Website: {$vendor_url}";
				} else {
					$result = "<div class='vendor-info'>";
					$result .= "<h4>Vendor Information</h4>";
					$result .= "<p><strong>Name:</strong> {$vendor_name}</p>";
					$result .= "<p><strong>Email:</strong> <a href='mailto:{$vendor_email}'>{$vendor_email}</a></p>";
					$result .= "<p><strong>Phone:</strong> {$vendor_phone}</p>";
					if ($vendor_url !== 'Not provided') {
						$result .= "<p><strong>Website:</strong> <a href='{$vendor_url}' target='_blank'>{$vendor_url}</a></p>";
					} else {
						$result .= "<p><strong>Website:</strong> {$vendor_url}</p>";
					}
					$result .= "</div>";
				}
				break;
				
			case 'store_name':
				// For vendor plugins that have store names
				$store_name = '';
				if (function_exists('dokan_get_store_info')) {
					$store_info = dokan_get_store_info($vendor_id);
					$store_name = isset($store_info['store_name']) ? $store_info['store_name'] : '';
				}
				
				if (empty($store_name) && defined('WCFM_TOKEN')) {
					$wcfm_profile = get_user_meta($vendor_id, 'wcfmmp_profile_settings', true);
					$store_name = isset($wcfm_profile['store_name']) ? $wcfm_profile['store_name'] : '';
				}
				
				$result = !empty($store_name) ? $store_name : $vendor_data->display_name;
				break;
				
			case 'address':
				$address = '';
				if (function_exists('dokan_get_store_info')) {
					$store_info = dokan_get_store_info($vendor_id);
					if (isset($store_info['address'])) {
						$addr = $store_info['address'];
						$address_parts = array_filter([
							$addr['street_1'] ?? '',
							$addr['street_2'] ?? '',
							$addr['city'] ?? '',
							$addr['state'] ?? '',
							$addr['zip'] ?? '',
							$addr['country'] ?? ''
						]);
						$address = implode(', ', $address_parts);
					}
				}
				
				$result = !empty($address) ? $address : 'Not provided';
				break;
				
			default:
				// For custom fields or unknown show values
				$custom_field = get_user_meta($vendor_id, $show, true);
				$result = !empty($custom_field) ? $custom_field : 'Not available';
				break;
		}
		
		return $result;
	}

	public function wbcom_show_shortcode_attributes($instructions, $shortcode_groupings){
		$instructions['complete_course']['[ld_notifications field="vendor" show="name"]'] = 'Display vendor name';
		$instructions['complete_course']['[ld_notifications field="vendor" show="email"]'] = 'Display vendor email';
		$instructions['complete_course']['[ld_notifications field="vendor" show="phone"]'] = 'Display vendor phone';
		$instructions['complete_course']['[ld_notifications field="vendor" show="url"]'] = 'Display vendor website';
		$instructions['complete_course']['[ld_notifications field="vendor" show="all"]'] = 'Display all vendor info (HTML format)';
		$instructions['complete_course']['[ld_notifications field="vendor" show="all" format="plain"]'] = 'Display all vendor info (plain text)';
		$instructions['complete_course']['[ld_notifications field="vendor" show="store_name"]'] = 'Display store name (for vendor plugins)';
		$instructions['complete_course']['[ld_notifications field="vendor" show="address"]'] = 'Display vendor address';
		return $instructions;
	}

}