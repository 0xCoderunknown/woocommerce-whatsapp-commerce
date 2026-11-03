<?php
/**
 * 🔒 WHOLESALE / B2B MODULE: RETAILER LOGIN MODAL & GATING SCRIPT
 *
 * Emits the frontend 4-digit PIN verification modal, WhatsApp registration handshake,
 * and client-side gating script for zero-session rate protection.
 *
 * @package StatelessWaCommerce
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('swac_is_module_active') || !swac_is_module_active('wholesale')) {
    return;
}

/**
 * Emits the login modal markup and zero-cost registration CTA in the footer.
 */
add_action('wp_footer', function () {
    $cfg = function_exists('swac_get_config') ? swac_get_config() : [];
    $store_name = $cfg['name'] ?? get_bloginfo('name');
    $base_wa = $cfg['base_url'] ?? '';

    $reg_message = sprintf(
        /* translators: %s: store name */
        __("Hello %s, I want to purchase at wholesale rates. Here are my shop details:\n\n• Shop Name:\n• Owner Name:\n• Area / Pincode:\n• Contact Number:\n\nPlease approve my retailer account and issue my 4-digit PIN.", 'stateless-wa-commerce'),
        $store_name
    );

    $whatsapp_reg_url = function_exists('swac_build_whatsapp_url')
        ? swac_build_whatsapp_url($reg_message, $base_wa)
        : '';
    ?>
    <!-- SWAC B2B Retailer Login Modal -->
    <div id="swac-retailer-modal" class="swac-modal-backdrop swac-b2b-modal-hidden" aria-hidden="true" role="dialog" aria-modal="true">
        <div class="swac-modal-card">
            <div class="swac-modal-card-header">
                <div class="swac-modal-title">
                    <span class="swac-modal-icon" aria-hidden="true">🔐</span>
                    <h3><?php esc_html_e('Retailer Wholesale Login', 'stateless-wa-commerce'); ?></h3>
                </div>
                <button type="button" class="swac-modal-close-btn" id="swac-modal-close" aria-label="<?php esc_attr_e('Close modal', 'stateless-wa-commerce'); ?>">✕</button>
            </div>

            <div class="swac-modal-card-body">
                <p class="swac-modal-subtitle">
                    <?php esc_html_e('Wholesale PTR rates, schemes, and margins are exclusive to approved retailers. Enter your registered mobile and 4-digit PIN.', 'stateless-wa-commerce'); ?>
                </p>

                <form id="swac-retailer-login-form" novalidate>
                    <div class="swac-form-row">
                        <label for="swac-input-phone"><?php esc_html_e('Mobile Number', 'stateless-wa-commerce'); ?></label>
                        <input
                            type="tel"
                            id="swac-input-phone"
                            class="swac-modal-input"
                            placeholder="<?php esc_attr_e('10-digit mobile number', 'stateless-wa-commerce'); ?>"
                            maxlength="14"
                            required
                            autocomplete="tel"
                        >
                    </div>

                    <div class="swac-form-row">
                        <label for="swac-input-pin"><?php esc_html_e('4-Digit PIN', 'stateless-wa-commerce'); ?></label>
                        <input
                            type="password"
                            id="swac-input-pin"
                            class="swac-modal-input"
                            placeholder="••••"
                            maxlength="6"
                            inputmode="numeric"
                            required
                            autocomplete="current-password"
                        >
                    </div>

                    <div id="swac-login-msg" class="swac-login-alert swac-b2b-modal-hidden" role="alert"></div>

                    <button type="submit" id="swac-login-submit" class="swac-modal-submit-btn">
                        <?php esc_html_e('Unlock Wholesale Rates', 'stateless-wa-commerce'); ?>
                    </button>
                </form>

                <?php if (!empty($whatsapp_reg_url)) : ?>
                    <div class="swac-modal-card-footer">
                        <span class="swac-footer-note"><?php esc_html_e('Not an approved retailer yet?', 'stateless-wa-commerce'); ?></span>
                        <a href="<?php echo esc_url($whatsapp_reg_url); ?>" class="swac-wa-register-link" target="_blank" rel="noopener noreferrer">
                            <span aria-hidden="true">💬</span>
                            <?php esc_html_e('Apply for Retailer Account via WhatsApp', 'stateless-wa-commerce'); ?>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Active Retailer Floating Pill -->
    <div id="swac-retailer-pill" class="swac-retailer-status-pill swac-b2b-modal-hidden">
        <span class="swac-pill-store" id="swac-pill-store-name">🏪 <?php esc_html_e('Retailer', 'stateless-wa-commerce'); ?></span>
        <button type="button" id="swac-pill-logout" class="swac-pill-logout-btn" title="<?php esc_attr_e('Lock wholesale rates & sign out', 'stateless-wa-commerce'); ?>">
            <?php esc_html_e('Sign Out', 'stateless-wa-commerce'); ?>
        </button>
    </div>
    <?php
}, 30);
