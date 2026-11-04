<?php
/**
 * 🗂️ PHARMACY MODULE: REGISTER CUSTOM TAXONOMIES
 *
 * Registers pharmaceutical taxonomies attached to the WooCommerce 'product' post type:
 *  1. composition (Active pharmaceutical ingredients / chemical formula)
 *  2. pack_size   (Quantitative packaging unit, e.g., '10 tablets', '100 ml')
 *  3. generic     (Flag designating low-cost generic equivalents)
 *
 * @package StatelessWaCommerce
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('swac_is_module_active') || !swac_is_module_active('pharmacy')) {
    return;
}

// Taxonomy slug definitions
if (!defined('SWAC_TAX_SALT')) {
    define('SWAC_TAX_SALT', 'composition');
}
if (!defined('SWAC_TAX_PACK_SIZE')) {
    define('SWAC_TAX_PACK_SIZE', 'pack_size');
}
if (!defined('SWAC_TAX_GENERIC')) {
    define('SWAC_TAX_GENERIC', 'generic');
}

add_action('init', function () {
    $taxonomies = [
        SWAC_TAX_SALT => [
            'singular' => __('Composition', 'stateless-wa-commerce'),
            'plural' => __('Compositions', 'stateless-wa-commerce'),
            'slug' => 'composition',
            'public' => true,
            'hierarchical' => false,
        ],
        SWAC_TAX_PACK_SIZE => [
            'singular' => __('Pack Size', 'stateless-wa-commerce'),
            'plural' => __('Pack Sizes', 'stateless-wa-commerce'),
            'slug' => 'pack-size',
            'public' => true,
            'hierarchical' => false,
        ],
        SWAC_TAX_GENERIC => [
            'singular' => __('Generic Flag', 'stateless-wa-commerce'),
            'plural' => __('Generic Flags', 'stateless-wa-commerce'),
            'slug' => 'generic',
            'public' => true,
            'hierarchical' => true,
        ],
    ];

    /**
     * Filter pharmacy taxonomy schema.
     *
     * @param array $taxonomies Taxonomies configuration schema.
     */
    $taxonomies = (array)apply_filters('swac_pharmacy_taxonomies_schema', $taxonomies);

    foreach ($taxonomies as $key => $config) {
        $labels = [
            'name' => $config['plural'],
            'singular_name' => $config['singular'],
            'search_items' => sprintf(__('Search %s', 'stateless-wa-commerce'), $config['plural']),
            'all_items' => sprintf(__('All %s', 'stateless-wa-commerce'), $config['plural']),
            'edit_item' => sprintf(__('Edit %s', 'stateless-wa-commerce'), $config['singular']),
            'update_item' => sprintf(__('Update %s', 'stateless-wa-commerce'), $config['singular']),
            'add_new_item' => sprintf(__('Add New %s', 'stateless-wa-commerce'), $config['singular']),
            'new_item_name' => sprintf(__('New %s Name', 'stateless-wa-commerce'), $config['singular']),
            'menu_name' => $config['plural'],
        ];

        register_taxonomy($key, 'product', [
            'hierarchical' => $config['hierarchical'],
            'labels' => $labels,
            'show_ui' => true,
            'show_admin_column' => true,
            'query_var' => true,
            'show_in_rest' => true,
            'rewrite' => ['slug' => $config['slug']],
        ]);
    }
});

// Hook into Select2 taxonomies for admin
$add_swac_select2 = function ($taxonomies) {
    if (!in_array(SWAC_TAX_SALT, $taxonomies, true)) {
        $taxonomies[] = SWAC_TAX_SALT;
    }
    if (!in_array(SWAC_TAX_PACK_SIZE, $taxonomies, true)) {
        $taxonomies[] = SWAC_TAX_PACK_SIZE;
    }
    return $taxonomies;
};
add_filter('swac_select2_taxonomies', $add_swac_select2);

// Hook into cache purge taxonomies
$add_swac_cache_purge = function ($taxonomies) {
    $taxonomies[] = SWAC_TAX_SALT;
    $taxonomies[] = SWAC_TAX_PACK_SIZE;
    $taxonomies[] = SWAC_TAX_GENERIC;
    return array_unique($taxonomies);
};
add_filter('swac_cache_purge_taxonomies', $add_swac_cache_purge);

// Hook into sitemap taxonomies
$add_swac_sitemap = function ($taxonomies) {
    $taxonomies[] = SWAC_TAX_SALT;
    $taxonomies[] = SWAC_TAX_PACK_SIZE;
    $taxonomies[] = SWAC_TAX_GENERIC;
    return array_unique($taxonomies);
};
add_filter('swac_sitemap_taxonomies', $add_swac_sitemap);
