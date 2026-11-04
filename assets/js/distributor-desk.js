/**
 * 🖥️ FRONTEND ASSET: DISTRIBUTOR DESK ENGINE
 * Handles quick rate sheet inline updates, mobile product creation, and retailer PIN directory.
 *
 * @package StatelessWaCommerce
 */

(function () {
    'use strict';

    const root = document.getElementById('swac-desk-root');
    if (!root) return;

    const cfg = window.swacDeskConfig || {};
    const restUrl = cfg.rest_url || '/wp-json/';
    const isWpAdmin = root.dataset.auth === '1';
    let masterToken = sessionStorage.getItem('swac_master_token') || '';
    let allProducts = [];

    // Helper: Auth Headers
    function getHeaders(isJson = true) {
        const headers = {};
        if (cfg.nonce) {
            headers['X-WP-Nonce'] = cfg.nonce;
        }
        if (masterToken) {
            headers['X-SWAC-Master-Token'] = masterToken;
        }
        if (isJson) {
            headers['Content-Type'] = 'application/json';
        }
        return headers;
    }

    // Helper: Toast
    function showToast(msg, duration = 3000) {
        const toast = document.getElementById('swac-desk-toast');
        if (!toast) return;
        toast.textContent = msg;
        toast.classList.remove('swac-desk-hidden');
        setTimeout(() => toast.classList.add('swac-desk-hidden'), duration);
    }

    // Helper: Escapes HTML
    function esc(str) {
        if (!str) return '';
        return String(str).replace(/[&<>"']/g, m => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
        }[m]));
    }

    // ── 1. Authentication & Initialization ────────────────────────────────────
    function initAuth() {
        if (isWpAdmin || masterToken) {
            document.getElementById('swac-desk-login-box')?.classList.add('swac-desk-hidden');
            document.getElementById('swac-desk-app')?.classList.remove('swac-desk-hidden');
            loadProducts();
            loadRetailers();
        }

        const authForm = document.getElementById('swac-desk-auth-form');
        if (authForm) {
            authForm.addEventListener('submit', function (e) {
                e.preventDefault();
                const pinInput = document.getElementById('swac-desk-pin-input');
                const errBox = document.getElementById('swac-desk-auth-error');
                const pin = pinInput ? pinInput.value.trim() : '';

                if (!pin) return;
                if (errBox) errBox.classList.add('swac-desk-hidden');

                fetch(restUrl + 'swac/v1/desk/auth', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ pin })
                })
                .then(res => res.json())
                .then(data => {
                    if (data && data.success && data.token) {
                        masterToken = data.token;
                        sessionStorage.setItem('swac_master_token', masterToken);
                        document.getElementById('swac-desk-login-box').classList.add('swac-desk-hidden');
                        document.getElementById('swac-desk-app').classList.remove('swac-desk-hidden');
                        loadProducts();
                        loadRetailers();
                        showToast('Distributor Desk unlocked.');
                    } else {
                        if (errBox) {
                            errBox.textContent = data.message || 'Incorrect PIN.';
                            errBox.classList.remove('swac-desk-hidden');
                        }
                    }
                })
                .catch(() => {
                    if (errBox) {
                        errBox.textContent = 'Server connection error.';
                        errBox.classList.remove('swac-desk-hidden');
                    }
                });
            });
        }
    }

    // ── 2. Tab Navigation ─────────────────────────────────────────────────────
    function initTabs() {
        const tabs = document.querySelectorAll('.swac-desk-tab-btn');
        tabs.forEach(btn => {
            btn.addEventListener('click', function () {
                const targetId = this.dataset.tab;
                tabs.forEach(t => t.classList.remove('swac-desk-tab-btn--active'));
                this.classList.add('swac-desk-tab-btn--active');

                document.querySelectorAll('.swac-desk-tab-content').forEach(section => {
                    section.classList.remove('swac-desk-tab-content--active');
                });

                if (targetId === 'tab-rates') {
                    document.getElementById('swac-tab-rates')?.classList.add('swac-desk-tab-content--active');
                } else if (targetId === 'tab-add') {
                    document.getElementById('swac-tab-add')?.classList.add('swac-desk-tab-content--active');
                } else if (targetId === 'tab-retailers') {
                    document.getElementById('swac-tab-retailers')?.classList.add('swac-desk-tab-content--active');
                }
            });
        });
    }

    // ── 3. Tab 1: Quick Rate Sheet Engine ─────────────────────────────────────
    function loadProducts(search = '') {
        const tbody = document.getElementById('swac-rates-tbody');
        if (!tbody) return;

        let url = restUrl + 'swac/v1/desk/products';
        if (search) {
            url += '?search=' + encodeURIComponent(search);
        }

        fetch(url, { headers: getHeaders() })
        .then(res => res.json())
        .then(data => {
            if (data && data.success && Array.isArray(data.products)) {
                allProducts = data.products;
                renderRateTable(allProducts);
            } else {
                tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; padding:20px; color:#ef4444;">Failed to load catalog.</td></tr>';
            }
        })
        .catch(() => {
            tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; padding:20px; color:#ef4444;">Network error loading catalog.</td></tr>';
        });
    }

    function renderRateTable(products) {
        const tbody = document.getElementById('swac-rates-tbody');
        if (!tbody) return;

        if (products.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; padding:30px; color:#64748b;">No products found.</td></tr>';
            return;
        }

        tbody.innerHTML = products.map(item => `
            <tr data-id="${item.id}">
                <td>
                    <div class="swac-desk-item-cell">
                        <img src="${esc(item.thumb)}" class="swac-desk-thumb" alt="" loading="lazy">
                        <div>
                            <strong>${esc(item.name)}</strong>
                            ${item.brand ? `<div style="font-size:11px; color:#64748b;">${esc(item.brand)}</div>` : ''}
                            ${item.scheme_badge ? `<span style="display:inline-block; font-size:11px; background:#fffbeb; color:#b45309; padding:1px 5px; border-radius:3px; margin-top:2px;">🎁 ${esc(item.scheme_badge)}</span>` : ''}
                        </div>
                    </div>
                </td>
                <td>
                    <button type="button" class="swac-stock-toggle-btn ${item.in_stock ? 'swac-stock-toggle-btn--in' : 'swac-stock-toggle-btn--out'}" data-id="${item.id}" data-stock="${item.in_stock ? '1' : '0'}">
                        ${item.in_stock ? '🟢 In Stock' : '🔴 Out of Stock'}
                    </button>
                </td>
                <td>
                    <input type="number" step="0.01" class="swac-table-input swac-mrp-input" data-id="${item.id}" value="${item.mrp || ''}">
                </td>
                <td>
                    <input type="number" step="0.01" class="swac-table-input swac-ptr-input" data-id="${item.id}" value="${item.ptr || ''}">
                </td>
                <td>
                    <input type="number" step="1" class="swac-table-input swac-carton-input" data-id="${item.id}" value="${item.carton_pack || ''}" placeholder="—">
                </td>
                <td style="text-align:right;">
                    <button type="button" class="swac-desk-btn swac-desk-btn--primary swac-save-rate-btn" data-id="${item.id}" style="height:32px; padding:0 10px; font-size:12px;">
                        Save
                    </button>
                </td>
            </tr>
        `).join('');
    }

    function initRateTableEvents() {
        const tbody = document.getElementById('swac-rates-tbody');
        if (!tbody) return;

        // 1. Stock Status Toggle
        tbody.addEventListener('click', function (e) {
            const stockBtn = e.target.closest('.swac-stock-toggle-btn');
            if (stockBtn) {
                const id = stockBtn.dataset.id;
                const currentStock = stockBtn.dataset.stock === '1';
                const newStock = !currentStock;

                stockBtn.disabled = true;
                fetch(restUrl + 'swac/v1/desk/quick-update', {
                    method: 'POST',
                    headers: getHeaders(),
                    body: JSON.stringify({ id, in_stock: newStock })
                })
                .then(res => res.json())
                .then(data => {
                    stockBtn.disabled = false;
                    if (data && data.success) {
                        stockBtn.dataset.stock = newStock ? '1' : '0';
                        stockBtn.className = `swac-stock-toggle-btn ${newStock ? 'swac-stock-toggle-btn--in' : 'swac-stock-toggle-btn--out'}`;
                        stockBtn.textContent = newStock ? '🟢 In Stock' : '🔴 Out of Stock';
                        showToast('Stock status updated.');
                    }
                });
                return;
            }

            // 2. Save Rate Button
            const saveBtn = e.target.closest('.swac-save-rate-btn');
            if (saveBtn) {
                saveRowRate(saveBtn.dataset.id);
            }
        });

        // 3. Enter key inside input saves the row
        tbody.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                const input = e.target.closest('.swac-table-input');
                if (input && input.dataset.id) {
                    e.preventDefault();
                    saveRowRate(input.dataset.id);
                }
            }
        });

        // 4. Live Search
        const searchInput = document.getElementById('swac-rates-search');
        if (searchInput) {
            let debounceTimer;
            searchInput.addEventListener('input', function () {
                clearTimeout(debounceTimer);
                const q = this.value.toLowerCase().trim();
                debounceTimer = setTimeout(() => {
                    if (!q) {
                        renderRateTable(allProducts);
                    } else {
                        const filtered = allProducts.filter(p =>
                            (p.name && p.name.toLowerCase().includes(q)) ||
                            (p.brand && p.brand.toLowerCase().includes(q))
                        );
                        renderRateTable(filtered);
                    }
                }, 150);
            });
        }

        // 5. Refresh Button
        document.getElementById('swac-rates-refresh')?.addEventListener('click', () => loadProducts());
    }

    function saveRowRate(productId) {
        const row = document.querySelector(`tr[data-id="${productId}"]`);
        if (!row) return;

        const mrpInput = row.querySelector('.swac-mrp-input');
        const ptrInput = row.querySelector('.swac-ptr-input');
        const cartonInput = row.querySelector('.swac-carton-input');
        const saveBtn = row.querySelector('.swac-save-rate-btn');

        const payload = {
            id: productId,
            mrp: mrpInput ? parseFloat(mrpInput.value) : 0,
            ptr: ptrInput ? parseFloat(ptrInput.value) : 0,
            carton_pack: cartonInput ? parseInt(cartonInput.value, 10) || 0 : 0,
        };

        if (saveBtn) {
            saveBtn.disabled = true;
            saveBtn.textContent = '...';
        }

        fetch(restUrl + 'swac/v1/desk/quick-update', {
            method: 'POST',
            headers: getHeaders(),
            body: JSON.stringify(payload)
        })
        .then(res => res.json())
        .then(data => {
            if (saveBtn) {
                saveBtn.disabled = false;
                saveBtn.textContent = 'Save';
            }
            if (data && data.success) {
                showToast('✓ Saved: Rate updated');
            } else {
                showToast('Failed to save rate: ' + (data.message || ''));
            }
        })
        .catch(() => {
            if (saveBtn) {
                saveBtn.disabled = false;
                saveBtn.textContent = 'Save';
            }
            showToast('Network error saving rate.');
        });
    }

    // ── 4. Tab 2: Fast Product Creation Form ──────────────────────────────────
    function initAddProductForm() {
        const form = document.getElementById('swac-add-product-form');
        if (!form) return;

        // Scheme Radio Toggles
        const schemeRadios = form.querySelectorAll('input[name="scheme_type"]');
        const subfieldsBox = document.getElementById('swac-scheme-subfields');
        schemeRadios.forEach(radio => {
            radio.addEventListener('change', function () {
                const val = this.value;
                if (val === 'none') {
                    subfieldsBox?.classList.add('swac-desk-hidden');
                } else {
                    subfieldsBox?.classList.remove('swac-desk-hidden');
                    ['free_goods', 'tier_discount', 'cash_discount', 'custom'].forEach(t => {
                        const el = document.getElementById('swac-sub-' + t);
                        if (el) el.classList.toggle('swac-desk-hidden', t !== val);
                    });
                }
            });
        });

        // Photo Preview
        const imgInput = document.getElementById('swac-add-image');
        const previewBox = document.getElementById('swac-image-preview');
        if (imgInput && previewBox) {
            imgInput.addEventListener('change', function () {
                previewBox.innerHTML = '';
                const file = this.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function (e) {
                        previewBox.innerHTML = `<img src="${e.target.result}" style="width:70px; height:70px; border-radius:6px; object-fit:cover; border:1px solid #cbd5e1;">`;
                    };
                    reader.readAsDataURL(file);
                }
            });
        }

        // Form Submit
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            const submitBtn = document.getElementById('swac-add-submit-btn');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.textContent = 'Publishing...';
            }

            const formData = new FormData(form);
            fetch(restUrl + 'swac/v1/desk/create-product', {
                method: 'POST',
                headers: getHeaders(false), // multipart
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = '➕ Save & Publish to Wholesale Catalog';
                }
                if (data && data.success) {
                    form.reset();
                    if (previewBox) previewBox.innerHTML = '';
                    subfieldsBox?.classList.add('swac-desk-hidden');
                    showToast('🎉 ' + data.message);
                    loadProducts(); // refresh catalog
                } else {
                    showToast('Failed to add product: ' + (data.message || ''));
                }
            })
            .catch(() => {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = '➕ Save & Publish to Wholesale Catalog';
                }
                showToast('Network error creating product.');
            });
        });
    }

    // ── 5. Tab 3: Approved Retailers Desk ─────────────────────────────────────
    function loadRetailers() {
        const tbody = document.getElementById('swac-retailers-tbody');
        if (!tbody) return;

        fetch(restUrl + 'swac/v1/desk/retailers', { headers: getHeaders() })
        .then(res => res.json())
        .then(data => {
            if (data && data.success && Array.isArray(data.retailers)) {
                renderRetailersTable(data.retailers);
            }
        });
    }

    function renderRetailersTable(retailers) {
        const tbody = document.getElementById('swac-retailers-tbody');
        if (!tbody) return;

        if (retailers.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; padding:20px; color:#64748b;">No approved retailers registered yet.</td></tr>';
            return;
        }

        tbody.innerHTML = retailers.map(r => `
            <tr data-phone="${esc(r.phone)}">
                <td><strong>${esc(r.store_name)}</strong>${r.owner_name ? `<div style="font-size:11px; color:#64748b;">${esc(r.owner_name)}</div>` : ''}</td>
                <td><code>${esc(r.phone)}</code></td>
                <td><strong>${esc(r.pin)}</strong></td>
                <td>${esc(r.area || '—')}</td>
                <td><span style="color:${r.status === 'active' ? '#15803d' : '#b91c1c'}; font-weight:700;">${r.status === 'active' ? 'Active' : 'Suspended'}</span></td>
                <td style="text-align:right;">
                    <button type="button" class="swac-desk-btn swac-delete-retailer-btn" data-phone="${esc(r.phone)}" style="height:28px; padding:0 8px; font-size:11px; background:#fef2f2; color:#b91c1c; border:1px solid #fecaca;">✕</button>
                </td>
            </tr>
        `).join('');
    }

    function initRetailerEvents() {
        const tbody = document.getElementById('swac-retailers-tbody');
        if (tbody) {
            tbody.addEventListener('click', function (e) {
                const delBtn = e.target.closest('.swac-delete-retailer-btn');
                if (delBtn) {
                    const phone = delBtn.dataset.phone;
                    if (confirm(`Remove retailer ${phone}?`)) {
                        fetch(restUrl + 'swac/v1/desk/delete-retailer', {
                            method: 'POST',
                            headers: getHeaders(),
                            body: JSON.stringify({ phone })
                        })
                        .then(res => res.json())
                        .then(data => {
                            if (data && data.success) {
                                showToast('Retailer removed.');
                                loadRetailers();
                            }
                        });
                    }
                }
            });
        }

        const addForm = document.getElementById('swac-add-retailer-form');
        if (addForm) {
            addForm.addEventListener('submit', function (e) {
                e.preventDefault();
                const formData = new FormData(addForm);
                const payload = Object.fromEntries(formData.entries());

                fetch(restUrl + 'swac/v1/desk/save-retailer', {
                    method: 'POST',
                    headers: getHeaders(),
                    body: JSON.stringify(payload)
                })
                .then(res => res.json())
                .then(data => {
                    if (data && data.success) {
                        addForm.reset();
                        showToast('✓ Retailer approved & PIN activated!');
                        loadRetailers();
                    } else {
                        showToast('Error: ' + (data.message || ''));
                    }
                });
            });
        }

        document.getElementById('swac-retailers-refresh')?.addEventListener('click', () => loadRetailers());
    }

    // ── Bootstrap Desk ────────────────────────────────────────────────────────
    initAuth();
    initTabs();
    initRateTableEvents();
    initAddProductForm();
    initRetailerEvents();

})();
