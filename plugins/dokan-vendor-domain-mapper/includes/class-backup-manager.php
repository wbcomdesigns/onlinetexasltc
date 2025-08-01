<?php
/**
 * Backup Manager Class
 * 
 * Handles domain configuration backup and restoration
 */

if (!defined('ABSPATH')) {
    exit;
}

class Dokan_Backup_Manager {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('init', array($this, 'setup_backup_functionality'));
    }

    /**
     * Setup backup functionality
     */
    public function setup_backup_functionality() {
        // Add AJAX handlers
        add_action('wp_ajax_dokan_create_backup', array($this, 'process_ajax_create_backup'));
        add_action('wp_ajax_dokan_restore_backup', array($this, 'process_ajax_restore_backup'));
        add_action('wp_ajax_dokan_delete_backup', array($this, 'process_ajax_delete_backup'));
        add_action('wp_ajax_dokan_download_backup', array($this, 'process_ajax_download_backup'));
        
        // Add cron job for automatic backups
        add_action('dokan_domain_backup_cron', array($this, 'auto_backup'));
        
        // Schedule automatic backups
        if (!wp_next_scheduled('dokan_domain_backup_cron')) {
            wp_schedule_event(time(), 'daily', 'dokan_domain_backup_cron');
        }
    }

    /**
     * Create backup
     */
    public function create_backup($domain_id = null, $backup_type = 'full') {
        global $wpdb;

        $backup_data = array(
            'timestamp' => current_time('mysql'),
            'type' => $backup_type,
            'version' => DOKAN_DOMAIN_MAPPER_VERSION,
            'domains' => array(),
            'settings' => array(),
            'analytics' => array()
        );

        // Get domain mappings
        if ($domain_id) {
            $domains = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}dokan_domain_mappings WHERE id = %d",
                $domain_id
            ));
        } else {
            $domains = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}dokan_domain_mappings");
        }

        foreach ($domains as $domain) {
            $backup_data['domains'][] = array(
                'id' => $domain->id,
                'vendor_id' => $domain->vendor_id,
                'domain' => $domain->domain,
                'status' => $domain->status,
                'ssl_status' => $domain->ssl_status,
                'ssl_certificate_path' => $domain->ssl_certificate_path,
                'ssl_private_key_path' => $domain->ssl_private_key_path,
                'ssl_expiry_date' => $domain->ssl_expiry_date,
                'verification_token' => $domain->verification_token,
                'created_at' => $domain->created_at,
                'updated_at' => $domain->updated_at
            );
        }

        // Get plugin settings
        $settings = array();
        $option_prefix = 'dokan_domain_mapper_';
        $all_options = wp_load_alloptions();
        
        foreach ($all_options as $key => $value) {
            if (strpos($key, $option_prefix) === 0) {
                $settings[$key] = $value;
            }
        }
        
        $backup_data['settings'] = $settings;

        // Get analytics data if full backup
        if ($backup_type === 'full') {
            $analytics = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}dokan_domain_analytics");
            $backup_data['analytics'] = $analytics;
        }

        // Create backup file
        $backup_filename = 'dokan-domain-backup-' . date('Y-m-d-H-i-s') . '-' . $backup_type . '.json';
        $backup_path = $this->get_backup_directory() . '/' . $backup_filename;
        
        $backup_content = json_encode($backup_data, JSON_PRETTY_PRINT);
        
        if (file_put_contents($backup_path, $backup_content) === false) {
            return new WP_Error('backup_failed', __('Failed to create backup file.', 'dokan-vendor-domain-mapper'));
        }

        // Log backup
        $this->log_backup($backup_filename, $backup_type, count($backup_data['domains']));

        return array(
            'success' => true,
            'filename' => $backup_filename,
            'path' => $backup_path,
            'size' => filesize($backup_path),
            'domains_count' => count($backup_data['domains'])
        );
    }

    /**
     * Restore backup
     */
    public function restore_backup($backup_filename) {
        global $wpdb;

        $backup_path = $this->get_backup_directory() . '/' . $backup_filename;
        
        if (!file_exists($backup_path)) {
            return new WP_Error('backup_not_found', __('Backup file not found.', 'dokan-vendor-domain-mapper'));
        }

        $backup_content = file_get_contents($backup_path);
        if (!$backup_content) {
            return new WP_Error('backup_read_failed', __('Failed to read backup file.', 'dokan-vendor-domain-mapper'));
        }

        $backup_data = json_decode($backup_content, true);
        if (!$backup_data) {
            return new WP_Error('backup_invalid', __('Invalid backup file format.', 'dokan-vendor-domain-mapper'));
        }

        // Validate backup version
        if (!isset($backup_data['version'])) {
            return new WP_Error('backup_version_missing', __('Backup version information missing.', 'dokan-vendor-domain-mapper'));
        }

        // Start transaction
        $wpdb->query('START TRANSACTION');

        try {
            // Restore domains
            if (isset($backup_data['domains']) && is_array($backup_data['domains'])) {
                foreach ($backup_data['domains'] as $domain) {
                    $this->restore_domain($domain);
                }
            }

            // Restore settings
            if (isset($backup_data['settings']) && is_array($backup_data['settings'])) {
                foreach ($backup_data['settings'] as $key => $value) {
                    update_option($key, $value);
                }
            }

            // Restore analytics if full backup
            if (isset($backup_data['analytics']) && is_array($backup_data['analytics'])) {
                $this->restore_analytics($backup_data['analytics']);
            }

            // Commit transaction
            $wpdb->query('COMMIT');

            // Log restoration
            $this->log_restoration($backup_filename);

            return array(
                'success' => true,
                'message' => sprintf(__('Backup restored successfully. %d domains restored.', 'dokan-vendor-domain-mapper'), count($backup_data['domains'])),
                'domains_count' => count($backup_data['domains'])
            );

        } catch (Exception $e) {
            // Rollback transaction
            $wpdb->query('ROLLBACK');
            return new WP_Error('restore_failed', $e->getMessage());
        }
    }

    /**
     * Restore domain
     */
    private function restore_domain($domain_data) {
        global $wpdb;

        // Check if domain exists
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}dokan_domain_mappings WHERE domain = %s",
            $domain_data['domain']
        ));

        if ($existing) {
            // Update existing domain
            $wpdb->update(
                $wpdb->prefix . 'dokan_domain_mappings',
                array(
                    'vendor_id' => $domain_data['vendor_id'],
                    'status' => $domain_data['status'],
                    'ssl_status' => $domain_data['ssl_status'],
                    'ssl_certificate_path' => $domain_data['ssl_certificate_path'],
                    'ssl_private_key_path' => $domain_data['ssl_private_key_path'],
                    'ssl_expiry_date' => $domain_data['ssl_expiry_date'],
                    'verification_token' => $domain_data['verification_token'],
                    'updated_at' => current_time('mysql')
                ),
                array('id' => $existing->id),
                array('%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s'),
                array('%d')
            );
        } else {
            // Insert new domain
            $wpdb->insert(
                $wpdb->prefix . 'dokan_domain_mappings',
                array(
                    'vendor_id' => $domain_data['vendor_id'],
                    'domain' => $domain_data['domain'],
                    'status' => $domain_data['status'],
                    'ssl_status' => $domain_data['ssl_status'],
                    'ssl_certificate_path' => $domain_data['ssl_certificate_path'],
                    'ssl_private_key_path' => $domain_data['ssl_private_key_path'],
                    'ssl_expiry_date' => $domain_data['ssl_expiry_date'],
                    'verification_token' => $domain_data['verification_token'],
                    'created_at' => $domain_data['created_at'],
                    'updated_at' => current_time('mysql')
                ),
                array('%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
            );
        }
    }

    /**
     * Restore analytics
     */
    private function restore_analytics($analytics_data) {
        global $wpdb;

        // Clear existing analytics
        $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}dokan_domain_analytics");

        // Insert analytics data
        foreach ($analytics_data as $analytics) {
            $wpdb->insert(
                $wpdb->prefix . 'dokan_domain_analytics',
                array(
                    'domain_id' => $analytics['domain_id'],
                    'date' => $analytics['date'],
                    'status_code' => $analytics['status_code'],
                    'response_time' => $analytics['response_time'],
                    'is_accessible' => $analytics['is_accessible'],
                    'created_at' => $analytics['created_at']
                ),
                array('%d', '%s', '%d', '%f', '%d', '%s')
            );
        }
    }

    /**
     * Get backup directory
     */
    private function get_backup_directory() {
        $upload_dir = wp_upload_dir();
        $backup_dir = $upload_dir['basedir'] . '/dokan-domain-backups';
        
        if (!file_exists($backup_dir)) {
            wp_mkdir_p($backup_dir);
        }
        
        return $backup_dir;
    }

    /**
     * Get backup URL
     */
    private function get_backup_url($filename) {
        $upload_dir = wp_upload_dir();
        return $upload_dir['baseurl'] . '/dokan-domain-backups/' . $filename;
    }

    /**
     * List backups
     */
    public function list_backups() {
        $backup_dir = $this->get_backup_directory();
        $backups = array();
        
        if (is_dir($backup_dir)) {
            $files = glob($backup_dir . '/dokan-domain-backup-*.json');
            
            foreach ($files as $file) {
                $filename = basename($file);
                $backup_info = $this->get_backup_info($filename);
                
                if ($backup_info) {
                    $backups[] = $backup_info;
                }
            }
        }
        
        // Sort by creation date (newest first)
        usort($backups, function($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });
        
        return $backups;
    }

    /**
     * Get backup info
     */
    private function get_backup_info($filename) {
        $backup_path = $this->get_backup_directory() . '/' . $filename;
        
        if (!file_exists($backup_path)) {
            return false;
        }
        
        $content = file_get_contents($backup_path);
        $data = json_decode($content, true);
        
        if (!$data) {
            return false;
        }
        
        return array(
            'filename' => $filename,
            'created_at' => $data['timestamp'],
            'type' => $data['type'],
            'version' => $data['version'],
            'size' => filesize($backup_path),
            'domains_count' => count($data['domains']),
            'url' => $this->get_backup_url($filename)
        );
    }

    /**
     * Delete backup
     */
    public function delete_backup($filename) {
        $backup_path = $this->get_backup_directory() . '/' . $filename;
        
        if (!file_exists($backup_path)) {
            return new WP_Error('backup_not_found', __('Backup file not found.', 'dokan-vendor-domain-mapper'));
        }
        
        if (unlink($backup_path)) {
            return array(
                'success' => true,
                'message' => __('Backup deleted successfully.', 'dokan-vendor-domain-mapper')
            );
        }
        
        return new WP_Error('delete_failed', __('Failed to delete backup file.', 'dokan-vendor-domain-mapper'));
    }

    /**
     * Auto backup
     */
    public function auto_backup() {
        // Check if auto backup is enabled
        if (get_option('dokan_domain_mapper_auto_backup', 'no') !== 'yes') {
            return;
        }
        
        // Create daily backup
        $this->create_backup(null, 'auto');
        
        // Clean old backups (keep last 7 days)
        $this->cleanup_old_backups();
    }

    /**
     * Cleanup old backups
     */
    private function cleanup_old_backups() {
        $backups = $this->list_backups();
        $max_backups = get_option('dokan_domain_mapper_max_backups', 7);
        
        if (count($backups) > $max_backups) {
            $backups_to_delete = array_slice($backups, $max_backups);
            
            foreach ($backups_to_delete as $backup) {
                $this->delete_backup($backup['filename']);
            }
        }
    }

    /**
     * Log backup
     */
    private function log_backup($filename, $type, $domains_count) {
        global $wpdb;
        
        $wpdb->insert(
            $wpdb->prefix . 'dokan_backup_logs',
            array(
                'filename' => $filename,
                'type' => $type,
                'domains_count' => $domains_count,
                'created_by' => get_current_user_id(),
                'created_at' => current_time('mysql')
            ),
            array('%s', '%s', '%d', '%d', '%s')
        );
    }

    /**
     * Log restoration
     */
    private function log_restoration($filename) {
        global $wpdb;
        
        $wpdb->insert(
            $wpdb->prefix . 'dokan_restoration_logs',
            array(
                'filename' => $filename,
                'restored_by' => get_current_user_id(),
                'created_at' => current_time('mysql')
            ),
            array('%s', '%d', '%s')
        );
    }

    /**
     * Process AJAX request for creating backup
     */
    public function process_ajax_create_backup() {
        check_ajax_referer('dokan_domain_mapper_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions.', 'dokan-vendor-domain-mapper'));
        }

        $domain_id = intval($_POST['domain_id'] ?? 0);
        $backup_type = sanitize_text_field($_POST['backup_type'] ?? 'full');

        $result = $this->create_backup($domain_id, $backup_type);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success($result);
    }

    /**
     * Process AJAX request for restoring backup
     */
    public function process_ajax_restore_backup() {
        check_ajax_referer('dokan_domain_mapper_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions.', 'dokan-vendor-domain-mapper'));
        }

        $filename = sanitize_text_field($_POST['filename']);

        $result = $this->restore_backup($filename);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success($result);
    }

    /**
     * Process AJAX request for deleting backup
     */
    public function process_ajax_delete_backup() {
        check_ajax_referer('dokan_domain_mapper_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions.', 'dokan-vendor-domain-mapper'));
        }

        $filename = sanitize_text_field($_POST['filename']);

        $result = $this->delete_backup($filename);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success($result);
    }

    /**
     * Process AJAX request for downloading backup
     */
    public function process_ajax_download_backup() {
        check_ajax_referer('dokan_domain_mapper_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions.', 'dokan-vendor-domain-mapper'));
        }

        $filename = sanitize_text_field($_POST['filename']);
        $backup_path = $this->get_backup_directory() . '/' . $filename;
        
        if (!file_exists($backup_path)) {
            wp_die(__('Backup file not found.', 'dokan-vendor-domain-mapper'));
        }

        // Force download
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($backup_path));
        header('Cache-Control: no-cache, must-revalidate');
        header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
        
        readfile($backup_path);
        exit;
    }
} 