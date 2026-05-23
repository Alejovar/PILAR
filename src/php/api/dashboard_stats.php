<?php
// /src/php/api/dashboard_stats.php
header('Content-Type: application/json');
require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/security/check_session_api.php';

$hoy = date('Y-m-d');

$plantas   = $conn->query("SELECT COUNT(*) FROM plantas WHERE activa = 1")->fetch_row()[0];
$empleados = $conn->query("SELECT COUNT(*) FROM empleados WHERE activo = 1")->fetch_row()[0];
$checadas  = $conn->query("SELECT COUNT(*) FROM registros_asistencia WHERE DATE(fecha_hora) = '{$hoy}'")->fetch_row()[0];

echo json_encode([
    'ok'       => true,
    'plantas'  => (int)$plantas,
    'empleados'=> (int)$empleados,
    'checadas' => (int)$checadas,
]);
