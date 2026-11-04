<?php
/**
 * 🏷️ SEO MODULE: TAXONOMY ARCHIVE METADATA & OPENGRAPH
 *
 * Generates SEO titles, meta descriptions, and OpenGraph tags for archive pages
 * across custom and native WooCommerce taxonomies.
 *
 * @package StatelessWaCommerce
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('swac_is_module_active') || !swac_is_module_active('seo')) {
    return;
}

if (!function_exists('swac_seo_is_supported_taxonomy')) {
    function swac_seo_is_supported_taxonomy(): bool
    {
        return is_tax() || is_category() || (function_exists('is_product_category') && is_product_category()) || (function_exists('is_product_taxonomy') && is_product_taxonomy());
    }
}

if (!function_exists('swac_seo_get_taxonomy_title')) {
    function swac_seo_get_taxonomy_title(string $title = ''): string
    {
        if (!swac_seo_is_supported_taxonomy()) {
            return $title;
        }

        $queried_object = get_queried_object();
        if ($queried_object && isset($queried_object->name)) {
            $term_name = single_term_title('', false) ?: $queried_object->name;
            $taxonomy = $queried_object->taxonomy ?? '';
            $tax_obj = get_taxonomy($taxonomy);
            $label = $tax_obj ? ($tax_obj->labels->singular_name ?: $tax_obj->label) : '';

            $store_name = get_bloginfo('name') ?: 'Store';

            $formatted_title = !empty($label)
                ? sprintf('%s %s | %s', $term_name, $label, $store_name)
                : sprintf('%s | %s', $term_name, $store_name);

            if (mb_strlen($formatted_title) > 60) {
                return mb_substr($formatted_title, 0, 57) . '...';
            }
            return $formatted_title;
        }

        return $title;
    }
}

add_filter('pre_get_document_title', 'swac_seo_get_taxonomy_title', 99);

if (!function_exists('swac_seo_get_taxonomy_description')) {
    function swac_seo_get_taxonomy_description(): string
    {
        if (!swac_seo_is_supported_taxonomy()) {
            return '';
        }

        $queried_object = get_queried_object();
        if ($queried_object && isset($queried_object->term_id)) {
            $raw_desc = term_description($queried_object->term_id, $queried_object->taxonomy);
            $clean_desc = (!empty($raw_desc) && !is_wp_error($raw_desc))
                ? trim(strip_tags((string)$raw_desc))
                : '';

            if (!empty($clean_desc)) {
                if (mb_strlen($clean_desc) > 155) {
                    return mb_substr($clean_desc, 0, 152) . '...';
                }
                return $clean_desc;
            }

            $term_name = single_term_title('', false) ?: $queried_object->name;
            $store_name = get_bloginfo('name') ?: 'Store';
            $loc_suffix = (string)apply_filters('swac_seo_location_suffix', '');

            if (!empty($loc_suffix)) {
                return sprintf(
                /* translators: 1: term name, 2: location suffix, 3: store name */
                    __('Browse %1$s online in %2$s with direct WhatsApp ordering at %3$s.', 'stateless-wa-commerce'),
                    $term_name,
                    $loc_suffix,
                    $store_name
                );
            }

            return sprintf(
            /* translators: 1: term name, 2: store name */
                __('Browse %1$s online with direct WhatsApp ordering at %2$s.', 'stateless-wa-commerce'),
                $term_name,
                $store_name
            );
        }

        return '';
    }
}

add_action('wp_head', function () {
    if (!swac_seo_is_supported_taxonomy()) {
        return;
    }

    $description = swac_seo_get_taxonomy_description();
    $title = swac_seo_get_taxonomy_title();
    $store_name = get_bloginfo('name') ?: 'Store';

    if (!empty($description)) {
        echo '<meta name="description" content="' . esc_attr($description) . '">' . "\n";
        echo '<meta property="og:description" content="' . esc_attr($description) . '">' . "\n";
    }

    echo '<meta property="og:title" content="' . esc_attr($title) . '">' . "\n";
    echo '<meta property="og:site_name" content="' . esc_attr($store_name) . '">' . "\n";
}, 1);
