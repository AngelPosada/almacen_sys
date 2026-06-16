<?php
if (!isset($config)) { $config = $GLOBALS['app_config'] ?? require ROOT_PATH . '/config/config.php'; }
/**
 * views/productos/show.php
 *
 * Detalle completo de un producto.
 * Variables: $producto (array formateado), $categorias (array para selector)
 */
?>

<div class="page-header">
  <div>
    <h1 class="page-title"><?= e($producto['nombre']) ?></h1>
    <p class="page-subtitle">
      <span style="font-family:monospace;font-weight:600;color:var(--color-primary)">
        <?= e($producto['codigo']) ?>
      </span>
      &mdash; <?= e($producto['categoria_nombre']) ?>
    </p>
  </div>
  <?php if (in_array($_SESSION['usuario_rol'], [1, 2])): ?>
  <div style="display:flex;gap:.625rem">
    <button class="btn btn-outline-primary" id="btnEditarProducto">
      <i class="ti ti-edit" aria-hidden="true"></i> Editar
    </button>
    <a href="<?= e($config['app']['url']) ?>/inventario/entradas?producto_id=<?= (int) $producto['id'] ?>"
       class="btn btn-primary">
      <i class="ti ti-plus" aria-hidden="true"></i> Registrar entrada
    </a>
  </div>
  <?php endif; ?>
</div>

<div style="display:grid;grid-template-columns:2fr 1fr;gap:1.25rem">

  <!-- Información principal -->
  <div style="display:flex;flex-direction:column;gap:1.25rem">

    <!-- Datos generales -->
    <div class="card">
      <div class="card-header">
        <div class="card-title">Información del producto</div>
      </div>
      <div class="card-body">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.25rem">

          <div>
            <div style="font-size:var(--font-size-xs);color:var(--text-muted);
                        text-transform:uppercase;letter-spacing:.05em;margin-bottom:.25rem">
              Código
            </div>
            <div style="font-family:monospace;font-weight:600;font-size:var(--font-size-md);
                        color:var(--color-primary)">
              <?= e($producto['codigo']) ?>
            </div>
          </div>

          <div>
            <div style="font-size:var(--font-size-xs);color:var(--text-muted);
                        text-transform:uppercase;letter-spacing:.05em;margin-bottom:.25rem">
              Categoría
            </div>
            <div style="font-weight:500"><?= e($producto['categoria_nombre']) ?></div>
          </div>

          <div>
            <div style="font-size:var(--font-size-xs);color:var(--text-muted);
                        text-transform:uppercase;letter-spacing:.05em;margin-bottom:.25rem">
              Unidad base
            </div>
            <div><?= e(ucfirst($producto['unidad_medida'])) ?></div>
          </div>

          <div>
            <div style="font-size:var(--font-size-xs);color:var(--text-muted);
                        text-transform:uppercase;letter-spacing:.05em;margin-bottom:.25rem">
              Unidades por caja
            </div>
            <div>
              <?= (int) $producto['unidades_por_caja'] ?>
              <?php if ($producto['presentacion']): ?>
              <span style="color:var(--text-muted);font-size:var(--font-size-xs)">
                (<?= e($producto['presentacion']) ?>)
              </span>
              <?php endif; ?>
            </div>
          </div>

          <div>
            <div style="font-size:var(--font-size-xs);color:var(--text-muted);
                        text-transform:uppercase;letter-spacing:.05em;margin-bottom:.25rem">
              Precio unitario
            </div>
            <div style="font-weight:600;font-size:var(--font-size-lg)">
              <?= e($producto['precio_fmt']) ?>
            </div>
          </div>

          <div>
            <div style="font-size:var(--font-size-xs);color:var(--text-muted);
                        text-transform:uppercase;letter-spacing:.05em;margin-bottom:.25rem">
              Estado
            </div>
            <span class="badge <?= $producto['activo'] ? 'badge-success' : 'badge-muted' ?>">
              <?= $producto['activo'] ? 'Activo' : 'Inactivo' ?>
            </span>
          </div>

          <?php if ($producto['descripcion']): ?>
          <div style="grid-column:1/-1">
            <div style="font-size:var(--font-size-xs);color:var(--text-muted);
                        text-transform:uppercase;letter-spacing:.05em;margin-bottom:.25rem">
              Descripción
            </div>
            <div style="color:var(--text-secondary);font-size:var(--font-size-sm);
                        line-height:1.6">
              <?= e($producto['descripcion']) ?>
            </div>
          </div>
          <?php endif; ?>

        </div>
      </div>
    </div>

    <!-- Historial de movimientos (placeholder) -->
    <div class="card">
      <div class="card-header">
        <div class="card-title">Últimos movimientos</div>
        <a href="<?= e($config['app']['url']) ?>/inventario?producto_id=<?= (int) $producto['id'] ?>"
           class="btn btn-ghost btn-sm">Ver todos</a>
      </div>
      <div class="card-body" style="text-align:center;padding:2rem;color:var(--text-muted)">
        <i class="ti ti-history" style="font-size:2rem;display:block;margin-bottom:.5rem" aria-hidden="true"></i>
        El historial completo estará disponible en el módulo de Inventario (Fase F).
      </div>
    </div>

  </div>

  <!-- Panel lateral: Stock -->
  <div style="display:flex;flex-direction:column;gap:1.25rem">

    <!-- KPI de stock -->
    <div class="card">
      <div class="card-header">
        <div class="card-title">Stock actual</div>
        <span class="badge <?= e($producto['badge_stock']['clase']) ?>">
          <?= e($producto['badge_stock']['label']) ?>
        </span>
      </div>
      <div class="card-body" style="text-align:center;padding:1.75rem 1.25rem">

        <!-- Valor principal -->
        <div style="font-family:var(--font-display);font-size:2.5rem;font-weight:700;
                    color:var(--text-primary);line-height:1.1;margin-bottom:.375rem">
          <?= number_format((int) $producto['stock_actual']) ?>
        </div>
        <div style="font-size:var(--font-size-sm);color:var(--text-muted);margin-bottom:1rem">
          piezas base en almacén
        </div>

        <!-- Presentación visual (cajas + piezas) -->
        <?php if ((int) $producto['unidades_por_caja'] > 1): ?>
        <div style="background:var(--bg-surface-2);border-radius:var(--border-radius);
                    padding:.875rem;margin-bottom:1rem">
          <div style="font-size:var(--font-size-xs);color:var(--text-muted);margin-bottom:.25rem">
            Equivale a:
          </div>
          <div style="font-weight:600;color:var(--color-primary)">
            <?= e($producto['stock_texto']) ?>
          </div>
        </div>
        <?php endif; ?>

        <!-- Barra de stock -->
        <?php
          $minimo     = max((int) $producto['stock_minimo'], 1);
          $pct        = min(round(((int) $producto['stock_actual'] / $minimo) * 100), 100);
          $barraClase = match ($producto['badge_stock']['label']) {
              'Crítico', 'Sin stock' => 'critical',
              'Bajo'                 => 'warning',
              default                => 'ok',
          };
        ?>
        <div class="stock-bar" style="height:8px;margin-bottom:.625rem">
          <div class="stock-bar-fill <?= $barraClase ?>"
               style="width:<?= $pct ?>%"></div>
        </div>

        <div style="display:flex;justify-content:space-between;
                    font-size:var(--font-size-xs);color:var(--text-muted)">
          <span>0 pz.</span>
          <span>Mín: <?= number_format((int) $producto['stock_minimo']) ?> pz.</span>
        </div>

      </div>
      <?php if (in_array($_SESSION['usuario_rol'], [1, 2])): ?>
      <div class="card-footer" style="text-align:center">
        <a href="<?= e($config['app']['url']) ?>/inventario/entradas?producto_id=<?= (int) $producto['id'] ?>"
           class="btn btn-primary w-100">
          <i class="ti ti-plus" aria-hidden="true"></i>
          Registrar entrada
        </a>
      </div>
      <?php endif; ?>
    </div>

    <!-- Datos adicionales -->
    <div class="card">
      <div class="card-header">
        <div class="card-title">Datos del sistema</div>
      </div>
      <div class="card-body">
        <div style="display:flex;flex-direction:column;gap:.75rem">
          <div>
            <div style="font-size:var(--font-size-xs);color:var(--text-muted)">Stock mínimo</div>
            <div style="font-size:var(--font-size-sm);font-weight:500">
              <?= e($producto['stock_minimo_texto']) ?>
            </div>
          </div>
          <?php if ($producto['ultima_entrada']): ?>
          <div>
            <div style="font-size:var(--font-size-xs);color:var(--text-muted)">Última entrada</div>
            <div style="font-size:var(--font-size-sm)">
              <?= date('d/m/Y H:i', strtotime($producto['ultima_entrada'])) ?>
            </div>
          </div>
          <?php endif; ?>
          <?php if ($producto['ultima_salida']): ?>
          <div>
            <div style="font-size:var(--font-size-xs);color:var(--text-muted)">Última salida</div>
            <div style="font-size:var(--font-size-sm)">
              <?= date('d/m/Y H:i', strtotime($producto['ultima_salida'])) ?>
            </div>
          </div>
          <?php endif; ?>
          <div>
            <div style="font-size:var(--font-size-xs);color:var(--text-muted)">Registrado</div>
            <div style="font-size:var(--font-size-sm)">
              <?= date('d/m/Y', strtotime($producto['creado_en'])) ?>
            </div>
          </div>
        </div>
      </div>
    </div>

  </div>
</div>

<?php
ob_start();
$baseUrl = e($config['app']['url']);
// Pasar datos del producto al JS para rellenar el modal de edición
$prodJs = json_encode([
    'id'           => $producto['id'],
    'codigo'       => $producto['codigo'],
    'nombre'       => $producto['nombre'],
    'desc'         => $producto['descripcion'] ?? '',
    'cat'          => $producto['categoria_id'],
    'unidad'       => $producto['unidad_medida'],
    'presentacion' => $producto['presentacion'] ?? '',
    'upc'          => $producto['unidades_por_caja'],
    'precio'       => $producto['precio_unitario'],
    'minimo'       => $producto['stock_minimo'],
    'activo'       => $producto['activo'],
], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);
?>
<!-- Modal de edición (reutilizar el mismo JS de la lista) -->
<div class="modal-backdrop" id="modalProducto" role="dialog" aria-modal="true"
     aria-labelledby="modalProdTitulo">
  <div class="modal modal-lg">
    <div class="modal-header">
      <h2 class="modal-title" id="modalProdTitulo">Editar producto</h2>
      <button class="modal-close" id="btnCerrarModalProd" aria-label="Cerrar">
        <i class="ti ti-x" aria-hidden="true"></i>
      </button>
    </div>
    <div class="modal-body">
      <form id="formProducto" novalidate>
        <?= Security::csrfField() ?>
        <input type="hidden" id="prodId" name="id" value="<?= (int) $producto['id'] ?>">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
          <div class="form-group">
            <label class="form-label" for="prodCodigo">Código</label>
            <input type="text" id="prodCodigo" name="codigo" class="form-control"
                   value="<?= e($producto['codigo']) ?>" readonly>
          </div>
          <div class="form-group">
            <label class="form-label required" for="prodCategoria">Categoría</label>
            <select id="prodCategoria" name="categoria_id" class="form-control">
              <?php foreach ($categorias as $cat): ?>
              <option value="<?= (int) $cat['id'] ?>"
                <?= (int) $cat['id'] === (int) $producto['categoria_id'] ? 'selected' : '' ?>>
                <?= e($cat['nombre']) ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group" style="grid-column:1/-1">
            <label class="form-label required" for="prodNombre">Nombre</label>
            <input type="text" id="prodNombre" name="nombre" class="form-control"
                   value="<?= e($producto['nombre']) ?>">
            <div class="form-error" id="errNombre"></div>
          </div>
          <div class="form-group">
            <label class="form-label" for="prodUnidad">Unidad base</label>
            <select id="prodUnidad" name="unidad_medida" class="form-control">
              <?php
              $unidades = ['pieza','litro','metro','hoja','rollo','frasco','caja','paquete','kilogramo','gramo'];
              foreach ($unidades as $u):
              ?>
              <option value="<?= $u ?>" <?= $producto['unidad_medida'] === $u ? 'selected' : '' ?>>
                <?= ucfirst($u) ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label" for="prodUpc">Unidades por caja</label>
            <input type="number" id="prodUpc" name="unidades_por_caja" class="form-control"
                   value="<?= (int) $producto['unidades_por_caja'] ?>" min="1">
          </div>
          <div class="form-group">
            <label class="form-label" for="prodPresentacion">Presentación</label>
            <input type="text" id="prodPresentacion" name="presentacion" class="form-control"
                   value="<?= e($producto['presentacion'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label class="form-label" for="prodPrecio">Precio unitario</label>
            <div class="input-group">
              <div class="input-group-prepend">$</div>
              <input type="number" id="prodPrecio" name="precio_unitario" class="form-control"
                     value="<?= e($producto['precio_unitario']) ?>" min="0" step="0.0001">
            </div>
          </div>
          <div class="form-group">
            <label class="form-label" for="prodMinimo">Stock mínimo (piezas)</label>
            <input type="number" id="prodMinimo" name="stock_minimo" class="form-control"
                   value="<?= (int) $producto['stock_minimo'] ?>" min="0">
          </div>
          <div class="form-group" style="grid-column:1/-1">
            <label class="form-label" for="prodDesc">Descripción</label>
            <textarea id="prodDesc" name="descripcion" class="form-control" rows="2">
<?= e($producto['descripcion'] ?? '') ?></textarea>
          </div>
          <div class="form-group" style="grid-column:1/-1">
            <label style="display:flex;align-items:center;gap:.625rem;cursor:pointer">
              <input type="checkbox" id="prodActivo" name="activo" value="1"
                     <?= $producto['activo'] ? 'checked' : '' ?>>
              <span class="form-label" style="margin:0">Producto activo en catálogo</span>
            </label>
          </div>
        </div>
      </form>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" id="btnCancelarModalProd">Cancelar</button>
      <button class="btn btn-primary" id="btnGuardarProducto">
        <span id="btnProdTexto">Guardar cambios</span>
        <span id="btnProdSpinner" style="display:none">
          <i class="ti ti-loader-2" style="animation:spin .75s linear infinite"></i>
        </span>
      </button>
    </div>
  </div>
</div>

<script>
(function () {
'use strict';
const BASE = '<?= $baseUrl ?>';
const modal = document.getElementById('modalProducto');

document.getElementById('btnEditarProducto')?.addEventListener('click', () => {
  modal.classList.add('open');
  document.getElementById('prodNombre').focus();
});
['btnCerrarModalProd','btnCancelarModalProd'].forEach(id => {
  document.getElementById(id)?.addEventListener('click', () => modal.classList.remove('open'));
});
modal.addEventListener('click', e => { if (e.target === modal) modal.classList.remove('open'); });

document.getElementById('btnGuardarProducto').addEventListener('click', async function () {
  const id  = document.getElementById('prodId').value;
  const fd  = new FormData(document.getElementById('formProducto'));
  if (!document.getElementById('prodActivo').checked) fd.set('activo', '0');

  this.disabled = true;
  document.getElementById('btnProdTexto').style.display   = 'none';
  document.getElementById('btnProdSpinner').style.display = 'inline';

  try {
    const res  = await fetch(`${BASE}/productos/${id}/editar`, { method: 'POST', body: fd });
    const json = await res.json();
    if (json.success) {
      modal.classList.remove('open');
      await SwalInst.fire({ icon:'success', title: json.message, timer:1800, showConfirmButton:false });
      location.reload();
    } else {
      SwalInst.fire({ icon:'error', title: json.message || 'Datos inválidos.' });
    }
  } catch {
    SwalInst.fire({ icon:'error', title: 'Error de conexión.' });
  } finally {
    this.disabled = false;
    document.getElementById('btnProdTexto').style.display   = 'inline';
    document.getElementById('btnProdSpinner').style.display = 'none';
  }
});
})();
</script>
<?php
$extraJs = ob_get_clean();
?>
