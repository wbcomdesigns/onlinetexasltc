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
function otc_user_has_admin_role($user_id = null) {
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
function otc_should_show_vendor_features() {
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

$per_page = 10;

// Get admin products
try {
    // Get all users with administrator role
    $admin_users = get_users(array(
        'role' => 'administrator', 
        'fields' => 'ID'
    ));
    
    if (empty($admin_users)) {
        $admin_users = array(1); // Fallback to user ID 1
    }

    
    $products = get_admin_products_for_vendor( $paged, $per_page);
    
} catch (Exception $e) {
    $products = new WP_Query(array('post_type' => 'product', 'posts_per_page' => 0));
}

// Get current URL for pagination
$current_url = dokan_get_navigation_url('source');
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
                    <div class="dokan-alert dokan-alert-info" style="margin-bottom: 20px;">
                        <p><?php esc_html_e('Browse and duplicate admin products to add them to your store. Duplicated products will be saved as drafts for your review.', 'online-texas-core'); ?></p>
                    </div>

                    <?php if ($products && $products->have_posts()) : ?>
                        <div class="dokan-dashboard-product-listing">
                            <table class="dokan-table admin-products-table">
                                <thead>
                                    <tr>
                                        <th class="product-thumb"><?php esc_html_e('Image', 'online-texas-core'); ?></th>
                                        <th class="product-name"><?php esc_html_e('Product Name', 'online-texas-core'); ?></th>
                                        <th class="product-price"><?php esc_html_e('Price', 'online-texas-core'); ?></th>
                                        <th class="product-action"><?php esc_html_e('Action', 'online-texas-core'); ?></th>
                                    </tr>
                                </thead>
                                <tbody class='dokan-admin-products-listing'>
                                    <?php
                                    while ($products->have_posts()) : 
                                        $products->the_post();
                                        $product = wc_get_product(get_the_ID());
                                        $thumbnail = get_the_post_thumbnail_url(get_the_ID(), 'thumbnail');
                                        
                                        // Check if already duplicated by current vendor
                                        $already_duplicated = function_exists('is_duplicated') ? is_duplicated(get_the_ID()) : false;
                                        
                                        // Check if product is from admin
                                        $product_author = get_post_field('post_author', get_the_ID());
                                        $product_author_user = get_user_by('ID', $product_author);
                                        $is_admin_product = ($product_author == 0) || 
                                                          ($product_author_user && in_array('administrator', $product_author_user->roles));
                                    ?>
                                        <tr>
                                            <td class="product-thumb">
                                                <?php if ($thumbnail) : ?>
                                                    <img src="<?php echo esc_url($thumbnail); ?>" alt="<?php echo esc_attr(get_the_title()); ?>" class="product-thumbnail" style="width: 50px; height: 50px; object-fit: cover; border-radius: 3px;">
                                                <?php else : ?>
                                                    <span class="dokan-product-placeholder" style="display: inline-block; width: 50px; height: 50px; background: #f0f0f0; text-align: center; line-height: 50px; border-radius: 3px;">
                                                        <i class="fas fa-image"></i>
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="product-name">
                                                <strong><?php echo esc_html(get_the_title()); ?></strong>
                                                <?php if ($product && $product->get_short_description()) : ?>
                                                    <div class="product-excerpt" style="font-size: 12px; color: #666; margin-top: 5px;">
                                                        <?php echo wp_trim_words($product->get_short_description(), 10); ?>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td class="product-price">
                                                <?php if ($product) : ?>
                                                    <?php if ($product->get_sale_price()) : ?>
                                                        <del style="color: #999; font-size: 12px;"><?php echo wc_price($product->get_regular_price()); ?></del><br>
                                                        <span style="color: #dc3545; font-weight: bold;"><?php echo wc_price($product->get_sale_price()); ?></span>
                                                    <?php else : ?>
                                                        <span style="font-weight: bold;"><?php echo wc_price($product->get_regular_price()); ?></span>
                                                    <?php endif; ?>
                                                <?php else : ?>
                                                    <span style="color: #666;">—</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="product-action">
                                                <?php if (!$already_duplicated && $is_admin_product) : ?>
                                                    <button class="dokan-btn dokan-btn-sm dokan-btn-theme duplicate-btn"
                                                        data-product-id="<?php echo esc_attr(get_the_ID()); ?>"
                                                        data-product-name="<?php echo esc_attr(get_the_title()); ?>"
                                                        style="background: #007cba; color: white; border: none; padding: 8px 12px; border-radius: 3px; cursor: pointer; font-size: 12px;">
                                                        <i class="fas fa-copy"></i>
                                                        <?php esc_html_e('Duplicate', 'online-texas-core'); ?>
                                                    </button>
                                                <?php elseif ($already_duplicated) : ?>
                                                    <span class="dokan-text-muted" style="color: #28a745; font-size: 12px;">
                                                        <i class="fas fa-check"></i> <?php esc_html_e('Already Duplicated', 'online-texas-core'); ?>
                                                    </span>
                                                <?php else : ?>
                                                    <span class="dokan-text-muted" style="color: #999; font-size: 12px;">
                                                        <?php esc_html_e('Not Available', 'online-texas-core'); ?>
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>

                        <?php
                        // Pagination
                        $total_pages = $products->max_num_pages;
                        if ($total_pages > 1) :
                        ?>
                            <div class="dokan-pagination-container" style="text-align: center; margin-top: 20px;">
                                <?php
                                $pagination_args = array(
                                    'base' => add_query_arg('paged', '%#%', $current_url),
                                    'format' => '',
                                    'current' => $paged,
                                    'total' => $total_pages,
                                    'prev_text' => '&laquo; ' . __('Previous', 'online-texas-core'),
                                    'next_text' => __('Next', 'online-texas-core') . ' &raquo;',
                                    'type' => 'list',
                                    'end_size' => 2,
                                    'mid_size' => 1,
                                );

                                $pagination_links = paginate_links($pagination_args);
                                if ($pagination_links) {
                                    echo '<nav class="dokan-pagination">' . $pagination_links . '</nav>';
                                }
                                ?>
                            </div>
                        <?php endif; ?>

                    <?php else : ?>
                        <!-- No products found -->
                        <div class="dokan-alert dokan-alert-info">
                            <h4><?php esc_html_e('No Products Available', 'online-texas-core'); ?></h4>
                            <p><?php esc_html_e('There are currently no admin products available for duplication. This could mean:', 'online-texas-core'); ?></p>
                            <ul style="margin-left: 20px;">
                                <li><?php esc_html_e('• No admin products have been created with linked courses', 'online-texas-core'); ?></li>
                                <li><?php esc_html_e('• Admin has restricted access to available products', 'online-texas-core'); ?></li>
                                <li><?php esc_html_e('• You may have already duplicated all available products', 'online-texas-core'); ?></li>
                            </ul>
                            <p><?php esc_html_e('Please contact your administrator if you believe this is an error.', 'online-texas-core'); ?></p>
                        </div>
                    <?php endif; ?>

                    <?php wp_reset_postdata(); ?>
                </div>
            </div>
        </div>

        <!-- JavaScript for duplicate functionality -->
        <script>
        jQuery(document).ready(function($) {
            // Handle duplicate button clicks
            $(document).on('click', '.duplicate-btn', function(e) {
                e.preventDefault();
                
                const $button = $(this);
                const productId = $button.data('product-id');
                const productName = $button.data('product-name');
                const originalText = $button.html();
                
                // Show confirmation dialog
                if (!confirm('<?php echo esc_js(__('Are you sure you want to duplicate', 'online-texas-core')); ?> "' + productName + '"?')) {
                    return;
                }
                
                // Update button state
                $button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> <?php echo esc_js(__('Duplicating...', 'online-texas-core')); ?>');
                
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
                            alert('<?php echo esc_js(__('Product duplicated successfully! Check your Products page.', 'online-texas-core')); ?>');
                            $button.html('<i class="fas fa-check"></i> <?php echo esc_js(__('Duplicated', 'online-texas-core')); ?>')
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