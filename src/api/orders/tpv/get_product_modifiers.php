<?php
// get_product_modifiers.php - API para obtener opciones de guisos/sabores

require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/security/check_session.php';
// Evitar warnings/notices que rompan JSON
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

// Forzar UTF-8 para JSON
header('Content-Type: application/json; charset=utf-8');

// --- Verificación de sesión y rol ---
if (!isset($_SESSION['user_id']) || ($_SESSION['rol_id'] != 2 && $_SESSION['rol_id'] != 1)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado.'], JSON_UNESCAPED_UNICODE);
    exit();
}

// --- Validar input ---
$group_id = filter_input(INPUT_GET, 'group_id', FILTER_VALIDATE_INT);
if (!$group_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID de grupo de modificador inválido.'], JSON_UNESCAPED_UNICODE);
    exit();
}

// --- Cargar dependencias ---
require '../../../php/db_connection.php'; 
require 'MenuModel.php';      

try {
    $menuModel = new MenuModel($conn);
    $data = $menuModel->getModifiersByGroup($group_id); 

    // Forzar valores por defecto si algo falla
    $modifiers = $data['modifiers'] ?? [];
    $group_name = $data['group_name'] ?? 'Opción';

    // Retornar JSON seguro
    echo json_encode([
        'success' => true, 
        'modifiers' => $modifiers,
        'group_name' => $group_name
    ], JSON_UNESCAPED_UNICODE);
    exit();

} catch (\Exception $e) {
    error_log("DB Error fetching modifiers: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error al consultar modificadores en la base de datos.'
    ], JSON_UNESCAPED_UNICODE);
    exit();
}
?>
