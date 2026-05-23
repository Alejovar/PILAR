<?php
// /src/php/security/check_session.php
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 28800,
        'path'     => '/',
        'secure'   => false,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/db_connection.php';

// Verificar que haya sesión activa
if (empty($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit();
}
