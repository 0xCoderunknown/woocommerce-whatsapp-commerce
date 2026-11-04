/**
 * 🔒 WHOLESALE / B2B MODULE: RETAILER AUTH & PRICE GATING
 *
 * Manages the mobile + 4-digit PIN authentication modal, Shikhar-style Walled Garden
 * gate form, WhatsApp direct-invite auto-login, and client-side .swac-retailer-auth toggling.
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
            setTimeout(() => { toast.style.display = 'none'; }, 3500);
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

        // Notify cart drawer to refresh dispatch UI if already open
        if (typeof window.swacRefreshCartUI === 'function') {
            window.swacRefreshCartUI();
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

    function executeRetailerLogin(phone, pin, callbacks) {
        callbacks = callbacks || {};
        const endpoint = (window.swacConfig && window.swacConfig.rest_url)
            ? window.swacConfig.rest_url + 'swac/v1/retailer-login'
            : '/wp-json/swac/v1/retailer-login';

        fetch(endpoint, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({phone: phone, pin: pin})
        })
            .then(res => res.json())
            .then(data => {
                if (data && data.success && data.retailer) {
                    try {
                        localStorage.setItem(RETAILER_STORAGE_KEY, JSON.stringify(data.retailer));
                    } catch (err) {}
                    syncRetailerUI();
                    if (typeof callbacks.onSuccess === 'function') {
                        callbacks.onSuccess(data.retailer);
                    }
                } else {
                    const msg = (data && data.message) ? data.message : 'Login failed. Please check credentials.';
                    if (typeof callbacks.onError === 'function') {
                        callbacks.onError(msg);
                    }
                }
            })
            .catch(() => {
                if (typeof callbacks.onError === 'function') {
                    callbacks.onError('Network error during verification. Please try again.');
                }
            })
            .finally(() => {
                if (typeof callbacks.onComplete === 'function') {
                    callbacks.onComplete();
                }
            });
    }

    function checkDirectInviteLogin() {
        try {
            if (typeof window === 'undefined' || !window.location || !window.location.search) {
                return;
            }

            const params = new URLSearchParams(window.location.search);
            const phone = params.get('b2b_login') || params.get('b2b_phone') || params.get('login');
            const pin = params.get('pin') || params.get('b2b_pin');

            if (phone && pin) {
                // Strip credentials from address bar immediately for security
                params.delete('b2b_login');
                params.delete('b2b_phone');
                params.delete('login');
                params.delete('pin');
                params.delete('b2b_pin');
                const cleanQuery = params.toString();
                const cleanUrl = window.location.pathname + (cleanQuery ? '?' + cleanQuery : '') + window.location.hash;
                window.history.replaceState({}, document.title, cleanUrl);

                executeRetailerLogin(phone, pin, {
                    onSuccess: function (retailer) {
                        showToast(`Welcome, ${retailer.store_name || 'Retailer'}! Wholesale catalog unlocked.`);
                    },
                    onError: function (msg) {
                        showToast(msg);
                    }
                });
            }
        } catch (e) {}
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
                showToast('Signed out. Wholesale portal locked.');
                return;
            }
        });

        // 1. Popup Modal Login Form
        const modalForm = document.getElementById('swac-retailer-login-form');
        if (modalForm) {
            modalForm.addEventListener('submit', function (e) {
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

                executeRetailerLogin(phone, pin, {
                    onSuccess: function (retailer) {
                        closeRetailerModal();
                        showToast(`Wholesale catalog unlocked for ${retailer.store_name || 'Retailer'}!`);
                    },
                    onError: function (msg) {
                        if (alertEl) {
                            alertEl.textContent = msg;
                            alertEl.classList.remove('swac-b2b-modal-hidden');
                        }
                    },
                    onComplete: function () {
                        if (submitBtn) {
                            submitBtn.disabled = false;
                            submitBtn.textContent = 'Unlock Wholesale Rates';
                        }
                    }
                });
            });
        }

        // 2. Shikhar-Style Walled Garden Screen Form
        const gateForm = document.getElementById('swac-walled-gate-form');
        if (gateForm) {
            gateForm.addEventListener('submit', function (e) {
                e.preventDefault();
                const phoneInput = document.getElementById('swac-gate-phone');
                const pinInput = document.getElementById('swac-gate-pin');
                const alertEl = document.getElementById('swac-gate-msg');
                const submitBtn = document.getElementById('swac-gate-submit');

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
                    submitBtn.textContent = 'Verifying Credentials...';
                }
                if (alertEl) alertEl.classList.add('swac-b2b-modal-hidden');

                executeRetailerLogin(phone, pin, {
                    onSuccess: function (retailer) {
                        showToast(`Welcome, ${retailer.store_name || 'Retailer'}! Wholesale catalog unlocked.`);
                    },
                    onError: function (msg) {
                        if (alertEl) {
                            alertEl.textContent = msg;
                            alertEl.classList.remove('swac-b2b-modal-hidden');
                        }
                    },
                    onComplete: function () {
                        if (submitBtn) {
                            submitBtn.disabled = false;
                            submitBtn.innerHTML = '<span>Unlock Wholesale Catalog</span> <span aria-hidden="true">→</span>';
                        }
                    }
                });
            });
        }
    }

    // Expose sync globally so other components can trigger it
    window.swacSyncRetailerUI = syncRetailerUI;

    // Run on script execution
    if (typeof window !== 'undefined' && typeof document !== 'undefined') {
        syncRetailerUI();
        checkDirectInviteLogin();
        bindRetailerAuthEvents();
    }
})();
