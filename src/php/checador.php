<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
  <title>Checador | ROCEEL</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="/src/css/checador.css">
</head>
<body>

<!-- Header -->
<header class="checker-header">
  <div class="checker-logo">R</div>
  <div>
    <div class="checker-brand">ROCEEL</div>
    <div class="checker-brand-sub">Sistema de Asistencia</div>
  </div>
  <div class="checker-clock" id="liveClock">--:--:--</div>
</header>

<!-- Card principal -->
<div class="checker-card" id="mainCard">

  <!-- Búsqueda -->
  <div class="panel" id="searchPanel">
    <div class="input-group">
      <label><i class="fas fa-id-card"></i> &nbsp;NSS del empleado</label>
      <input type="text" id="inputNSS" placeholder="Ej. 12345678901"
             inputmode="numeric" maxlength="20">
    </div>
    <div class="input-group">
      <label><i class="fas fa-search"></i> &nbsp;O búsqueda por nombre</label>
      <input type="text" id="inputNombre" placeholder="Ej. García López">
    </div>
    <button class="btn-search" id="btnBuscar">
      <i class="fas fa-search"></i> Buscar empleado
    </button>
    <div class="error-msg" id="errorMsg"></div>
  </div>

  <!-- Datos del empleado (oculto inicialmente) -->
  <div id="employeeSection" style="display:none;">
    <div class="employee-info">
      <div class="emp-avatar"><i class="fas fa-user-hard-hat"></i></div>
      <div>
        <div class="emp-name" id="empName">—</div>
        <div class="emp-meta" id="empMeta">—</div>
        <div class="emp-nss"  id="empNss">NSS: —</div>
      </div>
    </div>

    <!-- Estado de hoy -->
    <div class="status-panel" id="statusPanel">
      <div class="status-item">
        <span class="label"><i class="fas fa-sign-in-alt"></i> Entrada</span>
        <span class="val green" id="stEntrada">—</span>
      </div>
      <div class="status-item">
        <span class="label"><i class="fas fa-utensils"></i> Salida comida</span>
        <span class="val yellow" id="stSalidaComida">—</span>
      </div>
      <div class="status-item">
        <span class="label"><i class="fas fa-undo"></i> Regreso comida</span>
        <span class="val yellow" id="stRegresoComida">—</span>
      </div>
      <div class="status-item">
        <span class="label"><i class="fas fa-sign-out-alt"></i> Salida</span>
        <span class="val red" id="stSalida">—</span>
      </div>
    </div>

    <!-- Botones de check -->
    <div class="check-grid">
      <button class="check-btn entrada" id="btnEntrada" data-tipo="entrada">
        <i class="fas fa-sign-in-alt"></i>
        Entrada
      </button>
      <button class="check-btn salida comida" id="btnSalidaComida" data-tipo="salida_comida">
        <i class="fas fa-utensils"></i>
        Salida<br>comida
      </button>
      <button class="check-btn comida" id="btnRegresoComida" data-tipo="regreso_comida">
        <i class="fas fa-undo-alt"></i>
        Regreso<br>comida
      </button>
      <button class="check-btn salida" id="btnSalida" data-tipo="salida">
        <i class="fas fa-sign-out-alt"></i>
        Salida
      </button>
    </div>

    <!-- Botón nueva búsqueda -->
    <div class="panel" style="padding-top:0;">
      <button class="btn-search" id="btnNueva" style="background:var(--surface-2);color:var(--text);">
        <i class="fas fa-arrow-left"></i> Nueva búsqueda
      </button>
    </div>
  </div>
</div>

<!-- Confirm overlay -->
<div class="confirm-overlay" id="confirmOverlay">
  <div class="confirm-box">
    <div class="confirm-icon" id="confirmIcon">🕐</div>
    <h3 id="confirmTitle">Confirmar checada</h3>
    <p id="confirmMsg">¿Deseas registrar tu entrada ahora?</p>
    <div class="confirm-btns">
      <button class="no" id="confirmNo">Cancelar</button>
      <button class="yes" id="confirmYes">Sí, registrar</button>
    </div>
  </div>
</div>

<!-- Success overlay -->
<div class="success-overlay" id="successOverlay">
  <div class="success-box">
    <div class="success-circle"><i class="fas fa-check"></i></div>
    <h2 id="successTitle">¡Registrado!</h2>
    <p id="successMsg">Tu asistencia ha sido guardada.</p>
  </div>
</div>

<script>
/* ============================================================
   ROCEEL — Checador público
   ============================================================ */
const API = {
  buscar : '/src/php/api/asistencia/buscar_empleado.php',
  estado : '/src/php/api/asistencia/estado_hoy.php',
  check  : '/src/php/api/asistencia/registrar.php',
};

let empleadoActual = null;
let pendingTipo    = null;

// Reloj
(function tick() {
  const n = new Date();
  document.getElementById('liveClock').textContent =
    n.toLocaleTimeString('es-MX', { hour:'2-digit', minute:'2-digit', second:'2-digit' });
  setTimeout(tick, 1000);
})();

// ---- Buscar ----
document.getElementById('btnBuscar').addEventListener('click', buscar);
['inputNSS','inputNombre'].forEach(id =>
  document.getElementById(id).addEventListener('keydown', e => { if(e.key==='Enter') buscar(); })
);

async function buscar() {
  const nss    = document.getElementById('inputNSS').value.trim();
  const nombre = document.getElementById('inputNombre').value.trim();
  const err    = document.getElementById('errorMsg');

  if (!nss && !nombre) {
    showError('Ingresa el NSS o el nombre del empleado.'); return;
  }
  err.classList.remove('show');

  const btn = document.getElementById('btnBuscar');
  btn.disabled = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Buscando...';

  try {
    const params = new URLSearchParams();
    if (nss)    params.set('nss',    nss);
    if (nombre) params.set('nombre', nombre);

    const res  = await fetch(API.buscar + '?' + params);
    const data = await res.json();

    if (!data.ok || !data.empleado) {
      showError(data.msg || 'Empleado no encontrado.'); return;
    }

    empleadoActual = data.empleado;
    mostrarEmpleado();
    await cargarEstado();
  } catch {
    showError('Error de red. Intenta de nuevo.');
  } finally {
    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-search"></i> Buscar empleado';
  }
}

function mostrarEmpleado() {
  const e = empleadoActual;
  document.getElementById('empName').textContent =
    `${e.nombre} ${e.apellido_paterno} ${e.apellido_materno || ''}`.trim();
  document.getElementById('empMeta').textContent =
    `${e.puesto || '—'}  ·  ${e.planta || '—'}`;
  document.getElementById('empNss').textContent = `NSS: ${e.numero_empleado}`;
  document.getElementById('searchPanel').style.display  = 'none';
  document.getElementById('employeeSection').style.display = 'block';
}

async function cargarEstado() {
  try {
    const res  = await fetch(API.estado + '?empleado_id=' + empleadoActual.id);
    const data = await res.json();
    if (!data.ok) return;

    const hoy = data.registros;
    const fmt = ts => ts ? new Date(ts).toLocaleTimeString('es-MX',{hour:'2-digit',minute:'2-digit'}) : '—';

    document.getElementById('stEntrada').textContent       = fmt(hoy.entrada);
    document.getElementById('stSalidaComida').textContent  = fmt(hoy.salida_comida);
    document.getElementById('stRegresoComida').textContent = fmt(hoy.regreso_comida);
    document.getElementById('stSalida').textContent        = fmt(hoy.salida);

    // Habilitar/deshabilitar botones según secuencia
    const b = (id, ok) => {
      const el = document.getElementById(id);
      el.disabled = !ok;
      if (!ok && hoy[id.replace('btn','').toLowerCase().replace('salidacomida','salida_comida').replace('regresocomida','regreso_comida')]) {
        el.classList.add('done');
      }
    };

    const btnE  = document.getElementById('btnEntrada');
    const btnSC = document.getElementById('btnSalidaComida');
    const btnRC = document.getElementById('btnRegresoComida');
    const btnS  = document.getElementById('btnSalida');

    // Secuencia: entrada → salida_comida → regreso_comida → salida
    btnE .disabled = !!hoy.entrada;
    btnSC.disabled = !hoy.entrada || !!hoy.salida_comida;
    btnRC.disabled = !hoy.salida_comida || !!hoy.regreso_comida;
    btnS .disabled = !hoy.regreso_comida || !!hoy.salida;

    if (hoy.entrada)        btnE .classList.add('done');
    if (hoy.salida_comida)  btnSC.classList.add('done');
    if (hoy.regreso_comida) btnRC.classList.add('done');
    if (hoy.salida)         btnS .classList.add('done');

  } catch { /* silencioso */ }
}

// ---- Botones check ----
['btnEntrada','btnSalidaComida','btnRegresoComida','btnSalida'].forEach(id => {
  document.getElementById(id).addEventListener('click', function() {
    pendingTipo = this.dataset.tipo;
    abrirConfirm(pendingTipo);
  });
});

const LABELS = {
  entrada:       { icon:'🟢', title:'Registrar entrada',       msg:'¿Confirmas tu entrada ahora?' },
  salida_comida: { icon:'🍽️', title:'Salida a comida',         msg:'¿Confirmas la salida a comida?' },
  regreso_comida:{ icon:'🔄', title:'Regreso de comida',       msg:'¿Confirmas tu regreso de comida?' },
  salida:        { icon:'🔴', title:'Registrar salida',        msg:'¿Confirmas tu salida por hoy?' },
};

function abrirConfirm(tipo) {
  const l = LABELS[tipo] || {};
  document.getElementById('confirmIcon').textContent = l.icon || '🕐';
  document.getElementById('confirmTitle').textContent = l.title || 'Confirmar';
  document.getElementById('confirmMsg').textContent   = l.msg   || '¿Confirmar?';
  document.getElementById('confirmOverlay').classList.add('open');
}

document.getElementById('confirmNo').addEventListener('click', () => {
  document.getElementById('confirmOverlay').classList.remove('open');
  pendingTipo = null;
});

document.getElementById('confirmYes').addEventListener('click', async () => {
  document.getElementById('confirmOverlay').classList.remove('open');
  if (!pendingTipo || !empleadoActual) return;

  try {
    const res  = await fetch(API.check, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        empleado_id: empleadoActual.id,
        planta_id:   empleadoActual.planta_id,
        tipo_evento: pendingTipo,
      })
    });
    const data = await res.json();

    if (data.ok) {
      mostrarExito(pendingTipo);
      await cargarEstado();
    } else {
      showError(data.msg || 'No se pudo registrar.');
    }
  } catch {
    showError('Error de red.');
  }
  pendingTipo = null;
});

function mostrarExito(tipo) {
  const l = LABELS[tipo] || {};
  document.getElementById('successTitle').textContent = l.title || '¡Listo!';
  document.getElementById('successMsg').textContent   = 'Checada registrada correctamente.';
  const ov = document.getElementById('successOverlay');
  ov.classList.add('open');
  setTimeout(() => ov.classList.remove('open'), 2200);
}

// ---- Nueva búsqueda ----
document.getElementById('btnNueva').addEventListener('click', () => {
  empleadoActual = null;
  pendingTipo    = null;
  document.getElementById('inputNSS').value    = '';
  document.getElementById('inputNombre').value = '';
  document.getElementById('errorMsg').classList.remove('show');
  document.getElementById('searchPanel').style.display  = 'block';
  document.getElementById('employeeSection').style.display = 'none';
  // Reset botones
  ['btnEntrada','btnSalidaComida','btnRegresoComida','btnSalida'].forEach(id => {
    const el = document.getElementById(id);
    el.disabled = false;
    el.classList.remove('done');
  });
});

function showError(msg) {
  const el = document.getElementById('errorMsg');
  el.textContent = msg;
  el.classList.add('show');
}
</script>
</body>
</html>
