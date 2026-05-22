<?php
// /src/api/manager/users/save_user.php

require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/security/check_session.php';
header('Content-Type: application/json');
require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/db_connection.php';

if (!isset($_SESSION['rol_id']) || $_SESSION['rol_id'] != 1) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']); exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!is_array($data)) {
    echo json_encode(['success' => false, 'message' => 'JSON invalido.']);
    exit;
}

$id = $data['id'] ?? null;
$name = trim($data['name']);
$user = trim($data['user']);
$nss = trim($data['nss'] ?? '');
$plant = trim($data['plant'] ?? '');
$tax_rate = (float)($data['tax_rate'] ?? 0);
$salary_per_day = (float)($data['salary_per_day'] ?? 0);
$overtime_rate = (float)($data['overtime_rate'] ?? 0);
$role_id = intval($data['role_id']);
$password = $data['password'];

if (empty($name) || empty($user) || empty($role_id)) {
    echo json_encode(['success' => false, 'message' => 'Faltan datos obligatorios']); exit;
}

try {
    if (!empty($id)) {
        // --- UPDATE ---
        $stmt = $conn->prepare("SELECT id FROM users WHERE user = ? AND id != ?");
        $stmt->bind_param("si", $user, $id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            throw new Exception("El nombre de usuario '$user' ya está en uso.");
        }
        $stmt->close();

        if (!empty($password)) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $sql = "UPDATE users SET name=?, user=?, nss=?, plant=?, tax_rate=?, salary_per_day=?, overtime_rate=?, rol_id=?, password=? WHERE id=?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssssdddisi", $name, $user, $nss, $plant, $tax_rate, $salary_per_day, $overtime_rate, $role_id, $hash, $id);
        } else {
            $sql = "UPDATE users SET name=?, user=?, nss=?, plant=?, tax_rate=?, salary_per_day=?, overtime_rate=?, rol_id=? WHERE id=?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssssdddii", $name, $user, $nss, $plant, $tax_rate, $salary_per_day, $overtime_rate, $role_id, $id);
        }
        $stmt->execute();

    } else {
        // --- INSERT ---
        if (empty($password)) throw new Exception("La contraseña es obligatoria.");

        $stmt = $conn->prepare("SELECT id FROM users WHERE user = ?");
        $stmt->bind_param("s", $user);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            throw new Exception("El usuario '$user' ya existe.");
        }
        $stmt->close();

        $hash = password_hash($password, PASSWORD_DEFAULT);
        
        $sql = "INSERT INTO users (name, user, password, nss, plant, tax_rate, salary_per_day, overtime_rate, rol_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssssdddi", $name, $user, $hash, $nss, $plant, $tax_rate, $salary_per_day, $overtime_rate, $role_id);
        
        if (!$stmt->execute()) throw new Exception("Error al crear usuario.");
    }

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    $message = $e->getMessage();
    if (stripos($message, "Field 'id' doesn't have a default value") !== false) {
        $message = "La tabla users requiere AUTO_INCREMENT en id.";
    }
    echo json_encode(['success' => false, 'message' => $message]);
}
?>
