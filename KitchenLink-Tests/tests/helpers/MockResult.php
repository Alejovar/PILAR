<?php
/**
 * MockResult.php
 *
 * Stub de mysqli_result para pruebas unitarias.
 */
class MockResult
{
    private ?array $data;
    public int $num_rows;

    public function __construct(?array $data)
    {
        $this->data = $data;

        if ($data === null) {
            $this->num_rows = 0;
        } elseif (isset($data['__num_rows__'])) {
            $this->num_rows = $data['__num_rows__'];
        } else {
            // Si es array de arrays (múltiples filas), contar
            $isMultiRow = isset($data[0]) && is_array($data[0]);
            $this->num_rows = $isMultiRow ? count($data) : 1;
        }
    }

    public function fetch_assoc(): ?array
    {
        if ($this->data === null || isset($this->data['__num_rows__'])) {
            return null;
        }
        // Si es array de arrays, devolver el primero
        if (isset($this->data[0]) && is_array($this->data[0])) {
            return $this->data[0];
        }
        return $this->data;
    }

    public function fetch_all(int $mode = MYSQLI_ASSOC): array
    {
        if ($this->data === null) return [];
        if (isset($this->data[0]) && is_array($this->data[0])) {
            return $this->data;
        }
        return [$this->data];
    }

    public function fetch_row(): ?array
    {
        if ($this->data === null) return null;
        return array_values($this->data);
    }
}
