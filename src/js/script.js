/**
 * Este script maneja la lógica del formulario de inicio de sesión.
 * Incluye validación de campos en tiempo real y la comunicación asíncrona
 * con el servidor para autenticar al usuario sin recargar la página.
 */
document.addEventListener('DOMContentLoaded', () => {
  
  // --- SELECCIÓN DE ELEMENTOS DEL DOM ---
  const loginForm = document.getElementById("loginForm");
  const loginError = document.getElementById("loginError");
  const submitButton = loginForm.querySelector('button[type="submit"]');
  const userInput = document.getElementById("user");
  const passwordInput = document.getElementById("password");

  if (loginForm) {

    // --- VALIDACIÓN EN TIEMPO REAL ---
    userInput.addEventListener("input", () => {
      userInput.value = userInput.value.replace(/[^A-Za-z0-9]/g, "");
      if (userInput.value.length > 12) {
        userInput.value = userInput.value.slice(0, 12);
      }
      loginError.textContent = "";
    });

    passwordInput.addEventListener("input", () => {
        if (passwordInput.value.length > 12) {
            passwordInput.value = passwordInput.value.slice(0, 12);
        }
        loginError.textContent = "";
    });

    // --- MANEJO DEL ENVÍO DEL FORMULARIO ---
    loginForm.addEventListener("submit", async (e) => {
      e.preventDefault();

      submitButton.disabled = true;
      submitButton.textContent = "Verificando...";
      let isBlocked = false; // Variable para controlar el estado de bloqueo.

      try {
        const response = await fetch(loginForm.action, {
          method: "POST",
          body: new FormData(loginForm)
        });

        const result = await response.json();

        if (result.success) {
          window.location.href = result.redirect || "/dashboard.php";
        } else {
          loginError.textContent = result.message || "Error en el inicio de sesión.";

          // Verificamos si el mensaje del servidor es el de rate limiting.
          if (result.message && result.message.includes("Demasiados intentos fallidos")) {
              isBlocked = true; // Marcamos que el usuario está bloqueado.
              // Deshabilitamos los campos para que no se puedan hacer más intentos.
              userInput.disabled = true;
              passwordInput.disabled = true;
              // El botón ya está deshabilitado, solo cambiamos el texto para claridad.
              submitButton.textContent = "Bloqueado";
          }
        }
      } catch (error) {
        console.error("Error en el login:", error);
        loginError.textContent = "Ocurrió un error en la conexión.";en 
      } finally {
        // --- RESTAURACIÓN CONDICIONAL DEL BOTÓN ---
        // Solo reactivamos el botón si el usuario NO ha sido bloqueado.//
        if (!isBlocked) {
          submitButton.disabled = false;
          submitButton.textContent = "Iniciar Sesión";
        }
      }
    });
  }
});
