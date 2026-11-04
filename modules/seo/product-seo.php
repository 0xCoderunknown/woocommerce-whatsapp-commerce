<?php
/**
 * 🏷️ SEO MODULE: PRODUCT METADATA & SCHEMA ENGINE
 *
 * Generates dynamic SEO titles, meta descriptions, OpenGraph tags, and Schema.org JSON-LD
 * for single product pages without external SEO plugins.
 *
 * @package StatelessWaCommerce
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('swac_is_module_active') || !swac_is_module_active('seo')) {
    return;
}

if (!function_exists('swac_seo_get_deepest_term')) {
    function swac_seo_get_deepest_term(int $product_id, string $taxonomy): ?WP_Term
    {
        $terms = function_exists('swac_get_product_terms_memoized')
            ? swac_get_product_terms_memoized($product_id, $taxonomy)
            : get_the_terms($product_id, $taxonomy);

        if (empty($terms) || is_wp_error($terms)) {
            return null;
        }

        $parent_ids = wp_list_pluck($terms, 'parent');
        $deepest = null;

        foreach ($terms as $term) {
            if (!in_array($term->term_id, $parent_ids, true)) {
                $deepest = $term;
                break;
            }
        }

        return $deepest ?? $terms[0];
    }
}

if (!function_exists('swac_seo_build_description')) {
    function swac_seo_build_description(int $product_id): string
    {
        $product_name = get_the_title($product_id);
        $loc_suffix = (string)apply_filters('swac_seo_location_suffix', '');

        $sentences = [];
        if (!empty($loc_suffix)) {
            $sentences[] = sprintf(__('Buy %s online in %s.', 'stateless-wa-commerce'), $product_name, $loc_suffix);
        } else {
            $sentences[] = sprintf(__('Buy %s online.', 'stateless-wa-commerce'), $product_name);
        }

        $cat_term = swac_seo_get_deepest_term($product_id, 'product_cat');
        if ($cat_term && isset($cat_term->name)) {
            $sentences[] = trim((string)$cat_term->name) . '.';
        }

        if (!empty($loc_suffix)) {
            $sentences[] = sprintf(__('Fast delivery in %s.', 'stateless-wa-commerce'), $loc_suffix);
        } else {
            $sentences[] = __('Fast and reliable dispatch available.', 'stateless-wa-commerce');
        }

        $description = implode(' ', $sentences);

        if (mb_strlen($description) > 155) {
            $trimmed = '';
            foreach ($sentences as $sentence) {
                $candidate = $trimmed === '' ? $sentence : $trimmed . ' ' . $sentence;
                if (mb_strlen($candidate) <= 155) {
                    $trimmed = $candidate;
                } else {
                    break;
                }
            }
            $description = $trimmed !== '' ? $trimmed : mb_substr($description, 0, 155);
        }

        return $description;
    }
}

if (!function_exists('swac_seo_get_custom_title')) {
    function swac_seo_get_custom_title(string $title = ''): string
    {
        if (!function_exists('is_product') || !is_product()) {
            return $title;
        }

        $product_id = get_queried_object_id();
        if (!$product_id) {
            return $title;
        }

        $product_name = get_the_title($product_id);
        $store_name = get_bloginfo('name') ?: 'Store';
        $loc_suffix = (string)apply_filters('swac_seo_location_suffix', '');

        if (!empty($loc_suffix)) {
            $full_title = sprintf('%s - Buy Online in %s | %s', $product_name, $loc_suffix, $store_name);
        } else {
            $full_title = sprintf('%s - Buy Online | %s', $product_name, $store_name);
        }

        if (mb_strlen($full_title) > 60) {
            return mb_substr($full_title, 0, 57) . '...';
        }

        return $full_title;
    }
}

add_filter('pre_get_document_title', 'swac_seo_get_custom_title', 98);

/**
 * Output OpenGraph and Meta tags
 */
add_action('wp_head', function () {
    if (!function_exists('is_product') || !is_product()) {
        return;
    }

    $product_id = get_queried_object_id();
    if (!$product_id) {
        return;
    }

    $description = swac_seo_build_description($product_id);
    $store_name = get_bloginfo('name') ?: 'Store';
    $title = swac_seo_get_custom_title();
    $permalink = get_permalink($product_id);
    $img_url = get_the_post_thumbnail_url($product_id, 'large') ?: '';

    echo '<meta name="description" content="' . esc_attr($description) . '">' . "\n";
    echo '<meta property="og:type" content="product">' . "\n";
    echo '<meta property="og:title" content="' . esc_attr($title) . '">' . "\n";
    echo '<meta property="og:description" content="' . esc_attr($description) . '">' . "\n";
    echo '<meta property="og:url" content="' . esc_url($permalink) . '">' . "\n";
    echo '<meta property="og:site_name" content="' . esc_attr($store_name) . '">' . "\n";

    if (!empty($img_url)) {
        echo '<meta property="og:image" content="' . esc_url($img_url) . '">' . "\n";
    }
}, 10);

/**
 * 4. Output Schema.org Product JSON-LD
 */
if (!function_exists('swac_seo_print_jsonld')) {
    /**
     * Outputs a structured JSON-LD <script> block directly into document head.
     *
     * @param array $schema Schema definition array.
     * @return void
     */
    function swac_seo_print_jsonld(array $schema): void
    {
        echo '<script type="application/ld+json">' . "\n";
        echo wp_json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        echo "\n" . '</script>' . "\n";
    }
}
/**
 */
add_action('wp_head', function () {
    if (!function_exists('is_product') || !is_product()) {
        return;
    }

    $product = function_exists('swac_get_current_product')
        ? swac_get_current_product()
        : null;

    if (!$product instanceof WC_Product) {
        return;
    }

    $product_id = $product->get_id();
    $permalink = get_permalink($product_id);
    $price = (float)$product->get_price();
    $sku = $product->get_sku() ?: (string)$product_id;
    $img_url = get_the_post_thumbnail_url($product_id, 'large') ?: '';
    $currency = function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : 'USD';

    $schema = [
        '@context' => 'https://schema.org/',
        '@type' => 'Product',
        'name' => $product->get_name(),
        'image' => $img_url ? [$img_url] : [],
        'description' => swac_seo_build_description($product_id),
        'sku' => $sku,
        'offers' => [
            '@type' => 'Offer',
            'url' => $permalink,
            'priceCurrency' => $currency,
            'price' => number_format($price, 2, '.', ''),
            'availability' => $product->is_in_stock() ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
        ],
    ];

    if (function_exists('swac_seo_print_jsonld')) {
        swac_seo_print_jsonld($schema);
    }
}, 5);
