<?php
// /src/php/ticket_attendance_template.php
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Comprobante de Asistencia</title>
    <link rel="icon" href="/src/images/logos/KitchenLink_logo.png" type="image/png" sizes="32x32">
    <link rel="stylesheet" href="/src/css/cashier.css">
    <style>
        @media print {
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
            ? `<div class="summary-separator">--- COMENTARIO ---</div><div class="total-row"><span>Nota:</span><span>${escapeHtml(safeComment)}</span></div>`
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

    });
</script>
</body>
</html>
