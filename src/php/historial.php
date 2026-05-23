<?php
// /src/php/historial.php  — Reporte catorcena
require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/security/check_session.php';
if ($_SESSION['rol'] !== 'admin') { header('Location: /login.php'); exit(); }
$userName = htmlspecialchars($_SESSION['user_name'] ?? 'Admin');
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Historial Catorcena | ROCEEL</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="/src/css/roceel.css">
  <!-- SheetJS para exportar Excel -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
</head>
<body>
<div class="shell">
  <?php include __DIR__ . '/sidebar.php'; ?>

  <main class="main-content">
    <div class="page-header">
      <div class="page-header-left">
        <div class="page-eyebrow">Reportes</div>
        <h1 class="page-title">Historial catorcena</h1>
        <p class="page-sub">90 horas por catorcena (lun–vie). Horas extra sobre ese límite.</p>
      </div>
      <button class="btn btn-accent" id="btnExportar" disabled>
        <i class="fas fa-file-excel"></i> Exportar Excel
      </button>
    </div>

    <!-- Filtros -->
    <div class="filter-bar" style="gap:14px;flex-wrap:wrap;">
      <!-- 1. Búsqueda -->
      <div style="display:flex;flex-direction:column;gap:4px;min-width:220px;">
        <label>Empleado (NSS o nombre)</label>
        <div class="search-wrap">
          <i class="fas fa-search"></i>
          <input type="text" id="filtBuscar" placeholder="Buscar...">
        </div>
      </div>

      <!-- 2. Catorcena (rango 14 días) -->
      <div style="display:flex;flex-direction:column;gap:4px;">
        <label>Fecha inicio catorcena</label>
        <input class="form-input" type="date" id="filtFechaInicio" style="width:170px;">
      </div>
      <div style="display:flex;flex-direction:column;gap:4px;align-self:flex-end;">
        <label style="color:var(--text-muted);font-size:10.5px;">Fin (14 días auto)</label>
        <input class="form-input" type="date" id="filtFechaFin" style="width:170px;" readonly>
      </div>

      <!-- 3. Área -->
      <div style="display:flex;flex-direction:column;gap:4px;">
        <label>Área</label>
        <select class="form-select" id="filtArea" style="width:170px;">
          <option value="">Todas</option>
        </select>
      </div>

      <!-- 4. Puesto -->
      <div style="display:flex;flex-direction:column;gap:4px;">
        <label>Puesto</label>
        <select class="form-select" id="filtPuesto" style="width:170px;">
          <option value="">Todos</option>
        </select>
      </div>

      <div style="align-self:flex-end;">
        <button class="btn btn-primary" id="btnBuscarHist">
          <i class="fas fa-filter"></i> Aplicar
        </button>
      </div>
    </div>

    <!-- Stats catorcena -->
    <div class="stat-grid" id="statsHist" style="display:none;">
      <div class="stat-card">
        <span class="stat-label">Empleados</span>
        <span class="stat-value" id="shEmpleados">0</span>
      </div>
      <div class="stat-card green">
        <span class="stat-label">Total horas trabajadas</span>
        <span class="stat-value" id="shTotalHoras">0</span>
      </div>
      <div class="stat-card" style="--val-color:var(--warning);">
        <span class="stat-label">Total horas extra</span>
        <span class="stat-value" style="color:var(--warning);" id="shTotalExtra">0</span>
      </div>
    </div>

    <!-- Table -->
    <div class="table-wrap" style="overflow-x:auto;">
      <table class="data-table">
        <thead>
          <tr>
            <th>NSS</th>
            <th>Nombre</th>
            <th>Planta</th>
            <th>Área</th>
            <th>Puesto</th>
            <th>Horas trab.</th>
            <th>Horas extra</th>
          </tr>
        </thead>
        <tbody id="tableHist">
          <tr><td colspan="7"><div class="empty-state"><i class="fas fa-filter"></i><p>Aplica los filtros para ver el reporte.</p></div></td></tr>
        </tbody>
      </table>
    </div>
  </main>
</div>

<div class="toast-container" id="toastContainer"></div>

<script>
const LIMITE_CATORCENA = 90; // horas normales

let reporteData = [];
let allAreas    = [];
let allPuestos  = [];

// Fecha fin = inicio + 13 días (14 días total)
document.getElementById('filtFechaInicio').addEventListener('change', function() {
  if (!this.value) { document.getElementById('filtFechaFin').value = ''; return; }
  const d = new Date(this.value);
  d.setDate(d.getDate() + 13);
  document.getElementById('filtFechaFin').value = d.toISOString().slice(0,10);
});

// Poner fecha inicio por defecto al día 1 del período actual
(function setDefaultDates() {
  const now  = new Date();
  const day  = now.getDate();
  // Catorcena 1: días 1-15, catorcena 2: días 16-fin de mes
  const inicio = new Date(now.getFullYear(), now.getMonth(), day <= 15 ? 1 : 16);
  document.getElementById('filtFechaInicio').value = inicio.toISOString().slice(0,10);
  document.getElementById('filtFechaInicio').dispatchEvent(new Event('change'));
})();

// Cargar áreas
(async () => {
  const r = await fetch('/src/php/api/areas_puestos/get_areas.php');
  const d = await r.json();
  if (!d.ok) return;
  allAreas   = d.areas;
  allPuestos = d.areas.flatMap(a => (a.puestos||[]).map(p => ({...p, area_id:a.id, area_nombre:a.nombre})));

  const fa = document.getElementById('filtArea');
  fa.innerHTML = '<option value="">Todas</option>' +
    allAreas.map(a => `<option value="${a.id}">${esc(a.nombre)}</option>`).join('');

  const fp = document.getElementById('filtPuesto');
  fp.innerHTML = '<option value="">Todos</option>' +
    allPuestos.map(p => `<option value="${p.id}">${esc(p.nombre)} (${esc(p.area_nombre)})</option>`).join('');
})();

// Filtrar puestos por área
document.getElementById('filtArea').addEventListener('change', function() {
  const aid = this.value;
  const fp  = document.getElementById('filtPuesto');
  const pts = allPuestos.filter(p => !aid || String(p.area_id) === aid);
  fp.innerHTML = '<option value="">Todos</option>' +
    pts.map(p => `<option value="${p.id}">${esc(p.nombre)}</option>`).join('');
});

// ---- Buscar / aplicar filtros ----
document.getElementById('btnBuscarHist').addEventListener('click', buscarHistorial);

async function buscarHistorial() {
  const buscar  = document.getElementById('filtBuscar').value.trim();
  const inicio  = document.getElementById('filtFechaInicio').value;
  const fin     = document.getElementById('filtFechaFin').value;
  const area_id = document.getElementById('filtArea').value;
  const puesto_id = document.getElementById('filtPuesto').value;

  if (!inicio || !fin) { toast('Selecciona la fecha de inicio de catorcena.','error'); return; }

  const btn = document.getElementById('btnBuscarHist');
  btn.disabled = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Cargando...';

  try {
    const params = new URLSearchParams({ inicio, fin });
    if (buscar)    params.set('buscar',    buscar);
    if (area_id)   params.set('area_id',   area_id);
    if (puesto_id) params.set('puesto_id', puesto_id);

    const r = await fetch('/src/php/api/historial/get_catorcena.php?' + params);
    const d = await r.json();

    if (!d.ok) { toast(d.msg || 'Error al cargar.','error'); return; }

    reporteData = d.reporte;
    renderReporte(reporteData, inicio, fin);
    document.getElementById('btnExportar').disabled = false;
  } catch { toast('Error de red.','error'); }
  finally {
    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-filter"></i> Aplicar';
  }
}

function renderReporte(list, inicio, fin) {
  // Stats
  const totalHoras = list.reduce((s,e) => s + e.horas_trabajadas, 0);
  const totalExtra = list.reduce((s,e) => s + (e.horas_extra||0), 0);
  document.getElementById('shEmpleados').textContent  = list.length;
  document.getElementById('shTotalHoras').textContent = totalHoras.toFixed(1);
  document.getElementById('shTotalExtra').textContent = totalExtra.toFixed(1);
  document.getElementById('statsHist').style.display  = 'grid';

  const tbody = document.getElementById('tableHist');
  if (!list.length) {
    tbody.innerHTML = `<tr><td colspan="7"><div class="empty-state"><i class="fas fa-chart-bar"></i><p>Sin registros para este período.</p></div></td></tr>`;
    return;
  }

  tbody.innerHTML = list.map(e => {
    const extra = e.horas_extra || 0;
    return `
      <tr>
        <td><strong style="color:var(--primary);">${esc(e.numero_empleado)}</strong></td>
        <td style="font-weight:600;">${esc(e.nombre_completo)}</td>
        <td>${esc(e.planta||'—')}</td>
        <td>${esc(e.area||'—')}</td>
        <td>${esc(e.puesto||'—')}</td>
        <td>
          <span class="badge ${e.horas_trabajadas >= LIMITE_CATORCENA ? 'badge-green' : 'badge-yellow'}">
            ${e.horas_trabajadas.toFixed(1)} h
          </span>
        </td>
        <td>
          ${extra > 0
            ? `<span class="badge" style="background:rgba(245,196,0,0.12);color:var(--primary);">${extra.toFixed(1)} h extra</span>`
            : '<span class="badge badge-gray">—</span>'}
        </td>
      </tr>
    `;
  }).join('');
}

// ---- Exportar Excel ----
document.getElementById('btnExportar').addEventListener('click', () => {
  if (!reporteData.length) return;
  const inicio = document.getElementById('filtFechaInicio').value;
  const fin    = document.getElementById('filtFechaFin').value;

  const rows = reporteData.map(e => ({
    NSS:               e.numero_empleado,
    Nombre:            e.nombre_completo,
    Planta:            e.planta || '',
    Área:              e.area   || '',
    Puesto:            e.puesto || '',
    'Horas Trabajadas': e.horas_trabajadas,
    'Horas Extra':      e.horas_extra || 0,
  }));

  const ws  = XLSX.utils.json_to_sheet(rows);
  const wb  = XLSX.utils.book_new();
  XLSX.utils.book_append_sheet(wb, ws, 'Catorcena');

  // Ajustar anchos
  ws['!cols'] = [10,28,18,18,20,17,13].map(w => ({wch:w}));

  XLSX.writeFile(wb, `ROCEEL_catorcena_${inicio}_${fin}.xlsx`);
  toast('Excel generado correctamente.','success');
});

function esc(s) { const d=document.createElement('div'); d.textContent=s??''; return d.innerHTML; }
function toast(msg, type='success') {
  const c = document.getElementById('toastContainer');
  const t = document.createElement('div');
  t.className = `toast ${type}`;
  t.innerHTML = `<i class="fas fa-${type==='success'?'check-circle':'exclamation-circle'}"></i> ${msg}`;
  c.appendChild(t);
  setTimeout(() => t.remove(), 3500);
}
</script>
</body>
</html>
