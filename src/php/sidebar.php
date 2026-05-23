<?php
// /src/php/sidebar.php  — Sidebar compartido
$currentPage = basename($_SERVER['PHP_SELF']);
$userName = htmlspecialchars($_SESSION['user_name'] ?? 'Admin');

$nav = [
  ['href'=>'/dashboard.php',              'icon'=>'fa-th-large',          'label'=>'Inicio',              'file'=>'dashboard.php'],
  ['href'=>'/src/php/plantas.php',         'icon'=>'fa-industry',          'label'=>'Plantas',             'file'=>'plantas.php'],
  ['href'=>'/src/php/empleados.php',       'icon'=>'fa-users-cog',         'label'=>'Empleados',           'file'=>'empleados.php'],
  ['href'=>'/src/php/historial.php',       'icon'=>'fa-file-chart-column', 'label'=>'Historial catorcena', 'file'=>'historial.php'],
  ['href'=>'/src/php/historial_empleado.php','icon'=>'fa-user-clock',      'label'=>'Por empleado',        'file'=>'historial_empleado.php'],
  ['href'=>'/checador.php',               'icon'=>'fa-mobile-screen-button','label'=>'Checador',           'file'=>'checador.php'],
];
?>
<aside class="sidebar">
  <div>

    <div class="sidebar-brand">
      
      <img src="./css/imagen/Logo_Roceel.png" alt="Logo ROCEEL" class="brand-logo">

      <div>
        <div class="brand-name">ROCEEL</div>
        <div class="brand-sub">Servicios Especializados</div>
      </div>
    </div>

    <nav class="nav">
      <div class="nav-section-label">Módulos</div>
      <?php foreach ($nav as $item): ?>
        <a href="<?= $item['href'] ?>"
           class="<?= $currentPage === $item['file'] ? 'active' : '' ?>">
          <i class="fas <?= $item['icon'] ?>"></i>
          <?= $item['label'] ?>
        </a>
      <?php endforeach; ?>
    </nav>
  </div>

  <div class="sidebar-user">
    <div class="sidebar-user-avatar"><i class="fas fa-user-tie"></i></div>
    <div>
      <div class="sidebar-user-name"><?= $userName ?></div>
      <div class="sidebar-user-role">Administrador</div>
    </div>
    <a href="/src/php/logout.php" class="logout-btn" title="Cerrar sesión">
      <i class="fas fa-sign-out-alt"></i>
    </a>
  </div>
</aside>
