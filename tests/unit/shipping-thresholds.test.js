const {test, describe, beforeEach} = require('node:test');
const assert = require('node:assert/strict');
const waCart = require('../../assets/js/wa_cart.js');

describe('Shipping Thresholds & Always-Free Postal Codes', () => {
    beforeEach(() => {
        waCart.setRuntimeConfig({
            freeShippingAt: 500,
            shippingCharge: 60,
            alwaysFreeShippingCodes: ['781001', '781005']
        });
        waCart.setZonesData({
            gated: false,
            always_free_shipping_codes: ['781001', '781005']
        });
    });

    test('should apply flat shipping fee when cart is below threshold', () => {
        const cart = [{id: 1, name: 'Item', salePrice: 350, mrp: 400, qty: 1}];
        const totals = waCart.computeCartTotals(cart, '781020');

        assert.equal(totals.saleTotal, 350);
        assert.equal(totals.shipping, 60);
        assert.equal(totals.amountPayable, 410);
        assert.equal(totals.isAlwaysFree, false);
    });

    test('should waive shipping fee when cart is at or above threshold', () => {
        const cart = [{id: 1, name: 'Item', salePrice: 500, mrp: 600, qty: 1}];
        const totals = waCart.computeCartTotals(cart, '781020');

        assert.equal(totals.saleTotal, 500);
        assert.equal(totals.shipping, 0);
        assert.equal(totals.amountPayable, 500);
        assert.equal(totals.isAlwaysFree, false);
    });

    test('should waive shipping fee for always-free postal code even below threshold', () => {
        const cart = [{id: 1, name: 'Item', salePrice: 150, mrp: 200, qty: 1}];
        const totals = waCart.computeCartTotals(cart, '781001');

        assert.equal(totals.saleTotal, 150);
        assert.equal(totals.shipping, 0);
        assert.equal(totals.amountPayable, 150);
        assert.equal(totals.isAlwaysFree, true);
    });

    test('should recognize always-free postal codes despite spaces, hyphens, or formatting', () => {
        assert.equal(waCart.isAlwaysFreePincode('781001'), true);
        assert.equal(waCart.isAlwaysFreePincode(' 781001 '), true);
        assert.equal(waCart.isAlwaysFreePincode('781-005'), true);
        assert.equal(waCart.isAlwaysFreePincode('781 005'), true);
        assert.equal(waCart.isAlwaysFreePincode('781020'), false);
        assert.equal(waCart.isAlwaysFreePincode(''), false);
        assert.equal(waCart.isAlwaysFreePincode(null), false);
    });

    test('should allow delivery in gated mode when postal code is in always-free tier', () => {
        waCart.setZonesData({
            gated: true,
            same_day_pins: ['781020'],
            outskirts_pins: ['781030'],
            always_free_shipping_codes: ['781001']
        });

        const resAlwaysFree = waCart.evaluateDeliveryZone('781001');
        assert.equal(resAlwaysFree.allowOrder, true);
        assert.equal(resAlwaysFree.statusClass, 'swac-pin-success');

        const resSameDay = waCart.evaluateDeliveryZone('781020');
        assert.equal(resSameDay.allowOrder, true);

        const resOutskirts = waCart.evaluateDeliveryZone('781030');
        assert.equal(resOutskirts.allowOrder, false);

        const resUnsupported = waCart.evaluateDeliveryZone('999999');
        assert.equal(resUnsupported.allowOrder, false);
    });
});
