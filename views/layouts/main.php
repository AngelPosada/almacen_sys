<?php if (!isset($config)) { $config = $GLOBALS['app_config'] ?? require ROOT_PATH . '/config/config.php'; } ?>
<!DOCTYPE html>
<html lang="es" data-theme="<?= ($_SESSION['usuario_rol'] ?? 3) === 1 ? 'dark' : 'light' ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($pageTitle ?? 'Sistema de Almacén') ?> — <?= e($config['app']['name'] ?? 'Almacén Escolar') ?></title>
  <meta name="robots" content="noindex, nofollow">

  <!-- Google Fonts (carga asíncrona para no bloquear) -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&family=Roboto+Condensed:wght@600;700&display=swap">
  <!-- Tabler Icons -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@3.10.0/dist/tabler-icons.min.css">
  <!-- SweetAlert2 -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
  <!-- DataTables -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/datatables.net-bs5@1.13.6/css/dataTables.bootstrap5.min.css">
  <!-- CSS Global del sistema -->
  <link rel="stylesheet" href="<?= e($config['app']['url']) ?>/assets/css/global.css">

  <?php if (isset($extraCss)): ?>
    <?= $extraCss ?>
  <?php endif; ?>
</head>
<body>
<div class="app-shell">

  <!-- ============================================================
       SIDEBAR
  ============================================================ -->
  <aside class="sidebar" id="sidebar">

    <!-- Logo institucional -->
    <a href="<?= e($config['app']['url']) ?>/dashboard" class="sidebar-logo">
      <div style="
        width:36px;height:36px;background:var(--color-primary);
        border-radius:8px;display:flex;align-items:center;justify-content:center;
        flex-shrink:0;color:#fff;font-size:1.1rem;">
        <i class="ti ti-building-warehouse" aria-hidden="true"></i>
      </div>
      <div class="sidebar-label">
        <div class="sidebar-logo-text">Almacén</div>
        <div class="sidebar-logo-sub">COBACH Durango</div>
      </div>
    </a>

    <!-- Navegación principal -->
    <nav style="flex:1;padding:0.5rem 0;overflow-y:auto">

      <?php
        $uri      = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/';
        $baseUrl  = $config['app']['url'];
        $rolActual = $_SESSION['usuario_rol'] ?? 3;

        // Helper: genera clase 'active' si la URI coincide
        $isActive = function(string $path) use ($uri, $baseUrl): string {
            $basePath = parse_url($baseUrl, PHP_URL_PATH) ?? '';
            $relative = str_replace($basePath, '', $uri);
            return str_starts_with(ltrim($relative, '/'), ltrim($path, '/')) ? ' active' : '';
        };
      ?>

      <!-- Dashboard -->
      <div class="sidebar-section">
        <a href="<?= e($baseUrl) ?>/dashboard"
           class="sidebar-item<?= $isActive('dashboard') ?>">
          <i class="ti ti-layout-dashboard sidebar-icon" aria-hidden="true"></i>
          <span class="sidebar-label">Dashboard</span>
        </a>
      </div>

      <!-- Inventario -->
      <div class="sidebar-section">
        <div class="sidebar-section-title">Inventario</div>
        <a href="<?= e($baseUrl) ?>/productos"
           class="sidebar-item<?= $isActive('productos') ?>">
          <i class="ti ti-package sidebar-icon" aria-hidden="true"></i>
          <span class="sidebar-label">Productos</span>
        </a>
        <a href="<?= e($baseUrl) ?>/categorias"
           class="sidebar-item<?= $isActive('categorias') ?>">
          <i class="ti ti-tag sidebar-icon" aria-hidden="true"></i>
          <span class="sidebar-label">Categorías</span>
        </a>
        <a href="<?= e($baseUrl) ?>/inventario"
           class="sidebar-item<?= $isActive('inventario') ?>">
          <i class="ti ti-list-details sidebar-icon" aria-hidden="true"></i>
          <span class="sidebar-label">Movimientos</span>
        </a>
      </div>

      <!-- Operaciones -->
      <div class="sidebar-section">
        <div class="sidebar-section-title">Operaciones</div>
        <a href="<?= e($baseUrl) ?>/pedidos"
           class="sidebar-item<?= $isActive('pedidos') ?>">
          <i class="ti ti-shopping-cart sidebar-icon" aria-hidden="true"></i>
          <span class="sidebar-label">Pedidos</span>
        </a>
        <a href="<?= e($baseUrl) ?>/requisiciones"
           class="sidebar-item<?= $isActive('requisiciones') ?>">
          <i class="ti ti-file-text sidebar-icon" aria-hidden="true"></i>
          <span class="sidebar-label">Requisiciones</span>
        </a>
        <a href="<?= e($baseUrl) ?>/vales"
           class="sidebar-item<?= $isActive('vales') ?>">
          <i class="ti ti-receipt sidebar-icon" aria-hidden="true"></i>
          <span class="sidebar-label">Vales</span>
        </a>
      </div>

      <!-- Reportes -->
      <div class="sidebar-section">
        <div class="sidebar-section-title">Reportes</div>
        <a href="<?= e($baseUrl) ?>/reportes"
           class="sidebar-item<?= $isActive('reportes') ?>">
          <i class="ti ti-chart-bar sidebar-icon" aria-hidden="true"></i>
          <span class="sidebar-label">Reportes</span>
        </a>
      </div>

      <!-- Administración (solo Admin) -->
      <?php if ($rolActual === 1): ?>
      <div class="sidebar-section">
        <div class="sidebar-section-title">Administración</div>
        <a href="<?= e($baseUrl) ?>/usuarios"
           class="sidebar-item<?= $isActive('usuarios') ?>">
          <i class="ti ti-users sidebar-icon" aria-hidden="true"></i>
          <span class="sidebar-label">Usuarios</span>
        </a>
        <a href="<?= e($baseUrl) ?>/configuracion"
           class="sidebar-item<?= $isActive('configuracion') ?>">
          <i class="ti ti-settings sidebar-icon" aria-hidden="true"></i>
          <span class="sidebar-label">Configuración</span>
        </a>
      </div>
      <?php endif; ?>

    </nav>

    <!-- Versión del sistema al fondo -->
    <div style="padding:1rem 1.25rem;border-top:1px solid var(--sidebar-border)">
      <div class="sidebar-label" style="font-size:var(--font-size-xs);color:var(--text-muted)">
        v1.0.0 — <?= date('Y') ?>
      </div>
    </div>

  </aside>

  <!-- ============================================================
       NAVBAR
  ============================================================ -->
  <header class="navbar" id="navbar">

    <div class="navbar-left">
      <!-- Toggle sidebar -->
      <button class="btn-sidebar-toggle" id="sidebarToggle" aria-label="Menú">
        <span></span><span></span><span></span>
      </button>

      <!-- Breadcrumb -->
      <?php if (isset($breadcrumb) && is_array($breadcrumb)): ?>
      <nav class="breadcrumb" aria-label="Ruta de navegación">
        <?php foreach ($breadcrumb as $i => $item): ?>
          <?php if ($i < count($breadcrumb) - 1): ?>
            <a href="<?= e($baseUrl . '/' . ltrim($item['url'] ?? '#', '/')) ?>"
               class="breadcrumb-item">
              <?= e($item['label']) ?>
            </a>
          <?php else: ?>
            <span class="breadcrumb-item active"><?= e($item['label']) ?></span>
          <?php endif; ?>
        <?php endforeach; ?>
      </nav>
      <?php endif; ?>
    </div>

    <div class="navbar-right">

      <!-- Indicador de rol -->
      <span class="badge badge-<?= $rolActual === 1 ? 'success' : ($rolActual === 2 ? 'info' : 'muted') ?>"
            style="font-size:0.625rem">
        <?= e($_SESSION['usuario_rol_nombre'] ?? 'Usuario') ?>
      </span>

      <!-- Menú de usuario -->
      <div class="navbar-user" id="navbarUser">
        <button class="navbar-user-btn" id="userMenuBtn" aria-expanded="false"
                aria-haspopup="true" type="button">
          <?php if (!empty($_SESSION['usuario_avatar'])): ?>
            <img src="<?= e($_SESSION['usuario_avatar']) ?>"
                 alt="Avatar" class="navbar-avatar">
          <?php else: ?>
            <div style="width:36px;height:36px;border-radius:50%;
                        background:var(--color-primary-light);
                        display:flex;align-items:center;justify-content:center;
                        color:var(--color-primary);font-weight:700;font-size:0.875rem">
              <?= strtoupper(substr($_SESSION['usuario_nombre'] ?? 'U', 0, 1)) ?>
            </div>
          <?php endif; ?>
          <div style="text-align:left;display:none" class="navbar-user-info">
            <span class="navbar-user-name">
              <?= e($_SESSION['usuario_nombre'] ?? '') ?>
            </span>
            <span class="navbar-user-role">
              <?= e($_SESSION['usuario_email'] ?? '') ?>
            </span>
          </div>
          <i class="ti ti-chevron-down" style="font-size:14px;color:var(--text-muted)" aria-hidden="true"></i>
        </button>

        <!-- Dropdown -->
        <div id="userDropdown" role="menu"
             style="display:none;position:absolute;top:calc(100% + 8px);right:0;
                    min-width:200px;background:var(--bg-surface);
                    border:1px solid var(--border-color);border-radius:var(--border-radius-lg);
                    box-shadow:var(--shadow-md);padding:0.5rem;z-index:var(--z-dropdown)">
          <div style="padding:0.625rem 0.75rem;border-bottom:1px solid var(--border-color);margin-bottom:0.375rem">
            <div style="font-weight:500;font-size:var(--font-size-sm);color:var(--text-primary)">
              <?= e($_SESSION['usuario_nombre'] ?? '') ?>
            </div>
            <div style="font-size:var(--font-size-xs);color:var(--text-muted)">
              <?= e($_SESSION['usuario_email'] ?? '') ?>
            </div>
          </div>
          <form method="POST" action="<?= e($baseUrl) ?>/auth/logout">
            <?= Security::csrfField() ?>
            <button type="submit" role="menuitem"
                    style="width:100%;text-align:left;background:none;border:none;
                           cursor:pointer;padding:0.5rem 0.75rem;
                           border-radius:var(--border-radius-sm);
                           font-size:var(--font-size-sm);color:var(--status-danger-text);
                           display:flex;align-items:center;gap:0.5rem;
                           transition:background-color var(--transition)"
                    onmouseover="this.style.backgroundColor='var(--status-danger-bg)'"
                    onmouseout="this.style.backgroundColor='transparent'">
              <i class="ti ti-logout" aria-hidden="true"></i> Cerrar sesión
            </button>
          </form>
        </div>
      </div>

    </div>
  </header>

  <!-- ============================================================
       CONTENIDO PRINCIPAL
  ============================================================ -->
  <main class="main-content" id="mainContent">
    <div class="page-wrapper animate-fade-in">
      <?= $content ?>
    </div>
  </main>

</div><!-- /.app-shell -->

<!-- Overlay para sidebar móvil -->
<div id="sidebarOverlay"
     style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.4);
            z-index:199;backdrop-filter:blur(2px)"
     onclick="closeSidebar()"></div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/datatables.net@1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/datatables.net-bs5@1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.js"></script>

<script>
// ============================================================
// SIDEBAR TOGGLE
// ============================================================
const sidebar      = document.getElementById('sidebar');
const navbar       = document.getElementById('navbar');
const mainContent  = document.getElementById('mainContent');
const overlay      = document.getElementById('sidebarOverlay');
const userBtn      = document.getElementById('userMenuBtn');
const userDropdown = document.getElementById('userDropdown');

function openSidebar() {
  sidebar.classList.add('mobile-open');
  overlay.style.display = 'block';
}
function closeSidebar() {
  sidebar.classList.remove('mobile-open');
  overlay.style.display = 'none';
}
function toggleSidebar() {
  if (window.innerWidth <= 768) {
    sidebar.classList.contains('mobile-open') ? closeSidebar() : openSidebar();
    return;
  }
  const collapsed = sidebar.classList.toggle('collapsed');
  navbar.classList.toggle('sidebar-collapsed', collapsed);
  mainContent.classList.toggle('sidebar-collapsed', collapsed);
  localStorage.setItem('sidebarCollapsed', collapsed ? '1' : '0');
}

document.getElementById('sidebarToggle').addEventListener('click', toggleSidebar);

// Restaurar estado del sidebar desde localStorage
if (window.innerWidth > 768 && localStorage.getItem('sidebarCollapsed') === '1') {
  sidebar.classList.add('collapsed');
  navbar.classList.add('sidebar-collapsed');
  mainContent.classList.add('sidebar-collapsed');
}

// ============================================================
// DROPDOWN DE USUARIO
// ============================================================
userBtn.addEventListener('click', (e) => {
  e.stopPropagation();
  const isOpen = userDropdown.style.display === 'block';
  userDropdown.style.display = isOpen ? 'none' : 'block';
  userBtn.setAttribute('aria-expanded', String(!isOpen));
});
document.addEventListener('click', () => {
  userDropdown.style.display = 'none';
  userBtn.setAttribute('aria-expanded', 'false');
});

// ============================================================
// CSRF TOKEN GLOBAL PARA AJAX
// ============================================================
window.CSRF_TOKEN = '<?= Security::csrfToken() ?>';
$.ajaxSetup({
  headers: { 'X-CSRF-TOKEN': window.CSRF_TOKEN }
});

// ============================================================
// SWEETALERT2 — TEMA INSTITUCIONAL
// ============================================================
const SwalInstitucional = Swal.mixin({
  confirmButtonColor: '#0E734E',
  cancelButtonColor:  '#6c757d',
  customClass: { popup: 'swal-institucional' }
});
window.SwalInst = SwalInstitucional;
</script>

<?php if (isset($extraJs)): ?>
  <?= $extraJs ?>
<?php endif; ?>

</body>
</html>
