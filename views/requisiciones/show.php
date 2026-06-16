<?php
if (!isset($config)) { $config = $GLOBALS['app_config'] ?? require ROOT_PATH . '/config/config.php'; }
/**
 * views/requisiciones/show.php
 * Variables: $req (array detallado con items[])
 */

$baseUrl = e($config['app']['url']);
$rol     = (int) ($_SESSION['usuario_rol'] ?? 3);

$accionLabels = [
    'enviada'    => ['label' => 'Enviar a Recursos Materiales', 'clase' => 'btn-primary',         'icono' => 'ti-send'],
    'validada'   => ['label' => 'Marcar como Validada',         'clase' => 'btn-outline-primary', 'icono' => 'ti-check'],
    'autorizada' => ['label' => 'Autorizar',                    'clase' => 'btn-primary',         'icono' => 'ti-shield-check'],
    'rechazada'  => ['label' => 'Rechazar',                     'clase' => 'btn-outline-danger',  'icono' => 'ti-x'],
    'comprada'   => ['label' => 'Marcar como Comprada',         'clase' => 'btn-secondary',       'icono' => 'ti-shopping-bag'],
    'cancelada'  => ['label' => 'Cancelar',                     'clase' => 'btn-outline-danger',  'icono' => 'ti-trash'],
];
?>

<div class="page-header">
  <div>
    <div style="display:flex;align-items:center;gap:.75rem;flex-wrap:wrap">
      <h1 class="page-title"><?= e($req['folio']) ?></h1>
      <span class="badge <?= e($req['estado_clase']) ?>" style="font-size:.8rem">
        <?= e($req['estado_label']) ?>
      </span>
      <?php if ($req['alerta_cotiz']): ?>
      <span class="badge badge-warning" style="font-size:.7rem">
        <i class="ti ti-file-invoice"></i> Requiere 3 cotizaciones
      </span>
      <?php endif; ?>
    </div>
    <p class="page-subtitle">
      Elaborada por <?= e($req['solicita_nombre']) ?>
      el <?= e($req['fecha_fmt']) ?>
    </p>
  </div>
  <div style="display:flex;gap:.625rem;flex-wrap:wrap">
    <a href="<?= $baseUrl ?>/requisiciones/<?= (int)$req['id'] ?>/pdf"
       target="_blank" class="btn btn-outline-primary">
      <i class="ti ti-printer"></i> Imprimir
    </a>
    <button class="btn btn-ghost" onclick="window.location.href='<?= $baseUrl ?>/requisiciones/<?= (int)$req['id'] ?>/excel'">
      <i class="ti ti-file-spreadsheet"></i> Excel
    </button>
  </div>
</div>

<div style="display:grid;grid-template-columns:2fr 1fr;gap:1.25rem;align-items:start">

  <!-- ─ Contenido principal ─ -->
  <div style="display:flex;flex-direction:column;gap:1.25rem">

    <!-- Datos de cabecera -->
    <div class="card">
      <div class="card-header"><div class="card-title">Datos de la requisición</div></div>
      <div class="card-body">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.25rem;font-size:var(--font-size-sm)">
          <div>
            <div style="font-size:var(--font-size-xs);color:var(--text-muted);
                        text-transform:uppercase;letter-spacing:.04em;margin-bottom:.2rem">
              Plantel / Área
            </div>
            <div style="font-weight:500"><?= e($req['plantel']) ?></div>
          </div>
          <?php if ($req['clave_programatica']): ?>
          <div>
            <div style="font-size:var(--font-size-xs);color:var(--text-muted);
                        text-transform:uppercase;letter-spacing:.04em;margin-bottom:.2rem">
              Clave programática
            </div>
            <div style="font-family:monospace"><?= e($req['clave_programatica']) ?></div>
          </div>
          <?php endif; ?>
          <div>
            <div style="font-size:var(--font-size-xs);color:var(--text-muted);
                        text-transform:uppercase;letter-spacing:.04em;margin-bottom:.2rem">
              Fecha de elaboración
            </div>
            <div><?= e($req['fecha_fmt']) ?></div>
          </div>
          <div>
            <div style="font-size:var(--font-size-xs);color:var(--text-muted);
                        text-transform:uppercase;letter-spacing:.04em;margin-bottom:.2rem">
              Total estimado
            </div>
            <div style="font-weight:700;color:var(--color-primary);font-size:var(--font-size-lg)">
              <?= e($req['total_iva_fmt']) ?>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Tabla de conceptos -->
    <div class="card">
      <div class="card-header"><div class="card-title">Conceptos</div></div>
      <div class="card-body" style="padding:0">
        <div style="overflow-x:auto">
          <table class="table" style="width:100%;min-width:650px">
            <thead>
              <tr>
                <th style="width:40px;text-align:center">No.</th>
                <th>Concepto</th>
                <th style="text-align:center;width:80px">Cantidad</th>
                <th>Especificaciones</th>
                <th style="text-align:right;width:110px">Precio unit.</th>
                <th style="text-align:right;width:110px">Total</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($req['items'] as $item): ?>
              <tr>
                <td style="text-align:center;color:var(--text-muted);font-size:var(--font-size-sm)">
                  <?= (int)$item['numero_item'] ?>
                </td>
                <td style="font-size:var(--font-size-sm);font-weight:500">
                  <?= e($item['concepto']) ?>
                </td>
                <td style="text-align:center;font-size:var(--font-size-sm)">
                  <?= e($item['cantidad_fmt']) ?>
                </td>
                <td style="font-size:var(--font-size-sm);color:var(--text-muted)">
                  <?= e($item['especificaciones'] ?? '—') ?>
                </td>
                <td style="text-align:right;font-size:var(--font-size-sm)">
                  <?= e($item['precio_fmt']) ?>
                </td>
                <td style="text-align:right;font-weight:600;font-size:var(--font-size-sm);
                            color:var(--color-primary)">
                  <?= e($item['total_fmt']) ?>
                </td>
              </tr>
              <?php endforeach; ?>
              <!-- Renglones vacíos hasta 10 (como el formato) -->
              <?php for ($i = count($req['items']); $i < 10; $i++): ?>
              <tr style="height:32px">
                <td style="color:var(--text-muted);text-align:center;
                            font-size:var(--font-size-xs)">
                  <?= $i + 1 ?>
                </td>
                <td colspan="5" style="border-bottom:1px solid var(--border-color)"></td>
              </tr>
              <?php endfor; ?>
            </tbody>
            <tfoot>
              <tr style="background:var(--bg-table-header)">
                <td colspan="4" style="padding:.75rem 1rem;text-align:right;
                                        font-weight:700;font-size:var(--font-size-sm)">
                  Subtotal
                </td>
                <td></td>
                <td style="padding:.75rem 1rem;text-align:right;font-weight:700">
                  <?= e($req['subtotal_fmt']) ?>
                </td>
              </tr>
              <tr style="background:var(--bg-table-header)">
                <td colspan="4" style="padding:.625rem 1rem;text-align:right;
                                        font-weight:700;font-size:var(--font-size-sm)">
                  COSTO TOTAL C/IVA (16%)
                </td>
                <td></td>
                <td style="padding:.625rem 1rem;text-align:right;font-weight:700;
                            color:var(--color-primary);font-size:var(--font-size-md)">
                  <?= e($req['total_iva_fmt']) ?>
                </td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>
    </div>

    <!-- Justificación -->
    <?php if ($req['justificacion']): ?>
    <div class="card">
      <div class="card-header"><div class="card-title">Justificación</div></div>
      <div class="card-body">
        <p style="font-size:var(--font-size-sm);color:var(--text-secondary);line-height:1.7">
          <?= e($req['justificacion']) ?>
        </p>
      </div>
    </div>
    <?php endif; ?>

  </div>

  <!-- ─ Panel lateral ─ -->
  <div style="display:flex;flex-direction:column;gap:1.25rem">

    <!-- Acciones de estado -->
    <?php if (!empty($req['acciones_disponibles'])): ?>
    <div class="card">
      <div class="card-header"><div class="card-title">Acciones</div></div>
      <div class="card-body" style="display:flex;flex-direction:column;gap:.625rem">
        <?php foreach ($req['acciones_disponibles'] as $accion): ?>
        <?php $info = $accionLabels[$accion] ?? null; if (!$info) continue; ?>
        <button class="btn <?= e($info['clase']) ?> btn-accion"
                data-accion="<?= e($accion) ?>"
                data-folio="<?= e($req['folio']) ?>">
          <i class="ti <?= e($info['icono']) ?>"></i>
          <?= e($info['label']) ?>
        </button>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- Flujo de firmas -->
    <div class="card">
      <div class="card-header"><div class="card-title">Firmas</div></div>
      <div class="card-body">
        <div style="display:flex;flex-direction:column;gap:1rem;font-size:var(--font-size-sm)">

          <?php
          $firmas = [
            ['rol' => 'Solicita (Director)',          'nombre' => $req['solicita_nombre'],  'relleno' => true],
            ['rol' => 'Valida (Jefe Rec. Materiales)', 'nombre' => $req['valida_nombre'],    'relleno' => !empty($req['valida_nombre'])],
            ['rol' => 'Autoriza (Dir. Administrativo)','nombre' => $req['autoriza_nombre'],  'relleno' => !empty($req['autoriza_nombre'])],
          ];
          foreach ($firmas as $f):
          ?>
          <div style="display:flex;align-items:center;gap:.75rem">
            <div style="width:28px;height:28px;border-radius:50%;
                        background:<?= $f['relleno'] ? 'var(--status-success-bg)':'var(--bg-surface-2)' ?>;
                        display:flex;align-items:center;justify-content:center;flex-shrink:0">
              <i class="ti <?= $f['relleno'] ? 'ti-check':'ti-dots' ?>"
                 style="font-size:.8rem;color:<?= $f['relleno'] ? 'var(--status-success-text)':'var(--text-muted)' ?>">
              </i>
            </div>
            <div>
              <div style="font-size:var(--font-size-xs);color:var(--text-muted)"><?= e($f['rol']) ?></div>
              <div style="font-weight:<?= $f['relleno'] ? '500':'400' ?>;
                          color:<?= $f['relleno'] ? 'var(--text-primary)':'var(--text-muted)' ?>">
                <?= $f['nombre'] ? e($f['nombre']) : 'Pendiente' ?>
              </div>
            </div>
          </div>
          <?php endforeach; ?>

        </div>
      </div>
    </div>

    <!-- Nota cotizaciones -->
    <?php if ($req['alerta_cotiz']): ?>
    <div class="card" style="border-color:var(--color-accent-soft)">
      <div class="card-body">
        <div style="display:flex;gap:.75rem;align-items:flex-start">
          <i class="ti ti-alert-triangle"
             style="color:var(--color-accent);font-size:1.25rem;flex-shrink:0;margin-top:.1rem"></i>
          <div style="font-size:var(--font-size-sm)">
            <strong>Monto mayor a $25,000</strong>
            <p style="color:var(--text-secondary);margin-top:.25rem;line-height:1.5">
              Esta requisición requiere 3 cotizaciones de diferentes proveedores adjuntas al documento.
            </p>
          </div>
        </div>
      </div>
    </div>
    <?php endif; ?>

  </div>

</div>

<?php
ob_start();
?>
<script>
(function () {
'use strict';
const BASE = '<?= $baseUrl ?>';
const REQ_ID = <?= (int)$req['id'] ?>;

document.querySelectorAll('.btn-accion').forEach(btn => {
  btn.addEventListener('click', async function () {
    const accion = this.dataset.accion;
    const folio  = this.dataset.folio;

    const { isConfirmed } = await SwalInst.fire({
      icon: accion === 'rechazada' || accion === 'cancelada' ? 'warning' : 'question',
      title: this.textContent.trim(),
      html:  `¿Confirmas esta acción para la requisición <b>${folio}</b>?`,
      showCancelButton:  true,
      confirmButtonText: 'Confirmar',
      cancelButtonText:  'Cancelar',
    });
    if (!isConfirmed) return;

    this.disabled = true;
    const fd = new FormData();
    fd.append('_csrf_token', window.CSRF_TOKEN);
    fd.append('action', accion);

    try {
      const res  = await fetch(`${BASE}/requisiciones/${REQ_ID}`, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: fd,
      });
      const json = await res.json();
      if (json.success) {
        await SwalInst.fire({
          icon: 'success', title: json.message, timer: 1800, showConfirmButton: false,
        });
        location.reload();
      } else {
        SwalInst.fire({ icon: 'error', title: json.message });
      }
    } catch {
      SwalInst.fire({ icon: 'error', title: 'Error de conexión.' });
    } finally {
      this.disabled = false;
    }
  });
});
})();
</script>
<?php $extraJs = ob_get_clean(); ?>
