<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
  <title>Checador Facial | ROCEEL</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="/src/css/checador.css">
  <style>
    /* ── Cámara / facial overlay ── */
    .cam-wrap {
      position: relative;
      width: 100%;
      max-width: 340px;
      margin: 0 auto;
      border-radius: 16px;
      overflow: hidden;
      background: #000;
    }
    .cam-wrap video  { width:100%; display:block; border-radius:16px; }
    .cam-wrap canvas {
      position:absolute; top:0; left:0;
      width:100%; height:100%;
      pointer-events:none;
    }
    .scan-ring {
      position:absolute; top:50%; left:50%;
      transform: translate(-50%,-50%);
      width:180px; height:180px;
      border: 3px solid var(--primary);
      border-radius:50%;
      box-shadow: 0 0 0 9999px rgba(0,0,0,0.45);
      animation: pulse-ring 2s ease-in-out infinite;
      pointer-events:none;
    }
    @keyframes pulse-ring {
      0%,100% { border-color: var(--primary); box-shadow: 0 0 0 9999px rgba(0,0,0,0.45), 0 0 20px rgba(245,196,0,0.3); }
      50%      { border-color: var(--accent);  box-shadow: 0 0 0 9999px rgba(0,0,0,0.45), 0 0 30px rgba(34,197,94,0.4); }
    }
    .scan-status {
      text-align:center;
      padding:12px 20px 0;
      font-size:13px;
      font-weight:700;
      min-height:38px;
      color: var(--text-muted);
    }
    .scan-status.found  { color: var(--accent); }
    .scan-status.error  { color: var(--danger); }
    .scan-status.loading{ color: var(--primary); }

    /* ── Panel del empleado detectado ── */
    .action-panel {
      padding:0 24px 20px;
      display:none;
    }
    .action-panel.show { display:block; }
    .detected-emp {
      display:flex; align-items:center; gap:12px;
      background:var(--surface-2);
      border:1px solid var(--border);
      border-radius:12px;
      padding:14px 16px;
      margin-bottom:14px;
    }
    .detected-avatar {
      width:44px;height:44px;border-radius:50%;
      background:var(--primary-glow);
      border:2px solid rgba(245,196,0,0.3);
      display:grid;place-items:center;
      font-size:18px;color:var(--primary);
      flex-shrink:0;
    }
    .detected-name { font-weight:800; font-size:15px; }
    .detected-meta { font-size:11px; color:var(--text-muted); }

    .event-btns { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
    .event-btn  {
      display:flex;flex-direction:column;align-items:center;gap:6px;
      padding:14px 10px; border-radius:12px;
      border:1px solid var(--border); background:var(--surface-2);
      font-family:inherit;font-size:12px;font-weight:700;
      color:var(--text-muted); cursor:pointer; text-align:center;
      transition:all 0.2s; line-height:1.3;
    }
    .event-btn i { font-size:20px; }
    .event-btn.entrada:not(:disabled):hover, .event-btn.entrada.active {
      background:rgba(34,197,94,0.12); border-color:rgba(34,197,94,0.4); color:var(--accent);
    }
    .event-btn.comida:not(:disabled):hover, .event-btn.comida.active {
      background:rgba(245,196,0,0.12); border-color:rgba(245,196,0,0.35); color:var(--primary);
    }
    .event-btn.salida:not(:disabled):hover, .event-btn.salida.active {
      background:rgba(239,68,68,0.12); border-color:rgba(239,68,68,0.4); color:var(--danger);
    }
    .event-btn:disabled { opacity:0.3; cursor:not-allowed; }
    .event-btn.done { border-style:dashed; }

    .btn-nueva-scan {
      width:100%; margin-top:10px; padding:12px;
      border-radius:10px; background:var(--surface-2);
      border:1px solid var(--border-light);
      color:var(--text-muted); font-family:inherit;
      font-size:13px; font-weight:700; cursor:pointer;
    }

    /* Estado del día */
    .hoy-panel { padding:14px 24px 0; }
    .hoy-row   {
      display:flex;align-items:center;justify-content:space-between;
      padding:7px 0; border-bottom:1px solid var(--border); font-size:13px;
    }
    .hoy-row:last-child { border-bottom:none; }
    .hoy-row .lbl { color:var(--text-muted); font-weight:600; }
    .hoy-row .val { font-weight:700; }
    .hoy-row .val.g { color:var(--accent); }
    .hoy-row .val.y { color:var(--primary); }
    .hoy-row .val.r { color:var(--danger); }

    /* Cargando modelos */
    .models-loading {
      text-align:center; padding:32px 24px;
    }
    .models-loading i { font-size:2rem; color:var(--primary); margin-bottom:12px; }
    .models-loading p { font-size:13px; color:var(--text-muted); margin-top:8px; }
    .progress-bar-wrap {
      background:var(--surface-2); border-radius:999px;
      height:6px; overflow:hidden; margin-top:14px;
    }
    .progress-bar {
      height:100%; background:var(--primary);
      border-radius:999px; transition:width 0.4s ease;
      width:0%;
    }

    /* ── Geo badge (indicador de estado GPS) ── */
    .geo-badge {
      display:inline-flex; align-items:center; gap:6px;
      font-size:11px; font-weight:700;
      padding:4px 10px; border-radius:999px;
      margin-top:10px;
    }
    .geo-badge.idle     { background:rgba(100,116,139,0.15); color:var(--text-muted); }
    .geo-badge.loading  { background:rgba(245,196,0,0.12);   color:var(--primary); }
    .geo-badge.ok       { background:rgba(34,197,94,0.12);   color:var(--accent); }
    .geo-badge.error    { background:rgba(239,68,68,0.12);   color:var(--danger); }
    .geo-badge i { font-size:10px; }
    #geoBadgeWrap { text-align:center; padding:4px 0 8px; }

    /* ── Overlay de geo-error ── */
    .geo-error-overlay {
      position:fixed; inset:0; z-index:300;
      background:rgba(0,0,0,0.75);
      display:flex; align-items:center; justify-content:center;
      backdrop-filter:blur(4px);
      opacity:0; pointer-events:none;
      transition:opacity 0.25s;
    }
    .geo-error-overlay.open { opacity:1; pointer-events:all; }
    .geo-error-box {
      background:var(--surface);
      border:1px solid var(--border);
      border-radius:20px;
      padding:32px 28px;
      max-width:320px; width:90%;
      text-align:center;
    }
    .geo-error-icon { font-size:2.8rem; margin-bottom:14px; }
    .geo-error-box h3 { font-size:17px; font-weight:800; margin-bottom:8px; }
    .geo-error-box p  { font-size:13px; color:var(--text-muted); line-height:1.55; }
    .geo-error-btns   { display:flex; gap:10px; margin-top:20px; }
    .geo-error-btns button {
      flex:1; padding:11px; border-radius:10px;
      font-family:inherit; font-size:13px; font-weight:700; cursor:pointer;
    }
    .geo-btn-retry  { background:var(--primary); color:#000; border:none; }
    .geo-btn-cancel { background:var(--surface-2); color:var(--text-muted); border:1px solid var(--border); }
  </style>
</head>
<body>

<!-- Header -->
<header class="checker-header">
  <img src="./src/css/imagen/Logo-Roceel.jpg" alt="Logo ROCEEL" class="checker-logo" style="width: 60px; height: 60px; object-fit: contain; border-radius: 8px;">
  <div>
    <div class="checker-brand">ROCEEL</div>
    <div class="checker-brand-sub">Checador Facial</div>
  </div>
  <div class="checker-clock" id="liveClock">--:--:--</div>
</header>

<!-- Card principal -->
<div class="checker-card" id="mainCard">

  <!-- Cargando modelos -->
  <div class="models-loading" id="secLoading">
    <i class="fas fa-brain fa-spin"></i>
    <p id="loadingMsg">Cargando modelos de reconocimiento facial...</p>
    <div class="progress-bar-wrap">
      <div class="progress-bar" id="loadProgress"></div>
    </div>
  </div>

  <!-- Cámara (oculta hasta que carguen modelos) -->
  <div id="secCamera" style="display:none;">
    <div class="panel" style="padding-bottom:12px;">
      <div class="cam-wrap" id="camWrap">
        <video id="video" autoplay muted playsinline></video>
        <canvas id="overlay"></canvas>
        <div class="scan-ring" id="scanRing"></div>
      </div>
      <div class="scan-status" id="scanStatus">Apunta tu rostro a la cámara...</div>

      <!-- Indicador GPS -->
      <div id="geoBadgeWrap">
        <span class="geo-badge idle" id="geoBadge">
          <i class="fas fa-location-dot"></i> GPS inactivo
        </span>
      </div>
    </div>

    <!-- Estado del día -->
    <div class="hoy-panel" id="hoyPanel" style="display:none;">
      <div class="hoy-row"><span class="lbl"><i class="fas fa-sign-in-alt"></i> Entrada</span>      <span class="val g" id="hoyEntrada">—</span></div>
      <div class="hoy-row"><span class="lbl"><i class="fas fa-utensils"></i> Salida comida</span>    <span class="val y" id="hoySalidaCom">—</span></div>
      <div class="hoy-row"><span class="lbl"><i class="fas fa-undo"></i> Regreso comida</span>       <span class="val y" id="hoyRegresoCom">—</span></div>
      <div class="hoy-row"><span class="lbl"><i class="fas fa-sign-out-alt"></i> Salida</span>       <span class="val r" id="hoySalida">—</span></div>
    </div>

    <!-- Panel de acción -->
    <div class="action-panel" id="actionPanel">
      <div class="detected-emp">
        <div class="detected-avatar"><i class="fas fa-user-hard-hat"></i></div>
        <div>
          <div class="detected-name" id="detNombre">—</div>
          <div class="detected-meta" id="detMeta">—</div>
        </div>
      </div>
      <div class="event-btns">
        <button class="event-btn entrada" id="btnEntrada"       data-tipo="entrada"        disabled><i class="fas fa-sign-in-alt"></i>Entrada</button>
        <button class="event-btn comida"  id="btnSalidaComida"  data-tipo="salida_comida"  disabled><i class="fas fa-utensils"></i>Salida<br>comida</button>
        <button class="event-btn comida"  id="btnRegresoComida" data-tipo="regreso_comida" disabled><i class="fas fa-undo-alt"></i>Regreso<br>comida</button>
        <button class="event-btn salida"  id="btnSalida"        data-tipo="salida"         disabled><i class="fas fa-sign-out-alt"></i>Salida</button>
      </div>
      <button class="btn-nueva-scan" id="btnNuevoScan">
        <i class="fas fa-camera"></i> Escanear otra persona
      </button>
    </div>
  </div>
</div>

<!-- Confirm overlay -->
<div class="confirm-overlay" id="confirmOverlay">
  <div class="confirm-box">
    <div class="confirm-icon" id="confirmIcon">🕐</div>
    <h3 id="confirmTitle">Confirmar</h3>
    <p id="confirmMsg">¿Confirmas este registro?</p>
    <div class="confirm-btns">
      <button class="no"  id="confirmNo">Cancelar</button>
      <button class="yes" id="confirmYes">Sí, registrar</button>
    </div>
  </div>
</div>

<!-- Success overlay -->
<div class="success-overlay" id="successOverlay">
  <div class="success-box">
    <div class="success-circle"><i class="fas fa-check"></i></div>
    <h2 id="successTitle">¡Registrado!</h2>
    <p id="successMsg">Checada guardada.</p>
  </div>
</div>

<!-- Geo-error overlay -->
<div class="geo-error-overlay" id="geoErrorOverlay">
  <div class="geo-error-box">
    <div class="geo-error-icon" id="geoErrorIcon">📍</div>
    <h3 id="geoErrorTitle">Fuera de rango</h3>
    <p id="geoErrorMsg">No se pudo validar tu ubicación.</p>
    <div class="geo-error-btns">
      <button class="geo-btn-retry"  id="geoRetryBtn"><i class="fas fa-rotate-right"></i> Reintentar</button>
      <button class="geo-btn-cancel" id="geoCancelBtn">Cancelar</button>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js"></script>

<script>
/* ============================================================
   ROCEEL — Checador Facial con Geolocalización
   Flujo: Reconocimiento facial → GPS → Validación → Registro
   ============================================================ */

const MODELS_URL     = '/src/face-models';
const API_DESC       = '/src/php/api/face/get_descriptors.php';
const API_ESTADO     = '/src/php/api/asistencia/estado_hoy.php';
const API_REGISTRAR  = '/src/php/api/asistencia/registrar.php';
const MATCH_THRESHOLD = 0.45;
const SCAN_INTERVAL   = 900;
const CONFIRM_LOCK    = 1800;

// Opciones de geolocalización — alta precisión, timeout razonable
const GEO_OPTIONS = {
  enableHighAccuracy: true,
  timeout:            10000,   // 10 s
  maximumAge:         0,        // siempre fresco, no cache
};

let faceMatcher    = null;
let knownEmpleados = [];
let empleadoActual = null;
let pendingTipo    = null;
let scanning       = false;
let lastDetect     = 0;
let videoStream    = null;
let detectLoop     = null;

// Coordenadas GPS actuales del dispositivo
let geoActual      = null;  // { lat, lng } | null

// ── Reloj ──
(function tick() {
  document.getElementById('liveClock').textContent =
    new Date().toLocaleTimeString('es-MX', { hour:'2-digit', minute:'2-digit', second:'2-digit' });
  setTimeout(tick, 1000);
})();

// ══════════════════════════════════════════════════════════
// GEOLOCALIZACIÓN
// Obtiene la posición actual del dispositivo.
// Retorna { lat, lng } o lanza un error descriptivo.
// ══════════════════════════════════════════════════════════

/**
 * Verifica si el navegador soporta la Geolocation API.
 */
function geoSoportada() {
  return 'geolocation' in navigator;
}

/**
 * Obtiene la posición actual como Promise.
 * Usa watchPosition internamente para mayor velocidad en mobile.
 */
function obtenerPosicion() {
  return new Promise((resolve, reject) => {
    if (!geoSoportada()) {
      reject(new Error('Tu navegador no soporta geolocalización.'));
      return;
    }

    // Intentar con getCurrentPosition; falla silenciosa en algunos browsers
    // → fallback a watchPosition que es más confiable
    let resuelto = false;

    const watchId = navigator.geolocation.watchPosition(
      (pos) => {
        if (resuelto) return;
        resuelto = true;
        navigator.geolocation.clearWatch(watchId);
        resolve({ lat: pos.coords.latitude, lng: pos.coords.longitude });
      },
      (err) => {
        if (resuelto) return;
        resuelto = true;
        navigator.geolocation.clearWatch(watchId);
        reject(mapGeoError(err));
      },
      GEO_OPTIONS
    );

    // Safety timeout propio por si watchPosition no dispara el error a tiempo
    setTimeout(() => {
      if (!resuelto) {
        resuelto = true;
        navigator.geolocation.clearWatch(watchId);
        reject(new Error('Tiempo agotado al obtener tu ubicación (10 s). Verifica tu GPS.'));
      }
    }, GEO_OPTIONS.timeout + 500);
  });
}

/** Convierte el código de error de la API a texto legible */
function mapGeoError(err) {
  const msgs = {
    1: 'Permiso de ubicación denegado. Permite el acceso en la configuración del navegador.',
    2: 'No se pudo determinar tu posición. Verifica que el GPS esté activo.',
    3: 'Tiempo agotado al obtener ubicación. Intenta de nuevo.',
  };
  return new Error(msgs[err.code] || `Error de geolocalización (código ${err.code}).`);
}

/** Actualiza el badge de estado del GPS en la UI */
function setGeoBadge(estado, texto) {
  const el = document.getElementById('geoBadge');
  el.className = `geo-badge ${estado}`;
  const icons = {
    idle:    'fa-location-dot',
    loading: 'fa-spinner fa-spin',
    ok:      'fa-circle-check',
    error:   'fa-triangle-exclamation',
  };
  el.innerHTML = `<i class="fas ${icons[estado] || 'fa-location-dot'}"></i> ${texto}`;
}

// ══════════════════════════════════════════════════════════
// INICIO: cargar modelos → cámara → descriptores
// ══════════════════════════════════════════════════════════
async function init() {
  // Verificar soporte GPS antes de arrancar
  if (!geoSoportada()) {
    setGeoBadge('error', 'GPS no disponible en este dispositivo');
  }

  setProgress(5, 'Cargando detector de caras...');
  try {
    await faceapi.nets.tinyFaceDetector.loadFromUri(MODELS_URL);
    setProgress(35, 'Cargando landmarks faciales...');
    await faceapi.nets.faceLandmark68TinyNet.loadFromUri(MODELS_URL);
    setProgress(65, 'Cargando reconocimiento facial...');
    await faceapi.nets.faceRecognitionNet.loadFromUri(MODELS_URL);
    setProgress(85, 'Cargando descriptores de empleados...');
    await cargarDescriptores();
    setProgress(100, '¡Listo!');
    await iniciarCamara();
  } catch (err) {
    console.error(err);
    document.getElementById('loadingMsg').textContent = '❌ Error al cargar modelos: ' + err.message;
    document.getElementById('loadingMsg').style.color = 'var(--danger)';
  }
}

function setProgress(pct, msg) {
  document.getElementById('loadProgress').style.width = pct + '%';
  document.getElementById('loadingMsg').textContent   = msg;
}

async function cargarDescriptores() {
  const r = await fetch(API_DESC);
  const d = await r.json();
  if (!d.ok || !d.empleados.length) return;

  knownEmpleados = d.empleados;
  const labeled = d.empleados.map(e => {
    const desc = new Float32Array(e.descriptor);
    return new faceapi.LabeledFaceDescriptors(String(e.id), [desc]);
  });
  faceMatcher = new faceapi.FaceMatcher(labeled, MATCH_THRESHOLD);
}

async function iniciarCamara() {
  try {
    videoStream = await navigator.mediaDevices.getUserMedia({
      video: { facingMode: 'user', width: { ideal: 640 }, height: { ideal: 480 } },
      audio: false,
    });
    const video = document.getElementById('video');
    video.srcObject = videoStream;
    await video.play();

    document.getElementById('secLoading').style.display = 'none';
    document.getElementById('secCamera').style.display  = 'block';

    video.addEventListener('loadedmetadata', () => {
      const canvas = document.getElementById('overlay');
      canvas.width  = video.videoWidth;
      canvas.height = video.videoHeight;
    });

    iniciarDeteccion();
  } catch (err) {
    document.getElementById('loadingMsg').textContent =
      '❌ No se pudo acceder a la cámara. Permite el permiso e intenta de nuevo.';
    document.getElementById('loadingMsg').style.color = 'var(--danger)';
  }
}

// ══════════════════════════════════════════════════════════
// DETECCIÓN CONTINUA
// ══════════════════════════════════════════════════════════
function iniciarDeteccion() {
  scanning   = true;
  geoActual  = null;
  setGeoBadge('idle', 'GPS inactivo');
  detectLoop = setInterval(detectarRostro, SCAN_INTERVAL);
}

function detenerDeteccion() {
  scanning = false;
  clearInterval(detectLoop);
}

async function detectarRostro() {
  if (!scanning || !faceMatcher) return;

  const video  = document.getElementById('video');
  const canvas = document.getElementById('overlay');
  const ctx    = canvas.getContext('2d');
  ctx.clearRect(0, 0, canvas.width, canvas.height);

  if (video.readyState < 2) return;

  const options    = new faceapi.TinyFaceDetectorOptions({ inputSize: 224, scoreThreshold: 0.5 });
  const detections = await faceapi
    .detectAllFaces(video, options)
    .withFaceLandmarks(true)
    .withFaceDescriptors();

  if (!detections.length) {
    setScanStatus('Apunta tu rostro a la cámara...', '');
    return;
  }

  // Buscar mejor match
  let bestMatch = null, bestDist = 1;
  for (const d of detections) {
    const match = faceMatcher.findBestMatch(d.descriptor);
    if (match.label !== 'unknown' && match.distance < bestDist) {
      bestDist  = match.distance;
      bestMatch = { match, detection: d };
    }
  }

  if (!bestMatch) {
    setScanStatus('Rostro no reconocido...', 'error');
    return;
  }

  const now = Date.now();
  if (now - lastDetect < CONFIRM_LOCK) return;
  lastDetect = now;

  const empId = parseInt(bestMatch.match.label);
  const emp   = knownEmpleados.find(e => e.id === empId);
  if (!emp) return;

  setScanStatus(`✅ Reconocido: ${emp.nombre}`, 'found');

  // ── Detección exitosa → detener escaneo → iniciar GPS ──
  detenerDeteccion();
  await mostrarEmpleado(emp, bestMatch.match.distance);
}

// ══════════════════════════════════════════════════════════
// FLUJO POSTERIOR AL RECONOCIMIENTO FACIAL
// ══════════════════════════════════════════════════════════
async function mostrarEmpleado(emp, score) {
  empleadoActual = emp;

  document.getElementById('detNombre').textContent = emp.nombre;
  document.getElementById('detMeta').textContent   =
    `NSS: ${emp.numero_empleado}  ·  score: ${(1 - score).toFixed(2)}`;

  // Cargar estado del día
  await cargarEstadoHoy(emp.id);

  document.getElementById('hoyPanel').style.display = 'block';
  document.getElementById('actionPanel').classList.add('show');

  // Iniciar captura GPS simultáneamente
  iniciarGPS();
}

/**
 * Inicia la captura de posición GPS.
 * Se ejecuta en segundo plano; actualiza geoActual cuando resuelve.
 * Los botones de checada quedan habilitados después de que GPS resuelve.
 */
async function iniciarGPS() {
  if (!geoSoportada()) {
    setGeoBadge('error', 'Navegador sin GPS');
    // Sin GPS → deshabilitar botones ya que la planta puede requerir validación
    setScanStatus('⚠️ Geolocalización no disponible en este navegador.', 'error');
    return;
  }

  setGeoBadge('loading', 'Obteniendo ubicación...');
  setScanStatus('📡 Obteniendo tu ubicación GPS...', 'loading');

  try {
    const pos = await obtenerPosicion();
    geoActual  = pos;
    setGeoBadge('ok', `GPS activo (${pos.lat.toFixed(5)}, ${pos.lng.toFixed(5)})`);
    setScanStatus(`✅ Reconocido: ${empleadoActual?.nombre}`, 'found');

    // Habilitar botones de evento (respetando la lógica de secuencia)
    habilitarBotonesSegunEstado();
  } catch (err) {
    geoActual = null;
    setGeoBadge('error', 'Error de GPS');
    mostrarGeoError(
      '❌',
      'No se pudo obtener tu ubicación',
      err.message,
      false  // no es error de rango, es error de permisos/hw
    );
  }
}

// ══════════════════════════════════════════════════════════
// ESTADO DEL DÍA
// ══════════════════════════════════════════════════════════
let _estadoHoy = null;  // Cache del estado para re-habilitar botones post-GPS

async function cargarEstadoHoy(empId) {
  try {
    const r = await fetch(API_ESTADO + '?empleado_id=' + empId);
    const d = await r.json();
    if (!d.ok) return;

    _estadoHoy = d.registros;
    const h    = d.registros;
    const fmt  = ts => ts
      ? new Date(ts).toLocaleTimeString('es-MX', { hour:'2-digit', minute:'2-digit' })
      : '—';

    document.getElementById('hoyEntrada').textContent    = fmt(h.entrada);
    document.getElementById('hoySalidaCom').textContent  = fmt(h.salida_comida);
    document.getElementById('hoyRegresoCom').textContent = fmt(h.regreso_comida);
    document.getElementById('hoySalida').textContent     = fmt(h.salida);

    // Los botones se habilitan definitivamente en habilitarBotonesSegunEstado()
    // tras recibir el GPS; por ahora solo aplicar clases "done"
    ['btnEntrada','btnSalidaComida','btnRegresoComida','btnSalida'].forEach(id => {
      document.getElementById(id).disabled = true;  // bloqueados hasta GPS
    });

    const e  = document.getElementById('btnEntrada');
    const sc = document.getElementById('btnSalidaComida');
    const rc = document.getElementById('btnRegresoComida');
    const s  = document.getElementById('btnSalida');

    if (h.entrada)        { e .classList.add('done'); }
    if (h.salida_comida)  { sc.classList.add('done'); }
    if (h.regreso_comida) { rc.classList.add('done'); }
    if (h.salida)         { s .classList.add('done'); }

  } catch { /* silencioso */ }
}

/** Habilita botones respetando secuencia y el estado GPS */
function habilitarBotonesSegunEstado() {
  if (!_estadoHoy) return;
  const h  = _estadoHoy;
  const e  = document.getElementById('btnEntrada');
  const sc = document.getElementById('btnSalidaComida');
  const rc = document.getElementById('btnRegresoComida');
  const s  = document.getElementById('btnSalida');

  e .disabled = !!h.entrada;
  sc.disabled = !h.entrada || !!h.salida_comida;
  rc.disabled = !h.salida_comida || !!h.regreso_comida;
  s .disabled = !h.regreso_comida || !!h.salida;
}

// ══════════════════════════════════════════════════════════
// BOTONES DE EVENTO
// ══════════════════════════════════════════════════════════
const LABELS = {
  entrada:        { icon:'🟢', title:'Registrar entrada',  msg:'¿Confirmas tu entrada?' },
  salida_comida:  { icon:'🍽️', title:'Salida a comida',    msg:'¿Confirmas la salida a comida?' },
  regreso_comida: { icon:'🔄', title:'Regreso de comida',  msg:'¿Confirmas tu regreso?' },
  salida:         { icon:'🔴', title:'Registrar salida',   msg:'¿Confirmas tu salida por hoy?' },
};

['btnEntrada','btnSalidaComida','btnRegresoComida','btnSalida'].forEach(id => {
  document.getElementById(id).addEventListener('click', function () {
    if (this.disabled || !empleadoActual) return;
    pendingTipo = this.dataset.tipo;
    const l = LABELS[pendingTipo] || {};
    document.getElementById('confirmIcon').textContent  = l.icon  || '🕐';
    document.getElementById('confirmTitle').textContent = l.title || 'Confirmar';
    document.getElementById('confirmMsg').textContent   = l.msg   || '¿Confirmar?';
    document.getElementById('confirmOverlay').classList.add('open');
  });
});

document.getElementById('confirmNo').addEventListener('click', () => {
  document.getElementById('confirmOverlay').classList.remove('open');
  pendingTipo = null;
});

document.getElementById('confirmYes').addEventListener('click', async () => {
  document.getElementById('confirmOverlay').classList.remove('open');
  if (!pendingTipo || !empleadoActual) return;
  await registrarChecada(pendingTipo);
  pendingTipo = null;
});

// ══════════════════════════════════════════════════════════
// REGISTRO CON VALIDACIÓN GEOGRÁFICA
// ══════════════════════════════════════════════════════════
async function registrarChecada(tipo) {
  // Si no hay GPS todavía, intentar obtenerlo una vez más
  if (!geoActual && geoSoportada()) {
    setGeoBadge('loading', 'Obteniendo ubicación...');
    setScanStatus('📡 Verificando ubicación GPS...', 'loading');
    try {
      geoActual = await obtenerPosicion();
      setGeoBadge('ok', `GPS activo`);
    } catch (err) {
      mostrarGeoError('❌', 'Sin acceso a GPS', err.message, false);
      setScanStatus(`✅ Reconocido: ${empleadoActual?.nombre}`, 'found');
      return;
    }
  }

  setScanStatus('🔄 Registrando...', 'loading');

  try {
    const body = {
      empleado_id: empleadoActual.id,
      planta_id:   empleadoActual.planta_id,
      tipo_evento: tipo,
      face_score:  null,
    };

    // Adjuntar coordenadas si están disponibles
    if (geoActual) {
      body.latitud  = geoActual.lat;
      body.longitud = geoActual.lng;
    }

    const r = await fetch(API_REGISTRAR, {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify(body),
    });
    const d = await r.json();

    if (d.ok) {
      // Éxito
      const l = LABELS[tipo] || {};
      document.getElementById('successTitle').textContent = l.title || '¡Listo!';
      document.getElementById('successMsg').textContent   =
        'Registrado: ' + new Date().toLocaleTimeString('es-MX', { hour:'2-digit', minute:'2-digit' });
      document.getElementById('successOverlay').classList.add('open');
      setTimeout(() => document.getElementById('successOverlay').classList.remove('open'), 2400);
      await cargarEstadoHoy(empleadoActual.id);
      habilitarBotonesSegunEstado();
      setScanStatus(`✅ Reconocido: ${empleadoActual.nombre}`, 'found');

    } else if (d.geo_error) {
      // Error de distancia/GPS devuelto por el backend
      mostrarGeoError(
        '📍',
        'Fuera de rango',
        d.msg || 'No estás dentro del área permitida.',
        true  // es error de distancia → ofrecer reintentar GPS
      );
      setScanStatus(`✅ Reconocido: ${empleadoActual.nombre}`, 'found');

    } else {
      setScanStatus('⚠️ ' + (d.msg || 'Error al registrar.'), 'error');
    }
  } catch {
    setScanStatus('❌ Error de red.', 'error');
  }
}

// ══════════════════════════════════════════════════════════
// GEO-ERROR OVERLAY
// ══════════════════════════════════════════════════════════
let _geoRetryTipo = null;  // tipo de evento que se intenta al reintentar

function mostrarGeoError(icono, titulo, msg, esRangoError) {
  document.getElementById('geoErrorIcon').textContent  = icono;
  document.getElementById('geoErrorTitle').textContent = titulo;
  document.getElementById('geoErrorMsg').textContent   = msg;
  _geoRetryTipo = esRangoError ? pendingTipo : null;
  document.getElementById('geoErrorOverlay').classList.add('open');
}

document.getElementById('geoCancelBtn').addEventListener('click', () => {
  document.getElementById('geoErrorOverlay').classList.remove('open');
  _geoRetryTipo = null;
});

document.getElementById('geoRetryBtn').addEventListener('click', async () => {
  document.getElementById('geoErrorOverlay').classList.remove('open');

  // Reintentar obtener GPS
  geoActual = null;
  setGeoBadge('loading', 'Reintentando GPS...');
  setScanStatus('📡 Reintentando ubicación GPS...', 'loading');

  try {
    geoActual = await obtenerPosicion();
    setGeoBadge('ok', `GPS activo`);

    if (_geoRetryTipo) {
      // Había un evento pendiente → reintentarlo automáticamente
      await registrarChecada(_geoRetryTipo);
    } else {
      setScanStatus(`✅ Reconocido: ${empleadoActual?.nombre}`, 'found');
    }
  } catch (err) {
    geoActual = null;
    setGeoBadge('error', 'Error de GPS');
    mostrarGeoError('❌', 'No se pudo obtener ubicación', err.message, false);
    setScanStatus(`✅ Reconocido: ${empleadoActual?.nombre}`, 'found');
  }
  _geoRetryTipo = null;
});

// ── Nuevo escaneo ──
document.getElementById('btnNuevoScan').addEventListener('click', resetScan);

function resetScan() {
  empleadoActual = null;
  pendingTipo    = null;
  lastDetect     = 0;
  geoActual      = null;
  _estadoHoy     = null;
  _geoRetryTipo  = null;

  document.getElementById('actionPanel').classList.remove('show');
  document.getElementById('hoyPanel').style.display = 'none';
  document.getElementById('overlay').getContext('2d').clearRect(
    0, 0,
    document.getElementById('overlay').width,
    document.getElementById('overlay').height
  );
  ['btnEntrada','btnSalidaComida','btnRegresoComida','btnSalida'].forEach(id => {
    const el = document.getElementById(id);
    el.disabled = true;
    el.classList.remove('done', 'active');
  });
  setScanStatus('Apunta tu rostro a la cámara...', '');
  iniciarDeteccion();
}

function setScanStatus(msg, type = '') {
  const el = document.getElementById('scanStatus');
  el.textContent = msg;
  el.className   = 'scan-status' + (type ? ' ' + type : '');
}

// ══════════════════════════════════════════════════════════
// ARRANCAR
// ══════════════════════════════════════════════════════════
init();
</script>
</body>
</html>
