<?php
// /src/php/empleados.php
require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/security/check_session.php';
if ($_SESSION['rol'] !== 'admin') { header('Location: /login.php'); exit(); }
$userName = htmlspecialchars($_SESSION['user_name'] ?? 'Admin');
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Empleados | ROCEEL</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="/src/css/roceel.css">
</head>
<body>
<div class="shell">
  <?php include __DIR__ . '/sidebar.php'; ?>

  <main class="main-content">
    <div class="page-header">
      <div class="page-header-left">
        <div class="page-eyebrow">Personal</div>
        <h1 class="page-title">Empleados</h1>
        <p class="page-sub">Gestión de personal, áreas y puestos.</p>
      </div>
      <div style="display:flex;gap:10px;flex-wrap:wrap;">
        <button class="btn btn-ghost" id="btnGestionAreas">
          <i class="fas fa-sitemap"></i> Áreas y puestos
        </button>
        <button class="btn btn-primary" id="btnNuevoEmp">
          <i class="fas fa-user-plus"></i> Nuevo empleado
        </button>
      </div>
    </div>

    <!-- Toolbar / Filtros -->
    <div class="filter-bar">
      <label>Filtros:</label>
      <div class="search-wrap" style="flex-grow:1;max-width:280px;">
        <i class="fas fa-search"></i>
        <input type="text" id="searchEmp" placeholder="Nombre o NSS...">
      </div>
      <select class="form-select" id="filtroArea" style="width:auto;min-width:150px;">
        <option value="">Todas las áreas</option>
      </select>
      <select class="form-select" id="filtroPuesto" style="width:auto;min-width:150px;">
        <option value="">Todos los puestos</option>
      </select>
      <select class="form-select" id="filtroEstado" style="width:auto;min-width:130px;">
        <option value="">Todos</option>
        <option value="1">Activos</option>
        <option value="0">Inactivos</option>
      </select>
    </div>

    <!-- Table -->
    <div class="table-wrap" style="overflow-x:auto;">
      <table class="data-table">
        <thead>
          <tr>
            <th>NSS</th>
            <th>Nombre</th>
            <th>Área</th>
            <th>Puesto</th>
            <th>Planta</th>
            <th>Estado</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody id="tableEmpleados">
          <tr><td colspan="7"><div class="empty-state"><i class="fas fa-spinner fa-spin"></i><p>Cargando...</p></div></td></tr>
        </tbody>
      </table>
    </div>
  </main>
</div>

<!-- ============================================================ -->
<!-- MODAL: Nuevo / Editar Empleado                               -->
<!-- ============================================================ -->
<div class="modal-overlay" id="modalEmpleado">
  <div class="modal-box wide">
    <div class="modal-header">
      <h2 id="modalEmpTitulo">Registrar empleado</h2>
      <button class="close-btn" id="closeModalEmp"><i class="fas fa-times"></i></button>
    </div>
    <form id="formEmpleado">
      <input type="hidden" id="empId">
      <div class="form-grid">
        <!-- Datos personales -->
        <div class="form-group">
          <label>Nombre</label>
          <input class="form-input" type="text" id="empNombre" required placeholder="Juan">
        </div>
        <div class="form-group">
          <label>Apellido paterno</label>
          <input class="form-input" type="text" id="empApellidoPat" required placeholder="García">
        </div>
        <div class="form-group">
          <label>Apellido materno</label>
          <input class="form-input" type="text" id="empApellidoMat" placeholder="López">
        </div>
        <div class="form-group">
          <label>NSS (N° empleado)</label>
          <input class="form-input" type="text" id="empNSS" required placeholder="12345678901" maxlength="20">
        </div>
        <div class="form-group">
          <label>RFC</label>
          <input class="form-input" type="text" id="empRFC" placeholder="GALO900101XXX" maxlength="15">
        </div>
        <div class="form-group">
          <label>CURP</label>
          <input class="form-input" type="text" id="empCURP" placeholder="GALO900101HCORXXX00" maxlength="20">
        </div>
        <div class="form-group span-2">
          <label>Email</label>
          <input class="form-input" type="email" id="empEmail" placeholder="juan.garcia@roceel.com">
        </div>
        <!-- Posición -->
        <div class="form-group">
          <label>Área</label>
          <select class="form-select" id="empArea" required>
            <option value="">— Seleccionar —</option>
          </select>
        </div>
        <div class="form-group">
          <label>Puesto</label>
          <select class="form-select" id="empPuesto" required>
            <option value="">— Seleccionar área primero —</option>
          </select>
        </div>
        <div class="form-group">
          <label>Planta</label>
          <select class="form-select" id="empPlanta" required>
            <option value="">— Seleccionar —</option>
          </select>
        </div>
        <div class="form-group">
          <label>Estado</label>
          <select class="form-select" id="empActivo">
            <option value="1">Activo</option>
            <option value="0">Inactivo</option>
          </select>
        </div>
      </div>
      <div class="modal-actions">
        <button type="button" class="btn btn-ghost" id="cancelModalEmp">Cancelar</button>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Guardar empleado</button>
      </div>
    </form>
  </div>
</div>

<!-- ============================================================ -->
<!-- MODAL: Áreas y Puestos                                       -->
<!-- ============================================================ -->
<div class="modal-overlay" id="modalAreas">
  <div class="modal-box wide">
    <div class="modal-header">
      <h2>Áreas y Puestos</h2>
      <button class="close-btn" id="closeModalAreas"><i class="fas fa-times"></i></button>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
      <!-- Áreas -->
      <div>
        <h3 style="font-size:14px;font-weight:800;margin-bottom:12px;color:var(--primary);">
          <i class="fas fa-sitemap"></i> Áreas
        </h3>
        <form id="formArea" style="display:flex;gap:8px;margin-bottom:12px;">
          <input class="form-input" type="text" id="nuevaArea" placeholder="Nombre del área" required style="flex:1;">
          <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i></button>
        </form>
        <div id="listaAreas" style="display:grid;gap:6px;max-height:280px;overflow-y:auto;"></div>
      </div>
      <!-- Puestos -->
      <div>
        <h3 style="font-size:14px;font-weight:800;margin-bottom:12px;color:var(--accent);">
          <i class="fas fa-briefcase"></i> Puestos
        </h3>
        <form id="formPuesto" style="display:grid;gap:8px;margin-bottom:12px;">
          <select class="form-select" id="areaParaPuesto" required>
            <option value="">Área del puesto...</option>
          </select>
          <div style="display:flex;gap:8px;">
            <input class="form-input" type="text" id="nuevoPuesto" placeholder="Nombre del puesto" required style="flex:1;">
            <button type="submit" class="btn btn-accent btn-sm"><i class="fas fa-plus"></i></button>
          </div>
        </form>
        <div id="listaPuestos" style="display:grid;gap:6px;max-height:240px;overflow-y:auto;"></div>
      </div>
    </div>

    <div class="modal-actions">
      <button class="btn btn-ghost" id="cancelModalAreas">Cerrar</button>
    </div>
  </div>
</div>

<!-- ============================================================ -->
<!-- MODAL: Captura Facial                                        -->
<!-- ============================================================ -->
<div class="modal-overlay" id="modalFace">
  <div class="modal-box" style="max-width:420px;">
    <div class="modal-header">
      <div>
        <h2>Registrar rostro</h2>
        <p style="font-size:12px;color:var(--text-muted);margin-top:2px;" id="faceModalNombre">—</p>
      </div>
      <button class="close-btn" id="closeFaceModal"><i class="fas fa-times"></i></button>
    </div>

    <div style="position:relative;background:#000;border-radius:12px;overflow:hidden;margin-bottom:14px;">
      <video id="faceVideo" autoplay muted playsinline style="width:100%;display:block;border-radius:12px;"></video>
    </div>

    <p id="faceStatus" style="text-align:center;font-size:13px;font-weight:600;margin-bottom:14px;color:var(--text-muted);">
      Iniciando cámara...
    </p>

    <div class="modal-actions" style="padding-top:0;border-top:none;justify-content:center;gap:10px;">
      <button class="btn btn-danger btn-sm" id="btnEliminarRostro">
        <i class="fas fa-trash"></i> Eliminar rostro
      </button>
      <button class="btn btn-primary" id="btnCapturarRostro">
        <i class="fas fa-camera"></i> Capturar rostro
      </button>
      <button class="btn btn-ghost" id="cancelFaceModal">Cancelar</button>
    </div>
  </div>
</div>

<div class="toast-container" id="toastContainer"></div>

<script>
const API_E  = '/src/php/api/empleados/';
const API_AP = '/src/php/api/areas_puestos/';
const API_PL = '/src/php/api/plantas/';
const API_PL_LIST = API_PL + 'plantas.php?action=list';

let allEmpleados = [], allAreas = [], allPuestos = [], allPlantas = [];

// ======================= LOAD ========================
async function init() {
  await Promise.all([loadAreas(), loadPlantas()]);
  loadEmpleados();
}

async function loadEmpleados() {
  const r = await fetch(API_E + 'get_empleados.php');
  const d = await r.json();
  if (d.ok) { allEmpleados = d.empleados; renderEmpleados(); }
}
async function loadAreas() {
  const r = await fetch(API_AP + 'get_areas.php');
  const d = await r.json();
  if (d.ok) {
    allAreas   = d.areas;
    allPuestos = d.areas.flatMap(a => (a.puestos||[]).map(p => ({...p, area_id:a.id, area_nombre:a.nombre})));
    populateAreaSelects();
    renderAreasModal();
    renderPuestosModal();
  }
}
async function loadPlantas() {
  try {
    const r = await fetch(API_PL_LIST);
    const d = await r.json();
    if (d.ok) {
      allPlantas = d.plantas.filter(p => p.activa);
      const sel  = document.getElementById('empPlanta');
      sel.innerHTML = '<option value="">— Seleccionar —</option>' +
        allPlantas.map(p => `<option value="${p.id}">${esc(p.nombre)}</option>`).join('');
    }
  } catch (err) {
    console.error('Error cargando plantas:', err);
  }
}

// ======================= RENDER EMPLEADOS ========================
function renderEmpleados() {
  const q  = document.getElementById('searchEmp').value.toLowerCase();
  const fa = document.getElementById('filtroArea').value;
  const fp = document.getElementById('filtroPuesto').value;
  const fe = document.getElementById('filtroEstado').value;

  let list = allEmpleados;
  if (q)  list = list.filter(e => (e.nombre+' '+e.apellido_paterno+' '+e.apellido_materno+' '+e.numero_empleado).toLowerCase().includes(q));
  if (fa) list = list.filter(e => String(e.area_id) === fa);
  if (fp) list = list.filter(e => String(e.puesto_id) === fp);
  if (fe !== '') list = list.filter(e => String(e.activo?1:0) === fe);

  const tbody = document.getElementById('tableEmpleados');
  if (!list.length) {
    tbody.innerHTML = `<tr><td colspan="7"><div class="empty-state"><i class="fas fa-users"></i><p>Sin resultados.</p></div></td></tr>`;
    return;
  }
  tbody.innerHTML = list.map(e => `
    <tr>
      <td><strong style="color:var(--primary);">${esc(e.numero_empleado)}</strong></td>
      <td style="font-weight:600;">${esc(e.nombre)} ${esc(e.apellido_paterno)} ${esc(e.apellido_materno||'')}</td>
      <td>${esc(e.area_nombre||'—')}</td>
      <td>${esc(e.puesto_nombre||'—')}</td>
      <td>${esc(e.planta_nombre||'—')}</td>
      <td>${e.activo ? '<span class="badge badge-green">Activo</span>' : '<span class="badge badge-gray">Inactivo</span>'}</td>
      <td style="display:flex;gap:6px;flex-wrap:wrap;">
        <button class="btn btn-ghost btn-sm" onclick="editEmpleado(${e.id})">
          <i class="fas fa-pen"></i> Editar
        </button>
        <button class="btn btn-sm" style="background:rgba(245,196,0,0.1);color:var(--primary);border:1px solid rgba(245,196,0,0.25);" onclick="abrirFace(${e.id},'${esc(e.nombre)} ${esc(e.apellido_paterno)}')" title="Registrar rostro">
          <i class="fas fa-camera"></i>
        </button>
      </td>
    </tr>
  `).join('');
}

// ======================= FILTROS ========================
['searchEmp','filtroArea','filtroPuesto','filtroEstado'].forEach(id =>
  document.getElementById(id).addEventListener('input', renderEmpleados)
);

function populateAreaSelects() {
  // Filtro área
  const fa = document.getElementById('filtroArea');
  fa.innerHTML = '<option value="">Todas las áreas</option>' +
    allAreas.map(a => `<option value="${a.id}">${esc(a.nombre)}</option>`).join('');

  // Select área modal empleado
  const ea = document.getElementById('empArea');
  ea.innerHTML = '<option value="">— Seleccionar —</option>' +
    allAreas.map(a => `<option value="${a.id}">${esc(a.nombre)}</option>`).join('');

  // Select área para puesto
  const ap = document.getElementById('areaParaPuesto');
  ap.innerHTML = '<option value="">Área del puesto...</option>' +
    allAreas.map(a => `<option value="${a.id}">${esc(a.nombre)}</option>`).join('');
}

// Cuando cambia área en modal empleado → filtrar puestos
document.getElementById('empArea').addEventListener('change', function() {
  const aid = this.value;
  const sel = document.getElementById('empPuesto');
  const pts = allPuestos.filter(p => String(p.area_id) === aid);
  sel.innerHTML = pts.length
    ? '<option value="">— Seleccionar —</option>' + pts.map(p => `<option value="${p.id}">${esc(p.nombre)}</option>`).join('')
    : '<option value="">Sin puestos en esta área</option>';
  // Filtro puesto
  const fp = document.getElementById('filtroPuesto');
  fp.innerHTML = '<option value="">Todos los puestos</option>' +
    allPuestos.filter(p => !aid || String(p.area_id) === aid).map(p => `<option value="${p.id}">${esc(p.nombre)}</option>`).join('');
});

// ======================= MODAL EMPLEADO ========================
const modalEmp = document.getElementById('modalEmpleado');
document.getElementById('btnNuevoEmp').addEventListener('click', () => {
  document.getElementById('modalEmpTitulo').textContent = 'Registrar empleado';
  document.getElementById('formEmpleado').reset();
  document.getElementById('empId').value = '';
  modalEmp.classList.add('open');
});
['closeModalEmp','cancelModalEmp'].forEach(id =>
  document.getElementById(id).addEventListener('click', () => modalEmp.classList.remove('open'))
);

function editEmpleado(id) {
  const e = allEmpleados.find(x => x.id == id);
  if (!e) return;
  document.getElementById('modalEmpTitulo').textContent = 'Editar empleado';
  document.getElementById('empId').value           = e.id;
  document.getElementById('empNombre').value       = e.nombre;
  document.getElementById('empApellidoPat').value  = e.apellido_paterno;
  document.getElementById('empApellidoMat').value  = e.apellido_materno || '';
  document.getElementById('empNSS').value          = e.numero_empleado;
  document.getElementById('empRFC').value          = e.rfc || '';
  document.getElementById('empCURP').value         = e.curp || '';
  document.getElementById('empEmail').value        = e.email || '';
  document.getElementById('empArea').value         = e.area_id || '';
  document.getElementById('empArea').dispatchEvent(new Event('change'));
  setTimeout(() => { document.getElementById('empPuesto').value = e.puesto_id || ''; }, 50);
  document.getElementById('empPlanta').value       = e.planta_id || '';
  document.getElementById('empActivo').value       = e.activo ? '1' : '0';
  modalEmp.classList.add('open');
}

document.getElementById('formEmpleado').addEventListener('submit', async e => {
  e.preventDefault();
  const body = {
    id:              document.getElementById('empId').value || null,
    nombre:          document.getElementById('empNombre').value.trim(),
    apellido_paterno:document.getElementById('empApellidoPat').value.trim(),
    apellido_materno:document.getElementById('empApellidoMat').value.trim(),
    numero_empleado: document.getElementById('empNSS').value.trim(),
    rfc:             document.getElementById('empRFC').value.trim().toUpperCase(),
    curp:            document.getElementById('empCURP').value.trim().toUpperCase(),
    email:           document.getElementById('empEmail').value.trim(),
    puesto_id:       document.getElementById('empPuesto').value,
    planta_id:       document.getElementById('empPlanta').value,
    activo:          document.getElementById('empActivo').value === '1',
  };
  try {
    const r = await fetch(API_E + 'save_empleado.php', {
      method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(body)
    });
    const raw = await r.text();
    let d;
    try {
      d = raw ? JSON.parse(raw) : {};
    } catch (parseError) {
      console.error('Respuesta inválida de save_empleado.php:', raw);
      toast('Respuesta inválida del servidor.', 'error');
      return;
    }

    if (!r.ok) {
      toast(d.msg || `Error del servidor (${r.status}).`, 'error');
      return;
    }

    if (d.ok) {
      toast(body.id ? 'Empleado actualizado.' : 'Empleado registrado.','success');
      modalEmp.classList.remove('open');
      loadEmpleados();
    } else toast(d.msg||'Error al guardar.','error');
  } catch (error) { toast('Error de red: ' + error.message,'error'); }
});

// ======================= MODAL ÁREAS/PUESTOS ========================
const modalAreas = document.getElementById('modalAreas');
document.getElementById('btnGestionAreas').addEventListener('click', () => modalAreas.classList.add('open'));
['closeModalAreas','cancelModalAreas'].forEach(id =>
  document.getElementById(id).addEventListener('click', () => modalAreas.classList.remove('open'))
);

function renderAreasModal() {
  document.getElementById('listaAreas').innerHTML = allAreas.map(a => `
    <div style="display:flex;align-items:center;justify-content:space-between;padding:8px 12px;background:var(--surface-2);border-radius:8px;border:1px solid var(--border);">
      <span style="font-weight:600;font-size:13px;">${esc(a.nombre)}</span>
      <span class="badge badge-yellow" style="font-size:10px;">${(a.puestos||[]).length} puestos</span>
    </div>
  `).join('') || '<p style="color:var(--text-muted);font-size:13px;">Sin áreas registradas.</p>';
}

function renderPuestosModal() {
  document.getElementById('listaPuestos').innerHTML = allPuestos.map(p => `
    <div style="display:flex;align-items:center;justify-content:space-between;padding:8px 12px;background:var(--surface-2);border-radius:8px;border:1px solid var(--border);">
      <div>
        <div style="font-weight:600;font-size:13px;">${esc(p.nombre)}</div>
        <div style="font-size:11px;color:var(--text-muted);">${esc(p.area_nombre)}</div>
      </div>
    </div>
  `).join('') || '<p style="color:var(--text-muted);font-size:13px;">Sin puestos registrados.</p>';
}

document.getElementById('formArea').addEventListener('submit', async e => {
  e.preventDefault();
  const nombre = document.getElementById('nuevaArea').value.trim();
  if (!nombre) return;
  const r = await fetch(API_AP + 'save_area.php', {
    method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({nombre})
  });
  const d = await r.json();
  if (d.ok) {
    toast('Área creada.','success');
    document.getElementById('nuevaArea').value = '';
    await loadAreas();
  } else toast(d.msg||'Error.','error');
});

document.getElementById('formPuesto').addEventListener('submit', async e => {
  e.preventDefault();
  const area_id = document.getElementById('areaParaPuesto').value;
  const nombre  = document.getElementById('nuevoPuesto').value.trim();
  if (!area_id || !nombre) return;
  const r = await fetch(API_AP + 'save_puesto.php', {
    method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({nombre, area_id})
  });
  const d = await r.json();
  if (d.ok) {
    toast('Puesto creado.','success');
    document.getElementById('nuevoPuesto').value = '';
    await loadAreas();
  } else toast(d.msg||'Error.','error');
});

// ======================= UTILS ========================
// ======================= FACIAL ========================
let faceStream = null, faceEmpId = null;

function abrirFace(empId, nombre) {
  faceEmpId = empId;
  document.getElementById('faceModalNombre').textContent = nombre;
  document.getElementById('faceStatus').textContent = 'Iniciando cámara...';
  document.getElementById('faceStatus').style.color = 'var(--text-muted)';
  document.getElementById('modalFace').classList.add('open');
  iniciarCamFace();
}

async function iniciarCamFace() {
  try {
    faceStream = await navigator.mediaDevices.getUserMedia({ video:{facingMode:'user'}, audio:false });
    const v = document.getElementById('faceVideo');
    v.srcObject = faceStream;
    await v.play();
    document.getElementById('faceStatus').textContent = 'Coloca tu rostro frente a la cámara y captura.';
  } catch(e) {
    document.getElementById('faceStatus').textContent = 'No se pudo acceder a la cámara.';
    document.getElementById('faceStatus').style.color = 'var(--danger)';
  }
}

function cerrarFaceModal() {
  document.getElementById('modalFace').classList.remove('open');
  if (faceStream) { faceStream.getTracks().forEach(t => t.stop()); faceStream = null; }
  faceEmpId = null;
}

document.getElementById('closeFaceModal').addEventListener('click', cerrarFaceModal);
document.getElementById('cancelFaceModal').addEventListener('click', cerrarFaceModal);

document.getElementById('btnCapturarRostro').addEventListener('click', async () => {
  if (!faceEmpId) return;
  const btn = document.getElementById('btnCapturarRostro');
  btn.disabled = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Procesando...';
  document.getElementById('faceStatus').textContent = 'Detectando rostro...';

  try {
    // Cargar face-api.js dinámicamente si no está cargado
    if (typeof faceapi === 'undefined') {
      await loadScript('https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js');
      await faceapi.nets.tinyFaceDetector.loadFromUri('/src/face-models');
      await faceapi.nets.faceLandmark68TinyNet.loadFromUri('/src/face-models');
      await faceapi.nets.faceRecognitionNet.loadFromUri('/src/face-models');
    }

    const video  = document.getElementById('faceVideo');
    const opts   = new faceapi.TinyFaceDetectorOptions({inputSize:224, scoreThreshold:0.5});
    const result = await faceapi.detectSingleFace(video, opts).withFaceLandmarks(true).withFaceDescriptor();

    if (!result) {
      document.getElementById('faceStatus').textContent = '❌ No se detectó ningún rostro. Intenta de nuevo.';
      document.getElementById('faceStatus').style.color = 'var(--danger)';
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-camera"></i> Capturar rostro';
      return;
    }

    const descriptor = Array.from(result.descriptor);
    const r = await fetch('/src/php/api/face/save_face.php', {
      method:'POST', headers:{'Content-Type':'application/json'},
      body: JSON.stringify({ empleado_id: faceEmpId, descriptor })
    });
    const d = await r.json();

    if (d.ok) {
      document.getElementById('faceStatus').textContent = '✅ Rostro registrado exitosamente.';
      document.getElementById('faceStatus').style.color = 'var(--accent)';
      toast('Rostro registrado.','success');
      setTimeout(cerrarFaceModal, 1800);
    } else {
      document.getElementById('faceStatus').textContent = '❌ ' + (d.msg||'Error.');
      document.getElementById('faceStatus').style.color = 'var(--danger)';
    }
  } catch(e) {
    document.getElementById('faceStatus').textContent = '❌ Error: ' + e.message;
    document.getElementById('faceStatus').style.color = 'var(--danger)';
  }

  btn.disabled = false;
  btn.innerHTML = '<i class="fas fa-camera"></i> Capturar rostro';
});

document.getElementById('btnEliminarRostro').addEventListener('click', async () => {
  if (!faceEmpId || !confirm('¿Eliminar el descriptor facial de este empleado?')) return;
  const r = await fetch('/src/php/api/face/save_face.php', {
    method:'POST', headers:{'Content-Type':'application/json'},
    body: JSON.stringify({ empleado_id: faceEmpId, descriptor: '' })
  });
  const d = await r.json();
  if (d.ok) { toast('Rostro eliminado.','success'); cerrarFaceModal(); }
  else toast(d.msg||'Error.','error');
});

function loadScript(src) {
  return new Promise((res,rej) => {
    const s = document.createElement('script');
    s.src = src; s.onload = res; s.onerror = rej;
    document.head.appendChild(s);
  });
}

function esc(s) { const d=document.createElement('div'); d.textContent=s??''; return d.innerHTML; }
function toast(msg, type='success') {
  const c = document.getElementById('toastContainer');
  const t = document.createElement('div');
  t.className = `toast ${type}`;
  t.innerHTML = `<i class="fas fa-${type==='success'?'check-circle':'exclamation-circle'}"></i> ${msg}`;
  c.appendChild(t);
  setTimeout(() => t.remove(), 3500);
}

init();
</script>
</body>
</html>
