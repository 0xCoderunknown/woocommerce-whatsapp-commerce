<?php
/**
 * 🏰 WHOLESALE / B2B MODULE: SHIKHAR-STYLE WALLED GARDEN GATE
 *
 * Provides a 100% private, invite-only B2B portal experience for stockists and distributors.
 * The entire catalog (products, prices, search, and drawer) is shielded behind mobile + 4-digit PIN authentication.
 * Fast inline script in <head> prevents layout flashes for returning authenticated retailers.
 *
 * @package StatelessWaCommerce
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('swac_is_module_active') || !swac_is_module_active('wholesale')) {
    return;
}

if (!function_exists('swac_get_catalog_mode') || swac_get_catalog_mode() !== 'walled_garden') {
    return;
}

/**
 * 1. Synchronous <head> script to pre-apply .swac-retailer-auth before first paint.
 * Eliminates FOUC (flash of unauthenticated content) for returning retailers.
 */
add_action('wp_head', function () {
    if (is_admin()) {
        return;
    }
    ?>
    <script>
    (function(){
        try {
            var raw = localStorage.getItem('swac_b2b_retailer');
            if (raw) {
                var parsed = JSON.parse(raw);
                if (parsed && parsed.token) {
                    document.documentElement.classList.add('swac-retailer-auth');
                }
            }
        } catch(e){}
    })();
    </script>
    <?php
}, 1);

/**
 * 2. Adds .swac-walled-garden-mode body class on all frontend pages.
 */
add_filter('body_class', function (array $classes): array {
    if (!is_admin()) {
        $classes[] = 'swac-walled-garden-mode';
    }
    return $classes;
});

/**
 * 3. Renders the Shikhar-style Walled Garden login gate screen.
 */
if (!function_exists('swac_render_walled_garden_gate')) {
    function swac_render_walled_garden_gate(): void
    {
        if (is_admin()) {
            return;
        }

        $cfg = function_exists('swac_get_config') ? swac_get_config() : [];
        $store_name = !empty($cfg['name']) ? $cfg['name'] : (!empty($cfg['store_name']) ? $cfg['store_name'] : get_bloginfo('name'));
        $base_wa = $cfg['base_url'] ?? '';

        $reg_message = sprintf(
            /* translators: %s: store name */
            __("Hello %s, I want to register our retail store for wholesale ordering. Here are our shop details:\n\n• Shop / Store Name:\n• Owner / Contact Person:\n• Delivery Route / Area:\n• Mobile Number:\n\nPlease approve our account and issue our 4-digit PIN.", 'stateless-wa-commerce'),
            $store_name
        );

        $whatsapp_reg_url = function_exists('swac_build_whatsapp_url')
            ? swac_build_whatsapp_url($reg_message, $base_wa)
            : '';
        ?>
        <!-- SWAC Shikhar-Style Walled Garden Portal Gate -->
        <div id="swac-walled-garden-gate" class="swac-walled-gate-screen">
            <div class="swac-walled-gate-card">
                <div class="swac-walled-gate-badge">
                    <span class="swac-walled-badge-icon" aria-hidden="true">🏢</span>
                    <span><?php esc_html_e('Wholesale Trade Portal', 'stateless-wa-commerce'); ?></span>
                </div>

                <h2 class="swac-walled-gate-title"><?php echo esc_html($store_name); ?></h2>

                <p class="swac-walled-gate-desc">
                    <?php esc_html_e('Private B2B ordering portal for approved retailers and trade stockists. Enter your registered mobile and 4-digit PIN to access wholesale rates and carton ordering.', 'stateless-wa-commerce'); ?>
                </p>

                <form id="swac-walled-gate-form" class="swac-walled-gate-form" novalidate>
                    <div class="swac-form-row">
                        <label for="swac-gate-phone"><strong><?php esc_html_e('Registered Mobile Number', 'stateless-wa-commerce'); ?></strong></label>
                        <input
                            type="tel"
                            id="swac-gate-phone"
                            class="swac-modal-input"
                            placeholder="<?php esc_attr_e('10-digit mobile number', 'stateless-wa-commerce'); ?>"
                            maxlength="14"
                            required
                            autocomplete="tel"
                            inputmode="tel"
                        >
                    </div>

                    <div class="swac-form-row">
                        <label for="swac-gate-pin"><strong><?php esc_html_e('4-Digit Login PIN', 'stateless-wa-commerce'); ?></strong></label>
                        <input
                            type="password"
                            id="swac-gate-pin"
                            class="swac-modal-input"
                            placeholder="••••"
                            maxlength="6"
                            inputmode="numeric"
                            required
                            autocomplete="current-password"
                        >
                    </div>

                    <div id="swac-gate-msg" class="swac-login-alert swac-b2b-modal-hidden" role="alert"></div>

                    <button type="submit" id="swac-gate-submit" class="swac-modal-submit-btn swac-walled-gate-btn">
                        <span><?php esc_html_e('Unlock Wholesale Catalog', 'stateless-wa-commerce'); ?></span>
                        <span aria-hidden="true">→</span>
                    </button>
                </form>

                <?php if (!empty($whatsapp_reg_url)) : ?>
                    <div class="swac-walled-gate-footer">
                        <div class="swac-footer-note"><?php esc_html_e('New Retail Store / Partner?', 'stateless-wa-commerce'); ?></div>
                        <a href="<?php echo esc_url($whatsapp_reg_url); ?>" class="swac-wa-register-link" target="_blank" rel="noopener noreferrer">
                            <span aria-hidden="true">💬</span>
                            <span><?php esc_html_e('Apply for Retailer Account via WhatsApp', 'stateless-wa-commerce'); ?></span>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
}
add_action('woocommerce_before_main_content', 'swac_render_walled_garden_gate', 5);
