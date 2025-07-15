<?php

/**
 * Template for displaying admin products in vendor dashboard
 * This template integrates with Dokan's dashboard layout
 */

// Don't load this template directly
if (!defined('ABSPATH')) {
    exit;
}

// Fix pagination query vars
$paged = max(1, get_query_var('paged'));
if (!$paged) {
    $paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
}

$per_page = 20;
$products = get_admin_products_for_vendor($paged, $per_page);

// Get current URL for pagination
$current_url = add_query_arg(array());
$current_url = remove_query_arg('paged', $current_url);
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
                    <?php esc_html_e('Admin Products', 'dokan'); ?>
                </h1>
            </div>

            <div class="online-texas-dashboard-content">
                <div class="admin-products-container">
                    <div class="dokan-alert dokan-alert-info" style="margin-bottom: 20px;">
                        <p><?php esc_html_e('Browse and duplicate admin products to add them to your store. Duplicated products will be saved as drafts for your review.', 'dokan'); ?></p>
                    </div>

                    <!-- Search and Filter Section -->
                    <div class="admin-products-filters">
                        <div class="admin-products-search-container">
                            <input type="text" class="admin-products-search dokan-form-control" placeholder="<?php esc_attr_e('Search products...', 'dokan'); ?>">
                            <button type="button" class="admin-products-search-btn dokan-btn dokan-btn-theme">
                                <?php esc_html_e('Search', 'dokan'); ?>
                            </button>
                        </div>
                    </div>

                    <?php if ($products && $products->have_posts()) : ?>
                        <div class="dokan-dashboard-product-listing">
                            <table class="dokan-table admin-products-table">
                                <thead>
                                    <tr>
                                        <th class="product-thumb"><?php esc_html_e('Image', 'dokan'); ?></th>
                                        <th class="product-name"><?php esc_html_e('Product Name', 'dokan'); ?></th>
                                        <th class="product-price"><?php esc_html_e('Price', 'dokan'); ?></th>
                                        <th class="product-stock"><?php esc_html_e('Stock Status', 'dokan'); ?></th>
                                        <th class="product-category"><?php esc_html_e('Categories', 'dokan'); ?></th>
                                        <th class="product-action"><?php esc_html_e('Action', 'dokan'); ?></th>
                                    </tr>
                                </thead>
                                <tbody class='dokan-admin-products-listing'>
                                    <?php
                                    require_once ONLINE_TEXAS_CORE_PATH . 'public/partials/online-texas-products-html.php';
                                    ?>
                                </tbody>
                            </table>
                        </div>

                        <?php
                        // Fixed pagination
                        $total_pages = $products->max_num_pages;
                        if ($total_pages > 1) :
                        ?>
                            <div class="dokan-pagination-container">
                                <?php
                                $pagination_args = array(
                                    'base' => add_query_arg('paged', '%#%'),
                                    'format' => '',
                                    'current' => $paged,
                                    'total' => $total_pages,
                                    'prev_text' => '<i class="fas fa-chevron-left"></i>',
                                    'next_text' => '<i class="fas fa-chevron-right"></i>',
                                    'type' => 'list',
                                    'end_size' => 3,
                                    'mid_size' => 3,
                                    'show_all' => false,
                                    'prev_next' => true,
                                );

                                $pagination_links = paginate_links($pagination_args);
                                if ($pagination_links) {
                                    echo '<nav class="dokan-pagination">' . $pagination_links . '</nav>';
                                }
                                ?>
                            </div>
                        <?php endif; ?>

                    <?php else : ?>
                        <div class="dokan-error">
                            <p><?php esc_html_e('No admin products found.', 'dokan'); ?></p>
                        </div>
                    <?php endif; ?>

                    <?php wp_reset_postdata(); ?>
                </div>
            </div>
        </div>

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