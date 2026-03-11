<div id="managerPasswordModal" class="modal-overlay">
    <div class="modal-content">
        <span class="close-btn">&times;</span>
        <h3>Acceso Restringido</h3>
        <p>Por favor, ingrese la contraseña de un gerente para continuar.</p>
        
        <form id="managerPasswordForm">
            <div class="form-group">
                <label for="managerPasswordInput">Contraseña:</label>
                <input type="password" id="managerPasswordInput" required maxlength="12">
                <p id="passwordErrorMsg" class="error-message" style="display:none;"></p>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" id="cancelPasswordBtn">Cancelar</button>
                <button type="submit" class="btn btn-primary">Verificar</button>
            </div>
        </form>
    </div>
</div>

<div id="advancedOptionsModal" class="modal-overlay">
    <div class="modal-content large">
        <span class="close-btn">&times;</span>
        <h3>Opciones Avanzadas de Gestión</h3>
        
        <div class="advanced-options-layout">
            <nav class="advanced-options-menu">
                <a href="#changeTableNumber" class="menu-item active">Cambiar # de Mesa</a>
                <a href="#changeGuestCount" class="menu-item">Cambiar # de Personas</a>
                <a href="#moveItems" class="menu-item">Mover Productos</a>
                <a href="#cancelItems" class="menu-item">Cancelar Productos</a>
                <a href="#changeServer" class="menu-item">Cambiar Mesero</a>
            </nav>

            <div class="advanced-options-content">
                
                <div id="changeTableNumber" class="content-tab active">
                    <h4>Cambiar Número de Mesa</h4>
                    <p>Reasigna la orden actual a un número de mesa diferente. (Mesa actual: <span id="currentTableDisplay"></span>)</p>

                    <form id="changeTableNumberForm">
                        <div class="form-group">
                            <label for="newTableNumberInput">Nuevo Número de Mesa:</label>
                             <input type="text" id="newTableNumberInput" required placeholder="Ej. 50" class="form-control" inputmode="numeric" maxlength="4">
                            <p id="tableNumberErrorMsg" class="error-message" style="display:none;"></p>
                        </div>

                        <div class="modal-actions">
                            <button type="submit" class="btn btn-primary">Reasignar Mesa</button>
                        </div>
                    </form>
                </div>

                <div id="changeGuestCount" class="content-tab">
                    <h4>Cambiar Número de Personas</h4>
                    <p>Actualiza el número de comensales en la Mesa <span id="currentGuestTableDisplay"></span>.</p>
                    
                    <form id="changeGuestCountForm">
                        <div class="form-group">
                            <label for="newGuestCountInput">Nuevo Número de Comensales:</label>
                            <input type="text" id="newGuestCountInput" required placeholder="Ej. 6" class="form-control" inputmode="numeric" maxlength="2">
                            <p id="guestCountErrorMsg" class="error-message" style="display:none;"></p>
                        </div>

                        <div class="modal-actions">
                            <button type="submit" class="btn btn-primary">Actualizar Comensales</button>
                        </div>
                    </form>
                </div>

                <div id="moveItems" class="content-tab">
                    <h4>Mover Productos de Mesa</h4>
                    <p>Mesa Origen: <span id="sourceTableDisplay"></span></p>

                    <div class="move-layout">
                        <div class="product-selection">
                            <h5>Productos en la Mesa Origen:</h5>
                            <div id="sourceProductsList" class="product-list-container">
                                <p class="loading-msg">Cargando productos...</p>
                            </div>
                            <p class="summary-text">Total de productos seleccionados: <span id="selectedCount">0</span></p>
                        </div>

                        <div class="destination-selection">
                            <form id="moveProductsForm">
                                <h5>Mesa Destino:</h5>
                                <div class="form-group">
                                    <label for="destinationTableSelect">Seleccionar Mesa Destino:</label>
                                    <select id="destinationTableSelect" required class="form-control">
                                        <option value="">Cargando mesas...</option>
                                    </select>
                                </div>
                                
                                <p id="moveErrorMsg" class="error-message" style="display:none;"></p>

                                <div class="modal-actions mt-3">
                                    <button type="submit" class="btn btn-primary" id="executeMoveBtn" disabled>
                                        Mover Seleccionados
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

               <div id="cancelItems" class="content-tab">
                    <h4>Eliminar/Cancelar Productos de Orden</h4>
                    <p>Mesa: <span id="cancelSourceTableDisplay"></span>. Seleccione los productos a cancelar.</p>

                    <div class="cancel-layout">
                        <div class="product-selection">
                            <h5>Productos Activos:</h5>
                            <div id="cancelProductsList" class="product-list-container">
                                <p class="loading-msg">Cargando productos...</p>
                            </div>
                            <p class="summary-text">Total de ítems a cancelar: <span id="cancelSelectedCount">0</span></p>
                        </div>

                        <div class="cancellation-details">
                            <form id="cancelProductsForm">
                                <h5>Detalles de la Cancelación:</h5>
                                <div class="form-group">
                                    <label for="cancellationReason">Razón de la Cancelación:</label>
                                    <textarea id="cancellationReason" rows="3" required placeholder="Ej. El cliente cambió de opinión o se rompió."></textarea>
                                </div>
                                
                                <p id="cancelErrorMsg" class="error-message" style="display:none;"></p>

                                <div class="modal-actions mt-3">
                                    <button type="submit" class="btn alert-btn" id="executeCancelBtn" disabled>
                                        Confirmar Cancelación
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div id="changeServer" class="content-tab">
                    <h4>Reasignar Mesa a otro Mesero</h4>
                    <p>Mesa: <span id="serverChangeTableDisplay"></span>. Mesero actual: <span id="currentServerDisplay"></span></p>

                    <form id="changeServerForm">
                        <div class="form-group">
                            <label for="newServerSelect">Seleccionar Nuevo Mesero:</label>
                            <select id="newServerSelect" required class="form-control">
                                <option value="">Cargando meseros...</option>
                            </select>
                        </div>
                        
                        <p id="serverErrorMsg" class="error-message" style="display:none;"></p>

                        <div class="modal-actions mt-3">
                            <button type="submit" class="btn btn-primary" id="executeServerChangeBtn">
                                Reasignar
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
