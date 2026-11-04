<?php
/**
 * 🖥️ WHOLESALE / B2B: DISTRIBUTOR DESK SHORTCODE CONTROLLER
 *
 * Emits the mobile-first Distributor Desk interface for [swac_distributor_portal].
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
 * Shortcode: [swac_distributor_portal]
 * Emits the Distributor Desk application interface.
 *
 * @return string HTML markup for the distributor desk portal.
 */
function swac_distributor_portal_shortcode(): string
{
    $is_logged_in = current_user_can('manage_woocommerce') || current_user_can('edit_products');
    $brand_tax = defined('SWAC_TAX_BRAND') ? SWAC_TAX_BRAND : 'product_brand';
    $existing_brands = taxonomy_exists($brand_tax) ? get_terms(['taxonomy' => $brand_tax, 'hide_empty' => false]) : [];
    if (is_wp_error($existing_brands)) {
        $existing_brands = [];
    }

    ob_start();
    include __DIR__ . '/templates/distributor-desk-view.php';
    return (string)ob_get_clean();
}

add_shortcode('swac_distributor_portal', 'swac_distributor_portal_shortcode');
add_shortcode('swac_frontend_admin', 'swac_distributor_portal_shortcode');
