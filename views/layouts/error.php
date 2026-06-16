<!DOCTYPE html>
<html lang="es" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Error <?= (int) ($code ?? 500) ?> — Almacén Escolar</title>
  <link rel="stylesheet" href="/almacen/assets/css/global.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@3.10.0/dist/tabler-icons.min.css">
  <style>
    body {
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      background-color: var(--bg-app);
    }
    .error-box {
      text-align: center;
      max-width: 480px;
      padding: 3rem 2rem;
    }
    .error-code {
      font-family: var(--font-display);
      font-size: 6rem;
      font-weight: 700;
      color: var(--color-primary);
      line-height: 1;
      opacity: 0.18;
    }
    .error-icon {
      font-size: 3.5rem;
      color: var(--color-accent);
      margin: 1rem 0;
    }
    .error-title {
      font-family: var(--font-display);
      font-size: var(--font-size-2xl);
      font-weight: 700;
      color: var(--text-primary);
      margin-bottom: 0.75rem;
    }
    .error-message {
      color: var(--text-muted);
      margin-bottom: 2rem;
      line-height: 1.7;
    }
  </style>
</head>
<body>
  <div class="error-box animate-fade-in-up">
    <div class="error-code"><?= (int) ($code ?? 500) ?></div>
    <?php
      $icon = match((int)($code ?? 500)) {
        403 => 'ti-lock',
        404 => 'ti-map-search',
        default => 'ti-alert-triangle',
      };
    ?>
    <div class="error-icon"><i class="ti <?= $icon ?>"></i></div>
    <div class="error-title"><?= e($title ?? 'Error') ?></div>
    <p class="error-message"><?= e($message ?? 'Ha ocurrido un error inesperado.') ?></p>
    <?php if (isset($_SESSION['usuario_id'])): ?>
      <a href="/almacen/dashboard" class="btn btn-primary">
        <i class="ti ti-home"></i> Ir al Dashboard
      </a>
    <?php else: ?>
      <a href="/almacen/auth/login" class="btn btn-primary">
        <i class="ti ti-login"></i> Iniciar sesión
      </a>
    <?php endif; ?>
  </div>
</body>
</html>
