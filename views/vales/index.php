<?php
if (!isset($config)) { $config = $GLOBALS['app_config'] ?? require ROOT_PATH . '/config/config.php'; }
/**
 * views/vales/index.php
 * Variables: $vales, $paginacion, $filtros
 */

$baseUrl = e($config['app']['url']);
$rol     = (int) ($_SESSION['usuario_rol'] ?? 3);
?>

<div class="page-header">
  <div>
    <h1 class="page-title">Vales</h1>
    <p class="page-subtitle">Vales de salida y resguardo del almacén</p>
  </div>
  <?php if ($rol <= 2): ?>
  <div style="display:flex;gap:.625rem">
    <a href="<?= $baseUrl ?>/vales/salida/nuevo" class="btn btn-primary">
      <i class="ti ti-receipt" aria-hidden="true"></i> Vale de salida
    </a>
    <a href="<?= $baseUrl ?>/vales/resguardo/nuevo" class="btn btn-outline-primary">
      <i class="ti ti-clipboard-list" aria-hidden="true"></i> Resguardo
    </a>
  </div>
  <?php endif; ?>
</div>

<!-- Filtros -->
<div class="card" style="margin-bottom:1.25rem">
  <div class="card-body" style="padding:1rem 1.25rem">
    <form method="GET" style="display:flex;gap:.75rem;flex-wrap:wrap;align-items:flex-end">
      <div style="min-width:140px">
        <label class="form-label" for="filTipo">Tipo</label>
        <select id="filTipo" name="tipo" class="form-control">
          <option value="">Todos</option>
          <option value="salida"    <?= ($filtros['tipo']??'') === 'salida'    ? 'selected':'' ?>>Salida</option>
          <option value="resguardo" <?= ($filtros['tipo']??'') === 'resguardo' ? 'selected':'' ?>>Resguardo</option>
        </select>
      </div>
      <div style="min-width:140px">
        <label class="form-label" for="filEstado">Estado</label>
        <select id="filEstado" name="estado" class="form-control">
          <option value="">Todos</option>
          <option value="borrador"  <?= ($filtros['estado']??'') === 'borrador'  ? 'selected':'' ?>>Borrador</option>
          <option value="emitido"   <?= ($filtros['estado']??'') === 'emitido'   ? 'selected':'' ?>>Emitido</option>
          <option value="cancelado" <?= ($filtros['estado']??'') === 'cancelado' ? 'selected':'' ?>>Cancelado</option>
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
          <i class="ti ti-filter"></i> Filtrar
        </button>
        <a href="<?= $baseUrl ?>/vales" class="btn btn-ghost">
          <i class="ti ti-x"></i>
        </a>
      </div>
    </form>
  </div>
</div>

<!-- Tabla -->
<div class="card">
  <div class="card-body" style="padding:0">
    <?php if (empty($vales)): ?>
    <div style="padding:3rem;text-align:center;color:var(--text-muted)">
      <i class="ti ti-receipt-off" style="font-size:2.5rem;display:block;margin-bottom:.75rem"></i>
      No hay vales registrados
    </div>
    <?php else: ?>
    <div class="table-container" style="border:none;border-radius:0">
      <table id="tablaVales" class="table" style="width:100%">
        <thead>
          <tr>
            <th>Folio</th>
            <th>Tipo</th>
            <th>Referencia / Plantel</th>
            <th>Para</th>
            <th style="text-align:right">Importe</th>
            <th>Fecha</th>
            <th style="text-align:center">Estado</th>
            <th style="text-align:center;width:120px">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($vales as $v): ?>
          <tr>
            <td>
              <span style="font-family:monospace;font-weight:700;
                           color:var(--color-primary);font-size:var(--font-size-sm)">
                <?= e($v['folio']) ?>
              </span>
            </td>
            <td>
              <span class="badge <?= e($v['tipo_clase']) ?>">
                <?= e($v['tipo_label']) ?>
              </span>
            </td>
            <td>
              <div style="font-size:var(--font-size-sm);font-weight:500">
                <?= e($v['referencia'] ?? '—') ?>
              </div>
              <?php if ($v['plantel']): ?>
              <div style="font-size:var(--font-size-xs);color:var(--text-muted)">
                <?= e($v['plantel']) ?>
              </div>
              <?php endif; ?>
            </td>
            <td style="font-size:var(--font-size-sm);color:var(--text-muted)">
              <?= e(trim($v['empleado_nombre']) ?: '—') ?>
            </td>
            <td style="text-align:right;font-weight:600;font-size:var(--font-size-sm)">
              <?= e($v['importe_fmt']) ?>
            </td>
            <td style="font-size:var(--font-size-sm)">
              <?= e($v['fecha_fmt']) ?>
            </td>
            <td style="text-align:center">
              <span class="badge <?= e($v['estado_clase']) ?>">
                <?= e($v['estado_label']) ?>
              </span>
            </td>
            <td style="text-align:center">
              <div class="table-actions" style="justify-content:center">
                <a href="<?= $baseUrl ?>/vales/<?= (int)$v['id'] ?>/pdf"
                   target="_blank"
                   class="btn btn-ghost btn-icon" title="Ver / imprimir">
                  <i class="ti ti-printer"></i>
                </a>
                <?php if ($rol <= 2 && $v['puede_emitir']): ?>
                <button class="btn btn-ghost btn-icon btn-emitir"
                        title="Emitir vale"
                        style="color:var(--color-primary)"
                        data-id="<?= (int)$v['id'] ?>"
                        data-folio="<?= e($v['folio']) ?>"
                        data-tipo="<?= e($v['tipo']) ?>">
                  <i class="ti ti-send"></i>
                </button>
                <?php endif; ?>
                <?php if ($rol <= 2 && $v['puede_cancelar']): ?>
                <button class="btn btn-ghost btn-icon btn-cancelar-vale"
                        title="Cancelar"
                        style="color:var(--status-danger-text)"
                        data-id="<?= (int)$v['id'] ?>"
                        data-folio="<?= e($v['folio']) ?>">
                  <i class="ti ti-x"></i>
                </button>
                <?php endif; ?>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>

  <?php if (($paginacion['paginas'] ?? 0) > 1): ?>
  <div class="card-footer" style="display:flex;align-items:center;justify-content:space-between">
    <div style="font-size:var(--font-size-sm);color:var(--text-muted)">
      <?= number_format($paginacion['total']) ?> vales en total
    </div>
    <div style="display:flex;gap:.375rem">
      <?php for ($i = 1; $i <= $paginacion['paginas']; $i++): ?>
      <?php $qs = http_build_query(array_merge($filtros, ['pagina' => $i])); ?>
      <a href="?<?= $qs ?>"
         class="btn btn-sm <?= $i === $paginacion['pagina_actual'] ? 'btn-primary' : 'btn-ghost' ?>">
        <?= $i ?>
      </a>
      <?php endfor; ?>
    </div>
  </div>
  <?php endif; ?>
</div>

<!-- Modal: nombre de quien recibe (para emitir) -->
<div class="modal-backdrop" id="modalEmitir">
  <div class="modal">
    <div class="modal-header">
      <h2 class="modal-title" id="modalEmitirTitulo">Emitir vale</h2>
      <button class="modal-close" id="btnCerrarEmitir" aria-label="Cerrar">
        <i class="ti ti-x"></i>
      </button>
    </div>
    <div class="modal-body">
      <div id="avisoSalida" class="alert alert-warning" style="margin-bottom:1rem">
        <i class="ti ti-alert-triangle"></i>
        <span>Al emitir este vale de <strong>salida</strong>, el stock se descontará
        inmediatamente. Esta acción no se puede deshacer.</span>
      </div>
      <div class="form-group">
        <label class="form-label" for="inputRecibio">Nombre de quien recibe</label>
        <input type="text" id="inputRecibio" class="form-control"
               placeholder="Nombre completo del receptor" maxlength="200">
        <div class="form-hint">Aparecerá impreso en el vale como "Nombre y Firma de Recibido".</div>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" id="btnCancelarEmitir">Cancelar</button>
      <button class="btn btn-primary" id="btnConfirmarEmitir">
        <i class="ti ti-send"></i> Emitir vale
      </button>
    </div>
  </div>
</div>

<?php
ob_start();
?>
<script>
(function () {
'use strict';
const BASE = '<?= $baseUrl ?>';

$('#tablaVales').DataTable({
  language: { url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json' },
  pageLength: 25,
  order: [[0, 'desc']],
  columnDefs: [{ orderable: false, targets: [6, 7] }],
  dom: 'tp',
});

// ── Modal Emitir ──
const modal    = document.getElementById('modalEmitir');
let valeActivo = null;

document.querySelectorAll('.btn-emitir').forEach(btn => {
  btn.addEventListener('click', function () {
    valeActivo = { id: this.dataset.id, folio: this.dataset.folio, tipo: this.dataset.tipo };
    document.getElementById('modalEmitirTitulo').textContent = `Emitir ${this.dataset.folio}`;
    document.getElementById('avisoSalida').style.display =
      this.dataset.tipo === 'salida' ? 'flex' : 'none';
    document.getElementById('inputRecibio').value = '';
    modal.classList.add('open');
    document.getElementById('inputRecibio').focus();
  });
});

['btnCerrarEmitir', 'btnCancelarEmitir'].forEach(id => {
  document.getElementById(id)?.addEventListener('click', () => modal.classList.remove('open'));
});

document.getElementById('btnConfirmarEmitir').addEventListener('click', async function () {
  if (!valeActivo) return;
  this.disabled = true;
  this.innerHTML = '<i class="ti ti-loader-2" style="animation:spin .75s linear infinite"></i> Emitiendo…';

  const fd = new FormData();
  fd.append('_csrf_token', window.CSRF_TOKEN);
  fd.append('recibio_nombre', document.getElementById('inputRecibio').value);

  try {
    const res  = await fetch(`${BASE}/vales/${valeActivo.id}/enviar`, { method: 'POST', body: fd });
    const json = await res.json();
    modal.classList.remove('open');

    if (json.success) {
      await SwalInst.fire({
        icon: 'success', title: json.message, timer: 2000, showConfirmButton: false,
      });
      location.reload();
    } else {
      SwalInst.fire({ icon: 'error', title: json.message });
    }
  } catch {
    SwalInst.fire({ icon: 'error', title: 'Error de conexión.' });
  } finally {
    this.disabled = false;
    this.innerHTML = '<i class="ti ti-send"></i> Emitir vale';
  }
});

// ── Cancelar vale ──
document.querySelectorAll('.btn-cancelar-vale').forEach(btn => {
  btn.addEventListener('click', async function () {
    const { isConfirmed } = await SwalInst.fire({
      icon:              'warning',
      title:             `Cancelar ${this.dataset.folio}`,
      text:              'El vale será cancelado y no podrá emitirse.',
      showCancelButton:  true,
      confirmButtonText: 'Sí, cancelar',
      cancelButtonText:  'Volver',
    });
    if (!isConfirmed) return;

    const fd = new FormData();
    fd.append('_csrf_token', window.CSRF_TOKEN);

    try {
      const res  = await fetch(`${BASE}/vales/${this.dataset.id}/enviar`, { method: 'POST', body: fd });
      // cancelar usa endpoint diferente — llamar via JS custom
      const res2 = await fetch(`${BASE}/vales/${this.dataset.id}/cancelar`, { method: 'POST',
        body: new URLSearchParams({ _csrf_token: window.CSRF_TOKEN }) });
      const json = await res2.json();
      if (json.success) {
        await SwalInst.fire({ icon: 'success', title: json.message, timer: 1800, showConfirmButton: false });
        location.reload();
      } else {
        SwalInst.fire({ icon: 'error', title: json.message });
      }
    } catch {
      SwalInst.fire({ icon: 'error', title: 'Error de conexión.' });
    }
  });
});

})();
</script>
<?php $extraJs = ob_get_clean(); ?>
