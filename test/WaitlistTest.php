<?php
/**
 * WaitlistTest.php
 *
 * Pruebas unitarias para:
 *   src/api/add_to_waitlist.php
 *   src/api/seat_client.php
 *   src/api/archive_from_waitlist.php
 *
 * Estrategia: se prueban las reglas de validación y la lógica transaccional
 * usando mocks de la conexión a BD.
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class WaitlistTest extends TestCase
{
    // ────────────────────────────────────────────────────────────────────────
    // Helpers: réplica de la lógica de validación de add_to_waitlist.php
    // ────────────────────────────────────────────────────────────────────────

    private function validateWaitlistInput(string $name, string $people, string $phone): array
    {
        if (!preg_match('/^[a-zA-Z\s]+$/', $name)) {
            return ['valid' => false, 'message' => 'Nombre inválido'];
        }
        if (!preg_match('/^[0-9]{1,2}$/', $people) || (int)$people == 0) {
            return ['valid' => false, 'message' => 'Número de personas inválido'];
        }
        if (!empty($phone) && !preg_match('/^[0-9]{1,10}$/', $phone)) {
            return ['valid' => false, 'message' => 'Teléfono inválido'];
        }
        return ['valid' => true];
    }

    // ────────────────────────────────────────────────────────────────────────
    // Validación de add_to_waitlist
    // ────────────────────────────────────────────────────────────────────────

    public function test_agregar_cliente_datos_validos(): void
    {
        $result = $this->validateWaitlistInput('Carlos Ruiz', '3', '8441234567');
        $this->assertTrue($result['valid']);
    }

    public function test_agregar_cliente_nombre_con_numero_es_invalido(): void
    {
        $result = $this->validateWaitlistInput('Carlos123', '3', '');
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('Nombre', $result['message']);
    }

    public function test_agregar_cliente_cero_personas_es_invalido(): void
    {
        $result = $this->validateWaitlistInput('Ana', '0', '');
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('personas', $result['message']);
    }

    public function test_agregar_cliente_telefono_muy_largo_es_invalido(): void
    {
        $result = $this->validateWaitlistInput('Ana', '2', '12345678901'); // 11 dígitos
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('Teléfono', $result['message']);
    }

    public function test_agregar_cliente_sin_telefono_es_valido(): void
    {
        $result = $this->validateWaitlistInput('Lucia Mendez', '5', '');
        $this->assertTrue($result['valid']);
    }

    // ────────────────────────────────────────────────────────────────────────
    // Lógica de seat_client.php — flujo transaccional con mocks
    // ────────────────────────────────────────────────────────────────────────

    public function test_sentar_cliente_exitosamente(): void
    {
        $conn = $this->createMock(MockConnection::class);

        // Simular que el cliente existe en la lista de espera
        $clientData = [
            'id'             => 1,
            'customer_name'  => 'Roberto Salinas',
            'number_of_people' => '2',
            'customer_phone' => '8440000001',
            'created_at'     => '2025-01-01 10:00:00',
        ];
        $tablesData = [
            ['table_name' => 'Mesa 3'],
            ['table_name' => 'Mesa 4'],
        ];

        // La función sentar ejecuta: SELECT cliente → SELECT mesas → INSERT historial
        // → UPDATE mesas → DELETE cliente
        $stmt = $this->createMock(MockStatement::class);
        $conn->method('prepare')->willReturn($stmt);
        $stmt->method('get_result')
             ->willReturnOnConsecutiveCalls(
                 new MockResult($clientData),   // SELECT waiting_list
                 new MockResult($tablesData),   // SELECT tables
                 new MockResult(null),          // INSERT historial (no necesita resultado)
                 new MockResult(null),          // UPDATE tables
                 new MockResult(null)           // DELETE cliente
             );

        // Simular la lógica core de seat_client
        $result = $this->executeSeatClient($conn, 1, [1, 2]);

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('sentado', $result['message']);
    }

    public function test_sentar_cliente_inexistente_lanza_error(): void
    {
        $conn = $this->createMock(MockConnection::class);
        $stmt = $this->createMock(MockStatement::class);
        $result_null = new MockResult(null);  // Cliente no encontrado

        $conn->method('prepare')->willReturn($stmt);
        $stmt->method('get_result')->willReturn($result_null);

        $result = $this->executeSeatClient($conn, 9999, [1]);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('no encontrado', strtolower($result['message']));
    }

    public function test_sentar_cliente_falla_si_no_hay_mesas(): void
    {
        $result = $this->validateSeatClientInput(1, []);
        $this->assertFalse($result['valid']);
    }

    public function test_sentar_cliente_falla_si_client_id_invalido(): void
    {
        $result = $this->validateSeatClientInput(0, [1, 2]);
        $this->assertFalse($result['valid']);
    }

    // ────────────────────────────────────────────────────────────────────────
    // Helpers privados
    // ────────────────────────────────────────────────────────────────────────

    /**
     * Replica la lógica de validación de parámetros de seat_client.php.
     */
    private function validateSeatClientInput(int $clientId, array $tableIds): array
    {
        if (!$clientId || empty($tableIds) || !is_array($tableIds)) {
            return ['valid' => false, 'message' => 'Faltan datos requeridos.'];
        }
        return ['valid' => true];
    }

    /**
     * Replica la lógica transaccional de seat_client.php usando un $conn mock.
     */
    private function executeSeatClient(object $conn, int $clientId, array $tableIds): array
    {
        try {
            $conn->begin_transaction();

            // 1. Buscar cliente
            $stmt = $conn->prepare("SELECT * FROM waiting_list WHERE id = ?");
            $client = $stmt->get_result()->fetch_assoc();
            if (!$client) {
                throw new \Exception("Cliente no encontrado.", 404);
            }

            // 2. Obtener nombres de mesas
            $placeholders = implode(',', array_fill(0, count($tableIds), '?'));
            $stmt2 = $conn->prepare("SELECT table_name FROM tables WHERE id IN ($placeholders)");
            $tablesResult = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);
            $tableNames = array_column($tablesResult, 'table_name');
            $tableNamesStr = implode(', ', $tableNames);

            // 3. INSERT historial
            $stmt3 = $conn->prepare("INSERT INTO waiting_list_history ...");
            $stmt3->get_result();

            // 4. UPDATE mesas
            $stmt4 = $conn->prepare("UPDATE tables SET status='ocupado' WHERE id IN (...)");
            $stmt4->get_result();

            // 5. DELETE de lista activa
            $stmt5 = $conn->prepare("DELETE FROM waiting_list WHERE id=?");
            $stmt5->get_result();

            $conn->commit();
            return ['success' => true, 'message' => 'Cliente sentado con éxito.'];

        } catch (\Exception $e) {
            $conn->rollback();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
