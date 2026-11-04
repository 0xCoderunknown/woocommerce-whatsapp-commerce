/**
 * 🚚 CLIENT MODULE: DELIVERY ZONES & CUTOFF ENGINE
 *
 * Evaluates postal codes against configured delivery zones, verifies always-free
 * shipping eligibility, and calculates Kolkata/IST timezone-aware delivery cutoff windows.
 *
 * @package StatelessWaCommerce
 */
(function () {
    'use strict';

    const safeWindow = (typeof window !== 'undefined') ? window : (typeof globalThis !== 'undefined' ? globalThis : {});

    let sameDaySet = null;
    let outskirtsSet = null;
    let alwaysFreeSet = null;

    /**
     * Clears cached pincode sets (used on config reload or test reset).
     */
    function resetZoneSets() {
        sameDaySet = null;
        outskirtsSet = null;
        alwaysFreeSet = null;
    }

    /**
     * Resolves Set lookups for same-day, outskirts, and always-free postal codes.
     *
     * @param {Object} [customZones]
     * @param {Object} [customConfig]
     * @returns {{sameDaySet: Set, outskirtsSet: Set, alwaysFreeSet: Set}}
     */
    function getZoneSets(customZones, customConfig) {
        if (!sameDaySet || sameDaySet.size === 0 || !alwaysFreeSet || customZones || customConfig) {
            const zonesData = customZones || safeWindow.swacZones || {};
            const configData = customConfig || safeWindow.swacCommerce || safeWindow.swacConfig || {};
            const rawSameDay = zonesData.same_day_pins || (zonesData.zones && zonesData.zones.standard && zonesData.zones.standard.pincodes ? Object.keys(zonesData.zones.standard.pincodes) : []) || [];
            const rawOutskirts = zonesData.outskirts_pins || [];
            const rawAlwaysFree = zonesData.always_free_shipping_codes || configData.always_free_shipping_codes || configData.alwaysFreeShippingCodes || [];

            const sSet = new Set(rawSameDay.map(String));
            const oSet = new Set(rawOutskirts.map(String));
            const aSet = new Set((Array.isArray(rawAlwaysFree) ? rawAlwaysFree : []).map(String).map(s => s.replace(/\D/g, '')).filter(Boolean));

            if (!customZones && !customConfig) {
                sameDaySet = sSet;
                outskirtsSet = oSet;
                alwaysFreeSet = aSet;
                return {sameDaySet, outskirtsSet, alwaysFreeSet};
            }
            return {sameDaySet: sSet, outskirtsSet: oSet, alwaysFreeSet: aSet};
        }
        return {sameDaySet, outskirtsSet, alwaysFreeSet};
    }

    /**
     * Checks if a postal code qualifies for always-free delivery regardless of cart subtotal.
     *
     * @param {string|number} pincode
     * @param {Object} [customZones]
     * @param {Object} [customConfig]
     * @returns {boolean}
     */
    function isAlwaysFreePincode(pincode, customZones, customConfig) {
        if (!pincode) return false;
        const clean = String(pincode).replace(/\D/g, '');
        if (!clean) return false;
        const {alwaysFreeSet: freeSet} = getZoneSets(customZones, customConfig);
        return Boolean(freeSet && freeSet.has(clean));
    }

    /**
     * Evaluates a postal code against open vs gated tiers and returns eligibility and status.
     *
     * @param {string|number} pincode
     * @param {Object} [customZones]
     * @param {Object} [customConfig]
     * @returns {Object|null}
     */
    function evaluateDeliveryZone(pincode, customZones, customConfig) {
        if (!pincode) return null;
        const clean = String(pincode).replace(/\D/g, '');
        if (clean.length < 3) return null;

        const zonesData = customZones || safeWindow.swacZones || {};
        const isGated = Boolean(zonesData.gated || (zonesData.same_day_pins && zonesData.same_day_pins.length > 0));

        // Always-free postal codes are always allowed for delivery
        if (isAlwaysFreePincode(clean, customZones, customConfig)) {
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

        const {sameDaySet: sDays, outskirtsSet: oSkirts} = getZoneSets(customZones, customConfig);
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

    /**
     * Formats a calendar date offset by daysAhead in Kolkata timezone.
     *
     * @param {Date} baseIstDate
     * @param {number} daysAhead
     * @returns {string} e.g. "15 Oct"
     */
    function getKolkataFormattedDate(baseIstDate, daysAhead) {
        const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        const target = new Date(baseIstDate.getTime() + (daysAhead * 24 * 60 * 60 * 1000));
        return `${target.getUTCDate()} ${months[target.getUTCMonth()]}`;
    }

    /**
     * Computes the dynamic delivery cutoff message according to IST store hours.
     *
     * @param {Date} [customNow]
     * @param {Object} [customConfig]
     * @returns {string}
     */
    function getKolkataDeliveryCutoffMessage(customNow, customConfig) {
        const now = (customNow instanceof Date) ? customNow : new Date();
        const cfg = customConfig || safeWindow.swacCommerce || safeWindow.swacConfig || {};
        const offsetHours = (typeof cfg.timezoneOffset === 'number') ? cfg.timezoneOffset : ((typeof cfg.timezone_offset === 'number') ? cfg.timezone_offset : 0);
        const istDate = new Date(now.getTime() + (offsetHours * 60 * 60 * 1000));
        const day = istDate.getUTCDay(); // 0 = Sun, 1 = Mon, ..., 6 = Sat
        const hour = istDate.getUTCHours(); // 0 to 23

        const msgs = cfg.cutoffMessages || cfg.cutoff_messages || {};
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

    function getDeliveryCutoffMessage(customNow, customConfig) {
        return getKolkataDeliveryCutoffMessage(customNow, customConfig);
    }

    /**
     * Returns a short estimated delivery window (e.g. "Today (Same-day)", "Tomorrow (15 Oct)").
     *
     * @param {Date} [customNow]
     * @param {Object} [customConfig]
     * @returns {string}
     */
    function getKolkataDeliveryEstimate(customNow, customConfig) {
        const now = (customNow instanceof Date) ? customNow : new Date();
        const cfg = customConfig || safeWindow.swacCommerce || safeWindow.swacConfig || {};
        const offsetHours = (typeof cfg.timezoneOffset === 'number') ? cfg.timezoneOffset : ((typeof cfg.timezone_offset === 'number') ? cfg.timezone_offset : 0);
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

    function getDeliveryEstimate(customNow, customConfig) {
        return getKolkataDeliveryEstimate(customNow, customConfig);
    }

    const api = {
        getZoneSets,
        resetZoneSets,
        isAlwaysFreePincode,
        evaluateDeliveryZone,
        getKolkataFormattedDate,
        getKolkataDeliveryCutoffMessage,
        getDeliveryCutoffMessage,
        getKolkataDeliveryEstimate,
        getDeliveryEstimate,
    };

    if (typeof window !== 'undefined') {
        window.swacDelivery = api;
    }

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = api;
    }
})();

