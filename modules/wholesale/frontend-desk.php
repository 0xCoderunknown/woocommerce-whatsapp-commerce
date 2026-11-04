<?php
/**
 * 🖥️ WHOLESALE / B2B MODULE: FRONTEND DISTRIBUTOR DESK BOOTSTRAP
 *
 * Loads REST API controllers, template markup, and conditional asset enqueuing.
 *
 * Shortcode: [swac_distributor_portal] (alias: [swac_frontend_admin])
 *
 * @package StatelessWaCommerce
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('swac_is_module_active') || !swac_is_module_active('wholesale')) {
    return;
}

// 1. REST API Controllers & Endpoints
require_once __DIR__ . '/desk-rest.php';

// 2. Shortcode Template Markup
require_once __DIR__ . '/desk-template.php';

// 3. Asset Enqueueing
/**
 * Enqueues assets strictly when the distributor desk shortcode is present.
 */
add_action('wp_enqueue_scripts', function () {
    global $post;
    if (is_a($post, 'WP_Post') && (has_shortcode($post->post_content, 'swac_distributor_portal') || has_shortcode($post->post_content, 'swac_frontend_admin'))) {
        wp_enqueue_style('swac-desk-style', SWAC_URL . 'assets/css/distributor-desk.css', [], SWAC_VERSION);
        wp_enqueue_script('swac-desk-script', SWAC_URL . 'assets/js/distributor-desk.js', [], SWAC_VERSION, true);
        wp_localize_script('swac-desk-script', 'swacDeskConfig', [
            'rest_url' => esc_url_raw(rest_url()),
            'nonce' => wp_create_nonce('wp_rest'),
            'is_admin' => current_user_can('manage_woocommerce'),
            'shop_url' => esc_url(wc_get_page_permalink('shop') ?: home_url('/')),
            'currency' => function_exists('swac_get_currency_symbol') ? swac_get_currency_symbol() : (function_exists('get_woocommerce_currency_symbol') ? get_woocommerce_currency_symbol() : '₹'),
        ]);
    }
});
