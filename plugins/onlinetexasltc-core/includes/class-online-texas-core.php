<?php

/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       https://wbcomdesigns.com/
 * @since      1.0.0
 *
 * @package    Online_Texas_Core
 * @subpackage Online_Texas_Core/includes
 */

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    Online_Texas_Core
 * @subpackage Online_Texas_Core/includes
 * @author     Wbcom Designs <admin@wbcomdesigns.com>
 */
class Online_Texas_Core {

	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      Online_Texas_Core_Loader    $loader    Maintains and registers all hooks for the plugin.
	 */
	protected $loader;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $plugin_name    The string used to uniquely identify this plugin.
	 */
	protected $plugin_name;

	/**
	 * The current version of the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $version    The current version of the plugin.
	 */
	protected $version;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the admin area and
	 * the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		if ( defined( 'ONLINE_TEXAS_CORE_VERSION' ) ) {
			$this->version = ONLINE_TEXAS_CORE_VERSION;
		} else {
			$this->version = '1.0.0';
		}
		$this->plugin_name = 'online-texas-core';

		$this->load_dependencies();
		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_public_hooks();

		// Load vendor codes module if present
		if ( file_exists( ONLINE_TEXAS_CORE_PATH . 'includes/class-online-texas-vendor-codes.php' ) ) {
			require_once ONLINE_TEXAS_CORE_PATH . 'includes/class-online-texas-vendor-codes.php';
			if ( class_exists( 'Online_Texas_Vendor_Codes' ) ) {
				Online_Texas_Vendor_Codes::instance();
			}
		}
	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * Include the following files that make up the plugin:
	 *
	 * - Online_Texas_Core_Loader. Orchestrates the hooks of the plugin.
	 * - Online_Texas_Core_i18n. Defines internationalization functionality.
	 * - Online_Texas_Core_Admin. Defines all hooks for the admin area.
	 * - Online_Texas_Core_Public. Defines all hooks for the public side of the site.
	 *
	 * Create an instance of the loader which will be used to register the hooks
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function load_dependencies() {

		/**
		 * The class responsible for orchestrating the actions and filters of the
		 * core plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-online-texas-core-loader.php';

		/**
		 * The class responsible for defining internationalization functionality
		 * of the plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-online-texas-core-i18n.php';


		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/online-texas-general-functions.php';

		/**
		 * The class responsible for defining all actions that occur in the admin area.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-online-texas-core-admin.php';

		/**
		 * The class responsible for defining all actions that occur in the public-facing
		 * side of the site.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'public/class-online-texas-core-public.php';

		$this->loader = new Online_Texas_Core_Loader();

	}

	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * Uses the Online_Texas_Core_i18n class in order to set the domain and to register the hook
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function set_locale() {

		$plugin_i18n = new Online_Texas_Core_i18n();

		$this->loader->add_action( 'plugins_loaded', $plugin_i18n, 'load_plugin_textdomain' );

	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_admin_hooks() {

		$plugin_admin = new Online_Texas_Core_Admin( $this->get_plugin_name(), $this->get_version() );

		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );

		// Add admin menu
		$this->loader->add_action( 'admin_menu', $plugin_admin, 'add_admin_menu' );

		// Add AJAX handlers
		$this->loader->add_action( 'wp_ajax_otc_manual_vendor_sync', $plugin_admin, 'ajax_manual_vendor_sync' );
		$this->loader->add_action( 'wp_ajax_otc_clear_debug_log', $plugin_admin, 'ajax_clear_debug_log' );
		$this->loader->add_action( 'wp_ajax_otc_sync_product_to_vendors', $plugin_admin, 'ajax_sync_product_to_vendors' );

		// Handle admin product save (for creating vendor products)
		$this->loader->add_action( 'save_post', $plugin_admin, 'save_product', 100, 2 );

		// Handle vendor product updates (for syncing vendor product price to their group)
		$this->loader->add_action( 'save_post', $plugin_admin, 'handle_vendor_product_update', 150, 2 );

		// Add vendor product columns
		$this->loader->add_filter( 'manage_product_posts_columns', $plugin_admin, 'add_product_columns' );
 		$this->loader->add_action( 'manage_product_posts_custom_column', $plugin_admin, 'populate_product_columns', 10, 2 );

		// Load vendor sync class if needed
		if ( function_exists( 'dokan' ) ) {
			require_once ONLINE_TEXAS_CORE_PATH . 'includes/class-online-texas-core-vendor-sync.php';
			$vendor_sync = new Online_Texas_Core_Vendor_Sync( $plugin_admin );
		}
	}

	/**
	 * Register all of the hooks related to the public-facing functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_public_hooks() {

		$plugin_public = new Online_Texas_Core_Public( $this->get_plugin_name(), $this->get_version() );

		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_styles',999 );
		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_scripts' );

		// Add the custom tab to vendor dashboard
		$this->loader->add_filter( 'dokan_query_var_filter', $plugin_public, 'add_admin_products_endpoint' );

		// Add the tab to dashboard navigation
		$this->loader->add_filter( 'dokan_get_dashboard_nav', $plugin_public, 'add_admin_products_tab' );

		// Add rewrite rule for the endpoint
		$this->loader->add_action( 'init', $plugin_public, 'add_admin_products_rewrite_rule' );

		// Handle the template for admin products page
		$this->loader->add_action( 'dokan_load_custom_template', $plugin_public, 'load_admin_products_template', 10, 1 );

		// Handle AJAX request for duplicating products
		$this->loader->add_action( 'wp_ajax_duplicate_admin_product', $plugin_public, 'handle_duplicate_admin_product' );
		$this->loader->add_action( 'wp_ajax_nopriv_duplicate_admin_product', $plugin_public, 'handle_duplicate_admin_product' );

		$this->loader->add_action( 'wp_ajax_fetch_products_lists', $plugin_public, 'fetch_products_lists_callback' );
		$this->loader->add_action( 'wp_ajax_nopriv_fetch_products_lists', $plugin_public, 'fetch_products_lists_callback' );
		
		$this->loader->add_action( 'init', $plugin_public, 'add_learndash_courses_endpoint' );
		$this->loader->add_filter( 'woocommerce_account_menu_items', $plugin_public, 'add_learndash_courses_menu' );
		$this->loader->add_action( 'woocommerce_account_my-courses_endpoint', $plugin_public, 'learndash_courses_endpoint_content' );
		$this->loader->add_filter( 'query_vars', $plugin_public, 'add_learndash_courses_query_vars',0 );


		$this->loader->add_filter( 'learndash_notifications_shortcode_output', $plugin_public, 'wbcom_fetch_vendor_details', 10, 3 );
		$this->loader->add_filter( 'learndash_notifications_shortcodes_instructions', $plugin_public, 'wbcom_show_shortcode_attributes', 10, 2 );
		
	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since    1.0.0
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @since     1.0.0
	 * @return    string    The name of the plugin.
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @since     1.0.0
	 * @return    Online_Texas_Core_Loader    Orchestrates the hooks of the plugin.
	 */
	public function get_loader() {
		return $this->loader;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @since     1.0.0
	 * @return    string    The version number of the plugin.
	 */
	public function get_version() {
		return $this->version;
	}

}