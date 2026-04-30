// ProductsMover.js
export class ProductsMover {
    constructor(advancedModalInstance) {
        this.advancedModal = advancedModalInstance;

        // Elementos del DOM
        this.sourceTableDisplay = document.getElementById('sourceTableDisplay');
        this.sourceProductsList = document.getElementById('sourceProductsList');
        this.selectedCountDisplay = document.getElementById('selectedCount');
        this.destinationTableSelect = document.getElementById('destinationTableSelect');
        this.moveProductsForm = document.getElementById('moveProductsForm');
        this.executeMoveBtn = document.getElementById('executeMoveBtn');
        this.moveErrorMsg = document.getElementById('moveErrorMsg');

        // Estado
        this.sourceTableNumber = null;
        this.sourceOrderID = null; 
        this.selectedItems = new Map();

        // Endpoints de la API
        this.api = {
            GET_DATA: '/src/api/orders/advanced_options/get_move_data.php',
            EXECUTE_MOVE: '/src/api/orders/advanced_options/execute_move.php'
        };
        
        this._handleSubmitBound = this._handleSubmit.bind(this);
        this._handleProductListClickBound = this._handleProductListClick.bind(this);
        this._checkCanMoveBound = this._checkCanMove.bind(this);
    }

    initialize(sourceTableNumber) {
        this._resetState();
        this.sourceTableNumber = parseInt(sourceTableNumber, 10);
        this.sourceTableDisplay.textContent = sourceTableNumber;
        this._setupEventListeners();
        this._loadMoveData();
    }
    
    dispose() {
        this.moveProductsForm.removeEventListener('submit', this._handleSubmitBound);
        this.sourceProductsList.removeEventListener('click', this._handleProductListClickBound);
        this.destinationTableSelect.removeEventListener('change', this._checkCanMoveBound);
        this._resetState();
    }

    _resetState() {
        this.selectedItems.clear();
        this.sourceOrderID = null;
        this.sourceProductsList.innerHTML = '<p class="loading-msg">Cargando productos...</p>';
        this.destinationTableSelect.innerHTML = '<option value="">Cargando mesas...</option>';
        this.selectedCountDisplay.textContent = '0';
        this.executeMoveBtn.disabled = true;
        this.moveErrorMsg.style.display = 'none';
        this.moveErrorMsg.textContent = '';
    }

    _setupEventListeners() {
        this.moveProductsForm.addEventListener('submit', this._handleSubmitBound);
        this.sourceProductsList.addEventListener('click', this._handleProductListClickBound);
        this.destinationTableSelect.addEventListener('change', this._checkCanMoveBound);
    }
    
    _handleProductListClick(e) {
        const itemElement = e.target.closest('.product-item');
        if (itemElement) {
            this._handleItemSelection(itemElement);
        }
    }

    async _loadMoveData() {
        try {
            const url = `${this.api.GET_DATA}?source_table=${this.sourceTableNumber}`;
            const res = await fetch(url);
            const data = await res.json();

            if (!data.success) {
                this.sourceProductsList.innerHTML = `<p class="error-msg">${data.message || 'Error al obtener datos.'}</p>`;
                return;
            }

            this.sourceOrderID = data.source_order_id;
            this._renderProducts(data.products);
            this._renderDestinationTables(data.available_tables);

        } catch (error) {
            this.sourceProductsList.innerHTML = `<p class="error-msg">Error de conexión al servidor.</p>`;
        }
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

    _updateTotalSelection() {
        let totalItems = 0;
        this.selectedItems.forEach(qty => {
            totalItems += qty; 
        });
        this.selectedCountDisplay.textContent = totalItems;
        this._checkCanMove();
    }

    _checkCanMove() {
        const hasSelection = this.selectedItems.size > 0;
        const hasDestination = this.destinationTableSelect.value !== '';
        this.executeMoveBtn.disabled = !(hasSelection && hasDestination);
    }

    _renderProducts(products) {
        this.sourceProductsList.innerHTML = '';
        if (products.length === 0) {
            this.sourceProductsList.innerHTML = '<p>No hay productos pendientes para mover.</p>';
            return;
        }

        const listHtml = products.map(p => {
            const modifierHtml = p.modifier_name
                ? `<small class="item-modifier">${p.modifier_name}</small>`
                : '';

            return `
                <div class="product-item" 
                     data-detail-id="${p.detail_id}" 
                     data-quantity="${p.quantity}"
                     title="Clic para mover ${p.product_name}">
                    
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

        this.sourceProductsList.innerHTML = listHtml;
    }

    _renderDestinationTables(tables) {
        this.destinationTableSelect.innerHTML = '<option value="">-- Seleccione Mesa --</option>';

        tables.forEach(t => {
            if (t.table_number !== this.sourceTableNumber) {
                const option = document.createElement('option');
                option.value = t.table_number; 
                option.textContent = `Mesa ${t.table_number} (${t.status})`;
                this.destinationTableSelect.appendChild(option);
            }
        });
    }

    // =================================================================
    // MÉTODO CORREGIDO
    // =================================================================
    async _handleSubmit(event) {
        event.preventDefault();
        this.moveErrorMsg.style.display = 'none';

        const destinationTableNumber = parseInt(this.destinationTableSelect.value, 10);
        
        if (!this.sourceOrderID || this.selectedItems.size === 0 || !destinationTableNumber) {
            this._showError('Debe seleccionar productos y una mesa de destino.');
            return;
        }
        
        // CORRECCIÓN: Se envía un array de objetos con 'detail_id' y 'quantity'.
        const itemsToMove = Array.from(this.selectedItems.entries()).map(([detailId, quantity]) => ({
            detail_id: parseInt(detailId, 10),
            quantity: quantity
        }));

        this.executeMoveBtn.disabled = true;
        this.executeMoveBtn.textContent = 'Moviendo...';

        try {
            const res = await fetch(this.api.EXECUTE_MOVE, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    source_order_id: this.sourceOrderID,
                    destination_table_number: destinationTableNumber,
                    items: itemsToMove // CORRECCIÓN: Se usa la clave 'items' que el backend espera
                })
            });
            const result = await res.json();
            
            if (result.success) {
                window.appAlert(result.message);
                this.advancedModal.close();
                window.dispatchEvent(new CustomEvent('table-list-update')); 
            } else {
                this._showError(result.message || 'Error desconocido al mover productos.');
            }

        } catch (error) {
            this._showError('Error de conexión con el servidor.');
        } finally {
            this.executeMoveBtn.disabled = false;
            this.executeMoveBtn.textContent = 'Mover Seleccionados';
        }
    }

    _showError(message) {
        this.moveErrorMsg.textContent = message;
        this.moveErrorMsg.style.display = 'block';
    }
}
