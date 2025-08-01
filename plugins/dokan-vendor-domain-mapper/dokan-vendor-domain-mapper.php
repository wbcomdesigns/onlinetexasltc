<?php
/**
 * Plugin Name: Dokan Vendor Domain Mapper
 * Plugin URI: https://wbcomdesigns.com
 * Description: Allow vendors to map custom domains to their Dokan store URLs with DNS verification and SSL support.
 * Version: 1.0.0
 * Author: Wbcom Designs
 * Author URI: https://wbcomdesigns.com
 * Text Domain: dokan-vendor-domain-mapper
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('DOKAN_DOMAIN_MAPPER_VERSION', '1.0.0');
define('DOKAN_DOMAIN_MAPPER_PLUGIN_FILE', __FILE__);
define('DOKAN_DOMAIN_MAPPER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('DOKAN_DOMAIN_MAPPER_PLUGIN_URL', plugin_dir_url(__FILE__));
define('DOKAN_DOMAIN_MAPPER_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Main Dokan Vendor Domain Mapper Class
 */
class Dokan_Vendor_Domain_Mapper {

    /**
     * Plugin instance
     */
    private static $instance = null;

    /**
     * Get plugin instance
     */
    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->register_plugin_hooks();
        $this->includes();
    }

    /**
     * Register plugin hooks
     */
    private function register_plugin_hooks() {
        add_action('plugins_loaded', array($this, 'setup_plugin'));
        add_action('init', array($this, 'load_textdomain'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }

    /**
     * Setup plugin functionality
     */
    public function setup_plugin() {
        // Check if Dokan is active
        if (!$this->is_dokan_active()) {
            add_action('admin_notices', array($this, 'dokan_missing_notice'));
            return;
        }

        // Load plugin components
        $this->load_plugin_components();
    }

    /**
     * Check if Dokan is active
     */
    private function is_dokan_active() {
        return class_exists('WeDevs_Dokan');
    }

    /**
     * Dokan missing notice
     */
    public function dokan_missing_notice() {
        ?>
        <div class="notice notice-error">
            <p><?php _e('Dokan Vendor Domain Mapper requires Dokan plugin to be installed and activated.', 'dokan-vendor-domain-mapper'); ?></p>
        </div>
        <?php
    }

    /**
     * Include required files
     */
    private function includes() {
        require_once DOKAN_DOMAIN_MAPPER_PLUGIN_DIR . 'includes/class-domain-mapper.php';
        require_once DOKAN_DOMAIN_MAPPER_PLUGIN_DIR . 'includes/class-dns-verifier.php';
        require_once DOKAN_DOMAIN_MAPPER_PLUGIN_DIR . 'includes/class-admin.php';
        require_once DOKAN_DOMAIN_MAPPER_PLUGIN_DIR . 'includes/class-vendor-dashboard.php';
        require_once DOKAN_DOMAIN_MAPPER_PLUGIN_DIR . 'includes/class-ssl-manager.php';
        require_once DOKAN_DOMAIN_MAPPER_PLUGIN_DIR . 'includes/class-proxy-manager.php';
        require_once DOKAN_DOMAIN_MAPPER_PLUGIN_DIR . 'includes/class-api.php';
        
        // Phase 3: Enhanced Functionality
        require_once DOKAN_DOMAIN_MAPPER_PLUGIN_DIR . 'includes/class-cloudflare-api.php';
        require_once DOKAN_DOMAIN_MAPPER_PLUGIN_DIR . 'includes/class-domain-analytics.php';
        require_once DOKAN_DOMAIN_MAPPER_PLUGIN_DIR . 'includes/class-notifications.php';
        require_once DOKAN_DOMAIN_MAPPER_PLUGIN_DIR . 'includes/class-domain-transfer.php';
        require_once DOKAN_DOMAIN_MAPPER_PLUGIN_DIR . 'includes/class-backup-manager.php';
    }

    /**
     * Load plugin components
     */
    private function load_plugin_components() {
        // Initialize core classes
        new Dokan_Domain_Mapper();
        new Dokan_DNS_Verifier();
        new Dokan_Domain_Mapper_Admin();
        new Dokan_Domain_Mapper_Vendor_Dashboard();
        new Dokan_SSL_Manager();
        new Dokan_Proxy_Manager();
        new Dokan_Domain_Mapper_API();
        
        // Initialize Phase 3: Enhanced Functionality
        new Dokan_Cloudflare_API();
        new Dokan_Domain_Analytics();
        new Dokan_Domain_Notifications();
        new Dokan_Domain_Transfer();
        new Dokan_Backup_Manager();
    }

    /**
     * Load text domain
     */
    public function load_textdomain() {
        load_plugin_textdomain('dokan-vendor-domain-mapper', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }

    /**
     * Plugin activation
     */
    public function activate() {
        // Create database tables
        $this->create_tables();
        
        // Set default options
        $this->set_default_options();
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Create database tables
     */
    private function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Main domain mappings table
        $table_name = $wpdb->prefix . 'dokan_domain_mappings';
        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            vendor_id bigint(20) NOT NULL,
            domain varchar(255) NOT NULL,
            status enum('pending','verified','approved','rejected','live') DEFAULT 'pending',
            ssl_status enum('none','manual','auto','cloudflare') DEFAULT 'none',
            ssl_certificate_path varchar(500),
            ssl_private_key_path varchar(500),
            ssl_expiry_date datetime,
            ssl_verification_required boolean DEFAULT true,
            verification_token varchar(64),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY domain (domain),
            KEY vendor_id (vendor_id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        // Domain analytics table
        $analytics_table = $wpdb->prefix . 'dokan_domain_analytics';
        $analytics_sql = "CREATE TABLE $analytics_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            domain_id bigint(20) NOT NULL,
            date datetime NOT NULL,
            status_code int(3) DEFAULT 0,
            response_time float DEFAULT 0,
            is_accessible tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY domain_id (domain_id),
            KEY date (date)
        ) $charset_collate;";
        dbDelta($analytics_sql);

        // Domain transfer requests table
        $transfer_table = $wpdb->prefix . 'dokan_domain_transfer_requests';
        $transfer_sql = "CREATE TABLE $transfer_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            domain_id bigint(20) NOT NULL,
            current_vendor_id bigint(20) NOT NULL,
            requesting_vendor_id bigint(20) NOT NULL,
            reason text,
            status enum('pending','approved','rejected') DEFAULT 'pending',
            rejection_reason text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY domain_id (domain_id),
            KEY current_vendor_id (current_vendor_id),
            KEY requesting_vendor_id (requesting_vendor_id)
        ) $charset_collate;";
        dbDelta($transfer_sql);

        // Domain transfer logs table
        $transfer_logs_table = $wpdb->prefix . 'dokan_domain_transfer_logs';
        $transfer_logs_sql = "CREATE TABLE $transfer_logs_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            domain_id bigint(20) NOT NULL,
            old_vendor_id bigint(20) NOT NULL,
            new_vendor_id bigint(20) NOT NULL,
            reason text,
            transferred_by bigint(20) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY domain_id (domain_id),
            KEY old_vendor_id (old_vendor_id),
            KEY new_vendor_id (new_vendor_id)
        ) $charset_collate;";
        dbDelta($transfer_logs_sql);

        // Backup logs table
        $backup_logs_table = $wpdb->prefix . 'dokan_backup_logs';
        $backup_logs_sql = "CREATE TABLE $backup_logs_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            filename varchar(255) NOT NULL,
            type varchar(50) NOT NULL,
            domains_count int(11) DEFAULT 0,
            created_by bigint(20) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY created_by (created_by),
            KEY created_at (created_at)
        ) $charset_collate;";
        dbDelta($backup_logs_sql);

        // Restoration logs table
        $restoration_logs_table = $wpdb->prefix . 'dokan_restoration_logs';
        $restoration_logs_sql = "CREATE TABLE $restoration_logs_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            filename varchar(255) NOT NULL,
            restored_by bigint(20) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY restored_by (restored_by),
            KEY created_at (created_at)
        ) $charset_collate;";
        dbDelta($restoration_logs_sql);
    }

    /**
     * Set default options
     */
    private function set_default_options() {
        $default_options = array(
            // Core settings
            'enable_domain_mapping' => 'yes',
            'require_dns_verification' => 'yes',
            'require_admin_approval' => 'yes',
            'max_domains_per_vendor' => 1,
            'ssl_provider' => 'cloudflare',
            'proxy_server_enabled' => 'no',
            'proxy_server_url' => '',
            'cloudflare_integration' => 'yes',
            'lets_encrypt_enabled' => 'no',
            'auto_ssl_renewal' => 'no',
            
            // Phase 3: Enhanced Functionality
            'cloudflare_api_token' => '',
            'cloudflare_zone_id' => '',
            'cloudflare_email' => '',
            'email_notifications' => 'yes',
            'admin_email' => get_option('admin_email'),
            'email_template' => 'default',
            'auto_backup' => 'no',
            'max_backups' => 7,
            'analytics_enabled' => 'yes',
            'domain_transfer_enabled' => 'yes',
            'backup_retention_days' => 30
        );

        foreach ($default_options as $key => $value) {
            if (get_option('dokan_domain_mapper_' . $key) === false) {
                update_option('dokan_domain_mapper_' . $key, $value);
            }
        }
    }
}

/**
 * Initialize the plugin
 */
function dokan_vendor_domain_mapper() {
    return Dokan_Vendor_Domain_Mapper::instance();
}

// Start the plugin
dokan_vendor_domain_mapper(); 