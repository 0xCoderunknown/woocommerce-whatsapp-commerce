<?php
/**
 * 💬 FRONTEND COMPONENT: WHATSAPP ACTION BUTTON
 *
 * Shortcode: [swac_button]
 * Attributes:
 *  - text     : Button label (default: 'Order on WhatsApp')
 *  - message  : Pre-filled text passed to WhatsApp (default: 'Hello ' . get_bloginfo('name') . '.')
 *  - fullwidth: 'yes' | 'no' (default: 'no')
 *  - icon     : 'yes' | 'no' (default: 'yes')
 *
 * @package StatelessWaCommerce
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('swac_button_shortcode')) {
    /**
     * Render the WhatsApp action button.
     *
     * @param array|string $atts Shortcode attributes.
     * @param string|null $content Enclosed button content.
     * @return string
     */
    function swac_button_shortcode($atts = [], ?string $content = null): string
    {
        $cfg = function_exists('swac_get_config') ? swac_get_config() : [];
        $base_url = !empty($cfg['base_url']) ? (string)$cfg['base_url'] : '';

        // If no phone number or base URL is configured, never render a broken wa.me/ link with an empty number.
        if (empty($base_url)) {
            if (current_user_can('manage_woocommerce')) {
                return '<span class="swac-btn-unconfigured" style="display:inline-block;padding:8px 12px;background:#fffbeb;border:1px dashed #f59e0b;color:#b45309;font-size:12px;border-radius:6px;">'
                    . esc_html__('⚠️ [swac_button]: No WhatsApp phone number configured.', 'stateless-wa-commerce')
                    . '</span>';
            }
            return '';
        }

        $default_msg = sprintf(__('Hello %s.', 'stateless-wa-commerce'), get_bloginfo('name') ?: 'Store');

        $parsed_atts = shortcode_atts([
            'text' => __('Order on WhatsApp', 'stateless-wa-commerce'),
            'message' => $default_msg,
            'fullwidth' => 'no',
            'icon' => 'yes',
        ], is_array($atts) ? $atts : [], 'swac_button');

        $text = !empty($parsed_atts['text']) ? esc_html($parsed_atts['text']) : __('Contact Us', 'stateless-wa-commerce');
        if (!empty($content)) {
            $text = esc_html(trim((string)$content));
        }

        $message = sanitize_text_field((string)($parsed_atts['message'] ?? $default_msg));
        $fw_val = strtolower((string)($parsed_atts['fullwidth'] ?? ''));
        $fullwidth = ($fw_val === 'yes' || $fw_val === 'true');

        $show_icon = !in_array(strtolower((string)($parsed_atts['icon'] ?? 'yes')), ['no', 'false', '0', 'none'], true);

        $wa_url = function_exists('swac_build_whatsapp_url')
            ? swac_build_whatsapp_url($message, $base_url)
            : $base_url . (str_contains($base_url, '?') ? '&' : '?') . 'text=' . rawurlencode($message);

        $class_name = 'swac-btn';
        if ($fullwidth) {
            $class_name .= ' swac-btn--fullwidth';
        }

        $svg_icon = ($show_icon && function_exists('swac_icon_svg'))
            ? swac_icon_svg()
            : '';

        return sprintf(
            '<a href="%s" target="_blank" rel="noopener noreferrer" class="%s">%s<span>%s</span></a>',
            esc_url($wa_url),
            esc_attr($class_name),
            $svg_icon,
            $text
        );
    }
}

add_shortcode('swac_button', 'swac_button_shortcode');
