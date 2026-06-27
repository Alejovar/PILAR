<?php
// /checador.php
require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/db_connection.php';

function getLivenessConfig(): string {
    global $conn;
    // Crear tabla si no existe aún
    $conn->query("
        CREATE TABLE IF NOT EXISTS sistema_config (
            clave      VARCHAR(50)  NOT NULL PRIMARY KEY,
            valor      VARCHAR(255) NOT NULL,
            updated_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $r = $conn->query("SELECT valor FROM sistema_config WHERE clave = 'liveness' LIMIT 1");
    $row = $r ? $r->fetch_assoc() : null;
    return ($row && $row['valor'] === 'on') ? 'true' : 'false';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
  <title>Checador Facial | PILAR</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="/src/css/checador.css">
  <style>
    .cam-wrap {
      position:relative; width:100%; max-width:340px;
      margin:0 auto; border-radius:16px; overflow:hidden; background:#000;
    }
    .cam-wrap video  { width:100%; display:block; border-radius:16px; transform:scaleX(-1); }
    .cam-wrap canvas {
      position:absolute; top:0; left:0;
      width:100%; height:100%; pointer-events:none;
    }
    .scan-ring {
      position:absolute; top:50%; left:50%;
      transform:translate(-50%,-50%);
      width:180px; height:180px;
      border:3px solid var(--primary); border-radius:50%;
      box-shadow:0 0 0 9999px rgba(0,0,0,0.45);
      animation:pulse-ring 2s ease-in-out infinite;
      pointer-events:none;
    }
    @keyframes pulse-ring {
      0%,100%{ border-color:var(--primary); box-shadow:0 0 0 9999px rgba(0,0,0,0.45),0 0 20px rgba(245,196,0,0.3); }
      50%    { border-color:var(--accent);  box-shadow:0 0 0 9999px rgba(0,0,0,0.45),0 0 30px rgba(34,197,94,0.4); }
    }
    .scan-status {
      text-align:center; padding:12px 20px 0;
      font-size:13px; font-weight:700; min-height:38px; color:var(--text-muted);
    }
    .scan-status.found   { color:var(--accent); }
    .scan-status.error   { color:var(--danger); }
    .scan-status.loading { color:var(--primary); }

    /* ── Liveness challenge overlay ── */
    .liveness-overlay {
      position:absolute; inset:0;
      background:rgba(0,0,0,0.82);
      display:flex; flex-direction:column;
      align-items:center; justify-content:center;
      border-radius:16px; gap:10px;
      opacity:0; pointer-events:none;
      transition:opacity 0.25s;
      z-index:10;
    }
    .liveness-overlay.show { opacity:1; pointer-events:all; }
    .challenge-icon { font-size:2.4rem; }
    .challenge-text {
      font-size:14px; font-weight:800;
      color:#fff; text-align:center;
      padding:0 16px; line-height:1.4;
    }
    .challenge-sub  { font-size:11px; color:rgba(255,255,255,0.55); }

    /* Barra de progreso de liveness */
    .liveness-bar-wrap {
      width:80%; background:rgba(255,255,255,0.15);
      border-radius:999px; height:5px; overflow:hidden;
    }
    .liveness-bar {
      height:100%; background:var(--accent);
      border-radius:999px; width:0%;
      transition:width 0.2s ease;
    }

    /* Indicador de parpadeos */
    .blink-dots {
      display:flex; gap:6px; margin-top:2px;
    }
    .blink-dot {
      width:10px; height:10px; border-radius:50%;
      background:rgba(255,255,255,0.25);
      transition:background 0.15s;
    }
    .blink-dot.done { background:var(--accent); }

    /* Timeout bar (rojo) */
    .timeout-bar-wrap {
      width:80%; background:rgba(255,255,255,0.1);
      border-radius:999px; height:3px; overflow:hidden;
    }
    .timeout-bar {
      height:100%; background:var(--danger);
      border-radius:999px; width:100%;
      transition:width linear;
    }

    /* Rate-limit badge */
    .rate-limit-box {
      background:var(--danger-glow);
      border:1px solid rgba(239,68,68,0.3);
      border-radius:12px; padding:16px 20px;
      text-align:center; margin:12px 20px;
      display:none;
    }
    .rate-limit-box.show { display:block; }
    .rate-limit-box p { font-size:13px; font-weight:700; color:var(--danger); }
    .rate-limit-box span { font-size:11px; color:var(--text-muted); }

    .action-panel { padding:0 24px 20px; display:none; }
    .action-panel.show { display:block; }
    .detected-emp {
      display:flex; align-items:center; gap:12px;
      background:var(--surface-2); border:1px solid var(--border);
      border-radius:12px; padding:14px 16px; margin-bottom:14px;
    }
    .detected-avatar {
      width:44px; height:44px; border-radius:50%;
      background:var(--primary-glow); border:2px solid rgba(245,196,0,0.3);
      display:grid; place-items:center; font-size:18px; color:var(--primary); flex-shrink:0;
    }
    .detected-name { font-weight:800; font-size:15px; }
    .detected-meta { font-size:11px; color:var(--text-muted); }
    .event-btns { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
    .event-btn {
      display:flex; flex-direction:column; align-items:center; gap:6px;
      padding:14px 10px; border-radius:12px;
      border:1px solid var(--border); background:var(--surface-2);
      font-family:inherit; font-size:12px; font-weight:700;
      color:var(--text-muted); cursor:pointer; text-align:center;
      transition:all 0.2s; line-height:1.3;
    }
    .event-btn i { font-size:20px; }
    .event-btn.entrada:not(:disabled):hover,.event-btn.entrada.active{ background:rgba(34,197,94,0.12);border-color:rgba(34,197,94,0.4);color:var(--accent); }
    .event-btn.comida:not(:disabled):hover,.event-btn.comida.active  { background:rgba(245,196,0,0.12);border-color:rgba(245,196,0,0.35);color:var(--primary); }
    .event-btn.salida:not(:disabled):hover,.event-btn.salida.active  { background:rgba(239,68,68,0.12);border-color:rgba(239,68,68,0.4);color:var(--danger); }
    .event-btn:disabled { opacity:0.3; cursor:not-allowed; }
    .event-btn.done { border-style:dashed; }
    .btn-nueva-scan {
      width:100%; margin-top:10px; padding:12px;
      border-radius:10px; background:var(--surface-2);
      border:1px solid var(--border-light);
      color:var(--text-muted); font-family:inherit; font-size:13px; font-weight:700; cursor:pointer;
    }
    .hoy-panel { padding:14px 24px 0; }
    .hoy-row {
      display:flex; align-items:center; justify-content:space-between;
      padding:7px 0; border-bottom:1px solid var(--border); font-size:13px;
    }
    .hoy-row:last-child { border-bottom:none; }
    .hoy-row .lbl { color:var(--text-muted); font-weight:600; }
    .hoy-row .val { font-weight:700; }
    .hoy-row .val.g { color:var(--accent); }
    .hoy-row .val.y { color:var(--primary); }
    .hoy-row .val.r { color:var(--danger); }
    .models-loading { text-align:center; padding:32px 24px; }
    .models-loading i { font-size:2rem; color:var(--primary); margin-bottom:12px; }
    .models-loading p { font-size:13px; color:var(--text-muted); margin-top:8px; }
    .progress-bar-wrap { background:var(--surface-2); border-radius:999px; height:6px; overflow:hidden; margin-top:14px; }
    .progress-bar { height:100%; background:var(--primary); border-radius:999px; transition:width 0.4s ease; width:0%; }

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

<header class="checker-header">
  <div class="checker-logo">P</div>
  <div>
    <div class="checker-brand">PILAR</div>
    <div class="checker-brand-sub">Checador Facial</div>
  </div>
  <div style="display:flex;flex-direction:column;align-items:flex-end;gap:4px;">
    <div class="checker-clock" id="liveClock">--:--:--</div>
    <span id="livenessModeTag" style="
      font-size:9px;font-weight:800;letter-spacing:0.06em;
      padding:2px 8px;border-radius:999px;white-space:nowrap;
    "></span>
  </div>
</header>

<div class="checker-card" id="mainCard">

  <!-- Cargando modelos -->
  <div class="models-loading" id="secLoading">
    <i class="fas fa-brain fa-spin"></i>
    <p id="loadingMsg">Cargando modelos de reconocimiento facial...</p>
    <div class="progress-bar-wrap"><div class="progress-bar" id="loadProgress"></div></div>
  </div>

  <!-- Cámara -->
  <div id="secCamera" style="display:none;">
    <div class="panel" style="padding-bottom:12px;">
      <div class="cam-wrap" id="camWrap">
        <video id="video" autoplay muted playsinline></video>
        <canvas id="overlay"></canvas>
        <div class="scan-ring" id="scanRing"></div>

        <!-- ── Liveness challenge overlay ── -->
        <div class="liveness-overlay" id="livenessOverlay">
          <div class="challenge-icon" id="challengeIcon">👁️</div>
          <div class="challenge-text" id="challengeText">Parpadea 2 veces</div>
          <div class="challenge-sub"  id="challengeSub">Mantén tu rostro centrado</div>

          <!-- Dots de parpadeo (solo para blink challenge) -->
          <div class="blink-dots" id="blinkDots">
            <div class="blink-dot" id="dot0"></div>
            <div class="blink-dot" id="dot1"></div>
          </div>

          <!-- Barra de progreso para head-turn -->
          <div class="liveness-bar-wrap" id="livenessBarWrap" style="display:none;">
            <div class="liveness-bar" id="livenessBar"></div>
          </div>

          <!-- Timeout bar -->
          <div class="timeout-bar-wrap">
            <div class="timeout-bar" id="timeoutBar"></div>
          </div>
        </div>
      </div>
      <div class="scan-status" id="scanStatus">Apunta tu rostro a la cámara...</div>
    </div>

    <!-- Rate limit -->
    <div class="rate-limit-box" id="rateLimitBox">
      <p>⛔ Demasiados intentos fallidos</p>
      <span id="rateLimitMsg">Espera antes de intentar de nuevo.</span>
    </div>

    <!-- Estado del día -->
    <div class="hoy-panel" id="hoyPanel" style="display:none;">
      <div class="hoy-row"><span class="lbl"><i class="fas fa-sign-in-alt"></i> Entrada</span>       <span class="val g" id="hoyEntrada">—</span></div>
      <div class="hoy-row"><span class="lbl"><i class="fas fa-utensils"></i> Salida comida</span>     <span class="val y" id="hoySalidaCom">—</span></div>
      <div class="hoy-row"><span class="lbl"><i class="fas fa-undo"></i> Regreso comida</span>        <span class="val y" id="hoyRegresoCom">—</span></div>
      <div class="hoy-row"><span class="lbl"><i class="fas fa-sign-out-alt"></i> Salida</span>        <span class="val r" id="hoySalida">—</span></div>
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
  PILAR — Checador Facial con Liveness Detection (3 capas)
   1. EAR Blink detection  — detecta parpadeos reales
   2. Head-turn challenge  — dirección aleatoria de cabeza
   3. Rate limiting        — bloqueo por intentos fallidos
   ============================================================ */

const MODELS_URL      = '/src/face-models';
const API_DESC        = '/src/php/api/face/get_descriptors.php';
const API_ESTADO      = '/src/php/api/asistencia/estado_hoy.php';
const API_REGISTRAR   = '/src/php/api/asistencia/registrar.php';
const MATCH_THRESHOLD = 0.45;
const SCAN_INTERVAL   = 120;   // ms — más rápido para liveness fluido
const CONFIRM_LOCK    = 2000;

// ── Feature flag — leído desde BD al cargar la página ──
const LIVENESS_ENABLED = <?= getLivenessConfig() ?>;

// ── Liveness config ──
const EAR_BLINK_THRESHOLD = 0.25;  // ajustado para faceLandmark68TinyNet
const BLINKS_REQUIRED     = 2;     // parpadeos necesarios
const CHALLENGE_TIMEOUT   = 8000;  // ms para completar el challenge
const HEAD_TURN_THRESHOLD = 12;    // grados de rotación aceptada como "giró"
const HEAD_HOLD_MS        = 600;   // ms que debe mantener la posición

// ── Rate limiting ──
const MAX_FAILED_ATTEMPTS = 5;
const BLOCK_DURATION_MS   = 60000; // 1 minuto

let faceMatcher    = null;
let knownEmpleados = [];
let empleadoActual = null;
let pendingTipo    = null;
let scanning       = false;
let lastDetect     = 0;
let videoStream    = null;
let detectLoop     = null;


// Opciones de geolocalización
const GEO_OPTIONS = {
  enableHighAccuracy: true,
  timeout:            10000,
  maximumAge:         0,
};

// Coordenadas GPS actuales del dispositivo
let geoActual = null;  // { lat, lng } | null
let _geoRetryTipo = null;

// Estado de liveness
let livenessState  = 'idle'; // idle | blink | head | passed | failed
let blinkCount     = 0;
let eyeWasClosed   = false;
let challengeTimer = null;
let timeoutAnim    = null;
let headChallenge  = null;   // { direction: 'left'|'right', holdStart: null }
let headHoldTimer  = null;
let pendingEmpId   = null;
let pendingScore   = null;

// Rate limiting (en memoria del cliente — el backend también valida)
let failedAttempts = parseInt(sessionStorage.getItem('rl_attempts') || '0');
let blockedUntil   = parseInt(sessionStorage.getItem('rl_until')    || '0');

// ── Reloj ──
(function tick() {
  document.getElementById('liveClock').textContent =
    new Date().toLocaleTimeString('es-MX',{hour:'2-digit',minute:'2-digit',second:'2-digit'});
  setTimeout(tick,1000);
})();

// Mostrar badge de modo en el header
(function() {
  const tag = document.getElementById('livenessModeTag');
  if (!tag) return;
  if (LIVENESS_ENABLED) {
    tag.textContent = '🛡️ MODO SEGURO';
    tag.style.background = 'rgba(34,197,94,0.15)';
    tag.style.color      = '#22c55e';
  } else {
    tag.textContent = '⚡ MODO ESTÁNDAR';
    tag.style.background = 'rgba(245,196,0,0.12)';
    tag.style.color      = '#f5c400';
  }
})();


// ══════════════════════════════════════════════════════════
// GEOLOCALIZACIÓN
// ══════════════════════════════════════════════════════════
function geoSoportada() { return 'geolocation' in navigator; }

function obtenerPosicion() {
  return new Promise((resolve, reject) => {
    if (!geoSoportada()) { reject(new Error('Tu navegador no soporta geolocalización.')); return; }
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
        const msgs = {
          1: 'Permiso de ubicación denegado.',
          2: 'No se pudo determinar tu posición.',
          3: 'Tiempo agotado al obtener ubicación.',
        };
        reject(new Error(msgs[err.code] || 'Error de geolocalización.'));
      },
      GEO_OPTIONS
    );
    setTimeout(() => {
      if (!resuelto) {
        resuelto = true;
        navigator.geolocation.clearWatch(watchId);
        reject(new Error('Tiempo agotado al obtener tu ubicación (10s).'));
      }
    }, GEO_OPTIONS.timeout + 500);
  });
}

function setGeoBadge(estado, texto) {
  const el = document.getElementById('geoBadge');
  if (!el) return;
  el.className = `geo-badge ${estado}`;
  const icons = { idle:'fa-location-dot', loading:'fa-spinner fa-spin', ok:'fa-circle-check', error:'fa-triangle-exclamation' };
  el.innerHTML = `<i class="fas ${icons[estado]||'fa-location-dot'}"></i> ${texto}`;
}

function mostrarGeoError(icono, titulo, msg, esRangoError) {
  document.getElementById('geoErrorIcon').textContent  = icono;
  document.getElementById('geoErrorTitle').textContent = titulo;
  document.getElementById('geoErrorMsg').textContent   = msg;
  _geoRetryTipo = esRangoError ? pendingTipo : null;
  document.getElementById('geoErrorOverlay').classList.add('open');
}

/**
 * Inicia GPS en segundo plano tras reconocimiento facial.
 * Habilita botones cuando resuelve.
 */
async function iniciarGPS() {
  if (!geoSoportada()) {
    setGeoBadge('error', 'Navegador sin GPS');
    setScanStatus('⚠️ Geolocalización no disponible.', 'error');
    return;
  }
  setGeoBadge('loading', 'Obteniendo ubicación...');
  try {
    const pos = await obtenerPosicion();
    geoActual  = pos;
    setGeoBadge('ok', `GPS activo (${pos.lat.toFixed(5)}, ${pos.lng.toFixed(5)})`);
    habilitarBotonesSegunEstado();
  } catch(err) {
    geoActual = null;
    setGeoBadge('error', 'Error de GPS');
    mostrarGeoError('❌', 'No se pudo obtener tu ubicación', err.message, false);
  }
}

// Geo-error overlay buttons
document.getElementById('geoCancelBtn').addEventListener('click', () => {
  document.getElementById('geoErrorOverlay').classList.remove('open');
  _geoRetryTipo = null;
});

document.getElementById('geoRetryBtn').addEventListener('click', async () => {
  document.getElementById('geoErrorOverlay').classList.remove('open');
  geoActual = null;
  setGeoBadge('loading', 'Reintentando GPS...');
  try {
    geoActual = await obtenerPosicion();
    setGeoBadge('ok', 'GPS activo');
    if (_geoRetryTipo) await registrarChecada(_geoRetryTipo);
    else setScanStatus(`✅ Verificado: ${empleadoActual?.nombre}`, 'found');
  } catch(err) {
    geoActual = null;
    setGeoBadge('error', 'Error de GPS');
    mostrarGeoError('❌', 'No se pudo obtener ubicación', err.message, false);
  }
  _geoRetryTipo = null;
});

// ══════════════════════════════════════════════════════════
// RATE LIMITING
// ══════════════════════════════════════════════════════════
function isBlocked() {
  if (Date.now() < blockedUntil) return true;
  if (blockedUntil && Date.now() >= blockedUntil) {
    // Bloqueo expiró — limpiar
    failedAttempts = 0;
    blockedUntil   = 0;
    sessionStorage.removeItem('rl_attempts');
    sessionStorage.removeItem('rl_until');
    document.getElementById('rateLimitBox').classList.remove('show');
  }
  return false;
}

function registrarFallo() {
  failedAttempts++;
  sessionStorage.setItem('rl_attempts', failedAttempts);
  if (failedAttempts >= MAX_FAILED_ATTEMPTS) {
    blockedUntil = Date.now() + BLOCK_DURATION_MS;
    sessionStorage.setItem('rl_until', blockedUntil);
    mostrarRateLimit();
  }
}

function registrarExito() {
  failedAttempts = 0;
  blockedUntil   = 0;
  sessionStorage.removeItem('rl_attempts');
  sessionStorage.removeItem('rl_until');
}

function mostrarRateLimit() {
  detenerDeteccion();
  ocultarLiveness();
  const box = document.getElementById('rateLimitBox');
  box.classList.add('show');
  const segsRestantes = () => Math.ceil((blockedUntil - Date.now()) / 1000);
  document.getElementById('rateLimitMsg').textContent =
    `Espera ${segsRestantes()} segundos antes de intentar de nuevo.`;
  const iv = setInterval(() => {
    if (!isBlocked()) {
      clearInterval(iv);
      box.classList.remove('show');
      iniciarDeteccion();
    } else {
      document.getElementById('rateLimitMsg').textContent =
        `Espera ${segsRestantes()} segundos antes de intentar de nuevo.`;
    }
  }, 1000);
}

// ══════════════════════════════════════════════════════════
// EAR — Eye Aspect Ratio
// Usando los 6 puntos de cada ojo de face-api landmarks 68
// Ojo derecho: 36-41, Ojo izquierdo: 42-47
// ══════════════════════════════════════════════════════════
function calcEAR(eye) {
  // eye = array de 6 puntos {x,y}
  // EAR = (||p2-p6|| + ||p3-p5||) / (2 * ||p1-p4||)
  const dist = (a, b) => Math.hypot(a.x - b.x, a.y - b.y);
  const A = dist(eye[1], eye[5]);
  const B = dist(eye[2], eye[4]);
  const C = dist(eye[0], eye[3]);
  return (A + B) / (2.0 * C);
}

function getEyes(landmarks) {
  const pts = landmarks.positions;
  // face-api landmark 68: índices 36-41 ojo der, 42-47 ojo izq
  const rightEye = pts.slice(36, 42);
  const leftEye  = pts.slice(42, 48);
  return { rightEye, leftEye };
}

// ══════════════════════════════════════════════════════════
// HEAD POSE — estimación simple de yaw por landmarks
// Compara distancia nariz-oreja izq vs nariz-oreja der
// ══════════════════════════════════════════════════════════
function estimarYaw(landmarks) {
  const pts = landmarks.positions;
  // Punta de nariz: 30, Orejas aprox: 0 (der) y 16 (izq)
  const nose   = pts[30];
  const earR   = pts[0];
  const earL   = pts[16];
  const distR  = Math.hypot(nose.x - earR.x, nose.y - earR.y);
  const distL  = Math.hypot(nose.x - earL.x, nose.y - earL.y);
  // Yaw positivo = girado a la izquierda (desde cámara)
  // Normalizamos como ratio para que sea independiente del tamaño del rostro
  const ratio  = (distL - distR) / ((distL + distR) / 2) * 90;
  return ratio; // aprox grados de yaw
}

// ══════════════════════════════════════════════════════════
// LIVENESS — Máquina de estados
// ══════════════════════════════════════════════════════════
function iniciarLiveness(empId, score) {
  pendingEmpId  = empId;
  pendingScore  = score;
  blinkCount    = 0;
  eyeWasClosed  = false;
  livenessState = 'blink';

  // Mostrar challenge de parpadeo primero
  mostrarChallengeBlink();
  iniciarTimeout();
}

function mostrarChallengeBlink() {
  document.getElementById('challengeIcon').textContent  = '👁️';
  document.getElementById('challengeText').textContent  = `Parpadea ${BLINKS_REQUIRED} veces`;
  document.getElementById('challengeSub').textContent   = 'Mantén tu rostro frente a la cámara';
  document.getElementById('blinkDots').style.display    = 'flex';
  document.getElementById('livenessBarWrap').style.display = 'none';
  actualizarBlinkDots();
  document.getElementById('livenessOverlay').classList.add('show');
}

function mostrarChallengeHead() {
  // Elegir dirección aleatoria
  const dirs = [
  { key:'left',  icon:'➡️', text:'Gira la cabeza a la DERECHA' },
  { key:'right', icon:'⬅️', text:'Gira la cabeza a la IZQUIERDA' },
  ];
  headChallenge = { ...dirs[Math.floor(Math.random() * dirs.length)], holdStart: null };

  document.getElementById('challengeIcon').textContent     = headChallenge.icon;
  document.getElementById('challengeText').textContent     = headChallenge.text;
  document.getElementById('challengeSub').textContent      = `Mantén la posición ${HEAD_HOLD_MS/1000}s`;
  document.getElementById('blinkDots').style.display       = 'none';
  document.getElementById('livenessBarWrap').style.display = 'block';
  document.getElementById('livenessBar').style.width       = '0%';
  livenessState = 'head';
}

function actualizarBlinkDots() {
  for (let i = 0; i < BLINKS_REQUIRED; i++) {
    const dot = document.getElementById('dot' + i);
    if (dot) dot.classList.toggle('done', i < blinkCount);
  }
}

function iniciarTimeout() {
  clearTimeout(challengeTimer);
  const bar   = document.getElementById('timeoutBar');
  const start = Date.now();

  // Animar barra de timeout
  bar.style.transition = `width ${CHALLENGE_TIMEOUT}ms linear`;
  bar.style.width      = '0%';

  challengeTimer = setTimeout(() => {
    if (livenessState !== 'passed') {
      livenessFailed('⏱️ Tiempo agotado. Inténtalo de nuevo.');
    }
  }, CHALLENGE_TIMEOUT);
}

function ocultarLiveness() {
  document.getElementById('livenessOverlay').classList.remove('show');
  clearTimeout(challengeTimer);
  clearTimeout(headHoldTimer);
  const bar = document.getElementById('timeoutBar');
  bar.style.transition = 'none';
  bar.style.width      = '100%';
}

function livenessAprobado() {
  livenessState = 'passed';
  ocultarLiveness();
  registrarExito();

  const emp = knownEmpleados.find(e => e.id === pendingEmpId);
  if (emp) mostrarEmpleado(emp, pendingScore);
}

function livenessRechazado(msg) {
  livenessState = 'failed';
  ocultarLiveness();
  registrarFallo();

  if (isBlocked()) {
    mostrarRateLimit();
    return;
  }

  setScanStatus('❌ ' + msg, 'error');
  setTimeout(() => {
    if (!isBlocked()) {
      livenessState = 'idle';
      pendingEmpId  = null;
      pendingScore  = null;
      lastDetect    = 0;
      setScanStatus('Apunta tu rostro a la cámara...', '');
      iniciarDeteccion();
    }
  }, 2500);
}

// Alias para legibilidad
const livenessAprobado_fn  = livenessAprobado;
const livenessRechazado_fn = livenessRechazado;
function livenessFailed(msg) { livenessRechazado_fn(msg); }

// ══════════════════════════════════════════════════════════
// PROCESAR LANDMARKS EN CADA FRAME (durante liveness)
// ══════════════════════════════════════════════════════════
function procesarLivenessFrame(landmarks) {
  if (livenessState === 'blink') {
    procesarBlink(landmarks);
  } else if (livenessState === 'head') {
    procesarHeadTurn(landmarks);
  }
}

function procesarBlink(landmarks) {
  const { rightEye, leftEye } = getEyes(landmarks);
  const earR  = calcEAR(rightEye);
  const earL  = calcEAR(leftEye);
  const ear   = (earR + earL) / 2;

  console.log('EAR:', ear.toFixed(3), '| closed:', ear < EAR_BLINK_THRESHOLD);

  const closed = ear < EAR_BLINK_THRESHOLD;

  if (closed && !eyeWasClosed) {
    eyeWasClosed = true;
  } else if (!closed && eyeWasClosed) {
    // Ojo se abrió después de estar cerrado = parpadeo completo
    eyeWasClosed = false;
    blinkCount++;
    actualizarBlinkDots();

    if (blinkCount >= BLINKS_REQUIRED) {
      // Parpadeos completos → pasar a head challenge
      clearTimeout(challengeTimer);
      mostrarChallengeHead();
      iniciarTimeout();
    }
  }
}

function procesarHeadTurn(landmarks) {
  const yaw = estimarYaw(landmarks);
  // yaw positivo = izquierda, negativo = derecha (desde perspectiva del usuario)
  const giroDetectado = headChallenge.key === 'left'
    ? yaw >  HEAD_TURN_THRESHOLD
    : yaw < -HEAD_TURN_THRESHOLD;

  const bar = document.getElementById('livenessBar');

  if (giroDetectado) {
    if (!headChallenge.holdStart) {
      headChallenge.holdStart = Date.now();
    }
    const elapsed = Date.now() - headChallenge.holdStart;
    const pct     = Math.min(100, (elapsed / HEAD_HOLD_MS) * 100);
    bar.style.width = pct + '%';

    if (elapsed >= HEAD_HOLD_MS) {
      livenessAprobado();
    }
  } else {
    // Volvió al centro o fue la dirección equivocada — resetear hold
    headChallenge.holdStart = null;
    bar.style.width = '0%';
  }
}

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
  const labeled  = d.empleados.map(e => {
    return new faceapi.LabeledFaceDescriptors(String(e.id), [new Float32Array(e.descriptor)]);
  });
  faceMatcher = new faceapi.FaceMatcher(labeled, MATCH_THRESHOLD);
}

async function iniciarCamara() {
  try {
    videoStream = await navigator.mediaDevices.getUserMedia({
      video:{ facingMode:'user', width:{ideal:640}, height:{ideal:480} }, audio:false
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
  scanning   = true;
  detectLoop = setInterval(detectarRostro, SCAN_INTERVAL);
}

function detenerDeteccion() {
  scanning = false;
  clearInterval(detectLoop);
}

async function detectarRostro() {
  if (!faceMatcher) return;

  // Aunque estemos en liveness, seguimos corriendo para leer landmarks
  const video  = document.getElementById('video');
  const canvas = document.getElementById('overlay');
  const ctx    = canvas.getContext('2d');
  ctx.clearRect(0, 0, canvas.width, canvas.height);
  if (video.readyState < 2) return;

  const options    = new faceapi.TinyFaceDetectorOptions({ inputSize:224, scoreThreshold:0.5 });
  const detections = await faceapi
    .detectAllFaces(video, options)
    .withFaceLandmarks(true)
    .withFaceDescriptors();

  if (!detections.length) {
    if (livenessState === 'idle') setScanStatus('Apunta tu rostro a la cámara...', '');
    return;
  }

  // ── Si estamos en liveness activo → procesar landmarks ──
  if (livenessState === 'blink' || livenessState === 'head') {
    // Usar el detection con mayor confianza
    const best = detections.reduce((a,b) => a.detection.score > b.detection.score ? a : b);
    procesarLivenessFrame(best.landmarks);
    return;
  }

  // ── Modo scan normal ──
  if (!scanning) return;
  if (isBlocked()) return;

  let bestMatch = null, bestDist = 1;
  for (const d of detections) {
    const match = faceMatcher.findBestMatch(d.descriptor);
    if (match.label !== 'unknown' && match.distance < bestDist) {
      bestDist  = match.distance;
      bestMatch = { match, detection:d };
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

  setScanStatus(`🔍 Verificando identidad...`, 'loading');

  // ── Feature flag: si liveness está OFF → pasar directo ──
  if (!LIVENESS_ENABLED) {
    const emp = knownEmpleados.find(e => e.id === empId);
    if (emp) await mostrarEmpleado(emp, bestMatch.match.distance);
    return;
  }

  livenessState = 'starting';
  iniciarLiveness(empId, bestMatch.match.distance);
}

// ══════════════════════════════════════════════════════════
// MOSTRAR EMPLEADO (después de pasar liveness)
// ══════════════════════════════════════════════════════════
async function mostrarEmpleado(emp, score) {
  detenerDeteccion();
  empleadoActual = emp;

  setScanStatus(`✅ Verificado: ${emp.nombre}`, 'found');
  document.getElementById('detNombre').textContent = emp.nombre;
  document.getElementById('detMeta').textContent   =
    `NSS: ${emp.numero_empleado}  ·  score: ${(1-score).toFixed(2)}`;

  await cargarEstadoHoy(emp.id);
  document.getElementById('hoyPanel').style.display = 'block';
  document.getElementById('actionPanel').classList.add('show');

  // Iniciar GPS en paralelo
  iniciarGPS();
}


let _estadoHoy = null;

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

async function cargarEstadoHoy(empId) {
  try {
    const r = await fetch(API_ESTADO + '?empleado_id=' + empId);
    const d = await r.json();
    if (!d.ok) return;
    _estadoHoy = d.registros;
    const h   = d.registros;
    const fmt = ts => ts ? new Date(ts).toLocaleTimeString('es-MX',{hour:'2-digit',minute:'2-digit'}) : '—';
    document.getElementById('hoyEntrada').textContent    = fmt(h.entrada);
    document.getElementById('hoySalidaCom').textContent  = fmt(h.salida_comida);
    document.getElementById('hoyRegresoCom').textContent = fmt(h.regreso_comida);
    document.getElementById('hoySalida').textContent     = fmt(h.salida);
    const e  = document.getElementById('btnEntrada');
    const sc = document.getElementById('btnSalidaComida');
    const rc = document.getElementById('btnRegresoComida');
    const s  = document.getElementById('btnSalida');
    // Botones bloqueados hasta GPS — solo aplicar clases done
    e .disabled = true; sc.disabled = true; rc.disabled = true; s .disabled = true;
    if (h.entrada)        e .classList.add('done');
    if (h.salida_comida)  sc.classList.add('done');
    if (h.regreso_comida) rc.classList.add('done');
    if (h.salida)         s .classList.add('done');
  } catch {}
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
  document.getElementById(id).addEventListener('click', function() {
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

async function registrarChecada(tipo) {
  // Si no hay GPS todavía, intentar una vez más
  if (!geoActual && geoSoportada()) {
    setGeoBadge('loading', 'Obteniendo ubicación...');
    try {
      geoActual = await obtenerPosicion();
      setGeoBadge('ok', 'GPS activo');
    } catch(err) {
      mostrarGeoError('❌', 'Sin acceso a GPS', err.message, false);
      setScanStatus(`✅ Verificado: ${empleadoActual?.nombre}`, 'found');
      return;
    }
  }

  setScanStatus('🔄 Registrando...', 'loading');
  try {
    const body = {
      empleado_id: empleadoActual.id,
      planta_id:   empleadoActual.planta_id,
      tipo_evento: tipo,
    };
    if (geoActual) { body.latitud = geoActual.lat; body.longitud = geoActual.lng; }

    const r = await fetch(API_REGISTRAR, {
      method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(body)
    });
    const d = await r.json();
    if (d.ok) {
      const l = LABELS[tipo] || {};
      document.getElementById('successTitle').textContent = l.title || '¡Listo!';
      document.getElementById('successMsg').textContent   =
        'Registrado: ' + new Date().toLocaleTimeString('es-MX',{hour:'2-digit',minute:'2-digit'});
      document.getElementById('successOverlay').classList.add('open');
      setTimeout(() => document.getElementById('successOverlay').classList.remove('open'), 2400);
      await cargarEstadoHoy(empleadoActual.id);
      habilitarBotonesSegunEstado();
      setScanStatus(`✅ Verificado: ${empleadoActual?.nombre}`, 'found');
    } else if (d.geo_error) {
      mostrarGeoError('📍', 'Fuera de rango', d.msg || 'No estás dentro del área permitida.', true);
      setScanStatus(`✅ Verificado: ${empleadoActual?.nombre}`, 'found');
    } else {
      setScanStatus('⚠️ ' + (d.msg || 'Error al registrar.'), 'error');
    }
  } catch { setScanStatus('❌ Error de red.', 'error'); }
}

document.getElementById('btnNuevoScan').addEventListener('click', resetScan);

function resetScan() {
  empleadoActual = null;
  pendingTipo    = null;
  lastDetect     = 0;
  livenessState  = 'idle';
  blinkCount     = 0;
  geoActual      = null;
  _estadoHoy     = null;
  _geoRetryTipo  = null;
  setGeoBadge('idle', 'GPS inactivo');
  eyeWasClosed   = false;
  pendingEmpId   = null;
  pendingScore   = null;
  ocultarLiveness();
  document.getElementById('actionPanel').classList.remove('show');
  document.getElementById('hoyPanel').style.display = 'none';
  document.getElementById('overlay').getContext('2d')
    .clearRect(0,0,document.getElementById('overlay').width,document.getElementById('overlay').height);
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

init();
</script>
</body>
</html>