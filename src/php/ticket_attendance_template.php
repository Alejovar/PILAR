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
        body.ticket-body { visibility: hidden; }
        @media print {
            .print-controls { display: none !important; }
        }
    </style>
</head>
<body class="ticket-body">

<div class="ticket-container" id="ticketContent">
    <p>Cargando comprobante...</p>
</div>

<div class="print-controls" style="text-align:center; margin-top: 20px;">
    <button onclick="window.print()" style="padding: 10px 20px; font-size: 16px;">Imprimir</button>
    <button onclick="window.close()" style="padding: 10px 20px; font-size: 16px;">Cerrar</button>
</div>

<script src="/src/js/session_interceptor.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const ticketContent = document.getElementById('ticketContent');
        const data = JSON.parse(localStorage.getItem('currentAttendanceTicketData'));
        localStorage.removeItem('currentAttendanceTicketData');

        if (!data) {
            ticketContent.innerHTML = '<p>Error: No se encontraron datos del comprobante.</p>';
            document.body.style.visibility = 'visible';
            return;
        }

        const salidaRow = data.type === 'SALIDA'
            ? `<div class="total-row"><span>Hora salida:</span><span>${data.exit_time || '-'}</span></div>`
            : '';

        ticketContent.innerHTML = `
            <header class="ticket-header">
                <h1>KitchenLink</h1>
                <p>COMPROBANTE DE ASISTENCIA</p>
            </header>
            <section class="ticket-summary-payments">
                <div class="summary-separator">--- ASISTENCIA ---</div>
                <div class="total-row"><span>Empleado:</span><span>${data.user_name || '-'}</span></div>
                <div class="total-row"><span>ID:</span><span>${data.user_id || '-'}</span></div>
                <div class="total-row"><span>Tipo:</span><span>${data.type || '-'}</span></div>
                <div class="total-row"><span>Fecha:</span><span>${data.date || '-'}</span></div>
                <div class="total-row"><span>Hora entrada:</span><span>${data.entry_time || '-'}</span></div>
                ${salidaRow}
                <div class="summary-separator-bold">================</div>
                <div class="total-row"><span>Método:</span><span>${data.method || '-'}</span></div>
            </section>
            <footer class="ticket-footer"><p>-- Fin del Comprobante --</p></footer>
        `;

        document.body.style.visibility = 'visible';
    });
</script>
</body>
</html>
