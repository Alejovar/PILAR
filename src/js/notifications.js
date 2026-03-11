// /src/js/notifications.js

document.addEventListener('DOMContentLoaded', () => {
    const notificationContainer = document.getElementById('notification-container');
    if (!notificationContainer) return;

    // Función para mostrar la alerta visual (sin cambios)
    function showNotification(message) {
        const alert = document.createElement('div');
        alert.className = 'toast-alert';
        alert.innerHTML = `<i class="fas fa-bell"></i> <div>${message}</div>`;
        notificationContainer.appendChild(alert);

        requestAnimationFrame(() => {
            alert.classList.add('show');
        });

        setTimeout(() => {
            alert.classList.remove('show');
            alert.classList.add('hide');
            alert.addEventListener('transitionend', () => alert.remove());
        }, 5000);
    }

    // Función que llama al archivo PHP
    async function checkNotifications() {
        try {
            // ==================================================================
            // EXPLICACIÓN DE LA RUTA:
            // La página que tienes abierta en el navegador es 'orders.php'.
            // El navegador hace la llamada desde la ubicación de ESA página (/src/php/).
            // Como 'ajax_check_notifications.php' también está en '/src/php/',
            // esta ruta simple funciona porque están "juntos" desde la perspectiva
            // del navegador.
            const response = await fetch('ajax_check_notifications.php'); 
            // ==================================================================
            
            if (!response.ok) {
                console.error('Error al contactar el servidor de notificaciones. Código:', response.status);
                return;
            }

            const notifications = await response.json();

            if (notifications.length > 0) {
                notifications.forEach(notification => {
                    showNotification(notification.message);
                });
            }
        } catch (error) {
            console.error('Fallo en la conexión de notificaciones:', error);
        }
    }

    // Inicia el ciclo de revisiones
    checkNotifications();
    setInterval(checkNotifications, 7000); 
});
