<?php
/**
 * Domain Analytics Class
 * 
 * Handles domain analytics and reporting functionality
 */

if (!defined('ABSPATH')) {
    exit;
}

class Dokan_Domain_Analytics {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('init', array($this, 'setup_analytics_functionality'));
    }

    /**
     * Setup analytics functionality
     */
    public function setup_analytics_functionality() {
        // Add AJAX handlers
        add_action('wp_ajax_dokan_get_domain_analytics', array($this, 'process_ajax_get_analytics'));
        add_action('wp_ajax_dokan_export_analytics', array($this, 'process_ajax_export_analytics'));
        
        // Add cron job for analytics collection
        add_action('dokan_domain_analytics_cron', array($this, 'collect_analytics'));
        
        // Schedule analytics collection
        if (!wp_next_scheduled('dokan_domain_analytics_cron')) {
            wp_schedule_event(time(), 'hourly', 'dokan_domain_analytics_cron');
        }
    }

    /**
     * Get domain analytics
     */
    public function get_domain_analytics($args = array()) {
        global $wpdb;

        $defaults = array(
            'vendor_id' => 0,
            'domain_id' => 0,
            'date_from' => date('Y-m-d', strtotime('-30 days')),
            'date_to' => date('Y-m-d'),
            'group_by' => 'day'
        );

        $args = wp_parse_args($args, $defaults);

        $where_conditions = array('1=1');
        $where_values = array();

        if ($args['vendor_id']) {
            $where_conditions[] = 'vendor_id = %d';
            $where_values[] = $args['vendor_id'];
        }

        if ($args['domain_id']) {
            $where_conditions[] = 'domain_id = %d';
            $where_values[] = $args['domain_id'];
        }

        $where_conditions[] = 'date >= %s';
        $where_values[] = $args['date_from'];

        $where_conditions[] = 'date <= %s';
        $where_values[] = $args['date_to'];

        $where_clause = implode(' AND ', $where_conditions);

        $group_by_clause = $args['group_by'] === 'hour' ? 'DATE_FORMAT(date, "%Y-%m-%d %H:00:00")' : 'DATE(date)';

        $query = $wpdb->prepare(
            "SELECT 
                {$group_by_clause} as period,
                COUNT(*) as total_requests,
                COUNT(CASE WHEN status_code = 200 THEN 1 END) as successful_requests,
                COUNT(CASE WHEN status_code >= 400 THEN 1 END) as error_requests,
                AVG(response_time) as avg_response_time,
                MAX(response_time) as max_response_time,
                MIN(response_time) as min_response_time
            FROM {$wpdb->prefix}dokan_domain_analytics 
            WHERE {$where_clause}
            GROUP BY {$group_by_clause}
            ORDER BY period ASC",
            $where_values
        );

        return $wpdb->get_results($query);
    }

    /**
     * Get domain performance metrics
     */
    public function get_domain_performance($domain_id, $period = '30') {
        global $wpdb;

        $date_from = date('Y-m-d', strtotime("-{$period} days"));

        $metrics = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                COUNT(*) as total_requests,
                COUNT(CASE WHEN status_code = 200 THEN 1 END) as successful_requests,
                COUNT(CASE WHEN status_code >= 400 THEN 1 END) as error_requests,
                AVG(response_time) as avg_response_time,
                MAX(response_time) as max_response_time,
                MIN(response_time) as min_response_time,
                COUNT(DISTINCT DATE(date)) as active_days
            FROM {$wpdb->prefix}dokan_domain_analytics 
            WHERE domain_id = %d AND date >= %s",
            $domain_id,
            $date_from
        ));

        if ($metrics) {
            $metrics->uptime_percentage = $metrics->total_requests > 0 ? 
                round(($metrics->successful_requests / $metrics->total_requests) * 100, 2) : 0;
            $metrics->avg_requests_per_day = $metrics->active_days > 0 ? 
                round($metrics->total_requests / $metrics->active_days, 2) : 0;
        }

        return $metrics;
    }

    /**
     * Get vendor analytics summary
     */
    public function get_vendor_analytics_summary($vendor_id) {
        global $wpdb;

        $summary = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                COUNT(DISTINCT dm.id) as total_domains,
                COUNT(DISTINCT CASE WHEN dm.status = 'live' THEN dm.id END) as live_domains,
                COUNT(DISTINCT CASE WHEN dm.ssl_status != 'none' THEN dm.id END) as ssl_enabled_domains,
                SUM(da.total_requests) as total_requests,
                AVG(da.avg_response_time) as avg_response_time
            FROM {$wpdb->prefix}dokan_domain_mappings dm
            LEFT JOIN (
                SELECT 
                    domain_id,
                    COUNT(*) as total_requests,
                    AVG(response_time) as avg_response_time
                FROM {$wpdb->prefix}dokan_domain_analytics 
                WHERE date >= %s
                GROUP BY domain_id
            ) da ON dm.id = da.domain_id
            WHERE dm.vendor_id = %d",
            date('Y-m-d', strtotime('-30 days')),
            $vendor_id
        ));

        return $summary;
    }

    /**
     * Get system-wide analytics
     */
    public function get_system_analytics() {
        global $wpdb;

        $analytics = array();

        // Domain statistics
        $analytics['domains'] = $wpdb->get_row(
            "SELECT 
                COUNT(*) as total_domains,
                COUNT(CASE WHEN status = 'live' THEN 1 END) as live_domains,
                COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_domains,
                COUNT(CASE WHEN status = 'verified' THEN 1 END) as verified_domains,
                COUNT(CASE WHEN ssl_status != 'none' THEN 1 END) as ssl_enabled_domains
            FROM {$wpdb->prefix}dokan_domain_mappings"
        );

        // Vendor statistics
        $analytics['vendors'] = $wpdb->get_row(
            "SELECT 
                COUNT(DISTINCT vendor_id) as total_vendors,
                COUNT(DISTINCT CASE WHEN status = 'live' THEN vendor_id END) as vendors_with_live_domains
            FROM {$wpdb->prefix}dokan_domain_mappings"
        );

        // Performance statistics
        $analytics['performance'] = $wpdb->get_row(
            "SELECT 
                COUNT(*) as total_requests,
                AVG(response_time) as avg_response_time,
                COUNT(CASE WHEN status_code = 200 THEN 1 END) as successful_requests,
                COUNT(CASE WHEN status_code >= 400 THEN 1 END) as error_requests
            FROM {$wpdb->prefix}dokan_domain_analytics 
            WHERE date >= %s",
            date('Y-m-d', strtotime('-7 days'))
        );

        // Top performing domains
        $analytics['top_domains'] = $wpdb->get_results(
            "SELECT 
                dm.domain,
                dm.vendor_id,
                COUNT(da.id) as total_requests,
                AVG(da.response_time) as avg_response_time,
                COUNT(CASE WHEN da.status_code = 200 THEN 1 END) as successful_requests
            FROM {$wpdb->prefix}dokan_domain_mappings dm
            LEFT JOIN {$wpdb->prefix}dokan_domain_analytics da ON dm.id = da.domain_id
            WHERE da.date >= %s
            GROUP BY dm.id
            ORDER BY total_requests DESC
            LIMIT 10",
            date('Y-m-d', strtotime('-7 days'))
        );

        return $analytics;
    }

    /**
     * Collect analytics data
     */
    public function collect_analytics() {
        global $wpdb;

        // Get all live domains
        $domains = $wpdb->get_results(
            "SELECT id, domain FROM {$wpdb->prefix}dokan_domain_mappings WHERE status = 'live'"
        );

        foreach ($domains as $domain) {
            $this->collect_domain_analytics($domain);
        }
    }

    /**
     * Collect analytics for specific domain
     */
    public function collect_domain_analytics($domain) {
        global $wpdb;

        $start_time = microtime(true);
        
        // Test domain accessibility
        $response = wp_remote_get("https://{$domain->domain}", array(
            'timeout' => 30,
            'user_agent' => 'Dokan-Domain-Mapper/1.0'
        ));

        $end_time = microtime(true);
        $response_time = round(($end_time - $start_time) * 1000, 2); // Convert to milliseconds

        $status_code = 0;
        $is_accessible = false;

        if (!is_wp_error($response)) {
            $status_code = wp_remote_retrieve_response_code($response);
            $is_accessible = $status_code >= 200 && $status_code < 400;
        }

        // Insert analytics data
        $wpdb->insert(
            $wpdb->prefix . 'dokan_domain_analytics',
            array(
                'domain_id' => $domain->id,
                'date' => current_time('mysql'),
                'status_code' => $status_code,
                'response_time' => $response_time,
                'is_accessible' => $is_accessible ? 1 : 0,
                'created_at' => current_time('mysql')
            ),
            array('%d', '%s', '%d', '%f', '%d', '%s')
        );
    }

    /**
     * Generate analytics report
     */
    public function generate_report($args = array()) {
        $defaults = array(
            'type' => 'vendor', // vendor, domain, system
            'vendor_id' => 0,
            'domain_id' => 0,
            'date_from' => date('Y-m-d', strtotime('-30 days')),
            'date_to' => date('Y-m-d'),
            'format' => 'json'
        );

        $args = wp_parse_args($args, $defaults);

        switch ($args['type']) {
            case 'vendor':
                $data = $this->get_vendor_analytics_summary($args['vendor_id']);
                break;
            case 'domain':
                $data = $this->get_domain_performance($args['domain_id']);
                break;
            case 'system':
                $data = $this->get_system_analytics();
                break;
            default:
                return new WP_Error('invalid_type', __('Invalid report type.', 'dokan-vendor-domain-mapper'));
        }

        if ($args['format'] === 'csv') {
            return $this->export_to_csv($data);
        }

        return $data;
    }

    /**
     * Export data to CSV
     */
    private function export_to_csv($data) {
        if (empty($data)) {
            return '';
        }

        $output = fopen('php://temp', 'r+');

        // Add headers
        if (is_object($data)) {
            fputcsv($output, array_keys((array) $data));
            fputcsv($output, array_values((array) $data));
        } elseif (is_array($data)) {
            if (!empty($data) && is_object($data[0])) {
                fputcsv($output, array_keys((array) $data[0]));
                foreach ($data as $row) {
                    fputcsv($output, array_values((array) $row));
                }
            }
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }

    /**
     * Process AJAX request for getting analytics
     */
    public function process_ajax_get_analytics() {
        check_ajax_referer('dokan_domain_mapper_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions.', 'dokan-vendor-domain-mapper'));
        }

        $args = array(
            'vendor_id' => intval($_POST['vendor_id'] ?? 0),
            'domain_id' => intval($_POST['domain_id'] ?? 0),
            'date_from' => sanitize_text_field($_POST['date_from'] ?? date('Y-m-d', strtotime('-30 days'))),
            'date_to' => sanitize_text_field($_POST['date_to'] ?? date('Y-m-d')),
            'group_by' => sanitize_text_field($_POST['group_by'] ?? 'day')
        );

        $analytics = $this->get_domain_analytics($args);

        if (is_wp_error($analytics)) {
            wp_send_json_error($analytics->get_error_message());
        }

        wp_send_json_success($analytics);
    }

    /**
     * Process AJAX request for exporting analytics
     */
    public function process_ajax_export_analytics() {
        check_ajax_referer('dokan_domain_mapper_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions.', 'dokan-vendor-domain-mapper'));
        }

        $args = array(
            'type' => sanitize_text_field($_POST['type'] ?? 'system'),
            'vendor_id' => intval($_POST['vendor_id'] ?? 0),
            'domain_id' => intval($_POST['domain_id'] ?? 0),
            'date_from' => sanitize_text_field($_POST['date_from'] ?? date('Y-m-d', strtotime('-30 days'))),
            'date_to' => sanitize_text_field($_POST['date_to'] ?? date('Y-m-d')),
            'format' => 'csv'
        );

        $report = $this->generate_report($args);

        if (is_wp_error($report)) {
            wp_send_json_error($report->get_error_message());
        }

        $filename = 'domain-analytics-' . date('Y-m-d') . '.csv';
        
        wp_send_json_success(array(
            'data' => $report,
            'filename' => $filename
        ));
    }
} 