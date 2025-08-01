# Contributing to Dokan Vendor Domain Mapper

Thank you for your interest in contributing to the Dokan Vendor Domain Mapper plugin! This document provides guidelines and information for contributors.

## 🤝 How to Contribute

### Types of Contributions

We welcome various types of contributions:

- **Bug Reports**: Report bugs and issues
- **Feature Requests**: Suggest new features
- **Code Contributions**: Submit pull requests
- **Documentation**: Improve documentation
- **Testing**: Help with testing and quality assurance
- **Translation**: Help translate the plugin

### Before You Start

1. **Check Existing Issues**: Search existing issues to avoid duplicates
2. **Read Documentation**: Familiarize yourself with the plugin architecture
3. **Set Up Development Environment**: Follow the setup guide below
4. **Follow Coding Standards**: Adhere to WordPress coding standards

## 🛠️ Development Setup

### Prerequisites

- **PHP**: 7.4 or higher
- **WordPress**: 5.0 or higher
- **Dokan**: 3.0 or higher
- **WooCommerce**: 5.0 or higher
- **Composer**: For dependency management
- **Node.js**: For asset compilation (optional)
- **Git**: For version control

### Local Development Environment

1. **Clone the Repository**
   ```bash
   git clone https://github.com/your-username/dokan-vendor-domain-mapper.git
   cd dokan-vendor-domain-mapper
   ```

2. **Install Dependencies**
   ```bash
   # Install PHP dependencies
   composer install
   
   # Install Node.js dependencies (if using asset compilation)
   npm install
   ```

3. **Set Up WordPress**
   ```bash
   # Create a local WordPress installation
   # Or use tools like Local by Flywheel, XAMPP, etc.
   ```

4. **Install Required Plugins**
   - Install and activate Dokan (Free or Pro)
   - Install and activate WooCommerce
   - Activate this plugin in development mode

5. **Configure Development Environment**
   ```bash
   # Copy development configuration
   cp wp-config-sample.php wp-config.php
   
   # Enable debug mode
   define('WP_DEBUG', true);
   define('WP_DEBUG_LOG', true);
   define('WP_DEBUG_DISPLAY', false);
   ```

### Development Workflow

1. **Create a Feature Branch**
   ```bash
   git checkout -b feature/your-feature-name
   ```

2. **Make Your Changes**
   - Follow WordPress coding standards
   - Write tests for new functionality
   - Update documentation as needed

3. **Test Your Changes**
   ```bash
   # Run PHP tests
   vendor/bin/phpunit
   
   # Run WordPress coding standards
   vendor/bin/phpcs --standard=WordPress
   
   # Test in different environments
   ```

4. **Submit a Pull Request**
   - Create a detailed description
   - Include screenshots if UI changes
   - Reference related issues

## 📝 Coding Standards

### PHP Standards

We follow WordPress coding standards:

```bash
# Install PHP_CodeSniffer with WordPress standards
composer require --dev squizlabs/php_codesniffer
composer require --dev wp-coding-standards/wpcs

# Set WordPress as the default standard
vendor/bin/phpcs --config-set default_standard WordPress
```

### Code Style Guidelines

1. **File Structure**
   ```php
   <?php
   /**
    * File description
    *
    * @package Dokan_Vendor_Domain_Mapper
    * @version 1.0.0
    */
   
   // Prevent direct access
   if ( ! defined( 'ABSPATH' ) ) {
       exit;
   }
   
   // Your code here
   ```

2. **Class Structure**
   ```php
   /**
    * Class description
    *
    * @since 1.0.0
    */
   class Dokan_Vendor_Domain_Mapper_Class {
   
       /**
        * Constructor
        *
        * @since 1.0.0
        */
       public function __construct() {
           // Constructor code
       }
   
       /**
        * Method description
        *
        * @since 1.0.0
        * @param string $param Parameter description.
        * @return bool Return description.
        */
       public function method_name( $param ) {
           // Method code
           return true;
       }
   }
   ```

3. **Database Queries**
   ```php
   // Use WordPress database functions
   global $wpdb;
   
   $results = $wpdb->get_results(
       $wpdb->prepare(
           "SELECT * FROM {$wpdb->prefix}dokan_domain_mappings WHERE vendor_id = %d",
           $vendor_id
       )
   );
   ```

4. **Security**
   ```php
   // Always sanitize inputs
   $domain = sanitize_text_field( $_POST['domain'] );
   
   // Always escape outputs
   echo esc_html( $domain );
   
   // Use nonces for forms
   wp_nonce_field( 'dokan_add_domain', 'dokan_domain_nonce' );
   
   // Verify nonces
   if ( ! wp_verify_nonce( $_POST['dokan_domain_nonce'], 'dokan_add_domain' ) ) {
       wp_die( 'Security check failed' );
   }
   ```

### JavaScript Standards

1. **File Structure**
   ```javascript
   /**
    * File description
    *
    * @package Dokan_Vendor_Domain_Mapper
    * @version 1.0.0
    */
   
   (function($) {
       'use strict';
   
       // Your code here
   
   })(jQuery);
   ```

2. **Code Style**
   ```javascript
   // Use camelCase for variables and functions
   var domainName = 'example.com';
   
   function verifyDomain() {
       // Function code
   }
   
   // Use descriptive variable names
   var domainMappingTable = $('#domain-mapping-table');
   ```

### CSS Standards

1. **File Structure**
   ```css
   /**
    * File description
    *
    * @package Dokan_Vendor_Domain_Mapper
    * @version 1.0.0
    */
   
   /* Your styles here */
   ```

2. **Naming Conventions**
   ```css
   /* Use BEM methodology */
   .dokan-domain-mapper {}
   .dokan-domain-mapper__header {}
   .dokan-domain-mapper__header--active {}
   ```

## 🧪 Testing

### Writing Tests

1. **Unit Tests**
   ```php
   /**
    * Test class description
    *
    * @package Dokan_Vendor_Domain_Mapper
    * @since 1.0.0
    */
   class Test_Dokan_Vendor_Domain_Mapper extends WP_UnitTestCase {
   
       /**
        * Test method description
        *
        * @since 1.0.0
        */
       public function test_method_name() {
           // Test code
           $this->assertTrue( true );
       }
   }
   ```

2. **Integration Tests**
   ```php
   /**
    * Integration test for domain mapping
    */
   class Test_Domain_Mapping_Integration extends WP_UnitTestCase {
   
       public function test_domain_verification_workflow() {
           // Test complete workflow
       }
   }
   ```

### Running Tests

```bash
# Run all tests
vendor/bin/phpunit

# Run specific test file
vendor/bin/phpunit tests/test-domain-mapper.php

# Run with coverage
vendor/bin/phpunit --coverage-html coverage/
```

## 📚 Documentation

### Code Documentation

1. **Inline Comments**
   ```php
   // Explain complex logic
   $domain_parts = explode( '.', $domain );
   $is_subdomain = count( $domain_parts ) > 2;
   ```

2. **Function Documentation**
   ```php
   /**
    * Verify domain ownership via DNS TXT record
    *
    * @since 1.0.0
    * @param string $domain Domain to verify.
    * @param string $token Verification token.
    * @return bool True if verified, false otherwise.
    */
   public function verify_domain( $domain, $token ) {
       // Function implementation
   }
   ```

### User Documentation

1. **README Updates**: Update main README for new features
2. **Inline Help**: Add help text in admin interfaces
3. **Video Tutorials**: Create video guides for complex features
4. **FAQ Updates**: Add common questions and answers

## 🔄 Pull Request Process

### Before Submitting

1. **Code Review**
   - Self-review your changes
   - Ensure all tests pass
   - Check for coding standards compliance

2. **Documentation**
   - Update relevant documentation
   - Add inline comments for complex logic
   - Update changelog if needed

3. **Testing**
   - Test in multiple environments
   - Test with different WordPress versions
   - Test with different Dokan versions

### Pull Request Template

```markdown
## Description
Brief description of changes

## Type of Change
- [ ] Bug fix
- [ ] New feature
- [ ] Documentation update
- [ ] Code refactoring
- [ ] Performance improvement

## Testing
- [ ] Unit tests pass
- [ ] Integration tests pass
- [ ] Manual testing completed
- [ ] Tested in multiple environments

## Screenshots
Add screenshots for UI changes

## Checklist
- [ ] Code follows WordPress standards
- [ ] Documentation updated
- [ ] Tests added/updated
- [ ] No breaking changes
- [ ] Backward compatibility maintained
```

## 🐛 Bug Reports

### Bug Report Template

```markdown
## Bug Description
Clear description of the bug

## Steps to Reproduce
1. Step 1
2. Step 2
3. Step 3

## Expected Behavior
What should happen

## Actual Behavior
What actually happens

## Environment
- WordPress Version: X.X.X
- Dokan Version: X.X.X
- WooCommerce Version: X.X.X
- PHP Version: X.X.X
- Server: Apache/Nginx
- Browser: Chrome/Firefox/Safari

## Additional Information
Screenshots, error logs, etc.
```

## 💡 Feature Requests

### Feature Request Template

```markdown
## Feature Description
Clear description of the feature

## Use Case
Why this feature is needed

## Proposed Solution
How the feature should work

## Alternative Solutions
Other ways to solve the problem

## Additional Information
Mockups, examples, etc.
```

## 📋 Issue Labels

We use the following labels to categorize issues:

- **bug**: Something isn't working
- **enhancement**: New feature or request
- **documentation**: Improvements to documentation
- **good first issue**: Good for newcomers
- **help wanted**: Extra attention is needed
- **invalid**: Something that won't be worked on
- **question**: Further information is requested
- **wontfix**: This will not be worked on

## 🏷️ Version Control

### Commit Messages

Use conventional commit format:

```
type(scope): description

feat(domain): add support for wildcard domains
fix(ssl): resolve certificate renewal issue
docs(readme): update installation instructions
test(api): add tests for domain verification
```

### Branch Naming

- `feature/feature-name`: New features
- `bugfix/bug-description`: Bug fixes
- `hotfix/critical-fix`: Critical fixes
- `docs/documentation-update`: Documentation updates

## 🤝 Community Guidelines

### Code of Conduct

1. **Be Respectful**: Treat everyone with respect
2. **Be Helpful**: Help others learn and grow
3. **Be Patient**: Everyone learns at their own pace
4. **Be Constructive**: Provide helpful feedback
5. **Be Inclusive**: Welcome diverse perspectives

### Communication

- **GitHub Issues**: For bug reports and feature requests
- **GitHub Discussions**: For questions and general discussion
- **Email**: For sensitive or private matters
- **Slack/Discord**: For real-time collaboration

## 🎯 Contribution Areas

### High Priority

- **Security**: Security improvements and vulnerability fixes
- **Performance**: Performance optimizations
- **Compatibility**: WordPress/Dokan/WooCommerce compatibility
- **Accessibility**: Accessibility improvements

### Medium Priority

- **Features**: New functionality
- **Documentation**: Documentation improvements
- **Testing**: Test coverage improvements
- **Code Quality**: Code refactoring and improvements

### Low Priority

- **Cosmetic**: UI/UX improvements
- **Optimization**: Minor optimizations
- **Documentation**: Minor documentation updates

## 🏆 Recognition

### Contributors

We recognize contributors in several ways:

1. **Contributors List**: Added to README contributors section
2. **Release Notes**: Mentioned in release announcements
3. **Hall of Fame**: Special recognition for significant contributions
4. **Swag**: Physical items for major contributors

### Contribution Levels

- **Bronze**: 1-5 contributions
- **Silver**: 6-20 contributions
- **Gold**: 21-50 contributions
- **Platinum**: 50+ contributions

## 📞 Getting Help

### Resources

- **Documentation**: [docs.your-site.com](https://docs.your-site.com)
- **GitHub Issues**: [Report Issues](https://github.com/your-username/dokan-vendor-domain-mapper/issues)
- **GitHub Discussions**: [Community Forum](https://github.com/your-username/dokan-vendor-domain-mapper/discussions)
- **Email**: support@your-company.com

### Mentorship

- **New Contributors**: We provide mentorship for new contributors
- **Code Reviews**: Experienced contributors review your code
- **Pair Programming**: Available for complex features
- **Office Hours**: Regular Q&A sessions

---

**Thank you for contributing to Dokan Vendor Domain Mapper!** 🎉

Your contributions help make this plugin better for everyone in the WordPress community. 