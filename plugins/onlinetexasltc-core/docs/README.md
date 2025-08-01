# Online Texas Core Documentation

## Overview

Online Texas Core integrates WooCommerce, Dokan, and LearnDash to create vendor products and manage course enrollments through registration codes.

## Features

- **Automatic Product Creation**: Creates vendor products when admin creates products with course links
- **Vendor Codes System**: Vendors can generate registration codes for customers
- **Admin Permissions**: Control code generation limits and approval workflows
- **Course Integration**: Automatic LearnDash group creation and enrollment

## Installation

1. Install and activate required plugins:
   - WooCommerce 5.0+
   - Dokan 3.0+
   - LearnDash 3.0+
   - Uncanny LearnDash Codes
   - Uncanny Automator

2. Upload and activate Online Texas Core plugin

3. Configure settings at **WordPress Admin → Online Texas Core**

## Quick Start Guide

### For Administrators

1. **Create a Product with Courses**
   - Add new WooCommerce product
   - Link to LearnDash courses
   - Set vendor availability to "Yes"

2. **Set Up Vendor Codes** (if using)
   - Create Uncanny Code Group
   - Create Automator Recipe
   - Create product with type "Automator Codes"

3. **Configure Permissions**
   - Edit product → Vendor Code Generation Permissions
   - Set max codes per vendor
   - Enable approval if needed

### For Vendors

1. **Access Vendor Dashboard**
   - Login to your vendor account
   - Navigate to Dokan dashboard

2. **Generate Registration Codes**
   - Go to **Codes** in vendor menu
   - Select product
   - Enter number of codes (1-20)
   - Click Generate

3. **Share Codes with Customers**
   - Provide code to customer
   - Customer redeems at `[vendor_code_redeem]` page
   - Automatic course enrollment

## Troubleshooting

### Common Issues

**Codes not generating**
- Check vendor has permission
- Verify product is "Automator Codes" type
- Ensure code limits not exceeded

**Courses not enrolling**
- Verify Automator recipe is active
- Check code group is properly linked
- Ensure course is published

**Products not syncing**
- Enable auto-creation in settings
- Check vendor role permissions
- Review debug logs if enabled

## Support

For assistance, contact:
- Plugin Support: support@wbcomdesigns.com
- Documentation: This file
- Version: 1.2.0