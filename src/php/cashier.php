<?php
// cashier.php - Interfaz de Caja (CORREGIDO)

require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/security/check_session.php';

// --- LÓGICA DE SEGURIDAD PARA CAJA ---
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
    <title>Gestión de Pagos | KitchenLink</title>
      <link rel="icon" href="/src/images/logos/KitchenLink_logo.png" type="image/png" sizes="32x32">
    <link rel="stylesheet" href="/src/css/cashier.css"> 
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>

<div class="main-container">
    <aside class="sidebar">
        <div>
            <h2>Restaurante</h2>
            <ul>
                <li><a href="#" class="active"><i class="fas fa-cash-register"></i> Gestión de Pagos</a></li>
                <li><a href="sales_history.php"><i class="fas fa-history"></i> Historial y Reportes</a></li>
            </ul>
        </div>
        
       <div class="user-info">
            <div class="user-details">
                <i class="fas fa-user-tie user-avatar"></i>
                <div class="user-text-container">
                    <div class="user-name-text"><?php echo $userName; ?></div>
                    <div class="session-status-text">Sesión activa</div>
                </div>
            </div>
            <a href="/src/php/logout.php" class="logout-btn" title="Cerrar Sesión">
                <i class="fas fa-sign-out-alt"></i>
            </a>
        </div>
    </aside>

    <main class="content">
        <div id="liveClockContainer"></div>
        <h1>Gestión de Pagos</h1>
        <div class="cashier-container">
            <section class="account-list">
                <h3>Cuentas Abiertas</h3>
                <ul id="openAccountsList"><p>Cargando cuentas...</p></ul>
            </section>
            <section class="account-details">
                <h3>Detalle de la Cuenta</h3>
                <div id="accountDetailsContent"><p class="placeholder-text">Seleccione una cuenta para ver los detalles.</p></div>
            </section>
        </div>
        <div class="footer-actions">
            <div class="control-buttons">
                <button class="action-btn" id="btn-print-ticket" disabled>Imprimir Ticket</button>
                <button class="action-btn primary-btn" id="btn-process-payment" disabled>Cobrar Cuenta</button>
            </div>
        </div>
    </main>
</div> 

<div id="paymentModal" class="modal">
    <div class="modal-content">
        <span class="close-button">&times;</span>
        <h2>Procesar Pago de Mesa <span id="modalTableNumber"></span></h2>
        
        <div class="payment-summary">
            <div>Total a Pagar: <span id="modalTotalAmount" class="amount-prominent">$0.00</span></div>
            <div>Monto Restante: <span id="modalRemainingAmount" class="amount-prominent remaining">$0.00</span></div>
        </div>

        <div class="payment-interface">
            <div class="add-payment-section">
                <h4>Agregar Pago</h4>
                <input type="text" id="paymentAmountInput" placeholder="Monto" class="payment-input">
                <div class="payment-methods">
                    <button class="method-btn" data-method="Efectivo">Efectivo</button>
                    <button class="method-btn" data-method="Tarjeta de Crédito">T. Crédito</button>
                    <button class="method-btn" data-method="Tarjeta de Débito">T. Débito</button>
                    <button class="method-btn" data-method="Cortesía">Cortesía</button>
                </div>
                <div id="cashChangeSection" style="display: none; margin-top: 15px;">
                    <input type="text" id="cashReceivedInput" placeholder="Cliente paga con" class="payment-input">
                    <p>Cambio: <span id="cashChangeAmount" class="amount-prominent">$0.00</span></p>
                </div>
                <div id="totalTipSection" style="display: none; margin-top: 10px;">
                    <p>Propina Total: <span id="modalTotalTipAmount" class="amount-prominent tip-total">$0.00</span></p>
                </div>
            </div>

            <div class="registered-payments-section">
                <h4>Pagos Registrados</h4>
                <ul id="paymentsMadeList"></ul>
            </div>
        </div>

        <div class="modal-footer">
            <button id="btn-finalize-payment" class="action-btn primary-btn" disabled>Finalizar y Cerrar Cuenta</button>
        </div>
    </div>
</div>

<script src="/src/js/session_interceptor.js"></script>
<script type="module" src="/src/js/cashier.js"></script>

</body>
</html>
