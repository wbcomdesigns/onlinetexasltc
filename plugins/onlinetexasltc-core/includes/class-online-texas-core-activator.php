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

		// Set plugin version for future upgrades
		update_option( 'online_texas_core_version', ONLINE_TEXAS_CORE_VERSION );

		// Flush rewrite rules
		flush_rewrite_rules();

		// Log activation
		error_log( 'Online Texas Core Plugin activated successfully.' );
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
	 *
	 * @since 1.0.0
	 */
	private static function create_default_options() {
		$default_options = array(
			'auto_create_for_new_vendors' => true,
			'debug_mode'                  => false,
			'vendor_product_status'       => 'draft'
		);

		// Only add options if they don't already exist
		if ( false === get_option( 'otc_options' ) ) {
			add_option( 'otc_options', $default_options );
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
			'auto_create_for_new_vendors' => true,
			'vendor_product_status'       => 'draft'
		);

		foreach ( $new_defaults as $key => $default_value ) {
			if ( ! isset( $options[ $key ] ) ) {
				$options[ $key ] = $default_value;
			}
		}

		update_option( 'otc_options', $options );

		// Log upgrade
		error_log( 'Online Texas Core Plugin upgraded to v1.1.0' );
	}
}