<?php
/**
 * 🔐 WHOLESALE / B2B MODULE: RETAILER AUTHENTICATION & DIRECTORY
 *
 * Provides zero-cost phone + 4-digit PIN authentication, REST API login verification,
 * and wp-admin retailer management directory for approved B2B accounts.
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
 * Option key used for storing the approved retailer registry.
 */
define('SWAC_RETAILER_OPTION', 'swac_b2b_approved_retailers');

/**
 * Retrieves the full registry of approved retailers.
 *
 * @return array<string, array{phone: string, pin: string, store_name: string, owner_name: string, area: string, status: string, created_at: string}>
 */
function swac_get_approved_retailers(): array
{
    $retailers = get_option(SWAC_RETAILER_OPTION, []);
    return is_array($retailers) ? $retailers : [];
}

/**
 * Saves a retailer record to the registry.
 *
 * @param array $data Retailer details.
 * @return bool True on success, false on validation failure.
 */
function swac_save_approved_retailer(array $data): bool
{
    $phone = function_exists('swac_sanitize_phone') ? swac_sanitize_phone($data['phone'] ?? '') : preg_replace('/[^0-9]/', '', (string)($data['phone'] ?? ''));
    if (strlen($phone) < 10) {
        return false;
    }

    $pin = sanitize_text_field((string)($data['pin'] ?? ''));
    if ($pin === '') {
        $pin = substr($phone, -4); // Default to last 4 digits of phone
    }

    $store_name = sanitize_text_field((string)($data['store_name'] ?? ''));
    if ($store_name === '') {
        $store_name = sprintf(__('Retailer %s', 'stateless-wa-commerce'), substr($phone, -4));
    }

    $owner_name = sanitize_text_field((string)($data['owner_name'] ?? ''));
    $area = sanitize_text_field((string)($data['area'] ?? ''));
    $status = in_array($data['status'] ?? 'active', ['active', 'suspended'], true) ? $data['status'] : 'active';

    $retailers = swac_get_approved_retailers();
    $retailers[$phone] = [
        'phone'      => $phone,
        'pin'        => $pin,
        'store_name' => $store_name,
        'owner_name' => $owner_name,
        'area'       => $area,
        'status'     => $status,
        'created_at' => $retailers[$phone]['created_at'] ?? gmdate('d-m-Y'),
    ];

    return update_option(SWAC_RETAILER_OPTION, $retailers);
}

/**
 * Removes a retailer record by phone number.
 *
 * @param string $phone Retailer phone number.
 * @return bool
 */
function swac_delete_approved_retailer(string $phone): bool
{
    $phone = function_exists('swac_sanitize_phone') ? swac_sanitize_phone($phone) : preg_replace('/[^0-9]/', '', $phone);
    $retailers = swac_get_approved_retailers();
    if (isset($retailers[$phone])) {
        unset($retailers[$phone]);
        return update_option(SWAC_RETAILER_OPTION, $retailers);
    }
    return false;
}

/**
 * Registers REST API endpoint for retailer PIN verification: POST /wp-json/swac/v1/retailer-login
 */
add_action('rest_api_init', function () {
    register_rest_route('swac/v1', '/retailer-login', [
        'methods'             => 'POST',
        'callback'            => 'swac_rest_retailer_login',
        'permission_callback' => '__return_true',
    ]);
});

/**
 * Handles the retailer login verification request.
 *
 * @param WP_REST_Request $request
 * @return WP_REST_Response
 */
function swac_rest_retailer_login(WP_REST_Request $request): WP_REST_Response
{
    $params = $request->get_json_params();
    if (empty($params)) {
        $params = $request->get_body_params();
    }

    $raw_phone = $params['phone'] ?? '';
    $raw_pin = (string)($params['pin'] ?? '');

    $phone = function_exists('swac_sanitize_phone') ? swac_sanitize_phone($raw_phone) : preg_replace('/[^0-9]/', '', (string)$raw_phone);
    if (strlen($phone) < 10 || $raw_pin === '') {
        return new WP_REST_Response([
            'success' => false,
            'message' => __('Please enter a valid 10-digit mobile number and PIN.', 'stateless-wa-commerce'),
        ], 400);
    }

    // Match against standard 10 digits (handling +91 or 91 prefixes)
    $clean_10 = substr($phone, -10);
    $retailers = swac_get_approved_retailers();
    $matched_retailer = null;

    foreach ($retailers as $r_phone => $r_data) {
        if (substr($r_phone, -10) === $clean_10) {
            $matched_retailer = $r_data;
            break;
        }
    }

    if (!$matched_retailer) {
        return new WP_REST_Response([
            'success' => false,
            'message' => __('Retailer number not registered. Please tap "Register via WhatsApp" to request approval.', 'stateless-wa-commerce'),
        ], 403);
    }

    if (($matched_retailer['status'] ?? 'active') !== 'active') {
        return new WP_REST_Response([
            'success' => false,
            'message' => __('Your wholesale account is currently suspended. Please contact distributor.', 'stateless-wa-commerce'),
        ], 403);
    }

    if ((string)$matched_retailer['pin'] !== $raw_pin) {
        return new WP_REST_Response([
            'success' => false,
            'message' => __('Incorrect 4-digit PIN. Please re-enter or request PIN from distributor.', 'stateless-wa-commerce'),
        ], 403);
    }

    // Generate lightweight authentication token
    $salt = defined('AUTH_KEY') ? AUTH_KEY : 'swac-b2b-token-salt';
    $token = hash_hmac('sha256', $clean_10 . '|' . $raw_pin, $salt);

    return new WP_REST_Response([
        'success'  => true,
        'retailer' => [
            'phone'      => $matched_retailer['phone'],
            'store_name' => $matched_retailer['store_name'],
            'owner_name' => $matched_retailer['owner_name'],
            'area'       => $matched_retailer['area'],
            'token'      => $token,
        ],
    ], 200);
}

/**
 * Adds "Approved Retailers" admin management screen under Products.
 */
add_action('admin_menu', function () {
    add_submenu_page(
        'edit.php?post_type=product',
        __('Approved Retailers', 'stateless-wa-commerce'),
        __('Approved Retailers', 'stateless-wa-commerce'),
        'manage_woocommerce',
        'swac-retailers',
        'swac_render_retailers_admin_page'
    );
});

/**
 * Renders the Approved Retailers wp-admin management page.
 */
function swac_render_retailers_admin_page()
{
    if (!current_user_can('manage_woocommerce')) {
        wp_die(esc_html__('Access Denied: Insufficient privileges.', 'stateless-wa-commerce'));
    }

    $message = '';
    $error = '';

    // Handle form submissions
    if (isset($_POST['swac_retailer_action']) && check_admin_referer('swac_retailer_admin_nonce')) {
        $action = sanitize_key($_POST['swac_retailer_action']);

        if ($action === 'add' || $action === 'edit') {
            $saved = swac_save_approved_retailer([
                'phone'      => $_POST['phone'] ?? '',
                'pin'        => $_POST['pin'] ?? '',
                'store_name' => $_POST['store_name'] ?? '',
                'owner_name' => $_POST['owner_name'] ?? '',
                'area'       => $_POST['area'] ?? '',
                'status'     => $_POST['status'] ?? 'active',
            ]);

            if ($saved) {
                $message = __('Retailer details successfully saved.', 'stateless-wa-commerce');
            } else {
                $error = __('Failed to save retailer. Please check mobile number.', 'stateless-wa-commerce');
            }
        } elseif ($action === 'delete') {
            $phone = sanitize_text_field($_POST['phone'] ?? '');
            if (swac_delete_approved_retailer($phone)) {
                $message = __('Retailer successfully removed from directory.', 'stateless-wa-commerce');
            }
        } elseif ($action === 'update_master_pin') {
            $master_pin = sanitize_text_field($_POST['master_pin'] ?? '');
            if (strlen($master_pin) >= 4) {
                update_option('swac_b2b_master_pin', $master_pin);
                $message = __('Master Desk PIN updated successfully.', 'stateless-wa-commerce');
            } else {
                $error = __('Master PIN must be at least 4 digits.', 'stateless-wa-commerce');
            }
        }
    }

    $retailers = swac_get_approved_retailers();
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('🏪 Approved Wholesale Retailers', 'stateless-wa-commerce'); ?></h1>
        <p class="description">
            <?php esc_html_e('Manage approved retailer accounts for zero-cost mobile + 4-digit PIN login. Only active retailers can view wholesale rates, margins, and schemes.', 'stateless-wa-commerce'); ?>
        </p>

        <?php if ($message) : ?>
            <div class="notice notice-success is-dismissible"><p><?php echo esc_html($message); ?></p></div>
        <?php endif; ?>
        <?php if ($error) : ?>
            <div class="notice notice-error is-dismissible"><p><?php echo esc_html($error); ?></p></div>
        <?php endif; ?>

        <div style="display:flex; gap:24px; margin-top:20px; flex-wrap:wrap;">
            <!-- Retailer Table -->
            <div style="flex:2; min-width:350px;">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width:28%;"><?php esc_html_e('Shop / Store Name', 'stateless-wa-commerce'); ?></th>
                            <th style="width:20%;"><?php esc_html_e('Mobile Number', 'stateless-wa-commerce'); ?></th>
                            <th style="width:14%;"><?php esc_html_e('4-Digit PIN', 'stateless-wa-commerce'); ?></th>
                            <th style="width:18%;"><?php esc_html_e('Area / Route', 'stateless-wa-commerce'); ?></th>
                            <th style="width:10%;"><?php esc_html_e('Status', 'stateless-wa-commerce'); ?></th>
                            <th style="width:10%; text-align:right;"><?php esc_html_e('Action', 'stateless-wa-commerce'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($retailers)) : ?>
                            <tr>
                                <td colspan="6" style="padding:20px; text-align:center; color:#64748b;">
                                    <?php esc_html_e('No approved retailers found yet. Add your first retailer using the form on the right.', 'stateless-wa-commerce'); ?>
                                </td>
                            </tr>
                        <?php else : ?>
                            <?php foreach ($retailers as $r) : ?>
                                <tr>
                                    <td>
                                        <strong><?php echo esc_html($r['store_name']); ?></strong>
                                        <?php if (!empty($r['owner_name'])) : ?>
                                            <div style="font-size:11px; color:#64748b;"><?php echo esc_html($r['owner_name']); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td><code><?php echo esc_html($r['phone']); ?></code></td>
                                    <td><strong style="letter-spacing:1px;"><?php echo esc_html($r['pin']); ?></strong></td>
                                    <td><?php echo esc_html($r['area'] ?: '—'); ?></td>
                                    <td>
                                        <?php if (($r['status'] ?? 'active') === 'active') : ?>
                                            <span style="color:#15803d; font-weight:700;"><?php esc_html_e('Active', 'stateless-wa-commerce'); ?></span>
                                        <?php else : ?>
                                            <span style="color:#b91c1c; font-weight:700;"><?php esc_html_e('Suspended', 'stateless-wa-commerce'); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align:right;">
                                        <form method="post" style="display:inline;" onsubmit="return confirm('<?php esc_attr_e('Are you sure you want to delete this retailer?', 'stateless-wa-commerce'); ?>');">
                                            <?php wp_nonce_field('swac_retailer_admin_nonce'); ?>
                                            <input type="hidden" name="swac_retailer_action" value="delete">
                                            <input type="hidden" name="phone" value="<?php echo esc_attr($r['phone']); ?>">
                                            <button type="submit" class="button button-small" style="color:#b91c1c; border-color:#fca5a5;">✕</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Add / Edit Form -->
            <div style="flex:1; min-width:280px; background:#fff; border:1px solid #ccd0d4; padding:18px; border-radius:6px; box-shadow:0 1px 1px rgba(0,0,0,0.04);">
                <h3 style="margin-top:0;"><?php esc_html_e('➕ Add / Approve Retailer', 'stateless-wa-commerce'); ?></h3>
                <form method="post">
                    <?php wp_nonce_field('swac_retailer_admin_nonce'); ?>
                    <input type="hidden" name="swac_retailer_action" value="add">

                    <p>
                        <label for="swac_store_name"><strong><?php esc_html_e('Shop / Store Name *', 'stateless-wa-commerce'); ?></strong></label><br>
                        <input type="text" id="swac_store_name" name="store_name" class="regular-text" style="width:100%;" required placeholder="<?php esc_attr_e('e.g. Shree Ganesh Stores', 'stateless-wa-commerce'); ?>">
                    </p>

                    <p>
                        <label for="swac_phone"><strong><?php esc_html_e('Mobile Number *', 'stateless-wa-commerce'); ?></strong></label><br>
                        <input type="tel" id="swac_phone" name="phone" class="regular-text" style="width:100%;" required placeholder="<?php esc_attr_e('e.g. 9876543210', 'stateless-wa-commerce'); ?>">
                    </p>

                    <p>
                        <label for="swac_pin"><strong><?php esc_html_e('4-Digit Login PIN', 'stateless-wa-commerce'); ?></strong></label><br>
                        <input type="text" id="swac_pin" name="pin" maxlength="6" class="regular-text" style="width:100%;" placeholder="<?php esc_attr_e('Leave blank for last 4 digits', 'stateless-wa-commerce'); ?>">
                        <span class="description" style="font-size:11px;"><?php esc_html_e('If empty, defaults to the last 4 digits of their phone.', 'stateless-wa-commerce'); ?></span>
                    </p>

                    <p>
                        <label for="swac_owner_name"><?php esc_html_e('Contact Person / Owner', 'stateless-wa-commerce'); ?></label><br>
                        <input type="text" id="swac_owner_name" name="owner_name" class="regular-text" style="width:100%;" placeholder="<?php esc_attr_e('e.g. Ramesh Sharma', 'stateless-wa-commerce'); ?>">
                    </p>

                    <p>
                        <label for="swac_area"><?php esc_html_e('Area / Delivery Route', 'stateless-wa-commerce'); ?></label><br>
                        <input type="text" id="swac_area" name="area" class="regular-text" style="width:100%;" placeholder="<?php esc_attr_e('e.g. Fancy Bazar / Beltola', 'stateless-wa-commerce'); ?>">
                    </p>

                    <p>
                        <label for="swac_status"><strong><?php esc_html_e('Status', 'stateless-wa-commerce'); ?></strong></label><br>
                        <select id="swac_status" name="status" style="width:100%;">
                            <option value="active"><?php esc_html_e('Active (Can view rates)', 'stateless-wa-commerce'); ?></option>
                            <option value="suspended"><?php esc_html_e('Suspended (Blocked)', 'stateless-wa-commerce'); ?></option>
                        </select>
                    </p>

                    <p style="margin-top:20px;">
                        <button type="submit" class="button button-primary" style="width:100%; height:36px;"><?php esc_html_e('Save & Approve Retailer', 'stateless-wa-commerce'); ?></button>
                    </p>
                </form>

                <!-- Master Desk PIN Card -->
                <div style="background:#f8fafc; border:1px solid #e2e8f0; padding:16px; border-radius:6px; margin-top:20px;">
                    <h4 style="margin:0 0 8px 0; font-size:13px;">🔐 <?php esc_html_e('Distributor Desk Master PIN', 'stateless-wa-commerce'); ?></h4>
                    <p class="description" style="margin-bottom:12px; font-size:12px;">
                        <?php esc_html_e('Used to unlock the frontend /distributor/ portal from mobile phones without WordPress login.', 'stateless-wa-commerce'); ?>
                    </p>
                    <form method="post">
                        <?php wp_nonce_field('swac_retailer_admin_nonce'); ?>
                        <input type="hidden" name="swac_retailer_action" value="update_master_pin">
                        <p style="margin-bottom:10px;">
                            <input type="text" name="master_pin" value="<?php echo esc_attr(get_option('swac_b2b_master_pin', '')); ?>" placeholder="e.g. 1234" class="regular-text" maxlength="8" style="font-size:15px; letter-spacing:2px; width:100%; text-align:center;">
                        </p>
                        <button type="submit" class="button" style="width:100%;"><?php esc_html_e('Update Master PIN', 'stateless-wa-commerce'); ?></button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php
}
