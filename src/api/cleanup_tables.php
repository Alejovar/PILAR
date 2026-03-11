<?php
// Ojo: Este script no es para que lo visite un usuario.
// Está pensado para ejecutarse automáticamente cada cierto tiempo (un "cron job").
// Su única misión es hacer limpieza en la base de datos.
require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/security/check_session.php';
// Incluimos la conexión a la base de datos.
// ¡Muy importante para un cron job!
// Nos aseguramos de que el servidor trabaje en la zona horaria correcta
// para que los cálculos con 'NOW()' y fechas sean precisos.
date_default_timezone_set('America/Mexico_City');

// Esta es la consulta de limpieza. Es un "seguro de vida".
// Busca mesas que lleven 'ocupado' por más de 6 horas y las libera.
// Sirve por si alguien olvidó marcar una mesa como 'disponible'.
$sql_cleanup = "UPDATE tables
                SET status = 'disponible', status_changed_at = NULL
                WHERE status = 'ocupado' AND status_changed_at <= NOW() - INTERVAL 6 HOUR";

// Ejecutamos la consulta directamente.
if ($conn->query($sql_cleanup)) {
    // Opcional: Las líneas comentadas servirían para registrar en un log que el script se ejecutó.
    // echo "Limpieza ejecutada con éxito a las " . date('Y-m-d H:i:s');
} else {
    // Opcional: Y esto para guardar un registro si algo sale mal.
    // error_log("Error en el cron job de limpieza: " . $conn->error);
}

// Cerramos la conexión. ¡Trabajo hecho!
$conn->close();
?>
