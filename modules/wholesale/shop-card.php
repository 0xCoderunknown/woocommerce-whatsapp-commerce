<?php
/**
 * 🛍️ WHOLESALE / B2B MODULE: SHOP CARD & CATALOG LOOP TRANSFORMATIONS
 *
 * Renders wholesale PTR rates, MRP-to-PTR retailer margins, promotional scheme badges,
 * carton packing guide labels, and inline quantity selectors directly on the catalog cards.
 * Provides client-side gated view for approved retailers.
 *
 * @package StatelessWaCommerce
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('swac_is_module_active') || !swac_is_module_active('wholesale')) {
    return;
}

/**
 * Adjusts commerce configuration limits for wholesale bulk ordering.
 */
add_filter('swac_commerce_config', function (array $config): array {
    $config['max_qty_per_item'] = 9999;
    $config['max_cart_items'] = 150;
    return $config;
}, 20);

/**
 * 1. Replaces standard loop price with Wholesale Pricing Block (Gated).
 */
if (!function_exists('swac_b2b_format_price_block_html')) {
    /**
     * Formats the dual-state Wholesale Pricing Block (Gated / Unlocked).
     *
     * @param array $b2b Wholesale product data array.
     * @param bool $is_card Whether rendering in a loop card or single page.
     * @return string
     */
    function swac_b2b_format_price_block_html(array $b2b, bool $is_card = false): string
    {
        $currency = function_exists('get_woocommerce_currency_symbol') ? get_woocommerce_currency_symbol() : '₹';
        $extra_class = $is_card ? ' swac-b2b-price-block--card' : ' swac-b2b-price-block--single';

        ob_start();
        ?>
        <div class="swac-b2b-price-block<?php echo esc_attr($extra_class); ?>">
            <!-- Unauthenticated Locked View -->
            <div class="swac-b2b-locked-view">
                <button type="button" class="swac-b2b-gate-trigger"
                        aria-label="<?php esc_attr_e('Login to view wholesale rate', 'stateless-wa-commerce'); ?>">
                    <span class="swac-lock-icon" aria-hidden="true">🔒</span>
                    <span><?php esc_html_e('Retailer PIN to View Rate', 'stateless-wa-commerce'); ?></span>
                </button>
            </div>

            <!-- Authenticated Unlocked View -->
            <div class="swac-b2b-unlocked-view">
                <div class="swac-b2b-rate-row">
                    <span class="swac-b2b-ptr">
                        <span class="swac-b2b-ptr-label"><?php esc_html_e('Rate:', 'stateless-wa-commerce'); ?></span>
                        <span class="swac-b2b-ptr-val"><?php echo wc_price($b2b['ptr']); ?></span>
                    </span>
                    <?php if ($b2b['mrp'] > $b2b['ptr'] && $b2b['mrp'] > 0) : ?>
                        <span class="swac-b2b-mrp">
                            <?php esc_html_e('MRP:', 'stateless-wa-commerce'); ?>
                            <s class="swac-b2b-mrp-strike"><?php echo wc_price($b2b['mrp']); ?></s>
                        </span>
                    <?php endif; ?>
                </div>

                <?php if ($b2b['margin_amount'] > 0) : ?>
                    <div class="swac-b2b-margin-row">
                        <span class="swac-b2b-margin-badge">
                            <?php
                            printf(
                            /* translators: 1: currency symbol, 2: margin amount, 3: margin percent */
                                esc_html__('Margin: %1$s%2$s (%3$s%%)', 'stateless-wa-commerce'),
                                esc_html($currency),
                                esc_html(number_format($b2b['margin_amount'], 2)),
                                esc_html($b2b['margin_percent'])
                            );
                            ?>
                        </span>
                    </div>
                <?php endif; ?>

                <?php if (!empty($b2b['scheme_badge'])) : ?>
                    <div class="swac-b2b-scheme-row">
                        <span class="swac-b2b-scheme-badge">
                            <span class="swac-b2b-scheme-icon" aria-hidden="true">🎁</span>
                            <?php echo esc_html($b2b['scheme_badge']); ?>
                        </span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
        return (string)ob_get_clean();
    }
}

/**
 * 1. Replaces standard loop price with Wholesale Pricing Block (Gated).
 */
add_filter('woocommerce_get_price_html', function ($price_html, $product) {
    if (is_admin() || !$product instanceof WC_Product) {
        return $price_html;
    }

    if (!function_exists('swac_b2b_get_product_data') || !function_exists('swac_b2b_format_price_block_html')) {
        return $price_html;
    }

    $b2b = swac_b2b_get_product_data($product);
    if (!$b2b['is_b2b']) {
        return $price_html;
    }

    return swac_b2b_format_price_block_html($b2b, true);
}, 25, 2);

/**
 * 2. Replaces standard Loop Add-to-Cart with Carton Guide & Gated Card Ordering.
 */
add_filter('woocommerce_loop_add_to_cart_link', function ($button_html, $product) {
    if (!$product instanceof WC_Product) {
        return $button_html;
    }

    if (!$product->is_in_stock()) {
        return sprintf(
            '<a href="%s" class="button swac-out-of-stock-btn">%s</a>',
            esc_url($product->get_permalink()),
            esc_html__('Out of Stock', 'stateless-wa-commerce')
        );
    }

    $b2b = function_exists('swac_b2b_get_product_data')
        ? swac_b2b_get_product_data($product)
        : ['is_b2b' => false, 'carton_pack' => 0, 'scheme_badge' => ''];

    $price = $product->get_price() ? strip_tags((string)wc_price($b2b['is_b2b'] ? $b2b['ptr'] : $product->get_price())) : '';
    $name = $product->get_name();
    $url = $product->get_permalink();
    $product_id = $product->get_id();
    $mrp = (float)($b2b['is_b2b'] ? $b2b['mrp'] : $product->get_regular_price());
    $sale_price = (float)($b2b['is_b2b'] ? $b2b['ptr'] : $product->get_price());
    $is_rx = (bool)apply_filters('swac_product_is_rx', false, $product);
    $button_label = (string)apply_filters('swac_loop_button_text', __('Add to Order', 'stateless-wa-commerce'));
    $icon = function_exists('swac_icon_svg') ? swac_icon_svg() : '';

    ob_start();
    ?>
    <div class="swac-b2b-loop-wrapper">
        <!-- Unauthenticated Locked Action -->
        <div class="swac-b2b-locked-action">
            <button type="button" class="button swac-b2b-gate-trigger swac-b2b-locked-order-btn">
                <span class="swac-lock-icon" aria-hidden="true">🔒</span>
                <span><?php esc_html_e('Retailer Login to Order', 'stateless-wa-commerce'); ?></span>
            </button>
        </div>

        <!-- Authenticated Unlocked Action -->
        <div class="swac-b2b-unlocked-action">
            <?php if (!empty($b2b['carton_pack']) && $b2b['carton_pack'] > 0) : ?>
                <div class="swac-b2b-carton-guide"
                     title="<?php esc_attr_e('Packing conversion reference', 'stateless-wa-commerce'); ?>">
                    <span class="swac-b2b-carton-icon" aria-hidden="true">📦</span>
                    <?php
                    printf(
                    /* translators: %d: pieces per carton */
                        esc_html__('1 Carton = %d pcs', 'stateless-wa-commerce'),
                        (int)$b2b['carton_pack']
                    );
                    ?>
                </div>
            <?php endif; ?>

            <div class="swac-b2b-loop-action">
                <div class="swac-qty-selector">
                    <button type="button" class="swac-qty-btn swac-qty-minus"
                            aria-label="<?php esc_attr_e('Decrease quantity', 'stateless-wa-commerce'); ?>">−
                    </button>
                    <input
                            type="number"
                            class="swac-qty-input"
                            value="<?php echo (!empty($b2b['carton_pack']) && $b2b['carton_pack'] > 0) ? esc_attr($b2b['carton_pack']) : '1'; ?>"
                            min="1"
                            max="9999"
                            data-carton-pack="<?php echo esc_attr($b2b['carton_pack']); ?>"
                            aria-label="<?php esc_attr_e('Order quantity', 'stateless-wa-commerce'); ?>"
                    >
                    <button type="button" class="swac-qty-btn swac-qty-plus"
                            aria-label="<?php esc_attr_e('Increase quantity', 'stateless-wa-commerce'); ?>">+
                    </button>
                </div>

                <a href="<?php echo esc_url($url); ?>"
                   class="button swac-loop-add swac-b2b-add-btn"
                   data-product-id="<?php echo esc_attr($product_id); ?>"
                   data-product-name="<?php echo esc_attr($name); ?>"
                   data-product-url="<?php echo esc_url($url); ?>"
                   data-product-price="<?php echo esc_attr($price); ?>"
                   data-product-mrp="<?php echo esc_attr($mrp); ?>"
                   data-product-sale-price="<?php echo esc_attr($sale_price); ?>"
                   data-carton-pack="<?php echo esc_attr($b2b['carton_pack']); ?>"
                   data-scheme-badge="<?php echo esc_attr($b2b['scheme_badge']); ?>"
                   data-is-rx="<?php echo $is_rx ? '1' : '0'; ?>"
                   aria-label="<?php echo esc_attr(sprintf(__('Add %s to wholesale order', 'stateless-wa-commerce'), $name)); ?>"
                >
                    <?php echo $icon; ?>
                    <span><?php echo esc_html($button_label); ?></span>
                </a>
            </div>
        </div>
    </div>
    <?php
    return (string)ob_get_clean();
}, 25, 2);
