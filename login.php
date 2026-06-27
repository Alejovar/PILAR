<?php
// /login.php
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['lifetime'=>28800,'path'=>'/','httponly'=>true,'samesite'=>'Lax']);
    session_start();
}
if (!empty($_SESSION['user_id'])) {
    header('Location: /dashboard.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Acceso | PILAR</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;600;700;800;900&display=swap');
    :root {
      --primary: #F5C400;
      --accent:  #22C55E;
      --bg:      #0f1117;
      --surface: #1a1d27;
      --s2:      #22263a;
      --border:  rgba(255,255,255,0.08);
      --text:    #f1f5f9;
      --muted:   #8892a4;
    }
    *, *::before, *::after { box-sizing: border-box; margin:0; padding:0; }
    body {
      font-family: 'Montserrat', sans-serif;
      background: var(--bg);
      color: var(--text);
      min-height: 100dvh;
      display: grid;
      grid-template-columns: 1fr 1fr;
    }

    /* ---- LEFT HERO ---- */
    .hero {
      background: linear-gradient(160deg, #1a1d27 0%, #0f1117 100%);
      border-right: 1px solid var(--border);
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      padding: 48px;
      position: relative;
      overflow: hidden;
    }
    .hero::before {
      content: '';
      position: absolute;
      top: -120px; left: -120px;
      width: 480px; height: 480px;
      background: radial-gradient(circle, rgba(245,196,0,0.12) 0%, transparent 70%);
      pointer-events: none;
    }
    .hero::after {
      content: '';
      position: absolute;
      bottom: -80px; right: -80px;
      width: 320px; height: 320px;
      background: radial-gradient(circle, rgba(34,197,94,0.1) 0%, transparent 70%);
      pointer-events: none;
    }
    .hero-brand {
      display: flex;
      align-items: center;
      gap: 14px;
      position: relative;
      z-index: 1;
    }
    .hero-logo {
      width: 75px;
      height: 75px;
      object-fit: contain;
      flex-shrink: 0;
    }
    .hero-brand-name { font-size: 20px; font-weight: 900; color: var(--primary); }
    .hero-brand-sub  { font-size: 11px; color: var(--muted); letter-spacing: 0.06em; }

    .hero-body { position: relative; z-index: 1; }
    .hero-body h1 {
      font-size: clamp(2rem, 3.5vw, 3rem);
      font-weight: 900;
      line-height: 1.1;
      margin-bottom: 16px;
    }
    .hero-body h1 span { color: var(--primary); }
    .hero-body p { color: var(--muted); font-size: 14px; max-width: 38ch; line-height: 1.7; }

    .hero-checker-link {
      display: inline-flex;
      align-items: center;
      gap: 10px;
      margin-top: 28px;
      padding: 14px 22px;
      background: rgba(34,197,94,0.1);
      border: 1px solid rgba(34,197,94,0.3);
      border-radius: 12px;
      color: var(--accent);
      font-weight: 700;
      font-size: 14px;
      text-decoration: none;
      transition: background 0.2s;
    }
    .hero-checker-link:hover { background: rgba(34,197,94,0.18); }
    .hero-checker-link i { font-size: 16px; }

    .hero-footer { position: relative; z-index: 1; }
    .hero-pills  { display: flex; gap: 8px; flex-wrap: wrap; }
    .pill {
      background: rgba(255,255,255,0.05);
      border: 1px solid var(--border);
      border-radius: 999px;
      padding: 5px 12px;
      font-size: 11px;
      font-weight: 700;
      color: var(--muted);
    }

    /* ---- RIGHT FORM ---- */
    .form-side {
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 40px 48px;
    }
    .form-box { width: 100%; max-width: 380px; }
    .form-box h2 { font-size: 24px; font-weight: 800; margin-bottom: 6px; }
    .form-box .sub { font-size: 13px; color: var(--muted); margin-bottom: 32px; }

    .field { display: flex; flex-direction: column; gap: 7px; margin-bottom: 16px; }
    .field label {
      font-size: 11px; font-weight: 700;
      text-transform: uppercase; letter-spacing: 0.08em;
      color: var(--muted);
    }
    .field-wrap { position: relative; }
    .field-wrap i {
      position: absolute; left: 14px; top: 50%;
      transform: translateY(-50%);
      color: var(--muted); font-size: 14px;
      pointer-events: none;
    }
    .field-wrap input {
      width: 100%;
      background: var(--s2);
      border: 1px solid rgba(255,255,255,0.1);
      border-radius: 10px;
      color: var(--text);
      padding: 13px 14px 13px 40px;
      font-size: 14px;
      font-family: inherit;
      outline: none;
      transition: border 0.2s;
    }
    .field-wrap input:focus { border-color: var(--primary); }
    .field-wrap input::placeholder { color: var(--muted); }
    .field-wrap .eye {
      position: absolute; right: 14px; top: 50%;
      transform: translateY(-50%);
      color: var(--muted); cursor: pointer;
      pointer-events: all;
    }

    .btn-login {
      width: 100%;
      padding: 15px;
      background: var(--primary);
      color: #0f1117;
      font-family: inherit;
      font-size: 15px;
      font-weight: 800;
      border: none;
      border-radius: 12px;
      cursor: pointer;
      margin-top: 10px;
      transition: background 0.2s, transform 0.1s;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
    }
    .btn-login:hover    { background: #d4a900; }
    .btn-login:active   { transform: scale(0.98); }
    .btn-login:disabled { opacity: 0.5; cursor: not-allowed; }

    .login-error {
      margin-top: 14px;
      padding: 12px 14px;
      background: rgba(239,68,68,0.1);
      border: 1px solid rgba(239,68,68,0.25);
      border-radius: 10px;
      color: #fca5a5;
      font-size: 13px;
      font-weight: 600;
      display: none;
    }
    .login-error.show { display: block; }

    .checker-hint {
      margin-top: 28px;
      padding: 14px;
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 12px;
      text-align: center;
      font-size: 12.5px;
      color: var(--muted);
    }
    .checker-hint a { color: var(--accent); font-weight: 700; text-decoration: none; }
    .checker-hint a:hover { text-decoration: underline; }

    /* Responsive */
    @media (max-width: 768px) {
      body { grid-template-columns: 1fr; }
      .hero {
        padding: 28px 24px;
        border-right: none;
        border-bottom: 1px solid var(--border);
        min-height: auto;
      }
      .hero-body h1 { font-size: 1.7rem; }
      .hero-body .hero-checker-link { margin-top: 18px; }
      .form-side { padding: 32px 20px; }
    }
  </style>
</head>
<body>

<!-- HERO LEFT -->
<div class="hero">
  <div class="hero-brand">
    <img src="./src/css/imagen/Logo-Pilar.jpg" alt="Logo PILAR" class="hero-logo">
    <div>
      <div class="hero-brand-name">PILAR</div>
    </div>
  </div>

  <div class="hero-body">
    <h1>Control de <span>asistencia</span> y nómina</h1>
    <p>Gestiona checadas, horas trabajadas, catorcenas y personal desde un solo panel seguro.</p>
    <a href="/checador.php" class="hero-checker-link">
      <i class="fas fa-mobile-screen-button"></i>
      Checador de empleados — acceso libre
    </a>
  </div>

  <div class="hero-footer">
    <div class="hero-pills">
      <span class="pill">NSS como ID</span>
      <span class="pill">Catorcenas 90h</span>
      <span class="pill">Horas extra</span>
      <span class="pill">Exporta Excel</span>
      <span class="pill">Multi-planta</span>
    </div>
  </div>
</div>

<!-- FORM RIGHT -->
<div class="form-side">
  <div class="form-box">
    <h2>Acceso administrativo</h2>
    <p class="sub">Ingresa tus credenciales de gerente / RRHH</p>

    <div class="field">
      <label>Usuario</label>
      <div class="field-wrap">
        <i class="fas fa-user"></i>
        <input type="text" id="username" placeholder="tu.usuario" autocomplete="username">
      </div>
    </div>

    <div class="field">
      <label>Contraseña</label>
      <div class="field-wrap">
        <i class="fas fa-lock"></i>
        <input type="password" id="password" placeholder="••••••••" autocomplete="current-password">
        <i class="fas fa-eye eye" id="toggleEye"></i>
      </div>
    </div>

    <button class="btn-login" id="btnLogin">
      <i class="fas fa-sign-in-alt"></i> Iniciar sesión
    </button>

    <div class="login-error" id="loginError"></div>

    <div class="checker-hint">
      ¿Eres empleado? <a href="/checador.php"><i class="fas fa-clock"></i> Ve al checador</a>
    </div>
  </div>
</div>

<script>
  // Toggle contraseña
  document.getElementById('toggleEye').addEventListener('click', () => {
    const inp = document.getElementById('password');
    const ico = document.getElementById('toggleEye');
    inp.type = inp.type === 'password' ? 'text' : 'password';
    ico.className = 'fas fa-eye' + (inp.type === 'password' ? '' : '-slash') + ' eye';
  });

  // Enter key
  ['username','password'].forEach(id => {
    document.getElementById(id).addEventListener('keydown', e => {
      if (e.key === 'Enter') login();
    });
  });

  async function login() {
    const btn = document.getElementById('btnLogin');
    const err = document.getElementById('loginError');
    err.classList.remove('show');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Verificando...';

    try {
      const res  = await fetch('/src/php/login_handler.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          username: document.getElementById('username').value.trim(),
          password: document.getElementById('password').value
        })
      });
      const data = await res.json();
      if (data.ok) {
        window.location.href = data.redirect || '/dashboard.php';
      } else {
        err.textContent = data.msg || 'Error de acceso.';
        err.classList.add('show');
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-sign-in-alt"></i> Iniciar sesión';
      }
    } catch {
      err.textContent = 'Error de red. Intenta de nuevo.';
      err.classList.add('show');
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-sign-in-alt"></i> Iniciar sesión';
    }
  }

  document.getElementById('btnLogin').addEventListener('click', login);
</script>
</body>
</html>
