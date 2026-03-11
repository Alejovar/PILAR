<?php
// /src/php/ticket_template.php
$order_id = $_GET['order_id'] ?? 0;
$discount = $_GET['discount'] ?? 0;

// 💡 Fuerza el juego de caracteres, esencial para acentos y 'ñ'
header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Pre-Ticket #<?php echo htmlspecialchars($order_id); ?> | KitchenLink</title>
      <link rel="icon" href="/src/images/logos/KitchenLink_logo.png" type="image/png" sizes="32x32">
    <link rel="stylesheet" href="/src/css/cashier.css">
    <style>
        /* Estilos para que la página se vea en blanco mientras carga */
        body.ticket-body { visibility: hidden; }
    </style>
</head>
<body class="ticket-body">

<div class="ticket-container" id="ticketContent">
    <p>Cargando ticket...</p>
</div>

<script>
    document.addEventListener('DOMContentLoaded', async () => {
        const orderId = <?php echo json_encode($order_id); ?>;
        const discount = <?php echo json_encode((float)$discount); ?>;
        const ticketContent = document.getElementById('ticketContent');

        if (!orderId) {
            ticketContent.innerHTML = '<p>Error: No se especificó una orden.</p>';
            return;
        }

        try {
            const response = await fetch(`/src/api/cashier/get_ticket_data.php?order_id=${orderId}`);
            const result = await response.json();

            if (!result.success) throw new Error(result.message);
            
            const sale = result.data;
            // Cálculo del IVA y Total ajustado por descuento
            const subtotalMenosDescuento = sale.totals.subtotal - discount;
            const tax = subtotalMenosDescuento * 0.16;
            const finalTotal = subtotalMenosDescuento + tax;

            let itemsHtml = '';
            sale.items.forEach(item => {
                const total = item.quantity * item.price_at_order;
                const modifier = item.modifier_name ? ` (${item.modifier_name})` : '';
                itemsHtml += `
                    <tr>
                        <td class="qty">${item.quantity}</td>
                        <td class="desc">${item.product_name}${modifier}</td>
                        <td class="price">${total.toFixed(2)}</td>
                    </tr>
                `;
            });

            ticketContent.innerHTML = `
                <header class="ticket-header">
                    <h1>${sale.header.restaurant_name}</h1>
                    <p>Orden de Cuenta (Pre-Ticket)</p>
                    <p>Folio: #${sale.header.order_id}</p>
                    <p>Fecha: ${sale.header.date}</p>
                    <p>Mesa: ${sale.header.table_number} | Atendió: ${sale.header.server_name}</p>
                </header>
                <section class="ticket-items">
                    <table>
                        <thead><tr><th class="qty">#</th><th class="desc">Descripción</th><th class="price">Total</th></tr></thead>
                        <tbody>${itemsHtml}</tbody>
                    </table>
                </section>
                <section class="ticket-totals">
                    <div class="total-row"><span>Subtotal:</span><span>$${sale.totals.subtotal.toFixed(2)}</span></div>
                    <div class="total-row"><span>Descuento:</span><span>-$${discount.toFixed(2)}</span></div>
                    <div class="total-row"><span>IVA (16%):</span><span>$${tax.toFixed(2)}</span></div>
                    <div class="total-row grand-total"><span>TOTAL:</span><span>$${finalTotal.toFixed(2)}</span></div>
                </section>
                <footer class="ticket-footer"><p>¡Gracias por su visita!</p></footer>
            `;
            
            // Hacer visible y llamar a imprimir
            document.body.style.visibility = 'visible';
            
            // 💡 Asegura que la impresión se active SÓLO después de que la ventana está lista
            // Si window.print() falla, puede ser porque se ejecuta antes de que el navegador aplique el CSS.
            setTimeout(() => {
                window.print();
                window.onafterprint = () => window.close(); // Cierra la ventana después de imprimir
            }, 100); 

        } catch (error) {
            ticketContent.innerHTML = `<p>Error al cargar el ticket: ${error.message}</p>`;
            document.body.style.visibility = 'visible';
        }
    });
</script>

</body>
</html>
