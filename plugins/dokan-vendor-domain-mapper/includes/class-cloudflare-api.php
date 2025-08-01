<?php
/**
 * Cloudflare API Integration Class
 * 
 * Handles Cloudflare API integration for DNS management and SSL automation
 */

if (!defined('ABSPATH')) {
    exit;
}

class Dokan_Cloudflare_API {

    /**
     * Cloudflare API configuration
     */
    private $api_token;
    private $zone_id;
    private $api_url = 'https://api.cloudflare.com/client/v4';

    /**
     * Constructor
     */
    public function __construct() {
        $this->api_token = get_option('dokan_domain_mapper_cloudflare_api_token', '');
        $this->zone_id = get_option('dokan_domain_mapper_cloudflare_zone_id', '');
        
        add_action('init', array($this, 'setup_cloudflare_functionality'));
    }

    /**
     * Setup Cloudflare API functionality
     */
    public function setup_cloudflare_functionality() {
        // Add AJAX handlers
        add_action('wp_ajax_dokan_cloudflare_add_dns_record', array($this, 'process_ajax_add_dns_record'));
        add_action('wp_ajax_dokan_cloudflare_update_dns_record', array($this, 'process_ajax_update_dns_record'));
        add_action('wp_ajax_dokan_cloudflare_delete_dns_record', array($this, 'process_ajax_delete_dns_record'));
        add_action('wp_ajax_dokan_cloudflare_enable_ssl', array($this, 'process_ajax_enable_ssl'));
        
        // Add settings
        add_action('admin_init', array($this, 'register_cloudflare_settings'));
    }

    /**
     * Register Cloudflare settings
     */
    public function register_cloudflare_settings() {
        register_setting('dokan_domain_mapper_settings', 'dokan_domain_mapper_cloudflare_api_token');
        register_setting('dokan_domain_mapper_settings', 'dokan_domain_mapper_cloudflare_zone_id');
        register_setting('dokan_domain_mapper_settings', 'dokan_domain_mapper_cloudflare_email');
    }

    /**
     * Test Cloudflare API connection
     */
    public function test_connection() {
        if (empty($this->api_token)) {
            return new WP_Error('no_api_token', __('Cloudflare API token is not configured.', 'dokan-vendor-domain-mapper'));
        }

        $response = wp_remote_get($this->api_url . '/user/tokens/verify', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_token,
                'Content-Type' => 'application/json'
            )
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($data['success']) {
            return array(
                'success' => true,
                'user' => $data['result']['user']
            );
        }

        return new WP_Error('api_error', $data['errors'][0]['message'] ?? __('Unknown API error.', 'dokan-vendor-domain-mapper'));
    }

    /**
     * Get zones (domains)
     */
    public function get_zones() {
        $response = wp_remote_get($this->api_url . '/zones', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_token,
                'Content-Type' => 'application/json'
            )
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($data['success']) {
            return $data['result'];
        }

        return new WP_Error('api_error', $data['errors'][0]['message'] ?? __('Failed to fetch zones.', 'dokan-vendor-domain-mapper'));
    }

    /**
     * Add DNS record
     */
    public function add_dns_record($domain, $type, $content, $name = '@', $ttl = 1, $proxied = true) {
        $zone_id = $this->get_zone_id_for_domain($domain);
        if (!$zone_id) {
            return new WP_Error('zone_not_found', __('Zone not found for domain.', 'dokan-vendor-domain-mapper'));
        }

        $record_data = array(
            'type' => $type,
            'name' => $name,
            'content' => $content,
            'ttl' => $ttl,
            'proxied' => $proxied
        );

        $response = wp_remote_post($this->api_url . "/zones/{$zone_id}/dns_records", array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_token,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($record_data)
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($data['success']) {
            return $data['result'];
        }

        return new WP_Error('api_error', $data['errors'][0]['message'] ?? __('Failed to add DNS record.', 'dokan-vendor-domain-mapper'));
    }

    /**
     * Update DNS record
     */
    public function update_dns_record($domain, $record_id, $type, $content, $name = '@', $ttl = 1, $proxied = true) {
        $zone_id = $this->get_zone_id_for_domain($domain);
        if (!$zone_id) {
            return new WP_Error('zone_not_found', __('Zone not found for domain.', 'dokan-vendor-domain-mapper'));
        }

        $record_data = array(
            'type' => $type,
            'name' => $name,
            'content' => $content,
            'ttl' => $ttl,
            'proxied' => $proxied
        );

        $response = wp_remote_put($this->api_url . "/zones/{$zone_id}/dns_records/{$record_id}", array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_token,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($record_data)
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($data['success']) {
            return $data['result'];
        }

        return new WP_Error('api_error', $data['errors'][0]['message'] ?? __('Failed to update DNS record.', 'dokan-vendor-domain-mapper'));
    }

    /**
     * Delete DNS record
     */
    public function delete_dns_record($domain, $record_id) {
        $zone_id = $this->get_zone_id_for_domain($domain);
        if (!$zone_id) {
            return new WP_Error('zone_not_found', __('Zone not found for domain.', 'dokan-vendor-domain-mapper'));
        }

        $response = wp_remote_request($this->api_url . "/zones/{$zone_id}/dns_records/{$record_id}", array(
            'method' => 'DELETE',
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_token,
                'Content-Type' => 'application/json'
            )
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($data['success']) {
            return true;
        }

        return new WP_Error('api_error', $data['errors'][0]['message'] ?? __('Failed to delete DNS record.', 'dokan-vendor-domain-mapper'));
    }

    /**
     * Get DNS records for domain
     */
    public function get_dns_records($domain) {
        $zone_id = $this->get_zone_id_for_domain($domain);
        if (!$zone_id) {
            return new WP_Error('zone_not_found', __('Zone not found for domain.', 'dokan-vendor-domain-mapper'));
        }

        $response = wp_remote_get($this->api_url . "/zones/{$zone_id}/dns_records", array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_token,
                'Content-Type' => 'application/json'
            )
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($data['success']) {
            return $data['result'];
        }

        return new WP_Error('api_error', $data['errors'][0]['message'] ?? __('Failed to fetch DNS records.', 'dokan-vendor-domain-mapper'));
    }

    /**
     * Enable SSL for domain
     */
    public function enable_ssl($domain, $ssl_mode = 'full') {
        $zone_id = $this->get_zone_id_for_domain($domain);
        if (!$zone_id) {
            return new WP_Error('zone_not_found', __('Zone not found for domain.', 'dokan-vendor-domain-mapper'));
        }

        $ssl_data = array(
            'value' => $ssl_mode
        );

        $response = wp_remote_patch($this->api_url . "/zones/{$zone_id}/settings/ssl", array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_token,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($ssl_data)
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($data['success']) {
            return $data['result'];
        }

        return new WP_Error('api_error', $data['errors'][0]['message'] ?? __('Failed to enable SSL.', 'dokan-vendor-domain-mapper'));
    }

    /**
     * Get SSL status for domain
     */
    public function get_ssl_status($domain) {
        $zone_id = $this->get_zone_id_for_domain($domain);
        if (!$zone_id) {
            return new WP_Error('zone_not_found', __('Zone not found for domain.', 'dokan-vendor-domain-mapper'));
        }

        $response = wp_remote_get($this->api_url . "/zones/{$zone_id}/settings/ssl", array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_token,
                'Content-Type' => 'application/json'
            )
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($data['success']) {
            return $data['result'];
        }

        return new WP_Error('api_error', $data['errors'][0]['message'] ?? __('Failed to get SSL status.', 'dokan-vendor-domain-mapper'));
    }

    /**
     * Get zone ID for domain
     */
    private function get_zone_id_for_domain($domain) {
        // Use cached zone ID if available
        if (!empty($this->zone_id)) {
            return $this->zone_id;
        }

        // Find zone ID for domain
        $zones = $this->get_zones();
        if (is_wp_error($zones)) {
            return false;
        }

        foreach ($zones as $zone) {
            if ($zone['name'] === $domain) {
                return $zone['id'];
            }
        }

        return false;
    }

    /**
     * Process AJAX request for adding DNS record
     */
    public function process_ajax_add_dns_record() {
        check_ajax_referer('dokan_domain_mapper_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions.', 'dokan-vendor-domain-mapper'));
        }

        $domain = sanitize_text_field($_POST['domain']);
        $type = sanitize_text_field($_POST['type']);
        $content = sanitize_text_field($_POST['content']);
        $name = sanitize_text_field($_POST['name'] ?? '@');

        $result = $this->add_dns_record($domain, $type, $content, $name);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success($result);
    }

    /**
     * Process AJAX request for updating DNS record
     */
    public function process_ajax_update_dns_record() {
        check_ajax_referer('dokan_domain_mapper_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions.', 'dokan-vendor-domain-mapper'));
        }

        $domain = sanitize_text_field($_POST['domain']);
        $record_id = sanitize_text_field($_POST['record_id']);
        $type = sanitize_text_field($_POST['type']);
        $content = sanitize_text_field($_POST['content']);
        $name = sanitize_text_field($_POST['name'] ?? '@');

        $result = $this->update_dns_record($domain, $record_id, $type, $content, $name);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success($result);
    }

    /**
     * Process AJAX request for deleting DNS record
     */
    public function process_ajax_delete_dns_record() {
        check_ajax_referer('dokan_domain_mapper_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions.', 'dokan-vendor-domain-mapper'));
        }

        $domain = sanitize_text_field($_POST['domain']);
        $record_id = sanitize_text_field($_POST['record_id']);

        $result = $this->delete_dns_record($domain, $record_id);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success(__('DNS record deleted successfully.', 'dokan-vendor-domain-mapper'));
    }

    /**
     * Process AJAX request for enabling SSL
     */
    public function process_ajax_enable_ssl() {
        check_ajax_referer('dokan_domain_mapper_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions.', 'dokan-vendor-domain-mapper'));
        }

        $domain = sanitize_text_field($_POST['domain']);
        $ssl_mode = sanitize_text_field($_POST['ssl_mode'] ?? 'full');

        $result = $this->enable_ssl($domain, $ssl_mode);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success(__('SSL enabled successfully.', 'dokan-vendor-domain-mapper'));
    }
} 