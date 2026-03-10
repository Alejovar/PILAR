<?php
/**
 * MockConnection.php
 *
 * Stub de mysqli para pruebas unitarias.
 * Permite inyectar resultados de consultas sin necesidad de una BD real.
 *
 * Uso:
 *   $conn = new MockConnection();
 *   $conn->queueResult(['user_id' => 1, 'name' => 'Juan']); // simula fetch_assoc
 *   $conn->queueRowCount(1);                                 // simula num_rows
 */
class MockConnection
{
    /** @var array Cola de resultados a devolver en orden */
    private array $resultQueue = [];

    /** @var int Filas afectadas por la última operación */
    public int $affected_rows = 1;

    /** @var int ID del último INSERT simulado */
    public int $insert_id = 99;

    /** @var bool Si la conexión ha fallado */
    public bool $connect_errno = false;

    /** @var bool Si hay una transacción activa */
    public bool $in_transaction = false;

    /** @var bool Si el commit fue llamado */
    public bool $committed = false;

    /** @var bool Si el rollback fue llamado */
    public bool $rolledBack = false;

    /** @var string Último error de conexión */
    public string $error = '';

    // ── Cola de resultados ────────────────────────────────────────────────────

    /**
     * Encola un resultado de fila única (o array de filas para fetch_all).
     * @param array|null $data  null simula "no encontrado"
     */
    public function queueResult(?array $data): void
    {
        $this->resultQueue[] = $data;
    }

    /**
     * Encola un conteo de filas (num_rows).
     */
    public function queueRowCount(int $count): void
    {
        $this->resultQueue[] = ['__num_rows__' => $count];
    }

    // ── API de mysqli ─────────────────────────────────────────────────────────

    public function prepare(string $sql): MockStatement
    {
        return new MockStatement($this);
    }

    public function begin_transaction(): void
    {
        $this->in_transaction = true;
    }

    public function commit(): void
    {
        $this->committed = true;
        $this->in_transaction = false;
    }

    public function rollback(): void
    {
        $this->rolledBack = true;
        $this->in_transaction = false;
    }

    public function close(): void {}

    public function ping(): bool { return true; }

    // ── Dequeue interno ───────────────────────────────────────────────────────

    public function dequeueResult(): ?array
    {
        return array_shift($this->resultQueue);
    }
}
