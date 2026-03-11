export class Modal {
    constructor(modalId) {
        this.modalElement = document.getElementById(modalId);
        if (!this.modalElement) {
            throw new Error(`No se encontró el modal con el ID: ${modalId}`);
        }
        // Ya no necesitamos la referencia a la 'X'
        // this.closeButton = this.modalElement.querySelector('.close-btn'); 
        this._initializeEvents();
    }

    open() {
        this.modalElement.classList.add('visible');
    }

    close() {
        this.modalElement.classList.remove('visible');
    }

    _initializeEvents() {
        // El evento para la 'X' ha sido eliminado.
        // if (this.closeButton) {
        //     this.closeButton.addEventListener('click', () => this.close());
        // }
        
        // Mantenemos el cierre al hacer clic en el fondo
        this.modalElement.addEventListener('click', (event) => {
            if (event.target === this.modalElement) {
                this.close();
            }
        });
    }
}
