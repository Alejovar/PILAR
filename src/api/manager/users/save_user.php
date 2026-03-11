<?php
// /src/api/manager/users/save_user.php

require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/security/check_session.php';
header('Content-Type: application/json');
require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/db_connection.php';

if (!isset($_SESSION['rol_id']) || $_SESSION['rol_id'] != 1) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']); exit;
}

$data = json_decode(file_get_contents('php://input'), true);

$id = $data['id'] ?? null;
$name = trim($data['name']);
$user = trim($data['user']);
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
            $sql = "UPDATE users SET name=?, user=?, rol_id=?, password=? WHERE id=?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssisi", $name, $user, $role_id, $hash, $id);
        } else {
            $sql = "UPDATE users SET name=?, user=?, rol_id=? WHERE id=?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssii", $name, $user, $role_id, $id);
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
        
        // 💡 CAMBIO: No insertamos 'status', dejamos que la BD ponga 'ACTIVO' por defecto
        $sql = "INSERT INTO users (name, user, password, rol_id) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssi", $name, $user, $hash, $role_id);
        
        if (!$stmt->execute()) throw new Exception("Error al crear usuario.");
    }

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
