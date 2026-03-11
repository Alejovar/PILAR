<?php
// Clase principal para la interfaz de lista de espera

require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/security/check_session.php';

// --- LÓGICA DE SEGURIDAD CRÍTICA ---
define('HOSTESS_ROLE_ID', 4); // ID 4 según tu base de datos

// 🔑 Verificación Crítica: Si el rol de la sesión NO es el requerido (4), se deniega el acceso.
if (!isset($_SESSION['rol_id']) || $_SESSION['rol_id'] != HOSTESS_ROLE_ID) {
    
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
    
    header('Location: /index.php?error=acceso_no_hostess_lista');
    exit();
}
// Si el script llega aquí, el usuario es una Hostess válida.

$hostess_name = htmlspecialchars($_SESSION['user_name'] ?? 'Hostess');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lista de Espera | KitchenLink</title>
      <link rel="icon" href="/src/images/logos/KitchenLink_logo.png" type="image/png" sizes="32x32">
    <link rel="stylesheet" href="/src/css/reservations.css">
    <link rel="stylesheet" href="/src/css/waiting_list.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
</head>
<body>

    <aside class="sidebar">
        <div>
            <h2>Restaurante</h2>
            <ul>
                <li><a href="/src/php/reservations.php"><i class="fas fa-calendar-alt"></i> Reservaciones</a></li>
                <li><a href="/src/php/waiting_list.php" class="active"><i class="fas fa-list-ol"></i> Lista de espera</a></li>
            </ul>
        </div>
        <div class="user-info">
            <div class="user-details">
                <i class="fas fa-user-circle user-avatar"></i>
                <strong><?php echo $hostess_name; ?></strong><br>
                <span>Sesión activa</span>
            </div>
            <a href="/src/php/logout.php" class="logout-btn" title="Cerrar Sesión">
                <i class="fas fa-sign-out-alt"></i>
            </a>
        </div>
    </aside>

    <main class="content">
        <h1>Lista de Espera</h1>

        <div class="main-view">
            <section class="waitlist-display-container">
                <div class="waitlist-header">
                    <h3>Clientes en espera</h3>
                    <div class="estimated-time">
                        <i class="fas fa-clock"></i>
                        <span>Espera estimada: <strong id="estimatedTime">-- min</strong></span>
                    </div>
                </div>
                <div id="waitingList">
                    </div>
            </section>
        
            <section class="form-section">
                <h3>Agregar a la lista</h3>
                <form id="waitlistForm" method="POST">
                    <div class="form-row">
                        <input 
                            type="text" 
                            name="customer_name" 
                            placeholder="Nombre del cliente" 
                            required 
                            pattern="[a-zA-Z\s]+" 
                            maxlength="100"
                            title="Solo se admiten letras y espacios.">
                        
                        <input 
                            type="text" 
                            name="number_of_people" 
                            placeholder="N° de personas" 
                            required 
                            pattern="[0-9]{1,2}"
                            maxlength="2"
                            title="Solo se admiten 1 o 2 dígitos numéricos.">
                    </div>
                    <input 
                        type="tel" 
                        name="customer_phone" 
                        placeholder="Teléfono (opcional)" 
                        pattern="[0-9]{1,10}"
                        maxlength="10"
                        title="El teléfono debe tener máximo 10 dígitos.">
                        
                    <button type="submit">Agregar</button>
                </form>
            </section>
        </div>
    </main>
    <div id="seatClientModal" class="modal-overlay" style="display: none;">
        <div class="modal-content">
            <button class="modal-close" title="Cerrar">&times;</button>
            <h3>Asignar Mesa</h3>
            <p>Seleccione una o más mesas para <strong id="modalClientName"></strong>:</p>
            
            <div id="modalTableGrid" class="modal-table-grid">
                </div>

            <div class="modal-actions">
                <button id="cancelSeatBtn" class="btn-secondary">Cancelar</button>
                <button id="confirmSeatBtn" class="btn-primary">Confirmar y Sentar</button>
            </div>
        </div>
    </div>
    <script src="/src/js/session_interceptor.js"></script>
    <script src="/src/js/waiting_list.js"></script>
</body>
</html>
