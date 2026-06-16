<?php
if (!isset($config)) { $config = $GLOBALS['app_config'] ?? require ROOT_PATH . '/config/config.php'; }
/**
 * views/inventario/salidas.php
 *
 * Formulario de registro de salida de mercancía.
 * Valida disponibilidad de stock en tiempo real antes de enviar.
 * Variables: $productos, $recientes
 */
?>

<div class="page-header">
  <div>
    <h1 class="page-title">Registrar salida</h1>
    <p class="page-subtitle">Descuenta stock por merma, préstamo o ajuste manual</p>
  </div>
  <a href="<?= e($config['app']['url']) ?>/inventario" class="btn btn-ghost">
    <i class="ti ti-arrow-left" aria-hidden="true"></i> Volver al historial
  </a>
</div>

<div class="alert alert-warning" style="margin-bottom:1.25rem">
  <i class="ti ti-info-circle" aria-hidden="true"></i>
  <span>
    Esta pantalla es para salidas manuales (merma, ajuste).
    Las salidas por entrega de pedidos se registran automáticamente al emitir el
    <a href="<?= e($config['app']['url']) ?>/vales/salida/nuevo"
       style="color:inherit;font-weight:600">vale de salida</a>.
  </span>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.25rem">

  <!-- ─ Formulario de salida ─ -->
  <div class="card">
    <div class="card-header">
      <div class="card-title">
        <i class="ti ti-arrow-up-right"
           style="color:var(--color-accent);margin-right:.375rem" aria-hidden="true"></i>
        Nueva salida
      </div>
    </div>
    <div class="card-body">
      <form id="formSalida" novalidate>
        <?= Security::csrfField() ?>

        <!-- Producto -->
        <div class="form-group">
          <label class="form-label required" for="salProducto">Producto</label>
          <select id="salProducto" name="producto_id" class="form-control" required>
            <option value="">— Seleccionar producto —</option>
            <?php foreach ($productos as $p): ?>
            <option value="<?= (int) $p['id'] ?>"
                    data-upc="<?= (int) $p['unidades_por_caja'] ?>"
                    data-unidad="<?= e($p['unidad_medida']) ?>"
                    data-stock="<?= (int) $p['stock_actual'] ?>">
              <?= e($p['codigo']) ?> — <?= e($p['nombre']) ?>
            </option>
            <?php endforeach; ?>
          </select>
          <div class="form-error" id="errProducto_id"></div>
        </div>

        <!-- Panel stock disponible -->
        <div id="panelStockDisponible"
             style="display:none;background:var(--bg-surface-2);
                    border-radius:var(--border-radius);padding:1rem;
                    margin-bottom:1.25rem;border:1px solid var(--border-color)">
          <div style="display:flex;justify-content:space-between;align-items:center">
            <div>
              <div style="font-size:var(--font-size-xs);color:var(--text-muted);
                          text-transform:uppercase;letter-spacing:.05em;margin-bottom:.25rem">
                Disponible en almacén
              </div>
              <div id="stockDispTexto"
                   style="font-size:var(--font-size-xl);font-weight:700;color:var(--text-primary)">
                —
              </div>
            </div>
            <div id="alertaStockCero" style="display:none">
              <span class="badge badge-danger" style="font-size:.75rem">Sin stock</span>
            </div>
          </div>
          <!-- Barra de disponibilidad -->
          <div class="stock-bar" style="margin-top:.75rem;height:6px" id="barraDisponible">
            <div class="stock-bar-fill ok" id="barraFill" style="width:100%"></div>
          </div>
          <!-- Indicador de cuánto quedará -->
          <div id="stockRestante" style="margin-top:.5rem;font-size:var(--font-size-xs);
                                          color:var(--text-muted);display:none">
            Quedará: <strong id="stockRestanteTexto">—</strong>
          </div>
        </div>

        <!-- Cantidad -->
        <div style="background:rgba(242,129,29,.08);border-radius:var(--border-radius);
                    padding:1rem;margin-bottom:1.25rem;border:1px solid rgba(242,129,29,.2)">
          <div style="font-size:var(--font-size-xs);font-weight:600;
                      color:var(--color-accent);text-transform:uppercase;
                      letter-spacing:.05em;margin-bottom:.875rem">
            Cantidad a descontar
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
            <div class="form-group" style="margin:0">
              <label class="form-label" for="salCajas">Cajas</label>
              <input type="number" id="salCajas" name="cajas"
                     class="form-control" value="0" min="0">
            </div>
            <div class="form-group" style="margin:0">
              <label class="form-label" for="salPiezas">
                <span id="labelSalPiezas">Piezas sueltas</span>
              </label>
              <input type="number" id="salPiezas" name="piezas_sueltas"
                     class="form-control" value="0" min="0">
            </div>
          </div>
          <div class="form-error" id="errCajas" style="margin-top:.5rem"></div>

          <div id="resumenSalida"
               style="margin-top:.875rem;padding:.625rem .875rem;
                      background:rgba(242,129,29,.12);border-radius:var(--border-radius-sm);
                      font-size:var(--font-size-sm);font-weight:500;
                      color:var(--color-accent-dark);display:none">
            A descontar: <span id="totalSalidaTexto">0</span>
          </div>
        </div>

        <!-- Origen -->
        <div class="form-group">
          <label class="form-label required" for="salOrigen">Motivo de la salida</label>
          <select id="salOrigen" name="origen" class="form-control">
            <option value="ajuste_manual">Ajuste manual</option>
            <option value="devolucion">Devolución a proveedor</option>
            <option value="inventario_fisico">Corrección por inventario físico</option>
          </select>
        </div>

        <!-- Observación (obligatoria en salidas) -->
        <div class="form-group">
          <label class="form-label required" for="salObs">Motivo detallado</label>
          <textarea id="salObs" name="observacion"
                    class="form-control" rows="3"
                    placeholder="Describe con detalle el motivo de esta salida…"
                    required></textarea>
          <div class="form-hint">Requerido para trazabilidad en auditoría.</div>
          <div class="form-error" id="errObservacion"></div>
        </div>

      </form>
    </div>
    <div class="card-footer" style="display:flex;justify-content:flex-end;gap:.75rem">
      <button type="button" class="btn btn-ghost" id="btnLimpiarSalida">
        <i class="ti ti-eraser" aria-hidden="true"></i> Limpiar
      </button>
      <button type="button" class="btn btn-accent" id="btnGuardarSalida" disabled>
        <span id="btnSalTexto">
          <i class="ti ti-arrow-up-right" aria-hidden="true"></i>
          Registrar salida
        </span>
        <span id="btnSalSpinner" style="display:none">
          <i class="ti ti-loader-2" style="animation:spin .75s linear infinite"></i>
          Guardando…
        </span>
      </button>
    </div>
  </div>

  <!-- ─ Salidas recientes hoy ─ -->
  <div class="card">
    <div class="card-header">
      <div class="card-title">Salidas de hoy</div>
      <span style="font-size:var(--font-size-xs);color:var(--text-muted)">
        <?= date('d/m/Y') ?>
      </span>
    </div>
    <div class="card-body" style="padding:0">

      <?php if (empty($recientes)): ?>
        <div style="padding:2.5rem;text-align:center;color:var(--text-muted)">
          <i class="ti ti-inbox" style="font-size:2rem;display:block;margin-bottom:.5rem"
             aria-hidden="true"></i>
          Sin salidas registradas hoy
        </div>
      <?php else: ?>
        <ul style="list-style:none">
          <?php foreach ($recientes as $r): ?>
          <li style="padding:.75rem 1.25rem;border-bottom:1px solid var(--border-color);
                     display:flex;align-items:center;gap:.75rem">
            <div style="width:32px;height:32px;border-radius:50%;
                        background:var(--status-danger-bg);
                        display:flex;align-items:center;justify-content:center;flex-shrink:0">
              <i class="ti ti-arrow-up-right"
                 style="font-size:.875rem;color:var(--status-danger-text)"
                 aria-hidden="true"></i>
            </div>
            <div style="flex:1;min-width:0">
              <div style="font-size:var(--font-size-sm);font-weight:500;
                          white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
                <?= e($r['producto_nombre']) ?>
              </div>
              <div style="font-size:var(--font-size-xs);color:var(--text-muted)">
                <?= e($r['origen_label'] ?? $r['origen']) ?> — <?= e($r['usuario_nombre']) ?>
              </div>
            </div>
            <div style="text-align:right;flex-shrink:0">
              <div style="font-weight:700;color:var(--status-danger-text);
                          font-size:var(--font-size-sm)">
                −<?= e($r['cantidad_texto']) ?>
              </div>
              <div style="font-size:var(--font-size-xs);color:var(--text-muted)">
                <?= e($r['fecha_fmt']) ?>
              </div>
            </div>
          </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>

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

const BASE       = '<?= $baseUrl ?>';
const selProd    = document.getElementById('salProducto');
const inCajas    = document.getElementById('salCajas');
const inPiezas   = document.getElementById('salPiezas');
const btnGuardar = document.getElementById('btnGuardarSalida');

let stockDisponible = 0;
let upcActual       = 1;
let unidadActual    = 'pieza';

// ── Seleccionar producto ──
selProd.addEventListener('change', function () {
  const opt = this.options[this.selectedIndex];
  upcActual       = parseInt(opt.dataset.upc)   || 1;
  unidadActual    = opt.dataset.unidad || 'pieza';
  stockDisponible = parseInt(opt.dataset.stock) || 0;

  const panel = document.getElementById('panelStockDisponible');

  if (this.value) {
    panel.style.display = 'block';
    document.getElementById('stockDispTexto').textContent =
      formatearTexto(stockDisponible, upcActual, unidadActual);
    document.getElementById('alertaStockCero').style.display =
      stockDisponible === 0 ? 'block' : 'none';
    document.getElementById('labelSalPiezas').textContent =
      upcActual > 1 ? `${unidadActual}s sueltas` : `${unidadActual}s`;
  } else {
    panel.style.display = 'none';
  }
  actualizarResumen();
});

// ── Calcular disponibilidad en tiempo real ──
[inCajas, inPiezas].forEach(el => el.addEventListener('input', actualizarResumen));

function actualizarResumen() {
  const cajas  = parseInt(inCajas.value)  || 0;
  const piezas = parseInt(inPiezas.value) || 0;
  const total  = (cajas * upcActual) + piezas;
  const restante = stockDisponible - total;

  const resumen  = document.getElementById('resumenSalida');
  const restDiv  = document.getElementById('stockRestante');
  const barraFill= document.getElementById('barraFill');

  if (total > 0 && selProd.value) {
    resumen.style.display = 'block';
    document.getElementById('totalSalidaTexto').textContent =
      formatearTexto(total, upcActual, unidadActual);
  } else {
    resumen.style.display = 'none';
  }

  if (total > 0 && selProd.value) {
    restDiv.style.display = 'block';
    document.getElementById('stockRestanteTexto').textContent =
      restante >= 0 ? formatearTexto(restante, upcActual, unidadActual) : '⚠ Insuficiente';
    document.getElementById('stockRestanteTexto').style.color =
      restante < 0 ? 'var(--status-danger-text)' : 'inherit';
  } else {
    restDiv.style.display = 'none';
  }

  // Barra visual
  if (stockDisponible > 0) {
    const pct = Math.max(0, Math.min(100, (restante / stockDisponible) * 100));
    barraFill.style.width = pct + '%';
    barraFill.className   = 'stock-bar-fill ' +
      (restante < 0 ? 'critical' : restante < stockDisponible * 0.2 ? 'warning' : 'ok');
  }

  // Habilitar/deshabilitar el botón de guardar
  const puedeGuardar = total > 0 && restante >= 0 && selProd.value !== '';
  btnGuardar.disabled = !puedeGuardar;
}

function formatearTexto(piezas, upc, unidad) {
  if (upc <= 1) return `${piezas} ${unidad}(s)`;
  const c = Math.floor(piezas / upc);
  const r = piezas % upc;
  const p = [];
  if (c > 0) p.push(`${c} caja${c > 1 ? 's' : ''}`);
  if (r > 0) p.push(`${r} ${unidad}${r > 1 ? 's' : ''}`);
  return p.join(' + ') || 'Sin stock';
}

// ── Guardar ──
btnGuardar.addEventListener('click', async function () {
  limpiarErrores();
  setLoading(true);

  const fd = new FormData(document.getElementById('formSalida'));

  try {
    const res  = await fetch(`${BASE}/inventario/salidas`, { method: 'POST', body: fd });
    const json = await res.json();

    if (json.success) {
      await SwalInst.fire({
        icon:              'success',
        title:             'Salida registrada',
        html:              `<b>${json.message}</b><br>
                            <small>Stock nuevo: ${json.data.stock_texto}</small>`,
        confirmButtonText: 'Aceptar',
      });
      location.reload();
    } else {
      if (json.errors) mostrarErrores(json.errors);
      SwalInst.fire({ icon: 'error', title: json.message || 'Error al registrar.' });
    }
  } catch {
    SwalInst.fire({ icon: 'error', title: 'Error de conexión.' });
  } finally {
    setLoading(false);
  }
});

document.getElementById('btnLimpiarSalida').addEventListener('click', function () {
  document.getElementById('formSalida').reset();
  document.getElementById('panelStockDisponible').style.display = 'none';
  document.getElementById('resumenSalida').style.display        = 'none';
  document.getElementById('stockRestante').style.display        = 'none';
  btnGuardar.disabled = true;
  limpiarErrores();
  stockDisponible = 0;
  upcActual = 1;
});

function setLoading(on) {
  btnGuardar.disabled = on;
  document.getElementById('btnSalTexto').style.display   = on ? 'none'   : 'inline';
  document.getElementById('btnSalSpinner').style.display = on ? 'inline' : 'none';
}
function limpiarErrores() {
  document.querySelectorAll('.form-error').forEach(el => el.textContent = '');
  document.querySelectorAll('.form-control').forEach(el => el.classList.remove('is-invalid'));
}
function mostrarErrores(errors) {
  Object.entries(errors).forEach(([campo, msg]) => {
    const k   = campo.charAt(0).toUpperCase() + campo.slice(1);
    const err = document.getElementById('err' + k);
    if (err) err.textContent = msg;
    const inp = document.querySelector(`[name="${campo}"]`);
    if (inp)  inp.classList.add('is-invalid');
  });
}

})();
</script>
<?php $extraJs = ob_get_clean(); ?>
