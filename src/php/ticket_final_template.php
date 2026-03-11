<?php
// /src/php/ticket_final_template.php
$sale_id = $_GET['sale_id'] ?? 0;
$discount = $_GET['discount'] ?? 0;
$cash_received = $_GET['cash_received'] ?? 0; // Efectivo que el cliente dio (input)
$change = $_GET['change'] ?? 0; // Cambio que se entregó (calculado)
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Recibo Final #<?php echo htmlspecialchars($sale_id); ?> | KitchenLink</title>
      <link rel="icon" href="/src/images/logos/KitchenLink_logo.png" type="image/png" sizes="32x32">
    <link rel="stylesheet" href="/src/css/cashier.css">
    <style>
        body.ticket-body { visibility: hidden; }
    </style>
</head>
<body class="ticket-body">

<div class="ticket-container" id="ticketContent">
    <p>Cargando recibo...</p>
</div>

<script>
    document.addEventListener('DOMContentLoaded', async () => {
        const saleId = <?php echo json_encode($sale_id); ?>;
        const discountFromJs = <?php echo json_encode((float)$discount); ?>;
        const cashReceived = <?php echo json_encode((float)$cash_received); ?>;
        const changeDue = <?php echo json_encode((float)$change); ?>;
        
        const ticketContent = document.getElementById('ticketContent');

        if (!saleId) {
            ticketContent.innerHTML = '<p>Error: No se especificó un ID de Venta.</p>';
            return;
        }

        try {
            // 1. Obtener datos del nuevo endpoint
            const response = await fetch(`/src/api/cashier/get_sale_details.php?sale_id=${saleId}`);
            const result = await response.json();

            if (!result.success) throw new Error(result.message);
            
            const sale = result.data;
            const totalPagadoCuenta = parseFloat(sale.totals.grand_total_paid);
            const totalDescuento = parseFloat(sale.totals.discount);
            const totalIVA = parseFloat(sale.totals.tax);
            const subtotalItems = parseFloat(sale.totals.subtotal_items);
            const tipCard = parseFloat(sale.totals.tip_card);

            
            // --- LÓGICA DE AGRUPACIÓN DE ÍTEMS ---
            const groupedItems = new Map();
            sale.items.forEach(item => {
                const modifier = item.modifier_name ? ` (${item.modifier_name})` : '';
                const key = item.product_name + modifier;
                const itemTotal = item.quantity * item.price_at_order;

                if (groupedItems.has(key)) {
                    const existing = groupedItems.get(key);
                    existing.quantity += item.quantity;
                    existing.total += itemTotal;
                } else {
                    groupedItems.set(key, {
                        quantity: item.quantity,
                        description: item.product_name,
                        modifier: modifier,
                        total: itemTotal 
                    });
                }
            });

            let itemsHtml = '';
            groupedItems.forEach(item => {
                itemsHtml += `
                    <tr>
                        <td class="qty">${item.quantity}</td>
                        <td class="desc">${item.description}${item.modifier}</td>
                        <td class="price">${item.total.toFixed(2)}</td>
                    </tr>
                `;
            });

            // --- SECCIÓN DE PAGOS (CORREGIDA) ---
            let paymentsHtml = '';
            let totalPagadoEfectivo = 0;

            sale.payments.forEach(p => {
                // 💥 CORRECCIÓN CRÍTICA: Leemos el nombre del método del campo 'method'
                const method_name = p.method; 
                const amount = parseFloat(p.amount);

                if (!isNaN(amount) && amount > 0) {
                    paymentsHtml += `<div class="payment-row"><span>${method_name}:</span><span>$${amount.toFixed(2)}</span></div>`;
                    
                    if (method_name === 'Efectivo') {
                        totalPagadoEfectivo += amount;
                    }
                }
            });

            // Calculamos el efectivo real que el cliente entregó (para el cambio)
            const efectivoRecibidoCliente = cashReceived > totalPagadoEfectivo ? cashReceived : totalPagadoEfectivo;


            ticketContent.innerHTML = `
                <header class="ticket-header">
                    <h1>${sale.header.restaurant_name}</h1>
                    <p>RECIBO DE VENTA FINAL</p>
                    <p>Venta: #${sale.header.sale_id} | Orden: #${sale.header.order_id}</p>
                    <p>Fecha: ${sale.header.date}</p>
                    <p>Mesa: ${sale.header.table_number} | Atendió: ${sale.header.server_name}</p>
                    
                    <p>Cobró: ${sale.header.cashier_name || 'Sistema'}</p> 
                    
                </header>
                <section class="ticket-items">
                    <table>
                        <thead><tr><th class="qty">#</th><th class="desc">Descripción</th><th class="price">Total</th></tr></thead>
                        <tbody>${itemsHtml}</tbody>
                    </table>
                </section>
                <section class="ticket-summary-payments">
                    <div class="summary-separator">---------------------------------</div>
                    
                    <div class="total-row"><span>Subtotal Ítems:</span><span>$${subtotalItems.toFixed(2)}</span></div>
                    <div class="total-row"><span>Descuento Aplicado:</span><span>-$${totalDescuento.toFixed(2)}</span></div>
                    <div class="total-row"><span>IVA (16%):</span><span>$${totalIVA.toFixed(2)}</span></div>
                    
                    <div class="summary-separator-bold">=================================</div>
                    
                    <div class="total-row grand-total"><span>TOTAL PAGADO:</span><span>$${totalPagadoCuenta.toFixed(2)}</span></div>
                    
                    ${tipCard > 0 ? `<div class="total-row tip-total"><span>Propina (Tarjeta):</span><span>$${tipCard.toFixed(2)}</span></div>` : ''}

                    <div class="summary-separator">--- Pagos ---</div>
                    ${paymentsHtml}
                    ${efectivoRecibidoCliente > 0 ? `<div class="payment-row cash-received"><span>Efectivo Recibido:</span><span>$${efectivoRecibidoCliente.toFixed(2)}</span></div>` : ''}
                    ${changeDue > 0 ? `<div class="payment-row cash-change"><span>Cambio:</span><span>$${changeDue.toFixed(2)}</span></div>` : ''}

                </section>
                <footer class="ticket-footer"><p>¡Gracias por su visita!</p></footer>
            `;
            
            // Hacer visible y llamar a imprimir
            document.body.style.visibility = 'visible';
            
            setTimeout(() => {
                window.print();
                window.onafterprint = () => window.close(); 
            }, 100); 

        } catch (error) {
            // Manejamos errores de parseo o de lógica
            const errorMessage = (error.message.includes('JSON')) ? "JSON.parse: Verifique la respuesta del servidor." : error.message;
            ticketContent.innerHTML = `<p>Error al cargar el recibo: ${errorMessage}</p>`;
            document.body.style.visibility = 'visible';
        }
    });
</script>

</body>
</html>
