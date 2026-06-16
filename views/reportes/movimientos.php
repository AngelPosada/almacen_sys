<?php
if (!isset($config)) { $config = $GLOBALS['app_config'] ?? require ROOT_PATH . '/config/config.php'; }
/**
 * views/reportes/movimientos.php
 * Variables: $movimientos, $totales, $top_salidas, $grafico, $filtros, $productos, $usuarios
 */

$baseUrl  = e($config['app']['url']);
$graficoJ = json_encode($grafico, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
?>

<div class="page-header">
  <div>
    <h1 class="page-title">Reporte de movimientos</h1>
    <p class="page-subtitle">
      Del <?= e($filtros['fecha_desde']) ?> al <?= e($filtros['fecha_hasta']) ?>
    </p>
  </div>
</div>

<!-- KPIs del período -->
<div style="display:grid;grid-template-columns:repeat(3,1fr) repeat(3,1fr);
            gap:1rem;margin-bottom:1.5rem">
  <?php
  $kpiDatos = [
    ['valor' => $totales['num_movimientos'],    'label' => 'Total movimientos',    'icono' => 'ti-activity',          'tipo' => 'primary'],
    ['valor' => $totales['piezas_entrada'],      'label' => 'Piezas ingresadas',    'icono' => 'ti-arrow-down-left',   'tipo' => 'secondary'],
    ['valor' => $totales['piezas_salida'],       'label' => 'Piezas salidas',       'icono' => 'ti-arrow-up-right',    'tipo' => 'accent'],
    ['valor' => $totales['num_ajustes'],         'label' => 'Ajustes',              'icono' => 'ti-adjustments',       'tipo' => 'primary'],
    ['valor' => $totales['productos_distintos'], 'label' => 'Productos distintos',  'icono' => 'ti-package',           'tipo' => 'secondary'],
    ['valor' => $totales['dias_actividad'],      'label' => 'Días con actividad',   'icono' => 'ti-calendar',          'tipo' => 'primary'],
  ];
  foreach ($kpiDatos as $k):
  ?>
  <div class="stat-card">
    <div class="stat-card-icon <?= e($k['tipo']) ?>">
      <i class="ti <?= e($k['icono']) ?>" style="font-size:1.1rem"></i>
    </div>
    <div class="stat-card-info">
      <div class="stat-card-value" style="font-size:1.5rem"><?= e($k['valor']) ?></div>
      <div class="stat-card-label"><?= e($k['label']) ?></div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Filtros -->
<div class="card" style="margin-bottom:1.25rem">
  <div class="card-body" style="padding:1rem 1.25rem">
    <form method="GET" style="display:flex;gap:.75rem;flex-wrap:wrap;align-items:flex-end">
      <div style="min-width:130px">
        <label class="form-label" for="fTipo">Tipo</label>
        <select id="fTipo" name="tipo" class="form-control">
          <option value="">Todos</option>
          <option value="entrada"    <?= ($filtros['tipo']??'') === 'entrada'    ? 'selected':'' ?>>Entrada</option>
          <option value="salida"     <?= ($filtros['tipo']??'') === 'salida'     ? 'selected':'' ?>>Salida</option>
          <option value="ajuste"     <?= ($filtros['tipo']??'') === 'ajuste'     ? 'selected':'' ?>>Ajuste</option>
        </select>
      </div>
      <div style="min-width:130px">
        <label class="form-label" for="fOrigen">Origen</label>
        <select id="fOrigen" name="origen" class="form-control">
          <option value="">Todos</option>
          <option value="compra"           <?= ($filtros['origen']??'') === 'compra'           ? 'selected':'' ?>>Compra</option>
          <option value="vale_salida"      <?= ($filtros['origen']??'') === 'vale_salida'      ? 'selected':'' ?>>Vale</option>
          <option value="ajuste_manual"    <?= ($filtros['origen']??'') === 'ajuste_manual'    ? 'selected':'' ?>>Ajuste manual</option>
          <option value="inventario_fisico"<?= ($filtros['origen']??'') === 'inventario_fisico'? 'selected':'' ?>>Inv. físico</option>
        </select>
      </div>
      <div style="min-width:130px">
        <label class="form-label" for="fDesde">Desde</label>
        <input type="date" id="fDesde" name="fecha_desde" class="form-control"
               value="<?= e($filtros['fecha_desde'] ?? '') ?>">
      </div>
      <div style="min-width:130px">
        <label class="form-label" for="fHasta">Hasta</label>
        <input type="date" id="fHasta" name="fecha_hasta" class="form-control"
               value="<?= e($filtros['fecha_hasta'] ?? '') ?>">
      </div>
      <div style="display:flex;gap:.5rem">
        <button type="submit" class="btn btn-primary">
          <i class="ti ti-filter"></i> Filtrar
        </button>
        <a href="<?= $baseUrl ?>/reportes/movimientos" class="btn btn-ghost">
          <i class="ti ti-x"></i>
        </a>
      </div>
    </form>
  </div>
</div>

<div class="dashboard-grid" style="margin-bottom:1.25rem">

  <!-- Gráfico de actividad -->
  <div class="card">
    <div class="card-header">
      <div class="card-title">Actividad del período</div>
    </div>
    <div class="card-body">
      <canvas id="graficoMov" height="200"></canvas>
    </div>
  </div>

  <!-- Top 10 salidas -->
  <div class="card">
    <div class="card-header">
      <div class="card-title">Top productos con más salidas</div>
    </div>
    <div class="card-body" style="padding:0">
      <?php if (empty($top_salidas)): ?>
      <div style="padding:2rem;text-align:center;color:var(--text-muted)">
        Sin salidas en el período
      </div>
      <?php else: ?>
      <table class="table" style="width:100%">
        <tbody>
          <?php foreach ($top_salidas as $i => $prod): ?>
          <tr>
            <td style="width:28px;color:var(--text-muted);font-size:var(--font-size-xs)">
              <?= $i + 1 ?>
            </td>
            <td>
              <div style="font-size:var(--font-size-sm);font-weight:500">
                <?= e($prod['nombre']) ?>
              </div>
              <div style="font-size:var(--font-size-xs);color:var(--text-muted)">
                <?= e($prod['codigo']) ?> · <?= (int)$prod['num_movimientos'] ?> movs.
              </div>
            </td>
            <td style="text-align:right;font-weight:700;
                        color:var(--status-danger-text);font-size:var(--font-size-sm)">
              −<?= e($prod['total_fmt']) ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>
  </div>

</div>

<!-- Tabla de movimientos -->
<div class="card">
  <div class="card-header">
    <div class="card-title">Detalle de movimientos</div>
    <span style="font-size:var(--font-size-xs);color:var(--text-muted)">
      <?= number_format($movimientos['total'] ?? 0) ?> registros
    </span>
  </div>
  <div class="card-body" style="padding:0">
    <div class="table-container" style="border:none;border-radius:0">
      <table id="tablaMov" class="table" style="width:100%">
        <thead>
          <tr>
            <th style="width:36px"></th>
            <th>Producto</th>
            <th>Cantidad</th>
            <th>Anterior → Posterior</th>
            <th>Origen</th>
            <th>Usuario</th>
            <th>Fecha</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($movimientos['items'] as $m): ?>
          <tr>
            <td style="text-align:center;padding:.5rem">
              <div style="width:28px;height:28px;border-radius:50%;
                          background:var(--status-<?= e($m['tipo_clase']) ?>-bg);
                          display:flex;align-items:center;justify-content:center;margin:0 auto">
                <i class="ti ti-<?= $m['es_salida'] ? 'arrow-up-right':'arrow-down-left' ?>"
                   style="font-size:.8rem;color:var(--status-<?= e($m['tipo_clase']) ?>-text)">
                </i>
              </div>
            </td>
            <td>
              <div style="font-size:var(--font-size-sm);font-weight:500">
                <?= e($m['producto_nombre']) ?>
              </div>
              <div style="font-size:var(--font-size-xs);color:var(--text-muted)">
                <?= e($m['producto_codigo']) ?>
              </div>
            </td>
            <td>
              <span style="font-weight:700;
                           color:var(--status-<?= e($m['tipo_clase']) ?>-text)">
                <?= e($m['signo']) ?><?= e($m['cantidad_texto']) ?>
              </span>
            </td>
            <td style="font-size:var(--font-size-xs);color:var(--text-muted)">
              <?= e($m['anterior_texto']) ?> → <?= e($m['posterior_texto']) ?>
            </td>
            <td>
              <span class="badge badge-muted"><?= e($m['origen_label']) ?></span>
            </td>
            <td style="font-size:var(--font-size-sm);color:var(--text-muted)">
              <?= e($m['usuario_nombre']) ?>
            </td>
            <td style="font-size:var(--font-size-sm);white-space:nowrap">
              <?= e($m['fecha_fmt']) ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Paginación -->
  <?php if (($movimientos['paginas'] ?? 0) > 1): ?>
  <div class="card-footer" style="display:flex;align-items:center;justify-content:space-between">
    <div style="font-size:var(--font-size-sm);color:var(--text-muted)">
      <?= number_format($movimientos['total']) ?> movimientos
    </div>
    <div style="display:flex;gap:.375rem">
      <?php for ($i = 1; $i <= $movimientos['paginas']; $i++): ?>
      <?php $qs = http_build_query(array_merge($filtros, ['pagina' => $i])); ?>
      <a href="?<?= $qs ?>"
         class="btn btn-sm <?= $i === $movimientos['pagina_actual'] ? 'btn-primary':'btn-ghost' ?>">
        <?= $i ?>
      </a>
      <?php endfor; ?>
    </div>
  </div>
  <?php endif; ?>
</div>

<?php
ob_start();
?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
(function () {
const G   = <?= $graficoJ ?>;
const dark = document.documentElement.dataset.theme === 'dark';
const gc   = dark ? 'rgba(255,255,255,.07)' : 'rgba(0,0,0,.06)';
const lc   = dark ? '#a0c4b4' : '#4a6358';

new Chart(document.getElementById('graficoMov').getContext('2d'), {
  type: 'bar',
  data: {
    labels:   G.labels,
    datasets: [
      { label:'Entradas', data: G.entradas, backgroundColor:'rgba(14,115,78,.65)',
        borderRadius:3 },
      { label:'Salidas',  data: G.salidas,  backgroundColor:'rgba(242,129,29,.65)',
        borderRadius:3 },
    ],
  },
  options: {
    responsive:true, maintainAspectRatio:false,
    interaction: { mode:'index', intersect:false },
    plugins: { legend: { position:'bottom', labels:{ color:lc, boxWidth:12, font:{size:11} } } },
    scales: {
      x: { grid:{color:gc}, ticks:{color:lc, font:{size:10}, maxRotation:45} },
      y: { beginAtZero:true, grid:{color:gc}, ticks:{color:lc, font:{size:11}, precision:0} },
    },
  },
});

$('#tablaMov').DataTable({
  language: { url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json' },
  pageLength:50, order:[[6,'desc']],
  columnDefs:[{ orderable:false, targets:[0] }],
  dom:'tp',
});
})();
</script>
<?php $extraJs = ob_get_clean(); ?>
