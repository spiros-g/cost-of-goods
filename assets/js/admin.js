(() => {
    'use strict';

    const root = document.querySelector('.cogs-studio-wrap');
    if (!root || typeof COGSStudio === 'undefined') return;

    const panels = new Map(
        [...root.querySelectorAll('[data-panel]')].map((panel) => [panel.dataset.panel, panel])
    );
    const loaded = new Set();

    const dashboardState = { period: '30', date_from: '', date_to: '' };
    const productState = {
        search: '',
        category: '',
        type: '',
        stock_status: '',
        cogs_state: '',
        margin_max: '',
    };
    const orderState = { status: '', date_from: '', date_to: '' };

    const esc = (value) => String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');

    const money = (value) => {
        const number = Number(value || 0);
        try {
            return new Intl.NumberFormat(undefined, {
                style: 'currency',
                currency: COGSStudio.currency,
                maximumFractionDigits: 2,
            }).format(number);
        } catch (error) {
            return `${number.toFixed(2)} ${COGSStudio.currency}`;
        }
    };

    const percent = (value) => `${Number(value || 0).toFixed(1)}%`;

    async function request(action, data = {}) {
        const body = new URLSearchParams({
            action,
            nonce: COGSStudio.nonce,
            ...data,
        });

        const response = await fetch(COGSStudio.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            body,
        });

        let json;
        try {
            json = await response.json();
        } catch (error) {
            throw new Error(COGSStudio.i18n.error);
        }

        if (!json.success) {
            throw new Error(json?.data?.message || COGSStudio.i18n.error);
        }
        return json.data;
    }

    function errorHtml(error) {
        return `<div class="cogs-studio-inline-notice cogs-studio-inline-notice--error">${esc(error.message || error)}</div>`;
    }

    function pagination(page, totalPages, target) {
        if (totalPages <= 1) return '';
        return `
            <div class="cogs-studio-pagination" data-pagination="${esc(target)}">
                <button type="button" class="button" data-page="${page - 1}" ${page <= 1 ? 'disabled' : ''}>← Previous</button>
                <span>Page ${page} / ${totalPages}</span>
                <button type="button" class="button" data-page="${page + 1}" ${page >= totalPages ? 'disabled' : ''}>Next →</button>
            </div>`;
    }

    function objectOptions(items, selected, placeholder) {
        const rows = Object.entries(items || {}).map(([value, label]) =>
            `<option value="${esc(value)}" ${String(value) === String(selected) ? 'selected' : ''}>${esc(label)}</option>`
        ).join('');
        return `<option value="">${esc(placeholder)}</option>${rows}`;
    }

    function categoryOptions(items, selected) {
        return `<option value="">All categories</option>${(items || []).map((item) =>
            `<option value="${esc(item.id)}" ${String(item.id) === String(selected) ? 'selected' : ''}>${esc(item.name)}</option>`
        ).join('')}`;
    }

    async function loadDashboard() {
        const panel = panels.get('dashboard');
        panel.innerHTML = '<div class="cogs-studio-loading">Loading dashboard…</div>';

        try {
            const data = await request('cogs_studio_dashboard', dashboardState);
            const o = data.orders;
            const i = data.inventory;

            if (!dashboardState.date_from) dashboardState.date_from = data.date_from || '';
            if (!dashboardState.date_to) dashboardState.date_to = data.date_to || '';

            panel.innerHTML = `
                <div class="cogs-studio-section-heading">
                    <div>
                        <h2>${esc(data.label)}</h2>
                        <p>Product sales basis; taxes and shipping are excluded.</p>
                    </div>
                    <div class="cogs-studio-filter-row">
                        <select name="dashboard-period" aria-label="Dashboard period">
                            <option value="7" ${dashboardState.period === '7' ? 'selected' : ''}>7 days</option>
                            <option value="30" ${dashboardState.period === '30' ? 'selected' : ''}>30 days</option>
                            <option value="90" ${dashboardState.period === '90' ? 'selected' : ''}>90 days</option>
                            <option value="custom" ${dashboardState.period === 'custom' ? 'selected' : ''}>Custom</option>
                        </select>
                        <input type="date" name="dashboard-date-from" value="${esc(dashboardState.date_from)}" ${dashboardState.period !== 'custom' ? 'disabled' : ''} aria-label="From date" />
                        <input type="date" name="dashboard-date-to" value="${esc(dashboardState.date_to)}" ${dashboardState.period !== 'custom' ? 'disabled' : ''} aria-label="To date" />
                        <button type="button" class="button" data-dashboard-apply>Apply</button>
                    </div>
                </div>
                <div class="cogs-studio-kpis">
                    <article><span>Net product sales</span><strong>${money(o.revenue)}</strong><small>${Number(o.count)} orders</small></article>
                    <article><span>COGS</span><strong>${money(o.cogs)}</strong><small>Historical item COGS</small></article>
                    <article><span>Gross profit</span><strong>${money(o.profit)}</strong><small>${percent(o.margin)} margin</small></article>
                    <article><span>Inventory cost</span><strong>${money(i.cost)}</strong><small>${Number(i.units).toFixed(0)} units</small></article>
                    <article><span>Inventory retail</span><strong>${money(i.retail_value)}</strong><small>${i.tracked} stock-managed items</small></article>
                    <article><span>Potential profit</span><strong>${money(i.profit)}</strong><small>Current stock × current margin</small></article>
                </div>`;
        } catch (error) {
            panel.innerHTML = errorHtml(error);
        }
    }

    function costModeControl(row) {
        if (!row.is_variation) return '';

        return `
            <select data-cost-mode aria-label="Variation COGS mode">
                <option value="inherit" ${row.cost_mode === 'inherit' ? 'selected' : ''}>Inherit parent</option>
                <option value="override" ${row.cost_mode === 'override' ? 'selected' : ''}>Override</option>
                <option value="additive" ${row.cost_mode === 'additive' ? 'selected' : ''}>Add to parent</option>
            </select>`;
    }

    function ensureProductsShell() {
        const panel = panels.get('products');
        if (panel.dataset.ready) return;

        panel.innerHTML = `
            <div class="cogs-studio-toolbar cogs-studio-toolbar--stack">
                <div class="cogs-studio-filter-row cogs-studio-filter-row--grow">
                    <input type="search" name="cogs-search" placeholder="Search product name or exact SKU…" />
                    <select name="cogs-category"><option value="">All categories</option></select>
                    <select name="cogs-type"><option value="">All types</option></select>
                    <select name="cogs-stock-status"><option value="">All stock statuses</option></select>
                    <select name="cogs-state">
                        <option value="">Any COGS state</option>
                        <option value="undefined">Undefined COGS</option>
                        <option value="defined">Defined COGS</option>
                    </select>
                    <input type="number" min="0" max="100" step="0.1" name="cogs-margin-max" placeholder="Margin ≤ %" />
                    <button type="button" class="button button-primary" data-products-search>Apply filters</button>
                    <button type="button" class="button" data-products-reset>Reset</button>
                </div>
                <div class="cogs-studio-filter-row">
                    <button type="button" class="button" data-csv-export>Export CSV</button>
                    <button type="button" class="button" data-csv-import-trigger>Import CSV</button>
                    <input type="file" accept=".csv,text/csv" data-csv-import hidden />
                    <span class="cogs-studio-help">CSV import accepts product_id/SKU, defined_cost and variation mode.</span>
                </div>
            </div>
            <div data-products-table class="cogs-studio-loading">Loading products…</div>`;
        panel.dataset.ready = '1';
    }

    function syncProductState() {
        const panel = panels.get('products');
        productState.search = panel.querySelector('[name="cogs-search"]')?.value || '';
        productState.category = panel.querySelector('[name="cogs-category"]')?.value || '';
        productState.type = panel.querySelector('[name="cogs-type"]')?.value || '';
        productState.stock_status = panel.querySelector('[name="cogs-stock-status"]')?.value || '';
        productState.cogs_state = panel.querySelector('[name="cogs-state"]')?.value || '';
        productState.margin_max = panel.querySelector('[name="cogs-margin-max"]')?.value || '';
    }

    function hydrateProductFilters(data) {
        const panel = panels.get('products');
        const category = panel.querySelector('[name="cogs-category"]');
        const type = panel.querySelector('[name="cogs-type"]');
        const stock = panel.querySelector('[name="cogs-stock-status"]');

        if (category) category.innerHTML = categoryOptions(data.categories, productState.category);
        if (type) type.innerHTML = objectOptions(data.types, productState.type, 'All types');
        if (stock) stock.innerHTML = objectOptions(data.stock_statuses, productState.stock_status, 'All stock statuses');

        const state = panel.querySelector('[name="cogs-state"]');
        const margin = panel.querySelector('[name="cogs-margin-max"]');
        const search = panel.querySelector('[name="cogs-search"]');
        if (state) state.value = productState.cogs_state;
        if (margin) margin.value = productState.margin_max;
        if (search) search.value = productState.search;
    }

    async function loadProducts(page = 1) {
        const panel = panels.get('products');
        ensureProductsShell();
        syncProductState();

        const target = panel.querySelector('[data-products-table]');
        target.innerHTML = '<div class="cogs-studio-loading">Loading products…</div>';

        try {
            const data = await request('cogs_studio_products', { page, ...productState });
            hydrateProductFilters(data);

            const rows = data.rows.map((row) => `
                <tr data-product-row="${row.id}">
                    <td class="cogs-studio-select-cell"><input type="checkbox" data-product-select value="${row.id}" aria-label="Select ${esc(row.name)}" /></td>
                    <td class="cogs-studio-product-cell">
                        <a href="${esc(row.edit_url)}"><strong>${esc(row.name)}</strong></a>
                        <span>#${row.id}${row.parent_id ? ` · variation of #${row.parent_id}` : ''}</span>
                        ${row.cogs_defined ? '' : '<span class="cogs-studio-warning-text">COGS undefined</span>'}
                    </td>
                    <td>${esc(row.sku || '—')}</td>
                    <td><span class="cogs-studio-badge">${esc(row.type)}</span></td>
                    <td>${money(row.price)}</td>
                    <td class="cogs-studio-cost-editor">
                        ${costModeControl(row)}
                        <input type="number" min="0" step="0.000001" value="${row.nominal_cost === null ? '' : esc(row.nominal_cost)}" data-cost-input ${row.cost_mode === 'inherit' ? 'disabled' : ''} />
                        <button type="button" class="button" data-save-cost>${esc(COGSStudio.i18n.save)}</button>
                    </td>
                    <td data-effective-cost>${money(row.cost)}</td>
                    <td data-profit>${money(row.profit)}</td>
                    <td data-margin>${percent(row.margin)}</td>
                    <td>${row.stock === null ? esc(row.stock_status) : esc(row.stock)}</td>
                </tr>`).join('');

            target.innerHTML = `
                <div class="cogs-studio-list-head">
                    <div class="cogs-studio-table-meta">${data.total} matching products / variations</div>
                    <div class="cogs-studio-bulk-bar">
                        <span data-selection-count>0 selected</span>
                        <input type="number" min="0" step="0.000001" placeholder="Bulk COGS" data-bulk-cost />
                        <button type="button" class="button" data-bulk-set disabled>Set selected</button>
                        <button type="button" class="button" data-bulk-clear disabled>Clear / inherit</button>
                    </div>
                </div>
                <div class="cogs-studio-table-scroll">
                    <table class="widefat striped cogs-studio-table cogs-studio-table--products">
                        <thead><tr><th class="cogs-studio-select-cell"><input type="checkbox" data-select-all aria-label="Select page" /></th><th>Product</th><th>SKU</th><th>Type</th><th>Price</th><th>Defined COGS</th><th>Effective COGS</th><th>Profit</th><th>Margin</th><th>Stock</th></tr></thead>
                        <tbody>${rows || '<tr><td colspan="10">No products found.</td></tr>'}</tbody>
                    </table>
                </div>
                ${pagination(data.page, data.total_pages, 'products')}`;
        } catch (error) {
            target.innerHTML = errorHtml(error);
        }
    }

    function selectedProductIds() {
        return [...panels.get('products').querySelectorAll('[data-product-select]:checked')]
            .map((input) => Number(input.value))
            .filter((id) => id > 0);
    }

    function updateSelectionUi() {
        const panel = panels.get('products');
        const count = selectedProductIds().length;
        const label = panel.querySelector('[data-selection-count]');
        if (label) label.textContent = `${count} selected`;
        panel.querySelectorAll('[data-bulk-set], [data-bulk-clear]').forEach((button) => {
            button.disabled = count === 0;
        });

        const all = panel.querySelectorAll('[data-product-select]');
        const checked = panel.querySelectorAll('[data-product-select]:checked');
        const selectAll = panel.querySelector('[data-select-all]');
        if (selectAll) {
            selectAll.checked = all.length > 0 && checked.length === all.length;
            selectAll.indeterminate = checked.length > 0 && checked.length < all.length;
        }
    }

    async function runBulk(operation) {
        const panel = panels.get('products');
        const ids = selectedProductIds();
        if (!ids.length) return;

        const cost = panel.querySelector('[data-bulk-cost]')?.value || '';
        if (operation === 'set' && cost === '') {
            window.alert('Enter a bulk COGS value first.');
            return;
        }

        const buttons = panel.querySelectorAll('[data-bulk-set], [data-bulk-clear]');
        buttons.forEach((button) => { button.disabled = true; });

        try {
            const data = await request('cogs_studio_bulk_cost', {
                product_ids: JSON.stringify(ids),
                operation,
                cost,
            });
            const details = data.errors?.length ? `\n${data.errors.join('\n')}` : '';
            window.alert(`${data.updated} products updated.${details}`);
            loaded.delete('dashboard');
            loaded.delete('history');
            await loadProducts(1);
        } catch (error) {
            window.alert(error.message || COGSStudio.i18n.error);
        } finally {
            buttons.forEach((button) => { button.disabled = false; });
        }
    }

    function downloadBase64(filename, base64) {
        const binary = atob(base64);
        const bytes = new Uint8Array(binary.length);
        for (let i = 0; i < binary.length; i += 1) bytes[i] = binary.charCodeAt(i);
        const url = URL.createObjectURL(new Blob([bytes], { type: 'text/csv;charset=utf-8' }));
        const link = document.createElement('a');
        link.href = url;
        link.download = filename;
        document.body.appendChild(link);
        link.click();
        link.remove();
        URL.revokeObjectURL(url);
    }

    function ensureOrdersShell() {
        const panel = panels.get('orders');
        if (panel.dataset.ready) return;
        panel.innerHTML = `
            <div class="cogs-studio-section-heading">
                <div><h2>Order profitability</h2><p>Realized orders only. Revenue uses product line totals after item refunds, excluding tax and shipping.</p></div>
            </div>
            <div class="cogs-studio-toolbar">
                <div class="cogs-studio-filter-row">
                    <select name="order-status"><option value="">All realized statuses</option></select>
                    <input type="date" name="order-date-from" aria-label="Orders from date" />
                    <input type="date" name="order-date-to" aria-label="Orders to date" />
                    <button type="button" class="button button-primary" data-orders-apply>Apply filters</button>
                    <button type="button" class="button" data-orders-reset>Reset</button>
                </div>
            </div>
            <div data-orders-table class="cogs-studio-loading">Loading orders…</div>`;
        panel.dataset.ready = '1';
    }

    function syncOrderState() {
        const panel = panels.get('orders');
        orderState.status = panel.querySelector('[name="order-status"]')?.value || '';
        orderState.date_from = panel.querySelector('[name="order-date-from"]')?.value || '';
        orderState.date_to = panel.querySelector('[name="order-date-to"]')?.value || '';
    }

    async function loadOrders(page = 1) {
        const panel = panels.get('orders');
        ensureOrdersShell();
        syncOrderState();

        const target = panel.querySelector('[data-orders-table]');
        target.innerHTML = '<div class="cogs-studio-loading">Loading orders…</div>';

        try {
            const data = await request('cogs_studio_orders', { page, ...orderState });
            const status = panel.querySelector('[name="order-status"]');
            if (status) status.innerHTML = objectOptions(data.status_options, orderState.status, 'All realized statuses');
            const from = panel.querySelector('[name="order-date-from"]');
            const to = panel.querySelector('[name="order-date-to"]');
            if (from) from.value = orderState.date_from;
            if (to) to.value = orderState.date_to;

            const rows = data.rows.map((row) => `
                <tr>
                    <td><a href="${esc(row.edit_url)}"><strong>#${esc(row.number)}</strong></a><span class="cogs-studio-subline">${esc(row.date)}</span></td>
                    <td>${esc(row.customer)}</td>
                    <td><span class="cogs-studio-badge">${esc(row.status)}</span></td>
                    <td>${money(row.revenue)}</td>
                    <td>${money(row.cogs)}</td>
                    <td>${money(row.profit)}</td>
                    <td>${percent(row.margin)}</td>
                </tr>`).join('');

            target.innerHTML = `
                <div class="cogs-studio-table-meta">${data.total} matching orders</div>
                <div class="cogs-studio-table-scroll">
                    <table class="widefat striped cogs-studio-table">
                        <thead><tr><th>Order</th><th>Customer</th><th>Status</th><th>Product sales</th><th>COGS</th><th>Gross profit</th><th>Margin</th></tr></thead>
                        <tbody>${rows || '<tr><td colspan="7">No orders found.</td></tr>'}</tbody>
                    </table>
                </div>
                ${pagination(data.page, data.total_pages, 'orders')}`;
        } catch (error) {
            target.innerHTML = errorHtml(error);
        }
    }

    async function loadHistory() {
        const panel = panels.get('history');
        panel.innerHTML = '<div class="cogs-studio-loading">Loading cost history…</div>';
        try {
            const data = await request('cogs_studio_history');
            const rows = data.rows.map((row) => `
                <tr>
                    <td>${esc(row.date)}</td>
                    <td>${esc(row.product)} <span class="cogs-studio-subline">#${row.product_id}</span></td>
                    <td>${row.old_cost === null ? '—' : money(row.old_cost)}</td>
                    <td>${row.new_cost === null ? '—' : money(row.new_cost)}</td>
                    <td>${esc(row.user)}</td>
                    <td><span class="cogs-studio-badge">${esc(row.source)}</span></td>
                </tr>`).join('');
            panel.innerHTML = `
                <div class="cogs-studio-section-heading"><div><h2>Cost History</h2><p>Native COGS changes from COGS Studio, WooCommerce admin, REST/API, WP-CLI, CSV import, bulk actions, and other WooCommerce CRUD paths are recorded here.</p></div></div>
                <div class="cogs-studio-table-scroll">
                    <table class="widefat striped cogs-studio-table">
                        <thead><tr><th>Date</th><th>Product</th><th>Old cost</th><th>New cost</th><th>User</th><th>Source</th></tr></thead>
                        <tbody>${rows || '<tr><td colspan="6">No COGS Studio changes recorded yet.</td></tr>'}</tbody>
                    </table>
                </div>`;
        } catch (error) {
            panel.innerHTML = errorHtml(error);
        }
    }

    async function loadSettings() {
        const panel = panels.get('settings');
        panel.innerHTML = '<div class="cogs-studio-loading">Loading system status…</div>';

        try {
            const data = await request('cogs_studio_system');
            const status = data.status;
            panel.innerHTML = `
                <div class="cogs-studio-settings-grid">
                    <article class="cogs-studio-card">
                        <h2>System</h2>
                        <p><strong>COGS Studio:</strong> v${esc(data.plugin_version)}</p>
                        <p><strong>WordPress:</strong> ${esc(status.wordpress_version)}</p>
                        <p><strong>PHP:</strong> ${esc(status.php_version)}</p>
                        <p><strong>WooCommerce:</strong> ${status.wc_version ? esc(status.wc_version) : 'Not active'}</p>
                        <p><strong>Minimum WooCommerce:</strong> ${esc(status.min_wc_version)}</p>
                        <p><strong>Tested through WooCommerce:</strong> ${esc(status.tested_wc_version)}</p>
                        <p><strong>Native COGS:</strong> ${status.cogs_enabled ? 'Enabled' : 'Disabled'}</p>
                        <p><strong>Source of truth:</strong> WooCommerce native Cost of Goods Sold API.</p>
                        <p><strong>Order compatibility:</strong> HPOS declared compatible.</p>
                    </article>
                    <article class="cogs-studio-card">
                        <h2>Data model</h2>
                        <p>Product costs remain owned by WooCommerce core. COGS Studio stores cost-change audit history, cached dashboard aggregates, and an immutable original COGS snapshot on paid order items so refund reporting cannot be re-priced by later product-cost changes.</p>
                        <p>No duplicate custom product COGS field is maintained by this plugin.</p>
                    </article>
                </div>`;
        } catch (error) {
            panel.innerHTML = errorHtml(error);
        }
    }

    async function activateTab(tab) {
        root.querySelectorAll('.cogs-studio-tab').forEach((button) => {
            button.classList.toggle('is-active', button.dataset.tab === tab);
        });
        panels.forEach((panel, name) => panel.classList.toggle('is-active', name === tab));

        if (loaded.has(tab) && !['products', 'orders'].includes(tab)) return;
        loaded.add(tab);

        if (tab === 'dashboard') await loadDashboard();
        if (tab === 'products') await loadProducts();
        if (tab === 'orders') await loadOrders();
        if (tab === 'history') await loadHistory();
        if (tab === 'settings') await loadSettings();
    }

    root.addEventListener('click', async (event) => {
        const tab = event.target.closest('.cogs-studio-tab');
        if (tab) {
            await activateTab(tab.dataset.tab);
            return;
        }

        if (event.target.closest('[data-dashboard-apply]')) {
            const panel = panels.get('dashboard');
            dashboardState.period = panel.querySelector('[name="dashboard-period"]')?.value || '30';
            dashboardState.date_from = panel.querySelector('[name="dashboard-date-from"]')?.value || '';
            dashboardState.date_to = panel.querySelector('[name="dashboard-date-to"]')?.value || '';
            await loadDashboard();
            return;
        }

        if (event.target.closest('[data-products-search]')) {
            await loadProducts(1);
            return;
        }

        if (event.target.closest('[data-products-reset]')) {
            Object.assign(productState, { search: '', category: '', type: '', stock_status: '', cogs_state: '', margin_max: '' });
            const panel = panels.get('products');
            panel.querySelector('[name="cogs-search"]').value = '';
            panel.querySelector('[name="cogs-category"]').value = '';
            panel.querySelector('[name="cogs-type"]').value = '';
            panel.querySelector('[name="cogs-stock-status"]').value = '';
            panel.querySelector('[name="cogs-state"]').value = '';
            panel.querySelector('[name="cogs-margin-max"]').value = '';
            await loadProducts(1);
            return;
        }

        const pageButton = event.target.closest('[data-pagination] [data-page]');
        if (pageButton && !pageButton.disabled) {
            const pager = pageButton.closest('[data-pagination]');
            const page = Number(pageButton.dataset.page || 1);
            if (pager.dataset.pagination === 'products') await loadProducts(page);
            if (pager.dataset.pagination === 'orders') await loadOrders(page);
            return;
        }

        const save = event.target.closest('[data-save-cost]');
        if (save) {
            const row = save.closest('[data-product-row]');
            const input = row.querySelector('[data-cost-input]');
            const mode = row.querySelector('[data-cost-mode]')?.value || '';
            save.disabled = true;
            save.textContent = 'Saving…';
            try {
                const data = await request('cogs_studio_save_cost', {
                    product_id: row.dataset.productRow,
                    cost: input.value,
                    mode,
                });
                row.querySelector('[data-effective-cost]').textContent = money(data.cost);
                row.querySelector('[data-profit]').textContent = money(data.profit);
                row.querySelector('[data-margin]').textContent = percent(data.margin);
                input.value = data.nominal_cost === null ? '' : data.nominal_cost;
                const modeSelect = row.querySelector('[data-cost-mode]');
                if (modeSelect && data.cost_mode) {
                    modeSelect.value = data.cost_mode;
                    input.disabled = data.cost_mode === 'inherit';
                }
                save.textContent = COGSStudio.i18n.saved;
                setTimeout(() => { save.textContent = COGSStudio.i18n.save; }, 1200);
                loaded.delete('dashboard');
                loaded.delete('history');
            } catch (error) {
                window.alert(error.message || COGSStudio.i18n.error);
                save.textContent = COGSStudio.i18n.save;
            } finally {
                save.disabled = false;
            }
            return;
        }

        if (event.target.closest('[data-bulk-set]')) {
            await runBulk('set');
            return;
        }

        if (event.target.closest('[data-bulk-clear]')) {
            await runBulk('clear');
            return;
        }

        if (event.target.closest('[data-csv-export]')) {
            try {
                const data = await request('cogs_studio_csv_export');
                downloadBase64(data.filename, data.content);
            } catch (error) {
                window.alert(error.message || COGSStudio.i18n.error);
            }
            return;
        }

        if (event.target.closest('[data-csv-import-trigger]')) {
            panels.get('products').querySelector('[data-csv-import]')?.click();
            return;
        }

        if (event.target.closest('[data-orders-apply]')) {
            await loadOrders(1);
            return;
        }

        if (event.target.closest('[data-orders-reset]')) {
            Object.assign(orderState, { status: '', date_from: '', date_to: '' });
            const panel = panels.get('orders');
            panel.querySelector('[name="order-status"]').value = '';
            panel.querySelector('[name="order-date-from"]').value = '';
            panel.querySelector('[name="order-date-to"]').value = '';
            await loadOrders(1);
            return;
        }
    });

    root.addEventListener('change', async (event) => {
        const modeSelect = event.target.closest('[data-cost-mode]');
        if (modeSelect) {
            const row = modeSelect.closest('[data-product-row]');
            const input = row?.querySelector('[data-cost-input]');
            if (input) {
                input.disabled = modeSelect.value === 'inherit';
                if (input.disabled) input.value = '';
            }
            return;
        }

        if (event.target.matches('[data-product-select], [data-select-all]')) {
            if (event.target.matches('[data-select-all]')) {
                const checked = event.target.checked;
                panels.get('products').querySelectorAll('[data-product-select]').forEach((input) => {
                    input.checked = checked;
                });
            }
            updateSelectionUi();
            return;
        }

        if (event.target.matches('[name="dashboard-period"]')) {
            dashboardState.period = event.target.value;
            const panel = panels.get('dashboard');
            panel.querySelectorAll('[name="dashboard-date-from"], [name="dashboard-date-to"]').forEach((input) => {
                input.disabled = dashboardState.period !== 'custom';
            });
            return;
        }

        if (event.target.matches('[data-csv-import]')) {
            const file = event.target.files?.[0];
            if (!file) return;

            if (file.size > 2 * 1024 * 1024) {
                window.alert('CSV file is too large. Maximum size is 2 MB.');
                event.target.value = '';
                return;
            }

            try {
                const csv = await file.text();
                const data = await request('cogs_studio_csv_import', { csv });
                const details = data.errors?.length ? `\n${data.errors.join('\n')}` : '';
                window.alert(`${data.updated} rows imported, ${data.skipped} skipped.${details}`);
                loaded.delete('dashboard');
                loaded.delete('history');
                await loadProducts(1);
            } catch (error) {
                window.alert(error.message || COGSStudio.i18n.error);
            } finally {
                event.target.value = '';
            }
        }
    });

    root.addEventListener('keydown', async (event) => {
        if (event.key === 'Enter' && event.target.matches('[name="cogs-search"], [name="cogs-margin-max"]')) {
            event.preventDefault();
            await loadProducts(1);
        }
    });

    activateTab('dashboard');
})();
