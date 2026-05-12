/**
 * checador_widget.js
 * Módulo de checador de asistencia que se inyecta en el panel izquierdo del login.
 * El toggle es el botón morado "Ir al Checador / Volver al Login".
 * La animación es la misma slide que ya tiene el container.active del login.
 *
 * Dependencias: face-api.js (ya cargado), face_login.js (para modelos ya cargados)
 */

(function () {
  'use strict';

  const MODEL_PATH    = '/src/face-models';
  const THRESHOLD     = 0.48;
  const SCAN_INTERVAL = 1600;
  const STABLE_HITS   = 2;

  // ── ESTADO ────────────────────────────────────
  let isChecadorMode  = false;
  let knownUsers      = [];
  let scanInterval    = null;
  let hitCount        = 0;
  let lastMatchId     = null;
  let isProcessing    = false;
  let cameraStream    = null;
  let currentActionType = null;  // 'ENTRADA' | 'SALIDA'
  let facialMode      = true;    // true = facial, false = manual
  let modelsLoaded    = false;
  let currentTicketData = null;

  // ── REFERENCIAS DOM ───────────────────────────
  const container     = document.getElementById('container');
  const leftPanel     = document.getElementById('leftPanel');
  const loginSection  = document.getElementById('loginSection');
  const checadorSection = document.getElementById('checadorSection');
  const toggleButtons = document.querySelectorAll('.btn-toggle-checador');

  // ── HTML DEL CHECADOR ─────────────────────────
  const checadorHTML = `
    <div class="checador-wrapper" id="checadorWrapper">
      <div class="checador-card">
        <div class="checador-header">
          <div class="checador-title"><i class="fas fa-clock"></i>Checador de Asistencia</div>
          <div class="checador-subtitle">Registra entrada o salida con reconocimiento facial o manual.</div>
        </div>

        <div class="checador-body">
          <!-- MODO FACIAL -->
          <div id="checadorFacialArea">
            <div class="checador-video-wrapper" id="checadorVideoWrapper">
              <video id="checadorVideo" autoplay muted playsinline></video>
              <canvas id="checadorCanvas"></canvas>
              <div id="checadorStatus" class="face-status">
                <i class="fas fa-camera"></i><span>Iniciando cámara...</span>
              </div>
            </div>
            <p class="face-hint">Mira la cámara y presiona ENTRADA o SALIDA</p>
          </div>

          <!-- MODO MANUAL -->
          <div id="checadorManualArea" class="checador-manual" style="display:none;">
            <input type="text" id="chkUser" placeholder="Nombre de usuario" autocomplete="off">
            <input type="password" id="chkPass" placeholder="Contraseña" autocomplete="off">
          </div>

          <!-- Botones ENTRADA / SALIDA -->
          <div class="checador-actions" id="chkBtns">
            <button class="btn-entrada" id="btnEntrada"><i class="fas fa-sign-in-alt"></i> ENTRADA</button>
            <button class="btn-salida"  id="btnSalida"><i class="fas fa-sign-out-alt"></i> SALIDA</button>
          </div>

          <!-- Comentario -->
          <div class="checador-comment-area">
            <label class="checador-comment-label">Comentario opcional (ej: olvidé checar salida):</label>
            <textarea id="chkComment" placeholder="Escribe aquí si necesitas aclarar algo..."></textarea>
          </div>

          <!-- Resultado -->
          <div class="checador-result" id="chkResult"></div>
          <button class="checador-ticket-btn" id="chkTicketBtn" style="display:none;">
            <i class="fas fa-print"></i> Ver/Imprimir Ticket
          </button>

          <!-- Switch facial/manual -->
          <button class="checador-mode-switch" id="chkModeSwitch">
            <i class="fas fa-keyboard"></i> Cambiar a usuario/contraseña
          </button>

          <!-- Mi Historial -->
          <div class="checador-history">
            <div class="checador-history-header">
              <span>Mi historial reciente:</span>
              <div class="checador-history-actions">
                <input type="date" id="histDateFrom">
                <input type="date" id="histDateTo">
                <button id="btnHistorial">Ver</button>
              </div>
            </div>
            <div class="checador-historial" id="chkHistorial">
              <p>Marca ENTRADA o SALIDA para ver tu historial.</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="attendance-ticket-modal" id="attendanceTicketModal" aria-hidden="true">
      <div class="attendance-ticket-shell" role="dialog" aria-modal="true" aria-label="Comprobante de asistencia">
        <div class="attendance-ticket-header">
          <h3>Comprobante de Asistencia</h3>
          <button type="button" id="closeAttendanceTicket" aria-label="Cerrar ticket"><i class="fas fa-times"></i></button>
        </div>
        <div class="attendance-ticket-body">
          <div class="attendance-ticket-card">
            <div class="attendance-ticket-brand">
              <h4>KitchenLink</h4>
              <span>ASISTENCIA</span>
            </div>
            <div class="attendance-ticket-row"><strong>Empleado</strong><span id="ticketUserName">-</span></div>
            <div class="attendance-ticket-row"><strong>ID</strong><span id="ticketUserId">-</span></div>
            <div class="attendance-ticket-row"><strong>Tipo</strong><span id="ticketType">-</span></div>
            <div class="attendance-ticket-row"><strong>Fecha</strong><span id="ticketDate">-</span></div>
            <div class="attendance-ticket-row"><strong>Hora entrada</strong><span id="ticketEntryTime">-</span></div>
            <div class="attendance-ticket-row"><strong>Hora salida</strong><span id="ticketExitTime">-</span></div>
            <div class="attendance-ticket-row"><strong>Método</strong><span id="ticketMethod">-</span></div>
            <div class="attendance-ticket-comment" id="ticketCommentBox" style="display:none;"></div>
          </div>
        </div>
        <div class="attendance-ticket-actions">
          <button type="button" id="viewTicketCommentBtn" class="close-btn" style="display:none;">Ver comentario</button>
          <button type="button" id="printAttendanceTicket" class="print-btn">Imprimir</button>
          <button type="button" id="openAttendanceTicketWindow" class="close-btn">Abrir panel</button>
        </div>
      </div>
    </div>

  `;

  // ── UTILIDADES ────────────────────────────────
  function setStatus(el, icon, text, cls = '') {
    if (!el) return;
    el.innerHTML = `<i class="fas fa-${icon}"></i><span>${text}</span>`;
    el.className = 'face-status ' + cls;
  }

  function showResult(msg, isOk) {
    const el = document.getElementById('chkResult');
    if (!el) return;
    el.textContent = msg;
    el.className   = 'checador-result ' + (isOk ? 'success' : 'error');
    setTimeout(() => { el.className = 'checador-result'; }, 5000);
  }

  function openAttendanceTicket() {
    const modal = document.getElementById('attendanceTicketModal');
    const data = currentTicketData || JSON.parse(localStorage.getItem('currentAttendanceTicketData') || 'null');
    if (!modal || !data) return;

    renderAttendanceTicket(data);
    modal.classList.add('active');
    modal.setAttribute('aria-hidden', 'false');
  }

  function closeAttendanceTicket() {
    const modal = document.getElementById('attendanceTicketModal');
    if (!modal) return;
    modal.classList.remove('active');
    modal.setAttribute('aria-hidden', 'true');
  }

  function renderAttendanceTicket(data) {
    const userNameEl = document.getElementById('ticketUserName');
    const userIdEl = document.getElementById('ticketUserId');
    const typeEl = document.getElementById('ticketType');
    const dateEl = document.getElementById('ticketDate');
    const entryEl = document.getElementById('ticketEntryTime');
    const exitEl = document.getElementById('ticketExitTime');
    const methodEl = document.getElementById('ticketMethod');
    const commentBox = document.getElementById('ticketCommentBox');
    const commentBtn = document.getElementById('viewTicketCommentBtn');

    if (userNameEl) userNameEl.textContent = data.user_name || '-';
    if (userIdEl) userIdEl.textContent = data.user_id || '-';
    if (typeEl) typeEl.textContent = data.type || '-';
    if (dateEl) dateEl.textContent = data.date || '-';
    if (entryEl) entryEl.textContent = data.entry_time || '-';
    if (exitEl) exitEl.textContent = data.exit_time || '-';
    if (methodEl) methodEl.textContent = data.method || '-';

    if (commentBox && commentBtn) {
      if (data.comment) {
        commentBtn.style.display = 'inline-flex';
        commentBtn.onclick = () => {
          const showing = commentBox.style.display === 'block';
          commentBox.style.display = showing ? 'none' : 'block';
          if (!showing) commentBox.textContent = `Comentario: ${data.comment}`;
        };
      } else {
        commentBtn.style.display = 'none';
        commentBox.style.display = 'none';
        commentBox.textContent = '';
      }
    }
  }

  function escapeHtml(value) {
    return String(value ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function formatDateTime(dt) {
    const d = new Date(dt);
    return {
      fecha: d.toLocaleDateString('es-MX', { day:'2-digit', month:'2-digit', year:'numeric' }),
      hora:  d.toLocaleTimeString('es-MX', { hour:'2-digit', minute:'2-digit', second:'2-digit' })
    };
  }

  // ── CÁMARA ────────────────────────────────────
  async function startChecadorCamera() {
    const video = document.getElementById('checadorVideo');
    if (!video) return false;
    try {
      cameraStream = await navigator.mediaDevices.getUserMedia({
        video: { facingMode: 'user', width: 320, height: 240 }
      });
      video.srcObject = cameraStream;
      await video.play();
      return true;
    } catch (e) {
      return false;
    }
  }

  function stopChecadorCamera() {
    if (scanInterval) clearInterval(scanInterval);
    scanInterval = null;
    if (cameraStream) {
      cameraStream.getTracks().forEach(t => t.stop());
      cameraStream = null;
    }
  }

  // ── DESCRIPTORES ──────────────────────────────
  async function loadDescriptors() {
    try {
      const res  = await fetch('/src/api/face/get_descriptors.php');
      const data = await res.json();
      if (!data.success) return;
      knownUsers = data.users.map(u => ({
        id:         u.id,
        name:       u.name,
        descriptor: new Float32Array(u.descriptor)
      }));
    } catch (e) { knownUsers = []; }
  }

  function findBestMatch(descriptor) {
    if (knownUsers.length === 0) return null;
    let best = null, bestDist = Infinity;
    for (const u of knownUsers) {
      const dist = faceapi.euclideanDistance(descriptor, u.descriptor);
      if (dist < bestDist) { bestDist = dist; best = u; }
    }
    return bestDist < THRESHOLD ? { user: best, distance: bestDist } : null;
  }

  // ── SCAN LOOP ─────────────────────────────────
  function startScan() {
    const video    = document.getElementById('checadorVideo');
    const canvas   = document.getElementById('checadorCanvas');
    const statusEl = document.getElementById('checadorStatus');
    const wrapper  = document.getElementById('checadorVideoWrapper');

    if (!video || !canvas) return;
    const opts = new faceapi.TinyFaceDetectorOptions({ inputSize: 224, scoreThreshold: 0.5 });

    scanInterval = setInterval(async () => {
      if (isProcessing || !cameraStream) return;

      const detection = await faceapi
        .detectSingleFace(video, opts)
        .withFaceLandmarks(true)
        .withFaceDescriptor();

      if (!detection) {
        hitCount = 0; lastMatchId = null;
        setStatus(statusEl, 'camera', 'Buscando rostro...', '');
        wrapper?.classList.remove('scanning', 'success');
        return;
      }

      // Dibujar landmarks
      canvas.width  = video.videoWidth;
      canvas.height = video.videoHeight;
      const ds = { width: video.videoWidth, height: video.videoHeight };
      faceapi.matchDimensions(canvas, ds);
      const resized = faceapi.resizeResults(detection, ds);
      canvas.getContext('2d').clearRect(0, 0, canvas.width, canvas.height);
      faceapi.draw.drawFaceLandmarks(canvas, resized);

      const match = findBestMatch(detection.descriptor);
      if (!match) {
        hitCount = 0; lastMatchId = null;
        setStatus(statusEl, 'user-slash', 'Rostro no reconocido', '');
        wrapper?.classList.add('scanning');
        return;
      }

      setStatus(statusEl, 'spinner', `Detectado: ${match.user.name}`, 'scanning');

      if (lastMatchId !== match.user.id) { lastMatchId = match.user.id; hitCount = 1; }
      else hitCount++;

      if (hitCount >= STABLE_HITS && currentActionType) {
        clearInterval(scanInterval);
        scanInterval = null;
        isProcessing = true;
        wrapper?.classList.add('success');
        setStatus(statusEl, 'check-circle', `¡Identificado: ${match.user.name}!`, 'matched');
        await sendAttendance({ user_id: match.user.id, method: 'FACIAL', name: match.user.name });
      }
    }, SCAN_INTERVAL);
  }

  // ── ENVIAR ASISTENCIA ─────────────────────────
  async function sendAttendance({ user_id, method, name, username, password }) {
    const comment = document.getElementById('chkComment')?.value?.trim() || '';
    const type    = currentActionType;

    const body = { type, method, comment };
    if (method === 'FACIAL') {
      body.user_id = user_id;
    } else {
      body.username = username;
      body.password = password;
    }

    try {
      const res  = await fetch('/src/api/attendance/record_attendance.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body)
      });
      const data = await res.json();

      if (data.success) {
        showResult(`✓ ${data.type} registrada para ${data.user_name}`, true);
        fillAndPrintTicket(data);
        currentTicketData = JSON.parse(localStorage.getItem('currentAttendanceTicketData') || 'null');
        loadUserHistorial(data.user_id);
        const ticketBtn = document.getElementById('chkTicketBtn');
        if (ticketBtn) {
          ticketBtn.style.display = 'inline-flex';
          ticketBtn.onclick = openAttendanceTicket;
        }
        // Limpiar comentario
        const cmt = document.getElementById('chkComment');
        if (cmt) cmt.value = '';
      } else {
        showResult(`✗ ${data.message}`, false);
      }
    } catch (e) {
      showResult('Error de conexión. Intenta de nuevo.', false);
    } finally {
      isProcessing = false;
      hitCount = 0; lastMatchId = null; currentActionType = null;
      if (facialMode && cameraStream) startScan();
    }
  }

  // ── TICKET ────────────────────────────────────
  function fillAndPrintTicket(data) {
    const dt = formatDateTime(data.timestamp);
    const entryDt = data.entry_timestamp ? formatDateTime(data.entry_timestamp) : null;
    const exitDt = data.exit_timestamp ? formatDateTime(data.exit_timestamp) : null;

    const payload = {
      user_name: data.user_name || '-',
      user_id: data.user_id || '-',
      type: data.type || '-',
      date: dt.fecha,
      entry_time: entryDt ? entryDt.hora : dt.hora,
      exit_time: exitDt ? exitDt.hora : '-',
      method: data.method || '-',
      comment: data.comment || ''
    };

    currentTicketData = payload;
    localStorage.setItem('currentAttendanceTicketData', JSON.stringify(payload));
  }

  // ── HISTORIAL ─────────────────────────────────
  async function loadUserHistorial(userId) {
    const el       = document.getElementById('chkHistorial');
    const dateFrom = document.getElementById('histDateFrom')?.value || '';
    const dateTo   = document.getElementById('histDateTo')?.value   || '';

    if (!el || !userId) return;
    el.innerHTML = '<p style="font-size:11px;color:#aaa;text-align:center;">Cargando...</p>';

    let url = `/src/api/attendance/get_attendance.php?user_id=${userId}`;
    if (dateFrom) url += `&date_from=${dateFrom}`;
    if (dateTo)   url += `&date_to=${dateTo}`;

    try {
      const res  = await fetch(url);
      const data = await res.json();

      if (!data.success || data.records.length === 0) {
        el.innerHTML = '<p style="font-size:11px;color:#aaa;text-align:center;">Sin registros en este período.</p>';
        return;
      }

      const latest = data.records[0];
      if (latest && latest.type) {
        lastMatchId = null;
      }

      el.innerHTML = data.records.slice(0, 15).map(r => {
        const dt  = formatDateTime(r.timestamp);
        const cls = r.type === 'ENTRADA' ? 'badge-entrada' : 'badge-salida';
        const com = r.comment ? ` <button type="button" class="comentary-btn" data-comment="${encodeURIComponent(r.comment)}">Ver comentario</button>` : '';
        return `
          <div class="historial-row">
            <span class="${cls}">${r.type}</span>
            <span>${dt.fecha} ${dt.hora}</span>
            <span class="badge-method">${r.method}${com}</span>
          </div>`;
      }).join('');

      el.querySelectorAll('.comentary-btn').forEach(btn => {
        btn.addEventListener('click', () => {
          const comment = decodeURIComponent(btn.dataset.comment || '');
          if (!comment) return;
          if (window.appAlert) window.appAlert(comment);
          else window.alert(comment);
        });
      });
    } catch (e) {
      el.innerHTML = '<p style="font-size:11px;color:#e74c3c;">Error al cargar historial.</p>';
    }
  }

  // ── TOGGLE LOGIN ↔ CHECADOR ───────────────────
  function enterChecadorMode() {
    isChecadorMode = true;

    // 1. Detener face login
    if (window.FaceLogin) window.FaceLogin.stopCamera();

    // 2. Animar: el panel morado se va a la izquierda (reutiliza .active de style.css)
    container.classList.add('active');
    leftPanel?.classList.add('checador-open');

    // 3. Ocultar login, mostrar checador
    loginSection.style.display    = 'none';
    checadorSection.style.display = 'flex';
    checadorSection.innerHTML     = checadorHTML;

    // 5. Inicializar checador
    initChecador();
  }

  function exitChecadorMode() {
    isChecadorMode = false;
    stopChecadorCamera();

    container.classList.remove('active');
    leftPanel?.classList.remove('checador-open');

    checadorSection.style.display = 'none';
    loginSection.style.display    = 'block';

    // Re-arrancar face login
    if (window.FaceLogin) {
      window.FaceLogin.startCamera().then(() => {
        window.FaceLogin.loadKnownDescriptors().then(() => window.FaceLogin.startScan());
      });
    }
  }

  if (toggleButtons.length) {
    toggleButtons.forEach(btn => {
      btn.addEventListener('click', () => {
        if (isChecadorMode) exitChecadorMode();
        else enterChecadorMode();
      });
    });
  }

  // ── INICIALIZAR CHECADOR ──────────────────────
  async function initChecador() {
    // Cargar modelos si aún no están
    if (!modelsLoaded) {
      try {
        await Promise.all([
          faceapi.nets.tinyFaceDetector.loadFromUri(MODEL_PATH),
          faceapi.nets.faceLandmark68TinyNet.loadFromUri(MODEL_PATH),
          faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_PATH),
        ]);
        modelsLoaded = true;
      } catch (e) { console.warn('[Checador] Modelos no disponibles'); }
    }

    await loadDescriptors();

    // Iniciar cámara
    const cameraOk = await startChecadorCamera();
    const statusEl = document.getElementById('checadorStatus');

    if (!cameraOk) {
      facialMode = false;
      switchToManual();
    } else {
      setStatus(statusEl, 'camera', 'Listo. Presiona ENTRADA o SALIDA', '');
    }

    // ── EVENTOS ────────────────────────────────
    document.getElementById('btnEntrada')?.addEventListener('click', () => handleAction('ENTRADA'));
    document.getElementById('btnSalida')?.addEventListener('click',  () => handleAction('SALIDA'));

    document.getElementById('chkModeSwitch')?.addEventListener('click', toggleFacialManual);

    document.getElementById('btnHistorial')?.addEventListener('click', () => {
      // Historial sin user_id: necesitamos uno; si está en manual, tomamos del campo
      const userEl = document.getElementById('chkUser');
      if (!facialMode && userEl?.value) {
        // Necesitamos resolver el user_id por username → hacemos login ligero
        showResult('Escribe usuario y contraseña, luego marca ENTRADA o SALIDA para ver historial.', false);
      }
    });

    document.getElementById('chkTicketBtn')?.addEventListener('click', openAttendanceTicket);
    document.getElementById('closeAttendanceTicket')?.addEventListener('click', closeAttendanceTicket);
    document.getElementById('printAttendanceTicket')?.addEventListener('click', () => window.print());
    document.getElementById('openAttendanceTicketWindow')?.addEventListener('click', openAttendanceTicket);
    document.getElementById('attendanceTicketModal')?.addEventListener('click', (e) => {
      if (e.target?.id === 'attendanceTicketModal') closeAttendanceTicket();
    });

    // Fechas por defecto (mes actual)
    const today    = new Date().toISOString().split('T')[0];
    const firstDay = today.slice(0, 7) + '-01';
    const df = document.getElementById('histDateFrom');
    const dt = document.getElementById('histDateTo');
    if (df) df.value = firstDay;
    if (dt) dt.value = today;
  }

  // ── LÓGICA ACCIÓN ENTRADA/SALIDA ─────────────
  function handleAction(type) {
    if (isProcessing) return;
    if (currentTicketData && currentTicketData.type === type) {
      const msg = type === 'ENTRADA'
        ? 'Ya existe una ENTRADA activa. Debes registrar SALIDA antes de volver a checar entrada.'
        : 'Ya existe una SALIDA activa. Debes registrar ENTRADA antes de volver a checar salida.';
      showResult(msg, false);
      return;
    }
    currentActionType = type;

    if (facialMode) {
      if (!cameraStream) { showResult('Cámara no disponible. Usa modo manual.', false); return; }
      if (knownUsers.length === 0) { showResult('Sin rostros registrados. Usa modo manual.', false); return; }
      hitCount = 0; lastMatchId = null;
      startScan();
    } else {
      // Manual
      const username = document.getElementById('chkUser')?.value?.trim();
      const password = document.getElementById('chkPass')?.value?.trim();
      if (!username || !password) { showResult('Ingresa usuario y contraseña.', false); return; }
      isProcessing = true;
      sendAttendance({ method: 'MANUAL', username, password });
    }
  }

  // ── SWITCH FACIAL / MANUAL EN CHECADOR ────────
  function toggleFacialManual() {
    facialMode = !facialMode;
    if (facialMode) {
      switchToFacial();
    } else {
      switchToManual();
    }
  }

  function switchToManual() {
    facialMode = false;
    stopChecadorCamera();
    document.getElementById('checadorFacialArea').style.display = 'none';
    document.getElementById('checadorManualArea').style.display = 'flex';
    const sw = document.getElementById('chkModeSwitch');
    if (sw) sw.innerHTML = '<i class="fas fa-face-smile"></i> Cambiar a reconocimiento facial';
  }

  async function switchToFacial() {
    facialMode = true;
    document.getElementById('checadorManualArea').style.display = 'none';
    document.getElementById('checadorFacialArea').style.display = 'block';
    const sw = document.getElementById('chkModeSwitch');
    if (sw) sw.innerHTML = '<i class="fas fa-keyboard"></i> Cambiar a usuario/contraseña';
    const ok = await startChecadorCamera();
    if (!ok) switchToManual();
  }

})();
