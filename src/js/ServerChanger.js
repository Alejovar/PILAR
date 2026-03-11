// ServerChanger.js
export class ServerChanger {
    constructor(advancedModalInstance) {
        this.advancedModal = advancedModalInstance; 
        
        this.form = document.getElementById('changeServerForm');
        this.serverSelect = document.getElementById('newServerSelect');
        this.errorMsg = document.getElementById('serverErrorMsg');
        this.currentTableDisplay = document.getElementById('serverChangeTableDisplay');
        this.currentServerDisplay = document.getElementById('currentServerDisplay');

        this.currentTableNumber = null;
        this.api = {
            GET_SERVERS: '/src/api/orders/advanced_options/get_server_list.php',
            EXECUTE_CHANGE: '/src/api/orders/advanced_options/change_server.php'
        };
    }

    initialize(currentTableNumber, currentServerName) {
        this._resetState();
        this.currentTableNumber = parseInt(currentTableNumber, 10);
        this.currentTableDisplay.textContent = currentTableNumber;
        this.currentServerDisplay.textContent = currentServerName || 'Desconocido';

        this._setupEventListeners();
        this._loadServerData();
    }

    _resetState() {
        this.serverSelect.innerHTML = '<option value="">Cargando meseros...</option>';
        this.errorMsg.style.display = 'none';
        this.errorMsg.textContent = '';
        this.form.reset();
    }

    _setupEventListeners() {
        this.form.removeEventListener('submit', this._handleSubmitBound);
        this._handleSubmitBound = this._handleSubmit.bind(this);
        this.form.addEventListener('submit', this._handleSubmitBound);
    }
    
    async _loadServerData() {
        try {
            const res = await fetch(this.api.GET_SERVERS);
            const data = await res.json();
            
            if (data.success && data.servers) {
                this._renderServerSelect(data.servers);
            } else {
                this.serverSelect.innerHTML = '<option value="">Error al cargar meseros</option>';
            }
        } catch (error) {
            this.serverSelect.innerHTML = '<option value="">Error de conexión</option>';
        }
    }
    
    _renderServerSelect(servers) {
        this.serverSelect.innerHTML = '<option value="">-- Seleccionar Mesero --</option>';
        servers.forEach(server => {
            const option = document.createElement('option');
            option.value = server.id;
            option.textContent = server.name;
            this.serverSelect.appendChild(option);
        });
    }

    async _handleSubmit(event) {
        event.preventDefault();
        this.errorMsg.style.display = 'none';

        const newServerId = parseInt(this.serverSelect.value, 10);

        if (isNaN(newServerId) || newServerId <= 0) {
            this._showError('Por favor, seleccione un mesero válido.');
            return;
        }

        const executeBtn = document.getElementById('executeServerChangeBtn');
        executeBtn.disabled = true;
        executeBtn.textContent = 'Reasignando...';

        try {
            const res = await fetch(this.api.EXECUTE_CHANGE, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    table_number: this.currentTableNumber,
                    new_server_id: newServerId
                })
            });
            const result = await res.json();

            if (result.success) {
                alert(result.message);
                this.advancedModal.close();
                window.dispatchEvent(new CustomEvent('table-list-update')); 
            } else {
                this._showError(result.message || 'Error al reasignar mesero.');
            }
        } catch (error) {
            this._showError('Error de conexión con el servidor.');
        } finally {
            executeBtn.disabled = false;
            executeBtn.textContent = 'Reasignar';
        }
    }

    _showError(message) {
        this.errorMsg.textContent = message;
        this.errorMsg.style.display = 'block';
    }
}
