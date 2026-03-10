<?php
/**
 * MockConnection.php
 *
 * Stub de mysqli para pruebas unitarias.
 * Permite inyectar resultados de consultas sin necesidad de una BD real.
 */
class MockConnection extends mysqli
{
    /** @var array Cola de resultados a devolver en orden */
    private array $resultQueue = [];

    // 🟢 CORRECCIÓN: Tipos exactos de PHP 8.1 (int|string)
    /** @var int|string Filas afectadas por la última operación */
    public int|string $affected_rows = 1;

    /** @var int|string ID del último INSERT simulado */
    public int|string $insert_id = 99;

    // 🟢 CORRECCIÓN: connect_errno nativo es int, no bool
    /** @var int Si la conexión ha fallado */
    public int $connect_errno = 0;

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
    
    // 🟢 CORRECCIÓN: Atributo para evitar errores si devuelves un MockStatement
    #[\ReturnTypeWillChange]
    public function prepare(string $query)
    {
        return new MockStatement($this);
    }

    // 🟢 CORRECCIÓN: Las firmas de transacción en PHP 8.1 exigen parámetros y retorno bool
    public function begin_transaction(int $flags = 0, ?string $name = null): bool
    {
        $this->in_transaction = true;
        return true;
    }

    public function commit(int $flags = 0, ?string $name = null): bool
    {
        $this->committed = true;
        $this->in_transaction = false;
        return true;
    }

    public function rollback(int $flags = 0, ?string $name = null): bool
    {
        $this->rolledBack = true;
        $this->in_transaction = false;
        return true;
    }

    // 🟢 CORRECCIÓN: close y ping devuelven bool
    public function close(): bool 
    { 
        return true; 
    }

    public function ping(): bool 
    { 
        return true; 
    }

    // ── Dequeue interno ───────────────────────────────────────────────────────

    public function dequeueResult(): ?array
    {
        return array_shift($this->resultQueue);
    }
}
?>