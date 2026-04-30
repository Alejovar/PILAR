// Archivo: /js/session_interceptor.js
// Propósito: Interceptar todas las llamadas 'fetch' para verificar la validez de la sesión de forma global.

(function() {
    const nativeAlert = window.alert.bind(window);
    const nativeConfirm = window.confirm.bind(window);
    let dialogQueue = Promise.resolve();

    function ensureDialogStyles() {
        if (document.getElementById('app-dialog-styles')) return;
        const style = document.createElement('style');
        style.id = 'app-dialog-styles';
        style.textContent = `
            .app-dialog-overlay {
                position: fixed;
                inset: 0;
                background: rgba(30, 27, 75, 0.35);
                backdrop-filter: blur(3px);
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 99999;
                opacity: 0;
                transition: opacity 0.15s ease;
                padding: 16px;
            }
            .app-dialog-overlay.visible {
                opacity: 1;
            }
            .app-dialog {
                width: 100%;
                max-width: 420px;
                background: #ffffff;
                border-radius: 16px;
                border: 1px solid #e8eaf6;
                box-shadow: 0 16px 40px rgba(30, 27, 75, 0.22);
                overflow: hidden;
                transform: translateY(6px);
                transition: transform 0.15s ease;
                font-family: 'Montserrat', 'Segoe UI', Tahoma, Arial, sans-serif;
            }
            .app-dialog-overlay.visible .app-dialog {
                transform: translateY(0);
            }
            .app-dialog-header {
                padding: 14px 16px;
                font-weight: 700;
                color: #ffffff;
                border-bottom: 1px solid rgba(255, 255, 255, 0.15);
                background: linear-gradient(135deg, #4f46e5, #7c3aed);
            }
            .app-dialog-body {
                padding: 18px 16px;
                color: #374151;
                line-height: 1.5;
                white-space: pre-line;
            }
            .app-dialog-actions {
                display: flex;
                justify-content: flex-end;
                gap: 10px;
                padding: 12px 16px 16px;
            }
            .app-dialog-btn {
                border: 0;
                border-radius: 10px;
                padding: 9px 16px;
                font-weight: 600;
                cursor: pointer;
                transition: transform 0.12s ease, box-shadow 0.12s ease, background 0.12s ease;
            }
            .app-dialog-btn.cancel {
                background: #eef2ff;
                color: #312e81;
            }
            .app-dialog-btn.cancel:hover {
                background: #e0e7ff;
            }
            .app-dialog-btn.confirm {
                background: linear-gradient(135deg, #4f46e5, #7c3aed);
                color: #ffffff;
                box-shadow: 0 6px 16px rgba(79, 70, 229, 0.3);
            }
            .app-dialog-btn.confirm:hover {
                background: linear-gradient(135deg, #4338ca, #6d28d9);
                box-shadow: 0 8px 18px rgba(109, 40, 217, 0.32);
            }
        `;
        (document.head || document.documentElement).appendChild(style);
    }

    function enqueueDialog(factory) {
        dialogQueue = dialogQueue
            .then(() => factory())
            .catch(() => factory());
        return dialogQueue;
    }

    function showAppDialog({ title = 'Aviso', message = '', showCancel = false, confirmText = 'Aceptar', cancelText = 'Cancelar' }) {
        if (!document.body) {
            if (showCancel) return Promise.resolve(nativeConfirm(String(message)));
            nativeAlert(String(message));
            return Promise.resolve(true);
        }

        ensureDialogStyles();

        return new Promise((resolve) => {
            const overlay = document.createElement('div');
            overlay.className = 'app-dialog-overlay';

            const dialog = document.createElement('div');
            dialog.className = 'app-dialog';
            dialog.setAttribute('role', 'dialog');
            dialog.setAttribute('aria-modal', 'true');
            dialog.setAttribute('aria-label', title);

            const header = document.createElement('div');
            header.className = 'app-dialog-header';
            header.textContent = title;

            const body = document.createElement('div');
            body.className = 'app-dialog-body';
            body.textContent = String(message);

            const actions = document.createElement('div');
            actions.className = 'app-dialog-actions';

            const confirmButton = document.createElement('button');
            confirmButton.type = 'button';
            confirmButton.className = 'app-dialog-btn confirm';
            confirmButton.textContent = confirmText;

            let cancelButton = null;
            if (showCancel) {
                cancelButton = document.createElement('button');
                cancelButton.type = 'button';
                cancelButton.className = 'app-dialog-btn cancel';
                cancelButton.textContent = cancelText;
                actions.appendChild(cancelButton);
            }
            actions.appendChild(confirmButton);

            dialog.appendChild(header);
            dialog.appendChild(body);
            dialog.appendChild(actions);
            overlay.appendChild(dialog);
            document.body.appendChild(overlay);

            const cleanup = (result) => {
                document.removeEventListener('keydown', onEscape);
                overlay.classList.remove('visible');
                setTimeout(() => overlay.remove(), 150);
                resolve(result);
            };

            const onEscape = (event) => {
                if (event.key === 'Escape') {
                    cleanup(showCancel ? false : true);
                }
            };

            confirmButton.addEventListener('click', () => cleanup(true));
            if (cancelButton) {
                cancelButton.addEventListener('click', () => cleanup(false));
            }
            overlay.addEventListener('click', (event) => {
                if (event.target === overlay) cleanup(showCancel ? false : true);
            });
            document.addEventListener('keydown', onEscape);

            requestAnimationFrame(() => {
                overlay.classList.add('visible');
                confirmButton.focus();
            });
        });
    }

    window.appAlert = (message, title = 'KitchenLink') => {
        return enqueueDialog(() => showAppDialog({
            title,
            message,
            showCancel: false,
            confirmText: 'Aceptar'
        }));
    };

    window.appConfirm = (message, title = 'Confirmación') => {
        return enqueueDialog(() => showAppDialog({
            title,
            message,
            showCancel: true,
            confirmText: 'Sí, continuar',
            cancelText: 'Cancelar'
        }));
    };

    // Reemplazo global de alert para evitar el cuadro nativo "localhost dice".
    window.alert = function(message) {
        window.appAlert(message);
    };

    // 1. Guardamos una copia de la función 'fetch' original del navegador.
    const originalFetch = window.fetch;

    // 2. Redefinimos la función 'fetch' para que pase por nuestro control de seguridad.
    window.fetch = function(...args) {
        
        // 3. Llamamos a la función 'fetch' original para que haga su trabajo normal.
        return originalFetch.apply(this, args).then(async response => {
            
            // 4. ✅ El Puesto de Control: Revisamos la respuesta del servidor.
            if (response.status === 401) {
                // Si el servidor nos responde con un código 401 (No Autorizado),
                // significa que el guardián 'check_session_api.php' ha detectado una sesión inválida.
                
                // Mostramos un mensaje claro al usuario.
                await window.appAlert("Tu sesión ha expirado o se ha iniciado en otro dispositivo. Serás redirigido.");
                
                // Redirigimos la página al inicio de sesión.
                window.location.href = '/index.php'; 
                
                // Detenemos la cadena de promesas para evitar errores en el código original.
                return new Promise(() => {}); 
            }
            
            // 5. Si la respuesta es cualquier otra cosa que no sea 401, la dejamos pasar sin cambios.
            return response;
        });
    };
})();
