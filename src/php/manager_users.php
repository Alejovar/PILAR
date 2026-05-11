<?php
// /src/php/manager_users.php

require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/security/check_session.php';

// --- LÓGICA DE SEGURIDAD CRÍTICA (BLOQUE SOLICITADO) ---
define('MANAGER_ROLE_ID', 1); // 1 = Gerente

// 🔑 Verificación Crítica: Si el rol NO es Gerente (1), se deniega el acceso.
if (!isset($_SESSION['rol_id']) || $_SESSION['rol_id'] != MANAGER_ROLE_ID) {
    
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

$userName = htmlspecialchars($_SESSION['user_name'] ?? 'Gerente');
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Personal | KitchenLink</title>
    <link rel="icon" href="/src/images/logos/KitchenLink_logo.png" type="image/png" sizes="32x32">
    
    <link rel="stylesheet" href="/src/css/manager_users.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>

<div class="main-container">
    <aside class="sidebar">
        <div>
            <h2>Administración</h2>
            <ul>
                <li><a href="manager_dashboard.php"><i class="fas fa-th-large"></i> Monitoreo de Mesas</a></li>

                <li><a href="#" class="active"><i class="fas fa-users-cog"></i> Usuarios</a></li>
                
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
        <div class="top-bar">
            <div id="liveClockContainer" class="clock-widget" style="font-size: 1.1rem; margin-left: auto;">--:--:--</div>
        </div>

        <h1 class="page-title">Gestión de Personal</h1>

        <div class="toolbar-actions" style="display: flex; justify-content: space-between; margin-bottom: 20px; gap: 15px;">
            
            <div class="search-wrapper" style="position: relative; flex-grow: 1; max-width: 400px;">
                <i class="fas fa-search" style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: #888;"></i>
                <input type="text" id="userSearchInput" placeholder="Buscar por nombre o usuario..." style="width: 100%; padding: 10px 10px 10px 35px; border-radius: 8px; border: 1px solid #ccc;">
            </div>

            <button class="action-btn primary-btn" id="btnNewUser" style="background-color: #5a2dfc; color: white; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; font-weight: bold; display: flex; align-items: center; gap: 8px;">
                <i class="fas fa-user-plus"></i> Nuevo Empleado
            </button>
        </div>
        <div class="users-container">
            <table class="users-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre Completo</th>
                        <th>Usuario (Login)</th>
                        <th>Rol</th>
                        <th>Estado</th>
                        <th>Rostro</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody id="usersTableBody">
                    <tr><td colspan="7" style="text-align:center; padding: 20px;">Cargando personal...</td></tr>
                </tbody>
            </table>
        </div>
    </main>
</div>

<div id="faceModal" class="face-modal">
    <div class="face-modal-content">
        <div class="face-modal-header">
            <h2 id="faceModalTitle">Registrar Rostro</h2>
            <p id="faceModalSubtitle"></p>
        </div>
        <div class="face-modal-body">
            <div class="face-video-wrapper">
                <video id="faceRegVideo" autoplay muted playsinline></video>
                <canvas id="faceRegCanvas"></canvas>
            </div>
            <div id="faceRegStatus" class="face-status">Iniciando camara...</div>
        </div>
        <div class="face-modal-actions">
            <button id="btnCaptureFace" class="face-btn primary">Capturar Rostro</button>
            <button id="btnDeleteFace" class="face-btn danger">Eliminar Rostro</button>
            <button id="cancelFaceModal" class="face-btn">Cancelar</button>
        </div>
    </div>
</div>

<div id="userModal" class="modal-overlay">
    <div class="modal-content">
        <h2 id="modalTitle">Registrar Empleado</h2>
        <form id="userForm">
            <input type="hidden" id="userId"> 
            
            <div class="form-group">
                <label for="userName">Nombre Completo</label>
                <input type="text" id="userName" required placeholder="Ej: Juan Pérez" autocomplete="off">
            </div>
            
            <div class="form-group">
                <label for="userLogin">Usuario (Para iniciar sesión)</label>
                <input type="text" id="userLogin" required placeholder="Ej: juan.perez" autocomplete="off">
            </div>

            <div class="form-group">
                <label for="userRole">Rol del Empleado</label>
                <select id="userRole" required class="form-control">
                    <option value="">-- Seleccionar Rol --</option>
                </select>
            </div>

            <div class="form-group">
                <label for="userPassword">Contraseña</label>
                
                <div class="password-container" style="position: relative;">
                    <input type="password" id="userPassword" placeholder="••••••" autocomplete="new-password" style="width: 100%; padding-right: 40px;">
                    <i class="fas fa-eye" id="togglePasswordBtn" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #888;"></i>
                </div>
                <small class="form-note" id="passHelpText" style="display:none;">Dejar en blanco para mantener la contraseña actual.</small>
            </div>

            <div class="modal-actions">
                <button type="button" id="cancelUserModal" class="cancel-btn">Cancelar</button>
                <button type="submit" class="confirm-btn">Guardar</button>
            </div>
        </form>
    </div>
</div>

<script src="/src/js/session_interceptor.js"></script>
<script src="/src/js/manager_users.js"></script> 
<script src="https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js"></script>
<script src="/src/js/manager_users_face.js"></script>

</body>
</html>
