<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Definiciones de Roles (IDs según tu DB)
define('MESERO_ROLE_ID', 2);
define('COCINA_ROLE_ID', 3);
define('HOSTESS_ROLE_ID', 4);
define('BARRA_ROLE_ID', 5);
define('CASHIER_ROLE_ID', 6);
define('MANAGER_ROLE_ID', 1);

// 1. Bloqueo de caché
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

// 2. Lógica de redirección por rol
if (isset($_SESSION['user_id']) && isset($_SESSION['rol_id'])) {
    $roleId = $_SESSION['rol_id'];
    $destination = '';
    switch ($roleId) {
        case MESERO_ROLE_ID:    $destination = '/src/php/orders.php'; break;
        case COCINA_ROLE_ID:    $destination = '/src/php/kitchen_orders.php'; break;
        case HOSTESS_ROLE_ID:   $destination = '/src/php/reservations.php'; break;
        case BARRA_ROLE_ID:     $destination = '/src/php/bar_orders.php'; break;
        case CASHIER_ROLE_ID:   $destination = '/src/php/cashier.php'; break;
        case MANAGER_ROLE_ID:   $destination = '/src/php/manager_dashboard.php'; break;
        default:
            session_unset();
            session_destroy();
            break;
    }
    if (!empty($destination)) {
        header('Location: ' . $destination);
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" />
  <link rel="stylesheet" href="/src/css/style.css" />
  <link rel="stylesheet" href="/src/css/login_facial.css" />
  <title>Login | KitchenLink</title>
  <link rel="icon" href="/src/images/logos/KitchenLink_logo.png" type="image/png" sizes="32x32">
</head>

<body>
  <!-- ===== CONTENEDOR PRINCIPAL LOGIN ===== -->
  <div class="container" id="container">

    <!-- PANEL IZQUIERDO: Formulario (Login o Checador) -->
    <div class="form-container sign-in" id="leftPanel">

      <!-- ---- MODO: INICIAR SESIÓN ---- -->
      <div id="loginSection">
        <form id="loginForm" action="/src/php/login_handler.php" method="POST">
          <h1>Iniciar Sesión</h1>
          <span>Ingresa tu nombre de usuario y contraseña</span>

          <!-- Cámara de reconocimiento facial -->
          <div id="faceLoginArea" class="face-login-area">
            <div class="face-video-wrapper">
              <video id="faceLoginVideo" autoplay muted playsinline></video>
              <canvas id="faceLoginCanvas"></canvas>
              <div id="faceLoginStatus" class="face-status">
                <i class="fas fa-camera"></i>
                <span>Iniciando cámara...</span>
              </div>
            </div>
            <p class="face-hint">Mira la cámara para ingresar automáticamente</p>
            <button type="button" id="btnSwitchToPassword" class="btn-switch-mode">
              <i class="fas fa-keyboard"></i> Usar usuario y contraseña
            </button>
          </div>

          <!-- Campos manuales (ocultos por defecto si la cámara funciona) -->
          <div id="manualLoginArea" class="manual-login-area" style="display:none; width:100%;">
            <input type="text" placeholder="Nombre de usuario" name="user" id="user" />
            <input type="password" placeholder="Contraseña" name="password" id="password" />
            <div id="loginError" class="login-error"></div>
            <button type="submit">Iniciar Sesión</button>
            <button type="button" id="btnSwitchToFace" class="btn-switch-mode" style="margin-top:8px;">
              <i class="fas fa-face-smile"></i> Usar reconocimiento facial
            </button>
          </div>

          <!-- Error facial -->
          <div id="faceLoginError" class="login-error" style="margin-top:8px;"></div>

        </form>
      </div>

      <!-- ---- MODO: CHECADOR (se inyecta dinámicamente por JS) ---- -->
      <div id="checadorSection" style="display:none; width:100%; height:100%;"></div>

      <div class="left-panel-toggle">
        <button type="button" class="btn-toggle-checador btn-toggle-checador-inline">
          <i class="fas fa-clock"></i> Ir al Checador
        </button>
        <button type="button" class="btn-toggle-checador btn-toggle-checador-back">
          <i class="fas fa-sign-in-alt"></i> Volver al Login
        </button>
      </div>

    </div>

    <!-- PANEL MORADO: Logo y toggle (doble panel para animacion) -->
    <div class="toggle-container">
      <div class="toggle">
        <div class="toggle-panel toggle-left">
          <h1>Checador de Asistencia</h1>
          <img src="/src/images/logos/KitchenLink_logo.png" alt="Logo de KitchenLink"
            class="toggle-logo" />
          <p>Registra tu entrada y salida de forma rapida.</p>
          <button type="button" class="btn-toggle-checador">
            <i class="fas fa-sign-in-alt"></i> Volver al Login
          </button>
        </div>
        <div class="toggle-panel toggle-right">
          <h1>¡Bienvenido a KitchenLink!</h1>
          <img src="/src/images/logos/KitchenLink_logo.png" alt="Logo de KitchenLink"
            class="toggle-logo" />
          <p>Accede con reconocimiento facial o usuario.</p>
          <button type="button" class="btn-toggle-checador">
            <i class="fas fa-clock"></i> Ir al Checador
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- face-api.js (CDN) -->
  <script src="https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js"></script>
  <script src="/src/js/session_interceptor.js"></script>
  <script src="/src/js/face_login.js"></script>
  <script src="/src/js/checador_widget.js"></script>
  <script src="/src/js/script.js"></script>
</body>

</html>
