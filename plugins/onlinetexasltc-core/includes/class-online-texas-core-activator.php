<?php
/**
 * Fired during plugin activation
 *
 * @link       https://wbcomdesigns.com/
 * @since      1.0.0
 *
 * @package    Online_Texas_Core
 * @subpackage Online_Texas_Core/includes
 */

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 * @package    Online_Texas_Core
 * @subpackage Online_Texas_Core/includes
 * @author     Wbcom Designs <admin@wbcomdesigns.com>
 */
class Online_Texas_Core_Activator {

	/**
	 * Plugin activation handler.
	 *
	 * Performs necessary checks and setup during plugin activation including:
	 * - WordPress and PHP version validation
	 * - Required plugin dependency checks
	 * - Database setup and default options
	 * - Rewrite rules flush
	 *
	 * @since    1.0.0
	 */
	public static function activate() {
		// Check WordPress version
		if ( version_compare( get_bloginfo( 'version' ), '5.0', '<' ) ) {
			deactivate_plugins( plugin_basename( __FILE__ ) );
			wp_die( 
				esc_html__( 'This plugin requires WordPress 5.0 or higher. Please update WordPress before activating this plugin.', 'online-texas-core' ),
				esc_html__( 'Plugin Activation Error', 'online-texas-core' ),
				array( 'back_link' => true )
			);
		}

		// Check PHP version
		if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
			deactivate_plugins( plugin_basename( __FILE__ ) );
			wp_die( 
				esc_html__( 'This plugin requires PHP 7.4 or higher. Please contact your hosting provider to upgrade PHP.', 'online-texas-core' ),
				esc_html__( 'Plugin Activation Error', 'online-texas-core' ),
				array( 'back_link' => true )
			);
		}

		// Check required plugins
		$missing_plugins = self::check_required_plugins();

		if ( ! empty( $missing_plugins ) ) {
			deactivate_plugins( plugin_basename( __FILE__ ) );
			
			$message = sprintf(
				/* translators: %s: List of missing plugins */
				esc_html__( 'Online Texas Core requires the following plugins to be installed and activated: %s', 'online-texas-core' ),
				'<br><strong>' . implode( '</strong><br><strong>', $missing_plugins ) . '</strong>'
			);
			
			wp_die( 
				$message,
				esc_html__( 'Plugin Activation Error', 'online-texas-core' ),
				array( 'back_link' => true )
			);
		}

		// Create default options
		self::create_default_options();

		// Set default product availability for existing products
		self::set_default_product_availability();

		// Set plugin version for future upgrades
		update_option( 'online_texas_core_version', ONLINE_TEXAS_CORE_VERSION );

		add_rewrite_endpoint('my-courses', EP_ROOT | EP_PAGES);

		// Flush rewrite rules
		flush_rewrite_rules();

		// Log activation

		self::create_uncanny_codes_code_meta_table();
	}

	/**
	 * Check if all required plugins are active and meet minimum version requirements.
	 * WooCommerce is handled by "Requires Plugins" header.
	 *
	 * @since 1.0.0
	 * @return array Array of missing or outdated plugins.
	 */
	private static function check_required_plugins() {
		$missing_plugins = array();

		// Check Dokan
		if ( ! function_exists( 'dokan' ) || ! ( dokan() instanceof WeDevs_Dokan ) ) {
			$missing_plugins[] = 'Dokan 3.0+';
		}

		// Check LearnDash - check for class or constant
		$learndash_active = false;
		if ( defined( 'LEARNDASH_VERSION' ) ) {
			$learndash_active = true;
		} elseif ( class_exists( 'SFWD_LMS' ) || class_exists( 'LearnDash_Settings_Section' ) ) {
			$learndash_active = true;
		}

		if ( ! $learndash_active ) {
			$missing_plugins[] = 'LearnDash 3.0+';
		}

		return $missing_plugins;
	}

	/**
	 * Create default plugin options.
	 * Task 1.3: Update default settings to be more restrictive.
	 *
	 * @since 1.0.0
	 */
	private static function create_default_options() {
		// Task 1.3: Updated default options
		$default_options = array(
			'auto_create_for_new_vendors' => false,  // Changed from true
			'debug_mode'                  => false,
			'vendor_product_status'       => 'draft',
			'plugin_enabled'              => false   // New master switch
		);

		// Only add options if they don't already exist
		if ( false === get_option( 'otc_options' ) ) {
			add_option( 'otc_options', $default_options );
		} else {
			// Update existing options with new defaults if they don't exist
			$existing_options = get_option( 'otc_options', array() );
			$updated = false;
			
			foreach ( $default_options as $key => $default_value ) {
				if ( ! isset( $existing_options[ $key ] ) ) {
					$existing_options[ $key ] = $default_value;
					$updated = true;
				}
			}
			
			if ( $updated ) {
				update_option( 'otc_options', $existing_options );
			}
		}

		// Initialize debug log
		if ( false === get_option( 'otc_debug_log' ) ) {
			add_option( 'otc_debug_log', array() );
		}

		// Set activation timestamp
		if ( false === get_option( 'otc_activated_on' ) ) {
			add_option( 'otc_activated_on', current_time( 'mysql' ) );
		}
	}

	/**
	 * Set default product availability for existing products.
	 * Task 1.3: Products with courses default to "Available to All Vendors",
	 * products without courses default to "Not Available".
	 *
	 * @since 1.1.0
	 */
	private static function set_default_product_availability() {
		// Get all products that don't have availability settings
		$products_without_availability = get_posts( array(
			'post_type'      => 'product',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'meta_query'     => array(
				array(
					'key'     => '_available_for_vendors',
					'compare' => 'NOT EXISTS'
				)
			),
			'fields' => 'ids'
		) );

		$updated_count = 0;

		foreach ( $products_without_availability as $product_id ) {
			// Check if product has linked courses
			$course_ids = self::get_product_courses( $product_id );
			
			if ( ! empty( $course_ids ) ) {
				// Product has courses - default to available to all vendors
				update_post_meta( $product_id, '_available_for_vendors', 'yes' );
				update_post_meta( $product_id, '_restricted_vendors', array() );
			} else {
				// Product has no courses - default to not available
				update_post_meta( $product_id, '_available_for_vendors', 'no' );
				update_post_meta( $product_id, '_restricted_vendors', array() );
			}
			
			$updated_count++;
		}

		if ( $updated_count > 0 ) {
	
		}
	}

	/**
	 * Get courses linked to a product (helper method for activation).
	 *
	 * @since 1.1.0
	 * @param int $product_id The product ID.
	 * @return array Array of course IDs.
	 */
	private static function get_product_courses( $product_id ) {
		// Get direct course links
		$courses = get_post_meta( $product_id, '_related_course', true );
		if ( ! is_array( $courses ) ) {
			$courses = ! empty( $courses ) ? array( $courses ) : array();
		}

		// Get courses from groups
		$groups = get_post_meta( $product_id, '_related_group', true );
		if ( ! empty( $groups ) && is_array( $groups ) ) {
			foreach ( $groups as $group_id ) {
				if ( function_exists( 'learndash_group_enrolled_courses' ) ) {
					$group_courses = learndash_group_enrolled_courses( $group_id );
					if ( ! empty( $group_courses ) ) {
						$courses = array_merge( $courses, (array) $group_courses );
					}
				}
			}
		}

		return array_unique( array_filter( array_map( 'intval', $courses ) ) );
	}

	/**
	 * Check if this is a fresh installation or an upgrade.
	 *
	 * @since 1.1.0
	 * @return bool True if this is a fresh installation, false if upgrade.
	 */
	private static function is_fresh_installation() {
		$existing_version = get_option( 'online_texas_core_version' );
		return empty( $existing_version );
	}

	/**
	 * Run upgrade routines if needed.
	 *
	 * @since 1.1.0
	 */
	private static function maybe_upgrade() {
		$current_version = get_option( 'online_texas_core_version', '1.0.0' );
		
		// Run upgrade routines based on version
		if ( version_compare( $current_version, '1.1.0', '<' ) ) {
			self::upgrade_to_1_1_0();
		}

		// Update version after all upgrades
		update_option( 'online_texas_core_version', ONLINE_TEXAS_CORE_VERSION );
	}

	/**
	 * Upgrade routine for version 1.1.0.
	 *
	 * @since 1.1.0
	 */
	private static function upgrade_to_1_1_0() {
		// Add any new default options
		$options = get_option( 'otc_options', array() );
		
		// Add new options if they don't exist
		$new_defaults = array(
			'auto_create_for_new_vendors' => false,  // Changed default for existing installations
			'vendor_product_status'       => 'draft',
			'plugin_enabled'              => false   // New master switch, default to disabled
		);

		foreach ( $new_defaults as $key => $default_value ) {
			if ( ! isset( $options[ $key ] ) ) {
				$options[ $key ] = $default_value;
			}
		}

		update_option( 'otc_options', $options );

		// Set default product availability for existing products
		self::set_default_product_availability();

		// Log upgrade

	}

	/**
	 * Create the uncanny_codes_code_meta table if it doesn't exist
	 */
	public static function create_uncanny_codes_code_meta_table() {
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
		dbDelta( $sql );
	}
}