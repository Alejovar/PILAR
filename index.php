<?php
// File: index.php (antiguo index.html) - REDIRECCIÓN POR ROL

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Definiciones de Roles (IDs según tu DB)
define('MESERO_ROLE_ID', 2);
define('COCINA_ROLE_ID', 3);
define('HOSTESS_ROLE_ID', 4);
define('BARRA_ROLE_ID', 5); // 🔑 ROL DE BARRA AÑADIDO

// 1. Bloqueo de caché
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

// 2. 🔑 LÓGICA CRÍTICA DE REDIRECCIÓN POR ROL
if (isset($_SESSION['user_id']) && isset($_SESSION['rol_id'])) {
    
    $roleId = $_SESSION['rol_id'];
    $destination = '';
    
    // Determina la página de destino basada en el rol
    switch ($roleId) {
        case MESERO_ROLE_ID:
            $destination = '/src/php/orders.php';
            break;
        case COCINA_ROLE_ID:
            $destination = '/src/php/kitchen_orders.php';
            break;
        case HOSTESS_ROLE_ID:
            $destination = '/src/php/reservations.php';
            break;
        case BARRA_ROLE_ID: // 🔑 REDIRECCIÓN A BARRA
            $destination = '/src/php/bar_orders.php';
            break;
        case CASHIER_ROLE_ID:
            $destination = '/src/php/cashier.php';
            break;
        case MANAGER_ROLE_ID:
            $destination = '/src/php/manager_dashboard.php';
            break;
        default:
            // Si el rol es desconocido, cerramos la sesión y dejamos que vea el login
            session_unset();
            session_destroy();
            break;
    }

    // Redirige si se encontró una interfaz válida
    if (!empty($destination)) {
        header('Location: ' . $destination);
        exit();
    }
}
// 3. El resto del código es el HTML del formulario de login
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sesión Bloqueada | KitchenLink</title>
      <link rel="icon" href="/src/images/logos/KitchenLink_logo.png" type="image/png" sizes="32x32">
	  <link rel="manifest" href="/manifest.json">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary-purple: #7f00ff;
            --purple-light: #a855f7;
            --purple-dark: #6b21a8;
            --glow: rgba(127, 0, 255, 0.4);
        }
        
        body, html {
            height: 100%;
            overflow: hidden;
            font-family: 'Inter', 'Segoe UI', sans-serif;
            cursor: pointer;
            position: relative;
        }

        /* Fondo con gradiente animado */
        .background {
            position: fixed;
            inset: 0;
            background: linear-gradient(135deg, 
                #f7e6ff 0%, 
                #ede9fe 25%, 
                #faf5ff 50%, 
                #f3e8ff 75%, 
                #ffffff 100%);
            background-size: 400% 400%;
            animation: gradientFlow 20s ease infinite;
        }

        /* Capa de aurora suave */
        .aurora {
            position: fixed;
            inset: 0;
            background: 
                radial-gradient(ellipse at 20% 30%, rgba(168, 85, 247, 0.1) 0%, transparent 50%),
                radial-gradient(ellipse at 80% 70%, rgba(147, 51, 234, 0.1) 0%, transparent 50%);
            animation: auroraShift 15s ease-in-out infinite alternate;
            pointer-events: none;
        }

        /* Orbes flotantes decorativos */
        .orb {
            position: absolute;
            border-radius: 50%;
            filter: blur(70px);
            opacity: 0.25;
            animation: float 20s ease-in-out infinite;
        }

        .orb1 {
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, var(--primary-purple), transparent);
            top: -100px;
            left: -100px;
            animation-delay: 0s;
        }

        .orb2 {
            width: 350px;
            height: 350px;
            background: radial-gradient(circle, var(--purple-light), transparent);
            bottom: -50px;
            right: -50px;
            animation-delay: -7s;
        }

        .orb3 {
            width: 300px;
            height: 300px;
            background: radial-gradient(circle, var(--purple-dark), transparent);
            top: 40%;
            right: 20%;
            animation-delay: -14s;
        }

        /* Partículas flotantes */
        .particles {
            position: fixed;
            inset: 0;
            overflow: hidden;
            pointer-events: none;
        }

        .particle {
            position: absolute;
            border-radius: 50%;
            opacity: 0.5;
            animation: particleFloat 15s linear infinite;
        }

        .particle.small {
            width: 3px;
            height: 3px;
            background: var(--primary-purple);
        }

        .particle.medium {
            width: 5px;
            height: 5px;
            background: var(--purple-light);
        }

        /* Estrellas parpadeantes */
        .star {
            position: absolute;
            width: 2px;
            height: 2px;
            background: white;
            border-radius: 50%;
            box-shadow: 0 0 3px rgba(127, 0, 255, 0.8);
            animation: twinkle 3s ease-in-out infinite;
        }

        /* Ondas expansivas periódicas */
        .ripple-container {
            position: fixed;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            pointer-events: none;
        }

        .ripple {
            position: absolute;
            width: 50px;
            height: 50px;
            border: 2px solid var(--primary-purple);
            border-radius: 50%;
            opacity: 0;
            animation: rippleExpand 4s ease-out infinite;
        }

        .ripple:nth-child(2) { animation-delay: 1.3s; }
        .ripple:nth-child(3) { animation-delay: 2.6s; }

        /* Contenedor principal */
        .container {
            position: relative;
            z-index: 10;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            perspective: 1500px;
        }

        /* Reloj y fecha en la esquina */
        .datetime-container {
            position: fixed;
            top: 40px;
            right: 50px;
            text-align: right;
            z-index: 20;
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(20px);
            padding: 20px 30px;
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.5);
            box-shadow: 0 8px 32px rgba(127, 0, 255, 0.1);
            animation: fadeInDown 1s ease-out forwards;
        }

        .time {
            font-size: 3rem;
            font-weight: 700;
            background: linear-gradient(135deg, var(--primary-purple), var(--purple-light));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            letter-spacing: -1px;
            line-height: 1;
            margin-bottom: 8px;
        }

        .date {
            font-size: 1.1rem;
            color: #64748b;
            font-weight: 500;
            text-transform: capitalize;
        }

        /* Card con efecto glassmorphism */
        .lock-card {
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(30px);
            border: 2px solid rgba(255, 255, 255, 0.6);
            border-radius: 36px;
            padding: 60px 70px;
            box-shadow: 
                0 8px 32px rgba(127, 0, 255, 0.15),
                0 0 0 1px rgba(255, 255, 255, 0.5) inset,
                0 0 100px rgba(127, 0, 255, 0.08);
            text-align: center;
            max-width: 500px;
            width: 90%;
            position: relative;
            overflow: visible;
            animation: cardEntrance 1.2s cubic-bezier(0.34, 1.56, 0.64, 1) forwards,
                       breathe 5s ease-in-out infinite;
            transform-style: preserve-3d;
            transition: all 0.5s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        /* Aura del card */
        .card-aura {
            position: absolute;
            inset: -40px;
            background: radial-gradient(circle, var(--glow) 0%, transparent 70%);
            border-radius: 50%;
            animation: auraPulse 3s ease-in-out infinite;
            z-index: -1;
            filter: blur(40px);
        }

        .lock-card:hover {
            transform: translateY(-8px) scale(1.03);
            box-shadow: 
                0 25px 70px rgba(127, 0, 255, 0.25),
                0 0 0 2px rgba(255, 255, 255, 0.9) inset,
                0 0 150px rgba(127, 0, 255, 0.15);
        }

        /* Logo container */
        .logo-wrapper {
            position: relative;
            margin-bottom: 35px;
            animation: logoFloat 4s ease-in-out infinite;
        }

        .logo-glow {
            position: absolute;
            inset: -30px;
            background: radial-gradient(circle, var(--glow) 0%, transparent 70%);
            border-radius: 50%;
            animation: logoGlowPulse 2.5s ease-in-out infinite;
            z-index: -1;
        }

        /* Anillos orbitales alrededor del logo */
        .orbit-ring {
            position: absolute;
            border: 2px solid rgba(127, 0, 255, 0.2);
            border-radius: 50%;
            animation: orbitSpin 20s linear infinite;
        }

        .orbit-ring:nth-child(1) {
            inset: -20px;
            animation-duration: 15s;
        }

        .orbit-ring:nth-child(2) {
            inset: -35px;
            animation-duration: 20s;
            animation-direction: reverse;
        }

        .logo-container {
            width: 150px;
            height: 150px;
            margin: 0 auto;
            background: linear-gradient(135deg, 
                var(--primary-purple) 0%, 
                var(--purple-light) 50%,
                var(--purple-dark) 100%);
            border-radius: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
            box-shadow: 
                0 15px 50px rgba(127, 0, 255, 0.4),
                0 0 60px rgba(127, 0, 255, 0.2) inset;
        }

        .logo-container::before {
            content: '';
            position: absolute;
            inset: -100%;
            background: linear-gradient(
                45deg,
                transparent 30%,
                rgba(255, 255, 255, 0.4) 50%,
                transparent 70%
            );
            animation: shine 4s linear infinite;
        }

        .logo-container::after {
            content: '';
            position: absolute;
            inset: 4px;
            background: linear-gradient(135deg, 
                rgba(255, 255, 255, 0.1), 
                transparent);
            border-radius: 28px;
        }

        .logo-container img {
            max-width: 80%;
            height: auto;
            position: relative;
            z-index: 1;
            filter: brightness(0) invert(1) drop-shadow(0 0 10px rgba(255,255,255,0.5));
            animation: logoImagePulse 3s ease-in-out infinite;
        }

        /* Icono de candado alternativo */
        .lock-icon {
            font-size: 4rem;
            color: white;
            animation: lockBounce 2s ease-in-out infinite;
            filter: drop-shadow(0 0 20px rgba(255,255,255,0.5));
        }

        /* Título */
        h1 {
            font-size: 2.8rem;
            font-weight: 800;
            background: linear-gradient(135deg, 
                var(--primary-purple) 0%, 
                var(--purple-light) 50%,
                var(--primary-purple) 100%);
            background-size: 200% 200%;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 16px;
            letter-spacing: -1.5px;
            animation: titleGradient 5s ease infinite;
            filter: drop-shadow(0 0 15px rgba(127, 0, 255, 0.2));
        }

        /* Mensaje */
        .message {
            font-size: 1.15rem;
            color: #64748b;
            line-height: 1.9;
            margin-bottom: 35px;
            animation: messageFloat 3s ease-in-out infinite;
        }

        /* Indicador de clic */
        .click-indicator {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            padding: 16px 32px;
            background: linear-gradient(135deg, 
                var(--primary-purple) 0%, 
                var(--purple-light) 50%,
                var(--primary-purple) 100%);
            background-size: 200% 200%;
            color: white;
            border-radius: 50px;
            font-weight: 700;
            font-size: 1rem;
            box-shadow: 
                0 10px 30px rgba(127, 0, 255, 0.4),
                0 0 20px rgba(127, 0, 255, 0.2) inset;
            animation: bounceIn 1.5s ease-out 0.5s backwards,
                       clickPulse 2s ease-in-out 2s infinite,
                       buttonGradient 3s ease infinite;
            transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
            position: relative;
            overflow: hidden;
        }

        .click-indicator::before {
            content: '';
            position: absolute;
            inset: -100%;
            background: linear-gradient(
                45deg,
                transparent,
                rgba(255, 255, 255, 0.3),
                transparent
            );
            animation: buttonShine 2.5s linear infinite;
        }

        .click-indicator:hover {
            transform: scale(1.08) translateY(-2px);
            box-shadow: 
                0 15px 40px rgba(127, 0, 255, 0.5),
                0 0 30px rgba(127, 0, 255, 0.3) inset;
        }

        .click-icon {
            animation: clickBounce 1s ease-in-out infinite;
            font-size: 1.3rem;
            filter: drop-shadow(0 2px 4px rgba(0,0,0,0.2));
        }

        /* Animaciones */
        @keyframes gradientFlow {
            0%, 100% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
        }

        @keyframes auroraShift {
            0% { opacity: 0.5; transform: scale(1); }
            50% { opacity: 0.8; transform: scale(1.1); }
            100% { opacity: 0.5; transform: scale(1); }
        }

        @keyframes float {
            0%, 100% { transform: translate(0, 0) scale(1); }
            50% { transform: translate(30px, -30px) scale(1.1); }
        }

        @keyframes particleFloat {
            0% {
                transform: translateY(100vh) translateX(0) rotate(0deg);
                opacity: 0;
            }
            10% { opacity: 0.5; }
            90% { opacity: 0.5; }
            100% {
                transform: translateY(-20vh) translateX(100px) rotate(360deg);
                opacity: 0;
            }
        }

        @keyframes twinkle {
            0%, 100% { opacity: 0.3; transform: scale(1); }
            50% { opacity: 1; transform: scale(1.5); }
        }

        @keyframes rippleExpand {
            0% {
                transform: scale(0);
                opacity: 1;
            }
            100% {
                transform: scale(30);
                opacity: 0;
            }
        }

        @keyframes fadeInDown {
            0% {
                opacity: 0;
                transform: translateY(-30px);
            }
            100% {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes cardEntrance {
            0% {
                opacity: 0;
                transform: translateY(80px) rotateX(-20deg) scale(0.9);
            }
            100% {
                opacity: 1;
                transform: translateY(0) rotateX(0) scale(1);
            }
        }

        @keyframes breathe {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.02); }
        }

        @keyframes auraPulse {
            0%, 100% { 
                opacity: 0.4; 
                transform: scale(1); 
            }
            50% { 
                opacity: 0.7; 
                transform: scale(1.2); 
            }
        }

        @keyframes logoFloat {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
        }

        @keyframes logoGlowPulse {
            0%, 100% { 
                opacity: 0.5; 
                transform: scale(1);
            }
            50% { 
                opacity: 1; 
                transform: scale(1.3);
            }
        }

        @keyframes orbitSpin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        @keyframes shine {
            0% { transform: translateX(-100%) translateY(-100%) rotate(45deg); }
            100% { transform: translateX(100%) translateY(100%) rotate(45deg); }
        }

        @keyframes logoImagePulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }

        @keyframes lockBounce {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.08); }
        }

        @keyframes titleGradient {
            0%, 100% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
        }

        @keyframes messageFloat {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-3px); }
        }

        @keyframes bounceIn {
            0% {
                opacity: 0;
                transform: scale(0.5) translateY(30px);
            }
            50% { 
                transform: scale(1.05); 
            }
            100% {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
        }

        @keyframes clickPulse {
            0%, 100% { 
                transform: scale(1);
            }
            50% { 
                transform: scale(1.06);
            }
        }

        @keyframes buttonGradient {
            0%, 100% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
        }

        @keyframes buttonShine {
            0% { transform: translateX(-100%) translateY(-100%); }
            100% { transform: translateX(200%) translateY(200%); }
        }

        @keyframes clickBounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-5px); }
        }

        /* Responsive */
        @media (max-width: 768px) {
            .datetime-container {
                top: 20px;
                right: 20px;
                padding: 15px 20px;
            }

            .time {
                font-size: 2rem;
            }

            .date {
                font-size: 0.9rem;
            }

            .lock-card {
                padding: 45px 35px;
            }
            
            h1 {
                font-size: 2.2rem;
            }
            
            .logo-container {
                width: 120px;
                height: 120px;
            }
        }
    </style>
</head>
<body>

<!-- Fondo animado -->
<div class="background"></div>
<div class="aurora"></div>

<!-- Orbes flotantes -->
<div class="orb orb1"></div>
<div class="orb orb2"></div>
<div class="orb orb3"></div>

<!-- Partículas -->
<div class="particles" id="particles"></div>

<!-- Estrellas -->
<div class="particles" id="stars"></div>

<!-- Ondas expansivas -->
<div class="ripple-container">
    <div class="ripple"></div>
    <div class="ripple"></div>
    <div class="ripple"></div>
</div>

<!-- Reloj y fecha -->
<div class="datetime-container">
    <div class="time" id="time">00:00:00</div>
    <div class="date" id="date">Cargando...</div>
</div>

<!-- Contenedor principal -->
<div class="container">
    <div class="lock-card" id="lockCard">
        <div class="card-aura"></div>
        
        <div class="logo-wrapper">
            <div class="orbit-ring"></div>
            <div class="orbit-ring"></div>
            <div class="logo-glow"></div>
            <div class="logo-container">
                <img src="/src/images/logos/KitchenLink_logo.png" alt="KitchenLink Logo" onerror="this.style.display='none'; this.nextElementSibling.style.display='block';"> 
                <div class="lock-icon" style="display: none;">🔒</div>
            </div>
        </div>
        
        <h1>Sesión Bloqueada</h1>
        <p class="message">Tu sesión ha sido bloqueada por seguridad.<br>Toca cualquier parte de la pantalla para continuar.</p>
        
        <div class="click-indicator">
            <span class="click-icon">👆</span>
            <span>Toca para desbloquear</span>
        </div>
    </div>
</div>

<script>
    // Actualizar fecha y hora
    function updateDateTime() {
        const now = new Date();
        
        // Formatear hora
        const hours = String(now.getHours()).padStart(2, '0');
        const minutes = String(now.getMinutes()).padStart(2, '0');
        const seconds = String(now.getSeconds()).padStart(2, '0');
        document.getElementById('time').textContent = `${hours}:${minutes}:${seconds}`;
        
        // Formatear fecha
        const options = { 
            weekday: 'long', 
            year: 'numeric', 
            month: 'long', 
            day: 'numeric' 
        };
        const dateString = now.toLocaleDateString('es-ES', options);
        document.getElementById('date').textContent = dateString;
    }

    // Actualizar cada segundo
    updateDateTime();
    setInterval(updateDateTime, 1000);

    // Crear partículas flotantes (40 partículas)
    const particlesContainer = document.getElementById('particles');
    const particleCount = 40;
    const sizes = ['small', 'medium'];

    for (let i = 0; i < particleCount; i++) {
        const particle = document.createElement('div');
        particle.className = `particle ${sizes[Math.floor(Math.random() * sizes.length)]}`;
        particle.style.left = Math.random() * 100 + '%';
        particle.style.animationDelay = Math.random() * 15 + 's';
        particle.style.animationDuration = (Math.random() * 10 + 10) + 's';
        particlesContainer.appendChild(particle);
    }

    // Crear estrellas parpadeantes (25 estrellas)
    const starsContainer = document.getElementById('stars');
    const starCount = 25;

    for (let i = 0; i < starCount; i++) {
        const star = document.createElement('div');
        star.className = 'star';
        star.style.left = Math.random() * 100 + '%';
        star.style.top = Math.random() * 100 + '%';
        star.style.animationDelay = Math.random() * 3 + 's';
        star.style.animationDuration = (Math.random() * 2 + 2) + 's';
        starsContainer.appendChild(star);
    }

    // Efecto de paralaje suave con el mouse
    document.addEventListener('mousemove', (e) => {
        const card = document.getElementById('lockCard');
        const x = (e.clientX / window.innerWidth - 0.5) * 15;
        const y = (e.clientY / window.innerHeight - 0.5) * 15;
        
        card.style.transform = `
            perspective(1500px) 
            rotateY(${x}deg) 
            rotateX(${-y}deg)
            translateY(-8px)
        `;
    });

    // Restaurar posición
    document.addEventListener('mouseleave', () => {
        const card = document.getElementById('lockCard');
        card.style.transform = 'perspective(1500px) rotateY(0) rotateX(0)';
    });

    // Función para desbloquear con animación
    const unlock = () => {
        const card = document.getElementById('lockCard');
        const datetime = document.querySelector('.datetime-container');
        
        card.style.animation = 'none';
        card.style.transform = 'scale(0.9)';
        card.style.opacity = '0';
        card.style.filter = 'blur(10px)';
        
        datetime.style.transform = 'translateY(-30px)';
        datetime.style.opacity = '0';
        
        setTimeout(() => {
            window.location.href = 'login.php';
        }, 400);
    };

    // Event listeners
    document.addEventListener('DOMContentLoaded', () => {
        document.body.addEventListener('click', unlock);
        document.addEventListener('keydown', unlock);
    });

    // Efecto de ripple al hacer clic
    document.body.addEventListener('click', (e) => {
        const ripple = document.createElement('div');
        ripple.style.position = 'fixed';
        ripple.style.left = e.clientX + 'px';
        ripple.style.top = e.clientY + 'px';
        ripple.style.width = '10px';
        ripple.style.height = '10px';
        ripple.style.borderRadius = '50%';
        ripple.style.border = '3px solid var(--primary-purple)';
        ripple.style.opacity = '1';
        ripple.style.pointerEvents = 'none';
        ripple.style.animation = 'rippleExpand 0.8s ease-out forwards';
        ripple.style.zIndex = '9999';
        document.body.appendChild(ripple);
        
        setTimeout(() => ripple.remove(), 800);
    });
</script>

</body>
</html>
