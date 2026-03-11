/**
 * Este script gestiona la lógica de la página de "Lista de Espera" para la aplicación KitchenLink.
 * Se encarga de cargar clientes, agregarlos, sentarlos en mesas disponibles y cancelarlos.
 * Utiliza funciones asíncronas (async/await) para comunicarse con una API en el backend.
 */
document.addEventListener('DOMContentLoaded', () => {

    // --- SELECCIÓN DE ELEMENTOS DEL DOM ---
    // Guardamos en constantes las referencias a los elementos HTML con los que vamos a interactuar.
    // Esto es más eficiente que buscarlos en el DOM cada vez que los necesitamos.

    const waitlistForm = document.getElementById('waitlistForm'); // El formulario para agregar nuevos clientes a la lista.
    const waitingListContainer = document.getElementById('waitingList'); // El contenedor <div> donde se mostrarán las tarjetas de los clientes en espera.
    const estimatedTimeEl = document.getElementById('estimatedTime'); // El elemento <span> o <div> para mostrar el tiempo de espera estimado.
    
    // Elementos de la ventana modal que se usa para sentar a un cliente.
    const modal = document.getElementById('seatClientModal'); // El contenedor principal de la ventana modal.
    const modalClientName = document.getElementById('modalClientName'); // Elemento para mostrar el nombre del cliente que se va a sentar.
    const modalTableGrid = document.getElementById('modalTableGrid'); // El contenedor donde se cargarán las mesas disponibles.
    const closeModalBtn = modal.querySelector('.modal-close'); // El botón (generalmente una 'X') para cerrar la modal.
    const cancelSeatBtn = document.getElementById('cancelSeatBtn'); // Botón para cancelar la acción de sentar y cerrar la modal.
    const confirmSeatBtn = document.getElementById('confirmSeatBtn'); // Botón para confirmar la selección de mesa y sentar al cliente.

    // --- VARIABLES DE ESTADO ---
    
    // Variable para almacenar temporalmente el ID del cliente que se está procesando en la modal.
    // Es 'null' cuando la modal está cerrada.
    let currentClientId = null; 

    // --- FUNCIONES ASÍNCRONAS (Comunicación con el Backend) ---

    /**
     * @description Carga la lista de espera desde el servidor y la muestra en la página.
     * Se comunica con la API para obtener los clientes actuales en estado 'waiting'.
     */
    async function loadWaitingList() {
        try {
            // Realiza una petición GET a la API para obtener la lista de clientes.
            const response = await fetch('/src/api/get_waiting_list.php');
            // Si la respuesta del servidor no es exitosa (ej: error 500), lanza un error.
            if (!response.ok) throw new Error('Error en la respuesta del servidor');
            // Convierte la respuesta JSON en un array de objetos JavaScript.
            const clients = await response.json();
            
            // Limpia el contenido actual del contenedor antes de agregar los nuevos datos.
            waitingListContainer.innerHTML = ''; 

            if (clients.length > 0) {
                // Si hay clientes, itera sobre el array y crea una "tarjeta" HTML para cada uno.
                clients.forEach(client => waitingListContainer.appendChild(createClientCard(client)));
            } else {
                // Si no hay clientes, muestra un mensaje informativo.
                waitingListContainer.innerHTML = '<p style="text-align: center; color: #888;">No hay nadie en la lista de espera.</p>';
            }
            // Llama a la función para actualizar el tiempo de espera estimado.
            updateEstimatedTime(clients.length);
        } catch (error) {
            // Si ocurre un error en el `try` (ej: fallo de red), lo muestra en la consola.
            console.error("Error al cargar la lista de espera:", error);
            // Muestra un mensaje de error al usuario en la interfaz.
            waitingListContainer.innerHTML = '<p style="color: red; text-align: center;">No se pudo cargar la lista.</p>';
        }
    }

    /**
     * @description Envía los datos del nuevo cliente al servidor para agregarlo a la lista de espera.
     * @param {Event} event - El evento 'submit' del formulario.
     */
    async function addClientToList(event) {
        // Previene el comportamiento por defecto del formulario (que es recargar la página).
        event.preventDefault(); 
        // Crea un objeto FormData a partir de los datos del formulario.
        const formData = new FormData(waitlistForm);
        try {
            // Realiza una petición POST a la API, enviando los datos del formulario.
            const response = await fetch('/src/api/add_to_waitlist.php', { method: 'POST', body: formData });
            // Interpreta la respuesta JSON del servidor.
            const result = await response.json();

            if (result.success) {
                // Si la operación fue exitosa...
                waitlistForm.reset(); // Limpia los campos del formulario.
                loadWaitingList();    // Vuelve a cargar la lista para mostrar al nuevo cliente.
            } else {
                // Si el servidor indica un error, muestra un mensaje al usuario.
                alert('Error: ' + (result.message || 'No se pudo agregar al cliente.'));
            }
        } catch (error) {
            console.error('Error al agregar cliente:', error);
            alert('Error de conexión. Inténtelo de nuevo.');
        }
    }
    
    /**
     * @description Cambia el estado de un cliente a 'cancelled' y lo archiva.
     * @param {number} clientId - El ID del cliente a cancelar.
     */
    async function archiveClientAsCancelled(clientId) {
        // Pide confirmación al usuario antes de realizar la acción. Si cancela, la función termina.
        if (!confirm('¿Marcar este cliente como cancelado y moverlo al historial?')) return;
        try {
            // Realiza una petición POST a la API.
            const response = await fetch('/src/api/archive_from_waitlist.php', {
                method: 'POST',
                // Define las cabeceras para indicar que se enviará contenido en formato JSON.
                headers: { 'Content-Type': 'application/json' },
                // Convierte el objeto JavaScript a una cadena JSON para enviarlo en el cuerpo de la petición.
                body: JSON.stringify({ id: clientId, status: 'cancelled' })
            });
            const result = await response.json();
            if (result.success) {
                // Si fue exitoso, recarga la lista para que el cliente desaparezca.
                loadWaitingList(); 
            } else {
                alert('Error: ' + (result.message || 'No se pudo archivar al cliente.'));
            }
        } catch (error) {
            console.error('Error al archivar cliente:', error);
            alert('Error de conexión al intentar archivar.');
        }
    }

    /**
     * @description Abre la ventana modal para sentar a un cliente y carga las mesas disponibles.
     * @param {number} clientId - El ID del cliente a sentar.
     * @param {string} clientName - El nombre del cliente.
     */
    async function openSeatClientModal(clientId, clientName) {
        // Guarda el ID y nombre del cliente en las variables correspondientes.
        currentClientId = clientId;
        modalClientName.textContent = clientName; 
        // Muestra un mensaje de carga mientras se obtienen las mesas.
        modalTableGrid.innerHTML = '<p>Cargando mesas...</p>';
        // Hace visible la ventana modal.
        modal.style.display = 'flex'; 

        try {
            // Pide al servidor la lista de mesas con estado 'available'.
            const response = await fetch('/src/api/get_current_available_tables.php');
            const data = await response.json();
            // Limpia el mensaje de "Cargando...".
            modalTableGrid.innerHTML = '';
            if (data.success && data.tables.length > 0) {
                // Si hay mesas, crea un botón para cada una.
                data.tables.forEach(table => {
                    const tableBox = document.createElement('div');
                    tableBox.className = 'modal-table-box';
                    tableBox.textContent = table.table_name;
                    // Almacena el ID de la mesa en un atributo 'data-' para usarlo después.
                    tableBox.dataset.tableId = table.id;
                    modalTableGrid.appendChild(tableBox);
                });
            } else {
                // Si no hay mesas disponibles, muestra un mensaje.
                modalTableGrid.innerHTML = '<p style="color: #888;">No hay mesas disponibles en este momento.</p>';
            }
        } catch (error) {
            console.error("Error al cargar mesas:", error);
            modalTableGrid.innerHTML = '<p style="color: red;">Error al cargar las mesas.</p>';
        }
    }

    /**
     * @description Oculta la ventana modal y resetea la variable de estado.
     */
    function closeSeatClientModal() {
        modal.style.display = 'none';
        // Limpia el ID del cliente actual para evitar errores en futuras aperturas.
        currentClientId = null;
    }

    /**
     * @description Confirma la acción de sentar, enviando al servidor el ID del cliente y los IDs de las mesas seleccionadas.
     */
    async function confirmAndSeatClient() {
        // Busca todos los elementos de mesa que tengan la clase 'selected'.
        const selectedTables = modalTableGrid.querySelectorAll('.modal-table-box.selected');
        // Si no se seleccionó ninguna mesa, muestra una alerta y detiene la función.
        if (selectedTables.length === 0) {
            alert('Por favor, seleccione al menos una mesa.');
            return;
        }

        // Crea un array con los IDs de las mesas seleccionadas, extrayéndolos de los atributos 'data-table-id'.
        const tableIds = Array.from(selectedTables).map(el => el.dataset.tableId);

        try {
            // Envía los datos al servidor para actualizar la base de datos.
            const response = await fetch('/src/api/seat_client.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ client_id: currentClientId, table_ids: tableIds })
            });
            const result = await response.json();
            if (result.success) {
                alert('¡Cliente sentado con éxito!');
                // Cierra la modal y recarga la lista de espera (el cliente ya no aparecerá).
                closeSeatClientModal();
                loadWaitingList();
            } else {
                alert('Error: ' + result.message);
            }
        } catch (error) {
            console.error("Error al sentar al cliente:", error);
            alert('Hubo un error de conexión al intentar sentar al cliente.');
        }
    }

    // --- FUNCIONES AUXILIARES (Lógica de la Interfaz) ---
    
    /**
     * @description Crea y devuelve el elemento HTML para la tarjeta de un cliente.
     * @param {object} client - El objeto cliente con datos como id, nombre, etc.
     * @returns {HTMLElement} - El elemento <div> de la tarjeta del cliente.
     */
    function createClientCard(client) {
        // Crea el elemento <div> principal de la tarjeta.
        const card = document.createElement('div');
        card.className = 'client-card';
        // Guarda el ID y nombre en atributos 'data-' para un fácil acceso desde JavaScript.
        card.dataset.clientId = client.id;
        card.dataset.clientName = client.customer_name;

        // Obtiene la primera letra del nombre para usarla como avatar.
        const avatarLetter = client.customer_name.charAt(0).toUpperCase(); 
        // Crea el HTML para el teléfono solo si el cliente proporcionó uno.
        const phoneHtml = client.customer_phone ? `<div class="details phone"><i class="fas fa-phone-alt"></i> ${client.customer_phone}</div>` : '';
        
        // Define la estructura interna de la tarjeta usando un template string.
        card.innerHTML = `
            <div class="client-info">
                <div class="client-avatar">${avatarLetter}</div>
                <div class="client-details">
                    <div class="name">${client.customer_name}</div>
                    <div class="details"><i class="fas fa-users"></i> Mesa para ${client.number_of_people}</div>
                    ${phoneHtml}
                </div>
            </div>
            <div class="client-actions">
                <button class="btn-seat" title="Sentar cliente"><i class="fas fa-check"></i></button>
                <button class="btn-cancel" title="Cancelar registro"><i class="fas fa-times"></i></button>
            </div>
        `;
        return card;
    }
    
    /**
     * @description Calcula y muestra el tiempo de espera estimado.
     * @param {number} clientCount - El número de clientes actualmente en la lista.
     */
    function updateEstimatedTime(clientCount) {
        // Define un tiempo promedio de espera por grupo (en minutos).
        const averageTimePerGroup = 15; 
        // Calcula el total de minutos.
        const estimatedMinutes = clientCount * averageTimePerGroup;
        // Actualiza el texto en la página.
        estimatedTimeEl.textContent = `${estimatedMinutes} min`;
    }

    // --- EVENT LISTENERS (Manejadores de Eventos) ---
    
    // Referencias a los campos de entrada del formulario.
    const nameInput = waitlistForm.querySelector('input[name="customer_name"]');
    const peopleInput = waitlistForm.querySelector('input[name="number_of_people"]');
    const phoneInput = waitlistForm.querySelector('input[name="customer_phone"]');

    // Validación en tiempo real para el campo de nombre (solo permite letras y espacios).
    nameInput.addEventListener('input', (e) => {
        e.target.value = e.target.value.replace(/[^a-zA-Z\s]/g, '');
    });

    // Función reutilizable para validar que un campo solo contenga números.
    const allowOnlyNumbers = (e) => {
        e.target.value = e.target.value.replace(/[^0-9]/g, '');
    };
    // Asigna la validación a los campos de "personas" y "teléfono".
    peopleInput.addEventListener('input', allowOnlyNumbers);
    phoneInput.addEventListener('input', allowOnlyNumbers);

    // Validación para limitar la longitud del teléfono a 10 dígitos.
    phoneInput.addEventListener('input', (e) => {
        if (e.target.value.length > 10) {
            e.target.value = e.target.value.slice(0, 10);
        }
    });
    
    // Asigna la función 'addClientToList' al evento 'submit' del formulario.
    waitlistForm.addEventListener('submit', addClientToList);

    /**
     * Escucha clics en el contenedor de la lista de espera.
     * Esto se llama "delegación de eventos". En lugar de poner un listener en cada botón,
     * ponemos uno en el contenedor padre y determinamos qué botón se presionó.
     * Es más eficiente y funciona para elementos creados dinámicamente.
     */
    waitingListContainer.addEventListener('click', (e) => {
        // Busca si el clic fue en un botón 'sentar' o en un ícono dentro de él.
        const seatButton = e.target.closest('.btn-seat');
        // Busca si el clic fue en un botón 'cancelar'.
        const cancelButton = e.target.closest('.btn-cancel');
        
        if (seatButton) {
            const card = seatButton.closest('.client-card');
            // Si se hizo clic en 'sentar', abre la modal con los datos de esa tarjeta.
            openSeatClientModal(card.dataset.clientId, card.dataset.clientName);
        }
        if (cancelButton) {
            // Si se hizo clic en 'cancelar', archiva al cliente de esa tarjeta.
            archiveClientAsCancelled(cancelButton.closest('.client-card').dataset.clientId);
        }
    });

    // Asigna las funciones de cerrar/confirmar a los botones de la modal.
    closeModalBtn.addEventListener('click', closeSeatClientModal);
    cancelSeatBtn.addEventListener('click', closeSeatClientModal);
    confirmSeatBtn.addEventListener('click', confirmAndSeatClient);

    // Listener para la selección de mesas en la modal.
    modalTableGrid.addEventListener('click', (e) => {
        // Verifica si el elemento clickeado es una caja de mesa.
        if (e.target.classList.contains('modal-table-box')) {
            // Añade o quita la clase 'selected' para cambiar su apariencia.
            e.target.classList.toggle('selected');
        }
    });

    // --- INICIALIZACIÓN ---
    
    // Llama a esta función una vez cuando la página carga por primera vez
    // para mostrar la lista de espera inicial.
    loadWaitingList(); 
});
