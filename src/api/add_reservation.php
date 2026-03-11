<?php
//El propósito de este archivo es servir como un endpoint de API para crear una nueva reservación.
// Recibe datos de un formulario mediante POST, los valida rigurosamente en el servidor y, si todo es correcto,
// los inserta en la base de datos de manera segura y atómica (usando una transacción).

// --- CONFIGURACIÓN INICIAL Y DE RESPUESTA
require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/security/check_session.php';
// Se especifica que la respuesta de este script será en formato JSON.
// Esto es fundamental para que el cliente (JavaScript en el navegador) sepa cómo interpretar los datos que recibe.
header('Content-Type: application/json');

// Inicia o reanuda una sesión existente. Es un requisito para poder acceder a variables de sesión
// como $_SESSION['user_id'], que usamos para identificar al usuario autenticado.
session_start();

// Incluye el archivo de conexión a la base de datos. Se usa 'require' en lugar de 'include'
// porque la conexión a la BD es indispensable para el funcionamiento. Si el archivo no existe,
// 'require' detendrá la ejecución con un error fatal, lo cual es el comportamiento deseado.
// __DIR__ asegura que la ruta al archivo sea siempre correcta, sin importar desde dónde se ejecute el script.
require __DIR__ . '/../php/db_connection.php';

// --- VERIFICACIONES INICIALES DE SEGURIDAD Y MÉTODO ---

// PRIMERA CAPA DE SEGURIDAD: AUTORIZACIÓN.
// Se verifica si el 'user_id' existe en la sesión. Si no, significa que no hay un usuario logueado.
// En este caso, se niega el acceso para evitar que usuarios no autenticados puedan crear reservaciones.
if (!isset($_SESSION['user_id'])) {
    // Se envía el código de estado HTTP 401 'Unauthorized', que es el estándar para indicar falta de autenticación.
    http_response_code(401);
    // Se devuelve un mensaje de error claro en formato JSON.
    echo json_encode(['success' => false, 'message' => 'Acceso denegado. Tu sesión pudo haber expirado.']);
    // Se termina la ejecución del script para no procesar nada más.
    exit();
}

// SEGUNDA CAPA DE SEGURIDAD: MÉTODO HTTP.
// Se asegura que la solicitud se haya realizado usando el método POST. Para operaciones que crean o modifican
// datos en el servidor (como esta), se debe usar POST por convención y seguridad, para evitar que la operación
// se active accidentalmente (por ejemplo, al visitar una URL).
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // Se envía el código 405 'Method Not Allowed', indicando que el recurso no soporta el método HTTP utilizado.
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
    exit();
}

// --- RECOLECCIÓN Y LIMPIEZA DE DATOS DEL FORMULARIO ---

// Se recuperan los datos enviados por el cliente. El operador '??' (null coalescing) es una forma segura
// de asi El propógnar un valor por defecto ('[]' o '') si la variable $_POST no contiene la clave esperada.
// Esto previene errores de "Índice no definido" y hace el código más robusto.
$table_ids = $_POST['table_ids'] ?? [];
$hostess_id = $_SESSION['user_id']; // El ID del hostess se obtiene de la sesión, no del cliente, por seguridad.

// La función 'trim()' se utiliza para eliminar espacios en blanco al inicio y al final de los datos de texto.
// Esto es una buena práctica para limpiar la entrada del usuario y evitar problemas de validación o datos sucios en la BD.
$customer_name = trim($_POST['customer_name'] ?? '');
$reservation_date = $_POST['reservation_date'] ?? '';
$reservation_time = $_POST['reservation_time'] ?? '';
$number_of_people = trim($_POST['number_of_people'] ?? '');
$customer_phone = trim($_POST['customer_phone'] ?? '');
$special_requests = trim($_POST['special_requests'] ?? '');

// --- BLOQUE DE VALIDACIÓN DE DATOS EN EL SERVIDOR ---
// PROFESOR: Este bloque es crítico. NUNCA se debe confiar en la validación del cliente (JavaScript),
// ya que puede ser fácilmente manipulada. La validación en el servidor es la única fuente de verdad
// para garantizar la integridad y seguridad de los datos.

// Validación de campos obligatorios.
if (empty($table_ids) || empty($customer_name) || empty($reservation_date) || empty($reservation_time) || empty($number_of_people)) {
    // Se usa el código 400 'Bad Request' para indicar que la solicitud del cliente es incorrecta o está incompleta.
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Faltan datos requeridos (Fecha, Hora, Mesas, Nombre o N° de Personas).']);
    exit();
}

// Validación de formato usando expresiones regulares (preg_match).
// Esto asegura que los datos se ajusten al formato esperado, previniendo inyección de código y datos malformados.
if (!preg_match('/^[a-zA-Z\s]+$/', $customer_name)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'El nombre solo puede contener letras y espacios.']);
    exit();
}
if (!preg_match('/^[0-9]{1,2}$/', $number_of_people) || (int)$number_of_people == 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'El número de personas debe ser entre 1 y 99.']);
    exit();
}
// El teléfono es opcional, por lo que la validación solo se aplica si no está vacío.
if (!empty($customer_phone) && !preg_match('/^[0-9]{1,10}$/', $customer_phone)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'El teléfono debe contener máximo 10 dígitos.']);
    exit();
}

// Validación de la lógica de negocio para fecha y hora.
try {
    // Es crucial definir la zona horaria explícitamente para evitar ambigüedades y errores
    // de cálculo que dependen de la configuración del servidor.
    $timezone = new DateTimeZone('America/Mexico_City');

    // Se crea un objeto DateTime a partir de la fecha y hora proporcionadas.
    // Si el formato es inválido, esto lanzará una Excepción que será capturada por el bloque catch.
    $reservationDateTime = new DateTime($reservation_date . ' ' . $reservation_time, $timezone);
    $now = new DateTime('now', $timezone); // Se obtiene la fecha y hora actual en la misma zona horaria.
    $reservationTime = $reservationDateTime->format('H:i:s');

    // REGLA DE NEGOCIO 1: No se puede reservar en el pasado.
    if ($reservationDateTime < $now) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'No se puede reservar en una fecha y hora pasadas.']);
        exit();
    }

    // REGLA DE NEGOCIO 2: Se valida que la hora de la reservación esté dentro del horario de operación.
    if ($reservationTime < '08:00:00' || $reservationTime > '22:00:00') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Las reservaciones son solo de 8:00 AM a 10:00 PM.']);
        exit();
    }
} catch (Exception $e) {
    // Este bloque 'catch' maneja cualquier error que ocurra al crear los objetos DateTime,
    // lo que usualmente significa que el formato de fecha u hora era incorrecto.
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'El formato de fecha u hora es inválido.']);
    exit();
}

// --- TRANSACCIÓN EN LA BASE DE DATOS ---
// PROFESOR: Se utiliza una transacción para asegurar la ATOMICIDAD de la operación.
// Una reservación implica insertar datos en DOS tablas ('reservations' y 'reservation_tables').
// La transacción garantiza que AMBAS inserciones se completen con éxito. Si alguna falla,
// TODA la operación se revierte (rollback), evitando datos inconsistentes en la base de datos.

$conn->begin_transaction();
try {
    // PASO 1: Insertar el registro principal en la tabla 'reservations'.
    // Se utilizan CONSULTAS PREPARADAS (prepare, bind_param, execute) para prevenir inyecciones SQL.
    // Este es el método más seguro para interactuar con la base de datos.
    $stmt_reservations = $conn->prepare(
        "INSERT INTO reservations (hostess_id, customer_name, customer_phone, reservation_date, reservation_time, number_of_people, special_requests)
         VALUES (?, ?, ?, ?, ?, ?, ?)"
    );
    // Es buena práctica verificar si la preparación de la consulta falló.
    if ($stmt_reservations === false) throw new Exception("Error al preparar la consulta de reservación: " . $conn->error);

    // Se asocian las variables PHP a los parámetros de la consulta. 
    // el tipo de dato de cada variable (i: integer, s: string), lo que añade una capa extra de seguridad.
    $stmt_reservations->bind_param("issssis", $hostess_id, $customer_name, $customer_phone, $reservation_date, $reservation_time, $number_of_people, $special_requests);
    $stmt_reservations->execute();

    // Se obtiene el ID auto-generado de la reservación que acabamos de insertar.
    // Este ID es necesario para enlazar las mesas a esta reservación en la siguiente tabla.
    $reservation_id = $conn->insert_id;
    $stmt_reservations->close(); // Se cierra la consulta preparada para liberar recursos.

    // Si por alguna razón no se generó un ID, lanzamos un error para revertir la transacción.
    if ($reservation_id <= 0) throw new Exception("No se pudo crear el registro de reservación principal.");

    // PASO 2: Insertar una fila en 'reservation_tables' por cada mesa seleccionada.
    $stmt_tables = $conn->prepare("INSERT INTO reservation_tables (reservation_id, table_id) VALUES (?, ?)");
    if ($stmt_tables === false) throw new Exception("Error al preparar la consulta de mesas: " . $conn->error);

    // Se itera sobre el array de IDs de mesas recibidas.
    foreach ($table_ids as $table_id) {
        // En cada iteración, se asocian los IDs y se ejecuta la inserción.
        $stmt_tables->bind_param("ii", $reservation_id, $table_id);
        $stmt_tables->execute();
    }
    $stmt_tables->close();

    // SI LLEGAMOS AQUÍ, TODO SALIÓ BIEN.
    // Se confirma la transacción, haciendo permanentes todos los cambios en la base de datos.
    $conn->commit();
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    // SI OCURRE CUALQUIER ERROR dentro del bloque 'try'...
    // Se revierte la transacción. Esto deshará cualquier inserción que se haya realizado,
    // manteniendo la base de datos en un estado consistente.
    $conn->rollback();
    // Se envía un código 500 'Internal Server Error', ya que el problema ocurrió en el servidor (la BD).
    http_response_code(500);
    // En un entorno de producción, sería mejor registrar $e->getMessage() en un archivo de log y
    // mostrar un mensaje más genérico al usuario.
    echo json_encode(['success' => false, 'message' => 'Error en la base de datos: ' . $e->getMessage()]);
}

// Se cierra la conexión a la base de datos para liberar recursos del servidor.
$conn->close();
?>
