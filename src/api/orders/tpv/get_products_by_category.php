<?php
// =====================================================
// get_products_by_category.php - API para TPV (MySQLi)
// =====================================================

require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/security/check_session.php';
header('Content-Type: application/json; charset=utf-8');

// -----------------------------------------------------
// 🔐 Validación de sesión
// -----------------------------------------------------
if (!isset($_SESSION['user_id']) || ($_SESSION['rol_id'] != 2 && $_SESSION['rol_id'] != 1)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado.'], JSON_UNESCAPED_UNICODE);
    exit();
}

// -----------------------------------------------------
// 🔎 Validación de parámetro
// -----------------------------------------------------
$category_id = filter_input(INPUT_GET, 'category_id', FILTER_VALIDATE_INT);
if (!$category_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID de categoría inválido.'], JSON_UNESCAPED_UNICODE);
    exit();
}

// -----------------------------------------------------
// 🔌 Conexión a la base de datos (MySQLi)
// -----------------------------------------------------
$absolute_conn_path = $_SERVER['DOCUMENT_ROOT'] . '/src/php/db_connection.php';

if (!file_exists($absolute_conn_path)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'No se encontró el archivo de conexión.'], JSON_UNESCAPED_UNICODE);
    exit();
}

require_once $absolute_conn_path;

// Verificar conexión
if (!isset($conn) || !$conn instanceof mysqli || $conn->connect_errno) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error de conexión con el servidor.'], JSON_UNESCAPED_UNICODE);
    exit();
}

// -----------------------------------------------------
// 📦 Importar el modelo del menú
// -----------------------------------------------------
$menu_model_path = __DIR__ . '/MenuModel.php'; // ✅ MISMA CARPETA
if (!file_exists($menu_model_path)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'No se encontró MenuModel.php.'], JSON_UNESCAPED_UNICODE);
    exit();
}
require_once $menu_model_path;

// -----------------------------------------------------
// 🧠 Ejecutar consulta
// -----------------------------------------------------
try {
    $menuModel = new MenuModel($conn);
    $products = $menuModel->getProductsByCategory($category_id);

    echo json_encode([
        'success' => true,
        'products' => $products
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log("DB Error fetching products: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error al consultar productos: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
