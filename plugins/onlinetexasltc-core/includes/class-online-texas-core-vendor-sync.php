<?php
/**
 * Vendor synchronization functionality.
 *
 * Handles automatic creation of vendor products when users become vendors
 * through Dokan or WordPress role changes.
 *
 * @link       https://wbcomdesigns.com/
 * @since      1.1.0
 *
 * @package    Online_Texas_Core
 * @subpackage Online_Texas_Core/includes
 */

/**
 * The vendor sync class.
 *
 * This class handles the automatic creation of vendor products when:
 * - New vendors are created via Dokan
 * - Existing users get vendor roles
 * - Vendors are enabled/reactivated
 *
 * @since      1.1.0
 * @package    Online_Texas_Core
 * @subpackage Online_Texas_Core/includes
 * @author     Wbcom Designs <admin@wbcomdesigns.com>
 */
class Online_Texas_Core_Vendor_Sync {

	/**
	 * Reference to the admin class.
	 *
	 * @since    1.1.0
	 * @access   private
	 * @var      Online_Texas_Core_Admin    $admin    Reference to admin class.
	 */
	private $admin;

	/**
	 * Initialize the class and set up hooks.
	 *
	 * @since    1.1.0
	 * @param    Online_Texas_Core_Admin $admin Reference to the admin class.
	 */
	public function __construct( $admin ) {
		$this->admin = $admin;
		$this->init_hooks();
	}

	/**
	 * Initialize hooks for vendor events.
	 *
	 * @since 1.1.0
	 */
	private function init_hooks() {
		// Dokan vendor events
		add_action( 'dokan_new_seller_created', array( $this, 'new_vendor_created' ), 10, 2 );
		add_action( 'dokan_seller_enabled', array( $this, 'vendor_enabled' ), 10, 1 );

		// WordPress role changes
		add_action( 'set_user_role', array( $this, 'user_role_changed' ), 10, 3 );
		add_action( 'add_user_role', array( $this, 'user_role_added' ), 10, 2 );

		// User registration
		add_action( 'user_register', array( $this, 'user_registered' ), 10, 1 );

		// Scheduled event for delayed vendor check
		add_action( 'otc_check_new_user_vendor_status', array( $this, 'check_new_user_vendor_status' ) );
	}

	/**
	 * Handle new vendor creation via Dokan.
	 *
	 * @since 1.1.0
	 * @param int   $user_id        The user ID of the new vendor.
	 * @param array $dokan_settings Dokan settings array (optional).
	 */
	public function new_vendor_created( $user_id, $dokan_settings = null ) {
		$this->log_debug( "New vendor created via Dokan: {$user_id}" );
		$this->create_products_for_vendor( $user_id );
	}

	/**
	 * Handle vendor being enabled via Dokan.
	 *
	 * @since 1.1.0
	 * @param int $vendor_id The vendor user ID.
	 */
	public function vendor_enabled( $vendor_id ) {
		$this->log_debug( "Vendor enabled via Dokan: {$vendor_id}" );
		$this->create_products_for_vendor( $vendor_id );
	}

	/**
	 * Handle WordPress user role changes.
	 *
	 * @since 1.1.0
	 * @param int    $user_id   The user ID.
	 * @param string $role      The new role.
	 * @param array  $old_roles Array of old roles.
	 */
	public function user_role_changed( $user_id, $role, $old_roles ) {
		$was_vendor = $this->had_vendor_role( $old_roles );
		$is_vendor = $this->is_vendor( $user_id );

		if ( ! $was_vendor && $is_vendor ) {
			$this->log_debug( "User {$user_id} became vendor via role change" );
			$this->create_products_for_vendor( $user_id );
		}
	}

	/**
	 * Handle role being added to user.
	 *
	 * @since 1.1.0
	 * @param int    $user_id The user ID.
	 * @param string $role    The role being added.
	 */
	public function user_role_added( $user_id, $role ) {
		if ( $this->is_vendor_role( $role ) ) {
			$this->log_debug( "Vendor role added to user: {$user_id}" );
			$this->create_products_for_vendor( $user_id );
		}
	}

	/**
	 * Handle user registration.
	 *
	 * @since 1.1.0
	 * @param int $user_id The newly registered user ID.
	 */
	public function user_registered( $user_id ) {
		// Schedule a delayed check to ensure role is properly set
		wp_schedule_single_event( time() + 5, 'otc_check_new_user_vendor_status', array( $user_id ) );
	}

	/**
	 * Check new user vendor status (delayed).
	 *
	 * @since 1.1.0
	 * @param int $user_id The user ID to check.
	 */
	public function check_new_user_vendor_status( $user_id ) {
		if ( $this->is_vendor( $user_id ) ) {
			$this->log_debug( "New user registered as vendor: {$user_id}" );
			$this->create_products_for_vendor( $user_id );
		}
	}

	/**
	 * Create products for a vendor from all existing admin products.
	 * Task 1.3: Check plugin and auto-creation options.
	 *
	 * @since 1.1.0
	 * @param int $vendor_id The vendor user ID.
	 * @return int|false Number of products created or false on failure.
	 */
	public function create_products_for_vendor( $vendor_id ) {
		// Check if plugin functionality is enabled
		$options = get_option( 'otc_options', array() );
		if ( empty( $options['plugin_enabled'] ) ) {
			$this->log_debug( "Plugin functionality disabled for vendor: {$vendor_id}" );
			return false;
		}

		// Check if auto-creation is enabled
		if ( empty( $options['auto_create_for_new_vendors'] ) ) {
			$this->log_debug( "Auto-create disabled for vendor: {$vendor_id}" );
			return false;
		}

		// Validate vendor
		if ( ! $this->is_vendor( $vendor_id ) ) {
			$this->log_debug( "User {$vendor_id} is not a valid vendor" );
			return false;
		}

		// Get all admin products with courses
		$admin_products = $this->get_admin_products_with_courses();

		if ( empty( $admin_products ) ) {
			$this->log_debug( "No admin products found for vendor {$vendor_id}" );
			return 0;
		}

		$created_count = 0;
		$skipped_count = 0;

		foreach ( $admin_products as $admin_product_id ) {
			// Check if vendor already has this product
			if ( $this->vendor_has_product( $vendor_id, $admin_product_id ) ) {
				$skipped_count++;
				continue;
			}

			// Check product availability for this vendor
			$available_for_vendors = get_post_meta( $admin_product_id, '_available_for_vendors', true );
			$restricted_vendors = get_post_meta( $admin_product_id, '_restricted_vendors', true );

			// If availability is not set, default based on course presence
			if ( empty( $available_for_vendors ) ) {
				$course_ids = $this->get_product_courses( $admin_product_id );
				$available_for_vendors = ! empty( $course_ids ) ? 'yes' : 'no';
			}

			// Check if vendor is allowed to have this product
			$can_create = false;
			switch ( $available_for_vendors ) {
				case 'yes':
					$can_create = true;
					break;
				case 'selective':
					$can_create = is_array( $restricted_vendors ) && in_array( $vendor_id, $restricted_vendors );
					break;
				case 'no':
				default:
					$can_create = false;
					break;
			}

			if ( ! $can_create ) {
				$this->log_debug( "Product {$admin_product_id} not available for vendor {$vendor_id}" );
				$skipped_count++;
				continue;
			}

			// Get courses for this product
			$course_ids = $this->get_product_courses( $admin_product_id );

			if ( empty( $course_ids ) ) {
				continue;
			}

			// Create vendor product using the existing function
			$result = create_single_vendor_product( $admin_product_id, $vendor_id, $course_ids );

			if ( $result ) {
				$created_count++;
			}
		}

		$this->log_debug( "Created {$created_count} products for vendor {$vendor_id}, skipped {$skipped_count}" );

		return $created_count;
	}

	/**
	 * Check if a user is a vendor.
	 *
	 * @since 1.1.0
	 * @param int $user_id The user ID to check.
	 * @return bool True if user is a vendor, false otherwise.
	 */
	private function is_vendor( $user_id ) {
		return dokan_is_user_seller( $user_id );
	}

	/**
	 * Check if a role is a vendor role.
	 *
	 * @since 1.1.0
	 * @param string $role The role to check.
	 * @return bool True if it's a vendor role, false otherwise.
	 */
	private function is_vendor_role( $role ) {
		$vendor_roles = array( 'seller', 'vendor', 'shop_manager' );
		return in_array( $role, $vendor_roles, true );
	}

	/**
	 * Check if an array of roles contains vendor roles.
	 *
	 * @since 1.1.0
	 * @param array $roles Array of roles to check.
	 * @return bool True if contains vendor role, false otherwise.
	 */
	private function had_vendor_role( $roles ) {
		if ( ! is_array( $roles ) ) {
			return false;
		}

		$vendor_roles = array( 'seller', 'vendor', 'shop_manager' );
		return ! empty( array_intersect( $roles, $vendor_roles ) );
	}

	/**
	 * Get all admin products that have linked courses.
	 *
	 * @since 1.1.0
	 * @return array Array of admin product IDs.
	 */
	private function get_admin_products_with_courses() {
		$args = array(
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
			'fields'         => 'ids',
			'author__in'     => $this->get_admin_user_ids()
		);

		return get_posts( $args );
	}

	/**
	 * Get all admin user IDs.
	 *
	 * @since 1.1.0
	 * @return array Array of admin user IDs.
	 */
	private function get_admin_user_ids() {
		$admins = get_users( array(
			'role'   => 'administrator',
			'fields' => 'ID'
		) );

		return $admins;
	}

	/**
	 * Check if a vendor already has a product for a given admin product.
	 *
	 * @since 1.1.0
	 * @param int $vendor_id        The vendor user ID.
	 * @param int $admin_product_id The admin product ID.
	 * @return bool True if vendor has the product, false otherwise.
	 */
	private function vendor_has_product( $vendor_id, $admin_product_id ) {
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
			'fields'         => 'ids',
			'post_status'    => 'any'
		);

		$products = get_posts( $args );
		return ! empty( $products );
	}

	/**
	 * Get courses linked to a product.
	 *
	 * @since 1.1.0
	 * @param int $product_id The product ID.
	 * @return array Array of course IDs.
	 */
	private function get_product_courses( $product_id ) {
		// Get direct course links
		$courses = get_post_meta( $product_id, '_related_course', true );
		if ( ! is_array( $courses ) ) {
			$courses = ! empty( $courses ) ? array( $courses ) : array();
		}

		// Get courses from groups
		$groups = get_post_meta( $product_id, '_related_group', true );
		if ( ! empty( $groups ) && is_array( $groups ) ) {
			foreach ( $groups as $group_id ) {
				$group_courses = learndash_group_enrolled_courses( $group_id );
				if ( ! empty( $group_courses ) ) {
					$courses = array_merge( $courses, (array) $group_courses );
				}
			}
		}

		return array_unique( array_filter( array_map( 'intval', $courses ) ) );
	}

	/**
	 * Log debug messages.
	 *
	 * @since 1.1.0
	 * @param string $message The message to log.
	 */
	private function log_debug( $message ) {
		// Log to WordPress error log


		// Also save to plugin log if debug mode is on
		$options = get_option( 'otc_options', array() );
		if ( ! empty( $options['debug_mode'] ) ) {
			$log = get_option( 'otc_debug_log', array() );
			$log[] = array(
				'timestamp' => current_time( 'mysql' ),
				'message'   => sanitize_text_field( $message ),
				'type'      => 'vendor_sync'
			);

			// Keep only last 100 entries
			if ( count( $log ) > 100 ) {
				$log = array_slice( $log, -100 );
			}

			update_option( 'otc_debug_log', $log );
		}
	}
}