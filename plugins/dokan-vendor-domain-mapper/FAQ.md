# Frequently Asked Questions (FAQ)

## General Questions

### What is the Dokan Vendor Domain Mapper plugin?

The Dokan Vendor Domain Mapper plugin allows vendors on your Dokan-powered WooCommerce marketplace to map their own custom domains to their store URLs. This provides a white-labeled experience while maintaining centralized product and order management.

### What are the system requirements?

- **WordPress**: 5.0 or higher
- **Dokan**: 3.0 or higher (Free or Pro)
- **WooCommerce**: 5.0 or higher
- **PHP**: 7.4 or higher
- **MySQL**: 5.7 or higher

### Is this plugin free?

Yes, this plugin is released under the GPL v2 or later license, which means it's free to use, modify, and distribute.

### Does this plugin work with WordPress multisite?

Yes, the plugin is designed to work with both single-site and multisite WordPress installations. However, some features may require additional configuration in multisite environments.

## Installation & Setup

### How do I install the plugin?

1. Download the plugin ZIP file
2. Go to **WordPress Admin → Plugins → Add New**
3. Click **Upload Plugin**
4. Choose the ZIP file and click **Install Now**
5. Click **Activate Plugin**

### Do I need server access to use this plugin?

It depends on your setup:
- **Direct Server Access**: Required for automatic reverse proxy configuration
- **Proxy Server**: Requires a separate server for reverse proxy
- **Cloudflare**: No server access needed, but requires Cloudflare account

### Can I use this plugin on shared hosting?

Yes, but with limitations:
- You'll need to use a reverse proxy server or Cloudflare
- SSL certificate management may be manual
- Some advanced features may not be available

## Domain Management

### How many domains can a vendor have?

By default, each vendor can have 1 domain. This can be customized using the `dokan_domain_limit_per_vendor` filter.

### What types of domains are supported?

- Root domains (e.g., `mystore.com`)
- Subdomains (e.g., `shop.mystore.com`)
- Custom domains with any valid TLD

### Can vendors use subdomains of my main domain?

Yes, vendors can use subdomains of your main domain (e.g., `vendor.yourmarketplace.com`). This is often the easiest setup as it doesn't require external DNS configuration.

### How does domain verification work?

1. Vendor adds a domain
2. System generates a unique verification token
3. Vendor adds a TXT record to their DNS: `dokan-verification=TOKEN`
4. System verifies the TXT record exists
5. Admin approves the verified domain

### How long does DNS verification take?

DNS propagation can take anywhere from a few minutes to 24 hours, depending on the DNS provider and TTL settings.

## SSL Certificates

### Do I need SSL certificates for vendor domains?

Yes, SSL certificates are highly recommended for security and SEO. The plugin supports multiple SSL methods:
- **Let's Encrypt**: Free, automated certificates
- **Cloudflare**: Free SSL with Cloudflare proxy
- **Manual**: Upload your own certificates
- **Commercial**: Purchase from SSL providers

### How do I set up automatic SSL with Let's Encrypt?

1. Ensure you have server access
2. Install Certbot: `sudo apt-get install certbot python3-certbot-nginx`
3. Generate certificate: `sudo certbot --nginx -d vendor-domain.com`
4. Set up auto-renewal in crontab

### Can I use Cloudflare for SSL?

Yes, Cloudflare provides free SSL certificates. Simply:
1. Add your domain to Cloudflare
2. Point DNS to your server
3. Enable Cloudflare proxy (orange cloud)
4. SSL will be automatically provisioned

### What happens when SSL certificates expire?

The plugin monitors certificate expiry dates and will:
- Send email notifications to vendors
- Display warnings in the admin interface
- For Let's Encrypt, attempt automatic renewal

## Reverse Proxy Configuration

### What is a reverse proxy?

A reverse proxy is a server that forwards requests from vendor domains to your main WordPress site. It allows vendors to use their own domains while keeping all data centralized.

### Which web servers are supported?

- **NGINX**: Most popular, excellent performance
- **Apache**: Traditional choice, widely supported
- **Cloudflare Workers**: Serverless, global distribution
- **Caddy**: Modern, automatic SSL

### Do I need to configure the reverse proxy manually?

It depends on your setup:
- **Direct Server Access**: Plugin can generate configurations automatically
- **Proxy Server**: Manual configuration required
- **Cloudflare**: Automatic configuration via Workers

### Can I use a CDN with vendor domains?

Yes, you can use CDNs like:
- **Cloudflare**: Built-in CDN with SSL
- **AWS CloudFront**: High-performance CDN
- **Bunny CDN**: Cost-effective CDN
- **KeyCDN**: Developer-friendly CDN

## Troubleshooting

### DNS verification is failing. What should I do?

1. **Check DNS propagation**:
   ```bash
   dig TXT yourdomain.com
   nslookup -type=TXT yourdomain.com
   ```

2. **Verify TXT record format**:
   - Should be: `dokan-verification=YOUR_TOKEN`
   - No extra spaces or quotes

3. **Wait for propagation**:
   - Can take up to 24 hours
   - Check with multiple DNS servers

4. **Common issues**:
   - Wrong DNS provider selected
   - TXT record not saved properly
   - DNS cache not cleared

### My vendor domain is not loading. What's wrong?

1. **Check domain status**:
   - Ensure domain is "Live" in admin panel
   - Verify DNS is pointing to correct server

2. **Check reverse proxy**:
   - Verify configuration is applied
   - Check web server logs
   - Test proxy connection

3. **Check SSL certificate**:
   - Ensure certificate is valid
   - Check certificate chain
   - Verify domain matches certificate

### SSL certificate is not working. How do I fix it?

1. **Check certificate installation**:
   ```bash
   openssl s_client -connect yourdomain.com:443 -servername yourdomain.com
   ```

2. **Verify certificate files**:
   - Certificate file exists and is readable
   - Private key file exists and is readable
   - Certificate matches domain

3. **Check web server configuration**:
   - SSL configuration is correct
   - Certificate paths are accurate
   - Server is listening on port 443

### The admin interface is not showing domains. Why?

1. **Check user permissions**:
   - Ensure user has admin capabilities
   - Verify Dokan admin access

2. **Check database**:
   - Verify plugin is activated
   - Check if database table exists
   - Look for error logs

3. **Check plugin conflicts**:
   - Disable other plugins temporarily
   - Check for JavaScript errors
   - Verify theme compatibility

## Performance & Optimization

### How does this plugin affect performance?

The plugin is designed for minimal performance impact:
- **Database queries**: Optimized and cached
- **DNS lookups**: Cached to reduce latency
- **SSL checks**: Performed asynchronously
- **Resource usage**: Minimal memory footprint

### Can I use caching with vendor domains?

Yes, you can use various caching solutions:
- **Object caching**: Redis, Memcached
- **Page caching**: WP Rocket, W3 Total Cache
- **CDN caching**: Cloudflare, Bunny CDN
- **Server caching**: NGINX FastCGI cache

### How do I monitor domain performance?

The plugin provides:
- **Health checks**: Automatic domain monitoring
- **Response time tracking**: Performance metrics
- **SSL certificate monitoring**: Expiry tracking
- **Error logging**: Detailed error reports

## Security

### Is this plugin secure?

Yes, the plugin implements multiple security measures:
- **DNS verification**: Prevents unauthorized domain mapping
- **Input validation**: All inputs are sanitized
- **Nonce verification**: CSRF protection
- **Access controls**: Role-based permissions
- **Rate limiting**: Prevents abuse

### Can vendors access other vendors' domains?

No, vendors can only access their own domains. The plugin implements strict access controls to ensure data isolation.

### What happens if a vendor's domain is compromised?

1. **Immediate actions**:
   - Disable the domain in admin panel
   - Revoke SSL certificate if necessary
   - Notify vendor of security issue

2. **Investigation**:
   - Check access logs
   - Review domain configuration
   - Identify security vulnerabilities

3. **Recovery**:
   - Re-verify domain ownership
   - Update security settings
   - Re-enable domain if safe

## API & Integration

### Can I integrate this with other plugins?

Yes, the plugin provides:
- **REST API**: Full API for external integrations
- **Hooks and filters**: WordPress standard hooks
- **AJAX endpoints**: For custom frontend integration
- **Webhook support**: For external notifications

### How do I use the REST API?

The plugin provides several API endpoints:

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
```

### Can I customize the domain verification process?

Yes, you can customize using WordPress hooks:

```php
// Custom domain validation
add_filter('dokan_domain_validation', function($is_valid, $domain) {
    // Your custom validation logic
    return $is_valid;
}, 10, 2);

// Custom DNS verification
add_filter('dokan_dns_verification', function($is_verified, $domain, $token) {
    // Your custom verification logic
    return $is_verified;
}, 10, 3);
```

## Support & Updates

### How do I get support?

- **Documentation**: [docs.your-site.com](https://docs.your-site.com)
- **GitHub Issues**: [Report Issues](https://github.com/your-username/dokan-vendor-domain-mapper/issues)
- **Email Support**: support@your-company.com
- **Community Forum**: [Community Forum](https://your-forum.com)

### How often is the plugin updated?

The plugin follows semantic versioning:
- **Major updates**: New features, may include breaking changes
- **Minor updates**: New features, backward compatible
- **Patch updates**: Bug fixes and security updates

### Can I contribute to the plugin?

Yes! We welcome contributions:
- **Bug reports**: Help improve the plugin
- **Feature requests**: Suggest new functionality
- **Code contributions**: Submit pull requests
- **Documentation**: Help improve docs
- **Testing**: Help with quality assurance

### Is there a premium version?

Currently, there is no premium version. All features are available in the free version. However, we may offer premium add-ons in the future for advanced features like:
- Advanced analytics
- Priority support
- Custom integrations
- White-label options

## Migration & Compatibility

### Can I migrate from another domain mapping plugin?

Yes, the plugin provides migration tools for common domain mapping plugins. Contact support for specific migration instructions.

### Does this work with other marketplace plugins?

The plugin is specifically designed for Dokan, but may work with other marketplace plugins with some customization. Test thoroughly before production use.

### What happens if I deactivate the plugin?

When deactivated:
- Domain mappings remain in database
- Vendor domains will stop working
- SSL certificates remain active
- No data is lost

### Can I backup my domain configurations?

Yes, the plugin provides:
- **Database backup**: Domain mapping data
- **Configuration export**: Proxy configurations
- **SSL certificate backup**: Certificate files
- **Full backup**: Complete plugin data

---

**Still have questions?** Contact our support team at support@your-company.com or visit our [documentation](https://docs.your-site.com) for more detailed information. 