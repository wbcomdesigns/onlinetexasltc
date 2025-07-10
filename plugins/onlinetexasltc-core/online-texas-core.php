<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://https://wbcomdesigns.com/
 * @since             1.0.0
 * @package           Online_Texas_Core
 *
 * @wordpress-plugin
 * Plugin Name:       Online Texas Core
 * Plugin URI:        https://https://wbcomdesigns.com/
 * Description:       This Plugin holds the custom functionality developed by Wbcom
 * Version:           1.0.0
 * Author:            Wbcom
 * Author URI:        https://https://wbcomdesigns.com//
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       online-texas-core
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'ONLINE_TEXAS_CORE_VERSION', '1.0.0' );

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-online-texas-core-activator.php
 */
function activate_online_texas_core() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-online-texas-core-activator.php';
	Online_Texas_Core_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-online-texas-core-deactivator.php
 */
function deactivate_online_texas_core() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-online-texas-core-deactivator.php';
	Online_Texas_Core_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_online_texas_core' );
register_deactivation_hook( __FILE__, 'deactivate_online_texas_core' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-online-texas-core.php';

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
run_online_texas_core();

add_action('plugins_loaded', 'online_texas_check_dependencies');
/**
 * Check if required plugins are active
 */
function online_texas_check_dependencies() {
        return class_exists('WooCommerce') && 
               class_exists('WeDevs_Dokan') && 
               defined('LEARNDASH_VERSION');
    }