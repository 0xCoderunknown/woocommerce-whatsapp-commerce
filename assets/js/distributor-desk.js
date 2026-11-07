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
    let masterToken = sessionStorage.getItem('swac_master_token') || localStorage.getItem('swac_b2b_master_token') || '';
    let userRole = isWpAdmin ? 'owner' : (localStorage.getItem('swac_desk_role') || 'owner');
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

    // Role UI Application
    function applyRoleUI() {
        const badge = document.getElementById('swac-desk-role-badge');
        if (userRole === 'staff') {
            root.classList.add('swac-desk-role--staff');
            root.classList.remove('swac-desk-role--owner');
            if (badge) {
                badge.textContent = '📦 Staff (Stock Control)';
                badge.className = 'swac-desk-badge swac-desk-badge--staff';
            }
        } else {
            root.classList.add('swac-desk-role--owner');
            root.classList.remove('swac-desk-role--staff');
            if (badge) {
                badge.textContent = isWpAdmin ? '👑 Admin (Owner)' : '👑 Owner (Full Access)';
                badge.className = 'swac-desk-badge';
            }
        }
    }

    // ── 1. Authentication & Initialization ────────────────────────────────────
    function initAuth() {
        if (isWpAdmin || masterToken) {
            document.getElementById('swac-desk-login-box')?.classList.add('swac-desk-hidden');
            document.getElementById('swac-desk-app')?.classList.remove('swac-desk-hidden');
            applyRoleUI();
            loadProducts();
            if (userRole === 'owner') {
                loadRetailers();
                loadSettings();
            }
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
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({pin})
                })
                    .then(res => res.json())
                    .then(data => {
                        if (data && data.success && data.token) {
                            masterToken = data.token;
                            userRole = data.role || 'owner';
                            sessionStorage.setItem('swac_master_token', masterToken);
                            localStorage.setItem('swac_b2b_master_token', masterToken);
                            localStorage.setItem('swac_desk_role', userRole);

                            document.getElementById('swac-desk-login-box')?.classList.add('swac-desk-hidden');
                            document.getElementById('swac-desk-app')?.classList.remove('swac-desk-hidden');
                            applyRoleUI();
                            loadProducts();
                            if (userRole === 'owner') {
                                loadRetailers();
                                loadSettings();
                            }
                            showToast(userRole === 'owner' ? 'Owner Desk unlocked.' : 'Warehouse Staff Desk unlocked.');
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
        const lockBtn = document.getElementById('swac-desk-lock-btn');
        if (lockBtn) {
            lockBtn.addEventListener('click', function () {
                if (masterToken) {
                    fetch(restUrl + 'swac/v1/desk/logout', {
                        method: 'POST',
                        headers: getHeaders(true)
                    }).catch(() => {});
                }
                masterToken = '';
                userRole = isWpAdmin ? 'owner' : '';
                sessionStorage.removeItem('swac_master_token');
                localStorage.removeItem('swac_b2b_master_token');
                localStorage.removeItem('swac_desk_role');
                root.classList.remove('swac-desk-role--staff', 'swac-desk-role--owner');
                document.getElementById('swac-desk-app')?.classList.add('swac-desk-hidden');
                document.getElementById('swac-desk-login-box')?.classList.remove('swac-desk-hidden');
                const pinInput = document.getElementById('swac-desk-pin-input');
                if (pinInput) pinInput.value = '';
                showToast('Distributor Desk locked.');
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
                } else if (targetId === 'tab-settings') {
                    document.getElementById('swac-tab-settings')?.classList.add('swac-desk-tab-content--active');
                    loadSettings();
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

        fetch(url, {headers: getHeaders()})
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
            tbody.innerHTML = '<tr><td colspan="7" class="swac-rates-empty-cell" style="text-align:center; padding:48px 20px; color:#64748b; font-size:0.95rem;">🔍 No products found matching your search.</td></tr>';
            return;
        }

        const isStaff = userRole === 'staff';
        const disabledAttr = isStaff ? ' disabled readonly tabindex="-1"' : '';

        tbody.innerHTML = products.map(item => `
            <tr data-id="${item.id}" class="swac-fast-row">
                <td class="swac-col-product">
                    <div class="swac-desk-item-cell">
                        <img src="${esc(item.thumb)}" class="swac-desk-thumb" alt="" loading="lazy">
                        <div class="swac-desk-item-info">
                            <strong class="swac-desk-item-title">${esc(item.name)}</strong>
                            ${item.brand ? `<div class="swac-desk-item-brand">${esc(item.brand)}</div>` : ''}
                        </div>
                    </div>
                </td>
                <td class="swac-col-stock">
                    <button type="button" 
                            class="swac-stock-toggle-btn ${item.in_stock ? 'swac-stock-toggle-btn--in' : 'swac-stock-toggle-btn--out'}" 
                            data-id="${item.id}" 
                            data-stock="${item.in_stock ? '1' : '0'}"
                            title="Click to toggle stock status">
                        ${item.in_stock ? '🟢 IN STOCK' : '🔴 OUT OF STOCK'}
                    </button>
                </td>
                <td class="swac-col-ptr">
                    <input type="number" 
                           step="0.01" 
                           min="0" 
                           class="swac-table-input swac-fast-input swac-ptr-input" 
                           data-id="${item.id}" 
                           data-field="ptr" 
                           data-last-saved="${item.ptr || ''}" 
                           value="${item.ptr || ''}" 
                           placeholder="0.00"${disabledAttr}
                           title="Base Wholesale Rate (PTR)">
                </td>
                <td class="swac-col-mrp">
                    <input type="number" 
                           step="0.01" 
                           min="0" 
                           class="swac-table-input swac-fast-input swac-mrp-input" 
                           data-id="${item.id}" 
                           data-field="mrp" 
                           data-last-saved="${item.mrp || ''}" 
                           value="${item.mrp || ''}" 
                           placeholder="0.00"${disabledAttr}
                           title="Retail MRP">
                </td>
                <td class="swac-col-scheme">
                    <input type="text" 
                           class="swac-table-input swac-fast-input swac-scheme-input" 
                           data-id="${item.id}" 
                           data-field="scheme_shorthand" 
                           data-last-saved="${esc(item.scheme_shorthand || '')}" 
                           value="${esc(item.scheme_shorthand || '')}" 
                           placeholder="e.g. 10+1"${disabledAttr}
                           title="Scheme (e.g. 10+1, 10:2%, 2% CD)">
                </td>
                <td class="swac-col-moq">
                    <input type="number" 
                           step="1" 
                           min="1" 
                           class="swac-table-input swac-fast-input swac-moq-input" 
                           data-id="${item.id}" 
                           data-field="moq" 
                           data-last-saved="${item.moq || '1'}" 
                           value="${item.moq || '1'}" 
                           placeholder="1"${disabledAttr}
                           title="Minimum Order Quantity">
                </td>
                <td class="swac-col-status" style="text-align:right;">
                    <span class="swac-row-sync-status" data-id="${item.id}"></span>
                </td>
            </tr>
        `).join('');
    }

    // Helper: Fast In-Place Cell Auto-Save
    function saveCell(input) {
        if (!input || !input.dataset.id || !input.dataset.field) return;

        const productId = input.dataset.id;
        const field = input.dataset.field;
        const value = (input.value || '').trim();
        const lastSaved = (input.dataset.lastSaved || '').trim();

        if (value === lastSaved) {
            return; // No change, skip network request
        }

        const row = input.closest('tr');
        const statusSpan = row ? row.querySelector('.swac-row-sync-status') : null;

        if (statusSpan) {
            statusSpan.innerHTML = '<span class="swac-sync-badge swac-sync-saving">Saving...</span>';
        }

        fetch(restUrl + 'swac/v1/desk/fast-row-update', {
            method: 'POST',
            headers: getHeaders(),
            body: JSON.stringify({
                id: productId,
                field: field,
                value: value
            })
        })
            .then(res => res.json())
            .then(data => {
                if (data && data.success) {
                    input.dataset.lastSaved = value;

                    // Update local cache allProducts
                    const item = allProducts.find(p => String(p.id) === String(productId));
                    if (item) {
                        if (field === 'ptr') item.ptr = parseFloat(value) || 0;
                        else if (field === 'mrp') item.mrp = parseFloat(value) || 0;
                        else if (field === 'scheme_shorthand') item.scheme_shorthand = value;
                        else if (field === 'moq') item.moq = parseInt(value, 10) || 1;
                    }

                    if (statusSpan) {
                        statusSpan.innerHTML = '<span class="swac-sync-badge swac-sync-saved">Saved ✓</span>';
                        clearTimeout(statusSpan._timer);
                        statusSpan._timer = setTimeout(() => {
                            if (statusSpan.textContent.includes('Saved')) {
                                statusSpan.innerHTML = '';
                            }
                        }, 1800);
                    }
                } else {
                    if (statusSpan) {
                        statusSpan.innerHTML = '<span class="swac-sync-badge swac-sync-error">⚠️ Error</span>';
                    }
                    showToast('Failed to save ' + field + ': ' + (data && data.message ? data.message : 'Error'));
                }
            })
            .catch(() => {
                if (statusSpan) {
                    statusSpan.innerHTML = '<span class="swac-sync-badge swac-sync-error">⚠️ Error</span>';
                }
                showToast('Network error saving ' + field + '.');
            });
    }

    // Helper: 1-Tap Stock Pill Toggle
    function toggleStock(stockBtn) {
        if (!stockBtn || !stockBtn.dataset.id) return;

        const id = stockBtn.dataset.id;
        const currentStock = stockBtn.dataset.stock === '1';
        const newStock = !currentStock;
        const row = stockBtn.closest('tr');
        const statusSpan = row ? row.querySelector('.swac-row-sync-status') : null;

        // Optimistic UI toggle
        stockBtn.disabled = true;
        stockBtn.dataset.stock = newStock ? '1' : '0';
        stockBtn.className = 'swac-stock-toggle-btn ' + (newStock ? 'swac-stock-toggle-btn--in' : 'swac-stock-toggle-btn--out');
        stockBtn.textContent = newStock ? '🟢 IN STOCK' : '🔴 OUT OF STOCK';

        if (statusSpan) {
            statusSpan.innerHTML = '<span class="swac-sync-badge swac-sync-saving">Saving...</span>';
        }

        fetch(restUrl + 'swac/v1/desk/fast-row-update', {
            method: 'POST',
            headers: getHeaders(),
            body: JSON.stringify({
                id: id,
                field: 'in_stock',
                value: newStock
            })
        })
            .then(res => res.json())
            .then(data => {
                stockBtn.disabled = false;
                if (data && data.success) {
                    const item = allProducts.find(p => String(p.id) === String(id));
                    if (item) {
                        item.in_stock = newStock;
                    }

                    if (statusSpan) {
                        statusSpan.innerHTML = '<span class="swac-sync-badge swac-sync-saved">Saved ✓</span>';
                        clearTimeout(statusSpan._timer);
                        statusSpan._timer = setTimeout(() => {
                            if (statusSpan.textContent.includes('Saved')) {
                                statusSpan.innerHTML = '';
                            }
                        }, 1800);
                    }
                } else {
                    // Revert on failure
                    stockBtn.dataset.stock = currentStock ? '1' : '0';
                    stockBtn.className = 'swac-stock-toggle-btn ' + (currentStock ? 'swac-stock-toggle-btn--in' : 'swac-stock-toggle-btn--out');
                    stockBtn.textContent = currentStock ? '🟢 IN STOCK' : '🔴 OUT OF STOCK';
                    if (statusSpan) {
                        statusSpan.innerHTML = '<span class="swac-sync-badge swac-sync-error">⚠️ Error</span>';
                    }
                    showToast('Failed to update stock: ' + (data && data.message ? data.message : 'Error'));
                }
            })
            .catch(() => {
                stockBtn.disabled = false;
                stockBtn.dataset.stock = currentStock ? '1' : '0';
                stockBtn.className = 'swac-stock-toggle-btn ' + (currentStock ? 'swac-stock-toggle-btn--in' : 'swac-stock-toggle-btn--out');
                stockBtn.textContent = currentStock ? '🟢 IN STOCK' : '🔴 OUT OF STOCK';
                if (statusSpan) {
                    statusSpan.innerHTML = '<span class="swac-sync-badge swac-sync-error">⚠️ Error</span>';
                }
                showToast('Network error updating stock.');
            });
    }

    function initRateTableEvents() {
        const tbody = document.getElementById('swac-rates-tbody');
        if (!tbody) return;

        // 1. 1-Tap Stock Pill Button Click
        tbody.addEventListener('click', function (e) {
            const stockBtn = e.target.closest('.swac-stock-toggle-btn');
            if (stockBtn) {
                toggleStock(stockBtn);
            }
        });

        // 2. Auto-save on blur / focusout for in-place inputs
        tbody.addEventListener('focusout', function (e) {
            const input = e.target.closest('.swac-fast-input');
            if (input) {
                saveCell(input);
            }
        });

        // 3. Auto-save on change event
        tbody.addEventListener('change', function (e) {
            const input = e.target.closest('.swac-fast-input');
            if (input) {
                saveCell(input);
            }
        });

        // 4. Enter key inside input commits and blurs
        tbody.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                const input = e.target.closest('.swac-fast-input');
                if (input) {
                    e.preventDefault();
                    input.blur();
                }
            }
        });

        // 5. Instant Live Search (DOM Filter)
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
                }, 100);
            });
        }

        // 5. Refresh Button
        document.getElementById('swac-rates-refresh')?.addEventListener('click', () => loadProducts());

        // 6. CSV Import & Export
        const importBtn = document.getElementById('swac-rates-import-btn');
        const importInput = document.getElementById('swac-rates-import-file');
        const exportBtn = document.getElementById('swac-rates-export-btn');

        if (importBtn && importInput) {
            importBtn.addEventListener('click', () => importInput.click());
        }

        if (importInput) {
            importInput.addEventListener('change', function () {
                const file = this.files[0];
                if (!file) return;

                const formData = new FormData();
                formData.append('file', file);

                showToast('Syncing rates from CSV...');
                fetch(restUrl + 'swac/v1/desk/import-csv', {
                    method: 'POST',
                    headers: getHeaders(false),
                    body: formData
                })
                    .then(res => res.json())
                    .then(data => {
                        importInput.value = '';
                        if (data && data.success) {
                            showToast('✓ ' + data.message);
                            loadProducts();
                        } else {
                            showToast('Failed to import CSV: ' + (data.message || ''));
                        }
                    })
                    .catch(() => {
                        importInput.value = '';
                        showToast('Network error uploading CSV.');
                    });
            });
        }

        if (exportBtn) {
            exportBtn.addEventListener('click', function () {
                showToast('Preparing wholesale rates CSV export...');
                fetch(restUrl + 'swac/v1/desk/export-csv', {
                    method: 'GET',
                    headers: getHeaders(false)
                })
                    .then(res => {
                        if (!res.ok) throw new Error('Export error');
                        return res.blob();
                    })
                    .then(blob => {
                        const url = window.URL.createObjectURL(blob);
                        const a = document.createElement('a');
                        a.style.display = 'none';
                        a.href = url;
                        a.download = `wholesale-rates-${new Date().toISOString().slice(0, 10)}.csv`;
                        document.body.appendChild(a);
                        a.click();
                        setTimeout(() => {
                            window.URL.revokeObjectURL(url);
                            a.remove();
                        }, 1000);
                        showToast('✓ CSV Downloaded.');
                    })
                    .catch(() => {
                        showToast('Failed to download CSV.');
                    });
            });
        }
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

        fetch(restUrl + 'swac/v1/desk/retailers', {headers: getHeaders()})
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
                            body: JSON.stringify({phone})
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

    // ── 6. Tab 4: Store Settings Engine ───────────────────────────────────────
    function loadSettings() {
        fetch(restUrl + 'swac/v1/desk/settings', {
            method: 'GET',
            headers: getHeaders(false)
        })
            .then(res => res.json())
            .then(data => {
                if (data && data.success && data.settings) {
                    const s = data.settings;
                    const phoneEl = document.getElementById('swac-set-phone');
                    const nameEl = document.getElementById('swac-set-store-name');
                    const cityEl = document.getElementById('swac-set-city');
                    const freeShipEl = document.getElementById('swac-set-free-ship');
                    const shipChargeEl = document.getElementById('swac-set-ship-charge');
                    const disclaimerEl = document.getElementById('swac-set-disclaimer');
                    const pinEl = document.getElementById('swac-set-master-pin');
                    const staffPinEl = document.getElementById('swac-set-staff-pin');

                    if (phoneEl) phoneEl.value = s.phone_number || '';
                    if (nameEl) nameEl.value = s.store_name || '';
                    if (cityEl) cityEl.value = s.city_name || '';
                    if (freeShipEl) freeShipEl.value = s.free_shipping_at || 0;
                    if (shipChargeEl) shipChargeEl.value = s.shipping_charge || 0;
                    if (disclaimerEl) disclaimerEl.value = s.order_disclaimer || '';
                    if (pinEl) pinEl.value = '';
                    if (staffPinEl) staffPinEl.value = '';
                }
            })
            .catch(() => {});
    }

    function initSettingsEvents() {
        const settingsForm = document.getElementById('swac-desk-settings-form');
        if (settingsForm) {
            settingsForm.addEventListener('submit', function (e) {
                e.preventDefault();
                const saveBtn = document.getElementById('swac-settings-save-btn');
                const origText = saveBtn ? saveBtn.textContent : '';
                if (saveBtn) {
                    saveBtn.disabled = true;
                    saveBtn.textContent = 'Saving...';
                }

                const payload = {
                    phone_number: document.getElementById('swac-set-phone')?.value.trim() || '',
                    store_name: document.getElementById('swac-set-store-name')?.value.trim() || '',
                    city_name: document.getElementById('swac-set-city')?.value.trim() || '',
                    free_shipping_at: parseFloat(document.getElementById('swac-set-free-ship')?.value) || 0,
                    shipping_charge: parseFloat(document.getElementById('swac-set-ship-charge')?.value) || 0,
                    order_disclaimer: document.getElementById('swac-set-disclaimer')?.value.trim() || '',
                    master_pin: document.getElementById('swac-set-master-pin')?.value.trim() || '',
                    staff_pin: document.getElementById('swac-set-staff-pin')?.value.trim() || ''
                };

                fetch(restUrl + 'swac/v1/desk/settings', {
                    method: 'POST',
                    headers: getHeaders(true),
                    body: JSON.stringify(payload)
                })
                    .then(res => res.json())
                    .then(data => {
                        if (saveBtn) {
                            saveBtn.disabled = false;
                            saveBtn.textContent = origText;
                        }
                        if (data && data.success) {
                            showToast(data.message || 'Store settings saved.');
                            const pinEl = document.getElementById('swac-set-master-pin');
                            const staffPinEl = document.getElementById('swac-set-staff-pin');
                            if (pinEl) pinEl.value = '';
                            if (staffPinEl) staffPinEl.value = '';
                        } else {
                            showToast('Error: ' + (data.message || ''));
                        }
                    })
                    .catch(() => {
                        if (saveBtn) {
                            saveBtn.disabled = false;
                            saveBtn.textContent = origText;
                        }
                        showToast('Server connection error.');
                    });
            });
        }
    }

    // ── Bootstrap Desk ────────────────────────────────────────────────────────
    initAuth();
    initTabs();
    initRateTableEvents();
    initAddProductForm();
    initRetailerEvents();
    initSettingsEvents();

})();
