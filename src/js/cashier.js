// /src/js/cashier.js

// 💥 MODIFICACIÓN: Hacemos el listener principal ASÍNCRONO
document.addEventListener('DOMContentLoaded', async () => {

    // <<<--- INICIO DE LA IMPLEMENTACIÓN NUEVA --->>>
    
    // 1. VERIFICACIÓN DE TURNO
    // Primero, verificamos si el turno está abierto antes de cargar esta pantalla.
    try {
        const response = await fetch('/src/api/cashier/history_reports/get_shift_status.php');
        
        if (!response.ok) {
            throw new Error(`Error de red: ${response.status}`);
        }

        const data = await response.json();

        if (!data.success || data.status === 'CLOSED') {
            // 2. ¡TURNO CERRADO!
            // Bloqueamos la carga de esta página y redirigimos.
            window.appAlert("El turno está cerrado. Debes abrir un nuevo turno desde 'Historial y Reportes' para continuar.");
            // Redirigimos a la pantalla de administración
            window.location.href = '/src/php/sales_history.php';
            return; // Detenemos la ejecución de este script
        }

        // 3. TURNO ABIERTO
        // Si llegamos aquí, el turno está 'OPEN'. El resto del script puede continuar.
        console.log('Turno verificado. Estado: ABIERTO.');

    } catch (error) {
        console.error("Error crítico verificando el turno:", error);
        document.body.innerHTML = `<h1><i class="fas fa-exclamation-triangle"></i> Error fatal al verificar el estado del turno.</h1><p>${error.message}. Contacte al administrador.</p>`;
        return; // Detenemos la ejecución
    }
    
    // <<<--- FIN DE LA IMPLEMENTACIÓN NUEVA --->>>


    // --- ELEMENTOS DEL DOM ---
    // (Tu código original continúa aquí sin cambios)
    const clockContainer = document.getElementById('liveClockContainer');
    const openAccountsList = document.getElementById('openAccountsList');
    const accountDetailsContent = document.getElementById('accountDetailsContent');
    const btnPrintTicket = document.getElementById('btn-print-ticket');
    const btnProcessPayment = document.getElementById('btn-process-payment');
    const paymentModal = document.getElementById('paymentModal');
    const closeModalButton = paymentModal.querySelector('.close-button');
    const modalTableNumber = document.getElementById('modalTableNumber');
    const modalTotalAmount = document.getElementById('modalTotalAmount');
    const modalRemainingAmount = document.getElementById('modalRemainingAmount');
    const paymentAmountInput = document.getElementById('paymentAmountInput');
    const paymentMethodButtons = paymentModal.querySelectorAll('.method-btn');
    const cashChangeSection = document.getElementById('cashChangeSection');
    const cashReceivedInput = document.getElementById('cashReceivedInput');
    const cashChangeAmount = document.getElementById('cashChangeAmount');
    const paymentsMadeList = document.getElementById('paymentsMadeList');
    const btnFinalizePayment = document.getElementById('btn-finalize-payment');
    const totalTipSection = document.getElementById('totalTipSection');
    const modalTotalTipAmount = document.getElementById('modalTotalTipAmount');

    // --- VARIABLES DE ESTADO ---
    let selectedOrderId = null;
    let currentAccountDetails = null;
    let paymentsRegistered = [];
    let totalDue = 0;
    let discountAmount = 0;
    
    const POLLING_INTERVAL_MS = 10000; // 💡 10 segundos para actualizar la lista

    // --- RELOJ Y UTILIDADES ---
    function updateClock() {
        if (!clockContainer) return;
        const now = new Date();
        const hours = String(now.getHours()).padStart(2, '0');
        const minutes = String(now.getMinutes()).padStart(2, '0');
        const seconds = String(now.getSeconds()).padStart(2, '0');
        
        // Formato: "Nov 08 21:45:12"
        clockContainer.textContent = now.toLocaleDateString('es-MX', { month: 'short', day: '2-digit' }) + ` ${hours}:${minutes}:${seconds}`;
    }
    const formatCurrency = (amount) => `$${parseFloat(amount).toFixed(2)}`;

    // --- LÓGICA DE API ---
    async function fetchOpenAccounts() {
        try {
            // Nota: Esta API debe incluir 'pre_bill_status' de restaurant_tables en su respuesta
            const response = await fetch('/src/api/cashier/get_open_accounts.php');
            if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
            const result = await response.json();
            if (result.success) {
                renderOpenAccounts(result.data);
            } else {
                openAccountsList.innerHTML = `<p class="error-message">${result.message || 'Error al cargar las cuentas.'}</p>`;
            }
        } catch (error) {
            console.error('Error al obtener cuentas abiertas:', error);
            openAccountsList.innerHTML = `<p class="error-message">No se pudo conectar con el servidor.</p>`;
        }
    }

    async function fetchAccountDetails(orderId) {
        accountDetailsContent.innerHTML = '<p>Cargando detalles...</p>';
        discountAmount = 0;
        try {
            const response = await fetch(`/src/api/cashier/get_account_details.php?order_id=${orderId}`);
            if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
            const result = await response.json();
            if (result.success) {
                currentAccountDetails = result.data;
                renderAccountDetails(currentAccountDetails);
                btnProcessPayment.disabled = false;
                btnPrintTicket.disabled = false;
            } else {
                accountDetailsContent.innerHTML = `<p class="error-message">${result.message || 'Error al cargar los detalles.'}</p>`;
            }
        } catch (error) {
            console.error('Error al obtener detalles de la cuenta:', error);
            accountDetailsContent.innerHTML = `<p class="error-message">No se pudo conectar con el servidor.</p>`;
        }
    }

    // --- FUNCIONES DE RENDERIZADO ---
    function renderOpenAccounts(accounts) {
        openAccountsList.innerHTML = '';
        if (accounts.length === 0) {
            openAccountsList.innerHTML = '<p>No se encontraron cuentas abiertas.</p>';
            return;
        }
        accounts.forEach(account => {
            const li = document.createElement('li');
            li.className = 'account-item';
            li.dataset.orderId = account.order_id;

            // 💡 LÓGICA DE ALERTA VISUAL (Recibe pre_bill_status de la API)
            if (account.pre_bill_status === 'REQUESTED') {
                li.classList.add('prebill-requested'); // Estilo para cambiar el color
            }

            // 💥 CRÍTICO: Estructura simple para posicionamiento absoluto del botón
            li.innerHTML = `
                <div class="account-details-wrapper">
                    <div class="account-info">
                        <strong>Mesa ${account.table_number}</strong>
                        <span>Atendido por: ${account.server_name}</span>
                    </div>
                    <div class="account-total">${formatCurrency(account.total_amount)}</div>
                </div>
                <div class="account-actions-box"></div> 
            `;
            
            li.addEventListener('click', () => {
                document.querySelectorAll('.account-item.selected').forEach(el => el.classList.remove('selected'));
                li.classList.add('selected');
                selectedOrderId = account.order_id;
                fetchAccountDetails(account.order_id);
            });

            // 💡 LÓGICA DEL BOTÓN DE CANCELACIÓN DINÁMICO
            if (account.pre_bill_status === 'REQUESTED') {
                const actionsBox = li.querySelector('.account-actions-box'); // Buscamos el contenedor actions-box
                const cancelButton = document.createElement('button');
                
                cancelButton.textContent = '❌ Cancelar Solicitud';
                cancelButton.className = 'cancel-prebill-btn';
                cancelButton.dataset.orderId = account.order_id;
                
                cancelButton.addEventListener('click', (e) => {
                    e.stopPropagation(); // Evita que se seleccione la mesa al hacer clic en el botón
                    cancelPreBillRequest(account.order_id);
                });
                
                actionsBox.appendChild(cancelButton); // Insertamos el botón en el contenedor
            }

            openAccountsList.appendChild(li);
        });
    }

    function renderAccountDetails(details) {
        const groupedItems = {};
        details.items.forEach(item => {
            const key = item.was_cancelled 
                ? `cancelled-${item.detail_id}` 
                : `${item.product_name}-${item.modifier_name || 'none'}`;

            if (groupedItems[key]) {
                groupedItems[key].quantity += item.quantity;
            } else {
                groupedItems[key] = { ...item };
            }
        });
        const finalItemsToRender = Object.values(groupedItems);

        const itemsHtml = finalItemsToRender.map(item => {
            const itemTotal = item.quantity * item.price_at_order;
            const modifierHtml = item.modifier_name ? ` <span class="modifier-text">(${item.modifier_name})</span>` : '';
            const cancelledClass = item.was_cancelled ? 'cancelled' : '';
            
            return `<div class="item-row ${cancelledClass}">
                        <span class="item-qty">${item.quantity}</span>
                        <span class="item-name">${item.product_name}${modifierHtml}</span>
                        <span class="item-price">${formatCurrency(item.price_at_order)}</span>
                        <span class="item-total">${formatCurrency(itemTotal)}</span>
                    </div>`;
        }).join('');

        const subtotal = parseFloat(details.subtotal);
        const taxableBase = Math.max(0, subtotal - discountAmount);
        const tax = taxableBase * 0.16;
        const finalTotal = taxableBase + tax;
        totalDue = finalTotal;

        const removeDiscountButtonHtml = discountAmount > 0 ? `<button id="removeDiscountBtn" title="Quitar descuento">❌</button>` : '';

        const detailsHtml = `
            <div class="account-header"><h4>Orden #${details.order_id} para Mesa ${details.table_number}</h4><p>Abierta a las: ${new Date(details.order_time).toLocaleTimeString()}</p></div>
            <div class="order-items-list">${itemsHtml}</div>
            <div class="order-summary-totals">
                <div class="total-row"><span>Subtotal:</span><span>${formatCurrency(subtotal)}</span></div>
                <div class="discount-section">
                    <input type="text" id="discountInput" placeholder="Ej: 10% o 50">
                    <button id="applyDiscountBtn">Aplicar Descuento</button>
                </div>
                <div class="total-row discount">
                    <span>Descuento:</span>
                    <span>-${formatCurrency(discountAmount)} ${removeDiscountButtonHtml}</span>
                </div>
                <div class="total-row"><span>IVA (16%):</span><span>${formatCurrency(tax)}</span></div>
                <div class="total-row grand-total"><span>Total a Pagar:</span><span>${formatCurrency(finalTotal)}</span></div>
            </div>`;
        accountDetailsContent.innerHTML = detailsHtml;

        document.getElementById('applyDiscountBtn').addEventListener('click', applyDiscount);
        document.getElementById('discountInput').addEventListener('input', validateDiscountInput);
        if (discountAmount > 0) {
            document.getElementById('removeDiscountBtn').addEventListener('click', removeDiscount);
        }
    }

    // --- LÓGICA DE DESCUENTO ---
    function applyDiscount() {
        const input = document.getElementById('discountInput');
        const value = input.value.trim();
        const subtotal = parseFloat(currentAccountDetails.subtotal);
        let calculatedDiscount = 0;

        if (value.includes('%')) {
            const percentage = parseFloat(value.replace('%', ''));
            if (isNaN(percentage)) return;
            if (percentage > 100) {
                window.appAlert("El descuento en porcentaje no puede ser mayor al 100%.");
                input.value = "100%";
                calculatedDiscount = subtotal;
            } else {
                calculatedDiscount = (subtotal * percentage) / 100;
            }
        } else {
            const fixedAmount = parseFloat(value);
            if (isNaN(fixedAmount)) return;
            if (fixedAmount > subtotal) {
                window.appAlert("El descuento no puede ser mayor al subtotal de la cuenta.");
                input.value = subtotal.toFixed(2);
                calculatedDiscount = subtotal;
            } else {
                calculatedDiscount = fixedAmount;
            }
        }
        discountAmount = calculatedDiscount;
        renderAccountDetails(currentAccountDetails);
    }

    function removeDiscount() {
        discountAmount = 0;
        renderAccountDetails(currentAccountDetails);
    }

    // --- LÓGICA DEL MODAL DE PAGO ---
    function openPaymentModal() {
        if (!currentAccountDetails) {
            window.appAlert("Por favor, seleccione una cuenta primero.");
            return;
        }
        paymentsRegistered = [];
        [paymentAmountInput, cashReceivedInput].forEach(input => input.value = '');
        modalTableNumber.textContent = currentAccountDetails.table_number;
        modalTotalAmount.textContent = formatCurrency(totalDue);
        updatePaymentStatus();
        paymentModal.style.display = 'flex';
    }

    function closePaymentModal() {
        paymentModal.style.display = 'none';
    }

    function addPayment(method) {
        let amount = parseFloat(paymentAmountInput.value.replace(',', '.'));
        if (isNaN(amount) || amount <= 0) {
            window.appAlert("Por favor, ingrese un monto de pago válido.");
            return;
        }

        const totalPaid = paymentsRegistered.reduce((sum, p) => sum + p.amount, 0);
        const remaining = totalDue - totalPaid;
        let paymentAmount = amount;
        let tipAmount = 0;

        if (method.includes('Tarjeta') && amount > remaining + 0.001) {
            paymentAmount = remaining > 0 ? remaining : 0;
            tipAmount = amount - paymentAmount;
            window.appAlert(`Pago de ${formatCurrency(paymentAmount)} y propina de ${formatCurrency(tipAmount)} registrados.`);
        } else if (amount > remaining + 0.001 && method !== 'Efectivo') {
            window.appAlert(`El monto para ${method} no puede exceder el restante (${formatCurrency(remaining)}).`);
            return;
        }

        paymentsRegistered.push({ method, amount: paymentAmount, tip: tipAmount });
        paymentAmountInput.value = '';
        updatePaymentStatus();
    }

    function updatePaymentStatus() {
        const totalPaid = paymentsRegistered.reduce((sum, p) => sum + p.amount, 0);
        const remaining = totalDue - totalPaid;
        const currentTotalTip = paymentsRegistered.reduce((sum, p) => sum + p.tip, 0);

        modalRemainingAmount.textContent = formatCurrency(Math.max(0, remaining));
        
        paymentsMadeList.innerHTML = paymentsRegistered.map((p, index) => {
            return `<li>
                        <span>${p.method}:</span>
                        <div>
                            <strong>${formatCurrency(p.amount)}</strong>
                            <button class="delete-payment-btn" data-index="${index}" title="Eliminar pago">X</button>
                        </div>
                    </li>`;
        }).join('');

        const hasCashPayment = paymentsRegistered.some(p => p.method === 'Efectivo');
        cashChangeSection.style.display = hasCashPayment ? 'block' : 'none';
        if (!hasCashPayment) cashReceivedInput.value = '';

        if (currentTotalTip > 0) {
            totalTipSection.style.display = 'block';
            modalTotalTipAmount.textContent = formatCurrency(currentTotalTip);
        } else {
            totalTipSection.style.display = 'none';
        }

        btnFinalizePayment.disabled = remaining > 0.001;
        calculateChange();
    }

    function deletePayment(indexToDelete) {
        paymentsRegistered.splice(indexToDelete, 1);
        updatePaymentStatus();
    }

    function calculateChange() {
        const cashReceived = parseFloat(cashReceivedInput.value.replace(',', '.'));
        const totalCashPaid = paymentsRegistered.filter(p => p.method === 'Efectivo').reduce((sum, p) => sum + p.amount, 0);

        if (isNaN(cashReceived) && totalCashPaid <= 0) {
            cashChangeAmount.textContent = formatCurrency(0);
            return;
        }
        
        const paidWithOtherMethods = paymentsRegistered.filter(p => p.method !== 'Efectivo').reduce((sum, p) => sum + p.amount, 0);
        const dueInCash = totalDue - paidWithOtherMethods;
        const effectiveCash = cashReceived > totalCashPaid ? cashReceived : totalCashPaid;
        const change = effectiveCash - dueInCash;
        
        cashChangeAmount.textContent = formatCurrency(Math.max(0, change));
        btnFinalizePayment.disabled = effectiveCash < dueInCash - 0.001;
    }

    // --- LÓGICA DEL MODAL DE PAGO (FUNCIÓN FINALIZE PAYMENT) ---
    async function finalizePayment() {
        if (btnFinalizePayment.disabled) return;
        
        const totalTipAmount = paymentsRegistered.reduce((sum, p) => sum + p.tip, 0);
        const isCourtesy = paymentsRegistered.some(p => p.method === 'Cortesía') && (paymentsRegistered.reduce((sum, p) => sum + p.amount, 0) >= totalDue);

        // Calculamos el cambio y el efectivo recibido para el recibo final
        const cashReceived = parseFloat(cashReceivedInput.value.replace(',', '.')) || 0;
        const totalCashPaid = paymentsRegistered.filter(p => p.method === 'Efectivo').reduce((sum, p) => sum + p.amount, 0);
        const paidWithOtherMethods = paymentsRegistered.filter(p => p.method !== 'Efectivo').reduce((sum, p) => sum + p.amount, 0);
        const dueInCash = totalDue - paidWithOtherMethods;
        const effectiveCash = cashReceived > totalCashPaid ? cashReceived : totalCashPaid;
        const cash_change = Math.max(0, effectiveCash - dueInCash);


        const payload = {
            order_id: selectedOrderId,
            payments: paymentsRegistered.map(({ method, amount }) => ({ method, amount })),
            tip_amount_card: totalTipAmount,
            discount_amount: discountAmount,
            is_courtesy: isCourtesy,
            cash_change: cash_change // 💡 AÑADIMOS EL CAMBIO AL PAYLOAD
        };

        try {
            const response = await fetch('/src/api/cashier/process_payment.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const result = await response.json();
            if (result.success) {
                window.appAlert(`Cuenta cerrada exitosamente. Movimiento #${result.new_sale_id}`);
                
                // 💥 LÓGICA: Abrir el Recibo Final
                const saleId = result.new_sale_id;
                const received = cashReceived; // Efectivo que el cliente dio

                // Abrimos el recibo final con el ID de la venta y los parámetros necesarios
                const receiptUrl = `/src/php/ticket_final_template.php?sale_id=${saleId}&discount=${discountAmount}&cash_received=${received}&change=${cash_change}`;

                // Usamos un tamaño grande para asegurar la visibilidad de los botones de impresión
                const printWindow = window.open(receiptUrl, '_blank', 'width=700,height=800,scrollbars=yes,resizable=yes');
                if (printWindow) {
                    printWindow.focus();
                }

                // Continuar con el cierre del modal y la actualización de la interfaz
                closePaymentModal();
                fetchOpenAccounts();
                accountDetailsContent.innerHTML = '<p class="placeholder-text">Seleccione una cuenta para ver los detalles.</p>';
                btnProcessPayment.disabled = true;
                btnPrintTicket.disabled = true;
            } else {
                window.appAlert('Error al cerrar la cuenta: ' + result.message);
            }
        } catch (error) {
            window.appAlert('Error de conexión al finalizar el pago.');
        }
    }
    
    // 💡 NUEVA FUNCIÓN: Cancelar la solicitud de pre-ticket
    async function cancelPreBillRequest(orderId) {
        const confirmed = await window.appConfirm(`¿Estás seguro de cancelar la solicitud de cobro para la Orden #${orderId}? Esto desbloqueará al mesero para que pueda añadir más ítems.`, 'Confirmar acción');

        if (!confirmed) {
            return;
        }
        try {
            const response = await fetch('/src/api/cashier/cancel_prebill.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ order_id: orderId })
            });
            const result = await response.json();
            
            if (result.success) {
                window.appAlert('Solicitud de cuenta cancelada. La mesa ha sido desbloqueada.');
                fetchOpenAccounts(); // Recarga la lista de mesas para quitar el botón y la alerta
            } else {
                window.appAlert('Error al cancelar: ' + result.message);
            }
        } catch (error) {
            console.error('Error de red al cancelar pre-ticket:', error);
        }
    }


    // --- LÓGICA DE IMPRESIÓN (PRE-TICKET) ---
    async function printTicket() {
        if (!selectedOrderId) {
            window.appAlert("Por favor, seleccione una cuenta para imprimir el ticket.");
            return;
        }

        // 1. 💥 Notificar al servidor que el pre-ticket fue solicitado (Bloqueo de Mesero)
        try {
            const response = await fetch('/src/api/cashier/set_prebill_requested.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ order_id: selectedOrderId })
            });
            const result = await response.json();

            if (!result.success) {
                console.warn('Advertencia: No se pudo marcar la mesa como "Cuenta Solicitada" para bloquear al mesero. Imprimiendo de todos modos. Error: ' + result.message);
            }
        } catch (error) {
            console.error('Fallo de red al solicitar pre-ticket:', error);
        }
        
        // 2. Abrir la ventana de impresión
        const ticketUrl = `/src/php/ticket_template.php?order_id=${selectedOrderId}&discount=${discountAmount}`;

        // Usamos un tamaño grande para asegurar la visibilidad de los botones de impresión
        const printWindow = window.open(ticketUrl, '_blank', 'width=700,height=800,scrollbars=yes,resizable=yes');
        
        if (printWindow) {
            printWindow.focus();
        } else {
            window.appAlert("El navegador bloqueó la ventana emergente de impresión. Por favor, permítala.");
        }
    }

    // --- VALIDACIONES DE ENTRADA ---
    function validateNumericInput(event) {
        const input = event.target;
        let value = input.value.replace(/[^0-9.,]/g, '');
        value = value.replace(',', '.');
        value = value.replace(/(\..*)\./g, '$1');
        if (value.startsWith('0') && value.length > 1 && !value.startsWith('0.')) {
            value = value.substring(1);
        }
        if (value === '0') {
            value = '';
        }
        if (parseFloat(value) > 999999) {
            value = '999999';
        }
        input.value = value;
    }

    function validateDiscountInput(event) {
        const input = event.target;
        let value = input.value.trim().replace(/[^0-9.,%]/g, '');
        value = value.replace(',', '.');
        value = value.replace(/%(?!$)/g, '');
        value = value.replace(/(\..*)\./g, '$1');
        if (value.startsWith('0') && value.length > 1 && !value.startsWith('0.')) {
            value = value.substring(1);
        }
        if (value === '0') {
            value = '';
        }
        if (value.includes('%') && parseFloat(value.replace('%', '')) > 100) {
            value = '100%';
        }
        if (parseFloat(value) > 999999) {
            value = '999999';
        }
        input.value = value;
    }


    updateClock();
    setInterval(updateClock, 1000);
    
    // 💥 Polling para actualización automática de la lista de cuentas
    fetchOpenAccounts(); 
    setInterval(fetchOpenAccounts, POLLING_INTERVAL_MS);

    btnProcessPayment.addEventListener('click', openPaymentModal);
    closeModalButton.addEventListener('click', closePaymentModal);
    window.addEventListener('click', (event) => { if (event.target === paymentModal) closePaymentModal(); });

    paymentMethodButtons.forEach(button => {
        button.addEventListener('click', () => addPayment(button.dataset.method));
    });

    paymentsMadeList.addEventListener('click', (event) => {
        if (event.target.classList.contains('delete-payment-btn')) {
            deletePayment(parseInt(event.target.dataset.index, 10));
        }
    });

    cashReceivedInput.addEventListener('input', calculateChange);
    btnFinalizePayment.addEventListener('click', finalizePayment);

    // Conectar el botón de imprimir
    btnPrintTicket.addEventListener('click', printTicket);

    [paymentAmountInput, cashReceivedInput].forEach(input => {
        input.addEventListener('input', validateNumericInput);
    });
});
