<?php
if (!isset($config)) { $config = $GLOBALS['app_config'] ?? require ROOT_PATH . '/config/config.php'; }
/**
 * views/requisiciones/index.php
 * Variables: $requisiciones, $paginacion, $contadores, $filtros
 */

$baseUrl = e($config['app']['url']);
$rol     = (int) ($_SESSION['usuario_rol'] ?? 3);

$estados = [
    ''           => 'Todas',
    'borrador'   => 'Borrador',
    'enviada'    => 'Enviadas',
    'validada'   => 'Validadas',
    'autorizada' => 'Autorizadas',
    'rechazada'  => 'Rechazadas',
    'comprada'   => 'Compradas',
    'cancelada'  => 'Canceladas',
];
?>

<div class="page-header">
  <div>
    <h1 class="page-title">Requisiciones</h1>
    <p class="page-subtitle">Solicitudes institucionales de adquisición de bienes</p>
  </div>
  <a href="<?= $baseUrl ?>/requisiciones/nueva" class="btn btn-primary">
    <i class="ti ti-plus"></i> Nueva requisición
  </a>
</div>

<!-- Tabs de estado -->
<div style="display:flex;gap:.375rem;flex-wrap:wrap;margin-bottom:1.25rem">
  <?php foreach ($estados as $val => $label): ?>
  <?php
    $activo = ($filtros['estado'] ?? '') === $val;
    $count  = $val === '' ? array_sum($contadores) : ($contadores[$val] ?? 0);
    $qs     = $val ? '?estado=' . $val : '';
  ?>
  <a href="<?= $baseUrl ?>/requisiciones<?= $qs ?>"
     style="display:flex;align-items:center;gap:.375rem;
            padding:.5rem 1rem;border-radius:var(--border-radius-sm);
            font-size:var(--font-size-sm);font-weight:<?= $activo ? '600':'400' ?>;
            text-decoration:none;
            background:<?= $activo ? 'var(--color-primary)':'var(--bg-surface)' ?>;
            color:<?= $activo ? '#fff':'var(--text-secondary)' ?>;
            border:1px solid <?= $activo ? 'var(--color-primary)':'var(--border-color)' ?>;
            transition:all var(--transition)">
    <?= e($label) ?>
    <?php if ($count > 0): ?>
    <span style="background:<?= $activo ? 'rgba(255,255,255,.25)':'var(--bg-surface-2)' ?>;
                 border-radius:20px;padding:1px 6px;font-size:var(--font-size-xs)">
      <?= $count ?>
    </span>
    <?php endif; ?>
  </a>
  <?php endforeach; ?>
</div>

<!-- Tabla -->
<div class="card">
  <div class="card-body" style="padding:0">
    <?php if (empty($requisiciones)): ?>
    <div style="padding:3rem;text-align:center;color:var(--text-muted)">
      <i class="ti ti-file-off" style="font-size:2.5rem;display:block;margin-bottom:.75rem"></i>
      No hay requisiciones
      <?= !empty($filtros['estado']) ? "con estado «{$filtros['estado']}»" : '' ?>
    </div>
    <?php else: ?>
    <div class="table-container" style="border:none;border-radius:0">
      <table id="tablaReqs" class="table" style="width:100%">
        <thead>
          <tr>
            <th>Folio</th>
            <th>Plantel / Área</th>
            <th>Solicita</th>
            <th style="text-align:right">Total estimado</th>
            <th>Fecha</th>
            <th style="text-align:center">Estado</th>
            <th style="text-align:center;width:90px">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($requisiciones as $r): ?>
          <tr>
            <td>
              <a href="<?= $baseUrl ?>/requisiciones/<?= (int)$r['id'] ?>"
                 style="font-family:monospace;font-weight:700;
                        color:var(--color-primary);text-decoration:none;
                        font-size:var(--font-size-sm)">
                <?= e($r['folio']) ?>
              </a>
              <?php if ($r['alerta_cotiz']): ?>
              <div title="Requiere 3 cotizaciones">
                <span class="badge badge-warning" style="font-size:.6rem;margin-top:2px">
                  <i class="ti ti-file-invoice" style="font-size:.65rem"></i> 3 cotiz.
                </span>
              </div>
              <?php endif; ?>
            </td>
            <td style="font-size:var(--font-size-sm)">
              <?= e($r['plantel']) ?>
            </td>
            <td style="font-size:var(--font-size-sm);color:var(--text-muted)">
              <?= e($r['solicita_nombre']) ?>
            </td>
            <td style="text-align:right;font-weight:600;font-size:var(--font-size-sm)">
              <?= e($r['total_fmt']) ?>
            </td>
            <td style="font-size:var(--font-size-sm)">
              <?= e($r['fecha_fmt']) ?>
            </td>
            <td style="text-align:center">
              <span class="badge <?= e($r['estado_clase']) ?>">
                <?= e($r['estado_label']) ?>
              </span>
            </td>
            <td style="text-align:center">
              <div class="table-actions" style="justify-content:center">
                <a href="<?= $baseUrl ?>/requisiciones/<?= (int)$r['id'] ?>"
                   class="btn btn-ghost btn-icon" title="Ver detalle">
                  <i class="ti ti-eye"></i>
                </a>
                <a href="<?= $baseUrl ?>/requisiciones/<?= (int)$r['id'] ?>/pdf"
                   target="_blank"
                   class="btn btn-ghost btn-icon" title="Imprimir">
                  <i class="ti ti-printer"></i>
                </a>
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
      <?= number_format($paginacion['total']) ?> requisiciones
    </div>
    <div style="display:flex;gap:.375rem">
      <?php for ($i = 1; $i <= $paginacion['paginas']; $i++): ?>
      <?php $qs = http_build_query(array_merge($filtros, ['pagina' => $i])); ?>
      <a href="?<?= $qs ?>"
         class="btn btn-sm <?= $i === $paginacion['pagina_actual'] ? 'btn-primary':'btn-ghost' ?>">
        <?= $i ?>
      </a>
      <?php endfor; ?>
    </div>
  </div>
  <?php endif; ?>
</div>

<?php
ob_start();
?>
<script>
$('#tablaReqs').DataTable({
  language: { url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json' },
  pageLength: 25, order: [[0,'desc']],
  columnDefs: [{ orderable: false, targets: [5,6] }],
  dom: 'tp',
});
</script>
<?php $extraJs = ob_get_clean(); ?>
