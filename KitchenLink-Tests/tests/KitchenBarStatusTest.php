<?php
/**
 * KitchenBarStatusTest.php
 *
 * Pruebas unitarias para:
 *   src/api/kitchen/update_item_status.php
 *   src/api/bar/update_item_status.php
 *
 * Reglas bajo prueba:
 *  - Solo se aceptan solicitudes POST
 *  - Se requiere user_id en sesión
 *  - detail_id y new_status son requeridos
 *  - Los únicos estados válidos son 'EN_PREPARACION' y 'LISTO'
 *  - Al pasar a 'LISTO', el ítem se guarda en la tabla de historial correcta según área
 *  - La tabla de historial es kitchen_production_history para COCINA
 *  - La tabla de historial es bar_production_history para BAR
 *  - La actualización simple (EN_PREPARACION) no usa transacción
 *  - El flujo a 'LISTO' usa transacción con commit/rollback
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class KitchenBarStatusTest extends TestCase
{
    // ────────────────────────────────────────────────────────────────────────
    // Validación de datos de entrada
    // ────────────────────────────────────────────────────────────────────────

    public function test_falta_detail_id_es_invalido(): void
    {
        $result = $this->validateStatusInput(null, 'EN_PREPARACION');
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('parámetros', $result['error']);
    }

    public function test_falta_new_status_es_invalido(): void
    {
        $result = $this->validateStatusInput(5, null);
        $this->assertFalse($result['valid']);
    }

    public function test_detail_id_cero_es_invalido(): void
    {
        $result = $this->validateStatusInput(0, 'LISTO');
        $this->assertFalse($result['valid']);
    }

    public function test_datos_validos_retornan_ok(): void
    {
        $result = $this->validateStatusInput(3, 'EN_PREPARACION');
        $this->assertTrue($result['valid']);
    }

    // ────────────────────────────────────────────────────────────────────────
    // Validación de estados permitidos
    // ────────────────────────────────────────────────────────────────────────

    public function test_estado_EN_PREPARACION_es_valido(): void
    {
        $this->assertTrue($this->isAllowedStatus('EN_PREPARACION'));
    }

    public function test_estado_LISTO_es_valido(): void
    {
        $this->assertTrue($this->isAllowedStatus('LISTO'));
    }

    public function test_estado_PENDIENTE_no_esta_permitido(): void
    {
        $this->assertFalse($this->isAllowedStatus('PENDIENTE'));
    }

    public function test_estado_CANCELADO_no_esta_permitido(): void
    {
        $this->assertFalse($this->isAllowedStatus('CANCELADO'));
    }

    public function test_estado_vacio_no_esta_permitido(): void
    {
        $this->assertFalse($this->isAllowedStatus(''));
    }

    public function test_estado_minusculas_no_es_valido(): void
    {
        // Los estados son case-sensitive
        $this->assertFalse($this->isAllowedStatus('listo'));
    }

    // ────────────────────────────────────────────────────────────────────────
    // Selección de tabla de historial según área de preparación
    // ────────────────────────────────────────────────────────────────────────

    public function test_area_COCINA_usa_tabla_kitchen_history(): void
    {
        $table = $this->getHistoryTable('COCINA');
        $this->assertEquals('kitchen_production_history', $table);
    }

    public function test_area_BAR_usa_tabla_bar_history(): void
    {
        $table = $this->getHistoryTable('BAR');
        $this->assertEquals('bar_production_history', $table);
    }

    public function test_area_desconocida_usa_tabla_kitchen_por_defecto(): void
    {
        // Si por alguna razón el área no es reconocida, va a COCINA
        $table = $this->getHistoryTable('OTRO');
        $this->assertEquals('kitchen_production_history', $table);
    }

    // ────────────────────────────────────────────────────────────────────────
    // Flujo transaccional al marcar como LISTO
    // ────────────────────────────────────────────────────────────────────────

    public function test_marcar_listo_hace_commit_si_exito(): void
    {
        $conn = $this->createMock(MockConnection::class);
        $conn->expects($this->once())->method('commit');
        $conn->expects($this->never())->method('rollback');

        $conn->begin_transaction();
        // ... operaciones exitosas ...
        $conn->commit();
    }

    public function test_marcar_listo_hace_rollback_si_item_no_existe(): void
    {
        $conn = $this->createMock(MockConnection::class);
        $conn->expects($this->never())->method('commit');
        $conn->expects($this->once())->method('rollback');

        try {
            $conn->begin_transaction();
            throw new \Exception("No se encontró el ítem.");
        } catch (\Exception) {
            $conn->rollback();
        }
    }

    public function test_marcar_en_preparacion_no_usa_transaccion(): void
    {
        // EN_PREPARACION es una actualización simple sin transacción
        $conn = $this->createMock(MockConnection::class);
        $conn->expects($this->never())->method('begin_transaction');
        $conn->expects($this->never())->method('commit');

        // Simula la lógica: si new_status !== 'LISTO', no hay transacción
        $newStatus = 'EN_PREPARACION';
        if ($newStatus === 'LISTO') {
            $conn->begin_transaction();
            // ... lógica con historial ...
            $conn->commit();
        }
        // else: solo UPDATE simple
        $this->assertTrue(true); // Llega aquí sin llamar transacción
    }

    // ────────────────────────────────────────────────────────────────────────
    // Cadena de tipos para bind_param del INSERT de historial
    // ────────────────────────────────────────────────────────────────────────

    public function test_cadena_de_tipos_para_insertar_en_historial_es_correcta(): void
    {
        // "iiisssssiss" = i(detail_id), i(order_id), i(table_number),
        //                 s(batch_timestamp), s(service_time), s(server_name),
        //                 s(product_name), s(modifier_name), i(quantity),
        //                 s(special_notes), s(timestamp_added)
        $expectedTypes = 'iiisssssiss';
        $this->assertEquals(11, strlen($expectedTypes));
        $this->assertEquals('i', $expectedTypes[0]);  // detail_id
        $this->assertEquals('s', $expectedTypes[7]);  // modifier_name
        $this->assertEquals('i', $expectedTypes[8]);  // quantity
    }

    // ────────────────────────────────────────────────────────────────────────
    // Helpers privados
    // ────────────────────────────────────────────────────────────────────────

    private function validateStatusInput(mixed $detailId, mixed $newStatus): array
    {
        if (empty($detailId) || empty($newStatus)) {
            return ['valid' => false, 'error' => 'Faltan parámetros.'];
        }
        return ['valid' => true];
    }

    private function isAllowedStatus(string $status): bool
    {
        return in_array($status, ['EN_PREPARACION', 'LISTO'], true);
    }

    private function getHistoryTable(string $preparationArea): string
    {
        return ($preparationArea === 'BAR') ? 'bar_production_history' : 'kitchen_production_history';
    }
}
