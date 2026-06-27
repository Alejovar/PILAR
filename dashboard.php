<?php
// /dashboard.php
require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/security/check_session.php';

if ($_SESSION['rol'] !== 'admin') {
    header('Location: /login.php?error=sin_acceso');
    exit();
}

$userName = htmlspecialchars($_SESSION['user_name'] ?? 'Administrador');
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Panel de Control | PILAR</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="/src/css/pilar.css">
</head>
<body>
<div class="shell">

  <!-- SIDEBAR -->
  <?php include $_SERVER['DOCUMENT_ROOT'] . '/src/php/sidebar.php'; ?>

  <!-- MAIN -->
  <main class="main-content">

    <div class="page-header">
      <div class="page-header-left">
        <div class="page-eyebrow">PILAR</div>
        <h1 class="page-title">Panel de Control</h1>
        <p class="page-sub">Gestión de plantas, empleados e historial de asistencia.</p>
      </div>
      <div class="clock-badge" id="liveClock">--:--:--</div>
    </div>

    <!-- Stats rápidas -->
    <div class="stat-grid" id="quickStats">
      <div class="stat-card">
        <span class="stat-label">Plantas activas</span>
        <span class="stat-value" id="stPlantas">—</span>
        <span class="stat-hint">Instalaciones registradas</span>
      </div>
      <div class="stat-card green">
        <span class="stat-label">Empleados activos</span>
        <span class="stat-value" id="stEmpleados">—</span>
        <span class="stat-hint">Personal en nómina</span>
      </div>
      <div class="stat-card">
        <span class="stat-label">Checadas hoy</span>
        <span class="stat-value" id="stChecadas">—</span>
        <span class="stat-hint">Registros del día</span>
      </div>
    </div>

    <!-- Módulos -->
    <div style="display:grid; grid-template-columns: repeat(auto-fit,minmax(280px,1fr)); gap:16px;">

      <a href="/src/php/plantas.php" class="card" style="text-decoration:none;display:block;transition:transform 0.2s,box-shadow 0.2s;" onmouseover="this.style.transform='translateY(-4px)';this.style.boxShadow='0 16px 40px rgba(0,0,0,0.5)'" onmouseout="this.style.transform='';this.style.boxShadow=''">
        <i class="fas fa-industry" style="font-size:2rem;color:var(--primary);"></i>
        <h3 style="margin:14px 0 8px;font-size:17px;">Plantas</h3>
        <p style="color:var(--text-muted);font-size:13px;line-height:1.6;">Alta, edición y búsqueda de plantas / instalaciones con nombre, código y ubicación.</p>
      </a>

      <a href="/src/php/empleados.php" class="card" style="text-decoration:none;display:block;transition:transform 0.2s,box-shadow 0.2s;" onmouseover="this.style.transform='translateY(-4px)';this.style.boxShadow='0 16px 40px rgba(0,0,0,0.5)'" onmouseout="this.style.transform='';this.style.boxShadow=''">
        <i class="fas fa-users-cog" style="font-size:2rem;color:var(--accent);"></i>
        <h3 style="margin:14px 0 8px;font-size:17px;">Empleados</h3>
        <p style="color:var(--text-muted);font-size:13px;line-height:1.6;">Registro completo: NSS, RFC, CURP, área, puesto, planta y estado activo/inactivo.</p>
      </a>

      <a href="/src/php/historial.php" class="card" style="text-decoration:none;display:block;transition:transform 0.2s,box-shadow 0.2s;" onmouseover="this.style.transform='translateY(-4px)';this.style.boxShadow='0 16px 40px rgba(0,0,0,0.5)'" onmouseout="this.style.transform='';this.style.boxShadow=''">
        <i class="fas fa-file-chart-column" style="font-size:2rem;color:var(--primary);"></i>
        <h3 style="margin:14px 0 8px;font-size:17px;">Historial catorcena</h3>
        <p style="color:var(--text-muted);font-size:13px;line-height:1.6;">Reportes de horas trabajadas por catorcena (90 h), horas extra y exportación a Excel.</p>
      </a>

      <a href="/src/php/historial_empleado.php" class="card" style="text-decoration:none;display:block;transition:transform 0.2s,box-shadow 0.2s;" onmouseover="this.style.transform='translateY(-4px)';this.style.boxShadow='0 16px 40px rgba(0,0,0,0.5)'" onmouseout="this.style.transform='';this.style.boxShadow=''">
        <i class="fas fa-user-clock" style="font-size:2rem;color:var(--accent);"></i>
        <h3 style="margin:14px 0 8px;font-size:17px;">Historial por empleado</h3>
        <p style="color:var(--text-muted);font-size:13px;line-height:1.6;">Tarjetas y tabla detallada de entradas/salidas antes y después de comida por empleado.</p>
      </a>

      <a href="/checador.php" class="card" style="text-decoration:none;display:block;transition:transform 0.2s,box-shadow 0.2s;" onmouseover="this.style.transform='translateY(-4px)';this.style.boxShadow='0 16px 40px rgba(0,0,0,0.5)'" onmouseout="this.style.transform='';this.style.boxShadow=''">
        <i class="fas fa-mobile-screen-button" style="font-size:2rem;color:var(--text-muted);"></i>
        <h3 style="margin:14px 0 8px;font-size:17px;">Checador</h3>
        <p style="color:var(--text-muted);font-size:13px;line-height:1.6;">Módulo de checada pública. Accesible desde cualquier dispositivo sin autenticación.</p>
      </a>
    </div>

  </main>
</div>

<script>
// Reloj
(function tick() {
  document.getElementById('liveClock').textContent =
    new Date().toLocaleTimeString('es-MX',{hour:'2-digit',minute:'2-digit',second:'2-digit'});
  setTimeout(tick,1000);
})();

// Stats rápidas
(async () => {
  try {
    const r = await fetch('/src/php/api/dashboard_stats.php');
    const d = await r.json();
    if (d.ok) {
      document.getElementById('stPlantas').textContent  = d.plantas   ?? '—';
      document.getElementById('stEmpleados').textContent= d.empleados ?? '—';
      document.getElementById('stChecadas').textContent = d.checadas  ?? '—';
    }
  } catch {}
})();
</script>
</body>
</html>
