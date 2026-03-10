<?php
/**
 * MenuModelTest.php
 *
 * Pruebas unitarias para: src/api/orders/tpv/MenuModel.php
 *
 * Cubre:
 *  - getProductsByCategory()  → productos disponibles por categoría
 *  - getModifiersByGroup()    → modificadores y nombre de grupo
 *  - getPreparationAreaByProductId() → área de preparación (COCINA / BAR)
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

// Incluye la clase real bajo prueba
require_once __DIR__ . '/../../src/api/orders/tpv/MenuModel.php';

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

        $conn = $this->createMock(MockConnection::class);
        $stmt = $this->createMock(MockStatement::class);
        $result = new MockResult($fakeProducts);

        $conn->method('prepare')->willReturn($stmt);
        $stmt->method('get_result')->willReturn($result);

        $model = new MenuModel($conn);
        $products = $model->getProductsByCategory(1);

        $this->assertCount(2, $products);
        $this->assertEquals('Tacos', $products[0]['name']);
        $this->assertEquals('BAR', $products[1]['preparation_area']);
    }

    public function test_getProductsByCategory_devuelve_array_vacio_sin_productos(): void
    {
        $conn = $this->createMock(MockConnection::class);
        $stmt = $this->createMock(MockStatement::class);
        $result = new MockResult([]);  // Sin filas

        $conn->method('prepare')->willReturn($stmt);
        $stmt->method('get_result')->willReturn($result);

        $model = new MenuModel($conn);
        $products = $model->getProductsByCategory(999);

        $this->assertIsArray($products);
        $this->assertEmpty($products);
    }

    public function test_getProductsByCategory_lanza_excepcion_si_prepare_falla(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches('/Error de BD/');

        $conn = $this->createMock(MockConnection::class);
        $conn->method('prepare')->willReturn(false);  // Simula fallo de prepare()

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

        // prepare() se llama dos veces: para modifiers y para group_name
        $conn->method('prepare')->willReturnOnConsecutiveCalls($stmt1, $stmt2);
        $stmt1->method('get_result')->willReturn($result1);
        $stmt2->method('get_result')->willReturn($result2);

        $model  = new MenuModel($conn);
        $output = $model->getModifiersByGroup(1);

        $this->assertArrayHasKey('modifiers', $output);
        $this->assertArrayHasKey('group_name', $output);
        $this->assertEquals('Extras', $output['group_name']);
        $this->assertCount(1, $output['modifiers']);
        $this->assertEquals('Con queso', $output['modifiers'][0]['modifier_name']);
    }

    public function test_getModifiersByGroup_retorna_lista_vacia_si_no_hay_modificadores(): void
    {
        $conn   = $this->createMock(MockConnection::class);
        $stmt1  = $this->createMock(MockStatement::class);
        $stmt2  = $this->createMock(MockStatement::class);
        $result1 = new MockResult([]);
        $result2 = new MockResult(['group_name' => 'Vacío']);

        $conn->method('prepare')->willReturnOnConsecutiveCalls($stmt1, $stmt2);
        $stmt1->method('get_result')->willReturn($result1);
        $stmt2->method('get_result')->willReturn($result2);

        $model  = new MenuModel($conn);
        $output = $model->getModifiersByGroup(5);

        $this->assertEmpty($output['modifiers']);
        $this->assertEquals('Vacío', $output['group_name']);
    }

    public function test_getModifiersByGroup_retorna_fallback_si_prepare_lanza_error(): void
    {
        $conn = $this->createMock(MockConnection::class);
        $conn->error = 'Error SQL simulado';
        $conn->method('prepare')->willReturn(false);

        $model  = new MenuModel($conn);
        $output = $model->getModifiersByGroup(1);

        // Debe devolver estructura vacía, no lanzar excepción
        $this->assertArrayHasKey('modifiers', $output);
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
        $conn   = $this->createMock(MockConnection::class);
        $stmt   = $this->createMock(MockStatement::class);
        $result = new MockResult(null);  // Producto no existe → fetch_assoc() = null

        $conn->method('prepare')->willReturn($stmt);
        $stmt->method('get_result')->willReturn($result);

        $model = new MenuModel($conn);
        $area  = $model->getPreparationAreaByProductId(9999);

        $this->assertEquals('COCINA', $area);
    }

    public function test_getPreparationArea_lanza_excepcion_si_prepare_falla(): void
    {
        $this->expectException(Exception::class);

        $conn = $this->createMock(MockConnection::class);
        $conn->error = 'Error SQL';
        $conn->method('prepare')->willReturn(false);

        $model = new MenuModel($conn);
        $model->getPreparationAreaByProductId(1);
    }
}
