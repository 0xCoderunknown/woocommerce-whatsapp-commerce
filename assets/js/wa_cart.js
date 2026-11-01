(function () {
    'use strict';

    /**
     * 🧭 ARCHITECTURAL SECTION INDEX
     * -------------------------------------------------------------------------
     * § 01. Customer Metadata Storage  : getCustomerDetails(), saveCustomerDetails()
     * § 02. Delivery Zones & Gates     : evaluateDeliveryZone(), getZoneSets()
     * § 03. Configuration & Bridge     : CONFIG (window.swacCommerce), formatters
     * § 04. Cart LocalStorage CRUD     : getCart(), saveCart(), addToCart(), removeFromCart()
     * § 05. UI Floating Bubble Counter : refreshUI(), updateCounter()
     * § 06. Cart Drawer & Address UI   : openDrawer(), closeDrawer(), renderAddressSection()
     * § 07. Cart Math & Reconciliation : computeCartTotals(), DOM price reconciliation
     * § 08. Loyalty State Machine      : loyaltyChoice, banner rendering
     * § 09. Pincode & Form Validation  : runPincodeValidation(), validateAddress()
     * § 10. Order Builder & Serializer : buildOrderMessage(), sendOrderToWhatsApp()
     * § 11. Add-to-Cart Interception   : interceptAddToCart(), bindLoopButtons()
     * § 12. Catalog Ordering Autosubmit: bindCatalogOrdering()
     * § 13. Initialization             : initAddressState(), refreshUI(), interceptAddToCart()
     * -------------------------------------------------------------------------
     */

    const safeWindow = (typeof window !== 'undefined') ? window : (typeof globalThis !== 'undefined' ? globalThis : {});
    const safeDoc = (typeof document !== 'undefined') ? document : null;
    const safeStorage = (function () {
        try {
            if (typeof localStorage !== 'undefined') {
                return localStorage;
            }
        } catch (e) {}
        const memoryStore = {};
        return {
            getItem: function (k) { return Object.prototype.hasOwnProperty.call(memoryStore, k) ? memoryStore[k] : null; },
            setItem: function (k, v) { memoryStore[k] = String(v); },
            removeItem: function (k) { delete memoryStore[k]; },
            clear: function () { Object.keys(memoryStore).forEach(function (k) { delete memoryStore[k]; }); }
        };
    })();

    const STORAGE_KEY = 'swac_cart';
    const CUSTOMER_STORAGE_KEY = 'swac_customer_details';

    // ── Persistent Customer / Delivery Metadata ──────────────────────────────
    function getCustomerDetails() {
        try {
            const raw = safeStorage.getItem(CUSTOMER_STORAGE_KEY);
            if (!raw) return null;
            const data = JSON.parse(raw);
            if (data && typeof data === 'object' && !Array.isArray(data)) {
                return {
                    name: (data.name || '').trim(),
                    address: (data.address || '').trim(),
                    landmark: (data.landmark || '').trim(),
                    area: (data.area || '').trim(),
                    pincode: (data.pincode || '').trim()
                };
            }
            return null;
        } catch (e) {
            return null;
        }
    }

    function saveCustomerDetails(details) {
        try {
            const clean = {
                name: (details.name || '').trim(),
                address: (details.address || '').trim(),
                landmark: (details.landmark || '').trim(),
                area: (details.area || '').trim(),
                pincode: (details.pincode || '').trim()
            };
            safeStorage.setItem(CUSTOMER_STORAGE_KEY, JSON.stringify(clean));
            return clean;
        } catch (e) {
            return details;
        }
    }

    // ── Delivery Service Zones & Pincode Gate ────────────────────────────────
    let sameDaySet = null;
    let outskirtsSet = null;
    let alwaysFreeSet = null;

    function getZoneSets() {
        if (!sameDaySet || sameDaySet.size === 0 || !alwaysFreeSet) {
            const zonesData = safeWindow.swacZones || {};
            const configData = safeWindow.swacCommerce || safeWindow.swacConfig || {};
            const rawSameDay = zonesData.same_day_pins || (zonesData.zones && zonesData.zones.standard && zonesData.zones.standard.pincodes ? Object.keys(zonesData.zones.standard.pincodes) : []) || [];
            const rawOutskirts = zonesData.outskirts_pins || [];
            const rawAlwaysFree = zonesData.always_free_shipping_codes || configData.always_free_shipping_codes || (typeof CONFIG !== 'undefined' && CONFIG.alwaysFreeShippingCodes) || [];

            sameDaySet = new Set(rawSameDay.map(String));
            outskirtsSet = new Set(rawOutskirts.map(String));
            alwaysFreeSet = new Set((Array.isArray(rawAlwaysFree) ? rawAlwaysFree : []).map(String).map(s => s.replace(/\D/g, '')).filter(Boolean));
        }
        return { sameDaySet, outskirtsSet, alwaysFreeSet };
    }

    function isAlwaysFreePincode(pincode) {
        if (!pincode) return false;
        const clean = String(pincode).replace(/\D/g, '');
        if (!clean) return false;
        const { alwaysFreeSet: freeSet } = getZoneSets();
        return Boolean(freeSet && freeSet.has(clean));
    }

    function evaluateDeliveryZone(pincode) {
        if (!pincode) return null;
        const clean = String(pincode).replace(/\D/g, '');
        if (clean.length < 3) return null;

        const zonesData = safeWindow.swacZones || {};
        const isGated = Boolean(zonesData.gated || (zonesData.same_day_pins && zonesData.same_day_pins.length > 0));

        // Always-free postal codes are always allowed for delivery
        if (isAlwaysFreePincode(clean)) {
            const msgs = zonesData.messages || {};
            return {
                allowOrder: true,
                statusClass: 'swac-pin-success',
                message: msgs.available || msgs.same_day || '✓ Delivery is available to your location'
            };
        }

        // In Open Mode (default), any valid postal code format is accepted
        if (!isGated) {
            return {
                allowOrder: true,
                statusClass: 'swac-pin-success',
                message: (zonesData.messages && (zonesData.messages.available || zonesData.messages.same_day)) || '✓ Delivery is available to your location'
            };
        }

        const { sameDaySet: sDays, outskirtsSet: oSkirts } = getZoneSets();
        const msgs = zonesData.messages || {};

        if (sDays.has(clean)) {
            return {
                allowOrder: true,
                statusClass: 'swac-pin-success',
                message: msgs.available || msgs.same_day || '✓ Delivery available to your location'
            };
        }
        if (oSkirts.has(clean)) {
            return {
                allowOrder: false,
                statusClass: 'swac-pin-warning',
                message: msgs.unsupported || msgs.outskirts || '⚠️ We do not deliver to this location yet.'
            };
        }
        return {
            allowOrder: false,
            statusClass: 'swac-pin-error',
            message: msgs.invalid || msgs.outside || '❌ We do not deliver to this location.'
        };
    }


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
            // If stored address exists but fails pincode validation, expand form to force correction
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

    // ── Cached DOM references (script is footer-enqueued — DOM already parsed) ─
    const _bubble = safeDoc ? safeDoc.getElementById('swac-cart-bubble') : null;
    const _counter = safeDoc ? safeDoc.getElementById('swac-cart-count') : null;

    // ── Configuration ────────────────────────────────────────────────────────
    const CONFIG = (function () {
        const wa = safeWindow.swacCommerce || safeWindow.swacConfig || {};
        const loy = wa.loyalty || {};
        const isLoyEnabled = (function () {
            if (typeof loy.enabled === 'boolean') return loy.enabled;
            if (typeof loy.enabled === 'string') return loy.enabled !== 'false' && loy.enabled !== '0' && loy.enabled !== '';
            if (typeof loy.enabled === 'number') return loy.enabled !== 0;
            return false;
        })();
        const itemLabel = (typeof loy.item_label === 'string') ? loy.item_label.trim() : '';

        const isPrescriptionNoteEnabled = (function () {
            if (typeof wa.prescription_note_enabled === 'boolean') return wa.prescription_note_enabled;
            if (typeof wa.prescription_note_enabled === 'string') return wa.prescription_note_enabled !== 'false' && wa.prescription_note_enabled !== '0' && wa.prescription_note_enabled !== '';
            if (typeof wa.prescription_note_enabled === 'number') return wa.prescription_note_enabled !== 0;
            return false;
        })();
        const prescriptionNote = (typeof wa.prescription_note === 'string')
            ? wa.prescription_note.trim()
            : "";

        return {
            number: wa.number || wa.phone_number || '',
            name: wa.name || wa.store_name || 'Store',
            baseUrl: wa.baseUrl || wa.base_url || '',
            cityName: wa.cityName || wa.city_name || '',
            currencySymbol: wa.currencySymbol || wa.currency_symbol || '',
            freeShippingAt: parseFloat(wa.free_shipping_at) || 0,
            shippingCharge: parseFloat(wa.shipping_charge) || 0,
            alwaysFreeShippingCodes: (Array.isArray(wa.always_free_shipping_codes) ? wa.always_free_shipping_codes : []).map(String).map(s => s.replace(/\D/g, '')).filter(Boolean),
            isAdmin: Boolean(wa.is_admin),
            maxCartItems: parseInt(wa.max_cart_items, 10) || 10,
            maxQtyPerItem: parseInt(wa.max_qty_per_item, 10) || 10,
            prescriptionNoteEnabled: isPrescriptionNoteEnabled,
            prescriptionNote: prescriptionNote,
            loyalty: {
                enabled: isLoyEnabled,
                threshold: parseFloat(loy.threshold) || 0,
                itemLabel: itemLabel,
                itemWorth: parseFloat(loy.item_worth) || 0,
                itemPrice: parseFloat(loy.item_price) || 0,
                flag: loy.flag || 'OFFER ACCEPTED ✅'
            },
            timezoneOffset: (typeof wa.timezone_offset === 'number') ? wa.timezone_offset : 0,
            cutoffMessages: (wa.cutoff_messages && typeof wa.cutoff_messages === 'object') ? wa.cutoff_messages : {}
        };
    })();

    const fmtPrice = n => (CONFIG.currencySymbol ? CONFIG.currencySymbol + ' ' : '') + n.toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 2 });
    const fmtInr = fmtPrice;

    function isLoyaltyActive(saleTotal) {
        return CONFIG.loyalty.enabled && CONFIG.loyalty.itemLabel.length > 0 && CONFIG.loyalty.threshold > 0 && saleTotal >= CONFIG.loyalty.threshold;
    }

    let loyaltyChoice = null;

    let _toastTimer = null;
    function showToast(msg) {
        let toast = document.getElementById('swac-toast');
        if (!toast) {
            toast = document.createElement('div');
            toast.id = 'swac-toast';
            toast.className = 'swac-toast';
            toast.setAttribute('role', 'status');
            toast.setAttribute('aria-live', 'polite');
            document.body.appendChild(toast);
        }
        toast.textContent = msg;
        toast.classList.add('swac-toast--visible');
        if (_toastTimer) clearTimeout(_toastTimer);
        _toastTimer = setTimeout(() => {
            toast.classList.remove('swac-toast--visible');
        }, 3200);
    }

    // ── Storage Helpers ──────────────────────────────────────────────────────
    function getCart() {
        try {
            const raw = safeStorage.getItem(STORAGE_KEY);
            if (!raw) return [];
            const data = JSON.parse(raw);

            if (data && Array.isArray(data.items)) {
                // Expire carts older than 7 days to prevent stale pricing accumulation.
                const SEVEN_DAYS_MS = 7 * 24 * 60 * 60 * 1000;
                if (data.updatedAt && (Date.now() - data.updatedAt) > SEVEN_DAYS_MS) {
                    safeStorage.removeItem(STORAGE_KEY);
                    return [];
                }
                return data.items;
            }
            return [];
        } catch (e) {
            return [];
        }
    }

    function saveCart(cart) {
        try {
            const payload = {
                items: cart,
                updatedAt: Date.now()
            };
            safeStorage.setItem(STORAGE_KEY, JSON.stringify(payload));
        } catch (e) {
            // QuotaExceededError — storage is full (common in private/incognito browsing).
            // Cart data is not persisted but the in-memory session continues to work.
        }
    }

    function addToCart(item, btn) {
        const cart = getCart();
        const existing = cart.find(i => String(i.id) === String(item.id));
        if (!existing) {
            if (cart.length >= CONFIG.maxCartItems) {
                showToast(`Cart limit reached (max ${CONFIG.maxCartItems} different items). Please place this order first.`);
                return;
            }
            if (item.qty > CONFIG.maxQtyPerItem) {
                item.qty = CONFIG.maxQtyPerItem;
                showToast(`Maximum ${CONFIG.maxQtyPerItem} units allowed per item.`);
            }
            cart.push(item);
        } else {
            if (existing.qty >= CONFIG.maxQtyPerItem) {
                showToast(`Maximum limit of ${CONFIG.maxQtyPerItem} units already in cart for this item.`);
                return;
            }
            if (existing.qty + item.qty > CONFIG.maxQtyPerItem) {
                existing.qty = CONFIG.maxQtyPerItem;
                showToast(`Quantity adjusted to maximum allowed (${CONFIG.maxQtyPerItem} units).`);
            } else {
                existing.qty += item.qty;
            }
            if (item.price) existing.price = item.price;
            if (typeof item.mrp !== 'undefined' && item.mrp > 0) existing.mrp = item.mrp;
            if (typeof item.salePrice !== 'undefined' && item.salePrice > 0) existing.salePrice = item.salePrice;
            if (typeof item.isRx !== 'undefined') existing.isRx = item.isRx;
            if (item.name) existing.name = item.name;
            if (item.url) existing.url = item.url;
        }
        saveCart(cart);
        loyaltyChoice = null;
        refreshUI();
        showAddedFeedback(btn);
    }

    function removeFromCart(productId) {
        // Use String() on both sides — item.id may be stored as a number in legacy carts
        // while productId always arrives as a string from dataset.id.
        const cart = getCart().filter(i => String(i.id) !== String(productId));
        saveCart(cart);
        loyaltyChoice = null;
        refreshUI();
    }

    function clearCart() {
        safeStorage.removeItem(STORAGE_KEY);
        loyaltyChoice = null;
        hideClearConfirm();
        refreshUI();
    }

    function showClearConfirm() {
        const clearConfirm = document.getElementById('swac-clear-confirm');
        const clearBtn = document.getElementById('swac-clear-cart');
        if (clearConfirm) clearConfirm.style.display = 'flex';
        if (clearBtn) clearBtn.style.display = 'none';
    }

    function hideClearConfirm() {
        const clearConfirm = document.getElementById('swac-clear-confirm');
        const clearBtn = document.getElementById('swac-clear-cart');
        if (clearConfirm) clearConfirm.style.display = 'none';
        if (clearBtn) clearBtn.style.display = '';
    }

    // ── UI Updates ───────────────────────────────────────────────────────────
    function refreshUI() {
        const cart = getCart();
        const total = cart.reduce((sum, i) => sum + i.qty, 0);

        if (!_bubble) return;

        if (total > 0) {
            _bubble.style.display = 'flex';
            if (_counter) _counter.textContent = total;
        } else {
            _bubble.style.display = 'none';
            closeDrawer();
        }

        renderDrawerItems();
    }

    // ── Drawer Management ────────────────────────────────────────────────────
    function openDrawer() {
        const drawer = document.getElementById('swac-drawer');
        const overlay = document.getElementById('swac-drawer-overlay');
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
            document.getElementById('swac-drawer-close')?.focus();
        }, 50);
    }

    function closeDrawer() {
        const drawer = document.getElementById('swac-drawer');
        const overlay = document.getElementById('swac-drawer-overlay');
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
        const body = document.getElementById('swac-drawer-body');
        if (!body) return;

        const cart = getCart();

        if (cart.length === 0) {
            body.innerHTML = '<p class="swac-drawer-empty">Your order list is empty.</p>';
            updateSendButton();
            hideClearConfirm();
            return;
        }

        ensureDrawerBodyStructure(body);

        const itemsContainer = document.getElementById('swac-items-list');
        if (itemsContainer) {
            itemsContainer.innerHTML = cart.map(item => {
                const isMax = item.qty >= CONFIG.maxQtyPerItem;
                return `
                <div class="swac-drawer-item" data-id="${escHtml(String(item.id))}">
                    <div class="swac-item-info">
                        <span class="swac-item-name">${escHtml(item.name)}</span>
                        ${item.price ? `<span class="swac-item-price">${escHtml(item.price)}</span>` : ''}
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
        const section = document.getElementById('swac-loyalty-section');
        const totalsSection = document.getElementById('swac-totals-section');
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
        const container = document.getElementById('swac-address-section');
        if (!container) return;

        const saved = getCustomerDetails();

        // Guard: initAddressState() owns the initial addressMode decision at boot.
        // Here we only correct preview → edit if saved data has since become invalid
        // (missing required fields or unserviceable pincode).
        if (addressMode === 'preview') {
            const zoneRes = (saved && saved.pincode) ? evaluateDeliveryZone(saved.pincode) : null;
            if (!saved || !saved.name || !saved.address || !zoneRes || !zoneRes.allowOrder) {
                addressMode = 'edit';
                addressCollapsed = false;
            }
        }

        if (addressMode === 'preview' && saved) {
            container.innerHTML = `
                <div class="swac-address-card" id="swac-address-card">
                    <div class="swac-address-card-header">
                        <span class="swac-address-card-badge">
                            &#127968; Delivery Details
                            ${justSaved ? '<span class="swac-address-saved-pill" id="swac-address-saved-pill">&#10003; Saved</span>' : ''}
                        </span>
                        <button type="button" class="swac-address-edit-btn" id="swac-addr-edit-btn" aria-label="Edit delivery address">
                            Change / Edit
                        </button>
                    </div>
                    <div class="swac-address-card-body">
                        <div class="swac-address-card-sub">${saved.area ? escHtml(saved.area) + ', ' : ''}${saved.pincode ? 'PIN: ' + escHtml(saved.pincode) : ''}${CONFIG.cityName ? ', ' + escHtml(CONFIG.cityName) : ''}</div>
                    </div>
                </div>
            `;
            document.getElementById('swac-addr-edit-btn')?.addEventListener('click', function () {
                addressMode = 'edit';
                renderAddressSection(true);
                setTimeout(() => {
                    document.getElementById('swac-addr-name')?.focus();
                }, 50);
            });
        } else {
            // Edit / First-Time Form
            const hasSaved = Boolean(saved && saved.name && saved.address);

            // If first-time and collapsed, show the "+ Add Delivery Address" prompt
            if (!hasSaved && addressCollapsed) {
                container.innerHTML = `
                    <div class="swac-address-add-prompt">
                        <button type="button" class="swac-address-add-btn" id="swac-addr-add-btn" aria-label="Add Delivery Address">
                            <span class="swac-address-add-icon">&#128205; +</span>
                            <span class="swac-address-add-text">Add Delivery Address</span>
                        </button>
                    </div>
                `;
                document.getElementById('swac-addr-add-btn')?.addEventListener('click', function () {
                    addressCollapsed = false;
                    renderAddressSection(true);
                    setTimeout(() => {
                        document.getElementById('swac-addr-name')?.focus();
                    }, 50);
                });
                return;
            }

            // If already displaying form and not force-updating, preserve user typing
            if (!force && container.querySelector('.swac-address-form')) {
                return;
            }

            const current = {
                name: addressDraft.name || saved?.name || '',
                address: addressDraft.address || saved?.address || '',
                landmark: addressDraft.landmark || saved?.landmark || '',
                area: addressDraft.area || saved?.area || '',
                pincode: addressDraft.pincode || saved?.pincode || '',
                saveDefault: typeof addressDraft.saveDefault === 'boolean' ? addressDraft.saveDefault : true
            };

            container.innerHTML = `
                <div class="swac-address-form" id="swac-address-form">
                    <div class="swac-address-form-header">
                        <span class="swac-address-form-title">&#128205; Delivery Address</span>
                        ${hasSaved ? `<button type="button" class="swac-address-cancel-btn" id="swac-addr-cancel-btn" aria-label="Cancel editing">Cancel</button>` : `<button type="button" class="swac-address-collapse-btn" id="swac-addr-collapse-btn" aria-label="Collapse address form">Collapse</button>`}
                    </div>
                    <div class="swac-form-group">
                        <label for="swac-addr-name" class="swac-form-label">Full Name <span class="swac-form-req">*</span></label>
                        <input type="text" id="swac-addr-name" class="swac-form-input" placeholder="e.g. Rahul Sharma" value="${escHtml(current.name)}" autocomplete="name" />
                        <div id="swac-addr-name-err" class="swac-form-error-msg" style="display:none;"></div>
                    </div>
                    <div class="swac-form-group">
                        <label for="swac-addr-address" class="swac-form-label">Delivery Address &amp; Street <span class="swac-form-req">*</span></label>
                        <input type="text" id="swac-addr-address" class="swac-form-input" placeholder="House/Flat No, Building, Street / Bylane" value="${escHtml(current.address)}" autocomplete="street-address" />
                        <div id="swac-addr-address-err" class="swac-form-error-msg" style="display:none;"></div>
                    </div>
                    <div class="swac-form-group">
                        <label for="swac-addr-landmark" class="swac-form-label">Landmark <span style="font-weight:400;color:#64748b;">(Optional)</span></label>
                        <input type="text" id="swac-addr-landmark" class="swac-form-input" placeholder="e.g. Near Central Park" value="${escHtml(current.landmark)}" />
                    </div>
                    <div class="swac-form-group">
                        <label for="swac-addr-area" class="swac-form-label">Area / Locality <span style="font-weight:400;color:#64748b;">(Optional)</span></label>
                        <input type="text" id="swac-addr-area" class="swac-form-input" placeholder="e.g. Downtown" value="${escHtml(current.area)}" />
                    </div>
                    <div class="swac-form-group">
                        <label for="swac-addr-pincode" class="swac-form-label">Postal / Pincode <span class="swac-form-req">*</span></label>
                        <input type="text" id="swac-addr-pincode" class="swac-form-input" placeholder="e.g. Postal code" value="${escHtml(current.pincode)}" inputmode="numeric" maxlength="10" autocomplete="postal-code" />
                        <div id="swac-pincode-status" class="swac-pincode-status" style="display:none;"></div>
                        <div id="swac-addr-pincode-err" class="swac-form-error-msg" style="display:none;"></div>
                    </div>
                    <label class="swac-form-checkbox-label">
                        <input type="checkbox" id="swac-addr-save-default" ${current.saveDefault ? 'checked' : ''} />
                        <span>Save as my default delivery address</span>
                    </label>
                    <div class="swac-address-form-actions">
                        <button type="button" class="swac-btn-save-address" id="swac-btn-save-address">Save Address</button>
                        ${hasSaved ? `<button type="button" class="swac-btn-cancel-address" id="swac-btn-cancel-address">Cancel</button>` : ''}
                    </div>
                </div>
            `;

            const nameInput = document.getElementById('swac-addr-name');
            const addrInput = document.getElementById('swac-addr-address');
            const landmarkInput = document.getElementById('swac-addr-landmark');
            const areaInput = document.getElementById('swac-addr-area');
            const pincodeInput = document.getElementById('swac-addr-pincode');
            const saveCheck = document.getElementById('swac-addr-save-default');

            const syncDraft = () => {
                addressDraft.name = nameInput ? nameInput.value : '';
                addressDraft.address = addrInput ? addrInput.value : '';
                addressDraft.landmark = landmarkInput ? landmarkInput.value : '';
                addressDraft.area = areaInput ? areaInput.value : '';
                addressDraft.pincode = pincodeInput ? pincodeInput.value : '';
                addressDraft.saveDefault = saveCheck ? saveCheck.checked : true;
            };

            function applyPincodeGating(pincodeVal, isBlur) {
                const statusEl = document.getElementById('swac-pincode-status');
                const errEl = document.getElementById('swac-addr-pincode-err');
                const pinInput = document.getElementById('swac-addr-pincode');
                const saveBtn = document.getElementById('swac-btn-save-address');
                const sendBtn = document.getElementById('swac-send-order');

                const clean = String(pincodeVal || '').replace(/\D/g, '');

                if (clean.length === 6) {
                    const res = evaluateDeliveryZone(clean);
                    if (!res) return null;

                    if (statusEl) {
                        statusEl.className = 'swac-pincode-status ' + res.statusClass;
                        statusEl.textContent = res.message;
                        statusEl.style.display = 'flex';
                    }
                    if (errEl) errEl.style.display = 'none';

                    if (res.allowOrder) {
                        if (pinInput) pinInput.classList.remove('swac-input-error');
                        if (saveBtn) {
                            saveBtn.disabled = false;
                            saveBtn.style.opacity = '';
                            saveBtn.style.pointerEvents = '';
                        }
                        updateSendButton();
                    } else {
                        if (pinInput) pinInput.classList.add('swac-input-error');
                        if (saveBtn) {
                            saveBtn.disabled = true;
                            saveBtn.style.opacity = '0.5';
                            saveBtn.style.pointerEvents = 'none';
                        }
                        if (sendBtn) {
                            sendBtn.disabled = true;
                            sendBtn.style.opacity = '0.5';
                            sendBtn.style.pointerEvents = 'none';
                        }
                    }
                    return res;
                }

                // Pin is fewer than 6 digits — clear the zone badge in all cases.
                if (statusEl) {
                    statusEl.style.display = 'none';
                    statusEl.className = 'swac-pincode-status';
                    statusEl.textContent = '';
                }

                if (clean.length > 0) {
                    // Partially typed: always keep Save/Send disabled—wrong-length pin.
                    if (saveBtn) {
                        saveBtn.disabled = true;
                        saveBtn.style.opacity = '0.5';
                        saveBtn.style.pointerEvents = 'none';
                    }
                    if (sendBtn) {
                        sendBtn.disabled = true;
                        sendBtn.style.opacity = '0.5';
                        sendBtn.style.pointerEvents = 'none';
                    }
                    // Show format error only on blur to avoid an instant red flash while typing.
                    if (isBlur) {
                        if (pinInput) pinInput.classList.add('swac-input-error');
                        if (errEl) {
                            errEl.textContent = 'Pincode must be 6 digits';
                            errEl.style.display = 'block';
                        }
                    } else {
                        if (pinInput) pinInput.classList.remove('swac-input-error');
                        if (errEl) errEl.style.display = 'none';
                    }
                } else {
                    // Empty field: clear errors; Save is accessible until the user tries to submit.
                    if (pinInput) pinInput.classList.remove('swac-input-error');
                    if (errEl) errEl.style.display = 'none';
                    if (saveBtn) {
                        saveBtn.disabled = false;
                        saveBtn.style.opacity = '';
                        saveBtn.style.pointerEvents = '';
                    }
                    updateSendButton();
                }

                return null;
            }

            [nameInput, addrInput, landmarkInput, areaInput].forEach(el => {
                el?.addEventListener('input', function () {
                    syncDraft();
                    if (this.classList.contains('swac-input-error')) {
                        this.classList.remove('swac-input-error');
                        const errEl = document.getElementById(this.id + '-err');
                        if (errEl) errEl.style.display = 'none';
                    }
                });
            });

            pincodeInput?.addEventListener('input', function () {
                syncDraft();
                applyPincodeGating(this.value, false);
                refreshLoyaltySection();
            });

            pincodeInput?.addEventListener('blur', function () {
                syncDraft();
                applyPincodeGating(this.value, true);
                refreshLoyaltySection();
            });

            if (current.pincode) {
                applyPincodeGating(current.pincode, false);
            }

            saveCheck?.addEventListener('change', syncDraft);

            const handleCancel = function () {
                if (saved) {
                    addressDraft = {
                        name: saved.name || '',
                        address: saved.address || '',
                        landmark: saved.landmark || '',
                        area: saved.area || '',
                        pincode: saved.pincode || '',
                        saveDefault: true
                    };
                    // Only restore preview if the saved pincode is actually serviceable.
                    // If not, leave the form open so the user is forced to correct it.
                    const cancelZoneRes = saved.pincode ? evaluateDeliveryZone(saved.pincode) : null;
                    if (saved.name && saved.address && cancelZoneRes && cancelZoneRes.allowOrder) {
                        addressMode = 'preview';
                    } else {
                        addressMode = 'edit';
                        addressCollapsed = false;
                    }
                } else {
                    addressMode = 'edit';
                    addressCollapsed = true;
                }
                renderAddressSection(true);
            };

            document.getElementById('swac-addr-cancel-btn')?.addEventListener('click', handleCancel);
            document.getElementById('swac-btn-cancel-address')?.addEventListener('click', handleCancel);

            document.getElementById('swac-addr-collapse-btn')?.addEventListener('click', function () {
                addressCollapsed = true;
                renderAddressSection(true);
            });

            document.getElementById('swac-btn-save-address')?.addEventListener('click', function () {
                let isValid = true;
                let firstInvalid = null;

                const nameVal = nameInput ? nameInput.value.trim() : '';
                const addrVal = addrInput ? addrInput.value.trim() : '';
                const landmarkVal = landmarkInput ? landmarkInput.value.trim() : '';
                const areaVal = areaInput ? areaInput.value.trim() : '';
                const pincodeVal = pincodeInput ? pincodeInput.value.trim() : '';
                const shouldSave = saveCheck ? saveCheck.checked : true;

                if (!nameVal) {
                    isValid = false;
                    if (nameInput) {
                        nameInput.classList.add('swac-input-error');
                        const errEl = document.getElementById('swac-addr-name-err');
                        if (errEl) {
                            errEl.textContent = 'Please enter your full name';
                            errEl.style.display = 'block';
                        }
                        if (!firstInvalid) firstInvalid = nameInput;
                    }
                }

                if (!addrVal) {
                    isValid = false;
                    if (addrInput) {
                        addrInput.classList.add('swac-input-error');
                        const errEl = document.getElementById('swac-addr-address-err');
                        if (errEl) {
                            errEl.textContent = 'Please enter your delivery address';
                            errEl.style.display = 'block';
                        }
                        if (!firstInvalid) firstInvalid = addrInput;
                    }
                }

                const { isValid: pinIsValid, cleanPin } = runPincodeValidation(pincodeInput);
                if (!pinIsValid) {
                    isValid = false;
                    if (!firstInvalid) firstInvalid = pincodeInput;
                }

                if (!isValid) {
                    if (firstInvalid) {
                        firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        setTimeout(() => firstInvalid.focus(), 150);
                    }
                    return;
                }

                const details = {
                    name: nameVal,
                    address: addrVal,
                    landmark: landmarkVal,
                    area: areaVal,
                    pincode: cleanPin
                };

                saveCustomerDetails(details);
                addressDraft = { ...details, saveDefault: shouldSave };
                addressMode = 'preview';
                renderAddressSection(true, true);
            });
        }
    }

    function getCurrentPincode() {
        if (addressDraft && addressDraft.pincode) {
            return String(addressDraft.pincode).trim();
        }
        const pinInput = safeDoc ? safeDoc.getElementById('swac-addr-pincode') : null;
        if (pinInput && pinInput.value) {
            return String(pinInput.value).trim();
        }
        const saved = getCustomerDetails();
        if (saved && saved.pincode) {
            return String(saved.pincode).trim();
        }
        return '';
    }

    // ── Calculations ─────────────────────────────────────────────────────────
    function computeCartTotals(cart, pincode) {
        const code = pincode !== undefined ? pincode : getCurrentPincode();
        const isAlwaysFree = isAlwaysFreePincode(code);

        const freeAt = CONFIG.freeShippingAt;
        const shipCharge = CONFIG.shippingCharge;

        let totalMrp = 0;
        let saleTotal = 0;

        (cart || []).forEach(function (item) {
            const sale = parseFloat(item.salePrice) || 0;
            let mrp = parseFloat(item.mrp) || 0;

            if (mrp <= 0) {
                mrp = sale;
            }
            if (mrp < sale) {
                mrp = sale;
            }

            const qty = parseInt(item.qty, 10) || 1;
            totalMrp += mrp * qty;
            saleTotal += sale * qty;
        });

        let youSave = totalMrp - saleTotal;
        let savePct = totalMrp > 0 ? Math.round(youSave / totalMrp * 100) : 0;
        const shipping = isAlwaysFree ? 0 : (saleTotal < freeAt ? shipCharge : 0);
        let amountPayable = saleTotal + shipping;

        if (loyaltyChoice === 'yes' && isLoyaltyActive(saleTotal)) {
            const loyaltyDiscount = CONFIG.loyalty.itemWorth - CONFIG.loyalty.itemPrice;
            youSave = youSave + loyaltyDiscount;
            savePct = Math.round(youSave / (totalMrp + CONFIG.loyalty.itemWorth) * 100);
            amountPayable += CONFIG.loyalty.itemPrice;
        }

        return { totalMrp, saleTotal, youSave, savePct, shipping, amountPayable, isAlwaysFree };
    }

    // ── Loyalty & Delivery Offer State Machine ──────────────────────────────
    function renderLoyaltyUI(container, cart, totals, pincode) {
        if (!container) return;
        const code = pincode !== undefined ? pincode : getCurrentPincode();
        const isAlwaysFree = Boolean((totals && totals.isAlwaysFree) || isAlwaysFreePincode(code));

        // Always-Free Postal Code Badge
        if (isAlwaysFree && code) {
            const freeZoneCard = document.createElement('div');
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

        // Stage 1: Free Delivery Nudge (orders below free shipping minimum, only if NOT already free delivery)
        if (!isAlwaysFree && freeAt > 0 && shipCharge > 0 && totals.saleTotal < freeAt) {
            loyaltyChoice = null;
            const shipGap = Math.ceil(freeAt - totals.saleTotal);
            const card = document.createElement('div');
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
        let disabledReason = null;
        if (!loy.enabled) {
            disabledReason = 'SWAC_LOYALTY_ENABLED';
        } else if (!loy.itemLabel) {
            disabledReason = 'SWAC_LOYALTY_ITEM_LABEL';
        } else if (loy.threshold <= 0) {
            disabledReason = 'SWAC_LOYALTY_THRESHOLD';
        }

        if (disabledReason) {
            loyaltyChoice = null;
            if (CONFIG.isAdmin) {
                const card = document.createElement('div');
                card.className = 'swac-loyalty-card swac-loyalty-card--admin-disabled';
                card.innerHTML = `
                    <div class="swac-loyalty-heading">🔒 Admin Notice</div>
                    <div class="swac-loyalty-body">
                        Loyalty offer is currently <strong>turned off</strong> via <code>${disabledReason}</code> in <code>core/config.php</code>. Visitors see nothing.
                    </div>
                `;
                container.appendChild(card);
            }
            return;
        }

        const threshold = loy.threshold;
        const itemLabel = loy.itemLabel;
        const itemWorth = loy.itemWorth;
        const itemPrice = loy.itemPrice;

        const card = document.createElement('div');

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

            document.getElementById('swac-loyalty-remove')?.addEventListener('click', function () {
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

            document.getElementById('swac-loyalty-yes')?.addEventListener('click', function () {
                loyaltyChoice = 'yes';
                refreshLoyaltySection();
            });
        }
    }

    function renderTotals(container, cart, totals) {
        const loy = CONFIG.loyalty;
        const { totalMrp, saleTotal, youSave, savePct, shipping, amountPayable } = totals;
        const loyaltyActive = isLoyaltyActive(saleTotal);

        const shipLabel = shipping === 0 ? '<span class="swac-totals-free">FREE</span>' : fmtInr(shipping);

        const block = document.createElement('div');
        block.className = 'swac-totals-block';
        block.innerHTML = `
            <div class="swac-totals-row"><span>Total MRP</span><span>${fmtInr(totalMrp)}</span></div>
            <div class="swac-totals-row swac-totals-save"><span>Discount</span><span>${fmtInr(youSave)} (${savePct}% off)</span></div>
            <div class="swac-totals-row"><span>Shipping</span><span>${shipLabel}</span></div>
            ${loyaltyChoice === 'yes' && loyaltyActive
                ? `<div class="swac-totals-row swac-totals-loyalty cdr-totals-loyalty"><span><span class="cdr-loyalty-gift-icon" aria-hidden="true">&#127873;</span>${escHtml(loy.itemLabel)} (worth ${fmtInr(loy.itemWorth)})</span><span>+${fmtInr(loy.itemPrice)}</span></div>`
                : ''}
            <div class="swac-totals-row swac-totals-payable"><span>To Pay</span><span>${fmtInr(amountPayable)}</span></div>
            <div class="swac-drawer-delivery-info">
                ${CONFIG.cityName ? `<span class="swac-drawer-area-badge">&#128205; Delivering in ${escHtml(CONFIG.cityName)}</span>` : ''}
                <span class="swac-drawer-delivery-cutoff">&#9889; <span class="swac-drawer-cutoff-text">${escHtml(getDeliveryCutoffMessage())}</span></span>
            </div>
        `;
        container.appendChild(block);
    }

    function updateSendButton() {
        const btn = document.getElementById('swac-send-order');
        const note = document.getElementById('swac-order-note');
        if (!btn) return;
        const cart = getCart();
        if (cart.length === 0) {
            btn.disabled = true;
            btn.style.opacity = '0.5';
            btn.style.pointerEvents = 'none';
            btn.title = 'Your order list is empty';
            if (note) note.style.display = 'none';
            return;
        }

        if (note) note.style.display = '';

        // ── Phone number configuration check ──────────────────────────────────
        if (!CONFIG.baseUrl && !CONFIG.number) {
            btn.disabled = true;
            btn.style.opacity = '0.5';
            btn.style.pointerEvents = 'none';
            btn.title = 'WhatsApp ordering is not configured yet';
            if (note) {
                note.textContent = CONFIG.isAdmin
                    ? '⚠️ Admin Notice: No phone number configured. Set phone_number in swac_commerce_config.'
                    : 'WhatsApp ordering is currently unavailable.';
            }
            return;
        }

        // ── Pincode serviceability gate ───────────────────────────────────────
        if (addressMode === 'preview') {
            // Preview card: validate the saved/confirmed pincode
            const saved = getCustomerDetails();
            if (!saved || !saved.pincode) {
                // Saved data missing pincode — treat as not ready
                btn.disabled = true;
                btn.style.opacity = '0.5';
                btn.style.pointerEvents = 'none';
                btn.title = 'Please add a valid delivery address to continue';
                return;
            }
            const zoneRes = evaluateDeliveryZone(saved.pincode);
            if (!zoneRes || !zoneRes.allowOrder) {
                btn.disabled = true;
                btn.style.opacity = '0.5';
                btn.style.pointerEvents = 'none';
                btn.title = (zoneRes && zoneRes.message) || 'Pincode is outside our delivery area';
                return;
            }
        } else {
            // Edit mode: check the live DOM input when available, otherwise fall
            // back to the draft (covers the "collapsed" state where the form is not
            // rendered and #swac-addr-pincode does not exist in the DOM).
            const pincodeInput = document.getElementById('swac-addr-pincode');
            const pinVal = pincodeInput ? pincodeInput.value : (addressDraft.pincode || '');
            const cleanPin = String(pinVal).replace(/\D/g, '');

            if (cleanPin.length !== 6) {
                // Incomplete or missing pin — disable until the user fills the form
                btn.disabled = true;
                btn.style.opacity = '0.5';
                btn.style.pointerEvents = 'none';
                btn.title = 'Please complete your delivery address to continue';
                return;
            }
            const zoneRes = evaluateDeliveryZone(cleanPin);
            if (!zoneRes || !zoneRes.allowOrder) {
                btn.disabled = true;
                btn.style.opacity = '0.5';
                btn.style.pointerEvents = 'none';
                btn.title = (zoneRes && zoneRes.message) || 'Pincode is outside our delivery area';
                return;
            }
        }

        btn.disabled = false;
        btn.style.opacity = '';
        btn.style.pointerEvents = '';
        btn.title = '';
    }

    function escHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    // ── Pincode Validation Helper ─────────────────────────────────────────────
    /**
     * Validates the pincode input element, updates all related DOM feedback
     * (error class on input, status badge, inline error message), and returns
     * the result. Centralises logic that was previously duplicated in the
     * Save Address handler and validateAddress().
     *
     * @param {HTMLElement|null} pincodeInput
     * @returns {{ isValid: boolean, cleanPin: string, zoneRes: object|null }}
     */
    function runPincodeValidation(pincodeInput) {
        const pincodeVal = pincodeInput ? pincodeInput.value.trim() : '';
        const cleanPin = pincodeVal.replace(/\D/g, '');
        const zoneRes = evaluateDeliveryZone(cleanPin);
        const isValid = Boolean(cleanPin && cleanPin.length === 6 && zoneRes && zoneRes.allowOrder);

        if (pincodeInput) {
            pincodeInput.classList.toggle('swac-input-error', !isValid);
        }

        const statusEl = document.getElementById('swac-pincode-status');
        if (statusEl) {
            if (cleanPin.length === 6 && zoneRes) {
                statusEl.className = 'swac-pincode-status ' + zoneRes.statusClass;
                statusEl.textContent = zoneRes.message;
                statusEl.style.display = 'flex';
            } else {
                statusEl.style.display = 'none';
                statusEl.className = 'swac-pincode-status';
                statusEl.textContent = '';
            }
        }

        const errEl = document.getElementById('swac-addr-pincode-err');
        if (errEl) {
            if (!isValid) {
                errEl.textContent = (zoneRes && !zoneRes.allowOrder)
                    ? zoneRes.message
                    : 'Please enter a valid postal code';
                errEl.style.display = 'block';
            } else {
                errEl.style.display = 'none';
            }
        }

        return { isValid, cleanPin, zoneRes };
    }

    // ── Form Validation ──────────────────────────────────────────────────────
    function validateAddress() {
        if (addressMode === 'preview') {
            const saved = getCustomerDetails();
            if (saved && saved.name && saved.address && saved.pincode) {
                const zoneRes = evaluateDeliveryZone(saved.pincode);
                if (zoneRes && zoneRes.allowOrder) {
                    return saved;
                }
            }
            addressMode = 'edit';
            addressCollapsed = false;
            renderAddressSection(true);
        } else if (addressCollapsed) {
            addressCollapsed = false;
            renderAddressSection(true);
        }

        const nameInput = document.getElementById('swac-addr-name');
        const addrInput = document.getElementById('swac-addr-address');
        const landmarkInput = document.getElementById('swac-addr-landmark');
        const areaInput = document.getElementById('swac-addr-area');
        const pincodeInput = document.getElementById('swac-addr-pincode');
        const saveCheck = document.getElementById('swac-addr-save-default');

        let isValid = true;
        let firstInvalid = null;

        const nameVal = nameInput ? nameInput.value.trim() : '';
        const addrVal = addrInput ? addrInput.value.trim() : '';
        const landmarkVal = landmarkInput ? landmarkInput.value.trim() : '';
        const areaVal = areaInput ? areaInput.value.trim() : '';
        const pincodeVal = pincodeInput ? pincodeInput.value.trim() : '';
        const shouldSave = saveCheck ? saveCheck.checked : true;

        if (!nameVal) {
            isValid = false;
            if (nameInput) {
                nameInput.classList.add('swac-input-error');
                const errEl = document.getElementById('swac-addr-name-err');
                if (errEl) {
                    errEl.textContent = 'Please enter your full name';
                    errEl.style.display = 'block';
                }
                if (!firstInvalid) firstInvalid = nameInput;
            }
        }

        if (!addrVal) {
            isValid = false;
            if (addrInput) {
                addrInput.classList.add('swac-input-error');
                const errEl = document.getElementById('swac-addr-address-err');
                if (errEl) {
                    errEl.textContent = 'Please enter your delivery address';
                    errEl.style.display = 'block';
                }
                if (!firstInvalid) firstInvalid = addrInput;
            }
        }

        const { isValid: pinIsValid, cleanPin } = runPincodeValidation(pincodeInput);
        if (!pinIsValid) {
            isValid = false;
            if (!firstInvalid) firstInvalid = pincodeInput;
        }

        if (!isValid) {
            if (firstInvalid) {
                firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
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
            addressDraft = { ...details, saveDefault: true };
            addressMode = 'preview';
            renderAddressSection(true);
        } else {
            addressDraft = { ...details, saveDefault: false };
        }

        return details;
    }

    // ── Order Submission ─────────────────────────────────────────────────────
    function buildOrderMessage(cart, details) {
        const loy = CONFIG.loyalty;

        const lines = cart.map((item, idx) => {
            const rxTag = item.isRx ? '[Rx] ' : '';
            let line = `${idx + 1}. ${rxTag}*${item.name}*`;
            if (item.qty > 1) {
                line += ` — Qty: ${item.qty}`;
            }
            const unitPriceNum = parseFloat(item.salePrice) || 0;
            if (unitPriceNum > 0) {
                if (item.qty > 1) {
                    line += `\n   Price: ${fmtInr(unitPriceNum)} \u00d7 ${item.qty} = ${fmtInr(unitPriceNum * item.qty)}`;
                } else {
                    line += `\n   Price: ${fmtInr(unitPriceNum)}`;
                }
            } else if (item.price) {
                line += `\n   Price: ${item.price}${item.qty > 1 ? ' \u00d7 ' + item.qty : ''}`;
            }
            return line;
        });

        const { totalMrp, saleTotal, youSave, savePct, shipping, amountPayable } = computeCartTotals(cart, details ? details.pincode : undefined);
        const shipLabel = shipping === 0 ? 'FREE' : fmtInr(shipping);
        const loyaltyActive = isLoyaltyActive(saleTotal);

        const sep = '\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500';

        let msg = `Hello ${CONFIG.name}, I'd like to order:\n\n`
            + lines.join('\n\n')
            + `\n\n${sep}`
            + `\nTotal MRP:      ${fmtInr(totalMrp)}`
            + `\nDiscount:       ${fmtInr(youSave)} (${savePct}% off)`
            + `\nShipping:       ${shipLabel}`
            + `\nTo Pay:         ${fmtInr(amountPayable)}`
            + `\n${sep}`;

        if (loyaltyChoice === 'yes' && loyaltyActive) {
            msg += `\n\n\uD83C\uDF81  ${loy.itemLabel} (worth ${fmtInr(loy.itemWorth)}) \u2192 ${fmtInr(loy.itemPrice)} added to order`
                + `\n${loy.flag}`
                + `\n${sep}`;
        }

        const deliveryEstimate = getDeliveryEstimate();
        if (deliveryEstimate) {
            msg += `\n\n*Estimated Delivery:* ${deliveryEstimate}`;
        }

        if (details) {
            // Strip CR/LF from user-entered fields: a newline mid-field would break
            // the WhatsApp message structure (each line is a labelled record).
            const safeStr = (val) => (val || '').replace(/[\r\n]+/g, ' ').trim();
            msg += `\n\n*Delivery Details:*`
                + `\nName: ${safeStr(details.name)}`
                + `\nAddress: ${safeStr(details.address)}`;
            if (details.landmark) {
                msg += `\nLandmark: ${safeStr(details.landmark)}`;
            }
            if (details.area) {
                msg += `\nArea: ${safeStr(details.area)}`;
            }
            if (details.pincode) {
                msg += `\nPostal Code: ${details.pincode}` + (CONFIG.cityName ? `, ${CONFIG.cityName}` : '');
            } else if (CONFIG.cityName) {
                msg += `\nCity: ${CONFIG.cityName}`;
            }
            msg += `\n${sep}`;
        }

        const hasRx = cart.some(item => Boolean(item.isRx));
        if (CONFIG.prescriptionNoteEnabled && CONFIG.prescriptionNote && hasRx) {
            msg += `\n\n${CONFIG.prescriptionNote}`;
        }

        msg += `\n\nPlease confirm my order.`;

        return msg;
    }

    function sendOrderToWhatsApp(e) {
        if (e && typeof e.preventDefault === 'function') {
            e.preventDefault();
        }
        const cart = getCart();
        if (cart.length === 0) return;

        let baseUrl = CONFIG.baseUrl;
        if (!baseUrl && CONFIG.number) {
            baseUrl = 'https://api.whatsapp.com/send?phone=' + encodeURIComponent(String(CONFIG.number).replace(/\D/g, ''));
        }

        if (!baseUrl) {
            showToast('WhatsApp ordering is not configured yet (missing phone number).');
            return;
        }

        const deliveryDetails = validateAddress();
        if (!deliveryDetails) {
            return;
        }

        const message = buildOrderMessage(cart, deliveryDetails);
        const sep = baseUrl.includes('?') ? '&' : '?';
        const url = `${baseUrl}${sep}text=${encodeURIComponent(message)}`;
        window.open(url, '_blank', 'noopener,noreferrer');
    }

    // Direct single-product WhatsApp button handler removed.

    // ── Cart Interception ────────────────────────────────────────────────────
    function interceptAddToCart() {
        const form = document.querySelector('form.cart');
        if (!form) return;

        const qtyInput = form.querySelector('input[name="quantity"], .qty');
        if (qtyInput) {
            qtyInput.setAttribute('max', String(CONFIG.maxQtyPerItem));
            qtyInput.addEventListener('change', function () {
                if (parseInt(this.value, 10) > CONFIG.maxQtyPerItem) {
                    this.value = CONFIG.maxQtyPerItem;
                    showToast(`Maximum ${CONFIG.maxQtyPerItem} units allowed per item.`);
                }
            });
        }

        let isProcessing = false;

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            e.stopImmediatePropagation();

            if (isProcessing) return;
            isProcessing = true;

            try {
                const dataEl = form.querySelector('#swac-product-data, .swac-product-data') || document.getElementById('swac-product-data');
                let item;

                if (dataEl) {
                    item = {
                        id: dataEl.dataset.productId || Date.now(),
                        name: dataEl.dataset.productName || 'Product',
                        url: dataEl.dataset.productUrl || window.location.href,
                        price: dataEl.dataset.productPrice || '',
                        mrp: parseFloat(dataEl.dataset.productMrp) || 0,
                        salePrice: parseFloat(dataEl.dataset.productSalePrice) || 0,
                        qty: getSelectedQty(),
                        isRx: dataEl.dataset.isRx === '1',
                    };
                } else {
                    const btnVal = form.querySelector('[name="add-to-cart"]')?.value || form.querySelector('.single_add_to_cart_button')?.value;
                    const titleEl = document.querySelector('.product_title, h1');
                    const title = titleEl ? titleEl.textContent.trim() : 'Product';
                    item = {
                        id: btnVal || Date.now(),
                        name: title,
                        url: window.location.href,
                        price: '',
                        mrp: 0,
                        salePrice: 0,
                        qty: getSelectedQty(),
                        isRx: false,
                    };
                }

                const submitBtn = form.querySelector('.single_add_to_cart_button') || form.querySelector('button[type="submit"]');
                addToCart(item, submitBtn);
            } finally {
                // Always clear the lock — even if saveCart throws a QuotaExceededError.
                setTimeout(() => {
                    isProcessing = false;
                }, 300);
            }
        });
    }

    function getSelectedQty() {
        const qtyInput = document.querySelector('form.cart .qty, form.cart input[name="quantity"]');
        if (qtyInput) {
            const parsed = parseInt(qtyInput.value, 10);
            if (!isNaN(parsed) && parsed > 0) {
                if (parsed > CONFIG.maxQtyPerItem) {
                    qtyInput.value = CONFIG.maxQtyPerItem;
                    showToast(`Maximum ${CONFIG.maxQtyPerItem} units allowed per item.`);
                    return CONFIG.maxQtyPerItem;
                }
                return parsed;
            }
        }
        return 1;
    }

    // WeakMaps avoid expando properties on DOM nodes, preventing naming
    // collisions with future browser-native properties.
    const _feedbackTimers = new WeakMap();
    const _feedbackOrigHtml = new WeakMap();

    function showAddedFeedback(btn) {
        const addBtn = btn || document.querySelector('form.cart .single_add_to_cart_button');
        if (!addBtn) return;

        if (_feedbackTimers.has(addBtn)) {
            clearTimeout(_feedbackTimers.get(addBtn));
        } else {
            _feedbackOrigHtml.set(addBtn, addBtn.innerHTML);
        }

        addBtn.textContent = 'Added to Cart';
        addBtn.classList.add('swac-btn-added');
        _feedbackTimers.set(addBtn, setTimeout(() => {
            const origHtml = _feedbackOrigHtml.get(addBtn);
            if (origHtml) {
                addBtn.innerHTML = origHtml;
                _feedbackOrigHtml.delete(addBtn);
            }
            addBtn.classList.remove('swac-btn-added');
            _feedbackTimers.delete(addBtn);
        }, 1800));
    }

    // ── Event Bindings ───────────────────────────────────────────────────────
    function bindDrawerEvents() {
        document.getElementById('swac-cart-bubble')?.addEventListener('click', openDrawer);
        document.getElementById('swac-drawer-close')?.addEventListener('click', closeDrawer);
        document.getElementById('swac-drawer-overlay')?.addEventListener('click', closeDrawer);
        document.getElementById('swac-send-order')?.addEventListener('click', sendOrderToWhatsApp);
        document.getElementById('swac-clear-cart')?.addEventListener('click', showClearConfirm);
        document.getElementById('swac-clear-cancel')?.addEventListener('click', hideClearConfirm);
        document.getElementById('swac-clear-proceed')?.addEventListener('click', function () {
            hideClearConfirm();
            clearCart();
        });

        // Close on Escape key press and maintain keyboard Tab focus trap inside modal drawer
        document.addEventListener('keydown', function (e) {
            const drawer = document.getElementById('swac-drawer');
            const isOpen = drawer && drawer.classList.contains('swac-drawer--open');
            if (!isOpen) return;

            if (e.key === 'Escape') {
                closeDrawer();
                _bubble?.focus();
                return;
            }

            if (e.key === 'Tab') {
                const focusables = Array.from(drawer.querySelectorAll(
                    'button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
                )).filter(el => el.offsetParent !== null || window.getComputedStyle(el).display !== 'none');

                if (focusables.length === 0) {
                    e.preventDefault();
                    return;
                }

                const firstEl = focusables[0];
                const lastEl = focusables[focusables.length - 1];

                if (e.shiftKey) {
                    if (document.activeElement === firstEl || !drawer.contains(document.activeElement)) {
                        e.preventDefault();
                        lastEl.focus();
                    }
                } else {
                    if (document.activeElement === lastEl || !drawer.contains(document.activeElement)) {
                        e.preventDefault();
                        firstEl.focus();
                    }
                }
            }
        });

        const drawerBody = document.getElementById('swac-drawer-body');
        drawerBody?.addEventListener('click', function (e) {
            const btn = e.target.closest('button[data-id]');
            if (!btn) return;

            const id = btn.dataset.id;
            const cart = getCart();
            const item = cart.find(i => String(i.id) === String(id));
            if (!item) return;

            if (btn.classList.contains('swac-qty-plus')) {
                if (item.qty >= CONFIG.maxQtyPerItem) {
                    showToast(`Maximum ${CONFIG.maxQtyPerItem} units allowed per item for retail orders.`);
                    return;
                }
                item.qty++;
                saveCart(cart);
                renderDrawerItems();
            } else if (btn.classList.contains('swac-qty-minus')) {
                if (item.qty > 1) {
                    item.qty--;
                    saveCart(cart);
                    renderDrawerItems();
                } else {
                    removeFromCart(id);
                }
            } else if (btn.classList.contains('swac-remove-btn')) {
                removeFromCart(id);
            }
        });
    }

    function bindLoopButtons() {
        document.addEventListener('click', function (e) {
            const btn = e.target.closest('.swac-loop-add');
            if (!btn) return;
            e.preventDefault();

            const item = {
                id: btn.dataset.productId || btn.href,
                name: btn.dataset.productName || 'Product',
                url: btn.dataset.productUrl || btn.href,
                price: btn.dataset.productPrice || '',
                mrp: parseFloat(btn.dataset.productMrp) || 0,
                salePrice: parseFloat(btn.dataset.productSalePrice) || 0,
                qty: 1,
                isRx: btn.dataset.isRx === '1',
            };

            addToCart(item, btn);
        });
    }

    function bindActionRowButtons() {
        document.addEventListener('click', function (e) {
            // 1. Quantity Plus / Minus inside .swac-qty-selector
            const qtyBtn = e.target.closest('.swac-qty-selector .swac-qty-btn');
            if (qtyBtn) {
                e.preventDefault();
                const selector = qtyBtn.closest('.swac-qty-selector');
                const input = selector ? selector.querySelector('.swac-qty-input') : null;
                if (!input) return;

                let val = parseInt(input.value, 10) || 1;
                if (qtyBtn.classList.contains('swac-qty-plus')) {
                    if (val >= CONFIG.maxQtyPerItem) {
                        showToast(`Maximum ${CONFIG.maxQtyPerItem} units allowed per item.`);
                        return;
                    }
                    val++;
                } else if (qtyBtn.classList.contains('swac-qty-minus')) {
                    if (val > 1) {
                        val--;
                    }
                }
                input.value = val;
                return;
            }

            // 2. Add to Order Click on .swac-single-add-btn
            const addBtn = e.target.closest('.swac-single-add-btn');
            if (addBtn) {
                e.preventDefault();
                const actionRow = addBtn.closest('.swac-action-row');
                const qtyInput = actionRow ? actionRow.querySelector('.swac-qty-input') : null;
                let qty = qtyInput ? (parseInt(qtyInput.value, 10) || 1) : 1;
                if (qty > CONFIG.maxQtyPerItem) {
                    qty = CONFIG.maxQtyPerItem;
                    if (qtyInput) qtyInput.value = qty;
                    showToast(`Maximum ${CONFIG.maxQtyPerItem} units allowed per item.`);
                }

                const dataEl = document.getElementById('swac-product-data');
                const titleEl = document.querySelector('.product_title, h1');
                const defaultTitle = titleEl ? titleEl.textContent.trim() : 'Product';

                const item = {
                    id: addBtn.dataset.productId || dataEl?.dataset.productId || Date.now(),
                    name: addBtn.dataset.productName || dataEl?.dataset.productName || defaultTitle,
                    url: addBtn.dataset.productUrl || dataEl?.dataset.productUrl || window.location.href,
                    price: addBtn.dataset.productPrice || dataEl?.dataset.productPrice || '',
                    mrp: parseFloat(addBtn.dataset.productMrp) || parseFloat(dataEl?.dataset.productMrp) || 0,
                    salePrice: parseFloat(addBtn.dataset.productSalePrice) || parseFloat(dataEl?.dataset.productSalePrice) || 0,
                    qty: qty,
                    isRx: (addBtn.dataset.isRx === '1') || (dataEl?.dataset.isRx === '1'),
                };

                addToCart(item, addBtn);
            }
        });

        // 3. Clamp quantity inputs on manual typing
        document.addEventListener('change', function (e) {
            const input = e.target.closest('.swac-qty-selector .swac-qty-input');
            if (input) {
                let val = parseInt(input.value, 10);
                if (isNaN(val) || val < 1) {
                    val = 1;
                } else if (val > CONFIG.maxQtyPerItem) {
                    val = CONFIG.maxQtyPerItem;
                    showToast(`Maximum ${CONFIG.maxQtyPerItem} units allowed per item.`);
                }
                input.value = val;
            }
        });
    }

    // ── Catalog Ordering: Auto-Submit on Selection / Click ───────────────────
    // Custom builders and stripped WooCommerce templates lack the auto-submit JS
    // for .woocommerce-ordering. This ensures selecting an option reloads the page.
    function bindCatalogOrdering() {
        document.addEventListener('change', function (e) {
            const target = e.target;
            if (target && (target.matches('.woocommerce-ordering select.orderby') || target.matches('form.woocommerce-ordering select') || target.matches('select[name="orderby"]') || target.classList.contains('orderby'))) {
                const form = target.closest('form');
                if (form) {
                    form.submit();
                }
            }
        });

        document.addEventListener('click', function (e) {
            const btn = e.target.closest('.woocommerce-ordering button, .woocommerce-ordering input[type="submit"]');
            if (btn) {
                const form = btn.closest('form');
                if (form) {
                    form.submit();
                }
            }
        });
    }

    // ── Delivery Cutoff Notice: Client-Side Hydration (Cache-Busting) ─────────
    // Reconciles static HTML with active store timezone to prevent stale notices
    // when pages are served from static full-page cache across cutoff boundaries.
    function getKolkataFormattedDate(baseIstDate, daysAhead) {
        const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        const target = new Date(baseIstDate.getTime() + (daysAhead * 24 * 60 * 60 * 1000));
        return `${target.getUTCDate()} ${months[target.getUTCMonth()]}`;
    }

    function getKolkataDeliveryCutoffMessage(customNow) {
        const now = (customNow instanceof Date) ? customNow : new Date();
        const offsetHours = (typeof CONFIG.timezoneOffset === 'number') ? CONFIG.timezoneOffset : 0;
        const istDate = new Date(now.getTime() + (offsetHours * 60 * 60 * 1000));
        const day = istDate.getUTCDay(); // 0 = Sun, 1 = Mon, ..., 6 = Sat
        const hour = istDate.getUTCHours(); // 0 to 23

        const msgs = CONFIG.cutoffMessages || {};
        const msgMorning = msgs.morning || 'Order before 12 noon for same-day delivery';

        // 1. Weekend Window: Saturday 12:00 PM to Sunday 11:59:59 PM
        if (day === 0 || (day === 6 && hour >= 12)) {
            return `Order now to get it by Monday (${getKolkataFormattedDate(istDate, day === 0 ? 1 : 2)})`;
        }
        // 2. Morning Cutoff Window: Monday through Saturday, 12:00 AM to 11:59:59 AM
        if (hour < 12) {
            return msgMorning;
        }
        // 3. Weekday Afternoon/Evening: Monday through Friday, 12:00 PM to 11:59:59 PM
        return `Order now to receive it by tomorrow (${getKolkataFormattedDate(istDate, 1)})`;
    }

    function getDeliveryCutoffMessage(customNow) {
        return getKolkataDeliveryCutoffMessage(customNow);
    }

    function getKolkataDeliveryEstimate(customNow) {
        const now = (customNow instanceof Date) ? customNow : new Date();
        const offsetHours = (typeof CONFIG.timezoneOffset === 'number') ? CONFIG.timezoneOffset : 0;
        const istDate = new Date(now.getTime() + (offsetHours * 60 * 60 * 1000));
        const day = istDate.getUTCDay();
        const hour = istDate.getUTCHours();

        if (day === 0 || (day === 6 && hour >= 12)) {
            return `Monday (${getKolkataFormattedDate(istDate, day === 0 ? 1 : 2)})`;
        }
        if (hour < 12) {
            return 'Today (Same-day)';
        }
        return `Tomorrow (${getKolkataFormattedDate(istDate, 1)})`;
    }

    function getDeliveryEstimate(customNow) {
        return getKolkataDeliveryEstimate(customNow);
    }

    function hydrateDeliveryNotice() {
        const message = getDeliveryCutoffMessage();
        if (!message) return;

        // 1. Single Product Page Notice
        const productTextEl = document.querySelector('.swac-delivery-notice .swac-delivery-text');
        if (productTextEl && productTextEl.textContent.trim() !== message) {
            productTextEl.textContent = message;
        }

        // 2. Slide-out Order Drawer Notice
        const drawerTextEl = document.querySelector('.swac-drawer-cutoff-text');
        if (drawerTextEl && drawerTextEl.textContent.trim() !== message) {
            drawerTextEl.textContent = message;
        }
    }

    // ── Initialization (Browser only) ──────────────────────────────────────────
    // NOTE: This script is enqueued in the footer (in_footer=true), so the DOM
    // is already parsed when this IIFE runs. Do NOT wrap in DOMContentLoaded —
    // it would never fire because the event has already dispatched.
    if (typeof window !== 'undefined' && typeof document !== 'undefined') {
        initAddressState();
        refreshUI();
        interceptAddToCart();
        bindDrawerEvents();
        bindLoopButtons();
        bindCatalogOrdering();
        hydrateDeliveryNotice();
    }

    // ── CommonJS Export Parity (Automated Testing & Subsystems) ───────────────
    if (typeof module !== 'undefined' && module.exports) {
        module.exports = {
            computeCartTotals,
            evaluateDeliveryZone,
            isAlwaysFreePincode,
            isLoyaltyActive,
            getKolkataDeliveryCutoffMessage,
            getDeliveryCutoffMessage,
            getKolkataDeliveryEstimate,
            getDeliveryEstimate,
            getKolkataFormattedDate,
            buildOrderMessage,
            getZoneSets,
            CONFIG,
            setRuntimeConfig: function (cfg) {
                Object.assign(CONFIG, cfg);
            },
            setZonesData: function (data) {
                safeWindow.swacZones = data;
                sameDaySet = null;
                outskirtsSet = null;
                alwaysFreeSet = null;
            }
        };
    }

})();