<?php
// Este archivo solo sirve para probar la conexion con la base de datos, por si llega haber algun error
// Incluye el archivo de conexión a la base de datos
include 'db_connection.php';

// Si el script llega a esta línea, la conexión fue exitosa.
echo "¡Conexión exitosa!";

$conn->close();
?>
