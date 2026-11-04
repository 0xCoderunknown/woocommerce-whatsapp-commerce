(function () {
    'use strict';

    /**
     * 🧭 STATELESS WHATSAPP COMMERCE: CORE CART ENGINE & ORCHESTRATOR
     * -------------------------------------------------------------------------
     * § 01. Submodule Resolvers       : getDeliveryEngine(), getCartMath(), getOrderSerializer(), getDrawerUI()
     * § 02. Customer Metadata Storage : getCustomerDetails(), saveCustomerDetails()
     * § 03. Delivery Zones & Gates    : evaluateDeliveryZone(), isAlwaysFreePincode(), getZoneSets()
     * § 04. Configuration & Bridge    : CONFIG (window.swacCommerce), fmtPrice, showToast
     * § 05. Cart LocalStorage CRUD    : getCart(), saveCart(), addToCart(), removeFromCart(), clearCart()
     * § 06. Floating Bubble & Counter : refreshUI(), openDrawer(), closeDrawer()
     * § 07. Cart Math & Calculations  : computeCartTotals(), isLoyaltyActive()
     * § 08. Order Builder & Dispatch  : buildOrderMessage(), sendOrderToWhatsApp()
     * § 09. Add-to-Cart Interception  : interceptAddToCart(), bindLoopButtons(), bindActionRowButtons()
     * § 10. Catalog Ordering Auto-Sub : bindCatalogOrdering()
     * § 11. Delivery Cutoff Hydration : hydrateDeliveryNotice(), getDeliveryCutoffMessage()
     * § 12. Initialization & Exports  : window.swacCart, module.exports parity
     * -------------------------------------------------------------------------
     */

    const safeWindow = (typeof window !== 'undefined') ? window : (typeof globalThis !== 'undefined' ? globalThis : {});
    const safeDoc = (typeof document !== 'undefined') ? document : null;
    const safeStorage = (function () {
        try {
            if (typeof localStorage !== 'undefined') {
                return localStorage;
            }
        } catch (e) {
        }
        const memoryStore = {};
        return {
            getItem: function (k) {
                return Object.prototype.hasOwnProperty.call(memoryStore, k) ? memoryStore[k] : null;
            },
            setItem: function (k, v) {
                memoryStore[k] = String(v);
            },
            removeItem: function (k) {
                delete memoryStore[k];
            },
            clear: function () {
                Object.keys(memoryStore).forEach(function (k) {
                    delete memoryStore[k];
                });
            }
        };
    })();

    const STORAGE_KEY = 'swac_cart';
    const CUSTOMER_STORAGE_KEY = 'swac_customer_details';
    const RETAILER_STORAGE_KEY = 'swac_b2b_retailer';

    // ── Submodule Resolvers (Zero-build modular architecture) ─────────────────
    function getDeliveryEngine() {
        if (typeof window !== 'undefined' && window.swacDelivery) {
            return window.swacDelivery;
        }
        if (typeof require === 'function') {
            try { return require('./delivery-engine.js'); } catch (e) {}
        }
        return null;
    }

    function getCartMath() {
        if (typeof window !== 'undefined' && window.swacCartMath) {
            return window.swacCartMath;
        }
        if (typeof require === 'function') {
            try { return require('./cart-math.js'); } catch (e) {}
        }
        return null;
    }

    function getOrderSerializer() {
        if (typeof window !== 'undefined' && window.swacOrderSerializer) {
            return window.swacOrderSerializer;
        }
        if (typeof require === 'function') {
            try { return require('./order-serializer.js'); } catch (e) {}
        }
        return null;
    }

    function getDrawerUI() {
        if (typeof window !== 'undefined' && window.swacDrawer) {
            return window.swacDrawer;
        }
        if (typeof require === 'function') {
            try { return require('./drawer-ui.js'); } catch (e) {}
        }
        return null;
    }

    // ── Persistent Customer / Delivery Metadata ──────────────────────────────
    function getCustomerDetails() {
        try {
            const raw = safeStorage.getItem(CUSTOMER_STORAGE_KEY);
            let details = null;
            if (raw) {
                const data = JSON.parse(raw);
                if (data && typeof data === 'object' && !Array.isArray(data)) {
                    details = {
                        name: (data.name || '').trim(),
                        address: (data.address || '').trim(),
                        landmark: (data.landmark || '').trim(),
                        area: (data.area || '').trim(),
                        pincode: (data.pincode || '').trim()
                    };
                }
            }

            // Fallback / enrich with authenticated B2B retailer identity
            const retailer = getSavedRetailer();
            if (retailer && retailer.store_name) {
                if (!details) {
                    details = {name: '', address: '', landmark: '', area: '', pincode: ''};
                }
                if (!details.name) {
                    details.name = retailer.store_name + (retailer.owner_name ? ' (' + retailer.owner_name + ')' : '');
                }
                if (!details.area && retailer.area) {
                    details.area = retailer.area;
                }
            }

            return details;
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

    // ── Delivery Service Zones & Pincode Gate (Delegated to delivery-engine.js) ──
    function getZoneSets() {
        const engine = getDeliveryEngine();
        return engine ? engine.getZoneSets(safeWindow.swacZones, typeof CONFIG !== 'undefined' ? CONFIG : safeWindow.swacCommerce) : {sameDaySet: new Set(), outskirtsSet: new Set(), alwaysFreeSet: new Set()};
    }

    function isAlwaysFreePincode(pincode) {
        const engine = getDeliveryEngine();
        return engine ? engine.isAlwaysFreePincode(pincode, safeWindow.swacZones, typeof CONFIG !== 'undefined' ? CONFIG : safeWindow.swacCommerce) : false;
    }

    function evaluateDeliveryZone(pincode) {
        const engine = getDeliveryEngine();
        return engine ? engine.evaluateDeliveryZone(pincode, safeWindow.swacZones, typeof CONFIG !== 'undefined' ? CONFIG : safeWindow.swacCommerce) : null;
    }

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

    const fmtPrice = n => (CONFIG.currencySymbol ? CONFIG.currencySymbol + ' ' : '') + Number(n).toLocaleString(undefined, {
        minimumFractionDigits: 0,
        maximumFractionDigits: 2
    });
    const fmtInr = fmtPrice;

    function escHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function isLoyaltyActive(saleTotal) {
        const math = getCartMath();
        return math ? math.isLoyaltyActive(saleTotal, CONFIG) : false;
    }

    let _toastTimer = null;
    safeWindow.swacShowToast = showToast;

    function showToast(msg) {
        if (!safeDoc) return;
        let toast = safeDoc.getElementById('swac-toast');
        if (!toast) {
            toast = safeDoc.createElement('div');
            toast.id = 'swac-toast';
            toast.className = 'swac-toast';
            toast.setAttribute('role', 'status');
            toast.setAttribute('aria-live', 'polite');
            safeDoc.body.appendChild(toast);
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
                updatedAt: Date.now(),
                items: cart
            };
            safeStorage.setItem(STORAGE_KEY, JSON.stringify(payload));
        } catch (e) {
        }
    }

    function addToCart(item, btn) {
        const cart = getCart();
        const existing = cart.find(i => String(i.id) === String(item.id));
        const currentTotalUnits = cart.reduce((sum, i) => sum + (parseInt(i.qty, 10) || 1), 0);
        const addQty = parseInt(item.qty, 10) || 1;

        if (!existing && cart.length >= CONFIG.maxCartItems) {
            showToast(`Order limit reached! You can order up to ${CONFIG.maxCartItems} distinct products per WhatsApp order.`);
            return;
        }

        if (existing) {
            const nextItemQty = existing.qty + addQty;
            if (nextItemQty > CONFIG.maxQtyPerItem) {
                showToast(`Maximum ${CONFIG.maxQtyPerItem} units allowed per item for retail orders.`);
                return;
            }
            existing.qty = nextItemQty;
            existing.price = item.price || existing.price;
            existing.mrp = item.mrp || existing.mrp;
            existing.salePrice = item.salePrice || existing.salePrice;
            existing.cartonPack = item.cartonPack || existing.cartonPack;
            existing.schemeBadge = item.schemeBadge || existing.schemeBadge;
        } else {
            if (addQty > CONFIG.maxQtyPerItem) {
                showToast(`Maximum ${CONFIG.maxQtyPerItem} units allowed per item for retail orders.`);
                return;
            }
            cart.push(item);
        }

        saveCart(cart);
        refreshUI();

        if (btn) {
            showAddedFeedback(btn);
        }

        openDrawer();
    }

    function removeFromCart(productId) {
        let cart = getCart();
        cart = cart.filter(i => String(i.id) !== String(productId));
        saveCart(cart);
        refreshUI();
        getDrawerUI()?.renderDrawerItems();
    }

    function clearCart() {
        saveCart([]);
        refreshUI();
        getDrawerUI()?.renderDrawerItems();
    }

    // ── UI Updates & Drawer Coordination ─────────────────────────────────────
    function refreshUI() {
        if (!safeDoc) return;
        const _bubble = safeDoc.getElementById('swac-cart-bubble');
        const _counter = safeDoc.getElementById('swac-cart-count');
        const cart = getCart();
        const count = cart.reduce((sum, item) => sum + (parseInt(item.qty, 10) || 1), 0);

        if (_counter) _counter.textContent = count;
        if (_bubble) {
            if (count > 0) {
                _bubble.classList.add('swac-cart-bubble--visible');
            } else {
                _bubble.classList.remove('swac-cart-bubble--visible');
            }
        }

        const drawer = safeDoc.getElementById('swac-drawer');
        if (drawer && drawer.classList.contains('swac-drawer--open')) {
            getDrawerUI()?.renderDrawerItems();
        }
    }

    function openDrawer() {
        getDrawerUI()?.openDrawer();
    }

    function closeDrawer() {
        getDrawerUI()?.closeDrawer();
    }

    function getCurrentPincode() {
        return getDrawerUI()?.getCurrentPincode() || '';
    }

    function validateAddress() {
        return getDrawerUI()?.validateAddress() || getCustomerDetails();
    }

    // ── Calculations (Delegated to cart-math.js) ──────────────────────────────
    function computeCartTotals(cart, pincode) {
        const math = getCartMath();
        const code = pincode !== undefined ? pincode : getCurrentPincode();
        const loyaltyChoice = getDrawerUI()?.getLoyaltyChoice() || null;
        if (math && typeof math.computeCartTotals === 'function') {
            return math.computeCartTotals(cart, code, {
                config: CONFIG,
                isAlwaysFreePincode: isAlwaysFreePincode,
                loyaltyChoice: loyaltyChoice,
            });
        }
        return {totalMrp: 0, saleTotal: 0, youSave: 0, savePct: 0, shipping: 0, amountPayable: 0, isAlwaysFree: false};
    }

    // ── Order Submission (Delegated to order-serializer.js) ───────────────────
    function buildOrderMessage(cart, details) {
        const serializer = getOrderSerializer();
        const loyaltyChoice = getDrawerUI()?.getLoyaltyChoice() || null;
        if (serializer && typeof serializer.buildOrderMessage === 'function') {
            return serializer.buildOrderMessage(cart, details, {
                config: CONFIG,
                formatPrice: fmtInr,
                computeTotals: computeCartTotals,
                isLoyaltyActive: isLoyaltyActive,
                loyaltyChoice: loyaltyChoice,
                getDeliveryEstimate: getDeliveryEstimate,
                getSavedRetailer: getSavedRetailer,
            });
        }
        return '';
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
        const serializer = getOrderSerializer();
        const url = (serializer && typeof serializer.buildWhatsAppOrderUrl === 'function')
            ? serializer.buildWhatsAppOrderUrl(baseUrl, message)
            : `${baseUrl}${baseUrl.includes('?') ? '&' : '?'}text=${encodeURIComponent(message)}`;
        window.open(url, '_blank', 'noopener,noreferrer');
    }

    // ── Cart Interception & Product Page Buttons ──────────────────────────────
    function interceptAddToCart() {
        if (!safeDoc) return;
        const form = safeDoc.querySelector('form.cart');
        if (!form) return;

        const defaultBtn = form.querySelector('button[type="submit"][name="add-to-cart"], button.single_add_to_cart_button');
        if (!defaultBtn) return;

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            e.stopPropagation();

            const nameEl = safeDoc.querySelector('.product_title, h1.entry-title');
            const priceEl = safeDoc.querySelector('.woocommerce-Price-amount, .price');
            const qty = getSelectedQty();

            const hiddenId = form.querySelector('input[name="add-to-cart"]');
            const btnVal = defaultBtn.value;
            const fallbackId = (hiddenId ? hiddenId.value : '') || btnVal || window.location.pathname;

            let priceFormatted = '';
            let unitPrice = 0;
            if (priceEl) {
                const insEl = priceEl.querySelector('ins .woocommerce-Price-amount') || priceEl;
                priceFormatted = insEl.textContent.trim();
                const num = parseFloat(priceFormatted.replace(/[^0-9.]/g, ''));
                if (!isNaN(num)) unitPrice = num;
            }

            const delivEl = safeDoc.querySelector('.swac-action-row');
            const mrpRaw = delivEl ? delivEl.dataset.productMrp : null;
            const salePriceRaw = delivEl ? delivEl.dataset.productSalePrice : null;
            const cartonPackRaw = delivEl ? delivEl.dataset.cartonPack : null;
            const schemeBadgeRaw = delivEl ? delivEl.dataset.schemeBadge : null;
            const isRxRaw = delivEl ? delivEl.dataset.isRx : null;

            const mrp = mrpRaw ? parseFloat(mrpRaw) : unitPrice;
            const salePrice = salePriceRaw ? parseFloat(salePriceRaw) : unitPrice;
            const cartonPack = cartonPackRaw ? parseInt(cartonPackRaw, 10) : 0;
            const schemeBadge = schemeBadgeRaw || '';
            const isRx = isRxRaw === '1';

            const item = {
                id: fallbackId,
                name: nameEl ? nameEl.textContent.trim() : 'Product',
                url: window.location.href,
                price: priceFormatted,
                mrp: mrp,
                salePrice: salePrice,
                qty: qty,
                cartonPack: cartonPack,
                schemeBadge: schemeBadge,
                isRx: isRx,
            };

            addToCart(item, defaultBtn);
        });
    }

    function getSelectedQty() {
        if (!safeDoc) return 1;
        const input = safeDoc.querySelector('form.cart input[name="quantity"]');
        if (!input) return 1;
        const val = parseInt(input.value, 10);
        return isNaN(val) || val < 1 ? 1 : val;
    }

    function showAddedFeedback(btn) {
        if (!btn) return;
        const originalText = btn.innerHTML;
        btn.innerHTML = '&#10003; Added!';
        btn.classList.add('swac-btn--added');
        setTimeout(() => {
            btn.innerHTML = originalText;
            btn.classList.remove('swac-btn--added');
        }, 1400);
    }

    function bindLoopButtons() {
        if (!safeDoc) return;
        safeDoc.addEventListener('click', function (e) {
            const btn = e.target.closest('.swac-loop-add');
            if (!btn) return;
            e.preventDefault();

            const parentAction = btn.closest('.swac-b2b-loop-action, .swac-b2b-loop-wrapper, .swac-action-row');
            const qtyInput = parentAction ? parentAction.querySelector('.swac-qty-input') : null;
            let qty = qtyInput ? (parseInt(qtyInput.value, 10) || 1) : 1;
            if (qty < 1) qty = 1;
            if (qty > CONFIG.maxQtyPerItem) qty = CONFIG.maxQtyPerItem;

            const item = {
                id: btn.dataset.productId || btn.href,
                name: btn.dataset.productName || 'Product',
                url: btn.dataset.productUrl || btn.href,
                price: btn.dataset.productPrice || '',
                mrp: parseFloat(btn.dataset.productMrp) || 0,
                salePrice: parseFloat(btn.dataset.productSalePrice) || 0,
                qty: qty,
                cartonPack: parseInt(btn.dataset.cartonPack, 10) || 0,
                schemeBadge: btn.dataset.schemeBadge || '',
                isRx: btn.dataset.isRx === '1',
            };

            addToCart(item, btn);
        });
    }

    function bindActionRowButtons() {
        if (!safeDoc) return;
        safeDoc.addEventListener('click', function (e) {
            // 1. Quantity Plus / Minus inside .swac-qty-selector
            const qtyBtn = e.target.closest('.swac-qty-selector .swac-qty-btn');
            if (qtyBtn) {
                e.preventDefault();
                const selector = qtyBtn.closest('.swac-qty-selector');
                const input = selector ? selector.querySelector('.swac-qty-input') : null;
                if (!input) return;

                let val = parseInt(input.value, 10) || 1;
                const min = parseInt(input.min, 10) || 1;
                const max = parseInt(input.max, 10) || CONFIG.maxQtyPerItem;

                if (qtyBtn.classList.contains('swac-qty-plus')) {
                    if (val < max) val++;
                    else showToast(`Maximum ${max} units per item allowed.`);
                } else if (qtyBtn.classList.contains('swac-qty-minus')) {
                    if (val > min) val--;
                }
                input.value = val;
                input.dispatchEvent(new Event('change', {bubbles: true}));
                return;
            }

            // 2. Direct Add-to-Cart from Action Row Button (.swac-action-btn)
            const actionBtn = e.target.closest('.swac-action-btn:not(.swac-loop-add)');
            if (actionBtn) {
                e.preventDefault();
                const row = actionBtn.closest('.swac-action-row');
                const qtyInput = row ? row.querySelector('.swac-qty-input') : null;
                let qty = qtyInput ? (parseInt(qtyInput.value, 10) || 1) : 1;
                if (qty < 1) qty = 1;
                if (qty > CONFIG.maxQtyPerItem) qty = CONFIG.maxQtyPerItem;

                const nameEl = safeDoc.querySelector('.product_title, h1.entry-title');
                const priceEl = safeDoc.querySelector('.woocommerce-Price-amount, .price');
                let priceFormatted = '';
                let unitPrice = 0;
                if (priceEl) {
                    const insEl = priceEl.querySelector('ins .woocommerce-Price-amount') || priceEl;
                    priceFormatted = insEl.textContent.trim();
                    const num = parseFloat(priceFormatted.replace(/[^0-9.]/g, ''));
                    if (!isNaN(num)) unitPrice = num;
                }

                const mrpRaw = row ? row.dataset.productMrp : null;
                const salePriceRaw = row ? row.dataset.productSalePrice : null;
                const cartonPackRaw = row ? row.dataset.cartonPack : null;
                const schemeBadgeRaw = row ? row.dataset.schemeBadge : null;
                const isRxRaw = row ? row.dataset.isRx : null;

                const item = {
                    id: (row ? row.dataset.productId : '') || window.location.pathname,
                    name: nameEl ? nameEl.textContent.trim() : (row ? row.dataset.productName || 'Product' : 'Product'),
                    url: window.location.href,
                    price: priceFormatted,
                    mrp: mrpRaw ? parseFloat(mrpRaw) : unitPrice,
                    salePrice: salePriceRaw ? parseFloat(salePriceRaw) : unitPrice,
                    qty: qty,
                    cartonPack: cartonPackRaw ? parseInt(cartonPackRaw, 10) : 0,
                    schemeBadge: schemeBadgeRaw || '',
                    isRx: isRxRaw === '1',
                };

                addToCart(item, actionBtn);
            }
        });
    }

    function bindCatalogOrdering() {
        if (!safeDoc) return;
        safeDoc.addEventListener('change', function (e) {
            const select = e.target.closest('select.orderby');
            if (select) {
                const form = select.closest('form');
                if (form) form.submit();
            }
        });

        safeDoc.addEventListener('click', function (e) {
            const btn = e.target.closest('.woocommerce-ordering button[type="submit"]');
            if (btn) {
                const form = btn.closest('form');
                if (form) form.submit();
            }
        });
    }

    // ── Delivery Cutoff Notice & Estimates (Delegated to delivery-engine.js) ──
    function getKolkataFormattedDate(baseIstDate, daysAhead) {
        const engine = getDeliveryEngine();
        return engine ? engine.getKolkataFormattedDate(baseIstDate, daysAhead) : '';
    }

    function getKolkataDeliveryCutoffMessage(customNow) {
        const engine = getDeliveryEngine();
        return engine ? engine.getKolkataDeliveryCutoffMessage(customNow, CONFIG) : '';
    }

    function getDeliveryCutoffMessage(customNow) {
        return getKolkataDeliveryCutoffMessage(customNow);
    }

    function getKolkataDeliveryEstimate(customNow) {
        const engine = getDeliveryEngine();
        return engine ? engine.getKolkataDeliveryEstimate(customNow, CONFIG) : '';
    }

    function getDeliveryEstimate(customNow) {
        return getKolkataDeliveryEstimate(customNow);
    }

    function getSavedRetailer() {
        try {
            const raw = localStorage.getItem(RETAILER_STORAGE_KEY);
            return raw ? JSON.parse(raw) : null;
        } catch (e) {
            return null;
        }
    }

    function hydrateDeliveryNotice() {
        if (!safeDoc) return;
        const message = getDeliveryCutoffMessage();
        if (!message) return;

        // 1. Single Product Page Notice
        const productTextEl = safeDoc.querySelector('.swac-delivery-notice .swac-delivery-text');
        if (productTextEl && productTextEl.textContent.trim() !== message) {
            productTextEl.textContent = message;
        }

        // 2. Slide-out Order Drawer Notice
        const drawerTextEl = safeDoc.querySelector('.swac-drawer-cutoff-text');
        if (drawerTextEl && drawerTextEl.textContent.trim() !== message) {
            drawerTextEl.textContent = message;
        }
    }

    // ── Global Cart API Bridge ────────────────────────────────────────────────
    const cartApi = {
        CONFIG,
        fmtPrice,
        fmtInr,
        escHtml,
        getCart,
        saveCart,
        addToCart,
        removeFromCart,
        clearCart,
        getCustomerDetails,
        saveCustomerDetails,
        computeCartTotals,
        evaluateDeliveryZone,
        isAlwaysFreePincode,
        refreshUI,
        showToast,
        openDrawer,
        closeDrawer,
        sendOrderToWhatsApp,
        getDeliveryEstimate,
        getDeliveryCutoffMessage,
        getSavedRetailer,
        hydrateDeliveryNotice,
    };

    safeWindow.swacCart = cartApi;
    safeWindow.swacRefreshCartUI = refreshUI;
    safeWindow.swacOpenCartDrawer = openDrawer;
    safeWindow.swacCloseCartDrawer = closeDrawer;

    // ── Initialization (Browser only) ──────────────────────────────────────────
    if (typeof window !== 'undefined' && typeof document !== 'undefined') {
        refreshUI();
        interceptAddToCart();
        bindLoopButtons();
        bindActionRowButtons();
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
                const engine = getDeliveryEngine();
                if (engine && typeof engine.resetZoneSets === 'function') {
                    engine.resetZoneSets();
                }
            }
        };
    }

})();
