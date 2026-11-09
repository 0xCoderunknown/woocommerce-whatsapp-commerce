<?php
/**
 * 📢 FRONTEND SINGLE PRODUCT: NOTICES & STOCK SAFETY NET
 *
 * Emits category compliance notices and fail-safe out-of-stock indicators.
 *
 * @package StatelessWaCommerce
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 1. Category-specific compliance & non-returnable notices
 */
add_action('woocommerce_after_add_to_cart_button', function () {
    if (!is_product()) {
        return;
    }

    $product = function_exists('swac_get_current_product') ? swac_get_current_product() : null;
    if (!$product instanceof WC_Product) {
        return;
    }

    $non_returnable_cats = (array)apply_filters('swac_non_returnable_categories', []);

    if (empty($non_returnable_cats)) {
        return;
    }

    $product_cats = wp_get_post_terms($product->get_id(), 'product_cat', ['fields' => 'slugs']);
    if (empty($product_cats) || is_wp_error($product_cats)) {
        return;
    }

    if (!empty(array_intersect($non_returnable_cats, $product_cats))) {
        echo '<div class="swac-sp-notice swac-notice-nr"><span>🚫</span><span><strong>'
            . esc_html__('Notice:', 'stateless-wa-commerce')
            . '</strong> '
            . esc_html__('This item is non-returnable.', 'stateless-wa-commerce')
            . '</span></div>';
    }
}, 99);

/**
 * 2. Single Product Out-of-Stock Notice Safety Net
 */
if (!function_exists('swac_render_single_product_stock_status')) {
    function swac_render_single_product_stock_status(): string
    {
        static $rendered = false;
        if ($rendered) {
            return '';
        }

        if (function_exists('is_product') && !is_product()) {
            return '';
        }

        $product = function_exists('swac_get_current_product') ? swac_get_current_product() : null;
        if (!$product instanceof WC_Product || $product->is_in_stock()) {
            return '';
        }

        $rendered = true;

        return sprintf(
            '<div class="swac-single-stock-notice-wrapper"><div class="swac-sp-notice swac-notice-oos"><div class="swac-notice-content"><strong>%s</strong></div></div></div>',
            esc_html__('Out of stock', 'stateless-wa-commerce')
        );
    }
}

add_action('woocommerce_single_product_summary', function () {
    echo swac_render_single_product_stock_status();
}, 25);

add_action('woocommerce_before_add_to_cart_form', function () {
    echo swac_render_single_product_stock_status();
}, 5);

add_shortcode('swac_stock_status', function () {
    return swac_render_single_product_stock_status();
});

/**
 * 3. Dynamic Delivery Cutoff Notice Badge: [swac_delivery_notice]
 */
if (!function_exists('swac_render_delivery_notice')) {
    /**
     * Renders HTML for the delivery cutoff notice badge.
     *
     * @param int|null $timestamp Optional unix timestamp for preview.
     * @return string
     */
    function swac_render_delivery_notice(?int $timestamp = null): string
    {
        if (function_exists('is_product') && is_product()) {
            $product = function_exists('swac_get_current_product') ? swac_get_current_product() : null;
            if ($product && !$product->is_in_stock()) {
                return '';
            }
        }

        $message = function_exists('swac_get_delivery_cutoff_message')
            ? swac_get_delivery_cutoff_message($timestamp)
            : '';

        if (empty($message)) {
            return '';
        }

        $disclaimer = (string)apply_filters('swac_delivery_batch_disclaimer', '');

        $disclaimer_html = !empty($disclaimer)
            ? sprintf('<div class="swac-batch-price-line">%s</div>', esc_html($disclaimer))
            : '';

        return sprintf(
            '%s<div class="swac-delivery-notice"><span class="swac-delivery-icon" aria-hidden="true">⚡</span> <span class="swac-delivery-text">%s</span></div>',
            $disclaimer_html,
            esc_html($message)
        );
    }
}

add_shortcode('swac_delivery_notice', function () {
    return swac_render_delivery_notice();
});

add_action('woocommerce_after_add_to_cart_button', function () {
    if (function_exists('is_product') && !is_product()) {
        return;
    }
    echo swac_render_delivery_notice();
}, 15);
