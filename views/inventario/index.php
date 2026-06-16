<?php
if (!isset($config)) { $config = $GLOBALS['app_config'] ?? require ROOT_PATH . '/config/config.php'; }
/**
 * views/inventario/index.php
 *
 * Historial completo de movimientos con filtros.
 * Variables: $totales, $movimientos, $paginacion, $filtros
 */
?>

<!-- Encabezado -->
<div class="page-header">
  <div>
    <h1 class="page-title">Movimientos de inventario</h1>
    <p class="page-subtitle">Historial completo de entradas, salidas y ajustes</p>
  </div>
  <?php if (in_array($_SESSION['usuario_rol'], [1, 2])): ?>
  <div style="display:flex;gap:.625rem">
    <a href="<?= e($config['app']['url']) ?>/inventario/entradas"
       class="btn btn-primary">
      <i class="ti ti-arrow-down-left" aria-hidden="true"></i> Entrada
    </a>
    <a href="<?= e($config['app']['url']) ?>/inventario/salidas"
       class="btn btn-outline-primary">
      <i class="ti ti-arrow-up-right" aria-hidden="true"></i> Salida
    </a>
  </div>
  <?php endif; ?>
</div>

<!-- KPIs del día -->
<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:1rem;margin-bottom:1.5rem">

  <div class="stat-card">
    <div class="stat-card-icon primary">
      <i class="ti ti-arrow-down-left" style="font-size:1.25rem" aria-hidden="true"></i>
    </div>
    <div class="stat-card-info">
      <div class="stat-card-value"><?= number_format($totales['num_entradas']) ?></div>
      <div class="stat-card-label">Entradas hoy</div>
    </div>
  </div>

  <div class="stat-card">
    <div class="stat-card-icon accent">
      <i class="ti ti-arrow-up-right" style="font-size:1.25rem" aria-hidden="true"></i>
    </div>
    <div class="stat-card-info">
      <div class="stat-card-value"><?= number_format($totales['num_salidas']) ?></div>
      <div class="stat-card-label">Salidas hoy</div>
    </div>
  </div>

  <div class="stat-card">
    <div class="stat-card-icon secondary">
      <i class="ti ti-package-import" style="font-size:1.25rem" aria-hidden="true"></i>
    </div>
    <div class="stat-card-info">
      <div class="stat-card-value"><?= number_format($totales['piezas_entradas']) ?></div>
      <div class="stat-card-label">Piezas ingresadas hoy</div>
    </div>
  </div>

  <div class="stat-card">
    <div class="stat-card-icon warning">
      <i class="ti ti-package-export" style="font-size:1.25rem" aria-hidden="true"></i>
    </div>
    <div class="stat-card-info">
      <div class="stat-card-value"><?= number_format($totales['piezas_salidas']) ?></div>
      <div class="stat-card-label">Piezas salidas hoy</div>
    </div>
  </div>

</div>

<!-- Filtros -->
<div class="card" style="margin-bottom:1.25rem">
  <div class="card-body" style="padding:1rem 1.25rem">
    <form method="GET" style="display:flex;gap:.75rem;flex-wrap:wrap;align-items:flex-end">
      <div style="flex:1;min-width:160px">
        <label class="form-label" for="filTipo">Tipo</label>
        <select id="filTipo" name="tipo" class="form-control">
          <option value="">Todos</option>
          <option value="entrada"    <?= ($filtros['tipo']??'') === 'entrada'    ? 'selected':'' ?>>Entrada</option>
          <option value="salida"     <?= ($filtros['tipo']??'') === 'salida'     ? 'selected':'' ?>>Salida</option>
          <option value="ajuste"     <?= ($filtros['tipo']??'') === 'ajuste'     ? 'selected':'' ?>>Ajuste</option>
          <option value="devolucion" <?= ($filtros['tipo']??'') === 'devolucion' ? 'selected':'' ?>>Devolución</option>
        </select>
      </div>
      <div style="flex:1;min-width:160px">
        <label class="form-label" for="filOrigen">Origen</label>
        <select id="filOrigen" name="origen" class="form-control">
          <option value="">Todos</option>
          <option value="compra"           <?= ($filtros['origen']??'') === 'compra'           ? 'selected':'' ?>>Compra</option>
          <option value="devolucion"       <?= ($filtros['origen']??'') === 'devolucion'       ? 'selected':'' ?>>Devolución</option>
          <option value="vale_salida"      <?= ($filtros['origen']??'') === 'vale_salida'      ? 'selected':'' ?>>Vale de salida</option>
          <option value="ajuste_manual"    <?= ($filtros['origen']??'') === 'ajuste_manual'    ? 'selected':'' ?>>Ajuste manual</option>
          <option value="inventario_fisico"<?= ($filtros['origen']??'') === 'inventario_fisico'? 'selected':'' ?>>Inventario físico</option>
        </select>
      </div>
      <div style="min-width:140px">
        <label class="form-label" for="filDesde">Desde</label>
        <input type="date" id="filDesde" name="fecha_desde" class="form-control"
               value="<?= e($filtros['fecha_desde'] ?? '') ?>">
      </div>
      <div style="min-width:140px">
        <label class="form-label" for="filHasta">Hasta</label>
        <input type="date" id="filHasta" name="fecha_hasta" class="form-control"
               value="<?= e($filtros['fecha_hasta'] ?? '') ?>">
      </div>
      <div style="display:flex;gap:.5rem">
        <button type="submit" class="btn btn-primary">
          <i class="ti ti-filter" aria-hidden="true"></i> Filtrar
        </button>
        <a href="<?= e($config['app']['url']) ?>/inventario" class="btn btn-ghost">
          <i class="ti ti-x" aria-hidden="true"></i>
        </a>
      </div>
    </form>
  </div>
</div>

<!-- Tabla de movimientos -->
<div class="card">
  <div class="card-body" style="padding:0">
    <div class="table-container" style="border:none;border-radius:0">
      <table id="tablaMovimientos" class="table" style="width:100%">
        <thead>
          <tr>
            <th style="width:44px"></th>
            <th>Producto</th>
            <th>Cantidad</th>
            <th>Stock anterior</th>
            <th>Stock posterior</th>
            <th>Origen</th>
            <th>Registrado por</th>
            <th>Fecha y hora</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($movimientos as $m): ?>
          <tr>
            <!-- Ícono tipo -->
            <td style="text-align:center;padding:.75rem .5rem">
              <div style="width:32px;height:32px;border-radius:50%;
                          background:var(--status-<?= e($m['tipo_clase']) ?>-bg);
                          display:flex;align-items:center;justify-content:center;margin:0 auto">
                <i class="ti <?= e($m['tipo_icono']) ?>"
                   style="font-size:.875rem;color:var(--status-<?= e($m['tipo_clase']) ?>-text)"
                   aria-hidden="true"></i>
              </div>
            </td>
            <!-- Producto -->
            <td>
              <div style="font-weight:500;font-size:var(--font-size-sm)">
                <?= e($m['producto_nombre']) ?>
              </div>
              <div style="font-size:var(--font-size-xs);color:var(--text-muted)">
                <?= e($m['producto_codigo']) ?>
              </div>
            </td>
            <!-- Cantidad -->
            <td>
              <span style="font-weight:700;
                           color:var(--status-<?= e($m['tipo_clase']) ?>-text)">
                <?= e($m['signo']) ?><?= e($m['cantidad_texto']) ?>
              </span>
            </td>
            <!-- Stock anterior -->
            <td style="font-size:var(--font-size-sm);color:var(--text-muted)">
              <?= e($m['anterior_texto']) ?>
            </td>
            <!-- Stock posterior -->
            <td style="font-size:var(--font-size-sm);font-weight:500">
              <?= e($m['posterior_texto']) ?>
            </td>
            <!-- Origen -->
            <td>
              <span class="badge badge-muted"><?= e($m['origen_label']) ?></span>
            </td>
            <!-- Usuario -->
            <td style="font-size:var(--font-size-sm);color:var(--text-muted)">
              <?= e($m['usuario_nombre']) ?>
            </td>
            <!-- Fecha -->
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
  <?php if ($paginacion['paginas'] > 1): ?>
  <div class="card-footer" style="display:flex;align-items:center;justify-content:space-between">
    <div style="font-size:var(--font-size-sm);color:var(--text-muted)">
      <?= number_format($paginacion['total']) ?> movimientos en total
    </div>
    <div style="display:flex;gap:.375rem">
      <?php for ($i = 1; $i <= $paginacion['paginas']; $i++): ?>
        <?php
          $qs = http_build_query(array_merge($filtros, ['pagina' => $i]));
        ?>
        <a href="?<?= $qs ?>"
           class="btn btn-sm <?= $i === $paginacion['pagina_actual'] ? 'btn-primary' : 'btn-ghost' ?>">
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
<script>
$('#tablaMovimientos').DataTable({
  language: { url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json' },
  pageLength: 25,
  order: [[7, 'desc']],
  columnDefs: [{ orderable: false, targets: 0 }],
  dom: 'tp',
});
</script>
<?php $extraJs = ob_get_clean(); ?>
