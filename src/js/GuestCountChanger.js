// GuestCountChanger.js
export class GuestCountChanger {
    constructor(advancedModalInstance) {
        this.advancedModal = advancedModalInstance; 
        
        this.form = document.getElementById('changeGuestCountForm');
        this.input = document.getElementById('newGuestCountInput');
        this.errorMsg = document.getElementById('guestCountErrorMsg');
        this.currentTableDisplay = document.getElementById('currentGuestTableDisplay');

        this.currentTableNumber = null;
        this.apiEndpoint = '/src/api/orders/advanced_options/change_guest_count.php';

        // Vinculamos el método para que 'this' funcione correctamente en el listener
        this._handleInputFormatting = this._formatInput.bind(this);
    }

    /**
     * Formatea la entrada del usuario en tiempo real para permitir solo números válidos.
     */
    _formatInput() {
        let value = this.input.value;

        // 1. Elimina cualquier caracter que no sea un dígito (letras, espacios, etc.).
        let numericValue = value.replace(/[^0-9]/g, '');

        // 2. Si el valor es '0', lo borra para forzar que el número empiece en 1.
        if (numericValue === '0') {
            numericValue = '';
        }

        // 3. El atributo maxlength="2" del HTML ya limita la longitud, pero esto es un refuerzo.
        if (numericValue.length > 2) {
            numericValue = numericValue.slice(0, 2);
        }

        // 4. Actualiza el valor del input para reflejar la limpieza.
        this.input.value = numericValue;
    }

    /**
     * Inicializa el componente con los datos de la mesa y activa los listeners.
     * @param {string|number} currentTableNumber - El número de la mesa actual.
     */
    initialize(currentTableNumber) {
        this.input.value = '';
        this.errorMsg.style.display = 'none';
        this.currentTableNumber = parseInt(currentTableNumber, 10);
        this.currentTableDisplay.textContent = currentTableNumber;

        // Añadimos el listener para la validación en tiempo real.
        // Se remueve primero para evitar que se acumulen listeners si se llama a initialize múltiples veces.
        this.input.removeEventListener('input', this._handleInputFormatting);
        this.input.addEventListener('input', this._handleInputFormatting);

        if (this.form) {
            this.form.removeEventListener('submit', this._handleSubmitBound);
            this._handleSubmitBound = this._handleSubmit.bind(this);
            this.form.addEventListener('submit', this._handleSubmitBound);
        }
    }

    /**
     * Maneja el envío del formulario, validando el valor final y enviándolo a la API.
     * @param {Event} event - El evento de envío del formulario.
     */
    async _handleSubmit(event) {
        event.preventDefault(); 
        this.errorMsg.style.display = 'none';

        const newGuestCount = parseInt(this.input.value, 10);

        // Validación final para el rango de 1 a 99.
        if (isNaN(newGuestCount) || newGuestCount < 1 || newGuestCount > 99) {
            this._showError('Por favor, ingrese un número entre 1 y 99.');
            return;
        }

        try {
            const res = await fetch(this.apiEndpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    table_number: this.currentTableNumber,
                    new_guest_count: newGuestCount
                })
            });
            const result = await res.json();

            if (result.success) {
                window.appAlert(`${result.message}`);
                this.advancedModal.close();
                // Dispara un evento global para que otras partes de la UI se actualicen.
                window.dispatchEvent(new CustomEvent('table-list-update')); 
            } else {
                this._showError(result.message || 'Error desconocido al actualizar comensales.');
            }
        } catch (error) {
            console.error('Error de conexión:', error);
            this._showError('Error de conexión con el servidor.');
        }
    }

    /**
     * Muestra un mensaje de error en la interfaz.
     * @param {string} message - El mensaje de error a mostrar.
     */
    _showError(message) {
        this.errorMsg.textContent = message;
        this.errorMsg.style.display = 'block';
    }
}
