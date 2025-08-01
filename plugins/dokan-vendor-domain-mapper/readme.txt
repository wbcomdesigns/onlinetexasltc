=== Dokan Vendor Domain Mapper ===
Contributors: dokan, woocommerce
Tags: dokan, woocommerce, marketplace, domain, mapping, vendor, store, custom domain, ssl, proxy
Requires at least: 5.0
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Enable Dokan vendors to map custom domains to their store URLs with DNS verification, SSL support, and reverse proxy configuration.

== Description ==

**Dokan Vendor Domain Mapper** is a powerful extension for Dokan-powered WooCommerce marketplaces that allows vendors to create white-labeled storefronts using their own custom domains.

= Key Features =

**For Vendors:**
* Add custom domains or subdomains via Dokan dashboard
* Automatic DNS verification with TXT records
* Real-time domain status monitoring
* SSL certificate management
* Domain health checks and monitoring

**For Administrators:**
* Centralized domain request management
* Bulk approval/rejection workflows
* Reverse proxy configuration generation
* SSL certificate provisioning
* Comprehensive domain analytics

**Technical Features:**
* DNS verification using TXT records
* Support for multiple reverse proxy servers (NGINX, Apache, Cloudflare Workers, Caddy)
* SSL certificate management (Let's Encrypt, Cloudflare, manual)
* WordPress REST API integration
* Automated SSL renewal checks
* Security validation and domain limits

= Perfect For =

* **Marketplace Owners** who want to offer white-label solutions to vendors
* **Vendors** who want their own branded storefronts
* **Developers** who need programmatic domain management
* **Agencies** managing multiple vendor domains

= Server Requirements =

* Dokan Plugin (Free or Pro)
* WooCommerce 5.0+
* PHP 7.4+
* Server with reverse proxy capabilities (NGINX, Apache, Cloudflare, etc.)
* Optional: SSL certificate provisioning (Let's Encrypt, Cloudflare)

= Quick Start =

1. Install and activate the plugin
2. Configure your server's reverse proxy settings
3. Vendors can add domains from their Dokan dashboard
4. Verify domain ownership via DNS TXT records
5. Approve domains from the admin panel
6. Generate and deploy reverse proxy configurations

= Why Choose This Plugin? =

* **Complete Solution**: Everything needed for domain mapping in one plugin
* **Security First**: DNS verification, domain validation, and security checks
* **Flexible Deployment**: Works with any reverse proxy setup
* **SSL Ready**: Built-in SSL certificate management
* **Developer Friendly**: REST API, hooks, and filters for customization
* **Production Ready**: Comprehensive error handling and monitoring

== Installation ==

= Automatic Installation (Recommended) =

1. Upload the plugin files to `/wp-content/plugins/dokan-vendor-domain-mapper/`
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Configure the plugin settings under 'Dokan → Domain Settings'
4. Set up your server's reverse proxy configuration

= Manual Installation =

1. Download the plugin zip file
2. Extract the files to `/wp-content/plugins/dokan-vendor-domain-mapper/`
3. Activate the plugin through the 'Plugins' screen in WordPress
4. Follow the setup wizard to configure your domain mapping

= Server Configuration =

**NGINX Example:**
```nginx
server {
    listen 80;
    server_name vendor-domain.com;
    
    location / {
        proxy_pass https://yourmarketplace.com/store/vendorname;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

**Apache Example:**
```apache
<VirtualHost *:80>
    ServerName vendor-domain.com
    ProxyPreserveHost On
    ProxyPass / https://yourmarketplace.com/store/vendorname/
    ProxyPassReverse / https://yourmarketplace.com/store/vendorname/
</VirtualHost>
```

== Frequently Asked Questions ==

= Do I need server access to use this plugin? =

While server access is recommended for optimal performance, you can use a reverse proxy server or services like Cloudflare Workers to handle domain mapping without direct server access.

= How does SSL certificate management work? =

The plugin supports multiple SSL providers:
* **Cloudflare**: Automatic SSL with Cloudflare integration
* **Let's Encrypt**: Automated certificate provisioning
* **Manual**: Upload your own certificates

= Can vendors use subdomains? =

Yes! Vendors can map both root domains (example.com) and subdomains (shop.example.com) to their stores.

= Is there a limit on domains per vendor? =

Yes, you can configure domain limits per vendor in the plugin settings. Default is 1 domain per vendor.

= How secure is the DNS verification? =

The plugin uses TXT record verification, which is the industry standard for domain ownership verification. Each domain gets a unique verification token.

= Can I customize the vendor dashboard interface? =

Yes! The plugin provides hooks, filters, and template overrides for complete customization.

= Does this work with WordPress Multisite? =

The plugin is designed for single-site installations. Multisite compatibility is planned for future versions.

= How do I handle SSL certificate renewals? =

The plugin includes automated SSL renewal checks via WordPress cron jobs and will notify vendors of expiring certificates.

== Screenshots ==

1. Vendor Dashboard - Domain Management Interface
2. Admin Panel - Domain Request Management
3. DNS Verification Process
4. SSL Certificate Management
5. Reverse Proxy Configuration Generator
6. Domain Health Monitoring

== Changelog ==

= 1.0.0 =
* Initial release
* Core domain mapping functionality
* DNS verification system
* Admin and vendor dashboards
* SSL certificate management
* Reverse proxy configuration generation
* REST API endpoints
* Comprehensive documentation

== Upgrade Notice ==

= 1.0.0 =
Initial release of Dokan Vendor Domain Mapper. This plugin provides complete domain mapping functionality for Dokan-powered marketplaces.

== Support ==

**Documentation:** [Plugin Documentation](https://github.com/dokan/dokan-vendor-domain-mapper)
**Support Forum:** [WordPress.org Support](https://wordpress.org/support/plugin/dokan-vendor-domain-mapper)
**GitHub Issues:** [Report Bugs](https://github.com/dokan/dokan-vendor-domain-mapper/issues)

== Credits ==

Developed by the Dokan team with contributions from the WordPress community.

== License ==

This plugin is licensed under the GPL v2 or later.

== Privacy ==

This plugin does not collect any personal data beyond what is necessary for domain mapping functionality. All domain verification data is stored locally and is not transmitted to external services. 