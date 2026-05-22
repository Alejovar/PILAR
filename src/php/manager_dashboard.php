<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/security/check_session.php';

define('MANAGER_ROLE_ID', 1);

if (!isset($_SESSION['rol_id']) || $_SESSION['rol_id'] != MANAGER_ROLE_ID) {
    if (isset($conn) && isset($_SESSION['user_id'])) {
        try {
            $clean_stmt = $conn->prepare("UPDATE users SET session_token = NULL WHERE id = ?");
            $clean_stmt->bind_param("i", $_SESSION['user_id']);
            $clean_stmt->execute();
            $clean_stmt->close();
        } catch (Throwable $e) {}
    }

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_unset();
        session_destroy();
    }

    header('Location: /login.php');
    exit();
}

$userName = htmlspecialchars($_SESSION['user_name'] ?? 'Gerente');
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Panel de Administración | KitchenLink</title>
  <link rel="icon" href="/src/images/logos/KitchenLink_logo.png" type="image/png" sizes="32x32">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap');
    * { box-sizing: border-box; }
    body { margin: 0; font-family: 'Montserrat', sans-serif; background: linear-gradient(135deg, #f8f5ff 0%, #eef4ff 100%); color: #1f2937; }
    .shell { min-height: 100vh; display: grid; grid-template-columns: 280px 1fr; }
    .sidebar { background: rgba(255,255,255,0.78); backdrop-filter: blur(16px); border-right: 1px solid rgba(127,0,255,0.08); padding: 24px 20px; display: flex; flex-direction: column; justify-content: space-between; }
    .sidebar h2 { margin: 0 0 18px; font-size: 18px; }
    .nav { display: grid; gap: 10px; }
    .nav a { text-decoration: none; color: #374151; padding: 12px 14px; border-radius: 14px; background: rgba(255,255,255,0.7); border: 1px solid rgba(127,0,255,0.08); font-weight: 600; display: flex; gap: 10px; align-items: center; }
    .nav a.active { background: linear-gradient(135deg, #7f00ff, #a855f7); color: #fff; border-color: transparent; }
    .user-card { margin-top: 18px; padding-top: 18px; border-top: 1px solid rgba(0,0,0,0.08); display: flex; align-items: center; justify-content: space-between; gap: 10px; }
    .user-meta { min-width: 0; }
    .user-meta strong { display:block; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .logout { width: 38px; height: 38px; border-radius: 12px; display: grid; place-items: center; text-decoration: none; color: #dc2626; background: rgba(220,38,38,0.08); }
    .main { padding: 26px; }
    .hero { background: rgba(255,255,255,0.8); border: 1px solid rgba(127,0,255,0.08); box-shadow: 0 20px 40px rgba(25, 15, 60, 0.06); border-radius: 24px; padding: 28px; display: grid; gap: 16px; }
    .hero-top { display: flex; justify-content: space-between; align-items: start; gap: 16px; }
    .eyebrow { color: #7f00ff; font-size: 12px; font-weight: 800; letter-spacing: .12em; text-transform: uppercase; }
    .hero h1 { margin: 6px 0 8px; font-size: clamp(2rem, 4vw, 3.2rem); line-height: 1; }
    .hero p { margin: 0; color: #4b5563; max-width: 62ch; }
    .clock { font-size: 16px; font-weight: 800; color: #7f00ff; background: rgba(127,0,255,0.08); padding: 10px 14px; border-radius: 999px; white-space: nowrap; }
    .grid { margin-top: 22px; display: grid; grid-template-columns: repeat(12, 1fr); gap: 16px; }
    .card { grid-column: span 4; background: rgba(255,255,255,0.86); border: 1px solid rgba(127,0,255,0.08); border-radius: 22px; padding: 20px; box-shadow: 0 14px 30px rgba(25, 15, 60, 0.05); text-decoration: none; color: inherit; display: block; }
    .card i { font-size: 28px; color: #7f00ff; }
    .card h3 { margin: 14px 0 8px; font-size: 18px; }
    .card p { margin: 0; color: #6b7280; line-height: 1.5; }
    .wide { grid-column: span 8; }
    .meta-strip { display: flex; gap: 12px; flex-wrap: wrap; margin-top: 8px; }
    .pill { background: rgba(127,0,255,0.08); color: #5b21b6; border-radius: 999px; padding: 8px 12px; font-size: 12px; font-weight: 700; }
    @media (max-width: 980px) { .shell { grid-template-columns: 1fr; } .sidebar { border-right: 0; border-bottom: 1px solid rgba(127,0,255,0.08); } .card, .wide { grid-column: span 12; } }
  </style>
</head>
<body>
  <div class="shell">
    <aside class="sidebar">
      <div>
        <h2>Administración</h2>
        <nav class="nav">
          <a href="#" class="active"><i class="fas fa-th-large"></i> Inicio</a>
          <a href="manager_users.php"><i class="fas fa-users-cog"></i> Usuarios</a>
          <a href="manager_checador.php"><i class="fas fa-clock"></i> Reportes de asistencia</a>
          <a href="/checador.php"><i class="fas fa-mobile-alt"></i> Checador móvil</a>
        </nav>
      </div>

      <div class="user-card">
        <div class="user-meta">
          <strong><?php echo $userName; ?></strong>
          <small>Panel del gerente</small>
        </div>
        <a href="/src/php/logout.php" class="logout" title="Cerrar sesión"><i class="fas fa-sign-out-alt"></i></a>
      </div>
    </aside>

    <main class="main">
      <section class="hero">
        <div class="hero-top">
          <div>
            <div class="eyebrow">KitchenLink Administración</div>
            <h1>Usuarios, permisos y nómina</h1>
            <p>Este panel centraliza el alta de personal, rostros, permisos especiales y la consulta de entradas y salidas para exportar a Excel o al ERP externo con NSS como identificador principal.</p>
            <div class="meta-strip">
              <span class="pill">NSS como ID</span>
              <span class="pill">Plantas múltiples</span>
              <span class="pill">Retardo 8:01</span>
              <span class="pill">Falta 8:15</span>
            </div>
          </div>
          <div id="liveClockContainer" class="clock">--:--:--</div>
        </div>
      </section>

      <section class="grid">
        <a class="card wide" href="manager_users.php">
          <i class="fas fa-user-plus"></i>
          <h3>Registrar empleados y rostros</h3>
          <p>Da de alta personal con NSS, planta, salario por día, impuestos y captura facial desde una sola pantalla.</p>
        </a>

        <a class="card" href="manager_checador.php">
          <i class="fas fa-file-export"></i>
          <h3>Reportes de asistencia</h3>
          <p>Filtra por usuario, fechas y exporta la asistencia con retardo, permisos y horas extra.</p>
        </a>

        <a class="card" href="/checador.php">
          <i class="fas fa-mobile-screen-button"></i>
          <h3>Checador móvil</h3>
          <p>Abre la versión separada para entrada y salida, optimizada para usuarios en dispositivo móvil.</p>
        </a>
      </section>
    </main>
  </div>

  <script>
    (function updateClock() {
      const el = document.getElementById('liveClockContainer');
      if (el) {
        const now = new Date();
        el.textContent = now.toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
      }
      setTimeout(updateClock, 1000);
    })();
  </script>
</body>
</html>