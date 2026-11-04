const {test, describe, beforeEach} = require('node:test');
const assert = require('node:assert/strict');
const waCart = require('../../assets/js/wa_cart.js');

describe('Cart Math & Reconciliation', () => {
    beforeEach(() => {
        waCart.setRuntimeConfig({
            freeShippingAt: 500,
            shippingCharge: 50,
            alwaysFreeShippingCodes: [],
            loyalty: {
                enabled: false,
                threshold: 0,
                itemLabel: '',
                itemWorth: 0,
                itemPrice: 0,
                flag: 'OFFER ACCEPTED ✅'
            }
        });
        waCart.setZonesData({
            gated: false,
            always_free_shipping_codes: []
        });
    });

    test('should calculate subtotal, total MRP, and savings for single item', () => {
        const cart = [
            {id: 1, name: 'Product A', salePrice: 100, mrp: 150, qty: 1}
        ];
        const totals = waCart.computeCartTotals(cart);

        assert.equal(totals.saleTotal, 100);
        assert.equal(totals.totalMrp, 150);
        assert.equal(totals.youSave, 50);
        assert.equal(totals.savePct, 33);
        assert.equal(totals.shipping, 50); // under 500 threshold
        assert.equal(totals.amountPayable, 150);
        assert.equal(totals.isAlwaysFree, false);
    });

    test('should handle multi-item quantities and multiple products', () => {
        const cart = [
            {id: 1, name: 'Product A', salePrice: 100, mrp: 120, qty: 2}, // sale 200, mrp 240
            {id: 2, name: 'Product B', salePrice: 200, mrp: 250, qty: 1}  // sale 200, mrp 250
        ];
        const totals = waCart.computeCartTotals(cart);

        assert.equal(totals.saleTotal, 400);
        assert.equal(totals.totalMrp, 490);
        assert.equal(totals.youSave, 90);
        assert.equal(totals.savePct, 18);
        assert.equal(totals.shipping, 50);
        assert.equal(totals.amountPayable, 450);
    });

    test('should clamp MRP when mrp <= 0 or mrp < salePrice', () => {
        const cart = [
            {id: 1, name: 'Item 1', salePrice: 120, mrp: 0, qty: 1},    // mrp falls back to 120
            {id: 2, name: 'Item 2', salePrice: 200, mrp: 150, qty: 1}   // mrp clamped up to 200
        ];
        const totals = waCart.computeCartTotals(cart);

        assert.equal(totals.saleTotal, 320);
        assert.equal(totals.totalMrp, 320);
        assert.equal(totals.youSave, 0);
        assert.equal(totals.savePct, 0);
    });

    test('should return 0 totals on empty cart', () => {
        const totals = waCart.computeCartTotals([]);

        assert.equal(totals.saleTotal, 0);
        assert.equal(totals.totalMrp, 0);
        assert.equal(totals.youSave, 0);
        assert.equal(totals.savePct, 0);
        assert.equal(totals.shipping, 50);
        assert.equal(totals.amountPayable, 50);
    });

    test('should account for loyalty item when active and accepted', () => {
        waCart.setRuntimeConfig({
            freeShippingAt: 500,
            shippingCharge: 50,
            loyalty: {
                enabled: true,
                threshold: 400,
                itemLabel: 'Free Lip Balm',
                itemWorth: 100,
                itemPrice: 20,
                flag: 'OFFER ACCEPTED ✅'
            }
        });

        const cart = [
            {id: 1, name: 'High Value Item', salePrice: 450, mrp: 500, qty: 1}
        ];

        // Before accepting loyalty offer:
        const totalsWithout = waCart.computeCartTotals(cart);
        assert.equal(totalsWithout.saleTotal, 450);
        assert.equal(totalsWithout.amountPayable, 500); // 450 + 50 shipping

        // Test loyalty active check
        assert.equal(waCart.isLoyaltyActive(totalsWithout.saleTotal), true);
    });
});
