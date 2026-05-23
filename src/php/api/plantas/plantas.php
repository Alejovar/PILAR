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

  <!-- Leaflet CSS -->
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">

  <style>
    /* ── Mapa dentro del modal ── */
    #mapContainer {
      position: relative;
      border-radius: 12px;
      overflow: hidden;
      border: 1px solid var(--border);
      margin-top: 6px;
    }
    #plantaMap {
      height: 300px;
      width: 100%;
      z-index: 0;
    }

    /* Barra de búsqueda sobre el mapa */
    .map-search-bar {
      display: flex;
      gap: 8px;
      margin-bottom: 8px;
    }
    .map-search-bar input {
      flex: 1;
    }
    .map-search-bar button {
      white-space: nowrap;
      padding: 0 14px;
    }

    /* Coordenadas como read-only debajo del mapa */
    .coords-display {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 10px;
      margin-top: 8px;
    }
    .coords-display .form-group { margin: 0; }

    /* Slider de radio */
    #radioRange {
      width: 100%;
      accent-color: var(--primary);
      cursor: pointer;
    }
    .radio-label-row {
      display: flex;
      justify-content: space-between;
      font-size: 12px;
      color: var(--text-muted);
      font-weight: 600;
      margin-top: 4px;
    }

    /* Pin instrucciones */
    .map-tip {
      font-size: 11px;
      color: var(--text-muted);
      text-align: center;
      padding: 5px 0 2px;
    }
    .map-tip i { color: var(--primary); }

    /* Badge de coords en tabla */
    .coords-badge {
      display: inline-flex; align-items: center; gap: 5px;
      font-size: 11px; color: var(--text-muted);
    }
    .coords-badge.has-coords { color: var(--accent); }
    .coords-badge i { font-size: 10px; }

    /* Modal más ancho para acomodar el mapa */
    #modalPlanta .modal-box {
      max-width: 620px;
    }

    /* Leaflet overrides para tema oscuro */
    .leaflet-control-zoom a {
      background: var(--surface) !important;
      color: var(--text) !important;
      border-color: var(--border) !important;
    }
    .leaflet-control-attribution {
      background: rgba(0,0,0,0.5) !important;
      color: #999 !important;
      font-size: 9px !important;
    }
    .leaflet-popup-content-wrapper {
      background: var(--surface) !important;
      color: var(--text) !important;
      border: 1px solid var(--border) !important;
    }
    .leaflet-popup-tip { background: var(--surface) !important; }
  </style>
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
            <th>Coordenadas</th>
            <th>Radio</th>
            <th>Estado</th>
            <th>Creada</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody id="tablePlantas">
          <tr><td colspan="8" class="empty-state"><i class="fas fa-spinner fa-spin"></i><p>Cargando...</p></td></tr>
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
        <!-- Fila 1: Nombre y Código -->
        <div class="form-group">
          <label>Nombre *</label>
          <input class="form-input" type="text" id="plantaNombre" placeholder="Ej. Planta Norte" required>
        </div>
        <div class="form-group">
          <label>Código *</label>
          <input class="form-input" type="text" id="plantaCodigo" placeholder="Ej. PLT-01" required maxlength="20">
        </div>

        <!-- Fila 2: Dirección -->
        <div class="form-group span-2">
          <label>Dirección / Ubicación</label>
          <input class="form-input" type="text" id="plantaUbicacion" placeholder="Ej. Av. Industrial #123, Saltillo, Coah.">
        </div>

        <!-- Estado -->
        <div class="form-group">
          <label>Estado</label>
          <select class="form-select" id="plantaActiva">
            <option value="1">Activa</option>
            <option value="0">Inactiva</option>
          </select>
        </div>
      </div>

      <!-- ── Sección de Mapa ── -->
      <div style="margin-top:18px; padding-top:16px; border-top:1px solid var(--border);">
        <div style="display:flex; align-items:center; gap:8px; margin-bottom:12px;">
          <i class="fas fa-map-location-dot" style="color:var(--primary);"></i>
          <span style="font-weight:800; font-size:14px;">Ubicación GPS de la Planta</span>
          <span style="font-size:11px; color:var(--text-muted); margin-left:auto;">Opcional — para validar asistencia</span>
        </div>

        <!-- Búsqueda de dirección -->
        <div class="map-search-bar">
          <input class="form-input" type="text" id="mapSearchInput"
                 placeholder="Buscar dirección o lugar... (ej. Planta ROCEEL, Saltillo)">
          <button type="button" class="btn btn-ghost" id="mapSearchBtn">
            <i class="fas fa-search"></i> Buscar
          </button>
          <button type="button" class="btn btn-ghost" id="mapMyLocBtn" title="Usar mi ubicación actual">
            <i class="fas fa-location-crosshairs"></i>
          </button>
        </div>

        <!-- Mapa Leaflet -->
        <div id="mapContainer">
          <div id="plantaMap"></div>
        </div>
        <p class="map-tip"><i class="fas fa-hand-pointer"></i> Haz clic en el mapa para fijar la ubicación exacta de la planta</p>

        <!-- Coordenadas (read-only, se llenan al clicar) -->
        <div class="coords-display">
          <div class="form-group">
            <label>Latitud</label>
            <input class="form-input" type="text" id="plantaLat" placeholder="—" readonly
                   style="background:var(--surface-2); cursor:default;">
          </div>
          <div class="form-group">
            <label>Longitud</label>
            <input class="form-input" type="text" id="plantaLng" placeholder="—" readonly
                   style="background:var(--surface-2); cursor:default;">
          </div>
        </div>

        <!-- Radio permitido -->
        <div class="form-group" style="margin-top:14px;">
          <label>Radio permitido: <strong id="radioValor">100 m</strong></label>
          <input type="range" id="radioRange" min="10" max="1000" step="10" value="100">
          <div class="radio-label-row">
            <span>10 m (estricto)</span>
            <span>500 m</span>
            <span>1000 m (amplio)</span>
          </div>
        </div>

        <!-- Botón limpiar coords -->
        <button type="button" class="btn btn-ghost btn-sm" id="clearCoordsBtn" style="margin-top:6px;">
          <i class="fas fa-xmark"></i> Limpiar coordenadas
        </button>
      </div>

      <div class="modal-actions" style="margin-top:20px;">
        <button type="button" class="btn btn-ghost" id="cancelModalPlanta">Cancelar</button>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Guardar</button>
      </div>
    </form>
  </div>
</div>

<!-- Toast -->
<div class="toast-container" id="toastContainer"></div>

<!-- Leaflet JS -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
const API_P = '/src/php/api/plantas/';

let allPlantas = [];
let leafletMap = null;      // instancia del mapa
let plantaMarker = null;    // marcador del pin de la planta
let radioCircle  = null;    // círculo visual del radio
let mapIniciado  = false;   // el mapa solo se inicia al abrir el modal

// ── Variables del formulario de mapa ──
let coordsActuales = { lat: null, lng: null };

// ══════════════════════════════════════════════════════════
// CARGA Y RENDER DE TABLA
// ══════════════════════════════════════════════════════════
async function loadPlantas() {
  try {
    const r = await fetch(API_P + 'get_plantas.php');
    const d = await r.json();
    if (d.ok) { allPlantas = d.plantas; renderPlantas(allPlantas); }
    else toast('Error cargando plantas.', 'error');
  } catch { toast('Error de red.', 'error'); }
}

function renderPlantas(list) {
  const tbody = document.getElementById('tablePlantas');
  if (!list.length) {
    tbody.innerHTML = `<tr><td colspan="8"><div class="empty-state"><i class="fas fa-industry"></i><p>No hay plantas registradas.</p></div></td></tr>`;
    return;
  }
  tbody.innerHTML = list.map(p => {
    const tieneCoords = p.latitud !== null && p.longitud !== null;
    const coordsHtml = tieneCoords
      ? `<span class="coords-badge has-coords"><i class="fas fa-circle-check"></i>${p.latitud.toFixed(4)}, ${p.longitud.toFixed(4)}</span>`
      : `<span class="coords-badge"><i class="fas fa-circle-xmark"></i>Sin configurar</span>`;
    const radioHtml  = tieneCoords ? `<span class="badge badge-yellow">${p.radio_permitido ?? 100} m</span>` : '—';
    return `
      <tr>
        <td><span class="badge badge-yellow">${esc(p.codigo)}</span></td>
        <td style="font-weight:700;">${esc(p.nombre)}</td>
        <td style="color:var(--text-muted);">${esc(p.ubicacion || '—')}</td>
        <td>${coordsHtml}</td>
        <td>${radioHtml}</td>
        <td>${p.activa ? '<span class="badge badge-green">Activa</span>' : '<span class="badge badge-gray">Inactiva</span>'}</td>
        <td style="color:var(--text-muted);font-size:12px;">${fmtDate(p.created_at)}</td>
        <td>
          <button class="btn btn-ghost btn-sm" onclick="editPlanta(${p.id})"><i class="fas fa-pen"></i> Editar</button>
        </td>
      </tr>
    `;
  }).join('');
}

document.getElementById('searchPlantas').addEventListener('input', function () {
  const q = this.value.toLowerCase();
  renderPlantas(allPlantas.filter(p =>
    p.nombre.toLowerCase().includes(q) || p.codigo.toLowerCase().includes(q)
  ));
});

// ══════════════════════════════════════════════════════════
// MAPA LEAFLET
// ══════════════════════════════════════════════════════════
const SALTILLO = [25.4267, -101.0]; // Centro por defecto (Saltillo)

function iniciarMapa() {
  if (mapIniciado) return;
  mapIniciado = true;

  leafletMap = L.map('plantaMap', {
    center: SALTILLO,
    zoom:   13,
    zoomControl: true,
  });

  // Tile layer con tema oscuro (CartoDB Dark)
  L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
    attribution: '© OpenStreetMap contributors © CARTO',
    subdomains:  'abcd',
    maxZoom:     19,
  }).addTo(leafletMap);

  // Clic en el mapa → colocar pin
  leafletMap.on('click', (e) => {
    const { lat, lng } = e.latlng;
    colocarPin(lat, lng);
  });
}

/** Coloca (o mueve) el marcador y el círculo en el mapa */
function colocarPin(lat, lng) {
  coordsActuales = { lat, lng };

  // Icono personalizado tipo pin amarillo
  const icon = L.divIcon({
    html: `<div style="
      width:28px; height:28px;
      background:var(--primary, #f5c400);
      border: 3px solid #fff;
      border-radius: 50% 50% 50% 0;
      transform: rotate(-45deg);
      box-shadow: 0 2px 8px rgba(0,0,0,0.5);
    "></div>`,
    className: '',
    iconSize:   [28, 28],
    iconAnchor: [14, 28],
    popupAnchor:[0, -30],
  });

  if (plantaMarker) {
    plantaMarker.setLatLng([lat, lng]);
  } else {
    plantaMarker = L.marker([lat, lng], { icon, draggable: true }).addTo(leafletMap);
    // Soporte drag del marcador
    plantaMarker.on('dragend', (ev) => {
      const pos = ev.target.getLatLng();
      colocarPin(pos.lat, pos.lng);
    });
  }

  // Círculo del radio
  actualizarCirculo(lat, lng);

  // Centrar el mapa en el pin
  leafletMap.panTo([lat, lng]);

  // Actualizar inputs de coordenadas
  document.getElementById('plantaLat').value = lat.toFixed(7);
  document.getElementById('plantaLng').value = lng.toFixed(7);
}

/** Actualiza/crea el círculo del radio permitido */
function actualizarCirculo(lat, lng) {
  const radio = parseInt(document.getElementById('radioRange').value);
  if (radioCircle) {
    radioCircle.setLatLng([lat, lng]);
    radioCircle.setRadius(radio);
  } else {
    radioCircle = L.circle([lat, lng], {
      radius:      radio,
      color:       '#f5c400',
      fillColor:   '#f5c400',
      fillOpacity: 0.12,
      weight:      2,
      dashArray:   '6 4',
    }).addTo(leafletMap);
  }
}

/** Limpia el pin y el círculo */
function limpiarPin() {
  if (plantaMarker) { plantaMarker.remove(); plantaMarker = null; }
  if (radioCircle)  { radioCircle.remove();  radioCircle  = null; }
  coordsActuales = { lat: null, lng: null };
  document.getElementById('plantaLat').value = '';
  document.getElementById('plantaLng').value = '';
}

// Slider de radio → actualizar etiqueta y círculo
document.getElementById('radioRange').addEventListener('input', function () {
  const v = parseInt(this.value);
  document.getElementById('radioValor').textContent = v + ' m';
  if (coordsActuales.lat !== null) {
    actualizarCirculo(coordsActuales.lat, coordsActuales.lng);
  }
});

// Botón limpiar
document.getElementById('clearCoordsBtn').addEventListener('click', limpiarPin);

// ── Búsqueda de dirección vía Nominatim (OpenStreetMap) ──
document.getElementById('mapSearchBtn').addEventListener('click', buscarDireccion);
document.getElementById('mapSearchInput').addEventListener('keydown', (e) => {
  if (e.key === 'Enter') { e.preventDefault(); buscarDireccion(); }
});

async function buscarDireccion() {
  const query = document.getElementById('mapSearchInput').value.trim();
  if (!query) return;

  const btn = document.getElementById('mapSearchBtn');
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
  btn.disabled  = true;

  try {
    const url = `https://nominatim.openstreetmap.org/search?q=${encodeURIComponent(query)}&format=json&limit=1&addressdetails=1`;
    const r   = await fetch(url, { headers: { 'Accept-Language': 'es' } });
    const d   = await r.json();

    if (d.length) {
      const { lat, lon } = d[0];
      leafletMap.setView([parseFloat(lat), parseFloat(lon)], 16);
      colocarPin(parseFloat(lat), parseFloat(lon));
    } else {
      toast('No se encontró esa dirección. Intenta ser más específico.', 'error');
    }
  } catch {
    toast('Error al buscar la dirección.', 'error');
  } finally {
    btn.innerHTML = '<i class="fas fa-search"></i> Buscar';
    btn.disabled  = false;
  }
}

// ── Botón "Mi ubicación" ──
document.getElementById('mapMyLocBtn').addEventListener('click', async () => {
  if (!navigator.geolocation) {
    toast('Geolocalización no disponible.', 'error');
    return;
  }
  const btn = document.getElementById('mapMyLocBtn');
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
  btn.disabled  = true;

  navigator.geolocation.getCurrentPosition(
    (pos) => {
      const { latitude, longitude } = pos.coords;
      leafletMap.setView([latitude, longitude], 17);
      colocarPin(latitude, longitude);
      btn.innerHTML = '<i class="fas fa-location-crosshairs"></i>';
      btn.disabled  = false;
    },
    (err) => {
      toast('No se pudo obtener tu ubicación.', 'error');
      btn.innerHTML = '<i class="fas fa-location-crosshairs"></i>';
      btn.disabled  = false;
    },
    { enableHighAccuracy: true, timeout: 10000 }
  );
});

// ══════════════════════════════════════════════════════════
// MODAL — Abrir / Cerrar
// ══════════════════════════════════════════════════════════
const modalEl = document.getElementById('modalPlanta');
const formEl  = document.getElementById('formPlanta');

function abrirModal() {
  modalEl.classList.add('open');

  // Iniciar mapa la primera vez + forzar resize (el mapa puede no renderizar bien en display:none)
  setTimeout(() => {
    iniciarMapa();
    leafletMap.invalidateSize();
  }, 150);
}

function cerrarModal() {
  modalEl.classList.remove('open');
}

document.getElementById('btnNuevPlanta').addEventListener('click', () => {
  document.getElementById('modalPlantaTitulo').textContent = 'Nueva planta';
  formEl.reset();
  document.getElementById('plantaId').value = '';
  document.getElementById('radioRange').value = 100;
  document.getElementById('radioValor').textContent = '100 m';
  limpiarPin();
  abrirModal();
  leafletMap && leafletMap.setView(SALTILLO, 13);
});

['closeModalPlanta', 'cancelModalPlanta'].forEach(id =>
  document.getElementById(id).addEventListener('click', cerrarModal)
);

function editPlanta(id) {
  const p = allPlantas.find(x => x.id == id);
  if (!p) return;

  document.getElementById('modalPlantaTitulo').textContent  = 'Editar planta';
  document.getElementById('plantaId').value      = p.id;
  document.getElementById('plantaNombre').value  = p.nombre;
  document.getElementById('plantaCodigo').value  = p.codigo;
  document.getElementById('plantaUbicacion').value = p.ubicacion || '';
  document.getElementById('plantaActiva').value  = p.activa ? '1' : '0';

  const radio = p.radio_permitido ?? 100;
  document.getElementById('radioRange').value = radio;
  document.getElementById('radioValor').textContent = radio + ' m';

  abrirModal();

  // Colocar pin si hay coordenadas guardadas
  setTimeout(() => {
    leafletMap.invalidateSize();
    if (p.latitud !== null && p.longitud !== null) {
      leafletMap.setView([p.latitud, p.longitud], 16);
      colocarPin(p.latitud, p.longitud);
    } else {
      limpiarPin();
      leafletMap.setView(SALTILLO, 13);
    }
  }, 200);
}

// ══════════════════════════════════════════════════════════
// GUARDAR
// ══════════════════════════════════════════════════════════
formEl.addEventListener('submit', async (e) => {
  e.preventDefault();

  const body = {
    id:              document.getElementById('plantaId').value || null,
    nombre:          document.getElementById('plantaNombre').value.trim(),
    codigo:          document.getElementById('plantaCodigo').value.trim().toUpperCase(),
    ubicacion:       document.getElementById('plantaUbicacion').value.trim(),
    activa:          document.getElementById('plantaActiva').value === '1',
    latitud:         coordsActuales.lat,
    longitud:        coordsActuales.lng,
    radio_permitido: parseInt(document.getElementById('radioRange').value),
  };

  try {
    const r = await fetch(API_P + 'save_planta.php', {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify(body),
    });
    const d = await r.json();
    if (d.ok) {
      toast(body.id ? 'Planta actualizada.' : 'Planta creada.', 'success');
      cerrarModal();
      loadPlantas();
    } else {
      toast(d.msg || 'Error al guardar.', 'error');
    }
  } catch {
    toast('Error de red.', 'error');
  }
});

// ── Utils ──
function esc(s) { const d = document.createElement('div'); d.textContent = s ?? ''; return d.innerHTML; }
function fmtDate(s) { if (!s) return '—'; return new Date(s).toLocaleDateString('es-MX'); }

function toast(msg, type = 'success') {
  const c = document.getElementById('toastContainer');
  const t = document.createElement('div');
  t.className = `toast ${type}`;
  t.innerHTML = `<i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i> ${msg}`;
  c.appendChild(t);
  setTimeout(() => t.remove(), 3500);
}

loadPlantas();
</script>
</body>
</html>
