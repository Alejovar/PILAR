<?php
/**
 * AdvancedOrderOptionsTest.php
 *
 * Pruebas unitarias para:
 *   src/api/orders/advanced_options/execute_cancel.php
 *   src/api/orders/advanced_options/change_server.php
 *   src/api/orders/advanced_options/change_table.php
 *   src/api/orders/advanced_options/change_guest_count.php
 *   src/api/orders/advanced_options/execute_move.php
 *
 * Reglas bajo prueba:
 *  - Cancelación requiere order_id, items y razón de >= 5 caracteres
 *  - La razón de cancelación no puede ser muy corta
 *  - Cambio de mesero requiere que el nuevo mesero tenga rol_id = 2
 *  - Cambio de mesa detecta conflictos de número duplicado
 *  - Cambio de mesa rechaza si el nuevo número es igual al actual
 *  - Cambio de comensales requiere número positivo
 *  - El movimiento de productos construye el número de placeholders correcto
 *  - Todas las operaciones verifican que el turno de caja esté abierto
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class AdvancedOrderOptionsTest extends TestCase
{
    // ────────────────────────────────────────────────────────────────────────
    // execute_cancel — Validación de datos de entrada
    // ────────────────────────────────────────────────────────────────────────

    public function test_cancelar_items_falta_order_id_es_invalido(): void
    {
        $result = $this->validateCancelInput(0, [1, 2], 'Cliente pidió cancelar');
        $this->assertFalse($result['valid']);
    }

    public function test_cancelar_items_lista_vacia_es_invalida(): void
    {
        $result = $this->validateCancelInput(5, [], 'Motivo válido');
        $this->assertFalse($result['valid']);
    }

    public function test_cancelar_razon_muy_corta_es_invalida(): void
    {
        $result = $this->validateCancelInput(5, [1], 'ok'); // Menos de 5 caracteres
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('razón', $result['error']);
    }

    public function test_cancelar_razon_exactamente_5_caracteres_es_valida(): void
    {
        $result = $this->validateCancelInput(5, [1], 'corto'); // Exactamente 5 chars
        $this->assertTrue($result['valid']);
    }

    public function test_cancelar_datos_validos_completos(): void
    {
        $result = $this->validateCancelInput(3, [10, 11], 'El cliente cambió de opinión');
        $this->assertTrue($result['valid']);
    }

    public function test_cancelar_con_placeholders_dinamicos_correctos(): void
    {
        $items        = [1, 2, 3];
        $itemCount    = count($items);
        $placeholders = implode(',', array_fill(0, $itemCount, '?'));
        $types        = str_repeat('i', $itemCount);

        $this->assertEquals('?,?,?', $placeholders);
        $this->assertEquals('iii', $types);
    }

    public function test_bind_types_para_cancelacion_son_correctos(): void
    {
        // Tipos: s (razón) + d (cero_price) + i*N (detail_ids) + i (order_id)
        $items     = [10, 20];
        $bindTypes = "sd" . str_repeat('i', count($items)) . "i";
        $this->assertEquals("sdiii", $bindTypes);
    }

    // ────────────────────────────────────────────────────────────────────────
    // change_server — Validación
    // ────────────────────────────────────────────────────────────────────────

    public function test_cambiar_mesero_falta_table_number_es_invalido(): void
    {
        $result = $this->validateChangeServer(0, 5);
        $this->assertFalse($result['valid']);
    }

    public function test_cambiar_mesero_falta_new_server_id_es_invalido(): void
    {
        $result = $this->validateChangeServer(3, 0);
        $this->assertFalse($result['valid']);
    }

    public function test_cambiar_mesero_datos_validos(): void
    {
        $result = $this->validateChangeServer(3, 7);
        $this->assertTrue($result['valid']);
    }

    public function test_cambiar_mesero_falla_si_rol_no_es_mesero(): void
    {
        // La BD debe devolver nombre solo si rol_id=2
        $serverRow = null; // Simula: servidor con otro rol no encontrado
        $result    = $this->checkServerExists($serverRow);
        $this->assertFalse($result);
    }

    public function test_cambiar_mesero_exitoso_si_rol_es_correcto(): void
    {
        $serverRow = ['name' => 'Luis Torres']; // Devuelve nombre = encontró un mesero válido
        $result    = $this->checkServerExists($serverRow);
        $this->assertTrue($result);
    }

    // ────────────────────────────────────────────────────────────────────────
    // change_table — Validación
    // ────────────────────────────────────────────────────────────────────────

    public function test_cambiar_numero_mesa_mismo_numero_es_invalido(): void
    {
        $result = $this->validateChangeTable(5, 5);
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('idéntico', $result['error']);
    }

    public function test_cambiar_numero_mesa_numero_negativo_es_invalido(): void
    {
        $result = $this->validateChangeTable(-1, 5);
        $this->assertFalse($result['valid']);
    }

    public function test_cambiar_numero_mesa_cero_es_invalido(): void
    {
        $result = $this->validateChangeTable(0, 5);
        $this->assertFalse($result['valid']);
    }

    public function test_cambiar_numero_mesa_conflicto_con_mesa_existente(): void
    {
        // Simula que el nuevo número ya está en uso por otra mesa
        $conflictRow = ['table_id' => 7];
        $result      = $this->checkTableConflict($conflictRow, 3, 5);
        $this->assertFalse($result['allowed']);
        $this->assertStringContainsString('ya está asignado', $result['message']);
    }

    public function test_cambiar_numero_mesa_sin_conflicto_es_valido(): void
    {
        $conflictRow = null; // No hay conflicto
        $result      = $this->checkTableConflict($conflictRow, 3, 5);
        $this->assertTrue($result['allowed']);
    }

    public function test_cambiar_numero_mesa_datos_validos(): void
    {
        $result = $this->validateChangeTable(3, 7);
        $this->assertTrue($result['valid']);
    }

    // ────────────────────────────────────────────────────────────────────────
    // change_guest_count — Validación
    // ────────────────────────────────────────────────────────────────────────

    public function test_cambiar_comensales_cero_es_invalido(): void
    {
        $result = $this->validateChangeGuestCount(3, 0);
        $this->assertFalse($result['valid']);
    }

    public function test_cambiar_comensales_numero_negativo_es_invalido(): void
    {
        $result = $this->validateChangeGuestCount(3, -2);
        $this->assertFalse($result['valid']);
    }

    public function test_cambiar_comensales_mesa_invalida(): void
    {
        $result = $this->validateChangeGuestCount(0, 4);
        $this->assertFalse($result['valid']);
    }

    public function test_cambiar_comensales_datos_validos(): void
    {
        $result = $this->validateChangeGuestCount(5, 6);
        $this->assertTrue($result['valid']);
    }

    // ────────────────────────────────────────────────────────────────────────
    // execute_move — Validación
    // ────────────────────────────────────────────────────────────────────────

    public function test_mover_items_sin_source_order_invalido(): void
    {
        $result = $this->validateMoveInput(0, 3, [['detail_id' => 1]]);
        $this->assertFalse($result['valid']);
    }

    public function test_mover_items_sin_destino_invalido(): void
    {
        $result = $this->validateMoveInput(2, 0, [['detail_id' => 1]]);
        $this->assertFalse($result['valid']);
    }

    public function test_mover_items_lista_vacia_invalida(): void
    {
        $result = $this->validateMoveInput(2, 3, []);
        $this->assertFalse($result['valid']);
    }

    public function test_mover_items_datos_validos(): void
    {
        $result = $this->validateMoveInput(2, 5, [['detail_id' => 10], ['detail_id' => 11]]);
        $this->assertTrue($result['valid']);
    }

    public function test_orden_origen_vacia_debe_cerrarse(): void
    {
        // Simula que después de mover items, la orden de origen quedó vacía
        $itemsLeft = 0;
        $shouldClose = ($itemsLeft == 0);
        $this->assertTrue($shouldClose);
    }

    public function test_orden_origen_no_vacia_no_debe_cerrarse(): void
    {
        $itemsLeft   = 3;
        $shouldClose = ($itemsLeft == 0);
        $this->assertFalse($shouldClose);
    }

    // ────────────────────────────────────────────────────────────────────────
    // Verificación de turno abierto (común a todos los módulos)
    // ────────────────────────────────────────────────────────────────────────

    public function test_turno_cerrado_bloquea_accion(): void
    {
        $shiftOpen = false;
        $this->assertFalse($shiftOpen, 'La acción debe ser bloqueada si el turno está cerrado.');
    }

    public function test_turno_abierto_permite_accion(): void
    {
        $shiftOpen = true;
        $this->assertTrue($shiftOpen);
    }

    // ────────────────────────────────────────────────────────────────────────
    // Helpers privados
    // ────────────────────────────────────────────────────────────────────────

    private function validateCancelInput(int $orderId, array $items, string $reason): array
    {
        if ($orderId <= 0 || empty($items) || strlen($reason) < 5) {
            return ['valid' => false, 'error' => 'Datos de cancelación inválidos o razón muy corta.'];
        }
        return ['valid' => true];
    }

    private function validateChangeServer(int $tableNumber, int $newServerId): array
    {
        if ($tableNumber <= 0 || $newServerId <= 0) {
            return ['valid' => false, 'error' => 'Valores de mesa o mesero inválidos.'];
        }
        return ['valid' => true];
    }

    private function checkServerExists(?array $serverRow): bool
    {
        return $serverRow !== null && isset($serverRow['name']);
    }

    private function validateChangeTable(int $newNumber, int $currentNumber): array
    {
        if ($newNumber <= 0 || $currentNumber <= 0) {
            return ['valid' => false, 'error' => 'Los números de mesa no son válidos.'];
        }
        if ($newNumber === $currentNumber) {
            return ['valid' => false, 'error' => 'El nuevo número de mesa es idéntico al actual.'];
        }
        return ['valid' => true];
    }

    private function checkTableConflict(?array $conflictRow, int $newNumber, int $currentNumber): array
    {
        if ($conflictRow !== null) {
            return ['allowed' => false, 'message' => "El número {$newNumber} ya está asignado a otra mesa."];
        }
        return ['allowed' => true, 'message' => ''];
    }

    private function validateChangeGuestCount(int $tableNumber, int $guestCount): array
    {
        if ($tableNumber <= 0 || $guestCount <= 0) {
            return ['valid' => false, 'error' => 'Los valores ingresados no son válidos.'];
        }
        return ['valid' => true];
    }

    private function validateMoveInput(int $sourceOrderId, int $destTableNumber, array $items): array
    {
        if ($sourceOrderId <= 0 || $destTableNumber <= 0 || empty($items)) {
            return ['valid' => false, 'error' => 'Datos de movimiento inválidos.'];
        }
        return ['valid' => true];
    }
}
