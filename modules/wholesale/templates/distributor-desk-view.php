<?php
/**
 * 🖥️ WHOLESALE / B2B: DISTRIBUTOR DESK VIEW TEMPLATE
 *
 * View markup for the [swac_distributor_portal] shortcode.
 *
 * Available variables:
 * - bool  $is_logged_in     Whether the current user is an authorized admin/shop manager.
 * - array $existing_brands  List of brand WP_Term objects.
 *
 * @package StatelessWaCommerce
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div id="swac-desk-root" class="swac-desk-container" data-auth="<?php echo $is_logged_in ? '1' : '0'; ?>">
    <!-- 1. Master Passcode Gate (Visible only when accessing without WP login) -->
    <div id="swac-desk-login-box" class="swac-desk-card <?php echo $is_logged_in ? 'swac-desk-hidden' : ''; ?>"
         style="max-width:380px; margin:40px auto; text-align:center;">
        <div style="font-size:32px; margin-bottom:10px;">🔐</div>
        <h2 style="margin:0 0 8px 0; font-size:1.3rem;"><?php esc_html_e('Distributor Desk Login', 'woocommerce-whatsapp-commerce'); ?></h2>
        <p style="font-size:0.85rem; color:#64748b; margin-bottom:20px;"><?php esc_html_e('Enter your Master PIN to access wholesale rate controls and product entry.', 'woocommerce-whatsapp-commerce'); ?></p>
        <form id="swac-desk-auth-form">
            <input type="password" id="swac-desk-pin-input" class="swac-desk-input" placeholder="••••" maxlength="8"
                   inputmode="numeric" required
                   style="text-align:center; font-size:24px; letter-spacing:4px; height:48px; margin-bottom:16px;">
            <div id="swac-desk-auth-error" class="swac-desk-alert swac-desk-hidden"
                 style="margin-bottom:14px;"></div>
            <button type="submit" class="swac-desk-btn swac-desk-btn--primary"
                    style="width:100%; height:44px;"><?php esc_html_e('Unlock Distributor Desk', 'woocommerce-whatsapp-commerce'); ?></button>
        </form>
    </div>

    <!-- 2. Main Distributor Desk (Visible when authorized) -->
    <div id="swac-desk-app" class="<?php echo $is_logged_in ? '' : 'swac-desk-hidden'; ?>">
        <!-- Top Header & Tab Navigation -->
        <header class="swac-desk-header">
            <div class="swac-desk-header-left">
                <h1 class="swac-desk-brand-title">
                    📦 <?php esc_html_e('Distributor Desk', 'woocommerce-whatsapp-commerce'); ?></h1>
                <span id="swac-desk-role-badge" class="swac-desk-badge"><?php esc_html_e('Counter Speed', 'woocommerce-whatsapp-commerce'); ?></span>
            </div>
            <div class="swac-desk-header-right">
                <a href="<?php echo esc_url(wc_get_page_permalink('shop') ?: home_url('/')); ?>"
                   class="swac-desk-btn swac-desk-btn--outline" target="_blank" rel="noopener">
                    <?php esc_html_e('View Live Shop ↗', 'woocommerce-whatsapp-commerce'); ?>
                </a>
                <button type="button" id="swac-desk-lock-btn" class="swac-desk-btn swac-desk-btn--outline"
                        style="margin-left:8px;" title="<?php esc_attr_e('Lock Distributor Desk', 'woocommerce-whatsapp-commerce'); ?>">
                    🔒 <?php esc_html_e('Lock', 'woocommerce-whatsapp-commerce'); ?>
                </button>
            </div>
        </header>

        <!-- Navigation Tabs -->
        <nav class="swac-desk-tabs" role="tablist">
            <button type="button" class="swac-desk-tab-btn swac-desk-tab-btn--active" data-tab="tab-rates"
                    role="tab">
                ⚡ <?php esc_html_e('Quick Rate Sheet', 'woocommerce-whatsapp-commerce'); ?>
            </button>
            <button type="button" class="swac-desk-tab-btn swac-desk-owner-only" data-tab="tab-add" role="tab">
                ➕ <?php esc_html_e('Add Product', 'woocommerce-whatsapp-commerce'); ?>
            </button>
            <button type="button" class="swac-desk-tab-btn swac-desk-owner-only" data-tab="tab-retailers" role="tab">
                🏪 <?php esc_html_e('Approved Retailers', 'woocommerce-whatsapp-commerce'); ?>
            </button>
            <button type="button" class="swac-desk-tab-btn swac-desk-owner-only" data-tab="tab-settings" role="tab">
                ⚙️ <?php esc_html_e('Store Settings', 'woocommerce-whatsapp-commerce'); ?>
            </button>
        </nav>

        <!-- Notification Toast / Banner -->
        <div id="swac-desk-toast" class="swac-desk-toast swac-desk-hidden"></div>

        <!-- TAB 1: QUICK RATE SHEET -->
        <section id="swac-tab-rates" class="swac-desk-tab-content swac-desk-tab-content--active">
            <div class="swac-desk-toolbar" style="flex-wrap:wrap;">
                <input type="text" id="swac-rates-search" class="swac-desk-input" style="flex:1; min-width:200px;"
                       placeholder="<?php esc_attr_e('🔍 Fast search product name or brand...', 'woocommerce-whatsapp-commerce'); ?>">
                <button type="button" id="swac-rates-refresh" class="swac-desk-btn swac-desk-btn--secondary">
                    🔄 <?php esc_html_e('Refresh', 'woocommerce-whatsapp-commerce'); ?>
                </button>
                <input type="file" id="swac-rates-import-file" accept=".csv" style="display:none;">
                <button type="button" id="swac-rates-import-btn" class="swac-desk-btn swac-desk-btn--secondary swac-desk-owner-only"
                        title="<?php esc_attr_e('Bulk update rates & stock from CSV file', 'woocommerce-whatsapp-commerce'); ?>">
                    📤 <?php esc_html_e('Import CSV', 'woocommerce-whatsapp-commerce'); ?>
                </button>
                <button type="button" id="swac-rates-export-btn" class="swac-desk-btn swac-desk-btn--secondary swac-desk-owner-only"
                        title="<?php esc_attr_e('Download current rates as spreadsheet CSV', 'woocommerce-whatsapp-commerce'); ?>">
                    📥 <?php esc_html_e('Export CSV', 'woocommerce-whatsapp-commerce'); ?>
                </button>
            </div>

            <div class="swac-desk-table-wrapper">
                <table class="swac-desk-table swac-fast-grid">
                    <thead>
                    <tr>
                        <th style="width:28%;"><?php esc_html_e('Item / Brand', 'woocommerce-whatsapp-commerce'); ?></th>
                        <th style="width:16%;"><?php esc_html_e('Stock', 'woocommerce-whatsapp-commerce'); ?></th>
                        <th style="width:13%;"><?php esc_html_e('Rate / PTR (₹)', 'woocommerce-whatsapp-commerce'); ?></th>
                        <th style="width:13%;"><?php esc_html_e('Retail MRP (₹)', 'woocommerce-whatsapp-commerce'); ?></th>
                        <th style="width:14%;"><?php esc_html_e('Scheme', 'woocommerce-whatsapp-commerce'); ?></th>
                        <th style="width:8%;"><?php esc_html_e('MOQ', 'woocommerce-whatsapp-commerce'); ?></th>
                        <th style="width:8%; text-align:right;"><?php esc_html_e('Status', 'woocommerce-whatsapp-commerce'); ?></th>
                    </tr>
                    </thead>
                    <tbody id="swac-rates-tbody">
                    <tr>
                        <td colspan="7"
                            style="text-align:center; padding:30px; color:#64748b;"><?php esc_html_e('Loading products...', 'woocommerce-whatsapp-commerce'); ?></td>
                    </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- TAB 2: FAST PRODUCT ENTRY -->
        <section id="swac-tab-add" class="swac-desk-tab-content">
            <div class="swac-desk-card" style="max-width:820px; margin:0 auto; width:100%;">
                <h2 style="margin:0 0 16px 0; font-size:1.25rem;">
                    ➕ <?php esc_html_e('Add New Wholesale Product', 'woocommerce-whatsapp-commerce'); ?></h2>
                <form id="swac-add-product-form" enctype="multipart/form-data">
                    <div class="swac-desk-grid-2">
                        <div class="swac-desk-field">
                            <label for="swac-add-name"><strong><?php esc_html_e('Product Title *', 'woocommerce-whatsapp-commerce'); ?></strong></label>
                            <input type="text" id="swac-add-name" name="name" class="swac-desk-input" required
                                   placeholder="<?php esc_attr_e('e.g. Rin Bar 250g', 'woocommerce-whatsapp-commerce'); ?>">
                        </div>
                        <div class="swac-desk-field">
                            <label for="swac-add-brand"><?php esc_html_e('Brand', 'woocommerce-whatsapp-commerce'); ?></label>
                            <input type="text" id="swac-add-brand" name="brand" class="swac-desk-input"
                                   list="swac-brands-list"
                                   placeholder="<?php esc_attr_e('e.g. HUL / ITC / Nestle', 'woocommerce-whatsapp-commerce'); ?>">
                            <datalist id="swac-brands-list">
                                <?php foreach ($existing_brands as $b) : ?>
                                    <option value="<?php echo esc_attr($b->name); ?>"></option>
                                <?php endforeach; ?>
                            </datalist>
                        </div>
                    </div>

                    <div class="swac-desk-grid-3" style="margin-top:14px;">
                        <div class="swac-desk-field">
                            <label for="swac-add-mrp"><strong><?php esc_html_e('MRP (₹) *', 'woocommerce-whatsapp-commerce'); ?></strong></label>
                            <input type="number" id="swac-add-mrp" name="mrp" class="swac-desk-input" step="0.01"
                                   min="0" required placeholder="30.00">
                        </div>
                        <div class="swac-desk-field">
                            <label for="swac-add-ptr"><strong><?php esc_html_e('Rate / PTR (₹) *', 'woocommerce-whatsapp-commerce'); ?></strong></label>
                            <input type="number" id="swac-add-ptr" name="ptr" class="swac-desk-input" step="0.01"
                                   min="0" required placeholder="24.50">
                        </div>
                        <div class="swac-desk-field">
                            <label for="swac-add-carton"><?php esc_html_e('Carton Pack', 'woocommerce-whatsapp-commerce'); ?></label>
                            <input type="number" id="swac-add-carton" name="carton_pack" class="swac-desk-input"
                                   step="1" min="0"
                                   placeholder="<?php esc_attr_e('e.g. 144 pcs', 'woocommerce-whatsapp-commerce'); ?>">
                        </div>
                    </div>

                    <!-- Scheme Selector -->
                    <div class="swac-desk-field" style="margin-top:16px;">
                        <label><strong><?php esc_html_e('Active Promotional Scheme', 'woocommerce-whatsapp-commerce'); ?></strong></label>
                        <div style="display:flex; gap:16px; flex-wrap:wrap; margin-top:8px;">
                            <label><input type="radio" name="scheme_type" value="none"
                                          checked> <?php esc_html_e('None', 'woocommerce-whatsapp-commerce'); ?></label>
                            <label><input type="radio" name="scheme_type"
                                          value="free_goods"> <?php esc_html_e('Buy X Get Y Free', 'woocommerce-whatsapp-commerce'); ?>
                            </label>
                            <label><input type="radio" name="scheme_type"
                                          value="tier_discount"> <?php esc_html_e('Volume Slabs', 'woocommerce-whatsapp-commerce'); ?>
                            </label>
                            <label><input type="radio" name="scheme_type"
                                          value="cash_discount"> <?php esc_html_e('Cash Discount (CD %)', 'woocommerce-whatsapp-commerce'); ?>
                            </label>
                            <label><input type="radio" name="scheme_type"
                                          value="custom"> <?php esc_html_e('Custom Text', 'woocommerce-whatsapp-commerce'); ?>
                            </label>
                        </div>
                    </div>

                    <!-- Dynamic Scheme Fields -->
                    <div id="swac-scheme-subfields" class="swac-desk-field swac-desk-hidden"
                         style="margin-top:10px; background:#f8fafc; padding:12px; border-radius:6px; border:1px solid #e2e8f0;">
                        <div id="swac-sub-free_goods" class="swac-desk-grid-2 swac-desk-hidden">
                            <div><label><?php esc_html_e('Buy Qty (X)', 'woocommerce-whatsapp-commerce'); ?></label><input
                                        type="number" name="scheme_buy" class="swac-desk-input" value="12"></div>
                            <div><label><?php esc_html_e('Free Qty (Y)', 'woocommerce-whatsapp-commerce'); ?></label><input
                                        type="number" name="scheme_free" class="swac-desk-input" value="1"></div>
                        </div>
                        <div id="swac-sub-tier_discount" class="swac-desk-hidden">
                            <label><?php esc_html_e('Slabs (e.g. 10:2%, 25:5%)', 'woocommerce-whatsapp-commerce'); ?></label>
                            <input type="text" name="scheme_tiers" class="swac-desk-input"
                                   placeholder="10:2%, 25:5%">
                        </div>
                        <div id="swac-sub-cash_discount" class="swac-desk-hidden">
                            <label><?php esc_html_e('CD % (e.g. 2)', 'woocommerce-whatsapp-commerce'); ?></label>
                            <input type="number" name="scheme_cd" class="swac-desk-input" step="0.1" value="2">
                        </div>
                        <div id="swac-sub-custom" class="swac-desk-hidden">
                            <label><?php esc_html_e('Custom Scheme Badge Text', 'woocommerce-whatsapp-commerce'); ?></label>
                            <input type="text" name="scheme_custom" class="swac-desk-input"
                                   placeholder="<?php esc_attr_e('e.g. Extra ₹20 off per box', 'woocommerce-whatsapp-commerce'); ?>">
                        </div>
                    </div>

                    <!-- Photo Upload (Supports Phone Camera / Gallery) -->
                    <div class="swac-desk-field" style="margin-top:16px;">
                        <label for="swac-add-image"><strong><?php esc_html_e('Product Photo (Camera / Gallery)', 'woocommerce-whatsapp-commerce'); ?></strong></label>
                        <input type="file" id="swac-add-image" name="image" accept="image/*" class="swac-desk-input"
                               style="padding:8px;">
                        <div id="swac-image-preview" style="margin-top:8px;"></div>
                    </div>

                    <div style="margin-top:24px;">
                        <button type="submit" id="swac-add-submit-btn" class="swac-desk-btn swac-desk-btn--primary"
                                style="width:100%; height:46px; font-size:15px;">
                            ➕ <?php esc_html_e('Save & Publish to Wholesale Catalog', 'woocommerce-whatsapp-commerce'); ?>
                        </button>
                    </div>
                </form>
            </div>
        </section>

        <!-- TAB 3: APPROVED RETAILERS DESK -->
        <section id="swac-tab-retailers" class="swac-desk-tab-content">
            <div style="display:flex; gap:20px; flex-wrap:wrap; width:100%;">
                <!-- Retailers List -->
                <div style="flex:2; min-width:320px;">
                    <div class="swac-desk-toolbar">
                        <h2 style="margin:0; font-size:1.15rem;">
                            🏪 <?php esc_html_e('Approved Accounts', 'woocommerce-whatsapp-commerce'); ?></h2>
                        <button type="button" id="swac-retailers-refresh"
                                class="swac-desk-btn swac-desk-btn--secondary">
                            🔄 <?php esc_html_e('Refresh', 'woocommerce-whatsapp-commerce'); ?></button>
                    </div>
                    <div class="swac-desk-table-wrapper">
                        <table class="swac-desk-table">
                            <thead>
                            <tr>
                                <th><?php esc_html_e('Store / Owner', 'woocommerce-whatsapp-commerce'); ?></th>
                                <th><?php esc_html_e('Mobile', 'woocommerce-whatsapp-commerce'); ?></th>
                                <th><?php esc_html_e('4-Digit PIN', 'woocommerce-whatsapp-commerce'); ?></th>
                                <th><?php esc_html_e('Route', 'woocommerce-whatsapp-commerce'); ?></th>
                                <th><?php esc_html_e('Status', 'woocommerce-whatsapp-commerce'); ?></th>
                                <th style="text-align:right;"><?php esc_html_e('Action', 'woocommerce-whatsapp-commerce'); ?></th>
                            </tr>
                            </thead>
                            <tbody id="swac-retailers-tbody">
                            <tr>
                                <td colspan="6"
                                    style="text-align:center; padding:20px; color:#64748b;"><?php esc_html_e('Loading retailers...', 'woocommerce-whatsapp-commerce'); ?></td>
                            </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Add Retailer Card -->
                <div style="flex:1; min-width:280px;">
                    <div class="swac-desk-card">
                        <h3 style="margin-top:0; font-size:1.1rem;">
                            ➕ <?php esc_html_e('Approve New Retailer', 'woocommerce-whatsapp-commerce'); ?></h3>
                        <form id="swac-add-retailer-form">
                            <div class="swac-desk-field">
                                <label><strong><?php esc_html_e('Shop / Store Name *', 'woocommerce-whatsapp-commerce'); ?></strong></label>
                                <input type="text" name="store_name" class="swac-desk-input" required
                                       placeholder="<?php esc_attr_e('e.g. Shree Ganesh Stores', 'woocommerce-whatsapp-commerce'); ?>">
                            </div>
                            <div class="swac-desk-field" style="margin-top:10px;">
                                <label><strong><?php esc_html_e('Mobile Number *', 'woocommerce-whatsapp-commerce'); ?></strong></label>
                                <input type="tel" name="phone" class="swac-desk-input" required
                                       placeholder="<?php esc_attr_e('10-digit number', 'woocommerce-whatsapp-commerce'); ?>">
                            </div>
                            <div class="swac-desk-field" style="margin-top:10px;">
                                <label><?php esc_html_e('4-Digit PIN (Blank = Last 4 digits)', 'woocommerce-whatsapp-commerce'); ?></label>
                                <input type="text" name="pin" maxlength="6" class="swac-desk-input"
                                       placeholder="••••">
                            </div>
                            <div class="swac-desk-field" style="margin-top:10px;">
                                <label><?php esc_html_e('Area / Delivery Route', 'woocommerce-whatsapp-commerce'); ?></label>
                                <input type="text" name="area" class="swac-desk-input"
                                       placeholder="<?php esc_attr_e('e.g. Fancy Bazar', 'woocommerce-whatsapp-commerce'); ?>">
                            </div>
                            <div style="margin-top:16px;">
                                <button type="submit" class="swac-desk-btn swac-desk-btn--primary"
                                        style="width:100%;">
                                    <?php esc_html_e('Save & Activate PIN', 'woocommerce-whatsapp-commerce'); ?>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </section>

        <!-- TAB 4: STORE SETTINGS -->
        <section id="swac-tab-settings" class="swac-desk-tab-content">
            <div style="max-width:820px; margin:0 auto; width:100%;">
                <div class="swac-desk-card" style="width:100%;">
                    <h2 style="margin-top:0; font-size:1.25rem;">
                        ⚙️ <?php esc_html_e('Store Contact & Delivery Settings', 'woocommerce-whatsapp-commerce'); ?>
                    </h2>
                    <p style="font-size:0.85rem; color:#64748b; margin-bottom:20px;">
                        <?php esc_html_e('Configure your public WhatsApp number, store title, delivery fees, and order footer note. Changes reflect instantly.', 'woocommerce-whatsapp-commerce'); ?>
                    </p>

                    <form id="swac-desk-settings-form">
                        <div class="swac-desk-field">
                            <label><strong><?php esc_html_e('WhatsApp Business Phone *', 'woocommerce-whatsapp-commerce'); ?></strong></label>
                            <input type="tel" id="swac-set-phone" name="phone_number" class="swac-desk-input" required
                                   placeholder="<?php esc_attr_e('e.g. 919876543210 (Country code + number)', 'woocommerce-whatsapp-commerce'); ?>">
                            <small style="color:#64748b; font-size:0.75rem; display:block; margin-top:4px;">
                                <?php esc_html_e('E.164 format without "+" or spaces (e.g. 919876543210 for India).', 'woocommerce-whatsapp-commerce'); ?>
                            </small>
                        </div>

                        <div class="swac-desk-field" style="margin-top:14px;">
                            <label><strong><?php esc_html_e('Store Display Name', 'woocommerce-whatsapp-commerce'); ?></strong></label>
                            <input type="text" id="swac-set-store-name" name="store_name" class="swac-desk-input"
                                   placeholder="<?php esc_attr_e('e.g. Mahabir Pharmaceuticals', 'woocommerce-whatsapp-commerce'); ?>">
                        </div>

                        <div class="swac-desk-field" style="margin-top:14px;">
                            <label><strong><?php esc_html_e('City / Territory Focus', 'woocommerce-whatsapp-commerce'); ?></strong></label>
                            <input type="text" id="swac-set-city" name="city_name" class="swac-desk-input"
                                   placeholder="<?php esc_attr_e('e.g. Guwahati', 'woocommerce-whatsapp-commerce'); ?>">
                        </div>

                        <div style="display:flex; gap:12px; margin-top:14px; flex-wrap:wrap;">
                            <div class="swac-desk-field" style="flex:1; min-width:180px;">
                                <label><strong><?php esc_html_e('Free Delivery Above (₹)', 'woocommerce-whatsapp-commerce'); ?></strong></label>
                                <input type="number" step="any" min="0" id="swac-set-free-ship" name="free_shipping_at" class="swac-desk-input"
                                       placeholder="0.00">
                                <small style="color:#64748b; font-size:0.75rem; display:block; margin-top:4px;">
                                    <?php esc_html_e('Orders above this amount get free delivery. 0 = always charge.', 'woocommerce-whatsapp-commerce'); ?>
                                </small>
                            </div>
                            <div class="swac-desk-field" style="flex:1; min-width:180px;">
                                <label><strong><?php esc_html_e('Flat Delivery Fee (₹)', 'woocommerce-whatsapp-commerce'); ?></strong></label>
                                <input type="number" step="any" min="0" id="swac-set-ship-charge" name="shipping_charge" class="swac-desk-input"
                                       placeholder="0.00">
                                <small style="color:#64748b; font-size:0.75rem; display:block; margin-top:4px;">
                                    <?php esc_html_e('Applied when order is below free threshold. 0 = free.', 'woocommerce-whatsapp-commerce'); ?>
                                </small>
                            </div>
                        </div>

                        <div class="swac-desk-field" style="margin-top:14px;">
                            <label><strong><?php esc_html_e('WhatsApp Order Note / Disclaimer', 'woocommerce-whatsapp-commerce'); ?></strong></label>
                            <textarea id="swac-set-disclaimer" name="order_disclaimer" class="swac-desk-input" rows="2"
                                      placeholder="<?php esc_attr_e('⚡ Final bill & stock availability will be confirmed on WhatsApp.', 'woocommerce-whatsapp-commerce'); ?>"></textarea>
                        </div>

                        <div class="swac-desk-field" style="margin-top:14px; padding-top:14px; border-top:1px dashed #cbd5e1;">
                            <label><strong><?php esc_html_e('Change Master PIN (Owner Full Access)', 'woocommerce-whatsapp-commerce'); ?></strong></label>
                            <input type="password" id="swac-set-master-pin" name="master_pin" maxlength="12" class="swac-desk-input"
                                   placeholder="•••• (Leave blank to keep unchanged)">
                            <small style="color:#64748b; font-size:0.75rem; display:block; margin-top:4px;">
                                <?php esc_html_e('Passcode for full owner controls (rates, products, retailers, settings). Minimum 4 digits.', 'woocommerce-whatsapp-commerce'); ?>
                            </small>
                        </div>

                        <div class="swac-desk-field" style="margin-top:14px;">
                            <label><strong><?php esc_html_e('Staff PIN (Warehouse Stock-Only Access)', 'woocommerce-whatsapp-commerce'); ?></strong></label>
                            <input type="password" id="swac-set-staff-pin" name="staff_pin" maxlength="12" class="swac-desk-input"
                                   placeholder="•••• (Leave blank to keep unchanged)">
                            <small style="color:#64748b; font-size:0.75rem; display:block; margin-top:4px;">
                                <?php esc_html_e('Passcode shared with warehouse staff. Staff can strictly toggle In Stock / Out of Stock without seeing or editing rates or settings.', 'woocommerce-whatsapp-commerce'); ?>
                            </small>
                        </div>

                        <div style="margin-top:20px;">
                            <button type="submit" id="swac-settings-save-btn" class="swac-desk-btn swac-desk-btn--primary" style="width:100%; height:44px;">
                                💾 <?php esc_html_e('Save Store Settings', 'woocommerce-whatsapp-commerce'); ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </section>
    </div>
</div>
