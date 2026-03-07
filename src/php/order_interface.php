<?php
// order_interface.php - VERSIÓN FINAL Y ROBUSTA (Versión B como predeterminada)

// 1. Seguridad (Verifica login/token y obtiene $_SESSION['rol_id'])
require_once $_SERVER['DOCUMENT_ROOT'] . '/KitchenLink/src/php/security/check_session.php';

// 2. Definición del rol
define('MANAGER_ROLE_ID', 1);
define('MESERO_ROLE_ID', 2);

$back_url = '/KitchenLink/src/php/orders.php'; // Por defecto (Mesero)

if (isset($_SESSION['rol_id']) && $_SESSION['rol_id'] == MANAGER_ROLE_ID) {
    $back_url = '/KitchenLink/src/php/manager_dashboard.php'; 
}

// 🔑 VERIFICACIÓN DE ROL: Si no es mesero, redirige.
if ($_SESSION['rol_id'] != MESERO_ROLE_ID && $_SESSION['rol_id'] != MANAGER_ROLE_ID) {
    
    // 💥 CORRECCIÓN CRÍTICA: Destruir la sesión para forzar el logout
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_unset();
        session_destroy();
    }
    
    header('Location: /KitchenLink/index.php?error=acceso_no_mesero');
    exit();
}

// 3. Obtener y validar la mesa
$table_number = filter_input(INPUT_GET, 'table', FILTER_VALIDATE_INT);
if (!$table_number) {
    header('Location: /KitchenLink/src/php/orders.php');
    exit();
}

// 4. Conexión a DB (Ya está abierta por check_session.php, solo verificamos)
if (!isset($conn) || $conn->connect_error) {
    die("Error fatal de conexión a la base de datos."); 
}

// 5. Consulta Estado de la Mesa (para el bloqueo)
$mesa_estado = 'ACTIVE';
$sql_table_status = "SELECT rt.pre_bill_status FROM restaurant_tables rt WHERE rt.table_number = ?";
$stmt_status = $conn->prepare($sql_table_status);
$stmt_status->bind_param("i", $table_number);
$stmt_status->execute();
$status_result = $stmt_status->get_result();
if ($row_status = $status_result->fetch_assoc()) {
    $mesa_estado = $row_status['pre_bill_status']; // 💥 OBTENEMOS EL ESTADO DE BLOQUEO
}
$stmt_status->close();

// 6. Consulta de Categorías
$categories = [];
try {
    $sql_categories = "SELECT category_id, category_name FROM menu_categories ORDER BY display_order ASC";
    $stmt = $conn->prepare($sql_categories);
    $stmt->execute();
    $categories_result = $stmt->get_result();
    $categories = $categories_result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} catch (\Exception $e) {
    error_log("DB Error fetching categories: " . $e->getMessage());
}

// 7. Consulta de items
$existing_items = [];
try {
    $sql_all_items = "
        SELECT
            od.added_at, p.name AS product_name, m.modifier_name,
            od.product_id AS id, od.price_at_order AS price,
            od.special_notes AS comment, od.modifier_id
        FROM orders o
        JOIN restaurant_tables rt ON o.table_id = rt.table_id
        JOIN order_details od ON o.order_id = od.order_id
        JOIN products p ON od.product_id = p.product_id
        LEFT JOIN modifiers m ON od.modifier_id = m.modifier_id
        WHERE rt.table_number = ? AND o.status != 'PAGADA'
        ORDER BY od.added_at ASC, od.detail_id ASC";

    $stmt_items = $conn->prepare($sql_all_items);
    if ($stmt_items === false) {
        throw new \Exception("Error al preparar la consulta de ítems: " . $conn->error);
    }
    
    $stmt_items->bind_param("i", $table_number);
    $stmt_items->execute();
    $items_result = $stmt_items->get_result();

    while ($row = $items_result->fetch_assoc()) {
        $item_name = htmlspecialchars($row['product_name']);
        if (!empty($row['modifier_name'])) {
            $item_name .= " (" . htmlspecialchars($row['modifier_name']) . ")";
        }
        $existing_items[] = [
            'id' => (int)$row['id'],
            'name' => $item_name,
            'price' => (float)$row['price'],
            'comment' => $row['comment'],
            'modifier_id' => $row['modifier_id'] ? (int)$row['modifier_id'] : null,
            'type' => 'product',
            'sentTimestamp' => (new DateTime($row['added_at']))->format(DateTime::ATOM)
        ];
    }
    $stmt_items->close();
} catch (\Exception $e) {
    die("Error fatal al consultar los detalles de la orden: " . $e->getMessage());
}

// 8. Preparar JSON de datos iniciales
$initial_data = [
    'table_status' => $mesa_estado,
    'server_time' => (new DateTime())->format(DateTime::ATOM),
    'items' => $existing_items
];
$initial_order_json = json_encode($initial_data);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ordenando Mesa #<?php echo htmlspecialchars($table_number); ?> | KitchenLink</title>
      <link rel="icon" href="/KitchenLink/src/images/logos/KitchenLink_logo.png" type="image/png" sizes="32x32">
    <link rel="stylesheet" href="/KitchenLink/src/css/tpv.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>

    <div class="tpv-container">
        <header class="tpv-header" style="display: flex; justify-content: space-between; align-items: center;">
            <h2 style="margin: 0;">Mesa Actual: #<?php echo htmlspecialchars($table_number); ?></h2>
            <div id="liveClockContainer"></div>

            <button onclick="registerABClick('B', '<?php echo $back_url; ?>')" style="background-color: #0d6efd; color: white; padding: 10px 24px; border: none; border-radius: 8px; font-weight: bold; font-size: 16px; cursor: pointer; box-shadow: 0 4px 10px rgba(13, 110, 253, 0.4); display: flex; align-items: center; gap: 8px;" id="btn-back-tables">
                <i class="fas fa-arrow-left"></i> Volver a Mesas
            </button>
        </header>

        <div class="tpv-layout">
            <aside class="category-sidebar">
                <h3>Menú</h3>
                <nav id="categoryList">
                    <?php if (!empty($categories)): ?>
                        <?php $first = true; foreach ($categories as $cat): ?>
                            <a href="#" class="category-item <?php echo $first ? 'active' : ''; ?>" data-category-id="<?php echo $cat['category_id']; ?>">
                                <?php echo htmlspecialchars($cat['category_name']); ?>
                            </a>
                        <?php $first = false; endforeach; ?>
                    <?php else: ?>
                        <p>No hay categorías.</p>
                    <?php endif; ?>
                </nav>
            </aside>

            <section class="product-grid-area">
                <div class="search-product-area">
                    <div class="search-input-wrapper">
                        <i class="fas fa-search search-icon"></i>
                        <input type="text" id="productSearchInput" placeholder="Buscar producto por nombre..." autocomplete="off">
                    </div>
                    
                    <div id="searchResultsDropdown" class="search-results-dropdown" style="display:none;"></div>
                </div>
                
                <h2>Productos</h2> 
                <div id="productGrid"><p id="productLoading">Seleccione una categoría.</p></div>
            </section>
            
            <aside class="order-summary-area">
                <h3>Resumen de Orden</h3>
                <div id="orderItems"><p class="text-center">Aún no hay productos.</p></div>
                <div class="order-total">
                    <span>Total:</span>
                    <span id="orderTotal">$0.00</span>
                </div>
                <div class="order-controls">
                    <div class="quantity-control">
                        <label for="quantitySelector">Cantidad:</label>
                        <input type="number" id="quantitySelector" value="1" min="1" max="20">
                    </div>
                    <button id="addTimeBtn" class="btn btn-secondary"><i class="fas fa-clock"></i> Añadir Tiempo</button>
                </div>
                <div class="order-actions">
                    <button class="btn btn-primary" id="sendOrderBtn">Enviar</button>
                </div>
                <div id="lockMessageContainer"></div> 
            </aside>
        </div>
    </div>
    
    <div id="modifierModal" class="modal-overlay" style="display:none;">
        <div class="modal-content">
            <span class="close-btn">&times;</span>
            <h3 id="modalProductName"></h3>
            <p>Seleccione <span id="modifierGroupName">la opción</span> requerida:</p>
            <div id="modifierOptions" class="modifier-options-grid"></div>
            <button id="addModifiedItemBtn" class="btn btn-primary">Añadir al Pedido</button>
        </div>
    </div>

    <div id="commentModal" class="modal-overlay" style="display:none;">
        <div class="modal-content">
            <span class="close-btn">&times;</span>
            <h3>Añadir Comentario</h3>
            <p id="commentModalItemName"></p>
            <textarea id="commentInput" placeholder="Ej: Sin cebolla, término medio..." rows="4" maxlength="255"></textarea>
            <input type="hidden" id="commentItemIndex">
            <div class="modal-actions">
                <button id="cancelCommentBtn" class="btn btn-secondary">Cancelar</button>
                <button id="saveCommentBtn" class="btn btn-primary">Guardar</button>
            </div>
        </div>
    </div>
    
    <script id="initialOrderData" type="application/json"><?php echo $initial_order_json; ?></script>
    <script src="/KitchenLink/src/js/session_interceptor.js"></script>
    <script src="/KitchenLink/src/js/tpv.js"></script> 

    <script>
        // Ocultar el Toast automáticamente después de 3.5 segundos
        setTimeout(() => {
            const toast = document.getElementById('ab-toast');
            if(toast) {
                toast.style.opacity = '0'; // Efecto de desvanecimiento
                setTimeout(() => toast.remove(), 500); // Lo borra del HTML al terminar
            }
        }, 3500);

        // Guardamos el tiempo exacto en que la interfaz terminó de cargar
        const viewStartTime = Date.now();

        function registerABClick(version, targetUrl) {
            const clickTime = Date.now();
            const elapsedSeconds = ((clickTime - viewStartTime) / 1000).toFixed(2);
            
            console.log(`[Métrica] Navegación: ${elapsedSeconds} segundos`);

            let formData = new FormData();
            formData.append('version', version);
            formData.append('time', elapsedSeconds);

            // Enviamos la data al archivo PHP para guardarlo (por si sigues recolectando métricas)
            fetch('/KitchenLink/test/save_metric.php', {
                method: 'POST',
                body: formData
            }).then(() => {
                window.location.href = targetUrl;
            }).catch(() => {
                window.location.href = targetUrl;
            });
        }
    </script>
</body>
</html>