<?php
/**
 * Admin Settings Template
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php _e('Domain Mapping Settings', 'dokan-vendor-domain-mapper'); ?></h1>

    <form method="post" action="options.php">
        <?php
        settings_fields('dokan_domain_mapper_settings');
        do_settings_sections('dokan_domain_mapper_settings');
        ?>

        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="enable_domain_mapping"><?php _e('Enable Domain Mapping', 'dokan-vendor-domain-mapper'); ?></label>
                </th>
                <td>
                    <input type="checkbox" id="enable_domain_mapping" name="dokan_domain_mapper_enable_domain_mapping" value="yes" <?php checked(get_option('dokan_domain_mapper_enable_domain_mapping', 'yes'), 'yes'); ?>>
                    <p class="description"><?php _e('Enable domain mapping functionality for vendors.', 'dokan-vendor-domain-mapper'); ?></p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="require_dns_verification"><?php _e('Require DNS Verification', 'dokan-vendor-domain-mapper'); ?></label>
                </th>
                <td>
                    <input type="checkbox" id="require_dns_verification" name="dokan_domain_mapper_require_dns_verification" value="yes" <?php checked(get_option('dokan_domain_mapper_require_dns_verification', 'yes'), 'yes'); ?>>
                    <p class="description"><?php _e('Require vendors to verify domain ownership via DNS TXT records.', 'dokan-vendor-domain-mapper'); ?></p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="require_admin_approval"><?php _e('Require Admin Approval', 'dokan-vendor-domain-mapper'); ?></label>
                </th>
                <td>
                    <input type="checkbox" id="require_admin_approval" name="dokan_domain_mapper_require_admin_approval" value="yes" <?php checked(get_option('dokan_domain_mapper_require_admin_approval', 'yes'), 'yes'); ?>>
                    <p class="description"><?php _e('Require admin approval before domains go live.', 'dokan-vendor-domain-mapper'); ?></p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="max_domains_per_vendor"><?php _e('Max Domains Per Vendor', 'dokan-vendor-domain-mapper'); ?></label>
                </th>
                <td>
                    <input type="number" id="max_domains_per_vendor" name="dokan_domain_mapper_max_domains_per_vendor" value="<?php echo esc_attr(get_option('dokan_domain_mapper_max_domains_per_vendor', 1)); ?>" min="1" max="10">
                    <p class="description"><?php _e('Maximum number of domains each vendor can add.', 'dokan-vendor-domain-mapper'); ?></p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="ssl_provider"><?php _e('Default SSL Provider', 'dokan-vendor-domain-mapper'); ?></label>
                </th>
                <td>
                    <select id="ssl_provider" name="dokan_domain_mapper_ssl_provider">
                        <option value="cloudflare" <?php selected(get_option('dokan_domain_mapper_ssl_provider', 'cloudflare'), 'cloudflare'); ?>><?php _e('Cloudflare', 'dokan-vendor-domain-mapper'); ?></option>
                        <option value="lets_encrypt" <?php selected(get_option('dokan_domain_mapper_ssl_provider', 'cloudflare'), 'lets_encrypt'); ?>><?php _e('Let\'s Encrypt', 'dokan-vendor-domain-mapper'); ?></option>
                        <option value="manual" <?php selected(get_option('dokan_domain_mapper_ssl_provider', 'cloudflare'), 'manual'); ?>><?php _e('Manual', 'dokan-vendor-domain-mapper'); ?></option>
                    </select>
                    <p class="description"><?php _e('Default SSL certificate provider for new domains.', 'dokan-vendor-domain-mapper'); ?></p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="proxy_server_enabled"><?php _e('Enable Proxy Server', 'dokan-vendor-domain-mapper'); ?></label>
                </th>
                <td>
                    <input type="checkbox" id="proxy_server_enabled" name="dokan_domain_mapper_proxy_server_enabled" value="yes" <?php checked(get_option('dokan_domain_mapper_proxy_server_enabled', 'no'), 'yes'); ?>>
                    <p class="description"><?php _e('Enable reverse proxy server configuration.', 'dokan-vendor-domain-mapper'); ?></p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="proxy_server_url"><?php _e('Proxy Server URL', 'dokan-vendor-domain-mapper'); ?></label>
                </th>
                <td>
                    <input type="url" id="proxy_server_url" name="dokan_domain_mapper_proxy_server_url" value="<?php echo esc_attr(get_option('dokan_domain_mapper_proxy_server_url', '')); ?>" class="regular-text">
                    <p class="description"><?php _e('URL of your reverse proxy server (e.g., https://proxy.yourdomain.com).', 'dokan-vendor-domain-mapper'); ?></p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="cloudflare_integration"><?php _e('Cloudflare Integration', 'dokan-vendor-domain-mapper'); ?></label>
                </th>
                <td>
                    <input type="checkbox" id="cloudflare_integration" name="dokan_domain_mapper_cloudflare_integration" value="yes" <?php checked(get_option('dokan_domain_mapper_cloudflare_integration', 'yes'), 'yes'); ?>>
                    <p class="description"><?php _e('Enable Cloudflare integration for automatic SSL and DNS management.', 'dokan-vendor-domain-mapper'); ?></p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="lets_encrypt_enabled"><?php _e('Let\'s Encrypt Integration', 'dokan-vendor-domain-mapper'); ?></label>
                </th>
                <td>
                    <input type="checkbox" id="lets_encrypt_enabled" name="dokan_domain_mapper_lets_encrypt_enabled" value="yes" <?php checked(get_option('dokan_domain_mapper_lets_encrypt_enabled', 'no'), 'yes'); ?>>
                    <p class="description"><?php _e('Enable Let\'s Encrypt integration for automatic SSL certificates.', 'dokan-vendor-domain-mapper'); ?></p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="auto_ssl_renewal"><?php _e('Auto SSL Renewal', 'dokan-vendor-domain-mapper'); ?></label>
                </th>
                <td>
                    <input type="checkbox" id="auto_ssl_renewal" name="dokan_domain_mapper_auto_ssl_renewal" value="yes" <?php checked(get_option('dokan_domain_mapper_auto_ssl_renewal', 'no'), 'yes'); ?>>
                    <p class="description"><?php _e('Automatically renew SSL certificates before expiry.', 'dokan-vendor-domain-mapper'); ?></p>
                </td>
            </tr>
        </table>

        <?php submit_button(); ?>
    </form>

    <div class="domain-mapper-settings-info">
        <h3><?php _e('Server Configuration', 'dokan-vendor-domain-mapper'); ?></h3>
        <p><?php _e('To use domain mapping, you need to configure your server with a reverse proxy. Here are the supported configurations:', 'dokan-vendor-domain-mapper'); ?></p>
        
        <ul>
            <li><strong>NGINX:</strong> <?php _e('Most common and recommended for high performance.', 'dokan-vendor-domain-mapper'); ?></li>
            <li><strong>Apache:</strong> <?php _e('Good for shared hosting environments.', 'dokan-vendor-domain-mapper'); ?></li>
            <li><strong>Cloudflare Workers:</strong> <?php _e('Serverless solution with automatic SSL.', 'dokan-vendor-domain-mapper'); ?></li>
            <li><strong>Caddy:</strong> <?php _e('Modern web server with automatic HTTPS.', 'dokan-vendor-domain-mapper'); ?></li>
        </ul>

        <p><a href="<?php echo admin_url('admin.php?page=dokan-domain-mapping'); ?>" class="button"><?php _e('View Domain Mappings', 'dokan-vendor-domain-mapper'); ?></a></p>
    </div>
</div>

<style>
.domain-mapper-settings-info {
    margin-top: 30px;
    padding: 20px;
    background: #f9f9f9;
    border-left: 4px solid #0073aa;
}

.domain-mapper-settings-info h3 {
    margin-top: 0;
}

.domain-mapper-settings-info ul {
    margin-left: 20px;
}

.domain-mapper-settings-info li {
    margin-bottom: 5px;
}
</style> 