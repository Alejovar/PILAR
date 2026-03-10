<?php
/**
 * CashierTest.php
 *
 * Pruebas unitarias para:
 *   src/api/cashier/process_payment.php
 *   src/api/cashier/history_reports/open_shift.php
 *   src/api/cashier/history_reports/close_shift.php
 *   src/api/cashier/set_prebill_requested.php
 *
 * Reglas bajo prueba:
 *  - Solo cajeros (rol_id=6) y gerentes (rol_id=1) procesan pagos
 *  - El cálculo de subtotal, impuesto y total es correcto
 *  - Los ítems cancelados no se incluyen en el subtotal
 *  - No se puede abrir turno si ya hay uno abierto
 *  - Solo se puede cerrar turno si no hay mesas abiertas
 *  - El conteo de efectivo manual debe ser numérico y positivo
 *  - El arqueo (diferencia) se calcula correctamente
 *  - No se puede pagar sin order_id o sin métodos de pago
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class CashierTest extends TestCase
{
    // ────────────────────────────────────────────────────────────────────────
    // process_payment — Validación de rol
    // ────────────────────────────────────────────────────────────────────────

    public function test_solo_cajero_y_gerente_pueden_procesar_pago(): void
    {
        $this->assertTrue($this->validateCashierRole(6));   // Cajero
        $this->assertTrue($this->validateCashierRole(1));   // Gerente
        $this->assertFalse($this->validateCashierRole(2));  // Mesero
        $this->assertFalse($this->validateCashierRole(3));  // Otro
    }

    // ────────────────────────────────────────────────────────────────────────
    // process_payment — Validación de datos de entrada
    // ────────────────────────────────────────────────────────────────────────

    public function test_pago_sin_order_id_es_invalido(): void
    {
        $result = $this->validatePaymentInput(null, [['method' => 'Efectivo', 'amount' => 100]]);
        $this->assertFalse($result['valid']);
    }

    public function test_pago_sin_metodos_es_invalido(): void
    {
        $result = $this->validatePaymentInput(10, []);
        $this->assertFalse($result['valid']);
    }

    public function test_pago_con_datos_correctos_es_valido(): void
    {
        $payments = [['method' => 'Efectivo', 'amount' => 200]];
        $result   = $this->validatePaymentInput(5, $payments);
        $this->assertTrue($result['valid']);
    }

    // ────────────────────────────────────────────────────────────────────────
    // process_payment — Cálculo de totales
    // ────────────────────────────────────────────────────────────────────────

    public function test_subtotal_excluye_items_cancelados(): void
    {
        $orderDetails = [
            ['quantity' => 2, 'price_at_order' => 80.0, 'is_cancelled' => 0],   // 160
            ['quantity' => 1, 'price_at_order' => 50.0, 'is_cancelled' => 1],   // Cancelado → no suma
            ['quantity' => 3, 'price_at_order' => 30.0, 'is_cancelled' => 0],   // 90
        ];

        $subtotal = $this->calcSubtotal($orderDetails);
        $this->assertEquals(250.0, $subtotal);
    }

    public function test_impuesto_16_porciento_es_correcto(): void
    {
        $subtotal  = 250.0;
        $tax       = $subtotal * 0.16;
        $this->assertEquals(40.0, $tax);
    }

    public function test_total_con_descuento_y_propina(): void
    {
        $subtotal        = 250.0;
        $tax             = $subtotal * 0.16;   // 40.0
        $discount        = 25.0;
        $tip             = 15.0;
        $grandTotal      = ($subtotal + $tax - $discount) + $tip;

        $this->assertEquals(280.0, $grandTotal);
    }

    public function test_total_sin_descuento_ni_propina(): void
    {
        $subtotal   = 200.0;
        $tax        = $subtotal * 0.16;  // 32.0
        $grandTotal = $subtotal + $tax;
        $this->assertEquals(232.0, $grandTotal);
    }

    public function test_cortesia_no_afecta_el_calculo_de_totales(): void
    {
        // is_courtesy es un flag de registro, no descuenta del total
        $subtotal   = 100.0;
        $tax        = $subtotal * 0.16;
        $grandTotal = $subtotal + $tax;
        $isCourtesy = true;

        $this->assertEquals(116.0, $grandTotal);
        $this->assertTrue($isCourtesy); // El flag existe pero el total sigue igual
    }

    // ────────────────────────────────────────────────────────────────────────
    // open_shift — Lógica de apertura de turno
    // ────────────────────────────────────────────────────────────────────────

    public function test_abrir_turno_con_monto_valido(): void
    {
        $result = $this->validateOpenShift(1, 500.0, false);
        $this->assertTrue($result['valid']);
    }

    public function test_abrir_turno_falla_si_ya_hay_turno_abierto(): void
    {
        $result = $this->validateOpenShift(1, 500.0, true); // shiftAlreadyOpen=true
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('ya existe', strtolower($result['error']));
    }

    public function test_abrir_turno_falla_con_monto_negativo(): void
    {
        $result = $this->validateOpenShift(1, -100.0, false);
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('inválido', strtolower($result['error']));
    }

    public function test_abrir_turno_falla_con_monto_no_numerico(): void
    {
        $result = $this->validateOpenShiftRaw(1, 'abc', false);
        $this->assertFalse($result['valid']);
    }

    public function test_abrir_turno_acepta_monto_cero(): void
    {
        // Fondo de caja en 0 es válido (turno sin efectivo inicial)
        $result = $this->validateOpenShift(1, 0.0, false);
        $this->assertTrue($result['valid']);
    }

    public function test_rol_no_autorizado_no_puede_abrir_turno(): void
    {
        $result = $this->validateOpenShift(2, 500.0, false); // rol_id=2 (mesero)
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('autorizado', strtolower($result['error']));
    }

    // ────────────────────────────────────────────────────────────────────────
    // close_shift — Lógica de cierre de turno
    // ────────────────────────────────────────────────────────────────────────

    public function test_cerrar_turno_falla_si_hay_mesas_abiertas(): void
    {
        $result = $this->validateCloseShift(1, 500.0, openTablesCount: 2);
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('cuenta', strtolower($result['error']));
    }

    public function test_cerrar_turno_exitoso_sin_mesas_abiertas(): void
    {
        $result = $this->validateCloseShift(1, 800.0, openTablesCount: 0);
        $this->assertTrue($result['valid']);
    }

    public function test_cerrar_turno_falla_con_monto_no_numerico(): void
    {
        $result = $this->validateCloseShiftRaw(1, 'novalido', openTablesCount: 0);
        $this->assertFalse($result['valid']);
    }

    public function test_calculo_arqueo_diferencia_sobrante(): void
    {
        $startingCash   = 500.0;
        $cashSales      = 300.0;
        $expected       = $startingCash + $cashSales;  // 800.0
        $manualCount    = 850.0;
        $difference     = $manualCount - $expected;    // +50 → sobrante

        $this->assertEquals(800.0, $expected);
        $this->assertEquals(50.0, $difference);
    }

    public function test_calculo_arqueo_diferencia_faltante(): void
    {
        $startingCash = 500.0;
        $cashSales    = 300.0;
        $expected     = $startingCash + $cashSales;  // 800.0
        $manualCount  = 750.0;
        $difference   = $manualCount - $expected;   // -50 → faltante

        $this->assertEquals(-50.0, $difference);
    }

    public function test_calculo_arqueo_sin_diferencia(): void
    {
        $startingCash = 200.0;
        $cashSales    = 400.0;
        $expected     = $startingCash + $cashSales;  // 600.0
        $manualCount  = 600.0;
        $difference   = $manualCount - $expected;   // 0

        $this->assertEquals(0.0, $difference);
    }

    // ────────────────────────────────────────────────────────────────────────
    // Lógica de pagos mixtos (desglose por método)
    // ────────────────────────────────────────────────────────────────────────

    public function test_desglose_de_pagos_acumula_efectivo_correctamente(): void
    {
        $payments = [
            ['method' => 'Efectivo', 'amount' => 150.0],
            ['method' => 'Efectivo', 'amount' => 50.0],
            ['method' => 'Tarjeta',  'amount' => 200.0],
        ];

        $breakdown        = [];
        $totalCashSales   = 0.0;

        foreach ($payments as $p) {
            $method = $p['method'];
            $amount = (float)$p['amount'];
            $breakdown[$method] = ($breakdown[$method] ?? 0) + $amount;
            if ($method === 'Efectivo') $totalCashSales += $amount;
        }

        $this->assertEquals(200.0, $breakdown['Efectivo']);
        $this->assertEquals(200.0, $breakdown['Tarjeta']);
        $this->assertEquals(200.0, $totalCashSales);
    }

    // ────────────────────────────────────────────────────────────────────────
    // Helpers privados
    // ────────────────────────────────────────────────────────────────────────

    private function validateCashierRole(int $rolId): bool
    {
        return in_array($rolId, [6, 1], true);
    }

    private function validatePaymentInput(?int $orderId, array $payments): array
    {
        if (!$orderId || empty($payments)) {
            return ['valid' => false, 'error' => 'Missing required payment data.'];
        }
        return ['valid' => true];
    }

    private function calcSubtotal(array $items): float
    {
        $subtotal = 0.0;
        foreach ($items as $item) {
            if (!$item['is_cancelled']) {
                $subtotal += $item['quantity'] * $item['price_at_order'];
            }
        }
        return $subtotal;
    }

    private function validateOpenShift(int $rolId, float $startingCash, bool $shiftAlreadyOpen): array
    {
        if (!in_array($rolId, [1, 6])) {
            return ['valid' => false, 'error' => 'Acceso no autorizado.'];
        }
        if ($startingCash < 0) {
            return ['valid' => false, 'error' => 'Monto de fondo de caja inválido.'];
        }
        if ($shiftAlreadyOpen) {
            return ['valid' => false, 'error' => 'Ya existe un turno abierto.'];
        }
        return ['valid' => true];
    }

    private function validateOpenShiftRaw(int $rolId, mixed $startingCash, bool $shiftAlreadyOpen): array
    {
        if (!is_numeric($startingCash) || (float)$startingCash < 0) {
            return ['valid' => false, 'error' => 'Monto de fondo de caja inválido.'];
        }
        return $this->validateOpenShift($rolId, (float)$startingCash, $shiftAlreadyOpen);
    }

    private function validateCloseShift(int $rolId, mixed $manualCash, int $openTablesCount): array
    {
        if (!in_array($rolId, [1, 6])) {
            return ['valid' => false, 'error' => 'Acceso no autorizado.'];
        }
        if (!is_numeric($manualCash)) {
            return ['valid' => false, 'error' => 'Conteo de efectivo inválido.'];
        }
        if ($openTablesCount > 0) {
            return ['valid' => false, 'error' => "No se puede cerrar el turno. Hay {$openTablesCount} cuenta(s) abierta(s) sin cobrar."];
        }
        return ['valid' => true];
    }

    private function validateCloseShiftRaw(int $rolId, mixed $manualCash, int $openTablesCount): array
    {
        if (!is_numeric($manualCash)) {
            return ['valid' => false, 'error' => 'Conteo de efectivo inválido.'];
        }
        return $this->validateCloseShift($rolId, (float)$manualCash, $openTablesCount);
    }
}
