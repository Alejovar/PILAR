<?php
/**
 * MenuModelTest.php
 *
 * Pruebas unitarias para: src/api/orders/tpv/MenuModel.php
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../src/api/orders/tpv/MenuModel.php';

// Stub personalizado ultra-simple para evadir los chequeos estrictos de createMock
class EmptyResultStub {
    public function fetch_all() { return []; }
    public function fetch_assoc() { return null; }
}

class GroupNameResultStub {
    public function fetch_assoc() { return ['group_name' => 'Vacío']; }
}

class StatementStub {
    private $result;
    public function __construct($result = null) { $this->result = $result; }
    public function bind_param() { return true; }
    public function execute() { return true; }
    public function get_result() { return $this->result; }
    public function close() { return true; }
}

class BrokenConnectionStub extends mysqli {
    public string $error = "Simulated error";
    #[\ReturnTypeWillChange]
    public function prepare($query) { return false; }
    public function close(): bool { return true; }
}

class SuccessConnectionStub extends mysqli {
    private $stmt;
    public function __construct($stmt) { $this->stmt = $stmt; }
    #[\ReturnTypeWillChange]
    public function prepare($query) {
        // Si pasamos un array de statements, los sacamos en orden
        if (is_array($this->stmt)) {
            return array_shift($this->stmt);
        }
        return $this->stmt;
    }
    public function close(): bool { return true; }
}

class MenuModelTest extends TestCase
{
    // ────────────────────────────────────────────────────────────────────────
    // getProductsByCategory
    // ────────────────────────────────────────────────────────────────────────

    public function test_getProductsByCategory_devuelve_productos_de_la_categoria(): void
    {
        $fakeProducts = [
            ['product_id' => 1, 'name' => 'Tacos', 'price' => 80.0, 'modifier_group_id' => null,
             'is_available' => 1, 'stock_quantity' => null, 'preparation_area' => 'COCINA'],
            ['product_id' => 2, 'name' => 'Agua', 'price'  => 30.0, 'modifier_group_id' => null,
             'is_available' => 1, 'stock_quantity' => 5,    'preparation_area' => 'BAR'],
        ];

        // Este test pasa bien con los mocks de PHPUnit
        $conn = $this->createMock(MockConnection::class);
        $stmt = $this->createMock(MockStatement::class);
        $result = new MockResult($fakeProducts);

        $conn->method('prepare')->willReturn($stmt);
        $stmt->method('get_result')->willReturn($result);

        $model = new MenuModel($conn);
        $products = $model->getProductsByCategory(1);

        $this->assertCount(2, $products);
        $this->assertEquals('Tacos', $products[0]['name']);
    }

    public function test_getProductsByCategory_devuelve_array_vacio_sin_productos(): void
    {
        // 🟢 FIX: Usamos stubs manuales
        $stmt = new StatementStub(new EmptyResultStub());
        $conn = new SuccessConnectionStub($stmt);

        $model = new MenuModel($conn);
        $products = $model->getProductsByCategory(999);

        $this->assertIsArray($products);
        $this->assertEmpty($products);
    }

    public function test_getProductsByCategory_lanza_excepcion_si_prepare_falla(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches('/Error de BD/');

        // 🟢 FIX: Usamos stub manual que fuerza falla en prepare y evita el error "already closed"
        $conn = new BrokenConnectionStub();
        $model = new MenuModel($conn);
        $model->getProductsByCategory(1);
    }

    // ────────────────────────────────────────────────────────────────────────
    // getModifiersByGroup
    // ────────────────────────────────────────────────────────────────────────

    public function test_getModifiersByGroup_retorna_modificadores_y_nombre_grupo(): void
    {
        $fakeModifiers = [
            ['modifier_id' => 1, 'modifier_name' => 'Con queso', 'modifier_price' => 10.0,
             'is_active' => 1, 'stock_quantity' => null],
        ];
        $fakeGroupName = ['group_name' => 'Extras'];

        $conn   = $this->createMock(MockConnection::class);
        $stmt1  = $this->createMock(MockStatement::class);
        $stmt2  = $this->createMock(MockStatement::class);
        $result1 = new MockResult($fakeModifiers);
        $result2 = new MockResult($fakeGroupName);

        $conn->method('prepare')->willReturnOnConsecutiveCalls($stmt1, $stmt2);
        $stmt1->method('get_result')->willReturn($result1);
        $stmt2->method('get_result')->willReturn($result2);

        $model  = new MenuModel($conn);
        $output = $model->getModifiersByGroup(1);

        $this->assertEquals('Extras', $output['group_name']);
        $this->assertCount(1, $output['modifiers']);
    }

    public function test_getModifiersByGroup_retorna_lista_vacia_si_no_hay_modificadores(): void
    {
        // 🟢 FIX: Stubs manuales para listas múltiples
        $stmt1 = new StatementStub(new EmptyResultStub()); // Para modifiers
        $stmt2 = new StatementStub(new GroupNameResultStub()); // Para group name
        
        $conn = new SuccessConnectionStub([$stmt1, $stmt2]);

        $model  = new MenuModel($conn);
        $output = $model->getModifiersByGroup(5);

        $this->assertEmpty($output['modifiers']);
        $this->assertEquals('Vacío', $output['group_name']);
    }

    public function test_getModifiersByGroup_retorna_fallback_si_prepare_lanza_error(): void
    {
        // 🟢 FIX: Stub manual roto
        $conn = new BrokenConnectionStub();

        $model  = new MenuModel($conn);
        $output = $model->getModifiersByGroup(1);

        $this->assertEmpty($output['modifiers']);
        $this->assertEquals('Error de Conexión', $output['group_name']);
    }

    // ────────────────────────────────────────────────────────────────────────
    // getPreparationAreaByProductId
    // ────────────────────────────────────────────────────────────────────────

    public function test_getPreparationArea_retorna_COCINA_para_producto_de_cocina(): void
    {
        $conn   = $this->createMock(MockConnection::class);
        $stmt   = $this->createMock(MockStatement::class);
        $result = new MockResult(['preparation_area' => 'COCINA']);

        $conn->method('prepare')->willReturn($stmt);
        $stmt->method('get_result')->willReturn($result);

        $model = new MenuModel($conn);
        $area  = $model->getPreparationAreaByProductId(1);

        $this->assertEquals('COCINA', $area);
    }

    public function test_getPreparationArea_retorna_BAR_para_producto_de_bar(): void
    {
        $conn   = $this->createMock(MockConnection::class);
        $stmt   = $this->createMock(MockStatement::class);
        $result = new MockResult(['preparation_area' => 'BAR']);

        $conn->method('prepare')->willReturn($stmt);
        $stmt->method('get_result')->willReturn($result);

        $model = new MenuModel($conn);
        $area  = $model->getPreparationAreaByProductId(7);

        $this->assertEquals('BAR', $area);
    }

    public function test_getPreparationArea_retorna_COCINA_por_defecto_si_no_se_encuentra(): void
    {
        // 🟢 FIX: Stub manual
        $stmt = new StatementStub(new EmptyResultStub()); // fetch_assoc retorna null
        $conn = new SuccessConnectionStub($stmt);

        $model = new MenuModel($conn);
        $area  = $model->getPreparationAreaByProductId(9999);

        $this->assertEquals('COCINA', $area);
    }

    public function test_getPreparationArea_lanza_excepcion_si_prepare_falla(): void
    {
        $this->expectException(Exception::class);

        // 🟢 FIX: Stub manual roto
        $conn = new BrokenConnectionStub();

        $model = new MenuModel($conn);
        $model->getPreparationAreaByProductId(1);
    }
}