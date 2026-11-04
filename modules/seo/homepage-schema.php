<?php
/**
 * 🏥 SEO MODULE: HOMEPAGE LOCALBUSINESS SCHEMA
 *
 * Emits a structured Schema.org LocalBusiness JSON-LD block exclusively on the homepage.
 * Sourced dynamically from WordPress site configuration.
 *
 * @package StatelessWaCommerce
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('swac_is_module_active') || !swac_is_module_active('seo')) {
    return;
}

add_action('wp_head', 'swac_seo_output_homepage_schema', 5);

if (!function_exists('swac_seo_output_homepage_schema')) {
    function swac_seo_output_homepage_schema(): void
    {
        if (!is_front_page()) {
            return;
        }

        static $schema_printed = false;
        if ($schema_printed) {
            return;
        }
        $schema_printed = true;

        $cfg = function_exists('swac_get_config') ? swac_get_config() : [];
        $site_url = home_url('/');
        $site_icon = function_exists('get_site_icon_url') ? get_site_icon_url(512) : '';
        if (empty($site_icon)) {
            $site_icon = home_url('/favicon.ico');
        }

        $store_name = $cfg['store_name'] ?? get_bloginfo('name');
        $phone = !empty($cfg['phone_number']) ? '+' . $cfg['phone_number'] : '';

        // Derive address from WooCommerce settings if available
        $country = function_exists('WC') && WC()->countries ? WC()->countries->get_base_country() : '';
        $city = function_exists('WC') && WC()->countries ? WC()->countries->get_base_city() : '';
        $address = function_exists('WC') && WC()->countries ? WC()->countries->get_base_address() : '';
        $postcode = function_exists('WC') && WC()->countries ? WC()->countries->get_base_postcode() : '';

        $schema = [
            '@context' => 'https://schema.org/',
            '@type' => 'LocalBusiness',
            'name' => $store_name,
            'url' => $site_url,
            'logo' => $site_icon,
            'image' => $site_icon,
            'telephone' => $phone,
            'address' => [
                '@type' => 'PostalAddress',
                'streetAddress' => $address,
                'addressLocality' => $city,
                'postalCode' => $postcode,
                'addressCountry' => $country,
            ],
        ];

        /**
         * Filter homepage structured schema.
         *
         * @param array $schema Schema definition array.
         */
        $schema = (array)apply_filters('swac_homepage_schema', $schema);

        if (function_exists('swac_seo_print_jsonld')) {
            swac_seo_print_jsonld($schema);
        }
    }
}
