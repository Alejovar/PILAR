<?php
// /src/php/ticket_shift_report_template.php
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Turno (Corte Z)</title>
    <link rel="icon" href="/src/images/logos/KitchenLink_logo.png" type="image/png" sizes="32x32">
    
    <link rel="stylesheet" href="/src/css/cashier.css">
    
    <style>
        body.ticket-body { visibility: hidden; }
        
        /* Ocultamos los botones de control al imprimir */
        @media print {
            .print-controls {
                display: none !important;
            }
        }
    </style>
</head>
<body class="ticket-body">

<div class="ticket-container" id="ticketContent">
    <p>Cargando reporte...</p>
</div>

<div class="print-controls" style="text-align:center; margin-top: 20px;">
    <button onclick="window.print()" style="padding: 10px 20px; font-size: 16px;">Imprimir</button>
    <button onclick="window.close()" style="padding: 10px 20px; font-size: 16px;">Cerrar</button>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const ticketContent = document.getElementById('ticketContent');
        
        // Obtenemos los datos que el JS principal guardó
        const reportData = JSON.parse(localStorage.getItem('currentShiftReportData'));
        localStorage.removeItem('currentShiftReportData'); // Limpiamos

        if (!reportData) {
            ticketContent.innerHTML = "<p>Error: No se encontraron datos del reporte.</p>";
            document.body.style.visibility = 'visible';
            return;
        }

        const formatCurrency = (amount) => `$${parseFloat(amount).toFixed(2)}`;
        
        // --- 1. Desglose de Ventas (usando clases .total-row) ---
        let salesHtml = `<div class="summary-separator">--- VENTAS TOTALES ---</div>`;
        salesHtml += `<div class="total-row"><span>Subtotal:</span><span>${formatCurrency(reportData.totals.subtotal)}</span></div>`;
        salesHtml += `<div class="total-row discount"><span>Descuentos:</span><span>-${formatCurrency(reportData.totals.discount)}</span></div>`;
        salesHtml += `<div class="total-row"><span>Impuestos (IVA):</span><span>${formatCurrency(reportData.totals.tax)}</span></div>`;
        salesHtml += `<div class="summary-separator-bold">================</div>`;
        salesHtml += `<div class="total-row grand-total"><span>GRAN TOTAL:</span><span>${formatCurrency(reportData.totals.grand_total)}</span></div>`;
        salesHtml += `<div class="total-row tip-total"><span>Propinas Tarjeta:</span><span>${formatCurrency(reportData.totals.card_tips)}</span></div>`;
        salesHtml += `<div class="total-row"><span># de Ventas:</span><span>${reportData.totals.sales_count}</span></div>`;

        // --- 2. Desglose de Pagos (usando clases .payment-row) ---
        let paymentsHtml = `<div class="summary-separator">--- DESGLOSE DE PAGOS ---</div>`;
        for (const [method, amount] of Object.entries(reportData.payments)) {
            paymentsHtml += `<div class="payment-row"><span>${method}:</span><span>${formatCurrency(amount)}</span></div>`;
        }

        // --- 3. Arqueo de Caja (usando clases .total-row y .cash-change) ---
        const cash = reportData.cash_report;
        let cashHtml = `<div class="summary-separator">--- ARQUEO DE CAJA ---</div>`;
        cashHtml += `<div class="total-row"><span>Fondo Inicial:</span><span>${formatCurrency(cash.starting_cash)}</span></div>`;
        cashHtml += `<div class="total-row"><span>Ventas en Efectivo:</span><span>${formatCurrency(cash.total_cash_sales)}</span></div>`;
        // Aquí irían Entradas/Salidas si las tuvieras
        cashHtml += `<div class="total-row grand-total"><span>Efectivo Esperado:</span><span>${formatCurrency(cash.expected_cash_total)}</span></div>`;
        cashHtml += `<div class="total-row"><span>Efectivo Contado:</span><span>${formatCurrency(cash.manual_cash_total)}</span></div>`;
        cashHtml += `<div class="summary-separator-bold">================</div>`;
        
        // Reutilizamos la clase 'cash-change' (que es grande y negrita) para la diferencia
        let diffText = "DIFERENCIA:";
        if (cash.difference > 0.01) diffText = "SOBRANTE:";
        if (cash.difference < -0.01) diffText = "FALTANTE:";
        cashHtml += `<div class="payment-row cash-change"><span>${diffText}</span><span>${formatCurrency(cash.difference)}</span></div>`;

        // --- Renderizado Final ---
        ticketContent.innerHTML = `
            <header class="ticket-header">
                <h1>Reporte de Turno (Corte Z)</h1>
                <p>Turno #${reportData.shift_id}</p>
                <p>Abierto por: ${reportData.user_opened_name}</p>
                <p>Cerrado por: ${reportData.user_closed_name}</p>
                <p>Fecha Cierre: ${new Date().toLocaleString('es-MX', { dateStyle: 'short', timeStyle: 'short' })}</p>
            </header>
            
            <section class="ticket-summary-payments">
                ${salesHtml}
                ${paymentsHtml}
                ${cashHtml}
            </section>
            
            <footer class="ticket-footer"><p>-- Fin del Reporte --</p></footer>
        `;
        
        // Hacer visible e imprimir
        document.body.style.visibility = 'visible';
        setTimeout(() => window.print(), 300); // Damos un poco más de tiempo

    });
</script>
</body>
</html>
