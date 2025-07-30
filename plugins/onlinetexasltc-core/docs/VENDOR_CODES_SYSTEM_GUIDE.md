# Vendor Codes System - Complete Setup Guide

## Overview

This guide explains how to set up and use the Vendor Codes System, which allows Dokan vendors to generate and manage registration codes for their customers using Uncanny LearnDash Codes and Automator.

## Table of Contents

1. [Prerequisites](#prerequisites)
2. [Step 1: Creating Uncanny LearnDash Codes](#step-1-creating-uncanny-learndash-codes)
3. [Step 2: Setting Up Uncanny Automator Recipe](#step-2-setting-up-uncanny-automator-recipe)
4. [Step 3: Creating Admin Product](#step-3-creating-admin-product)
5. [Step 4: Vendor Workflow](#step-4-vendor-workflow)
6. [Step 5: Customer Code Redemption](#step-5-customer-code-redemption)
7. [Troubleshooting](#troubleshooting)

---

## Prerequisites

Before starting, ensure you have the following plugins installed and activated:

- ✅ **Dokan Lite/Pro** - Multi-vendor marketplace
- ✅ **Uncanny LearnDash Codes** - Code generation and management
- ✅ **Uncanny Automator** - Automation workflows
- ✅ **LearnDash LMS** - Course management
- ✅ **WooCommerce** - E-commerce platform
- ✅ **Online Texas Core** - Custom vendor codes functionality

---

## Step 1: Creating Uncanny LearnDash Codes

### 1.1 Access Code Groups
1. Go to **WordPress Admin → Uncanny Codes → Code Groups**
2. Click **"Add New Code Group"**

### 1.2 Configure Code Group Settings
Fill in the following details:

**Basic Settings:**
- **Group Name**: `Admin Course Registration Codes` (or your preferred name)
- **Coupon For**: Select `Automator`
- **Coupon Amount**: Enter the number of codes to generate (e.g., 100)
- **Coupon Max Usage**: `1` (each code can be used once)
- **Coupon Prefix**: Optional (e.g., `ADMIN`)
- **Coupon Suffix**: Optional
- **Coupon Dash**: Leave empty

**Code Generation:**
- **Character Type**: Select `Numbers` and `Uppercase Letters`
- **Expiry Date**: Set if needed (optional)
- **Expiry Time**: Set if needed (optional)

**Advanced Settings:**
- **Coupon Courses**: Leave empty (will be set via Automator)
- **Coupon Groups**: Leave empty (will be set via Automator)

### 1.3 Generate Codes
1. Click **"Generate Codes"**
2. Wait for the codes to be generated
3. Verify codes appear in the list

---

## Step 2: Setting Up Uncanny Automator Recipe

### 2.1 Create New Recipe
1. Go to **WordPress Admin → Automator → Recipes**
2. Click **"Add New Recipe"**

### 2.2 Configure Recipe Settings
**Recipe Information:**
- **Recipe Name**: `Course Registration via Code`
- **Recipe Type**: `User`
- **Recipe Status**: `Active`

### 2.3 Add Trigger
1. Click **"Add Trigger"**
2. Search for and select **"A user redeems a code"**
3. Configure trigger settings:
   - **Code Group**: Select the code group you created in Step 1
   - **Trigger**: `A user redeems a code from this group`

### 2.4 Add Actions
1. Click **"Add Action"**
2. Add the following actions in sequence:

**Action 1: Enroll User in Course**
- Search for **"Enroll user in course"**
- Select the course you want users to be enrolled in
- Set enrollment status as needed

**Action 2: Add User to Group (Optional)**
- Search for **"Add user to group"**
- Select the group if you want users added to a specific group

**Action 3: Send Email Notification (Optional)**
- Search for **"Send email"**
- Configure welcome email or course access notification

### 2.5 Save Recipe
1. Click **"Save Recipe"**
2. Ensure recipe status is **"Active"**

---

## Step 3: Creating Admin Product

### 3.1 Create New Product
1. Go to **WordPress Admin → Products → Add New**
2. Fill in basic product details:
   - **Product Name**: `Course Registration Code`
   - **Description**: Add product description
   - **Price**: Set your desired price

### 3.2 Set Product Type
1. In the **Product Data** section, click the dropdown
2. Select **"Automator Codes"** (this is a custom product type)
3. The product data panel will change to show code-specific options

### 3.3 Configure Code Settings
**Code Group Assignment:**
- **Code Group**: Select the code group you created in Step 1
- This links the product to the specific batch of codes

**Product Settings:**
- **Regular Price**: Set the price for the code
- **Sale Price**: Optional discount price
- **Stock**: Set to `In Stock` or manage inventory as needed

### 3.4 Set Product Availability for Vendors
1. Scroll down to **"Product Data"** section
2. Look for **"Vendor Availability"** settings:
   - **Available for Vendors**: Select `Yes`
   - **Restricted Vendors**: Leave empty (or select specific vendors)

### 3.5 Publish Product
1. Set product status to **"Published"**
2. Click **"Publish"**

---

## Step 4: Vendor Workflow

### 4.1 Vendor Access
Vendors can access the system through their Dokan dashboard:
1. **Vendor Login**: Vendors log into their Dokan dashboard
2. **Source Products**: Navigate to **"Source Products"** in the dashboard menu

### 4.2 Duplicating Admin Products
1. **Browse Products**: Vendors see all available admin products
2. **Filter Options**: Use filters to find specific products:
   - **Product Type**: Filter by "Codes" or "Course" products
   - **Course Filter**: Search for products linked to specific courses
3. **Duplicate Product**: Click **"Clone & Generate Codes"** for automator_codes products

### 4.3 Automatic Code Generation
When a vendor duplicates an automator_codes product:
- ✅ **Product is automatically published** (no manual publishing needed)
- ✅ **Vendor-specific codes are generated** automatically
- ✅ **Codes are linked to the original admin recipe**
- ✅ **Vendor gets immediate access** to their codes

### 4.4 Managing Vendor Codes
Vendors can manage their codes through:
1. **Codes Dashboard**: Navigate to **"Registration Codes"** in vendor dashboard
2. **Generate More Codes**: Create additional codes for their duplicated products
3. **View Code Status**: See which codes have been redeemed and by whom

---

## Step 5: Customer Code Redemption

### 5.1 Code Distribution
Vendors can distribute codes to customers through:
- **Direct sharing**: Send codes via email, messaging, etc.
- **Website integration**: Use shortcodes on vendor pages
- **Custom forms**: Integrate with contact forms

### 5.2 Customer Redemption Process
1. **Customer receives code** from vendor
2. **Customer visits redemption page** (using shortcode `[vendor_code_redeem]`)
3. **Customer enters code** in the redemption form
4. **System validates code** and checks vendor association
5. **Customer is enrolled** in the course automatically
6. **Original admin recipe triggers** (enrollment, notifications, etc.)

### 5.3 What Happens During Redemption
- ✅ **Code is marked as redeemed**
- ✅ **Customer is enrolled in the course**
- ✅ **Admin's original recipe executes** (emails, group assignments, etc.)
- ✅ **Vendor can track redemption** in their dashboard
- ✅ **Commission is handled** (if applicable)

---

## Step 6: Admin Management

### 6.1 Vendor Code Requests
Admins can manage vendor code generation requests:
1. Go to **WordPress Admin → WooCommerce → Vendor Codes**
2. View pending requests from vendors
3. **Approve** or **Reject** requests as needed

### 6.2 Monitoring System
- **Track code usage** across all vendors
- **Monitor course enrollments** from vendor codes
- **View vendor performance** and code generation activity

---

## Troubleshooting

### Common Issues and Solutions

**Issue: Codes not generating for vendors**
- **Solution**: Check if the admin product is set to "Available for Vendors: Yes"
- **Solution**: Verify the vendor has proper permissions

**Issue: Course not enrolling after code redemption**
- **Solution**: Check if the Uncanny Automator recipe is active
- **Solution**: Verify the code group is properly linked to the product

**Issue: Vendor can't see products in Source Products**
- **Solution**: Ensure products have linked courses or are automator_codes type
- **Solution**: Check if vendor has already duplicated the product

**Issue: AJAX search not working**
- **Solution**: Clear browser cache and WordPress cache
- **Solution**: Check browser console for JavaScript errors

### Support Contacts
- **Technical Issues**: Contact your development team
- **Plugin Issues**: Check plugin documentation or support forums
- **System Configuration**: Refer to this guide or contact system administrator

---

## Best Practices

### For Admins
1. **Test recipes thoroughly** before making them available to vendors
2. **Monitor code usage** regularly to prevent abuse
3. **Set appropriate limits** for vendor code generation
4. **Use descriptive product names** for better vendor identification

### For Vendors
1. **Keep track of code distribution** to customers
2. **Monitor redemption rates** to optimize marketing
3. **Use course filters** to find relevant products quickly
4. **Generate codes in batches** to manage inventory effectively

### For Customers
1. **Use codes promptly** to avoid expiration
2. **Contact vendor** if codes don't work
3. **Check email** for course access instructions after redemption

---

## System Requirements

### Minimum Requirements
- **WordPress**: 5.0+
- **PHP**: 7.4+
- **MySQL**: 5.6+
- **Memory Limit**: 256MB+
- **Upload Limit**: 64MB+

### Recommended Requirements
- **WordPress**: 6.0+
- **PHP**: 8.0+
- **MySQL**: 8.0+
- **Memory Limit**: 512MB+
- **Upload Limit**: 128MB+

---

*This documentation is maintained by the development team. For updates or questions, please contact your system administrator.* 