// tpv.js - VERSIÓN FINAL ESTABLE Y CORREGIDA

// Rutas a los endpoints PHP (AJAX)
const API_ROUTES = {
    API_PRODUCT_URL: '/KitchenLink/src/api/orders/tpv/get_products_by_category.php',
    API_MODIFIER_URL: '/KitchenLink/src/api/orders/tpv/get_product_modifiers.php',
    API_SEND_ORDER: '/KitchenLink/src/api/orders/tpv/send_order.php',
    API_SEARCH_PRODUCT: '/KitchenLink/src/api/orders/tpv/search_products.php',
    API_GET_ACTIVE_ORDER_ID: '/KitchenLink/src/api/orders/get_active_order_id.php',
    API_GET_ORDER_ITEMS: '/KitchenLink/src/api/orders/tpv/get_current_order.php'
};

// Referencias del DOM y Variables de estado
let categoryList, productGrid, orderItems, orderTotalElement, sendOrderBtn, quantitySelector, addTimeBtn,
    commentModal, commentModalItemName, commentInput, commentItemIndex, saveCommentBtn, cancelCommentBtn,
    closeCommentModalBtn, modifierModal, modalProductName, modifierGroupName, modifierOptions,
    closeModifierModalBtn, clockContainer, lockMessageContainer;
let searchInput, searchDropdown;

const tableNumber = parseInt(new URLSearchParams(window.location.search).get('table')) || 0;
let currentOrder = [];
let timeCounter = 1;
let activeOrderId = 0; // CRÍTICO: ID de la orden actual
let currentProduct = null;
let databaseTotal = 0;
let isInterfaceLocked = false; // Nuevo estado de bloqueo
let activeCategoryId = null; // Variable para polling

// Eliminamos la definición global de Polling (se define y usa localmente)
const PRODUCT_POLLING_INTERVAL = 5000; 

// CAMBIO: Esta es la ÚNICA función principal, y es 'async'
document.addEventListener('DOMContentLoaded', async () => {
    
    // --- NUEVAS VARIABLES LOCALES DE POLLING ---
    let productPollingId = null;

    // <<<--- INICIO DE LA VERIFICACIÓN DE TURNO --- >>>
    // 1. VERIFICACIÓN DE TURNO INICIAL
    try {
        const response = await fetch('/KitchenLink/src/api/cashier/history_reports/get_shift_status.php');
        const data = await response.json();

        if (!data.success || data.status === 'CLOSED') {
            alert("El turno de caja ha sido cerrado. La sesión se cerrará.");
            window.location.href = '/KitchenLink/src/php/logout.php';
            return; // Detenemos la carga del resto del script
        }

    } catch (error) {
        document.body.innerHTML = "<h1>Error fatal al verificar el estado del turno.</h1>";
        return; // Detenemos la carga
    }
    // --- 👆 FIN DE LA VERIFICACIÓN 👆 ---

    // --- Inicialización de elementos del DOM ---
    categoryList = document.getElementById('categoryList');
    productGrid = document.getElementById('productGrid');
    orderItems = document.getElementById('orderItems');
    orderTotalElement = document.getElementById('orderTotal');
    sendOrderBtn = document.getElementById('sendOrderBtn');
    quantitySelector = document.getElementById('quantitySelector');
    addTimeBtn = document.getElementById('addTimeBtn');
    commentModal = document.getElementById('commentModal');
    commentModalItemName = document.getElementById('commentModalItemName');
    commentInput = document.getElementById('commentInput');
    commentItemIndex = document.getElementById('commentItemIndex');
    saveCommentBtn = document.getElementById('saveCommentBtn');
    cancelCommentBtn = document.getElementById('cancelCommentBtn');
    closeCommentModalBtn = commentModal.querySelector('.close-btn');
    modifierModal = document.getElementById('modifierModal');
    modalProductName = document.getElementById('modalProductName');
    modifierGroupName = document.getElementById('modifierGroupName');
    modifierOptions = document.getElementById('modifierOptions');
    closeModifierModalBtn = modifierModal.querySelector('.close-btn');
    clockContainer = document.getElementById('liveClockContainer');
    searchInput = document.getElementById('productSearchInput');
    searchDropdown = document.getElementById('searchResultsDropdown');
    lockMessageContainer = document.getElementById('lockMessageContainer');

    // ----------------------------------------------------
    // LÓGICA DE BLOQUEO DE INTERFAZ
    // ----------------------------------------------------

    function lockTpvInterface() {
        isInterfaceLocked = true;
        sendOrderBtn.disabled = true;
        quantitySelector.disabled = true;
        addTimeBtn.disabled = true;
        
        const addModifiedBtn = document.getElementById('addModifiedItemBtn');
        if (addModifiedBtn) addModifiedBtn.disabled = true;
        
        productGrid.style.pointerEvents = 'none';
        categoryList.style.pointerEvents = 'none';
        
        const btnBack = document.querySelector('.btn-back');
        if(btnBack) btnBack.style.pointerEvents = 'auto'; 

        if (!lockMessageContainer) {
            lockMessageContainer = document.getElementById('lockMessageContainer');
        }
        
        if (lockMessageContainer) {
            lockMessageContainer.innerHTML = 
                '<p class="lock-message" style="color: red; font-weight: bold; text-align: center; margin-top: 10px;">' +
                'MESA BLOQUEADA: COBRO SOLICITADO POR CAJA.' +
                '</p>';
        }
    }

    function unlockTpvInterface() {
        isInterfaceLocked = false;
        sendOrderBtn.disabled = false;
        quantitySelector.disabled = false;
        addTimeBtn.disabled = false;
        
        const addModifiedBtn = document.getElementById('addModifiedItemBtn');
        if (addModifiedBtn) addModifiedBtn.disabled = false;

        productGrid.style.pointerEvents = 'auto';
        categoryList.style.pointerEvents = 'auto';
        
        const btnBack = document.querySelector('.btn-back');
        if(btnBack) btnBack.style.pointerEvents = 'auto'; 
        
        if (lockMessageContainer) {
            lockMessageContainer.innerHTML = '';
        }
        renderOrderSummary();
    }


    // ----------------------------------------------------
    // FUNCIONES CLAVE DE ORDEN Y TIEMPOS
    // ----------------------------------------------------

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

    // FUNCIÓN CORREGIDA PARA EL CÁLCULO COMBINADO
    function updateOrderTotal() {
        const newItemsSubtotal = currentOrder
            .filter(item => item.type === 'product' && !item.sentTimestamp)
            .reduce((sum, item) => sum + (item.price || 0), 0);

        const grandTotal = databaseTotal + newItemsSubtotal;

        orderTotalElement.textContent = `$${grandTotal.toFixed(2)}`;
    }

    // 💡 NUEVA FUNCIÓN: Iniciar el Polling (se define aquí para ver loadProducts)
    function startProductPolling() {
        if (productPollingId) clearInterval(productPollingId);
        
        productPollingId = setInterval(() => {
            if (activeCategoryId) {
                loadProducts(activeCategoryId); 
            }
        }, PRODUCT_POLLING_INTERVAL);
    }


    async function loadInitialOrder() {
        if (tableNumber <= 0) return;

        // 1. Obtener la data inyectada en el HTML
        const initialDataElement = document.getElementById('initialOrderData');
        if (!initialDataElement) {
             console.error("No se encontró #initialOrderData. La interfaz no puede cargar.");
             return;
        }
        
        const initialData = JSON.parse(initialDataElement.textContent);
        
        // 💡 VERIFICACIÓN CRÍTICA DEL ESTADO DE BLOQUEO AL CARGAR
        if (initialData.table_status === 'REQUESTED') {
            lockTpvInterface();
        } else {
            unlockTpvInterface(); 
        }

        try {
            const urlId = `${API_ROUTES.API_GET_ACTIVE_ORDER_ID}?table_number=${tableNumber}`;
            const orderIdResponse = await fetch(urlId);
            const orderIdData = await orderIdResponse.json();

            if (!orderIdData.success || !orderIdData.order_id) {
                activeOrderId = 0;
                currentOrder = [];
                databaseTotal = 0; 
                timeCounter = 1;
                renderOrderSummary();
                return;
            }

            activeOrderId = orderIdData.order_id;

            const itemsResponse = await fetch(`${API_ROUTES.API_GET_ORDER_ITEMS}?order_id=${activeOrderId}`);
            const data = await itemsResponse.json();

            if (!data.success) throw new Error(data.message || 'Error al obtener ítems de orden.');

            databaseTotal = parseFloat(data.total) || 0;
            
            const times = data.times || [];
            currentOrder = []; // Limpieza para evitar la duplicación de ítems enviados
            let maxTime = 0;

            times.forEach(timeBatch => {
                const displayTime = timeBatch.service_time;
                
                // ✅ CORRECCIÓN CRÍTICA PARA EL DESBLOQUEO/DUPLICACIÓN
                const batchTimestamp = timeBatch.items[0]?.added_at || timeBatch.items[0]?.batch_timestamp;
                // Si el batchTimestamp es nulo/inválido, asignamos '1' para que evalúe a true y mantenga el ítem bloqueado.
                const timestampMs = batchTimestamp ? new Date(batchTimestamp).getTime() : 1; 

                maxTime = Math.max(maxTime, displayTime);

                if (timeBatch.items && timeBatch.items.length > 0) {
                    currentOrder.push({
                        type: 'time',
                        name: `--- Tiempo ${displayTime} ---`,
                        // CRÍTICO: Usar el timestamp convertido para bloquear el tiempo.
                        sentTimestamp: timestampMs
                    });
                    timeBatch.items.forEach(item => {
                        currentOrder.push({
                            type: 'product',
                            id: item.id, name: item.name, price: item.price,
                            quantity: 1, comment: item.comment, modifier_id: item.modifier_id,
                            // CRÍTICO: Asignar el timestamp VÁLIDO del lote a cada producto.
                            sentTimestamp: timestampMs
                        });
                    });
                }
            });

            timeCounter = maxTime > 0 ? maxTime : 1; // Lógica de contador original

        } catch (error) {
            console.error('Error al cargar la orden inicial:', error);
            currentOrder = [];
            databaseTotal = 0;
            timeCounter = 1;
        }
        renderOrderSummary();
        if (orderItems) {
            orderItems.scrollTop = orderItems.scrollHeight;
        }
    }

    // ----------------------------------------------------
    // FUNCIONES AUXILIARES
    // ----------------------------------------------------

    function getFirstPendingTimeIndex() {
        return currentOrder.findIndex(item => item.type === 'time' && !item.sentTimestamp);
    }

    function addItemToOrder(item) {
        if (isInterfaceLocked) {
            alert("La mesa está bloqueada. Cobro solicitado.");
            return;
        }

        const itemToAdd = {
            ...item,
            type: 'product',
            comment: item.comment || '',
            quantity: 1,
            modifier_id: item.modifier_id || undefined
        };

        const lastPendingTimeIndex = currentOrder.findLastIndex(i => i.type === 'time' && !i.sentTimestamp);

        if (lastPendingTimeIndex === -1) {
            currentOrder.push(itemToAdd);
        } else {
            const nextTimeIndex = currentOrder.findIndex(
                (i, index) => index > lastPendingTimeIndex && i.type === 'time'
            );
            const insertionIndex = (nextTimeIndex !== -1) ? nextTimeIndex : currentOrder.length;
            currentOrder.splice(insertionIndex, 0, itemToAdd);
        }

        renderOrderSummary();
        if (orderItems) orderItems.scrollTop = orderItems.scrollHeight;
    }

    function renderOrderSummary() {
        if (!orderItems) return;

        currentOrder = currentOrder.filter((item, index, arr) => {
            if (item.type !== 'time') return true;
            if (item.sentTimestamp) return true;
            if (item.name.includes('Tiempo 1')) return true;

            const nextTimeIndex = arr.slice(index + 1).findIndex(i => i.type === 'time');
            const productsInBlock = nextTimeIndex === -1 ?
                arr.slice(index + 1).some(i => i.type === 'product' && !i.sentTimestamp) :
                arr.slice(index + 1, index + 1 + nextTimeIndex).some(i => i.type === 'product' && !i.sentTimestamp);

            return productsInBlock;
        });

        const hasPendingTime = currentOrder.some(i => i.type === 'time' && !i.sentTimestamp);
        if (!hasPendingTime) {
            currentOrder.push({
                type: 'time',
                name: `--- Tiempo ${timeCounter} ---`
            });
        } else {
            const lastPendingIndex = currentOrder.findLastIndex(i => i.type === 'time' && !i.sentTimestamp);
            if (lastPendingIndex !== -1) {
                currentOrder[lastPendingIndex].name = `--- Tiempo ${timeCounter} ---`;
            }
        }

        orderItems.innerHTML = '';
        currentOrder.forEach((item, index) => {
            const itemDiv = document.createElement('div');
            const isTime = item.type === 'time';
            const isEditable = !item.sentTimestamp;

            if (isTime) {
                itemDiv.className = `order-time-separator ${isEditable ? 'time-pending' : 'time-sent'}`;
                itemDiv.innerHTML = `<span>${item.name}</span>`;
                if (!isEditable) {
                    itemDiv.classList.add('time-permanent');
                } else if (item.name.includes('Tiempo 1')) {
                    const nextTimeIndex = currentOrder.slice(index + 1).findIndex(i => i.type === 'time');
                    const productsInBlock = nextTimeIndex === -1 ?
                        currentOrder.slice(index + 1).some(i => i.type === 'product' && !i.sentTimestamp) :
                        currentOrder.slice(index + 1, index + 1 + nextTimeIndex).some(i => i.type === 'product' && !i.sentTimestamp);
                    if (!productsInBlock) itemDiv.classList.add('time-permanent');
                }
            } else {
                itemDiv.className = `order-item ${isEditable ? '' : 'locked-item'}`;
                itemDiv.dataset.index = index;
                const commentHTML = item.comment ? `<span class="item-comment"><i class="fas fa-sticky-note"></i> ${item.comment}</span>` : '';
                const displayQuantity = item.quantity && item.quantity > 1 ? `${item.quantity}x ` : '';
                itemDiv.innerHTML = `
                    <div class="item-details"><span class="item-name">${displayQuantity}${item.name}</span>${commentHTML}</div>
                    <span class="item-price">$${item.price.toFixed(2)}</span>
                    ${isEditable ? `<button class="btn-remove" data-index="${index}">&times;</button>` : '<span class="item-locked"><i class="fas fa-lock"></i></span>'}`;
            }
            orderItems.appendChild(itemDiv);
        });

        const hasNewItems = currentOrder.some(i => i.type === 'product' && !i.sentTimestamp);
        const hasSentItems = currentOrder.some(i => i.type === 'product' && i.sentTimestamp);

        // Si la interfaz no está bloqueada, controlamos los botones de envío normalmente
        if (!isInterfaceLocked) {
            sendOrderBtn.disabled = !hasNewItems;
            sendOrderBtn.textContent = hasSentItems ? 'Actualizar Comanda' : 'Enviar Comanda'; //Se modifico el texto del botón para reflejar si es una nueva orden o una actualización 
            
            const pendingTimeIndex = getFirstPendingTimeIndex();
            const hasProductsInActiveTime = pendingTimeIndex !== -1 && currentOrder.slice(pendingTimeIndex + 1).some(i => i.type === 'product' && !i.sentTimestamp);
            addTimeBtn.disabled = !hasProductsInActiveTime;
        } else {
            // Aseguramos que el texto de envío refleje el estado bloqueado
            sendOrderBtn.textContent = 'MESA BLOQUEADA';
        }


        updateOrderTotal();
    }

    async function sendOrderToKitchen() {
        // 💡 BLOQUEO EN EL FLUJO: Si la interfaz está bloqueada, no envía la orden.
        if (isInterfaceLocked) {
            alert("No se puede enviar la orden: Cobro solicitado.");
            return;
        }
        
        const timesMap = {};
        let current_service_time = 0;

        for (const item of currentOrder) {
            if (item.type === 'time') {
                const timeMatch = item.name.match(/Tiempo (\d+)/);
                if (timeMatch) {
                    current_service_time = parseInt(timeMatch[1]);
                }
            } else if (item.type === 'product' && !item.sentTimestamp) {
                if (current_service_time > 0) {
                    if (!timesMap[current_service_time]) {
                        timesMap[current_service_time] = {
                            service_time: current_service_time,
                            items: []
                        };
                    }
                    timesMap[current_service_time].items.push({
                        id: item.id,
                        quantity: item.quantity || 1,
                        comment: item.comment,
                        modifier_id: item.modifier_id
                    });
                }
            }
        }

        const finalTimesToSend = Object.values(timesMap);

        if (finalTimesToSend.length === 0) {
            console.warn("sendOrderToKitchen fue llamada, pero no se encontraron ítems nuevos para enviar.");
            renderOrderSummary();
            return;
        }

        sendOrderBtn.disabled = true;
        sendOrderBtn.textContent = 'Enviando...';
        try {
            const response = await fetch(API_ROUTES.API_SEND_ORDER, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    table_number: tableNumber,
                    times: finalTimesToSend,
                    // ✅ CRÍTICO: Enviar el ID para que el servidor sepa si debe crear o actualizar.
                    order_id: activeOrderId 
                })
            });

            // Capturamos el error si la MESA FUE CERRADA (410 Gone)
            if (response.status === 410) { 
                const errorData = await response.json();
                alert(errorData.message + '\nRegresando a la lista de mesas.');
                window.location.href = '/KitchenLink/src/php/orders.php'; // Regresar a lista de mesas
                return;
            }

            // Capturamos el error si la CUENTA FUE SOLICITADA (403 Forbidden)
            if (response.status === 403) {
                const errorData = await response.json();
                alert(errorData.message); 
                lockTpvInterface();     
                return;                 
            }
            
            // 💡 Capturamos la respuesta del servidor (incluyendo errores de Stock 86)
            const result = await response.json();
            
            if (!result.success) {
                // Si el servidor falla (ej: stock insuficiente), alerta y recarga (para reflejar 86)
                throw new Error(result.message || 'Error desconocido.');
            }

            // ✅ CRÍTICO: Actualizar activeOrderId si el servidor devuelve el ID (primera orden).
            if (result.order_id && activeOrderId === 0) {
                 activeOrderId = result.order_id;
            }

            // ✅ CRÍTICO: Usar await. Esperar a la recarga de la orden para que el estado local se sincronice con el servidor.
            await loadInitialOrder(); 

        } catch (error) {
            console.error('Error al enviar la orden:', error);
            alert(`Error al enviar la comanda: ${error.message}`);
            // Forzamos la recarga de productos si hay error (para ver el 86 aplicado)
            if (activeCategoryId) loadProducts(activeCategoryId);
            renderOrderSummary();
        }
    }

    // 💡 MODIFICADO: FUNCIÓN PARA MOSTRAR PRODUCTOS CON LÓGICA DE STOCK (85/86)
    function renderProducts(products) {
        productGrid.innerHTML = '';
        if (products.length === 0) {
            productGrid.innerHTML = '<p>No hay productos en esta categoría.</p>';
            return;
        }
        
        products.forEach(product => {
            const button = document.createElement('button');
            
            // 1. Lógica de Bloqueo (86): Comprobamos disponibilidad (is_available) y stock (stock_quantity = 0)
            const isAgotado = product.is_available == 0 || (product.stock_quantity !== null && product.stock_quantity == 0);
            
            let className = 'product-item-btn';
            
            // Si está agotado, añadimos clase especial y deshabilitamos
            if (isAgotado) {
                className += ' product-agotado';
                button.disabled = true; // 🛑 BLOQUEO FUNCIONAL
            }

            button.className = className;
            button.dataset.productId = product.product_id;
            button.dataset.price = product.price;
            button.dataset.modifierGroupId = product.modifier_group_id;
            
            // 2. Lógica de Badge de Stock (85)
            let stockBadge = '';
            // Si tiene conteo (no es NULL) y no está agotado
            if (product.stock_quantity !== null && !isAgotado) {
                stockBadge = `<span class="tpv-stock-badge">Quedan: ${product.stock_quantity}</span>`;
            }
            
            // 3. Etiqueta de Agotado (Visual)
            let agotadoLabel = isAgotado ? '<div class="agotado-overlay">AGOTADO</div>' : '';

            button.innerHTML = `
                ${agotadoLabel}
                <span class="product-name">${product.name}</span>
                ${stockBadge}
                <span class="product-price">$${parseFloat(product.price).toFixed(2)}</span>
            `;
            
            productGrid.appendChild(button);
        });
        
        // Bloqueo general de interfaz (por caja, no por stock)
        if (isInterfaceLocked) {
            productGrid.style.pointerEvents = 'none';
        } else {
            productGrid.style.pointerEvents = 'auto';
        }
    }

    async function handleCategoryClick(categoryId) {
        // 💡 BLOQUEO EN EL FLUJO: Si la interfaz está bloqueada, no permite cambiar categorías.
        if (isInterfaceLocked) { 
            alert("La mesa está bloqueada. Cobro solicitado.");
            return;
        }
        
        activeCategoryId = categoryId; // Guardar la categoría activa para el Polling
        productGrid.innerHTML = '<p id="productLoading">Cargando productos...</p>';

        document.querySelectorAll('.category-item').forEach(item => {
            if (item.dataset.categoryId == categoryId) {
                item.classList.add('active');
            } else {
                item.classList.remove('active');
            }
        });

        loadProducts(categoryId);
    }
    
    async function loadProducts(categoryId) {
        try {
            const response = await fetch(`${API_ROUTES.API_PRODUCT_URL}?category_id=${categoryId}`);
            const data = await response.json();
            if (data.success) {
                renderProducts(data.products);
                startProductPolling(); // 💡 Reiniciar el polling después de cada carga exitosa
            } else {
                productGrid.innerHTML = `<p class="error">Error: ${data.message}</p>`;
            }
        } catch (error) {
            console.error('Error al obtener productos:', error);
            productGrid.innerHTML = '<p class="error">Error de conexión con el servidor.</p>';
        }
    }

    async function loadModifiers(groupId) {
        modalProductName.textContent = currentProduct.name;
        modifierOptions.innerHTML = '<p>Cargando opciones...</p>';
        modifierModal.style.display = 'flex';
        try {
            const url = `${API_ROUTES.API_MODIFIER_URL}?group_id=${groupId}`;
            const response = await fetch(url);
            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            const data = await response.json();
            if (data.success) {
                modifierGroupName.textContent = data.group_name || 'Opción';
                renderModifiers(data.modifiers);
            } else {
                modifierGroupName.textContent = 'Error';
                modifierOptions.innerHTML = `<p class="error">${data.message}</p>`;
            }
        } catch (error) {
            console.error('Error al obtener modificadores:', error);
            modifierGroupName.textContent = 'Error:';
            modifierOptions.innerHTML = '<p class="error">Error de conexión.</p>';
        }
    }

    // 💡 MODIFICADO: FUNCIÓN PARA MOSTRAR MODIFICADORES CON LÓGICA DE STOCK (85/86)
    function renderModifiers(modifiers) {
        modifierOptions.innerHTML = '';
        modifiers.forEach(mod => {
            // Lógica de Bloqueo (86) para modificadores
            const isAgotado = mod.is_active == 0 || (mod.stock_quantity !== null && mod.stock_quantity == 0);
            const isDisabled = isAgotado;
            
            // Lógica de Stock (85)
            let stockInfo = '';
            if (mod.stock_quantity !== null) {
                stockInfo = `(Quedan: ${mod.stock_quantity})`;
            }

            const label = document.createElement('label');
            // Añadir clase 'option-agotada' si está agotado (para CSS)
            label.className = `modifier-option ${isAgotado ? 'option-agotada' : ''}`;
            
            const priceHtml = parseFloat(mod.modifier_price).toFixed(2) > 0 ? `(+$${parseFloat(mod.modifier_price).toFixed(2)})` : '';
            
            label.innerHTML = `
                <input type="radio" name="modifier-choice" value="${mod.modifier_id}" data-price="${mod.modifier_price}" ${isDisabled ? 'disabled' : ''}>
                <span class="modifier-name">${mod.modifier_name}</span>
                <span class="modifier-price">${priceHtml}</span>
                <span class="modifier-stock-info">${stockInfo}</span>
                ${isAgotado ? '<span class="agotado-label">AGOTADO</span>' : ''}
            `;
            modifierOptions.appendChild(label);
        });

        // 💡 Bloquear el botón de añadir si no hay modificadores disponibles
        const addModifiedBtn = document.getElementById('addModifiedItemBtn');
        const availableModifiers = modifiers.some(mod => mod.is_active == 1 && (mod.stock_quantity === null || mod.stock_quantity > 0));
        if (addModifiedBtn) {
            addModifiedBtn.disabled = !availableModifiers;
        }
    }


    let searchTimeout;

    function setupSearchListeners() {
        searchInput.addEventListener('input', () => {
            clearTimeout(searchTimeout);
            const query = searchInput.value.trim();
            
            // 💡 BLOQUEO: Prevenir búsqueda si está bloqueado
            if (isInterfaceLocked) {
                 searchDropdown.style.display = 'none';
                 return;
            }

            if (query.length < 2) {
                searchDropdown.style.display = 'none';
                return;
            }
            searchTimeout = setTimeout(() => executeGlobalSearch(query), 300);
        });

        document.addEventListener('click', (e) => {
            if (!searchInput.contains(e.target) && !searchDropdown.contains(e.target)) {
                searchDropdown.style.display = 'none';
            }
        });

        searchDropdown.addEventListener('click', (e) => {
            // 💡 BLOQUEO: Prevenir selección de búsqueda si está bloqueado
            if (isInterfaceLocked) return;
            
            const item = e.target.closest('.search-result-item');
            if (item) {
                const productId = parseInt(item.dataset.productId);
                const price = parseFloat(item.dataset.price);
                const categoryId = parseInt(item.dataset.categoryId);

                const rawModId = item.dataset.modifierGroupId;
                const modifierGroupId = (rawModId && rawModId !== 'null' && rawModId !== '0') ? parseInt(rawModId) : null;

                const name = item.querySelector('.result-name').textContent.replace(/AGOTADO/, '').trim(); // Limpiar la etiqueta de agotado
                const quantity = parseInt(quantitySelector.value) || 1;

                currentProduct = {
                    id: productId,
                    name: name,
                    price: price,
                    modifierGroupId: modifierGroupId,
                    quantity: quantity
                };
                
                // 💡 Asegurarse de que el producto encontrado no esté agotado (86)
                if (item.classList.contains('product-agotado')) {
                     alert("Este producto está agotado (86) y no puede ser añadido.");
                     searchInput.value = '';
                     searchDropdown.style.display = 'none';
                     return;
                }

                handleCategoryClick(categoryId);

                if (currentProduct.modifierGroupId) {
                    loadModifiers(currentProduct.modifierGroupId);
                } else {
                    for (let i = 0; i < quantity; i++) {
                        addItemToOrder({
                            id: currentProduct.id,
                            name: currentProduct.name,
                            price: currentProduct.price,
                            quantity: 1,
                            modifierGroupId: currentProduct.modifierGroupId
                        });
                    }
                }

                searchInput.value = '';
                searchDropdown.style.display = 'none';
            }
        });
    }

    async function executeGlobalSearch(query) {
        // 💡 BLOQUEO: Prevenir si ya está bloqueado
        if (isInterfaceLocked) return;
        
        try {
            const response = await fetch(`${API_ROUTES.API_SEARCH_PRODUCT}?query=${encodeURIComponent(query)}`);
            const data = await response.json();

            if (data.success && data.products.length > 0) {
                renderSearchResults(data.products);
            } else {
                searchDropdown.innerHTML = '<div class="search-result-item">No se encontraron productos.</div>';
                searchDropdown.style.display = 'block';
            }
        } catch (error) {
            searchDropdown.innerHTML = '<div class="search-result-item">Error de conexión.</div>';
            searchDropdown.style.display = 'block';
        }
    }

    function renderSearchResults(products) {
        searchDropdown.innerHTML = '';
        products.forEach(product => {
            const item = document.createElement('div');
            
            // 💡 Lógica de stock para búsqueda (85/86)
            const isAgotado = product.is_available == 0 || (product.stock_quantity !== null && product.stock_quantity == 0);
            
            item.className = `search-result-item ${isAgotado ? 'product-agotado' : ''}`;

            item.dataset.productId = product.product_id;
            item.dataset.price = product.price;
            item.dataset.categoryId = product.category_id;
            item.dataset.modifierGroupId = product.modifier_group_id || '';
            
            const stockInfo = product.stock_quantity !== null ? `(Quedan: ${product.stock_quantity})` : '';
            const agotadoLabel = isAgotado ? '<span class="agotado-label">AGOTADO</span>' : '';


            item.innerHTML = `
                <span class="result-name">${product.name} ${agotadoLabel}</span>
                <span class="result-price">$${product.price.toFixed(2)} ${stockInfo}</span>
            `;
            searchDropdown.appendChild(item);
        });

        searchDropdown.style.display = 'block';
    }

    // --- Inicialización de elementos del DOM ---
    const initialDataElement = document.getElementById('initialOrderData');
    if (initialDataElement) {
        const initialData = JSON.parse(initialDataElement.textContent);
        
        if (initialData.table_status === 'REQUESTED') {
            lockTpvInterface();
        } else {
            unlockTpvInterface();
        }
    }


    updateClock();
    setInterval(updateClock, 1000);

    sendOrderBtn.addEventListener('click', sendOrderToKitchen);

    // ✨ --- BLOQUE DE VALIDACIÓN AÑADIDO --- ✨
    if (quantitySelector) {
        quantitySelector.addEventListener('input', () => {
            if (isInterfaceLocked) return; // BLOQUEO
            
            let value = quantitySelector.value;
            // 1. Solo permite dígitos
            value = value.replace(/[^0-9]/g, '');
            // 2. Si el valor es '0', lo borra
            if (value === '0') {
                value = '';
            }
            // 3. Limita a 2 dígitos
            if (value.length > 2) {
                value = value.substring(0, 2);
            }
            // 4. Previene que sea mayor a 99
            if (parseInt(value, 10) > 99) {
                value = '99';
            }
            quantitySelector.value = value;
        });

        quantitySelector.addEventListener('blur', () => {
            if (isInterfaceLocked) return; // BLOQUEO
            
            // Si el campo queda vacío o es menor a 1, lo establece en 1
            if (quantitySelector.value === '' || parseInt(quantitySelector.value, 10) < 1) {
                quantitySelector.value = '1';
            }
        });
    }
    // --- FIN DEL BLOQUE DE VALIDACIÓN ---

    categoryList.addEventListener('click', (e) => {
        if (isInterfaceLocked) return; // BLOQUEO
        
        const categoryItem = e.target.closest('.category-item');
        if (categoryItem) {
            e.preventDefault();
            handleCategoryClick(categoryItem.dataset.categoryId);
        }
    });

    productGrid.addEventListener('click', (e) => {
        if (isInterfaceLocked) {
            alert("La mesa está bloqueada. Cobro solicitado.");
            return;
        }

        const productBtn = e.target.closest('.product-item-btn');
        if (!productBtn) return;
        
        // 💡 Bloquear si el producto está agotado (la tarjeta tiene la clase 'product-agotado')
        if (productBtn.classList.contains('product-agotado')) {
            // El botón ya debería estar deshabilitado, pero esta es una capa de seguridad extra.
            alert("Producto agotado (86).");
            return;
        }
        
        // ... (resto de la lógica de adición de productos) ...
        const quantity = parseInt(quantitySelector.value) || 1;

        currentProduct = {
            id: parseInt(productBtn.dataset.productId),
            name: productBtn.querySelector('.product-name').textContent.replace(/Quedan:\s\d+/, '').trim(), // Limpiar badge de stock del nombre
            price: parseFloat(productBtn.dataset.price),
            modifierGroupId: parseInt(productBtn.dataset.modifierGroupId) || null,
            quantity: quantity
        };

        if (currentProduct.modifierGroupId) {
            loadModifiers(currentProduct.modifierGroupId);
        } else {
            for (let i = 0; i < quantity; i++) {
                addItemToOrder({
                    id: currentProduct.id,
                    name: currentProduct.name,
                    price: currentProduct.price,
                    quantity: 1,
                    modifierGroupId: currentProduct.modifierGroupId
                });
            }
        }
    });

    setupSearchListeners();

    document.getElementById('addModifiedItemBtn').addEventListener('click', () => {
        if (isInterfaceLocked) {
            alert("La mesa está bloqueada. Cobro solicitado.");
            return;
        }
        
        if (!currentProduct) return;
        const selectedRadio = modifierOptions.querySelector('input[name="modifier-choice"]:checked');
        if (!selectedRadio) {
            alert("Por favor, selecciona una opción.");
            return;
        }
        
        // 💡 Bloqueo final si el modificador seleccionado está agotado
        if (selectedRadio.disabled) {
            alert("La opción seleccionada está agotada (86).");
            return;
        }


        const quantity = currentProduct.quantity;
        const modifier = {
            id: parseInt(selectedRadio.value),
            name: selectedRadio.closest('label').querySelector('.modifier-name').textContent.trim(),
            price: parseFloat(selectedRadio.dataset.price)
        };
        const unitPrice = currentProduct.price + modifier.price;

        for (let i = 0; i < quantity; i++) {
            const combinedItem = {
                id: currentProduct.id,
                name: `${currentProduct.name} (${modifier.name})`,
                price: unitPrice,
                modifier_id: modifier.id,
                quantity: 1
            };
            addItemToOrder(combinedItem);
        }

        modifierModal.style.display = 'none';
        currentProduct = null;
    });

    addTimeBtn.addEventListener('click', () => {
        if (isInterfaceLocked) {
            alert("La mesa está bloqueada. Cobro solicitado.");
            return;
        }
        
        const pendingTimes = currentOrder.filter(i => i.type === 'time' && !i.sentTimestamp);
        if (!pendingTimes.length) return;

        const activeTime = pendingTimes[pendingTimes.length - 1];
        const activeIndex = currentOrder.lastIndexOf(activeTime);
        currentOrder[activeIndex].sentTimestamp = Date.now();
        timeCounter++;
        currentOrder.push({
            type: 'time',
            name: `--- Tiempo ${timeCounter} ---`
        });
        renderOrderSummary();
        if (orderItems) orderItems.scrollTop = orderItems.scrollHeight;
    });

    saveCommentBtn.addEventListener('click', () => {
        if (isInterfaceLocked) return; // BLOQUEO
        
        const index = parseInt(commentItemIndex.value);
        if (index >= 0 && currentOrder[index]) {
            currentOrder[index].comment = commentInput.value.trim();
            renderOrderSummary();
            commentModal.style.display = 'none';
        }
    });

    [closeModifierModalBtn, closeCommentModalBtn, cancelCommentBtn].forEach(btn => {
        btn.addEventListener('click', () => {
            modifierModal.style.display = 'none';
            commentModal.style.display = 'none';
        });
    });

    orderItems.addEventListener('click', (e) => {
        // Bloqueo de eliminación y edición de comentarios
        if (isInterfaceLocked) {
            const removeBtn = e.target.closest('.btn-remove');
            const itemElement = e.target.closest('.order-item');
            if (removeBtn || itemElement) {
                alert("La mesa está bloqueada. Cobro solicitado.");
                return;
            }
        }
        
        const removeBtn = e.target.closest('.btn-remove');
        if (removeBtn) {
            e.stopPropagation();
            const indexToRemove = parseInt(removeBtn.dataset.index);
            if (!isNaN(indexToRemove) && currentOrder[indexToRemove] && !currentOrder[indexToRemove].sentTimestamp) {
                currentOrder.splice(indexToRemove, 1);
                renderOrderSummary();
                if (orderItems) {
                    orderItems.scrollTop = orderItems.scrollHeight;
                }
            }
            return;
        }

        const itemElement = e.target.closest('.order-item');
        if (itemElement) {
            const index = parseInt(itemElement.dataset.index);
            if (isNaN(index) || !currentOrder[index]) return;
            const item = currentOrder[index];
            const isItemEditable = !item.sentTimestamp;
            if (isItemEditable && item.type === 'product') {
                commentModalItemName.textContent = item.name;
                commentInput.value = item.comment || '';
                commentItemIndex.value = index;
                commentModal.style.display = 'flex';
            }
        }
    });

    loadInitialOrder();

    const firstCategory = document.querySelector('.category-item');
    if (firstCategory) {
        handleCategoryClick(firstCategory.dataset.categoryId);
    }
    
    // ----------------------------------------------------
    // 🔄 INICIO DEL POLLING (REFRESCO DE STOCK)
    // ----------------------------------------------------
    startProductPolling();

    // ----------------------------------------------------
    // 🔒 LÓGICA DE SEMÁFORO (CONCURRENCIA)
    // ----------------------------------------------------

    // 1. El Latido (Heartbeat): Mantiene la mesa ocupada
    // Se ejecuta cada 20 segundos para no saturar tu servidor gratuito.
    setInterval(() => {
        // Solo enviamos señal si hay una mesa válida y NO estamos bloqueados por la caja
        if (typeof tableNumber !== 'undefined' && tableNumber > 0 && !isInterfaceLocked) {
            fetch('/KitchenLink/src/api/orders/renew_lock.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ table_number: tableNumber })
            }).catch(err => console.error("Error en heartbeat", err));
        }
    }, 20000); 


    // 2. Liberación al Salir: Desbloquea la mesa si cierran la pestaña o regresan
    window.addEventListener('beforeunload', () => {
        if (productPollingId) clearInterval(productPollingId); // Detiene el polling
        
        if (typeof tableNumber !== 'undefined' && tableNumber > 0 && !isInterfaceLocked) {
            const data = JSON.stringify({ table_number: tableNumber });
            // Usamos sendBeacon para asegurar que se envíe aunque se cierre el navegador
            const blob = new Blob([data], {type: 'application/json'});
            navigator.sendBeacon('/KitchenLink/src/api/orders/unlock_table.php', blob);
        }
    });

}); // <-- Este es el ÚNICO cierre del 'DOMContentLoaded'