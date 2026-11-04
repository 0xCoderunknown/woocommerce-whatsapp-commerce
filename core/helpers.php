<?php
/**
 * 🛠️ CORE HELPERS & UTILITIES
 *
 * Provides shared, vendor-neutral helper functions for telephone normalization,
 * WhatsApp URL building, currency formatting, term resolution, and postal code sanitization.
 *
 * @package StatelessWaCommerce
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('swac_get_currency_symbol')) {
    /**
     * Resolves the configured or active WooCommerce currency symbol.
     *
     * @return string
     */
    function swac_get_currency_symbol(): string
    {
        $cfg = function_exists('swac_get_config') ? swac_get_config() : [];
        if (!empty($cfg['currency_symbol'])) {
            return (string)$cfg['currency_symbol'];
        }
        if (function_exists('get_woocommerce_currency_symbol')) {
            $wc_symbol = get_woocommerce_currency_symbol();
            if (!empty($wc_symbol)) {
                return $wc_symbol;
            }
        }
        return '₹';
    }
}

if (!function_exists('swac_clean_pincode')) {
    /**
     * Strips non-digit characters from postal code strings.
     *
     * @param mixed $pincode Raw postal code.
     * @return string
     */
    function swac_clean_pincode($pincode): string
    {
        return preg_replace('/\D/', '', (string)($pincode ?? ''));
    }
}

if (!function_exists('swac_sanitize_phone')) {
    /**
     * Sanitizes raw telephone input into clean numeric digits.
     *
     * @param mixed $phone Raw phone string.
     * @return string
     */
    function swac_sanitize_phone($phone): string
    {
        return preg_replace('/[^0-9]/', '', (string)($phone ?? ''));
    }
}

if (!function_exists('swac_build_whatsapp_url')) {
    /**
     * Builds a canonical WhatsApp direct URL, ensuring query string delimiters ('?' vs '&')
     * are correctly resolved whether the base URL already contains query parameters or not.
     *
     * @param string $message The unencoded message text to pre-fill.
     * @param string|null $base_url Optional explicit base URL. Defaults to configured base_url.
     * @return string Fully assembled, URL-encoded WhatsApp link (or empty string if no base URL).
     */
    function swac_build_whatsapp_url(string $message = '', ?string $base_url = null): string
    {
        if ($base_url === null) {
            $cfg = function_exists('swac_get_config') ? swac_get_config() : [];
            $base_url = !empty($cfg['base_url']) ? (string)$cfg['base_url'] : '';
        }

        $base_url = trim($base_url);
        if (empty($base_url)) {
            return '';
        }

        $clean_message = str_replace(["\r\n", "\r"], "\n", $message);
        $encoded_message = rawurlencode($clean_message);

        $separator = (strpos($base_url, '?') !== false) ? '&' : '?';

        return $base_url . $separator . 'text=' . $encoded_message;
    }
}

if (!function_exists('swac_icon_svg')) {
    /**
     * Returns the standardized WhatsApp SVG icon.
     *
     * @return string
     */
    function swac_icon_svg(): string
    {
        $default_svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="20" height="20" aria-hidden="true">
            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L0 24l6.335-1.662c1.72.937 3.658 1.43 5.63 1.432h.006c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/>
        </svg>';

        return (string)apply_filters('swac_cart_icon_svg', $default_svg);
    }
}

if (!function_exists('swac_get_current_product')) {
    /**
     * Resolves the current WC_Product instance in templates and shortcodes.
     * Falls back to get_queried_object_id() for visual page builders.
     *
     * @return WC_Product|null
     */
    function swac_get_current_product(): ?WC_Product
    {
        global $product;
        if (!$product instanceof WC_Product) {
            $product_id = get_queried_object_id();
            if ($product_id) {
                $product = wc_get_product($product_id);
            }
        }
        return ($product instanceof WC_Product) ? $product : null;
    }
}

if (!function_exists('swac_get_product_terms_memoized')) {
    /**
     * In-memory request-level cache for get_the_terms() lookups.
     *
     * @param int|WC_Product $product Product ID or WC_Product instance.
     * @param string $taxonomy Taxonomy identifier.
     * @return array Array of term objects, or empty array if none found.
     */
    function swac_get_product_terms_memoized($product, string $taxonomy): array
    {
        static $terms_cache = [];
        $product_id = is_numeric($product) ? (int)$product : ($product instanceof WC_Product ? $product->get_id() : 0);
        if (!$product_id) {
            return [];
        }

        $cache_key = $product_id . ':' . $taxonomy;
        if (!array_key_exists($cache_key, $terms_cache)) {
            $terms = get_the_terms($product_id, $taxonomy);
            $terms_cache[$cache_key] = (!empty($terms) && !is_wp_error($terms)) ? $terms : [];
        }

        return $terms_cache[$cache_key];
    }
}

if (!function_exists('swac_get_term_initial')) {
    /**
     * Extracts a clean uppercase initial character from a term name for monogram badges.
     *
     * @param string|null $name Term name.
     * @return string Single uppercase character, or '#' if not alphanumeric.
     */
    function swac_get_term_initial(?string $name = ''): string
    {
        $clean = trim(html_entity_decode((string)($name ?? ''), ENT_QUOTES, 'UTF-8'));
        $char = mb_strtoupper(mb_substr($clean, 0, 1, 'UTF-8'), 'UTF-8');
        return $char !== '' ? $char : '#';
    }
}
