=== Online Texas Core ===
Contributors: wbcomdesigns
Donate link: https://wbcomdesigns.com/
Tags: woocommerce, dokan, learndash, vendor, products, courses, integration
Requires at least: 5.0
Tested up to: 6.3
Requires PHP: 7.4
Stable tag: 1.2.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Automatically creates vendor products and LearnDash groups when admin creates products with course links. Seamlessly integrates WooCommerce, Dokan, LearnDash, and Uncanny LearnDash Codes for comprehensive vendor code management.

== Description ==

Online Texas Core is a powerful integration plugin that bridges WooCommerce, Dokan, and LearnDash to create a seamless vendor-based learning marketplace. When an admin creates a product and links it to LearnDash courses, the plugin automatically:

* **Creates duplicate products for all active vendors** with the format "Store Name - Product Name"
* **Generates individual LearnDash groups** for each vendor product
* **Links courses to vendor groups** and assigns vendors as group leaders
* **Maintains synchronization** between admin and vendor products
* **Preserves vendor pricing independence** while syncing product details

= Key Features =

* **Automatic Product Creation**: When admin creates products with course links, vendor versions are automatically generated
* **Smart Synchronization**: Updates to admin products sync to vendor products (description only for published vendor products)
* **LearnDash Integration**: Each vendor gets their own groups linked to the same courses as the original product
* **Vendor Independence**: Vendors can set their own pricing and publish when ready
* **Role-Based Detection**: Automatically creates products when users become vendors through any method
* **Manual Sync Tools**: Admin tools for manual synchronization and bulk operations
* **Debug Logging**: Comprehensive logging system for troubleshooting
* **Production Ready**: Built with security, performance, and scalability in mind

= New in 1.2.0 - Vendor Codes System =

* **Vendor Code Generation**: Vendors can generate registration codes for customers
* **Admin Permissions System**: Control code generation limits and approval workflows per product
* **Code Redemption**: Customers can redeem codes via shortcode for automatic course enrollment
* **Uncanny Integration**: Full integration with Uncanny LearnDash Codes and Automator
* **Recipe Forwarding**: Vendor codes trigger original admin Automator recipes
* **Vendor Dashboard**: Dedicated interface for code management in Dokan dashboard
* **Admin Oversight**: Comprehensive admin interface for managing vendor code requests
* **WooCommerce HPOS**: Declared compatibility with High-Performance Order Storage

= How It Works =

1. **Admin Creates Product**: Admin creates a WooCommerce product and links it to LearnDash courses or groups
2. **Automatic Duplication**: Plugin creates copies for all active vendors with vendor store name prefix
3. **LearnDash Groups**: Each vendor product gets its own LearnDash group linked to the same courses
4. **Vendor Customization**: Vendors can customize pricing and publish when ready
5. **Ongoing Sync**: Updates to admin products automatically sync to vendor products

= Requirements =

* WordPress 5.0 or higher
* PHP 7.4 or higher
* WooCommerce 5.0 or higher
* Dokan 3.0 or higher
* LearnDash 3.0 or higher
* Uncanny LearnDash Codes (for vendor codes functionality)
* Uncanny Automator (for recipe integration)

= Pro Features =

This is the core version. Contact Wbcom Designs for custom features and enterprise support.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/online-texas-core` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Ensure WooCommerce, Dokan, and LearnDash are installed and activated
4. Navigate to Dokan > Texas Core to view the dashboard
5. Configure settings under Settings > Texas Core

= Automatic Installation =

1. Go to your WordPress admin area and navigate to Plugins > Add New
2. Search for "Online Texas Core"
3. Click "Install Now" and then "Activate"

== Frequently Asked Questions ==

= What happens when I create a product with course links? =

The plugin automatically creates duplicate products for all active vendors in your marketplace. Each vendor product gets its own LearnDash group linked to the same courses as your original product.

= Can vendors set their own pricing? =

Yes! Vendor products are created as drafts with empty pricing, allowing vendors to set their own prices before publishing.

= What happens when I update an admin product? =

For draft vendor products: Full synchronization of all product details except pricing.
For published vendor products: Only the product description is updated to preserve vendor customizations.

= What if a vendor is added after I create products? =

New vendors automatically get products created for all existing admin products with course links. You can also manually sync specific vendors from the admin panel.

= Can I disable automatic product creation? =

Yes, you can disable automatic creation in Settings > Texas Core. You can then use the manual sync tools when needed.

= How do vendor codes work? =

Vendors can generate registration codes for their customers. When customers redeem these codes using the [vendor_code_redeem] shortcode, they are automatically enrolled in the associated courses through the admin's Automator recipes.

= Can I limit how many codes vendors can generate? =

Yes, admins can set per-product limits on code generation and optionally require approval for code generation requests through the Vendor Code Generation Permissions meta box on each product.

= What happens to LearnDash groups when vendor products are created? =

Each vendor product gets its own LearnDash group that's linked to the same courses as the original admin product. The vendor is automatically set as the group leader.

= Is this plugin compatible with Dokan Pro? =

Yes, the plugin works with both Dokan Lite and Dokan Pro versions.

= How do I troubleshoot issues? =

Enable debug mode in Settings > Texas Core to see detailed logs. Check the main plugin dashboard for recent activity and system status.

== Screenshots ==

1. Plugin dashboard showing statistics and quick actions
2. Settings page with configuration options
3. Product list showing linked courses and vendor information
4. Manual vendor sync interface
5. Debug log showing plugin activity

== Changelog ==

= 1.2.0 =
* Added: Vendor Codes System with Uncanny LearnDash Codes integration
* Added: Admin permissions system for controlling vendor code generation
* Added: Code redemption shortcode [vendor_code_redeem]
* Added: Recipe forwarding from vendor codes to admin Automator recipes
* Added: Admin approval workflow for code generation requests
* Added: Per-product code generation limits
* Added: Vendor statistics tracking
* Added: WooCommerce HPOS compatibility declaration
* Security: Fixed SQL injection vulnerabilities in vendor codes system
* Security: Fixed XSS vulnerabilities with proper output escaping
* Security: Implemented proper nonce verification for AJAX operations
* Security: Added comprehensive input validation and sanitization
* Security: Fixed race conditions with atomic operations and database locks
* Security: Implemented database transactions for data integrity
* Improved: Performance optimization for code generation
* Improved: Error handling throughout the plugin
* Improved: Debug logging (now production-safe)
* Fixed: Memory usage in pagination queries
* Fixed: Session management with WooCommerce

= 1.1.0 =
* Added comprehensive vendor lifecycle management
* Improved synchronization logic for published vs draft products
* Enhanced admin dashboard with statistics and manual sync tools
* Added proper error handling and debugging capabilities
* Implemented security improvements and input validation
* Added production-ready logging system
* Improved performance with caching and optimization
* Enhanced documentation and code structure

= 1.0.0 =
* Initial release
* Basic product duplication functionality
* LearnDash group creation
* Simple admin interface

== Upgrade Notice ==

= 1.2.0 =
Security update with new Vendor Codes System. Fixes SQL injection and XSS vulnerabilities. Adds code generation, admin permissions, and Uncanny integration. Backup recommended before upgrading.

= 1.1.0 =
Major update with vendor synchronization, improved performance, and enhanced admin dashboard. Backup recommended before upgrading.

= 1.0.0 =
Initial release of the plugin.

== Developer Notes ==

= Hooks and Filters =

The plugin provides several hooks for developers:

* `otc_before_vendor_product_created` - Fired before a vendor product is created
* `otc_after_vendor_product_created` - Fired after a vendor product is created
* `otc_vendor_product_sync_fields` - Filter the fields that are synced between admin and vendor products

= Custom Integration =

For custom integrations or modifications, please contact Wbcom Designs for professional development services.

= Contributing =

This plugin is developed by Wbcom Designs. For feature requests or bug reports, please contact our support team.

== Support ==

For support, feature requests, or custom development:

* Visit: https://wbcomdesigns.com/
* Email: admin@wbcomdesigns.com

Professional support and custom development services available.