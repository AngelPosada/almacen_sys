<?php
if (!isset($config)) { $config = $GLOBALS['app_config'] ?? require ROOT_PATH . '/config/config.php'; }
/**
 * views/inventario/entradas.php
 *
 * Formulario de registro de entrada de mercancía.
 * Variables: $productos, $recientes, $producto_presel
 */
?>

<div class="page-header">
  <div>
    <h1 class="page-title">Registrar entrada</h1>
    <p class="page-subtitle">Ingresa mercancía al almacén por compra o devolución</p>
  </div>
  <a href="<?= e($config['app']['url']) ?>/inventario" class="btn btn-ghost">
    <i class="ti ti-arrow-left" aria-hidden="true"></i> Volver al historial
  </a>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.25rem">

  <!-- ─ Formulario de entrada ─ -->
  <div class="card">
    <div class="card-header">
      <div class="card-title">
        <i class="ti ti-arrow-down-left"
           style="color:var(--color-primary);margin-right:.375rem" aria-hidden="true"></i>
        Nueva entrada
      </div>
    </div>
    <div class="card-body">
      <form id="formEntrada" novalidate>
        <?= Security::csrfField() ?>

        <!-- Producto -->
        <div class="form-group">
          <label class="form-label required" for="entProducto">Producto</label>
          <select id="entProducto" name="producto_id" class="form-control" required>
            <option value="">— Seleccionar producto —</option>
            <?php foreach ($productos as $p): ?>
            <option value="<?= (int) $p['id'] ?>"
                    data-upc="<?= (int) $p['unidades_por_caja'] ?>"
                    data-unidad="<?= e($p['unidad_medida']) ?>"
                    data-stock="<?= (int) $p['stock_actual'] ?>"
                    data-nombre="<?= e($p['nombre']) ?>"
                    <?= (int) $p['id'] === ($producto_presel ?? 0) ? 'selected' : '' ?>>
              <?= e($p['codigo']) ?> — <?= e($p['nombre']) ?>
            </option>
            <?php endforeach; ?>
          </select>
          <div class="form-error" id="errProducto_id"></div>
        </div>

        <!-- Panel de stock actual (se actualiza al seleccionar) -->
        <div id="panelStockActual"
             style="display:none;background:var(--bg-surface-2);
                    border-radius:var(--border-radius);padding:1rem;
                    margin-bottom:1.25rem;border:1px solid var(--border-color)">
          <div style="font-size:var(--font-size-xs);color:var(--text-muted);
                      text-transform:uppercase;letter-spacing:.05em;margin-bottom:.25rem">
            Stock actual en almacén
          </div>
          <div id="stockActualTexto"
               style="font-size:var(--font-size-xl);font-weight:700;
                      color:var(--color-primary)">
            —
          </div>
        </div>

        <!-- Cantidad: cajas + piezas sueltas -->
        <div style="background:var(--color-primary-light);border-radius:var(--border-radius);
                    padding:1rem;margin-bottom:1.25rem">
          <div style="font-size:var(--font-size-xs);font-weight:600;
                      color:var(--color-primary);text-transform:uppercase;
                      letter-spacing:.05em;margin-bottom:.875rem">
            Cantidad a ingresar
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
            <div class="form-group" style="margin:0">
              <label class="form-label" for="entCajas">
                Cajas completas
              </label>
              <input type="number" id="entCajas" name="cajas"
                     class="form-control" value="0" min="0">
              <div class="form-hint" id="hintCajas" style="color:var(--color-primary)">
                <!-- Actualizado por JS -->
              </div>
            </div>
            <div class="form-group" style="margin:0">
              <label class="form-label" for="entPiezas">
                <span id="labelPiezasSueltas">Piezas sueltas</span>
              </label>
              <input type="number" id="entPiezas" name="piezas_sueltas"
                     class="form-control" value="0" min="0">
            </div>
          </div>
          <div class="form-error" id="errCajas" style="margin-top:.5rem"></div>

          <!-- Resumen de piezas totales -->
          <div id="resumenPiezas"
               style="margin-top:.875rem;padding:.625rem .875rem;
                      background:rgba(14,115,78,.1);border-radius:var(--border-radius-sm);
                      font-size:var(--font-size-sm);font-weight:500;
                      color:var(--color-primary);display:none">
            Total: <span id="totalPiezasTexto">0 piezas</span>
          </div>
        </div>

        <!-- Origen -->
        <div class="form-group">
          <label class="form-label required" for="entOrigen">Origen de la entrada</label>
          <select id="entOrigen" name="origen" class="form-control">
            <option value="compra">Compra</option>
            <option value="devolucion">Devolución</option>
            <option value="ajuste_manual">Ajuste manual</option>
            <option value="inventario_fisico">Inventario físico</option>
          </select>
        </div>

        <!-- Observación -->
        <div class="form-group">
          <label class="form-label" for="entObs">Observación</label>
          <textarea id="entObs" name="observacion"
                    class="form-control" rows="2"
                    placeholder="Factura, proveedor, motivo…"></textarea>
        </div>

      </form>
    </div>
    <div class="card-footer" style="display:flex;justify-content:flex-end;gap:.75rem">
      <button type="button" class="btn btn-ghost" id="btnLimpiarEntrada">
        <i class="ti ti-eraser" aria-hidden="true"></i> Limpiar
      </button>
      <button type="button" class="btn btn-primary" id="btnGuardarEntrada">
        <span id="btnEntTexto">
          <i class="ti ti-arrow-down-left" aria-hidden="true"></i>
          Registrar entrada
        </span>
        <span id="btnEntSpinner" style="display:none">
          <i class="ti ti-loader-2" style="animation:spin .75s linear infinite"></i>
          Guardando…
        </span>
      </button>
    </div>
  </div>

  <!-- ─ Entradas recientes hoy ─ -->
  <div class="card">
    <div class="card-header">
      <div class="card-title">Entradas de hoy</div>
      <span style="font-size:var(--font-size-xs);color:var(--text-muted)">
        <?= date('d/m/Y') ?>
      </span>
    </div>
    <div class="card-body" style="padding:0" id="listaRecientes">

      <?php if (empty($recientes)): ?>
        <div style="padding:2.5rem;text-align:center;color:var(--text-muted)">
          <i class="ti ti-inbox" style="font-size:2rem;display:block;margin-bottom:.5rem"
             aria-hidden="true"></i>
          Sin entradas registradas hoy
        </div>
      <?php else: ?>
        <ul style="list-style:none" id="ulRecientes">
          <?php foreach ($recientes as $r): ?>
          <li style="padding:.75rem 1.25rem;border-bottom:1px solid var(--border-color);
                     display:flex;align-items:center;gap:.75rem">
            <div style="width:32px;height:32px;border-radius:50%;
                        background:var(--status-success-bg);
                        display:flex;align-items:center;justify-content:center;flex-shrink:0">
              <i class="ti ti-arrow-down-left"
                 style="font-size:.875rem;color:var(--status-success-text)"
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
              <div style="font-weight:700;color:var(--status-success-text);
                          font-size:var(--font-size-sm)">
                +<?= e($r['cantidad_texto']) ?>
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
const selProd    = document.getElementById('entProducto');
const inCajas    = document.getElementById('entCajas');
const inPiezas   = document.getElementById('entPiezas');
const btnGuardar = document.getElementById('btnGuardarEntrada');

// ── Al seleccionar producto: mostrar stock actual y actualizar hints ──
selProd.addEventListener('change', function () {
  const opt    = this.options[this.selectedIndex];
  const upc    = parseInt(opt.dataset.upc)   || 1;
  const unidad = opt.dataset.unidad || 'pieza';
  const stock  = parseInt(opt.dataset.stock) || 0;
  const panel  = document.getElementById('panelStockActual');

  if (this.value) {
    panel.style.display = 'block';
    document.getElementById('stockActualTexto').textContent =
      upc > 1 ? formatearCajasTexto(stock, upc, unidad) : `${stock} ${unidad}(s)`;
    document.getElementById('hintCajas').textContent =
      upc > 1 ? `1 caja = ${upc} ${unidad}s` : 'Este producto no se maneja en cajas';
    document.getElementById('labelPiezasSueltas').textContent =
      upc > 1 ? `${unidad.charAt(0).toUpperCase() + unidad.slice(1)}s sueltas` : `${unidad}s`;
  } else {
    panel.style.display = 'none';
  }
  actualizarResumen();
});

// ── Calcular resumen en tiempo real ──
[inCajas, inPiezas].forEach(el => el.addEventListener('input', actualizarResumen));

function actualizarResumen() {
  const opt        = selProd.options[selProd.selectedIndex];
  const upc        = parseInt(opt?.dataset?.upc) || 1;
  const unidad     = opt?.dataset?.unidad || 'pieza';
  const cajas      = parseInt(inCajas.value)  || 0;
  const piezas     = parseInt(inPiezas.value) || 0;
  const totalPiezas= (cajas * upc) + piezas;

  const resumen    = document.getElementById('resumenPiezas');
  const texto      = document.getElementById('totalPiezasTexto');

  if (totalPiezas > 0 && selProd.value) {
    resumen.style.display = 'block';
    texto.textContent     = formatearCajasTexto(totalPiezas, upc, unidad);
  } else {
    resumen.style.display = 'none';
  }
}

function formatearCajasTexto(piezas, upc, unidad) {
  if (upc <= 1) return `${piezas} ${unidad}(s)`;
  const cajas  = Math.floor(piezas / upc);
  const resto  = piezas % upc;
  const partes = [];
  if (cajas  > 0) partes.push(`${cajas} caja${cajas > 1 ? 's' : ''}`);
  if (resto  > 0) partes.push(`${resto} ${unidad}${resto > 1 ? 's' : ''}`);
  return partes.join(' + ') || 'Sin stock';
}

// ── Guardar ──
btnGuardar.addEventListener('click', async function () {
  limpiarErrores();
  setLoading(true);

  const fd = new FormData(document.getElementById('formEntrada'));

  try {
    const res  = await fetch(`${BASE}/inventario/entradas`, { method: 'POST', body: fd });
    const json = await res.json();

    if (json.success) {
      await SwalInst.fire({
        icon:              'success',
        title:             '¡Entrada registrada!',
        html:              `<b>${json.message}</b><br>
                            <small>Stock nuevo: ${json.data.stock_texto}</small>`,
        confirmButtonText: 'Aceptar',
      });
      // Actualizar panel de stock y limpiar form
      document.getElementById('formEntrada').reset();
      document.getElementById('panelStockActual').style.display = 'none';
      document.getElementById('resumenPiezas').style.display    = 'none';
      // Recargar lista de recientes
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

// ── Limpiar ──
document.getElementById('btnLimpiarEntrada').addEventListener('click', function () {
  document.getElementById('formEntrada').reset();
  document.getElementById('panelStockActual').style.display = 'none';
  document.getElementById('resumenPiezas').style.display    = 'none';
  limpiarErrores();
});

function setLoading(on) {
  btnGuardar.disabled = on;
  document.getElementById('btnEntTexto').style.display   = on ? 'none'   : 'inline';
  document.getElementById('btnEntSpinner').style.display = on ? 'inline' : 'none';
}
function limpiarErrores() {
  document.querySelectorAll('.form-error').forEach(el => el.textContent = '');
  document.querySelectorAll('.form-control').forEach(el => el.classList.remove('is-invalid'));
}
function mostrarErrores(errors) {
  Object.entries(errors).forEach(([campo, msg]) => {
    const k   = campo.charAt(0).toUpperCase() + campo.slice(1);
    const err = document.getElementById('err' + k);
    if (err) { err.textContent = msg; }
    const inp = document.querySelector(`[name="${campo}"]`);
    if (inp)  inp.classList.add('is-invalid');
  });
}

// Preseleccionar producto si viene por URL
<?php if ($producto_presel): ?>
selProd.dispatchEvent(new Event('change'));
<?php endif; ?>

})();
</script>
<?php $extraJs = ob_get_clean(); ?>
