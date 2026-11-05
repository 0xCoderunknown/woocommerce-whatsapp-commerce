<?php
/**
 * ⚡ FRONTEND SINGLE PRODUCT: DELIVERY CUTOFF NOTICE BADGE
 *
 * Displays a dynamic, timezone-aware same-day dispatch cutoff notice on single product pages.
 *
 * @package StatelessWaCommerce
 */

if (!defined('ABSPATH')) {
    exit;
}

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
