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

    /* Botón de acción del evento detectado */
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
  </style>
</head>
<body>

<!-- Header -->
<header class="checker-header">
  <div class="checker-logo">R</div>
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
    </div>

    <!-- Estado del día (se muestra cuando se detecta alguien) -->
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

<!-- face-api.js desde los modelos locales -->
<script src="https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js"></script>

<script>
/* ============================================================
   ROCEEL — Checador Facial
   Usa face-api.js con modelos TinyFaceDetector + FaceLandmark68Tiny
   + FaceRecognition. Matching local en el browser.
   ============================================================ */

const MODELS_URL     = '/src/face-models';
const API_DESC       = '/src/php/api/face/get_descriptors.php';
const API_ESTADO     = '/src/php/api/asistencia/estado_hoy.php';
const API_REGISTRAR  = '/src/php/api/asistencia/registrar.php';
const MATCH_THRESHOLD = 0.45;   // distancia máxima para reconocer (0=igual, 1=diferente)
const SCAN_INTERVAL   = 900;    // ms entre frames de detección
const CONFIRM_LOCK    = 1800;   // ms mínimo entre detecciones automáticas

let faceMatcher    = null;
let knownEmpleados = [];
let empleadoActual = null;
let pendingTipo    = null;
let scanning       = false;
let lastDetect     = 0;
let videoStream    = null;
let detectLoop     = null;

// ── Reloj ──
(function tick() {
  document.getElementById('liveClock').textContent =
    new Date().toLocaleTimeString('es-MX',{hour:'2-digit',minute:'2-digit',second:'2-digit'});
  setTimeout(tick,1000);
})();

// ══════════════════════════════════════════════════════════
// INICIO: cargar modelos → cámara → descriptores
// ══════════════════════════════════════════════════════════
async function init() {
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
  } catch(err) {
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
      video: { facingMode: 'user', width: {ideal:640}, height:{ideal:480} },
      audio: false
    });
    const video = document.getElementById('video');
    video.srcObject = videoStream;
    await video.play();

    // Mostrar sección cámara
    document.getElementById('secLoading').style.display = 'none';
    document.getElementById('secCamera').style.display  = 'block';

    // Ajustar canvas al video
    video.addEventListener('loadedmetadata', () => {
      const canvas = document.getElementById('overlay');
      canvas.width  = video.videoWidth;
      canvas.height = video.videoHeight;
    });

    iniciarDeteccion();
  } catch(err) {
    document.getElementById('loadingMsg').textContent =
      '❌ No se pudo acceder a la cámara. Permite el permiso e intenta de nuevo.';
    document.getElementById('loadingMsg').style.color = 'var(--danger)';
  }
}

// ══════════════════════════════════════════════════════════
// DETECCIÓN CONTINUA
// ══════════════════════════════════════════════════════════
function iniciarDeteccion() {
  scanning = true;
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

  const options = new faceapi.TinyFaceDetectorOptions({ inputSize:224, scoreThreshold:0.5 });

  const detections = await faceapi
    .detectAllFaces(video, options)
    .withFaceLandmarks(true)
    .withFaceDescriptors();

  if (!detections.length) {
    setScanStatus('Apunta tu rostro a la cámara...', '');
    return;
  }

  // Dibujar resultados en canvas
  const displaySize = { width: canvas.width, height: canvas.height };
  const resized = faceapi.resizeResults(detections, displaySize);

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

  // Evitar disparar múltiples veces para el mismo frame
  const now = Date.now();
  if (now - lastDetect < CONFIRM_LOCK) return;
  lastDetect = now;

  const empId = parseInt(bestMatch.match.label);
  const emp   = knownEmpleados.find(e => e.id === empId);
  if (!emp) return;

  setScanStatus(`✅ Reconocido: ${emp.nombre}`, 'found');
  await mostrarEmpleado(emp, bestMatch.match.distance);
}

async function mostrarEmpleado(emp, score) {
  detenerDeteccion();
  empleadoActual = emp;

  document.getElementById('detNombre').textContent = emp.nombre;
  document.getElementById('detMeta').textContent   = `NSS: ${emp.numero_empleado}  ·  score: ${(1-score).toFixed(2)}`;

  // Cargar estado del día
  await cargarEstadoHoy(emp.id);

  document.getElementById('hoyPanel').style.display = 'block';
  document.getElementById('actionPanel').classList.add('show');
}

async function cargarEstadoHoy(empId) {
  try {
    const r = await fetch(API_ESTADO + '?empleado_id=' + empId);
    const d = await r.json();
    if (!d.ok) return;

    const h   = d.registros;
    const fmt = ts => ts ? new Date(ts).toLocaleTimeString('es-MX',{hour:'2-digit',minute:'2-digit'}) : '—';

    document.getElementById('hoyEntrada').textContent    = fmt(h.entrada);
    document.getElementById('hoySalidaCom').textContent  = fmt(h.salida_comida);
    document.getElementById('hoyRegresoCom').textContent = fmt(h.regreso_comida);
    document.getElementById('hoySalida').textContent     = fmt(h.salida);

    // Habilitar botones según secuencia
    const e  = document.getElementById('btnEntrada');
    const sc = document.getElementById('btnSalidaComida');
    const rc = document.getElementById('btnRegresoComida');
    const s  = document.getElementById('btnSalida');

    e .disabled = !!h.entrada;
    sc.disabled = !h.entrada || !!h.salida_comida;
    rc.disabled = !h.salida_comida || !!h.regreso_comida;
    s .disabled = !h.regreso_comida || !!h.salida;

    if (h.entrada)        { e .classList.add('done'); }
    if (h.salida_comida)  { sc.classList.add('done'); }
    if (h.regreso_comida) { rc.classList.add('done'); }
    if (h.salida)         { s .classList.add('done'); }

  } catch { /* silencioso */ }
}

// ══════════════════════════════════════════════════════════
// BOTONES DE EVENTO
// ══════════════════════════════════════════════════════════
const LABELS = {
  entrada:       { icon:'🟢', title:'Registrar entrada',  msg:'¿Confirmas tu entrada?' },
  salida_comida: { icon:'🍽️', title:'Salida a comida',    msg:'¿Confirmas la salida a comida?' },
  regreso_comida:{ icon:'🔄', title:'Regreso de comida',  msg:'¿Confirmas tu regreso?' },
  salida:        { icon:'🔴', title:'Registrar salida',   msg:'¿Confirmas tu salida por hoy?' },
};

['btnEntrada','btnSalidaComida','btnRegresoComida','btnSalida'].forEach(id => {
  document.getElementById(id).addEventListener('click', function() {
    if (this.disabled || !empleadoActual) return;
    pendingTipo = this.dataset.tipo;
    const l = LABELS[pendingTipo] || {};
    document.getElementById('confirmIcon').textContent  = l.icon || '🕐';
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

  try {
    const r = await fetch(API_REGISTRAR, {
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body: JSON.stringify({
        empleado_id: empleadoActual.id,
        planta_id:   empleadoActual.planta_id,
        tipo_evento: pendingTipo,
      })
    });
    const d = await r.json();
    if (d.ok) {
      const l = LABELS[pendingTipo] || {};
      document.getElementById('successTitle').textContent = l.title || '¡Listo!';
      document.getElementById('successMsg').textContent   = 'Registrado: ' + new Date().toLocaleTimeString('es-MX',{hour:'2-digit',minute:'2-digit'});
      document.getElementById('successOverlay').classList.add('open');
      setTimeout(() => document.getElementById('successOverlay').classList.remove('open'), 2400);
      await cargarEstadoHoy(empleadoActual.id);
    } else {
      setScanStatus('⚠️ ' + (d.msg || 'Error al registrar.'), 'error');
    }
  } catch {
    setScanStatus('❌ Error de red.', 'error');
  }
  pendingTipo = null;
});

// ── Nuevo escaneo ──
document.getElementById('btnNuevoScan').addEventListener('click', resetScan);

function resetScan() {
  empleadoActual = null;
  pendingTipo    = null;
  lastDetect     = 0;

  // Limpiar UI
  document.getElementById('actionPanel').classList.remove('show');
  document.getElementById('hoyPanel').style.display = 'none';
  document.getElementById('overlay').getContext('2d').clearRect(
    0,0,
    document.getElementById('overlay').width,
    document.getElementById('overlay').height
  );
  ['btnEntrada','btnSalidaComida','btnRegresoComida','btnSalida'].forEach(id => {
    const el = document.getElementById(id);
    el.disabled = true;
    el.classList.remove('done','active');
  });
  setScanStatus('Apunta tu rostro a la cámara...', '');
  iniciarDeteccion();
}

function setScanStatus(msg, type='') {
  const el = document.getElementById('scanStatus');
  el.textContent = msg;
  el.className   = 'scan-status' + (type ? ' '+type : '');
}

// ══════════════════════════════════════════════════════════
// ARRANCAR
// ══════════════════════════════════════════════════════════
init();
</script>
</body>
</html>
