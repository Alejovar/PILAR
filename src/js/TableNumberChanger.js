class TableNumberChanger {
    constructor(advancedModalInstance) {
        this.advancedModal = advancedModalInstance; 
        
        this.form = document.getElementById('changeTableNumberForm');
        this.input = document.getElementById('newTableNumberInput');
        this.errorMsg = document.getElementById('tableNumberErrorMsg');
        this.currentTableDisplay = document.getElementById('currentTableDisplay');

        this.currentTableNumber = null;
        this.apiEndpoint = '/src/api/orders/advanced_options/change_table.php';

        // NUEVO: Vinculamos el método de formateo para usarlo en el event listener.
        this._handleInputFormatting = this._formatInput.bind(this);
    }

    // NUEVO: Método para formatear la entrada del usuario en tiempo real.
    _formatInput() {
        let value = this.input.value;

        // 1. Elimina cualquier caracter que no sea un dígito.
        let numericValue = value.replace(/[^0-9]/g, '');

        // 2. Si el valor es '0', lo borra para forzar que el número empiece en 1.
        if (numericValue === '0') {
            numericValue = '';
        }
        
        // 3. El maxlength="4" del HTML ya se encarga del límite, pero esto es un refuerzo.
        if (numericValue.length > 4) {
            numericValue = numericValue.slice(0, 4);
        }

        // 4. Actualiza el valor del input.
        this.input.value = numericValue;
    }

    initialize(currentTableNumber, currentOrderID) { 
        this.input.value = '';
        this.errorMsg.style.display = 'none';
        this.currentTableNumber = parseInt(currentTableNumber, 10);
        this.currentTableDisplay.textContent = currentTableNumber;

        // MODIFICADO: Añadimos el listener para la validación en tiempo real.
        // Lo removemos primero para evitar duplicados si se llama a initialize varias veces.
        this.input.removeEventListener('input', this._handleInputFormatting);
        this.input.addEventListener('input', this._handleInputFormatting);

        if (this.form) {
            this.form.removeEventListener('submit', this._handleSubmitBound);
            this._handleSubmitBound = this._handleSubmit.bind(this);
            this.form.addEventListener('submit', this._handleSubmitBound);
        }
    }

    async _handleSubmit(event) {
        event.preventDefault();
        this.errorMsg.style.display = 'none';

        const newTableNumber = parseInt(this.input.value, 10);
        
        // MODIFICADO: Validación más estricta para el rango de 1 a 9999.
        if (isNaN(newTableNumber) || newTableNumber < 1 || newTableNumber > 9999) {
            this._showError('Por favor, ingrese un número de mesa entre 1 y 9999.');
            return;
        }

        if (newTableNumber === this.currentTableNumber) {
            this._showError('La nueva mesa debe ser diferente a la actual.');
            return;
        }

        try {
            const res = await fetch(this.apiEndpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    current_table_number: this.currentTableNumber,
                    new_table_number: newTableNumber
                })
            });
            const result = await res.json();

            if (result.success) {
                window.appAlert(`${result.message}`);
                this.advancedModal.close();
                window.dispatchEvent(new CustomEvent('table-list-update')); 
            } else {
                this._showError(result.message || 'Error desconocido al reasignar la mesa.');
            }
        } catch (error) {
            console.error('Error de conexión:', error);
            this._showError('Error de conexión con el servidor.');
        }
    }

    _showError(message) {
        this.errorMsg.textContent = message;
        this.errorMsg.style.display = 'block';
    }
}

// EXPORTACIÓN FINAL
export { TableNumberChanger };
