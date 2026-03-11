<?php
/**
 * bootstrap.php
 * Configura el entorno global necesario para todos los tests de KitchenLink.
 * Crea mocks de las dependencias externas (sesión, servidor, conexión BD).
 */

// ─── Simular entorno de servidor ──────────────────────────────────────────────
$_SERVER['DOCUMENT_ROOT'] = __DIR__ . '/stubs';
$_SERVER['REQUEST_METHOD'] = 'POST';

// ─── Simular sesión autenticada por defecto (mesero) ─────────────────────────
$_SESSION = [
    'user_id'   => 1,
    'user_name' => 'Test User',
    'rol_id'    => 2, // 1=Gerente, 2=Mesero, 6=Cajero
];

// ─── Stub de check_session.php (no hace nada en tests) ───────────────────────
// Los archivos stub están en tests/stubs/src/php/security/
// y tests/stubs/src/php/

// ─── Autoload del helper de mocks ─────────────────────────────────────────────
require_once __DIR__ . '/helpers/MockConnection.php';
require_once __DIR__ . '/helpers/MockStatement.php';
require_once __DIR__ . '/helpers/MockResult.php';
