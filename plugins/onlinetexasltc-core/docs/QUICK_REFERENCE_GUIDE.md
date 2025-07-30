# Vendor Codes System - Quick Reference Guide

## Quick Start Checklist

### For Admins
- [ ] Create Uncanny Code Group
- [ ] Set up Automator Recipe
- [ ] Create Admin Product (Automator Codes type)
- [ ] Set product availability for vendors
- [ ] Test the complete workflow

### For Vendors
- [ ] Access Source Products page
- [ ] Duplicate admin products
- [ ] Generate additional codes if needed
- [ ] Distribute codes to customers
- [ ] Monitor code redemptions

---

## Common Tasks

### Creating a New Code Product (Admin)

1. **Create Code Group**
   ```
   Admin → Uncanny Codes → Code Groups → Add New
   - Group Name: [Your Product Name]
   - Coupon For: Automator
   - Coupon Amount: [Number of codes]
   - Generate Codes
   ```

2. **Create Automator Recipe**
   ```
   Admin → Automator → Recipes → Add New
   - Trigger: "A user redeems a code"
   - Action: "Enroll user in course"
   - Save as Active
   ```

3. **Create Product**
   ```
   Admin → Products → Add New
   - Product Type: Automator Codes
   - Link to Code Group
   - Set Vendor Availability: Yes
   - Publish
   ```

### Vendor Code Generation

1. **Access Source Products**
   ```
   Vendor Dashboard → Source Products
   ```

2. **Filter Products**
   ```
   - Use Product Type filter (Codes/Course)
   - Use Course filter to find specific courses
   - Use AJAX search for instant results
   ```

3. **Duplicate Product**
   ```
   - Click "Clone & Generate Codes"
   - Product auto-publishes
   - Codes are generated automatically
   ```

4. **Generate More Codes**
   ```
   Vendor Dashboard → Registration Codes
   - Select product
   - Enter number of codes (1-20)
   - Set expiry date (optional)
   - Generate
   ```

### Customer Code Redemption

1. **Setup Redemption Page**
   ```
   Add shortcode to any page:
   [vendor_code_redeem]
   ```

2. **Customer Process**
   ```
   - Customer enters code
   - System validates code
   - Customer is enrolled automatically
   - Admin recipe triggers
   ```

---

## Shortcuts & Tips

### Admin Shortcuts
- **Quick Code Group**: Use "Generate Codes" button for instant code creation
- **Bulk Product Setup**: Create multiple products with similar settings
- **Recipe Templates**: Save common recipe configurations as templates

### Vendor Shortcuts
- **Course Search**: Use AJAX search to find products instantly
- **Bulk Code Generation**: Generate up to 20 codes at once
- **Code Management**: View all codes and redemptions in one place

### System Features
- **Auto-Publishing**: Automator codes products publish automatically
- **Cross-Page Search**: Find products regardless of pagination
- **Real-Time Updates**: AJAX-powered interface for better UX

---

## Troubleshooting Quick Fixes

### Codes Not Generating
```
Check: Product → Vendor Availability → Yes
Check: Vendor permissions
Check: Code group is linked to product
```

### Course Not Enrolling
```
Check: Automator recipe is Active
Check: Code group is properly linked
Check: Course exists and is published
```

### Vendor Can't See Products
```
Check: Product has linked courses or is automator_codes type
Check: Vendor hasn't already duplicated the product
Check: Product is published and available for vendors
```

### AJAX Search Issues
```
Clear: Browser cache
Clear: WordPress cache
Check: Browser console for errors
```

---

## Important Notes

### Security
- ✅ All AJAX requests include nonce verification
- ✅ Vendor-only access is enforced
- ✅ Code redemption is validated
- ✅ Admin approval system for code generation

### Performance
- ✅ Pagination prevents loading too many records
- ✅ AJAX search provides instant results
- ✅ Efficient database queries
- ✅ Optimized code generation

### Integration
- ✅ Works with existing Dokan setup
- ✅ Compatible with Uncanny LearnDash Codes
- ✅ Integrates with Uncanny Automator
- ✅ Supports LearnDash courses and groups

---

## Contact & Support

### For Technical Issues
- **Development Team**: [Your contact info]
- **System Admin**: [Admin contact info]
- **Documentation**: Refer to main guide

### For Plugin Issues
- **Dokan Support**: [Dokan support URL]
- **Uncanny Support**: [Uncanny support URL]
- **LearnDash Support**: [LearnDash support URL]

---

*Last Updated: [Current Date]*
*Version: 1.0* 