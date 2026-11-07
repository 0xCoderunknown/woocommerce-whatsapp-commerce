const { test, describe, beforeEach } = require('node:test');
const assert = require('node:assert/strict');
const waCart = require('../../assets/js/wa_cart.js');

describe('Wholesale Schemes & B2B Cart Calculations', () => {
    beforeEach(() => {
        waCart.setRuntimeConfig({
            name: 'Guwahati Wholesale Depot',
            currencySymbol: '₹',
            freeShippingAt: 10000,
            shippingCharge: 0,
            alwaysFreeShippingCodes: [],
            loyalty: { enabled: false }
        });
    });

    test('should calculate wholesale PTR margin amount and percentage accurately', () => {
        const mrp = 30.0;
        const ptr = 24.50;

        const marginAmount = mrp - ptr;
        const marginPercent = Math.round(((mrp - ptr) / mrp) * 1000) / 10;

        assert.equal(marginAmount.toFixed(2), '5.50');
        assert.equal(marginPercent, 18.3);
    });

    test('should calculate carton breakdown from loose quantity', () => {
        const cartonPack = 144;

        // Exact 1 carton
        let qty = 144;
        let full = Math.floor(qty / cartonPack);
        let loose = qty % cartonPack;
        assert.equal(full, 1);
        assert.equal(loose, 0);

        // 2 cartons + 12 loose pcs
        qty = 300;
        full = Math.floor(qty / cartonPack);
        loose = qty % cartonPack;
        assert.equal(full, 2);
        assert.equal(loose, 12);
    });

    test('should calculate free goods scheme units correctly', () => {
        const buyQty = 12;
        const freeQty = 1;

        // 12 pcs -> 1 free
        assert.equal(Math.floor(12 / buyQty) * freeQty, 1);

        // 24 pcs -> 2 free
        assert.equal(Math.floor(24 / buyQty) * freeQty, 2);

        // 35 pcs -> still 2 free
        assert.equal(Math.floor(35 / buyQty) * freeQty, 2);

        // 48 pcs -> 4 free
        assert.equal(Math.floor(48 / buyQty) * freeQty, 4);
    });

    test('should format order message item with carton conversion and scheme badge', () => {
        const cart = [
            {
                id: 101,
                name: 'Rin Bar 250g',
                price: '₹24.50',
                mrp: 30,
                salePrice: 24.50,
                qty: 144,
                cartonPack: 144,
                schemeBadge: '12 + 1 FREE'
            }
        ];

        const details = {
            name: 'Ramesh Sharma',
            address: 'Shop 4, Fancy Bazar',
            pincode: '781001'
        };

        const msg = waCart.buildOrderMessage(cart, details);

        // Verify item line contains carton breakdown
        assert.match(msg, /Qty: 144 \(1 Carton\)/);
        // Verify scheme badge is formatted
        assert.match(msg, /Scheme: 12 \+ 1 FREE/);
        // Verify PTR calculation
        assert.match(msg, /Price: ₹ 24\.5 × 144 = ₹ 3,528/);
    });

    test('should format B2B dispatch route and remarks for authenticated retailers', () => {
        const cart = [
            {
                id: 102,
                name: 'Surf Excel 1kg',
                price: '₹120',
                mrp: 140,
                salePrice: 120,
                qty: 24,
                cartonPack: 12,
                schemeBadge: '2% Cash Discount'
            }
        ];

        const b2bDetails = {
            name: 'Maa Tara Stores (Ramesh Sharma)',
            area: 'Fancy Bazar Route #3',
            remarks: 'Ship via Assam Bengal Transport',
            isB2B: true
        };

        const msg = waCart.buildOrderMessage(cart, b2bDetails);

        assert.match(msg, /Dispatch Route:\* Fancy Bazar Route #3/);
        assert.match(msg, /Dispatch Note:\* Ship via Assam Bengal Transport/);
        assert.doesNotMatch(msg, /Postal Code:/);
    });

    test('should reliably parse shorthand scheme notations into schema types', () => {
        function parseScheme(shorthand) {
            const clean = (shorthand || '').trim();
            if (!clean || clean.toLowerCase() === 'none' || clean === '-') {
                return { scheme_type: 'none' };
            }
            const freeGoods = clean.match(/^\s*(\d+)\s*\+\s*(\d+)(?:\s+FREE)?\s*$/i);
            if (freeGoods) {
                return {
                    scheme_type: 'free_goods',
                    scheme_buy: parseInt(freeGoods[1], 10),
                    scheme_free: parseInt(freeGoods[2], 10)
                };
            }
            if (/^\s*\d+\s*:\s*\d+(?:\.\d+)?%/i.test(clean)) {
                return {
                    scheme_type: 'tier_discount',
                    scheme_tiers: clean
                };
            }
            const cd = clean.match(/^\s*(\d+(?:\.\d+)?)\s*%\s*CD\s*$/i);
            if (cd) {
                return {
                    scheme_type: 'cash_discount',
                    scheme_cd: parseFloat(cd[1])
                };
            }
            return {
                scheme_type: 'custom',
                scheme_custom: clean
            };
        }

        // 1. Free goods
        assert.deepEqual(parseScheme('10+1'), { scheme_type: 'free_goods', scheme_buy: 10, scheme_free: 1 });
        assert.deepEqual(parseScheme('12+1 FREE'), { scheme_type: 'free_goods', scheme_buy: 12, scheme_free: 1 });
        assert.deepEqual(parseScheme(' 24 + 2 '), { scheme_type: 'free_goods', scheme_buy: 24, scheme_free: 2 });

        // 2. Volume tiers
        assert.deepEqual(parseScheme('10:2%'), { scheme_type: 'tier_discount', scheme_tiers: '10:2%' });
        assert.deepEqual(parseScheme('10:2%, 25:5%'), { scheme_type: 'tier_discount', scheme_tiers: '10:2%, 25:5%' });

        // 3. Cash discount
        assert.deepEqual(parseScheme('2% CD'), { scheme_type: 'cash_discount', scheme_cd: 2 });
        assert.deepEqual(parseScheme('2.5%CD'), { scheme_type: 'cash_discount', scheme_cd: 2.5 });

        // 4. None / Empty
        assert.deepEqual(parseScheme(''), { scheme_type: 'none' });
        assert.deepEqual(parseScheme('none'), { scheme_type: 'none' });
        assert.deepEqual(parseScheme('-'), { scheme_type: 'none' });

        // 5. Custom promotional text
        assert.deepEqual(parseScheme('Diwali Special 5% Off'), { scheme_type: 'custom', scheme_custom: 'Diwali Special 5% Off' });
    });

    test('should clamp MOQ to minimum of 1', () => {
        const clampMoq = (val) => Math.max(1, parseInt(val, 10) || 1);

        assert.equal(clampMoq(0), 1);
        assert.equal(clampMoq(-5), 1);
        assert.equal(clampMoq(''), 1);
        assert.equal(clampMoq(null), 1);
        assert.equal(clampMoq(6), 6);
        assert.equal(clampMoq('12'), 12);
    });

    test('should enforce role-based permissions between owner and staff roles', () => {
        function validateUpdatePermissions(role, payload) {
            if (role === 'staff') {
                const restricted = ['ptr', 'mrp', 'scheme', 'scheme_shorthand', 'moq', 'carton', 'carton_pack'];
                const hasRestricted = restricted.some(k => Object.prototype.hasOwnProperty.call(payload, k));
                if (hasRestricted) {
                    return { allowed: false, error: 'Staff accounts are authorized for stock toggles only.' };
                }
                if (!Object.prototype.hasOwnProperty.call(payload, 'in_stock')) {
                    return { allowed: false, error: 'No stock update parameter provided.' };
                }
            }
            return { allowed: true };
        }

        // Staff allowed to toggle stock
        assert.deepEqual(validateUpdatePermissions('staff', { id: 101, in_stock: true }), { allowed: true });
        assert.deepEqual(validateUpdatePermissions('staff', { id: 101, in_stock: false }), { allowed: true });

        // Staff blocked from editing PTR, MRP, scheme, MOQ
        assert.equal(validateUpdatePermissions('staff', { id: 101, ptr: 25.50 }).allowed, false);
        assert.equal(validateUpdatePermissions('staff', { id: 101, mrp: 30.00 }).allowed, false);
        assert.equal(validateUpdatePermissions('staff', { id: 101, scheme: '10+1' }).allowed, false);
        assert.equal(validateUpdatePermissions('staff', { id: 101, moq: 12 }).allowed, false);
        assert.equal(validateUpdatePermissions('staff', { id: 101, in_stock: true, ptr: 25.50 }).allowed, false);

        // Owner allowed all fields
        assert.deepEqual(validateUpdatePermissions('owner', { id: 101, in_stock: true }), { allowed: true });
        assert.deepEqual(validateUpdatePermissions('owner', { id: 101, ptr: 25.50 }), { allowed: true });
        assert.deepEqual(validateUpdatePermissions('owner', { id: 101, mrp: 30.00, ptr: 25.50, scheme: '10+1' }), { allowed: true });
    });
});
