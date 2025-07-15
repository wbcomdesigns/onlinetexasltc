<?php

if ($products && $products->have_posts()) :

    while ($products->have_posts()) : $products->the_post();
        $product = wc_get_product(get_the_ID());
        $thumbnail = get_the_post_thumbnail_url(get_the_ID(), 'thumbnail');
        $categories = get_the_terms(get_the_ID(), 'product_cat');
        $category_names = array();
        if ($categories && !is_wp_error($categories)) {
            foreach ($categories as $category) {
                $category_names[] = $category->name;
            }
        }
?>
        <tr>
            <td class="product-thumb">
                <?php if ($thumbnail) : ?>
                    <img src="<?php echo esc_url($thumbnail); ?>" alt="<?php echo esc_attr(get_the_title()); ?>" class="product-thumbnail">
                <?php else : ?>
                    <span class="dokan-product-placeholder">
                        <i class="fas fa-image"></i>
                    </span>
                <?php endif; ?>
            </td>
            <td class="product-name">
                <strong><?php echo esc_html(get_the_title()); ?></strong>
                <?php if ($product && $product->get_short_description()) : ?>
                    <div class="product-excerpt">
                        <?php echo wp_trim_words($product->get_short_description(), 10); ?>
                    </div>
                <?php endif; ?>
            </td>
            <td class="product-price">
                <?php if ($product && $product->get_sale_price()) : ?>
                    <del class="original-price"><?php echo wc_price($product->get_regular_price()); ?></del>
                    <span class="sale-price"><?php echo wc_price($product->get_sale_price()); ?></span>
                <?php elseif ($product) : ?>
                    <span class="regular-price"><?php echo wc_price($product->get_regular_price()); ?></span>
                <?php endif; ?>
            </td>
            <td class="product-stock">
                <?php
                if ($product) {
                    $stock_status = $product->get_stock_status();
                    $stock_class = $stock_status === 'instock' ? 'dokan-label dokan-label-success' : 'dokan-label dokan-label-danger';
                    $stock_text = $stock_status === 'instock' ? __('In Stock', 'dokan') : __('Out of Stock', 'dokan');
                ?>
                    <span class="<?php echo esc_attr($stock_class); ?>">
                        <?php echo esc_html($stock_text); ?>
                    </span>
                <?php } ?>
            </td>
            <td class="product-category">
                <?php echo !empty($category_names) ? esc_html(implode(', ', $category_names)) : '-'; ?>
            </td>
            <td class="product-action">
                 <?php if ( ! is_duplicated( get_the_ID() ) ) : ?>
                    <button class="dokan-btn dokan-btn-sm dokan-btn-theme duplicate-btn"
                        data-product-id="<?php echo esc_attr(get_the_ID()); ?>"
                        data-product-name="<?php echo esc_attr(get_the_title()); ?>">
                        <i class="fas fa-copy"></i>
                        <?php esc_html_e('Duplicate', 'dokan'); ?>
                    </button>
                <?php else : ?>
                    <span class="dokan-text-muted">
                        <?php esc_html_e('Already Duplicated', 'dokan'); ?>
                    </span>
                <?php endif; ?>
            </td>
        </tr>
<?php endwhile;

endif; ?>