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

// ── Inyectar script anti-flash de tema ANTES del primer <head> ──
// Sin tocar ninguna página individual.
ob_start(function ($buffer) {
    $script = '<script>!function(){try{var t=localStorage.getItem("pilar_theme");"light"===t&&document.documentElement.setAttribute("data-theme","light")}catch(e){}}();</script>';
    return preg_replace('/<head(\s[^>]*)?>/', '$0' . $script, $buffer, 1);
});