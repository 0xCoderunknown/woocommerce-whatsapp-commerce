/**
 * 💬 CLIENT MODULE: WHATSAPP ORDER SERIALIZER
 *
 * Compiles in-memory cart items and customer delivery details into an itemized,
 * structured, URL-safe plain-text WhatsApp order message.
 * Supports B2B wholesale cartons, free goods schemes, and customer addresses.
 *
 * @package StatelessWaCommerce
 */
(function () {
    'use strict';

    /**
     * Strips CR/LF from customer/retailer fields to prevent message splitting or header injection.
     *
     * @param {string|null} val
     * @returns {string}
     */
    function sanitizeField(val) {
        return (val || '').replace(/[\r\n]+/g, ' ').trim();
    }

    /**
     * Resolves the proper query parameter delimiter ('?' vs '&') for WhatsApp URLs.
     *
     * @param {string} baseUrl
     * @returns {string}
     */
    function resolveDelimiter(baseUrl) {
        return (baseUrl && baseUrl.includes('?')) ? '&' : '?';
    }

    /**
     * Builds a canonical WhatsApp direct launch URL.
     *
     * @param {string} baseUrl Configured base wa.me / api.whatsapp.com URL.
     * @param {string} message Plain-text order message.
     * @returns {string}
     */
    function buildWhatsAppOrderUrl(baseUrl, message) {
        if (!baseUrl) return '';
        const delim = resolveDelimiter(baseUrl);
        return `${baseUrl}${delim}text=${encodeURIComponent(message)}`;
    }

    /**
     * Compiles an itemized WhatsApp order message.
     *
     * @param {Array} cart Cart items array.
     * @param {Object} details Customer delivery details or B2B retailer route.
     * @param {Object} [deps] Runtime dependency overrides (config, totals, formatters).
     * @returns {string}
     */
    function buildOrderMessage(cart, details, deps) {
        deps = deps || {};
        const cfg = deps.config || (typeof window !== 'undefined' ? (window.swacCommerce || window.swacConfig || {}) : {});
        const storeName = cfg.name || cfg.store_name || 'Store';
        const cityName = cfg.cityName || cfg.city_name || '';
        const loy = cfg.loyalty || {};

        const fmt = deps.formatPrice || function (num) {
            const sym = (cfg.currencySymbol || cfg.currency_symbol) ? ((cfg.currencySymbol || cfg.currency_symbol) + ' ') : '₹ ';
            const val = parseFloat(num) || 0;
            return sym + val.toLocaleString(undefined, {
                minimumFractionDigits: 0,
                maximumFractionDigits: 2
            });
        };

        const computeTotals = deps.computeTotals || function () {
            let total = 0;
            (cart || []).forEach(item => { total += (parseFloat(item.salePrice) || 0) * (item.qty || 1); });
            return { totalMrp: total, saleTotal: total, youSave: 0, savePct: 0, shipping: 0, amountPayable: total };
        };

        const lines = (cart || []).map((item, idx) => {
            const rxTag = item.isRx ? '[Rx] ' : '';
            let line = `${idx + 1}. ${rxTag}*${item.name}*`;
            if (item.qty > 1) {
                line += ` — Qty: ${item.qty}`;
            }
            if (item.cartonPack > 0) {
                const fullCartons = Math.floor(item.qty / item.cartonPack);
                const loosePcs = item.qty % item.cartonPack;
                if (fullCartons > 0 && loosePcs > 0) {
                    line += ` (${fullCartons} Ctn + ${loosePcs} pcs)`;
                } else if (fullCartons > 0) {
                    line += ` (${fullCartons} Carton${fullCartons > 1 ? 's' : ''})`;
                }
            }
            if (item.schemeBadge) {
                line += `\n   Scheme: ${item.schemeBadge}`;
            }
            const unitPriceNum = parseFloat(item.salePrice) || 0;
            if (unitPriceNum > 0) {
                if (item.qty > 1) {
                    line += `\n   Price: ${fmt(unitPriceNum)} \u00d7 ${item.qty} = ${fmt(unitPriceNum * item.qty)}`;
                } else {
                    line += `\n   Price: ${fmt(unitPriceNum)}`;
                }
            } else if (item.price) {
                line += `\n   Price: ${item.price}${item.qty > 1 ? ' \u00d7 ' + item.qty : ''}`;
            }
            return line;
        });

        const totals = computeTotals(cart, details ? details.pincode : undefined);
        const shipLabel = totals.shipping === 0 ? 'FREE' : fmt(totals.shipping);

        const isLoyActive = (typeof deps.isLoyaltyActive === 'function')
            ? deps.isLoyaltyActive(totals.saleTotal)
            : false;
        const loyChoice = deps.loyaltyChoice || 'no';

        const sep = '\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500';

        let msg = `Hello ${storeName}, I'd like to order:\n\n`
            + lines.join('\n\n')
            + `\n\n${sep}`
            + `\nTotal MRP:      ${fmt(totals.totalMrp)}`
            + `\nDiscount:       ${fmt(totals.youSave)} (${totals.savePct}% off)`
            + `\nShipping:       ${shipLabel}`
            + `\nTo Pay:         ${fmt(totals.amountPayable)}`
            + `\n${sep}`;

        const loyLabel = loy.itemLabel || loy.item_label || '';
        const loyWorth = loy.itemWorth ?? loy.item_worth ?? 0;
        const loyPrice = loy.itemPrice ?? loy.item_price ?? 0;
        const loyFlag = loy.flag || '';

        if (loyChoice === 'yes' && isLoyActive && loyLabel) {
            msg += `\n\n\uD83C\uDF81  ${loyLabel} (worth ${fmt(loyWorth)}) \u2192 ${fmt(loyPrice)} added to order`
                + (loyFlag ? `\n${loyFlag}` : '')
                + `\n${sep}`;
        }

        const estimate = (typeof deps.getDeliveryEstimate === 'function') ? deps.getDeliveryEstimate() : null;
        if (estimate) {
            msg += `\n\n*Estimated Delivery:* ${estimate}`;
        }

        const retailer = (typeof deps.getSavedRetailer === 'function') ? deps.getSavedRetailer() : null;
        if (retailer && retailer.store_name) {
            msg += `\n\n🏪 *Retailer:* ${sanitizeField(retailer.store_name)} (${sanitizeField(retailer.phone)})`;
            if (retailer.area) {
                msg += `\n📍 *Route / Area:* ${sanitizeField(retailer.area)}`;
            }
        }

        if (details && details.isB2B) {
            msg += `\n\n🚚 *Dispatch Route:* ${sanitizeField(details.area || 'Wholesale Delivery Route')}`;
            if (details.remarks) {
                msg += `\n📝 *Dispatch Note:* ${sanitizeField(details.remarks)}`;
            }
            msg += `\n${sep}`;
        } else if (details) {
            msg += `\n\n*Delivery Details:*`
                + `\nName: ${sanitizeField(details.name)}`
                + `\nAddress: ${sanitizeField(details.address)}`;
            if (details.landmark) {
                msg += `\nLandmark: ${sanitizeField(details.landmark)}`;
            }
            if (details.area) {
                msg += `\nArea: ${sanitizeField(details.area)}`;
            }
            if (details.pincode) {
                msg += `\nPostal Code: ${details.pincode}` + (cityName ? `, ${cityName}` : '');
            } else if (cityName) {
                msg += `\nCity: ${cityName}`;
            }
            msg += `\n${sep}`;
        }

        const hasRx = (cart || []).some(item => Boolean(item.isRx));
        const rxEnabled = cfg.prescriptionNoteEnabled ?? cfg.prescription_note_enabled;
        const rxNote = cfg.prescriptionNote || cfg.prescription_note;
        if (rxEnabled && rxNote && hasRx) {
            msg += `\n\n${rxNote}`;
        }

        msg += `\n\nPlease confirm my order.`;

        return msg;
    }

    const api = {
        sanitizeField,
        resolveDelimiter,
        buildWhatsAppOrderUrl,
        buildOrderMessage,
    };

    if (typeof window !== 'undefined') {
        window.swacOrderSerializer = api;
    }

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = api;
    }
})();
