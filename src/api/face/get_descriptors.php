<?php
// /src/api/face/get_descriptors.php
// Devuelve todos los descriptores faciales activos para que el cliente JS haga el matching.
// Esta ruta NO requiere sesión iniciada (es pública para login facial y checador).
// Solo devuelve id, nombre y descriptor — sin datos sensibles.

require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/db_connection.php';
header('Content-Type: application/json');

try {
    $sql = "SELECT id, name, face_descriptor FROM users
            WHERE status = 'ACTIVO' AND face_descriptor IS NOT NULL";
    $result = $conn->query($sql);

    $users = [];
    while ($row = $result->fetch_assoc()) {
        $users[] = [
            'id'         => (int)$row['id'],
            'name'       => $row['name'],
            'descriptor' => json_decode($row['face_descriptor'], true)
        ];
    }

    echo json_encode(['success' => true, 'users' => $users]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
