<?php
/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://wbcomdesigns.com/
 * @since      1.0.0
 *
 * @package    Online_Texas_Core
 * @subpackage Online_Texas_Core/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and hooks for admin area functionality
 * including product management, vendor synchronization, and admin interface.
 *
 * @package    Online_Texas_Core
 * @subpackage Online_Texas_Core/admin
 * @author     Wbcom Designs <admin@wbcomdesigns.com>
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
	 * @param    string    $plugin_name       The name of this plugin.
	 * @param    string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version = $version;
		
		// Declare HPOS compatibility
		add_action( 'before_woocommerce_init', array( $this, 'declare_hpos_compatibility' ) );
		
		// Add meta box for product vendor availability
		add_action( 'add_meta_boxes', array( $this, 'add_vendor_availability_meta_box' ) );
		add_action( 'save_post', array( $this, 'save_vendor_availability_meta' ), 10, 2 );
	}

	/**
	 * Declare compatibility with WooCommerce High-Performance Order Storage.
	 *
	 * @since 1.1.0
	 */
	public function declare_hpos_compatibility() {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}

	/**
	 * Add vendor availability meta box to product edit page.
	 *
	 * @since 1.1.0
	 */
	public function add_vendor_availability_meta_box() {
		add_meta_box(
			'otc_vendor_availability',
			__( 'Vendor Availability', 'online-texas-core' ),
			array( $this, 'render_vendor_availability_meta_box' ),
			'product',
			'side',
			'default'
		);
	}

	/**
	 * Render the vendor availability meta box.
	 *
	 * @since 1.1.0
	 * @param WP_Post $post The post object.
	 */
	public function render_vendor_availability_meta_box( $post ) {
		// Add nonce for security
		wp_nonce_field( 'otc_vendor_availability_nonce', 'otc_vendor_availability_nonce' );

		// Get current values
		$available_for_vendors = get_post_meta( $post->ID, '_available_for_vendors', true );
		$restricted_vendors = get_post_meta( $post->ID, '_restricted_vendors', true );
		
		// Get course count
		$course_ids = fetch_course_from_product( $post->ID );
		$has_courses = ! empty( $course_ids );

		// Get product type
		$product_type = '';
		if ( function_exists( 'wc_get_product' ) ) {
			$product = wc_get_product( $post->ID );
			if ( $product ) {
				$product_type = $product->get_type();
			}
		}

		// Get vendor product count
		global $wpdb;
		$vendor_count = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->posts} p 
			INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id 
			WHERE p.post_type = 'product' 
			AND pm.meta_key = '_parent_product_id' 
			AND pm.meta_value = %s",
			$post->ID
		) );

		// Set default based on course presence or automator_codes
		if ( empty( $available_for_vendors ) ) {
			if ( $product_type === 'automator_codes' ) {
				$available_for_vendors = 'yes';
			} else {
				$available_for_vendors = $has_courses ? 'yes' : 'no';
			}
		}

		if ( ! is_array( $restricted_vendors ) ) {
			$restricted_vendors = array();
		}

		?>
		<div id="otc-vendor-availability-options">
			<?php if ( $has_courses || $product_type === 'automator_codes' ) : ?>
				<?php if ( $product_type === 'automator_codes' ) : ?>
					<p><strong><?php esc_html_e( 'Codes Product:', 'online-texas-core' ); ?></strong> <?php esc_html_e( 'No course required.', 'online-texas-core' ); ?></p>
				<?php else : ?>
					<p><strong><?php esc_html_e( 'Linked Courses:', 'online-texas-core' ); ?></strong> <?php echo count( $course_ids ); ?></p>
				<?php endif; ?>
				<p><strong><?php esc_html_e( 'Vendor Products:', 'online-texas-core' ); ?></strong> <?php echo intval( $vendor_count ); ?></p>
				
				<p><label>
					<input type="radio" name="_available_for_vendors" value="yes" <?php checked( $available_for_vendors, 'yes' ); ?>>
					<?php esc_html_e( 'Available to All Vendors', 'online-texas-core' ); ?>
				</label></p>
				
				<p><label>
					<input type="radio" name="_available_for_vendors" value="selective" <?php checked( $available_for_vendors, 'selective' ); ?>>
					<?php esc_html_e( 'Available to Selected Vendors Only', 'online-texas-core' ); ?>
				</label></p>
				
				<p><label>
					<input type="radio" name="_available_for_vendors" value="no" <?php checked( $available_for_vendors, 'no' ); ?>>
					<?php esc_html_e( 'Not Available to Vendors', 'online-texas-core' ); ?>
				</label></p>

				<div id="otc-vendor-selection" style="<?php echo $available_for_vendors !== 'selective' ? 'display:none;' : ''; ?>">
					<p><strong><?php esc_html_e( 'Select Vendors:', 'online-texas-core' ); ?></strong></p>
					<?php $this->render_vendor_checklist( $restricted_vendors ); ?>
				</div>

				<div id="otc-sync-actions" style="margin-top: 15px;">
					<p><strong><?php esc_html_e( 'Sync Actions:', 'online-texas-core' ); ?></strong></p>
					<button type="button" class="button" id="otc-sync-to-all" data-product-id="<?php echo esc_attr( $post->ID ); ?>">
						<?php esc_html_e( 'Sync to All Vendors', 'online-texas-core' ); ?>
					</button>
					<button type="button" class="button" id="otc-sync-to-selected" data-product-id="<?php echo esc_attr( $post->ID ); ?>">
						<?php esc_html_e( 'Sync to Selected Vendors', 'online-texas-core' ); ?>
					</button>
					<p class="description"><?php printf( esc_html__( '%d vendors currently have this product', 'online-texas-core' ), intval( $vendor_count ) ); ?></p>
				</div>
			<?php else : ?>
				<p><?php esc_html_e( 'This product has no linked courses. Vendor availability is disabled.', 'online-texas-core' ); ?></p>
				<input type="hidden" name="_available_for_vendors" value="no">
			<?php endif; ?>
		</div>

		<script>
		jQuery(document).ready(function($) {
			$('input[name="_available_for_vendors"]').change(function() {
				if ($(this).val() === 'selective') {
					$('#otc-vendor-selection').show();
				} else {
					$('#otc-vendor-selection').hide();
				}
			});

			$('#otc-sync-to-all, #otc-sync-to-selected').click(function() {
				var productId = $(this).data('product-id');
				var syncType = $(this).attr('id') === 'otc-sync-to-all' ? 'all' : 'selected';
				var $button = $(this);
				var originalText = $button.text();
				
				$button.prop('disabled', true).text('Syncing...');
				
				$.post(ajaxurl, {
					action: 'otc_sync_product_to_vendors',
					product_id: productId,
					sync_type: syncType,
					nonce: '<?php echo wp_create_nonce( 'otc_sync_nonce' ); ?>'
				}, function(response) {
					if (response.success) {
						alert(response.data.message);
					} else {
						alert('Error: ' + response.data.message);
					}
				}).always(function() {
					$button.prop('disabled', false).text(originalText);
				});
			});
		});
		</script>

		<style>
		#otc-vendor-availability-options label {
			display: block;
			margin-bottom: 5px;
		}
		#otc-vendor-selection {
			border: 1px solid #ddd;
			padding: 10px;
			margin-top: 10px;
			max-height: 150px;
			overflow-y: auto;
		}
		#otc-vendor-selection label {
			margin-bottom: 3px;
			font-weight: normal;
		}
		#otc-sync-actions button {
			margin-right: 5px;
			margin-bottom: 5px;
		}
		</style>
		<?php
	}

	/**
	 * Render vendor checklist for selective availability.
	 *
	 * @since 1.1.0
	 * @param array $selected_vendors Array of selected vendor IDs.
	 */
	private function render_vendor_checklist( $selected_vendors ) {
		if ( ! function_exists( 'dokan_get_sellers' ) ) {
			echo '<p>' . esc_html__( 'Dokan not available', 'online-texas-core' ) . '</p>';
			return;
		}

		$vendors = dokan_get_sellers( array( 'status' => 'approved' ) );
		
		if ( empty( $vendors['users'] ) ) {
			echo '<p>' . esc_html__( 'No active vendors found', 'online-texas-core' ) . '</p>';
			return;
		}

		foreach ( $vendors['users'] as $vendor ) {
			$checked = in_array( $vendor->ID, $selected_vendors ) ? 'checked' : '';
			echo '<label>';
			echo '<input type="checkbox" name="_restricted_vendors[]" value="' . esc_attr( $vendor->ID ) . '" ' . $checked . '>';
			echo esc_html( $vendor->display_name ) . ' (' . esc_html( $vendor->user_email ) . ')';
			echo '</label>';
		}
	}

	/**
	 * Save vendor availability meta data.
	 *
	 * @since 1.1.0
	 * @param int     $post_id The post ID.
	 * @param WP_Post $post    The post object.
	 */
	public function save_vendor_availability_meta( $post_id, $post ) {
		// Security checks
		if ( ! isset( $_POST['otc_vendor_availability_nonce'] ) || 
			 ! wp_verify_nonce( $_POST['otc_vendor_availability_nonce'], 'otc_vendor_availability_nonce' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( $post->post_type !== 'product' ) {
			return;
		}

		// Save availability setting
		$available_for_vendors = sanitize_text_field( $_POST['_available_for_vendors'] ?? 'no' );
		$allowed_values = array( 'yes', 'no', 'selective' );
		if ( ! in_array( $available_for_vendors, $allowed_values ) ) {
			$available_for_vendors = 'no';
		}
		update_post_meta( $post_id, '_available_for_vendors', $available_for_vendors );

		// Save restricted vendors
		$restricted_vendors = array();
		if ( $available_for_vendors === 'selective' && isset( $_POST['_restricted_vendors'] ) ) {
			$restricted_vendors = array_map( 'intval', $_POST['_restricted_vendors'] );
		}
		update_post_meta( $post_id, '_restricted_vendors', $restricted_vendors );

		$vendors = dokan_get_sellers( array( 'status' => 'approved' ) );
		foreach ( $vendors['users'] as $vendor ) {
		$vendor_id = $vendor->ID;
		$get_restricted_products = get_user_meta($vendor_id, 'admin_restricted_products', true) ?? array();
		
		// Ensure it's always an array
		if (!is_array($get_restricted_products)) {
			$get_restricted_products = array();
		}
		
		if( 'no' === $available_for_vendors ){
			// Add to restriction list if not already present
			if( !in_array($post_id, $get_restricted_products) ){
				$get_restricted_products[] = $post_id;
			}
		} elseif( 'selective' === $available_for_vendors ){
			// Add to restriction list if vendor is NOT in allowed list
			if( !in_array($vendor_id, $restricted_vendors) ){
				if( !in_array($post_id, $get_restricted_products) ){
					$get_restricted_products[] = $post_id;
				}
			} else {
				// Remove from restriction list if vendor IS in allowed list
				$key = array_search($post_id, $get_restricted_products);
				if ($key !== false) {
					unset($get_restricted_products[$key]);
					$get_restricted_products = array_values($get_restricted_products); // Re-index array
				}
			}
		} else {
			// For 'yes' or any other value, remove from restriction list
			$key = array_search($post_id, $get_restricted_products);
			if ($key !== false) {
				unset($get_restricted_products[$key]);
				$get_restricted_products = array_values($get_restricted_products); // Re-index array
			}
		}
		update_user_meta($vendor_id, 'admin_restricted_products', $get_restricted_products);
	}
	}

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    1.0.0
	 * @param    string    $hook    The current admin page hook.
	 */
	public function enqueue_styles( $hook ) {
		// Only load on our admin pages
		if ( strpos( $hook, 'online-texas-core' ) === false && strpos( $hook, 'edit.php' ) === false && strpos( $hook, 'post.php' ) === false ) {
			return;
		}

		wp_enqueue_style( 
			$this->plugin_name, 
			ONLINE_TEXAS_CORE_URL . 'admin/css/online-texas-core-admin.css', 
			array(), 
			$this->version, 
			'all' 
		);
	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.0.0
	 * @param    string    $hook    The current admin page hook.
	 */
	public function enqueue_scripts( $hook ) {
		// Only load on our admin pages
		if ( strpos( $hook, 'online-texas-core' ) === false && strpos( $hook, 'post.php' ) === false ) {
			return;
		}

		wp_enqueue_script( 
			$this->plugin_name, 
			ONLINE_TEXAS_CORE_URL . 'admin/js/online-texas-core-admin.js', 
			array( 'jquery' ), 
			$this->version, 
			false 
		);

		// Localize script for AJAX
		wp_localize_script( $this->plugin_name, 'otc_ajax', array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( 'otc_nonce' ),
			'strings' => array(
				'syncing' => esc_html__( 'Syncing...', 'online-texas-core' ),
				'sync_success' => esc_html__( 'Sync completed successfully', 'online-texas-core' ),
				'sync_error' => esc_html__( 'Sync failed. Please try again.', 'online-texas-core' ),
				'confirm_clear_log' => esc_html__( 'Are you sure you want to clear the debug log?', 'online-texas-core' )
			)
		));
	}

	/**
	 * Check if a post ID corresponds to a WooCommerce product.
	 *
	 * @since 1.0.0
	 * @param int $post_id The post ID to check.
	 * @return bool True if it's a WooCommerce product, false otherwise.
	 */
	private function is_wc_product( $post_id ) {
		if ( ! function_exists( 'wc_get_product' ) ) {
			return false;
		}

		$product = wc_get_product( $post_id );
		return $product !== false;
	}

	/**
	 * Handle product save and trigger vendor product synchronization.
	 *
	 * @since 1.0.0
	 * @param int     $post_id The post ID being saved.
	 * @param WP_Post $post    The post object being saved.
	 */
	public function save_product( $post_id, $post ) {
		// Early exits for performance
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		// Check if plugin functionality is enabled
		$options = get_option( 'otc_options', array() );
		if ( empty( $options['plugin_enabled'] ) ) {
			return; // Plugin functionality disabled
		}

		if ( ! check_dependencies() ) {
			return;
		}

		// Only process WooCommerce products
		if ( ! $this->is_wc_product( $post_id ) ) {
			return;
		}

		// Only proceed for admin-created products
		if ( ! $this->is_admin_product( $post_id ) ) {
			return;
		}

		// Check if product has linked courses
		$linked_courses = fetch_course_from_product( $post_id );
		if ( empty( $linked_courses ) ) {
			return;
		}

		// Only process published products
		if ( $post->post_status !== 'publish' ) {
			return;
		}

		// Prevent infinite loops
		if ( get_transient( 'otc_processing_product_' . $post_id ) ) {
			return;
		}

		set_transient( 'otc_processing_product_' . $post_id, true, 30 );

		try {
			log_debug( "Processing product save for ID: {$post_id}" );

			// Check if we've already processed this product
			$already_processed = get_post_meta( $post_id, '_otc_vendor_products_created', true );
			
			// Use the auto_create_for_new_vendors option
			$enable_autosave = ! empty( $options['auto_create_for_new_vendors'] );
			
			if ( ! $already_processed && $enable_autosave ) {
				// First time - create vendor products
				$created_count = $this->create_vendor_products( $post_id, $linked_courses );
				if ( $created_count > 0 ) {
					update_post_meta( $post_id, '_otc_vendor_products_created', current_time( 'mysql' ) );
					log_debug( "Created {$created_count} vendor products for admin product {$post_id}" );
				}
			} else {
				// Update existing vendor products (excludes pricing)
				$this->sync_vendor_products( $post_id );
			}
		} catch ( Exception $e ) {
			log_debug( "Error processing product save: " . $e->getMessage(), 'error' );
		} finally {
			delete_transient( 'otc_processing_product_' . $post_id );
		}
	}

	/**
	 * Handle vendor product updates to sync their group price.
	 * Only syncs from vendor product to vendor group (vendor controls pricing).
	 *
	 * @since 1.1.0
	 * @param int     $post_id The post ID being saved.
	 * @param WP_Post $post    The post object being saved.
	 */
	public function handle_vendor_product_update( $post_id, $post ) {
		// Early exits for performance
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		// Only process WooCommerce products
		if ( ! $this->is_wc_product( $post_id ) ) {
			return;
		}

		// Check if this is a vendor product (has parent_product_id)
		$parent_id = get_post_meta( $post_id, '_parent_product_id', true );
		if ( ! $parent_id ) {
			return; // Not a vendor product, so skip
		}

		// Get the linked group ID
		$group_id = get_post_meta( $post_id, '_linked_ld_group_id', true );
		if ( ! $group_id ) {
			return; // No linked group
		}

		// Sync vendor product price to their group
		$this->sync_vendor_product_to_group( $post_id, $group_id );

		// Always update the product URL in case permalink changed
		$this->update_group_product_url( $group_id, $post_id );
	}

	/**
	 * Sync vendor product price to their group price.
	 * This allows vendors to control pricing for their own products and groups.
	 *
	 * @since 1.1.0
	 * @param int $vendor_product_id The vendor product ID.
	 * @param int $group_id          The group ID.
	 */
	private function sync_vendor_product_to_group( $vendor_product_id, $group_id ) {
		// Get vendor product price
		$vendor_product = wc_get_product( $vendor_product_id );
		if ( ! $vendor_product ) {
			return;
		}

		$product_price = $vendor_product->get_regular_price();
		if ( empty( $product_price ) ) {
			$product_price = $vendor_product->get_sale_price();
		}
		if ( empty( $product_price ) ) {
			$product_price = $vendor_product->get_price();
		}

		// Get current group settings
		$groups_settings = get_post_meta( $group_id, '_groups', true );
		if ( ! is_array( $groups_settings ) ) {
			$groups_settings = array();
		}

		// Update group price to match vendor product price
		$old_price = isset( $groups_settings['groups_group_price'] ) ? $groups_settings['groups_group_price'] : '';
		$groups_settings['groups_group_price'] = $product_price;

		// Save updated settings
		update_post_meta( $group_id, '_groups', $groups_settings );

		log_debug( "Synced vendor product {$vendor_product_id} price ({$product_price}) to group {$group_id}. Previous group price: {$old_price}" );
	}

	/**
	 * Check if a product was created by an admin user.
	 *
	 * @since 1.0.0
	 * @param int $product_id The product ID to check.
	 * @return bool True if created by admin, false otherwise.
	 */
	private function is_admin_product( $product_id ) {
		// Check if product has parent_product_id (indicates it's a vendor product)
		$parent_id = get_post_meta( $product_id, '_parent_product_id', true );
		if ( $parent_id ) {
			return false;
		}

		// Check if author has admin capabilities
		$product = get_post( $product_id );
		if ( ! $product ) {
			return false;
		}

		$author = get_user_by( 'ID', $product->post_author );
		if ( ! $author ) {
			return false;
		}

		return user_can( $author, 'manage_options' );
	}

	/**
	 * Create vendor products for all active vendors.
	 *
	 * @since 1.0.0
	 * @param int   $admin_product_id The admin product ID.
	 * @param array $course_ids       Array of course IDs.
	 * @return int Number of vendor products created.
	 */
	public function create_vendor_products( $admin_product_id, $course_ids ) {
		if ( ! function_exists( 'dokan_get_sellers' ) ) {
			log_debug( 'dokan_get_sellers function not available', 'error' );
			return 0;
		}

		$vendors = dokan_get_sellers( array( 'status' => 'approved' ) );
		
		if ( empty( $vendors['users'] ) ) {
			log_debug( 'No active vendors found' );
			return 0;
		}

		// Get the admin user ID who created the original product
		$admin_product = get_post( $admin_product_id );
		$admin_user_id = $admin_product ? $admin_product->post_author : 0;

		$created_count = 0;
		$skipped_admin_count = 0;
		$options = get_option( 'otc_options', array() );

		foreach ( $vendors['users'] as $vendor ) {
			// Skip the admin user who created the original product
			if ( $vendor->ID == $admin_user_id ) {
				log_debug( "Skipping admin user {$vendor->ID} (product author) from vendor product creation" );
				$skipped_admin_count++;
				continue;
			}

			// Skip if vendor product already exists
			$existing_product = $this->get_vendor_product( $admin_product_id, $vendor->ID );
			if ( $existing_product ) {
				continue;
			}

			// Check if auto-creation is enabled for new vendors
			if ( empty( $options['auto_create_for_new_vendors'] ) ) {
				continue;
			}

			$result = create_single_vendor_product( $admin_product_id, $vendor->ID, $course_ids );
			if ( $result ) {
				$created_count++;
			}
		}

		if ( $skipped_admin_count > 0 ) {
			log_debug( "Skipped {$skipped_admin_count} admin users during vendor product creation" );
		}

		return $created_count;
	}

	/**
	 * Update the product URL in a LearnDash group's settings (without touching price).
	 *
	 * @since 1.1.0
	 * @param int $group_id    The group ID.
	 * @param int $product_id  The product ID.
	 */
	private function update_group_product_url( $group_id, $product_id ) {
		// Get current group settings
		$groups_settings = get_post_meta( $group_id, '_groups', true );
		
		if ( ! is_array( $groups_settings ) ) {
			$groups_settings = array();
		}

		// Update ONLY the product URL (preserve vendor's price)
		$product_url = get_permalink( $product_id );
		$groups_settings['groups_custom_button_url'] = $product_url;

		// Save updated settings (price remains unchanged)
		update_post_meta( $group_id, '_groups', $groups_settings );

		log_debug( "Updated group {$group_id} product URL to: {$product_url} (price preserved)" );
	}

	/**
	 * Get existing vendor product for a given admin product and vendor.
	 *
	 * @since 1.0.0
	 * @param int $admin_product_id The admin product ID.
	 * @param int $vendor_id        The vendor user ID.
	 * @return WP_Post|false The vendor product post or false if not found.
	 */
	private function get_vendor_product( $admin_product_id, $vendor_id ) {
		$args = array(
			'post_type'      => 'product',
			'author'         => $vendor_id,
			'meta_query'     => array(
				array(
					'key'     => '_parent_product_id',
					'value'   => $admin_product_id,
					'compare' => '='
				)
			),
			'posts_per_page' => 1,
			'post_status'    => 'any',
			'fields'         => 'ids'
		);

		$products = get_posts( $args );
		return ! empty( $products ) ? get_post( $products[0] ) : false;
	}

	/**
	 * Sync vendor products when admin product is updated.
	 *
	 * @since 1.0.0
	 * @param int $admin_product_id The admin product ID that was updated.
	 */
	public function sync_vendor_products( $admin_product_id ) {
		if ( ! check_dependencies() ) {
			return;
		}

		$admin_product = wc_get_product( $admin_product_id );
		if ( ! $admin_product ) {
			return;
		}

		// Get all vendor products for this admin product
		$vendor_products = get_posts( array(
			'post_type'      => 'product',
			'post_status'    => 'any',
			'meta_query'     => array(
				array(
					'key'     => '_parent_product_id',
					'value'   => $admin_product_id,
					'compare' => '='
				)
			),
			'posts_per_page' => -1,
			'fields'         => 'ids'
		) );

		$synced_count = 0;

		foreach ( $vendor_products as $vendor_product_id ) {
			$vendor_product_post = get_post( $vendor_product_id );
			if ( ! $vendor_product_post ) {
				continue;
			}

			$vendor_product = wc_get_product( $vendor_product_id );
			if ( ! $vendor_product ) {
				continue;
			}

			// Only sync description for published vendor products
			// Draft products get full sync (excluding pricing)
			if ( $vendor_product_post->post_status === 'publish' ) {
				// Only update description for published products
				wp_update_post( array(
					'ID'           => $vendor_product_id,
					'post_content' => $admin_product->get_description()
				) );
			} else {
				// Full sync for draft products (excluding pricing)
				$this->sync_single_vendor_product( $vendor_product_id, $admin_product );
			}

			// Update course association
			$admin_courses = fetch_course_from_product( $admin_product_id );
			if ( ! empty( $admin_courses ) ) {
				$this->update_vendor_course_association( $vendor_product_id, $admin_courses );
			}

			$synced_count++;
		}

		log_debug( "Synced {$synced_count} vendor products for admin product {$admin_product_id} (pricing excluded)" );
	}

	/**
	 * Sync a single vendor product with its parent admin product.
	 * Excludes pricing to maintain vendor independence.
	 *
	 * @since 1.0.0
	 * @param int        $vendor_product_id The vendor product ID.
	 * @param WC_Product $admin_product     The admin product object.
	 */
	private function sync_single_vendor_product( $vendor_product_id, $admin_product ) {
		$vendor_product = wc_get_product( $vendor_product_id );

		if ( ! $vendor_product || ! $admin_product instanceof WC_Product ) {
			return;
		}

		// Sync allowed product fields (EXCLUDING PRICE - vendor controls pricing)
		$vendor_product->set_description( $admin_product->get_description() );
		$vendor_product->set_short_description( $admin_product->get_short_description() );
		$vendor_product->set_weight( $admin_product->get_weight() );
		$vendor_product->set_dimensions( $admin_product->get_dimensions() );
		$vendor_product->set_virtual( $admin_product->get_virtual() );
		$vendor_product->set_downloadable( $admin_product->get_downloadable() );

		// DO NOT sync prices - vendor maintains their own pricing:
		// - set_regular_price() - EXCLUDED
		// - set_sale_price() - EXCLUDED  
		// - set_price() - EXCLUDED

		// Save the updated product
		$vendor_product->save();

		log_debug( "Synced vendor product ID: {$vendor_product_id} (price excluded to maintain vendor independence)" );
	}

	/**
	 * Update vendor course association via LearnDash group.
	 *
	 * @since 1.0.0
	 * @param int   $vendor_product_id The vendor product ID.
	 * @param array $new_course_ids    Array of new course IDs.
	 */
	private function update_vendor_course_association( $vendor_product_id, $new_course_ids ) {
		$group_id = get_post_meta( $vendor_product_id, '_linked_ld_group_id', true );
		
		if ( $group_id && function_exists( 'learndash_set_group_enrolled_courses' ) ) {
			learndash_set_group_enrolled_courses( $group_id, $new_course_ids );
			log_debug( "Updated course association for group {$group_id}" );
		}
	}

	/**
	 * Add custom columns to the products list table.
	 *
	 * @since 1.0.0
	 * @param array $columns Existing columns.
	 * @return array Modified columns.
	 */
	public function add_product_columns( $columns ) {
		$columns['course_link'] = esc_html__( 'Linked Courses', 'online-texas-core' );
		$columns['vendor_availability'] = esc_html__( 'Vendor Availability', 'online-texas-core' );
		return $columns;
	}

	/**
	 * Populate custom columns in the products list table.
	 *
	 * @since 1.0.0
	 * @param string $column  The column name.
	 * @param int    $post_id The post ID.
	 */
	public function populate_product_columns( $column, $post_id ) {
		switch ( $column ) {
			case 'course_link':
				$course_ids = fetch_course_from_product( $post_id );
				if ( ! empty( $course_ids ) ) {
					$course_titles = array();
					foreach ( $course_ids as $course_id ) {
						$course = get_post( $course_id );
						if ( $course ) {
							$course_titles[] = esc_html( $course->post_title );
						}
					}
					echo implode( '<br>', $course_titles );
				} else {
					echo '—';
				}
				break;

			case 'vendor_availability':
				$parent_id = get_post_meta( $post_id, '_parent_product_id', true );
				if ( $parent_id ) {
					$parent = get_post( $parent_id );
					echo esc_html__( 'Vendor Product', 'online-texas-core' ) . '<br>';
					echo '<small>' . sprintf( 
						/* translators: %s: Parent product title */
						esc_html__( 'Parent: %s', 'online-texas-core' ), 
						$parent ? esc_html( $parent->post_title ) : esc_html__( 'Not found', 'online-texas-core' )
					) . '</small>';
				} else {
					// Check availability status
					$course_ids = fetch_course_from_product( $post_id );
					if ( empty( $course_ids ) ) {
						echo '<span style="color: #666;">— ' . esc_html__( 'No Courses', 'online-texas-core' ) . '</span>';
						break;
					}

					$available_for_vendors = get_post_meta( $post_id, '_available_for_vendors', true );
					$restricted_vendors = get_post_meta( $post_id, '_restricted_vendors', true );

					// Count vendor products for this admin product
					global $wpdb;
					$count = $wpdb->get_var( $wpdb->prepare(
						"SELECT COUNT(*) FROM {$wpdb->posts} p 
						INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id 
						WHERE p.post_type = 'product' 
						AND pm.meta_key = '_parent_product_id' 
						AND pm.meta_value = %s",
						$post_id
					) );

					switch ( $available_for_vendors ) {
						case 'yes':
							echo '<span style="color: #28a745;">✓ ' . esc_html__( 'All Vendors', 'online-texas-core' ) . '</span>';
							break;
						case 'selective':
							$vendor_count = is_array( $restricted_vendors ) ? count( $restricted_vendors ) : 0;
							echo '<span style="color: #ffc107;">⚡ ' . sprintf( esc_html__( 'Selected (%d)', 'online-texas-core' ), $vendor_count ) . '</span>';
							break;
						case 'no':
							echo '<span style="color: #dc3545;">✗ ' . esc_html__( 'Restricted', 'online-texas-core' ) . '</span>';
							break;
						default:
							// Default based on course presence
							if ( ! empty( $course_ids ) ) {
								echo '<span style="color: #28a745;">✓ ' . esc_html__( 'All Vendors', 'online-texas-core' ) . '</span>';
							} else {
								echo '<span style="color: #dc3545;">✗ ' . esc_html__( 'Restricted', 'online-texas-core' ) . '</span>';
							}
							break;
					}

					if ( $count > 0 ) {
						echo '<br><small>(' . sprintf( esc_html__( '%d duplicated', 'online-texas-core' ), intval( $count ) ) . ')</small>';
					}
				}
				break;
		}
	}

	/**
	 * Add admin menu for the plugin.
	 *
	 * @since 1.0.0
	 */
	public function add_admin_menu() {
		// Add main menu page
		add_menu_page(
			esc_html__( 'Texas Core', 'online-texas-core' ),
			esc_html__( 'Texas Core', 'online-texas-core' ),
			'manage_options',
			'online-texas-core',
			array( $this, 'admin_page' ),
			'dashicons-networking',
			30
		);

		// Add settings submenu
		add_submenu_page(
			'online-texas-core',
			esc_html__( 'Texas Core Settings', 'online-texas-core' ),
			esc_html__( 'Settings', 'online-texas-core' ),
			'manage_options',
			'online-texas-core-settings',
			array( $this, 'settings_page' )
		);
	}

	/**
	 * Display the main admin page.
	 *
	 * @since 1.0.0
	 */
	public function admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'online-texas-core' ) );
		}

		// Get statistics
		$stats = $this->get_plugin_statistics();

		include ONLINE_TEXAS_CORE_PATH . 'admin/partials/online-texas-core-admin-display.php';
	}

	/**
	 * Display the settings page.
	 *
	 * @since 1.0.0
	 */
	public function settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'online-texas-core' ) );
		}

		// Handle settings save
		if ( isset( $_POST['submit'] ) && wp_verify_nonce( $_POST['otc_settings_nonce'], 'otc_save_settings' ) ) {
			$this->save_settings();
		}

		$options = get_option( 'otc_options', array() );

		include ONLINE_TEXAS_CORE_PATH . 'admin/partials/online-texas-core-settings-display.php';
	}

	/**
	 * Save plugin settings.
	 *
	 * @since 1.0.0
	 */
	private function save_settings() {
		$options = array(
			'auto_create_for_new_vendors' => isset( $_POST['auto_create_for_new_vendors'] ),
			'debug_mode'                  => isset( $_POST['debug_mode'] ),
			'vendor_product_status'       => sanitize_text_field( $_POST['vendor_product_status'] ?? 'draft' ),
			'plugin_enabled'              => isset( $_POST['plugin_enabled'] )
		);

		// Validate vendor product status
		$allowed_statuses = array( 'draft', 'pending', 'publish' );
		if ( ! in_array( $options['vendor_product_status'], $allowed_statuses, true ) ) {
			$options['vendor_product_status'] = 'draft';
		}

		update_option( 'otc_options', $options );

		add_action( 'admin_notices', function() {
			echo '<div class="notice notice-success is-dismissible"><p>' . 
				 esc_html__( 'Settings saved successfully.', 'online-texas-core' ) . 
				 '</p></div>';
		});
	}

	/**
	 * Get plugin statistics for the admin dashboard.
	 *
	 * @since 1.0.0
	 * @return array Array of plugin statistics.
	 */
	private function get_plugin_statistics() {
		global $wpdb;

		// Get admin products with courses
		$admin_products_count = $wpdb->get_var(
			"SELECT COUNT(DISTINCT p.ID) 
			FROM {$wpdb->posts} p 
			INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id 
			WHERE p.post_type = 'product' 
			AND p.post_status = 'publish'
			AND pm.meta_key IN ('_related_course', '_related_group')"
		);

		// Get vendor products count
		$vendor_products_count = $wpdb->get_var(
			"SELECT COUNT(*) 
			FROM {$wpdb->posts} p 
			INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id 
			WHERE p.post_type = 'product' 
			AND pm.meta_key = '_parent_product_id'"
		);

		// Get active vendors count
		$vendors_count = 0;
		if ( function_exists( 'dokan_get_sellers' ) ) {
			$vendors = dokan_get_sellers( array( 'status' => 'approved' ) );
			
			if ( isset( $vendors['users'] ) && is_array( $vendors['users'] ) ) {
				$vendors_count = count( $vendors['users'] );
			} else {
				// Fallback: Count directly from database
				$vendors_count = $wpdb->get_var(
					"SELECT COUNT(u.ID) 
					FROM {$wpdb->users} u 
					INNER JOIN {$wpdb->usermeta} um1 ON u.ID = um1.user_id 
					INNER JOIN {$wpdb->usermeta} um2 ON u.ID = um2.user_id 
					WHERE um1.meta_key = 'wp_capabilities' 
					AND um1.meta_value LIKE '%seller%' 
					AND um2.meta_key = 'dokan_enable_selling' 
					AND um2.meta_value = 'yes'"
				);
			}
		}

		// Get LearnDash groups count
		$groups_count = 0;
		if ( function_exists( 'learndash_get_post_type_slug' ) ) {
			$groups_posts = wp_count_posts( learndash_get_post_type_slug( 'group' ) );
			$groups_count = isset( $groups_posts->publish ) ? $groups_posts->publish : 0;
		}

		return array(
			'admin_products'   => intval( $admin_products_count ),
			'vendor_products'  => intval( $vendor_products_count ),
			'active_vendors'   => intval( $vendors_count ),
			'learndash_groups' => intval( $groups_count )
		);
	}

	/**
	 * Handle AJAX request for manual vendor sync.
	 *
	 * @since 1.0.0
	 */
	public function ajax_manual_vendor_sync() {
		// Security checks
		if ( ! check_ajax_referer( 'otc_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Security check failed', 'online-texas-core' ) ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Insufficient permissions', 'online-texas-core' ) ) );
		}

		$vendor_id = isset( $_POST['vendor_id'] ) ? sanitize_text_field( $_POST['vendor_id'] ) : '';

		try {
			if ( $vendor_id === 'all' ) {
				$created_count = $this->sync_all_vendors();
				wp_send_json_success( array( 
					'message' => sprintf( 
						/* translators: %d: Number of products created */
						esc_html__( 'Created %d vendor products', 'online-texas-core' ), 
						$created_count 
					)
				) );
			} else {
				$vendor_id = intval( $vendor_id );
				$created_count = $this->sync_single_vendor( $vendor_id );
				
				if ( $created_count !== false ) {
					wp_send_json_success( array( 
						'message' => sprintf( 
							/* translators: %d: Number of products created */
							esc_html__( 'Created %d products for vendor', 'online-texas-core' ), 
							$created_count 
						)
					) );
				} else {
					wp_send_json_error( array( 'message' => esc_html__( 'Failed to sync vendor', 'online-texas-core' ) ) );
				}
			}
		} catch ( Exception $e ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Sync failed: ', 'online-texas-core' ) . $e->getMessage() ) );
		}
	}

	/**
	 * Handle AJAX request for product-level sync.
	 *
	 * @since 1.1.0
	 */
	public function ajax_sync_product_to_vendors() {
		// Security checks
		if ( ! check_ajax_referer( 'otc_sync_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Security check failed', 'online-texas-core' ) ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Insufficient permissions', 'online-texas-core' ) ) );
		}

		$product_id = isset( $_POST['product_id'] ) ? intval( $_POST['product_id'] ) : 0;
		$sync_type = isset( $_POST['sync_type'] ) ? sanitize_text_field( $_POST['sync_type'] ) : 'all';

		if ( ! $product_id ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Invalid product ID', 'online-texas-core' ) ) );
		}

		try {
			$course_ids = fetch_course_from_product( $product_id );
			if ( empty( $course_ids ) ) {
				wp_send_json_error( array( 'message' => esc_html__( 'Product has no linked courses', 'online-texas-core' ) ) );
			}

			$created_count = 0;
			$updated_count = 0;

			if ( $sync_type === 'all' ) {
				// Sync to all vendors
				if ( function_exists( 'dokan_get_sellers' ) ) {
					$vendors = dokan_get_sellers( array( 'status' => 'approved' ) );
					if ( ! empty( $vendors['users'] ) ) {
						foreach ( $vendors['users'] as $vendor ) {
							$existing_product = $this->get_vendor_product( $product_id, $vendor->ID );
							if ( $existing_product ) {
								$this->sync_single_vendor_product( $existing_product->ID, wc_get_product( $product_id ) );
								$updated_count++;
							} else {
								$result = create_single_vendor_product( $product_id, $vendor->ID, $course_ids );
								if ( $result ) {
									$created_count++;
								}
							}
						}
					}
				}
			} else {
				// Sync to selected vendors only
				$availability = get_post_meta( $product_id, '_available_for_vendors', true );
				$restricted_vendors = get_post_meta( $product_id, '_restricted_vendors', true );
				
				if ( $availability === 'selective' && is_array( $restricted_vendors ) ) {
					foreach ( $restricted_vendors as $vendor_id ) {
						$existing_product = $this->get_vendor_product( $product_id, $vendor_id );
						if ( $existing_product ) {
							$this->sync_single_vendor_product( $existing_product->ID, wc_get_product( $product_id ) );
							$updated_count++;
						} else {
							$result = create_single_vendor_product( $product_id, $vendor_id, $course_ids );
							if ( $result ) {
								$created_count++;
							}
						}
					}
				}
			}

			wp_send_json_success( array( 
				'message' => sprintf( 
					esc_html__( 'Sync completed: %d created, %d updated', 'online-texas-core' ), 
					$created_count, 
					$updated_count 
				)
			) );

		} catch ( Exception $e ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Sync failed: ', 'online-texas-core' ) . $e->getMessage() ) );
		}
	}

	/**
	 * Sync all vendors with admin products.
	 *
	 * @since 1.0.0
	 * @return int Number of products created.
	 */
	private function sync_all_vendors() {
		if ( ! function_exists( 'dokan_get_sellers' ) ) {
			return 0;
		}

		$vendors = dokan_get_sellers( array( 'status' => 'approved' ) );
		$total_created = 0;

		if ( ! empty( $vendors['users'] ) ) {
			foreach ( $vendors['users'] as $vendor ) {
				$created = $this->sync_single_vendor( $vendor->ID );
				if ( $created !== false ) {
					$total_created += $created;
				}
			}
		}

		return $total_created;
	}

	/**
	 * Sync a single vendor with admin products.
	 *
	 * @since 1.0.0
	 * @param int $vendor_id The vendor user ID.
	 * @return int|false Number of products created or false on failure.
	 */
	public function sync_single_vendor( $vendor_id ) {
		if ( ! function_exists( 'dokan_is_user_seller' ) || ! dokan_is_user_seller( $vendor_id ) ) {
			return false;
		}

		// Get all admin products with courses
		$admin_products = get_posts( array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'meta_query'     => array(
				'relation' => 'OR',
				array(
					'key'     => '_related_course',
					'compare' => 'EXISTS'
				),
				array(
					'key'     => '_related_group',
					'compare' => 'EXISTS'
				)
			),
			'posts_per_page' => -1,
			'fields'         => 'ids'
		) );

		$created_count = 0;

		foreach ( $admin_products as $admin_product_id ) {
			// Skip if not an admin product
			if ( ! $this->is_admin_product( $admin_product_id ) ) {
				continue;
			}

			// Get the admin user ID who created the original product
			$admin_product = get_post( $admin_product_id );
			$admin_user_id = $admin_product ? $admin_product->post_author : 0;

			// Skip if vendor is the admin who created this product
			if ( $vendor_id == $admin_user_id ) {
				log_debug( "Skipping admin user {$vendor_id} (product author) from vendor sync for product {$admin_product_id}" );
				continue;
			}

			// Check if vendor already has this product
			$existing_product = $this->get_vendor_product( $admin_product_id, $vendor_id );
			if ( $existing_product ) {
				continue;
			}

			$course_ids = fetch_course_from_product( $admin_product_id );
			if ( ! empty( $course_ids ) ) {
				$result = create_single_vendor_product( $admin_product_id, $vendor_id, $course_ids );
				if ( $result ) {
					$created_count++;
				}
			}
		}

		return $created_count;
	}

	/**
	 * Handle AJAX request to clear debug log.
	 *
	 * @since 1.0.0
	 */
	public function ajax_clear_debug_log() {
		// Security checks
		if ( ! check_ajax_referer( 'otc_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Security check failed', 'online-texas-core' ) ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Insufficient permissions', 'online-texas-core' ) ) );
		}

		delete_option( 'otc_debug_log' );
		wp_send_json_success( array( 'message' => esc_html__( 'Debug log cleared', 'online-texas-core' ) ) );
	}
}