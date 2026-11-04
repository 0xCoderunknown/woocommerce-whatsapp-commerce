const {test, describe, beforeEach} = require('node:test');
const assert = require('node:assert/strict');
const waCart = require('../../assets/js/wa_cart.js');

describe('Delimiter Safety & WhatsApp Message Serialization', () => {
    beforeEach(() => {
        waCart.setRuntimeConfig({
            name: 'Test Store',
            baseUrl: 'https://api.whatsapp.com/send?phone=919876543210',
            currencySymbol: '₹',
            freeShippingAt: 500,
            shippingCharge: 40,
            alwaysFreeShippingCodes: ['781001']
        });
    });

    test('should sanitize carriage returns and newlines in customer address fields', () => {
        const cart = [{id: 1, name: 'Item Alpha', salePrice: 200, mrp: 250, qty: 1}];
        const dirtyDetails = {
            name: 'John\nDoe',
            address: 'Line 1\r\nLine 2\nApartment 4B',
            landmark: 'Near\rWater Tank',
            area: 'Central\nZone',
            pincode: '781001'
        };

        const msg = waCart.buildOrderMessage(cart, dirtyDetails);

        // Name and address should not contain mid-field raw line breaks breaking record structure
        assert.match(msg, /Name: John Doe/);
        assert.match(msg, /Address: Line 1 Line 2 Apartment 4B/);
        assert.match(msg, /Landmark: Near Water Tank/);
        assert.match(msg, /Area: Central Zone/);
        assert.match(msg, /Postal Code: 781001/);
    });

    test('should serialize zero-shipping for always-free postal code in order message', () => {
        const cart = [{id: 1, name: 'Item Alpha', salePrice: 200, mrp: 250, qty: 1}];
        const details = {
            name: 'Alice Smith',
            address: '123 River Road',
            pincode: '781001'
        };

        const msg = waCart.buildOrderMessage(cart, details);
        assert.match(msg, /Shipping:\s+FREE/);
        assert.match(msg, /To Pay:\s+₹ 200/);
    });

    test('should resolve query delimiters safely for baseUrl with existing query string', () => {
        const baseUrlWithQuery = 'https://api.whatsapp.com/send?phone=919876543210';
        const sepWithQuery = baseUrlWithQuery.includes('?') ? '&' : '?';
        assert.equal(sepWithQuery, '&');

        const baseUrlWithoutQuery = 'https://wa.me/919876543210';
        const sepWithoutQuery = baseUrlWithoutQuery.includes('?') ? '&' : '?';
        assert.equal(sepWithoutQuery, '?');
    });

    test('should encode emojis, special characters, and newlines safely for WhatsApp URL parameter', () => {
        const rawMessage = "Hello 👋, I'd like to order:\n1. *Product & Test*\nPrice: ₹ 100\n\n⚡ Notes & Details";
        const encoded = encodeURIComponent(rawMessage);

        assert.ok(!encoded.includes('\n'), 'Encoded text must not contain raw newlines');
        assert.ok(!encoded.includes(' '), 'Encoded text must not contain unencoded spaces');
        assert.ok(encoded.includes('%F0%9F%91%8B'), 'Hand wave emoji should be properly percent-encoded');
        assert.ok(encoded.includes('%E2%9A%A1'), 'Lightning bolt emoji should be properly percent-encoded');

        // Decoding should restore the exact original message
        assert.equal(decodeURIComponent(encoded), rawMessage);
    });
});
