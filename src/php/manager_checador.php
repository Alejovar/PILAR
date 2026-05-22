<?php
// /src/php/manager_checador.php
// Vista del Gerente para revisar asistencia de empleados.

require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/security/check_session.php';

define('MGR_CHK_ROLE_ID', 1);

if (!isset($_SESSION['rol_id']) || $_SESSION['rol_id'] != MGR_CHK_ROLE_ID) {
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
    <title>Checador de Asistencia | KitchenLink</title>
    <link rel="icon" href="/src/images/logos/KitchenLink_logo.png" type="image/png" sizes="32x32">
    <link rel="stylesheet" href="/src/css/manager_users.css">
    <link rel="stylesheet" href="/src/css/login_facial.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

    <style>
        .summary-cards {
            display: flex;
            gap: 16px;
            margin-bottom: 24px;
            flex-wrap: wrap;
        }
        .summary-card {
            background: #fff;
            border-radius: 12px;
            padding: 18px 24px;
            min-width: 160px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.07);
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .summary-card .label { font-size: 12px; color: #888; }
        .summary-card .value { font-size: 26px; font-weight: 800; color: #5a2dfc; }
        .summary-card.entrada .value { color: #27ae60; }
        .summary-card.salida  .value { color: #e74c3c; }

        .export-btn {
            background: none;
            border: 1px solid #ccc;
            border-radius: 8px;
            padding: 8px 16px;
            font-size: 12px;
            cursor: pointer;
            color: #555;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .export-btn:hover { background: #f5f5f5; }

        .no-records {
            text-align: center;
            padding: 40px;
            color: #aaa;
            font-size: 14px;
        }
    </style>
</head>
<body>
<div class="main-container">
    <aside class="sidebar">
        <div>
            <h2>Administración</h2>
            <ul>
                <li><a href="manager_dashboard.php"><i class="fas fa-th-large"></i> Inicio</a></li>
                <li><a href="manager_users.php"><i class="fas fa-users-cog"></i> Usuarios</a></li>
                <li><a href="manager_checador.php" class="active"><i class="fas fa-chart-line"></i> Reportes de asistencia</a></li>
                <li><a href="/checador.php"><i class="fas fa-mobile-alt"></i> Checador móvil</a></li>
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
            <div id="liveClockContainer" class="clock-widget" style="font-size:1.1rem;margin-left:auto;">--:--:--</div>
        </div>

        <h1 class="page-title">Checador de Asistencia</h1>

        <!-- FILTROS -->
        <div class="mgr-attendance-filter">
            <div>
                <label style="font-size:12px;color:#666;display:block;margin-bottom:4px;">Empleado</label>
                <select id="filterEmployee">
                    <option value="">Todos los empleados</option>
                </select>
            </div>
            <div>
                <label style="font-size:12px;color:#666;display:block;margin-bottom:4px;">Desde</label>
                <input type="date" id="filterDateFrom">
            </div>
            <div>
                <label style="font-size:12px;color:#666;display:block;margin-bottom:4px;">Hasta</label>
                <input type="date" id="filterDateTo">
            </div>
            <button id="btnFilter"><i class="fas fa-search"></i> Filtrar</button>
            <button class="export-btn" id="btnExportCSV"><i class="fas fa-file-csv"></i> Exportar CSV</button>
        </div>

        <!-- RESUMEN -->
        <div class="summary-cards">
            <div class="summary-card">
                <span class="label">Total registros</span>
                <span class="value" id="sumTotal">—</span>
            </div>
            <div class="summary-card entrada">
                <span class="label">Entradas</span>
                <span class="value" id="sumEntradas">—</span>
            </div>
            <div class="summary-card salida">
                <span class="label">Salidas</span>
                <span class="value" id="sumSalidas">—</span>
            </div>
            <div class="summary-card">
                <span class="label">Empleados distintos</span>
                <span class="value" id="sumEmpleados">—</span>
            </div>
            <div class="summary-card">
                <span class="label">Retardos</span>
                <span class="value" id="sumRetardos">—</span>
            </div>
            <div class="summary-card">
                <span class="label">Permisos usados</span>
                <span class="value" id="sumPermisos">—</span>
            </div>
            <div class="summary-card">
                <span class="label">Horas extra</span>
                <span class="value" id="sumHorasExtra">—</span>
            </div>
        </div>

        <!-- TABLA -->
        <div class="users-container">
            <table class="attendance-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Empleado</th>
                        <th>NSS</th>
                        <th>Planta</th>
                        <th>Rol</th>
                        <th>Tipo</th>
                        <th>Estado</th>
                        <th>Fecha</th>
                        <th>Hora</th>
                        <th>Método</th>
                        <th>Retardo</th>
                        <th>Horas extra</th>
                        <th>Permiso</th>
                        <th>Comentario</th>
                    </tr>
                </thead>
                <tbody id="attendanceTableBody">
                    <tr><td colspan="14" class="no-records">Aplica los filtros para ver registros.</td></tr>
                </tbody>
            </table>
        </div>
    </main>
</div>

<script src="/src/js/session_interceptor.js"></script>
<script src="/src/js/manager_checador.js"></script>
</body>
</html>
