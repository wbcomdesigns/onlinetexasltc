# Changelog

All notable changes to Online Texas Core plugin will be documented in this file.

## [1.2.0] - 2025-08-01

### Added
- Vendor Codes System with Uncanny LearnDash Codes integration
- Admin permissions system for controlling vendor code generation
- Code redemption shortcode [vendor_code_redeem]
- Recipe forwarding from vendor codes to admin Automator recipes
- Admin approval workflow for code generation requests
- Per-product code generation limits
- Vendor statistics tracking
- WooCommerce HPOS compatibility declaration

### Security
- Fixed SQL injection vulnerabilities in vendor codes system
- Fixed XSS vulnerabilities with proper output escaping
- Implemented proper nonce verification for AJAX operations
- Added comprehensive input validation and sanitization
- Fixed race conditions with atomic operations and database locks
- Implemented database transactions for data integrity

### Improved
- Performance optimization for code generation
- Error handling throughout the plugin
- Debug logging (now production-safe)

### Fixed
- Memory usage in pagination queries
- Session management with WooCommerce

## [1.1.0] - 2025-08-01

### Added
- Vendor synchronization system
- Automatic product creation for new vendors
- Role-based vendor detection
- Improved dependency checking

### Changed
- Enhanced admin dashboard with statistics and manual sync tools
- Better synchronization logic for published vs draft products
- Improved error handling and debugging capabilities

### Security
- Implemented security improvements and input validation
- Added production-ready logging system

### Improved
- Performance with caching and optimization
- Enhanced documentation and code structure

## [1.0.0] - Initial Release

### Features
- Basic product duplication for vendors
- LearnDash group creation and management
- Simple admin interface
- Automatic vendor product creation when admin creates products with course links