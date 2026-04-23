<?php
// /src/api/manager/waste/waste_api.php
// TASK-02: API de mermas del gerente
// Acciones: get_open_orders | get_order_items | register_waste | get_waste_report

require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/security/check_session.php';
header('Content-Type: application/json; charset=utf-8');

// Solo Gerente (rol_id = 1)
if (!isset($_SESSION['rol_id']) || $_SESSION['rol_id'] != 1) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acceso denegado. Solo gerentes.'], JSON_UNESCAPED_UNICODE);
    exit();
}

try {
    require $_SERVER['DOCUMENT_ROOT'] . '/src/php/db_connection.php';
    if (!$conn) throw new Exception('Error de conexión a la base de datos.');
    $conn->set_charset("utf8mb4");

    $rawData = trim(file_get_contents('php://input'));
    $data    = empty($rawData) ? [] : json_decode($rawData, true, 512, JSON_THROW_ON_ERROR);
    $action  = $data['action'] ?? ($_GET['action'] ?? '');

    switch ($action) {

        // ── Órdenes abiertas (mesas con cuenta activa) ──────────────────────
        case 'get_open_orders':
            $sql = "
                SELECT
                    o.order_id,
                    rt.table_number,
                    u.name   AS server_name,
                    o.order_time,
                    SUM(od.quantity * od.price_at_order) AS total
                FROM orders o
                JOIN restaurant_tables rt ON o.table_id  = rt.table_id
                JOIN users             u  ON o.server_id = u.id
                JOIN order_details     od ON od.order_id  = o.order_id
                WHERE o.status         = 'PENDING'
                  AND od.is_cancelled  = 0
                GROUP BY o.order_id
                ORDER BY o.order_time ASC
            ";
            $result = $conn->query($sql);
            $orders = [];
            while ($row = $result->fetch_assoc()) {
                $orders[] = $row;
            }
            echo json_encode(['success' => true, 'orders' => $orders], JSON_UNESCAPED_UNICODE);
            exit();

        // ── Ítems activos (no cancelados) de una orden ──────────────────────
        case 'get_order_items':
            $order_id = intval($data['order_id'] ?? 0);
            if ($order_id <= 0) throw new Exception('order_id inválido.');

            $stmt = $conn->prepare("
                SELECT o.order_id, rt.table_number, u.name AS server_name, o.order_time
                FROM orders o
                JOIN restaurant_tables rt ON o.table_id  = rt.table_id
                JOIN users             u  ON o.server_id = u.id
                WHERE o.order_id = ? AND o.status = 'PENDING'
            ");
            $stmt->bind_param("i", $order_id);
            $stmt->execute();
            $order = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$order) throw new Exception('Orden no encontrada o ya cerrada.');

            $stmt = $conn->prepare("
                SELECT
                    od.detail_id,
                    p.name            AS product_name,
                    od.quantity,
                    od.price_at_order AS current_price,
                    p.price           AS catalog_price,
                    od.special_notes,
                    m.modifier_name,
                    od.item_status
                FROM order_details od
                JOIN products  p ON od.product_id  = p.product_id
                LEFT JOIN modifiers m ON od.modifier_id = m.modifier_id
                WHERE od.order_id    = ?
                  AND od.is_cancelled = 0
                ORDER BY od.added_at ASC
            ");
            $stmt->bind_param("i", $order_id);
            $stmt->execute();
            $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            echo json_encode([
                'success' => true,
                'order'   => $order,
                'items'   => $items,
            ], JSON_UNESCAPED_UNICODE);
            exit();

        // ── Registrar merma desde cuenta abierta ─────────────────────────────
        case 'register_waste':
            $order_id     = intval($data['order_id']    ?? 0);
            $detail_ids   = $data['detail_ids']  ?? [];
            $waste_reason = $data['waste_reason'] ?? '';
            $notes        = trim($data['notes']   ?? '');

            $allowed_reasons = ['expired', 'kitchen_error', 'waiter_error', 'damaged', 'other'];

            if ($order_id <= 0)                                    throw new Exception('order_id inválido.');
            if (empty($detail_ids))                                throw new Exception('Selecciona al menos un producto.');
            if (!in_array($waste_reason, $allowed_reasons))        throw new Exception('Motivo no válido.');

            $detail_ids = array_values(array_filter(array_map('intval', $detail_ids), fn($id) => $id > 0));
            if (empty($detail_ids)) throw new Exception('IDs de detalle inválidos.');

            $recorded_by  = intval($_SESSION['user_id']);
            $placeholders = implode(',', array_fill(0, count($detail_ids), '?'));
            $id_types     = str_repeat('i', count($detail_ids));

            $conn->begin_transaction();

            // ── Verificar que los ítems pertenezcan a la orden y no estén cancelados ──
            $check_stmt = $conn->prepare(
                "SELECT detail_id, price_at_order, quantity FROM order_details
                 WHERE detail_id IN ($placeholders)
                   AND order_id    = ?
                   AND is_cancelled = 0"
            );
            $check_stmt->bind_param($id_types . 'i', ...[...$detail_ids, $order_id]);
            $check_stmt->execute();
            $found_items = $check_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $check_stmt->close();

            if (count($found_items) !== count($detail_ids)) {
                $conn->rollback();
                throw new Exception('Algunos ítems no se encontraron o ya estaban cancelados.');
            }

            $cancel_note = '[MERMA] ' . $waste_reason . ($notes ? ': ' . $notes : '');

            // ── UPDATE: marcar como merma, precio a 0 para el cliente ──
            $upd_stmt = $conn->prepare("
                UPDATE order_details
                SET
                    is_cancelled        = 1,
                    cancellation_reason = ?,
                    price_at_order      = 0.00,
                    is_waste            = 1,
                    waste_reason        = ?,
                    waste_price         = price_at_order,
                    waste_recorded_by   = ?,
                    waste_recorded_at   = NOW()
                WHERE detail_id IN ($placeholders)
                  AND order_id    = ?
                  AND is_cancelled = 0
            ");
            // tipos: ss + n*i (detail_ids) + ii (recorded_by, order_id)
            $upd_stmt->bind_param('ss' . $id_types . 'ii', ...[$cancel_note, $waste_reason, ...$detail_ids, $recorded_by, $order_id]);
            $upd_stmt->execute();
            $affected = $upd_stmt->affected_rows;
            $upd_stmt->close();

            if ($affected === 0) {
                $conn->rollback();
                throw new Exception('No se pudo actualizar ningún ítem.');
            }

            $conn->commit();

            echo json_encode([
                'success'  => true,
                'message'  => "Se registraron {$affected} merma(s) correctamente. El cargo fue eliminado de la cuenta del cliente.",
                'affected' => $affected,
            ], JSON_UNESCAPED_UNICODE);
            exit();

        // ── Reporte de mermas por fecha ──────────────────────────────────────
        case 'get_waste_report':
            $start = $data['start_date'] ?? null;
            $end   = $data['end_date']   ?? null;
            if (!$start || !$end) throw new Exception('Se requieren fecha_inicio y fecha_fin.');

            // Mermas en órdenes ACTIVAS
            $sql_active = "
                SELECT
                    od.waste_recorded_at AS waste_date,
                    p.name               AS product_name,
                    od.quantity,
                    od.waste_price       AS unit_price,
                    (od.quantity * od.waste_price) AS total_waste_value,
                    od.waste_reason,
                    od.cancellation_reason AS notes,
                    u.name               AS recorded_by,
                    rt.table_number,
                    'open'               AS source
                FROM order_details od
                JOIN products          p  ON od.product_id        = p.product_id
                JOIN orders            o  ON od.order_id          = o.order_id
                JOIN restaurant_tables rt ON o.table_id           = rt.table_id
                LEFT JOIN users        u  ON od.waste_recorded_by = u.id
                WHERE od.is_waste = 1
                  AND od.waste_recorded_at >= ?
                  AND od.waste_recorded_at <  DATE_ADD(?, INTERVAL 1 DAY)
            ";

            // Mermas en órdenes YA COBRADAS
            $sql_history = "
                SELECT
                    sh.payment_time      AS waste_date,
                    shd.product_name,
                    shd.quantity,
                    shd.waste_price      AS unit_price,
                    (shd.quantity * shd.waste_price) AS total_waste_value,
                    shd.waste_reason,
                    NULL                 AS notes,
                    NULL                 AS recorded_by,
                    NULL                 AS table_number,
                    'history'            AS source
                FROM sales_history_details shd
                JOIN sales_history sh ON shd.sale_id = sh.sale_id
                WHERE shd.is_waste = 1
                  AND sh.payment_time >= ?
                  AND sh.payment_time <  DATE_ADD(?, INTERVAL 1 DAY)
            ";

            $records     = [];
            $total_items = 0;
            $total_value = 0;

            foreach ([[$sql_active, $start, $end], [$sql_history, $start, $end]] as [$sql, $s, $e]) {
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ss", $s, $e);
                $stmt->execute();
                $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                $stmt->close();
                foreach ($rows as $row) {
                    $total_items += $row['quantity'];
                    $total_value += floatval($row['total_waste_value']);
                    $records[]    = $row;
                }
            }

            usort($records, fn($a, $b) => strtotime($b['waste_date']) - strtotime($a['waste_date']));

            echo json_encode([
                'success'     => true,
                'records'     => $records,
                'count'       => count($records),
                'total_items' => $total_items,
                'total_value' => number_format($total_value, 2, '.', ''),
            ], JSON_UNESCAPED_UNICODE);
            exit();

        default:
            throw new Exception("Acción '{$action}' no reconocida.");
    }

} catch (JsonException $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'JSON inválido: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}