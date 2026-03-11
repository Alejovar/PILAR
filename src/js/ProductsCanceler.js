// ProductsCanceler.js
export class ProductsCanceler {
    constructor(advancedModalInstance) {
        this.advancedModal = advancedModalInstance;

        // Elementos del DOM
        this.sourceTableDisplay = document.getElementById('cancelSourceTableDisplay');
        this.productsList = document.getElementById('cancelProductsList');
        this.selectedCountDisplay = document.getElementById('cancelSelectedCount');
        this.cancellationReason = document.getElementById('cancellationReason');
        this.cancelProductsForm = document.getElementById('cancelProductsForm');
        this.executeCancelBtn = document.getElementById('executeCancelBtn');
        this.cancelErrorMsg = document.getElementById('cancelErrorMsg');

        // Estado
        this.sourceTableNumber = null;
        this.sourceOrderID = null; 
        this.selectedItems = new Map();

        // Endpoints de la API
        this.api = {
            GET_PRODUCTS: '/src/api/orders/advanced_options/get_cancel_data.php',
            EXECUTE_CANCEL: '/src/api/orders/advanced_options/execute_cancel.php'
        };

        // Enlaces (binds)
        this._handleSubmitBound = this._handleSubmit.bind(this);
        this._handleProductListClickBound = this._handleProductListClick.bind(this);
        this._checkCanCancelBound = this._checkCanCancel.bind(this);
    }

    initialize(sourceTableNumber) {
        this._resetState();
        this.sourceTableNumber = parseInt(sourceTableNumber, 10);
        this.sourceTableDisplay.textContent = sourceTableNumber;
        this._setupEventListeners();
        this._loadProducts();
    }
    
    dispose() {
        this.cancelProductsForm.removeEventListener('submit', this._handleSubmitBound);
        this.productsList.removeEventListener('click', this._handleProductListClickBound);
        this.cancellationReason.removeEventListener('input', this._checkCanCancelBound);
        this._resetState();
    }

    _resetState() {
        this.selectedItems.clear();
        this.sourceOrderID = null;
        this.productsList.innerHTML = '<p class="loading-msg">Cargando productos...</p>';
        this.selectedCountDisplay.textContent = '0';
        this.cancellationReason.value = '';
        this.executeCancelBtn.disabled = true;
        this.cancelErrorMsg.style.display = 'none';
    }

    _setupEventListeners() {
        this.cancelProductsForm.addEventListener('submit', this._handleSubmitBound);
        this.productsList.addEventListener('click', this._handleProductListClickBound);
        this.cancellationReason.addEventListener('input', this._checkCanCancelBound);
    }
    
    _handleProductListClick(e) {
        const itemElement = e.target.closest('.product-item');
        if (itemElement) {
            this._handleItemSelection(itemElement);
        }
    }

    async _loadProducts() {
        try {
            const url = `${this.api.GET_PRODUCTS}?source_table=${this.sourceTableNumber}`;
            const res = await fetch(url);
            const data = await res.json();

            if (!data.success) {
                this.productsList.innerHTML = `<p class="error-msg">${data.message || 'Error al obtener productos.'}</p>`;
                return;
            }

            this.sourceOrderID = data.source_order_id;
            this._renderProducts(data.products);

        } catch (error) {
            this.productsList.innerHTML = `<p class="error-msg">Error de conexión al servidor.</p>`;
        }
    }
    
    _updateTotalSelection() {
        let totalItems = 0;
        this.selectedItems.forEach(qty => {
            totalItems += qty; 
        });
        this.selectedCountDisplay.textContent = totalItems;
        this._checkCanCancel();
    }
    
    _handleItemSelection(itemElement) {
        const detailId = itemElement.dataset.detailId;
        const quantity = parseInt(itemElement.dataset.quantity, 10);
        
        const isSelected = itemElement.classList.toggle('selected');
        
        if (isSelected) {
            this.selectedItems.set(detailId, quantity);
        } else {
            this.selectedItems.delete(detailId);
        }

        this._updateTotalSelection();
    }
    
    // =================================================================
    // MÉTODO MODIFICADO
    // =================================================================
    _renderProducts(products) {
        this.productsList.innerHTML = '';
        if (products.length === 0) {
            this.productsList.innerHTML = '<p>No hay productos activos para cancelar.</p>';
            return;
        }

        const listHtml = products.map(p => {
            // NUEVO: Genera el HTML para el modificador solo si existe.
            const modifierHtml = p.modifier_name
                ? `<small class="item-modifier">${p.modifier_name}</small>`
                : '';

            return `
                <div class="product-item" 
                     data-detail-id="${p.detail_id}" 
                     data-quantity="${p.quantity}" 
                     title="Clic para cancelar ${p.product_name}">
                    
                    <span class="item-qty">${p.quantity}x</span>
                    
                    <div class="item-details">
                        <span class="item-name">${p.product_name}</span>
                        ${modifierHtml}
                    </div>

                    <span class="item-price">$${p.price_at_order.toFixed(2)}</span>
                    <i class="selection-icon fas fa-check-circle"></i>
                </div>
            `;
        }).join('');

        this.productsList.innerHTML = listHtml;
    }
    
    _checkCanCancel() {
        const hasSelection = this.selectedItems.size > 0;
        const hasReason = this.cancellationReason.value.trim().length > 5; 
        this.executeCancelBtn.disabled = !(hasSelection && hasReason);
    }
    
    async _handleSubmit(event) {
        // ... (Este método no necesita cambios)
        event.preventDefault();
        this.cancelErrorMsg.style.display = 'none';

        if (!this.sourceOrderID || this.selectedItems.size === 0) {
            this._showError('Debe seleccionar al menos un producto.');
            return;
        }
        
        const reason = this.cancellationReason.value.trim();
        if (reason.length < 5) {
             this._showError('La razón de cancelación debe tener al menos 5 caracteres.');
            return;
        }

        const itemsToCancel = Array.from(this.selectedItems.keys()).map(detailId => parseInt(detailId, 10));

        this.executeCancelBtn.disabled = true;
        this.executeCancelBtn.textContent = 'Cancelando...';

        try {
            const res = await fetch(this.api.EXECUTE_CANCEL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    order_id: this.sourceOrderID,
                    items_to_cancel: itemsToCancel,
                    reason: reason
                })
            });
            const result = await res.json();
            
            if (result.success) {
                alert(result.message);
                this.advancedModal.close();
                window.dispatchEvent(new CustomEvent('table-list-update')); 
            } else {
                this._showError(result.message || 'Error desconocido al cancelar productos.');
            }

        } catch (error) {
            this._showError('Error de conexión con el servidor.');
        } finally {
            this.executeCancelBtn.disabled = false;
            this.executeCancelBtn.textContent = 'Confirmar Cancelación';
        }
    }

    _showError(message) {
        this.cancelErrorMsg.textContent = message;
        this.cancelErrorMsg.style.display = 'block';
    }
}
