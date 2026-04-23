<?php
// /src/php/manager_waste.php
// TASK-28: Interfaz de control de mermas
// US-01: Reporte por fecha | US-02: Registrar merma desde cuenta activa

require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/security/check_session.php';

define('MANAGER_ROLE_ID', 1);

if (!isset($_SESSION['rol_id']) || $_SESSION['rol_id'] != MANAGER_ROLE_ID) {
    if (isset($conn) && isset($_SESSION['user_id'])) {
        try {
            $clean_stmt = $conn->prepare("UPDATE users SET session_token = NULL WHERE id = ?");
            $clean_stmt->bind_param("i", $_SESSION['user_id']);
            $clean_stmt->execute();
            $clean_stmt->close();
        } catch (\Throwable $e) {}
    }
    if (session_status() === PHP_SESSION_ACTIVE) { session_unset(); session_destroy(); }
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
    <title>Control de Mermas | KitchenLink</title>
    <link rel="icon" href="/src/images/logos/KitchenLink_logo.png" type="image/png" sizes="32x32">
    <link rel="stylesheet" href="/src/css/manager_waste.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>

<div class="main-container">
    <aside class="sidebar">
        <div>
            <h2>Administración</h2>
            <nav class="sidebar-nav">
                <ul>
                    <li><a href="manager_dashboard.php"><i class="fas fa-th-large"></i> Monitoreo de Mesas</a></li>
                    <li><a href="manager_users.php"><i class="fas fa-users-cog"></i> Usuarios</a></li>
                    <li><a href="manager_menu.php"><i class="fas fa-utensils"></i> Menú y Productos</a></li>
                    <li><a href="manager_reports.php"><i class="fas fa-chart-line"></i> Gestión de Reportes</a></li>
                    <li><a href="manager_waste.php" class="active"><i class="fas fa-trash-alt"></i> Control de Mermas</a></li>
                </ul>
            </nav>
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
        <div class="header-status">
            <div id="liveClockContainer" class="clock-widget">--:--:--</div>
        </div>

        <h1 class="page-title">Control de Mermas</h1>
        <p class="page-subtitle">Registra mermas directamente desde la cuenta de una mesa, o consulta el reporte histórico.</p>

        <div class="waste-tabs-container">
            <div class="waste-tabs-header">
                <button class="waste-tab-link active" data-tab="tab-register">
                    <i class="fas fa-minus-circle"></i> Registrar Merma
                </button>
                <button class="waste-tab-link" data-tab="tab-report">
                    <i class="fas fa-chart-bar"></i> Reporte por Fecha
                </button>
            </div>

            <div class="waste-tabs-content-wrapper">

                <!-- ── US-02: Registrar merma desde cuenta de mesa ── -->
                <div id="tab-register" class="waste-tab-content active">
                    <h4>Selecciona la mesa</h4>

                    <div id="registerFeedback"></div>

                    <!-- Mesas con cuenta abierta -->
                    <div id="ordersGrid" class="orders-grid">
                        <p class="initial-msg"><span class="loading-spinner">Cargando mesas activas...</span></p>
                    </div>

                    <!-- Panel de ítems (oculto hasta seleccionar mesa) -->
                    <div id="itemsPanel" class="items-panel" style="display:none;">
                        <h5 id="itemsPanelTitle">Productos en la cuenta</h5>
                        <p class="initial-msg" id="itemsLoading" style="display:none;">
                            <span class="loading-spinner">Cargando productos...</span>
                        </p>
                        <div id="itemsList"></div>

                        <!-- Formulario de motivo -->
                        <div class="waste-form-row" id="wasteFormRow" style="display:none;">
                            <div class="form-group" style="min-width:200px;">
                                <label for="wasteReason">Motivo de la merma</label>
                                <select id="wasteReason" class="form-control">
                                    <option value="expired">Caducado / Vencido</option>
                                    <option value="kitchen_error">Error de cocina (sí salió)</option>
                                    <option value="waiter_error">Error del mesero (sí salió)</option>
                                    <option value="damaged">Dañado / Derramado</option>
                                    <option value="other">Otro</option>
                                </select>
                            </div>
                            <div class="form-group" style="min-width:260px;">
                                <label for="wasteNotes">Notas adicionales (opcional)</label>
                                <input type="text" id="wasteNotes" class="form-control"
                                       placeholder="Ej: plato incorrecto enviado por cocina" maxlength="200">
                            </div>
                            <button class="action-btn danger-btn" id="btnRegisterWaste">
                                <i class="fas fa-exclamation-triangle"></i> Confirmar Merma
                            </button>
                        </div>
                    </div>
                </div>

                <!-- ── US-01: Reporte de mermas por fecha ── -->
                <div id="tab-report" class="waste-tab-content">
                    <h4>Reporte de Mermas por Fecha</h4>

                    <div class="filter-controls">
                        <div class="form-group">
                            <label for="reportStartDate">Fecha Inicio</label>
                            <input type="date" id="reportStartDate" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="reportEndDate">Fecha Fin</label>
                            <input type="date" id="reportEndDate" class="form-control">
                        </div>
                        <button class="action-btn primary-btn" id="btnRunReport">
                            <i class="fas fa-search"></i> Generar Reporte
                        </button>
                    </div>

                    <div id="reportResults" class="report-results-area">
                        <p class="initial-msg">
                            <i class="fas fa-info-circle"></i>
                            Selecciona un rango de fechas y presiona "Generar Reporte".
                        </p>
                    </div>
                </div>

            </div>
        </div>
    </main>
</div>

<script src="/src/js/session_interceptor.js"></script>
<script src="/src/js/manager_waste.js"></script>
</body>
</html>
