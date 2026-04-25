<?php
// /src/php/ticket_server_report_template.php
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Mesero</title>
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
    <p>Cargando reporte de mesero...</p>
</div>

<div class="print-controls" style="text-align:center; margin-top: 20px;">
    <button onclick="window.print()" style="padding: 10px 20px; font-size: 16px;">Imprimir</button>
    <button onclick="window.close()" style="padding: 10px 20px; font-size: 16px;">Cerrar</button>
</div>

<script src="/src/js/session_interceptor.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const ticketContent = document.getElementById('ticketContent');
        const reportData = JSON.parse(localStorage.getItem('currentServerReportData'));
        localStorage.removeItem('currentServerReportData');

        if (!reportData) {
            ticketContent.innerHTML = "<p>Error: No se encontraron datos del reporte.</p>";
            document.body.style.visibility = 'visible';
            return;
        }

        const formatCurrency = (amount) => `$${parseFloat(amount).toFixed(2)}`;
        // --- 👇 NUEVO: Función para formatear porcentaje ---
        const formatPercent = (rate) => `${(parseFloat(rate) * 100).toFixed(0)}%`;
        
        // --- SECCIÓN DE VENTAS (SIN CAMBIOS) ---
        let salesHtml = `<div class="summary-separator">--- TOTALES DE VENTAS ---</div>`;
        salesHtml += `<div class="total-row"><span># de Ventas:</span><span>${reportData.sales_count}</span></div>`;
        salesHtml += `<div class="total-row"><span>Subtotal:</span><span>${formatCurrency(reportData.subtotal)}</span></div>`;
        salesHtml += `<div class="total-row discount"><span>Descuentos:</span><span>-${formatCurrency(reportData.discount)}</span></div>`;
        salesHtml += `<div class="total-row grand-total"><span>TOTAL VENDIDO:</span><span>${formatCurrency(reportData.grand_total)}</span></div>`;
        
        
        // --- 👇 SECCIÓN DE LIQUIDACIÓN (MODIFICADA) ---
        let tipsHtml = `<div class="summary-separator">--- LIQUIDACIÓN DE PROPINAS ---</div>`;
        tipsHtml += `<div class="total-row"><span>Total Propinas Tarjeta:</span><span>${formatCurrency(reportData.card_tips)}</span></div>`;
        tipsHtml += `<div class="total-row discount"><span>Deducción (${formatPercent(reportData.deduction_rate)} de Venta):</span><span>-${formatCurrency(reportData.deduction_amount)}</span></div>`;
        tipsHtml += `<div class="summary-separator-bold">================</div>`;
        // Reutilizamos 'tip-total' para que salga verde, o 'cash-change' para que salga grande
        tipsHtml += `<div class="payment-row cash-change"><span>PAGO A MESERO:</span><span>${formatCurrency(reportData.final_payout)}</span></div>`;


        // --- Renderizado Final ---
        ticketContent.innerHTML = `
            <header class="ticket-header">
                <h1>Reporte de Mesero</h1>
                <p>Turno #${reportData.shift_id}</p>
                <p>Mesero: ${reportData.server_name}</p>
                <p>Fecha Reporte: ${new Date().toLocaleString('es-MX', { dateStyle: 'short', timeStyle: 'short' })}</p>
            </header>
            
            <section class="ticket-summary-payments">
                ${salesHtml}
                ${tipsHtml}
            </section>
            
            <footer class="ticket-footer"><p>-- Fin del Reporte --</p></footer>
        `;
        
        document.body.style.visibility = 'visible';
        setTimeout(() => window.print(), 300);

    });
</script>
</body>
</html>
