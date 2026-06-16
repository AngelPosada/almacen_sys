<?php
if (!isset($config)) { $config = $GLOBALS['app_config'] ?? require ROOT_PATH . '/config/config.php'; }
/**
 * views/pedidos/create.php
 *
 * Formulario de creación de pedido.
 * Permite agregar múltiples productos dinámicamente.
 * Variables: $productos, $empleados
 */

$baseUrl = e($config['app']['url']);
?>

<div class="page-header">
  <div>
    <h1 class="page-title">Nuevo pedido</h1>
    <p class="page-subtitle">Solicita materiales al almacén</p>
  </div>
  <a href="<?= $baseUrl ?>/pedidos" class="btn btn-ghost">
    <i class="ti ti-arrow-left" aria-hidden="true"></i> Volver
  </a>
</div>

<form id="formPedido" novalidate>
<?= Security::csrfField() ?>

<div style="display:grid;grid-template-columns:2fr 1fr;gap:1.25rem;align-items:start">

  <!-- ─ Panel izquierdo: ítems ─ -->
  <div style="display:flex;flex-direction:column;gap:1.25rem">

    <div class="card">
      <div class="card-header">
        <div class="card-title">Artículos solicitados</div>
        <button type="button" class="btn btn-primary btn-sm" id="btnAgregarItem">
          <i class="ti ti-plus" aria-hidden="true"></i> Agregar artículo
        </button>
      </div>
      <div class="card-body" style="padding:0">

        <!-- Tabla de ítems dinámica -->
        <div id="contenedorItems">
          <!-- Fila vacía inicial -->
          <div style="padding:3rem;text-align:center;color:var(--text-muted)" id="sinItems">
            <i class="ti ti-package-off"
               style="font-size:2rem;display:block;margin-bottom:.5rem" aria-hidden="true"></i>
            Agrega al menos un artículo al pedido
          </div>
        </div>

        <div class="form-error" id="errItems"
             style="padding:.5rem 1.25rem;display:none"></div>

        <!-- Totalizador -->
        <div id="totalPedido"
             style="display:none;padding:1rem 1.25rem;
                    border-top:1px solid var(--border-color);
                    background:var(--bg-surface-2)">
          <div style="display:flex;justify-content:space-between;align-items:center">
            <span style="font-size:var(--font-size-sm);color:var(--text-muted)">
              Total de artículos
            </span>
            <span style="font-weight:700;color:var(--color-primary)" id="lblTotalItems">
              0 artículos
            </span>
          </div>
        </div>

      </div>
    </div>

  </div>

  <!-- ─ Panel derecho: datos del pedido ─ -->
  <div style="display:flex;flex-direction:column;gap:1.25rem">

    <div class="card">
      <div class="card-header">
        <div class="card-title">Datos del pedido</div>
      </div>
      <div class="card-body">

        <!-- Prioridad -->
        <div class="form-group">
          <label class="form-label">Prioridad</label>
          <div style="display:flex;gap:.75rem">
            <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer;
                          flex:1;padding:.625rem .875rem;
                          border:1.5px solid var(--border-color);
                          border-radius:var(--border-radius-sm);
                          transition:all var(--transition)" id="lblNormal">
              <input type="radio" name="prioridad" value="normal" checked
                     onchange="togglePrioridad(this)">
              <span style="font-size:var(--font-size-sm)">Normal</span>
            </label>
            <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer;
                          flex:1;padding:.625rem .875rem;
                          border:1.5px solid var(--border-color);
                          border-radius:var(--border-radius-sm);
                          transition:all var(--transition)" id="lblUrgente">
              <input type="radio" name="prioridad" value="urgente"
                     onchange="togglePrioridad(this)">
              <i class="ti ti-flame" style="color:var(--color-accent)" aria-hidden="true"></i>
              <span style="font-size:var(--font-size-sm)">Urgente</span>
            </label>
          </div>
        </div>

        <!-- Fecha requerida -->
        <div class="form-group">
          <label class="form-label" for="fechaReq">Fecha requerida</label>
          <input type="date" id="fechaReq" name="fecha_requerida"
                 class="form-control"
                 min="<?= date('Y-m-d') ?>">
          <div class="form-hint">Opcional — cuándo necesitas el material</div>
        </div>

        <!-- Plantel -->
        <div class="form-group">
          <label class="form-label" for="pedPlantel">Plantel / Área</label>
          <input type="text" id="pedPlantel" name="plantel"
                 class="form-control" maxlength="120"
                 placeholder="Ej: Plantel 01 Durango">
        </div>

        <!-- Empleado beneficiario -->
        <?php if (!empty($empleados)): ?>
        <div class="form-group">
          <label class="form-label" for="pedEmpleado">Para empleado</label>
          <select id="pedEmpleado" name="empleado_id" class="form-control">
            <option value="">— Opcional —</option>
            <?php foreach ($empleados as $emp): ?>
            <option value="<?= (int) $emp['id'] ?>">
              <?= e($emp['numero_empleado'] . ' — ' . $emp['nombre'] . ' ' . $emp['apellidos']) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php endif; ?>

        <!-- Observaciones -->
        <div class="form-group">
          <label class="form-label" for="pedObs">Observaciones</label>
          <textarea id="pedObs" name="observaciones"
                    class="form-control" rows="3"
                    placeholder="Contexto adicional del pedido…"></textarea>
        </div>

      </div>
      <div class="card-footer">
        <button type="button" class="btn btn-primary w-100" id="btnEnviarPedido">
          <span id="btnPedTexto">
            <i class="ti ti-send" aria-hidden="true"></i> Enviar pedido
          </span>
          <span id="btnPedSpinner" style="display:none">
            <i class="ti ti-loader-2" style="animation:spin .75s linear infinite"></i>
            Enviando…
          </span>
        </button>
      </div>
    </div>

    <div class="alert alert-info" style="margin:0">
      <i class="ti ti-info-circle" aria-hidden="true"></i>
      <span style="font-size:var(--font-size-sm)">
        El pedido no descuenta stock. El almacén lo atenderá y registrará
        la entrega cuando esté disponible.
      </span>
    </div>

  </div>

</div>
</form>

<!-- Template de fila de ítem (oculto, se clona por JS) -->
<template id="templateItem">
  <div class="item-row"
       style="padding:1rem 1.25rem;border-bottom:1px solid var(--border-color);
              display:grid;grid-template-columns:1fr auto;gap:1rem;align-items:start">
    <div>
      <!-- Selector de producto -->
      <div class="form-group" style="margin-bottom:.75rem">
        <select name="producto_id[]" class="form-control sel-producto" required>
          <option value="">— Seleccionar producto —</option>
          <?php foreach ($productos as $p): ?>
          <option value="<?= (int) $p['id'] ?>"
                  data-upc="<?= (int) $p['unidades_por_caja'] ?>"
                  data-unidad="<?= e($p['unidad_medida']) ?>"
                  data-nombre="<?= e($p['nombre']) ?>"
                  data-stock="<?= (int) $p['stock_actual'] ?>">
            <?= e($p['codigo']) ?> — <?= e($p['nombre']) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- Cantidad: cajas + piezas -->
      <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:.625rem;align-items:end">
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
        <div style="font-size:var(--font-size-xs);color:var(--color-primary);
                    padding-bottom:.375rem;font-weight:500" class="txt-total">
          —
        </div>
      </div>

      <!-- Stock disponible -->
      <div class="stock-hint"
           style="margin-top:.375rem;font-size:var(--font-size-xs);
                  color:var(--text-muted);display:none">
        Disponible: <strong class="stock-disp">—</strong>
      </div>

      <!-- Observación del ítem -->
      <div class="form-group" style="margin-top:.625rem;margin-bottom:0">
        <input type="text" name="obs_item[]"
               class="form-control" placeholder="Observación (opcional)"
               style="font-size:var(--font-size-xs)">
      </div>
    </div>
    <!-- Botón eliminar fila -->
    <button type="button" class="btn btn-ghost btn-icon btn-quitar-item"
            style="margin-top:1.5rem;color:var(--status-danger-text)"
            title="Quitar">
      <i class="ti ti-trash" aria-hidden="true"></i>
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
let numItems = 0;

// ── Agregar fila ──
document.getElementById('btnAgregarItem').addEventListener('click', agregarFila);

function agregarFila() {
  const tmpl  = document.getElementById('templateItem');
  const clone = tmpl.content.cloneNode(true);
  const row   = clone.querySelector('.item-row');

  // Bindear eventos del clon
  const sel    = row.querySelector('.sel-producto');
  const inCaj  = row.querySelector('.inp-cajas');
  const inPie  = row.querySelector('.inp-piezas');
  const txtTot = row.querySelector('.txt-total');
  const hint   = row.querySelector('.stock-hint');
  const lblPie = row.querySelector('.lbl-piezas');

  sel.addEventListener('change', function () {
    const opt   = this.options[this.selectedIndex];
    const upc   = parseInt(opt.dataset.upc)  || 1;
    const unidad= opt.dataset.unidad || 'pieza';
    const stock = parseInt(opt.dataset.stock) || 0;

    if (this.value) {
      hint.style.display = 'block';
      hint.querySelector('.stock-disp').textContent =
        upc > 1 ? formatTexto(stock, upc, unidad) : `${stock} ${unidad}(s)`;
      lblPie.textContent = upc > 1 ? `${capitalize(unidad)}s sueltas` : `${capitalize(unidad)}s`;
    } else {
      hint.style.display = 'none';
    }
    actualizarTotal();
  });

  [inCaj, inPie].forEach(el => el.addEventListener('input', actualizarTotal));

  function actualizarTotal() {
    const opt   = sel.options[sel.selectedIndex];
    const upc   = parseInt(opt?.dataset?.upc) || 1;
    const unidad= opt?.dataset?.unidad || 'pieza';
    const total = (parseInt(inCaj.value)||0) * upc + (parseInt(inPie.value)||0);
    txtTot.textContent = total > 0 ? formatTexto(total, upc, unidad) : '—';
    actualizarTotalPedido();
  }

  row.querySelector('.btn-quitar-item').addEventListener('click', function () {
    row.remove();
    numItems--;
    actualizarTotalPedido();
    if (numItems === 0) {
      document.getElementById('sinItems').style.display = 'block';
      document.getElementById('totalPedido').style.display = 'none';
    }
  });

  const contenedor = document.getElementById('contenedorItems');
  document.getElementById('sinItems').style.display = 'none';
  document.getElementById('totalPedido').style.display = 'block';
  contenedor.appendChild(row);
  numItems++;
  actualizarTotalPedido();
  sel.focus();
}

function actualizarTotalPedido() {
  const filas = document.querySelectorAll('.item-row');
  document.getElementById('lblTotalItems').textContent =
    `${filas.length} artículo${filas.length !== 1 ? 's' : ''}`;
}

// ── Prioridad visual ──
function togglePrioridad(radio) {
  document.getElementById('lblNormal').style.borderColor =
    radio.value === 'normal'  ? 'var(--color-primary)' : 'var(--border-color)';
  document.getElementById('lblUrgente').style.borderColor =
    radio.value === 'urgente' ? 'var(--color-accent)'  : 'var(--border-color)';
  document.getElementById('lblUrgente').style.background =
    radio.value === 'urgente' ? 'var(--color-accent-light)' : '';
}
window.togglePrioridad = togglePrioridad;

// ── Enviar pedido ──
document.getElementById('btnEnviarPedido').addEventListener('click', async function () {
  const errDiv = document.getElementById('errItems');
  errDiv.style.display = 'none';
  errDiv.textContent   = '';

  const filas = document.querySelectorAll('.item-row');
  if (filas.length === 0) {
    errDiv.textContent   = 'Agrega al menos un artículo al pedido.';
    errDiv.style.display = 'block';
    return;
  }

  // Verificar que todos los productos estén seleccionados
  let valido = true;
  filas.forEach(fila => {
    const sel = fila.querySelector('.sel-producto');
    if (!sel.value) { sel.classList.add('is-invalid'); valido = false; }
    else sel.classList.remove('is-invalid');
  });
  if (!valido) {
    errDiv.textContent   = 'Selecciona el producto en cada artículo.';
    errDiv.style.display = 'block';
    return;
  }

  setLoading(true);
  const fd = new FormData(document.getElementById('formPedido'));

  try {
    const res  = await fetch(`${BASE}/pedidos`, { method: 'POST', body: fd });
    const json = await res.json();

    if (json.success) {
      await SwalInst.fire({
        icon:              'success',
        title:             json.message,
        html:              `Folio: <b>${json.data.folio}</b>`,
        confirmButtonText: 'Ver pedido',
      });
      window.location.href = `${BASE}/pedidos/${json.data.id}`;
    } else {
      if (json.errors?.items || json.errors?.general) {
        errDiv.textContent   = json.errors.items || json.errors.general;
        errDiv.style.display = 'block';
      }
      SwalInst.fire({ icon: 'error', title: json.message || 'Error al crear el pedido.' });
    }
  } catch {
    SwalInst.fire({ icon: 'error', title: 'Error de conexión.' });
  } finally {
    setLoading(false);
  }
});

function setLoading(on) {
  document.getElementById('btnEnviarPedido').disabled = on;
  document.getElementById('btnPedTexto').style.display   = on ? 'none'   : 'inline';
  document.getElementById('btnPedSpinner').style.display = on ? 'inline' : 'none';
}

function formatTexto(piezas, upc, unidad) {
  if (upc <= 1) return `${piezas} ${unidad}(s)`;
  const c = Math.floor(piezas / upc), r = piezas % upc, p = [];
  if (c > 0) p.push(`${c} caja${c>1?'s':''}`);
  if (r > 0) p.push(`${r} ${unidad}${r>1?'s':''}`);
  return p.join(' + ') || '—';
}
function capitalize(s) { return s.charAt(0).toUpperCase() + s.slice(1); }

// Agregar una fila vacía al cargar
agregarFila();
})();
</script>
<?php $extraJs = ob_get_clean(); ?>
