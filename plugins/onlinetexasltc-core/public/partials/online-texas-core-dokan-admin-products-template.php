<?php

/**
 * Template for displaying admin products in vendor dashboard
 *
 * @link       https://wbcomdesigns.com/
 * @since      1.0.0
 *
 * @package    Online_Texas_Core
 * @subpackage Online_Texas_Core/public/partials
 */

// Don't load this template directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Check if user has admin role
 */
function otc_user_has_admin_role($user_id = null)
{
    if (!$user_id) {
        $user_id = get_current_user_id();
    }

    $user = get_user_by('ID', $user_id);
    if (!$user) {
        return false;
    }

    return in_array('administrator', $user->roles);
}

/**
 * Check if user should see vendor features
 */
function otc_should_show_vendor_features()
{
    // Must be logged in
    if (!is_user_logged_in()) {
        return false;
    }

    // Must NOT be an administrator
    if (otc_user_has_admin_role()) {
        return false;
    }

    // Must be a Dokan seller
    if (!function_exists('dokan_is_user_seller') || !dokan_is_user_seller(get_current_user_id())) {
        return false;
    }

    return true;
}

// Security check using role-based method
if (!otc_should_show_vendor_features()) {
    wp_die(__('Access denied - This feature is for vendors only, not administrators.', 'online-texas-core'));
}

$current_user_id = get_current_user_id();

// Pagination handling
$paged = 1;
if (isset($_GET['paged']) && intval($_GET['paged']) > 0) {
    $paged = intval($_GET['paged']);
}

$per_page = 20;

// Get all admin products (merged list)
try {
    $all_products = get_all_admin_products_for_vendor($paged, $per_page);
} catch (Exception $e) {
    $all_products = array();
}

// Get current URL for pagination
$current_url = dokan_get_navigation_url('source');

// Ensure required template functions are available
if (! function_exists('esc_html')) {
    function esc_html($s)
    {
        return htmlspecialchars($s, ENT_QUOTES);
    }
}
if (! function_exists('esc_attr')) {
    function esc_attr($s)
    {
        return htmlspecialchars($s, ENT_QUOTES);
    }
}
if (! function_exists('get_the_post_thumbnail_url')) {
    function get_the_post_thumbnail_url($id, $size = 'thumbnail')
    {
        return '';
    }
}
if (! class_exists('WP_Query')) {
    class WP_Query {}
}

?>

<div class="dokan-dashboard-wrap">
    <?php
    /**
     *  dokan_dashboard_content_before hook
     *
     *  @hooked get_dashboard_side_navigation
     *
     *  @since 2.4
     */
    do_action('dokan_dashboard_content_before');
    ?>

    <div class="dokan-dashboard-content">
        <?php
        /**
         *  dokan_dashboard_content_before hook
         *
         *  @hooked show_seller_dashboard_notice
         *
         *  @since 2.4
         */
        do_action('dokan_help_content_inside_before');
        ?>

        <div class="dokan-admin-products-wrap">
            <div class="dokan-dashboard-header">
                <h1 class="entry-title">
                    <i class="fas fa-shopping-bag"></i>
                    <?php esc_html_e('Source Products', 'online-texas-core'); ?>
                </h1>
            </div>
            <div class="online-texas-dashboard-content">
                <div class="admin-products-container">
                    <!-- Welcome message -->
                    <div class="dokan-alert dokan-alert-info" style="margin-bottom: 20px; display: flex; align-items: center;">
                        <span class="dashicons dashicons-info" style="font-size: 22px; margin-right: 10px;"></span>
                        <span><?php esc_html_e('Browse and duplicate admin products to add them to your store. Regular products will be duplicated as drafts, while codes products will generate new codes for you.', 'online-texas-core'); ?></span>
                    </div>
                    
                    <?php if (!empty($all_products)) : ?>
                        <!-- Filter Controls -->
                        <div class="dokan-filter-controls" style="margin-bottom: 20px; padding: 15px; background: #f8f9fa; border-radius: 5px; border: 1px solid #e9ecef;">
                            <div style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap;">
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <label for="product-type-filter" style="font-weight: 600; margin: 0; color: #333;">
                                        <i class="fas fa-filter"></i> <?php esc_html_e('Filter by Type:', 'online-texas-core'); ?>
                                    </label>
                                    <select id="product-type-filter" style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; background: white; min-width: 150px;">
                                        <option value="all"><?php esc_html_e('All Products', 'online-texas-core'); ?></option>
                                        <option value="codes"><?php esc_html_e('Codes Products', 'online-texas-core'); ?></option>
                                        <option value="course"><?php esc_html_e('Course Products', 'online-texas-core'); ?></option>
                                    </select>
                                </div>
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <label for="course-filter" style="font-weight: 600; margin: 0; color: #333;">
                                        <i class="fas fa-graduation-cap"></i> <?php esc_html_e('Search by Course:', 'online-texas-core'); ?>
                                    </label>
                                    <select id="course-filter" style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; background: white; min-width: 200px;">
                                        <option value="all"><?php esc_html_e('All Courses', 'online-texas-core'); ?></option>
                                        <?php
                                        // Get ALL courses from the system for the filter (not just from current page)
                                        $all_courses = get_posts(array(
                                            'post_type' => 'sfwd-courses',
                                            'post_status' => 'publish',
                                            'numberposts' => -1,
                                            'orderby' => 'title',
                                            'order' => 'ASC'
                                        ));
                                        
                                        foreach ($all_courses as $course) {
                                            echo '<option value="' . esc_attr($course->ID) . '">' . esc_html($course->post_title) . '</option>';
                                        }
                                        ?>
                                    </select>
                                    <div id="course-search-loading" style="display: none; margin-left: 10px;">
                                        <i class="fas fa-spinner fa-spin"></i> <?php esc_html_e('Searching...', 'online-texas-core'); ?>
                                    </div>
                                </div>
                                <button id="clear-filters" class="dokan-btn dokan-btn-sm" style="background: #6c757d; color: white; border: none; padding: 8px 12px; border-radius: 4px; cursor: pointer;">
                                    <i class="fas fa-times"></i> <?php esc_html_e('Clear Filters', 'online-texas-core'); ?>
                                </button>
                            </div>
                            <div id="filter-summary" style="margin-top: 10px; font-size: 12px; color: #666; display: none;">
                                <span id="filter-text"></span> | <span id="product-count"></span>
                            </div>
                        </div>

                        <div class="dokan-dashboard-product-listing">
                            <table class="dokan-table admin-products-table">
                                <thead>
                                    <tr>
                                        <th class="product-thumb"><?php esc_html_e('Image', 'online-texas-core'); ?></th>
                                        <th class="product-name"><?php esc_html_e('Product Name', 'online-texas-core'); ?></th>
                                        <th class="product-type"><?php esc_html_e('Type', 'online-texas-core'); ?></th>
                                        <th class="product-courses"><?php esc_html_e('Linked Courses', 'online-texas-core'); ?></th>
                                        <th class="product-price"><?php esc_html_e('Price', 'online-texas-core'); ?></th>
                                        <th class="product-action"><?php esc_html_e('Action', 'online-texas-core'); ?></th>
                                    </tr>
                                </thead>
                                <tbody class='dokan-admin-products-listing'>
                                    <?php foreach ($all_products as $product_data) : ?>
                                        <tr class="product-row" 
                                            data-product-type="<?php echo esc_attr($product_data['is_automator_codes'] ? 'codes' : 'course'); ?>"
                                            data-courses="<?php echo esc_attr(implode(',', array_column($product_data['courses'] ?? array(), 'id'))); ?>">
                                            <td class="product-thumb">
                                                <?php if ($product_data['thumbnail']) : ?>
                                                    <img src="<?php echo esc_url($product_data['thumbnail']); ?>" alt="<?php echo esc_attr($product_data['name']); ?>" class="product-thumbnail" style="width: 50px; height: 50px; object-fit: cover; border-radius: 3px;">
                                                <?php else : ?>
                                                    <span class="dokan-product-placeholder" style="display: inline-block; width: 50px; height: 50px; background: #f0f0f0; text-align: center; line-height: 50px; border-radius: 3px;">
                                                        <i class="fas fa-image"></i>
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="product-name">
                                                <strong><?php echo esc_html($product_data['name']); ?></strong>
                                                <span class="dokan-label dokan-label-default" style="font-size:11px; margin-left: 4px;"><?php echo esc_html(ucfirst($product_data['status'])); ?></span>
                                                <?php if ($product_data['short_description']) : ?>
                                                    <div class="product-excerpt" style="font-size: 12px; color: #666; margin-top: 5px;">
                                                        <?php echo wp_trim_words($product_data['short_description'], 10); ?>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td class="product-type">
                                                <?php if ($product_data['is_automator_codes']) : ?>
                                                    <span class="dokan-label dokan-label-warning" style="font-size:11px;">
                                                        <i class="fas fa-tags"></i> <?php esc_html_e('Codes', 'online-texas-core'); ?>
                                                    </span>
                                                <?php else : ?>
                                                    <span class="dokan-label dokan-label-info" style="font-size:11px;">
                                                        <i class="fas fa-graduation-cap"></i> <?php esc_html_e('Course', 'online-texas-core'); ?>
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="product-courses">
                                                <?php if (!empty($product_data['courses']) && is_array($product_data['courses'])) : ?>
                                                    <div style="font-size: 11px; color: #666;">
                                                        <?php 
                                                        $course_names = array_column($product_data['courses'], 'name');
                                                        echo esc_html(implode(', ', array_slice($course_names, 0, 2)));
                                                        if (count($course_names) > 2) {
                                                            echo ' <span style="color: #999;">(+' . (count($course_names) - 2) . ' more)</span>';
                                                        }
                                                        ?>
                                                    </div>
                                                <?php else : ?>
                                                    <span style="font-size: 11px; color: #999;"><?php esc_html_e('No courses linked', 'online-texas-core'); ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="product-price">
                                                <?php if ($product_data['sale_price']) : ?>
                                                    <del style="color: #999; font-size: 12px;"><?php echo wc_price($product_data['price']); ?></del><br>
                                                    <span style="color: #dc3545; font-weight: bold;"><?php echo wc_price($product_data['sale_price']); ?></span>
                                                <?php else : ?>
                                                    <span style="font-weight: bold;"><?php echo wc_price($product_data['price']); ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="product-action">
                                                <?php if (!$product_data['already_duplicated']) : ?>
                                                    <button class="dokan-btn dokan-btn-sm dokan-btn-theme duplicate-btn"
                                                        data-product-id="<?php echo esc_attr($product_data['id']); ?>"
                                                        data-product-name="<?php echo esc_attr($product_data['name']); ?>"
                                                        data-product-type="<?php echo esc_attr($product_data['is_automator_codes'] ? 'codes' : 'regular'); ?>"
                                                        aria-label="<?php echo esc_attr($product_data['is_automator_codes'] ? 'Clone and generate codes for' : 'Duplicate'); ?> <?php echo esc_attr($product_data['name']); ?>"
                                                        title="<?php echo esc_attr($product_data['is_automator_codes'] ? 'Clone & Generate Codes' : 'Duplicate'); ?>"
                                                        style="background: #007cba; color: white; border: none; padding: 8px 12px; border-radius: 3px; cursor: pointer; font-size: 12px;">
                                                        <i class="fas fa-copy"></i>
                                                        <?php echo esc_html($product_data['is_automator_codes'] ? __('Clone & Generate Codes', 'online-texas-core') : __('Duplicate', 'online-texas-core')); ?>
                                                    </button>
                                                <?php else : ?>
                                                    <span class="dokan-text-muted" style="color: #28a745; font-size: 12px;">
                                                        <i class="fas fa-check"></i> <?php esc_html_e('Already Duplicated', 'online-texas-core'); ?>
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <?php
                        // Get total products count for pagination
                        $total_products = get_all_admin_products_for_vendor(1, 1000); // Get all for count
                        $total_count = is_array($total_products) ? count($total_products) : 0;
                        $total_pages = ceil($total_count / $per_page);
                        
                        if ($total_pages > 1) : ?>
                            <div class="dokan-pagination-container" style="margin-top: 20px; text-align: center;">
                                <div class="dokan-pagination">
                                    <?php if ($paged > 1) : ?>
                                        <a href="<?php echo esc_url(add_query_arg('paged', $paged - 1, $current_url)); ?>" class="dokan-btn dokan-btn-sm" style="margin-right: 5px;">
                                            <i class="fas fa-chevron-left"></i> <?php esc_html_e('Previous', 'online-texas-core'); ?>
                                        </a>
                                    <?php endif; ?>
                                    
                                    <span class="dokan-pagination-info" style="margin: 0 15px; color: #666;">
                                        <?php 
                                        $start = (($paged - 1) * $per_page) + 1;
                                        $end = min($paged * $per_page, $total_count);
                                        printf(esc_html__('Showing %d-%d of %d products', 'online-texas-core'), $start, $end, $total_count);
                                        ?>
                                    </span>
                                    
                                    <?php if ($paged < $total_pages) : ?>
                                        <a href="<?php echo esc_url(add_query_arg('paged', $paged + 1, $current_url)); ?>" class="dokan-btn dokan-btn-sm" style="margin-left: 5px;">
                                            <?php esc_html_e('Next', 'online-texas-core'); ?> <i class="fas fa-chevron-right"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Page numbers -->
                                <?php if ($total_pages <= 10) : ?>
                                    <div class="dokan-page-numbers" style="margin-top: 10px;">
                                        <?php for ($i = 1; $i <= $total_pages; $i++) : ?>
                                            <?php if ($i == $paged) : ?>
                                                <span class="dokan-page-number current" style="display: inline-block; padding: 5px 10px; margin: 0 2px; background: #007cba; color: white; border-radius: 3px; text-decoration: none;"><?php echo $i; ?></span>
                                            <?php else : ?>
                                                <a href="<?php echo esc_url(add_query_arg('paged', $i, $current_url)); ?>" class="dokan-page-number" style="display: inline-block; padding: 5px 10px; margin: 0 2px; background: #f8f9fa; color: #333; border: 1px solid #ddd; border-radius: 3px; text-decoration: none;"><?php echo $i; ?></a>
                                            <?php endif; ?>
                                        <?php endfor; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <!-- No results message (hidden by default) -->
                        <div id="no-results" class="dokan-alert dokan-alert-info" style="display: none; margin-top: 20px;">
                            <h4><?php esc_html_e('No Products Found', 'online-texas-core'); ?></h4>
                            <p><?php esc_html_e('No products match your current filter criteria. Try adjusting your filters or clear them to see all products.', 'online-texas-core'); ?></p>
                        </div>
                    <?php else : ?>
                        <!-- No products found -->
                        <div class="dokan-alert dokan-alert-info">
                            <h4><?php esc_html_e('No Products Available', 'online-texas-core'); ?></h4>
                            <p><?php esc_html_e('There are currently no admin products available for duplication. This could mean:', 'online-texas-core'); ?></p>
                            <ul style="margin-left: 20px;">
                                <li><?php esc_html_e('• No admin products have been created with linked courses or codes', 'online-texas-core'); ?></li>
                                <li><?php esc_html_e('• Admin has restricted access to available products', 'online-texas-core'); ?></li>
                                <li><?php esc_html_e('• You may have already duplicated all available products', 'online-texas-core'); ?></li>
                            </ul>
                            <p><?php esc_html_e('Please contact your administrator if you believe this is an error.', 'online-texas-core'); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- JavaScript for duplicate functionality and filtering -->
        <script>
            jQuery(document).ready(function($) {
                // Filter functionality
                function filterProducts() {
                    const typeFilter = $('#product-type-filter').val();
                    const courseFilter = $('#course-filter').val();
                    let visibleCount = 0;
                    let totalCount = $('.product-row').length;

                    $('.product-row').each(function() {
                        const $row = $(this);
                        const productType = $row.data('product-type');
                        const productCourses = $row.data('courses'); // Get comma-separated course IDs
                        
                        let showRow = true;

                        // Apply type filter
                        if (typeFilter !== 'all' && productType !== typeFilter) {
                            showRow = false;
                        }

                        // Apply course filter
                        if (courseFilter !== 'all') {
                            const courseIds = productCourses ? productCourses.split(',').map(id => id.trim()) : [];
                            if (!courseIds.includes(courseFilter)) {
                                showRow = false;
                            }
                        }

                        if (showRow) {
                            $row.show();
                            visibleCount++;
                        } else {
                            $row.hide();
                        }
                    });

                    // Update filter summary
                    updateFilterSummary(typeFilter, courseFilter, visibleCount, totalCount);

                    // Show/hide no results message
                    if (visibleCount === 0) {
                        $('#no-results').show();
                        $('.dokan-dashboard-product-listing').hide();
                    } else {
                        $('#no-results').hide();
                        $('.dokan-dashboard-product-listing').show();
                    }
                }

                function updateFilterSummary(typeFilter, courseFilter, visibleCount, totalCount) {
                    const filterText = [];
                    
                    if (typeFilter !== 'all') {
                        filterText.push(typeFilter === 'codes' ? 'Codes Products' : 'Course Products');
                    }
                    
                    if (courseFilter !== 'all') {
                        const courseName = $('#course-filter option:selected').text();
                        filterText.push(courseName);
                    }

                    if (filterText.length > 0) {
                        $('#filter-text').text('Showing: ' + filterText.join(' + '));
                        $('#product-count').text(visibleCount + ' of ' + totalCount + ' products');
                        $('#filter-summary').show();
                    } else {
                        $('#filter-summary').hide();
                    }
                    
                    // Show special message if course filter is applied but no products found on current page
                    if (courseFilter !== 'all' && visibleCount === 0) {
                        const courseName = $('#course-filter option:selected').text();
                        $('#no-results h4').text('No Products Found for Selected Course');
                        $('#no-results p').html('No products linked to "<strong>' + courseName + '</strong>" found on this page. <br>Try navigating to other pages or clear the filter to see all products.');
                        $('#no-results').show();
                        $('.dokan-dashboard-product-listing').hide();
                    }
                }

                // Product type filter change
                $('#product-type-filter').on('change', filterProducts);

                // Course filter change - AJAX search
                $('#course-filter').on('change', function() {
                    const courseId = $(this).val();
                    if (courseId === 'all') {
                        // Reset to normal pagination
                        window.location.href = '<?php echo esc_url($current_url); ?>';
                    } else {
                        // Perform AJAX search
                        performCourseSearch(courseId);
                    }
                });

                // Clear filters
                $('#clear-filters').on('click', function() {
                    $('#product-type-filter').val('all');
                    $('#course-filter').val('all');
                    window.location.href = '<?php echo esc_url($current_url); ?>';
                });

                // AJAX course search function
                function performCourseSearch(courseId) {
                    const $loading = $('#course-search-loading');
                    const $tableBody = $('.dokan-admin-products-listing');
                    const $pagination = $('.dokan-pagination-container');
                    const $noResults = $('#no-results');
                    
                    // Show loading
                    $loading.show();
                    $tableBody.html('<tr><td colspan="6" style="text-align: center; padding: 20px;"><i class="fas fa-spinner fa-spin"></i> <?php echo esc_js(__('Searching for products...', 'online-texas-core')); ?></td></tr>');
                    
                    $.ajax({
                        url: '<?php echo admin_url('admin-ajax.php'); ?>',
                        type: 'POST',
                        data: {
                            action: 'search_products_by_course',
                            course_id: courseId,
                            nonce: '<?php echo wp_create_nonce('search_products_by_course_nonce'); ?>'
                        },
                        success: function(response) {
                            $loading.hide();
                            
                            if (response.success && response.data.products) {
                                const products = response.data.products;
                                
                                if (products.length > 0) {
                                    // Hide pagination and show results
                                    $pagination.hide();
                                    $noResults.hide();
                                    
                                    // Build table rows
                                    let tableRows = '';
                                    products.forEach(function(product) {
                                        const thumbnail = product.thumbnail || '';
                                        const thumbnailHtml = thumbnail ? 
                                            `<img src="${thumbnail}" alt="${product.name}" class="product-thumbnail" style="width: 50px; height: 50px; object-fit: cover; border-radius: 3px;">` :
                                            `<span class="dokan-product-placeholder" style="display: inline-block; width: 50px; height: 50px; background: #f0f0f0; text-align: center; line-height: 50px; border-radius: 3px;"><i class="fas fa-image"></i></span>`;
                                        
                                        const priceHtml = product.sale_price ? 
                                            `<del style="color: #999; font-size: 12px;">${product.price}</del><br><span style="color: #dc3545; font-weight: bold;">${product.sale_price}</span>` :
                                            `<span style="font-weight: bold;">${product.price}</span>`;
                                        
                                        const typeHtml = product.is_automator_codes ? 
                                            `<span class="dokan-label dokan-label-warning" style="font-size:11px;"><i class="fas fa-tags"></i> <?php echo esc_js(__('Codes', 'online-texas-core')); ?></span>` :
                                            `<span class="dokan-label dokan-label-info" style="font-size:11px;"><i class="fas fa-graduation-cap"></i> <?php echo esc_js(__('Course', 'online-texas-core')); ?></span>`;
                                        
                                        const coursesHtml = product.courses && product.courses.length > 0 ? 
                                            `<div style="font-size: 11px; color: #666;">${product.courses.map(c => c.name).join(', ')}</div>` :
                                            `<span style="font-size: 11px; color: #999;"><?php echo esc_js(__('No courses linked', 'online-texas-core')); ?></span>`;
                                        
                                        const actionHtml = !product.already_duplicated ? 
                                            `<button class="dokan-btn dokan-btn-sm dokan-btn-theme duplicate-btn" data-product-id="${product.id}" data-product-name="${product.name}" data-product-type="${product.is_automator_codes ? 'codes' : 'regular'}" style="background: #007cba; color: white; border: none; padding: 8px 12px; border-radius: 3px; cursor: pointer; font-size: 12px;"><i class="fas fa-copy"></i> ${product.is_automator_codes ? '<?php echo esc_js(__('Clone & Generate Codes', 'online-texas-core')); ?>' : '<?php echo esc_js(__('Duplicate', 'online-texas-core')); ?>'}</button>` :
                                            `<span class="dokan-text-muted" style="color: #28a745; font-size: 12px;"><i class="fas fa-check"></i> <?php echo esc_js(__('Already Duplicated', 'online-texas-core')); ?></span>`;
                                        
                                        tableRows += `
                                            <tr class="product-row" data-product-type="${product.is_automator_codes ? 'codes' : 'course'}" data-courses="${product.courses ? product.courses.map(c => c.id).join(',') : ''}">
                                                <td class="product-thumb">${thumbnailHtml}</td>
                                                <td class="product-name">
                                                    <strong>${product.name}</strong>
                                                    <span class="dokan-label dokan-label-default" style="font-size:11px; margin-left: 4px;">${product.status}</span>
                                                    ${product.short_description ? `<div class="product-excerpt" style="font-size: 12px; color: #666; margin-top: 5px;">${product.short_description}</div>` : ''}
                                                </td>
                                                <td class="product-type">${typeHtml}</td>
                                                <td class="product-courses">${coursesHtml}</td>
                                                <td class="product-price">${priceHtml}</td>
                                                <td class="product-action">${actionHtml}</td>
                                            </tr>
                                        `;
                                    });
                                    
                                    $tableBody.html(tableRows);
                                    
                                    // Update filter summary
                                    const courseName = $('#course-filter option:selected').text();
                                    $('#filter-text').text('Showing: ' + courseName);
                                    $('#product-count').text(products.length + ' products found');
                                    $('#filter-summary').show();
                                    
                                } else {
                                    // No products found
                                    $pagination.hide();
                                    const courseName = $('#course-filter option:selected').text();
                                    $('#no-results h4').text('<?php echo esc_js(__('No Products Found', 'online-texas-core')); ?>');
                                    $('#no-results p').html(`<?php echo esc_js(__('No products are linked to', 'online-texas-core')); ?> "<strong>${courseName}</strong>".`);
                                    $('#no-results').show();
                                    $tableBody.html('');
                                }
                            } else {
                                // Error handling
                                $pagination.hide();
                                $('#no-results h4').text('<?php echo esc_js(__('Search Error', 'online-texas-core')); ?>');
                                $('#no-results p').text('<?php echo esc_js(__('An error occurred while searching. Please try again.', 'online-texas-core')); ?>');
                                $('#no-results').show();
                                $tableBody.html('');
                            }
                        },
                        error: function() {
                            $loading.hide();
                            $pagination.hide();
                            $('#no-results h4').text('<?php echo esc_js(__('Search Error', 'online-texas-core')); ?>');
                            $('#no-results p').text('<?php echo esc_js(__('An error occurred while searching. Please try again.', 'online-texas-core')); ?>');
                            $('#no-results').show();
                            $tableBody.html('');
                        }
                    });
                }

                // Handle duplicate button clicks
                $(document).on('click', '.duplicate-btn', function(e) {
                    e.preventDefault();

                    const $button = $(this);
                    const productId = $button.data('product-id');
                    const productName = $button.data('product-name');
                    const productType = $button.data('product-type');
                    const originalText = $button.html();

                    // Show confirmation dialog
                    const confirmMessage = productType === 'codes' 
                        ? '<?php echo esc_js(__('Are you sure you want to clone and generate codes for', 'online-texas-core')); ?> "' + productName + '"?'
                        : '<?php echo esc_js(__('Are you sure you want to duplicate', 'online-texas-core')); ?> "' + productName + '"?';
                    
                    if (!confirm(confirmMessage)) {
                        return;
                    }

                    // Update button state
                    $button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> <?php echo esc_js(__('Processing...', 'online-texas-core')); ?>');

                    // Make AJAX request
                    $.ajax({
                        url: '<?php echo admin_url('admin-ajax.php'); ?>',
                        type: 'POST',
                        data: {
                            action: 'duplicate_admin_product',
                            product_id: productId,
                            nonce: '<?php echo wp_create_nonce('duplicate_admin_product_nonce'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                const successMessage = productType === 'codes'
                                    ? '<?php echo esc_js(__('Product cloned and codes generated successfully! Check your Products and Codes pages.', 'online-texas-core')); ?>'
                                    : '<?php echo esc_js(__('Product duplicated successfully! Check your Products page.', 'online-texas-core')); ?>';
                                alert(successMessage);
                                $button.html('<i class="fas fa-check"></i> <?php echo esc_js(__('Completed', 'online-texas-core')); ?>')
                                    .removeClass('dokan-btn-theme')
                                    .css({
                                        'background': '#28a745',
                                        'border-color': '#28a745'
                                    });
                            } else {
                                alert('<?php echo esc_js(__('Error:', 'online-texas-core')); ?> ' + response.data);
                                $button.prop('disabled', false).html(originalText);
                            }
                        },
                        error: function() {
                            alert('<?php echo esc_js(__('An error occurred. Please try again.', 'online-texas-core')); ?>');
                            $button.prop('disabled', false).html(originalText);
                        }
                    });
                });
            });
        </script>

        <?php
        /**
         *  dokan_dashboard_content_inside_after hook
         *
         *  @since 2.4
         */
        do_action('dokan_dashboard_content_inside_after');
        ?>
    </div><!-- .dokan-dashboard-content -->

    <?php
    /**
     *  dokan_dashboard_content_after hook
     *
     *  @since 2.4
     */
    do_action('dokan_dashboard_content_after');
    ?>
</div><!-- .dokan-dashboard-wrap -->