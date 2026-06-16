<?php
/**
 * views/auth/login.php
 *
 * Vista de login institucional.
 * Variables disponibles: $expired (bool), $error (string|null)
 */

$config = require ROOT_PATH . '/config/config.php';
?>

<div class="auth-card animate-fade-in-up">

  <!-- Logo y título -->
  <div class="auth-logo">
    <div class="auth-logo-mark">
      <i class="ti ti-building-warehouse" aria-hidden="true"></i>
    </div>
    <div class="auth-logo-title">
      <?= e($config['institucion']['nombre'] ?? 'Almacén Escolar') ?>
    </div>
    <div class="auth-logo-sub">
      <?= e($config['institucion']['area'] ?? 'Sistema de Almacén') ?>
    </div>
  </div>

  <!-- Alerta de sesión expirada -->
  <?php if ($expired ?? false): ?>
    <div class="alert alert-warning" role="alert" style="margin-bottom:1.25rem">
      <i class="ti ti-clock-off" aria-hidden="true"></i>
      <span>Tu sesión expiró por inactividad. Inicia sesión de nuevo.</span>
    </div>
  <?php endif; ?>

  <!-- Alerta de error -->
  <?php if (!empty($error)): ?>
    <div class="alert alert-danger" role="alert" style="margin-bottom:1.25rem">
      <i class="ti ti-alert-circle" aria-hidden="true"></i>
      <span><?= e($error) ?></span>
    </div>
  <?php endif; ?>

  <!-- Descripción -->
  <p style="text-align:center;font-size:var(--font-size-sm);color:var(--text-muted);
            margin-bottom:1.5rem;line-height:1.6">
    Acceso exclusivo para personal institucional autorizado.<br>
    Utiliza tu cuenta de correo institucional.
  </p>

  <!-- Botón Google OAuth -->
  <a href="<?= e($config['app']['url']) ?>/auth/google"
     class="btn-google"
     role="button"
     aria-label="Iniciar sesión con Google">
    <!-- SVG logo de Google (inline, no imagen externa) -->
    <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
      <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
      <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
      <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/>
      <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
    </svg>
    Ingresar con Google
  </a>

  <div class="auth-divider">acceso seguro</div>

  <!-- Indicadores de seguridad -->
  <div style="display:flex;gap:1rem;justify-content:center;flex-wrap:wrap">
    <div style="display:flex;align-items:center;gap:0.375rem;
                font-size:var(--font-size-xs);color:var(--text-muted)">
      <i class="ti ti-shield-check" style="color:var(--color-primary)" aria-hidden="true"></i>
      OAuth 2.0 seguro
    </div>
    <div style="display:flex;align-items:center;gap:0.375rem;
                font-size:var(--font-size-xs);color:var(--text-muted)">
      <i class="ti ti-lock" style="color:var(--color-primary)" aria-hidden="true"></i>
      Sesión cifrada
    </div>
    <div style="display:flex;align-items:center;gap:0.375rem;
                font-size:var(--font-size-xs);color:var(--text-muted)">
      <i class="ti ti-eye-off" style="color:var(--color-primary)" aria-hidden="true"></i>
      Sin contraseñas
    </div>
  </div>

  <!-- Footer -->
  <div class="auth-footer">
    <?= e($config['institucion']['nombre'] ?? '') ?><br>
    Sistema Institucional de Almacén Escolar &copy; <?= date('Y') ?>
  </div>

</div>
