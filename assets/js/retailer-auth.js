/**
 * 🔒 WHOLESALE / B2B MODULE: RETAILER AUTH & PRICE GATING
 *
 * Manages the mobile + 4-digit PIN authentication modal, client-side
 * .swac-retailer-auth class toggling, and active retailer status pill.
 *
 * @package StatelessWaCommerce
 */
(function () {
    'use strict';

    const RETAILER_STORAGE_KEY = 'swac_b2b_retailer';

    function getSavedRetailer() {
        try {
            const raw = localStorage.getItem(RETAILER_STORAGE_KEY);
            return raw ? JSON.parse(raw) : null;
        } catch (e) {
            return null;
        }
    }

    function showToast(msg) {
        if (typeof window.swacShowToast === 'function') {
            window.swacShowToast(msg);
            return;
        }
        const toast = document.getElementById('swac-toast');
        if (toast) {
            toast.textContent = msg;
            toast.style.display = 'block';
            setTimeout(() => { toast.style.display = 'none'; }, 3000);
        }
    }

    function syncRetailerUI() {
        const retailer = getSavedRetailer();
        const root = document.documentElement;
        const body = document.body;
        const pill = document.getElementById('swac-retailer-pill');
        const pillName = document.getElementById('swac-pill-store-name');

        if (retailer && retailer.token) {
            root.classList.add('swac-retailer-auth');
            if (body) body.classList.add('swac-retailer-auth');
            if (pill) pill.classList.remove('swac-b2b-modal-hidden');
            if (pillName) pillName.textContent = `🏪 ${retailer.store_name || 'Retailer'}`;
        } else {
            root.classList.remove('swac-retailer-auth');
            if (body) body.classList.remove('swac-retailer-auth');
            if (pill) pill.classList.add('swac-b2b-modal-hidden');
        }
    }

    function openRetailerModal() {
        const modal = document.getElementById('swac-retailer-modal');
        if (modal) {
            modal.classList.remove('swac-b2b-modal-hidden');
            modal.setAttribute('aria-hidden', 'false');
            const phoneInput = document.getElementById('swac-input-phone');
            if (phoneInput) setTimeout(() => phoneInput.focus(), 100);
        }
    }

    function closeRetailerModal() {
        const modal = document.getElementById('swac-retailer-modal');
        if (modal) {
            modal.classList.add('swac-b2b-modal-hidden');
            modal.setAttribute('aria-hidden', 'true');
            const alertEl = document.getElementById('swac-login-msg');
            if (alertEl) alertEl.classList.add('swac-b2b-modal-hidden');
        }
    }

    function bindRetailerAuthEvents() {
        document.addEventListener('click', function (e) {
            const trigger = e.target.closest('.swac-b2b-gate-trigger');
            if (trigger) {
                e.preventDefault();
                openRetailerModal();
                return;
            }

            if (e.target.closest('#swac-modal-close')) {
                e.preventDefault();
                closeRetailerModal();
                return;
            }

            const modal = document.getElementById('swac-retailer-modal');
            if (modal && e.target === modal) {
                closeRetailerModal();
                return;
            }

            if (e.target.closest('#swac-pill-logout')) {
                e.preventDefault();
                try {
                    localStorage.removeItem(RETAILER_STORAGE_KEY);
                } catch (err) {}
                syncRetailerUI();
                showToast('Signed out. Wholesale rates locked.');
                return;
            }
        });

        const form = document.getElementById('swac-retailer-login-form');
        if (form) {
            form.addEventListener('submit', function (e) {
                e.preventDefault();
                const phoneInput = document.getElementById('swac-input-phone');
                const pinInput = document.getElementById('swac-input-pin');
                const alertEl = document.getElementById('swac-login-msg');
                const submitBtn = document.getElementById('swac-login-submit');

                const phone = phoneInput ? phoneInput.value.trim() : '';
                const pin = pinInput ? pinInput.value.trim() : '';

                if (phone.length < 10 || !pin) {
                    if (alertEl) {
                        alertEl.textContent = 'Please enter your 10-digit mobile number and PIN.';
                        alertEl.classList.remove('swac-b2b-modal-hidden');
                    }
                    return;
                }

                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.textContent = 'Verifying...';
                }
                if (alertEl) alertEl.classList.add('swac-b2b-modal-hidden');

                const endpoint = (window.swacConfig && window.swacConfig.rest_url)
                    ? window.swacConfig.rest_url + 'swac/v1/retailer-login'
                    : '/wp-json/swac/v1/retailer-login';

                fetch(endpoint, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ phone: phone, pin: pin })
                })
                .then(res => res.json())
                .then(data => {
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.textContent = 'Unlock Wholesale Rates';
                    }
                    if (data && data.success && data.retailer) {
                        try {
                            localStorage.setItem(RETAILER_STORAGE_KEY, JSON.stringify(data.retailer));
                        } catch (err) {}
                        syncRetailerUI();
                        closeRetailerModal();
                        showToast(`Wholesale rates unlocked for ${data.retailer.store_name || 'Retailer'}!`);
                    } else {
                        if (alertEl) {
                            alertEl.textContent = (data && data.message) ? data.message : 'Login failed. Please check credentials.';
                            alertEl.classList.remove('swac-b2b-modal-hidden');
                        }
                    }
                })
                .catch(() => {
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.textContent = 'Unlock Wholesale Rates';
                    }
                    if (alertEl) {
                        alertEl.textContent = 'Network error during verification. Please try again.';
                        alertEl.classList.remove('swac-b2b-modal-hidden');
                    }
                });
            });
        }
    }

    // Expose sync globally so other components can trigger it
    window.swacSyncRetailerUI = syncRetailerUI;

    // Run when script executes
    if (typeof window !== 'undefined' && typeof document !== 'undefined') {
        syncRetailerUI();
        bindRetailerAuthEvents();
    }
})();
