<?php
/**
 * 💬 GLOBAL FRONTEND COMPONENT: WHATSAPP CART DRAWER & FLOATING TRIGGER
 *
 * Emits the floating WhatsApp cart trigger bubble and slide-out modal drawer container.
 * Injected once into wp_footer on public frontend requests.
 *
 * @package StatelessWaCommerce
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('wp_footer', function () {
    $cfg = function_exists('swac_get_config') ? swac_get_config() : [];
    $disclaimer = $cfg['order_disclaimer'] ?? '';
    $icon = function_exists('swac_icon_svg') ? swac_icon_svg() : '';
    ?>
    <!-- WhatsApp Cart Bubble -->
    <button
            id="swac-cart-bubble"
            class="swac-cart-bubble"
            aria-label="<?php esc_attr_e('View your WhatsApp order list', 'stateless-wa-commerce'); ?>"
            style="display:none;"
    >
        <?php echo $icon; ?>
        <span class="swac-cart-bubble-label"><?php esc_html_e('My Order', 'stateless-wa-commerce'); ?></span>
        <span class="swac-cart-count" id="swac-cart-count">0</span>
    </button>

    <!-- WhatsApp Cart Drawer -->
    <div
            id="swac-drawer"
            class="swac-drawer"
            aria-hidden="true"
            inert
            role="dialog"
            aria-modal="true"
            aria-labelledby="swac-drawer-title"
            style="visibility:hidden;"
    >
        <div class="swac-drawer-header">
            <span id="swac-drawer-title">
                <?php echo $icon; ?><?php esc_html_e('My Order List', 'stateless-wa-commerce'); ?>
            </span>
            <button id="swac-drawer-close" class="swac-drawer-close"
                    aria-label="<?php esc_attr_e('Close order list', 'stateless-wa-commerce'); ?>">✕
            </button>
        </div>
        <div class="swac-drawer-body" id="swac-drawer-body">
            <!-- Items injected dynamically by wa_cart.js -->
        </div>
        <div class="swac-drawer-footer">
            <div id="swac-clear-confirm" class="swac-clear-confirm" style="display:none;" aria-live="polite">
                <span class="swac-clear-confirm-text"><?php esc_html_e('Empty your order list?', 'stateless-wa-commerce'); ?></span>
                <div class="swac-clear-confirm-btns">
                    <button type="button" id="swac-clear-cancel"
                            class="swac-clear-cancel-btn"><?php esc_html_e('Cancel', 'stateless-wa-commerce'); ?></button>
                    <button type="button" id="swac-clear-proceed"
                            class="swac-clear-proceed-btn"><?php esc_html_e('Clear', 'stateless-wa-commerce'); ?></button>
                </div>
            </div>
            <div id="swac-order-note" class="swac-order-note" style="display:none;">
                <?php echo esc_html($disclaimer); ?>
            </div>
            <button id="swac-send-order" class="swac-send-order-btn"
                    aria-label="<?php esc_attr_e('Send order on WhatsApp', 'stateless-wa-commerce'); ?>">
                <?php echo $icon; ?>
                <?php esc_html_e('Send Order on WhatsApp', 'stateless-wa-commerce'); ?>
            </button>
            <button id="swac-clear-cart" class="swac-clear-btn"
                    aria-label="<?php esc_attr_e('Clear all items from list', 'stateless-wa-commerce'); ?>">
                <?php esc_html_e('Clear list', 'stateless-wa-commerce'); ?>
            </button>
        </div>
    </div>
    <div id="swac-drawer-overlay" class="swac-drawer-overlay" style="display:none;"></div>
    <?php
});
