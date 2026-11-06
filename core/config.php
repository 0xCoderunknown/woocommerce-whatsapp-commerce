<?php
/**
 * 🏪 STORE CONFIGURATION — Stateless WhatsApp Commerce
 * ─────────────────────────────────────────────────────
 *
 * This is one of TWO files you need to know about:
 *
 *   core/config.php   ← you are here  (store: phone, name, shipping, etc.)
 *   features.php      ← plugin behaviour  (what gets turned on/off)
 *
 * Set your values below. Save. Done.
 * Everything below the ⚠️ warning line is engine code — leave it alone.
 *
 * @package StatelessWaCommerce
 */

if (!defined('ABSPATH')) {
    exit;
}

// ═══════════════════════════════════════════════════════════════════════════════
// YOUR STORE
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * WhatsApp phone number (required).
 *
 * E.164 format without the '+'. Country code + number. No spaces or dashes.
 *
 * Example: India +91 98765 43210  →  '919876543210'
 *          US    +1  555 123 4567 →  '15551234567'
 */
define('SWAC_PHONE', '');

/**
 * Store display name.
 *
 * Used in WhatsApp order messages: "Order from <name>".
 * Leave empty to use your WordPress site title automatically.
 */
define('SWAC_STORE_NAME', '');

/**
 * City / geographic focus  (optional).
 *
 * Shown in delivery messaging. Leave empty for no city reference.
 * Example: 'Mumbai', 'New York'
 */
define('SWAC_CITY_NAME', '');


// ─────────────────────────────────────────────────────────────────────────────
// SHIPPING
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Free shipping threshold.
 *
 * Order value at which shipping becomes free (in your store currency).
 * Set to 0.0 if you always charge shipping.
 * Example: 500.0 → free shipping on orders above 500
 */
define('SWAC_FREE_SHIPPING_AT', 0.0);

/**
 * Flat shipping charge.
 *
 * Applied when the order total is below SWAC_FREE_SHIPPING_AT.
 * Set to 0.0 if you never charge shipping.
 */
define('SWAC_SHIPPING_CHARGE', 0.0);

/**
 * Postal codes eligible for always-free shipping regardless of cart total.
 *
 * Array of postal code strings. Defaults to empty array [].
 * Example: ['781001', '781005', '781006']
 */
define('SWAC_ALWAYS_FREE_SHIPPING_POSTAL_CODES', []);


// ─────────────────────────────────────────────────────────────────────────────
// ORDER LIMITS
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Maximum distinct items in a single WhatsApp order.
 *
 * Prevents the WhatsApp URL from exceeding browser length limits (~2000 chars).
 * Recommended: 10–15. Hard maximum: 50.
 */
define('SWAC_MAX_CART_ITEMS', 10);

/**
 * Maximum quantity per individual item.
 *
 * Soft retail anti-bulk limit applied per line item.
 */
define('SWAC_MAX_QTY_PER_ITEM', 10);


// ─────────────────────────────────────────────────────────────────────────────
// ORDER DISCLAIMER
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Text appended to every WhatsApp order message.
 *
 * Reminds the customer that final price and availability are confirmed on WhatsApp.
 * Leave empty to disable the disclaimer entirely.
 */
define('SWAC_ORDER_DISCLAIMER', '⚡ Final bill & product availability will be confirmed on WhatsApp.');


// ─────────────────────────────────────────────────────────────────────────────
// LOYALTY REWARD  (optional cart incentive)
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Enable a loyalty reward offer in the cart drawer.
 *
 * When the cart value crosses SWAC_LOYALTY_THRESHOLD, the customer is shown
 * an offer for a free or discounted reward item.
 */
define('SWAC_LOYALTY_ENABLED', false);

/** Cart value (in store currency) that triggers the loyalty offer. */
define('SWAC_LOYALTY_THRESHOLD', 0);

/** Display label for the reward item shown to the customer. Example: 'Free Lip Balm' */
define('SWAC_LOYALTY_ITEM_LABEL', '');

/** Monetary worth of the reward item (shown for display purposes). */
define('SWAC_LOYALTY_ITEM_WORTH', 0);

/** Price charged for the reward item. Use 0 for a fully free reward. */
define('SWAC_LOYALTY_ITEM_PRICE', 0);

/** Text appended to the WhatsApp order when the loyalty offer is accepted. */
define('SWAC_LOYALTY_FLAG', 'OFFER ACCEPTED ✅');


// ═══════════════════════════════════════════════════════════════════════════════
// ⚠️  ENGINE — DO NOT EDIT BELOW THIS LINE
// ───────────────────────────────────────────────────────────────────────────────
// Plugin infrastructure: path constants, configuration resolver, module checker.
// Editing anything here without understanding the codebase will break the plugin.
//
// Your store settings live above.
// Feature on/off toggles live in features.php.
// ═══════════════════════════════════════════════════════════════════════════════

if (!defined('SWAC_PATH')) {
    define('SWAC_PATH', plugin_dir_path(dirname(__FILE__)));
}
if (!defined('SWAC_URL')) {
    define('SWAC_URL', plugin_dir_url(dirname(__FILE__)));
}
if (!defined('SWAC_VERSION')) {
    define('SWAC_VERSION', '1.7.5');
}

if (!function_exists('swac_get_config')) {
    /**
     * Assemble and return the resolved plugin configuration array.
     *
     * Reads from wp_options database settings with fallback to constants defined above.
     * Applies the swac_commerce_config filter for programmatic overrides.
     *
     * @param bool $refresh Force reload configuration without using static cache.
     * @return array<string, mixed>
     */
    function swac_get_config(bool $refresh = false): array
    {
        static $resolved_config = null;

        if ($resolved_config !== null && !$refresh) {
            return $resolved_config;
        }

        $currency_symbol = function_exists('get_woocommerce_currency_symbol')
            ? get_woocommerce_currency_symbol()
            : '$';

        $db_store_name = get_option('swac_store_name', null);
        $store_name = ($db_store_name !== null && $db_store_name !== '')
            ? (string)$db_store_name
            : (SWAC_STORE_NAME !== '' ? SWAC_STORE_NAME : (get_bloginfo('name') ?: 'Store'));

        $db_phone = get_option('swac_store_phone', null);
        $phone_number = ($db_phone !== null && $db_phone !== '') ? (string)$db_phone : SWAC_PHONE;

        $db_city = get_option('swac_city_name', null);
        $city_name = ($db_city !== null && $db_city !== '') ? (string)$db_city : SWAC_CITY_NAME;

        $db_free_ship = get_option('swac_free_shipping_at', null);
        $free_shipping_at = $db_free_ship !== null ? (float)$db_free_ship : (float)SWAC_FREE_SHIPPING_AT;

        $db_ship_charge = get_option('swac_shipping_charge', null);
        $shipping_charge = $db_ship_charge !== null ? (float)$db_ship_charge : (float)SWAC_SHIPPING_CHARGE;

        $db_disclaimer = get_option('swac_order_disclaimer', null);
        $order_disclaimer = $db_disclaimer !== null ? (string)$db_disclaimer : SWAC_ORDER_DISCLAIMER;

        $defaults = [
            'phone_number' => $phone_number,
            'store_name' => $store_name,
            'base_url' => '',
            'free_shipping_at' => $free_shipping_at,
            'shipping_charge' => $shipping_charge,
            'always_free_shipping_postal_codes' => (array)(defined('SWAC_ALWAYS_FREE_SHIPPING_POSTAL_CODES') ? SWAC_ALWAYS_FREE_SHIPPING_POSTAL_CODES : []),
            'currency_symbol' => $currency_symbol,
            'city_name' => $city_name,
            'max_cart_items' => (int)SWAC_MAX_CART_ITEMS,
            'max_qty_per_item' => (int)SWAC_MAX_QTY_PER_ITEM,
            'order_disclaimer' => $order_disclaimer,
            // Prescription note: pharmacy module concern — set via swac_commerce_config filter when pharmacy module is active.
            'prescription_note_enabled' => false,
            'prescription_note' => '',
            'loyalty' => [
                'enabled' => (bool)SWAC_LOYALTY_ENABLED,
                'threshold' => (int)SWAC_LOYALTY_THRESHOLD,
                'item_label' => SWAC_LOYALTY_ITEM_LABEL,
                'item_worth' => (int)SWAC_LOYALTY_ITEM_WORTH,
                'item_price' => (int)SWAC_LOYALTY_ITEM_PRICE,
                'flag' => SWAC_LOYALTY_FLAG,
            ],
            'modules' => [], // Controlled exclusively by features.php — do not set here.
        ];

        /** @filter swac_commerce_config Programmatic override for store configuration. */
        $config = apply_filters('swac_commerce_config', $defaults);

        if (empty($config['base_url']) && !empty($config['phone_number'])) {
            $config['base_url'] = 'https://api.whatsapp.com/send?phone='
                . preg_replace('/[^0-9]/', '', (string)$config['phone_number']);
        }

        $resolved_config = $config;
        return $resolved_config;
    }
}

if (!function_exists('swac_is_module_active')) {
    /**
     * Check if a specific subsystem module is enabled.
     *
     * @param string $module Module key ('pharmacy', 'explore', 'seo').
     * @return bool
     */
    function swac_is_module_active(string $module): bool
    {
        $config = swac_get_config();
        $active_modules = apply_filters('swac_active_modules', $config['modules'] ?? []);
        return in_array($module, (array)$active_modules, true);
    }
}
