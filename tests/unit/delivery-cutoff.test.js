const {test, describe, beforeEach} = require('node:test');
const assert = require('node:assert/strict');
const waCart = require('../../assets/js/wa_cart.js');

describe('Delivery Cutoff Windows & Estimates', () => {
    beforeEach(() => {
        waCart.setRuntimeConfig({
            timezoneOffset: 5.5, // Indian Standard Time (+05:30)
            cutoffMessages: {
                weekend: 'Order now to get it by Monday',
                morning: 'Order before 12 noon for same-day delivery',
                afternoon: 'Order now to receive it by tomorrow'
            }
        });
    });

    test('should return morning same-day delivery notice on weekday before 12 PM IST', () => {
        // Wednesday 14 Oct 2026, 09:30 AM IST (UTC: 04:00 AM)
        const wedMorningUtc = new Date(Date.UTC(2026, 9, 14, 4, 0, 0));
        const message = waCart.getDeliveryCutoffMessage(wedMorningUtc);
        const estimate = waCart.getDeliveryEstimate(wedMorningUtc);

        assert.equal(message, 'Order before 12 noon for same-day delivery');
        assert.equal(estimate, 'Today (Same-day)');
    });

    test('should return tomorrow delivery notice on weekday after 12 PM IST', () => {
        // Wednesday 14 Oct 2026, 03:30 PM IST (UTC: 10:00 AM)
        const wedAfternoonUtc = new Date(Date.UTC(2026, 9, 14, 10, 0, 0));
        const message = waCart.getDeliveryCutoffMessage(wedAfternoonUtc);
        const estimate = waCart.getDeliveryEstimate(wedAfternoonUtc);

        assert.match(message, /Order now to receive it by tomorrow/);
        assert.match(message, /15 Oct/);
        assert.equal(estimate, 'Tomorrow (15 Oct)');
    });

    test('should return Monday cutoff notice on Saturday afternoon after 12 PM IST', () => {
        // Saturday 17 Oct 2026, 02:00 PM IST (UTC: 08:30 AM)
        const satAfternoonUtc = new Date(Date.UTC(2026, 9, 17, 8, 30, 0));
        const message = waCart.getDeliveryCutoffMessage(satAfternoonUtc);
        const estimate = waCart.getDeliveryEstimate(satAfternoonUtc);

        assert.match(message, /Order now to get it by Monday/);
        assert.match(message, /19 Oct/);
        assert.equal(estimate, 'Monday (19 Oct)');
    });

    test('should return Monday cutoff notice on Sunday anytime', () => {
        // Sunday 18 Oct 2026, 10:00 AM IST (UTC: 04:30 AM)
        const sunMorningUtc = new Date(Date.UTC(2026, 9, 18, 4, 30, 0));
        const message = waCart.getDeliveryCutoffMessage(sunMorningUtc);
        const estimate = waCart.getDeliveryEstimate(sunMorningUtc);

        assert.match(message, /Order now to get it by Monday/);
        assert.match(message, /19 Oct/);
        assert.equal(estimate, 'Monday (19 Oct)');
    });

    test('should format Kolkata calendar dates properly with daysAhead offset', () => {
        const baseDate = new Date(Date.UTC(2026, 9, 10, 0, 0, 0)); // 10 Oct
        assert.equal(waCart.getKolkataFormattedDate(baseDate, 1), '11 Oct');
        assert.equal(waCart.getKolkataFormattedDate(baseDate, 2), '12 Oct');
    });
});
