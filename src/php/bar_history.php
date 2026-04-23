<?php
// bar_history.php - Interfaz para el Historial de Producción de Barra

// 1. Incluimos el check_session universal.
require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/security/check_session.php';

// --- LÓGICA DE SEGURIDAD (RESTRINGIR POR ROL) ---
define('BARRA_ROLE_ID', 5); // ID 5 corresponde al rol 'encargado de barra'

// 🔑 Verificación Crítica: Si el rol de la sesión NO es el requerido (5), se deniega el acceso.
if (!isset($_SESSION['rol_id']) || $_SESSION['rol_id'] != BARRA_ROLE_ID) {
    
    // 💥 CORRECCIÓN CRÍTICA: Destruir la sesión para forzar el logout
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_unset();
        session_destroy();
    }
    
    // Redirigir al inicio y forzar el login
    header('Location: /index.php?error=acceso_no_barra');
    exit();
}
// Si el script llega aquí, el usuario es un Encargado de Barra válido.

// Variables de personalización
$userName = htmlspecialchars($_SESSION['user_name'] ?? 'Bartender');
$rolName = htmlspecialchars($_SESSION['rol_name'] ?? 'Encargado de Barra'); 
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial de Barra | KitchenLink</title>
  <link rel="icon" href="/src/images/logos/KitchenLink_logo.png" type="image/png" sizes="32x32">
    <link rel="stylesheet" href="/src/css/bar_history.css"> 
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>

<div class="main-container">
    <aside class="sidebar">
        <div>
            <h2>Restaurante</h2>
            <ul>
                <li>
                    <a href="/src/php/bar_orders.php">
                        <i class="fas fa-martini-glass-citrus"></i> Órdenes de Barra
                    </a>
                </li>
                <li>
                    <a href="/src/php/bar_history.php" class="active">
                        <i class="fas fa-history"></i> Historial de Barra
                    </a>
                </li>
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

        <div class="history-header">
             <h1>Historial de Barra</h1>
            <div class="date-selector-wrapper">
                <label for="historyDate">Seleccionar fecha:</label>
                <input type="date" id="historyDate">
            </div>
        </div>

        <div id="barHistoryGrid" class="production-grid">
            <p class="loading-msg">Cargando historial...</p>
        </div>
    </main>
</div>
<script src="/src/js/session_interceptor.js"></script>
<script src="/src/js/history_bar.js"></script>

</body>
</html>
