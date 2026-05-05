<?php
// /src/api/orders/get_server_profile.php
// Devuelve el perfil de estadísticas del mesero autenticado (turno actual).

require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/security/check_session.php';
header('Content-Type: application/json; charset=utf-8');

$response = ['success' => false, 'message' => 'Error al obtener perfil.'];

// Solo meseros (rol 2) pueden consultar su propio perfil
if (!isset($_SESSION['rol_id']) || $_SESSION['rol_id'] != 2) {
    http_response_code(403);
    $response['message'] = 'Acceso no autorizado.';
    echo json_encode($response);
    exit;
}

$user_id   = $_SESSION['user_id']  ?? null;
$user_name = $_SESSION['user_name'] ?? null;

if (!$user_id) {
    http_response_code(400);
    $response['message'] = 'Sesión inválida.';
    echo json_encode($response);
    exit;
}

try {
    // 1. Buscar el turno abierto
    $sql_shift = "SELECT shift_id, start_time FROM cash_shifts WHERE status = 'OPEN' ORDER BY start_time DESC LIMIT 1";
    $stmt_shift = $conn->prepare($sql_shift);
    $stmt_shift->execute();
    $shift = $stmt_shift->get_result()->fetch_assoc();
    $stmt_shift->close();

    if (!$shift) {
        throw new Exception('No hay un turno abierto actualmente.');
    }

    $start_time = $shift['start_time'];

        // 2. Estadísticas del turno actual para este mesero (por ID para evitar desajustes de nombre)
    $sql_stats = "SELECT
                                        COUNT(sh.sale_id)       AS cuentas_cerradas,
                                        SUM(sh.grand_total)     AS venta_total,
                                        SUM(sh.tip_amount_card) AS propinas_tarjeta,
                                        SUM(sh.discount_amount) AS descuentos_aplicados,
                                        AVG(sh.grand_total)     AS ticket_promedio,
                                        SUM(sh.client_count)    AS clientes_atendidos
                                    FROM sales_history sh
                                    JOIN orders o ON o.order_id = sh.original_order_id
                                    WHERE sh.payment_time >= ?
                                        AND o.server_id = ?";

    $stmt_stats = $conn->prepare($sql_stats);
    $stmt_stats->bind_param("si", $start_time, $user_id);
    $stmt_stats->execute();
    $stats = $stmt_stats->get_result()->fetch_assoc();
    $stmt_stats->close();

        // 3. Mesas actualmente abiertas asignadas a este mesero
        $sql_active = "SELECT COUNT(*) AS mesas_abiertas
                                     FROM restaurant_tables
                                     WHERE assigned_server_id = ?
                                         AND pre_bill_status = 'ACTIVE'";
    $stmt_active = $conn->prepare($sql_active);
    $stmt_active->bind_param("i", $user_id);
    $stmt_active->execute();
    $active = $stmt_active->get_result()->fetch_assoc();
    $stmt_active->close();

    $response['success']             = true;
    $response['server_name']         = htmlspecialchars($user_name ?: ('Mesero #' . $user_id));
    $response['shift_start']         = $start_time;
    $response['cuentas_cerradas']    = (int)($stats['cuentas_cerradas']    ?? 0);
    $response['venta_total']         = (float)($stats['venta_total']        ?? 0);
    $response['propinas_tarjeta']    = (float)($stats['propinas_tarjeta']   ?? 0);
    $response['descuentos_aplicados']= (float)($stats['descuentos_aplicados']?? 0);
    $response['ticket_promedio']     = (float)($stats['ticket_promedio']    ?? 0);
    $response['clientes_atendidos']  = (int)($stats['clientes_atendidos']   ?? 0);
    $response['mesas_abiertas']      = (int)($active['mesas_abiertas']      ?? 0);

} catch (Throwable $e) {
    http_response_code(500);
    $response['message'] = $e->getMessage();
}

if (isset($conn)) $conn->close();
echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit;
?>