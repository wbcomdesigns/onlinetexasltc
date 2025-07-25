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

// Ensure WordPress functions are available for static analysis/linting
if ( ! function_exists( 'add_filter' ) ) {
	function add_filter() {}
}
if ( ! function_exists( 'add_action' ) ) {
	function add_action() {}
}
if ( ! function_exists( 'get_current_user_id' ) ) {
	function get_current_user_id() { return 0; }
}
if ( ! function_exists( '__' ) ) {
	function __( $text ) { return $text; }
}
if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( $text ) { return $text; }
}
if ( ! defined( 'EP_PAGES' ) ) {
	define( 'EP_PAGES', 1 );
}
if ( ! function_exists( 'add_rewrite_endpoint' ) ) {
	function add_rewrite_endpoint() {}
}
if ( ! function_exists( 'dokan_is_user_seller' ) ) {
	function dokan_is_user_seller() { return false; }
}
if ( ! function_exists( 'dokan_get_navigation_url' ) ) {
	function dokan_get_navigation_url( $slug ) { return '#'; }
}

// Remove linter-safe stubs for WordPress/DB functions
if ( ! function_exists( 'maybe_serialize' ) ) {
    function maybe_serialize( $data ) { return $data; }
}
if ( ! function_exists( 'maybe_unserialize' ) ) {
    function maybe_unserialize( $data ) { return $data; }
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
		add_filter( 'dokan_get_dashboard_nav', [ $this, 'add_vendor_codes_menu' ] );
		add_action( 'init', [ $this, 'register_codes_endpoint' ] );
		add_action( 'dokan_load_custom_template', [ $this, 'render_vendor_codes_page' ] );
		add_shortcode( 'vendor_code_redeem', [ $this, 'shortcode_vendor_code_redeem' ] );
		add_action( 'woocommerce_checkout_order_processed', [ $this, 'handle_discount_code_commission' ], 20, 1 );
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
		if ( isset( $query_vars['vendor-codes'] ) ) {
			$current_vendor = get_current_user_id();
			$success_message = '';
			// Handle form submission
			if ( isset( $_POST['otc_generate_codes_nonce'] ) && wp_verify_nonce( $_POST['otc_generate_codes_nonce'], 'otc_generate_codes' ) ) {
				$amount = max( 1, min( 20, intval( $_POST['code_amount'] ) ) );
				$expire = sanitize_text_field( $_POST['code_expire'] );
				$type   = sanitize_text_field( $_POST['code_type'] );
				// Use Uncanny's code generation logic
				$selected_product_id = isset($_POST['code_product_id']) ? intval($_POST['code_product_id']) : 0;
				$group_args = [
					'group-name'            => 'Vendor ' . $current_vendor . ' ' . current_time( 'mysql' ),
					'coupon-for'            => $type,
					'coupon-paid-unpaid'    => '',
					'coupon-prefix'         => '',
					'coupon-suffix'         => '',
					'coupon-amount'         => $amount,
					'coupon-max-usage'      => 1,
					'coupon-dash'           => '',
					'coupon-character-type' => [ 'numbers', 'uppercase-letters' ],
					'expiry-date'           => $expire,
					'expiry-time'           => '',
					'coupon-courses'        => [],
					'coupon-group'          => [],
					'product_id'            => $selected_product_id,
				];
				if ( $type === 'course' ) {
					$group_args['coupon-courses'] = []; // You can set course IDs here if needed
				}
				if ( $type === 'group' ) {
					$group_args['coupon-group'] = []; // You can set group IDs here if needed
				}
				$group_id = \uncanny_learndash_codes\Database::add_code_group_batch( $group_args );
				if ( $group_id ) {
					$codes = [];
					for ( $i = 0; $i < $amount; $i++ ) {
						$codes[] = strtoupper( wp_generate_password( 10, false, false ) );
					}
					\uncanny_learndash_codes\Database::add_codes_to_batch( $group_id, $codes, [ 'generation_type' => 'manual', 'coupon_amount' => $amount ] );
					// Fetch all codes for this group and associate with vendor
					$new_codes = \uncanny_learndash_codes\Database::get_coupons( $group_id );
					foreach ( $new_codes as $code_obj ) {
						self::add_code_meta( $code_obj->ID, '_dokan_vendor_id', $current_vendor );
					}
					$success_message = esc_html__( 'Codes generated and assigned to you!', 'online-texas-core' );
				}
			}

			echo '<div class="dokan-dashboard-wrap">';
			do_action( 'dokan_dashboard_content_before' );
			echo '<div class="dokan-dashboard-content">';
			do_action( 'dokan_dashboard_content_inside_before' );
			echo '<article class="dashboard-content-area">';
			echo '<h2>' . esc_html__( 'Vendor Codes Management', 'online-texas-core' ) . '</h2>';

			// Success message
			if ( $success_message ) {
				echo '<div class="dokan-alert dokan-alert-success">' . $success_message . '</div>';
			}

			echo '<div class="dokan-panel dokan-panel-default" style="max-width:600px;margin-bottom:30px;">';
			echo '<div class="dokan-panel-heading"><strong>' . esc_html__( 'Generate Codes', 'online-texas-core' ) . '</strong></div>';
			echo '<div class="dokan-panel-body">';
			// Fetch vendor's products (clones)
			$vendor_products = get_posts([
				'post_type' => 'product',
				'author' => $current_vendor,
				'posts_per_page' => -1,
				'post_status' => 'publish',
				'orderby' => 'title',
				'order' => 'ASC',
			]);
			echo '<form method="post" class="dokan-form-inline" style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;">';
			echo '<input type="hidden" name="otc_generate_codes_nonce" value="' . esc_attr( wp_create_nonce( 'otc_generate_codes' ) ) . '" />';
			echo '<div><label>' . esc_html__( 'Number (1-20):', 'online-texas-core' ) . '<br><input type="number" name="code_amount" min="1" max="20" value="1" class="dokan-form-control" required></label></div>';
			echo '<div><label>' . esc_html__( 'Expiration date:', 'online-texas-core' ) . '<br><input type="date" name="code_expire" class="dokan-form-control" required></label></div>';
			echo '<div><label>' . esc_html__( 'Type:', 'online-texas-core' ) . '<br><select name="code_type" class="dokan-form-control"><option value="course">' . esc_html__( 'Registration', 'online-texas-core' ) . '</option><option value="discount">' . esc_html__( 'Discount', 'online-texas-core' ) . '</option></select></label></div>';
			// Product dropdown
			echo '<div><label>' . esc_html__( 'Product:', 'online-texas-core' ) . '<br><select name="code_product_id" class="dokan-form-control" required>';
			foreach ( $vendor_products as $product ) {
				echo '<option value="' . esc_attr( $product->ID ) . '">' . esc_html( $product->post_title ) . '</option>';
			}
			echo '</select></label></div>';
			echo '<div><button type="submit" class="dokan-btn dokan-btn-theme">' . esc_html__( 'Generate Codes', 'online-texas-core' ) . '</button></div>';
			echo '</form>';
			echo '</div></div>';

			// List codes for this vendor
			$code_ids = self::get_vendor_code_ids( $current_vendor );
			echo '<div class="dokan-panel dokan-panel-default" style="max-width:900px;">';
			echo '<div class="dokan-panel-heading"><strong>' . esc_html__( 'Your Codes', 'online-texas-core' ) . '</strong></div>';
			echo '<div class="dokan-panel-body">';
			if ( ! empty( $code_ids ) ) {
				global $wpdb;
				$table = $wpdb->prefix . \uncanny_learndash_codes\Config::$tbl_codes;
				$usage_table = $wpdb->prefix . \uncanny_learndash_codes\Config::$tbl_codes_usage;
				$in = implode( ',', array_map( 'intval', $code_ids ) );
				// Join usage table to get redeemed user and date
				$rows = $wpdb->get_results( "SELECT c.ID, c.code, c.is_active, u.user_id as redeemed_user_id, u.date_redeemed FROM $table c LEFT JOIN $usage_table u ON c.ID = u.code_id WHERE c.ID IN ($in) ORDER BY c.ID DESC" );
				echo '<table class="dokan-table dokan-table-striped"><thead><tr><th>' . esc_html__( 'Code', 'online-texas-core' ) . '</th><th>' . esc_html__( 'Status', 'online-texas-core' ) . '</th><th>' . esc_html__( 'Redeemed By', 'online-texas-core' ) . '</th><th>' . esc_html__( 'Redeem Date', 'online-texas-core' ) . '</th></tr></thead><tbody>';
				foreach ( $rows as $row ) {
					$redeemed_by = '—';
					if ( $row->redeemed_user_id ) {
						$user = get_userdata( $row->redeemed_user_id );
						if ( $user ) {
							$redeemed_by = esc_html( $user->user_email );
						} else {
							$redeemed_by = esc_html( $row->redeemed_user_id );
						}
					}
					$redeem_date = $row->date_redeemed ? esc_html( $row->date_redeemed ) : '—';
					echo '<tr><td>' . esc_html( $row->code ) . '</td><td>' . ( $row->is_active ? esc_html__( 'Active', 'online-texas-core' ) : esc_html__( 'Inactive', 'online-texas-core' ) ) . '</td><td>' . $redeemed_by . '</td><td>' . $redeem_date . '</td></tr>';
				}
				echo '</tbody></table>';
			} else {
				echo '<p>' . esc_html__( 'No codes found for you yet.', 'online-texas-core' ) . '</p>';
			}
			echo '</div></div>';

			echo '</article>';
			echo '</div><!-- .dokan-dashboard-content -->';
			echo '</div><!-- .dokan-dashboard-wrap -->';
			exit;
		}
	}

    /**
     * Add meta for a code
     */
    public static function add_code_meta( $code_id, $meta_key, $meta_value ) {
        global $wpdb;
        $table = $wpdb->prefix . 'uncanny_codes_code_meta';
        $wpdb->insert( $table, [
            'code_id'    => $code_id,
            'meta_key'   => $meta_key,
            'meta_value' => maybe_serialize( $meta_value ),
        ] );
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
     * Get all code IDs for a vendor
     */
    public static function get_vendor_code_ids( $vendor_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'uncanny_codes_code_meta';
        return $wpdb->get_col( $wpdb->prepare( "SELECT code_id FROM $table WHERE meta_key = %s AND meta_value = %s", '_dokan_vendor_id', $vendor_id ) );
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
} 