<?php
// /src/php/manager_dashboard.php - Panel Principal del Gerente

// 1. Incluye el check_session universal.
require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/security/check_session.php';

// --- LÓGICA DE SEGURIDAD CRÍTICA ---
define('MANAGER_ROLE_ID', 1); // 1 = Gerente

// 🔑 Verificación Crítica: Si el rol NO es Gerente (1), se deniega el acceso.
if (!isset($_SESSION['rol_id']) || $_SESSION['rol_id'] != MANAGER_ROLE_ID) {
    
    // 💥 CORRECCIÓN CRÍTICA: Destrucción Total (PHP + Token de DB)
    
    // 1. Borrar el token de la base de datos
    if (isset($conn) && isset($_SESSION['user_id'])) {
        try {
            $clean_stmt = $conn->prepare("UPDATE users SET session_token = NULL WHERE id = ?");
            $clean_stmt->bind_param("i", $_SESSION['user_id']);
            $clean_stmt->execute();
            $clean_stmt->close();
        } catch (\Throwable $e) {
            // Manejo silencioso de error
        }
    }
    
    // 2. Destruir la sesión PHP
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_unset();
        session_destroy();
    }
    
    header('Location: /index.php?error=acceso_denegado_gerente');
    exit();
}

// Variables de Personalización
$userName = htmlspecialchars($_SESSION['user_name'] ?? 'Gerente'); 
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Gerente | KitchenLink</title>
    <link rel="icon" href="/src/images/logos/KitchenLink_logo.png" type="image/png" sizes="32x32">
    
    <link rel="stylesheet" href="/src/css/orders.css">
    <link rel="stylesheet" href="/src/css/modal_advanced_options.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

    <script>
        window.isManagerMode = true;
        window.currentUserRole = <?php echo $_SESSION['rol_id']; ?>;
    </script>
</head>
<body>

<div class="main-container">
    <aside class="sidebar">
        <div>
            <h2>Administración</h2>
            <ul>
                <li><a href="#" class="active"><i class="fas fa-th-large"></i> Monitoreo de Mesas</a></li>
                
                <li><a href="manager_users.php"><i class="fas fa-users-cog"></i> Usuarios</a></li>
                
                <li><a href="manager_menu.php"><i class="fas fa-utensils"></i> Menú y Productos</a></li>
                
                <li><a href="manager_reports.php"><i class="fas fa-chart-line"></i> Gestión de Reportes</a></li>

                <li><a href="manager_waste.php"><i class="fas fa-trash-alt"></i> Control de Mermas</a></li>

            </ul>
        </div>
        
       <div class="user-info">
            <div class="user-details">
                <i class="fas fa-user-tie user-avatar"></i>
                
                <div class="user-text-container">
                    <div class="user-name-text"><?php echo $userName; ?></div>
                    <div class="session-status-text">Gerente General</div>
                </div>
            </div>
            
            <a href="/src/php/logout.php" class="logout-btn" title="Cerrar Sesión">
                <i class="fas fa-sign-out-alt"></i>
            </a>
        </div>
    </aside>

    <main class="content">
        <div id="liveClockContainer"></div>

        <h1>Visión Global de Mesas</h1>
        
        <section class="table-status-container">
            <h3>Mapa del Restaurante</h3>
            
            <div class="table-grid" id="tableGridContainer">
                <p id="loadingMessage" style="padding: 20px; color: gray;">Cargando mapa del restaurante...</p>
            </div>
            
            <button class="fab" id="fab" title="Abrir Nueva Mesa">
                <i class="fas fa-plus"></i>
            </button>
        </section>
        
        <div class="footer-actions">
            <div class="control-buttons">
                <button class="action-btn primary-btn" id="btn-edit-order" disabled>Editar Mesa</button>
                <button class="action-btn primary-btn" id="btn-advanced-options" disabled>Opciones avanzadas</button>
            </div>
        </div>
    </main>
</div> 

<?php include 'modal_create_table.php'; ?> 

<?php 
    // Incluimos los modales de opciones avanzadas
    include $_SERVER['DOCUMENT_ROOT'] . '/src/components/advanced_options_modals.php';
?>

<div id="notification-container"></div> 

<script src="/src/js/session_interceptor.js"></script>
<script type="module" src="/src/js/orders.js"></script>
</body>
</html>
