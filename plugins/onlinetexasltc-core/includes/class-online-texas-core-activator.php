<?php

/**
 * Fired during plugin activation
 *
 * @link       https://https://wbcomdesigns.com/
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
 * @author     Wbcom <admin@wbcomdesigns.com>
 */
class Online_Texas_Core_Activator {

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.0.0
	 */
	public static function activate() {
		if (!class_exists('WooCommerce') || !class_exists('WeDevs_Dokan') || !defined('LEARNDASH_VERSION')) {
			deactivate_plugins(plugin_basename(__FILE__));
			add_action('admin_notices', function (){
			echo '<div class="notice notice-error"><p>';
			echo 'This plugin requires: WooCommerce, Dokan, and LearnDash';
			echo '</p></div>';
		});
		}
	}

}
