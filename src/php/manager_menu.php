<?php
// /src/php/manager_menu.php

require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/security/check_session.php';

// --- LÓGICA DE SEGURIDAD CRÍTICA (BLOQUE SOLICITADO) ---
define('MANAGER_ROLE_ID', 1); // 1 = Gerente

// 🔑 Verificación Crítica: Si el rol NO es Gerente (1), se deniega el acceso.
if (!isset($_SESSION['rol_id']) || $_SESSION['rol_id'] != MANAGER_ROLE_ID) {
    
    // 1. Borrar el token de la base de datos
    if (isset($conn) && isset($_SESSION['user_id'])) {
        try {
            $clean_stmt = $conn->prepare("UPDATE users SET session_token = NULL WHERE id = ?");
            $clean_stmt->bind_param("i", $_SESSION['user_id']);
            $clean_stmt->execute();
            $clean_stmt->close();
        } catch (\Throwable $e) {
            // Manejo silencioso de error
        }
    }
    
    // 2. Destruir la sesión PHP
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_unset();
        session_destroy();
    }
    
    header('Location: /index.php?error=acceso_denegado_gerente');
    exit();
}

$userName = htmlspecialchars($_SESSION['user_name'] ?? 'Gerente');
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Menú | KitchenLink</title>
    <link rel="icon" href="/src/images/logos/KitchenLink_logo.png" type="image/png" sizes="32x32">
    
    <link rel="stylesheet" href="/src/css/manager_users.css">
    <link rel="stylesheet" href="/src/css/manager_menu.css">
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>

<div class="main-container">
    <aside class="sidebar">
        <div>
            <h2>Administración</h2>
            <ul>
                <li><a href="manager_dashboard.php"><i class="fas fa-th-large"></i> Monitoreo de Mesas</a></li>
                <li><a href="manager_users.php"><i class="fas fa-users-cog"></i> Usuarios</a></li>
                <li><a href="#" class="active"><i class="fas fa-utensils"></i> Menú y Productos</a></li>
                <li><a href="manager_reports.php"><i class="fas fa-chart-line"></i> Gestión de Reportes</a></li>
                <li><a href="manager_waste.php"><i class="fas fa-trash-alt"></i> Control de Mermas</a></li>
                <li><a href="manager_checador.php"><i class="fas fa-clock"></i> Checador de Asistencia</a></li>

            </ul>
        </div>
        
       <div class="user-info">
            <div class="user-details">
                <i class="fas fa-user-tie user-avatar"></i>
                <div class="user-text-container">
                    <div class="user-name-text"><?php echo $userName; ?></div>
                    <div class="session-status-text">Gerente General</div>
                </div>
            </div>
            <a href="/src/php/logout.php" class="logout-btn" title="Cerrar Sesión">
                <i class="fas fa-sign-out-alt"></i>
            </a>
        </div>
    </aside>

    <main class="content">
        <div class="top-bar">
            <div id="liveClockContainer" class="clock-widget" style="font-size: 1.1rem; margin-left: auto;">--:--:--</div>
        </div>

        <h1 class="page-title">Gestión del Menú</h1>

        <div class="toolbar-actions" style="display: flex; justify-content: space-between; margin-bottom: 20px; gap: 15px;">
            
            <div class="search-wrapper" style="position: relative; flex-grow: 1; max-width: 400px;">
                <i class="fas fa-search" style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: #888;"></i>
                <input type="text" id="productSearch" placeholder="Buscar producto..." style="width: 100%; padding: 10px 10px 10px 35px; border-radius: 8px; border: 1px solid #ccc;">
            </div>

            <div style="display: flex; gap: 10px;">
                <button class="action-btn secondary-btn" id="btnManageModifiers" style="background-color: #f8f9fa; color: #333; border: 1px solid #ddd; padding: 10px 20px; border-radius: 8px; cursor: pointer; font-weight: bold; display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-layer-group"></i> Modificadores
                </button>

                <button class="action-btn primary-btn" id="btnAddProduct" disabled style="background-color: #5a2dfc; color: white; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; font-weight: bold; display: flex; align-items: center; gap: 8px; opacity: 0.5;">
                    <i class="fas fa-plus"></i> Nuevo Producto
                </button>
            </div>
        </div>
        
        <div class="menu-management-layout" style="background: white; border-radius: 12px; border: 1px solid #e0e0e0; overflow: hidden; display: grid; grid-template-columns: 300px 1fr; height: calc(100vh - 220px);">
            
            <aside class="categories-panel" style="border-right: 1px solid #e0e0e0; display: flex; flex-direction: column;">
                <div class="panel-header" style="padding: 15px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; background: #f9fafb;">
                    <h3 style="margin:0; font-size: 1rem; color: #333;">Categorías</h3>
                    <button class="btn-icon-add" id="btnAddCategory" title="Nueva Categoría" style="background: #5a2dfc; color: white; border: none; width: 28px; height: 28px; border-radius: 6px; cursor: pointer;">
                        <i class="fas fa-plus" style="font-size: 0.8rem;"></i>
                    </button>
                </div>
                <div id="categoriesList" class="categories-list" style="flex-grow: 1; overflow-y: auto; padding: 10px;">
                    <p style="text-align:center; padding:20px; color:#999;">Cargando...</p>
                </div>
            </aside>

            <section class="products-panel" style="display: flex; flex-direction: column; background: #fff;">
                <div class="panel-header-products" style="padding: 15px 20px; border-bottom: 1px solid #eee; background: #fff;">
                    <h3 id="currentCategoryTitle" style="margin: 0; color: #333;">Selecciona una categoría</h3>
                </div>
                
                <div id="productsGrid" class="products-grid" style="flex-grow: 1; overflow-y: auto; padding: 20px; background: #fafafa;">
                    <div class="empty-state" style="text-align: center; color: #999; margin-top: 50px;">
                        <i class="fas fa-arrow-left" style="font-size: 2rem; margin-bottom: 10px;"></i>
                        <p>Selecciona una categoría para ver sus productos.</p>
                    </div>
                </div>
            </section>

        </div>
    </main>
</div>

<div id="categoryModal" class="modal-overlay">
    <div class="modal-content">
        <h2 id="catModalTitle">Categoría</h2>
        <form id="categoryForm">
            <input type="hidden" id="catId">
            <div class="form-group">
                <label>Nombre de la Categoría</label>
                <input type="text" id="catName" required placeholder="Ej: Bebidas Calientes" autocomplete="off">
            </div>
            <div class="form-group">
                <label>Área de Preparación</label>
                <select id="catArea" class="form-control">
                    <option value="COCINA">Cocina</option>
                    <option value="BARRA">Barra</option>
                </select>
            </div>
            <div class="modal-actions">
                <button type="button" id="cancelCatModal" class="cancel-btn">Cancelar</button>
                <button type="submit" class="confirm-btn">Guardar</button>
            </div>
        </form>
    </div>
</div>

<div id="productModal" class="modal-overlay">
    <div class="modal-content">
        <h2 id="prodModalTitle">Producto</h2>
        <form id="productForm">
            <input type="hidden" id="prodId">
            <input type="hidden" id="prodCategoryId">
            
            <div class="form-group">
                <label>Nombre del Producto</label>
                <input type="text" id="prodName" required placeholder="Ej: Hamburguesa Especial" autocomplete="off">
            </div>

            <div class="form-group">
                <label>Precio ($)</label>
                <input type="number" id="prodPrice" required step="0.50" min="0" placeholder="0.00">
            </div>

            <div class="form-group">
                <label>Grupo de Modificadores</label>
                <select id="prodModifierGroup" class="form-control">
                    <option value="">-- Ninguno --</option>
                </select>
            </div>

            <div class="form-group">
                <label>Límite de Stock (Opcional)</label>
                <input type="number" id="prodStock" placeholder="Dejar vacío para infinito" min="0">
                <small class="form-note" style="display:block; margin-top:5px; color:#888; font-size:0.8rem;">Si pones un número, se restará con cada venta.</small>
            </div>

            <div class="modal-actions">
                <button type="button" id="cancelProdModal" class="cancel-btn">Cancelar</button>
                <button type="submit" class="confirm-btn">Guardar</button>
            </div>
        </form>
    </div>
</div>

<div id="modifiersManagerModal" class="modal-overlay">
    <div class="modal-content large-modal" style="max-width: 800px; height: 80vh; padding: 0; display: flex; flex-direction: column;">
        <div class="modal-header-custom" style="padding: 15px 20px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; background: #f8f9fa;">
            <h2 style="margin: 0; font-size: 1.2rem;">Gestión de Modificadores</h2>
            <button class="close-modal-x" id="closeModManager" style="background:none; border:none; font-size: 1.5rem; cursor: pointer;">&times;</button>
        </div>
        
        <div class="mod-manager-layout" style="display: grid; grid-template-columns: 1fr 1.5fr; height: 100%; overflow: hidden;">
            <div class="mod-groups-col" style="border-right: 1px solid #eee; background: #fafafa; display: flex; flex-direction: column;">
                <div class="col-header" style="padding: 10px 15px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center;">
                    <h4 style="margin:0; font-size:0.9rem; color:#555;">Grupos</h4>
                    <button class="btn-icon-add small" id="btnAddModGroup" style="width:24px; height:24px; font-size:0.7rem; background:#5a2dfc; color:white; border:none; border-radius:4px; cursor:pointer;"><i class="fas fa-plus"></i></button>
                </div>
                <div id="modGroupsList" class="scroll-list" style="flex-grow: 1; overflow-y: auto; padding: 10px;"></div>
            </div>
            <div class="mod-options-col" style="background: #fff; display: flex; flex-direction: column;">
                <div class="col-header" style="padding: 10px 15px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center;">
                    <h4 id="selectedGroupName" style="margin:0; font-size:0.9rem; color:#333;">Selecciona un Grupo</h4>
                    <button class="btn-icon-add small" id="btnAddModOption" disabled style="width:24px; height:24px; font-size:0.7rem; background:#5a2dfc; color:white; border:none; border-radius:4px; cursor:pointer; opacity:0.5;"><i class="fas fa-plus"></i></button>
                </div>
                <div id="modOptionsList" class="scroll-list" style="flex-grow: 1; overflow-y: auto; padding: 10px;"></div>
            </div>
        </div>
    </div>
</div>

<div id="modGroupFormModal" class="modal-overlay" style="z-index: 1100;">
    <div class="modal-content">
        <h3>Grupo de Modificadores</h3>
        <form id="modGroupForm">
            <input type="hidden" id="mgId">
            <div class="form-group">
                <label>Nombre del Grupo</label>
                <input type="text" id="mgName" required placeholder="Ej: Término de Carne">
            </div>
            <div class="modal-actions">
                <button type="button" class="cancel-btn" id="cancelMgBtn">Cancelar</button>
                <button type="submit" class="confirm-btn">Guardar</button>
            </div>
        </form>
    </div>
</div>

<div id="modOptionFormModal" class="modal-overlay" style="z-index: 1100;">
    <div class="modal-content">
        <h3>Opción de Modificador</h3>
        <form id="modOptionForm">
            <input type="hidden" id="moId">
            <input type="hidden" id="moGroupId">
            
            <div class="form-group">
                <label>Nombre de la Opción</label>
                <input type="text" id="moName" required placeholder="Ej: 3/4">
            </div>
            <div class="form-group">
                <label>Precio Extra ($)</label>
                <input type="number" id="moPrice" required step="0.50" min="0" value="0">
            </div>
            
            <div class="form-group">
                <label>Límite de Stock (Opcional)</label>
                <input type="number" id="moStock" placeholder="Dejar vacío para infinito" min="0">
                <small class="form-note" style="display:block; margin-top:5px; color:#888; font-size:0.8rem;">
                    Si pones un número, se restará con cada venta.
                </small>
            </div>
            <div class="modal-actions">
                <button type="button" class="cancel-btn" id="cancelMoBtn">Cancelar</button>
                <button type="submit" class="confirm-btn">Guardar</button>
            </div>
        </form>
    </div>
</div>

<script src="/src/js/session_interceptor.js"></script>
<script src="/src/js/manager_menu.js"></script> 

</body>
</html>
