<?php
// /src/php/api/empleados/save_empleado.php
header('Content-Type: application/json');
require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/security/check_session_api.php';

function log_emp(string $msg): void { error_log('[save_empleado] ' . $msg); }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok'=>false,'msg'=>'Método no permitido.']); exit();
}

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    echo json_encode(['ok'=>false,'msg'=>'Petición inválida.']); exit();
}

$id              = intval($data['id']               ?? 0);
$numero_empleado = trim($data['numero_empleado']    ?? '');
$nombre          = trim($data['nombre']             ?? '');
$apellido_pat    = trim($data['apellido_paterno']   ?? '');
$apellido_mat    = trim($data['apellido_materno']   ?? '');
$email           = trim($data['email']              ?? '');
$rfc             = strtoupper(trim($data['rfc']     ?? ''));
$curp            = strtoupper(trim($data['curp']    ?? ''));
$puesto_id       = intval($data['puesto_id']        ?? 0);
$activo          = isset($data['activo']) ? (int)(bool)$data['activo'] : 1;

// Plantas: array de IDs. El primero es la principal.
$plantas_ids = array_filter(array_map('intval', (array)($data['plantas_ids'] ?? [])));
$plantas_ids = array_values(array_unique($plantas_ids));

if (!$numero_empleado || !$nombre || !$apellido_pat || !$puesto_id || empty($plantas_ids)) {
    echo json_encode(['ok'=>false,'msg'=>'Campos obligatorios: NSS, nombre, apellido paterno, puesto y al menos una planta.']);
    exit();
}

// Planta principal = primera del array
$planta_principal = $plantas_ids[0];

// Asegurar columnas rfc/curp
foreach (['rfc'=>'VARCHAR(15)','curp'=>'VARCHAR(20)'] as $col => $def) {
    $r = $conn->query("SHOW COLUMNS FROM empleados LIKE '{$col}'");
    if (!$r || !$r->num_rows) {
        $conn->query("ALTER TABLE empleados ADD COLUMN {$col} {$def} AFTER email");
    }
}

// Asegurar tabla pivote
$conn->query("
    CREATE TABLE IF NOT EXISTS empleado_plantas (
        empleado_id  INT UNSIGNED NOT NULL,
        planta_id    INT UNSIGNED NOT NULL,
        es_principal BOOLEAN      NOT NULL DEFAULT FALSE,
        created_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (empleado_id, planta_id),
        FOREIGN KEY (empleado_id) REFERENCES empleados(id) ON DELETE CASCADE,
        FOREIGN KEY (planta_id)   REFERENCES plantas(id)   ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

$conn->begin_transaction();
try {
    if ($id) {
        $stmt = $conn->prepare("
            UPDATE empleados
            SET numero_empleado=?, nombre=?, apellido_paterno=?, apellido_materno=?,
                email=?, rfc=?, curp=?, puesto_id=?, planta_id=?, activo=?
            WHERE id=?
        ");
        $stmt->bind_param('sssssssiiii',
            $numero_empleado, $nombre, $apellido_pat, $apellido_mat,
            $email, $rfc, $curp, $puesto_id, $planta_principal, $activo, $id
        );
        $stmt->execute();
        $stmt->close();
        $empId = $id;
    } else {
        $stmt = $conn->prepare("
            INSERT INTO empleados
                (numero_empleado, nombre, apellido_paterno, apellido_materno,
                 email, rfc, curp, puesto_id, planta_id, activo)
            VALUES (?,?,?,?,?,?,?,?,?,?)
        ");
        $stmt->bind_param('sssssssiii',
            $numero_empleado, $nombre, $apellido_pat, $apellido_mat,
            $email, $rfc, $curp, $puesto_id, $planta_principal, $activo
        );
        $stmt->execute();
        $stmt->close();
        $empId = $conn->insert_id;
    }

    // Sincronizar tabla pivote: borrar las actuales e insertar las nuevas
    $del = $conn->prepare("DELETE FROM empleado_plantas WHERE empleado_id = ?");
    $del->bind_param('i', $empId);
    $del->execute();
    $del->close();

    $ins = $conn->prepare("
        INSERT INTO empleado_plantas (empleado_id, planta_id, es_principal)
        VALUES (?, ?, ?)
    ");
    foreach ($plantas_ids as $i => $pid) {
        $esPrincipal = ($i === 0) ? 1 : 0;
        $ins->bind_param('iii', $empId, $pid, $esPrincipal);
        $ins->execute();
    }
    $ins->close();

    $conn->commit();
    echo json_encode(['ok'=>true, 'id'=>$empId]);

} catch (Exception $e) {
    $conn->rollback();
    log_emp('Error: ' . $e->getMessage());
    $err = $e->getMessage();
    if (str_contains($err, 'Duplicate')) {
        echo json_encode(['ok'=>false,'msg'=>'El NSS ya está registrado.']);
    } else {
        echo json_encode(['ok'=>false,'msg'=>'Error al guardar: ' . $err]);
    }
}