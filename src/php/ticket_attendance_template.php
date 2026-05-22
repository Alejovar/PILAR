<?php
// /src/php/ticket_attendance_template.php
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Comprobante de Asistencia</title>
    <link rel="icon" href="/src/images/logos/KitchenLink_logo.png" type="image/png" sizes="32x32">
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 24px; background: #f4f7fb; color: #1f2937; }
        .ticket-container { max-width: 420px; margin: 0 auto; background: #fff; border-radius: 18px; padding: 22px; box-shadow: 0 10px 30px rgba(0,0,0,.08); }
        .ticket-header { text-align: center; margin-bottom: 18px; }
        .ticket-header h1 { margin: 0; font-size: 28px; }
        .ticket-header p { margin: 6px 0 0; font-size: 12px; letter-spacing: 1px; }
        .summary-separator, .summary-separator-bold { text-align: center; margin: 14px 0; font-weight: 700; }
        .total-row { display: flex; justify-content: space-between; gap: 12px; padding: 6px 0; border-bottom: 1px dashed #e5e7eb; }
        .total-row span:last-child { text-align: right; }
        .ticket-comment-text { margin-top: 8px; padding: 10px 12px; background: #f8fafc; border-radius: 10px; font-size: 13px; }
        .ticket-footer { text-align: center; margin-top: 18px; font-size: 12px; color: #6b7280; }
        .print-controls { text-align: center; margin-top: 20px; display: flex; gap: 10px; justify-content: center; flex-wrap: wrap; }
        .print-controls button { padding: 10px 18px; font-size: 15px; border: 0; border-radius: 10px; cursor: pointer; background: #5a2dfc; color: #fff; }
        @media print {
            body { background: #fff; padding: 0; }
            .ticket-container { box-shadow: none; border-radius: 0; max-width: none; }
            .print-controls { display: none !important; }
        }
    </style>
</head>
<body>

<div class="ticket-container" id="ticketContent">
    <p>Cargando comprobante...</p>
</div>

<div class="print-controls" style="text-align:center; margin-top: 20px;">
    <button onclick="window.print()" style="padding: 10px 20px; font-size: 16px;">Imprimir</button>
    <button onclick="window.close()" style="padding: 10px 20px; font-size: 16px;">Cerrar</button>
</div>

<script src="/src/js/session_interceptor.js"></script>
<script>
    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    document.addEventListener('DOMContentLoaded', () => {
        const ticketContent = document.getElementById('ticketContent');
        const data = JSON.parse(localStorage.getItem('currentAttendanceTicketData'));
        localStorage.removeItem('currentAttendanceTicketData');

        if (!data) {
            ticketContent.innerHTML = '<p>Error: No se encontraron datos del comprobante.</p>';
            return;
        }

        const safeComment = String(data.comment || '').trim().slice(0, 60);
        const salidaRow = data.type === 'SALIDA'
            ? `<div class="total-row"><span>Hora salida:</span><span>${escapeHtml(data.exit_time || '-')}</span></div>`
            : '';

        const commentRow = safeComment
            ? `<div class="summary-separator">--- COMENTARIO ---</div><div class="total-row total-row-note"></div><div class="ticket-comment-text">${escapeHtml(safeComment)}</div>`
            : '';

        ticketContent.innerHTML = `
            <header class="ticket-header">
                <h1>KitchenLink</h1>
                <p>COMPROBANTE DE ASISTENCIA</p>
            </header>
            <section class="ticket-summary-payments">
                <div class="summary-separator">--- ASISTENCIA ---</div>
                <div class="total-row"><span>Empleado:</span><span>${escapeHtml(data.user_name || '-')}</span></div>
                <div class="total-row"><span>ID:</span><span>${escapeHtml(data.user_id || '-')}</span></div>
                <div class="total-row"><span>Tipo:</span><span>${escapeHtml(data.type || '-')}</span></div>
                <div class="total-row"><span>Fecha:</span><span>${escapeHtml(data.date || '-')}</span></div>
                <div class="total-row"><span>Hora entrada:</span><span>${escapeHtml(data.entry_time || '-')}</span></div>
                ${salidaRow}
                ${commentRow}
                <div class="summary-separator-bold">================</div>
                <div class="total-row"><span>Método:</span><span>${escapeHtml(data.method || '-')}</span></div>
            </section>
            <footer class="ticket-footer"><p>-- Fin del Comprobante --</p></footer>
        `;

        setTimeout(() => window.print(), 250);

    });
</script>
</body>
</html>
