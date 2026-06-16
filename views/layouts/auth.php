<?php if (!isset($config)) { $config = $GLOBALS['app_config'] ?? require ROOT_PATH . '/config/config.php'; } ?>
<!DOCTYPE html>
<html lang="es" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Acceso al Sistema — <?= e($config['app']['name'] ?? 'Almacén Escolar') ?></title>
  <meta name="robots" content="noindex, nofollow">

  <!-- Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&family=Roboto+Condensed:wght@600;700&display=swap">
  <!-- Tabler Icons -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@3.10.0/dist/tabler-icons.min.css">
  <!-- CSS Global del sistema -->
  <link rel="stylesheet" href="<?= e($config['app']['url']) ?>/assets/css/global.css">

  <style>
    /* Layout exclusivo del auth — no va en global.css */
    body {
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      background-color: var(--bg-app);
      background-image:
        radial-gradient(circle at 15% 50%, rgba(14,115,78,.07) 0%, transparent 50%),
        radial-gradient(circle at 85% 30%, rgba(104,166,137,.06) 0%, transparent 50%);
    }
    .auth-card {
      width: 100%;
      max-width: 420px;
      padding: 2.5rem 2rem;
      background-color: var(--bg-surface);
      border-radius: var(--border-radius-xl);
      border: 1px solid var(--border-color);
      box-shadow: var(--shadow-lg);
    }
    .auth-logo {
      display: flex;
      flex-direction: column;
      align-items: center;
      margin-bottom: 2rem;
      gap: 0.625rem;
    }
    .auth-logo-mark {
      width: 60px;
      height: 60px;
      background-color: var(--color-primary);
      border-radius: var(--border-radius-lg);
      display: flex;
      align-items: center;
      justify-content: center;
      color: #fff;
      font-size: 1.75rem;
    }
    .auth-logo-title {
      font-family: var(--font-display);
      font-size: var(--font-size-xl);
      font-weight: 700;
      color: var(--text-primary);
      text-align: center;
    }
    .auth-logo-sub {
      font-size: var(--font-size-xs);
      color: var(--text-muted);
      text-align: center;
      line-height: 1.4;
      max-width: 260px;
    }
    .auth-divider {
      display: flex;
      align-items: center;
      gap: 0.75rem;
      margin: 1.5rem 0;
      color: var(--text-muted);
      font-size: var(--font-size-xs);
    }
    .auth-divider::before,
    .auth-divider::after {
      content: '';
      flex: 1;
      height: 1px;
      background-color: var(--border-color);
    }
    .btn-google {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 0.75rem;
      width: 100%;
      padding: 0.75rem 1.25rem;
      background-color: var(--bg-surface);
      border: 1.5px solid var(--border-color);
      border-radius: var(--border-radius-sm);
      font-size: var(--font-size-base);
      font-weight: 500;
      color: var(--text-primary);
      text-decoration: none;
      transition: background-color var(--transition), box-shadow var(--transition), border-color var(--transition);
      cursor: pointer;
      font-family: var(--font-family);
    }
    .btn-google:hover {
      background-color: var(--bg-surface-2);
      border-color: var(--color-secondary);
      box-shadow: var(--shadow-sm);
      color: var(--text-primary);
    }
    .btn-google svg { flex-shrink: 0; width: 20px; height: 20px; }
    .auth-footer {
      margin-top: 2rem;
      text-align: center;
      font-size: var(--font-size-xs);
      color: var(--text-muted);
      line-height: 1.6;
    }
    @media (max-width: 480px) {
      .auth-card { padding: 2rem 1.25rem; border-radius: var(--border-radius-lg); }
    }
  </style>
</head>
<body>
  <?= $content ?>
</body>
</html>
