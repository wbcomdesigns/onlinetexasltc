<?php
/**
 * Vendor Codes Management for Dokan Vendors (Version 1)
 *
 * This class adds a new menu to the Dokan vendor dashboard for code management,
 * registers a custom endpoint, and renders a placeholder template.
 *
 * No changes are made to existing plugin functionality.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Online_Texas_Vendor_Codes {
	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		// Add vendor codes menu to Dokan dashboard
		add_filter( 'dokan_get_dashboard_nav', array( $this, 'add_vendor_codes_menu' ) );
		
		// Register custom endpoint
		add_action( 'init', array( $this, 'register_codes_endpoint' ) );
		
		// Handle endpoint content
		add_action( 'dokan_load_custom_template', array( $this, 'render_vendor_codes_page' ) );
		
		// Recipe forwarding system
		add_action( 'ulc_code_redeemed', array( $this, 'forward_vendor_code_to_admin_recipe' ), 10, 2 );
		
		// Add shortcode for code redemption
		add_shortcode( 'vendor_code_redeem', array( $this, 'shortcode_vendor_code_redeem' ) );
		
		// Handle commission for discount codes
		add_action( 'woocommerce_checkout_order_processed', array( $this, 'handle_discount_code_commission' ) );
		
		// Apply discount code to order
		add_action( 'woocommerce_before_checkout_process', array( $this, 'apply_discount_code_to_order' ) );
		
		// Ensure meta table exists
		$this->ensure_meta_table_exists();
	}
	
	/**
	 * Ensure the meta table exists
	 */
	public function ensure_meta_table_exists() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'uncanny_codes_code_meta';
		
		if ( ! self::table_exists( $table_name ) ) {
			self::create_meta_table();
		}
	}

	public function add_vendor_codes_menu( $nav ) {
		if ( ! function_exists( 'dokan_is_user_seller' ) || ! dokan_is_user_seller( get_current_user_id() ) ) {
			return $nav;
		}
		$nav['vendor-codes'] = [
			'title'      => __( 'Codes', 'online-texas-core' ),
			'icon'       => '<i class="fas fa-key"></i>',
			'url'        => dokan_get_navigation_url( 'vendor-codes' ),
			'pos'        => 120,
			'permission' => 'dokandar',
		];
		return $nav;
	}

	public function register_codes_endpoint() {
		add_rewrite_endpoint( 'vendor-codes', EP_PAGES );
	}

	public function render_vendor_codes_page( $query_vars ) {
		if ( ! isset( $query_vars['vendor-codes'] ) ) {
			return;
		}

		// Security check
		if ( ! function_exists( 'dokan_is_user_seller' ) || ! dokan_is_user_seller( get_current_user_id() ) ) {
			wp_die( esc_html__( 'Access denied - Vendors only.', 'online-texas-core' ) );
		}

		$current_vendor = get_current_user_id();
		$success_message = '';
		$error_message = '';

		// Pagination handling
		$paged = 1;
		if ( isset( $_GET['paged'] ) && intval( $_GET['paged'] ) > 0 ) {
			$paged = intval( $_GET['paged'] );
		}
		$per_page = 20;

		try {
			// Handle code generation form submission
			if ( isset( $_POST['generate_codes_nonce'] ) && wp_verify_nonce( $_POST['generate_codes_nonce'], 'generate_vendor_codes' ) ) {
				$admin_product_id = intval( $_POST['product_id'] );
				$codes_to_generate = intval( $_POST['codes_to_generate'] );
				$expiry_date = sanitize_text_field( $_POST['expiry_date'] );
				$code_type = sanitize_text_field( $_POST['code_type'] );

				// Validate inputs
				if ( $admin_product_id && $codes_to_generate >= 1 && $codes_to_generate <= 20 ) {
					// Check admin permissions using admin product ID
					if ( class_exists( 'Online_Texas_Admin_Permissions' ) ) {
						if ( ! Online_Texas_Admin_Permissions::vendor_can_generate_codes( $current_vendor, $admin_product_id ) ) {
							$error_message = esc_html__( 'You do not have permission to generate codes for this product or you have reached your limit.', 'online-texas-core' );
						} else {
							// Get vendor product ID for actual code generation
							$vendor_product_id = Online_Texas_Admin_Permissions::get_vendor_product_id( $current_vendor, $admin_product_id );
							
							if ( ! $vendor_product_id ) {
								$error_message = esc_html__( 'Vendor product not found. Please try duplicating the product again.', 'online-texas-core' );
							} else {
								// Check if approval is required
								if ( Online_Texas_Admin_Permissions::approval_required( $admin_product_id ) ) {
									// Create approval request
									$this->create_approval_request( $current_vendor, $admin_product_id, $codes_to_generate, $expiry_date, $code_type );
									$success_message = esc_html__( 'Code generation request submitted for admin approval.', 'online-texas-core' );
								} else {
									// Generate codes immediately using vendor product ID
									$result = $this->generate_codes_for_vendor( $current_vendor, $vendor_product_id, $codes_to_generate, $expiry_date, $code_type );
									if ( $result ) {
										$success_message = esc_html__( 'Codes generated successfully!', 'online-texas-core' );
									} else {
										$error_message = esc_html__( 'Failed to generate codes. Please try again.', 'online-texas-core' );
									}
								}
							}
						}
					} else {
						$error_message = esc_html__( 'Permission system not available.', 'online-texas-core' );
					}
				} else {
					$error_message = esc_html__( 'Invalid input. Please check your values.', 'online-texas-core' );
				}
			}

			// Get vendor's products that can generate codes
			$vendor_products = $this->get_vendor_products_for_code_generation( $current_vendor );

			// Get vendor's existing codes with pagination
			$vendor_codes = $this->get_vendor_codes( $current_vendor, $paged, $per_page );

		} catch ( Exception $e ) {
			$error_message = esc_html__( 'An error occurred while loading the codes page. Please try again.', 'online-texas-core' );
			$vendor_products = array();
			$vendor_codes = array();
		}

		// Render the page
		?>
		<div class="dokan-dashboard-wrap">
			<?php do_action( 'dokan_dashboard_content_before' ); ?>

			<div class="dokan-dashboard-content">
				<?php do_action( 'dokan_help_content_inside_before' ); ?>

				<div class="dokan-vendor-codes-wrap">
					<div class="dokan-dashboard-header">
						<h1 class="entry-title">
							<i class="fas fa-tags"></i>
							<?php esc_html_e( 'Registration Codes', 'online-texas-core' ); ?>
						</h1>
					</div>

					<div class="online-texas-dashboard-content">
						<?php if ( $success_message ) : ?>
							<div class="dokan-alert dokan-alert-success">
								<?php echo $success_message; ?>
							</div>
						<?php endif; ?>

						<?php if ( $error_message ) : ?>
							<div class="dokan-alert dokan-alert-danger">
								<?php echo $error_message; ?>
							</div>
						<?php endif; ?>

						<!-- Code Generation Form -->
						<div class="dokan-panel dokan-panel-default">
							<div class="dokan-panel-heading">
								<h3 class="dokan-panel-title">
									<i class="fas fa-plus"></i>
									<?php esc_html_e( 'Generate Registration Codes', 'online-texas-core' ); ?>
								</h3>
							</div>
							<div class="dokan-panel-body">
								<?php if ( ! empty( $vendor_products ) ) : ?>
									<form method="post" class="dokan-form">
										<?php wp_nonce_field( 'generate_vendor_codes', 'generate_codes_nonce' ); ?>
										
										<div class="dokan-form-group">
											<label for="product_id"><?php esc_html_e( 'Select Product:', 'online-texas-core' ); ?></label>
											<select name="product_id" id="product_id" class="dokan-form-control" required>
												<option value=""><?php esc_html_e( 'Choose a product...', 'online-texas-core' ); ?></option>
												<?php foreach ( $vendor_products as $product ) : ?>
													<option value="<?php echo esc_attr( $product['admin_product_id'] ); ?>" data-vendor-product-id="<?php echo esc_attr( $product['id'] ); ?>">
														<?php echo esc_html( $product['name'] ); ?>
														<?php if ( isset( $product['codes_generated'] ) ) : ?>
															(<?php echo esc_html( $product['codes_generated'] ); ?>/<?php echo esc_html( $product['max_codes'] ); ?>)
														<?php endif; ?>
													</option>
												<?php endforeach; ?>
											</select>
										</div>

										<div class="dokan-form-group">
											<label for="codes_to_generate"><?php esc_html_e( 'Number of Codes (1-20):', 'online-texas-core' ); ?></label>
											<input type="number" name="codes_to_generate" id="codes_to_generate" 
												   class="dokan-form-control" min="1" max="20" value="10" required>
										</div>

										<div class="dokan-form-group">
											<label for="expiry_date"><?php esc_html_e( 'Expiry Date (Optional):', 'online-texas-core' ); ?></label>
											<input type="date" name="expiry_date" id="expiry_date" class="dokan-form-control">
										</div>

										<input type="hidden" name="code_type" value="registration">

										<div class="dokan-form-group">
											<button type="submit" class="dokan-btn dokan-btn-theme">
												<i class="fas fa-magic"></i>
												<?php esc_html_e( 'Generate Registration Codes', 'online-texas-core' ); ?>
											</button>
										</div>
									</form>
								<?php else : ?>
									<div class="dokan-alert dokan-alert-info">
										<p><?php esc_html_e( 'No products available for code generation. Please duplicate admin products first.', 'online-texas-core' ); ?></p>
									</div>
								<?php endif; ?>
							</div>
						</div>

						<!-- Existing Codes -->
						<div class="dokan-panel dokan-panel-default">
							<div class="dokan-panel-heading">
								<h3 class="dokan-panel-title">
									<i class="fas fa-list"></i>
									<?php esc_html_e( 'Your Codes', 'online-texas-core' ); ?>
								</h3>
							</div>
							<div class="dokan-panel-body">
								<?php if ( ! empty( $vendor_codes['codes'] ) ) : ?>
									<div class="table-responsive">
										<table class="dokan-table">
											<thead>
												<tr>
													<th><?php esc_html_e( 'Code', 'online-texas-core' ); ?></th>
													<th><?php esc_html_e( 'Product', 'online-texas-core' ); ?></th>
													<th><?php esc_html_e( 'Status', 'online-texas-core' ); ?></th>
													<th><?php esc_html_e( 'Redeemed By', 'online-texas-core' ); ?></th>
													<th><?php esc_html_e( 'Date', 'online-texas-core' ); ?></th>
												</tr>
											</thead>
											<tbody>
												<?php foreach ( $vendor_codes['codes'] as $code ) : ?>
													<tr>
														<td><code><?php echo esc_html( $code['code'] ); ?></code></td>
														<td><?php echo esc_html( $code['product_name'] ); ?></td>
														<td>
															<span class="dokan-label dokan-label-<?php echo $code['status'] === 'active' ? 'success' : 'default'; ?>">
																<?php echo esc_html( ucfirst( $code['status'] ) ); ?>
															</span>
														</td>
														<td><?php echo esc_html( $code['redeemed_by'] ); ?></td>
														<td><?php echo esc_html( $code['date'] ); ?></td>
													</tr>
												<?php endforeach; ?>
											</tbody>
										</table>
									</div>
									<?php if ( $vendor_codes['total'] > $per_page ) : ?>
										<div class="dokan-pagination-container" style="margin-top: 20px; text-align: center;">
											<div class="dokan-pagination">
												<?php
												$total_pages = ceil( $vendor_codes['total'] / $per_page );
												$current_page = $paged;
												$base_url = dokan_get_navigation_url( 'vendor-codes' );
												
												// Show pagination info
												$start = (($current_page - 1) * $per_page) + 1;
												$end = min($current_page * $per_page, $vendor_codes['total']);
												?>
												<span class="dokan-pagination-info" style="margin: 0 15px; color: #666;">
													<?php printf(esc_html__('Showing %d-%d of %d codes', 'online-texas-core'), $start, $end, $vendor_codes['total']); ?>
												</span>
												
												<?php if ( $current_page > 1 ) : ?>
													<a href="<?php echo esc_url( add_query_arg( 'paged', $current_page - 1, $base_url ) ); ?>" class="dokan-btn dokan-btn-sm" style="margin-right: 5px;">
														<i class="fas fa-chevron-left"></i> <?php esc_html_e('Previous', 'online-texas-core'); ?>
													</a>
												<?php endif; ?>
												
												<?php if ( $current_page < $total_pages ) : ?>
													<a href="<?php echo esc_url( add_query_arg( 'paged', $current_page + 1, $base_url ) ); ?>" class="dokan-btn dokan-btn-sm" style="margin-left: 5px;">
														<?php esc_html_e('Next', 'online-texas-core'); ?> <i class="fas fa-chevron-right"></i>
													</a>
												<?php endif; ?>
											</div>
											
											<!-- Page numbers (show if 10 or fewer pages) -->
											<?php if ( $total_pages <= 10 ) : ?>
												<div class="dokan-page-numbers" style="margin-top: 10px;">
													<?php for ( $i = 1; $i <= $total_pages; $i++ ) : ?>
														<?php if ( $i == $current_page ) : ?>
															<span class="dokan-page-number current" style="display: inline-block; padding: 5px 10px; margin: 0 2px; background: #007cba; color: white; border-radius: 3px; text-decoration: none;"><?php echo $i; ?></span>
														<?php else : ?>
															<a href="<?php echo esc_url( add_query_arg( 'paged', $i, $base_url ) ); ?>" class="dokan-page-number" style="display: inline-block; padding: 5px 10px; margin: 0 2px; background: #f8f9fa; color: #333; border: 1px solid #ddd; border-radius: 3px; text-decoration: none;"><?php echo $i; ?></a>
														<?php endif; ?>
													<?php endfor; ?>
												</div>
											<?php endif; ?>
										</div>
									<?php endif; ?>
								<?php else : ?>
									<p><?php esc_html_e( 'No codes generated yet.', 'online-texas-core' ); ?></p>
								<?php endif; ?>
							</div>
						</div>
					</div>
				</div>

				<?php do_action( 'dokan_dashboard_content_after' ); ?>
			</div>
		</div>
		<?php
		exit;
	}

    /**
     * Add meta for a code
     */
    public static function add_code_meta( $code_id, $meta_key, $meta_value ) {
        global $wpdb;
        $table = $wpdb->prefix . 'uncanny_codes_code_meta';
        
        // Check if table exists, if not create it
        if ( ! self::table_exists( $table ) ) {
            self::create_meta_table();
        }
        
        $result = $wpdb->insert( $table, [
            'code_id'    => $code_id,
            'meta_key'   => $meta_key,
            'meta_value' => maybe_serialize( $meta_value ),
        ], [
            '%d', // code_id
            '%s', // meta_key
            '%s', // meta_value
        ] );
        
        return $result;
    }
    
    /**
     * Check if table exists
     */
    private static function table_exists( $table_name ) {
        global $wpdb;
        $result = $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" );
        $exists = $result === $table_name;
        return $exists;
    }
    
    /**
     * Create the meta table if it doesn't exist
     */
    private static function create_meta_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'uncanny_codes_code_meta';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            meta_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            code_id bigint(20) unsigned NOT NULL,
            meta_key varchar(255) NOT NULL,
            meta_value longtext NOT NULL,
            PRIMARY KEY  (meta_id),
            KEY code_id (code_id),
            KEY meta_key (meta_key)
        ) $charset_collate;";
        
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        $result = dbDelta( $sql );
        
        // Verify table was created
        $table_exists = self::table_exists( $table_name );
    }

    /**
     * Get meta value for a code
     */
    public static function get_code_meta( $code_id, $meta_key ) {
        global $wpdb;
        $table = $wpdb->prefix . 'uncanny_codes_code_meta';
        $value = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM $table WHERE code_id = %d AND meta_key = %s LIMIT 1", $code_id, $meta_key ) );
        return maybe_unserialize( $value );
    }

    /**
     * Get code IDs for a vendor
     */
    public static function get_vendor_code_ids( $vendor_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'uncanny_codes_code_meta';
        
        // Fix: Use proper comparison for vendor ID (it's stored as serialized integer)
        $vendor_id_serialized = maybe_serialize( $vendor_id );
        $code_ids = $wpdb->get_col( $wpdb->prepare( 
            "SELECT code_id FROM $table WHERE meta_key = %s AND meta_value = %s", 
            '_dokan_vendor_id', 
            $vendor_id_serialized 
        ) );
        
        return $code_ids;
    }
    
    /**
     * Debug function to check meta table contents
     */
    public static function debug_meta_table_contents() {
        global $wpdb;
        $table = $wpdb->prefix . 'uncanny_codes_code_meta';
        
        if ( ! self::table_exists( $table ) ) {
            return;
        }
        
        $all_meta = $wpdb->get_results( "SELECT * FROM $table ORDER BY meta_id DESC LIMIT 20" );
        
        // Check specifically for vendor meta
        $vendor_meta = $wpdb->get_results( "SELECT * FROM $table WHERE meta_key = '_dokan_vendor_id' ORDER BY meta_id DESC" );
    }

    /**
     * Get codes from vendor's duplicated products that link to original admin batches
     */
    public static function get_codes_from_vendor_products( $vendor_id ) {
        global $wpdb;
        
        // Get all products (clones) created by this vendor
        $product_ids = $wpdb->get_col( $wpdb->prepare( 
            "SELECT ID FROM {$wpdb->prefix}posts WHERE post_author = %d AND post_type = 'product'", 
            $vendor_id 
        ) );

        $code_ids = [];
        foreach ( $product_ids as $product_id ) {
            // Get the original admin product's batch ID
            $original_batch_id = get_post_meta( $product_id, 'codes_group_name', true );

            if ( $original_batch_id ) {
                // Get all codes from this original batch
                $codes = \uncanny_learndash_codes\Database::get_coupons( $original_batch_id );
                foreach ( $codes as $code_obj ) {
                    // Check if this code is associated with the current vendor
                    $dokan_vendor_id = self::get_code_meta( $code_obj->ID, '_dokan_vendor_id' );
                    if ( $dokan_vendor_id == $vendor_id ) {
                        $code_ids[] = $code_obj->ID;
                    }
                }
            }
        }
        return $code_ids;
    }

    /**
     * Get product name for a code group
     */
    public static function get_product_name_for_code( $group_id, $vendor_id ) {
        global $wpdb;
        
        // First, try to find the vendor's product that uses this batch
        $vendor_products = $wpdb->get_col( $wpdb->prepare( 
            "SELECT ID FROM {$wpdb->prefix}posts WHERE post_author = %d AND post_type = 'product'", 
            $vendor_id 
        ) );
        
        foreach ( $vendor_products as $product_id ) {
            // Check for _vendor_batch_id (new method)
            $vendor_batch_id = get_post_meta( $product_id, '_vendor_batch_id', true );
            if ( $vendor_batch_id == $group_id ) {
                $product = get_post( $product_id );
                if ( $product ) {
                    return esc_html( $product->post_title );
                }
            }
            
            // Check for codes_group_name (old method - fallback)
            $batch_id = get_post_meta( $product_id, 'codes_group_name', true );
            if ( $batch_id == $group_id ) {
                $product = get_post( $product_id );
                if ( $product ) {
                    return esc_html( $product->post_title );
                }
            }
        }
        
        // If not found in vendor products, try to find the admin product
        $table = $wpdb->prefix . \uncanny_learndash_codes\Config::$tbl_groups;
        $group = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE ID = %d", $group_id ) );
        
        if ( $group && $group->product_id ) {
            $product = get_post( $group->product_id );
            if ( $product ) {
                return esc_html( $product->post_title );
            }
        }
        
        // If still not found, try to find admin product through batch mapping
        $admin_batch_mapping = get_post_meta( $group_id, '_admin_batch_mapping', true );
        if ( $admin_batch_mapping ) {
            $admin_product_id = get_post_meta( $group_id, '_admin_product_id', true );
            if ( $admin_product_id ) {
                $product = get_post( $admin_product_id );
                if ( $product ) {
                    return esc_html( $product->post_title ) . ' (Admin)';
                }
            }
        }
        
        return esc_html__( 'Unknown Product', 'online-texas-core' );
    }

    /**
     * Shortcode handler for [vendor_code_redeem]
     */
    public function shortcode_vendor_code_redeem() {
        if ( ! is_user_logged_in() ) {
            return '<div class="dokan-alert dokan-alert-danger">' . esc_html__( 'You must be logged in to redeem a code.', 'online-texas-core' ) . '</div>';
        }
        $msg = '';
        if ( isset( $_POST['otc_redeem_code_nonce'] ) && wp_verify_nonce( $_POST['otc_redeem_code_nonce'], 'otc_redeem_code' ) ) {
            $code = trim( sanitize_text_field( $_POST['otc_code'] ) );
            if ( empty( $code ) ) {
                $msg = '<div class="dokan-alert dokan-alert-danger">' . esc_html__( 'Please enter a code.', 'online-texas-core' ) . '</div>';
            } else {
                // Validate code using Uncanny
                $code_row = $this->find_code_row( $code );
                if ( ! $code_row ) {
                    $msg = '<div class="dokan-alert dokan-alert-danger">' . esc_html__( 'Invalid code.', 'online-texas-core' ) . '</div>';
                } else {
                    // Check if already redeemed
                    $used = $code_row->used_date;
                    if ( $used ) {
                        $msg = '<div class="dokan-alert dokan-alert-danger">' . esc_html__( 'This code has already been redeemed.', 'online-texas-core' ) . '</div>';
                    } elseif ( ! $code_row->is_active ) {
                        $msg = '<div class="dokan-alert dokan-alert-danger">' . esc_html__( 'This code is not active.', 'online-texas-core' ) . '</div>';
                    } elseif ( $code_row->expire_date && strtotime( $code_row->expire_date ) < time() ) {
                        $msg = '<div class="dokan-alert dokan-alert-danger">' . esc_html__( 'This code has expired.', 'online-texas-core' ) . '</div>';
                    } else {
                        // Get group info for type and linked data
                        $group = $this->get_code_group( $code_row->code_group );
                        $user_id = get_current_user_id();
                        if ( $group && $group->code_for === 'course' ) {
                            $courses = maybe_unserialize( $group->linked_to );
                            if ( is_array( $courses ) && ! empty( $courses ) ) {
                                foreach ( $courses as $course_id ) {
                                    ld_update_course_access( $user_id, $course_id, true );
                                }
                                $msg = '<div class="dokan-alert dokan-alert-success">' . esc_html__( 'You have been enrolled in the course(s)!', 'online-texas-core' ) . '</div>';
                            } else {
                                $msg = '<div class="dokan-alert dokan-alert-danger">' . esc_html__( 'No course linked to this code.', 'online-texas-core' ) . '</div>';
                            }
                        } elseif ( $group && $group->code_for === 'discount' ) {
                            // Store code in session for WooCommerce discount
                            WC()->session->set( 'otc_discount_code', $code );
                            $msg = '<div class="dokan-alert dokan-alert-success">' . esc_html__( 'Discount code applied! Complete your purchase to use it.', 'online-texas-core' ) . '</div>';
                        } elseif ( $group && $group->code_for === 'automator' ) {
                            // Check if this is a discount code based on vendor meta
                            $vendor_id = self::get_code_meta( $code_row->ID, '_dokan_vendor_id' );
                            if ( $vendor_id ) {
                                // Get vendor batch info to determine code type
                                $vendor_batch_id = self::get_code_meta( $code_row->ID, '_vendor_batch_id' );
                                if ( $vendor_batch_id ) {
                                    $code_type = get_post_meta( $vendor_batch_id, '_vendor_code_type', true );
                                    if ( $code_type === 'discount' ) {
                                        // Store code in session for WooCommerce discount
                                        WC()->session->set( 'otc_discount_code', $code );
                                        $msg = '<div class="dokan-alert dokan-alert-success">' . esc_html__( 'Discount code applied! Complete your purchase to use it.', 'online-texas-core' ) . '</div>';
                                    } else {
                                        // Registration code - trigger recipe forwarding
                                        do_action( 'ulc_code_redeemed', $code_row->ID, $user_id );
                                        $msg = '<div class="dokan-alert dokan-alert-success">' . esc_html__( 'Registration code redeemed successfully!', 'online-texas-core' ) . '</div>';
                                    }
                                } else {
                                    // Fallback to registration code
                                    do_action( 'ulc_code_redeemed', $code_row->ID, $user_id );
                                    $msg = '<div class="dokan-alert dokan-alert-success">' . esc_html__( 'Registration code redeemed successfully!', 'online-texas-core' ) . '</div>';
                                }
                            } else {
                                // Fallback to registration code
                                do_action( 'ulc_code_redeemed', $code_row->ID, $user_id );
                                $msg = '<div class="dokan-alert dokan-alert-success">' . esc_html__( 'Registration code redeemed successfully!', 'online-texas-core' ) . '</div>';
                            }
                        } else {
                            $msg = '<div class="dokan-alert dokan-alert-danger">' . esc_html__( 'Unknown code type.', 'online-texas-core' ) . '</div>';
                        }
                        // Mark code as redeemed
                        $this->mark_code_redeemed( $code_row->ID, $user_id );
                    }
                }
            }
        }
        ob_start();
        echo '<form method="post" class="dokan-form-inline" style="max-width:400px;margin:20px auto;">';
        echo '<input type="hidden" name="otc_redeem_code_nonce" value="' . esc_attr( wp_create_nonce( 'otc_redeem_code' ) ) . '" />';
        echo '<label>' . esc_html__( 'Enter your code:', 'online-texas-core' ) . ' <input type="text" name="otc_code" class="dokan-form-control" required></label> ';
        echo '<button type="submit" class="dokan-btn dokan-btn-theme">' . esc_html__( 'Redeem', 'online-texas-core' ) . '</button>';
        echo '</form>';
        if ( $msg ) {
            echo $msg;
        }
        return ob_get_clean();
    }

    /**
     * Apply discount code to order
     */
    public function apply_discount_code_to_order() {
        if ( ! function_exists( 'WC' ) ) return;
        
        $code = WC()->session->get( 'otc_discount_code' );
        if ( ! $code ) return;
        
        // Find the code row
        $code_row = $this->find_code_row( $code );
        if ( ! $code_row ) return;
        
        // Get vendor batch info to determine discount amount
        $vendor_batch_id = self::get_code_meta( $code_row->ID, '_vendor_batch_id' );
        if ( $vendor_batch_id ) {
            // Get discount amount from batch meta (you can customize this)
            $discount_amount = get_post_meta( $vendor_batch_id, '_vendor_discount_amount', true ) ?: 10; // Default 10% discount
            
            // Apply discount to cart
            $cart = WC()->cart;
            if ( $cart ) {
                // Calculate discount (10% of cart total)
                $cart_total = $cart->get_total();
                $discount = ( $cart_total * $discount_amount ) / 100;
                
                // Add discount as a fee (negative amount)
                $cart->add_fee( 'Vendor Discount (' . $code . ')', -$discount );
                
                error_log( 'Applied vendor discount: ' . $discount . ' for code: ' . $code );
            }
        }
    }

    /**
     * Handle commission for orders using a vendor discount code
     */
    public function handle_discount_code_commission( $order_id ) {
        if ( ! function_exists( 'WC' ) ) return;
        $code = WC()->session->get( 'otc_discount_code' );
        if ( ! $code ) return;
        // Find the code row
        $code_row = $this->find_code_row( $code );
        if ( ! $code_row ) return;
        // Find the vendor for this code
        $vendor_id = self::get_code_meta( $code_row->ID, '_dokan_vendor_id' );
        if ( ! $vendor_id ) return;
        // Set commission to zero for this order for this vendor
        // Dokan Pro: set order commission meta
        update_post_meta( $order_id, '_dokan_vendor_commission', 0 );
        // Mark code as redeemed for this user/order if not already
        $user_id = get_current_user_id();
        if ( ! $code_row->used_date ) {
            $this->mark_code_redeemed( $code_row->ID, $user_id );
        }
        // Remove code from session
        WC()->session->__unset( 'otc_discount_code' );
    }

    /**
     * Find a code row by code string
     */
    private function find_code_row( $code ) {
        global $wpdb;
        $table = $wpdb->prefix . \uncanny_learndash_codes\Config::$tbl_codes;
        $group_table = $wpdb->prefix . \uncanny_learndash_codes\Config::$tbl_groups;
        return $wpdb->get_row( $wpdb->prepare( "SELECT c.*, g.expire_date, g.code_for, g.linked_to FROM $table c LEFT JOIN $group_table g ON c.code_group = g.ID WHERE c.code = %s LIMIT 1", $code ) );
    }

    /**
     * Get group info for a code group
     */
    private function get_code_group( $group_id ) {
        global $wpdb;
        $table = $wpdb->prefix . \uncanny_learndash_codes\Config::$tbl_groups;
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE ID = %d", $group_id ) );
    }

    /**
     * Mark code as redeemed for a user
     */
    private function mark_code_redeemed( $code_id, $user_id ) {
        global $wpdb;
        $table = $wpdb->prefix . \uncanny_learndash_codes\Config::$tbl_codes;
        $wpdb->update( $table, [ 'used_date' => current_time( 'mysql' ), 'user_id' => $user_id ], [ 'ID' => $code_id ] );
    }

	/**
	 * Forward vendor code redemption to original admin product recipe
	 */
	public function forward_vendor_code_to_admin_recipe( $code_id, $user_id ) {
		// Check if this is a vendor code
		$vendor_id = self::get_code_meta( $code_id, '_dokan_vendor_id' );
		if ( ! $vendor_id ) {
			return; // Not a vendor code, let Uncanny handle normally
		}
		
		// Get the vendor batch this code belongs to
		$vendor_batch_id = self::get_code_meta( $code_id, '_vendor_batch_id' );
		if ( ! $vendor_batch_id ) {
			error_log( 'Vendor code missing vendor_batch_id: ' . $code_id );
			return;
		}
		
		// Get the admin batch mapping
		$admin_batch_id = self::get_vendor_batch_admin_mapping( $vendor_batch_id );
		if ( ! $admin_batch_id ) {
			error_log( 'No admin batch mapping found for vendor batch: ' . $vendor_batch_id );
			return;
		}
		
		// Trigger the original admin product's recipe
		$this->trigger_admin_product_recipe( $admin_batch_id, $user_id, $code_id );
	}
	
	/**
	 * Get admin batch mapping for a vendor batch
	 */
	private function get_vendor_batch_admin_mapping( $vendor_batch_id ) {
		global $wpdb;
		
		// Find the vendor product that uses this batch
		$vendor_product_id = $wpdb->get_var( $wpdb->prepare(
			"SELECT post_id FROM {$wpdb->prefix}postmeta 
			 WHERE meta_key = 'codes_group_name' AND meta_value = %d",
			$vendor_batch_id
		) );
		
		if ( ! $vendor_product_id ) {
			return false;
		}
		
		// Get the admin batch mapping
		return get_post_meta( $vendor_product_id, '_admin_batch_mapping', true );
	}
	
	/**
	 * Trigger admin product recipe
	 */
	private function trigger_admin_product_recipe( $admin_batch_id, $user_id, $vendor_code_id ) {
		// Get the admin product that owns this batch
		global $wpdb;
		
		$admin_product_id = $wpdb->get_var( $wpdb->prepare(
			"SELECT post_id FROM {$wpdb->prefix}postmeta 
			 WHERE meta_key = 'codes_group_name' AND meta_value = %d",
			$admin_batch_id
		) );
		
		if ( ! $admin_product_id ) {
			error_log( 'No admin product found for batch: ' . $admin_batch_id );
			return;
		}
		
		// Trigger the original recipe by simulating a code redemption from the admin batch
		// We'll create a temporary code in the admin batch and redeem it
		$temp_code = $this->create_temp_code_for_recipe( $admin_batch_id, $user_id );
		if ( $temp_code ) {
			// Trigger the recipe
			do_action( 'ulc_code_redeemed', $temp_code, $user_id );
			
			// Clean up the temporary code
			$this->cleanup_temp_code( $temp_code );
		}
	}
	
	/**
	 * Create temporary code for recipe triggering
	 */
	private function create_temp_code_for_recipe( $admin_batch_id, $user_id ) {
		global $wpdb;
		
		$tbl_codes = $wpdb->prefix . \uncanny_learndash_codes\Config::$tbl_codes;
		
		// Create a temporary code
		$temp_code_value = 'TEMP_' . time() . '_' . $user_id;
		
		$result = $wpdb->insert( $tbl_codes, array(
			'code_group' => $admin_batch_id,
			'code' => $temp_code_value,
			'is_active' => 1,
			'used_date' => current_time( 'mysql' ),
			'user_id' => $user_id,
			'date_redeemed' => current_time( 'mysql' )
		) );
		
		if ( $result ) {
			return $wpdb->insert_id;
		}
		
		return false;
	}
	
	/**
	 * Clean up temporary code
	 */
	private function cleanup_temp_code( $code_id ) {
		global $wpdb;
		
		$tbl_codes = $wpdb->prefix . \uncanny_learndash_codes\Config::$tbl_codes;
		
		$wpdb->delete( $tbl_codes, array( 'ID' => $code_id ) );
	}
	
	/**
	 * Create vendor-specific batch
	 */
	public static function create_vendor_batch( $vendor_id, $admin_product_id, $admin_batch_id, $batch_size = 10 ) {
		// Get vendor info
		$vendor = get_user_by( 'ID', $vendor_id );
		if ( ! $vendor ) {
			return false;
		}
		
		$store_info = function_exists( 'dokan_get_store_info' ) ? dokan_get_store_info( $vendor_id ) : array();
		$store_name = ! empty( $store_info['store_name'] ) ? $store_info['store_name'] : $vendor->display_name;
		
		// Get admin product info
		$admin_product = wc_get_product( $admin_product_id );
		if ( ! $admin_product ) {
			return false;
		}
		
		// Create vendor batch
		$group_args = array(
			'group-name'            => $store_name . ' - ' . $admin_product->get_name() . ' (' . current_time( 'mysql' ) . ')',
			'coupon-for'            => 'automator',
			'coupon-paid-unpaid'    => '',
			'coupon-prefix'         => '',
			'coupon-suffix'         => '',
			'coupon-amount'         => $batch_size,
			'coupon-max-usage'      => 1,
			'coupon-dash'           => '',
			'coupon-character-type' => array( 'numbers', 'uppercase-letters' ),
			'expiry-date'           => '',
			'expiry-time'           => '',
			'coupon-courses'        => array(),
			'coupon-group'          => array(),
			'product_id'            => 0, // Will be set after vendor product creation
		);
		
		$vendor_batch_id = \uncanny_learndash_codes\Database::add_code_group_batch( $group_args );
		
		if ( $vendor_batch_id ) {
			// Store the admin batch mapping
			update_post_meta( $vendor_batch_id, '_admin_batch_mapping', $admin_batch_id );
			update_post_meta( $vendor_batch_id, '_vendor_id', $vendor_id );
			update_post_meta( $vendor_batch_id, '_admin_product_id', $admin_product_id );
			
			error_log( 'Created vendor batch ' . $vendor_batch_id . ' for vendor ' . $vendor_id . ' mapping to admin batch ' . $admin_batch_id );
		}
		
		return $vendor_batch_id;
	}
	
	/**
	 * Generate codes for vendor batch
	 */
	public static function generate_codes_for_vendor_batch( $vendor_batch_id, $code_count, $vendor_id, $expiry_date = null ) {
		// Generate codes
		$codes = array();
		for ( $i = 0; $i < $code_count; $i++ ) {
			$codes[] = strtoupper( wp_generate_password( 10, false, false ) );
		}
		
		// Add codes to batch
		$inserted = \uncanny_learndash_codes\Database::add_codes_to_batch( 
			$vendor_batch_id, 
			$codes, 
			array( 'generation_type' => 'manual', 'coupon_amount' => $code_count ) 
		);
		
		if ( $inserted ) {
			// Assign vendor meta to each code
			$new_codes = \uncanny_learndash_codes\Database::get_coupons( $vendor_batch_id );
			foreach ( $new_codes as $code_obj ) {
				self::add_code_meta( $code_obj->ID, '_dokan_vendor_id', $vendor_id );
				self::add_code_meta( $code_obj->ID, '_vendor_batch_id', $vendor_batch_id );
				
				// Set expiry date on individual code if provided
				if ( $expiry_date ) {
					global $wpdb;
					$wpdb->update(
						\uncanny_learndash_codes\Config::$tbl_codes,
						array( 'expire_date' => $expiry_date ),
						array( 'ID' => $code_obj->ID ),
						array( '%s' ),
						array( '%d' )
					);
				}
			}
			
			error_log( 'Generated ' . $code_count . ' codes for vendor batch ' . $vendor_batch_id . ' with expiry: ' . $expiry_date );
			return true;
		}
		
		return false;
	}

	/**
	 * Get admin products that vendor can duplicate for code generation
	 */
	private function get_vendor_products_for_code_generation( $vendor_id ) {
		$products = array();

		// Get vendor's own duplicated automator_codes products
		$vendor_product_ids = get_admin_automator_code_products_for_vendor( 1, 100, false );
		foreach ( $vendor_product_ids as $vendor_product_id ) {
			$product = wc_get_product( $vendor_product_id );
			if ( $product && $product->get_type() === 'automator_codes' ) {
				$admin_product_id = get_post_meta( $vendor_product_id, '_parent_product_id', true );
				if ( $admin_product_id && class_exists( 'Online_Texas_Admin_Permissions' ) ) {
					$can_generate = Online_Texas_Admin_Permissions::vendor_can_generate_codes( $vendor_id, $admin_product_id );
					if ( $can_generate ) {
						$codes_generated = get_post_meta( $vendor_product_id, '_vendor_codes_generated', true ) ?: 0;
						$max_codes = Online_Texas_Admin_Permissions::get_max_codes( $admin_product_id );
						$products[] = array(
							'id' => $vendor_product_id, // Use vendor product ID for the form
							'name' => $product->get_name(),
							'codes_generated' => $codes_generated,
							'max_codes' => $max_codes,
							'admin_product_id' => $admin_product_id
						);
					}
				}
			}
		}

		return $products;
	}

	/**
	 * Get vendor's existing codes
	 */
	private function get_vendor_codes( $vendor_id, $paged = 1, $per_page = 20 ) {
		global $wpdb;
		
		$codes = array();
		$total_count = 0;
		
		// Safety check - ensure Uncanny classes are available
		if ( ! class_exists( '\uncanny_learndash_codes\Config' ) ) {
			error_log( 'Uncanny LearnDash Codes Config class not found' );
			return array( 'codes' => $codes, 'total' => $total_count );
		}
		
		// Get vendor's code IDs
		$vendor_code_ids = self::get_vendor_code_ids( $vendor_id );
		
		if ( ! empty( $vendor_code_ids ) ) {
			$tbl_codes = $wpdb->prefix . \uncanny_learndash_codes\Config::$tbl_codes;
			$tbl_usage = $wpdb->prefix . \uncanny_learndash_codes\Config::$tbl_codes_usage;
			
			// Safety check - ensure tables exist
			if ( ! self::table_exists( $tbl_codes ) || ! self::table_exists( $tbl_usage ) ) {
				error_log( 'Uncanny codes tables not found: ' . $tbl_codes . ' or ' . $tbl_usage );
				return array( 'codes' => $codes, 'total' => $total_count );
			}
			
			$code_ids_placeholders = implode( ',', array_fill( 0, count( $vendor_code_ids ), '%d' ) );
			
			// Get total count first
			$count_query = $wpdb->prepare(
				"SELECT COUNT(*) FROM {$tbl_codes} WHERE ID IN ({$code_ids_placeholders})",
				$vendor_code_ids
			);
			$total_count = $wpdb->get_var( $count_query );
			
			// Calculate offset for pagination
			$offset = ( $paged - 1 ) * $per_page;
			
			$query = $wpdb->prepare(
				"SELECT c.*, u.user_id as redeemed_user_id, u.date_redeemed, c.code_group 
				 FROM {$tbl_codes} c 
				 LEFT JOIN {$tbl_usage} u ON c.ID = u.code_id 
				 WHERE c.ID IN ({$code_ids_placeholders}) 
				 ORDER BY c.ID DESC
				 LIMIT %d OFFSET %d",
				array_merge( $vendor_code_ids, array( $per_page, $offset ) )
			);
			
			$results = $wpdb->get_results( $query );
			
			if ( $results ) {
				foreach ( $results as $row ) {
					$redeemed_by = '—';
					if ( $row->redeemed_user_id ) {
						$user = get_user_by( 'ID', $row->redeemed_user_id );
						$redeemed_by = $user ? $user->user_email : $row->redeemed_user_id;
					}
					
					$product_name = self::get_product_name_for_code( $row->code_group, $vendor_id );
					
					// All codes are registration type
					$code_type = 'registration';
					
					$codes[] = array(
						'code' => $row->code,
						'product_name' => $product_name,
						'type' => $code_type,
						'status' => $row->is_active ? 'active' : 'inactive',
						'redeemed_by' => $redeemed_by,
						'date' => $row->date_redeemed ?: '—'
					);
				}
			}
		}
		
		return array( 'codes' => $codes, 'total' => $total_count );
	}

	/**
	 * Create approval request
	 */
	private function create_approval_request( $vendor_id, $product_id, $codes_to_generate, $expiry_date, $code_type = 'registration' ) {
		// TODO: Implement approval request system
		
		// Store request in options for now (in production, use a proper table)
		$requests = get_option( 'vendor_code_requests', array() );
		$request_id = time() . '_' . $vendor_id;
		
		$requests[$request_id] = array(
			'vendor_id' => $vendor_id,
			'product_id' => $product_id,
			'codes_requested' => $codes_to_generate,
			'expiry_date' => $expiry_date,
			'code_type' => 'registration', // Always registration
			'status' => 'pending',
			'request_date' => current_time( 'mysql' )
		);
		
		update_option( 'vendor_code_requests', $requests );
		
		return $request_id;
	}

	/**
	 * Generate codes for vendor (static wrapper)
	 */
	public static function generate_codes_for_vendor_static( $vendor_id, $vendor_product_id, $codes_to_generate, $expiry_date, $code_type = 'registration' ) {
		$instance = new self();
		return $instance->generate_codes_for_vendor( $vendor_id, $vendor_product_id, $codes_to_generate, $expiry_date, 'registration' );
	}

	/**
	 * Generate codes for vendor
	 */
	public function generate_codes_for_vendor( $vendor_id, $vendor_product_id, $codes_to_generate, $expiry_date, $code_type ) {
		// Get vendor's batch ID from the vendor product
		$vendor_batch_id = get_post_meta( $vendor_product_id, '_vendor_batch_id', true );
		
		// Fallback: Check for old meta key
		if ( ! $vendor_batch_id ) {
			$vendor_batch_id = get_post_meta( $vendor_product_id, 'codes_group_name', true );
			
			// If found in old meta key, migrate to new meta key
			if ( $vendor_batch_id ) {
				update_post_meta( $vendor_product_id, '_vendor_batch_id', $vendor_batch_id );
			}
		}
		
		if ( ! $vendor_batch_id ) {
			return false;
		}
		
		// Generate codes
		$result = self::generate_codes_for_vendor_batch( $vendor_batch_id, $codes_to_generate, $vendor_id, $expiry_date );
		
		if ( $result ) {
			// Update vendor's code count
			$current_count = get_post_meta( $vendor_product_id, '_vendor_codes_generated', true ) ?: 0;
			$new_count = $current_count + $codes_to_generate;
			update_post_meta( $vendor_product_id, '_vendor_codes_generated', $new_count );
			
			// Set expiry date if provided
			if ( $expiry_date ) {
				update_post_meta( $vendor_batch_id, '_vendor_batch_expiry', $expiry_date );
			}
			
			// Set code type (always registration)
			update_post_meta( $vendor_batch_id, '_vendor_code_type', 'registration' );
			
			return true;
		}
		
		return false;
	}
}