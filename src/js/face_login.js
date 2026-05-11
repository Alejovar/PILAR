/**
 * face_login.js
 * Maneja el reconocimiento facial en la pantalla de login.
 * Usa face-api.js (cargado desde CDN en login.php).
 *
 * Flujo:
 *  1. Carga los modelos de face-api desde /src/face-models/
 *  2. Inicia la cámara
 *  3. Carga descriptores desde /src/api/face/get_descriptors.php
 *  4. Cada ~1.5 s detecta rostro y compara con descriptores conocidos
 *  5. Si hay match (distancia < THRESHOLD), llama /src/api/face/facial_login.php
 *  6. Si la cámara falla, muestra automáticamente el formulario manual
 */

(function () {
  'use strict';

  // ──────────────────────────────────────────────
  // CONFIGURACIÓN
  // ──────────────────────────────────────────────
  const MODEL_PATH   = '/src/face-models';
  const THRESHOLD    = 0.48;   // Distancia euclidiana máxima para considerar match
  const SCAN_INTERVAL= 1500;   // ms entre detecciones
  const STABLE_HITS  = 2;      // Detecciones consecutivas requeridas antes de autenticar

  // Elementos del DOM
  const faceArea     = document.getElementById('faceLoginArea');
  const manualArea   = document.getElementById('manualLoginArea');
  const video        = document.getElementById('faceLoginVideo');
  const canvas       = document.getElementById('faceLoginCanvas');
  const statusEl     = document.getElementById('faceLoginStatus');
  const faceError    = document.getElementById('faceLoginError');
  const btnToPass    = document.getElementById('btnSwitchToPassword');
  const btnToFace    = document.getElementById('btnSwitchToFace');

  // Estado
  let knownUsers    = [];   // [{id, name, descriptor (Float32Array)}]
  let scanInterval  = null;
  let hitCount      = 0;    // Hits consecutivos del mismo usuario
  let lastMatchId   = null;
  let isAuthenticating = false;

  // ──────────────────────────────────────────────
  // SWITCH MODO
  // ──────────────────────────────────────────────
  function showManual() {
    faceArea.style.display  = 'none';
    manualArea.style.display = 'flex';
    manualArea.style.flexDirection = 'column';
    stopCamera();
  }

  function showFacial() {
    manualArea.style.display = 'none';
    faceArea.style.display   = 'flex';
    startCamera();
  }

  if (btnToPass) btnToPass.addEventListener('click', showManual);
  if (btnToFace) btnToFace.addEventListener('click', showFacial);

  // ──────────────────────────────────────────────
  // HELPERS DE STATUS
  // ──────────────────────────────────────────────
  function setStatus(icon, text, cls = '') {
    statusEl.innerHTML = `<i class="fas fa-${icon}"></i><span>${text}</span>`;
    statusEl.className = 'face-status ' + cls;
  }

  // ──────────────────────────────────────────────
  // CARGA DE MODELOS
  // ──────────────────────────────────────────────
  async function loadModels() {
    setStatus('spinner', 'Cargando modelos...', 'scanning');
    await Promise.all([
      faceapi.nets.tinyFaceDetector.loadFromUri(MODEL_PATH),
      faceapi.nets.faceLandmark68TinyNet.loadFromUri(MODEL_PATH),
      faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_PATH),
    ]);
  }

  // ──────────────────────────────────────────────
  // CARGA DE DESCRIPTORES CONOCIDOS
  // ──────────────────────────────────────────────
  async function loadKnownDescriptors() {
    try {
      const res  = await fetch('/src/api/face/get_descriptors.php');
      const data = await res.json();
      if (!data.success) throw new Error(data.message);

      knownUsers = data.users.map(u => ({
        id:         u.id,
        name:       u.name,
        descriptor: new Float32Array(u.descriptor)
      }));

      console.log(`[FaceLogin] ${knownUsers.length} usuario(s) con rostro registrado.`);
    } catch (e) {
      console.warn('[FaceLogin] No se pudieron cargar descriptores:', e.message);
      knownUsers = [];
    }
  }

  // ──────────────────────────────────────────────
  // CÁMARA
  // ──────────────────────────────────────────────
  async function startCamera() {
    try {
      const stream = await navigator.mediaDevices.getUserMedia({
        video: { facingMode: 'user', width: 320, height: 240 }
      });
      video.srcObject = stream;
      await video.play();
      return true;
    } catch (e) {
      console.warn('[FaceLogin] Cámara no disponible:', e.message);
      return false;
    }
  }

  function stopCamera() {
    if (scanInterval) clearInterval(scanInterval);
    if (video.srcObject) {
      video.srcObject.getTracks().forEach(t => t.stop());
      video.srcObject = null;
    }
  }

  // ──────────────────────────────────────────────
  // MATCHING LOCAL (más rápido que FaceMatcher)
  // ──────────────────────────────────────────────
  function findBestMatch(descriptor) {
    if (knownUsers.length === 0) return null;
    let best = null, bestDist = Infinity;
    for (const u of knownUsers) {
      const dist = faceapi.euclideanDistance(descriptor, u.descriptor);
      if (dist < bestDist) { bestDist = dist; best = u; }
    }
    return bestDist < THRESHOLD ? { user: best, distance: bestDist } : null;
  }

  // ──────────────────────────────────────────────
  // LOOP DE DETECCIÓN
  // ──────────────────────────────────────────────
  function startScan() {
    const wrapper = document.querySelector('.face-video-wrapper');
    const opts = new faceapi.TinyFaceDetectorOptions({ inputSize: 224, scoreThreshold: 0.5 });

    scanInterval = setInterval(async () => {
      if (isAuthenticating || !video.srcObject) return;

      const detection = await faceapi
        .detectSingleFace(video, opts)
        .withFaceLandmarks(true)
        .withFaceDescriptor();

      if (!detection) {
        hitCount = 0;
        lastMatchId = null;
        setStatus('camera', 'Buscando rostro...', '');
        wrapper?.classList.remove('scanning', 'success');
        return;
      }

      // Dibujar landmarks en canvas
      canvas.width  = video.videoWidth;
      canvas.height = video.videoHeight;
      const displaySize = { width: video.videoWidth, height: video.videoHeight };
      faceapi.matchDimensions(canvas, displaySize);
      const resized = faceapi.resizeResults(detection, displaySize);
      const ctx = canvas.getContext('2d');
      ctx.clearRect(0, 0, canvas.width, canvas.height);
      faceapi.draw.drawFaceLandmarks(canvas, resized);

      const match = findBestMatch(detection.descriptor);

      if (!match) {
        hitCount = 0;
        lastMatchId = null;
        setStatus('user-slash', 'Rostro no reconocido', '');
        wrapper?.classList.add('scanning');
        wrapper?.classList.remove('success');
        return;
      }

      wrapper?.classList.add('scanning');
      setStatus('spinner', `Verificando... ${match.user.name}`, 'scanning');

      if (lastMatchId !== match.user.id) {
        lastMatchId = match.user.id;
        hitCount = 1;
      } else {
        hitCount++;
      }

      if (hitCount >= STABLE_HITS) {
        // ¡Match estable! Autenticar
        clearInterval(scanInterval);
        isAuthenticating = true;
        wrapper?.classList.remove('scanning');
        wrapper?.classList.add('success');
        setStatus('check-circle', `¡Hola, ${match.user.name}!`, 'matched');
        authenticateByFace(match.user.id, match.user.name);
      }
    }, SCAN_INTERVAL);
  }

  // ──────────────────────────────────────────────
  // AUTENTICACIÓN POR FACE → servidor
  // ──────────────────────────────────────────────
  async function authenticateByFace(userId, userName) {
    faceError.textContent = '';
    try {
      const res  = await fetch('/src/api/face/facial_login.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ user_id: userId })
      });
      const data = await res.json();
      if (data.success) {
        setStatus('check-circle', `¡Bienvenido, ${userName}!`, 'matched');
        setTimeout(() => { window.location.href = data.redirect; }, 800);
      } else {
        faceError.textContent = data.message;
        isAuthenticating = false;
        hitCount = 0;
        lastMatchId = null;
        document.querySelector('.face-video-wrapper')?.classList.remove('success');
        startScan();
      }
    } catch (e) {
      faceError.textContent = 'Error de conexión. Usa usuario y contraseña.';
      isAuthenticating = false;
      startScan();
    }
  }

  // ──────────────────────────────────────────────
  // INICIALIZACIÓN
  // ──────────────────────────────────────────────
  async function init() {
    // Solo iniciar si estamos en loginSection visible
    const loginSection = document.getElementById('loginSection');
    if (!loginSection) return;

    try {
      await loadModels();
      const cameraOk = await startCamera();

      if (!cameraOk) {
        // Sin cámara → mostrar manual directamente
        showManual();
        return;
      }

      await loadKnownDescriptors();

      if (knownUsers.length === 0) {
        // Sin usuarios registrados con cara → ir a manual pero mantener cámara para registro futuro
        setStatus('info-circle', 'Sin rostros registrados', '');
        showManual();
        return;
      }

      setStatus('camera', 'Buscando rostro...', '');
      startScan();

    } catch (e) {
      console.error('[FaceLogin] Error de inicialización:', e);
      showManual();
    }
  }

  // Arrancar solo si la pantalla es de login (no el checador)
  document.addEventListener('DOMContentLoaded', () => {
    const loginSection = document.getElementById('loginSection');
    if (loginSection) init();
  });

  // Exponer para que checador_widget pueda parar el scan
  window.FaceLogin = { stopCamera, startCamera, startScan, loadKnownDescriptors };

})();
