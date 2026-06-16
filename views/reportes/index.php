<?php
if (!isset($config)) { $config = $GLOBALS['app_config'] ?? require ROOT_PATH . '/config/config.php'; }
/**
 * views/reportes/index.php
 * Variables: $kpis, $valor_total, $categorias
 */

$baseUrl = e($config['app']['url']);
$rol     = (int) ($_SESSION['usuario_rol'] ?? 3);

$secciones = [
    [
        'url'    => 'reportes/inventario',
        'titulo' => 'Inventario valorizado',
        'desc'   => 'Stock actual, valor por producto y alertas de nivel mínimo.',
        'icono'  => 'ti-package',
        'color'  => 'primary',
    ],
    [
        'url'    => 'reportes/movimientos',
        'titulo' => 'Movimientos',
        'desc'   => 'Historial de entradas, salidas y ajustes con gráfico de actividad.',
        'icono'  => 'ti-activity',
        'color'  => 'secondary',
    ],
    [
        'url'    => 'reportes/requisiciones',
        'titulo' => 'Requisiciones',
        'desc'   => 'Seguimiento de solicitudes de compra, montos y estados.',
        'icono'  => 'ti-file-text',
        'color'  => 'accent',
    ],
];

// Auditoría solo para Admin y Auditor
if ($rol === 1 || $rol === 4) {
    $secciones[] = [
        'url'    => 'reportes/auditoria',
        'titulo' => 'Bitácora de auditoría',
        'desc'   => 'Registro completo de acciones críticas del sistema.',
        'icono'  => 'ti-shield-check',
        'color'  => 'warning',
    ];
}

$colorMap = [
    'primary'   => ['bg' => 'var(--color-primary-light)',  'text' => 'var(--color-primary)'],
    'secondary' => ['bg' => 'var(--color-secondary-light)','text' => 'var(--color-secondary)'],
    'accent'    => ['bg' => 'var(--color-accent-light)',   'text' => 'var(--color-accent)'],
    'warning'   => ['bg' => 'var(--status-warning-bg)',    'text' => 'var(--status-warning-text)'],
];
?>

<div class="page-header">
  <div>
    <h1 class="page-title">Reportes</h1>
    <p class="page-subtitle">Análisis y exportación de datos del sistema</p>
  </div>
</div>

<!-- KPIs rápidos -->
<div class="stats-grid" style="margin-bottom:1.75rem">
  <div class="stat-card">
    <div class="stat-card-icon primary">
      <i class="ti ti-package" style="font-size:1.25rem"></i>
    </div>
    <div class="stat-card-info">
      <div class="stat-card-value"><?= number_format((int)($kpis['total_productos'] ?? 0)) ?></div>
      <div class="stat-card-label">Productos activos</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-card-icon secondary">
      <i class="ti ti-currency-dollar" style="font-size:1.25rem"></i>
    </div>
    <div class="stat-card-info">
      <div class="stat-card-value" style="font-size:1.5rem"><?= e($valor_total) ?></div>
      <div class="stat-card-label">Valor del inventario</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-card-icon <?= (int)($kpis['productos_stock_critico'] ?? 0) > 0 ? 'accent' : 'primary' ?>">
      <i class="ti ti-alert-triangle" style="font-size:1.25rem"></i>
    </div>
    <div class="stat-card-info">
      <div class="stat-card-value"><?= number_format((int)($kpis['productos_stock_critico'] ?? 0)) ?></div>
      <div class="stat-card-label">Productos en alerta</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-card-icon primary">
      <i class="ti ti-activity" style="font-size:1.25rem"></i>
    </div>
    <div class="stat-card-info">
      <div class="stat-card-value"><?= number_format((int)($kpis['movimientos_mes'] ?? 0)) ?></div>
      <div class="stat-card-label">Movimientos este mes</div>
    </div>
  </div>
</div>

<!-- Tarjetas de secciones -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));
            gap:1.25rem;margin-bottom:1.75rem">
  <?php foreach ($secciones as $s):
    $c = $colorMap[$s['color']] ?? $colorMap['primary'];
  ?>
  <a href="<?= $baseUrl ?>/<?= e($s['url']) ?>"
     class="card"
     style="text-decoration:none;cursor:pointer;transition:box-shadow var(--transition),transform var(--transition)"
     onmouseover="this.style.transform='translateY(-3px)';this.style.boxShadow='var(--shadow-md)'"
     onmouseout="this.style.transform='';this.style.boxShadow=''">
    <div class="card-body" style="display:flex;align-items:flex-start;gap:1rem;padding:1.5rem">
      <div style="width:48px;height:48px;border-radius:var(--border-radius);
                  background:<?= $c['bg'] ?>;display:flex;align-items:center;
                  justify-content:center;flex-shrink:0">
        <i class="ti <?= e($s['icono']) ?>"
           style="font-size:1.375rem;color:<?= $c['text'] ?>"></i>
      </div>
      <div>
        <div style="font-family:var(--font-display);font-weight:700;
                    font-size:var(--font-size-md);color:var(--text-primary);
                    margin-bottom:.375rem">
          <?= e($s['titulo']) ?>
        </div>
        <div style="font-size:var(--font-size-sm);color:var(--text-muted);line-height:1.5">
          <?= e($s['desc']) ?>
        </div>
      </div>
    </div>
  </a>
  <?php endforeach; ?>
</div>

<!-- Top categorías por valor -->
<?php if (!empty($categorias)): ?>
<div class="card">
  <div class="card-header">
    <div class="card-title">Top categorías por valor de inventario</div>
    <a href="<?= $baseUrl ?>/reportes/inventario" class="btn btn-ghost btn-sm">
      Ver completo
    </a>
  </div>
  <div class="card-body" style="padding:0">
    <table class="table">
      <thead>
        <tr>
          <th>Categoría</th>
          <th style="text-align:center">Productos</th>
          <th style="text-align:center">En alerta</th>
          <th style="text-align:right">Valor total</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($categorias as $cat): ?>
        <tr>
          <td style="font-weight:500;font-size:var(--font-size-sm)">
            <?= e($cat['categoria']) ?>
          </td>
          <td style="text-align:center;font-size:var(--font-size-sm)">
            <?= number_format((int)$cat['total_productos']) ?>
          </td>
          <td style="text-align:center">
            <?php if ((int)$cat['productos_criticos'] > 0): ?>
            <span class="badge badge-danger"><?= (int)$cat['productos_criticos'] ?></span>
            <?php else: ?>
            <span style="color:var(--text-muted);font-size:var(--font-size-xs)">—</span>
            <?php endif; ?>
          </td>
          <td style="text-align:right;font-weight:600;color:var(--color-primary);
                      font-size:var(--font-size-sm)">
            <?= e($cat['valor_fmt']) ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>
