<?php
if (!isset($config)) { $config = $GLOBALS['app_config'] ?? require ROOT_PATH . '/config/config.php'; }
/**
 * views/categorias/index.php
 *
 * Lista de categorías con CRUD completo en modales.
 * Variables: $categorias (array), $raices (array)
 */
?>

<!-- Encabezado -->
<div class="page-header">
  <div>
    <h1 class="page-title">Categorías</h1>
    <p class="page-subtitle">Clasificación de productos del almacén</p>
  </div>
  <?php if (in_array($_SESSION['usuario_rol'], [1, 2])): ?>
  <button class="btn btn-primary" id="btnNuevaCategoria">
    <i class="ti ti-plus" aria-hidden="true"></i> Nueva categoría
  </button>
  <?php endif; ?>
</div>

<!-- Tabla -->
<div class="card">
  <div class="card-body" style="padding:0">
    <div class="table-container" style="border:none;border-radius:0">
      <table id="tablaCategorias" class="table" style="width:100%">
        <thead>
          <tr>
            <th>Categoría</th>
            <th>Nivel</th>
            <th>Descripción</th>
            <th style="text-align:center">Productos</th>
            <th style="text-align:center">Estado</th>
            <?php if (in_array($_SESSION['usuario_rol'], [1, 2])): ?>
            <th style="text-align:center;width:110px">Acciones</th>
            <?php endif; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($categorias as $cat): ?>
          <tr>
            <!-- Nombre con ícono y color -->
            <td>
              <div style="display:flex;align-items:center;gap:.625rem">
                <div style="width:32px;height:32px;border-radius:var(--border-radius-sm);
                            background:<?= e($cat['color'] ?? '#0E734E') ?>22;
                            display:flex;align-items:center;justify-content:center;flex-shrink:0">
                  <i class="ti <?= e($cat['icono'] ?? 'ti-tag') ?>"
                     style="color:<?= e($cat['color'] ?? '#0E734E') ?>;font-size:.875rem"
                     aria-hidden="true"></i>
                </div>
                <div>
                  <div style="font-weight:500;font-size:var(--font-size-sm)">
                    <?= e($cat['nombre']) ?>
                  </div>
                  <?php if ($cat['parent_nombre']): ?>
                  <div style="font-size:var(--font-size-xs);color:var(--text-muted)">
                    Subcat. de <?= e($cat['parent_nombre']) ?>
                  </div>
                  <?php endif; ?>
                </div>
              </div>
            </td>
            <!-- Nivel -->
            <td>
              <span class="badge <?= $cat['parent_id'] ? 'badge-info' : 'badge-success' ?>">
                <?= $cat['parent_id'] ? 'Subcategoría' : 'Principal' ?>
              </span>
            </td>
            <!-- Descripción -->
            <td style="color:var(--text-muted);font-size:var(--font-size-sm);max-width:240px">
              <?= e($cat['descripcion'] ?? '—') ?>
            </td>
            <!-- Productos -->
            <td style="text-align:center">
              <span style="font-weight:600;color:var(--color-primary)">
                <?= (int) $cat['total_productos'] ?>
              </span>
            </td>
            <!-- Estado -->
            <td style="text-align:center">
              <span class="badge <?= $cat['activo'] ? 'badge-success' : 'badge-muted' ?>">
                <?= $cat['activo'] ? 'Activa' : 'Inactiva' ?>
              </span>
            </td>
            <!-- Acciones -->
            <?php if (in_array($_SESSION['usuario_rol'], [1, 2])): ?>
            <td style="text-align:center">
              <div class="table-actions" style="justify-content:center">
                <button class="btn btn-ghost btn-icon btn-editar"
                        title="Editar"
                        data-id="<?= (int) $cat['id'] ?>"
                        data-nombre="<?= e($cat['nombre']) ?>"
                        data-descripcion="<?= e($cat['descripcion'] ?? '') ?>"
                        data-parent="<?= (int) ($cat['parent_id'] ?? 0) ?>"
                        data-icono="<?= e($cat['icono'] ?? 'ti-tag') ?>"
                        data-color="<?= e($cat['color'] ?? '#0E734E') ?>"
                        data-activo="<?= (int) $cat['activo'] ?>">
                  <i class="ti ti-edit" aria-hidden="true"></i>
                </button>
                <button class="btn btn-ghost btn-icon btn-eliminar"
                        title="Eliminar"
                        style="color:var(--status-danger-text)"
                        data-id="<?= (int) $cat['id'] ?>"
                        data-nombre="<?= e($cat['nombre']) ?>">
                  <i class="ti ti-trash" aria-hidden="true"></i>
                </button>
              </div>
            </td>
            <?php endif; ?>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- ============================================================
     MODAL: CREAR / EDITAR CATEGORÍA
============================================================ -->
<div class="modal-backdrop" id="modalCategoria" role="dialog" aria-modal="true"
     aria-labelledby="modalCatTitulo">
  <div class="modal">
    <div class="modal-header">
      <h2 class="modal-title" id="modalCatTitulo">Nueva categoría</h2>
      <button class="modal-close" id="btnCerrarModal" aria-label="Cerrar">
        <i class="ti ti-x" aria-hidden="true"></i>
      </button>
    </div>
    <div class="modal-body">
      <form id="formCategoria" novalidate>
        <?= Security::csrfField() ?>
        <input type="hidden" id="catId" name="id" value="">

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
          <!-- Nombre -->
          <div class="form-group" style="grid-column:1/-1">
            <label class="form-label required" for="catNombre">Nombre</label>
            <input type="text" id="catNombre" name="nombre"
                   class="form-control" maxlength="120"
                   placeholder="Ej: Papelería" required>
            <div class="form-error" id="errNombre"></div>
          </div>

          <!-- Categoría padre -->
          <div class="form-group">
            <label class="form-label" for="catParent">Categoría padre</label>
            <select id="catParent" name="parent_id" class="form-control">
              <option value="">— Ninguna (principal) —</option>
              <?php foreach ($raices as $r): ?>
              <option value="<?= (int) $r['id'] ?>">
                <?= e($r['nombre']) ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- Ícono -->
          <div class="form-group">
            <label class="form-label" for="catIcono">Ícono (clase Tabler)</label>
            <div class="input-group">
              <div class="input-group-prepend" id="iconoPreview">
                <i class="ti ti-tag" aria-hidden="true"></i>
              </div>
              <input type="text" id="catIcono" name="icono"
                     class="form-control" value="ti-tag"
                     placeholder="ti-tag">
            </div>
            <div class="form-hint">Consulta tabler-icons.io para los nombres.</div>
          </div>

          <!-- Color -->
          <div class="form-group">
            <label class="form-label" for="catColor">Color identificador</label>
            <div class="input-group">
              <input type="color" id="catColor" name="color"
                     value="#0E734E"
                     style="width:44px;padding:4px;cursor:pointer;
                            border:1.5px solid var(--border-color);
                            border-right:none;
                            border-radius:var(--border-radius-sm) 0 0 var(--border-radius-sm);
                            background:var(--bg-input)">
              <input type="text" id="catColorHex" class="form-control"
                     value="#0E734E" maxlength="7"
                     style="border-radius:0 var(--border-radius-sm) var(--border-radius-sm) 0">
            </div>
          </div>

          <!-- Descripción -->
          <div class="form-group" style="grid-column:1/-1">
            <label class="form-label" for="catDesc">Descripción</label>
            <textarea id="catDesc" name="descripcion"
                      class="form-control" rows="2"
                      maxlength="300" placeholder="Descripción opcional…"></textarea>
          </div>

          <!-- Activo (solo en edición) -->
          <div class="form-group" id="grupoActivo" style="display:none;grid-column:1/-1">
            <label style="display:flex;align-items:center;gap:.625rem;cursor:pointer">
              <input type="checkbox" id="catActivo" name="activo" value="1">
              <span class="form-label" style="margin:0">Categoría activa</span>
            </label>
          </div>
        </div>
      </form>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" id="btnCancelarModal">Cancelar</button>
      <button class="btn btn-primary" id="btnGuardarCategoria">
        <span id="btnGuardarTexto">Guardar</span>
        <span id="btnGuardarSpinner" style="display:none">
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
$('#tablaCategorias').DataTable({
  language: {
    url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json',
  },
  pageLength: 25,
  order: [[0, 'asc']],
  columnDefs: [{ orderable: false, targets: -1 }],
});

// ── Referencias DOM ──
const modal    = document.getElementById('modalCategoria');
const form     = document.getElementById('formCategoria');
const titulo   = document.getElementById('modalCatTitulo');
const catId    = document.getElementById('catId');
const btnGuardar = document.getElementById('btnGuardarCategoria');

function abrirModal(modo, datos = {}) {
  titulo.textContent = modo === 'crear' ? 'Nueva categoría' : 'Editar categoría';
  catId.value        = datos.id    || '';
  document.getElementById('catNombre').value      = datos.nombre      || '';
  document.getElementById('catDesc').value        = datos.descripcion || '';
  document.getElementById('catParent').value      = datos.parent      || '';
  document.getElementById('catIcono').value       = datos.icono       || 'ti-tag';
  document.getElementById('catColor').value       = datos.color       || '#0E734E';
  document.getElementById('catColorHex').value    = datos.color       || '#0E734E';
  actualizarIconoPreview(datos.icono || 'ti-tag');

  const grupoActivo = document.getElementById('grupoActivo');
  grupoActivo.style.display = modo === 'editar' ? 'block' : 'none';
  if (modo === 'editar') {
    document.getElementById('catActivo').checked = datos.activo == 1;
  }

  limpiarErrores();
  modal.classList.add('open');
  document.getElementById('catNombre').focus();
}

function cerrarModal() {
  modal.classList.remove('open');
  form.reset();
}

// ── Botón "Nueva" ──
document.getElementById('btnNuevaCategoria')?.addEventListener('click', () => {
  abrirModal('crear');
});

// ── Botones "Editar" ──
document.querySelectorAll('.btn-editar').forEach(btn => {
  btn.addEventListener('click', function () {
    abrirModal('editar', {
      id:          this.dataset.id,
      nombre:      this.dataset.nombre,
      descripcion: this.dataset.descripcion,
      parent:      this.dataset.parent,
      icono:       this.dataset.icono,
      color:       this.dataset.color,
      activo:      this.dataset.activo,
    });
  });
});

// ── Cerrar modal ──
['btnCerrarModal', 'btnCancelarModal'].forEach(id => {
  document.getElementById(id)?.addEventListener('click', cerrarModal);
});
modal.addEventListener('click', e => { if (e.target === modal) cerrarModal(); });

// ── Sync color picker ↔ texto hex ──
document.getElementById('catColor').addEventListener('input', function () {
  document.getElementById('catColorHex').value = this.value.toUpperCase();
});
document.getElementById('catColorHex').addEventListener('input', function () {
  if (/^#[0-9A-Fa-f]{6}$/.test(this.value)) {
    document.getElementById('catColor').value = this.value;
  }
});

// ── Preview ícono ──
document.getElementById('catIcono').addEventListener('input', function () {
  actualizarIconoPreview(this.value.trim());
});
function actualizarIconoPreview(clase) {
  const prev = document.getElementById('iconoPreview');
  prev.innerHTML = `<i class="ti ${clase}" aria-hidden="true"></i>`;
}

// ── Guardar (crear o editar) ──
btnGuardar.addEventListener('click', async function () {
  limpiarErrores();
  const id  = catId.value;
  const url = id
    ? `${BASE}/categorias/${id}/editar`
    : `${BASE}/categorias`;

  const fd  = new FormData(form);
  if (document.getElementById('catActivo') && !document.getElementById('catActivo').checked) {
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
document.querySelectorAll('.btn-eliminar').forEach(btn => {
  btn.addEventListener('click', async function () {
    const id     = this.dataset.id;
    const nombre = this.dataset.nombre;

    const confirm = await SwalInst.fire({
      icon:              'warning',
      title:             '¿Eliminar categoría?',
      html:              `<b>${nombre}</b> será eliminada permanentemente.`,
      showCancelButton:  true,
      confirmButtonText: 'Sí, eliminar',
      cancelButtonText:  'Cancelar',
    });

    if (!confirm.isConfirmed) return;

    const fd = new FormData();
    fd.append('_csrf_token', window.CSRF_TOKEN);

    try {
      const res  = await fetch(`${BASE}/categorias/${id}/eliminar`, {
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
  document.getElementById('btnGuardarTexto').style.display   = on ? 'none'   : 'inline';
  document.getElementById('btnGuardarSpinner').style.display = on ? 'inline' : 'none';
}
function limpiarErrores() {
  document.querySelectorAll('.form-error').forEach(el => el.textContent = '');
  document.querySelectorAll('.form-control').forEach(el => el.classList.remove('is-invalid'));
}
function mostrarErrores(errors) {
  Object.entries(errors).forEach(([campo, msg]) => {
    const el = document.getElementById('err' + campo.charAt(0).toUpperCase() + campo.slice(1));
    if (el) { el.textContent = msg; }
    const input = document.querySelector(`[name="${campo}"]`);
    if (input) input.classList.add('is-invalid');
  });
}

})();
</script>
<?php
$extraJs = ob_get_clean();
?>
