<?php
// orders.php - Tu archivo principal en el servidor

// 1. Incluye el check_session universal.
require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/security/check_session.php';

// --- LÓGICA DE SEGURIDAD CRÍTICA ---
define('MESERO_ROLE_ID', 2);

// 🔑 Verificación Crítica: Si el rol de la sesión NO es el requerido (2), se deniega el acceso.
if (!isset($_SESSION['rol_id']) || $_SESSION['rol_id'] != MESERO_ROLE_ID) {
    
    // 💥 CORRECCIÓN CRÍTICA: Destrucción Total (PHP + Token de DB)
    
    // 1. Borrar el token de la base de datos
    // Usamos $conn que fue abierta por check_session.php
    if (isset($conn) && isset($_SESSION['user_id'])) {
        try {
            $clean_stmt = $conn->prepare("UPDATE users SET session_token = NULL WHERE id = ?");
            $clean_stmt->bind_param("i", $_SESSION['user_id']);
            $clean_stmt->execute();
            $clean_stmt->close();
        } catch (\Throwable $e) {
            // Manejo de error si falla la limpieza del token (opcional)
        }
    }
    
    // 2. Destruir la sesión PHP
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_unset();
        session_destroy();
    }
    
    header('Location: /index.php?error=acceso_no_mesero_pendientes');
    exit();
}
// Si el script llega aquí, el usuario es un Mesero válido.

// Variables de Personalización
$userName = htmlspecialchars($_SESSION['user_name'] ?? 'Mesero'); 
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Mesas | KitchenLink</title>
    <link rel="icon" href="/src/images/logos/KitchenLink_logo.png" type="image/png" sizes="32x32">
    <link rel="stylesheet" href="/src/css/orders.css">
    <link rel="stylesheet" href="/src/css/modal_advanced_options.css">
    <link rel="stylesheet" href="/src/css/server_profile_modal.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>

<div class="main-container">
    <aside class="sidebar">
        <div>
            <h2>Restaurante</h2>
            <ul>
                <li><a href="#" class="active"><i class="fas fa-utensils"></i> Mesas</a></li>
                <li><a href="/src/php/pending_orders.php"><i class="fas fa-bell"></i> Órdenes Pendientes</a></li>
            </ul>
        </div>
        
        <div class="user-info">
            <div class="user-details" id="userProfileTrigger" title="Ver mi perfil">
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

        <h1>Gestión de Mesas</h1>
        
        <section class="table-status-container">
            <h3>Estado de Mis Mesas</h3>
            
            <div class="table-grid" id="tableGridContainer">
                <p id="loadingMessage" style="padding: 20px; color: gray;">Cargando mesas...</p>
            </div>
            
            <button class="fab" id="fab" title="Añadir nueva mesa/orden">
                <i class="fas fa-plus"></i>
            </button>
        </section>
        
        <div class="footer-actions">
            <div class="control-buttons">
                <button class="action-btn primary-btn" id="btn-edit-order">Editar mesa</button>
                <button class="action-btn primary-btn" id="btn-advanced-options">Opciones avanzadas</button>
            </div>
        </div>
    </main>
</div>

<?php include 'modal_create_table.php'; ?>

<?php
    // Modal de perfil del mesero
    include $_SERVER['DOCUMENT_ROOT'] . '/src/components/server_profile_modal.php';
?>

<?php 
    // Modales de opciones avanzadas
    include $_SERVER['DOCUMENT_ROOT'] . '/src/components/advanced_options_modals.php';
?>

<div id="notification-container"></div>
<script src="/src/js/session_interceptor.js"></script>
<script type="module" src="/src/js/orders.js"></script>
<script type="module" src="/src/js/notifications.js"></script>
</body>
</html>