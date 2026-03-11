<?php
// /src/php/views/sales_history.php

require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/security/check_session.php';

// --- LÓGICA DE SEGURIDAD PARA HISTORIAL Y REPORTES ---
define('CASHIER_ROLE_ID', 6);
define('MANAGER_ROLE_ID', 1);

if (!isset($_SESSION['rol_id']) || !in_array($_SESSION['rol_id'], [CASHIER_ROLE_ID, MANAGER_ROLE_ID])) {
    header('Location: /index.php?error=unauthorized_access');
    exit();
}

$userName = htmlspecialchars($_SESSION['user_name'] ?? 'Usuario'); 
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial y Reportes | KitchenLink</title>
      <link rel="icon" href="/src/images/logos/KitchenLink_logo.png" type="image/png" sizes="32x32">
    
    <link rel="stylesheet" href="/src/css/sales_history.css"> 
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

</head>
<body>

<div id="page-loader">
    <i class="fas fa-spinner fa-spin"></i>&nbsp;&nbsp; Verificando estado del turno...
</div>

<div id="shiftOpenModal" class="modal">
    <div class="modal-content">
        <h2>Abrir Nuevo Turno</h2>
        <p>El sistema se encuentra cerrado. Debes abrir un nuevo turno para continuar.</p>
        
        <div class="form-group" style="margin-top: 20px;">
            <label for="startingCashInput">Fondo de Caja Inicial (Caja Chica)</label>
            <input type="text" id="startingCashInput" placeholder="Ej: 1500.00" class="payment-input">
        </div>

        <div class="modal-footer" style="margin-top: 20px;">
            <a href="/src/php/logout.php" class="action-btn" style="background-color: #6c757d; text-decoration:none;">Salir</a>
            <button id="btn-open-shift-confirm" class="action-btn primary-btn">Abrir Turno</button>
        </div>
    </div>
</div>


<div class="main-container">
    <aside class="sidebar">
        <div>
            <h2>Restaurante</h2>
            <ul>
                <li><a href="cashier.php"><i class="fas fa-cash-register"></i> Gestión de Pagos</a></li>
                <li><a href="#" class="active"><i class="fas fa-history"></i> Historial y Reportes</a></li>
            </ul>
        </div>
        
       <div class="user-info">
            <div class="user-details">
                <i class="fas fa-user-circle user-avatar"></i>
                <div class="user-text-container">
                    <div class="user-name-text"><?php echo $userName; ?></div>
                    <div class="session-status-text">Sesión Activa</div>
                </div>
            </div>
            <a href="/src/php/logout.php" class="logout-btn" title="Cerrar Sesión">
                <i class="fas fa-sign-out-alt"></i>
            </a>
        </div>
    </aside>

    <main class="content">
        <div id="liveClockContainer"></div> <h1>Historial y Reportes</h1>

        <div class="tab-container" id="mainTabs">
            <button class="tab-link active" data-tab="tab-reprint">Reimpresión de Tickets</button>
            <button class="tab-link" data-tab="tab-reports">Reportes y Cortes</button>
            <button class="tab-link" data-tab="tab-reconciliation">Arqueo de Caja</button>
        </div>

        <div id="tab-reprint" class="tab-content active">
            <div class="filter-form">
                <div class="form-group">
                    <label for="searchFolio">Buscar por Folio (Sale ID)</label>
                    <input type="text" id="searchFolio" placeholder="Ej: 1024">
                </div>
                <div class="form-group">
                    <label for="searchStartDate">Fecha de Inicio</label>
                    <input type="date" id="searchStartDate">
                </div>
                <div class="form-group">
                    <label for="searchEndDate">Fecha de Fin</label>
                    <input type="date" id="searchEndDate">
                </div>
                <button class="action-btn primary-btn" id="btnSearchTickets"><i class="fas fa-search"></i> Buscar</button>
            </div>
            
            <div class="results-container">
                <table class="results-table">
                    <thead>
                        <tr>
                            <th>Folio</th>
                            <th>Fecha y Hora</th>
                            <th>Mesa</th>
                            <th>Mesero</th>
                            <th>Cajero</th>
                            <th>Total</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody id="ticketResultsBody">
                        <tr><td colspan="7" style="text-align:center; padding: 20px;">Use los filtros para buscar tickets.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div id="tab-reports" class="tab-content">
            <div class="report-section">
                <h4>Corte de Turno (Corte Z)</h4>
                <p>Genera el reporte Z final del turno actual. Esta acción consolida todas las ventas, impuestos, propinas y descuentos desde la apertura de caja. Se recomienda realizar el Arqueo de Caja primero.</p>
                <button class="action-btn primary-btn" id="btnGenerateShiftReport"><i class="fas fa-file-invoice-dollar"></i> Generar Corte Z del Turno</button>
            </div>

            <div class="report-section">
                <h4>Reporte de Mesero</h4>
                <p>Genera un reporte de ventas y propinas (especialmente de tarjeta) para un mesero específico durante el turno actual. Útil para liquidar propinas.</p>
                
                <div class="filter-form" style="padding:0; box-shadow:none; background:none;">
                    <div class="form-group">
                        <label for="selectServerReport">Seleccionar Mesero</label>
                        <select id="selectServerReport">
                            <option value="">Cargando meseros...</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="serverDeductionRate">Deducción (% de Venta)</label>
                        <input type="text" id="serverDeductionRate" placeholder="Ej: 0.10" value="0.0" style="width: 150px;">
                    </div>

                    <button class="action-btn primary-btn" id="btnGenerateServerReport"><i class="fas fa-user-tag"></i> Generar Reporte</button>
                </div>

            </div>
        </div>

        <div id="tab-reconciliation" class="tab-content">
            <div class="report-section">
                <h4>Arqueo de Caja (Corte X)</h4>
                <p>Realice el conteo físico del dinero en caja y compárelo con el total esperado por el sistema. Esto no cierra el turno, solo verifica.</p>
                
                <div class="reconciliation-grid">
                    <div class="recon-section" id="system-totals">
                        <h4>Totales del Sistema (Esperado)</h4>
                        <div class="recon-row">
                            <span>Fondo de Caja Inicial:</span>
                            <span id="reconStartCash">$0.00</span>
                        </div>
                        <div class="recon-row">
                            <span>Ventas en Efectivo:</span>
                            <span id="reconCashSales">$0.00</span>
                        </div>
                        <div class="recon-row">
                            <span>Entradas de Efectivo:</span>
                            <span id="reconCashIn">$0.00</span>
                        </div>
                        <div class="recon-row">
                            <span>Salidas de Efectivo:</span>
                            <span id="reconCashOut">$0.00</span>
                        </div>
                        <div class="recon-row total">
                            <span>Total Efectivo Esperado:</span>
                            <span id="reconExpectedTotal">$0.00</span>
                        </div>
                    </div>
                    
                    <div class="recon-section" id="manual-count">
                        <h4>Conteo Manual (Físico)</h4>
                        
                        <div class="recon-row">
                            <label for="count-1000">$1000 x</label>
                            <input type="number" id="count-1000" class="count-input recon-denom" data-value="1000" min="0">
                        </div>
                        <div class="recon-row">
                            <label for="count-500">$500 x</label>
                            <input type="number" id="count-500" class="count-input recon-denom" data-value="500" min="0">
                        </div>
                        <div class="recon-row">
                            <label for="count-200">$200 x</label>
                            <input type="number" id="count-200" class="count-input recon-denom" data-value="200" min="0">
                        </div>
                        <div class="recon-row">
                            <label for="count-100">$100 x</label>
                            <input type="number" id="count-100" class="count-input recon-denom" data-value="100" min="0">
                        </div>
                        <div class="recon-row">
                            <label for="count-50">$50 x</label>
                            <input type="number" id="count-50" class="count-input recon-denom" data-value="50" min="0">
                        </div>
                        <div class="recon-row">
                            <label for="count-20">$20 x</label>
                            <input type="number" id="count-20" class="count-input recon-denom" data-value="20" min="0">
                        </div>
                         <div class="recon-row">
                            <label for="count-coins">Total en Monedas:</label>
                            <input type="number" id="count-coins" class="count-input recon-denom" data-value="1" step="0.01" min="0" placeholder="Ej: 120.50">
                        </div>
                        <div class="recon-row total">
                            <span>Total Efectivo Contado:</span>
                            <span id="reconManualTotal">$0.00</span>
                        </div>
                    </div>
                </div>

                <div class="report-section" style="margin-top:20px; padding: 20px;">
                    <h4 style="text-align:center; margin-top:0;">Diferencia</h4>
                    <div id="reconDifferenceAmount" class="difference-total zero">$0.00</div>
                    <p id="reconDifferenceText" style="text-align:center; margin-top:10px;">En Cuadre</p>
                </div>
            </div>
        </div>

    </main>
</div> 

<script src="/src/js/session_interceptor.js"></script>
<script type="module" src="/src/js/sales_history.js"></script>

</body>
</html>
