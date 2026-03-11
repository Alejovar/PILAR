// /js/pending_orders.js - VERSIÓN FINAL CON TABLA DE DETALLES Y MODIFICADORES

// 💡 CAMBIO: La función principal ahora es 'async'
document.addEventListener('DOMContentLoaded', async () => {

    // <<<--- INICIO DE LA VERIFICACIÓN DE TURNO (LO NUEVO) --- >>>
    // 1. VERIFICACIÓN DE TURNO INICIAL
    try {
        // Reutilizamos el API que ya existe
        const response = await fetch('/src/api/cashier/history_reports/get_shift_status.php');
        const data = await response.json();

        if (!data.success || data.status === 'CLOSED') {
            // ¡Turno cerrado!
            alert("El turno de caja ha sido cerrado. La sesión se cerrará.");
            // Redirigimos al logout para limpiar la sesión
            window.location.href = '/src/php/logout.php';
            return; // Detenemos la carga del resto del script
        }

    } catch (error) {
        // Error grave de conexión
        document.body.innerHTML = "<h1>Error fatal al verificar el estado del turno.</h1>";
        return; // Detenemos la carga
    }
    // --- 👆 FIN DE LA VERIFICACIÓN 👆 ---

    
    // --- EL RESTO DE TU CÓDIGO ORIGINAL CONTINÚA AQUÍ ---
    const ordersGrid = document.getElementById('ordersGrid');
    const clockContainer = document.getElementById('liveClockContainer');
    
    const API_ENDPOINT = '/src/api/orders/pending_orders/get_pending_orders.php'; 
    const API_DETAIL = '/src/api/orders/pending_orders/get_order_details.php'; 
    const API_COMPLETE = '/src/api/orders/pending_orders/mark_as_completed.php';

    const detailsPanel = document.getElementById('orderDetailsPanel');
    const closeDetailsBtn = document.getElementById('closeDetailsPanel');
    const detailItemsList = document.getElementById('detailItemsList');
    const detailFooter = document.getElementById('detailPanelFooter');

    function closeDetailModal() {
        if (detailsPanel) detailsPanel.classList.remove('active');
    }
    
    if (closeDetailsBtn) {
        closeDetailsBtn.addEventListener('click', closeDetailModal);
    }
    
    if (detailsPanel) {
        detailsPanel.addEventListener('click', (e) => {
            if (e.target === detailsPanel) {
                closeDetailModal();
            }
        });
    }

    function updateClock() {
        if (!clockContainer) return;
        const now = new Date();
        const months = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
        const month = months[now.getMonth()];
        const day = now.getDate();
        const hours = String(now.getHours()).padStart(2, '0');
        const minutes = String(now.getMinutes()).padStart(2, '0');
        const seconds = String(now.getSeconds()).padStart(2, '0');
        clockContainer.textContent = `${month} ${day} ${hours}:${minutes}:${seconds}`;
    }

    async function fetchAndDisplayOrders() {
        try {
            const response = await fetch(API_ENDPOINT + `?t=${Date.now()}`); 
            if (!response.ok) throw new Error(`Error de red: ${response.status}`);
            const data = await response.json();
            if (data.error || !data.success) throw new Error(data.error || 'Fallo en la API.');
            renderOrders(data.orders_summary, data.server_time); 
        } catch (error) {
            console.error('Error al cargar órdenes:', error);
            ordersGrid.innerHTML = `<p class="error-msg">No se pudieron cargar las órdenes.</p>`;
        }
    }

    function renderOrders(ordersSummary, serverTime) {
        ordersGrid.innerHTML = ''; 
        if (!ordersSummary || ordersSummary.length === 0) {
            ordersGrid.innerHTML = '<p class="no-orders">¡Excelente! No tienes ninguna orden pendiente.</p>';
            return;
        }
        const serverNowMs = new Date(serverTime + "Z").getTime(); 
        
        ordersSummary.forEach(order => {
            const orderCard = document.createElement('div');
            const batchTimeMs = new Date(order.batch_timestamp + "Z").getTime(); 
            let timeDiffMinutes = isNaN(batchTimeMs) ? '--' : Math.round((serverNowMs - batchTimeMs) / 60000); 
            if (timeDiffMinutes < 0) timeDiffMinutes = 0; 
            
            const isReadyToCollect = order.kitchen_ready > 0 || order.bar_ready > 0;
            const cardClass = isReadyToCollect ? 'status-ready-collect' : 'status-pending-work';
            orderCard.className = `order-card ${cardClass}`;

            const getStatusHtml = (ready, totalActive, areaName) => {
                let statusClass = 'none', statusText = '--';
                if (totalActive > 0) {
                    if (ready >= totalActive) {
                        statusClass = 'ready'; statusText = 'LISTO';
                    } else {
                        statusClass = 'in-progress'; statusText = 'PENDIENTE';
                    }
                }
                return `<div class="status-item status-${statusClass}"><span class="area-name">${areaName}:</span><span class="status-text">${statusText}</span><span class="status-counts">(${ready}/${totalActive})</span></div>`;
            };

            const kitchenStatus = getStatusHtml(order.kitchen_ready, order.total_kitchen_active, 'Cocina');
            const barStatus = getStatusHtml(order.bar_ready, order.total_bar_active, 'Barra');

            orderCard.innerHTML = `
                <div class="order-card-header">
                    <h2>Mesa ${order.table_number} (#${order.order_id})</h2>
                    <span class="time-ago">Hace ${timeDiffMinutes} min</span>
                </div>
                <div class="order-card-body">
                    <div class="area-statuses">${kitchenStatus}${barStatus}</div>
                    <button class="btn-detail primary-btn" data-order-id="${order.order_id}" data-table-number="${order.table_number}" data-batch-id="${order.batch_id}" data-batch-time="${order.batch_timestamp}">Ver Detalle</button>
                </div>`;
            ordersGrid.appendChild(orderCard);
        });
    }

    ordersGrid.addEventListener('click', (e) => {
        const button = e.target.closest('.btn-detail');
        if (button) {
            const { orderId, tableNumber, batchId, batchTime } = button.dataset;
            displayOrderDetails(orderId, tableNumber, batchId, batchTime);
        }
    });

    async function displayOrderDetails(orderId, tableNumber, batchId, batchTime) {
        if (!detailsPanel) return;
        detailsPanel.classList.add('active'); 
        
        document.getElementById('detailOrderId').textContent = orderId;
        document.getElementById('detailTableNumber').textContent = tableNumber;
        document.getElementById('detailBatchTime').textContent = new Date(batchTime + "Z").toLocaleTimeString();
        detailItemsList.innerHTML = '<p class="loading-msg">Cargando detalles...</p>';
        detailFooter.innerHTML = '';

        try {
            const url = `${API_DETAIL}?order_id=${orderId}&batch_id=${batchId}`;
            const response = await fetch(url);
            if (!response.ok) throw new Error(`Error de red: ${response.status}`);
            const data = await response.json();

            if (data.success) {
                renderDetailItems(data.items);
                
                if (data.items.length > 0) {
                    const allItemsReady = data.items.every(item => item.item_status === 'LISTO');
                    const completeButton = document.createElement('button');
                    completeButton.id = 'completeOrderBtn';
                    completeButton.className = 'primary-btn complete-btn';
                    completeButton.textContent = 'Marcar como Entregado';
                    completeButton.disabled = !allItemsReady;

                    if (!allItemsReady) {
                        completeButton.title = 'Todos los productos deben estar en estado "LISTO" para entregar.';
                    }
                    
                    completeButton.addEventListener('click', () => {
                        handleCompleteOrder(orderId, batchId);
                    });
                    
                    detailFooter.appendChild(completeButton);
                }
            } else {
                detailItemsList.innerHTML = `<p class="error-msg">Error: ${data.message || 'Fallo de API.'}</p>`;
            }
        } catch (error) {
            console.error('Error al obtener detalles:', error);
            detailItemsList.innerHTML = `<p class="error-msg">Error de conexión al obtener detalles.</p>`;
        }
    }
    
    /**
     * ✅ FUNCIÓN CORREGIDA PARA CREAR UNA TABLA DE DETALLES CON MODIFICADORES
     * @param {Array} items - La lista de productos de la orden.
     */
    function renderDetailItems(items) {
        detailItemsList.innerHTML = '';
        if (items.length === 0) {
            detailItemsList.innerHTML = '<p>No se encontraron ítems activos en este lote.</p>';
            return;
        }

        // 1. Creamos la estructura de la tabla con su encabezado
        let tableHtml = `
            <table id="detailItemsTable">
                <thead>
                    <tr>
                        <th>Cant.</th>
                        <th>Producto</th>
                        <th>Tiempo</th>
                        <th>Área</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
        `;

        // 2. Mapeamos cada ítem a una fila <tr> de la tabla
        tableHtml += items.map(item => {
            const statusClass = item.item_status === 'LISTO' ? 'ready' : (item.item_status === 'EN_PREPARACION' ? 'preparing' : 'pending');
            const areaClass = item.preparation_area === 'BARRA' ? 'bar' : 'kitchen';
            
            const modifierHtml = item.modifier_name ? `<span class="detail-modifier">(${item.modifier_name})</span>` : '';
            const notesHtml = item.special_notes ? `<span class="detail-notes">(${item.special_notes})</span>` : '';
            
            return `
                <tr>
                    <td class="item-qty">${item.quantity}x</td>
                    <td class="item-name">
                        ${item.product_name}
                        ${modifierHtml}
                        ${notesHtml}
                    </td>
                    <td class="item-time">T${item.service_time}</td>
                    <td><span class="item-status status-area status-${areaClass}">${item.preparation_area}</span></td>
                    <td><span class="item-status status-state status-${statusClass}">${item.item_status.replace('_', ' ')}</span></td>
                </tr>
            `;
        }).join('');
        
        // 3. Cerramos la tabla y la inyectamos en el DOM
        tableHtml += `</tbody></table>`;
        detailItemsList.innerHTML = tableHtml;
    }

    async function handleCompleteOrder(orderId, batchId) {
        const btn = document.getElementById('completeOrderBtn');
        btn.disabled = true;
        btn.textContent = 'Procesando...';

        try {
            const response = await fetch(API_COMPLETE, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ order_id: orderId, batch_id: batchId })
            });

            const result = await response.json();
            if (!result.success) {
                throw new Error(result.message || 'Error desconocido del servidor.');
            }

            closeDetailModal();
            fetchAndDisplayOrders();

        } catch (error) {
            alert(`Error al completar la orden: ${error.message}`);
            btn.disabled = false;
            btn.textContent = 'Marcar como Entregado';
        }
    }

    updateClock();
    setInterval(updateClock, 1000); 

    fetchAndDisplayOrders(); 
    setInterval(fetchAndDisplayOrders, 5000); 
});
