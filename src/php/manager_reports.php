<?php
// /src/php/manager_reports.php

require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/security/check_session.php';

// --- LÓGICA DE SEGURIDAD CRÍTICA (SOLO GERENTE) ---
define('MANAGER_ROLE_ID', 1); 

if (!isset($_SESSION['rol_id']) || $_SESSION['rol_id'] != MANAGER_ROLE_ID) {
    
    // 1. Borrar el token de la base de datos
    if (isset($conn) && isset($_SESSION['user_id'])) {
        try {
            $clean_stmt = $conn->prepare("UPDATE users SET session_token = NULL WHERE id = ?");
            $clean_stmt->bind_param("i", $_SESSION['user_id']);
            $clean_stmt->execute();
            $clean_stmt->close();
        } catch (\Throwable $e) {}
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
    <title>Reportes Analíticos | KitchenLink</title>
    <link rel="icon" href="/src/images/logos/KitchenLink_logo.png" type="image/png" sizes="32x32">
    
    <link rel="stylesheet" href="/src/css/manager_reports.css"> 
    
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
                    <li><a href="#" class="active"><i class="fas fa-chart-line"></i> Gestión de Reportes</a></li>
                    <li><a href="manager_waste.php"><i class="fas fa-trash-alt"></i> Control de Mermas</a></li>
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
            <a href="/src/php/logout.php" class="logout-btn" title="Cerrar Sesión"><i class="fas fa-sign-out-alt"></i></a>
        </div>
    </aside>

    <main class="content">
        <div class="header-status">
            <div id="liveClockContainer" class="clock-widget">--:--:--</div>
        </div>

        <h1 class="page-title">Reportes Analíticos</h1>

        <div class="report-tabs-container">
            
            <div id="reportTabs" class="report-tabs-header">
                <button class="report-tab-link active" data-tab="product-mix">Productos Más Vendidos</button>
                <button class="report-tab-link" data-tab="cancellation-report">Cancelaciones</button> <button class="report-tab-link" data-tab="service-metrics">Métricas de Servicio</button>
                <button class="report-tab-link" data-tab="rotation-metrics">Rotación de Mesas</button> </div>

            <div class="report-tabs-content-wrapper">
                
                <div id="product-mix" class="report-tab-content active">
                    <h4>Ventas por Volumen</h4>
                    
                    <div class="filter-controls">
                        <div class="form-group">
                            <label for="productMixStartDate">Fecha Inicio</label>
                            <input type="date" id="productMixStartDate" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="productMixEndDate">Fecha Fin</label>
                            <input type="date" id="productMixEndDate" class="form-control">
                        </div>
                        <button class="action-btn primary-btn" id="btnRunProductMix"><i class="fas fa-chart-bar"></i> Generar Reporte</button>
                    </div>
                    
                    <div id="productMixResults" class="report-results-area">
                        <p class="initial-msg"><i class="fas fa-info-circle"></i> Selecciona un rango de fechas y presiona 'Generar Reporte'.</p>
                    </div>
                </div>

                <div id="cancellation-report" class="report-tab-content" style="display: none;">
                    <h4>Pérdidas por Cancelación</h4>
                    
                    <div class="filter-controls">
                        <div class="form-group">
                            <label for="cancellationStartDate">Fecha Inicio</label>
                            <input type="date" id="cancellationStartDate" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="cancellationEndDate">Fecha Fin</label>
                            <input type="date" id="cancellationEndDate" class="form-control">
                        </div>
                        <button class="action-btn primary-btn" id="btnRunCancellationReport"><i class="fas fa-ban"></i> Generar Reporte</button>
                    </div>

                    <div id="cancellationResults" class="report-results-area">
                        <p class="initial-msg"><i class="fas fa-info-circle"></i> Muestra los productos cancelados y el costo total.</p>
                    </div>
                </div>


                <div id="service-metrics" class="report-tab-content" style="display: none;">
                    <h4>Personas Atendidas</h4>
                    
                    <div class="filter-controls">
                        <div class="form-group">
                            <label for="serviceStartDate">Fecha Inicio</label>
                            <input type="date" id="serviceStartDate" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="serviceEndDate">Fecha Fin</label>
                            <input type="date" id="serviceEndDate" class="form-control">
                        </div>
                         <div class="form-group">
                            <label for="serviceServerSelect">Filtrar por Mesero</label>
                            <select id="serviceServerSelect" class="form-control">
                                 <option value="">Todos los Meseros</option>
                            </select>
                        </div>
                        <button class="action-btn primary-btn" id="btnRunServiceMetrics"><i class="fas fa-user-friends"></i> Generar Métricas</button>
                    </div>

                    <div id="serviceMetricsResults" class="report-results-area">
                        <p class="initial-msg"><i class="fas fa-info-circle"></i> Muestra el número de clientes servidos por período.</p>
                    </div>
                </div>
                
                <div id="rotation-metrics" class="report-tab-content" style="display: none;">
                    <h4>Tiempo Promedio de Servicio</h4>
                    <div class="filter-controls">
                        <div class="form-group">
                            <label for="rotationStartDate">Fecha Inicio</label>
                            <input type="date" id="rotationStartDate" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="rotationEndDate">Fecha Fin</label>
                            <input type="date" id="rotationEndDate" class="form-control">
                        </div>
                        <button class="action-btn primary-btn" id="btnRunRotationReport"><i class="fas fa-hourglass-half"></i> Generar Reporte</button>
                    </div>
                     <div id="rotationResults" class="report-results-area">
                        <p class="initial-msg"><i class="fas fa-info-circle"></i> Analiza el tiempo promedio que una mesa está ocupada.</p>
                    </div>
                </div>

            </div>
        </div>
    </main>
</div> 

<script src="/src/js/session_interceptor.js"></script>
<script src="/src/js/manager_reports.js"></script> 

</body>
</html>
