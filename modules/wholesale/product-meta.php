<?php
/**
 * 📦 WHOLESALE / B2B MODULE: PRODUCT METADATA & DATA SCHEMAS
 *
 * Registers product meta fields for B2B wholesale pricing (PTR), margin
 * calculation, carton packing guide, and scheme promotions (free goods, volume tiers, CD).
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
 * Retrieves normalized wholesale / B2B data for a product.
 *
 * @param WC_Product|int $product Product instance or ID.
 * @return array
 */
function swac_b2b_get_product_data($product): array
{
    if (is_numeric($product)) {
        $product = wc_get_product((int)$product);
    }

    if (!$product instanceof WC_Product) {
        return [
            'is_b2b' => false,
            'ptr' => 0.0,
            'mrp' => 0.0,
            'margin_amount' => 0.0,
            'margin_percent' => 0.0,
            'moq' => 1,
            'carton_pack' => 0,
            'scheme_type' => 'none',
            'scheme_text' => '',
            'scheme_badge' => '',
        ];
    }

    $product_id = $product->get_id();
    $raw_ptr = get_post_meta($product_id, '_swac_b2b_ptr', true);
    $carton_pack = (int)get_post_meta($product_id, '_swac_b2b_carton_pack', true);
    $scheme_type = (string)get_post_meta($product_id, '_swac_b2b_scheme_type', true);
    if ($scheme_type === '') {
        $scheme_type = 'none';
    }

    $mrp = (float)$product->get_regular_price();
    if ($mrp <= 0) {
        $mrp = (float)$product->get_price();
    }

    // If PTR is explicitly defined, use it. Otherwise fallback to current store price.
    $has_explicit_ptr = ($raw_ptr !== '' && $raw_ptr !== false);
    $ptr = $has_explicit_ptr ? (float)$raw_ptr : (float)$product->get_price();

    // Margin calculation
    $margin_amount = ($mrp > $ptr && $ptr > 0) ? ($mrp - $ptr) : 0.0;
    $margin_percent = ($mrp > 0 && $margin_amount > 0) ? round(($margin_amount / $mrp) * 100, 1) : 0.0;

    // Scheme details resolution
    $scheme_text = '';
    $scheme_badge = '';

    if ($scheme_type === 'free_goods') {
        $buy_qty = (int)get_post_meta($product_id, '_swac_b2b_scheme_buy', true);
        $free_qty = (int)get_post_meta($product_id, '_swac_b2b_scheme_free', true);
        if ($buy_qty > 0 && $free_qty > 0) {
            $scheme_badge = sprintf('%d + %d FREE', $buy_qty, $free_qty);
            $scheme_text = sprintf(
            /* translators: 1: buy quantity, 2: free quantity */
                __('Buy %1$d pcs, get %2$d pc FREE', 'stateless-wa-commerce'),
                $buy_qty,
                $free_qty
            );
        }
    } elseif ($scheme_type === 'tier_discount') {
        $tiers = (string)get_post_meta($product_id, '_swac_b2b_scheme_tiers', true);
        if ($tiers !== '') {
            $scheme_badge = sprintf(__('Slab: %s', 'stateless-wa-commerce'), $tiers);
            $scheme_text = sprintf(__('Volume Slab Discount: %s', 'stateless-wa-commerce'), $tiers);
        }
    } elseif ($scheme_type === 'cash_discount') {
        $cd_percent = (float)get_post_meta($product_id, '_swac_b2b_scheme_cd', true);
        if ($cd_percent > 0) {
            $scheme_badge = sprintf('%s%% Cash Discount', rtrim(rtrim((string)$cd_percent, '0'), '.'));
            $scheme_text = sprintf(
            /* translators: %s: discount percentage */
                __('%s%% Cash Discount on prompt settlement', 'stateless-wa-commerce'),
                rtrim(rtrim((string)$cd_percent, '0'), '.')
            );
        }
    } elseif ($scheme_type === 'custom') {
        $custom_text = (string)get_post_meta($product_id, '_swac_b2b_scheme_custom', true);
        if ($custom_text !== '') {
            $scheme_badge = $custom_text;
            $scheme_text = $custom_text;
        }
    }

    $moq = (int)get_post_meta($product_id, '_swac_b2b_moq', true);
    if ($moq < 1) {
        $moq = 1;
    }

    $is_b2b = ($has_explicit_ptr || $carton_pack > 0 || $scheme_type !== 'none');

    return [
        'is_b2b' => $is_b2b,
        'ptr' => $ptr,
        'mrp' => $mrp,
        'margin_amount' => $margin_amount,
        'margin_percent' => $margin_percent,
        'moq' => $moq,
        'carton_pack' => $carton_pack,
        'scheme_type' => $scheme_type,
        'scheme_text' => $scheme_text,
        'scheme_badge' => $scheme_badge,
    ];
}

/**
 * Adds Wholesale / B2B tab in WooCommerce Product Data meta box.
 */
add_filter('woocommerce_product_data_tabs', function (array $tabs): array {
    $tabs['swac_wholesale'] = [
        'label' => __('Wholesale & Scheme', 'stateless-wa-commerce'),
        'target' => 'swac_wholesale_product_data',
        'class' => ['show_if_simple', 'show_if_variable'],
        'priority' => 21,
    ];
    return $tabs;
});

/**
 * Renders the Wholesale & Scheme panel fields in wp-admin.
 */
add_action('woocommerce_product_data_panels', function () {
    global $post;
    if (!$post) {
        return;
    }

    $product_id = $post->ID;
    $ptr = get_post_meta($product_id, '_swac_b2b_ptr', true);
    $carton_pack = get_post_meta($product_id, '_swac_b2b_carton_pack', true);
    $moq = get_post_meta($product_id, '_swac_b2b_moq', true) ?: '1';
    $scheme_type = get_post_meta($product_id, '_swac_b2b_scheme_type', true) ?: 'none';
    $scheme_buy = get_post_meta($product_id, '_swac_b2b_scheme_buy', true);
    $scheme_free = get_post_meta($product_id, '_swac_b2b_scheme_free', true);
    $scheme_tiers = get_post_meta($product_id, '_swac_b2b_scheme_tiers', true);
    $scheme_cd = get_post_meta($product_id, '_swac_b2b_scheme_cd', true);
    $scheme_custom = get_post_meta($product_id, '_swac_b2b_scheme_custom', true);
    ?>
    <div id="swac_wholesale_product_data" class="panel woocommerce_options_panel hidden">
        <div class="options_group">
            <p class="form-field">
                <strong><?php esc_html_e('B2B Wholesale / Sub-Wholesale Configuration', 'stateless-wa-commerce'); ?></strong><br>
                <span class="description"><?php esc_html_e('Configure PTR rates, retailer margins, carton packing guide, and promotional schemes.', 'stateless-wa-commerce'); ?></span>
            </p>

            <?php
            woocommerce_wp_text_input([
                'id' => '_swac_b2b_ptr',
                'label' => __('Wholesale Rate / PTR (₹)', 'stateless-wa-commerce'),
                'description' => __('Price To Retailer (PTR). If left empty, regular price is used.', 'stateless-wa-commerce'),
                'desc_tip' => true,
                'type' => 'text',
                'value' => $ptr,
                'placeholder' => __('e.g. 24.50', 'stateless-wa-commerce'),
            ]);

            woocommerce_wp_text_input([
                'id' => '_swac_b2b_carton_pack',
                'label' => __('Carton Packing Guide', 'stateless-wa-commerce'),
                'description' => __('Number of pieces per carton / case (e.g. 144). Shown as reference guide for retailers.', 'stateless-wa-commerce'),
                'desc_tip' => true,
                'type' => 'number',
                'value' => $carton_pack,
                'placeholder' => __('e.g. 144', 'stateless-wa-commerce'),
                'custom_attributes' => ['min' => '0', 'step' => '1'],
            ]);

            woocommerce_wp_text_input([
                'id' => '_swac_b2b_moq',
                'label' => __('Minimum Order Quantity (MOQ)', 'stateless-wa-commerce'),
                'description' => __('Minimum units a retailer must order per line item (default: 1).', 'stateless-wa-commerce'),
                'desc_tip' => true,
                'type' => 'number',
                'value' => $moq,
                'placeholder' => '1',
                'custom_attributes' => ['min' => '1', 'step' => '1'],
            ]);

            woocommerce_wp_select([
                'id' => '_swac_b2b_scheme_type',
                'label' => __('Active Scheme Type', 'stateless-wa-commerce'),
                'description' => __('Select promotional scheme applied on this item.', 'stateless-wa-commerce'),
                'desc_tip' => true,
                'value' => $scheme_type,
                'options' => [
                    'none' => __('No Scheme', 'stateless-wa-commerce'),
                    'free_goods' => __('Buy X Get Y Free (e.g. 12+1)', 'stateless-wa-commerce'),
                    'tier_discount' => __('Volume Tier Slabs (e.g. 10=2%, 25=5%)', 'stateless-wa-commerce'),
                    'cash_discount' => __('Cash Discount (CD %)', 'stateless-wa-commerce'),
                    'custom' => __('Custom Scheme Text', 'stateless-wa-commerce'),
                ],
            ]);
            ?>
        </div>

        <div class="options_group swac-scheme-fields">
            <?php
            woocommerce_wp_text_input([
                'id' => '_swac_b2b_scheme_buy',
                'label' => __('Buy Quantity (X)', 'stateless-wa-commerce'),
                'type' => 'number',
                'value' => $scheme_buy,
                'placeholder' => '12',
                'custom_attributes' => ['min' => '1', 'step' => '1'],
            ]);

            woocommerce_wp_text_input([
                'id' => '_swac_b2b_scheme_free',
                'label' => __('Free Quantity (Y)', 'stateless-wa-commerce'),
                'type' => 'number',
                'value' => $scheme_free,
                'placeholder' => '1',
                'custom_attributes' => ['min' => '1', 'step' => '1'],
            ]);

            woocommerce_wp_text_input([
                'id' => '_swac_b2b_scheme_tiers',
                'label' => __('Volume Tier Slabs', 'stateless-wa-commerce'),
                'description' => __('Format: Qty:Discount (e.g. 10:2%, 25:5%)', 'stateless-wa-commerce'),
                'desc_tip' => true,
                'value' => $scheme_tiers,
                'placeholder' => '10:2%, 25:5%',
            ]);

            woocommerce_wp_text_input([
                'id' => '_swac_b2b_scheme_cd',
                'label' => __('Cash Discount %', 'stateless-wa-commerce'),
                'type' => 'text',
                'value' => $scheme_cd,
                'placeholder' => '2',
            ]);

            woocommerce_wp_text_input([
                'id' => '_swac_b2b_scheme_custom',
                'label' => __('Custom Scheme Text', 'stateless-wa-commerce'),
                'value' => $scheme_custom,
                'placeholder' => __('e.g. Extra ₹20 off per box', 'stateless-wa-commerce'),
            ]);
            ?>
        </div>
    </div>
    <?php
});

/**
 * Saves wholesale product metadata on product save.
 */
add_action('woocommerce_process_product_meta', function (int $post_id) {
    if (isset($_POST['_swac_b2b_ptr'])) {
        $ptr = wc_clean(wp_unslash($_POST['_swac_b2b_ptr']));
        update_post_meta($post_id, '_swac_b2b_ptr', $ptr);
    }

    if (isset($_POST['_swac_b2b_carton_pack'])) {
        $pack = absint(wp_unslash($_POST['_swac_b2b_carton_pack']));
        update_post_meta($post_id, '_swac_b2b_carton_pack', $pack);
    }

    if (isset($_POST['_swac_b2b_moq'])) {
        $moq = max(1, absint(wp_unslash($_POST['_swac_b2b_moq'])));
        update_post_meta($post_id, '_swac_b2b_moq', $moq);
    }

    if (isset($_POST['_swac_b2b_scheme_type'])) {
        $scheme_type = sanitize_key(wp_unslash($_POST['_swac_b2b_scheme_type']));
        update_post_meta($post_id, '_swac_b2b_scheme_type', $scheme_type);
    }

    if (isset($_POST['_swac_b2b_scheme_buy'])) {
        $buy = absint(wp_unslash($_POST['_swac_b2b_scheme_buy']));
        update_post_meta($post_id, '_swac_b2b_scheme_buy', $buy);
    }

    if (isset($_POST['_swac_b2b_scheme_free'])) {
        $free = absint(wp_unslash($_POST['_swac_b2b_scheme_free']));
        update_post_meta($post_id, '_swac_b2b_scheme_free', $free);
    }

    if (isset($_POST['_swac_b2b_scheme_tiers'])) {
        $tiers = sanitize_text_field(wp_unslash($_POST['_swac_b2b_scheme_tiers']));
        update_post_meta($post_id, '_swac_b2b_scheme_tiers', $tiers);
    }

    if (isset($_POST['_swac_b2b_scheme_cd'])) {
        $cd = sanitize_text_field(wp_unslash($_POST['_swac_b2b_scheme_cd']));
        update_post_meta($post_id, '_swac_b2b_scheme_cd', $cd);
    }

    if (isset($_POST['_swac_b2b_scheme_custom'])) {
        $custom = sanitize_text_field(wp_unslash($_POST['_swac_b2b_scheme_custom']));
        update_post_meta($post_id, '_swac_b2b_scheme_custom', $custom);
    }
});

