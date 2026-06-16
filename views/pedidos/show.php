<?php
if (!isset($config)) { $config = $GLOBALS['app_config'] ?? require ROOT_PATH . '/config/config.php'; }
/**
 * views/pedidos/show.php
 *
 * Detalle completo de un pedido.
 * Almacenista/Admin puede registrar entregas parciales o totales por ítem.
 * Variables: $pedido (array formateado con items[])
 */

$baseUrl = e($config['app']['url']);
$rol     = (int) ($_SESSION['usuario_rol'] ?? 3);
$esPropietario = (int) $pedido['solicitante_id'] === (int) ($_SESSION['usuario_id'] ?? 0);
?>

<div class="page-header">
  <div>
    <div style="display:flex;align-items:center;gap:.75rem;flex-wrap:wrap">
      <h1 class="page-title"><?= e($pedido['folio']) ?></h1>
      <span class="badge <?= e($pedido['estado_clase']) ?>" style="font-size:.8rem">
        <?= e($pedido['estado_label']) ?>
      </span>
      <?php if ($pedido['es_urgente']): ?>
      <span class="badge badge-danger" style="font-size:.7rem">
        <i class="ti ti-flame" aria-hidden="true"></i> URGENTE
      </span>
      <?php endif; ?>
    </div>
    <p class="page-subtitle">
      Solicitado por <?= e($pedido['solicitante_nombre']) ?>
      el <?= e($pedido['fecha_fmt']) ?>
    </p>
  </div>

  <div style="display:flex;gap:.625rem">
    <?php if ($pedido['puede_cancelar'] && ($rol <= 2 || $esPropietario)): ?>
    <button class="btn btn-outline-danger" id="btnCancelarPedido"
            data-id="<?= (int) $pedido['id'] ?>"
            data-folio="<?= e($pedido['folio']) ?>">
      <i class="ti ti-x" aria-hidden="true"></i> Cancelar
    </button>
    <?php endif; ?>
    <?php if ($rol <= 2 && $pedido['puede_entregar']): ?>
    <button class="btn btn-primary" id="btnGuardarEntregas">
      <span id="btnEntTexto">
        <i class="ti ti-check" aria-hidden="true"></i> Guardar entregas
      </span>
      <span id="btnEntSpinner" style="display:none">
        <i class="ti ti-loader-2" style="animation:spin .75s linear infinite"></i>
      </span>
    </button>
    <?php endif; ?>
  </div>
</div>

<div style="display:grid;grid-template-columns:2fr 1fr;gap:1.25rem;align-items:start">

  <!-- ─ Ítems del pedido ─ -->
  <div>
    <div class="card">
      <div class="card-header">
        <div>
          <div class="card-title">Artículos solicitados</div>
          <div class="card-subtitle">
            <?= count($pedido['items']) ?> artículo<?= count($pedido['items']) !== 1 ? 's' : '' ?>
          </div>
        </div>
        <!-- Progreso global -->
        <div style="text-align:right">
          <div style="font-size:var(--font-size-xs);color:var(--text-muted);margin-bottom:.25rem">
            Progreso de entrega
          </div>
          <div style="font-size:var(--font-size-lg);font-weight:700;
                      color:var(--color-primary)">
            <?= (int) $pedido['pct_entregado'] ?>%
          </div>
        </div>
      </div>

      <?php if (empty($pedido['items'])): ?>
      <div class="card-body" style="text-align:center;color:var(--text-muted);padding:2rem">
        Sin artículos en este pedido.
      </div>
      <?php else: ?>

      <form id="formEntregas">
        <?= Security::csrfField() ?>
        <?php foreach ($pedido['items'] as $item): ?>
        <div style="padding:1.25rem;border-bottom:1px solid var(--border-color)">
          <div style="display:grid;grid-template-columns:1fr auto;gap:1rem;align-items:start">

            <!-- Info del producto -->
            <div>
              <div style="display:flex;align-items:center;gap:.625rem;margin-bottom:.5rem">
                <span style="font-weight:600;font-family:monospace;font-size:var(--font-size-sm);
                             color:var(--color-primary)">
                  <?= e($item['codigo']) ?>
                </span>
                <span class="badge <?= e($item['item_clase']) ?>">
                  <?= e($item['item_label']) ?>
                </span>
              </div>

              <div style="font-weight:500;margin-bottom:.5rem">
                <?= e($item['producto_nombre']) ?>
              </div>

              <!-- Progreso del ítem -->
              <div style="display:grid;grid-template-columns:repeat(3,1fr);
                          gap:.5rem;font-size:var(--font-size-xs);
                          color:var(--text-muted);margin-bottom:.625rem">
                <div>
                  <div>Solicitado</div>
                  <div style="font-weight:600;color:var(--text-primary)">
                    <?= e($item['sol_texto']) ?>
                  </div>
                </div>
                <div>
                  <div>Entregado</div>
                  <div style="font-weight:600;color:var(--status-success-text)">
                    <?= e($item['ent_texto']) ?>
                  </div>
                </div>
                <div>
                  <div>Pendiente</div>
                  <div style="font-weight:600;color:var(--status-warning-text)">
                    <?= e($item['pend_texto']) ?>
                  </div>
                </div>
              </div>

              <!-- Stock disponible -->
              <div style="font-size:var(--font-size-xs);color:var(--text-muted)">
                Stock disponible:
                <strong style="color:var(--color-primary)">
                  <?= e($item['stock_texto']) ?>
                </strong>
              </div>
            </div>

            <!-- Campo de entrega (solo Admin/Almacenista y si el ítem puede entregarse) -->
            <?php if ($rol <= 2 && $item['puede_entregar']): ?>
            <div style="min-width:160px">
              <input type="hidden" name="item_id[]" value="<?= (int) $item['id'] ?>">
              <label class="form-label" style="font-size:var(--font-size-xs)">
                Piezas a entregar ahora
              </label>
              <input type="number"
                     name="cantidad_piezas[]"
                     class="form-control inp-entrega"
                     value="0"
                     min="0"
                     max="<?= (int) ($item['cantidad_piezas'] - $item['cantidad_entregada_piezas']) ?>"
                     data-max="<?= (int) ($item['cantidad_piezas'] - $item['cantidad_entregada_piezas']) ?>"
                     data-stock="<?= (int) $item['stock_actual'] ?>"
                     style="text-align:center">
              <div style="font-size:var(--font-size-xs);color:var(--text-muted);
                          margin-top:.25rem;text-align:center">
                máx. <?= (int) ($item['cantidad_piezas'] - $item['cantidad_entregada_piezas']) ?> pz.
              </div>
            </div>
            <?php elseif ($item['estado_item'] === 'entregado'): ?>
            <div style="color:var(--status-success-text);font-size:1.5rem">
              <i class="ti ti-circle-check" aria-hidden="true"></i>
            </div>
            <?php elseif ($item['estado_item'] === 'cancelado'): ?>
            <div style="color:var(--status-danger-text);font-size:1.5rem">
              <i class="ti ti-circle-x" aria-hidden="true"></i>
            </div>
            <?php endif; ?>

          </div>
        </div>
        <?php endforeach; ?>
      </form>

      <?php endif; ?>
    </div>
  </div>

  <!-- ─ Panel lateral: detalles del pedido ─ -->
  <div style="display:flex;flex-direction:column;gap:1.25rem">

    <!-- Datos generales -->
    <div class="card">
      <div class="card-header">
        <div class="card-title">Detalles</div>
      </div>
      <div class="card-body">
        <dl style="display:flex;flex-direction:column;gap:.875rem;font-size:var(--font-size-sm)">

          <div>
            <dt style="font-size:var(--font-size-xs);color:var(--text-muted);
                       text-transform:uppercase;letter-spacing:.05em;margin-bottom:.2rem">
              Solicitante
            </dt>
            <dd style="font-weight:500"><?= e($pedido['solicitante_nombre']) ?></dd>
            <dd style="font-size:var(--font-size-xs);color:var(--text-muted)">
              <?= e($pedido['solicitante_email'] ?? '') ?>
            </dd>
          </div>

          <?php if ($pedido['empleado_nombre']): ?>
          <div>
            <dt style="font-size:var(--font-size-xs);color:var(--text-muted);
                       text-transform:uppercase;letter-spacing:.05em;margin-bottom:.2rem">
              Para empleado
            </dt>
            <dd style="font-weight:500">
              <?= e($pedido['empleado_nombre'] . ' ' . $pedido['empleado_apellidos']) ?>
            </dd>
            <?php if ($pedido['empleado_puesto']): ?>
            <dd style="font-size:var(--font-size-xs);color:var(--text-muted)">
              <?= e($pedido['empleado_puesto']) ?>
            </dd>
            <?php endif; ?>
          </div>
          <?php endif; ?>

          <?php if ($pedido['plantel']): ?>
          <div>
            <dt style="font-size:var(--font-size-xs);color:var(--text-muted);
                       text-transform:uppercase;letter-spacing:.05em;margin-bottom:.2rem">
              Plantel / Área
            </dt>
            <dd><?= e($pedido['plantel']) ?></dd>
          </div>
          <?php endif; ?>

          <?php if ($pedido['fecha_req_fmt']): ?>
          <div>
            <dt style="font-size:var(--font-size-xs);color:var(--text-muted);
                       text-transform:uppercase;letter-spacing:.05em;margin-bottom:.2rem">
              Fecha requerida
            </dt>
            <dd style="font-weight:500;color:var(--color-accent)">
              <?= e($pedido['fecha_req_fmt']) ?>
            </dd>
          </div>
          <?php endif; ?>

          <?php if ($pedido['almacenista_nombre']): ?>
          <div>
            <dt style="font-size:var(--font-size-xs);color:var(--text-muted);
                       text-transform:uppercase;letter-spacing:.05em;margin-bottom:.2rem">
              Atendido por
            </dt>
            <dd><?= e($pedido['almacenista_nombre']) ?></dd>
            <?php if ($pedido['fecha_atencion']): ?>
            <dd style="font-size:var(--font-size-xs);color:var(--text-muted)">
              <?= date('d/m/Y H:i', strtotime($pedido['fecha_atencion'])) ?>
            </dd>
            <?php endif; ?>
          </div>
          <?php endif; ?>

          <?php if ($pedido['observaciones']): ?>
          <div>
            <dt style="font-size:var(--font-size-xs);color:var(--text-muted);
                       text-transform:uppercase;letter-spacing:.05em;margin-bottom:.2rem">
              Observaciones
            </dt>
            <dd style="color:var(--text-secondary);line-height:1.5">
              <?= e($pedido['observaciones']) ?>
            </dd>
          </div>
          <?php endif; ?>

        </dl>
      </div>
    </div>

    <!-- Barra de progreso grande -->
    <div class="card">
      <div class="card-body" style="text-align:center;padding:1.5rem">
        <div style="font-size:3rem;font-weight:700;
                    color:var(--color-primary);line-height:1;margin-bottom:.5rem">
          <?= (int) $pedido['pct_entregado'] ?>%
        </div>
        <div style="font-size:var(--font-size-sm);color:var(--text-muted);margin-bottom:1rem">
          del pedido entregado
        </div>
        <div class="stock-bar" style="height:10px">
          <div class="stock-bar-fill <?= $pedido['pct_entregado'] >= 100 ? 'ok' : ($pedido['pct_entregado'] > 0 ? 'warning' : 'critical') ?>"
               style="width:<?= (int) $pedido['pct_entregado'] ?>%"></div>
        </div>
      </div>
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

// ── Validar que no se entregue más del stock disponible ──
document.querySelectorAll('.inp-entrega').forEach(inp => {
  inp.addEventListener('input', function () {
    const max   = parseInt(this.dataset.max)   || 0;
    const stock = parseInt(this.dataset.stock) || 0;
    const val   = parseInt(this.value)         || 0;
    const limite = Math.min(max, stock);
    if (val > limite) { this.value = limite; }
    if (val < 0)      { this.value = 0; }
  });
});

// ── Guardar entregas ──
document.getElementById('btnGuardarEntregas')?.addEventListener('click', async function () {
  // Verificar que hay al menos un ítem con cantidad > 0
  const inps   = document.querySelectorAll('.inp-entrega');
  const hayAlgo = Array.from(inps).some(i => parseInt(i.value) > 0);

  if (!hayAlgo) {
    SwalInst.fire({
      icon:  'info',
      title: 'Ingresa la cantidad entregada en al menos un artículo.'
    });
    return;
  }

  setLoading(true);
  const fd = new FormData(document.getElementById('formEntregas'));

  try {
    const res  = await fetch(`${BASE}/pedidos/<?= (int) $pedido['id'] ?>/entregar`, {
      method: 'POST', body: fd,
    });
    const json = await res.json();

    if (json.success) {
      await SwalInst.fire({
        icon:              'success',
        title:             json.message,
        timer:             2000,
        showConfirmButton: false,
      });
      location.reload();
    } else {
      SwalInst.fire({ icon: 'error', title: json.message });
    }
  } catch {
    SwalInst.fire({ icon: 'error', title: 'Error de conexión.' });
  } finally {
    setLoading(false);
  }
});

function setLoading(on) {
  const btn = document.getElementById('btnGuardarEntregas');
  if (!btn) return;
  btn.disabled = on;
  document.getElementById('btnEntTexto').style.display   = on ? 'none'   : 'inline';
  document.getElementById('btnEntSpinner').style.display = on ? 'inline' : 'none';
}

// ── Cancelar pedido ──
document.getElementById('btnCancelarPedido')?.addEventListener('click', async function () {
  const id    = this.dataset.id;
  const folio = this.dataset.folio;

  const { value: motivo, isConfirmed } = await SwalInst.fire({
    icon:              'warning',
    title:             `Cancelar ${folio}`,
    input:             'textarea',
    inputLabel:        'Motivo de cancelación',
    inputPlaceholder:  'Describe el motivo…',
    showCancelButton:  true,
    confirmButtonText: 'Cancelar pedido',
    cancelButtonText:  'Volver',
    inputValidator: v => !v || v.length < 5 ? 'El motivo debe tener al menos 5 caracteres.' : null,
  });

  if (!isConfirmed) return;

  const fd = new FormData();
  fd.append('_csrf_token', window.CSRF_TOKEN);
  fd.append('motivo', motivo);

  try {
    const res  = await fetch(`${BASE}/pedidos/${id}/cancelar`, { method: 'POST', body: fd });
    const json = await res.json();
    if (json.success) {
      await SwalInst.fire({ icon:'success', title: json.message, timer:1800, showConfirmButton:false });
      location.reload();
    } else {
      SwalInst.fire({ icon:'error', title: json.message });
    }
  } catch {
    SwalInst.fire({ icon:'error', title: 'Error de conexión.' });
  }
});

})();
</script>
<?php $extraJs = ob_get_clean(); ?>
