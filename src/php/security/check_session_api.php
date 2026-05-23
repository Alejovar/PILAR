<?php
// /src/php/security/check_session_api.php
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['lifetime'=>28800,'path'=>'/','httponly'=>true,'samesite'=>'Lax']);
    session_start();
}
require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/db_connection.php';

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok'=>false,'msg'=>'No autenticado.']);
    exit();
}
