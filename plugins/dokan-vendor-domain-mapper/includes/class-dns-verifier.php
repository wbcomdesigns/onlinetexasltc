<?php
/**
 * DNS Verifier Class
 * 
 * Handles DNS verification for domain mapping
 */

if (!defined('ABSPATH')) {
    exit;
}

class Dokan_DNS_Verifier {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('init', array($this, 'setup_dns_verification_functionality'));
    }

    /**
     * Setup DNS verification functionality
     */
    public function setup_dns_verification_functionality() {
        // Add AJAX handler for DNS verification
        add_action('wp_ajax_dokan_check_dns', array($this, 'process_ajax_check_dns'));
    }

    /**
     * Verify domain DNS
     */
    public function verify_domain($domain, $token) {
        // Clean domain
        $domain = $this->clean_domain($domain);
        
        // Get DNS TXT records
        $txt_records = $this->get_dns_txt_records($domain);
        
        if (empty($txt_records)) {
            return array(
                'verified' => false,
                'message' => __('No TXT records found for this domain.', 'dokan-vendor-domain-mapper'),
                'records' => array()
            );
        }

        // Check if verification token exists in TXT records
        $token_found = false;
        $found_records = array();

        foreach ($txt_records as $record) {
            if (isset($record['txt']) && strpos($record['txt'], $token) !== false) {
                $token_found = true;
                $found_records[] = $record['txt'];
            }
        }

        if ($token_found) {
            return array(
                'verified' => true,
                'message' => __('Domain verification successful!', 'dokan-vendor-domain-mapper'),
                'records' => $found_records
            );
        } else {
            return array(
                'verified' => false,
                'message' => __('Verification token not found in DNS TXT records.', 'dokan-vendor-domain-mapper'),
                'records' => $txt_records
            );
        }
    }

    /**
     * Get DNS TXT records for domain
     */
    public function get_dns_txt_records($domain) {
        // Use WordPress DNS functions if available
        if (function_exists('dns_get_record')) {
            $records = @dns_get_record($domain, DNS_TXT);
            
            if ($records === false) {
                return array();
            }

            return $records;
        }

        // Fallback to external DNS lookup
        return $this->external_dns_lookup($domain, 'TXT');
    }

    /**
     * External DNS lookup using public DNS servers
     */
    private function external_dns_lookup($domain, $type = 'TXT') {
        $dns_servers = array(
            '8.8.8.8',    // Google DNS
            '8.8.4.4',    // Google DNS
            '1.1.1.1',    // Cloudflare DNS
            '1.0.0.1'     // Cloudflare DNS
        );

        foreach ($dns_servers as $dns_server) {
            $records = $this->query_dns_server($dns_server, $domain, $type);
            if (!empty($records)) {
                return $records;
            }
        }

        return array();
    }

    /**
     * Query specific DNS server
     */
    private function query_dns_server($server, $domain, $type) {
        // Use nslookup if available
        if (function_exists('shell_exec')) {
            $command = "nslookup -type={$type} {$domain} {$server} 2>&1";
            $output = shell_exec($command);
            
            if ($output && strpos($output, 'text =') !== false) {
                return $this->parse_nslookup_output($output);
            }
        }

        // Use dig if available
        if (function_exists('shell_exec')) {
            $command = "dig @{$server} {$domain} {$type} +short 2>&1";
            $output = shell_exec($command);
            
            if ($output && !empty(trim($output))) {
                return $this->parse_dig_output($output);
            }
        }

        return array();
    }

    /**
     * Parse nslookup output
     */
    private function parse_nslookup_output($output) {
        $records = array();
        $lines = explode("\n", $output);
        
        foreach ($lines as $line) {
            if (strpos($line, 'text =') !== false) {
                $txt = trim(str_replace('text =', '', $line));
                $txt = trim($txt, '"');
                $records[] = array('txt' => $txt);
            }
        }
        
        return $records;
    }

    /**
     * Parse dig output
     */
    private function parse_dig_output($output) {
        $records = array();
        $lines = explode("\n", $output);
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (!empty($line) && $line !== ';; Got answer:' && $line !== ';; ->>HEADER<<-') {
                $txt = trim($line, '"');
                $records[] = array('txt' => $txt);
            }
        }
        
        return $records;
    }

    /**
     * Clean domain name
     */
    private function clean_domain($domain) {
        // Remove protocol
        $domain = preg_replace('#^https?://#', '', $domain);
        
        // Remove trailing slash
        $domain = rtrim($domain, '/');
        
        // Remove www prefix if present
        $domain = preg_replace('/^www\./', '', $domain);
        
        return $domain;
    }

    /**
     * Check if domain is accessible
     */
    public function check_domain_accessibility($domain) {
        $domain = $this->clean_domain($domain);
        
        // Check HTTP accessibility
        $http_url = 'http://' . $domain;
        $http_response = wp_remote_get($http_url, array(
            'timeout' => 10,
            'sslverify' => false
        ));

        $http_accessible = !is_wp_error($http_response) && wp_remote_retrieve_response_code($http_response) < 400;

        // Check HTTPS accessibility
        $https_url = 'https://' . $domain;
        $https_response = wp_remote_get($https_url, array(
            'timeout' => 10,
            'sslverify' => false
        ));

        $https_accessible = !is_wp_error($https_response) && wp_remote_retrieve_response_code($https_response) < 400;

        return array(
            'domain' => $domain,
            'http_accessible' => $http_accessible,
            'https_accessible' => $https_accessible,
            'http_response_code' => is_wp_error($http_response) ? 0 : wp_remote_retrieve_response_code($http_response),
            'https_response_code' => is_wp_error($https_response) ? 0 : wp_remote_retrieve_response_code($https_response)
        );
    }

    /**
     * Get domain DNS information
     */
    public function get_domain_dns_info($domain) {
        $domain = $this->clean_domain($domain);
        
        $dns_info = array(
            'domain' => $domain,
            'a_records' => array(),
            'cname_records' => array(),
            'txt_records' => array(),
            'mx_records' => array(),
            'ns_records' => array()
        );

        // Get A records
        if (function_exists('dns_get_record')) {
            $a_records = @dns_get_record($domain, DNS_A);
            if ($a_records !== false) {
                $dns_info['a_records'] = $a_records;
            }
        }

        // Get CNAME records
        if (function_exists('dns_get_record')) {
            $cname_records = @dns_get_record($domain, DNS_CNAME);
            if ($cname_records !== false) {
                $dns_info['cname_records'] = $cname_records;
            }
        }

        // Get TXT records
        $dns_info['txt_records'] = $this->get_dns_txt_records($domain);

        // Get MX records
        if (function_exists('dns_get_record')) {
            $mx_records = @dns_get_record($domain, DNS_MX);
            if ($mx_records !== false) {
                $dns_info['mx_records'] = $mx_records;
            }
        }

        // Get NS records
        if (function_exists('dns_get_record')) {
            $ns_records = @dns_get_record($domain, DNS_NS);
            if ($ns_records !== false) {
                $dns_info['ns_records'] = $ns_records;
            }
        }

        return $dns_info;
    }

    /**
     * Validate DNS configuration for domain mapping
     */
    public function validate_dns_configuration($domain) {
        $domain = $this->clean_domain($domain);
        $dns_info = $this->get_domain_dns_info($domain);
        
        $validation = array(
            'domain' => $domain,
            'valid' => true,
            'errors' => array(),
            'warnings' => array(),
            'recommendations' => array()
        );

        // Check if domain has A records
        if (empty($dns_info['a_records']) && empty($dns_info['cname_records'])) {
            $validation['valid'] = false;
            $validation['errors'][] = __('No A or CNAME records found for this domain.', 'dokan-vendor-domain-mapper');
        }

        // Check if domain has nameservers
        if (empty($dns_info['ns_records'])) {
            $validation['warnings'][] = __('No nameserver records found for this domain.', 'dokan-vendor-domain-mapper');
        }

        // Check domain accessibility
        $accessibility = $this->check_domain_accessibility($domain);
        if (!$accessibility['http_accessible'] && !$accessibility['https_accessible']) {
            $validation['warnings'][] = __('Domain appears to be inaccessible. Please check your DNS configuration.', 'dokan-vendor-domain-mapper');
        }

        // Add recommendations
        if (empty($dns_info['a_records'])) {
            $validation['recommendations'][] = __('Add an A record pointing to your server IP address.', 'dokan-vendor-domain-mapper');
        }

        if (!$accessibility['https_accessible']) {
            $validation['recommendations'][] = __('Consider setting up SSL certificate for secure access.', 'dokan-vendor-domain-mapper');
        }

        return $validation;
    }

    /**
     * Process AJAX request for checking DNS
     */
    public function process_ajax_check_dns() {
        check_ajax_referer('dokan_domain_mapper_nonce', 'nonce');

        if (!current_user_can('dokan_is_seller')) {
            wp_die(__('Access denied.', 'dokan-vendor-domain-mapper'));
        }

        $domain = sanitize_text_field($_POST['domain']);
        $token = sanitize_text_field($_POST['token']);

        $result = $this->verify_domain($domain, $token);

        wp_send_json_success($result);
    }

    /**
     * Get verification instructions for different DNS providers
     */
    public function get_dns_provider_instructions($provider = '') {
        $instructions = array(
            'general' => array(
                'title' => __('General DNS Setup Instructions', 'dokan-vendor-domain-mapper'),
                'steps' => array(
                    __('Log in to your domain registrar or DNS provider.', 'dokan-vendor-domain-mapper'),
                    __('Navigate to DNS management or DNS settings.', 'dokan-vendor-domain-mapper'),
                    __('Add a new TXT record with the following details:', 'dokan-vendor-domain-mapper'),
                    __('Wait 5-10 minutes for DNS propagation.', 'dokan-vendor-domain-mapper'),
                    __('Click "Verify Domain" to confirm the setup.', 'dokan-vendor-domain-mapper')
                )
            ),
            'cloudflare' => array(
                'title' => __('Cloudflare DNS Setup', 'dokan-vendor-domain-mapper'),
                'steps' => array(
                    __('Log in to your Cloudflare dashboard.', 'dokan-vendor-domain-mapper'),
                    __('Select your domain.', 'dokan-vendor-domain-mapper'),
                    __('Go to DNS settings.', 'dokan-vendor-domain-mapper'),
                    __('Click "Add record".', 'dokan-vendor-domain-mapper'),
                    __('Set Type to "TXT".', 'dokan-vendor-domain-mapper'),
                    __('Set Name to your domain (e.g., example.com).', 'dokan-vendor-domain-mapper'),
                    __('Set Content to the verification token.', 'dokan-vendor-domain-mapper'),
                    __('Click "Save".', 'dokan-vendor-domain-mapper')
                )
            ),
            'godaddy' => array(
                'title' => __('GoDaddy DNS Setup', 'dokan-vendor-domain-mapper'),
                'steps' => array(
                    __('Log in to your GoDaddy account.', 'dokan-vendor-domain-mapper'),
                    __('Go to My Products > Domains.', 'dokan-vendor-domain-mapper'),
                    __('Click "DNS" next to your domain.', 'dokan-vendor-domain-mapper'),
                    __('Click "Add" in the Records section.', 'dokan-vendor-domain-mapper'),
                    __('Set Type to "TXT".', 'dokan-vendor-domain-mapper'),
                    __('Set Host to "@" (for root domain) or your subdomain.', 'dokan-vendor-domain-mapper'),
                    __('Set Value to the verification token.', 'dokan-vendor-domain-mapper'),
                    __('Click "Save".', 'dokan-vendor-domain-mapper')
                )
            ),
            'namecheap' => array(
                'title' => __('Namecheap DNS Setup', 'dokan-vendor-domain-mapper'),
                'steps' => array(
                    __('Log in to your Namecheap account.', 'dokan-vendor-domain-mapper'),
                    __('Go to Domain List > Manage.', 'dokan-vendor-domain-mapper'),
                    __('Click "Advanced DNS".', 'dokan-vendor-domain-mapper'),
                    __('Click "Add New Record".', 'dokan-vendor-domain-mapper'),
                    __('Set Type to "TXT Record".', 'dokan-vendor-domain-mapper'),
                    __('Set Host to "@" (for root domain) or your subdomain.', 'dokan-vendor-domain-mapper'),
                    __('Set Value to the verification token.', 'dokan-vendor-domain-mapper'),
                    __('Click "Save Changes".', 'dokan-vendor-domain-mapper')
                )
            )
        );

        if (!empty($provider) && isset($instructions[$provider])) {
            return $instructions[$provider];
        }

        return $instructions;
    }

    /**
     * Detect DNS provider based on nameservers
     */
    public function detect_dns_provider($domain) {
        $domain = $this->clean_domain($domain);
        $dns_info = $this->get_domain_dns_info($domain);
        
        if (empty($dns_info['ns_records'])) {
            return 'unknown';
        }

        $nameservers = array();
        foreach ($dns_info['ns_records'] as $ns_record) {
            if (isset($ns_record['target'])) {
                $nameservers[] = strtolower($ns_record['target']);
            }
        }

        // Check for common DNS providers
        if (array_intersect($nameservers, array('ns1.cloudflare.com', 'ns2.cloudflare.com'))) {
            return 'cloudflare';
        }

        if (array_intersect($nameservers, array('ns1.godaddy.com', 'ns2.godaddy.com'))) {
            return 'godaddy';
        }

        if (array_intersect($nameservers, array('dns1.namecheap.com', 'dns2.namecheap.com'))) {
            return 'namecheap';
        }

        if (array_intersect($nameservers, array('ns1.google.com', 'ns2.google.com'))) {
            return 'google';
        }

        return 'unknown';
    }

    /**
     * Get DNS propagation status
     */
    public function check_dns_propagation($domain, $token) {
        $dns_servers = array(
            '8.8.8.8' => 'Google DNS',
            '1.1.1.1' => 'Cloudflare DNS',
            '208.67.222.222' => 'OpenDNS',
            '9.9.9.9' => 'Quad9 DNS'
        );

        $results = array();
        $propagated = 0;

        foreach ($dns_servers as $server => $name) {
            $records = $this->query_dns_server($server, $domain, 'TXT');
            $found = false;

            foreach ($records as $record) {
                if (isset($record['txt']) && strpos($record['txt'], $token) !== false) {
                    $found = true;
                    break;
                }
            }

            $results[$name] = array(
                'server' => $server,
                'found' => $found,
                'records' => $records
            );

            if ($found) {
                $propagated++;
            }
        }

        return array(
            'domain' => $domain,
            'total_servers' => count($dns_servers),
            'propagated_servers' => $propagated,
            'propagation_percentage' => round(($propagated / count($dns_servers)) * 100, 2),
            'results' => $results
        );
    }
} 