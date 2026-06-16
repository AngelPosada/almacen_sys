<?php
if (!isset($config)) { $config = $GLOBALS['app_config'] ?? require ROOT_PATH . '/config/config.php'; }
/**
 * views/reportes/requisiciones.php
 * Variables: $lista, $kpis, $filtros
 */

$baseUrl = e($config['app']['url']);
?>

<div class="page-header">
  <div>
    <h1 class="page-title">Reporte de requisiciones</h1>
    <p class="page-subtitle">
      Del <?= e($filtros['fecha_desde']) ?> al <?= e($filtros['fecha_hasta']) ?>
    </p>
  </div>
</div>

<!-- KPIs -->
<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:1rem;margin-bottom:1.5rem">
  <?php
  $kpiDef = [
    ['k'=>'total',          'l'=>'Requisiciones',       'i'=>'ti-file-text',     'c'=>'primary'],
    ['k'=>'en_proceso',     'l'=>'En proceso',           'i'=>'ti-clock',         'c'=>'secondary'],
    ['k'=>'autorizadas',    'l'=>'Autorizadas',          'i'=>'ti-shield-check',  'c'=>'primary'],
    ['k'=>'monto_total',    'l'=>'Monto total',          'i'=>'ti-currency-dollar','c'=>'accent'],
  ];
  foreach ($kpiDef as $kd):
  ?>
  <div class="stat-card">
    <div class="stat-card-icon <?= e($kd['c']) ?>">
      <i class="ti <?= e($kd['i']) ?>" style="font-size:1.1rem"></i>
    </div>
    <div class="stat-card-info">
      <div class="stat-card-value" style="font-size:1.5rem">
        <?= e($kpis[$kd['k']] ?? '0') ?>
      </div>
      <div class="stat-card-label"><?= e($kd['l']) ?></div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Fila secundaria de KPIs -->
<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:1rem;margin-bottom:1.5rem">
  <?php
  $kpiDef2 = [
    ['k'=>'rechazadas',    'l'=>'Rechazadas',         'i'=>'ti-x',            'c'=>'accent'],
    ['k'=>'compradas',     'l'=>'Compradas/Cerradas', 'i'=>'ti-shopping-bag', 'c'=>'secondary'],
    ['k'=>'con_cotizacion','l'=>'Req. > $25,000',     'i'=>'ti-receipt-2',    'c'=>'warning'],
    ['k'=>'monto_promedio','l'=>'Monto promedio',      'i'=>'ti-math-avg',     'c'=>'primary'],
  ];
  foreach ($kpiDef2 as $kd):
  ?>
  <div class="stat-card">
    <div class="stat-card-icon <?= e($kd['c']) ?>">
      <i class="ti <?= e($kd['i']) ?>" style="font-size:1.1rem"></i>
    </div>
    <div class="stat-card-info">
      <div class="stat-card-value" style="font-size:1.5rem">
        <?= e($kpis[$kd['k']] ?? '0') ?>
      </div>
      <div class="stat-card-label"><?= e($kd['l']) ?></div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Filtros de fecha -->
<div class="card" style="margin-bottom:1.25rem">
  <div class="card-body" style="padding:1rem 1.25rem">
    <form method="GET" style="display:flex;gap:.75rem;align-items:flex-end;flex-wrap:wrap">
      <div style="min-width:150px">
        <label class="form-label" for="fDesde">Desde</label>
        <input type="date" id="fDesde" name="fecha_desde" class="form-control"
               value="<?= e($filtros['fecha_desde'] ?? '') ?>">
      </div>
      <div style="min-width:150px">
        <label class="form-label" for="fHasta">Hasta</label>
        <input type="date" id="fHasta" name="fecha_hasta" class="form-control"
               value="<?= e($filtros['fecha_hasta'] ?? '') ?>">
      </div>
      <button type="submit" class="btn btn-primary">
        <i class="ti ti-filter"></i> Filtrar
      </button>
      <a href="<?= $baseUrl ?>/reportes/requisiciones" class="btn btn-ghost">
        <i class="ti ti-x"></i>
      </a>
    </form>
  </div>
</div>

<!-- Tabla de requisiciones -->
<div class="card">
  <div class="card-header">
    <div class="card-title">Requisiciones del período</div>
    <span style="font-size:var(--font-size-xs);color:var(--text-muted)">
      <?= count($lista) ?> registros
    </span>
  </div>
  <div class="card-body" style="padding:0">
    <?php if (empty($lista)): ?>
    <div style="padding:3rem;text-align:center;color:var(--text-muted)">
      <i class="ti ti-file-off" style="font-size:2rem;display:block;margin-bottom:.5rem"></i>
      Sin requisiciones en el período
    </div>
    <?php else: ?>
    <div class="table-container" style="border:none;border-radius:0">
      <table id="tablaReqRep" class="table" style="width:100%">
        <thead>
          <tr>
            <th>Folio</th>
            <th>Plantel / Área</th>
            <th>Solicita</th>
            <th style="text-align:center">Conceptos</th>
            <th style="text-align:right">Total estimado</th>
            <th>Fecha</th>
            <th style="text-align:center">Estado</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($lista as $r): ?>
          <tr>
            <td>
              <a href="<?= $baseUrl ?>/requisiciones/<?= (int)$r['id'] ?>"
                 style="font-family:monospace;font-weight:700;
                        color:var(--color-primary);text-decoration:none;
                        font-size:var(--font-size-sm)">
                <?= e($r['folio']) ?>
              </a>
              <?php if ($r['cotizaciones_requeridas']): ?>
              <span class="badge badge-warning" style="font-size:.6rem;display:block;margin-top:2px">
                3 cotiz.
              </span>
              <?php endif; ?>
            </td>
            <td style="font-size:var(--font-size-sm)"><?= e($r['plantel']) ?></td>
            <td style="font-size:var(--font-size-sm);color:var(--text-muted)">
              <?= e($r['solicita_nombre']) ?>
            </td>
            <td style="text-align:center;font-size:var(--font-size-sm)">
              <?= (int)$r['num_items'] ?>
            </td>
            <td style="text-align:right;font-weight:700;
                        color:var(--color-primary);font-size:var(--font-size-sm)">
              <?= e($r['total_fmt']) ?>
            </td>
            <td style="font-size:var(--font-size-sm)"><?= e($r['fecha_fmt']) ?></td>
            <td style="text-align:center">
              <span class="badge <?= e($r['estado_clase']) ?>">
                <?= e($r['estado_label']) ?>
              </span>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php
ob_start();
?>
<script>
$('#tablaReqRep').DataTable({
  language: { url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json' },
  pageLength:25, order:[[0,'desc']],
  columnDefs:[{ orderable:false, targets:[6] }],
});
</script>
<?php $extraJs = ob_get_clean(); ?>
