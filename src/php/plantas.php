<?php
// /src/php/plantas.php
require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/security/check_session.php';
if ($_SESSION['rol'] !== 'admin') { header('Location: /login.php'); exit(); }
$userName = htmlspecialchars($_SESSION['user_name'] ?? 'Admin');
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Plantas | ROCEEL</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="/src/css/roceel.css">
</head>
<body>
<div class="shell">
  <?php include __DIR__ . '/sidebar.php'; ?>

  <main class="main-content">

    <div class="page-header">
      <div class="page-header-left">
        <div class="page-eyebrow">Configuración</div>
        <h1 class="page-title">Plantas</h1>
        <p class="page-sub">Registra y administra las instalaciones de Roceel.</p>
      </div>
      <button class="btn btn-primary" id="btnNuevPlanta">
        <i class="fas fa-plus"></i> Nueva planta
      </button>
    </div>

    <!-- Toolbar -->
    <div class="toolbar">
      <div class="toolbar-left">
        <div class="search-wrap">
          <i class="fas fa-search"></i>
          <input type="text" id="searchPlantas" placeholder="Buscar por nombre...">
        </div>
      </div>
    </div>

    <!-- Table -->
    <div class="table-wrap">
      <table class="data-table">
        <thead>
          <tr>
            <th>Código</th>
            <th>Nombre</th>
            <th>Ubicación</th>
            <th>Estado</th>
            <th>Creada</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody id="tablePlantas">
          <tr><td colspan="6" class="empty-state"><i class="fas fa-spinner fa-spin"></i><p>Cargando...</p></td></tr>
        </tbody>
      </table>
    </div>

  </main>
</div>

<!-- Modal Nueva/Editar Planta -->
<div class="modal-overlay" id="modalPlanta">
  <div class="modal-box">
    <div class="modal-header">
      <h2 id="modalPlantaTitulo">Nueva planta</h2>
      <button class="close-btn" id="closeModalPlanta"><i class="fas fa-times"></i></button>
    </div>
    <form id="formPlanta">
      <input type="hidden" id="plantaId">
      <div class="form-grid">
        <div class="form-group">
          <label>Nombre</label>
          <input class="form-input" type="text" id="plantaNombre" placeholder="Ej. Planta Norte" required>
        </div>
        <div class="form-group">
          <label>Código</label>
          <input class="form-input" type="text" id="plantaCodigo" placeholder="Ej. PLT-01" required maxlength="20">
        </div>
        <div class="form-group span-2">
          <label>Dirección / Ubicación</label>
          <input class="form-input" type="text" id="plantaUbicacion" placeholder="Ej. Av. Industrial #123, Saltillo, Coah.">
        </div>
        <div class="form-group">
          <label>Estado</label>
          <select class="form-select" id="plantaActiva">
            <option value="1">Activa</option>
            <option value="0">Inactiva</option>
          </select>
        </div>
      </div>
      <div class="modal-actions">
        <button type="button" class="btn btn-ghost" id="cancelModalPlanta">Cancelar</button>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Guardar</button>
      </div>
    </form>
  </div>
</div>

<!-- Toast -->
<div class="toast-container" id="toastContainer"></div>

<script>
const API_P = '/src/php/api/plantas/';

let allPlantas = [];

// ---- Load ----
async function loadPlantas() {
  try {
    const r = await fetch(API_P + 'get_plantas.php');
    const d = await r.json();
    if (d.ok) { allPlantas = d.plantas; renderPlantas(allPlantas); }
    else toast('Error cargando plantas.','error');
  } catch { toast('Error de red.','error'); }
}

function renderPlantas(list) {
  const tbody = document.getElementById('tablePlantas');
  if (!list.length) {
    tbody.innerHTML = `<tr><td colspan="6"><div class="empty-state"><i class="fas fa-industry"></i><p>No hay plantas registradas.</p></div></td></tr>`;
    return;
  }
  tbody.innerHTML = list.map(p => `
    <tr>
      <td><span class="badge badge-yellow">${esc(p.codigo)}</span></td>
      <td style="font-weight:700;">${esc(p.nombre)}</td>
      <td style="color:var(--text-muted);">${esc(p.ubicacion||'—')}</td>
      <td>${p.activa ? '<span class="badge badge-green">Activa</span>' : '<span class="badge badge-gray">Inactiva</span>'}</td>
      <td style="color:var(--text-muted);font-size:12px;">${fmtDate(p.created_at)}</td>
      <td>
        <button class="btn btn-ghost btn-sm" onclick="editPlanta(${p.id})"><i class="fas fa-pen"></i> Editar</button>
      </td>
    </tr>
  `).join('');
}

// ---- Search ----
document.getElementById('searchPlantas').addEventListener('input', function() {
  const q = this.value.toLowerCase();
  renderPlantas(allPlantas.filter(p => p.nombre.toLowerCase().includes(q) || p.codigo.toLowerCase().includes(q)));
});

// ---- Modal ----
const modalEl  = document.getElementById('modalPlanta');
const formEl   = document.getElementById('formPlanta');

document.getElementById('btnNuevPlanta').addEventListener('click', () => {
  document.getElementById('modalPlantaTitulo').textContent = 'Nueva planta';
  formEl.reset();
  document.getElementById('plantaId').value = '';
  modalEl.classList.add('open');
});
['closeModalPlanta','cancelModalPlanta'].forEach(id =>
  document.getElementById(id).addEventListener('click', () => modalEl.classList.remove('open'))
);

function editPlanta(id) {
  const p = allPlantas.find(x => x.id == id);
  if (!p) return;
  document.getElementById('modalPlantaTitulo').textContent = 'Editar planta';
  document.getElementById('plantaId').value      = p.id;
  document.getElementById('plantaNombre').value  = p.nombre;
  document.getElementById('plantaCodigo').value  = p.codigo;
  document.getElementById('plantaUbicacion').value = p.ubicacion || '';
  document.getElementById('plantaActiva').value  = p.activa ? '1' : '0';
  modalEl.classList.add('open');
}

formEl.addEventListener('submit', async e => {
  e.preventDefault();
  const body = {
    id:        document.getElementById('plantaId').value || null,
    nombre:    document.getElementById('plantaNombre').value.trim(),
    codigo:    document.getElementById('plantaCodigo').value.trim().toUpperCase(),
    ubicacion: document.getElementById('plantaUbicacion').value.trim(),
    activa:    document.getElementById('plantaActiva').value === '1',
  };
  try {
    const r = await fetch(API_P + 'save_planta.php', {
      method: 'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(body)
    });
    const d = await r.json();
    if (d.ok) {
      toast(body.id ? 'Planta actualizada.' : 'Planta creada.', 'success');
      modalEl.classList.remove('open');
      loadPlantas();
    } else toast(d.msg || 'Error al guardar.','error');
  } catch { toast('Error de red.','error'); }
});

// ---- Utils ----
function esc(s) { const d=document.createElement('div'); d.textContent=s??''; return d.innerHTML; }
function fmtDate(s) { if(!s) return '—'; const d=new Date(s); return d.toLocaleDateString('es-MX'); }
function toast(msg, type='success') {
  const c = document.getElementById('toastContainer');
  const t = document.createElement('div');
  t.className = `toast ${type}`;
  t.innerHTML = `<i class="fas fa-${type==='success'?'check-circle':'exclamation-circle'}"></i> ${msg}`;
  c.appendChild(t);
  setTimeout(() => t.remove(), 3500);
}

loadPlantas();
</script>
</body>
</html>
