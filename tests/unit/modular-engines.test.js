const {test, describe} = require('node:test');
const assert = require('node:assert/strict');
const deliveryEngine = require('../../assets/js/delivery-engine.js');
const cartMath = require('../../assets/js/cart-math.js');

describe('Modular Client Engines (Direct Isolation)', () => {
    describe('delivery-engine.js', () => {
        test('should directly evaluate always-free postal codes', () => {
            const zones = {
                gated: true,
                always_free_shipping_codes: ['781001', '781005']
            };
            assert.equal(deliveryEngine.isAlwaysFreePincode('781001', zones), true);
            assert.equal(deliveryEngine.isAlwaysFreePincode('781020', zones), false);
        });

        test('should directly evaluate gated vs open delivery tiers', () => {
            const openZones = {gated: false, same_day_pins: []};
            const openRes = deliveryEngine.evaluateDeliveryZone('781020', openZones);
            assert.equal(openRes.allowOrder, true);

            const gatedZones = {
                gated: true,
                same_day_pins: ['781001'],
                outskirts_pins: ['781020']
            };
            const validRes = deliveryEngine.evaluateDeliveryZone('781001', gatedZones);
            const invalidRes = deliveryEngine.evaluateDeliveryZone('781020', gatedZones);

            assert.equal(validRes.allowOrder, true);
            assert.equal(invalidRes.allowOrder, false);
        });

        test('should directly calculate Kolkata cutoff windows', () => {
            const config = {
                timezoneOffset: 5.5,
                cutoffMessages: {morning: 'Order before 12 noon'}
            };
            const wedMorningUtc = new Date(Date.UTC(2026, 9, 14, 4, 0, 0));
            const msg = deliveryEngine.getKolkataDeliveryCutoffMessage(wedMorningUtc, config);
            const estimate = deliveryEngine.getDeliveryEstimate(wedMorningUtc, config);

            assert.equal(msg, 'Order before 12 noon');
            assert.equal(estimate, 'Today (Same-day)');
        });
    });

    describe('cart-math.js', () => {
        test('should directly calculate cart totals without DOM dependencies', () => {
            const cart = [
                {salePrice: 120, mrp: 150, qty: 2},
                {salePrice: 200, mrp: 200, qty: 1}
            ];
            const config = {
                freeShippingAt: 500,
                shippingCharge: 40,
                alwaysFreeShippingCodes: []
            };

            const totals = cartMath.computeCartTotals(cart, '781020', {config});

            // Subtotal: (120*2) + 200 = 440 (under 500 threshold => +40 shipping)
            assert.equal(totals.saleTotal, 440);
            assert.equal(totals.totalMrp, 500);
            assert.equal(totals.youSave, 60);
            assert.equal(totals.shipping, 40);
            assert.equal(totals.amountPayable, 480);
            assert.equal(totals.isAlwaysFree, false);
        });

        test('should directly compute always-free shipping waiver', () => {
            const cart = [{salePrice: 100, mrp: 100, qty: 1}];
            const config = {
                freeShippingAt: 500,
                shippingCharge: 50,
                alwaysFreeShippingCodes: ['781001']
            };

            const totals = cartMath.computeCartTotals(cart, '781001', {config});
            assert.equal(totals.shipping, 0);
            assert.equal(totals.amountPayable, 100);
            assert.equal(totals.isAlwaysFree, true);
        });
    });
});

