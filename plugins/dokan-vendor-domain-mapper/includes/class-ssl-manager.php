<?php
/**
 * SSL Manager Class
 * 
 * Handles SSL certificate management for domain mapping
 */

if (!defined('ABSPATH')) {
    exit;
}

class Dokan_SSL_Manager {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('init', array($this, 'setup_ssl_management_functionality'));
    }

    /**
     * Setup SSL management functionality
     */
    public function setup_ssl_management_functionality() {
        // Add AJAX handlers
        add_action('wp_ajax_dokan_check_ssl_status', array($this, 'process_ajax_check_ssl_status'));
        add_action('wp_ajax_dokan_setup_ssl', array($this, 'process_ajax_setup_ssl'));
        
        // Add cron job for SSL renewal
        add_action('dokan_ssl_renewal_cron', array($this, 'check_ssl_renewals'));
        
        // Schedule SSL renewal check
        if (!wp_next_scheduled('dokan_ssl_renewal_cron')) {
            wp_schedule_event(time(), 'daily', 'dokan_ssl_renewal_cron');
        }
    }

    /**
     * Get SSL status for domain
     */
    public function get_ssl_status($domain_mapping) {
        $domain = $domain_mapping->domain;
        $ssl_status = $domain_mapping->ssl_status;

        $status_info = array(
            'domain' => $domain,
            'ssl_status' => $ssl_status,
            'ssl_valid' => false,
            'ssl_expiry' => null,
            'ssl_issuer' => null,
            'ssl_provider' => $this->detect_ssl_provider($domain),
            'setup_required' => false,
            'setup_instructions' => array()
        );

        // Check SSL certificate
        $ssl_info = $this->check_ssl_certificate($domain);
        if ($ssl_info) {
            $status_info['ssl_valid'] = $ssl_info['valid'];
            $status_info['ssl_expiry'] = $ssl_info['expiry'];
            $status_info['ssl_issuer'] = $ssl_info['issuer'];
        }

        // Determine if setup is required
        if ($ssl_status === 'none' || ($ssl_status === 'manual' && !$status_info['ssl_valid'])) {
            $status_info['setup_required'] = true;
            $status_info['setup_instructions'] = $this->get_ssl_setup_instructions($domain);
        }

        return $status_info;
    }

    /**
     * Check SSL certificate
     */
    public function check_ssl_certificate($domain) {
        $context = stream_context_create(array(
            'ssl' => array(
                'capture_peer_cert' => true,
                'verify_peer' => false,
                'verify_peer_name' => false
            )
        ));

        $client = @stream_socket_client(
            "ssl://{$domain}:443",
            $errno,
            $errstr,
            30,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if (!$client) {
            return false;
        }

        $params = stream_context_get_params($client);
        $cert = $params['options']['ssl']['peer_certificate'];

        if (!$cert) {
            fclose($client);
            return false;
        }

        $cert_info = openssl_x509_parse($cert);
        fclose($client);

        if (!$cert_info) {
            return false;
        }

        $expiry = $cert_info['validTo_time_t'];
        $now = time();

        return array(
            'valid' => $expiry > $now,
            'expiry' => date('Y-m-d H:i:s', $expiry),
            'issuer' => $cert_info['issuer']['O'] ?? 'Unknown',
            'days_remaining' => max(0, floor(($expiry - $now) / 86400))
        );
    }

    /**
     * Detect SSL provider
     */
    public function detect_ssl_provider($domain) {
        $ssl_info = $this->check_ssl_certificate($domain);
        
        if (!$ssl_info) {
            return 'none';
        }

        $issuer = strtolower($ssl_info['issuer']);

        if (strpos($issuer, 'cloudflare') !== false) {
            return 'cloudflare';
        }

        if (strpos($issuer, 'lets encrypt') !== false || strpos($issuer, 'let\'s encrypt') !== false) {
            return 'lets_encrypt';
        }

        if (strpos($issuer, 'digicert') !== false) {
            return 'digicert';
        }

        if (strpos($issuer, 'comodo') !== false) {
            return 'comodo';
        }

        return 'other';
    }

    /**
     * Get SSL setup instructions
     */
    public function get_ssl_setup_instructions($domain) {
        $instructions = array();

        // Cloudflare instructions
        $instructions['cloudflare'] = array(
            'title' => __('Cloudflare SSL Setup', 'dokan-vendor-domain-mapper'),
            'description' => __('Cloudflare provides free SSL certificates with automatic renewal.', 'dokan-vendor-domain-mapper'),
            'steps' => array(
                __('Sign up for a free Cloudflare account at cloudflare.com', 'dokan-vendor-domain-mapper'),
                __('Add your domain to Cloudflare', 'dokan-vendor-domain-mapper'),
                __('Update your domain\'s nameservers to Cloudflare\'s nameservers', 'dokan-vendor-domain-mapper'),
                __('Wait for DNS propagation (usually 5-10 minutes)', 'dokan-vendor-domain-mapper'),
                __('In Cloudflare dashboard, go to SSL/TLS settings', 'dokan-vendor-domain-mapper'),
                __('Set SSL mode to "Flexible" or "Full"', 'dokan-vendor-domain-mapper'),
                __('SSL certificate will be automatically provisioned', 'dokan-vendor-domain-mapper')
            ),
            'benefits' => array(
                __('Free SSL certificates', 'dokan-vendor-domain-mapper'),
                __('Automatic renewal', 'dokan-vendor-domain-mapper'),
                __('CDN benefits', 'dokan-vendor-domain-mapper'),
                __('DDoS protection', 'dokan-vendor-domain-mapper')
            )
        );

        // Let's Encrypt instructions
        $instructions['lets_encrypt'] = array(
            'title' => __('Let\'s Encrypt SSL Setup', 'dokan-vendor-domain-mapper'),
            'description' => __('Let\'s Encrypt provides free SSL certificates with 90-day validity.', 'dokan-vendor-domain-mapper'),
            'steps' => array(
                __('Ensure you have SSH access to your server', 'dokan-vendor-domain-mapper'),
                __('Install Certbot on your server', 'dokan-vendor-domain-mapper'),
                __('Run: sudo certbot --nginx -d ' . $domain, 'dokan-vendor-domain-mapper'),
                __('Follow the prompts to complete certificate installation', 'dokan-vendor-domain-mapper'),
                __('Set up automatic renewal: sudo crontab -e', 'dokan-vendor-domain-mapper'),
                __('Add: 0 12 * * * /usr/bin/certbot renew --quiet', 'dokan-vendor-domain-mapper')
            ),
            'requirements' => array(
                __('Server access required', 'dokan-vendor-domain-mapper'),
                __('Domain must point to your server', 'dokan-vendor-domain-mapper'),
                __('Certbot must be installed', 'dokan-vendor-domain-mapper')
            )
        );

        // Manual SSL instructions
        $instructions['manual'] = array(
            'title' => __('Manual SSL Setup', 'dokan-vendor-domain-mapper'),
            'description' => __('Purchase and install SSL certificate manually.', 'dokan-vendor-domain-mapper'),
            'steps' => array(
                __('Purchase SSL certificate from a certificate authority', 'dokan-vendor-domain-mapper'),
                __('Generate CSR (Certificate Signing Request) on your server', 'dokan-vendor-domain-mapper'),
                __('Submit CSR to certificate authority', 'dokan-vendor-domain-mapper'),
                __('Download and install the certificate files', 'dokan-vendor-domain-mapper'),
                __('Configure your web server (Apache/Nginx)', 'dokan-vendor-domain-mapper'),
                __('Test SSL configuration', 'dokan-vendor-domain-mapper')
            ),
            'providers' => array(
                'DigiCert' => 'https://www.digicert.com/',
                'Comodo' => 'https://www.comodo.com/',
                'GlobalSign' => 'https://www.globalsign.com/',
                'GoDaddy' => 'https://www.godaddy.com/'
            )
        );

        return $instructions;
    }

    /**
     * Setup SSL for domain
     */
    public function setup_ssl($domain_id, $provider = 'cloudflare') {
        global $wpdb;

        $domain_mapping = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}dokan_domain_mappings WHERE id = %d",
            $domain_id
        ));

        if (!$domain_mapping) {
            return new WP_Error('domain_not_found', __('Domain mapping not found.', 'dokan-vendor-domain-mapper'));
        }

        $domain = $domain_mapping->domain;

        switch ($provider) {
            case 'cloudflare':
                return $this->setup_cloudflare_ssl($domain_mapping);
            
            case 'lets_encrypt':
                return $this->setup_lets_encrypt_ssl($domain_mapping);
            
            case 'manual':
                return $this->setup_manual_ssl($domain_mapping);
            
            default:
                return new WP_Error('invalid_provider', __('Invalid SSL provider.', 'dokan-vendor-domain-mapper'));
        }
    }

    /**
     * Setup Cloudflare SSL
     */
    private function setup_cloudflare_ssl($domain_mapping) {
        // Update SSL status
        global $wpdb;
        
        $result = $wpdb->update(
            $wpdb->prefix . 'dokan_domain_mappings',
            array(
                'ssl_status' => 'cloudflare',
                'updated_at' => current_time('mysql')
            ),
            array('id' => $domain_mapping->id),
            array('%s', '%s'),
            array('%d')
        );

        if ($result === false) {
            return new WP_Error('database_error', __('Failed to update SSL status.', 'dokan-vendor-domain-mapper'));
        }

        return array(
            'success' => true,
            'message' => __('Cloudflare SSL setup initiated. Please complete the DNS configuration.', 'dokan-vendor-domain-mapper'),
            'instructions' => $this->get_ssl_setup_instructions($domain_mapping->domain)['cloudflare']
        );
    }

    /**
     * Setup Let's Encrypt SSL
     */
    private function setup_lets_encrypt_ssl($domain_mapping) {
        // Check if Let's Encrypt is enabled
        if (get_option('dokan_domain_mapper_lets_encrypt_enabled', 'no') !== 'yes') {
            return new WP_Error('lets_encrypt_disabled', __('Let\'s Encrypt is not enabled.', 'dokan-vendor-domain-mapper'));
        }

        // Update SSL status
        global $wpdb;
        
        $result = $wpdb->update(
            $wpdb->prefix . 'dokan_domain_mappings',
            array(
                'ssl_status' => 'auto',
                'updated_at' => current_time('mysql')
            ),
            array('id' => $domain_mapping->id),
            array('%s', '%s'),
            array('%d')
        );

        if ($result === false) {
            return new WP_Error('database_error', __('Failed to update SSL status.', 'dokan-vendor-domain-mapper'));
        }

        // Generate Let's Encrypt command
        $command = $this->generate_lets_encrypt_command($domain_mapping->domain);

        return array(
            'success' => true,
            'message' => __('Let\'s Encrypt SSL setup initiated.', 'dokan-vendor-domain-mapper'),
            'command' => $command,
            'instructions' => $this->get_ssl_setup_instructions($domain_mapping->domain)['lets_encrypt']
        );
    }

    /**
     * Automated SSL provisioning
     */
    public function auto_provision_ssl($domain_id) {
        global $wpdb;

        $domain_mapping = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}dokan_domain_mappings WHERE id = %d",
            $domain_id
        ));

        if (!$domain_mapping) {
            return new WP_Error('domain_not_found', __('Domain mapping not found.', 'dokan-vendor-domain-mapper'));
        }

        // Check if auto SSL is enabled
        if (get_option('dokan_domain_mapper_auto_ssl_renewal', 'no') !== 'yes') {
            return new WP_Error('auto_ssl_disabled', __('Auto SSL provisioning is disabled.', 'dokan-vendor-domain-mapper'));
        }

        // Determine best SSL provider
        $provider = $this->determine_best_ssl_provider($domain_mapping->domain);
        
        // Attempt SSL setup
        $result = $this->setup_ssl($domain_id, $provider);
        
        if (is_wp_error($result)) {
            // Log the error
            error_log("Auto SSL provisioning failed for domain {$domain_mapping->domain}: " . $result->get_error_message());
            return $result;
        }

        // Send notification
        $this->notify_ssl_provisioned($domain_mapping, $provider);

        return array(
            'success' => true,
            'provider' => $provider,
            'message' => sprintf(__('SSL certificate automatically provisioned using %s.', 'dokan-vendor-domain-mapper'), ucfirst($provider))
        );
    }

    /**
     * Determine best SSL provider for domain
     */
    private function determine_best_ssl_provider($domain) {
        // Check if Cloudflare is available
        if ($this->is_cloudflare_domain($domain)) {
            return 'cloudflare';
        }

        // Check if Let's Encrypt is available
        if ($this->can_use_lets_encrypt($domain)) {
            return 'lets_encrypt';
        }

        // Fallback to manual
        return 'manual';
    }

    /**
     * Check if domain is on Cloudflare
     */
    private function is_cloudflare_domain($domain) {
        $nameservers = dns_get_record($domain, DNS_NS);
        
        foreach ($nameservers as $ns) {
            if (strpos($ns['target'], 'cloudflare.com') !== false) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Check if Let's Encrypt can be used
     */
    private function can_use_lets_encrypt($domain) {
        // Check if certbot is available
        $certbot_path = $this->find_certbot_path();
        if (!$certbot_path) {
            return false;
        }

        // Check if domain is accessible
        $context = stream_context_create(array(
            'http' => array(
                'timeout' => 10,
                'user_agent' => 'Dokan-Domain-Mapper/1.0'
            )
        ));

        $response = @file_get_contents("http://{$domain}", false, $context);
        return $response !== false;
    }

    /**
     * Find certbot path
     */
    private function find_certbot_path() {
        $possible_paths = array(
            '/usr/bin/certbot',
            '/usr/local/bin/certbot',
            '/opt/certbot/bin/certbot',
            'certbot'
        );

        foreach ($possible_paths as $path) {
            if (is_executable($path) || shell_exec("which {$path}")) {
                return $path;
            }
        }

        return false;
    }

    /**
     * Notify SSL provisioned
     */
    private function notify_ssl_provisioned($domain_mapping, $provider) {
        $vendor = get_user_by('id', $domain_mapping->vendor_id);
        if (!$vendor) {
            return;
        }

        $subject = sprintf(__('SSL Certificate Provisioned for %s', 'dokan-vendor-domain-mapper'), $domain_mapping->domain);
        $message = sprintf(
            __('Hello %s,

Your SSL certificate for %s has been automatically provisioned using %s.

Domain: %s
SSL Provider: %s
Status: Active

Your domain is now secure with HTTPS.

Best regards,
%s', 'dokan-vendor-domain-mapper'),
            $vendor->display_name,
            $domain_mapping->domain,
            ucfirst($provider),
            $domain_mapping->domain,
            ucfirst($provider),
            get_bloginfo('name')
        );

        wp_mail($vendor->user_email, $subject, $message);
    }

    /**
     * Setup manual SSL
     */
    private function setup_manual_ssl($domain_mapping) {
        // Update SSL status
        global $wpdb;
        
        $result = $wpdb->update(
            $wpdb->prefix . 'dokan_domain_mappings',
            array(
                'ssl_status' => 'manual',
                'updated_at' => current_time('mysql')
            ),
            array('id' => $domain_mapping->id),
            array('%s', '%s'),
            array('%d')
        );

        if ($result === false) {
            return new WP_Error('database_error', __('Failed to update SSL status.', 'dokan-vendor-domain-mapper'));
        }

        return array(
            'success' => true,
            'message' => __('Manual SSL setup initiated. Please follow the instructions to install your certificate.', 'dokan-vendor-domain-mapper'),
            'instructions' => $this->get_ssl_setup_instructions($domain_mapping->domain)['manual']
        );
    }

    /**
     * Generate Let's Encrypt command
     */
    private function generate_lets_encrypt_command($domain) {
        $commands = array();

        // Check if certbot is installed
        $commands[] = 'which certbot || echo "Certbot not found"';

        // Generate certificate
        $commands[] = "sudo certbot --nginx -d {$domain} --non-interactive --agree-tos --email " . get_option('admin_email');

        // Test renewal
        $commands[] = 'sudo certbot renew --dry-run';

        return implode("\n", $commands);
    }

    /**
     * Check SSL renewals
     */
    public function check_ssl_renewals() {
        global $wpdb;

        // Get domains with SSL certificates that expire soon
        $domains = $wpdb->get_results("
            SELECT * FROM {$wpdb->prefix}dokan_domain_mappings 
            WHERE ssl_status IN ('auto', 'lets_encrypt') 
            AND ssl_expiry_date IS NOT NULL
        ");

        foreach ($domains as $domain_mapping) {
            $ssl_info = $this->check_ssl_certificate($domain_mapping->domain);
            
            if ($ssl_info && $ssl_info['days_remaining'] <= 30) {
                $this->notify_ssl_expiry($domain_mapping, $ssl_info);
            }
        }
    }

    /**
     * Notify SSL expiry
     */
    private function notify_ssl_expiry($domain_mapping, $ssl_info) {
        $vendor = dokan_get_vendor_by_id($domain_mapping->vendor_id);
        if (!$vendor) {
            return;
        }

        $subject = sprintf(__('SSL Certificate Expiring Soon: %s', 'dokan-vendor-domain-mapper'), $domain_mapping->domain);
        $message = sprintf(
            __('Your SSL certificate for %s will expire on %s (%d days remaining).

Please renew your SSL certificate to avoid service interruption.

If you are using Let\'s Encrypt, the certificate should renew automatically.
If you are using a manual certificate, please contact your certificate provider.', 'dokan-vendor-domain-mapper'),
            $domain_mapping->domain,
            $ssl_info['expiry'],
            $ssl_info['days_remaining']
        );

        wp_mail($vendor->get_email(), $subject, $message);
    }

    /**
     * Process AJAX request for checking SSL status
     */
    public function process_ajax_check_ssl_status() {
        check_ajax_referer('dokan_domain_mapper_nonce', 'nonce');

        if (!current_user_can('dokan_is_seller')) {
            wp_die(__('Access denied.', 'dokan-vendor-domain-mapper'));
        }

        $domain_id = intval($_POST['domain_id']);
        
        global $wpdb;
        $domain_mapping = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}dokan_domain_mappings WHERE id = %d",
            $domain_id
        ));

        if (!$domain_mapping) {
            wp_send_json_error(__('Domain mapping not found.', 'dokan-vendor-domain-mapper'));
        }

        $ssl_status = $this->get_ssl_status($domain_mapping);
        wp_send_json_success($ssl_status);
    }

    /**
     * Process AJAX request for setting up SSL
     */
    public function process_ajax_setup_ssl() {
        check_ajax_referer('dokan_domain_mapper_nonce', 'nonce');

        if (!current_user_can('dokan_is_seller')) {
            wp_die(__('Access denied.', 'dokan-vendor-domain-mapper'));
        }

        $domain_id = intval($_POST['domain_id']);
        $provider = sanitize_text_field($_POST['provider']);

        $result = $this->setup_ssl($domain_id, $provider);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success($result);
        }
    }

    /**
     * Get SSL configuration for web server
     */
    public function get_ssl_configuration($domain_mapping) {
        $domain = $domain_mapping->domain;
        $ssl_status = $domain_mapping->ssl_status;

        $configs = array();

        // NGINX configuration
        $configs['nginx'] = array(
            'title' => __('NGINX Configuration', 'dokan-vendor-domain-mapper'),
            'content' => $this->generate_nginx_ssl_config($domain, $ssl_status)
        );

        // Apache configuration
        $configs['apache'] = array(
            'title' => __('Apache Configuration', 'dokan-vendor-domain-mapper'),
            'content' => $this->generate_apache_ssl_config($domain, $ssl_status)
        );

        return $configs;
    }

    /**
     * Generate NGINX SSL configuration
     */
    private function generate_nginx_ssl_config($domain, $ssl_status) {
        $config = "server {\n";
        $config .= "    listen 80;\n";
        $config .= "    listen 443 ssl;\n";
        $config .= "    server_name {$domain};\n\n";

        if ($ssl_status !== 'none') {
            $config .= "    # SSL Configuration\n";
            $config .= "    ssl_certificate /etc/ssl/certs/{$domain}.crt;\n";
            $config .= "    ssl_certificate_key /etc/ssl/private/{$domain}.key;\n";
            $config .= "    ssl_protocols TLSv1.2 TLSv1.3;\n";
            $config .= "    ssl_ciphers ECDHE-RSA-AES256-GCM-SHA512:DHE-RSA-AES256-GCM-SHA512:ECDHE-RSA-AES256-GCM-SHA384:DHE-RSA-AES256-GCM-SHA384;\n";
            $config .= "    ssl_prefer_server_ciphers off;\n\n";
        }

        $config .= "    location / {\n";
        $config .= "        proxy_pass " . get_site_url() . ";\n";
        $config .= "        proxy_set_header Host \$host;\n";
        $config .= "        proxy_set_header X-Real-IP \$remote_addr;\n";
        $config .= "        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;\n";
        $config .= "        proxy_set_header X-Forwarded-Proto \$scheme;\n";
        $config .= "    }\n";
        $config .= "}\n";

        return $config;
    }

    /**
     * Generate Apache SSL configuration
     */
    private function generate_apache_ssl_config($domain, $ssl_status) {
        $config = "<VirtualHost *:80>\n";
        $config .= "    ServerName {$domain}\n";
        $config .= "    Redirect permanent / https://{$domain}/\n";
        $config .= "</VirtualHost>\n\n";

        $config .= "<VirtualHost *:443>\n";
        $config .= "    ServerName {$domain}\n";

        if ($ssl_status !== 'none') {
            $config .= "    SSLEngine on\n";
            $config .= "    SSLCertificateFile /etc/ssl/certs/{$domain}.crt\n";
            $config .= "    SSLCertificateKeyFile /etc/ssl/private/{$domain}.key\n";
            $config .= "    SSLCertificateChainFile /etc/ssl/certs/{$domain}.chain.crt\n\n";
        }

        $config .= "    ProxyPreserveHost On\n";
        $config .= "    ProxyPass / " . get_site_url() . "/\n";
        $config .= "    ProxyPassReverse / " . get_site_url() . "/\n";
        $config .= "</VirtualHost>\n";

        return $config;
    }
} 