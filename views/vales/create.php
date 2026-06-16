<?php
if (!isset($config)) { $config = $GLOBALS['app_config'] ?? require ROOT_PATH . '/config/config.php'; }
/**
 * views/vales/create.php
 *
 * Formulario compartido para Vale de Salida y Vale de Resguardo.
 * El tipo se controla con la variable $tipo ('salida' | 'resguardo').
 * Variables: $tipo, $productos, $empleados
 */

$baseUrl  = e($config['app']['url']);
$esSalida = ($tipo ?? 'salida') === 'salida';
$urlPost  = $baseUrl . ($esSalida ? '/vales/salida' : '/vales/resguardo');
?>

<div class="page-header">
  <div>
    <h1 class="page-title">
      <?= $esSalida ? 'Nuevo vale de salida' : 'Nuevo vale de resguardo' ?>
    </h1>
    <p class="page-subtitle">
      <?= $esSalida
        ? 'Emite la salida de materiales y descuenta el stock automáticamente'
        : 'Registra artículos en resguardo de un empleado sin descontar stock' ?>
    </p>
  </div>
  <a href="<?= $baseUrl ?>/vales" class="btn btn-ghost">
    <i class="ti ti-arrow-left" aria-hidden="true"></i> Volver
  </a>
</div>

<?php if ($esSalida): ?>
<div class="alert alert-warning" style="margin-bottom:1.25rem">
  <i class="ti ti-alert-triangle" aria-hidden="true"></i>
  <span>
    <strong>Atención:</strong> Al emitir este vale, el stock se descontará
    inmediatamente y de forma permanente. Verifica las cantidades antes de confirmar.
  </span>
</div>
<?php else: ?>
<div class="alert alert-info" style="margin-bottom:1.25rem">
  <i class="ti ti-info-circle" aria-hidden="true"></i>
  <span>
    El vale de resguardo <strong>no descuenta stock</strong>.
    Solo registra la asignación de artículos a un empleado para su custodia.
  </span>
</div>
<?php endif; ?>

<form id="formVale" novalidate>
<?= Security::csrfField() ?>

<div style="display:grid;grid-template-columns:2fr 1fr;gap:1.25rem;align-items:start">

  <!-- ─ Artículos ─ -->
  <div style="display:flex;flex-direction:column;gap:1.25rem">
    <div class="card">
      <div class="card-header">
        <div class="card-title">Artículos del vale</div>
        <button type="button" class="btn btn-primary btn-sm" id="btnAgregarItem">
          <i class="ti ti-plus" aria-hidden="true"></i> Agregar artículo
        </button>
      </div>
      <div class="card-body" style="padding:0">

        <div id="contenedorItems">
          <div style="padding:2.5rem;text-align:center;color:var(--text-muted)" id="sinItems">
            <i class="ti ti-package-off"
               style="font-size:2rem;display:block;margin-bottom:.5rem" aria-hidden="true"></i>
            Agrega artículos al vale
          </div>
        </div>

        <div class="form-error" id="errItems"
             style="padding:.5rem 1.25rem;display:none"></div>

        <!-- Totalizador -->
        <div id="totalVale"
             style="display:none;padding:1rem 1.25rem;
                    border-top:1px solid var(--border-color);
                    background:var(--bg-surface-2)">
          <div style="display:flex;justify-content:space-between">
            <span style="font-size:var(--font-size-sm);color:var(--text-muted)">
              Importe estimado total
            </span>
            <span style="font-weight:700;color:var(--color-primary)"
                  id="lblImporteTotal">
              $0.00
            </span>
          </div>
        </div>

      </div>
    </div>
  </div>

  <!-- ─ Datos del vale ─ -->
  <div style="display:flex;flex-direction:column;gap:1.25rem">
    <div class="card">
      <div class="card-header">
        <div class="card-title">Datos del documento</div>
      </div>
      <div class="card-body">

        <!-- Referencia / Concepto -->
        <div class="form-group">
          <label class="form-label <?= $esSalida ? 'required' : '' ?>"
                 for="valeRef">
            <?= $esSalida ? 'Referencia / Concepto' : 'Concepto' ?>
          </label>
          <input type="text" id="valeRef" name="referencia"
                 class="form-control" maxlength="100"
                 placeholder="Ej: Suministros primer trimestre">
          <div class="form-error" id="errReferencia"></div>
        </div>

        <!-- Fecha de emisión -->
        <div class="form-group">
          <label class="form-label required" for="valeFecha">
            Fecha de emisión
          </label>
          <input type="date" id="valeFecha" name="fecha_emision"
                 class="form-control"
                 value="<?= date('Y-m-d') ?>">
        </div>

        <!-- Plantel -->
        <div class="form-group">
          <label class="form-label" for="valePlantel">Plantel / Área</label>
          <input type="text" id="valePlantel" name="plantel"
                 class="form-control" maxlength="150"
                 placeholder="Ej: Plantel 01 Durango">
        </div>

        <!-- Empleado que recibe -->
        <?php if (!empty($empleados)): ?>
        <div class="form-group">
          <label class="form-label" for="valeEmpleado">
            <?= $esSalida ? 'Recibido por (empleado)' : 'Empleado en resguardo' ?>
          </label>
          <select id="valeEmpleado" name="empleado_id" class="form-control">
            <option value="">— Opcional —</option>
            <?php foreach ($empleados as $emp): ?>
            <option value="<?= (int) $emp['id'] ?>">
              <?= e($emp['numero_empleado'] . ' — ' . $emp['nombre'] . ' ' . $emp['apellidos']) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php endif; ?>

        <!-- Nombre y firma de quien recibe -->
        <div class="form-group">
          <label class="form-label" for="valeRecibio">
            Nombre completo de quien recibe
          </label>
          <input type="text" id="valeRecibio" name="recibio_nombre"
                 class="form-control" maxlength="200"
                 placeholder="Nombre tal como aparecerá en el documento">
          <div class="form-hint">Para el campo de firma del documento impreso.</div>
        </div>

        <!-- Observaciones -->
        <div class="form-group">
          <label class="form-label" for="valeObs">Observaciones</label>
          <textarea id="valeObs" name="observaciones"
                    class="form-control" rows="2"
                    placeholder="Notas adicionales para el documento…"></textarea>
        </div>

      </div>
      <div class="card-footer">
        <button type="button" class="btn btn-primary w-100"
                id="btnEmitirVale">
          <span id="btnValeTexto">
            <i class="ti ti-<?= $esSalida ? 'arrow-up-right' : 'clipboard-check' ?>"
               aria-hidden="true"></i>
            Emitir vale<?= $esSalida ? ' y descontar stock' : ' de resguardo' ?>
          </span>
          <span id="btnValeSpinner" style="display:none">
            <i class="ti ti-loader-2"
               style="animation:spin .75s linear infinite"></i>
            Procesando…
          </span>
        </button>
      </div>
    </div>
  </div>

</div>
</form>

<!-- Template de fila de ítem -->
<template id="templateItem">
  <div class="item-row"
       style="padding:1rem 1.25rem;border-bottom:1px solid var(--border-color)">
    <div style="display:grid;grid-template-columns:1fr auto;gap:.75rem;align-items:start">
      <div>
        <!-- Selector de producto -->
        <div class="form-group" style="margin-bottom:.625rem">
          <select name="producto_id[]" class="form-control sel-producto" required>
            <option value="">— Seleccionar producto —</option>
            <?php foreach ($productos as $p): ?>
            <option value="<?= (int) $p['id'] ?>"
                    data-upc="<?= (int) $p['unidades_por_caja'] ?>"
                    data-unidad="<?= e($p['unidad_medida']) ?>"
                    data-precio="<?= (float) $p['precio_unitario'] ?>"
                    data-stock="<?= (int) $p['stock_actual'] ?>">
              <?= e($p['codigo'] . ' — ' . $p['nombre']) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- Cantidad -->
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:.5rem;align-items:end">
          <div class="form-group" style="margin:0">
            <label class="form-label" style="font-size:var(--font-size-xs)">Cajas</label>
            <input type="number" name="cantidad_cajas[]"
                   class="form-control inp-cajas" value="0" min="0">
          </div>
          <div class="form-group" style="margin:0">
            <label class="form-label lbl-piezas" style="font-size:var(--font-size-xs)">
              Piezas
            </label>
            <input type="number" name="cantidad_piezas[]"
                   class="form-control inp-piezas" value="0" min="0">
          </div>
          <div style="font-size:var(--font-size-xs);padding-bottom:.375rem;
                      font-weight:600;color:var(--color-primary)"
               class="txt-total">—</div>
        </div>

        <!-- Stock disponible e importe estimado -->
        <div class="item-info"
             style="margin-top:.375rem;font-size:var(--font-size-xs);
                    color:var(--text-muted);display:none">
          Disponible: <strong class="txt-stock">—</strong>
          &nbsp;|&nbsp;
          Importe est.: <strong class="txt-importe">$0.00</strong>
        </div>

        <!-- Descripción personalizada -->
        <div class="form-group" style="margin-top:.5rem;margin-bottom:0">
          <input type="text" name="descripcion_item[]"
                 class="form-control"
                 placeholder="Descripción para el impreso (opcional)"
                 style="font-size:var(--font-size-xs)">
        </div>
      </div>
      <button type="button"
              class="btn btn-ghost btn-icon btn-quitar"
              style="margin-top:1.5rem;color:var(--status-danger-text)"
              title="Quitar">
        <i class="ti ti-trash" aria-hidden="true"></i>
      </button>
    </div>
  </div>
</template>

<?php
ob_start();
?>
<script>
(function () {
'use strict';

const BASE     = '<?= $baseUrl ?>';
const URL_POST = '<?= $urlPost ?>';
let numItems   = 0;

document.getElementById('btnAgregarItem').addEventListener('click', agregarFila);

function agregarFila() {
  const tmpl  = document.getElementById('templateItem');
  const clone = tmpl.content.cloneNode(true);
  const row   = clone.querySelector('.item-row');
  const sel   = row.querySelector('.sel-producto');
  const inCaj = row.querySelector('.inp-cajas');
  const inPie = row.querySelector('.inp-piezas');
  const txtTo = row.querySelector('.txt-total');
  const info  = row.querySelector('.item-info');
  const txtSt = row.querySelector('.txt-stock');
  const txtIm = row.querySelector('.txt-importe');
  const lblPie= row.querySelector('.lbl-piezas');

  let upcActual = 1, unidadActual = 'pieza', precioActual = 0, stockActual = 0;

  sel.addEventListener('change', function () {
    const opt = this.options[this.selectedIndex];
    upcActual    = parseInt(opt.dataset.upc)    || 1;
    unidadActual = opt.dataset.unidad || 'pieza';
    precioActual = parseFloat(opt.dataset.precio) || 0;
    stockActual  = parseInt(opt.dataset.stock)  || 0;

    if (this.value) {
      info.style.display = 'block';
      txtSt.textContent  = fmtTexto(stockActual, upcActual, unidadActual);
      lblPie.textContent = upcActual > 1
        ? capitalize(unidadActual) + 's sueltas'
        : capitalize(unidadActual) + 's';
    } else {
      info.style.display = 'none';
    }
    recalcular();
  });

  [inCaj, inPie].forEach(el => el.addEventListener('input', recalcular));

  function recalcular() {
    const total   = (parseInt(inCaj.value)||0) * upcActual + (parseInt(inPie.value)||0);
    const importe = total * precioActual;
    txtTo.textContent  = total > 0 ? fmtTexto(total, upcActual, unidadActual) : '—';
    txtIm.textContent  = '$' + importe.toFixed(2);
    actualizarTotal();
  }

  row.querySelector('.btn-quitar').addEventListener('click', function () {
    row.remove();
    numItems--;
    actualizarTotal();
    if (numItems === 0) {
      document.getElementById('sinItems').style.display = 'block';
      document.getElementById('totalVale').style.display = 'none';
    }
  });

  document.getElementById('sinItems').style.display = 'none';
  document.getElementById('totalVale').style.display = 'block';
  document.getElementById('contenedorItems').appendChild(row);
  numItems++;
  sel.focus();
}

function actualizarTotal() {
  let total = 0;
  document.querySelectorAll('.item-row').forEach(row => {
    const sel   = row.querySelector('.sel-producto');
    const inCaj = row.querySelector('.inp-cajas');
    const inPie = row.querySelector('.inp-piezas');
    const opt   = sel.options[sel.selectedIndex];
    const upc   = parseInt(opt?.dataset?.upc)    || 1;
    const precio= parseFloat(opt?.dataset?.precio) || 0;
    const cant  = (parseInt(inCaj?.value)||0) * upc + (parseInt(inPie?.value)||0);
    total += cant * precio;
  });
  document.getElementById('lblImporteTotal').textContent = '$' + total.toFixed(2);
}

// ── Emitir vale ──
document.getElementById('btnEmitirVale').addEventListener('click', async function () {
  const errDiv = document.getElementById('errItems');
  errDiv.style.display = 'none';

  if (document.querySelectorAll('.item-row').length === 0) {
    errDiv.textContent   = 'Agrega al menos un artículo al vale.';
    errDiv.style.display = 'block';
    return;
  }

  let valido = true;
  document.querySelectorAll('.sel-producto').forEach(sel => {
    if (!sel.value) { sel.classList.add('is-invalid'); valido = false; }
    else sel.classList.remove('is-invalid');
  });
  if (!valido) {
    errDiv.textContent   = 'Selecciona el producto en cada artículo.';
    errDiv.style.display = 'block';
    return;
  }

  <?php if ($esSalida): ?>
  // Confirmación extra para vales de salida (irreversible)
  const confirm = await SwalInst.fire({
    icon:              'warning',
    title:             '¿Confirmar emisión del vale?',
    html:              'El stock se <b>descontará inmediatamente</b>.<br>' +
                       'Esta acción <b>no se puede deshacer</b>.',
    showCancelButton:  true,
    confirmButtonText: 'Sí, emitir y descontar',
    cancelButtonText:  'Revisar',
  });
  if (!confirm.isConfirmed) return;
  <?php endif; ?>

  setLoading(true);
  const fd = new FormData(document.getElementById('formVale'));

  try {
    const res  = await fetch(URL_POST, { method: 'POST', body: fd });
    const json = await res.json();

    if (json.success) {
      await SwalInst.fire({
        icon:              'success',
        title:             json.message,
        html:              `Folio: <b>${json.data.folio}</b>`,
        showCancelButton:  true,
        confirmButtonText: 'Ver / Imprimir',
        cancelButtonText:  'Ir a vales',
      }).then(r => {
        if (r.isConfirmed) {
          window.open(`${BASE}/vales/${json.data.id}/pdf`, '_blank');
        }
        window.location.href = `${BASE}/vales`;
      });
    } else {
      if (json.errors?.items || json.errors?.general) {
        errDiv.textContent   = json.errors.items || json.errors.general;
        errDiv.style.display = 'block';
      }
      SwalInst.fire({ icon: 'error', title: json.message || 'Error al emitir el vale.' });
    }
  } catch {
    SwalInst.fire({ icon: 'error', title: 'Error de conexión.' });
  } finally {
    setLoading(false);
  }
});

function setLoading(on) {
  document.getElementById('btnEmitirVale').disabled = on;
  document.getElementById('btnValeTexto').style.display   = on ? 'none'   : 'inline';
  document.getElementById('btnValeSpinner').style.display = on ? 'inline' : 'none';
}
function fmtTexto(piezas, upc, unidad) {
  if (upc <= 1) return `${piezas} ${unidad}(s)`;
  const c = Math.floor(piezas / upc), r = piezas % upc, p = [];
  if (c > 0) p.push(`${c} caja${c>1?'s':''}`);
  if (r > 0) p.push(`${r} ${unidad}${r>1?'s':''}`);
  return p.join(' + ') || '—';
}
function capitalize(s) { return s.charAt(0).toUpperCase() + s.slice(1); }

agregarFila(); // Una fila vacía al cargar
})();
</script>
<?php $extraJs = ob_get_clean(); ?>
