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
      <img src="/src/css/imagen/Logo-Pilar.jpg" alt="Logo PILAR" class="brand-icon" style="width: 60px; height: 60px; object-fit: contain; border-radius: 8px;">
      <div>
        <div class="brand-name">PILAR</div>
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

  <div class="sidebar-footer">
    <!-- Toggle de tema -->
    <button class="theme-toggle-btn" id="themeToggleBtn" title="Cambiar tema">
      <i class="fas fa-sun"  id="themeIconSun"  style="display:none;"></i>
      <i class="fas fa-moon" id="themeIconMoon"></i>
      <span id="themeToggleLabel">Tema claro</span>
    </button>

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
  </div>
</aside>

<script>
(function () {
  const btn       = document.getElementById('themeToggleBtn');
  const iconSun   = document.getElementById('themeIconSun');
  const iconMoon  = document.getElementById('themeIconMoon');
  const label     = document.getElementById('themeToggleLabel');
  const STORAGE_KEY = 'pilar_theme';

  function aplicarTema(tema) {
    if (tema === 'light') {
      document.documentElement.setAttribute('data-theme', 'light');
      iconSun.style.display  = 'block';
      iconMoon.style.display = 'none';
      label.textContent      = 'Tema oscuro';
    } else {
      document.documentElement.removeAttribute('data-theme');
      iconSun.style.display  = 'none';
      iconMoon.style.display = 'block';
      label.textContent      = 'Tema claro';
    }
  }

  // Aplicar preferencia guardada al cargar
  const guardado = localStorage.getItem(STORAGE_KEY) || 'dark';
  aplicarTema(guardado);

  btn.addEventListener('click', function () {
    const actual = document.documentElement.getAttribute('data-theme') === 'light' ? 'light' : 'dark';
    const nuevo  = actual === 'light' ? 'dark' : 'light';
    localStorage.setItem(STORAGE_KEY, nuevo);
    aplicarTema(nuevo);
  });
})();
</script>
