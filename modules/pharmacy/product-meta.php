<?php
/**
 * 🏷️ PHARMACY MODULE: PRODUCT METADATA SHORTCODES
 *
 * Emits dynamic chemical composition tags and prescription regulatory badges.
 *
 * @package StatelessWaCommerce
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('swac_is_module_active') || !swac_is_module_active('pharmacy')) {
    return;
}

/**
 * Composition Link Tag Shortcode: [swac_composition_tag]
 */
function swac_composition_tag_shortcode() {
    if (!function_exists('is_product') || !is_product()) {
        return '';
    }

    global $post;
    $post_id = !empty($post->ID) ? (int)$post->ID : (int)(get_the_ID() ?: get_queried_object_id());
    if (!$post_id) {
        return '';
    }

    $salt_tax = defined('SWAC_TAX_SALT') ? SWAC_TAX_SALT : 'composition';
    $terms = function_exists('swac_get_product_terms_memoized')
        ? swac_get_product_terms_memoized($post_id, $salt_tax)
        : get_the_terms($post_id, $salt_tax);

    if (empty($terms) || is_wp_error($terms)) {
        return '';
    }

    $output = '';
    foreach ($terms as $term) {
        $term_link = get_term_link($term);
        if (is_wp_error($term_link)) {
            continue;
        }

        $output .= sprintf(
            '<a href="%s" class="swac-salt-tag wa-salt-tag pd-salt-tag">%s</a>',
            esc_url($term_link),
            esc_html($term->name)
        );
    }

    return $output;
}
add_shortcode('swac_composition_tag', 'swac_composition_tag_shortcode');
add_shortcode('g1_composition_tag', 'swac_composition_tag_shortcode');

/**
 * Regulatory Rx Badge Shortcode: [swac_rx_badge]
 */
function swac_rx_badge_shortcode() {
    if (!function_exists('is_product') || !is_product()) {
        return '';
    }

    $product = function_exists('swac_get_current_product') ? swac_get_current_product() : null;
    if (!$product) {
        return '';
    }

    $is_rx = function_exists('swac_is_prescription_product') && swac_is_prescription_product($product);

    if ($is_rx) {
        return '<span class="swac-rx-badge wa-rx-badge pd-rx-badge" title="' . esc_attr__('Prescription Required', 'stateless-wa-commerce') . '">℞ Rx Required</span>';
    }

    return '<span class="swac-otc-badge wa-otc-badge pd-otc-badge">' . esc_html__('OTC', 'stateless-wa-commerce') . '</span>';
}
add_shortcode('swac_rx_badge', 'swac_rx_badge_shortcode');
