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
        <h2 style="margin:0 0 8px 0; font-size:1.3rem;"><?php esc_html_e('Distributor Desk Login', 'stateless-wa-commerce'); ?></h2>
        <p style="font-size:0.85rem; color:#64748b; margin-bottom:20px;"><?php esc_html_e('Enter your Master PIN to access wholesale rate controls and product entry.', 'stateless-wa-commerce'); ?></p>
        <form id="swac-desk-auth-form">
            <input type="password" id="swac-desk-pin-input" class="swac-desk-input" placeholder="••••" maxlength="8"
                   inputmode="numeric" required
                   style="text-align:center; font-size:24px; letter-spacing:4px; height:48px; margin-bottom:16px;">
            <div id="swac-desk-auth-error" class="swac-desk-alert swac-desk-hidden"
                 style="margin-bottom:14px;"></div>
            <button type="submit" class="swac-desk-btn swac-desk-btn--primary"
                    style="width:100%; height:44px;"><?php esc_html_e('Unlock Distributor Desk', 'stateless-wa-commerce'); ?></button>
        </form>
    </div>

    <!-- 2. Main Distributor Desk (Visible when authorized) -->
    <div id="swac-desk-app" class="<?php echo $is_logged_in ? '' : 'swac-desk-hidden'; ?>">
        <!-- Top Header & Tab Navigation -->
        <header class="swac-desk-header">
            <div class="swac-desk-header-left">
                <h1 class="swac-desk-brand-title">
                    📦 <?php esc_html_e('Distributor Desk', 'stateless-wa-commerce'); ?></h1>
                <span class="swac-desk-badge"><?php esc_html_e('Counter Speed', 'stateless-wa-commerce'); ?></span>
            </div>
            <div class="swac-desk-header-right">
                <a href="<?php echo esc_url(wc_get_page_permalink('shop') ?: home_url('/')); ?>"
                   class="swac-desk-btn swac-desk-btn--outline" target="_blank" rel="noopener">
                    <?php esc_html_e('View Live Shop ↗', 'stateless-wa-commerce'); ?>
                </a>
                <button type="button" id="swac-desk-lock-btn" class="swac-desk-btn swac-desk-btn--outline"
                        style="margin-left:8px;" title="<?php esc_attr_e('Lock Distributor Desk', 'stateless-wa-commerce'); ?>">
                    🔒 <?php esc_html_e('Lock', 'stateless-wa-commerce'); ?>
                </button>
            </div>
        </header>

        <!-- Navigation Tabs -->
        <nav class="swac-desk-tabs" role="tablist">
            <button type="button" class="swac-desk-tab-btn swac-desk-tab-btn--active" data-tab="tab-rates"
                    role="tab">
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
            <div class="swac-desk-toolbar" style="flex-wrap:wrap;">
                <input type="text" id="swac-rates-search" class="swac-desk-input" style="flex:1; min-width:200px;"
                       placeholder="<?php esc_attr_e('🔍 Fast search product name or brand...', 'stateless-wa-commerce'); ?>">
                <button type="button" id="swac-rates-refresh" class="swac-desk-btn swac-desk-btn--secondary">
                    🔄 <?php esc_html_e('Refresh', 'stateless-wa-commerce'); ?>
                </button>
                <input type="file" id="swac-rates-import-file" accept=".csv" style="display:none;">
                <button type="button" id="swac-rates-import-btn" class="swac-desk-btn swac-desk-btn--secondary"
                        title="<?php esc_attr_e('Bulk update rates & stock from CSV file', 'stateless-wa-commerce'); ?>">
                    📤 <?php esc_html_e('Import CSV', 'stateless-wa-commerce'); ?>
                </button>
                <button type="button" id="swac-rates-export-btn" class="swac-desk-btn swac-desk-btn--secondary"
                        title="<?php esc_attr_e('Download current rates as spreadsheet CSV', 'stateless-wa-commerce'); ?>">
                    📥 <?php esc_html_e('Export CSV', 'stateless-wa-commerce'); ?>
                </button>
            </div>

            <div class="swac-desk-table-wrapper">
                <table class="swac-desk-table swac-fast-grid">
                    <thead>
                    <tr>
                        <th style="width:28%;"><?php esc_html_e('Item / Brand', 'stateless-wa-commerce'); ?></th>
                        <th style="width:16%;"><?php esc_html_e('Stock', 'stateless-wa-commerce'); ?></th>
                        <th style="width:13%;"><?php esc_html_e('Rate / PTR (₹)', 'stateless-wa-commerce'); ?></th>
                        <th style="width:13%;"><?php esc_html_e('Retail MRP (₹)', 'stateless-wa-commerce'); ?></th>
                        <th style="width:14%;"><?php esc_html_e('Scheme', 'stateless-wa-commerce'); ?></th>
                        <th style="width:8%;"><?php esc_html_e('MOQ', 'stateless-wa-commerce'); ?></th>
                        <th style="width:8%; text-align:right;"><?php esc_html_e('Status', 'stateless-wa-commerce'); ?></th>
                    </tr>
                    </thead>
                    <tbody id="swac-rates-tbody">
                    <tr>
                        <td colspan="7"
                            style="text-align:center; padding:30px; color:#64748b;"><?php esc_html_e('Loading products...', 'stateless-wa-commerce'); ?></td>
                    </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- TAB 2: FAST PRODUCT ENTRY -->
        <section id="swac-tab-add" class="swac-desk-tab-content">
            <div class="swac-desk-card" style="max-width:680px; margin:0 auto;">
                <h2 style="margin:0 0 16px 0; font-size:1.25rem;">
                    ➕ <?php esc_html_e('Add New Wholesale Product', 'stateless-wa-commerce'); ?></h2>
                <form id="swac-add-product-form" enctype="multipart/form-data">
                    <div class="swac-desk-grid-2">
                        <div class="swac-desk-field">
                            <label for="swac-add-name"><strong><?php esc_html_e('Product Title *', 'stateless-wa-commerce'); ?></strong></label>
                            <input type="text" id="swac-add-name" name="name" class="swac-desk-input" required
                                   placeholder="<?php esc_attr_e('e.g. Rin Bar 250g', 'stateless-wa-commerce'); ?>">
                        </div>
                        <div class="swac-desk-field">
                            <label for="swac-add-brand"><?php esc_html_e('Brand', 'stateless-wa-commerce'); ?></label>
                            <input type="text" id="swac-add-brand" name="brand" class="swac-desk-input"
                                   list="swac-brands-list"
                                   placeholder="<?php esc_attr_e('e.g. HUL / ITC / Nestle', 'stateless-wa-commerce'); ?>">
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
                            <input type="number" id="swac-add-mrp" name="mrp" class="swac-desk-input" step="0.01"
                                   min="0" required placeholder="30.00">
                        </div>
                        <div class="swac-desk-field">
                            <label for="swac-add-ptr"><strong><?php esc_html_e('Rate / PTR (₹) *', 'stateless-wa-commerce'); ?></strong></label>
                            <input type="number" id="swac-add-ptr" name="ptr" class="swac-desk-input" step="0.01"
                                   min="0" required placeholder="24.50">
                        </div>
                        <div class="swac-desk-field">
                            <label for="swac-add-carton"><?php esc_html_e('Carton Pack', 'stateless-wa-commerce'); ?></label>
                            <input type="number" id="swac-add-carton" name="carton_pack" class="swac-desk-input"
                                   step="1" min="0"
                                   placeholder="<?php esc_attr_e('e.g. 144 pcs', 'stateless-wa-commerce'); ?>">
                        </div>
                    </div>

                    <!-- Scheme Selector -->
                    <div class="swac-desk-field" style="margin-top:16px;">
                        <label><strong><?php esc_html_e('Active Promotional Scheme', 'stateless-wa-commerce'); ?></strong></label>
                        <div style="display:flex; gap:16px; flex-wrap:wrap; margin-top:8px;">
                            <label><input type="radio" name="scheme_type" value="none"
                                          checked> <?php esc_html_e('None', 'stateless-wa-commerce'); ?></label>
                            <label><input type="radio" name="scheme_type"
                                          value="free_goods"> <?php esc_html_e('Buy X Get Y Free', 'stateless-wa-commerce'); ?>
                            </label>
                            <label><input type="radio" name="scheme_type"
                                          value="tier_discount"> <?php esc_html_e('Volume Slabs', 'stateless-wa-commerce'); ?>
                            </label>
                            <label><input type="radio" name="scheme_type"
                                          value="cash_discount"> <?php esc_html_e('Cash Discount (CD %)', 'stateless-wa-commerce'); ?>
                            </label>
                            <label><input type="radio" name="scheme_type"
                                          value="custom"> <?php esc_html_e('Custom Text', 'stateless-wa-commerce'); ?>
                            </label>
                        </div>
                    </div>

                    <!-- Dynamic Scheme Fields -->
                    <div id="swac-scheme-subfields" class="swac-desk-field swac-desk-hidden"
                         style="margin-top:10px; background:#f8fafc; padding:12px; border-radius:6px; border:1px solid #e2e8f0;">
                        <div id="swac-sub-free_goods" class="swac-desk-grid-2 swac-desk-hidden">
                            <div><label><?php esc_html_e('Buy Qty (X)', 'stateless-wa-commerce'); ?></label><input
                                        type="number" name="scheme_buy" class="swac-desk-input" value="12"></div>
                            <div><label><?php esc_html_e('Free Qty (Y)', 'stateless-wa-commerce'); ?></label><input
                                        type="number" name="scheme_free" class="swac-desk-input" value="1"></div>
                        </div>
                        <div id="swac-sub-tier_discount" class="swac-desk-hidden">
                            <label><?php esc_html_e('Slabs (e.g. 10:2%, 25:5%)', 'stateless-wa-commerce'); ?></label>
                            <input type="text" name="scheme_tiers" class="swac-desk-input"
                                   placeholder="10:2%, 25:5%">
                        </div>
                        <div id="swac-sub-cash_discount" class="swac-desk-hidden">
                            <label><?php esc_html_e('CD % (e.g. 2)', 'stateless-wa-commerce'); ?></label>
                            <input type="number" name="scheme_cd" class="swac-desk-input" step="0.1" value="2">
                        </div>
                        <div id="swac-sub-custom" class="swac-desk-hidden">
                            <label><?php esc_html_e('Custom Scheme Badge Text', 'stateless-wa-commerce'); ?></label>
                            <input type="text" name="scheme_custom" class="swac-desk-input"
                                   placeholder="<?php esc_attr_e('e.g. Extra ₹20 off per box', 'stateless-wa-commerce'); ?>">
                        </div>
                    </div>

                    <!-- Photo Upload (Supports Phone Camera / Gallery) -->
                    <div class="swac-desk-field" style="margin-top:16px;">
                        <label for="swac-add-image"><strong><?php esc_html_e('Product Photo (Camera / Gallery)', 'stateless-wa-commerce'); ?></strong></label>
                        <input type="file" id="swac-add-image" name="image" accept="image/*" class="swac-desk-input"
                               style="padding:8px;">
                        <div id="swac-image-preview" style="margin-top:8px;"></div>
                    </div>

                    <div style="margin-top:24px;">
                        <button type="submit" id="swac-add-submit-btn" class="swac-desk-btn swac-desk-btn--primary"
                                style="width:100%; height:46px; font-size:15px;">
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
                        <h2 style="margin:0; font-size:1.15rem;">
                            🏪 <?php esc_html_e('Approved Accounts', 'stateless-wa-commerce'); ?></h2>
                        <button type="button" id="swac-retailers-refresh"
                                class="swac-desk-btn swac-desk-btn--secondary">
                            🔄 <?php esc_html_e('Refresh', 'stateless-wa-commerce'); ?></button>
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
                            <tr>
                                <td colspan="6"
                                    style="text-align:center; padding:20px; color:#64748b;"><?php esc_html_e('Loading retailers...', 'stateless-wa-commerce'); ?></td>
                            </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Add Retailer Card -->
                <div style="flex:1; min-width:280px;">
                    <div class="swac-desk-card">
                        <h3 style="margin-top:0; font-size:1.1rem;">
                            ➕ <?php esc_html_e('Approve New Retailer', 'stateless-wa-commerce'); ?></h3>
                        <form id="swac-add-retailer-form">
                            <div class="swac-desk-field">
                                <label><strong><?php esc_html_e('Shop / Store Name *', 'stateless-wa-commerce'); ?></strong></label>
                                <input type="text" name="store_name" class="swac-desk-input" required
                                       placeholder="<?php esc_attr_e('e.g. Shree Ganesh Stores', 'stateless-wa-commerce'); ?>">
                            </div>
                            <div class="swac-desk-field" style="margin-top:10px;">
                                <label><strong><?php esc_html_e('Mobile Number *', 'stateless-wa-commerce'); ?></strong></label>
                                <input type="tel" name="phone" class="swac-desk-input" required
                                       placeholder="<?php esc_attr_e('10-digit number', 'stateless-wa-commerce'); ?>">
                            </div>
                            <div class="swac-desk-field" style="margin-top:10px;">
                                <label><?php esc_html_e('4-Digit PIN (Blank = Last 4 digits)', 'stateless-wa-commerce'); ?></label>
                                <input type="text" name="pin" maxlength="6" class="swac-desk-input"
                                       placeholder="••••">
                            </div>
                            <div class="swac-desk-field" style="margin-top:10px;">
                                <label><?php esc_html_e('Area / Delivery Route', 'stateless-wa-commerce'); ?></label>
                                <input type="text" name="area" class="swac-desk-input"
                                       placeholder="<?php esc_attr_e('e.g. Fancy Bazar', 'stateless-wa-commerce'); ?>">
                            </div>
                            <div style="margin-top:16px;">
                                <button type="submit" class="swac-desk-btn swac-desk-btn--primary"
                                        style="width:100%;">
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
