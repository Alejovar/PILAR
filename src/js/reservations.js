/**
 * Este script gestiona la página de Reservaciones de KitchenLink.
 * Se encarga de la creación, visualización y ahora la edición de reservaciones.
 */
document.addEventListener('DOMContentLoaded', () => {

    // --- 1. SELECCIÓN DE ELEMENTOS DEL DOM ---
    const reservaForm = document.getElementById('reservaForm');
    const dateInput = reservaForm.querySelector('input[name="reservation_date"]');
    const timeInput = reservaForm.querySelector('input[name="reservation_time"]');
    const numPersonasInput = reservaForm.querySelector('input[name="number_of_people"]');
    const nombreClienteInput = reservaForm.querySelector('input[name="customer_name"]');
    const telClienteInput = reservaForm.querySelector('input[name="customer_phone"]');
    const tableSelectorContainer = document.getElementById('tableSelectorContainer'); 
    const hiddenTableInputsContainer = document.getElementById('hiddenTableInputs'); 
    const tableGrid = document.getElementById('tableGrid'); 
    const viewDateInput = document.getElementById('viewDate'); 
    const reservationsList = document.getElementById('reservationsList');
    
    // Nueva variable global para manejar el estado de edición
    let currentEditingReservationId = null; 

    // --- 2. FUNCIONES DE LÓGICA Y ASÍNCRONAS ---

    /**
     * @description Función para resetear el formulario al estado de "Nueva Reservación".
     */
    function resetFormToNew() {
        currentEditingReservationId = null;
        reservaForm.reset();
        
        const submitButton = reservaForm.querySelector('button[type="submit"]');
        submitButton.textContent = 'Registrar Reservación';
        submitButton.classList.remove('btn-update'); // Quita la clase de estilo si se usó
        
        // Limpiar inputs ocultos y selecciones visuales
        tableSelectorContainer.innerHTML = '<span style="color: #999; font-size: 14px; align-self: center;">Seleccione fecha y hora...</span>';
        hiddenTableInputsContainer.innerHTML = '';
    }

    /**
     * Valida la lógica de negocio para una nueva reservación.
     */
    function validateReservationLogic() {
        const selectedDate = dateInput.value;
        const selectedTime = timeInput.value;

        const reservationDateTime = new Date(`${selectedDate}T${selectedTime}`);
        const now = new Date();

        if (reservationDateTime < (now - 60000)) {
            window.appAlert('Error: No se puede reservar en una fecha u hora que ya ha pasado.');
            return false;
        }

        const hour = parseInt(selectedTime.split(':')[0]);
        if (hour < 8 || hour > 22) { 
            window.appAlert('Error: Las reservaciones solo están disponibles de 8:00 AM a 10:00 PM.');
            return false;
        }

        return true; 
    }

    /**
     * @description Carga los detalles de una reservación existente en el formulario para su edición.
     * @param {number} reservationId - El ID de la reservación a editar.
     */
    async function editReservation(reservationId) {
        // 1. Obtener los detalles de la reservación por ID (Necesitas la API get_reservation_details.php)
        try {
            const response = await fetch(`/src/api/get_reservation_details.php?id=${reservationId}`);
            if (!response.ok) throw new Error(`Error HTTP: ${response.status}`);
            
            const res = await response.json();
            if (!res.success || !res.reservation) {
                window.appAlert("Error: No se encontraron los detalles de la reservación.");
                return;
            }

            const reservation = res.reservation;

            // 2. Autorellenar Campos del Formulario
            currentEditingReservationId = reservationId;
            
            // Cargar los campos de texto
            nombreClienteInput.value = reservation.customer_name || '';
            telClienteInput.value = reservation.customer_phone || '';
            numPersonasInput.value = reservation.number_of_people || '';
            reservaForm.querySelector('textarea[name="special_requests"]').value = reservation.special_requests || '';
            
            // Cargar la fecha y hora
            dateInput.value = reservation.reservation_date || ''; 
            timeInput.value = reservation.reservation_time ? reservation.reservation_time.substring(0, 5) : '';

            // 3. Modificar el Botón de Envío y Título
            const submitButton = reservaForm.querySelector('button[type="submit"]');
            submitButton.textContent = 'Actualizar Reservación';
            submitButton.classList.add('btn-update'); 

            // 4. Recargar mesas disponibles (la función también preselecciona si la API lo permite)
            await fetchAvailableTablesForForm(); 
            
            reservaForm.scrollIntoView({ behavior: 'smooth' });

        } catch (error) {
            console.error('Error al cargar datos para edición:', error);
            window.appAlert('Error al cargar la reservación. Por favor, revisa tu conexión y API.');
        }
    }


    /**
     * @description Carga el estado actual de TODAS las mesas.
     */
    async function loadTableStatuses() {
        try {
            const response = await fetch('/src/api/get_table_status.php');
            const tables = await response.json();

            tableGrid.innerHTML = ''; 

            tables.forEach(table => {
                const tableBox = document.createElement('div');
                tableBox.className = `table-box ${table.status}`;
                tableBox.dataset.tableId = table.id; 
                tableBox.innerHTML = `
                    <div class="table-name">${table.table_name}</div>
                    <span class="table-status-text">${table.status}</span>
                `;
                tableGrid.appendChild(tableBox);
            });
        } catch (error) {
            console.error('Error al cargar estados de mesas:', error);
        }
    }

    /**
     * @description Busca y muestra únicamente las mesas DISPONIBLES para el formulario de nueva reservación.
     */
    async function fetchAvailableTablesForForm() {
        const date = dateInput.value;
        const time = timeInput.value;

        // Limpiamos selecciones previas.
        tableSelectorContainer.innerHTML = '<span style="color: #999; font-size: 14px; align-self: center;">Seleccione fecha y hora...</span>';
        hiddenTableInputsContainer.innerHTML = '';

        if (!date || !time) return;

        try {
            const response = await fetch(`/src/api/get_available_tables.php?date=${date}&time=${time}`);
            const tables = await response.json();
            tableSelectorContainer.innerHTML = '';
            
            if (tables.length > 0) {
                tables.forEach(table => {
                    const optionDiv = document.createElement('div');
                    optionDiv.className = 'table-option';
                    optionDiv.dataset.tableId = table.id;
                    optionDiv.innerHTML = `Mesa ${table.table_name}`;
                    
                    // Lógica para preseleccionar la mesa si estamos editando (requeriría más datos de la API)
                    
                    tableSelectorContainer.appendChild(optionDiv);
                });
            } else {
                tableSelectorContainer.innerHTML = '<span style="color: var(--color-alert); font-size: 14px; align-self: center;">No hay mesas disponibles para esta hora.</span>';
            }
        } catch (error) {
            console.error('Error al cargar mesas disponibles:', error);
            tableSelectorContainer.innerHTML = '<span style="color: var(--color-alert); font-size: 14px; align-self: center;">Error al cargar datos.</span>';
        }
    }

    /**
     * @description Carga la lista de reservaciones para una fecha específica.
     */
    async function loadReservations(date) {
        if (!date) return;
        try {
            const response = await fetch(`/src/api/get_reservations.php?date=${date}`);
            
            if (!response.ok) {
                throw new Error(`Error HTTP: ${response.status} al cargar reservaciones.`);
            }
            
            const reservations = await response.json();
            reservationsList.innerHTML = '';
            
            if (reservations.length > 0) {
                reservations.forEach(res => {
                    const card = document.createElement('div');
                    card.className = 'reservation-card';
                    card.dataset.reservationId = res.id;
                    
                    const displayTime = res.reservation_time?.substring(0, 5) ?? 'Hora no definida';

                    card.innerHTML = `
                        <div class="card-header">
                            <span class="customer-name">${res.customer_name}</span>
                            <span class="reservation-time">${displayTime}</span>
                            <button class="details-toggle"><i class="fas fa-chevron-down"></i></button>
                        </div>
                        <div class="card-status status-${res.status}">${res.status.toUpperCase()}</div>
                        <div class="reservation-details" style="display: none;">
                            <p><strong>Teléfono:</strong> ${res.customer_phone}</p>
                            <p><strong>Personas:</strong> ${res.number_of_people}</p>
                            <p><strong>Mesas:</strong> ${res.table_names}</p>
                            ${res.special_requests ? `<p><strong>Solicitudes:</strong> ${res.special_requests}</p>` : ''}
                        </div>
                        
                        ${res.status === 'reservada' ? `
                           <div class="card-actions-edit">
                                <button class="btn btn-icon btn-primary btn-edit-reservation" title="Editar reservación" data-reservation-id="${res.id}">
                                    <i class="fas fa-pencil-alt"></i>
                                </button>
                            </div>
                            
                            <div class="card-actions">
                                <button class="btn btn-icon btn-success btn-confirm" title="Confirmar llegada" data-reservation-id="${res.id}">
                                    <i class="fas fa-check"></i>
                                </button>
                                <button class="btn btn-icon btn-danger btn-cancel" title="Cancelar reservación" data-reservation-id="${res.id}">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        ` : ''}
                        `;
                    reservationsList.appendChild(card);
                });
            } else {
                reservationsList.innerHTML = '<p class="text-center">No hay reservaciones para esta fecha.</p>';
            }
        } catch (error) {
            console.error('Error al cargar reservaciones:', error);
            reservationsList.innerHTML = `<p class="text-center text-danger">Error al cargar datos. Mensaje: ${error.message}</p>`;
        }
    }

    /**
     * @description Función reutilizable para enviar una petición a la API para archivar una reservación.
     */
    async function archiveReservationAPI(reservationId, status) {
        try {
            const response = await fetch('/src/api/archive_reservation.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ reservation_id: reservationId, status: status })
            });
            
            if (!response.ok) {
                 throw new Error(`Error HTTP: ${response.status}`);
            }

            const result = await response.json();
            if (result.success) {
                window.appAlert(`Reservación ${status} con éxito.`);
                return true;
            } else {
                window.appAlert(`Error al ${status === 'completada' ? 'confirmar' : 'cancelar'}: ` + result.message);
                return false;
            }
        } catch (error) {
            console.error('Error en la API de archivo:', error);
            window.appAlert(`Error de conexión al procesar la reservación. Error: ${error.message}`);
            return false;
        }
    }

    // --- 3. EVENT LISTENERS ---

    // [VALIDACIONES EN TIEMPO REAL: Se mantienen sin cambios]
    const allowOnlyNumbers = (e) => { e.target.value = e.target.value.replace(/[^0-9]/g, ''); };
    numPersonasInput.addEventListener('input', allowOnlyNumbers);
    telClienteInput.addEventListener('input', allowOnlyNumbers);
    nombreClienteInput.addEventListener('input', (e) => { e.target.value = e.target.value.replace(/[^a-zA-Z\s]/g, ''); });

    telClienteInput.addEventListener('input', (e) => { if (e.target.value.length > 10) e.target.value = e.target.value.slice(0, 10); });
    numPersonasInput.addEventListener('input', (e) => { if (e.target.value.length > 2) e.target.value = e.target.value.slice(0, 2); });

    // Cuando el usuario cambia la fecha o la hora, se vuelve a buscar qué mesas están disponibles.
    dateInput.addEventListener('change', fetchAvailableTablesForForm);
    timeInput.addEventListener('change', fetchAvailableTablesForForm);

    // Maneja la selección de mesas en el formulario.
    tableSelectorContainer.addEventListener('click', (e) => {
        if (e.target.classList.contains('table-option')) {
            const tableButton = e.target;
            const tableId = tableButton.dataset.tableId;

            tableButton.classList.toggle('selected');
            
            if (tableButton.classList.contains('selected')) {
                const hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = 'table_ids[]';
                hiddenInput.value = tableId;
                hiddenInput.id = `table-input-${tableId}`;
                hiddenTableInputsContainer.appendChild(hiddenInput);
            } else { 
                const inputToRemove = document.getElementById(`table-input-${tableId}`);
                if (inputToRemove) inputToRemove.remove();
            }
        }
    });

    // Maneja el envío del formulario de nueva reservación / actualización.
    reservaForm.addEventListener('submit', async (e) => {
        e.preventDefault(); 

        if (!validateReservationLogic()) {
            return; 
        }

        const formData = new FormData(reservaForm);
        
        // --- Lógica de Edición vs. Registro ---
        const isUpdating = currentEditingReservationId !== null;
        const apiUrl = isUpdating ? '/src/api/update_reservation.php' : '/src/api/add_reservation.php';
        
        // Si estamos actualizando, añadimos el ID de la reservación al FormData
        if (isUpdating) {
            formData.append('reservation_id', currentEditingReservationId);
        } else if (!formData.has('table_ids[]')) {
            // Si es nueva reservación, verifica que se haya seleccionado al menos una mesa.
            window.appAlert('Por favor, seleccione al menos una mesa.');
            return;
        }

        try {
            const response = await fetch(apiUrl, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            });
            
            if (!response.ok) {
                 throw new Error(`Error HTTP: ${response.status}`);
            }

            const rawText = await response.text();
            let result;
            try {
                result = JSON.parse(rawText);
            } catch (parseError) {
                console.error('Respuesta no JSON:', rawText);
                throw new Error('Respuesta invalida del servidor.');
            }
            if (result.success) {
                window.appAlert(`¡Reservación ${isUpdating ? 'actualizada' : 'registrada'} con éxito!`);
                resetFormToNew(); // Vuelve al estado de registro
                
                // Recarga las vistas 
                fetchAvailableTablesForForm();
                loadReservations(viewDateInput.value);
                loadTableStatuses();
            } else {
                window.appAlert(`Error al ${isUpdating ? 'actualizar' : 'registrar'} reservación: ` + result.message);
            }
        } catch (error) {
            console.error('Error en el envío del formulario:', error);
            window.appAlert(`Error de conexión al ${isUpdating ? 'actualizar' : 'registrar'} la reservación.`);
        }
    });

    // Cuando el usuario cambia la fecha de visualización, se recarga la lista de reservaciones.
    viewDateInput.addEventListener('change', () => loadReservations(viewDateInput.value));

    // Usamos el modo de captura para mayor robustez del clic en las mesas.
    tableGrid.addEventListener('click', async (e) => {
        // ... (Lógica para cambiar manualmente el estado de una mesa se mantiene) ...
        const tableBox = e.target.closest('.table-box');

        if (tableBox) {
            const tableId = tableBox.dataset.tableId;
            const tableNameElement = tableBox.querySelector('.table-name');

            if (!tableNameElement) {
                console.error("ERROR CRÍTICO: Elemento de nombre de mesa (.table-name) no encontrado.");
                return;
            }

            const tableName = tableNameElement.textContent;

            const tableStatusConfirmed = await window.appConfirm(`¿Desea cambiar el estado de la ${tableName}?`, 'Confirmar cambio');
            if (!tableStatusConfirmed) return;

            try {
                const response = await fetch('/src/api/update_table_status.php', { 
                    method: 'POST', 
                    headers: {'Content-Type': 'application/json'}, 
                    body: JSON.stringify({ table_id: tableId }) 
                });
                
                if (!response.ok) {
                     throw new Error(`Error HTTP: ${response.status}`);
                }
                
                const result = await response.json(); 
                
                if (result.success) {
                    loadTableStatuses();
                    fetchAvailableTablesForForm();
                } else {
                    window.appAlert("Error: " + result.message);
                }
            } catch (error) { 
                console.error('Error al actualizar estado:', error);
                window.appAlert("Error de conexión al actualizar el estado de la mesa. Revisa la Consola (F12) para detalles.");
            }
        }
    }, true);


    /**
     * Listener global que maneja clics en elementos dinámicos (delegación de eventos).
     */
    document.addEventListener('click', async (e) => {
        
        // --- 1. Clic en el botón para expandir/colapsar detalles de una reservación ---
        if (e.target.closest('.details-toggle')) {
            const card = e.target.closest('.reservation-card');
            const details = card.querySelector('.reservation-details');
            const icon = card.querySelector('.details-toggle i');
            const isVisible = details.style.display === 'block';

            details.style.display = isVisible ? 'none' : 'block';
            icon.className = isVisible ? 'fas fa-chevron-down' : 'fas fa-chevron-up';
        }

        // --- 2. Clic en el botón de EDICIÓN (Lápiz) ---
        const editButton = e.target.closest('.btn-edit-reservation');

        if (editButton) {
            const reservationId = editButton.dataset.reservationId;
            // Llama a la función que rellena el formulario
            await editReservation(reservationId); 
        }

        // --- 3. Clic en los botones de 'Confirmar' o 'Cancelar' de una reservación ---
        const confirmButton = e.target.closest('.btn-confirm');
        const cancelButton = e.target.closest('.btn-cancel');

        if (confirmButton || cancelButton) {
            const card = e.target.closest('.reservation-card');
            const reservationId = card.dataset.reservationId;
            const [btnConfirm, btnCancel] = [card.querySelector('.btn-confirm'), card.querySelector('.btn-cancel')];

            let action = confirmButton ? 'confirmar la llegada del cliente' : 'CANCELAR esta reservación';
            let status = confirmButton ? 'completada' : 'cancelada';

            const reservationActionConfirmed = await window.appConfirm(`¿Está seguro de que desea ${action}?`, 'Confirmar acción');

            if (reservationActionConfirmed) {
                btnConfirm.classList.add('processing');
                btnCancel.classList.add('processing');
                btnConfirm.disabled = true;
                btnCancel.disabled = true;

                const success = await archiveReservationAPI(reservationId, status);

                if (success) {
                    loadReservations(viewDateInput.value);
                    loadTableStatuses();
                    fetchAvailableTablesForForm();
                } else {
                    btnConfirm.classList.remove('processing');
                    btnCancel.classList.remove('processing');
                    btnConfirm.disabled = false;
                    btnCancel.disabled = false;
                }
            }
        }
    });

    // --- 4. INICIALIZACIÓN ---
    const todayDate = new Date();
    const year = todayDate.getFullYear();
    const month = String(todayDate.getMonth() + 1).padStart(2, '0');
    const day = String(todayDate.getDate()).padStart(2, '0');
    const today = `${year}-${month}-${day}`;

    dateInput.value = today;
    viewDateInput.value = today;
    dateInput.min = today;

    loadTableStatuses();
    loadReservations(today);
    fetchAvailableTablesForForm();

    // --- 5. TEMPORIZADOR AUTOMÁTICO DE LIMPIEZA ---
    const cleanupInterval = 5 * 60 * 1000;
    setInterval(async () => {
        try {
            console.log("Ejecutando limpieza automática de mesas... " + new Date().toLocaleTimeString());
            await fetch('/src/api/cleanup_tables.php');
            await loadTableStatuses();
        } catch (error) {
            console.error("Error durante la limpieza automática:", error);
        }
    }, cleanupInterval);
});
