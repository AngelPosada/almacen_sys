<?php
if (!isset($config)) { $config = $GLOBALS['app_config'] ?? require ROOT_PATH . '/config/config.php'; }
/**
 * views/vales/create_salida.php
 * Variables: $productos, $empleados
 */

$baseUrl = e($config['app']['url']);
?>

<div class="page-header">
  <div>
    <h1 class="page-title">Nuevo vale de salida</h1>
    <p class="page-subtitle">
      Al emitir el vale, el stock se descuenta automáticamente.
    </p>
  </div>
  <a href="<?= $baseUrl ?>/vales" class="btn btn-ghost">
    <i class="ti ti-arrow-left"></i> Volver
  </a>
</div>

<div class="alert alert-warning" style="margin-bottom:1.25rem">
  <i class="ti ti-alert-triangle"></i>
  <span>
    Este documento es el <strong>único</strong> que descuenta stock del almacén.
    Verifica la información antes de emitirlo.
  </span>
</div>

<form id="formValeSalida" novalidate>
<?= Security::csrfField() ?>

<div style="display:grid;grid-template-columns:2fr 1fr;gap:1.25rem;align-items:start">

  <!-- Artículos -->
  <div class="card">
    <div class="card-header">
      <div class="card-title">Artículos a entregar</div>
      <button type="button" class="btn btn-primary btn-sm" id="btnAgregarItem">
        <i class="ti ti-plus"></i> Agregar artículo
      </button>
    </div>
    <div class="card-body" style="padding:0">
      <div id="contenedorItems">
        <div style="padding:3rem;text-align:center;color:var(--text-muted)" id="sinItems">
          <i class="ti ti-package-off" style="font-size:2rem;display:block;margin-bottom:.5rem"></i>
          Agrega al menos un artículo
        </div>
      </div>
      <div class="form-error" id="errItems" style="padding:.5rem 1.25rem;display:none"></div>

      <!-- Total -->
      <div id="totalVale" style="display:none;padding:1rem 1.25rem;
                                  border-top:1px solid var(--border-color);
                                  background:var(--bg-surface-2)">
        <div style="display:flex;justify-content:space-between">
          <span style="font-size:var(--font-size-sm);color:var(--text-muted)">Importe total estimado</span>
          <span style="font-weight:700;color:var(--color-primary)" id="lblImporteTotal">$0.00</span>
        </div>
      </div>
    </div>
  </div>

  <!-- Datos del vale -->
  <div style="display:flex;flex-direction:column;gap:1.25rem">
    <div class="card">
      <div class="card-header"><div class="card-title">Datos del vale</div></div>
      <div class="card-body">

        <div class="form-group">
          <label class="form-label required" for="valRef">Referencia</label>
          <input type="text" id="valRef" name="referencia"
                 class="form-control" maxlength="100"
                 placeholder="Concepto de la salida…">
          <div class="form-error" id="errReferencia"></div>
        </div>

        <div class="form-group">
          <label class="form-label required" for="valPlantel">Plantel / Área</label>
          <input type="text" id="valPlantel" name="plantel"
                 class="form-control" maxlength="150">
          <div class="form-error" id="errPlantel"></div>
        </div>

        <div class="form-group">
          <label class="form-label" for="valFecha">Fecha de emisión</label>
          <input type="date" id="valFecha" name="fecha_emision"
                 class="form-control" value="<?= date('Y-m-d') ?>">
        </div>

        <?php if (!empty($empleados)): ?>
        <div class="form-group">
          <label class="form-label" for="valEmpleado">Empleado receptor</label>
          <select id="valEmpleado" name="empleado_id" class="form-control">
            <option value="">— Opcional —</option>
            <?php foreach ($empleados as $e): ?>
            <option value="<?= (int)$e['id'] ?>">
              <?= e($e['numero_empleado'] . ' — ' . $e['nombre'] . ' ' . $e['apellidos']) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php endif; ?>

        <div class="form-group">
          <label class="form-label" for="valObs">Observaciones</label>
          <textarea id="valObs" name="observaciones"
                    class="form-control" rows="2"></textarea>
        </div>

      </div>
      <div class="card-footer">
        <button type="button" class="btn btn-primary w-100" id="btnGuardarVale">
          <span id="btnValeTexto">
            <i class="ti ti-device-floppy"></i> Guardar borrador
          </span>
          <span id="btnValeSpinner" style="display:none">
            <i class="ti ti-loader-2" style="animation:spin .75s linear infinite"></i>
          </span>
        </button>
        <p style="font-size:var(--font-size-xs);color:var(--text-muted);
                  margin-top:.625rem;text-align:center">
          Se guarda como borrador. El stock se descuenta al emitir.
        </p>
      </div>
    </div>
  </div>

</div>
</form>

<!-- Template de ítem -->
<template id="tmplItem">
  <div class="item-row" style="padding:1rem 1.25rem;border-bottom:1px solid var(--border-color);
                                display:grid;grid-template-columns:1fr auto;gap:.75rem;align-items:start">
    <div>
      <div class="form-group" style="margin-bottom:.625rem">
        <select name="producto_id[]" class="form-control sel-prod" required>
          <option value="">— Seleccionar producto —</option>
          <?php foreach ($productos as $p): ?>
          <option value="<?= (int)$p['id'] ?>"
                  data-upc="<?= (int)$p['unidades_por_caja'] ?>"
                  data-unidad="<?= e($p['unidad_medida']) ?>"
                  data-stock="<?= (int)$p['stock_actual'] ?>"
                  data-precio="<?= e($p['precio_unitario']) ?>">
            <?= e($p['codigo'] . ' — ' . $p['nombre']) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- Stock disponible -->
      <div class="hint-stock" style="display:none;margin-bottom:.5rem;
           padding:.375rem .75rem;background:var(--bg-surface-2);
           border-radius:var(--border-radius-sm);font-size:var(--font-size-xs)">
        Disponible: <strong class="txt-stock">—</strong>
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:.5rem;align-items:end">
        <div class="form-group" style="margin:0">
          <label class="form-label" style="font-size:var(--font-size-xs)">Cajas</label>
          <input type="number" name="cantidad_cajas[]"
                 class="form-control inp-cajas" value="0" min="0">
        </div>
        <div class="form-group" style="margin:0">
          <label class="form-label lbl-piezas" style="font-size:var(--font-size-xs)">Piezas</label>
          <input type="number" name="cantidad_piezas[]"
                 class="form-control inp-piezas" value="0" min="0">
        </div>
        <div style="font-size:var(--font-size-xs);color:var(--text-muted);
                    padding-bottom:.375rem" class="txt-total">—</div>
      </div>

      <!-- Descripción del ítem (para el impreso) -->
      <div class="form-group" style="margin-top:.5rem;margin-bottom:0">
        <input type="text" name="descripcion_item[]"
               class="form-control inp-desc"
               placeholder="Descripción para el impreso"
               style="font-size:var(--font-size-xs)">
      </div>
    </div>
    <button type="button" class="btn btn-ghost btn-icon btn-quitar"
            style="margin-top:1.5rem;color:var(--status-danger-text)" title="Quitar">
      <i class="ti ti-trash"></i>
    </button>
  </div>
</template>

<?php
ob_start();
?>
<script>
(function () {
'use strict';
const BASE = '<?= $baseUrl ?>';
let importeTotal = 0;

document.getElementById('btnAgregarItem').addEventListener('click', agregarFila);

function agregarFila() {
  const clone = document.getElementById('tmplItem').content.cloneNode(true);
  const row   = clone.querySelector('.item-row');
  const sel   = row.querySelector('.sel-prod');
  const inCaj = row.querySelector('.inp-cajas');
  const inPie = row.querySelector('.inp-piezas');
  const inDes = row.querySelector('.inp-desc');

  sel.addEventListener('change', function () {
    const opt   = this.options[this.selectedIndex];
    const stock = parseInt(opt.dataset.stock) || 0;
    const upc   = parseInt(opt.dataset.upc)   || 1;
    const unidad= opt.dataset.unidad || 'pieza';
    const hint  = row.querySelector('.hint-stock');

    if (this.value) {
      hint.style.display = 'flex';
      row.querySelector('.txt-stock').textContent = fmt(stock, upc, unidad);
      row.querySelector('.lbl-piezas').textContent =
        upc > 1 ? `${cap(unidad)}s sueltas` : `${cap(unidad)}s`;
      inDes.value = opt.text.split(' — ')[1] || '';
    } else {
      hint.style.display = 'none';
    }
    calcTotal();
  });

  [inCaj, inPie].forEach(el => el.addEventListener('input', calcTotal));

  function calcTotal() {
    const opt    = sel.options[sel.selectedIndex];
    const upc    = parseInt(opt?.dataset?.upc) || 1;
    const unidad = opt?.dataset?.unidad || 'pieza';
    const precio = parseFloat(opt?.dataset?.precio) || 0;
    const total  = (parseInt(inCaj.value)||0)*upc + (parseInt(inPie.value)||0);
    row.querySelector('.txt-total').textContent = total > 0 ? fmt(total, upc, unidad) : '—';
    actualizarImporte();
  }

  row.querySelector('.btn-quitar').addEventListener('click', function () {
    row.remove();
    actualizarImporte();
    if (document.querySelectorAll('.item-row').length === 0) {
      document.getElementById('sinItems').style.display = 'block';
      document.getElementById('totalVale').style.display = 'none';
    }
  });

  document.getElementById('sinItems').style.display = 'none';
  document.getElementById('totalVale').style.display = 'block';
  document.getElementById('contenedorItems').appendChild(row);
  sel.focus();
}

function actualizarImporte() {
  let total = 0;
  document.querySelectorAll('.item-row').forEach(row => {
    const sel    = row.querySelector('.sel-prod');
    const opt    = sel.options[sel.selectedIndex];
    const upc    = parseInt(opt?.dataset?.upc) || 1;
    const precio = parseFloat(opt?.dataset?.precio) || 0;
    const cant   = (parseInt(row.querySelector('.inp-cajas').value)||0)*upc
                 + (parseInt(row.querySelector('.inp-piezas').value)||0);
    total += cant * precio;
  });
  document.getElementById('lblImporteTotal').textContent =
    '$' + total.toLocaleString('es-MX', { minimumFractionDigits: 2 });
}

// Guardar
document.getElementById('btnGuardarVale').addEventListener('click', async function () {
  const err = document.getElementById('errItems');
  err.style.display = 'none';

  if (document.querySelectorAll('.item-row').length === 0) {
    err.textContent   = 'Agrega al menos un artículo.';
    err.style.display = 'block';
    return;
  }

  // Validar selección en cada fila
  let ok = true;
  document.querySelectorAll('.sel-prod').forEach(s => {
    if (!s.value) { s.classList.add('is-invalid'); ok = false; }
    else s.classList.remove('is-invalid');
  });
  if (!ok) { err.textContent = 'Selecciona el producto en cada fila.'; err.style.display = 'block'; return; }

  setLoading(true);
  const fd = new FormData(document.getElementById('formValeSalida'));
  try {
    const res  = await fetch(`${BASE}/vales/salida`, { method: 'POST', body: fd });
    const json = await res.json();
    if (json.success) {
      await SwalInst.fire({
        icon: 'success', title: json.message,
        html: `Folio: <b>${json.data.folio}</b>`,
        confirmButtonText: 'Ver vales',
      });
      window.location.href = `${BASE}/vales`;
    } else {
      const msg = json.errors?.stock ? json.errors.stock.join('<br>') : json.message;
      SwalInst.fire({ icon: 'error', title: 'Sin stock suficiente', html: msg });
    }
  } catch { SwalInst.fire({ icon: 'error', title: 'Error de conexión.' }); }
  finally { setLoading(false); }
});

function setLoading(on) {
  document.getElementById('btnGuardarVale').disabled = on;
  document.getElementById('btnValeTexto').style.display   = on ? 'none'   : 'inline';
  document.getElementById('btnValeSpinner').style.display = on ? 'inline' : 'none';
}
function fmt(p, upc, u) {
  if (upc <= 1) return `${p} ${u}(s)`;
  const c = Math.floor(p/upc), r = p%upc, pts = [];
  if (c>0) pts.push(`${c} caja${c>1?'s':''}`);
  if (r>0) pts.push(`${r} ${u}${r>1?'s':''}`);
  return pts.join(' + ') || '—';
}
function cap(s) { return s.charAt(0).toUpperCase() + s.slice(1); }

agregarFila();
})();
</script>
<?php $extraJs = ob_get_clean(); ?>
