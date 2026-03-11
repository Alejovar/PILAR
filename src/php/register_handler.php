<?php
// Clase AUN NO DISPONIBLE es la logica para registrar nuevos usuarios, hecha solo para facilitar el registrar
// nuevo usuario, proximamente disponible.
include 'db_connection.php';
session_start(); // 

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Contraseña que se escribió en el campo "Contraseña de administrador"
    $admin_password_input = $_POST['admin_password'];
    $is_authorized = false; // Una bandera para saber si la contraseña es válida

    // 1. Buscamos las contraseñas de TODOS los usuarios con rol de Gerente (rol_id = 1)
    $sql = "SELECT password FROM users WHERE rol_id = 1";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        // 2. Comparamos la contraseña ingresada con cada una de las contraseñas de gerente
        while($row = $result->fetch_assoc()) {
            $hashed_password_from_db = $row['password'];
            
            // Usamos password_verify para comparar
            if (password_verify($admin_password_input, $hashed_password_from_db)) {
                // ¡Coincide! La contraseña es válida.
                $is_authorized = true;
                break; // Salimos del bucle porque ya encontramos una coincidencia
            }
        }
    }

    // 3. Si la bandera es verdadera, procedemos a registrar al nuevo usuario
    if ($is_authorized) {
        // Obtiene los datos del nuevo usuario del formulario
        $user = $_POST['user'];
        $password_nuevo_usuario = $_POST['password'];
        $name = $_POST['name'];
        $role_id = $_POST['role_id'];

        // Encripta la contraseña del NUEVO usuario
        $hashed_password_nuevo = password_hash($password_nuevo_usuario, PASSWORD_DEFAULT);

        // Inserta el nuevo usuario en la base de datos
        $stmt_insert = $conn->prepare("INSERT INTO users (user, password, name, rol_id) VALUES (?, ?, ?, ?)");
        $stmt_insert->bind_param("sssi", $user, $hashed_password_nuevo, $name, $role_id);

        if ($stmt_insert->execute()) {
            echo "Usuario " . htmlspecialchars($username) . " registrado exitosamente.";
        } else {
            echo "Error al registrar el nuevo usuario: " . $stmt_insert->error;
        }
        $stmt_insert->close();

    } else {
        // Si después de revisar a todos los gerentes no hubo coincidencia
        echo "Contraseña de administrador incorrecta.";
    }
}

$conn->close();
?>
