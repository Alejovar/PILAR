/**
 * manager_checador.js
 * Lógica del módulo de asistencia para el Gerente.
 * Filtra por empleado y rango de fechas, muestra tabla y permite exportar CSV.
 */

(function () {
  'use strict';

  let allRecords = [];

  // ── RELOJ ──────────────────────────────────────
  (function updateClock() {
    const el = document.getElementById('liveClockContainer');
    if (el) {
      const now = new Date();
      el.textContent = now.toLocaleTimeString('es-MX', { hour:'2-digit', minute:'2-digit', second:'2-digit' });
    }
    setTimeout(updateClock, 1000);
  })();

  // ── CARGAR LISTA DE EMPLEADOS ──────────────────
  async function loadEmployees() {
    try {
      const res  = await fetch('/src/api/attendance/get_employees_list.php');
      const data = await res.json();
      if (!data.success) return;

      const sel = document.getElementById('filterEmployee');
      data.employees.forEach(e => {
        const opt = document.createElement('option');
        opt.value = e.id;
        const plantLabel = e.plant ? ` - ${e.plant}` : '';
        opt.textContent = `${e.name}${plantLabel} (${e.rol_name || 'Sin rol'})`;
        sel.appendChild(opt);
      });
    } catch (e) { console.error('[ManagerChecador] Error cargando empleados:', e); }
  }

  // ── CARGAR REGISTROS ───────────────────────────
  async function loadRecords() {
    const userId   = document.getElementById('filterEmployee')?.value || '';
    const dateFrom = document.getElementById('filterDateFrom')?.value || '';
    const dateTo   = document.getElementById('filterDateTo')?.value   || '';

    let url = '/src/api/attendance/get_attendance.php?';
    if (userId)   url += `user_id=${encodeURIComponent(userId)}&`;
    if (dateFrom) url += `date_from=${encodeURIComponent(dateFrom)}&`;
    if (dateTo)   url += `date_to=${encodeURIComponent(dateTo)}&`;

    document.getElementById('attendanceTableBody').innerHTML =
      '<tr><td colspan="14" class="no-records">Cargando...</td></tr>';

    try {
      const res  = await fetch(url);
      const data = await res.json();

      if (!data.success) throw new Error(data.message);
      allRecords = data.records;
      renderTable(allRecords);
      updateSummary(allRecords);
    } catch (e) {
      document.getElementById('attendanceTableBody').innerHTML =
        `<tr><td colspan="14" class="no-records" style="color:#e74c3c;">Error: ${e.message}</td></tr>`;
    }
  }

  // ── RENDERIZAR TABLA ───────────────────────────
  function renderTable(records) {
    const tbody = document.getElementById('attendanceTableBody');
    if (!records.length) {
      tbody.innerHTML = '<tr><td colspan="14" class="no-records">Sin registros para los filtros seleccionados.</td></tr>';
      return;
    }

    tbody.innerHTML = records.map((r, i) => {
      const dt       = new Date(r.timestamp);
      const fecha    = dt.toLocaleDateString('es-MX',  { day:'2-digit', month:'2-digit', year:'numeric' });
      const hora     = dt.toLocaleTimeString('es-MX',  { hour:'2-digit', minute:'2-digit', second:'2-digit' });
      const tipoCls  = r.type === 'ENTRADA' ? 'entrada' : 'salida';
      const status   = r.entry_status || (r.type === 'SALIDA' ? 'CIERRE' : '—');
      const lateMin  = Number(r.minutes_late || 0);
      const overtime = Number(r.overtime_minutes || 0);
      const comment  = r.comment ? `<span title="${escapeHtml(r.comment)}" style="cursor:help;">
                        <i class="fas fa-comment" style="color:#aaa;"></i> ${escapeHtml(r.comment.slice(0, 30))}${r.comment.length > 30 ? '...' : ''}
                       </span>` : '—';
      const permission = r.permission_reason ? escapeHtml(r.permission_reason) : '—';

      return `
        <tr>
          <td>${i + 1}</td>
          <td><strong>${escapeHtml(r.user_name)}</strong><br>
              <small style="color:#aaa;">${escapeHtml(r.username)}</small></td>
          <td>${escapeHtml(r.nss || '—')}</td>
          <td>${escapeHtml(r.plant || '—')}</td>
          <td>${escapeHtml(r.rol_name || '—')}</td>
          <td><span class="badge-type ${tipoCls}">${r.type}</span></td>
          <td><span class="badge-method">${escapeHtml(status)}</span></td>
          <td>${fecha}</td>
          <td>${hora}</td>
          <td><span class="badge-method">${r.method}</span></td>
          <td>${lateMin ? `${lateMin} min` : '—'}</td>
          <td>${overtime ? `${overtime} min` : '—'}</td>
          <td>${permission}</td>
          <td>${comment}</td>
        </tr>`;
    }).join('');
  }

  // ── RESUMEN ────────────────────────────────────
  function updateSummary(records) {
    const entradas   = records.filter(r => r.type === 'ENTRADA').length;
    const salidas    = records.filter(r => r.type === 'SALIDA').length;
    const empleados  = new Set(records.map(r => r.user_id)).size;
    const retardos   = records.filter(r => r.entry_status === 'RETARDO').length;
    const permisos   = records.filter(r => r.entry_status === 'PERMISO').length;
    const extraMinutes = records.reduce((sum, r) => sum + Number(r.overtime_minutes || 0), 0);

    document.getElementById('sumTotal').textContent    = records.length;
    document.getElementById('sumEntradas').textContent  = entradas;
    document.getElementById('sumSalidas').textContent   = salidas;
    document.getElementById('sumEmpleados').textContent = empleados;
    document.getElementById('sumRetardos').textContent  = retardos;
    document.getElementById('sumPermisos').textContent   = permisos;
    document.getElementById('sumHorasExtra').textContent = `${(extraMinutes / 60).toFixed(1)} h`;
  }

  // ── EXPORTAR CSV ───────────────────────────────
  function exportCSV() {
    if (!allRecords.length) { alert('No hay registros para exportar.'); return; }

    const headers = ['#','Empleado','Usuario','NSS','Planta','Rol','Tipo','Estado','Fecha','Hora','Método','Retardo (min)','Horas extra (min)','Permiso','Comentario'];
    const rows    = allRecords.map((r, i) => {
      const dt   = new Date(r.timestamp);
      const fecha = dt.toLocaleDateString('es-MX', { day:'2-digit', month:'2-digit', year:'numeric' });
      const hora  = dt.toLocaleTimeString('es-MX', { hour:'2-digit', minute:'2-digit', second:'2-digit' });
      const status = r.entry_status || (r.type === 'SALIDA' ? 'CIERRE' : '');
      return [
        i + 1,
        csvEscape(r.user_name),
        csvEscape(r.username),
        csvEscape(r.nss || ''),
        csvEscape(r.plant || ''),
        csvEscape(r.rol_name || ''),
        r.type,
        csvEscape(status),
        fecha,
        hora,
        r.method,
        r.minutes_late || 0,
        r.overtime_minutes || 0,
        csvEscape(r.permission_reason || ''),
        csvEscape(r.comment || '')
      ].join(',');
    });

    const csv  = [headers.join(','), ...rows].join('\n');
    const blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' });
    const url  = URL.createObjectURL(blob);
    const a    = document.createElement('a');
    a.href     = url;
    a.download = `asistencia_${new Date().toISOString().slice(0,10)}.csv`;
    a.click();
    URL.revokeObjectURL(url);
  }

  function csvEscape(str) {
    return `"${String(str).replace(/"/g, '""')}"`;
  }

  function escapeHtml(str) {
    return String(str)
      .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
      .replace(/"/g,'&quot;').replace(/'/g,'&#39;');
  }

  // ── INICIALIZACIÓN ─────────────────────────────
  document.addEventListener('DOMContentLoaded', async () => {
    // Fechas por defecto
    const today    = new Date().toISOString().split('T')[0];
    const firstDay = today.slice(0,7) + '-01';
    document.getElementById('filterDateFrom').value = firstDay;
    document.getElementById('filterDateTo').value   = today;

    await loadEmployees();

    document.getElementById('btnFilter')?.addEventListener('click', loadRecords);
    document.getElementById('btnExportCSV')?.addEventListener('click', exportCSV);

    // Cargar todos los registros del mes actual al abrir
    loadRecords();
  });

})();
