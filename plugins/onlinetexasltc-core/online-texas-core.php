<?php
/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://wbcomdesigns.com/
 * @since             1.0.0
 * @package           Online_Texas_Core
 *
 * Plugin Name: Online Texas Core
 * Plugin URI: https://wbcomdesigns.com/
 * Description: Creates vendor products and LearnDash groups automatically when admin creates products with course links. Integrates WooCommerce, Dokan and LearnDash.
 * Version: 1.1.0
 * Author: Wbcom Designs
 * Author URI: https://wbcomdesigns.com/
 * Text Domain: online-texas-core
 * Requires Plugins: woocommerce
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 * Domain Path: /languages/
 * License: GPL2
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 5.0
 * Tested up to: 6.3
 * Requires PHP: 7.4
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 */
define( 'ONLINE_TEXAS_CORE_VERSION', '1.1.0' );
define( 'ONLINE_TEXAS_CORE_PATH', plugin_dir_path( __FILE__ ) );
define( 'ONLINE_TEXAS_CORE_URL', plugin_dir_url( __FILE__ ) );

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-online-texas-core-activator.php
 */
function activate_online_texas_core() {
	require_once ONLINE_TEXAS_CORE_PATH . 'includes/class-online-texas-core-activator.php';
	Online_Texas_Core_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-online-texas-core-deactivator.php
 */
function deactivate_online_texas_core() {
	require_once ONLINE_TEXAS_CORE_PATH . 'includes/class-online-texas-core-deactivator.php';
	Online_Texas_Core_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_online_texas_core' );
register_deactivation_hook( __FILE__, 'deactivate_online_texas_core' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require ONLINE_TEXAS_CORE_PATH . 'includes/class-online-texas-core.php';

/**
 * Check if required plugins are active and meet minimum version requirements.
 * WooCommerce dependency is handled by "Requires Plugins" header.
 *
 * @since 1.1.0
 * @return bool True if all dependencies are met, false otherwise.
 */
function online_texas_check_dependencies() {
	$missing_plugins = array();

	// Check Dokan - proper way to check if Dokan is fully loaded
	if ( ! function_exists( 'dokan' ) || ! ( dokan() instanceof WeDevs_Dokan ) ) {
		$missing_plugins[] = 'Dokan 3.0+';
	}

	// Check LearnDash - since debug shows it's active, check for the constant first
	if ( ! defined( 'LEARNDASH_VERSION' ) && ! class_exists( 'SFWD_LMS' ) ) {
		$missing_plugins[] = 'LearnDash 3.0+';
	}

	if ( ! empty( $missing_plugins ) ) {
		add_action( 'admin_notices', function() use ( $missing_plugins ) {
			?>
			<div class="notice notice-error">
				<p><strong><?php esc_html_e( 'Online Texas Core Plugin Error:', 'online-texas-core' ); ?></strong></p>
				<p>
					<?php
					printf(
						/* translators: %s: List of missing plugins */
						esc_html__( 'Required plugins missing: %s', 'online-texas-core' ),
						'<strong>' . esc_html( implode( ', ', $missing_plugins ) ) . '</strong>'
					);
					?>
				</p>
				<p><?php esc_html_e( 'Plugin has been deactivated.', 'online-texas-core' ); ?></p>
			</div>
			<?php
		});

		// Deactivate this plugin
		deactivate_plugins( plugin_basename( __FILE__ ) );
		return false;
	}

	return true;
}

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_online_texas_core() {
	$plugin = new Online_Texas_Core();
	$plugin->run();
}

// Check dependencies and run plugin at the right time
add_action( 'plugins_loaded', function() {
	if ( online_texas_check_dependencies() ) {
		run_online_texas_core();
	}
}, 15 ); // Higher priority to ensure other plugins are loaded first