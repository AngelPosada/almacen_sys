<?php
if (!isset($config)) { $config = $GLOBALS['app_config'] ?? require ROOT_PATH . '/config/config.php'; }
/**
 * views/requisiciones/create.php
 * Formulario fiel al formato FOR 8.4 DeRM v14 — 10 renglones exactos.
 */

$baseUrl = e($config['app']['url']);
?>

<div class="page-header">
  <div>
    <h1 class="page-title">Nueva requisición</h1>
    <p class="page-subtitle">Formato FOR 8.4 DeRM — Departamento de Recursos Materiales</p>
  </div>
  <a href="<?= $baseUrl ?>/requisiciones" class="btn btn-ghost">
    <i class="ti ti-arrow-left"></i> Volver
  </a>
</div>

<form id="formRequisicion" novalidate>
<?= Security::csrfField() ?>

<div style="display:grid;grid-template-columns:2fr 1fr;gap:1.25rem;align-items:start">

  <!-- Panel principal -->
  <div style="display:flex;flex-direction:column;gap:1.25rem">

    <!-- Cabecera del documento -->
    <div class="card">
      <div class="card-header">
        <div class="card-title">Datos de la requisición</div>
      </div>
      <div class="card-body">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">

          <div class="form-group" style="grid-column:1/-1">
            <label class="form-label required" for="reqPlantel">Plantel / Área</label>
            <input type="text" id="reqPlantel" name="plantel"
                   class="form-control" maxlength="150"
                   placeholder="Nombre del plantel o área solicitante">
            <div class="form-error" id="errPlantel"></div>
          </div>

          <div class="form-group">
            <label class="form-label" for="reqClave">Clave programática</label>
            <input type="text" id="reqClave" name="clave_programatica"
                   class="form-control" maxlength="80"
                   placeholder="Ej: 11-00-01">
          </div>

          <div class="form-group">
            <label class="form-label required" for="reqFecha">Fecha de elaboración</label>
            <input type="date" id="reqFecha" name="fecha_elaboracion"
                   class="form-control" value="<?= date('Y-m-d') ?>">
            <div class="form-error" id="errFecha_elaboracion"></div>
          </div>

        </div>
      </div>
    </div>

    <!-- Tabla de conceptos (10 renglones como formato oficial) -->
    <div class="card">
      <div class="card-header">
        <div class="card-title">Conceptos solicitados</div>
        <span style="font-size:var(--font-size-xs);color:var(--text-muted)">
          Máximo 10 renglones
        </span>
      </div>
      <div class="card-body" style="padding:0">
        <div style="overflow-x:auto">
          <table style="width:100%;border-collapse:collapse;min-width:700px">
            <thead>
              <tr style="background:var(--bg-table-header)">
                <th style="padding:.625rem .75rem;text-align:center;font-size:var(--font-size-xs);
                            font-weight:600;color:var(--text-secondary);text-transform:uppercase;
                            letter-spacing:.05em;border-bottom:1.5px solid var(--border-color);width:40px">
                  No.
                </th>
                <th style="padding:.625rem .75rem;font-size:var(--font-size-xs);font-weight:600;
                            color:var(--text-secondary);text-transform:uppercase;
                            letter-spacing:.05em;border-bottom:1.5px solid var(--border-color)">
                  Concepto *
                </th>
                <th style="padding:.625rem .75rem;text-align:center;font-size:var(--font-size-xs);
                            font-weight:600;color:var(--text-secondary);text-transform:uppercase;
                            letter-spacing:.05em;border-bottom:1.5px solid var(--border-color);width:90px">
                  Cantidad *
                </th>
                <th style="padding:.625rem .75rem;font-size:var(--font-size-xs);font-weight:600;
                            color:var(--text-secondary);text-transform:uppercase;
                            letter-spacing:.05em;border-bottom:1.5px solid var(--border-color)">
                  Especificaciones
                </th>
                <th style="padding:.625rem .75rem;text-align:right;font-size:var(--font-size-xs);
                            font-weight:600;color:var(--text-secondary);text-transform:uppercase;
                            letter-spacing:.05em;border-bottom:1.5px solid var(--border-color);width:110px">
                  Precio unit.
                </th>
                <th style="padding:.625rem .75rem;text-align:right;font-size:var(--font-size-xs);
                            font-weight:600;color:var(--text-secondary);text-transform:uppercase;
                            letter-spacing:.05em;border-bottom:1.5px solid var(--border-color);width:110px">
                  Total
                </th>
              </tr>
            </thead>
            <tbody id="tbodyItems">
              <?php for ($n = 1; $n <= 10; $n++): ?>
              <tr class="fila-item" data-row="<?= $n ?>">
                <td style="padding:.5rem .75rem;text-align:center;border-bottom:1px solid var(--border-color);
                            color:var(--text-muted);font-size:var(--font-size-sm)">
                  <?= $n ?>
                </td>
                <td style="padding:.375rem .5rem;border-bottom:1px solid var(--border-color)">
                  <input type="text" name="concepto[]"
                         class="form-control inp-concepto"
                         style="border:none;background:transparent;padding:.375rem .5rem;
                                font-size:var(--font-size-sm)"
                         placeholder="Describe el artículo…">
                </td>
                <td style="padding:.375rem .5rem;border-bottom:1px solid var(--border-color)">
                  <input type="number" name="cantidad[]"
                         class="form-control inp-cantidad"
                         style="border:none;background:transparent;
                                text-align:center;font-size:var(--font-size-sm)"
                         value="" min="0" step="0.001" placeholder="0">
                </td>
                <td style="padding:.375rem .5rem;border-bottom:1px solid var(--border-color)">
                  <input type="text" name="especificaciones[]"
                         class="form-control"
                         style="border:none;background:transparent;
                                font-size:var(--font-size-sm)"
                         placeholder="Marca, modelo, medida…">
                </td>
                <td style="padding:.375rem .5rem;border-bottom:1px solid var(--border-color)">
                  <input type="number" name="precio_unitario[]"
                         class="form-control inp-precio"
                         style="border:none;background:transparent;
                                text-align:right;font-size:var(--font-size-sm)"
                         value="" min="0" step="0.0001" placeholder="$0.00">
                </td>
                <td style="padding:.5rem .75rem;border-bottom:1px solid var(--border-color);
                            text-align:right;font-size:var(--font-size-sm);
                            color:var(--color-primary);font-weight:600" class="td-total">
                  —
                </td>
              </tr>
              <?php endfor; ?>
            </tbody>
            <!-- Total general -->
            <tfoot>
              <tr style="background:var(--bg-table-header)">
                <td colspan="5" style="padding:.75rem;text-align:right;
                                        font-weight:700;font-size:var(--font-size-sm);
                                        border-top:2px solid var(--border-color)">
                  COSTO TOTAL C/IVA
                </td>
                <td style="padding:.75rem;text-align:right;font-weight:700;
                            color:var(--color-primary);
                            border-top:2px solid var(--border-color)" id="tdTotalGeneral">
                  $0.00
                </td>
              </tr>
            </tfoot>
          </table>
        </div>

        <!-- Alerta cotizaciones -->
        <div id="alertaCotizaciones" class="alert alert-warning"
             style="display:none;margin:1rem 1.25rem;margin-bottom:0">
          <i class="ti ti-alert-triangle"></i>
          <span>
            <strong>El monto supera $25,000.00.</strong>
            Se deberán adjuntar 3 cotizaciones al enviar esta requisición.
          </span>
        </div>

        <div class="form-error" id="errItems" style="padding:.5rem 1.25rem"></div>
      </div>
    </div>

    <!-- Justificación -->
    <div class="card">
      <div class="card-header">
        <div class="card-title">Justificación</div>
      </div>
      <div class="card-body">
        <textarea name="justificacion" class="form-control" rows="4"
                  placeholder="Describe el motivo de la requisición…"></textarea>
      </div>
    </div>

  </div>

  <!-- Panel lateral -->
  <div style="display:flex;flex-direction:column;gap:1.25rem">

    <!-- Resumen -->
    <div class="card">
      <div class="card-header"><div class="card-title">Resumen</div></div>
      <div class="card-body">
        <div style="display:flex;flex-direction:column;gap:.875rem">
          <div>
            <div style="font-size:var(--font-size-xs);color:var(--text-muted)">Subtotal</div>
            <div style="font-size:var(--font-size-lg);font-weight:700;
                        color:var(--text-primary)" id="resSubtotal">$0.00</div>
          </div>
          <div>
            <div style="font-size:var(--font-size-xs);color:var(--text-muted)">IVA (16%)</div>
            <div id="resIva" style="font-size:var(--font-size-sm)">$0.00</div>
          </div>
          <div style="border-top:2px solid var(--border-color);padding-top:.875rem">
            <div style="font-size:var(--font-size-xs);color:var(--text-muted)">Total con IVA</div>
            <div style="font-size:var(--font-size-2xl);font-weight:700;
                        color:var(--color-primary)" id="resTotal">$0.00</div>
          </div>
          <div id="resAlertaCotiz" style="display:none">
            <span class="badge badge-warning">⚠ Requiere 3 cotizaciones</span>
          </div>
        </div>
      </div>
      <div class="card-footer">
        <button type="button" class="btn btn-primary w-100" id="btnGuardarReq">
          <span id="btnReqTexto">
            <i class="ti ti-device-floppy"></i> Guardar como borrador
          </span>
          <span id="btnReqSpinner" style="display:none">
            <i class="ti ti-loader-2" style="animation:spin .75s linear infinite"></i>
          </span>
        </button>
      </div>
    </div>

    <!-- Info de firmantes -->
    <div class="card">
      <div class="card-header"><div class="card-title">Firmantes del documento</div></div>
      <div class="card-body">
        <dl style="font-size:var(--font-size-sm);display:flex;flex-direction:column;gap:.75rem">
          <div>
            <dt style="font-size:var(--font-size-xs);color:var(--text-muted);
                       text-transform:uppercase;letter-spacing:.04em">Solicita (Director)</dt>
            <dd style="font-weight:500"><?= e($_SESSION['usuario_nombre'] ?? '') ?></dd>
          </div>
          <div>
            <dt style="font-size:var(--font-size-xs);color:var(--text-muted);
                       text-transform:uppercase;letter-spacing:.04em">Valida</dt>
            <dd>
              <?= e($config['institucion']['jefe_recursos'] ?? 'Jefe de Recursos Materiales') ?>
            </dd>
          </div>
          <div>
            <dt style="font-size:var(--font-size-xs);color:var(--text-muted);
                       text-transform:uppercase;letter-spacing:.04em">Autoriza</dt>
            <dd>
              <?= e($config['institucion']['director_admin'] ?? 'Director Administrativo') ?>
            </dd>
          </div>
        </dl>
      </div>
    </div>

    <div class="alert alert-info" style="margin:0">
      <i class="ti ti-info-circle"></i>
      <span style="font-size:var(--font-size-sm)">
        La requisición <strong>no descuenta stock</strong>.
        Es un documento de solicitud de compra.
      </span>
    </div>

  </div>

</div>
</form>

<?php
ob_start();
$baseUrlJs = e($config['app']['url']);
?>
<script>
(function () {
'use strict';
const BASE    = '<?= $baseUrlJs ?>';
const UMBRAL  = 25000;
const TASA_IVA= 0.16;

// ── Calcular totales en tiempo real ──
document.querySelectorAll('.fila-item').forEach(fila => {
  const inCant  = fila.querySelector('.inp-cantidad');
  const inPrecio= fila.querySelector('.inp-precio');
  const tdTotal = fila.querySelector('.td-total');

  function calcFila() {
    const cant  = parseFloat(inCant.value)  || 0;
    const precio= parseFloat(inPrecio.value)|| 0;
    const total = cant * precio;
    tdTotal.textContent = total > 0 ? fmt(total) : '—';
    calcGeneral();
  }

  inCant.addEventListener('input', calcFila);
  inPrecio.addEventListener('input', calcFila);
});

function calcGeneral() {
  let subtotal = 0;
  document.querySelectorAll('.td-total').forEach(td => {
    const val = parseFloat(td.textContent.replace(/[$,]/g, '')) || 0;
    subtotal += val;
  });

  const iva   = subtotal * TASA_IVA;
  const total = subtotal + iva;

  document.getElementById('resSubtotal').textContent   = fmt(subtotal);
  document.getElementById('resIva').textContent        = fmt(iva);
  document.getElementById('resTotal').textContent      = fmt(total);
  document.getElementById('tdTotalGeneral').textContent= fmt(total);

  const requiereCotiz = total > UMBRAL;
  document.getElementById('alertaCotizaciones').style.display = requiereCotiz ? 'flex' : 'none';
  document.getElementById('resAlertaCotiz').style.display     = requiereCotiz ? 'block': 'none';
}

// ── Guardar ──
document.getElementById('btnGuardarReq').addEventListener('click', async function () {
  const errDiv = document.getElementById('errItems');
  errDiv.textContent = '';

  // Verificar al menos un concepto
  const conceptos = document.querySelectorAll('.inp-concepto');
  const hayConcepto = Array.from(conceptos).some(c => c.value.trim() !== '');
  if (!hayConcepto) {
    errDiv.textContent = 'Agrega al menos un concepto a la requisición.';
    return;
  }

  setLoading(true);
  const fd = new FormData(document.getElementById('formRequisicion'));
  try {
    const res  = await fetch(`${BASE}/requisiciones`, { method: 'POST', body: fd });
    const json = await res.json();

    if (json.success) {
      let msg = json.message;
      if (json.data?.alerta) msg += '\n\n⚠ ' + json.data.alerta;
      await SwalInst.fire({
        icon: 'success', title: '¡Requisición guardada!',
        html: `Folio: <b>${json.data.folio}</b>` +
              (json.data.alerta ? `<br><small style="color:var(--color-accent)">${json.data.alerta}</small>` : ''),
        confirmButtonText: 'Ver requisición',
      });
      window.location.href = `${BASE}/requisiciones/${json.data.id}`;
    } else {
      const errores = json.errors || {};
      Object.entries(errores).forEach(([k, v]) => {
        const el = document.getElementById('err' + k.charAt(0).toUpperCase() + k.slice(1));
        if (el) el.textContent = v;
      });
      if (errores.items) errDiv.textContent = errores.items;
      SwalInst.fire({ icon: 'error', title: json.message || 'Error al guardar.' });
    }
  } catch { SwalInst.fire({ icon: 'error', title: 'Error de conexión.' }); }
  finally  { setLoading(false); }
});

function setLoading(on) {
  document.getElementById('btnGuardarReq').disabled = on;
  document.getElementById('btnReqTexto').style.display   = on ? 'none'  :'inline';
  document.getElementById('btnReqSpinner').style.display = on ? 'inline':'none';
}

function fmt(n) {
  return '$' + n.toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
})();
</script>
<?php $extraJs = ob_get_clean(); ?>
