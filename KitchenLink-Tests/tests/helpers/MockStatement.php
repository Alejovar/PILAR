<?php
/**
 * MockStatement.php
 *
 * Stub de mysqli_stmt para pruebas unitarias.
 * Consume resultados de la cola de MockConnection.
 */
class MockStatement
{
    private MockConnection $conn;

    public int $affected_rows = 1;
    public int $num_rows      = 0;
    public string $error      = '';

    private ?array $pendingResult = null;

    public function __construct(MockConnection $conn)
    {
        $this->conn = $conn;
    }

    // Métodos de mysqli_stmt —————————————————————————————————————————————————

    public function bind_param(string $types, mixed &...$vars): bool { return true; }

    public function execute(): bool { return true; }

    public function store_result(): bool { return true; }

    public function get_result(): MockResult
    {
        $data = $this->conn->dequeueResult();
        return new MockResult($data);
    }

    public function bind_result(mixed &...$vars): bool { return true; }

    public function fetch(): ?bool { return null; }

    public function close(): void {}
}
