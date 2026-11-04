<?php
/**
 * 🎨 FRONTEND ASSET ENQUEUE
 *
 * Registers and enqueues all plugin stylesheets and scripts.
 * Localizes runtime configuration (swacCommerce) and delivery zone
 * data (swacZones) for the WhatsApp cart engine (wa_cart.js).
 *
 * @package StatelessWaCommerce
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('wp_enqueue_scripts', function () {
    // 1. Core Stylesheets
    wp_enqueue_style('swac-frontend-style', SWAC_URL . 'assets/css/frontend.css', [], SWAC_VERSION, 'all');
    wp_enqueue_style('swac-cart-drawer-style', SWAC_URL . 'assets/css/cart-drawer.css', ['swac-frontend-style'], SWAC_VERSION, 'all');

    // 2. Modular Subsystem Stylesheets (Enqueued conditionally if active and present)
    if (swac_is_module_active('pharmacy') && file_exists(SWAC_PATH . 'assets/css/modules/pharmacy.css')) {
        wp_enqueue_style('swac-pharmacy-style', SWAC_URL . 'assets/css/modules/pharmacy.css', ['swac-frontend-style'], SWAC_VERSION, 'all');
    }

    if (swac_is_module_active('explore') && file_exists(SWAC_PATH . 'assets/css/modules/explore.css')) {
        wp_register_style('swac-explore-style', SWAC_URL . 'assets/css/modules/explore.css', ['swac-frontend-style'], SWAC_VERSION, 'all');
    }

    // 3. Explore Search JS (registered for on-demand enqueue by shortcodes)
    wp_register_script('swac-frontend-script', SWAC_URL . 'assets/js/frontend.js', [], SWAC_VERSION, [
        'strategy' => 'defer',
        'in_footer' => true,
    ]);

    // 4. WhatsApp Cart Engine & Submodules (delivery-engine.js, cart-math.js, order-serializer.js, wa_cart.js)
    if (!is_admin()) {
        wp_enqueue_script('swac-delivery-engine', SWAC_URL . 'assets/js/delivery-engine.js', [], SWAC_VERSION, [
            'strategy' => 'defer',
            'in_footer' => true,
        ]);
        wp_enqueue_script('swac-cart-math', SWAC_URL . 'assets/js/cart-math.js', ['swac-delivery-engine'], SWAC_VERSION, [
            'strategy' => 'defer',
            'in_footer' => true,
        ]);
        wp_enqueue_script('swac-order-serializer', SWAC_URL . 'assets/js/order-serializer.js', ['swac-cart-math'], SWAC_VERSION, [
            'strategy' => 'defer',
            'in_footer' => true,
        ]);
        wp_enqueue_script('swac-cart-script', SWAC_URL . 'assets/js/wa_cart.js', [
            'swac-delivery-engine',
            'swac-cart-math',
            'swac-order-serializer',
        ], SWAC_VERSION, [
            'strategy' => 'defer',
            'in_footer' => true,
        ]);

        $cfg = swac_get_config();
        $zones = swac_get_delivery_zones();
        $same_day_pins = [];
        $outskirts_pins = [];
        if (!empty($zones['zones']) && is_array($zones['zones'])) {
            foreach ($zones['zones'] as $zone_tier) {
                if (!empty($zone_tier['pincodes']) && is_array($zone_tier['pincodes'])) {
                    $pins = array_map('strval', array_keys($zone_tier['pincodes']));
                    if (!empty($zone_tier['allow_order'])) {
                        $same_day_pins = array_merge($same_day_pins, $pins);
                    } else {
                        $outskirts_pins = array_merge($outskirts_pins, $pins);
                    }
                }
            }
        }

        $always_free_pins = $cfg['always_free_shipping_postal_codes']
            ?? (defined('SWAC_ALWAYS_FREE_SHIPPING_POSTAL_CODES') ? SWAC_ALWAYS_FREE_SHIPPING_POSTAL_CODES : []);
        $always_free_pins = (array)apply_filters('swac_always_free_shipping_postal_codes', $always_free_pins, null);
        $always_free_codes = array_values(array_unique(array_filter(array_map(function ($c) {
            return function_exists('swac_clean_pincode') ? swac_clean_pincode($c) : preg_replace('/\D/', '', (string)$c);
        }, $always_free_pins))));

        $localized_zones = [
            'gated' => !empty($zones['gated']),
            'same_day_pins' => array_values(array_unique($same_day_pins)),
            'outskirts_pins' => array_values(array_unique($outskirts_pins)),
            'always_free_shipping_codes' => $always_free_codes,
            'messages' => $zones['messages'] ?? [],
        ];

        $currency_symbol = function_exists('swac_get_currency_symbol')
            ? swac_get_currency_symbol()
            : ($cfg['currency_symbol'] ?? '₹');

        $localized_config = [
            'number' => $cfg['phone_number'],
            'name' => $cfg['store_name'],
            'baseUrl' => $cfg['base_url'],
            'cityName' => $cfg['city_name'],
            'currencySymbol' => $currency_symbol,
            'free_shipping_at' => $cfg['free_shipping_at'],
            'shipping_charge' => $cfg['shipping_charge'],
            'always_free_shipping_codes' => $always_free_codes,
            'rest_url' => esc_url_raw(rest_url()),
            'is_admin' => current_user_can('manage_woocommerce'),
            'prescription_note_enabled' => (bool)$cfg['prescription_note_enabled'],
            'prescription_note' => $cfg['prescription_note'],
            'max_cart_items' => (int)($cfg['max_cart_items'] ?? 10),
            'max_qty_per_item' => (int)($cfg['max_qty_per_item'] ?? 10),
            'loyalty' => $cfg['loyalty'],
            'timezone_offset' => function_exists('wp_timezone') ? (float)((wp_timezone()->getOffset(new DateTime('now', new DateTimeZone('UTC')))) / 3600) : 0.0,
            'cutoff_messages' => [
                'weekend' => __('Order now to get it by Monday', 'stateless-wa-commerce'),
                'morning' => __('Order before 12 noon for same-day delivery', 'stateless-wa-commerce'),
                'afternoon' => __('Order now to receive it by tomorrow', 'stateless-wa-commerce'),
            ],
        ];

        // Localize runtime configuration and delivery zones
        wp_localize_script('swac-cart-script', 'swacZones', $localized_zones);
        wp_localize_script('swac-cart-script', 'swacCommerce', $localized_config);
        wp_localize_script('swac-cart-script', 'swacConfig', $localized_config);
    }
});

/**
 * Output dns-prefetch resource hints for WhatsApp gateways (api.whatsapp.com & wa.me).
 */
add_action('wp_head', function () {
    echo '<link rel="dns-prefetch" href="https://api.whatsapp.com">' . "\n";
    echo '<link rel="dns-prefetch" href="https://wa.me">' . "\n";
}, 1);
