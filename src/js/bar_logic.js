/*******************************************************
* /src/js/bar_logic.js - Versión con Modificadores
********************************************************/

document.addEventListener('DOMContentLoaded', () => {
    const clockContainer = document.getElementById('liveClockContainer');
    const barGrid = document.getElementById('barOrdersGrid');
    
    const API_ENDPOINT = '/src/api/bar/get_bar_orders.php'; 
    const API_ACTION_ENDPOINT = '/src/api/bar/update_item_status.php';

    function parseUTCTimestamp(sqlTimestamp) {
        if (!sqlTimestamp) return new Date(NaN);
        let clean = sqlTimestamp.replace('T', ' ').replace('Z', '').trim().split('.')[0];
        const parts = clean.split(' ');
        if (parts.length < 2) return new Date(NaN);
        const [datePart, timePart] = parts;
        const [year, month, day] = datePart.split('-').map(Number);
        const [hours = 0, minutes = 0, seconds = 0] = timePart.split(':').map(Number);
        const date = new Date(Date.UTC(year, month - 1, day, hours, minutes, seconds));
        return isNaN(date.getTime()) ? new Date(NaN) : date;
    }

    function updateClock() {
        const now = new Date();
        const months = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
        const month = months[now.getMonth()];
        const day = now.getDate();
        const hours = String(now.getHours()).padStart(2, '0');
        const minutes = String(now.getMinutes()).padStart(2, '0');
        const seconds = String(now.getSeconds()).padStart(2, '0');
        if (clockContainer) clockContainer.textContent = `${month} ${day} ${hours}:${minutes}:${seconds}`;
    }
    
    async function updateItemStatus(detailId, newStatus) {
        try {
            const response = await fetch(API_ACTION_ENDPOINT, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ detail_id: detailId, new_status: newStatus })
            });
            const data = await response.json();
            if (data.success) {
                fetchAndDisplayBarOrders(); 
            } else {
                window.appAlert('Error al actualizar estado: ' + data.message);
            }
        } catch (error) {
            console.error('Fallo en la conexión al actualizar:', error);
            window.appAlert('Error de conexión con el servidor.');
        }
    }

    async function fetchAndDisplayBarOrders() {
        try {
            const response = await fetch(API_ENDPOINT + `?t=${Date.now()}`);
            if (!response.ok) throw new Error(`Error de red: ${response.status}`);
            const data = await response.json();
            if (data.error || !data.success) throw new Error(data.error || 'Fallo en la API.');
            const groupedOrders = groupItemsByLote(data.production_items);
            renderProductionItems(groupedOrders); 
        } catch (error) {
            console.error('Error al cargar órdenes de barra:', error);
            barGrid.innerHTML = `<p class="error-msg">Error: No se pudieron cargar las órdenes.</p>`;
        }
    }

    function groupItemsByLote(items) {
        const grouped = {};
        items.forEach(item => {
            const loteKey = `${item.order_id}_${item.added_at}`;
            if (!grouped[loteKey]) {
                grouped[loteKey] = {
                    order_id: item.order_id, table_number: item.table_number,
                    server_name: item.server_name, server_id: item.server_id,
                    order_time: item.order_time, added_at: item.added_at, times: {},
                };
            }
            const time = item.service_time;
            if (!grouped[loteKey].times[time]) {
                grouped[loteKey].times[time] = [];
            }
            grouped[loteKey].times[time].push(item);
        });
        return Object.values(grouped);
    }
    
    function renderProductionItems(groupedOrders) {
        const nowMs = Date.now(); 
        const existingCardKeys = new Set(Array.from(document.querySelectorAll('.production-card')).map(c => c.dataset.loteKey));
        const incomingCardKeys = new Set(groupedOrders.map(g => `${g.order_id}_${g.added_at}`));

        existingCardKeys.forEach(key => {
            if (!incomingCardKeys.has(key)) {
                document.querySelector(`[data-lote-key="${key}"]`)?.remove();
            }
        });

        groupedOrders.forEach(orderGroup => {
            const loteKey = `${orderGroup.order_id}_${orderGroup.added_at}`;
            const existingCard = document.querySelector(`[data-lote-key="${loteKey}"]`);
            const newCardHtml = createItemHtml(orderGroup, nowMs);

            if (existingCard) {
                if (existingCard.innerHTML.trim() !== newCardHtml.trim()) {
                    existingCard.innerHTML = newCardHtml;
                }
            } else {
                const cardWrapper = document.createElement('div');
                cardWrapper.className = 'production-card';
                cardWrapper.dataset.loteKey = loteKey;
                cardWrapper.innerHTML = newCardHtml;
                barGrid.appendChild(cardWrapper);
            }
        });

        if (barGrid.childElementCount === 0) {
            barGrid.innerHTML = '<p class="no-orders">¡Todas las bebidas listas!</p>';
        } else {
            barGrid.querySelector('.no-orders, .loading-msg')?.remove();
        }
    }
    
    function createItemHtml(orderGroup, nowMs) {
        const addedAtDate = parseUTCTimestamp(orderGroup.added_at);
        const addedTimeMs = addedAtDate.getTime();
        let timeDiffMinutes = isNaN(addedTimeMs) ? '--' : Math.round((nowMs - addedTimeMs) / 60000); 
        if (timeDiffMinutes < 0) timeDiffMinutes = 0;
        const entryTime = !isNaN(addedTimeMs) ? addedAtDate.toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' }) : 'Hora inválida';
        
        let totalPending = 0, totalPreparing = 0, totalItems = 0;
        let itemsListHtml = '';
        const sortedTimes = Object.keys(orderGroup.times).sort((a, b) => a - b);

        sortedTimes.forEach(time => {
            itemsListHtml += `<div class="time-separator">--- Tiempo ${time} ---</div>`;
            orderGroup.times[time].forEach(item => {
                totalItems++;
                if (item.item_status === 'PENDIENTE') totalPending++;
                if (item.item_status === 'EN_PREPARACION') totalPreparing++;
                
                // Lógica para mostrar el modificador y las notas
                const modifierHtml = item.modifier_name ? `<span class="item-modifier">(${item.modifier_name})</span>` : '';
                const notesHtml = item.special_notes ? `<span class="item-notes">(${item.special_notes})</span>` : '';
                const itemStatusClass = item.item_status.toLowerCase().replace('_', '-');
                
                itemsListHtml += `
                    <div class="product-item status-${itemStatusClass}" data-detail-id="${item.detail_id}" data-status="${item.item_status}">
                        <span class="product-qty">${item.quantity}x</span>
                        <span class="product-name">${item.product_name} ${modifierHtml} ${notesHtml}</span>
                        <span class="status-indicator"></span>
                    </div>
                `;
            });
        });

        const totalPendingWork = totalPending + totalPreparing;
        const consolidatedStatus = totalPreparing > 0 || (totalPending > 0 && totalItems > totalPending) ? 'EN_PREPARACION' : 'PENDIENTE';
        const statusClass = consolidatedStatus.toLowerCase().replace('_', '-');
        const invalidWarn = isNaN(addedTimeMs) ? `<div class="invalid-time-warn">⚠️ Error de hora (${orderGroup.added_at})</div>` : '';

        return `
            ${invalidWarn}
            <div class="card-header status-bg-${statusClass}">
                <span class="table-info">Mesa ${orderGroup.table_number} (#${orderGroup.order_id})</span>
                <span class="time-ago">Hace ${timeDiffMinutes} min</span>
            </div>
            <div class="card-meta">
                <span>Mesero: <strong>${orderGroup.server_name}</strong></span>
                <span>Entrada: <strong>${entryTime}</strong></span>
            </div>
            <div class="card-body">${itemsListHtml}</div>
            <div class="card-footer">
                <span class="item-status">${consolidatedStatus.replace('_', ' ')} (${totalPendingWork}/${totalItems})</span>
            </div>
        `;
    }

    barGrid.addEventListener('click', (e) => {
        const productItem = e.target.closest('.product-item');
        if (!productItem) return;
        const detailId = productItem.dataset.detailId;
        const currentStatus = productItem.dataset.status;
        let newStatus = null;
        if (currentStatus === 'PENDIENTE') newStatus = 'EN_PREPARACION';
        else if (currentStatus === 'EN_PREPARACION') newStatus = 'LISTO';
        if (detailId && newStatus) updateItemStatus(detailId, newStatus);
    });

    updateClock();
    setInterval(updateClock, 1000);
    fetchAndDisplayBarOrders();
    setInterval(fetchAndDisplayBarOrders, 5000);
});
