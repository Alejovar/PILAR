<?php
// /src/php/api/face/get_descriptors.php
// Endpoint PÚBLICO — devuelve descriptores faciales para matching en el cliente.
// Solo expone: id, nombre completo, descriptor. Sin datos sensibles.
header('Content-Type: application/json');
require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/db_connection.php';

try {
    $sql = "
        SELECT e.id,
               CONCAT(e.nombre,' ',e.apellido_paterno,' ',COALESCE(e.apellido_materno,'')) AS nombre,
               e.face_descriptor,
               e.numero_empleado,
               e.planta_id
        FROM   empleados e
        WHERE  e.activo = 1
          AND  e.face_descriptor IS NOT NULL
          AND  e.face_descriptor != ''
    ";

    $result  = $conn->query($sql);
    $empleados = [];

    while ($row = $result->fetch_assoc()) {
        $descriptor = json_decode($row['face_descriptor'], true);
        if (!$descriptor || !is_array($descriptor)) continue;

        $empleados[] = [
            'id'              => (int)$row['id'],
            'nombre'          => trim($row['nombre']),
            'numero_empleado' => $row['numero_empleado'],
            'planta_id'       => (int)$row['planta_id'],
            'descriptor'      => $descriptor,
        ];
    }

    echo json_encode(['ok' => true, 'empleados' => $empleados]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Error interno.']);
}
