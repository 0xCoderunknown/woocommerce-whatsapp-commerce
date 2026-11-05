<?php
/**
 * Plugin Name: Stateless WhatsApp Commerce for WooCommerce
 * Description: Zero-session catalog lockdown and client-side WhatsApp commerce engine for WooCommerce. Serving dynamic catalogs from full-page static cache.
 * Version: 1.7.3
 * Author: 0xCoderunknown
 * License: GPL-2.0-or-later
 * Text Domain: stateless-wa-commerce
 * Domain Path: /languages
 * Requires at least: 6.2
 * Requires PHP: 8.2
 * WC requires at least: 8.0
 * WC tested up to: 11.1
 *
 * @package StatelessWaCommerce
 */

if (!defined('ABSPATH')) {
    exit;
}

// 🗺️ REWRITE RULES: ACTIVATION & DEACTIVATION HOOKS
register_activation_hook(__FILE__, function () {
    add_rewrite_rule('^sitemap\.xml$', 'index.php?swac_sitemap=1', 'top');
    flush_rewrite_rules();
});

register_deactivation_hook(__FILE__, function () {
    flush_rewrite_rules();
});

// 🛍️ DECLARE HPOS (High-Performance Order Storage) & CART/CHECKOUT BLOCKS COMPATIBILITY
// Must be declared in the root plugin file early via before_woocommerce_init hook
add_action('before_woocommerce_init', function () {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
    }
});

// 🔧 BOOTSTRAP CONFIGURATION & TECHNICAL PRIMITIVES
require_once plugin_dir_path(__FILE__) . 'core/config.php';

// ⚙️ DEVELOPER FEATURE FLAGS (human-editable — see features.php)
require_once plugin_dir_path(__FILE__) . 'features.php';

/**
 * 🌐 LOAD PLUGIN TEXTDOMAIN
 */
add_action('init', function () {
    load_plugin_textdomain('stateless-wa-commerce', false, dirname(plugin_basename(__FILE__)) . '/languages');
});

// (Admin notices → backend/admin-tools.php)

// 📂 CORE SUBSYSTEM
require_once SWAC_PATH . 'core/helpers.php';
require_once SWAC_PATH . 'core/pricing-engine.php';
require_once SWAC_PATH . 'core/delivery-cutoff.php';
require_once SWAC_PATH . 'core/delivery-zones.php';
require_once SWAC_PATH . 'core/catalog-lockdown.php';
require_once SWAC_PATH . 'core/cache-manager.php';

// 📂 INTEGRATIONS
require_once SWAC_PATH . 'integrations/ssl-security.php';
require_once SWAC_PATH . 'integrations/sitemap-generator.php';

// 📂 FRONTEND COMPONENTS
require_once SWAC_PATH . 'frontend/archive/archive-loop.php';
require_once SWAC_PATH . 'frontend/components/whatsapp-button.php';
require_once SWAC_PATH . 'frontend/components/cart-drawer.php';
require_once SWAC_PATH . 'frontend/single/product-meta.php';
require_once SWAC_PATH . 'frontend/single/notices.php';
require_once SWAC_PATH . 'frontend/single/delivery-notice.php';
require_once SWAC_PATH . 'frontend/single/single-whatsapp.php';
require_once SWAC_PATH . 'frontend/enqueue.php';

// 📂 BACKEND UTILITIES
if (is_admin()) {
    require_once SWAC_PATH . 'backend/admin-tools.php';
    require_once SWAC_PATH . 'backend/product-editor.php';
    require_once SWAC_PATH . 'backend/taxonomy-ui.php';
}

// 📂 MODULAR SUBSYSTEMS (Feature Flagged & Folder-Safe)

// 1. Pharmacy Module
if (swac_is_module_active('pharmacy') && is_dir(SWAC_PATH . 'modules/pharmacy')) {
    $pharmacy_files = [
        'modules/pharmacy/register-taxonomies.php',
        'modules/pharmacy/pack-metrics.php',
        'modules/pharmacy/generic-upsell.php',
        'modules/pharmacy/explore-salts.php',
        'modules/pharmacy/product-meta.php',
        'modules/pharmacy/size-charts.php',
    ];
    foreach ($pharmacy_files as $file) {
        if (file_exists(SWAC_PATH . $file)) {
            require_once SWAC_PATH . $file;
        }
    }
}

// 2. Directory Exploration Module
if (swac_is_module_active('explore') && is_dir(SWAC_PATH . 'modules/explore')) {
    $explore_files = [
        'modules/explore/card-template.php',
        'modules/explore/brands.php',
        'modules/explore/categories.php',
    ];
    foreach ($explore_files as $file) {
        if (file_exists(SWAC_PATH . $file)) {
            require_once SWAC_PATH . $file;
        }
    }
}

// 3. Wholesale / B2B Schemes Module
if (swac_is_module_active('wholesale') && is_dir(SWAC_PATH . 'modules/wholesale')) {
    $wholesale_files = [
        'modules/wholesale/product-meta.php',
        'modules/wholesale/shop-card.php',
        'modules/wholesale/retailer-auth.php',
        'modules/wholesale/login-modal.php',
        'modules/wholesale/frontend-desk.php',
        'modules/wholesale/walled-garden.php',
    ];
    foreach ($wholesale_files as $file) {
        if (file_exists(SWAC_PATH . $file)) {
            require_once SWAC_PATH . $file;
        }
    }
}

// 4. Native SEO Module
if (swac_is_module_active('seo') && is_dir(SWAC_PATH . 'modules/seo')) {
    $seo_files = [
        'modules/seo/homepage-schema.php',
        'modules/seo/product-seo.php',
        'modules/seo/taxonomy-seo.php',
    ];
    foreach ($seo_files as $file) {
        if (file_exists(SWAC_PATH . $file)) {
            require_once SWAC_PATH . $file;
        }
    }
}

// (Frontend asset enqueue → frontend/enqueue.php)

