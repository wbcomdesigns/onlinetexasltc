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
 * Defines the plugin name, version, and two examples hooks for how to
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

	// Add the custom tab to vendor dashboard
	public function add_admin_products_endpoint($query_vars) {
		// Only add endpoint for vendors
		if (!dokan_is_user_seller(get_current_user_id())) {
			return $query_vars;
		}

		$query_vars['source'] = 'source';
		$query_vars['paged'] = 'paged';
		return $query_vars;
	}

	// Add the tab to dashboard navigation
	public function add_admin_products_tab($urls) {
		// Only show tab for vendors
		if (!dokan_is_user_seller(get_current_user_id())) {
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

	// Add rewrite rule for the endpoint
	public function add_admin_products_rewrite_rule() {
		add_rewrite_endpoint('source', EP_PAGES);
	}

	// Handle the template for admin products page
	public function load_admin_products_template( $query_vars ) {
		if ( isset( $query_vars['source'] ) ) {
			// Security check: Only vendors should access this template
			if (!dokan_is_user_seller(get_current_user_id())) {
				wp_die(__('Access denied - This feature is for vendors only.', 'online-texas-core'));
			}

        	require_once ONLINE_TEXAS_CORE_PATH . 'public/partials/online-texas-core-dokan-admin-products-template.php';
        }
	}

	// Handle AJAX request for duplicating products
	public function handle_duplicate_admin_product() {
		// Verify nonce
		if (!wp_verify_nonce($_POST['nonce'], 'duplicate_admin_product_nonce')) {
			wp_die('Security check failed');
		}
		
		// Check if user is vendor
		if (!dokan_is_user_seller(get_current_user_id())) {
			wp_send_json_error('Access denied');
		}
		
		$product_id = intval($_POST['product_id']);
		$original_product = wc_get_product($product_id);
		
		if (!$original_product) {
			wp_send_json_error('Product not found');
		}
		
		// Check if product belongs to admin (user_id = 0 or admin user)
		$product_author = get_post_field('post_author', $product_id);
		$admin_users = get_users(array('role' => 'administrator'));
		$admin_user_ids = wp_list_pluck($admin_users, 'ID');
		
		if ($product_author != 0 && !in_array($product_author, $admin_user_ids)) {
			wp_send_json_error('This is not an admin product');
		}

		// Check product availability for vendor
		$available_for_vendors = get_post_meta($product_id, '_available_for_vendors', true);
		$restricted_vendors = get_post_meta($product_id, '_restricted_vendors', true);
		$current_vendor_id = get_current_user_id();

		// If availability is not set, default based on course presence
		if (empty($available_for_vendors)) {
			$course_ids = fetch_course_from_product($product_id);
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
			wp_send_json_error('This product is not available for duplication');
		}

		$course_ids = fetch_course_from_product( $product_id );
		
		// Duplicate the product
		$duplicated_product_id = create_single_vendor_product( $product_id, get_current_user_id(), $course_ids);
		
		if ($duplicated_product_id) {
			wp_send_json_success(array(
				'message' => 'Product duplicated successfully',
				'product_id' => $duplicated_product_id
			));
		} else {
			wp_send_json_error('Failed to duplicate product');
		}
	}

	public function fetch_products_lists_callback() {
		// Verify nonce
		if (!wp_verify_nonce($_POST['nonce'], 'duplicate_admin_product_nonce')) {
			wp_die('Security check failed');
		}
		
		// Check if user is vendor
		if (!dokan_is_user_seller(get_current_user_id())) {
			wp_send_json_error('Access denied');
		}
		
		$page = ( isset( $_POST['page'] ) ) ? intval( sanitize_text_field( wp_unslash( $_POST['page'] ) ) ) : 1;
		$per_page = 20;
		$products = get_admin_products_for_vendor($page, $per_page);
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
			wp_send_json_error('Failed to load products');
		}                          
	}
}