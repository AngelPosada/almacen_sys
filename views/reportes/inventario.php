<?php
if (!isset($config)) { $config = $GLOBALS['app_config'] ?? require ROOT_PATH . '/config/config.php'; }
/**
 * views/reportes/inventario.php
 * Variables: $productos, $categorias, $kpis, $filtros
 */

$baseUrl = e($config['app']['url']);
?>

<div class="page-header">
  <div>
    <h1 class="page-title">Inventario valorizado</h1>
    <p class="page-subtitle">
      Generado el <?= date('d/m/Y H:i') ?> por <?= e($_SESSION['usuario_nombre'] ?? '') ?>
    </p>
  </div>
  <button class="btn btn-outline-primary" id="btnExportarExcel">
    <i class="ti ti-file-spreadsheet"></i> Exportar Excel
  </button>
</div>

<!-- KPIs -->
<div class="stats-grid" style="margin-bottom:1.5rem">
  <div class="stat-card">
    <div class="stat-card-icon primary">
      <i class="ti ti-package" style="font-size:1.25rem"></i>
    </div>
    <div class="stat-card-info">
      <div class="stat-card-value"><?= e($kpis['total_productos']) ?></div>
      <div class="stat-card-label">Productos activos</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-card-icon secondary">
      <i class="ti ti-currency-dollar" style="font-size:1.25rem"></i>
    </div>
    <div class="stat-card-info">
      <div class="stat-card-value" style="font-size:1.5rem"><?= e($kpis['valor_total']) ?></div>
      <div class="stat-card-label">Valor total del inventario</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-card-icon accent">
      <i class="ti ti-alert-triangle" style="font-size:1.25rem"></i>
    </div>
    <div class="stat-card-info">
      <div class="stat-card-value"><?= e($kpis['productos_critico']) ?></div>
      <div class="stat-card-label">Productos en alerta</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-card-icon primary">
      <i class="ti ti-category" style="font-size:1.25rem"></i>
    </div>
    <div class="stat-card-info">
      <div class="stat-card-value"><?= e($kpis['categorias']) ?></div>
      <div class="stat-card-label">Categorías</div>
    </div>
  </div>
</div>

<!-- Filtros -->
<div class="card" style="margin-bottom:1.25rem">
  <div class="card-body" style="padding:1rem 1.25rem">
    <form method="GET" style="display:flex;gap:.75rem;flex-wrap:wrap;align-items:flex-end">
      <div style="min-width:160px">
        <label class="form-label" for="filEstStock">Estado stock</label>
        <select id="filEstStock" name="estado_stock" class="form-control">
          <option value="">Todos</option>
          <option value="ok"       <?= ($filtros['estado_stock']??'') === 'ok'       ? 'selected':'' ?>>Normal</option>
          <option value="bajo"     <?= ($filtros['estado_stock']??'') === 'bajo'     ? 'selected':'' ?>>Bajo</option>
          <option value="critico"  <?= ($filtros['estado_stock']??'') === 'critico'  ? 'selected':'' ?>>Crítico</option>
          <option value="sin_stock"<?= ($filtros['estado_stock']??'') === 'sin_stock'? 'selected':'' ?>>Sin stock</option>
        </select>
      </div>
      <div style="min-width:160px">
        <label class="form-label" for="filOrden">Ordenar por</label>
        <select id="filOrden" name="orden" class="form-control">
          <option value="nombre"    <?= ($filtros['orden']??'') === 'nombre'    ? 'selected':'' ?>>Nombre</option>
          <option value="valor"     <?= ($filtros['orden']??'') === 'valor'     ? 'selected':'' ?>>Mayor valor</option>
          <option value="stock"     <?= ($filtros['orden']??'') === 'stock'     ? 'selected':'' ?>>Mayor stock</option>
          <option value="categoria" <?= ($filtros['orden']??'') === 'categoria' ? 'selected':'' ?>>Categoría</option>
        </select>
      </div>
      <div style="display:flex;gap:.5rem">
        <button type="submit" class="btn btn-primary">
          <i class="ti ti-filter"></i> Filtrar
        </button>
        <a href="<?= $baseUrl ?>/reportes/inventario" class="btn btn-ghost">
          <i class="ti ti-x"></i>
        </a>
      </div>
    </form>
  </div>
</div>

<div style="display:grid;grid-template-columns:2fr 1fr;gap:1.25rem">

  <!-- Tabla principal -->
  <div class="card">
    <div class="card-header">
      <div class="card-title">Detalle por producto</div>
      <span style="font-size:var(--font-size-xs);color:var(--text-muted)">
        <?= count($productos) ?> productos
      </span>
    </div>
    <div class="card-body" style="padding:0">
      <div class="table-container" style="border:none;border-radius:0">
        <table id="tablaInventario" class="table" style="width:100%">
          <thead>
            <tr>
              <th>Código</th>
              <th>Producto</th>
              <th>Categoría</th>
              <th style="text-align:center">Stock</th>
              <th style="text-align:right">Precio unit.</th>
              <th style="text-align:right">Valor total</th>
              <th style="text-align:center">Estado</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($productos as $p): ?>
            <tr>
              <td>
                <a href="<?= $baseUrl ?>/productos/<?= (int)$p['id'] ?>"
                   style="font-family:monospace;font-weight:600;
                          color:var(--color-primary);text-decoration:none;
                          font-size:var(--font-size-sm)">
                  <?= e($p['codigo']) ?>
                </a>
              </td>
              <td>
                <div style="font-size:var(--font-size-sm);font-weight:500">
                  <?= e($p['nombre']) ?>
                </div>
                <?php if ($p['presentacion']): ?>
                <div style="font-size:var(--font-size-xs);color:var(--text-muted)">
                  <?= e($p['presentacion']) ?>
                </div>
                <?php endif; ?>
              </td>
              <td style="font-size:var(--font-size-sm);color:var(--text-muted)">
                <?= e($p['categoria_nombre']) ?>
              </td>
              <td style="text-align:center">
                <div style="font-weight:600;font-size:var(--font-size-sm)">
                  <?= e($p['stock_texto']) ?>
                </div>
                <div style="font-size:var(--font-size-xs);color:var(--text-muted)">
                  mín <?= number_format((int)$p['stock_minimo']) ?> pz.
                </div>
              </td>
              <td style="text-align:right;font-size:var(--font-size-sm)">
                <?= e($p['precio_fmt']) ?>
              </td>
              <td style="text-align:right;font-weight:700;
                          color:var(--color-primary);font-size:var(--font-size-sm)">
                <?= e($p['valor_fmt']) ?>
              </td>
              <td style="text-align:center">
                <span class="badge <?= e($p['estado_clase']) ?>">
                  <?= e($p['estado_label']) ?>
                </span>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Tabla por categorías -->
  <div class="card">
    <div class="card-header">
      <div class="card-title">Por categoría</div>
    </div>
    <div class="card-body" style="padding:0">
      <table class="table" style="width:100%">
        <thead>
          <tr>
            <th>Categoría</th>
            <th style="text-align:center">Prods.</th>
            <th style="text-align:right">Valor</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($categorias as $c): ?>
          <tr>
            <td style="font-size:var(--font-size-sm)">
              <?= e($c['categoria']) ?>
              <?php if ((int)$c['productos_criticos'] > 0): ?>
              <span class="badge badge-danger"
                    style="font-size:.6rem;margin-left:4px">
                <?= (int)$c['productos_criticos'] ?> ⚠
              </span>
              <?php endif; ?>
            </td>
            <td style="text-align:center;font-size:var(--font-size-sm)">
              <?= (int)$c['total_productos'] ?>
            </td>
            <td style="text-align:right;font-weight:600;
                        color:var(--color-primary);font-size:var(--font-size-sm)">
              <?= e($c['valor_fmt']) ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>

<?php
ob_start();
$baseUrlJs = e($config['app']['url']);
?>
<script>
$('#tablaInventario').DataTable({
  language: { url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json' },
  pageLength: 50, order: [[1,'asc']],
  columnDefs: [{ orderable: false, targets: [6] }],
});

document.getElementById('btnExportarExcel')?.addEventListener('click', async function () {
  const fd = new FormData();
  fd.append('_csrf_token', window.CSRF_TOKEN);
  fd.append('tipo', 'inventario');
  <?php foreach ($filtros as $k => $v): ?>
  fd.append('<?= e($k) ?>', '<?= e($v) ?>');
  <?php endforeach; ?>

  this.disabled = true;
  this.innerHTML = '<i class="ti ti-loader-2" style="animation:spin .75s linear infinite"></i> Generando…';

  try {
    const res  = await fetch('<?= $baseUrlJs ?>/reportes/exportar', { method:'POST', body:fd });
    const json = await res.json();
    if (json.success) {
      // Cuando PhpSpreadsheet esté disponible, aquí se descargará el .xlsx
      SwalInst.fire({
        icon:  'info',
        title: 'Exportación lista',
        html:  'La integración con Excel (.xlsx) estará disponible con PhpSpreadsheet.<br>' +
               '<small>Los datos están listos en el servidor.</small>',
      });
    }
  } catch { SwalInst.fire({ icon:'error', title:'Error de conexión.' }); }
  finally {
    this.disabled = false;
    this.innerHTML = '<i class="ti ti-file-spreadsheet"></i> Exportar Excel';
  }
});
</script>
<?php $extraJs = ob_get_clean(); ?>
