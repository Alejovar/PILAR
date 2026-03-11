<?php
// modal_create_table.php - Contiene el HTML estilizado del modal.
?>

<div id="newTableModal" class="modal-overlay">
    <div class="modal-content">
        <h2>Crear Nueva Mesa</h2>
        <form id="newTableForm">
            
            <div class="form-group">
                <label for="mesaNumber">Número de Mesa:</label>
                <input type="number" id="mesaNumber" required min="1" max="9999" maxlength="4"> 
                <p class="validation-message" id="mesaNumberError"></p>
            </div>
            
            <div class="form-group">
                <label for="clientCount">Número de Personas:</label>
                <input type="number" id="clientCount" required min="1" max="99" maxlength="2"> 
                <p class="validation-message" id="clientCountError"></p>
            </div>

            <div class="form-group" id="serverSelectContainer" style="display: none;">
                <label for="assignedServerSelect">Asignar a Mesero:</label>
                <select id="assignedServerSelect" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; font-size: 1rem;">
                    <option value="">-- Asignar a mí (Default) --</option>
                    </select>
            </div>
            <div class="control-buttonsmodal">
                <button type="button" class="action-btnmodal secondary-btnmodal" id="cancelCreate">Cancelar</button>
                <button type="submit" class="action-btnmodal primary-btnmodal">Crear Mesa</button>
            </div>
        </form>
    </div>
</div>
