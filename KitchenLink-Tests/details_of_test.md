# KitchenLink — Pruebas Unitarias del Backend

Suite de pruebas PHPUnit para todos los módulos de `src/api/`.

---

## Estructura

```
KitchenLink-Tests/
├── composer.json
├── phpunit.xml
└── tests/
    ├── bootstrap.php                   ← Configuración global (mocks de sesión, servidor)
    ├── helpers/
    │   ├── MockConnection.php          ← Stub de mysqli (sin BD real)
    │   ├── MockStatement.php           ← Stub de mysqli_stmt
    │   └── MockResult.php             ← Stub de mysqli_result
    ├── MenuModelTest.php               ← MenuModel (productos, modificadores, áreas)
    ├── ReservationValidationTest.php   ← add_reservation / update_reservation
    ├── WaitlistTest.php                ← add_to_waitlist / seat_client
    ├── SendOrderTest.php               ← send_order (stock, roles, transacción)
    ├── CashierTest.php                 ← process_payment / open_shift / close_shift
    ├── AdvancedOrderOptionsTest.php    ← cancel / change_server / change_table / move
    └── KitchenBarStatusTest.php        ← kitchen/update_item_status + bar/update_item_status
```

---

## Instalación

```bash
# 1. Instalar dependencias
composer install

# 2. Ejecutar todos los tests
./vendor/bin/phpunit

# 3. Ejecutar con salida legible (TestDox)
./vendor/bin/phpunit --testdox

# 4. Ejecutar solo un módulo
./vendor/bin/phpunit tests/CashierTest.php
./vendor/bin/phpunit tests/SendOrderTest.php
./vendor/bin/phpunit tests/MenuModelTest.php

# 5. Con reporte de cobertura (requiere Xdebug o PCOV)
./vendor/bin/phpunit --coverage-html coverage/
```

---

## Módulos cubiertos y casos de prueba

| Archivo de Test                  | Módulo API                                      | # Tests |
|----------------------------------|-------------------------------------------------|---------|
| `MenuModelTest`                  | `orders/tpv/MenuModel.php`                      | 10      |
| `ReservationValidationTest`      | `add_reservation.php`, `update_reservation.php` | 14      |
| `WaitlistTest`                   | `add_to_waitlist.php`, `seat_client.php`        | 9       |
| `SendOrderTest`                  | `orders/tpv/send_order.php`                     | 16      |
| `CashierTest`                    | `cashier/process_payment.php`, `open_shift.php`, `close_shift.php` | 20 |
| `AdvancedOrderOptionsTest`       | `execute_cancel.php`, `change_server.php`, `change_table.php`, `change_guest_count.php`, `execute_move.php` | 21 |
| `KitchenBarStatusTest`           | `kitchen/update_item_status.php`, `bar/update_item_status.php` | 14 |
| **Total**                        |                                                 | **~104**|

---

## Estrategia de pruebas

Los tests son **unitarios puros**: no requieren base de datos, servidor web ni sesión real.

### ¿Cómo funciona sin BD real?

Se usan clases `Mock*` en `tests/helpers/` que simulan `mysqli`:

```php
// En tu test:
$conn = $this->createMock(MockConnection::class);
$stmt = $this->createMock(MockStatement::class);
$conn->method('prepare')->willReturn($stmt);
$stmt->method('get_result')->willReturn(new MockResult(['user_id' => 1]));
```

### Reglas de negocio probadas

| Regla                                      | Test                          |
|--------------------------------------------|-------------------------------|
| Solo meseros/gerentes envían órdenes        | `SendOrderTest`               |
| Turno de caja debe estar abierto           | `AdvancedOrderOptionsTest`, `SendOrderTest` |
| Pre-bill solicitado bloquea nuevas órdenes | `SendOrderTest`               |
| Stock se descuenta y desactiva al llegar 0 | `SendOrderTest`               |
| Cancelación requiere razón ≥ 5 chars       | `AdvancedOrderOptionsTest`    |
| Subtotal excluye ítems cancelados          | `CashierTest`                 |
| IVA = 16% del subtotal                     | `CashierTest`                 |
| Arqueo de caja (diferencia)                | `CashierTest`                 |
| No cerrar turno con mesas abiertas         | `CashierTest`                 |
| Reservaciones solo 08:00–22:00             | `ReservationValidationTest`   |
| No reservar en el pasado                   | `ReservationValidationTest`   |
| Nombre solo letras y espacios              | `ReservationValidationTest`, `WaitlistTest` |
| Historial: COCINA→kitchen_history, BAR→bar_history | `KitchenBarStatusTest` |
| Estado de ítem: solo EN_PREPARACION o LISTO | `KitchenBarStatusTest`      |

---

## Integración en pipeline CI/CD

Agrega este paso a tu pipeline (GitHub Actions, GitLab CI, etc.):

```yaml
# .github/workflows/tests.yml
name: Backend Unit Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
          extensions: mysqli, mbstring
          coverage: xdebug

      - name: Install dependencies
        run: composer install --no-interaction
        working-directory: KitchenLink-Tests

      - name: Run tests
        run: ./vendor/bin/phpunit --testdox
        working-directory: KitchenLink-Tests
```

---

## Notas para el equipo

- **No edites** los archivos `helpers/Mock*.php` a menos que cambies la API de mysqli que usas.
- Cada archivo de test corresponde a un módulo del backend. Cuando agregues un endpoint nuevo, crea su test en un archivo separado.
- Los tests de integración (con BD real) deben ir en una carpeta `tests/integration/` separada para no mezclarlos con los unitarios.
