<?php
/**
 * 🖥️ WHOLESALE / B2B MODULE: FRONTEND DISTRIBUTOR DESK
 *
 * Provides a mobile-first, zero-admin frontend portal for wholesalers and billing
 * clerks to rapidly add products, tweak daily rates/schemes, and manage retailer PINs.
 *
 * Shortcode: [swac_distributor_portal] (alias: [swac_frontend_admin])
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
 * Verifies if current request has distributor desk authorization.
 *
 * @param WP_REST_Request|null $request Optional REST request.
 * @return bool
 */
function swac_desk_is_authorized(?WP_REST_Request $request = null): bool
{
    // 1. Logged in WordPress administrators / shop managers
    if (current_user_can('manage_woocommerce') || current_user_can('edit_products')) {
        return true;
    }

    // 2. Token-based authorization for mobile access
    $token = '';
    if ($request instanceof WP_REST_Request) {
        $token = (string)$request->get_header('X-SWAC-Master-Token');
        if (empty($token)) {
            $token = (string)($request->get_param('master_token') ?? '');
        }
    }

    if (!empty($token)) {
        $stored_hash = get_option('swac_b2b_master_hash', '');
        if (!empty($stored_hash) && hash_equals($stored_hash, hash('sha256', $token))) {
            return true;
        }
    }

    return false;
}

/**
 * Registers REST API endpoints for Frontend Distributor Desk.
 */
add_action('rest_api_init', function () {
    // 1. Master PIN Verification
    register_rest_route('swac/v1', '/desk/auth', [
        'methods'             => 'POST',
        'callback'            => 'swac_rest_desk_auth',
        'permission_callback' => '__return_true',
    ]);

    // 2. Fetch Catalog Items for Quick Rate Sheet
    register_rest_route('swac/v1', '/desk/products', [
        'methods'             => 'GET',
        'callback'            => 'swac_rest_desk_get_products',
        'permission_callback' => 'swac_desk_is_authorized',
    ]);

    // 3. Quick Update Rate & Scheme
    register_rest_route('swac/v1', '/desk/quick-update', [
        'methods'             => 'POST',
        'callback'            => 'swac_rest_desk_quick_update',
        'permission_callback' => 'swac_desk_is_authorized',
    ]);

    // 4. Create New Product
    register_rest_route('swac/v1', '/desk/create-product', [
        'methods'             => 'POST',
        'callback'            => 'swac_rest_desk_create_product',
        'permission_callback' => 'swac_desk_is_authorized',
    ]);

    // 5. Retailer Management Endpoints
    register_rest_route('swac/v1', '/desk/retailers', [
        'methods'             => 'GET',
        'callback'            => 'swac_rest_desk_get_retailers',
        'permission_callback' => 'swac_desk_is_authorized',
    ]);

    register_rest_route('swac/v1', '/desk/save-retailer', [
        'methods'             => 'POST',
        'callback'            => 'swac_rest_desk_save_retailer',
        'permission_callback' => 'swac_desk_is_authorized',
    ]);

    register_rest_route('swac/v1', '/desk/delete-retailer', [
        'methods'             => 'POST',
        'callback'            => 'swac_rest_desk_delete_retailer',
        'permission_callback' => 'swac_desk_is_authorized',
    ]);
});

/**
 * Handles master passcode verification for distributor mobile login.
 */
function swac_rest_desk_auth(WP_REST_Request $request): WP_REST_Response
{
    $params = $request->get_json_params() ?: $request->get_body_params();
    $pin = sanitize_text_field((string)($params['pin'] ?? ''));

    if (empty($pin)) {
        return new WP_REST_Response(['success' => false, 'message' => __('PIN required.', 'stateless-wa-commerce')], 400);
    }

    $configured_pin = (string)get_option('swac_b2b_master_pin', '');
    if (empty($configured_pin)) {
        $cfg = function_exists('swac_get_config') ? swac_get_config() : [];
        $raw_phone = $cfg['phone_number'] ?? '';
        $configured_pin = strlen($raw_phone) >= 4 ? substr($raw_phone, -4) : '1234';
    }

    if ($pin !== $configured_pin) {
        return new WP_REST_Response([
            'success' => false,
            'message' => __('Incorrect Master PIN. Please try again.', 'stateless-wa-commerce'),
        ], 403);
    }

    $token = wp_generate_password(32, false);
    update_option('swac_b2b_master_hash', hash('sha256', $token));

    return new WP_REST_Response([
        'success' => true,
        'token'   => $token,
        'message' => __('Access Granted.', 'stateless-wa-commerce'),
    ], 200);
}

/**
 * Returns products for the daily quick rate sheet.
 */
function swac_rest_desk_get_products(WP_REST_Request $request): WP_REST_Response
{
    $search = sanitize_text_field((string)$request->get_param('search'));
    $args = [
        'post_type'      => 'product',
        'post_status'    => 'publish',
        'posts_per_page' => 150,
        'orderby'        => 'title',
        'order'          => 'ASC',
    ];

    if (!empty($search)) {
        $args['s'] = $search;
    }

    $query = new WP_Query($args);
    $items = [];

    foreach ($query->posts as $post) {
        $product = wc_get_product($post->ID);
        if (!$product) {
            continue;
        }

        $b2b = function_exists('swac_b2b_get_product_data')
            ? swac_b2b_get_product_data($product)
            : ['ptr' => (float)$product->get_price(), 'mrp' => (float)$product->get_regular_price(), 'carton_pack' => 0, 'scheme_type' => 'none', 'scheme_badge' => ''];

        $image_id = $product->get_image_id();
        $thumb = $image_id ? wp_get_attachment_image_url($image_id, 'thumbnail') : wc_placeholder_img_src();

        $brand_tax = defined('SWAC_TAX_BRAND') ? SWAC_TAX_BRAND : 'product_brand';
        $brand_terms = get_the_terms($product->get_id(), $brand_tax);
        $brand = (!empty($brand_terms) && !is_wp_error($brand_terms)) ? current($brand_terms)->name : '';

        $items[] = [
            'id'           => $product->get_id(),
            'name'         => $product->get_name(),
            'brand'        => $brand,
            'thumb'        => $thumb,
            'in_stock'     => $product->is_in_stock(),
            'mrp'          => $b2b['mrp'],
            'ptr'          => $b2b['ptr'],
            'carton_pack'  => $b2b['carton_pack'],
            'scheme_type'  => $b2b['scheme_type'],
            'scheme_badge' => $b2b['scheme_badge'],
            'permalink'    => $product->get_permalink(),
        ];
    }

    return new WP_REST_Response(['success' => true, 'products' => $items], 200);
}

/**
 * Handles quick inline update of Rate, MRP, Scheme, Carton, and Stock.
 */
function swac_rest_desk_quick_update(WP_REST_Request $request): WP_REST_Response
{
    $params = $request->get_json_params() ?: $request->get_body_params();
    $product_id = absint($params['id'] ?? 0);
    $product = wc_get_product($product_id);

    if (!$product) {
        return new WP_REST_Response(['success' => false, 'message' => __('Product not found.', 'stateless-wa-commerce')], 404);
    }

    // 1. Update PTR
    if (isset($params['ptr'])) {
        $ptr = (float)$params['ptr'];
        update_post_meta($product_id, '_swac_b2b_ptr', $ptr > 0 ? (string)$ptr : '');
    }

    // 2. Update MRP
    if (isset($params['mrp'])) {
        $mrp = (float)$params['mrp'];
        if ($mrp > 0) {
            $product->set_regular_price($mrp);
        }
    }

    // 3. Update Carton Pack
    if (isset($params['carton_pack'])) {
        $carton = absint($params['carton_pack']);
        update_post_meta($product_id, '_swac_b2b_carton_pack', $carton);
    }

    // 4. Update Scheme
    if (isset($params['scheme_type'])) {
        $scheme_type = sanitize_key($params['scheme_type']);
        update_post_meta($product_id, '_swac_b2b_scheme_type', $scheme_type);

        if ($scheme_type === 'free_goods') {
            update_post_meta($product_id, '_swac_b2b_scheme_buy', absint($params['scheme_buy'] ?? 12));
            update_post_meta($product_id, '_swac_b2b_scheme_free', absint($params['scheme_free'] ?? 1));
        } elseif ($scheme_type === 'tier_discount') {
            update_post_meta($product_id, '_swac_b2b_scheme_tiers', sanitize_text_field($params['scheme_tiers'] ?? ''));
        } elseif ($scheme_type === 'cash_discount') {
            update_post_meta($product_id, '_swac_b2b_scheme_cd', sanitize_text_field($params['scheme_cd'] ?? '2'));
        } elseif ($scheme_type === 'custom') {
            update_post_meta($product_id, '_swac_b2b_scheme_custom', sanitize_text_field($params['scheme_custom'] ?? ''));
        }
    }

    // 5. Update Stock Status
    if (isset($params['in_stock'])) {
        $in_stock = (bool)$params['in_stock'];
        $product->set_stock_status($in_stock ? 'instock' : 'outofstock');
    }

    $product->save();

    // Trigger selective cache purge if available
    if (function_exists('swac_purge_product_cache')) {
        swac_purge_product_cache($product_id);
    }

    return new WP_REST_Response([
        'success' => true,
        'message' => __('Rate & Scheme updated.', 'stateless-wa-commerce'),
    ], 200);
}

/**
 * Handles fast creation of a new product from the frontend mobile form.
 */
function swac_rest_desk_create_product(WP_REST_Request $request): WP_REST_Response
{
    $name = sanitize_text_field((string)$request->get_param('name'));
    $mrp = (float)$request->get_param('mrp');
    $ptr = (float)$request->get_param('ptr');
    $carton = absint($request->get_param('carton_pack'));
    $brand_name = sanitize_text_field((string)$request->get_param('brand'));
    $scheme_type = sanitize_key((string)$request->get_param('scheme_type') ?: 'none');

    if (empty($name) || $mrp <= 0) {
        return new WP_REST_Response([
            'success' => false,
            'message' => __('Product name and MRP are required.', 'stateless-wa-commerce'),
        ], 400);
    }

    // 1. Create WooCommerce Product
    $product = new WC_Product_Simple();
    $product->set_name($name);
    $product->set_status('publish');
    $product->set_regular_price($mrp);
    $product->set_price($ptr > 0 ? $ptr : $mrp);
    $product->set_stock_status('instock');
    $product_id = $product->save();

    if (!$product_id) {
        return new WP_REST_Response([
            'success' => false,
            'message' => __('Failed to create product.', 'stateless-wa-commerce'),
        ], 500);
    }

    // 2. Set Wholesale Meta
    if ($ptr > 0) {
        update_post_meta($product_id, '_swac_b2b_ptr', (string)$ptr);
    }
    if ($carton > 0) {
        update_post_meta($product_id, '_swac_b2b_carton_pack', $carton);
    }
    if ($scheme_type !== 'none') {
        update_post_meta($product_id, '_swac_b2b_scheme_type', $scheme_type);
        if ($scheme_type === 'free_goods') {
            update_post_meta($product_id, '_swac_b2b_scheme_buy', absint($request->get_param('scheme_buy') ?: 12));
            update_post_meta($product_id, '_swac_b2b_scheme_free', absint($request->get_param('scheme_free') ?: 1));
        } elseif ($scheme_type === 'tier_discount') {
            update_post_meta($product_id, '_swac_b2b_scheme_tiers', sanitize_text_field((string)$request->get_param('scheme_tiers')));
        } elseif ($scheme_type === 'cash_discount') {
            update_post_meta($product_id, '_swac_b2b_scheme_cd', sanitize_text_field((string)$request->get_param('scheme_cd') ?: '2'));
        } elseif ($scheme_type === 'custom') {
            update_post_meta($product_id, '_swac_b2b_scheme_custom', sanitize_text_field((string)$request->get_param('scheme_custom')));
        }
    }

    // 3. Assign Brand Taxonomy
    if (!empty($brand_name)) {
        $brand_tax = defined('SWAC_TAX_BRAND') ? SWAC_TAX_BRAND : 'product_brand';
        if (taxonomy_exists($brand_tax)) {
            wp_set_object_terms($product_id, $brand_name, $brand_tax, true);
        }
    }

    // 4. Handle Direct Photo Upload (Phone Camera / Gallery)
    $files = $request->get_file_params();
    if (!empty($files['image']) && !empty($files['image']['name'])) {
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        $attachment_id = media_handle_upload('image', $product_id);
        if (!is_wp_error($attachment_id)) {
            $product->set_image_id($attachment_id);
            $product->save();
        }
    }

    return new WP_REST_Response([
        'success'    => true,
        'product_id' => $product_id,
        'name'       => $name,
        'permalink'  => $product->get_permalink(),
        'message'    => sprintf(
            /* translators: %s: product title */
            __('%s successfully added to wholesale catalog!', 'stateless-wa-commerce'),
            $name
        ),
    ], 201);
}

/**
 * Returns approved retailers for desk management.
 */
function swac_rest_desk_get_retailers(): WP_REST_Response
{
    $retailers = function_exists('swac_get_approved_retailers') ? swac_get_approved_retailers() : [];
    return new WP_REST_Response(['success' => true, 'retailers' => array_values($retailers)], 200);
}

/**
 * Saves a retailer from the desk.
 */
function swac_rest_desk_save_retailer(WP_REST_Request $request): WP_REST_Response
{
    $params = $request->get_json_params() ?: $request->get_body_params();
    $saved = function_exists('swac_save_approved_retailer') ? swac_save_approved_retailer($params) : false;

    if ($saved) {
        return new WP_REST_Response(['success' => true, 'message' => __('Retailer saved.', 'stateless-wa-commerce')], 200);
    }
    return new WP_REST_Response(['success' => false, 'message' => __('Validation error. Check mobile number.', 'stateless-wa-commerce')], 400);
}

/**
 * Deletes a retailer from the desk.
 */
function swac_rest_desk_delete_retailer(WP_REST_Request $request): WP_REST_Response
{
    $params = $request->get_json_params() ?: $request->get_body_params();
    $phone = sanitize_text_field((string)($params['phone'] ?? ''));
    $deleted = function_exists('swac_delete_approved_retailer') ? swac_delete_approved_retailer($phone) : false;

    if ($deleted) {
        return new WP_REST_Response(['success' => true, 'message' => __('Retailer removed.', 'stateless-wa-commerce')], 200);
    }
    return new WP_REST_Response(['success' => false, 'message' => __('Retailer not found.', 'stateless-wa-commerce')], 404);
}

/**
 * Shortcode: [swac_distributor_portal]
 * Emits the Distributor Desk application interface.
 */
function swac_distributor_portal_shortcode(): string
{
    $is_logged_in = current_user_can('manage_woocommerce') || current_user_can('edit_products');
    $brand_tax = defined('SWAC_TAX_BRAND') ? SWAC_TAX_BRAND : 'product_brand';
    $existing_brands = taxonomy_exists($brand_tax) ? get_terms(['taxonomy' => $brand_tax, 'hide_empty' => false]) : [];
    if (is_wp_error($existing_brands)) {
        $existing_brands = [];
    }

    ob_start();
    ?>
    <div id="swac-desk-root" class="swac-desk-container" data-auth="<?php echo $is_logged_in ? '1' : '0'; ?>">
        <!-- 1. Master Passcode Gate (Visible only when accessing without WP login) -->
        <div id="swac-desk-login-box" class="swac-desk-card <?php echo $is_logged_in ? 'swac-desk-hidden' : ''; ?>" style="max-width:380px; margin:40px auto; text-align:center;">
            <div style="font-size:32px; margin-bottom:10px;">🔐</div>
            <h2 style="margin:0 0 8px 0; font-size:1.3rem;"><?php esc_html_e('Distributor Desk Login', 'stateless-wa-commerce'); ?></h2>
            <p style="font-size:0.85rem; color:#64748b; margin-bottom:20px;"><?php esc_html_e('Enter your Master PIN to access wholesale rate controls and product entry.', 'stateless-wa-commerce'); ?></p>
            <form id="swac-desk-auth-form">
                <input type="password" id="swac-desk-pin-input" class="swac-desk-input" placeholder="••••" maxlength="8" inputmode="numeric" required style="text-align:center; font-size:24px; letter-spacing:4px; height:48px; margin-bottom:16px;">
                <div id="swac-desk-auth-error" class="swac-desk-alert swac-desk-hidden" style="margin-bottom:14px;"></div>
                <button type="submit" class="swac-desk-btn swac-desk-btn--primary" style="width:100%; height:44px;"><?php esc_html_e('Unlock Distributor Desk', 'stateless-wa-commerce'); ?></button>
            </form>
        </div>

        <!-- 2. Main Distributor Desk (Visible when authorized) -->
        <div id="swac-desk-app" class="<?php echo $is_logged_in ? '' : 'swac-desk-hidden'; ?>">
            <!-- Top Header & Tab Navigation -->
            <header class="swac-desk-header">
                <div class="swac-desk-header-left">
                    <h1 class="swac-desk-brand-title">📦 <?php esc_html_e('Distributor Desk', 'stateless-wa-commerce'); ?></h1>
                    <span class="swac-desk-badge"><?php esc_html_e('Counter Speed', 'stateless-wa-commerce'); ?></span>
                </div>
                <div class="swac-desk-header-right">
                    <a href="<?php echo esc_url(wc_get_page_permalink('shop') ?: home_url('/')); ?>" class="swac-desk-btn swac-desk-btn--outline" target="_blank" rel="noopener">
                        <?php esc_html_e('View Live Shop ↗', 'stateless-wa-commerce'); ?>
                    </a>
                </div>
            </header>

            <!-- Navigation Tabs -->
            <nav class="swac-desk-tabs" role="tablist">
                <button type="button" class="swac-desk-tab-btn swac-desk-tab-btn--active" data-tab="tab-rates" role="tab">
                    ⚡ <?php esc_html_e('Quick Rate Sheet', 'stateless-wa-commerce'); ?>
                </button>
                <button type="button" class="swac-desk-tab-btn" data-tab="tab-add" role="tab">
                    ➕ <?php esc_html_e('Add Product', 'stateless-wa-commerce'); ?>
                </button>
                <button type="button" class="swac-desk-tab-btn" data-tab="tab-retailers" role="tab">
                    🏪 <?php esc_html_e('Approved Retailers', 'stateless-wa-commerce'); ?>
                </button>
            </nav>

            <!-- Notification Toast / Banner -->
            <div id="swac-desk-toast" class="swac-desk-toast swac-desk-hidden"></div>

            <!-- TAB 1: QUICK RATE SHEET -->
            <section id="swac-tab-rates" class="swac-desk-tab-content swac-desk-tab-content--active">
                <div class="swac-desk-toolbar">
                    <input type="text" id="swac-rates-search" class="swac-desk-input" placeholder="<?php esc_attr_e('🔍 Fast search product name or brand...', 'stateless-wa-commerce'); ?>">
                    <button type="button" id="swac-rates-refresh" class="swac-desk-btn swac-desk-btn--secondary">
                        🔄 <?php esc_html_e('Refresh', 'stateless-wa-commerce'); ?>
                    </button>
                </div>

                <div class="swac-desk-table-wrapper">
                    <table class="swac-desk-table">
                        <thead>
                            <tr>
                                <th style="width:36%;"><?php esc_html_e('Item / Brand', 'stateless-wa-commerce'); ?></th>
                                <th style="width:14%;"><?php esc_html_e('Stock', 'stateless-wa-commerce'); ?></th>
                                <th style="width:14%;"><?php esc_html_e('MRP (₹)', 'stateless-wa-commerce'); ?></th>
                                <th style="width:14%;"><?php esc_html_e('Rate / PTR (₹)', 'stateless-wa-commerce'); ?></th>
                                <th style="width:12%;"><?php esc_html_e('Carton', 'stateless-wa-commerce'); ?></th>
                                <th style="width:10%; text-align:right;"><?php esc_html_e('Action', 'stateless-wa-commerce'); ?></th>
                            </tr>
                        </thead>
                        <tbody id="swac-rates-tbody">
                            <tr><td colspan="6" style="text-align:center; padding:30px; color:#64748b;"><?php esc_html_e('Loading products...', 'stateless-wa-commerce'); ?></td></tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- TAB 2: FAST PRODUCT ENTRY -->
            <section id="swac-tab-add" class="swac-desk-tab-content">
                <div class="swac-desk-card" style="max-width:680px; margin:0 auto;">
                    <h2 style="margin:0 0 16px 0; font-size:1.25rem;">➕ <?php esc_html_e('Add New Wholesale Product', 'stateless-wa-commerce'); ?></h2>
                    <form id="swac-add-product-form" enctype="multipart/form-data">
                        <div class="swac-desk-grid-2">
                            <div class="swac-desk-field">
                                <label for="swac-add-name"><strong><?php esc_html_e('Product Title *', 'stateless-wa-commerce'); ?></strong></label>
                                <input type="text" id="swac-add-name" name="name" class="swac-desk-input" required placeholder="<?php esc_attr_e('e.g. Rin Bar 250g', 'stateless-wa-commerce'); ?>">
                            </div>
                            <div class="swac-desk-field">
                                <label for="swac-add-brand"><?php esc_html_e('Brand', 'stateless-wa-commerce'); ?></label>
                                <input type="text" id="swac-add-brand" name="brand" class="swac-desk-input" list="swac-brands-list" placeholder="<?php esc_attr_e('e.g. HUL / ITC / Nestle', 'stateless-wa-commerce'); ?>">
                                <datalist id="swac-brands-list">
                                    <?php foreach ($existing_brands as $b) : ?>
                                        <option value="<?php echo esc_attr($b->name); ?>"></option>
                                    <?php endforeach; ?>
                                </datalist>
                            </div>
                        </div>

                        <div class="swac-desk-grid-3" style="margin-top:14px;">
                            <div class="swac-desk-field">
                                <label for="swac-add-mrp"><strong><?php esc_html_e('MRP (₹) *', 'stateless-wa-commerce'); ?></strong></label>
                                <input type="number" id="swac-add-mrp" name="mrp" class="swac-desk-input" step="0.01" min="0" required placeholder="30.00">
                            </div>
                            <div class="swac-desk-field">
                                <label for="swac-add-ptr"><strong><?php esc_html_e('Rate / PTR (₹) *', 'stateless-wa-commerce'); ?></strong></label>
                                <input type="number" id="swac-add-ptr" name="ptr" class="swac-desk-input" step="0.01" min="0" required placeholder="24.50">
                            </div>
                            <div class="swac-desk-field">
                                <label for="swac-add-carton"><?php esc_html_e('Carton Pack', 'stateless-wa-commerce'); ?></label>
                                <input type="number" id="swac-add-carton" name="carton_pack" class="swac-desk-input" step="1" min="0" placeholder="<?php esc_attr_e('e.g. 144 pcs', 'stateless-wa-commerce'); ?>">
                            </div>
                        </div>

                        <!-- Scheme Selector -->
                        <div class="swac-desk-field" style="margin-top:16px;">
                            <label><strong><?php esc_html_e('Active Promotional Scheme', 'stateless-wa-commerce'); ?></strong></label>
                            <div style="display:flex; gap:16px; flex-wrap:wrap; margin-top:8px;">
                                <label><input type="radio" name="scheme_type" value="none" checked> <?php esc_html_e('None', 'stateless-wa-commerce'); ?></label>
                                <label><input type="radio" name="scheme_type" value="free_goods"> <?php esc_html_e('Buy X Get Y Free', 'stateless-wa-commerce'); ?></label>
                                <label><input type="radio" name="scheme_type" value="tier_discount"> <?php esc_html_e('Volume Slabs', 'stateless-wa-commerce'); ?></label>
                                <label><input type="radio" name="scheme_type" value="cash_discount"> <?php esc_html_e('Cash Discount (CD %)', 'stateless-wa-commerce'); ?></label>
                                <label><input type="radio" name="scheme_type" value="custom"> <?php esc_html_e('Custom Text', 'stateless-wa-commerce'); ?></label>
                            </div>
                        </div>

                        <!-- Dynamic Scheme Fields -->
                        <div id="swac-scheme-subfields" class="swac-desk-field swac-desk-hidden" style="margin-top:10px; background:#f8fafc; padding:12px; border-radius:6px; border:1px solid #e2e8f0;">
                            <div id="swac-sub-free_goods" class="swac-desk-grid-2 swac-desk-hidden">
                                <div><label><?php esc_html_e('Buy Qty (X)', 'stateless-wa-commerce'); ?></label><input type="number" name="scheme_buy" class="swac-desk-input" value="12"></div>
                                <div><label><?php esc_html_e('Free Qty (Y)', 'stateless-wa-commerce'); ?></label><input type="number" name="scheme_free" class="swac-desk-input" value="1"></div>
                            </div>
                            <div id="swac-sub-tier_discount" class="swac-desk-hidden">
                                <label><?php esc_html_e('Slabs (e.g. 10:2%, 25:5%)', 'stateless-wa-commerce'); ?></label>
                                <input type="text" name="scheme_tiers" class="swac-desk-input" placeholder="10:2%, 25:5%">
                            </div>
                            <div id="swac-sub-cash_discount" class="swac-desk-hidden">
                                <label><?php esc_html_e('CD % (e.g. 2)', 'stateless-wa-commerce'); ?></label>
                                <input type="number" name="scheme_cd" class="swac-desk-input" step="0.1" value="2">
                            </div>
                            <div id="swac-sub-custom" class="swac-desk-hidden">
                                <label><?php esc_html_e('Custom Scheme Badge Text', 'stateless-wa-commerce'); ?></label>
                                <input type="text" name="scheme_custom" class="swac-desk-input" placeholder="<?php esc_attr_e('e.g. Extra ₹20 off per box', 'stateless-wa-commerce'); ?>">
                            </div>
                        </div>

                        <!-- Photo Upload (Supports Phone Camera / Gallery) -->
                        <div class="swac-desk-field" style="margin-top:16px;">
                            <label for="swac-add-image"><strong><?php esc_html_e('Product Photo (Camera / Gallery)', 'stateless-wa-commerce'); ?></strong></label>
                            <input type="file" id="swac-add-image" name="image" accept="image/*" class="swac-desk-input" style="padding:8px;">
                            <div id="swac-image-preview" style="margin-top:8px;"></div>
                        </div>

                        <div style="margin-top:24px;">
                            <button type="submit" id="swac-add-submit-btn" class="swac-desk-btn swac-desk-btn--primary" style="width:100%; height:46px; font-size:15px;">
                                ➕ <?php esc_html_e('Save & Publish to Wholesale Catalog', 'stateless-wa-commerce'); ?>
                            </button>
                        </div>
                    </form>
                </div>
            </section>

            <!-- TAB 3: APPROVED RETAILERS DESK -->
            <section id="swac-tab-retailers" class="swac-desk-tab-content">
                <div style="display:flex; gap:20px; flex-wrap:wrap;">
                    <!-- Retailers List -->
                    <div style="flex:2; min-width:320px;">
                        <div class="swac-desk-toolbar">
                            <h2 style="margin:0; font-size:1.15rem;">🏪 <?php esc_html_e('Approved Accounts', 'stateless-wa-commerce'); ?></h2>
                            <button type="button" id="swac-retailers-refresh" class="swac-desk-btn swac-desk-btn--secondary">🔄 <?php esc_html_e('Refresh', 'stateless-wa-commerce'); ?></button>
                        </div>
                        <div class="swac-desk-table-wrapper">
                            <table class="swac-desk-table">
                                <thead>
                                    <tr>
                                        <th><?php esc_html_e('Store / Owner', 'stateless-wa-commerce'); ?></th>
                                        <th><?php esc_html_e('Mobile', 'stateless-wa-commerce'); ?></th>
                                        <th><?php esc_html_e('4-Digit PIN', 'stateless-wa-commerce'); ?></th>
                                        <th><?php esc_html_e('Route', 'stateless-wa-commerce'); ?></th>
                                        <th><?php esc_html_e('Status', 'stateless-wa-commerce'); ?></th>
                                        <th style="text-align:right;"><?php esc_html_e('Action', 'stateless-wa-commerce'); ?></th>
                                    </tr>
                                </thead>
                                <tbody id="swac-retailers-tbody">
                                    <tr><td colspan="6" style="text-align:center; padding:20px; color:#64748b;"><?php esc_html_e('Loading retailers...', 'stateless-wa-commerce'); ?></td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Add Retailer Card -->
                    <div style="flex:1; min-width:280px;">
                        <div class="swac-desk-card">
                            <h3 style="margin-top:0; font-size:1.1rem;">➕ <?php esc_html_e('Approve New Retailer', 'stateless-wa-commerce'); ?></h3>
                            <form id="swac-add-retailer-form">
                                <div class="swac-desk-field">
                                    <label><strong><?php esc_html_e('Shop / Store Name *', 'stateless-wa-commerce'); ?></strong></label>
                                    <input type="text" name="store_name" class="swac-desk-input" required placeholder="<?php esc_attr_e('e.g. Shree Ganesh Stores', 'stateless-wa-commerce'); ?>">
                                </div>
                                <div class="swac-desk-field" style="margin-top:10px;">
                                    <label><strong><?php esc_html_e('Mobile Number *', 'stateless-wa-commerce'); ?></strong></label>
                                    <input type="tel" name="phone" class="swac-desk-input" required placeholder="<?php esc_attr_e('10-digit number', 'stateless-wa-commerce'); ?>">
                                </div>
                                <div class="swac-desk-field" style="margin-top:10px;">
                                    <label><?php esc_html_e('4-Digit PIN (Blank = Last 4 digits)', 'stateless-wa-commerce'); ?></label>
                                    <input type="text" name="pin" maxlength="6" class="swac-desk-input" placeholder="••••">
                                </div>
                                <div class="swac-desk-field" style="margin-top:10px;">
                                    <label><?php esc_html_e('Area / Delivery Route', 'stateless-wa-commerce'); ?></label>
                                    <input type="text" name="area" class="swac-desk-input" placeholder="<?php esc_attr_e('e.g. Fancy Bazar', 'stateless-wa-commerce'); ?>">
                                </div>
                                <div style="margin-top:16px;">
                                    <button type="submit" class="swac-desk-btn swac-desk-btn--primary" style="width:100%;">
                                        <?php esc_html_e('Save & Activate PIN', 'stateless-wa-commerce'); ?>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>
    <?php
    return (string)ob_get_clean();
}
add_shortcode('swac_distributor_portal', 'swac_distributor_portal_shortcode');
add_shortcode('swac_frontend_admin', 'swac_distributor_portal_shortcode');


/**
 * Enqueues assets strictly when the distributor desk shortcode is present.
 */
add_action('wp_enqueue_scripts', function () {
    global $post;
    if (is_a($post, 'WP_Post') && (has_shortcode($post->post_content, 'swac_distributor_portal') || has_shortcode($post->post_content, 'swac_frontend_admin'))) {
        wp_enqueue_style('swac-desk-style', SWAC_URL . 'assets/css/distributor-desk.css', [], SWAC_VERSION);
        wp_enqueue_script('swac-desk-script', SWAC_URL . 'assets/js/distributor-desk.js', [], SWAC_VERSION, true);
        wp_localize_script('swac-desk-script', 'swacDeskConfig', [
            'rest_url' => esc_url_raw(rest_url()),
            'nonce'    => wp_create_nonce('wp_rest'),
            'is_admin' => current_user_can('manage_woocommerce'),
            'shop_url' => esc_url(wc_get_page_permalink('shop') ?: home_url('/')),
            'currency' => function_exists('get_woocommerce_currency_symbol') ? get_woocommerce_currency_symbol() : '₹',
        ]);
    }
});