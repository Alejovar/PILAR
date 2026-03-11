<?php
session_start();

// Verifica si el usuario ha iniciado sesión; de lo contrario, lo redirige al login
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: index.html");
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | KitchenLink</title>
</head>
<body>
    <h1>¡Bienvenido a KitchenLink!</h1>
    <p>Has iniciado sesión exitosamente como: **<?php echo htmlspecialchars($_SESSION['username']); ?>**.</p>
    <p>Tu rol es: **<?php echo htmlspecialchars($_SESSION['rol']); ?>**.</p>

    <a href="/src/php/logout.php">Cerrar sesión</a>
</body>
</html>
