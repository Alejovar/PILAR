<?php
/**
 * ReservationValidationTest.php
 *
 * Pruebas unitarias para la lógica de validación de:
 *   src/api/add_reservation.php
 *   src/api/update_reservation.php
 *
 * Estrategia: se extrae la lógica pura de validación en funciones
 * testeables sin ejecutar el endpoint completo (que requiere BD y sesión).
 *
 * Las reglas de negocio bajo prueba son:
 *  - Nombre solo letras y espacios
 *  - Número de personas entre 1 y 99
 *  - Teléfono opcional: máx 10 dígitos
 *  - No reservar en el pasado
 *  - Horario de operación: 08:00 - 22:00
 *  - Campos obligatorios no vacíos
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class ReservationValidationTest extends TestCase
{
    // ────────────────────────────────────────────────────────────────────────
    // Helpers que replican la lógica del endpoint
    // ────────────────────────────────────────────────────────────────────────

    private function validateName(string $name): bool
    {
        return (bool) preg_match('/^[a-zA-Z\s]+$/', $name);
    }

    private function validatePeople(string $people): bool
    {
        return (bool) preg_match('/^[0-9]{1,2}$/', $people) && (int)$people > 0;
    }

    private function validatePhone(string $phone): bool
    {
        if (empty($phone)) return true;  // Opcional
        return (bool) preg_match('/^[0-9]{1,10}$/', $phone);
    }

    private function validateDateTimeRange(string $date, string $time): array
    {
        $tz = new DateTimeZone('America/Mexico_City');
        try {
            $reservation = new DateTime("$date $time", $tz);
            $now         = new DateTime('now', $tz);
            $timeStr     = $reservation->format('H:i:s');

            if ($reservation < $now) {
                return ['valid' => false, 'error' => 'Fecha pasada'];
            }
            if ($timeStr < '08:00:00' || $timeStr > '22:00:00') {
                return ['valid' => false, 'error' => 'Fuera de horario'];
            }
            return ['valid' => true];
        } catch (Exception $e) {
            return ['valid' => false, 'error' => 'Formato inválido'];
        }
    }

    // ────────────────────────────────────────────────────────────────────────
    // Validación de nombre
    // ────────────────────────────────────────────────────────────────────────

    public function test_nombre_valido_solo_letras(): void
    {
        $this->assertTrue($this->validateName('Juan Garcia'));
    }

    public function test_nombre_invalido_con_numeros(): void
    {
        $this->assertFalse($this->validateName('Juan123'));
    }

    public function test_nombre_invalido_con_caracteres_especiales(): void
    {
        $this->assertFalse($this->validateName('Juan@García'));
    }

    public function test_nombre_invalido_vacio(): void
    {
        $this->assertFalse($this->validateName(''));
    }

    // ────────────────────────────────────────────────────────────────────────
    // Validación de número de personas
    // ────────────────────────────────────────────────────────────────────────

    public function test_personas_valido_uno(): void
    {
        $this->assertTrue($this->validatePeople('1'));
    }

    public function test_personas_valido_noventa_y_nueve(): void
    {
        $this->assertTrue($this->validatePeople('99'));
    }

    public function test_personas_invalido_cero(): void
    {
        $this->assertFalse($this->validatePeople('0'));
    }

    public function test_personas_invalido_mayor_dos_digitos(): void
    {
        $this->assertFalse($this->validatePeople('100'));
    }

    public function test_personas_invalido_texto(): void
    {
        $this->assertFalse($this->validatePeople('abc'));
    }

    // ────────────────────────────────────────────────────────────────────────
    // Validación de teléfono
    // ────────────────────────────────────────────────────────────────────────

    public function test_telefono_vacio_es_valido_es_opcional(): void
    {
        $this->assertTrue($this->validatePhone(''));
    }

    public function test_telefono_valido_10_digitos(): void
    {
        $this->assertTrue($this->validatePhone('8441234567'));
    }

    public function test_telefono_invalido_con_letras(): void
    {
        $this->assertFalse($this->validatePhone('844abc1234'));
    }

    public function test_telefono_invalido_mas_de_10_digitos(): void
    {
        $this->assertFalse($this->validatePhone('84412345678'));
    }

    // ────────────────────────────────────────────────────────────────────────
    // Validación de fecha y hora
    // ────────────────────────────────────────────────────────────────────────

    public function test_fecha_futura_en_horario_valido(): void
    {
        $futureDate = date('Y-m-d', strtotime('+7 days'));
        $result = $this->validateDateTimeRange($futureDate, '12:00');
        $this->assertTrue($result['valid']);
    }

    public function test_fecha_pasada_es_invalida(): void
    {
        $result = $this->validateDateTimeRange('2020-01-01', '12:00');
        $this->assertFalse($result['valid']);
        $this->assertEquals('Fecha pasada', $result['error']);
    }

    public function test_hora_antes_de_apertura_invalida(): void
    {
        $futureDate = date('Y-m-d', strtotime('+1 day'));
        $result = $this->validateDateTimeRange($futureDate, '07:00');
        $this->assertFalse($result['valid']);
        $this->assertEquals('Fuera de horario', $result['error']);
    }

    public function test_hora_despues_de_cierre_invalida(): void
    {
        $futureDate = date('Y-m-d', strtotime('+1 day'));
        $result = $this->validateDateTimeRange($futureDate, '23:00');
        $this->assertFalse($result['valid']);
        $this->assertEquals('Fuera de horario', $result['error']);
    }

    public function test_hora_exacta_apertura_es_valida(): void
    {
        $futureDate = date('Y-m-d', strtotime('+1 day'));
        $result = $this->validateDateTimeRange($futureDate, '08:00');
        $this->assertTrue($result['valid']);
    }

    public function test_hora_exacta_cierre_es_valida(): void
    {
        $futureDate = date('Y-m-d', strtotime('+1 day'));
        $result = $this->validateDateTimeRange($futureDate, '22:00');
        $this->assertTrue($result['valid']);
    }

    public function test_formato_fecha_invalido_retorna_error(): void
    {
        $result = $this->validateDateTimeRange('no-es-fecha', 'hora');
        $this->assertFalse($result['valid']);
    }

    // ────────────────────────────────────────────────────────────────────────
    // Validación de campos requeridos (simulando la lógica del endpoint)
    // ────────────────────────────────────────────────────────────────────────

    public function test_campos_obligatorios_todos_presentes(): void
    {
        $data = [
            'table_ids'        => [1, 2],
            'customer_name'    => 'Maria Lopez',
            'reservation_date' => date('Y-m-d', strtotime('+1 day')),
            'reservation_time' => '13:00',
            'number_of_people' => '4',
        ];

        $isValid = !empty($data['table_ids'])
            && !empty($data['customer_name'])
            && !empty($data['reservation_date'])
            && !empty($data['reservation_time'])
            && !empty($data['number_of_people']);

        $this->assertTrue($isValid);
    }

    public function test_campos_obligatorios_sin_mesas_es_invalido(): void
    {
        $data = [
            'table_ids'        => [],   // Sin mesas
            'customer_name'    => 'Maria Lopez',
            'reservation_date' => date('Y-m-d', strtotime('+1 day')),
            'reservation_time' => '13:00',
            'number_of_people' => '4',
        ];

        $isValid = !empty($data['table_ids']);
        $this->assertFalse($isValid);
    }

    public function test_campos_obligatorios_sin_nombre_es_invalido(): void
    {
        $data = [
            'table_ids'        => [1],
            'customer_name'    => '',   // Sin nombre
            'reservation_date' => date('Y-m-d', strtotime('+1 day')),
            'reservation_time' => '13:00',
            'number_of_people' => '4',
        ];

        $isValid = !empty($data['customer_name']);
        $this->assertFalse($isValid);
    }
}
