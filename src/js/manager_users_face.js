/**
 * manager_users_face.js
 * Maneja el modal de registro facial en manager_users.php.
 * Añade: columna de rostro en tabla, botón de registrar/actualizar rostro,
 * captura de descriptor y guardado via /src/api/manager/users/save_face.php
 *
 * Depende de: manager_users.js (para la tabla) y face-api.js
 */

(function () {
  'use strict';

  const MODEL_PATH = '/src/face-models';
  let modelsLoaded = false;
  let cameraStream = null;
  let faceRegInterval = null;
  let targetUserId = null;
  let lastDescriptor = null;

  // ── REFERENCIAS DOM ───────────────────────────
  const faceModal       = document.getElementById('faceModal');
  const faceModalTitle  = document.getElementById('faceModalTitle');
  const faceModalSub    = document.getElementById('faceModalSubtitle');
  const faceRegStatus   = document.getElementById('faceRegStatus');
  const faceRegVideo    = document.getElementById('faceRegVideo');
  const faceRegCanvas   = document.getElementById('faceRegCanvas');
  const btnCapture      = document.getElementById('btnCaptureFace');
  const btnDelete       = document.getElementById('btnDeleteFace');
  const btnCancel       = document.getElementById('cancelFaceModal');

  // ── UTILIDADES ────────────────────────────────
  function setStatus(text, color = '#7f00ff') {
    if (faceRegStatus) { faceRegStatus.textContent = text; faceRegStatus.style.color = color; }
  }

  // ── MODELOS ───────────────────────────────────
  async function ensureModels() {
    if (modelsLoaded) return;
    setStatus('Cargando modelos de reconocimiento...', '#888');
    await Promise.all([
      faceapi.nets.tinyFaceDetector.loadFromUri(MODEL_PATH),
      faceapi.nets.faceLandmark68TinyNet.loadFromUri(MODEL_PATH),
      faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_PATH),
    ]);
    modelsLoaded = true;
  }

  // ── CÁMARA ────────────────────────────────────
  async function startRegCamera() {
    try {
      cameraStream = await navigator.mediaDevices.getUserMedia({
        video: { facingMode: 'user', width: 480, height: 360 }
      });
      faceRegVideo.srcObject = cameraStream;
      await faceRegVideo.play();
      return true;
    } catch (e) {
      setStatus('⚠ Cámara no disponible: ' + e.message, '#e74c3c');
      return false;
    }
  }

  function stopRegCamera() {
    if (faceRegInterval) { clearInterval(faceRegInterval); faceRegInterval = null; }
    if (cameraStream) { cameraStream.getTracks().forEach(t => t.stop()); cameraStream = null; }
    if (faceRegVideo) faceRegVideo.srcObject = null;
  }

  // ── LOOP DE VISTA PREVIA ──────────────────────
  function startPreviewLoop() {
    const opts = new faceapi.TinyFaceDetectorOptions({ inputSize: 224, scoreThreshold: 0.5 });

    faceRegInterval = setInterval(async () => {
      if (!cameraStream) return;

      const detection = await faceapi
        .detectSingleFace(faceRegVideo, opts)
        .withFaceLandmarks(true)
        .withFaceDescriptor();

      faceRegCanvas.width  = faceRegVideo.videoWidth;
      faceRegCanvas.height = faceRegVideo.videoHeight;
      const ctx = faceRegCanvas.getContext('2d');
      ctx.clearRect(0, 0, faceRegCanvas.width, faceRegCanvas.height);

      if (!detection) {
        lastDescriptor = null;
        setStatus('Coloca tu rostro frente a la cámara...', '#888');
        return;
      }

      const ds = { width: faceRegVideo.videoWidth, height: faceRegVideo.videoHeight };
      faceapi.matchDimensions(faceRegCanvas, ds);
      const resized = faceapi.resizeResults(detection, ds);
      faceapi.draw.drawFaceLandmarks(faceRegCanvas, resized);

      lastDescriptor = detection.descriptor;
      setStatus('✓ Rostro detectado. Pulsa "Capturar Rostro".', '#27ae60');
    }, 800);
  }

  // ── ABRIR MODAL FACIAL ────────────────────────
  async function openFaceModal(userId, userName, hasFace) {
    targetUserId = userId;
    if (faceModalTitle) faceModalTitle.textContent = hasFace ? 'Actualizar Rostro' : 'Registrar Rostro';
    if (faceModalSub)   faceModalSub.textContent   = `Empleado: ${userName}`;
    if (btnDelete)      btnDelete.style.display = hasFace ? 'inline-flex' : 'none';

    lastDescriptor = null;
    faceModal.classList.add('active');

    try {
      await ensureModels();
      const ok = await startRegCamera();
      if (ok) startPreviewLoop();
    } catch (e) {
      setStatus('Error: ' + e.message, '#e74c3c');
    }
  }

  function closeFaceModal() {
    stopRegCamera();
    faceModal.classList.remove('active');
    targetUserId = null;
    lastDescriptor = null;
    setStatus('Iniciando cámara...');
  }

  // ── CAPTURAR Y GUARDAR ────────────────────────
  async function captureAndSave() {
    if (!lastDescriptor) {
      setStatus('⚠ No se detectó ningún rostro. Inténtalo de nuevo.', '#e74c3c');
      return;
    }
    if (!targetUserId) return;

    setStatus('Guardando...', '#7f00ff');
    btnCapture.disabled = true;

    try {
      const res  = await fetch('/src/api/manager/users/save_face.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          user_id:    targetUserId,
          descriptor: Array.from(lastDescriptor)  // Float32Array → array normal
        })
      });
      const data = await res.json();

      if (data.success) {
        setStatus('✓ Rostro guardado correctamente.', '#27ae60');
        setTimeout(() => {
          closeFaceModal();
          // Refrescar la tabla
          if (typeof loadUsers === 'function') loadUsers();
          else location.reload();
        }, 1200);
      } else {
        setStatus('✗ ' + data.message, '#e74c3c');
      }
    } catch (e) {
      setStatus('Error de conexión.', '#e74c3c');
    } finally {
      btnCapture.disabled = false;
    }
  }

  // ── ELIMINAR ROSTRO ───────────────────────────
  async function deleteFace() {
    if (!targetUserId || !confirm('¿Eliminar el rostro registrado de este empleado?')) return;

    setStatus('Eliminando...', '#e74c3c');
    try {
      const res  = await fetch('/src/api/manager/users/save_face.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ user_id: targetUserId, descriptor: null })
      });
      const data = await res.json();
      if (data.success) {
        setStatus('✓ Rostro eliminado.', '#888');
        setTimeout(() => {
          closeFaceModal();
          if (typeof loadUsers === 'function') loadUsers();
          else location.reload();
        }, 900);
      } else {
        setStatus('✗ ' + data.message, '#e74c3c');
      }
    } catch (e) {
      setStatus('Error de conexión.', '#e74c3c');
    }
  }

  // ── EVENTOS ───────────────────────────────────
  if (btnCapture) btnCapture.addEventListener('click', captureAndSave);
  if (btnDelete)  btnDelete.addEventListener('click',  deleteFace);
  if (btnCancel)  btnCancel.addEventListener('click',  closeFaceModal);

  // Cerrar con click en overlay
  if (faceModal) {
    faceModal.addEventListener('click', (e) => {
      if (e.target === faceModal) closeFaceModal();
    });
  }

  // ── HOOK EN LA TABLA DE USUARIOS ─────────────
  // Escucha el evento personalizado que manager_users.js debería disparar al renderizar la tabla.
  // O usamos MutationObserver para añadir botones de facial a las filas.
  const tbody = document.getElementById('usersTableBody');
  if (tbody) {
    const observer = new MutationObserver(() => {
      document.querySelectorAll('[data-face-btn]').forEach(btn => {
        if (btn._faceListenerAttached) return;
        btn._faceListenerAttached = true;
        btn.addEventListener('click', () => {
          openFaceModal(
            parseInt(btn.dataset.userId),
            btn.dataset.userName,
            btn.dataset.hasFace === '1'
          );
        });
      });
    });
    observer.observe(tbody, { childList: true, subtree: true });
  }

  // Exponer para que manager_users.js pueda llamarlo al renderizar filas
  window.FaceManager = { openFaceModal };

})();
