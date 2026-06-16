<?php
if (!isset($config)) { $config = $GLOBALS['app_config'] ?? require ROOT_PATH . '/config/config.php'; }
/**
 * views/productos/index.php
 *
 * Lista de productos con filtros, tabla DataTable y CRUD en modal.
 *
 * Variables: $productos, $paginacion, $categorias, $filtros
 */
?>

<!-- Encabezado -->
<div class="page-header">
  <div>
    <h1 class="page-title">Productos</h1>
    <p class="page-subtitle">Catálogo completo de artículos del almacén</p>
  </div>
  <?php if (in_array($_SESSION['usuario_rol'], [1, 2])): ?>
  <button class="btn btn-primary" id="btnNuevoProducto">
    <i class="ti ti-plus" aria-hidden="true"></i> Nuevo producto
  </button>
  <?php endif; ?>
</div>

<!-- Filtros rápidos -->
<div class="card" style="margin-bottom:1.25rem">
  <div class="card-body" style="padding:1rem 1.25rem">
    <form method="GET" action="" style="display:flex;gap:.75rem;flex-wrap:wrap;align-items:flex-end">
      <div style="flex:1;min-width:200px">
        <label class="form-label" for="filBusqueda">Buscar</label>
        <div class="input-group">
          <div class="input-group-prepend">
            <i class="ti ti-search" aria-hidden="true"></i>
          </div>
          <input type="text" id="filBusqueda" name="busqueda"
                 class="form-control" placeholder="Nombre o código…"
                 value="<?= e($filtros['busqueda'] ?? '') ?>">
        </div>
      </div>
      <div style="min-width:180px">
        <label class="form-label" for="filCategoria">Categoría</label>
        <select id="filCategoria" name="categoria_id" class="form-control">
          <option value="">Todas</option>
          <?php foreach ($categorias as $cat): ?>
          <option value="<?= (int) $cat['id'] ?>"
            <?= (int) ($filtros['categoria_id'] ?? 0) === (int) $cat['id'] ? 'selected' : '' ?>>
            <?= e($cat['nombre']) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div style="min-width:150px">
        <label class="form-label" for="filEstado">Estado stock</label>
        <select id="filEstado" name="estado_stock" class="form-control">
          <option value="">Todos</option>
          <option value="ok"       <?= ($filtros['estado_stock']??'') === 'ok'       ? 'selected':'' ?>>Normal</option>
          <option value="bajo"     <?= ($filtros['estado_stock']??'') === 'bajo'     ? 'selected':'' ?>>Bajo</option>
          <option value="critico"  <?= ($filtros['estado_stock']??'') === 'critico'  ? 'selected':'' ?>>Crítico</option>
          <option value="sin_stock"<?= ($filtros['estado_stock']??'') === 'sin_stock'? 'selected':'' ?>>Sin stock</option>
        </select>
      </div>
      <div style="display:flex;gap:.5rem">
        <button type="submit" class="btn btn-primary">
          <i class="ti ti-filter" aria-hidden="true"></i> Filtrar
        </button>
        <a href="<?= e($config['app']['url']) ?>/productos" class="btn btn-ghost">
          <i class="ti ti-x" aria-hidden="true"></i>
        </a>
      </div>
    </form>
  </div>
</div>

<!-- Tabla de productos -->
<div class="card">
  <div class="card-body" style="padding:0">
    <div class="table-container" style="border:none;border-radius:0">
      <table id="tablaProductos" class="table" style="width:100%">
        <thead>
          <tr>
            <th>Código</th>
            <th>Nombre</th>
            <th>Categoría</th>
            <th style="text-align:right">Stock actual</th>
            <th style="text-align:right">Precio unit.</th>
            <th style="text-align:center">Estado</th>
            <th style="text-align:center;width:120px">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($productos as $p): ?>
          <tr>
            <!-- Código -->
            <td>
              <span style="font-family:monospace;font-size:var(--font-size-sm);
                           font-weight:600;color:var(--color-primary)">
                <?= e($p['codigo']) ?>
              </span>
            </td>
            <!-- Nombre -->
            <td>
              <div style="font-weight:500;font-size:var(--font-size-sm)">
                <?= e($p['nombre']) ?>
              </div>
              <?php if ($p['presentacion']): ?>
              <div style="font-size:var(--font-size-xs);color:var(--text-muted)">
                <?= e($p['presentacion']) ?>
              </div>
              <?php endif; ?>
            </td>
            <!-- Categoría -->
            <td style="font-size:var(--font-size-sm);color:var(--text-muted)">
              <?= e($p['categoria_nombre']) ?>
            </td>
            <!-- Stock -->
            <td style="text-align:right">
              <div style="font-weight:600;font-size:var(--font-size-sm)">
                <?= e($p['stock_texto']) ?>
              </div>
              <div style="font-size:var(--font-size-xs);color:var(--text-muted)">
                mín: <?= number_format((int) $p['stock_minimo']) ?> pz.
              </div>
            </td>
            <!-- Precio -->
            <td style="text-align:right;font-size:var(--font-size-sm)">
              <?= e($p['precio_fmt']) ?>
            </td>
            <!-- Estado stock -->
            <td style="text-align:center">
              <span class="badge <?= e($p['badge_stock']['clase']) ?>">
                <?= e($p['badge_stock']['label']) ?>
              </span>
            </td>
            <!-- Acciones -->
            <td style="text-align:center">
              <div class="table-actions" style="justify-content:center">
                <a href="<?= e($config['app']['url']) ?>/productos/<?= (int) $p['producto_id'] ?>"
                   class="btn btn-ghost btn-icon" title="Ver detalle">
                  <i class="ti ti-eye" aria-hidden="true"></i>
                </a>
                <?php if (in_array($_SESSION['usuario_rol'], [1, 2])): ?>
                <button class="btn btn-ghost btn-icon btn-editar-prod"
                        title="Editar"
                        data-id="<?= (int) $p['producto_id'] ?>"
                        data-codigo="<?= e($p['codigo']) ?>"
                        data-nombre="<?= e($p['nombre']) ?>"
                        data-desc="<?= e($p['descripcion'] ?? '') ?>"
                        data-cat="<?= (int) $p['categoria_id'] ?>"
                        data-unidad="<?= e($p['unidad_medida']) ?>"
                        data-presentacion="<?= e($p['presentacion'] ?? '') ?>"
                        data-upc="<?= (int) $p['unidades_por_caja'] ?>"
                        data-precio="<?= e($p['precio_unitario']) ?>"
                        data-minimo="<?= (int) $p['stock_minimo'] ?>"
                        data-activo="<?= (int) $p['activo'] ?>">
                  <i class="ti ti-edit" aria-hidden="true"></i>
                </button>
                <button class="btn btn-ghost btn-icon btn-eliminar-prod"
                        title="Eliminar"
                        style="color:var(--status-danger-text)"
                        data-id="<?= (int) $p['producto_id'] ?>"
                        data-nombre="<?= e($p['nombre']) ?>">
                  <i class="ti ti-trash" aria-hidden="true"></i>
                </button>
                <?php endif; ?>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- ============================================================
     MODAL: CREAR / EDITAR PRODUCTO
============================================================ -->
<div class="modal-backdrop" id="modalProducto" role="dialog" aria-modal="true"
     aria-labelledby="modalProdTitulo">
  <div class="modal modal-lg">
    <div class="modal-header">
      <h2 class="modal-title" id="modalProdTitulo">Nuevo producto</h2>
      <button class="modal-close" id="btnCerrarModalProd" aria-label="Cerrar">
        <i class="ti ti-x" aria-hidden="true"></i>
      </button>
    </div>
    <div class="modal-body">
      <form id="formProducto" novalidate>
        <?= Security::csrfField() ?>
        <input type="hidden" id="prodId" name="id" value="">

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">

          <!-- Código -->
          <div class="form-group">
            <label class="form-label required" for="prodCodigo">Código institucional</label>
            <input type="text" id="prodCodigo" name="codigo"
                   class="form-control" maxlength="40"
                   placeholder="Ej: PAP-001"
                   style="text-transform:uppercase">
            <div class="form-error" id="errCodigo"></div>
          </div>

          <!-- Categoría -->
          <div class="form-group">
            <label class="form-label required" for="prodCategoria">Categoría</label>
            <select id="prodCategoria" name="categoria_id" class="form-control">
              <option value="">— Seleccionar —</option>
              <?php foreach ($categorias as $cat): ?>
              <option value="<?= (int) $cat['id'] ?>"><?= e($cat['nombre']) ?></option>
              <?php endforeach; ?>
            </select>
            <div class="form-error" id="errCategoria_id"></div>
          </div>

          <!-- Nombre -->
          <div class="form-group" style="grid-column:1/-1">
            <label class="form-label required" for="prodNombre">Nombre del producto</label>
            <input type="text" id="prodNombre" name="nombre"
                   class="form-control" maxlength="200"
                   placeholder="Ej: Tóner HP LaserJet 85A">
            <div class="form-error" id="errNombre"></div>
          </div>

          <!-- Unidad de medida -->
          <div class="form-group">
            <label class="form-label required" for="prodUnidad">Unidad base</label>
            <select id="prodUnidad" name="unidad_medida" class="form-control">
              <option value="pieza">Pieza</option>
              <option value="litro">Litro</option>
              <option value="metro">Metro</option>
              <option value="hoja">Hoja</option>
              <option value="rollo">Rollo</option>
              <option value="frasco">Frasco</option>
              <option value="caja">Caja</option>
              <option value="paquete">Paquete</option>
              <option value="kilogramo">Kilogramo</option>
              <option value="gramo">Gramo</option>
            </select>
          </div>

          <!-- Unidades por caja — campo CRÍTICO -->
          <div class="form-group">
            <label class="form-label required" for="prodUpc">
              Unidades por caja
              <span style="font-size:var(--font-size-xs);color:var(--color-accent);
                           font-weight:400">— factor de conversión</span>
            </label>
            <input type="number" id="prodUpc" name="unidades_por_caja"
                   class="form-control" value="1" min="1" max="9999">
            <div class="form-hint" id="hintUpc">
              Usa 1 si el producto no se maneja en cajas.
            </div>
            <div class="form-error" id="errUnidades_por_caja"></div>
          </div>

          <!-- Presentación visual -->
          <div class="form-group">
            <label class="form-label" for="prodPresentacion">Presentación visible</label>
            <input type="text" id="prodPresentacion" name="presentacion"
                   class="form-control" maxlength="60"
                   placeholder="Ej: Caja de 50 piezas">
            <div class="form-hint">Texto descriptivo para reportes e impresiones.</div>
          </div>

          <!-- Precio unitario -->
          <div class="form-group">
            <label class="form-label required" for="prodPrecio">
              Precio unitario (por pieza)
            </label>
            <div class="input-group">
              <div class="input-group-prepend">$</div>
              <input type="number" id="prodPrecio" name="precio_unitario"
                     class="form-control" value="0" min="0" step="0.0001">
            </div>
            <div class="form-error" id="errPrecio_unitario"></div>
          </div>

          <!-- Stock mínimo -->
          <div class="form-group">
            <label class="form-label required" for="prodMinimo">
              Stock mínimo (en piezas)
            </label>
            <input type="number" id="prodMinimo" name="stock_minimo"
                   class="form-control" value="0" min="0">
            <div class="form-hint">El sistema alertará cuando el stock baje de este valor.</div>
          </div>

          <!-- Descripción -->
          <div class="form-group" style="grid-column:1/-1">
            <label class="form-label" for="prodDesc">Descripción</label>
            <textarea id="prodDesc" name="descripcion"
                      class="form-control" rows="2"
                      placeholder="Especificaciones, marca, modelo…"></textarea>
          </div>

          <!-- Activo (solo en edición) -->
          <div class="form-group" id="grupoProdActivo" style="display:none;grid-column:1/-1">
            <label style="display:flex;align-items:center;gap:.625rem;cursor:pointer">
              <input type="checkbox" id="prodActivo" name="activo" value="1">
              <span class="form-label" style="margin:0">Producto activo en catálogo</span>
            </label>
          </div>

          <!-- Alerta informativa sobre la regla de stock -->
          <div class="alert alert-info" style="grid-column:1/-1;margin:0">
            <i class="ti ti-info-circle" aria-hidden="true"></i>
            <span>
              El sistema almacena el stock siempre en <strong>piezas base</strong>.
              El campo "Unidades por caja" solo controla cómo se muestra visualmente.
            </span>
          </div>

        </div>
      </form>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" id="btnCancelarModalProd">Cancelar</button>
      <button class="btn btn-primary" id="btnGuardarProducto">
        <span id="btnProdTexto">Guardar</span>
        <span id="btnProdSpinner" style="display:none">
          <i class="ti ti-loader-2" style="animation:spin .75s linear infinite"></i>
        </span>
      </button>
    </div>
  </div>
</div>

<?php
ob_start();
$baseUrl = e($config['app']['url']);
?>
<script>
(function () {
'use strict';

const BASE = '<?= $baseUrl ?>';

// ── DataTable ──
$('#tablaProductos').DataTable({
  language: { url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json' },
  pageLength: 25,
  order: [[1, 'asc']],
  columnDefs: [{ orderable: false, targets: -1 }],
});

// ── Modal refs ──
const modal      = document.getElementById('modalProducto');
const form       = document.getElementById('formProducto');
const titulo     = document.getElementById('modalProdTitulo');
const prodId     = document.getElementById('prodId');
const btnGuardar = document.getElementById('btnGuardarProducto');

// ── Hint dinámico para unidades/caja ──
document.getElementById('prodUpc').addEventListener('input', function () {
  const v   = parseInt(this.value) || 1;
  const hint = document.getElementById('hintUpc');
  hint.textContent = v <= 1
    ? 'Usa 1 si el producto no se maneja en cajas.'
    : `Ejemplo: 3 cajas = ${3 * v} piezas base en el sistema.`;
});

function abrirModal(modo, d = {}) {
  titulo.textContent = modo === 'crear' ? 'Nuevo producto' : 'Editar producto';
  prodId.value = d.id || '';

  document.getElementById('prodCodigo').value       = d.codigo       || '';
  document.getElementById('prodNombre').value       = d.nombre       || '';
  document.getElementById('prodDesc').value         = d.desc         || '';
  document.getElementById('prodCategoria').value    = d.cat          || '';
  document.getElementById('prodUnidad').value       = d.unidad       || 'pieza';
  document.getElementById('prodPresentacion').value = d.presentacion || '';
  document.getElementById('prodUpc').value          = d.upc          || 1;
  document.getElementById('prodPrecio').value       = d.precio       || 0;
  document.getElementById('prodMinimo').value       = d.minimo       || 0;

  const ga = document.getElementById('grupoProdActivo');
  ga.style.display = modo === 'editar' ? 'block' : 'none';
  if (modo === 'editar') {
    document.getElementById('prodActivo').checked = d.activo == 1;
  }

  // El código no es editable en modo editar (integridad referencial)
  document.getElementById('prodCodigo').readOnly = modo === 'editar';

  limpiarErrores();
  modal.classList.add('open');
  document.getElementById(modo === 'crear' ? 'prodCodigo' : 'prodNombre').focus();
}

function cerrarModal() { modal.classList.remove('open'); form.reset(); }

document.getElementById('btnNuevoProducto')?.addEventListener('click', () => abrirModal('crear'));

document.querySelectorAll('.btn-editar-prod').forEach(btn => {
  btn.addEventListener('click', function () {
    abrirModal('editar', {
      id:          this.dataset.id,
      codigo:      this.dataset.codigo,
      nombre:      this.dataset.nombre,
      desc:        this.dataset.desc,
      cat:         this.dataset.cat,
      unidad:      this.dataset.unidad,
      presentacion:this.dataset.presentacion,
      upc:         this.dataset.upc,
      precio:      this.dataset.precio,
      minimo:      this.dataset.minimo,
      activo:      this.dataset.activo,
    });
  });
});

['btnCerrarModalProd', 'btnCancelarModalProd'].forEach(id => {
  document.getElementById(id)?.addEventListener('click', cerrarModal);
});
modal.addEventListener('click', e => { if (e.target === modal) cerrarModal(); });

// ── Guardar ──
btnGuardar.addEventListener('click', async function () {
  limpiarErrores();
  const id  = prodId.value;
  const url = id ? `${BASE}/productos/${id}/editar` : `${BASE}/productos`;
  const fd  = new FormData(form);

  if (document.getElementById('prodActivo') &&
      !document.getElementById('prodActivo').checked) {
    fd.set('activo', '0');
  }

  setLoading(true);
  try {
    const res  = await fetch(url, { method: 'POST', body: fd });
    const json = await res.json();

    if (json.success) {
      cerrarModal();
      await SwalInst.fire({
        icon: 'success', title: json.message,
        timer: 1800, showConfirmButton: false,
      });
      location.reload();
    } else {
      if (json.errors) mostrarErrores(json.errors);
      else SwalInst.fire({ icon: 'error', title: json.message });
    }
  } catch {
    SwalInst.fire({ icon: 'error', title: 'Error de conexión.' });
  } finally {
    setLoading(false);
  }
});

// ── Eliminar ──
document.querySelectorAll('.btn-eliminar-prod').forEach(btn => {
  btn.addEventListener('click', async function () {
    const id     = this.dataset.id;
    const nombre = this.dataset.nombre;

    const confirm = await SwalInst.fire({
      icon:              'warning',
      title:             '¿Eliminar producto?',
      html:              `<b>${nombre}</b> será eliminado. Esta acción no se puede deshacer.`,
      showCancelButton:  true,
      confirmButtonText: 'Sí, eliminar',
      cancelButtonText:  'Cancelar',
    });
    if (!confirm.isConfirmed) return;

    const fd = new FormData();
    fd.append('_csrf_token', window.CSRF_TOKEN);

    try {
      const res  = await fetch(`${BASE}/productos/${id}/eliminar`, {
        method: 'POST', body: fd,
      });
      const json = await res.json();
      if (json.success) {
        await SwalInst.fire({
          icon: 'success', title: json.message,
          timer: 1600, showConfirmButton: false,
        });
        location.reload();
      } else {
        SwalInst.fire({ icon: 'error', title: json.message });
      }
    } catch {
      SwalInst.fire({ icon: 'error', title: 'Error de conexión.' });
    }
  });
});

// ── Helpers ──
function setLoading(on) {
  btnGuardar.disabled = on;
  document.getElementById('btnProdTexto').style.display   = on ? 'none'   : 'inline';
  document.getElementById('btnProdSpinner').style.display = on ? 'inline' : 'none';
}
function limpiarErrores() {
  document.querySelectorAll('.form-error').forEach(el => el.textContent = '');
  document.querySelectorAll('.form-control').forEach(el => el.classList.remove('is-invalid'));
}
function mostrarErrores(errors) {
  Object.entries(errors).forEach(([campo, msg]) => {
    const key = campo.replace(/_([a-z])/g, (_, l) => l.toUpperCase());
    const err = document.getElementById('err' + key.charAt(0).toUpperCase() + key.slice(1));
    if (err) err.textContent = msg;
    const inp = document.querySelector(`[name="${campo}"]`);
    if (inp) inp.classList.add('is-invalid');
  });
}

})();
</script>
<?php
$extraJs = ob_get_clean();
?>
