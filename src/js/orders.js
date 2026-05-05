// /js/orders.js - VERSIÓN MAESTRA FINAL

import { ModalAdvancedOptions } from './ModalAdvancedOptions.js';
import { ServerProfileModal }   from './ServerProfileModal.js';

document.addEventListener('DOMContentLoaded', async () => {

    // 1. VERIFICACIÓN DE TURNO (SOLO PARA MESEROS)
    if (typeof window.isManagerMode === 'undefined' || !window.isManagerMode) {
        try {
            const response = await fetch('/src/api/cashier/history_reports/get_shift_status.php');
            const data = await response.json();

            if (!data.success || data.status === 'CLOSED') {
                window.appAlert("El turno de caja ha sido cerrado. La sesión se cerrará.");
                window.location.href = '/src/php/logout.php';
                return; 
            }
        } catch (error) {
            document.body.innerHTML = "<h1>Error fatal al verificar el estado del turno.</h1>";
            return; 
        }
    }
    
    // --- REFERENCIAS DEL DOM ---
    const tableGridContainer = document.getElementById('tableGridContainer');
    const clockContainer = document.getElementById('liveClockContainer');
    const fab = document.getElementById('fab');
    const modal = document.getElementById('newTableModal');
    const newTableForm = document.getElementById('newTableForm');
    const controlButtons = document.querySelectorAll('.action-btn');
    
    const inputTableNumber = document.getElementById('mesaNumber');
    const inputClientCount = document.getElementById('clientCount');
    const tableNumberError = document.getElementById('mesaNumberError');
    const clientCountError = document.getElementById('clientCountError');
    
    // Elementos del modo gerente
    const serverSelectContainer = document.getElementById('serverSelectContainer');
    const assignedServerSelect = document.getElementById('assignedServerSelect');
    
    let selectedTable = null;

    const API_ROUTES = {
        GET_TABLES: '/src/api/orders/get_tables.php',
        CREATE_TABLE: '/src/api/orders/create_table.php',
        GET_SERVERS: '/src/api/manager/get_active_servers.php',
        LOCK_TABLE: '/src/api/orders/lock_table.php' // 🔒 Ruta del semáforo
    };

    // --- FUNCIONES ---

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

    function handleTableClick(clickedTable) {
        if (selectedTable && selectedTable !== clickedTable) {
            selectedTable.classList.remove('selected');
        }
        clickedTable.classList.toggle('selected');
        selectedTable = clickedTable.classList.contains('selected') ? clickedTable : null;
        updateControlButtons();
    }

    function updateControlButtons() {
        const shouldEnable = selectedTable !== null;
        controlButtons.forEach(button => {
            button.disabled = !shouldEnable;
        });
    }

    // Cargar lista de meseros (Solo para Gerente)
    async function loadServerOptions() {
        if (!assignedServerSelect) return;
        if (assignedServerSelect.options.length > 1) return; 

        try {
            const response = await fetch(API_ROUTES.GET_SERVERS);
            const result = await response.json();
            
            if (result.success) {
                assignedServerSelect.innerHTML = '<option value="">-- Asignar a mí (Default) --</option>';
                result.data.forEach(server => {
                    const option = document.createElement('option');
                    option.value = server.id;
                    option.textContent = server.name;
                    assignedServerSelect.appendChild(option);
                });
            }
        } catch (error) {
            console.error("Error cargando meseros", error);
        }
    }

    async function fetchAndRenderTables() {
        const currentSelectionNumber = selectedTable ? selectedTable.dataset.tableNumber : null; 
        selectedTable = null; 

        try {
            const response = await fetch(API_ROUTES.GET_TABLES);
            if (!response.ok) throw new Error('Error de red al cargar mesas.');
            const data = await response.json();
            
            tableGridContainer.innerHTML = '';
            
            if (data.success && data.tables.length > 0) {
                data.tables.forEach(table => {
                    const tableButton = document.createElement('button');
                    tableButton.className = 'table-btn';
                    tableButton.dataset.tableNumber = table.table_number;
                    
                    // Guardamos nombre para el modal avanzado
                    tableButton.dataset.serverName = table.mesero_nombre || 'Sin Asignar'; 
                    
                    if (table.pre_bill_status === 'REQUESTED') {
                        tableButton.classList.add('prebill-requested'); 
                    }
                    
                    // ETIQUETA DE NOMBRE (Diseño Limpio)
                    let serverLabel = '';
                    if (window.isManagerMode && table.mesero_nombre) {
                        let nombreMostrar = table.mesero_nombre;
                        if (nombreMostrar.length > 15) {
                            nombreMostrar = nombreMostrar.substring(0, 15) + '...';
                        }
                        serverLabel = `<div class="server-tag">${nombreMostrar}</div>`;
                    }

                    // HTML DE LA TARJETA
                    tableButton.innerHTML = `
                        <span class="table-number">${table.table_number}</span>
                        ${serverLabel}
                        <div class="table-info">
                            <div class="timer"><i class="fas fa-clock"></i><span>${table.minutes_occupied} min</span></div>
                            <div class="client-count"><i class="fas fa-users"></i><span>${table.client_count}</span></div>
                        </div>`;
                    
                    tableButton.addEventListener('click', () => handleTableClick(tableButton));
                    tableGridContainer.appendChild(tableButton);

                    if (currentSelectionNumber && table.table_number == currentSelectionNumber) {
                        handleTableClick(tableButton); 
                    }
                });
            } else {
                tableGridContainer.innerHTML = '<p class="no-tables-msg">No hay mesas activas.</p>';
            }
            updateControlButtons();
        } catch (error) {
            console.error('Error al cargar mesas:', error);
            tableGridContainer.innerHTML = `<p class="error-msg">Error de conexión.</p>`;
        }
    }
    
    function closeModal() {
        modal.classList.remove('visible');
        const main = document.querySelector('main');
        if(main) main.classList.remove('blurred');
        
        newTableForm.reset();
        if (tableNumberError) tableNumberError.textContent = '';
        if (clientCountError) clientCountError.textContent = '';
    }

    // --- VALIDACIONES ---
    function formatNumericInput(input, maxLength) {
        if (!input) return;
        let value = input.value.replace(/[^0-9]/g, '');
        if (value === '0') value = '';
        if (value.length > maxLength) value = value.slice(0, maxLength);
        input.value = value;
    }
    
    if (inputTableNumber) inputTableNumber.addEventListener('input', () => formatNumericInput(inputTableNumber, 4));
    if (inputClientCount) inputClientCount.addEventListener('input', () => formatNumericInput(inputClientCount, 2));

    // --- EVENTOS ---

    fab.addEventListener('click', () => {
        modal.classList.add('visible');
        const main = document.querySelector('main');
        if(main) main.classList.add('blurred');

        // MODO GERENTE: Mostrar selector
        if (window.isManagerMode && serverSelectContainer) {
            serverSelectContainer.style.display = 'block';
            loadServerOptions();
        } else if (serverSelectContainer) {
            serverSelectContainer.style.display = 'none';
        }
    });
    
    document.getElementById('cancelCreate').addEventListener('click', closeModal);

    newTableForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const tableNumber = document.getElementById('mesaNumber').value;
        const clientCount = document.getElementById('clientCount').value;
        
        let assignedServerId = null;
        if (assignedServerSelect && assignedServerSelect.value) {
            assignedServerId = assignedServerSelect.value;
        }

        try {
            const response = await fetch(API_ROUTES.CREATE_TABLE, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    table_number: tableNumber, 
                    client_count: clientCount,
                    assigned_server_id: assignedServerId 
                })
            });
            const data = await response.json();
            if (data.success) {
                fetchAndRenderTables();
                closeModal();
            } else {
                if (tableNumberError) tableNumberError.textContent = data.message;
            }
        } catch (error) {
            if (tableNumberError) tableNumberError.textContent = 'Error de conexión.';
        }
    });

    // -----------------------------------------------------------
    // 🔒 LOGICA DE ENTRADA A LA MESA (CON BLOQUEO AJAX)
    // -----------------------------------------------------------
    document.getElementById('btn-edit-order').addEventListener('click', async () => {
        if (!selectedTable) {
            window.appAlert('Por favor, selecciona una mesa primero.');
            return;
        }
        const tableNumber = selectedTable.dataset.tableNumber;
        const btn = document.getElementById('btn-edit-order');
        
        const originalText = btn.innerHTML; 
        btn.innerText = "Verificando...";
        btn.disabled = true;

        try {
            // 1. Intentamos bloquear la mesa
            const response = await fetch(API_ROUTES.LOCK_TABLE, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ table_number: tableNumber })
            });
            const result = await response.json();

            if (result.success) {
                // 2. ÉXITO: Entramos
                window.location.href = `order_interface.php?table=${tableNumber}`;
            } else {
                // 3. ERROR: Mesa ocupada
                window.appAlert("⚠️ ACCESO DENEGADO\n" + result.message);
                
                btn.innerHTML = originalText;
                btn.disabled = false;
                fetchAndRenderTables();
            }

        } catch (error) {
            console.error("Error de bloqueo:", error);
            window.appAlert("Error de conexión al intentar acceder a la mesa.");
            btn.innerHTML = originalText;
            btn.disabled = false;
        }
    });

    // --- INICIALIZACIÓN ---
    updateClock();
    setInterval(updateClock, 1000);
    updateControlButtons();
    
    fetchAndRenderTables(); 
    setInterval(fetchAndRenderTables, 5000);

    window.addEventListener('table-list-update', fetchAndRenderTables);

    const optionsManager = new ModalAdvancedOptions('#btn-advanced-options');
    optionsManager.initialize();

    const profileModal = new ServerProfileModal();
    profileModal.init();
    
});