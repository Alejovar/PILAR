// Archivo: /js/session_interceptor.js
// Propósito: Interceptar todas las llamadas 'fetch' para verificar la validez de la sesión de forma global.

(function() {
    // 1. Guardamos una copia de la función 'fetch' original del navegador.
    const originalFetch = window.fetch;

    // 2. Redefinimos la función 'fetch' para que pase por nuestro control de seguridad.
    window.fetch = function(...args) {
        
        // 3. Llamamos a la función 'fetch' original para que haga su trabajo normal.
        return originalFetch.apply(this, args).then(response => {
            
            // 4. ✅ El Puesto de Control: Revisamos la respuesta del servidor.
            if (response.status === 401) {
                // Si el servidor nos responde con un código 401 (No Autorizado),
                // significa que el guardián 'check_session_api.php' ha detectado una sesión inválida.
                
                // Mostramos un mensaje claro al usuario.
                alert("Tu sesión ha expirado o se ha iniciado en otro dispositivo. Serás redirigido.");
                
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
