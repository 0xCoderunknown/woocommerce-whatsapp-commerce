<?php
/**
 * 📦 PHARMACY MODULE: PACK METRICS & UNIT RESOLUTION ENGINE
 *
 * Provides pharmacological package parsing, quantitative unit metrics,
 * prescription (Rx) tagging, and generic equivalent candidate searches.
 *
 * @package StatelessWaCommerce
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('swac_is_module_active') || !swac_is_module_active('pharmacy')) {
    return;
}

if (!function_exists('swac_resolve_product_pack_size')) {
    /**
     * Resolves the pack size term and dosage form word with pluralization rules.
     *
     * @param WC_Product $product
     * @return string
     */
    function swac_resolve_product_pack_size(WC_Product $product): string
    {
        $product_id = $product->get_id();
        $pack_size_tax = defined('SWAC_TAX_PACK_SIZE') ? SWAC_TAX_PACK_SIZE : 'pack_size';
        $pack_size_terms = function_exists('swac_get_product_terms_memoized')
            ? swac_get_product_terms_memoized($product_id, $pack_size_tax)
            : get_the_terms($product_id, $pack_size_tax);

        if (empty($pack_size_terms) || is_wp_error($pack_size_terms)) {
            return '';
        }

        $term_name = trim((string)(current($pack_size_terms)->name ?? ''));
        if (empty($term_name)) {
            return '';
        }

        $title = $product->get_name();
        $resolved_size = $term_name;

        $qty = 1.0;
        if (preg_match('/^(\d+(?:\.\d+)?)/', $term_name, $num_matches)) {
            $qty = (float)$num_matches[1];
        }

        $pattern = '/\b(Capsules?|Tablets?|Syrups?|Creams?|Ointments?|Gels?|Injections?|Drops?|Suspensions?|Lotions?|Solutions?|Soaps?|Powders?|Strips?|Bottles?|Softgels?|Sachets?)\b/i';
        $has_measurement_unit = (bool)preg_match('/\d\s*(ml|g|kg|l|mg|gm|gms|oz)\b/i', $term_name);

        if (preg_match($pattern, $term_name, $term_matches)) {
            $matched_word = $term_matches[1];
            if ($qty > 1 && !$has_measurement_unit && substr(strtolower($matched_word), -1) !== 's') {
                $plural_word = $matched_word . 's';
                $resolved_size = preg_replace('/\b' . preg_quote($matched_word, '/') . '\b/i', $plural_word, $term_name);
            }
        } elseif (preg_match($pattern, $title, $matches)) {
            $form_word = ucfirst(strtolower($matches[1]));

            if ($qty > 1 && !$has_measurement_unit) {
                if (substr(strtolower($form_word), -1) !== 's') {
                    $form_word .= 's';
                }
            } else {
                if (substr(strtolower($form_word), -1) === 's' && substr(strtolower($form_word), -2) !== 'ss') {
                    $form_word = preg_replace('/s$/i', '', $form_word);
                }
            }

            $resolved_size = $term_name . ' ' . $form_word;
        }

        return (string)$resolved_size;
    }
}

if (!function_exists('swac_are_pack_units_compatible')) {
    /**
     * Assesses whether two packaging unit types are pharmacologically comparable.
     *
     * @param string $type_a
     * @param string $type_b
     * @return bool
     */
    function swac_are_pack_units_compatible(string $type_a, string $type_b): bool
    {
        if ($type_a === $type_b) {
            return true;
        }
        if (($type_a === 'unit' && $type_b === 'solid') || ($type_a === 'solid' && $type_b === 'unit')) {
            return true;
        }
        return false;
    }
}

if (!function_exists('swac_get_product_pack_metrics')) {
    /**
     * Extracts quantitative package metrics (count, unit category, normalized unit price).
     *
     * @param int|WC_Product $product
     * @return array{qty: float, unit_type: string, unit_name: string, unit_price: float}
     */
    function swac_get_product_pack_metrics($product): array
    {
        $product_id = is_numeric($product) ? (int)$product : ($product instanceof WC_Product ? $product->get_id() : 0);
        $wc_product = ($product instanceof WC_Product) ? $product : ($product_id ? wc_get_product($product_id) : null);

        $default = [
            'qty' => 1.0,
            'unit_type' => 'unit',
            'unit_name' => 'unit',
            'unit_price' => $wc_product ? (float)$wc_product->get_price() : 0.0,
        ];

        if (!$wc_product) {
            return $default;
        }

        $price = (float)$wc_product->get_price();

        // 1. Meta Override: _swac_pack_qty
        $meta_qty = (float)$wc_product->get_meta('_swac_pack_qty');
        if ($meta_qty > 0) {
            return [
                'qty' => $meta_qty,
                'unit_type' => 'unit',
                'unit_name' => 'unit',
                'unit_price' => $price / $meta_qty,
            ];
        }

        // 2. Fetch pack_size term
        $pack_size_tax = defined('SWAC_TAX_PACK_SIZE') ? SWAC_TAX_PACK_SIZE : 'pack_size';
        $pack_size_terms = function_exists('swac_get_product_terms_memoized')
            ? swac_get_product_terms_memoized($product_id, $pack_size_tax)
            : get_the_terms($product_id, $pack_size_tax);

        $term_name = (!empty($pack_size_terms) && !is_wp_error($pack_size_terms))
            ? trim((string)(current($pack_size_terms)->name ?? ''))
            : '';
        $title = $wc_product->get_name();

        $qty = 0.0;
        $unit_type = 'unit';
        $unit_name = 'unit';

        if (preg_match('/\b(\d+(?:\.\d+)?)\s*x\s*(\d+(?:\.\d+)?)\b/i', $term_name ?: $title, $mult_match)) {
            $qty = (float)$mult_match[1] * (float)$mult_match[2];
        } elseif (preg_match('/\b(?:strip|pack|box|bottle)\s+of\s+(\d+(?:\.\d+)?)/i', $term_name ?: $title, $of_match)) {
            $qty = (float)$of_match[1];
        } elseif (preg_match('/^(\d+(?:\.\d+)?)/', $term_name, $num_match)) {
            $qty = (float)$num_match[1];
        } elseif (preg_match('/\b(\d+(?:\.\d+)?)\s*\'?s\b/i', $title, $s_match)) {
            $qty = (float)$s_match[1];
        } elseif (preg_match('/\b(\d+(?:\.\d+)?)\s*(capsules?|tablets?|softgels?|sachets?|strips?|ml|gms?|g|mg)\b/i', $title, $t_match)) {
            $qty = (float)$t_match[1];
        }

        if ($qty <= 0.0) {
            $qty = 1.0;
        }

        $combined_text = strtolower($term_name . ' ' . $title);

        if (preg_match('/\b(ml|l|liter|litre|millilit|syrup|drop|suspension|solution|lotion)\b/i', $combined_text, $vol_m)) {
            $unit_type = 'volume';
            $unit_name = strtolower($vol_m[1]) === 'l' ? 'l' : 'ml';
        } elseif (preg_match('/\b(gm|gms|kg|mg|\bg\b|cream|ointment|gel|powder)\b/i', $combined_text, $wt_m)) {
            $unit_type = 'weight';
            $unit_name = strtolower($wt_m[1]);
        } elseif (preg_match('/\b(capsules?|tablets?|softgels?|sachets?|pills?|strips?)\b/i', $combined_text, $solid_m)) {
            $unit_type = 'solid';
            $unit_name = strtolower(rtrim($solid_m[1], 's'));
        }

        return [
            'qty' => $qty,
            'unit_type' => $unit_type,
            'unit_name' => $unit_name,
            'unit_price' => $price / max(1.0, $qty),
        ];
    }
}

if (!function_exists('swac_get_product_salt_name')) {
    /**
     * Retrieves the primary salt/composition name for a product.
     *
     * @param int|WC_Product $product
     * @return string
     */
    function swac_get_product_salt_name($product): string
    {
        $product_id = is_numeric($product) ? (int)$product : ($product instanceof WC_Product ? $product->get_id() : 0);
        if (!$product_id) {
            return '';
        }

        $salt_tax = defined('SWAC_TAX_SALT') ? SWAC_TAX_SALT : 'composition';
        $terms = function_exists('swac_get_product_terms_memoized')
            ? swac_get_product_terms_memoized($product_id, $salt_tax)
            : get_the_terms($product_id, $salt_tax);

        if (!empty($terms) && !is_wp_error($terms)) {
            return (string)(current($terms)->name ?? '');
        }

        return '';
    }
}

if (!function_exists('swac_is_prescription_product')) {
    /**
     * Determines whether a product requires a prescription (Rx).
     *
     * @param int|WC_Product|null $product
     * @return bool
     */
    function swac_is_prescription_product($product = null): bool
    {
        if (!function_exists('swac_is_module_active') || !swac_is_module_active('pharmacy')) {
            return false;
        }

        $product_id = 0;
        if ($product instanceof WC_Product) {
            $product_id = $product->get_id();
        } elseif (is_numeric($product) && (int)$product > 0) {
            $product_id = (int)$product;
        } else {
            $current = function_exists('swac_get_current_product') ? swac_get_current_product() : null;
            $product_id = $current ? $current->get_id() : (int)get_the_ID();
        }

        if (!$product_id) {
            return false;
        }

        $tags = function_exists('swac_get_product_terms_memoized')
            ? swac_get_product_terms_memoized($product_id, 'product_tag')
            : get_the_terms($product_id, 'product_tag');

        $has_rx_tag = false;
        $has_otc_tag = false;

        if (!empty($tags) && !is_wp_error($tags)) {
            foreach ($tags as $tag) {
                if (in_array($tag->slug, ['otc-item', 'otc-medicine', 'otc'], true)) {
                    $has_otc_tag = true;
                    break;
                }
                if (in_array($tag->slug, ['rx', 'prescription', 'prescription-required', 'schedule-h', 'schedule-h1', 'schedule-x'], true)) {
                    $has_rx_tag = true;
                }
            }
        }

        if ($has_otc_tag) {
            $is_rx = false;
        } elseif ($has_rx_tag) {
            $is_rx = true;
        } else {
            // By default, general items are never Rx unless negative exclusion is explicitly enabled
            $is_rx = (bool)apply_filters('swac_pharmacy_default_rx_mode', false);
        }

        return (bool)apply_filters('swac_is_prescription_product', $is_rx, $product_id, $product);
    }
}

// Bridge pharmacy Rx status to core cart metadata filters
$bridge_rx = function ($is_rx, $product) {
    return swac_is_prescription_product($product);
};
add_filter('swac_product_is_rx', $bridge_rx, 10, 2);

// Supply default pharmacy prescription note if pharmacy module is active and store hasn't overridden it
$supply_rx_note = function ($config) {
    if (empty($config['prescription_note'])) {
        $config['prescription_note'] = __("📋 Note: Doctor's prescription required on WhatsApp for prescription medicines (Rx).", 'stateless-wa-commerce');
    }
    return $config;
};
add_filter('swac_commerce_config', $supply_rx_note);

if (!function_exists('swac_is_generic_product')) {
    /**
     * Checks if a product is classified as generic via generic taxonomy ('gen' slug).
     *
     * @param int|WC_Product|null $product
     * @return bool
     */
    function swac_is_generic_product($product = null): bool
    {
        $product_id = 0;
        if ($product instanceof WC_Product) {
            $product_id = $product->get_id();
        } elseif (is_numeric($product) && (int)$product > 0) {
            $product_id = (int)$product;
        } else {
            $current = function_exists('swac_get_current_product') ? swac_get_current_product() : null;
            $product_id = $current ? $current->get_id() : (int)get_the_ID();
        }

        if (!$product_id) {
            return false;
        }

        $generic_tax = defined('SWAC_TAX_GENERIC') ? SWAC_TAX_GENERIC : 'generic';
        $terms = function_exists('swac_get_product_terms_memoized')
            ? swac_get_product_terms_memoized($product_id, $generic_tax)
            : get_the_terms($product_id, $generic_tax);

        if (!empty($terms) && !is_wp_error($terms)) {
            foreach ($terms as $term) {
                if ($term->slug === 'gen') {
                    return true;
                }
            }
        }

        return false;
    }
}

if (!function_exists('swac_render_generic_badge')) {
    /**
     * Renders the generic badge HTML.
     *
     * @param int|WC_Product|null $product
     * @param string $extra_class
     * @return string
     */
    function swac_render_generic_badge($product = null, string $extra_class = ''): string
    {
        if (!swac_is_generic_product($product)) {
            return '';
        }

        $class = 'swac-generic-badge';
        if (!empty($extra_class)) {
            $class .= ' ' . trim($extra_class);
        }

        return '<span class="' . esc_attr($class) . '">' . esc_html__('GENERIC', 'stateless-wa-commerce') . '</span>';
    }
}

// Repurpose loop sale flash position for generic medicines
add_action('woocommerce_before_shop_loop_item_title', function () {
    if (is_front_page() || is_home()) {
        return;
    }

    global $product;
    if ($product instanceof WC_Product) {
        if (function_exists('swac_render_generic_badge')) {
            echo swac_render_generic_badge($product);
        }
    }
}, 9);

if (!function_exists('swac_find_generic_alternate')) {
    /**
     * Finds the cheapest equivalent generic alternate product for a branded product.
     *
     * @param int $product_id
     * @return WP_Post|null
     */
    function swac_find_generic_alternate(int $product_id): ?WP_Post
    {
        $cache_key = 'swac_gen_alt_' . $product_id;
        $cached = get_transient($cache_key);
        if (false !== $cached) {
            return $cached ? get_post((int)$cached) : null;
        }

        if (swac_is_generic_product($product_id)) {
            set_transient($cache_key, 0, 43200);
            return null;
        }

        $cat_terms = function_exists('swac_get_product_terms_memoized')
            ? swac_get_product_terms_memoized($product_id, 'product_cat')
            : get_the_terms($product_id, 'product_cat');

        if (empty($cat_terms) || is_wp_error($cat_terms)) {
            set_transient($cache_key, 0, 300);
            return null;
        }
        $category_id = current($cat_terms)->term_id;

        $salt_tax = defined('SWAC_TAX_SALT') ? SWAC_TAX_SALT : 'composition';
        $salt_terms = function_exists('swac_get_product_terms_memoized')
            ? swac_get_product_terms_memoized($product_id, $salt_tax)
            : get_the_terms($product_id, $salt_tax);

        $salt_term_id = (!empty($salt_terms) && !is_wp_error($salt_terms)) ? current($salt_terms)->term_id : null;

        $generic_tax = defined('SWAC_TAX_GENERIC') ? SWAC_TAX_GENERIC : 'generic';
        $generic_term = get_term_by('slug', 'gen', $generic_tax);
        if (!$generic_term || is_wp_error($generic_term)) {
            set_transient($cache_key, 0, 300);
            return null;
        }

        $tax_query = [
            'relation' => 'AND',
            ['taxonomy' => $generic_tax, 'field' => 'term_id', 'terms' => $generic_term->term_id],
            ['taxonomy' => 'product_cat', 'field' => 'term_id', 'terms' => $category_id],
        ];

        if ($salt_term_id) {
            $tax_query[] = ['taxonomy' => $salt_tax, 'field' => 'term_id', 'terms' => $salt_term_id];
        }

        $query = new WP_Query([
            'post_type' => 'product',
            'posts_per_page' => 5,
            'post__not_in' => [$product_id],
            'tax_query' => $tax_query,
            'meta_query' => [
                ['key' => '_stock_status', 'value' => 'instock', 'compare' => '='],
            ],
            'orderby' => 'meta_value_num',
            'meta_key' => '_price',
            'order' => 'ASC',
            'fields' => 'ids',
            'no_found_rows' => true,
        ]);

        if (empty($query->posts)) {
            set_transient($cache_key, 0, 300);
            return null;
        }

        $source_product = wc_get_product($product_id);
        if (!$source_product) {
            set_transient($cache_key, 0, 300);
            return null;
        }

        $source_metrics = swac_get_product_pack_metrics($source_product);
        $source_unit_price = (float)$source_metrics['unit_price'];

        if ($source_unit_price <= 0.0) {
            set_transient($cache_key, 0, 300);
            return null;
        }

        $best_candidate_id = null;
        $lowest_candidate_unit_price = INF;

        foreach ($query->posts as $cand_post_id) {
            $cand_id = (int)$cand_post_id;
            $candidate_product = wc_get_product($cand_id);
            if (!$candidate_product) {
                continue;
            }

            $cand_metrics = swac_get_product_pack_metrics($candidate_product);
            $cand_unit_price = (float)$cand_metrics['unit_price'];

            if ($cand_unit_price <= 0.0) {
                continue;
            }

            if (!swac_are_pack_units_compatible($source_metrics['unit_type'], $cand_metrics['unit_type'])) {
                continue;
            }

            if ($cand_unit_price >= ($source_unit_price - 0.001)) {
                continue;
            }

            if ($cand_unit_price < $lowest_candidate_unit_price) {
                $lowest_candidate_unit_price = $cand_unit_price;
                $best_candidate_id = $cand_id;
            }
        }

        if (!$best_candidate_id) {
            set_transient($cache_key, 0, 300);
            return null;
        }

        set_transient($cache_key, $best_candidate_id, 43200);
        return get_post($best_candidate_id);
    }
}
