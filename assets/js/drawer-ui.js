/**
 * 🛒 CLIENT MODULE: CART DRAWER & ADDRESS UI ENGINE
 *
 * Encapsulates the slide-out order drawer presentation layer:
 * - Slide-out drawer animation & keyboard focus trap
 * - Cart item quantity management (+, -, delete)
 * - Customer address state machine (preview / edit mode toggle)
 * - Pincode serviceability gating & inline status banners
 * - Dynamic loyalty reward nudge cards & tier thresholds
 * - Order totals breakdown & WhatsApp submit button state
 *
 * @package StatelessWaCommerce
 */
(function () {
    'use strict';

    const safeWindow = (typeof window !== 'undefined') ? window : (typeof globalThis !== 'undefined' ? globalThis : {});
    const safeDoc = (typeof document !== 'undefined') ? document : null;

    let addressMode = null; // 'preview' or 'edit'
    let addressCollapsed = false;
    let addressDraft = {
        name: '',
        address: '',
        landmark: '',
        area: '',
        pincode: '',
        saveDefault: true
    };

    let loyaltyChoice = null;

    function getCartApi() {
        return safeWindow.swacCart || null;
    }

    function getConfig() {
        const api = getCartApi();
        return (api && api.CONFIG) || safeWindow.swacCommerce || safeWindow.swacConfig || {};
    }

    function escHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function fmtPrice(n) {
        const cfg = getConfig();
        const sym = cfg.currencySymbol ? cfg.currencySymbol + ' ' : '';
        return sym + Number(n).toLocaleString(undefined, {
            minimumFractionDigits: 0,
            maximumFractionDigits: 2
        });
    }
    const fmtInr = fmtPrice;

    function getCustomerDetails() {
        const api = getCartApi();
        return api && typeof api.getCustomerDetails === 'function' ? api.getCustomerDetails() : null;
    }

    function saveCustomerDetails(details) {
        const api = getCartApi();
        return api && typeof api.saveCustomerDetails === 'function' ? api.saveCustomerDetails(details) : details;
    }

    function getCart() {
        const api = getCartApi();
        return api && typeof api.getCart === 'function' ? api.getCart() : [];
    }

    function saveCart(cart) {
        const api = getCartApi();
        if (api && typeof api.saveCart === 'function') {
            api.saveCart(cart);
        }
    }

    function removeFromCart(id) {
        const api = getCartApi();
        if (api && typeof api.removeFromCart === 'function') {
            api.removeFromCart(id);
        }
    }

    function clearCart() {
        const api = getCartApi();
        if (api && typeof api.clearCart === 'function') {
            api.clearCart();
        }
    }

    function showToast(msg) {
        const api = getCartApi();
        if (api && typeof api.showToast === 'function') {
            api.showToast(msg);
        } else if (typeof safeWindow.swacShowToast === 'function') {
            safeWindow.swacShowToast(msg);
        }
    }

    function computeCartTotals(cart, pincode) {
        const api = getCartApi();
        if (api && typeof api.computeCartTotals === 'function') {
            return api.computeCartTotals(cart, pincode);
        }
        if (typeof safeWindow.swacCartMath !== 'undefined' && typeof safeWindow.swacCartMath.computeCartTotals === 'function') {
            return safeWindow.swacCartMath.computeCartTotals(cart, pincode, {
                config: getConfig(),
                loyaltyChoice: loyaltyChoice,
            });
        }
        return {totalMrp: 0, saleTotal: 0, youSave: 0, savePct: 0, shipping: 0, amountPayable: 0, isAlwaysFree: false};
    }

    function evaluateDeliveryZone(pincode) {
        const api = getCartApi();
        if (api && typeof api.evaluateDeliveryZone === 'function') {
            return api.evaluateDeliveryZone(pincode);
        }
        if (typeof safeWindow.swacDelivery !== 'undefined' && typeof safeWindow.swacDelivery.evaluateDeliveryZone === 'function') {
            return safeWindow.swacDelivery.evaluateDeliveryZone(pincode, safeWindow.swacZones, getConfig());
        }
        return null;
    }

    function isAlwaysFreePincode(pincode) {
        const api = getCartApi();
        if (api && typeof api.isAlwaysFreePincode === 'function') {
            return api.isAlwaysFreePincode(pincode);
        }
        if (typeof safeWindow.swacDelivery !== 'undefined' && typeof safeWindow.swacDelivery.isAlwaysFreePincode === 'function') {
            return safeWindow.swacDelivery.isAlwaysFreePincode(pincode, safeWindow.swacZones, getConfig());
        }
        return false;
    }

    function isLoyaltyActive(saleTotal) {
        const api = getCartApi();
        if (api && typeof api.isLoyaltyActive === 'function') {
            return api.isLoyaltyActive(saleTotal);
        }
        if (typeof safeWindow.swacCartMath !== 'undefined' && typeof safeWindow.swacCartMath.isLoyaltyActive === 'function') {
            return safeWindow.swacCartMath.isLoyaltyActive(saleTotal, getConfig());
        }
        return false;
    }

    function getSavedRetailer() {
        const api = getCartApi();
        if (api && typeof api.getSavedRetailer === 'function') {
            return api.getSavedRetailer();
        }
        try {
            const raw = localStorage.getItem('swac_b2b_retailer');
            return raw ? JSON.parse(raw) : null;
        } catch (e) {
            return null;
        }
    }

    function hydrateDeliveryNotice() {
        const api = getCartApi();
        if (api && typeof api.hydrateDeliveryNotice === 'function') {
            api.hydrateDeliveryNotice();
        }
    }

    function initAddressState() {
        const saved = getCustomerDetails();
        const zoneRes = (saved && saved.pincode) ? evaluateDeliveryZone(saved.pincode) : null;
        const isValid = Boolean(saved && saved.name && saved.address && saved.pincode && zoneRes && zoneRes.allowOrder);

        if (isValid) {
            addressMode = 'preview';
            addressCollapsed = false;
            addressDraft = {
                name: saved.name,
                address: saved.address,
                landmark: saved.landmark || '',
                area: saved.area || '',
                pincode: saved.pincode || '',
                saveDefault: true
            };
        } else {
            addressMode = 'edit';
            addressCollapsed = !(saved && (saved.name || saved.address || saved.pincode));
            addressDraft = {
                name: saved?.name || '',
                address: saved?.address || '',
                landmark: saved?.landmark || '',
                area: saved?.area || '',
                pincode: saved?.pincode || '',
                saveDefault: true
            };
        }
    }

    function showClearConfirm() {
        if (!safeDoc) return;
        const c = safeDoc.getElementById('swac-clear-confirm');
        if (c) c.style.display = 'block';
    }

    function hideClearConfirm() {
        if (!safeDoc) return;
        const c = safeDoc.getElementById('swac-clear-confirm');
        if (c) c.style.display = 'none';
    }

    function openDrawer() {
        if (!safeDoc) return;
        const drawer = safeDoc.getElementById('swac-drawer');
        const overlay = safeDoc.getElementById('swac-drawer-overlay');
        if (!drawer) return;
        drawer.removeAttribute('inert');
        drawer.setAttribute('aria-hidden', 'false');
        drawer.style.visibility = 'visible';
        drawer.classList.add('swac-drawer--open');
        if (overlay) overlay.style.display = 'block';
        hideClearConfirm();
        renderDrawerItems();
        hydrateDeliveryNotice();

        setTimeout(() => {
            safeDoc.getElementById('swac-drawer-close')?.focus();
        }, 50);
    }

    function closeDrawer() {
        if (!safeDoc) return;
        const drawer = safeDoc.getElementById('swac-drawer');
        const overlay = safeDoc.getElementById('swac-drawer-overlay');
        if (!drawer) return;
        drawer.classList.remove('swac-drawer--open');
        drawer.setAttribute('aria-hidden', 'true');
        drawer.setAttribute('inert', '');
        if (overlay) overlay.style.display = 'none';
        hideClearConfirm();

        setTimeout(() => {
            if (!drawer.classList.contains('swac-drawer--open')) {
                drawer.style.visibility = 'hidden';
            }
        }, 300);
    }

    function ensureDrawerBodyStructure(body) {
        if (!safeDoc || !body) return;
        if (!body.querySelector('#swac-items-list')) {
            body.innerHTML = `
                <div id="swac-items-list" class="swac-items-list"></div>
                <div id="swac-loyalty-section"></div>
                <div id="swac-totals-section"></div>
                <div id="swac-address-section" class="swac-address-section"></div>
            `;
        }
    }

    function renderDrawerItems() {
        if (!safeDoc) return;
        const body = safeDoc.getElementById('swac-drawer-body');
        if (!body) return;

        const cart = getCart();

        if (cart.length === 0) {
            body.innerHTML = '<p class="swac-drawer-empty">Your order list is empty.</p>';
            updateSendButton();
            hideClearConfirm();
            return;
        }

        ensureDrawerBodyStructure(body);

        const CONFIG = getConfig();
        const itemsContainer = safeDoc.getElementById('swac-items-list');
        if (itemsContainer) {
            itemsContainer.innerHTML = cart.map(item => {
                const isMax = item.qty >= CONFIG.maxQtyPerItem;
                return `
                <div class="swac-drawer-item" data-id="${escHtml(String(item.id))}">
                    <div class="swac-item-info">
                        <span class="swac-item-name">${escHtml(item.name)}</span>
                        ${item.price ? `<span class="swac-item-price">${escHtml(item.price)}</span>` : ''}
                        ${item.schemeBadge ? `<span class="swac-drawer-scheme-tag">🎁 ${escHtml(item.schemeBadge)}</span>` : ''}
                        ${item.cartonPack > 0 ? `<span class="swac-drawer-carton-note">📦 1 Ctn = ${item.cartonPack} pcs</span>` : ''}
                    </div>
                    <div class="swac-item-controls">
                        <button class="swac-qty-btn swac-qty-minus" data-id="${escHtml(String(item.id))}" aria-label="Decrease quantity of ${escHtml(item.name)}">−</button>
                        <span class="swac-item-qty" aria-label="Current quantity: ${item.qty}">${item.qty}</span>
                        <button class="swac-qty-btn swac-qty-plus ${isMax ? 'swac-qty-btn--disabled' : ''}" data-id="${escHtml(String(item.id))}" ${isMax ? 'disabled title="Max units reached"' : ''} aria-label="Increase quantity of ${escHtml(item.name)}">+</button>
                        <button class="swac-remove-btn" data-id="${escHtml(String(item.id))}" aria-label="Remove ${escHtml(item.name)} from order">✕</button>
                    </div>
                </div>
            `;
            }).join('');
        }

        refreshLoyaltySection();
        renderAddressSection();
        updateSendButton();
    }

    function refreshLoyaltySection() {
        const section = safeDoc.getElementById('swac-loyalty-section');
        const totalsSection = safeDoc.getElementById('swac-totals-section');
        if (!section) return;
        section.innerHTML = '';
        const cart = getCart();
        const totals = computeCartTotals(cart);
        renderLoyaltyUI(section, cart, totals);
        if (totalsSection) {
            totalsSection.innerHTML = '';
            renderTotals(totalsSection, cart, totals);
        }
        updateSendButton();
    }

    function renderAddressSection(force, justSaved) {
        if (!safeDoc) return;
        const container = safeDoc.getElementById('swac-address-section');
        if (!container) return;

        const retailer = typeof getSavedRetailer === 'function' ? getSavedRetailer() : null;
        if (retailer && retailer.store_name) {
            container.innerHTML = `
                <div class="swac-address-card swac-b2b-route-card" id="swac-address-card">
                    <div class="swac-address-card-header">
                        <span class="swac-address-card-badge">
                            🚚 Wholesale Route Dispatch
                        </span>
                        <span style="font-size:11px; font-weight:700; color:#15803d;">✓ Route Verified</span>
                    </div>
                    <div class="swac-address-card-body" style="padding:10px 12px; font-size:12px; line-height:1.5;">
                        <div style="font-weight:700; color:#0f172a; font-size:13px;">🏪 ${escHtml(retailer.store_name)}</div>
                        <div style="color:#475569;">📍 Route / Area: <strong>${escHtml(retailer.area || 'Wholesale Delivery Route')}</strong></div>
                        <div style="color:#475569;">📞 Mobile: <code>${escHtml(retailer.phone)}</code></div>
                        <div style="margin-top:8px;">
                            <input type="text" id="swac-b2b-remarks" class="swac-addr-input" style="font-size:12px; height:34px; width:100%; box-sizing:border-box;" placeholder="Optional dispatch note (e.g. transport builty, carton mark)...">
                        </div>
                    </div>
                </div>
            `;
            updateSendButton();
            return;
        }

        const saved = getCustomerDetails();
        const isCollapsed = addressCollapsed;
        const currentDraft = addressDraft || {
            name: saved?.name || '',
            address: saved?.address || '',
            landmark: saved?.landmark || '',
            area: saved?.area || '',
            pincode: saved?.pincode || '',
            saveDefault: true
        };

        if (addressMode === 'preview' && saved && saved.name && saved.address && saved.pincode) {
            container.innerHTML = `
                <div class="swac-address-card" id="swac-address-card">
                    <div class="swac-address-card-header">
                        <span class="swac-address-card-badge">
                            ${saved.name.includes('(Retailer)') ? '🏪 B2B Verified' : '📍 Delivery Address'}
                        </span>
                        <button type="button" class="swac-address-btn swac-address-btn--text" id="swac-addr-edit-btn" aria-label="Change delivery address">Change</button>
                    </div>
                    <div class="swac-address-card-body">
                        <strong>${escHtml(saved.name)}</strong>
                        <div>${escHtml(saved.address)}</div>
                        ${saved.landmark ? `<div>Landmark: ${escHtml(saved.landmark)}</div>` : ''}
                        ${saved.area ? `<div>Area: ${escHtml(saved.area)}</div>` : ''}
                        <div>Postal Code: <strong>${escHtml(saved.pincode)}</strong></div>
                    </div>
                    ${justSaved ? '<div class="swac-address-card-toast">✓ Address saved</div>' : ''}
                </div>
            `;

            safeDoc.getElementById('swac-addr-edit-btn')?.addEventListener('click', () => {
                addressMode = 'edit';
                addressDraft = {...saved, saveDefault: true};
                renderAddressSection(true);
            });
            updateSendButton();
            return;
        }

        // EDIT MODE
        container.innerHTML = `
            <div class="swac-address-card" id="swac-address-card">
                <button type="button" class="swac-address-accordion-trigger ${isCollapsed ? 'swac-address-accordion-trigger--collapsed' : ''}" id="swac-addr-collapse-btn" aria-expanded="${!isCollapsed}" aria-controls="swac-addr-form-wrapper">
                    <span>${isCollapsed ? '+ Enter Delivery Address' : '📍 Delivery Address'}</span>
                    <span class="swac-accordion-icon" aria-hidden="true">${isCollapsed ? '▼' : '▲'}</span>
                </button>
                <div id="swac-addr-form-wrapper" class="${isCollapsed ? 'swac-address-form-wrapper--collapsed' : ''}" ${isCollapsed ? 'hidden' : ''}>
                    <div class="swac-address-card-body">
                        <div class="swac-addr-field">
                            <label for="swac-addr-name">Your Name <span class="swac-required">*</span></label>
                            <input type="text" id="swac-addr-name" class="swac-addr-input" value="${escHtml(currentDraft.name)}" placeholder="e.g. John Doe" autocomplete="name" required>
                        </div>
                        <div class="swac-addr-field">
                            <label for="swac-addr-address">House / Flat / Street <span class="swac-required">*</span></label>
                            <textarea id="swac-addr-address" class="swac-addr-input swac-addr-textarea" rows="2" placeholder="House/Flat No., Building, Street" autocomplete="street-address" required>${escHtml(currentDraft.address)}</textarea>
                        </div>
                        <div class="swac-addr-field">
                            <label for="swac-addr-landmark">Landmark <span class="swac-optional">(optional)</span></label>
                            <input type="text" id="swac-addr-landmark" class="swac-addr-input" value="${escHtml(currentDraft.landmark)}" placeholder="Near Temple / School / Metro">
                        </div>
                        <div class="swac-addr-field">
                            <label for="swac-addr-area">Area / Locality <span class="swac-optional">(optional)</span></label>
                            <input type="text" id="swac-addr-area" class="swac-addr-input" value="${escHtml(currentDraft.area)}" placeholder="e.g. Downtown / West End">
                        </div>
                        <div class="swac-addr-field">
                            <label for="swac-addr-pincode">Postal Code <span class="swac-required">*</span></label>
                            <input type="text" id="swac-addr-pincode" class="swac-addr-input" value="${escHtml(currentDraft.pincode)}" placeholder="6-digit postal code" inputmode="numeric" maxlength="10" autocomplete="postal-code" required>
                            <div id="swac-pincode-status" class="swac-pincode-status" role="status" aria-live="polite"></div>
                        </div>
                        <div class="swac-addr-field swac-addr-field--checkbox">
                            <label>
                                <input type="checkbox" id="swac-addr-save-default" ${currentDraft.saveDefault ? 'checked' : ''}>
                                Save as default delivery address
                            </label>
                        </div>
                        ${saved && saved.name ? `
                            <div class="swac-addr-actions">
                                <button type="button" class="swac-address-btn swac-address-btn--secondary" id="swac-addr-cancel-btn">Cancel</button>
                            </div>
                        ` : ''}
                    </div>
                </div>
            </div>
        `;

        // Bind events for edit form
        safeDoc.getElementById('swac-addr-collapse-btn')?.addEventListener('click', () => {
            addressCollapsed = !addressCollapsed;
            renderAddressSection(true);
        });

        safeDoc.getElementById('swac-addr-cancel-btn')?.addEventListener('click', () => {
            addressMode = 'preview';
            addressCollapsed = false;
            renderAddressSection(true);
        });

        const syncField = (id, key) => {
            safeDoc.getElementById(id)?.addEventListener('input', (e) => {
                addressDraft[key] = e.target.value;
            });
        };
        syncField('swac-addr-name', 'name');
        syncField('swac-addr-address', 'address');
        syncField('swac-addr-landmark', 'landmark');
        syncField('swac-addr-area', 'area');

        safeDoc.getElementById('swac-addr-save-default')?.addEventListener('change', (e) => {
            addressDraft.saveDefault = e.target.checked;
        });

        const pinInput = safeDoc.getElementById('swac-addr-pincode');
        if (pinInput) {
            pinInput.addEventListener('input', (e) => {
                addressDraft.pincode = e.target.value;
                applyPincodeGating(e.target.value, false);
            });
            pinInput.addEventListener('blur', (e) => {
                applyPincodeGating(e.target.value, true);
            });
            if (currentDraft.pincode) {
                applyPincodeGating(currentDraft.pincode, false);
            }
        }
        updateSendButton();
    }

    function applyPincodeGating(pincodeVal, isBlur) {
        if (!safeDoc) return;
        const cleanPin = String(pincodeVal || '').replace(/\D/g, '');
        const statusEl = safeDoc.getElementById('swac-pincode-status');
        const pinInput = safeDoc.getElementById('swac-addr-pincode');

        if (!statusEl || !pinInput) return;

        if (!cleanPin) {
            statusEl.textContent = '';
            statusEl.className = 'swac-pincode-status';
            pinInput.classList.remove('swac-addr-input--invalid', 'swac-addr-input--valid');
            updateSendButton();
            return;
        }

        const isFree = isAlwaysFreePincode(cleanPin);
        const zoneRes = evaluateDeliveryZone(cleanPin);

        if (isFree) {
            statusEl.textContent = '🎉 Free Delivery Zone: Delivery is fully waived for this postal code!';
            statusEl.className = 'swac-pincode-status swac-pin-success';
            pinInput.classList.remove('swac-addr-input--invalid');
            pinInput.classList.add('swac-addr-input--valid');
            refreshLoyaltySection();
            updateSendButton();
            return;
        }

        if (zoneRes) {
            statusEl.textContent = zoneRes.message;
            statusEl.className = 'swac-pincode-status ' + zoneRes.statusClass;
            if (zoneRes.allowOrder) {
                pinInput.classList.remove('swac-addr-input--invalid');
                pinInput.classList.add('swac-addr-input--valid');
            } else {
                pinInput.classList.remove('swac-addr-input--valid');
                pinInput.classList.add('swac-addr-input--invalid');
            }
        } else if (isBlur && cleanPin.length < 6) {
            statusEl.textContent = 'Please enter a valid 6-digit postal code.';
            statusEl.className = 'swac-pincode-status swac-pin-error';
            pinInput.classList.add('swac-addr-input--invalid');
        } else {
            statusEl.textContent = '';
            statusEl.className = 'swac-pincode-status';
            pinInput.classList.remove('swac-addr-input--invalid', 'swac-addr-input--valid');
        }

        refreshLoyaltySection();
        updateSendButton();
    }

    function getCurrentPincode() {
        if (!safeDoc) {
            const saved = getCustomerDetails();
            return saved && saved.pincode ? String(saved.pincode).replace(/\D/g, '') : '';
        }
        const pinInput = safeDoc.getElementById('swac-addr-pincode');
        if (pinInput && pinInput.value) {
            return String(pinInput.value).replace(/\D/g, '');
        }
        if (addressDraft && addressDraft.pincode) {
            return String(addressDraft.pincode).replace(/\D/g, '');
        }
        const saved = getCustomerDetails();
        return saved && saved.pincode ? String(saved.pincode).replace(/\D/g, '') : '';
    }

    function renderLoyaltyUI(container, cart, totals, pincode) {
        if (!container) return;
        const code = pincode !== undefined ? pincode : getCurrentPincode();
        const isAlwaysFree = Boolean((totals && totals.isAlwaysFree) || isAlwaysFreePincode(code));
        const CONFIG = getConfig();

        // Always-Free Postal Code Badge
        if (isAlwaysFree && code) {
            const freeZoneCard = safeDoc.createElement('div');
            freeZoneCard.className = 'swac-loyalty-card swac-loyalty-card--free-zone';
            freeZoneCard.innerHTML = `
                <div class="swac-loyalty-body">
                    &#127881; <strong>Free Delivery Unlocked!</strong>
                    <div style="font-size:11px;color:#047857;margin-top:3px;">
                        Special free delivery zone applied for your postal code (${escHtml(code)}).
                    </div>
                </div>
            `;
            container.appendChild(freeZoneCard);
        }

        const freeAt = CONFIG.freeShippingAt;
        const shipCharge = CONFIG.shippingCharge;

        if (!isAlwaysFree && freeAt > 0 && shipCharge > 0 && totals.saleTotal < freeAt) {
            loyaltyChoice = null;
            const shipGap = Math.ceil(freeAt - totals.saleTotal);
            const card = safeDoc.createElement('div');
            card.className = 'swac-loyalty-card swac-loyalty-card--shipping';
            card.innerHTML = `
                <div class="swac-loyalty-body">
                    &#128666; Add <strong>&#8377;${shipGap}</strong> more to get <strong>FREE Delivery</strong>!
                    <div style="font-size:11px;color:#0369a1;margin-top:3px;">
                        Standard delivery fee is &#8377;${shipCharge} for orders under &#8377;${freeAt}.
                    </div>
                </div>
            `;
            container.appendChild(card);
            return;
        }

        const loy = CONFIG.loyalty;
        if (!loy || !loy.enabled || !loy.itemLabel || loy.threshold <= 0) {
            loyaltyChoice = null;
            return;
        }

        const threshold = loy.threshold;
        const itemLabel = loy.itemLabel;
        const itemWorth = loy.itemWorth;
        const itemPrice = loy.itemPrice;

        const card = safeDoc.createElement('div');

        if (totals.saleTotal < threshold) {
            loyaltyChoice = null;
            const gap = Math.ceil(threshold - totals.saleTotal);
            card.className = 'swac-loyalty-card swac-loyalty-card--nudge';
            card.innerHTML = `
                <div class="swac-loyalty-body">
                    &#128138; Add <strong>&#8377;${gap}</strong> more to unlock
                    <strong>${escHtml(itemLabel)}</strong>
                    worth &#8377;${itemWorth} for just &#8377;${itemPrice}!
                </div>
            `;
            container.appendChild(card);
        } else if (loyaltyChoice === 'yes') {
            card.className = 'swac-loyalty-card swac-loyalty-card--confirmed';
            card.innerHTML = `
                <div class="swac-loyalty-heading">&#127881; Loyalty offer added.</div>
                <div class="swac-loyalty-body">
                    <strong>${escHtml(itemLabel)}</strong> added for &#8377;${itemPrice}.
                </div>
                <div class="swac-loyalty-actions">
                    <button id="swac-loyalty-remove" class="swac-loyalty-btn swac-loyalty-btn--remove" aria-label="Remove loyalty offer">Remove</button>
                </div>
            `;
            container.appendChild(card);

            safeDoc.getElementById('swac-loyalty-remove')?.addEventListener('click', function () {
                loyaltyChoice = null;
                refreshLoyaltySection();
            });
        } else {
            card.className = 'swac-loyalty-card swac-loyalty-card--offer';
            card.innerHTML = `
                <div class="swac-loyalty-heading">&#127873; Loyalty Offer Unlocked!</div>
                <div class="swac-loyalty-body">
                    Add <strong>${escHtml(itemLabel)}</strong>
                    worth ${fmtInr(itemWorth)} for just ${fmtInr(itemPrice)}?
                </div>
                <div class="swac-loyalty-actions">
                    <button id="swac-loyalty-yes" class="swac-loyalty-btn swac-loyalty-btn--yes" aria-label="Accept loyalty offer">Yes, add it!</button>
                </div>
            `;
            container.appendChild(card);

            safeDoc.getElementById('swac-loyalty-yes')?.addEventListener('click', function () {
                loyaltyChoice = 'yes';
                refreshLoyaltySection();
            });
        }
    }

    function renderTotals(container, cart, totals) {
        const {totalMrp, saleTotal, youSave, savePct, shipping, amountPayable} = totals;
        const shipLabel = shipping === 0 ? '<span class="swac-totals-free">FREE</span>' : fmtInr(shipping);

        const block = safeDoc.createElement('div');
        block.className = 'swac-totals-block';
        block.innerHTML = `
            <div class="swac-totals-row">
                <span>Total MRP:</span>
                <span>${fmtInr(totalMrp)}</span>
            </div>
            <div class="swac-totals-row swac-totals-savings">
                <span>Discount / Savings:</span>
                <span>-${fmtInr(youSave)} (${savePct}% off)</span>
            </div>
            <div class="swac-totals-row">
                <span>Shipping Fee:</span>
                <span>${shipLabel}</span>
            </div>
            <div class="swac-totals-row swac-totals-grand">
                <span>To Pay:</span>
                <span>${fmtInr(amountPayable)}</span>
            </div>
        `;
        container.appendChild(block);
    }

    function updateSendButton() {
        if (!safeDoc) return;
        const btn = safeDoc.getElementById('swac-send-btn');
        if (!btn) return;

        const cart = getCart();
        const CONFIG = getConfig();

        if (cart.length === 0) {
            btn.disabled = true;
            btn.title = 'Add items to your order first';
            btn.classList.add('swac-btn--disabled');
            return;
        }

        if (!CONFIG.number && !CONFIG.baseUrl) {
            btn.disabled = true;
            btn.title = 'Store WhatsApp number not configured yet';
            btn.classList.add('swac-btn--disabled');
            return;
        }

        const retailer = typeof getSavedRetailer === 'function' ? getSavedRetailer() : null;
        if (retailer && retailer.store_name) {
            btn.disabled = false;
            btn.title = 'Send order via WhatsApp';
            btn.classList.remove('swac-btn--disabled');
            return;
        }

        const curPin = getCurrentPincode();
        if (curPin) {
            const isFree = isAlwaysFreePincode(curPin);
            const zoneRes = evaluateDeliveryZone(curPin);
            if (!isFree && zoneRes && !zoneRes.allowOrder) {
                btn.disabled = true;
                btn.title = 'Delivery is not available to this postal code';
                btn.classList.add('swac-btn--disabled');
                return;
            }
        }

        btn.disabled = false;
        btn.title = 'Send order via WhatsApp';
        btn.classList.remove('swac-btn--disabled');
    }

    function runPincodeValidation(pincodeInput) {
        const pinVal = (pincodeInput?.value || '').trim();
        const cleanPin = pinVal.replace(/\D/g, '');

        if (!pinVal) {
            pincodeInput?.classList.add('swac-addr-input--invalid');
            return {isValid: false, cleanPin: ''};
        }

        const isFree = isAlwaysFreePincode(cleanPin);
        const zoneRes = evaluateDeliveryZone(cleanPin);

        if (!isFree && zoneRes && !zoneRes.allowOrder) {
            pincodeInput?.classList.add('swac-addr-input--invalid');
            return {isValid: false, cleanPin};
        }

        pincodeInput?.classList.remove('swac-addr-input--invalid');
        return {isValid: true, cleanPin};
    }

    function validateAddress() {
        if (!safeDoc) return getCustomerDetails();
        const retailer = typeof getSavedRetailer === 'function' ? getSavedRetailer() : null;
        if (retailer && retailer.store_name) {
            const remarksInput = safeDoc.getElementById('swac-b2b-remarks');
            return {
                name: retailer.store_name + (retailer.owner_name ? ' (' + retailer.owner_name + ')' : ''),
                address: retailer.area || 'Wholesale Registered Route',
                landmark: '',
                area: retailer.area || '',
                pincode: '',
                isB2B: true,
                remarks: remarksInput ? remarksInput.value.trim() : ''
            };
        }

        if (addressMode === 'preview') {
            const saved = getCustomerDetails();
            if (saved && saved.name && saved.address && saved.pincode) {
                const isFree = isAlwaysFreePincode(saved.pincode);
                const zoneRes = evaluateDeliveryZone(saved.pincode);
                if (isFree || !zoneRes || zoneRes.allowOrder) {
                    return saved;
                }
            }
            addressMode = 'edit';
            addressCollapsed = false;
            renderAddressSection(true);
        }

        const nameInput = safeDoc.getElementById('swac-addr-name');
        const addrInput = safeDoc.getElementById('swac-addr-address');
        const landmarkInput = safeDoc.getElementById('swac-addr-landmark');
        const areaInput = safeDoc.getElementById('swac-addr-area');
        const pincodeInput = safeDoc.getElementById('swac-addr-pincode');
        const saveCheck = safeDoc.getElementById('swac-addr-save-default');

        const nameVal = (nameInput?.value || '').trim();
        const addrVal = (addrInput?.value || '').trim();
        const landmarkVal = (landmarkInput?.value || '').trim();
        const areaVal = (areaInput?.value || '').trim();
        const shouldSave = saveCheck ? saveCheck.checked : true;

        let isValid = true;
        let firstInvalid = null;

        if (!nameVal) {
            isValid = false;
            nameInput?.classList.add('swac-addr-input--invalid');
            if (!firstInvalid) firstInvalid = nameInput;
        } else {
            nameInput?.classList.remove('swac-addr-input--invalid');
        }

        if (!addrVal) {
            isValid = false;
            addrInput?.classList.add('swac-addr-input--invalid');
            if (!firstInvalid) firstInvalid = addrInput;
        } else {
            addrInput?.classList.remove('swac-addr-input--invalid');
        }

        const {isValid: pinIsValid, cleanPin} = runPincodeValidation(pincodeInput);
        if (!pinIsValid) {
            isValid = false;
            if (!firstInvalid) firstInvalid = pincodeInput;
        }

        if (!isValid) {
            if (firstInvalid) {
                firstInvalid.scrollIntoView({behavior: 'smooth', block: 'center'});
                setTimeout(() => firstInvalid.focus(), 150);
            }
            return null;
        }

        const details = {
            name: nameVal,
            address: addrVal,
            landmark: landmarkVal,
            area: areaVal,
            pincode: cleanPin
        };

        if (shouldSave) {
            saveCustomerDetails(details);
            addressDraft = {...details, saveDefault: true};
            addressMode = 'preview';
            renderAddressSection(true);
        } else {
            addressDraft = {...details, saveDefault: false};
        }

        return details;
    }

    function bindDrawerEvents() {
        if (!safeDoc) return;
        safeDoc.getElementById('swac-cart-bubble')?.addEventListener('click', openDrawer);
        safeDoc.getElementById('swac-drawer-close')?.addEventListener('click', closeDrawer);
        safeDoc.getElementById('swac-drawer-overlay')?.addEventListener('click', closeDrawer);

        // Header Clear Cart
        safeDoc.getElementById('swac-drawer-clear-btn')?.addEventListener('click', function () {
            if (getCart().length > 0) showClearConfirm();
        });
        safeDoc.getElementById('swac-clear-cancel')?.addEventListener('click', hideClearConfirm);
        safeDoc.getElementById('swac-clear-accept')?.addEventListener('click', function () {
            clearCart();
            hideClearConfirm();
        });

        // WhatsApp Order Button
        safeDoc.getElementById('swac-send-btn')?.addEventListener('click', function (e) {
            const api = getCartApi();
            if (api && typeof api.sendOrderToWhatsApp === 'function') {
                api.sendOrderToWhatsApp(e);
            }
        });

        // Keyboard Focus Trap & Escape key
        const drawer = safeDoc.getElementById('swac-drawer');
        drawer?.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                closeDrawer();
                return;
            }
            if (e.key === 'Tab') {
                const focusables = drawer.querySelectorAll('button:not([disabled]):not([inert]), [href], input:not([disabled]):not([inert]), select:not([disabled]):not([inert]), textarea:not([disabled]):not([inert]), [tabindex]:not([tabindex="-1"])');
                if (focusables.length === 0) return;
                const firstEl = focusables[0];
                const lastEl = focusables[focusables.length - 1];

                if (e.shiftKey) {
                    if (safeDoc.activeElement === firstEl || !drawer.contains(safeDoc.activeElement)) {
                        e.preventDefault();
                        lastEl.focus();
                    }
                } else {
                    if (safeDoc.activeElement === lastEl || !drawer.contains(safeDoc.activeElement)) {
                        e.preventDefault();
                        firstEl.focus();
                    }
                }
            }
        });

        const drawerBody = safeDoc.getElementById('swac-drawer-body');
        drawerBody?.addEventListener('click', function (e) {
            const btn = e.target.closest('button[data-id]');
            if (!btn) return;

            const id = btn.dataset.id;
            const cart = getCart();
            const item = cart.find(i => String(i.id) === String(id));
            if (!item) return;

            const CONFIG = getConfig();

            if (btn.classList.contains('swac-qty-plus')) {
                if (item.qty >= CONFIG.maxQtyPerItem) {
                    showToast(`Maximum ${CONFIG.maxQtyPerItem} units allowed per item for retail orders.`);
                    return;
                }
                item.qty++;
                saveCart(cart);
                renderDrawerItems();
                const api = getCartApi();
                if (api && typeof api.refreshUI === 'function') api.refreshUI();
            } else if (btn.classList.contains('swac-qty-minus')) {
                if (item.qty > 1) {
                    item.qty--;
                    saveCart(cart);
                    renderDrawerItems();
                    const api = getCartApi();
                    if (api && typeof api.refreshUI === 'function') api.refreshUI();
                } else {
                    removeFromCart(id);
                }
            } else if (btn.classList.contains('swac-remove-btn')) {
                removeFromCart(id);
            }
        });
    }

    const drawerApi = {
        openDrawer,
        closeDrawer,
        renderDrawerItems,
        refreshLoyaltySection,
        renderAddressSection,
        validateAddress,
        initAddressState,
        bindDrawerEvents,
        getCurrentPincode,
        getLoyaltyChoice: () => loyaltyChoice,
        setLoyaltyChoice: (c) => { loyaltyChoice = c; },
    };

    safeWindow.swacDrawer = drawerApi;

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = drawerApi;
    }

    if (typeof window !== 'undefined' && typeof document !== 'undefined') {
        initAddressState();
        bindDrawerEvents();
    }
})();
