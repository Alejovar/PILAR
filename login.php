<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('MANAGER_ROLE_ID', 1);

// 1. Bloqueo de caché
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

// 2. Lógica de redirección por rol
if (isset($_SESSION['user_id']) && isset($_SESSION['rol_id'])) {
    $roleId = $_SESSION['rol_id'];
  if ((int)$roleId === MANAGER_ROLE_ID) {
    header('Location: /src/php/manager_dashboard.php');
    exit();
  }

  header('Location: /checador.php');
  exit();
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" />
  <link rel="stylesheet" href="/src/css/style.css?v=20260511" />
  <link rel="stylesheet" href="/src/css/login_facial.css?v=20260511" />
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
          <span>Ingresa con reconocimiento facial para acceder al sistema</span>

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
          </div>

          <!-- Campos manuales (ocultos por defecto si la cámara funciona) -->
          <div id="manualLoginArea" class="manual-login-area" style="display:none; width:100%;">
            <input type="text" placeholder="Nombre de usuario" name="user" id="user" />
            <input type="password" placeholder="Contraseña" name="password" id="password" />
            <div id="loginError" class="login-error"></div>
            <button type="submit">Iniciar Sesión</button>
          </div>

          <!-- Error facial -->
          <div id="faceLoginError" class="login-error" style="margin-top:8px;"></div>

          <a href="/checador.php" class="btn-switch-mode" style="text-decoration:none; margin-top:12px;">
            <i class="fas fa-clock"></i> Abrir checador de asistencia
          </a>

        </form>
      </div>

    </div>

    <!-- PANEL MORADO: Logo y toggle (doble panel para animacion) -->
    <div class="toggle-container">
      <div class="toggle">
        <div class="toggle-panel toggle-left">
          <h1>Acceso facial</h1>
          <img src="/src/images/logos/KitchenLink_logo.png" alt="Logo de KitchenLink"
            class="toggle-logo" />
          <p>El checador de entrada y salida ahora vive en una pantalla separada, optimizada para móvil.</p>
          <a href="/checador.php" class="btn-toggle-checador" style="text-decoration:none;">
            <i class="fas fa-clock"></i> Ir al checador
          </a>
        </div>
        <div class="toggle-panel toggle-right">
          <h1>Panel del gerente</h1>
          <img src="/src/images/logos/KitchenLink_logo.png" alt="Logo de KitchenLink"
            class="toggle-logo" />
          <p>Administra empleados, rostros, permisos y reportes para nómina.</p>
          <a href="/src/php/manager_dashboard.php" class="btn-toggle-checador" style="text-decoration:none;">
            <i class="fas fa-user-shield"></i> Abrir dashboard
          </a>
        </div>
      </div>
    </div>
  </div>

  <!-- face-api.js (CDN) -->
  <script src="https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js"></script>
  <script src="/src/js/session_interceptor.js?v=20260511"></script>
  <script src="/src/js/face_login.js?v=20260511"></script>
  <script src="/src/js/script.js?v=20260511"></script>
</body>

</html>
