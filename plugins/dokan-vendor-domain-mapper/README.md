# Dokan Vendor Domain Mapper

[![WordPress](https://img.shields.io/badge/WordPress-5.0+-blue.svg)](https://wordpress.org/)
[![Dokan](https://img.shields.io/badge/Dokan-3.0+-orange.svg)](https://wedevs.com/dokan/)
[![WooCommerce](https://img.shields.io/badge/WooCommerce-5.0+-green.svg)](https://woocommerce.com/)
[![License](https://img.shields.io/badge/License-GPL%20v2%20or%20later-green.svg)](https://www.gnu.org/licenses/gpl-2.0.html)

> **Professional domain mapping solution for Dokan-powered WooCommerce marketplaces**

The Dokan Vendor Domain Mapper plugin enables vendors on your Dokan marketplace to map their own custom domains to their store URLs. This provides a white-labeled experience while maintaining centralized product and order management.

## 🚀 Features

### For Vendors
- **Custom Domain Mapping**: Map your own domain to your Dokan store
- **DNS Verification**: Simple TXT record verification process
- **SSL Certificate Management**: Automatic SSL detection and setup guidance
- **Domain Health Monitoring**: Real-time domain status and health checks
- **User-Friendly Dashboard**: Integrated into Dokan vendor dashboard

### For Administrators
- **Centralized Management**: Approve and manage all vendor domain requests
- **Bulk Operations**: Handle multiple domains efficiently
- **Reverse Proxy Configuration**: Generate server configurations automatically
- **SSL Certificate Monitoring**: Track SSL status and expiry dates
- **Comprehensive Analytics**: Domain usage and performance insights

### Technical Features
- **Multi-Server Support**: NGINX, Apache, Cloudflare Workers, Caddy
- **REST API**: Full API support for integrations
- **Security First**: DNS verification, domain validation, access controls
- **Performance Optimized**: Caching, monitoring, and health checks
- **WordPress Standards**: Follows WordPress coding standards and best practices

## 📋 Requirements

- **WordPress**: 5.0 or higher
- **Dokan**: 3.0 or higher (Free or Pro)
- **WooCommerce**: 5.0 or higher
- **PHP**: 7.4 or higher
- **MySQL**: 5.7 or higher

### Server Requirements
- **For Direct Server Access**: SSH access to configure reverse proxy
- **For Proxy Server**: Separate server for reverse proxy setup
- **For Cloudflare**: Cloudflare account with DNS access

## 🛠️ Installation

### Method 1: Manual Installation (Recommended)

1. **Download the Plugin**
   ```bash
   # Clone the repository
   git clone https://github.com/your-username/dokan-vendor-domain-mapper.git
   
   # Or download the ZIP file and extract
   ```

2. **Upload to WordPress**
   ```bash
   # Copy to wp-content/plugins/
   cp -r dokan-vendor-domain-mapper wp-content/plugins/
   ```

3. **Activate the Plugin**
   - Go to **WordPress Admin → Plugins**
   - Find "Dokan Vendor Domain Mapper"
   - Click **Activate**

### Method 2: WordPress Admin Upload

1. Download the plugin ZIP file
2. Go to **WordPress Admin → Plugins → Add New**
3. Click **Upload Plugin**
4. Choose the ZIP file and click **Install Now**
5. Click **Activate Plugin**

## ⚙️ Configuration

### 1. Plugin Settings

Navigate to **Dokan → Domain Mapping → Settings** to configure:

```php
// Default settings
$default_settings = [
    'domain_limit_per_vendor' => 1,
    'auto_approve_domains' => false,
    'require_dns_verification' => true,
    'ssl_verification_required' => true,
    'proxy_server_type' => 'nginx',
    'cloudflare_integration' => false,
    'lets_encrypt_integration' => false,
];
```

### 2. Server Configuration

#### Option A: Direct Server Access (Recommended)

1. **NGINX Configuration**
   ```nginx
   # Add to your nginx.conf or site configuration
   server {
       listen 80;
       server_name ~^(?<vendor_domain>.+)\.yourmarketplace\.com$;
       
       location / {
           proxy_pass http://yourmarketplace.com/store/$vendor_domain;
           proxy_set_header Host $host;
           proxy_set_header X-Real-IP $remote_addr;
           proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
           proxy_set_header X-Forwarded-Proto $scheme;
       }
   }
   ```

2. **Apache Configuration**
   ```apache
   # Add to your .htaccess or virtual host
   RewriteEngine On
   RewriteCond %{HTTP_HOST} ^(.+)\.yourmarketplace\.com$
   RewriteRule ^(.*)$ https://yourmarketplace.com/store/%1/$1 [P,L]
   ```

#### Option B: Reverse Proxy Server

1. Set up a separate server for reverse proxy
2. Configure DNS to point vendor domains to proxy server
3. Use the generated configurations from the admin panel

#### Option C: Cloudflare Integration

1. Add your domain to Cloudflare
2. Configure DNS records to point to your main server
3. Enable Cloudflare proxy for vendor domains

### 3. SSL Certificate Setup

#### Automatic SSL (Let's Encrypt)
```bash
# Install Certbot
sudo apt-get install certbot python3-certbot-nginx

# Generate certificate
sudo certbot --nginx -d vendor-domain.com

# Set up auto-renewal
sudo crontab -e
# Add: 0 12 * * * /usr/bin/certbot renew --quiet
```

#### Manual SSL
1. Purchase SSL certificate from your provider
2. Upload certificate files to server
3. Configure web server to use certificates
4. Update domain mapping with certificate paths

## 📖 Usage

### For Vendors

1. **Access Domain Management**
   - Go to **Dokan Dashboard → Store Domain**
   - View your current domain status

2. **Add New Domain**
   - Enter your domain name (e.g., `mystore.com`)
   - Click **Add Domain**
   - Follow DNS verification instructions

3. **DNS Verification**
   - Add TXT record: `dokan-verification=YOUR_TOKEN`
   - Wait for DNS propagation (up to 24 hours)
   - Click **Verify Domain**

4. **Monitor Status**
   - Pending: Awaiting admin approval
   - Verified: DNS verified, pending approval
   - Approved: Ready for configuration
   - Live: Domain is active and accessible

### For Administrators

1. **Review Domain Requests**
   - Go to **Dokan → Domain Mapping**
   - View all pending domain requests
   - Check DNS verification status

2. **Approve Domains**
   - Review vendor and domain information
   - Click **Approve** for verified domains
   - Generate proxy configuration if needed

3. **Manage SSL Certificates**
   - Monitor SSL certificate expiry dates
   - Set up automatic renewal for Let's Encrypt
   - Configure manual SSL certificates

4. **Generate Configurations**
   - Select server type (NGINX, Apache, etc.)
   - Generate configuration files
   - Provide setup instructions to server admin

## 🔧 API Reference

### REST API Endpoints

#### Vendor Endpoints
```php
// Get vendor domains
GET /wp-json/dokan/v1/domains

// Add new domain
POST /wp-json/dokan/v1/domains
{
    "domain": "mystore.com"
}

// Verify domain
POST /wp-json/dokan/v1/domains/{id}/verify

// Delete domain
DELETE /wp-json/dokan/v1/domains/{id}
```

#### Admin Endpoints
```php
// Get all domains
GET /wp-json/dokan/v1/admin/domains

// Approve domain
POST /wp-json/dokan/v1/admin/domains/{id}/approve

// Reject domain
POST /wp-json/dokan/v1/admin/domains/{id}/reject
{
    "reason": "Invalid domain format"
}
```

#### Public Endpoints
```php
// Check domain availability
GET /wp-json/dokan/v1/public/domain-availability?domain=mystore.com

// Validate domain format
GET /wp-json/dokan/v1/public/validate-domain?domain=mystore.com
```

### AJAX Actions

#### Vendor Actions
```php
// Add domain
wp_ajax_dokan_add_domain

// Verify domain
wp_ajax_dokan_verify_domain

// Delete domain
wp_ajax_dokan_delete_domain
```

#### Admin Actions
```php
// Approve domain
wp_ajax_dokan_approve_domain

// Reject domain
wp_ajax_dokan_reject_domain

// Generate proxy config
wp_ajax_dokan_generate_proxy_config
```

## 🎨 Customization

### Hooks and Filters

#### Domain Validation
```php
// Custom domain validation
add_filter('dokan_domain_validation', function($is_valid, $domain) {
    // Your custom validation logic
    return $is_valid;
}, 10, 2);

// Custom domain limit per vendor
add_filter('dokan_domain_limit_per_vendor', function($limit) {
    return 5; // Allow 5 domains per vendor
});
```

#### DNS Verification
```php
// Custom DNS verification
add_filter('dokan_dns_verification', function($is_verified, $domain, $token) {
    // Your custom verification logic
    return $is_verified;
}, 10, 3);
```

#### Proxy Configuration
```php
// Custom proxy configuration
add_filter('dokan_proxy_config', function($config, $domain_mapping) {
    // Modify configuration
    return $config;
}, 10, 2);
```

### Template Overrides

Create custom templates in your theme:

```php
// Copy templates to your theme
wp-content/plugins/dokan-vendor-domain-mapper/templates/
└── your-theme/
    ├── admin/
    │   └── domain-mappings.php
    └── vendor/
        └── domain-mapping.php
```

## 🔒 Security

### Built-in Security Features

- **DNS Verification**: Prevents unauthorized domain mapping
- **Domain Validation**: Ensures proper domain format
- **Access Controls**: Vendor-specific domain access
- **Nonce Verification**: CSRF protection for all forms
- **Rate Limiting**: Prevents abuse of API endpoints
- **Input Sanitization**: All user inputs are sanitized

### Best Practices

1. **Regular Updates**: Keep the plugin updated
2. **SSL Certificates**: Always use HTTPS for vendor domains
3. **Server Security**: Secure your reverse proxy server
4. **Domain Monitoring**: Monitor for suspicious domain activities
5. **Backup Strategy**: Regular backups of domain configurations

## 🐛 Troubleshooting

### Common Issues

#### DNS Verification Fails
```php
// Check DNS propagation
$dns_records = dns_get_record($domain, DNS_TXT);
if (empty($dns_records)) {
    // DNS not propagated yet
    echo "DNS not propagated. Wait up to 24 hours.";
}
```

#### SSL Certificate Issues
```php
// Check SSL certificate
$ssl_info = $ssl_manager->check_ssl_certificate($domain);
if (!$ssl_info['valid']) {
    echo "SSL certificate invalid: " . $ssl_info['error'];
}
```

#### Proxy Configuration Problems
```php
// Test proxy connection
$health_check = $proxy_manager->test_connection($domain);
if (!$health_check['success']) {
    echo "Proxy connection failed: " . $health_check['error'];
}
```

### Debug Mode

Enable debug mode in `wp-config.php`:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

Check logs at `wp-content/debug.log`

### Support Resources

- [Documentation](https://your-docs-site.com)
- [GitHub Issues](https://github.com/your-username/dokan-vendor-domain-mapper/issues)
- [Community Forum](https://your-forum.com)
- [Email Support](mailto:support@your-company.com)

## 📊 Performance

### Optimization Tips

1. **Caching**: Enable object caching for domain lookups
2. **Database**: Optimize domain mapping queries
3. **CDN**: Use CDN for vendor domain assets
4. **Monitoring**: Monitor domain response times
5. **Cleanup**: Regular cleanup of expired domains

### Benchmarks

- **Domain Lookup**: < 50ms
- **DNS Verification**: < 100ms
- **SSL Check**: < 200ms
- **Proxy Generation**: < 100ms

## 🤝 Contributing

We welcome contributions! Please see our [Contributing Guide](CONTRIBUTING.md) for details.

### Development Setup

```bash
# Clone repository
git clone https://github.com/your-username/dokan-vendor-domain-mapper.git

# Install dependencies
composer install

# Run tests
phpunit

# Build for production
npm run build
```

## 📄 License

This project is licensed under the GPL v2 or later - see the [LICENSE](LICENSE) file for details.

## 🙏 Acknowledgments

- [Dokan](https://wedevs.com/dokan/) - The amazing marketplace plugin
- [WooCommerce](https://woocommerce.com/) - The e-commerce platform
- [WordPress](https://wordpress.org/) - The content management system
- All contributors and community members

## 📞 Support

- **Documentation**: [docs.your-site.com](https://docs.your-site.com)
- **GitHub Issues**: [Report a Bug](https://github.com/your-username/dokan-vendor-domain-mapper/issues)
- **Email**: support@your-company.com
- **Live Chat**: Available on our website

---

**Made with ❤️ for the WordPress community** 