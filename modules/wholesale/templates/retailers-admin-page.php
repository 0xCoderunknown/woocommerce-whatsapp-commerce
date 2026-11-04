<?php
/**
 * 🏪 WHOLESALE MODULE: APPROVED RETAILERS ADMIN TEMPLATE
 *
 * View template for the wp-admin Approved Retailers management page.
 *
 * @var string $message Success message.
 * @var string $error Error message.
 * @var array $retailers List of approved retailers.
 * @var string $master_pin Current Master Desk PIN.
 *
 * @package StatelessWaCommerce
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap">
    <h1><?php esc_html_e('🏪 Approved Wholesale Retailers', 'stateless-wa-commerce'); ?></h1>
    <p class="description">
        <?php esc_html_e('Manage approved retailer accounts for zero-cost mobile + 4-digit PIN login. Only active retailers can view wholesale rates, margins, and schemes.', 'stateless-wa-commerce'); ?>
    </p>

    <?php if (!empty($message)) : ?>
        <div class="notice notice-success is-dismissible"><p><?php echo esc_html($message); ?></p></div>
    <?php endif; ?>
    <?php if (!empty($error)) : ?>
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
                                <form method="post" style="display:inline;"
                                      onsubmit="return confirm('<?php esc_attr_e('Are you sure you want to delete this retailer?', 'stateless-wa-commerce'); ?>');">
                                    <?php wp_nonce_field('swac_retailer_admin_nonce'); ?>
                                    <input type="hidden" name="swac_retailer_action" value="delete">
                                    <input type="hidden" name="phone" value="<?php echo esc_attr($r['phone']); ?>">
                                    <button type="submit" class="button button-small"
                                            style="color:#b91c1c; border-color:#fca5a5;">✕
                                    </button>
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
                    <input type="text" id="swac_store_name" name="store_name" class="regular-text"
                           style="width:100%;" required
                           placeholder="<?php esc_attr_e('e.g. Apex Wholesale Mart', 'stateless-wa-commerce'); ?>">
                </p>

                <p>
                    <label for="swac_phone"><strong><?php esc_html_e('Mobile Number *', 'stateless-wa-commerce'); ?></strong></label><br>
                    <input type="tel" id="swac_phone" name="phone" class="regular-text" style="width:100%;" required
                           placeholder="<?php esc_attr_e('e.g. 9876543210', 'stateless-wa-commerce'); ?>">
                </p>

                <p>
                    <label for="swac_pin"><strong><?php esc_html_e('4-Digit Login PIN', 'stateless-wa-commerce'); ?></strong></label><br>
                    <input type="text" id="swac_pin" name="pin" maxlength="6" class="regular-text"
                           style="width:100%;"
                           placeholder="<?php esc_attr_e('Leave blank for last 4 digits', 'stateless-wa-commerce'); ?>">
                    <span class="description"
                          style="font-size:11px;"><?php esc_html_e('If empty, defaults to the last 4 digits of their phone.', 'stateless-wa-commerce'); ?></span>
                </p>

                <p>
                    <label for="swac_owner_name"><?php esc_html_e('Contact Person / Owner', 'stateless-wa-commerce'); ?></label><br>
                    <input type="text" id="swac_owner_name" name="owner_name" class="regular-text"
                           style="width:100%;"
                           placeholder="<?php esc_attr_e('e.g. Retail Store Owner', 'stateless-wa-commerce'); ?>">
                </p>

                <p>
                    <label for="swac_area"><?php esc_html_e('Area / Delivery Route', 'stateless-wa-commerce'); ?></label><br>
                    <input type="text" id="swac_area" name="area" class="regular-text" style="width:100%;"
                           placeholder="<?php esc_attr_e('e.g. Central Market Route', 'stateless-wa-commerce'); ?>">
                </p>

                <p>
                    <label for="swac_status"><strong><?php esc_html_e('Status', 'stateless-wa-commerce'); ?></strong></label><br>
                    <select id="swac_status" name="status" style="width:100%;">
                        <option value="active"><?php esc_html_e('Active (Can view rates)', 'stateless-wa-commerce'); ?></option>
                        <option value="suspended"><?php esc_html_e('Suspended (Blocked)', 'stateless-wa-commerce'); ?></option>
                    </select>
                </p>

                <p style="margin-top:20px;">
                    <button type="submit" class="button button-primary"
                            style="width:100%; height:36px;"><?php esc_html_e('Save & Approve Retailer', 'stateless-wa-commerce'); ?></button>
                </p>
            </form>

            <!-- Master Desk PIN Card -->
            <div style="background:#f8fafc; border:1px solid #e2e8f0; padding:16px; border-radius:6px; margin-top:20px;">
                <h4 style="margin:0 0 8px 0; font-size:13px;">
                    🔐 <?php esc_html_e('Distributor Desk Master PIN', 'stateless-wa-commerce'); ?></h4>
                <p class="description" style="margin-bottom:12px; font-size:12px;">
                    <?php esc_html_e('Used to unlock the frontend distributor portal from mobile phones without WordPress login.', 'stateless-wa-commerce'); ?>
                </p>
                <form method="post">
                    <?php wp_nonce_field('swac_retailer_admin_nonce'); ?>
                    <input type="hidden" name="swac_retailer_action" value="update_master_pin">
                    <p style="margin-bottom:10px;">
                        <input type="text" name="master_pin"
                               value="<?php echo esc_attr($master_pin); ?>"
                               placeholder="e.g. 1234" class="regular-text" maxlength="8"
                               style="font-size:15px; letter-spacing:2px; width:100%; text-align:center;">
                    </p>
                    <button type="submit" class="button"
                            style="width:100%;"><?php esc_html_e('Update Master PIN', 'stateless-wa-commerce'); ?></button>
                </form>
            </div>
        </div>
    </div>
</div>
