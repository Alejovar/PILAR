<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Checador | KitchenLink</title>
  <link rel="icon" href="/src/images/logos/KitchenLink_logo.png" type="image/png" sizes="32x32">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" />
  <link rel="stylesheet" href="/src/css/style.css?v=20260511" />
  <link rel="stylesheet" href="/src/css/login_facial.css?v=20260511" />
  <style>
    body {
      min-height: 100vh;
      margin: 0;
      background:
        radial-gradient(circle at top left, rgba(127,0,255,0.20), transparent 35%),
        radial-gradient(circle at bottom right, rgba(16,185,129,0.14), transparent 30%),
        linear-gradient(135deg, #fbf7ff 0%, #f3f6ff 100%);
    }
    .checador-page-shell {
      min-height: 100vh;
      display: grid;
      place-items: center;
      padding: 18px;
    }
    .checador-topbar {
      width: min(100%, 820px);
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 12px;
      margin-bottom: 14px;
      color: #2c3e50;
    }
    .checador-brand {
      display: flex;
      align-items: center;
      gap: 12px;
    }
    .checador-brand img {
      width: 44px;
      height: 44px;
      object-fit: contain;
      filter: drop-shadow(0 10px 18px rgba(127,0,255,0.18));
    }
    .checador-top-actions a {
      text-decoration: none;
      color: #7f00ff;
      font-weight: 700;
      font-size: 14px;
    }
    #checadorSection {
      width: min(100%, 820px);
    }
    @media (max-width: 640px) {
      .checador-topbar {
        flex-direction: column;
        align-items: flex-start;
      }
    }
  </style>
</head>
<body>
  <div class="checador-page-shell">
    <div style="width:min(100%,820px);">
      <div class="checador-topbar">
        <div class="checador-brand">
          <img src="/src/images/logos/KitchenLink_logo.png" alt="KitchenLink" />
          <div>
            <div style="font-size:14px;color:#7f00ff;font-weight:800;letter-spacing:.08em;text-transform:uppercase;">KitchenLink</div>
            <div style="font-size:1.4rem;font-weight:800;line-height:1.1;">Checador de asistencia</div>
            <div style="font-size:13px;color:#6b7280;">Entrada, salida e historial por fecha desde un diseño pensado para móvil.</div>
          </div>
        </div>
        <div class="checador-top-actions">
          <a href="/login.php"><i class="fas fa-arrow-left"></i> Volver al login</a>
        </div>
      </div>
      <div id="checadorSection"></div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js"></script>
  <script src="/src/js/session_interceptor.js?v=20260511"></script>
  <script src="/src/js/checador_widget.js?v=20260511"></script>
</body>
</html>