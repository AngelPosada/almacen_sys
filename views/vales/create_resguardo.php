<?php
if (!isset($config)) { $config = $GLOBALS['app_config'] ?? require ROOT_PATH . '/config/config.php'; }
/**
 * views/vales/create_resguardo.php
 * Variables: $productos, $empleados
 */

$baseUrl = e($config['app']['url']);
?>

<div class="page-header">
  <div>
    <h1 class="page-title">Nuevo vale de resguardo</h1>
    <p class="page-subtitle">
      Registra artículos bajo resguardo de un empleado. No descuenta stock.
    </p>
  </div>
  <a href="<?= $baseUrl ?>/vales" class="btn btn-ghost">
    <i class="ti ti-arrow-left"></i> Volver
  </a>
</div>

<div class="alert alert-info" style="margin-bottom:1.25rem">
  <i class="ti ti-info-circle"></i>
  <span>
    El vale de resguardo <strong>no descuenta stock</strong>. Solo registra que los artículos
    quedan bajo la responsabilidad del empleado.
  </span>
</div>

<form id="formValeResguardo" novalidate>
<?= Security::csrfField() ?>

<div style="display:grid;grid-template-columns:2fr 1fr;gap:1.25rem;align-items:start">

  <!-- Artículos -->
  <div class="card">
    <div class="card-header">
      <div class="card-title">Artículos en resguardo</div>
      <button type="button" class="btn btn-primary btn-sm" id="btnAgregarItem">
        <i class="ti ti-plus"></i> Agregar artículo
      </button>
    </div>
    <div class="card-body" style="padding:0">
      <div id="contenedorItems">
        <div style="padding:3rem;text-align:center;color:var(--text-muted)" id="sinItems">
          <i class="ti ti-clipboard-list" style="font-size:2rem;display:block;margin-bottom:.5rem"></i>
          Agrega los artículos en resguardo
        </div>
      </div>
      <div class="form-error" id="errItems" style="padding:.5rem 1.25rem;display:none"></div>
    </div>
  </div>

  <!-- Datos -->
  <div style="display:flex;flex-direction:column;gap:1.25rem">
    <div class="card">
      <div class="card-header"><div class="card-title">Datos del resguardo</div></div>
      <div class="card-body">

        <div class="form-group">
          <label class="form-label required" for="resEmpleado">Empleado responsable</label>
          <select id="resEmpleado" name="empleado_id" class="form-control">
            <option value="">— Seleccionar —</option>
            <?php foreach ($empleados as $e): ?>
            <option value="<?= (int)$e['id'] ?>">
              <?= e($e['numero_empleado'] . ' — ' . $e['nombre'] . ' ' . $e['apellidos']) ?>
              <?= $e['puesto'] ? " / {$e['puesto']}" : '' ?>
            </option>
            <?php endforeach; ?>
          </select>
          <div class="form-error" id="errEmpleado_id"></div>
        </div>

        <div class="form-group">
          <label class="form-label required" for="resPlantel">Plantel / Área</label>
          <input type="text" id="resPlantel" name="plantel"
                 class="form-control" maxlength="150">
          <div class="form-error" id="errPlantel"></div>
        </div>

        <div class="form-group">
          <label class="form-label" for="resFecha">Fecha</label>
          <input type="date" id="resFecha" name="fecha_emision"
                 class="form-control" value="<?= date('Y-m-d') ?>">
        </div>

        <div class="form-group">
          <label class="form-label" for="resRef">Referencia</label>
          <input type="text" id="resRef" name="referencia"
                 class="form-control" maxlength="100"
                 placeholder="Motivo del resguardo…">
        </div>

        <div class="form-group">
          <label class="form-label" for="resObs">Observaciones</label>
          <textarea id="resObs" name="observaciones" class="form-control" rows="2"></textarea>
        </div>

      </div>
      <div class="card-footer">
        <button type="button" class="btn btn-primary w-100" id="btnGuardarResguardo">
          <span id="btnResTexto">
            <i class="ti ti-clipboard-check"></i> Guardar resguardo
          </span>
          <span id="btnResSpinner" style="display:none">
            <i class="ti ti-loader-2" style="animation:spin .75s linear infinite"></i>
          </span>
        </button>
      </div>
    </div>
  </div>

</div>
</form>

<!-- Template ítem -->
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
                  data-precio="<?= e($p['precio_unitario']) ?>">
            <?= e($p['codigo'] . ' — ' . $p['nombre']) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:.5rem;align-items:end">
        <div class="form-group" style="margin:0">
          <label class="form-label" style="font-size:var(--font-size-xs)">Cajas</label>
          <input type="number" name="cantidad_cajas[]" class="form-control inp-cajas" value="0" min="0">
        </div>
        <div class="form-group" style="margin:0">
          <label class="form-label lbl-piezas" style="font-size:var(--font-size-xs)">Piezas</label>
          <input type="number" name="cantidad_piezas[]" class="form-control inp-piezas" value="0" min="0">
        </div>
        <div style="font-size:var(--font-size-xs);color:var(--text-muted);padding-bottom:.375rem" class="txt-total">—</div>
      </div>
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
    const upc   = parseInt(opt.dataset.upc) || 1;
    const unidad= opt.dataset.unidad || 'pieza';
    row.querySelector('.lbl-piezas').textContent = upc > 1 ? `${cap(unidad)}s sueltas` : `${cap(unidad)}s`;
    if (this.value) inDes.value = opt.text.split(' — ')[1] || '';
    calc();
  });

  [inCaj, inPie].forEach(el => el.addEventListener('input', calc));

  function calc() {
    const opt   = sel.options[sel.selectedIndex];
    const upc   = parseInt(opt?.dataset?.upc) || 1;
    const unidad= opt?.dataset?.unidad || 'pieza';
    const total = (parseInt(inCaj.value)||0)*upc + (parseInt(inPie.value)||0);
    row.querySelector('.txt-total').textContent = total > 0 ? fmt(total, upc, unidad) : '—';
  }

  row.querySelector('.btn-quitar').addEventListener('click', function () {
    row.remove();
    if (!document.querySelectorAll('.item-row').length) {
      document.getElementById('sinItems').style.display = 'block';
    }
  });

  document.getElementById('sinItems').style.display = 'none';
  document.getElementById('contenedorItems').appendChild(row);
  sel.focus();
}

document.getElementById('btnGuardarResguardo').addEventListener('click', async function () {
  const err = document.getElementById('errItems');
  err.style.display = 'none';
  if (!document.querySelectorAll('.item-row').length) {
    err.textContent = 'Agrega al menos un artículo.'; err.style.display = 'block'; return;
  }
  let ok = true;
  document.querySelectorAll('.sel-prod').forEach(s => {
    if (!s.value) { s.classList.add('is-invalid'); ok = false; }
    else s.classList.remove('is-invalid');
  });
  if (!ok) { err.textContent = 'Selecciona el producto en cada fila.'; err.style.display = 'block'; return; }

  document.getElementById('btnGuardarResguardo').disabled = true;
  document.getElementById('btnResTexto').style.display   = 'none';
  document.getElementById('btnResSpinner').style.display = 'inline';

  try {
    const res  = await fetch(`${BASE}/vales/resguardo`, {
      method: 'POST', body: new FormData(document.getElementById('formValeResguardo')),
    });
    const json = await res.json();
    if (json.success) {
      await SwalInst.fire({
        icon: 'success', title: json.message, html: `Folio: <b>${json.data.folio}</b>`,
        confirmButtonText: 'Ver vales',
      });
      window.location.href = `${BASE}/vales`;
    } else {
      SwalInst.fire({ icon: 'error', title: json.message || 'Error al guardar.' });
    }
  } catch { SwalInst.fire({ icon: 'error', title: 'Error de conexión.' }); }
  finally {
    document.getElementById('btnGuardarResguardo').disabled = false;
    document.getElementById('btnResTexto').style.display   = 'inline';
    document.getElementById('btnResSpinner').style.display = 'none';
  }
});

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
