<?php
/**
 * UNINSTALL: Stateless WhatsApp Commerce for WooCommerce
 *
 * Runs when the plugin is deleted via the WordPress admin.
 * Removes all plugin-created options and transients from the database.
 *
 * @package StatelessWaCommerce
 */

// Safety check - WordPress must trigger this, not direct access.
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// 1. Remove the explore module cache version counter.
delete_option('swac_explore_cache_version');

// 2. Remove any swac_* transients left by the explore/pharmacy modules.
//    Transients expire naturally, but this gives a clean slate on uninstall.
global $wpdb;

$wpdb->query(
    "DELETE FROM {$wpdb->options}
     WHERE option_name LIKE '_transient_swac_%'
        OR option_name LIKE '_transient_timeout_swac_%'
",
);

// 3. Remove the cron lockdown flag.
delete_option('swac_cron_cleaned');

// 4. Remove wholesale desk options and credentials.
delete_option('swac_b2b_approved_retailers');
delete_option('swac_b2b_master_pin');
delete_option('swac_b2b_staff_pin');
delete_option('swac_b2b_salesman_pin');
delete_option('swac_b2b_desk_tokens');
delete_option('swac_b2b_master_hash'); // Clean legacy single token if present

// 5. Remove store setting overrides.
delete_option('swac_store_phone');
delete_option('swac_store_name');
delete_option('swac_city_name');
delete_option('swac_free_shipping_at');
delete_option('swac_shipping_charge');
delete_option('swac_order_disclaimer');

