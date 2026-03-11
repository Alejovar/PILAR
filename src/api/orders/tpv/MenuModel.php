<?php
// MenuModel.php - Clase para manejar consultas del menú y modificadores.

class MenuModel {
    private $conn; // Conexión a la base de datos (MySQLi)

    public function __construct(mysqli $conn) {
        $this->conn = $conn;
    }

    /**
     * Obtiene los productos disponibles para una categoría específica,
     * incluyendo el área de preparación y la información de stock.
     */
    public function getProductsByCategory($categoryId) {
        $sql = "
            SELECT 
                p.product_id, 
                p.name, 
                p.price, 
                p.modifier_group_id,
                p.is_available,       /* <-- NUEVO: Estado de disponibilidad */
                p.stock_quantity,     /* <-- NUEVO: Conteo de Stock (85) */
                mc.preparation_area 
            FROM 
                products p
            JOIN
                menu_categories mc ON mc.category_id = p.category_id
            WHERE 
                p.category_id = ?
            ORDER BY 
                p.name ASC
        ";
        
        $stmt = null;
        try {
            if (!$this->conn) { throw new Exception("Conexión no inicializada."); }
            
            $stmt = $this->conn->prepare($sql);
            if ($stmt === false) { throw new Exception("Error al preparar SQL de productos: " . $this->conn->error); }
            
            $stmt->bind_param("i", $categoryId);
            $stmt->execute();
            $result = $stmt->get_result(); 
            $products = $result->fetch_all(MYSQLI_ASSOC); 
            $stmt->close();
            return $products;
        } catch (\Exception $e) {
            error_log("DB Error getting products: " . $e->getMessage());
            if ($stmt) $stmt->close(); 
            throw new \Exception("Error de BD: " . $e->getMessage()); 
        }
    }

    /**
     * Obtiene los modificadores (guisos/sabores) para un grupo dado,
     * incluyendo la información de stock y disponibilidad.
     */
    public function getModifiersByGroup($groupId) {
        // 💡 MODIFICADO: Añadimos 'is_active' y 'stock_quantity' a la consulta
        $sql_options = "SELECT modifier_id, modifier_name, modifier_price, is_active, stock_quantity 
                        FROM modifiers 
                        WHERE group_id = ? 
                        ORDER BY modifier_price ASC, modifier_name ASC";
                        
        $sql_name = "SELECT group_name FROM modifier_groups WHERE group_id = ?";
        
        $output = ['modifiers' => [], 'group_name' => 'Opción Requerida'];
        $stmt_options = null;
        $stmt_name = null;
        
        try {
            $stmt_options = $this->conn->prepare($sql_options);
            if ($stmt_options === false) throw new Exception("Error SQL en opciones: " . $this->conn->error);
            
            $stmt_options->bind_param("i", $groupId);
            $stmt_options->execute();
            $result_options = $stmt_options->get_result();
            $output['modifiers'] = $result_options->fetch_all(MYSQLI_ASSOC);
            $stmt_options->close();

            $stmt_name = $this->conn->prepare($sql_name);
            if ($stmt_name === false) throw new Exception("Error SQL en nombre de grupo: " . $this->conn->error);
            
            $stmt_name->bind_param("i", $groupId);
            $stmt_name->execute();
            $result_name = $stmt_name->get_result();
            $group_row = $result_name->fetch_assoc();
            $stmt_name->close();

            if ($group_row) {
                $output['group_name'] = $group_row['group_name'];
            }

            return $output; 

        } catch (\Exception $e) {
            error_log("DB Error getting modifiers: " . $e->getMessage());
            if ($stmt_options) $stmt_options->close();
            if ($stmt_name) $stmt_name->close();
            return ['modifiers' => [], 'group_name' => 'Error de Conexión'];
        }
    }

    /**
     * Obtiene el área de preparación para un producto específico.
     * (Esta función no necesita stock y se mantiene igual)
     */
    public function getPreparationAreaByProductId($productId) {
        $sql = "
            SELECT 
                mc.preparation_area 
            FROM 
                products p
            JOIN 
                menu_categories mc ON mc.category_id = p.category_id
            WHERE 
                p.product_id = ?
        ";
        $stmt = null;
        try {
            $stmt = $this->conn->prepare($sql);
            if ($stmt === false) throw new Exception("Error al preparar SQL de área: " . $this->conn->error);
            
            $stmt->bind_param("i", $productId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $area = $result->fetch_assoc()['preparation_area'] ?? 'COCINA'; 
            $stmt->close();
            return $area;
        } catch (\Exception $e) {
            error_log("DB Error getting product area: " . $e->getMessage());
            if ($stmt) $stmt->close();
            throw new \Exception("Error al consultar área de preparación: " . $e->getMessage()); 
        }
    }
}
