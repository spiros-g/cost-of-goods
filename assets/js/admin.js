(() => {
    'use strict';

    const root = document.querySelector('.cogs-studio-wrap');
    if (!root || typeof COGSStudio === 'undefined') return;

    const panels = new Map(
        [...root.querySelectorAll('[data-panel]')].map((panel) => [panel.dataset.panel, panel])
    );
    const loaded = new Set();

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
        } catch (e) {
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

        const json = await response.json();
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

    async function loadDashboard() {
        const panel = panels.get('dashboard');
        panel.innerHTML = '<div class="cogs-studio-loading">Loading dashboard…</div>';
        try {
            const data = await request('cogs_studio_dashboard');
            const o = data.orders;
            const i = data.inventory;
            panel.innerHTML = `
                <div class="cogs-studio-section-heading">
                    <div><h2>Last ${esc(data.period_days)} days</h2><p>Product sales basis; taxes and shipping are excluded.</p></div>
                </div>
                <div class="cogs-studio-kpis">
                    <article><span>Net product sales</span><strong>${money(o.revenue)}</strong><small>${Number(o.count)} orders</small></article>
                    <article><span>COGS</span><strong>${money(o.cogs)}</strong><small>Native WooCommerce COGS</small></article>
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

    async function loadProducts(page = 1) {
        const panel = panels.get('products');
        const currentSearch = panel.querySelector('[name="cogs-search"]')?.value || '';
        if (!panel.dataset.ready) {
            panel.innerHTML = `
                <div class="cogs-studio-toolbar">
                    <input type="search" name="cogs-search" placeholder="Search product name or exact SKU…" />
                    <button type="button" class="button button-primary" data-products-search>Search</button>
                </div>
                <div data-products-table class="cogs-studio-loading">Loading products…</div>`;
            panel.dataset.ready = '1';
        }

        const target = panel.querySelector('[data-products-table]');
        target.innerHTML = '<div class="cogs-studio-loading">Loading products…</div>';
        try {
            const data = await request('cogs_studio_products', { page, search: currentSearch });
            const rows = data.rows.map((row) => `
                <tr data-product-row="${row.id}">
                    <td class="cogs-studio-product-cell">
                        <a href="${esc(row.edit_url)}"><strong>${esc(row.name)}</strong></a>
                        <span>#${row.id}${row.parent_id ? ` · variation of #${row.parent_id}` : ''}</span>
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
                <div class="cogs-studio-table-meta">${data.total} products / variations</div>
                <div class="cogs-studio-table-scroll">
                    <table class="widefat striped cogs-studio-table">
                        <thead><tr><th>Product</th><th>SKU</th><th>Type</th><th>Price</th><th>Defined COGS</th><th>Effective COGS</th><th>Profit</th><th>Margin</th><th>Stock</th></tr></thead>
                        <tbody>${rows || '<tr><td colspan="9">No products found.</td></tr>'}</tbody>
                    </table>
                </div>
                ${pagination(data.page, data.total_pages, 'products')}`;
        } catch (error) {
            target.innerHTML = errorHtml(error);
        }
    }

    async function loadOrders(page = 1) {
        const panel = panels.get('orders');
        panel.innerHTML = '<div class="cogs-studio-loading">Loading orders…</div>';
        try {
            const data = await request('cogs_studio_orders', { page });
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

            panel.innerHTML = `
                <div class="cogs-studio-section-heading"><div><h2>Order profitability</h2><p>Revenue uses product line totals after item refunds, excluding tax and shipping.</p></div></div>
                <div class="cogs-studio-table-scroll">
                    <table class="widefat striped cogs-studio-table">
                        <thead><tr><th>Order</th><th>Customer</th><th>Status</th><th>Product sales</th><th>COGS</th><th>Gross profit</th><th>Margin</th></tr></thead>
                        <tbody>${rows || '<tr><td colspan="7">No orders found.</td></tr>'}</tbody>
                    </table>
                </div>
                ${pagination(data.page, data.total_pages, 'orders')}`;
        } catch (error) {
            panel.innerHTML = errorHtml(error);
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
                <div class="cogs-studio-section-heading"><div><h2>Cost History</h2><p>Native COGS changes from COGS Studio, WooCommerce admin, REST/API, WP-CLI, and migration paths are recorded here.</p></div></div>
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
                        <h2>Legacy migration</h2>
                        <p>Migrate values from the old <code>cog_cost</code> meta into WooCommerce native COGS. Any already-defined native COGS value — including an explicit zero on a variation — is preserved. Legacy meta is kept as a rollback safety net.</p>
                        <p><strong>Legacy candidates:</strong> ${Number(data.legacy_candidates || 0)}</p>
                        <p><strong>Last completed:</strong> ${data.migration_completed_at ? esc(data.migration_completed_at) : 'Never'}</p>
                        <button type="button" class="button button-primary" data-run-migration ${status.cogs_enabled ? '' : 'disabled'}>Run safe migration</button>
                        <div class="cogs-studio-migration-status" data-migration-status></div>
                    </article>
                    <article class="cogs-studio-card">
                        <h2>System</h2>
                        <p><strong>COGS Studio:</strong> v${esc(data.plugin_version)}</p>
                        <p><strong>WooCommerce:</strong> ${status.wc_version ? esc(status.wc_version) : 'Not active'}</p>
                        <p><strong>Minimum WooCommerce:</strong> ${esc(status.min_wc_version)}</p>
                        <p><strong>Native COGS:</strong> ${status.cogs_enabled ? 'Enabled' : 'Disabled'}</p>
                        <p><strong>Source of truth:</strong> WooCommerce native Cost of Goods Sold API.</p>
                        <p><strong>Order compatibility:</strong> HPOS declared compatible.</p>
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

        if (loaded.has(tab) && tab !== 'products' && tab !== 'orders') return;
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

        const search = event.target.closest('[data-products-search]');
        if (search) {
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

        const migration = event.target.closest('[data-run-migration]');
        if (migration) {
            const status = root.querySelector('[data-migration-status]');
            migration.disabled = true;
            let page = 1;
            let totals = { processed: 0, migrated: 0, skipped: 0, errors: 0 };
            try {
                while (true) {
                    const data = await request('cogs_studio_migrate', { page });
                    totals.processed += Number(data.processed || 0);
                    totals.migrated += Number(data.migrated || 0);
                    totals.skipped += Number(data.skipped || 0);
                    totals.errors += Number(data.errors || 0);
                    status.textContent = `Processed ${totals.processed} · Migrated ${totals.migrated} · Skipped ${totals.skipped} · Errors ${totals.errors}`;
                    if (!data.has_more) break;
                    page += 1;
                }
                status.textContent += ` · ${COGSStudio.i18n.migrationDone}`;
                loaded.delete('dashboard');
                loaded.delete('products');
                loaded.delete('history');
                loaded.delete('settings');
            } catch (error) {
                status.innerHTML = errorHtml(error);
            } finally {
                migration.disabled = false;
            }
        }
    });

    root.addEventListener('keydown', async (event) => {
        if (event.key === 'Enter' && event.target.matches('[name="cogs-search"]')) {
            event.preventDefault();
            await loadProducts(1);
        }
    });

    activateTab('dashboard');
})();
