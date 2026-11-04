/**
 * 🧮 CLIENT MODULE: CART MATH & PRICING ENGINE
 *
 * Zero-DOM computation of subtotal, strike-through MRP, savings amount,
 * savings percentage, shipping thresholds, always-free postal codes,
 * and loyalty reward discounts.
 *
 * @package StatelessWaCommerce
 */
(function () {
    'use strict';

    const safeWindow = (typeof window !== 'undefined') ? window : (typeof globalThis !== 'undefined' ? globalThis : {});

    /**
     * Determines whether the loyalty reward program is currently triggered for the cart subtotal.
     *
     * @param {number} saleTotal
     * @param {Object} [customConfig]
     * @returns {boolean}
     */
    function isLoyaltyActive(saleTotal, customConfig) {
        const cfg = customConfig || (safeWindow.swacCommerce || safeWindow.swacConfig || {});
        const loy = cfg.loyalty || {};
        const enabled = (typeof loy.enabled === 'boolean') ? loy.enabled : (loy.enabled === '1' || loy.enabled === true);
        const label = (typeof loy.itemLabel === 'string' ? loy.itemLabel : (loy.item_label || '')).trim();
        const threshold = parseFloat(loy.threshold) || 0;
        return Boolean(enabled && label.length > 0 && threshold > 0 && saleTotal >= threshold);
    }

    /**
     * Pure zero-DOM computation of cart totals.
     *
     * @param {Array} cart Array of cart items [{ salePrice, mrp, qty, ... }]
     * @param {string|number} [pincode] Customer postal code to check for always-free shipping.
     * @param {Object} [deps] Runtime dependency overrides (config, isAlwaysFreePincode, loyaltyChoice).
     * @returns {{
     *   totalMrp: number,
     *   saleTotal: number,
     *   youSave: number,
     *   savePct: number,
     *   shipping: number,
     *   amountPayable: number,
     *   isAlwaysFree: boolean
     * }}
     */
    function computeCartTotals(cart, pincode, deps) {
        deps = deps || {};
        const cfg = deps.config || (safeWindow.swacCommerce || safeWindow.swacConfig || {});
        const freeAt = parseFloat(cfg.freeShippingAt ?? cfg.free_shipping_at) || 0;
        const shipCharge = parseFloat(cfg.shippingCharge ?? cfg.shipping_charge) || 0;

        let isAlwaysFree = false;
        if (typeof deps.isAlwaysFreePincode === 'function') {
            isAlwaysFree = Boolean(deps.isAlwaysFreePincode(pincode));
        } else if (typeof safeWindow.swacDelivery !== 'undefined' && typeof safeWindow.swacDelivery.isAlwaysFreePincode === 'function') {
            isAlwaysFree = Boolean(safeWindow.swacDelivery.isAlwaysFreePincode(pincode));
        } else {
            const rawCodes = cfg.alwaysFreeShippingCodes || cfg.always_free_shipping_codes || (safeWindow.swacZones && safeWindow.swacZones.always_free_shipping_codes) || [];
            const clean = String(pincode || '').replace(/\D/g, '');
            if (clean && Array.isArray(rawCodes)) {
                const freeSet = new Set(rawCodes.map(String).map(s => s.replace(/\D/g, '')));
                isAlwaysFree = freeSet.has(clean);
            }
        }

        let totalMrp = 0;
        let saleTotal = 0;

        (cart || []).forEach(function (item) {
            const sale = parseFloat(item.salePrice) || 0;
            let mrp = parseFloat(item.mrp) || 0;

            if (mrp <= 0 || mrp < sale) {
                mrp = sale;
            }

            const qty = parseInt(item.qty, 10) || 1;
            totalMrp += mrp * qty;
            saleTotal += sale * qty;
        });

        let youSave = totalMrp - saleTotal;
        let savePct = totalMrp > 0 ? Math.round((youSave / totalMrp) * 100) : 0;
        const shipping = isAlwaysFree ? 0 : (saleTotal < freeAt ? shipCharge : 0);
        let amountPayable = saleTotal + shipping;

        const loyaltyChoice = deps.loyaltyChoice;
        const loy = cfg.loyalty || {};
        const loyItemWorth = parseFloat(loy.itemWorth ?? loy.item_worth) || 0;
        const loyItemPrice = parseFloat(loy.itemPrice ?? loy.item_price) || 0;

        if (loyaltyChoice === 'yes' && isLoyaltyActive(saleTotal, cfg)) {
            const loyaltyDiscount = loyItemWorth - loyItemPrice;
            youSave += loyaltyDiscount;
            savePct = Math.round((youSave / (totalMrp + loyItemWorth)) * 100);
            amountPayable += loyItemPrice;
        }

        return {totalMrp, saleTotal, youSave, savePct, shipping, amountPayable, isAlwaysFree};
    }

    const api = {
        isLoyaltyActive,
        computeCartTotals,
    };

    if (typeof window !== 'undefined') {
        window.swacCartMath = api;
    }

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = api;
    }
})();

