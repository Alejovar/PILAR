// /src/js/manager_waste.js

const API = '/src/api/manager/waste/waste_api.php';

let selectedOrderId = null;

const REASON_LABELS = {
    expired:       'Caducado',
    kitchen_error: 'Error de cocina',
    waiter_error:  'Error del mesero',
    damaged:       'Dañado / Derramado',
    other:         'Otro',
};

// ── Utilidades ────────────────────────────────────────────────────────────────

function escHtml(str) {
    return String(str ?? '')
        .replace(/&/g,'&amp;').replace(/</g,'&lt;')
        .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function fmt(n) { return parseFloat(n ?? 0).toFixed(2); }

function fmtDate(str) {
    if (!str) return '—';
    return new Date(str).toLocaleString('es-MX', {
        day:'2-digit', month:'2-digit', year:'numeric',
        hour:'2-digit', minute:'2-digit', second:'2-digit', hour12: false,
    });
}

function showFeedback(id, type, msg) {
    const el = document.getElementById(id);
    if (!el) return;
    const icon = type === 'success' ? 'check-circle' : 'exclamation-circle';
    el.innerHTML = `<div class="msg-${type}"><i class="fas fa-${icon}"></i> ${escHtml(msg)}</div>`;
    if (type === 'success') setTimeout(() => { el.innerHTML = ''; }, 6000);
}

function startClock() {
    const el = document.getElementById('liveClockContainer');
    if (!el) return;
    const tick = () => el.textContent = new Date().toLocaleTimeString('es-MX', { hour12: false });
    tick();
    setInterval(tick, 1000);
}

function setDefaultDates() {
    const today = new Date().toISOString().split('T')[0];
    const first = new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().split('T')[0];
    document.getElementById('reportStartDate').value = first;
    document.getElementById('reportEndDate').value   = today;
}

// ── Tabs ──────────────────────────────────────────────────────────────────────

function initTabs() {
    document.querySelectorAll('.waste-tab-link').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.waste-tab-link').forEach(b => b.classList.remove('active'));
            document.querySelectorAll('.waste-tab-content').forEach(c => c.classList.remove('active'));
            btn.classList.add('active');
            document.getElementById(btn.dataset.tab).classList.add('active');
        });
    });
}

// ── US-02: Mesas activas ──────────────────────────────────────────────────────

async function loadOpenOrders() {
    const grid = document.getElementById('ordersGrid');
    grid.innerHTML = '<p class="initial-msg"><span class="loading-spinner">Cargando mesas activas...</span></p>';

    try {
        const res  = await fetch(API, { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({action:'get_open_orders'}) });
        const data = await res.json();
        if (!data.success) throw new Error(data.message);

        if (!data.orders.length) {
            grid.innerHTML = '<p class="initial-msg"><i class="fas fa-coffee"></i> No hay mesas con cuentas abiertas en este momento.</p>';
            return;
        }

        grid.innerHTML = data.orders.map(o => `
            <div class="order-card" data-order-id="${o.order_id}">
                <div class="table-num"><i class="fas fa-chair"></i> Mesa ${escHtml(o.table_number)}</div>
                <div class="server-name"><i class="fas fa-user"></i> ${escHtml(o.server_name)}</div>
                <div class="order-total">$${fmt(o.total)}</div>
            </div>
        `).join('');

        grid.querySelectorAll('.order-card').forEach(card => {
            card.addEventListener('click', () => selectOrder(parseInt(card.dataset.orderId), card));
        });

    } catch (err) {
        grid.innerHTML = `<p class="msg-error"><i class="fas fa-exclamation-circle"></i> ${escHtml(err.message)}</p>`;
    }
}

async function selectOrder(orderId, cardEl) {
    document.querySelectorAll('.order-card').forEach(c => c.classList.remove('selected'));
    cardEl.classList.add('selected');
    selectedOrderId = orderId;

    const panel    = document.getElementById('itemsPanel');
    const loading  = document.getElementById('itemsLoading');
    const listEl   = document.getElementById('itemsList');
    const formRow  = document.getElementById('wasteFormRow');
    const titleEl  = document.getElementById('itemsPanelTitle');

    panel.style.display   = 'block';
    loading.style.display = 'flex';
    listEl.innerHTML      = '';
    formRow.style.display = 'none';
    document.getElementById('registerFeedback').innerHTML = '';

    try {
        const res  = await fetch(API, { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({action:'get_order_items', order_id: orderId}) });
        const data = await res.json();
        if (!data.success) throw new Error(data.message);

        titleEl.textContent = `Mesa ${data.order.table_number} — ${data.order.server_name}`;

        if (!data.items.length) {
            listEl.innerHTML = '<p class="initial-msg"><i class="fas fa-check-circle"></i> No hay productos activos en esta cuenta.</p>';
            loading.style.display = 'none';
            return;
        }

        listEl.innerHTML = data.items.map(item => `
            <div class="item-row">
                <input type="checkbox" class="item-check" data-detail-id="${item.detail_id}">
                <div class="item-name">
                    ${escHtml(item.product_name)}
                    ${item.modifier_name ? `<span class="item-modifier">+ ${escHtml(item.modifier_name)}</span>` : ''}
                    ${item.special_notes ? `<span class="item-modifier">📝 ${escHtml(item.special_notes)}</span>` : ''}
                </div>
                <span class="item-qty">x${item.quantity}</span>
                <span class="item-price">$${fmt(item.current_price)}</span>
            </div>
        `).join('');

        listEl.querySelectorAll('.item-check').forEach(chk => {
            chk.addEventListener('change', () => {
                const anyChecked = listEl.querySelectorAll('.item-check:checked').length > 0;
                formRow.style.display = anyChecked ? 'flex' : 'none';
            });
        });

        loading.style.display = 'none';

    } catch (err) {
        loading.style.display = 'none';
        listEl.innerHTML = `<p class="msg-error"><i class="fas fa-exclamation-circle"></i> ${escHtml(err.message)}</p>`;
    }
}

async function registerWaste() {
    if (!selectedOrderId) return;

    const checked   = [...document.querySelectorAll('.item-check:checked')];
    const detailIds = checked.map(c => parseInt(c.dataset.detailId));

    if (!detailIds.length) {
        showFeedback('registerFeedback', 'error', 'Selecciona al menos un producto para mermar.');
        return;
    }

    const reason = document.getElementById('wasteReason').value;
    const notes  = document.getElementById('wasteNotes').value.trim();
    const btn    = document.getElementById('btnRegisterWaste');

    const confirmMsg = `¿Confirmas que ${detailIds.length} producto(s) serán marcados como MERMA?\n\nEl cargo será eliminado de la cuenta del cliente.`;
    if (!confirm(confirmMsg)) return;

    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Procesando...';

    try {
        const res  = await fetch(API, {
            method:'POST', headers:{'Content-Type':'application/json'},
            body: JSON.stringify({ action:'register_waste', order_id: selectedOrderId, detail_ids: detailIds, waste_reason: reason, notes }),
        });
        const data = await res.json();
        if (!data.success) throw new Error(data.message);

        showFeedback('registerFeedback', 'success', data.message);

        await loadOpenOrders();
        const sameCard = document.querySelector(`.order-card[data-order-id="${selectedOrderId}"]`);
        if (sameCard) {
            selectOrder(selectedOrderId, sameCard);
        } else {
            document.getElementById('itemsPanel').style.display = 'none';
            selectedOrderId = null;
        }

    } catch (err) {
        showFeedback('registerFeedback', 'error', err.message);
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Confirmar Merma';
    }
}

// ── US-01: Reporte ────────────────────────────────────────────────────────────

async function runReport() {
    const start     = document.getElementById('reportStartDate').value;
    const end       = document.getElementById('reportEndDate').value;
    const container = document.getElementById('reportResults');

    if (!start || !end) {
        container.innerHTML = '<p class="msg-error"><i class="fas fa-exclamation-circle"></i> Selecciona ambas fechas.</p>';
        return;
    }

    container.innerHTML = '<p class="loading-spinner">Generando reporte...</p>';

    try {
        const res  = await fetch(API, {
            method:'POST', headers:{'Content-Type':'application/json'},
            body: JSON.stringify({ action:'get_waste_report', start_date: start, end_date: end }),
        });
        const data = await res.json();
        if (!data.success) throw new Error(data.message);

        if (!data.records.length) {
            container.innerHTML = '<p class="initial-msg"><i class="fas fa-inbox"></i> No hay mermas registradas en ese período.</p>';
            return;
        }

        const summaryHtml = `
            <div class="summary-bar">
                <div class="summary-item">
                    <span class="summary-label">Registros</span>
                    <span class="summary-value">${data.count}</span>
                </div>
                <div class="summary-item">
                    <span class="summary-label">Total unidades</span>
                    <span class="summary-value">${fmt(data.total_items)}</span>
                </div>
                <div class="summary-item">
                    <span class="summary-label">Costo total mermas</span>
                    <span class="summary-value danger">$${data.total_value}</span>
                </div>
            </div>
        `;

        const rows = data.records.map(r => {
            const label = REASON_LABELS[r.waste_reason] ?? r.waste_reason ?? '—';
            const cls   = `reason-${(r.waste_reason ?? 'other').replace(/[^a-z_]/g,'')}`;
            return `
                <tr>
                    <td>${fmtDate(r.waste_date)}</td>
                    <td><strong>${escHtml(r.product_name)}</strong></td>
                    <td>${fmt(r.quantity)}</td>
                    <td>$${fmt(r.unit_price)}</td>
                    <td>$${fmt(r.total_waste_value)}</td>
                    <td><span class="reason-badge ${cls}">${escHtml(label)}</span></td>
                    <td>${r.table_number ? `Mesa ${escHtml(r.table_number)}` : '—'}</td>
                    <td>${r.recorded_by ? escHtml(r.recorded_by) : '—'}</td>
                </tr>
            `;
        }).join('');

        container.innerHTML = summaryHtml + `
            <div class="table-wrapper">
                <table class="waste-table">
                    <thead>
                        <tr>
                            <th><i class="fas fa-clock"></i> Fecha y Hora</th>
                            <th>Producto</th>
                            <th>Cant.</th>
                            <th>Precio unit.</th>
                            <th>Valor merma</th>
                            <th>Motivo</th>
                            <th>Mesa</th>
                            <th>Registrado por</th>
                        </tr>
                    </thead>
                    <tbody>${rows}</tbody>
                </table>
            </div>
        `;

    } catch (err) {
        container.innerHTML = `<p class="msg-error"><i class="fas fa-exclamation-circle"></i> ${escHtml(err.message)}</p>`;
    }
}

// ── Init ──────────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    startClock();
    setDefaultDates();
    initTabs();
    loadOpenOrders();

    document.getElementById('btnRegisterWaste').addEventListener('click', registerWaste);
    document.getElementById('btnRunReport').addEventListener('click', runReport);
});