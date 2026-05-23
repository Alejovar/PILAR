<?php
// /src/php/historial_empleado.php
require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/security/check_session.php';
if ($_SESSION['rol'] !== 'admin') { header('Location: /login.php'); exit(); }
$userName = htmlspecialchars($_SESSION['user_name'] ?? 'Admin');
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Historial por Empleado | ROCEEL</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="/src/css/roceel.css">
</head>
<body>
<div class="shell">
  <?php include __DIR__ . '/sidebar.php'; ?>

  <main class="main-content">
    <div class="page-header">
      <div class="page-header-left">
        <div class="page-eyebrow">Reportes</div>
        <h1 class="page-title">Historial por empleado</h1>
        <p class="page-sub">Detalle de registros de asistencia con entradas y salidas diarias.</p>
      </div>
    </div>

    <!-- Filtros -->
    <div class="filter-bar" style="flex-wrap:wrap;gap:14px;">
      <div style="display:flex;flex-direction:column;gap:4px;flex:1;min-width:200px;">
        <label>Empleado (NSS o nombre)</label>
        <div class="search-wrap" style="width:100%;">
          <i class="fas fa-search"></i>
          <input type="text" id="heNombre" placeholder="Buscar empleado..." style="width:100%;">
        </div>
      </div>
      <div style="display:flex;flex-direction:column;gap:4px;">
        <label>Fecha inicio</label>
        <input class="form-input" type="date" id="heFechaInicio" style="width:160px;">
      </div>
      <div style="display:flex;flex-direction:column;gap:4px;">
        <label>Fecha fin</label>
        <input class="form-input" type="date" id="heFechaFin" style="width:160px;">
      </div>
      <div style="align-self:flex-end;">
        <button class="btn btn-primary" id="btnBuscarHE">
          <i class="fas fa-search"></i> Buscar
        </button>
      </div>
    </div>

    <!-- Resultados empleados (puede haber varios si busca por nombre) -->
    <div id="secEmpleados" style="display:none;margin-bottom:20px;">
      <p style="font-size:12px;color:var(--text-muted);margin-bottom:10px;">Selecciona un empleado:</p>
      <div id="listaEmpleados" style="display:flex;gap:10px;flex-wrap:wrap;"></div>
    </div>

    <!-- Tarjetas resumen del empleado seleccionado -->
    <div id="secResumen" style="display:none;">
      <div style="display:flex;align-items:center;gap:14px;margin-bottom:20px;padding:18px 22px;background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);">
        <div style="width:52px;height:52px;border-radius:50%;background:var(--primary-glow);border:2px solid rgba(245,196,0,0.3);display:grid;place-items:center;font-size:22px;color:var(--primary);">
          <i class="fas fa-user-hard-hat"></i>
        </div>
        <div>
          <div style="font-weight:800;font-size:17px;" id="heNombreLabel">—</div>
          <div style="font-size:12px;color:var(--text-muted);" id="heMetaLabel">—</div>
          <div style="font-size:11px;color:var(--primary);font-weight:700;" id="heNssLabel">NSS: —</div>
        </div>
        <div style="margin-left:auto;text-align:right;">
          <div style="font-size:11px;color:var(--text-muted);">Período</div>
          <div style="font-weight:700;font-size:13px;" id="hePeriodoLabel">—</div>
        </div>
      </div>

      <!-- Mini stat cards -->
      <div class="stat-grid" style="margin-bottom:22px;">
        <div class="stat-card">
          <span class="stat-label">Días trabajados</span>
          <span class="stat-value" id="heDias">0</span>
        </div>
        <div class="stat-card green">
          <span class="stat-label">Horas trabajadas</span>
          <span class="stat-value" id="heHoras">0</span>
        </div>
        <div class="stat-card">
          <span class="stat-label">Horas extra</span>
          <span class="stat-value" style="color:var(--warning);" id="heExtra">0</span>
        </div>
      </div>

      <!-- Tabla detallada -->
      <div class="table-wrap" style="overflow-x:auto;">
        <table class="data-table">
          <thead>
            <tr>
              <th>Fecha</th>
              <th>Entrada</th>
              <th>Salida comida</th>
              <th>Regreso comida</th>
              <th>Salida</th>
              <th>Horas del día</th>
            </tr>
          </thead>
          <tbody id="tableDetalle">
            <tr><td colspan="6"><div class="empty-state"><p>Sin datos.</p></div></td></tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Empty inicial -->
    <div id="secEmpty">
      <div class="empty-state" style="margin-top:40px;">
        <i class="fas fa-user-clock"></i>
        <p>Busca un empleado y selecciona el rango de fechas.</p>
      </div>
    </div>

  </main>
</div>

<div class="toast-container" id="toastContainer"></div>

<script>
let empleadoSel = null;

// Defaults: últimos 14 días
(function() {
  const fin = new Date();
  const ini = new Date(); ini.setDate(ini.getDate()-13);
  document.getElementById('heFechaFin').value   = fin.toISOString().slice(0,10);
  document.getElementById('heFechaInicio').value = ini.toISOString().slice(0,10);
})();

document.getElementById('btnBuscarHE').addEventListener('click', buscar);

async function buscar() {
  const q     = document.getElementById('heNombre').value.trim();
  const ini   = document.getElementById('heFechaInicio').value;
  const fin   = document.getElementById('heFechaFin').value;
  if (!q)   { toast('Ingresa nombre o NSS del empleado.','error'); return; }
  if (!ini) { toast('Selecciona fecha inicio.','error'); return; }

  document.getElementById('secEmpty').style.display    = 'none';
  document.getElementById('secResumen').style.display  = 'none';
  document.getElementById('secEmpleados').style.display= 'none';

  const params = new URLSearchParams({ buscar: q });
  const r = await fetch('/src/php/api/empleados/buscar.php?' + params);
  const d = await r.json();

  if (!d.ok || !d.empleados.length) {
    toast('No se encontró el empleado.','error');
    document.getElementById('secEmpty').style.display = 'block';
    return;
  }

  if (d.empleados.length === 1) {
    seleccionarEmpleado(d.empleados[0]);
    return;
  }

  // Varios resultados → mostrar lista
  document.getElementById('listaEmpleados').innerHTML = d.empleados.map(e => `
    <button class="btn btn-ghost" onclick="seleccionarEmpleado(${JSON.stringify(e).replace(/"/g,'&quot;')})">
      <i class="fas fa-user"></i> ${esc(e.nombre)} ${esc(e.apellido_paterno)} — <small>${esc(e.numero_empleado)}</small>
    </button>
  `).join('');
  document.getElementById('secEmpleados').style.display = 'block';
}

async function seleccionarEmpleado(emp) {
  empleadoSel = emp;
  document.getElementById('secEmpleados').style.display = 'none';

  const ini = document.getElementById('heFechaInicio').value;
  const fin = document.getElementById('heFechaFin').value;
  const finD = fin || new Date().toISOString().slice(0,10);

  const r = await fetch(`/src/php/api/historial/get_empleado.php?empleado_id=${emp.id}&inicio=${ini}&fin=${finD}`);
  const d = await r.json();
  if (!d.ok) { toast(d.msg||'Error.','error'); return; }

  // Header
  document.getElementById('heNombreLabel').textContent = `${emp.nombre} ${emp.apellido_paterno} ${emp.apellido_materno||''}`.trim();
  document.getElementById('heMetaLabel').textContent   = `${emp.puesto_nombre||'—'} · ${emp.planta_nombre||'—'}`;
  document.getElementById('heNssLabel').textContent    = `NSS: ${emp.numero_empleado}`;
  document.getElementById('hePeriodoLabel').textContent = `${fmtDate(ini)} — ${fmtDate(finD)}`;

  // Stats
  document.getElementById('heDias').textContent  = d.dias_trabajados;
  document.getElementById('heHoras').textContent = d.total_horas.toFixed(1) + 'h';
  document.getElementById('heExtra').textContent = (d.horas_extra||0).toFixed(1) + 'h';

  // Tabla
  const tbody = document.getElementById('tableDetalle');
  if (!d.dias.length) {
    tbody.innerHTML = `<tr><td colspan="6"><div class="empty-state"><p>Sin registros en este período.</p></div></td></tr>`;
  } else {
    tbody.innerHTML = d.dias.map(dia => `
      <tr>
        <td style="font-weight:700;">${fmtDate(dia.fecha)}</td>
        <td><span class="badge badge-green">${dia.entrada || '—'}</span></td>
        <td><span class="badge badge-yellow">${dia.salida_comida || '—'}</span></td>
        <td><span class="badge badge-yellow">${dia.regreso_comida || '—'}</span></td>
        <td><span class="badge badge-red">${dia.salida || '—'}</span></td>
        <td>
          ${dia.horas_dia != null
            ? `<strong style="color:var(--accent);">${Number(dia.horas_dia).toFixed(1)} h</strong>`
            : '<span style="color:var(--text-muted);">—</span>'}
        </td>
      </tr>
    `).join('');
  }

  document.getElementById('secResumen').style.display = 'block';
}

function esc(s) { const d=document.createElement('div'); d.textContent=s??''; return d.innerHTML; }
function fmtDate(s) {
  if (!s) return '—';
  const [y,m,d] = s.split('-');
  return `${d}/${m}/${y}`;
}
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
