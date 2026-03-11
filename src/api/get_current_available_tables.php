<?php
// src/api/get_current_available_tables.php
require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/security/check_session.php';
// Le decimos al cliente que la respuesta es JSON.
header('Content-Type: application/json');
// Nos conectamos a la base de datos.
// Usamos un try-catch por si algo falla con la base de datos. Es una buena práctica.
try {
    // Esta es una consulta súper simple:
    // "Dame todas las mesas que ahora mismo estén disponibles y ordénalas por nombre".
    $query = "SELECT id, table_name FROM tables WHERE status = 'disponible' ORDER BY table_name";
    $result = $conn->query($query);

    // Guardamos los resultados en un array para después mandarlos.
    $tables = [];
    while ($row = $result->fetch_assoc()) {
        $tables[] = $row;
    }

    // Enviamos la respuesta con éxito y la lista de mesas.
    echo json_encode(['success' => true, 'tables' => $tables]);

} catch (Exception $e) {
    // Si el 'try' falla, caemos aquí y mandamos un error de servidor.
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error del servidor: ' . $e->getMessage()]);
}

// Cerramos la conexión. ¡Listo!
$conn->close();
?>
