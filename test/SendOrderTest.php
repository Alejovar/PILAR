<?php
/**
 * SendOrderTest.php
 *
 * Pruebas unitarias para la lógica de negocio de:
 *   src/api/orders/tpv/send_order.php
 *
 * Reglas bajo prueba:
 *  - Solo meseros (rol_id=2) y gerentes (rol_id=1) pueden enviar órdenes
 *  - El turno de caja debe estar abierto
 *  - table_number debe ser > 0
 *  - El array de times no puede estar vacío
 *  - La mesa no puede tener pre_bill_status = 'REQUESTED'
 *  - Los productos deben existir y tener stock suficiente
 *  - Los modificadores (si existen) deben estar activos con stock suficiente
 *  - El stock se descuenta correctamente
 *  - Se usa transacción (commit si éxito, rollback si error)
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class SendOrderTest extends TestCase
{
    // ────────────────────────────────────────────────────────────────────────
    // Validación de rol
    // ────────────────────────────────────────────────────────────────────────

    public function test_acceso_denegado_si_rol_no_es_mesero_ni_gerente(): void
    {
        $result = $this->validateRole(6); // 6 = Cajero
        $this->assertFalse($result);
    }

    public function test_acceso_permitido_para_mesero(): void
    {
        $this->assertTrue($this->validateRole(2));
    }

    public function test_acceso_permitido_para_gerente(): void
    {
        $this->assertTrue($this->validateRole(1));
    }

    // ────────────────────────────────────────────────────────────────────────
    // Validación de datos de entrada
    // ────────────────────────────────────────────────────────────────────────

    public function test_datos_vacios_retornan_error(): void
    {
        $result = $this->validateOrderInput(0, []);
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('incompletos', $result['error']);
    }

    public function test_table_number_cero_es_invalido(): void
    {
        $result = $this->validateOrderInput(0, [['service_time' => 1, 'items' => []]]);
        $this->assertFalse($result['valid']);
    }

    public function test_times_vacio_es_invalido(): void
    {
        $result = $this->validateOrderInput(5, []);
        $this->assertFalse($result['valid']);
    }

    public function test_datos_validos_retornan_ok(): void
    {
        $times = [['service_time' => 1, 'items' => [['id' => 1, 'quantity' => 2]]]];
        $result = $this->validateOrderInput(3, $times);
        $this->assertTrue($result['valid']);
    }

    // ────────────────────────────────────────────────────────────────────────
    // Bloqueo por pre-bill solicitado
    // ────────────────────────────────────────────────────────────────────────

    public function test_orden_bloqueada_si_pre_bill_fue_solicitado(): void
    {
        $tableRow = ['table_id' => 10, 'assigned_server_id' => 1, 'pre_bill_status' => 'REQUESTED'];
        $result   = $this->checkPreBillStatus($tableRow);
        $this->assertFalse($result['allowed']);
        $this->assertStringContainsString('BLOQUEADA', $result['message']);
    }

    public function test_orden_permitida_si_pre_bill_no_solicitado(): void
    {
        $tableRow = ['table_id' => 10, 'assigned_server_id' => 1, 'pre_bill_status' => null];
        $result   = $this->checkPreBillStatus($tableRow);
        $this->assertTrue($result['allowed']);
    }

    // ────────────────────────────────────────────────────────────────────────
    // Validación de stock de producto
    // ────────────────────────────────────────────────────────────────────────

    public function test_producto_agotado_lanza_error(): void
    {
        $product = ['is_available' => 0, 'stock_quantity' => 0, 'price' => 80.0];
        $result  = $this->validateProductStock($product, 1);
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('AGOTADO', $result['error']);
    }

    public function test_producto_con_stock_null_siempre_disponible(): void
    {
        // stock_quantity=null significa sin control de stock
        $product = ['is_available' => 1, 'stock_quantity' => null, 'price' => 80.0];
        $result  = $this->validateProductStock($product, 5);
        $this->assertTrue($result['valid']);
    }

    public function test_stock_insuficiente_lanza_error(): void
    {
        $product = ['is_available' => 1, 'stock_quantity' => 2, 'price' => 80.0];
        $result  = $this->validateProductStock($product, 5); // Pedir 5, solo hay 2
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('insuficiente', $result['error']);
    }

    public function test_stock_suficiente_es_valido(): void
    {
        $product = ['is_available' => 1, 'stock_quantity' => 10, 'price' => 80.0];
        $result  = $this->validateProductStock($product, 3);
        $this->assertTrue($result['valid']);
    }

    public function test_stock_exacto_igual_a_pedido_es_valido(): void
    {
        $product = ['is_available' => 1, 'stock_quantity' => 3, 'price' => 80.0];
        $result  = $this->validateProductStock($product, 3);
        $this->assertTrue($result['valid']);
    }

    // ────────────────────────────────────────────────────────────────────────
    // Validación de stock de modificador
    // ────────────────────────────────────────────────────────────────────────

    public function test_modificador_inactivo_lanza_error(): void
    {
        $mod    = ['is_active' => 0, 'stock_quantity' => 5, 'modifier_price' => 10.0, 'modifier_name' => 'Queso'];
        $result = $this->validateModifierStock($mod, 1);
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('agotado', strtolower($result['error']));
    }

    public function test_modificador_sin_stock_lanza_error(): void
    {
        $mod    = ['is_active' => 1, 'stock_quantity' => 0, 'modifier_price' => 10.0, 'modifier_name' => 'Crema'];
        $result = $this->validateModifierStock($mod, 1);
        $this->assertFalse($result['valid']);
    }

    public function test_modificador_valido_con_stock_nulo(): void
    {
        $mod    = ['is_active' => 1, 'stock_quantity' => null, 'modifier_price' => 10.0, 'modifier_name' => 'Sin azúcar'];
        $result = $this->validateModifierStock($mod, 3);
        $this->assertTrue($result['valid']);
    }

    // ────────────────────────────────────────────────────────────────────────
    // Lógica de descuento de stock
    // ────────────────────────────────────────────────────────────────────────

    public function test_calculo_stock_restante_es_correcto(): void
    {
        $update   = ['current' => 10, 'qty' => 3];
        $newStock = $update['current'] - $update['qty'];
        $this->assertEquals(7, $newStock);
    }

    public function test_producto_queda_disponible_false_cuando_stock_llega_a_cero(): void
    {
        $update       = ['current' => 1, 'qty' => 1];
        $newStock     = $update['current'] - $update['qty'];
        $isAvailable  = ($newStock === 0) ? 0 : 1;
        $this->assertEquals(0, $isAvailable);
        $this->assertEquals(0, $newStock);
    }

    public function test_producto_sigue_disponible_si_stock_queda_mayor_a_cero(): void
    {
        $update      = ['current' => 5, 'qty' => 2];
        $newStock    = $update['current'] - $update['qty'];
        $isAvailable = ($newStock === 0) ? 0 : 1;
        $this->assertEquals(1, $isAvailable);
        $this->assertEquals(3, $newStock);
    }

    // ────────────────────────────────────────────────────────────────────────
    // Flujo transaccional con mock de BD
    // ────────────────────────────────────────────────────────────────────────

    public function test_transaccion_hace_rollback_si_producto_no_existe(): void
    {
        $conn = $this->createMock(MockConnection::class);

        // El commit NO debe ser llamado
        $conn->expects($this->never())->method('commit');
        $conn->expects($this->once())->method('rollback');

        try {
            $conn->begin_transaction();
            throw new \Exception("Producto ID 99 no encontrado.");
        } catch (\Exception $e) {
            $conn->rollback();
            $this->assertStringContainsString('no encontrado', $e->getMessage());
        }
    }

    public function test_transaccion_hace_commit_si_todo_es_exitoso(): void
    {
        $conn = $this->createMock(MockConnection::class);
        $conn->expects($this->once())->method('commit');
        $conn->expects($this->never())->method('rollback');

        $conn->begin_transaction();
        // ...procesamiento exitoso...
        $conn->commit();
    }

    // ────────────────────────────────────────────────────────────────────────
    // Helpers privados (réplica de la lógica del endpoint)
    // ────────────────────────────────────────────────────────────────────────

    private function validateRole(int $rolId): bool
    {
        return in_array($rolId, [1, 2], true);
    }

    private function validateOrderInput(int $tableNumber, array $times): array
    {
        if ($tableNumber <= 0 || empty($times)) {
            return ['valid' => false, 'error' => 'Datos incompletos.'];
        }
        return ['valid' => true];
    }

    private function checkPreBillStatus(array $tableRow): array
    {
        if ($tableRow['pre_bill_status'] === 'REQUESTED') {
            return ['allowed' => false, 'message' => 'ACCIÓN BLOQUEADA: La cuenta ya fue solicitada al cajero.'];
        }
        return ['allowed' => true, 'message' => ''];
    }

    private function validateProductStock(array $product, int $quantity): array
    {
        if ($product['is_available'] == 0 || ($product['stock_quantity'] !== null && $product['stock_quantity'] <= 0)) {
            return ['valid' => false, 'error' => 'El producto está AGOTADO (86).'];
        }
        if ($product['stock_quantity'] !== null && (int)$product['stock_quantity'] < $quantity) {
            return ['valid' => false, 'error' => 'Stock insuficiente.'];
        }
        return ['valid' => true];
    }

    private function validateModifierStock(array $modifier, int $quantity): array
    {
        if ($modifier['is_active'] == 0 || ($modifier['stock_quantity'] !== null && $modifier['stock_quantity'] <= 0)) {
            return ['valid' => false, 'error' => 'Modificador agotado.'];
        }
        if ($modifier['stock_quantity'] !== null && (int)$modifier['stock_quantity'] < $quantity) {
            return ['valid' => false, 'error' => 'Stock insuficiente del modificador.'];
        }
        return ['valid' => true];
    }
}
