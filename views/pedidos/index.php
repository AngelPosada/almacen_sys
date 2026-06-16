<?php
if (!isset($config)) { $config = $GLOBALS['app_config'] ?? require ROOT_PATH . '/config/config.php'; }
/**
 * views/pedidos/index.php
 *
 * Lista de pedidos con contadores por estado y filtros.
 * Variables: $pedidos, $paginacion, $contadores, $filtros
 */

$baseUrl = e($config['app']['url']);
$rol     = (int) ($_SESSION['usuario_rol'] ?? 3);
?>

<!-- Encabezado -->
<div class="page-header">
  <div>
    <h1 class="page-title">Pedidos</h1>
    <p class="page-subtitle">Solicitudes de material al almacén</p>
  </div>
  <a href="<?= $baseUrl ?>/pedidos/nuevo" class="btn btn-primary">
    <i class="ti ti-plus" aria-hidden="true"></i> Nuevo pedido
  </a>
</div>

<!-- Tabs de estado (contadores rápidos) -->
<?php
$estados = [
  ''                   => ['label' => 'Todos',          'clase' => ''],
  'pendiente'          => ['label' => 'Pendientes',      'clase' => 'badge-pending'],
  'en_proceso'         => ['label' => 'En proceso',      'clase' => 'badge-info'],
  'entregado_parcial'  => ['label' => 'Parcial',         'clase' => 'badge-warning'],
  'entregado'          => ['label' => 'Entregados',      'clase' => 'badge-success'],
  'cancelado'          => ['label' => 'Cancelados',      'clase' => 'badge-danger'],
];
?>
<div style="display:flex;gap:.375rem;flex-wrap:wrap;margin-bottom:1.25rem">
  <?php foreach ($estados as $val => $info): ?>
  <?php
    $activo = ($filtros['estado'] ?? '') === $val;
    $count  = $val === '' ? array_sum($contadores) : ($contadores[$val] ?? 0);
    $qs     = $val ? '?estado=' . $val : '';
  ?>
  <a href="<?= $baseUrl ?>/pedidos<?= $qs ?>"
     style="display:flex;align-items:center;gap:.375rem;padding:.5rem 1rem;
            border-radius:var(--border-radius-sm);font-size:var(--font-size-sm);
            font-weight:<?= $activo ? '600' : '400' ?>;
            text-decoration:none;
            background:<?= $activo ? 'var(--color-primary)' : 'var(--bg-surface)' ?>;
            color:<?= $activo ? '#fff' : 'var(--text-secondary)' ?>;
            border:1px solid <?= $activo ? 'var(--color-primary)' : 'var(--border-color)' ?>;
            transition:all var(--transition)">
    <?= e($info['label']) ?>
    <?php if ($count > 0): ?>
    <span style="background:<?= $activo ? 'rgba(255,255,255,.25)' : 'var(--bg-surface-2)' ?>;
                 border-radius:20px;padding:1px 6px;font-size:var(--font-size-xs)">
      <?= $count ?>
    </span>
    <?php endif; ?>
  </a>
  <?php endforeach; ?>
</div>

<!-- Tabla de pedidos -->
<div class="card">
  <div class="card-body" style="padding:0">
    <?php if (empty($pedidos)): ?>
      <div style="padding:3rem;text-align:center;color:var(--text-muted)">
        <i class="ti ti-shopping-cart-off"
           style="font-size:2.5rem;display:block;margin-bottom:.75rem" aria-hidden="true"></i>
        No hay pedidos
        <?= !empty($filtros['estado']) ? "con estado «{$filtros['estado']}»" : '' ?>
      </div>
    <?php else: ?>
    <div class="table-container" style="border:none;border-radius:0">
      <table id="tablaPedidos" class="table" style="width:100%">
        <thead>
          <tr>
            <th>Folio</th>
            <th>Solicitante</th>
            <th style="text-align:center">Artículos</th>
            <th style="text-align:center">Entregado</th>
            <th style="text-align:center">Prioridad</th>
            <th style="text-align:center">Estado</th>
            <th>Fecha req.</th>
            <th style="text-align:center;width:100px">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($pedidos as $p): ?>
          <tr>
            <!-- Folio -->
            <td>
              <a href="<?= $baseUrl ?>/pedidos/<?= (int) $p['id'] ?>"
                 style="font-family:monospace;font-weight:700;
                        color:var(--color-primary);text-decoration:none">
                <?= e($p['folio']) ?>
              </a>
            </td>
            <!-- Solicitante -->
            <td>
              <div style="font-size:var(--font-size-sm);font-weight:500">
                <?= e($p['solicitante']) ?>
              </div>
              <?php if ($p['plantel']): ?>
              <div style="font-size:var(--font-size-xs);color:var(--text-muted)">
                <?= e($p['plantel']) ?>
              </div>
              <?php endif; ?>
            </td>
            <!-- Artículos -->
            <td style="text-align:center;font-weight:600">
              <?= (int) $p['total_items'] ?>
            </td>
            <!-- Progreso de entrega -->
            <td style="min-width:100px">
              <div style="text-align:center;font-size:var(--font-size-xs);
                          color:var(--text-muted);margin-bottom:.25rem">
                <?= (int) $p['pct_entregado'] ?>%
              </div>
              <div class="stock-bar" style="height:5px">
                <div class="stock-bar-fill <?= $p['pct_entregado'] >= 100 ? 'ok' : ($p['pct_entregado'] > 0 ? 'warning' : 'critical') ?>"
                     style="width:<?= (int) $p['pct_entregado'] ?>%"></div>
              </div>
            </td>
            <!-- Prioridad -->
            <td style="text-align:center">
              <?php if ($p['es_urgente']): ?>
              <span class="badge badge-danger" style="font-size:.6rem;letter-spacing:.04em">
                <i class="ti ti-flame" style="font-size:.7rem" aria-hidden="true"></i> URGENTE
              </span>
              <?php else: ?>
              <span style="font-size:var(--font-size-xs);color:var(--text-muted)">Normal</span>
              <?php endif; ?>
            </td>
            <!-- Estado -->
            <td style="text-align:center">
              <span class="badge <?= e($p['estado_clase']) ?>">
                <?= e($p['estado_label']) ?>
              </span>
            </td>
            <!-- Fecha requerida -->
            <td style="font-size:var(--font-size-sm);color:var(--text-muted)">
              <?= $p['fecha_req_fmt'] ?? '—' ?>
            </td>
            <!-- Acciones -->
            <td style="text-align:center">
              <div class="table-actions" style="justify-content:center">
                <a href="<?= $baseUrl ?>/pedidos/<?= (int) $p['id'] ?>"
                   class="btn btn-ghost btn-icon" title="Ver detalle">
                  <i class="ti ti-eye" aria-hidden="true"></i>
                </a>
                <?php if ($rol <= 2 && $p['puede_cancelar']): ?>
                <button class="btn btn-ghost btn-icon btn-cancelar"
                        title="Cancelar"
                        style="color:var(--status-danger-text)"
                        data-id="<?= (int) $p['id'] ?>"
                        data-folio="<?= e($p['folio']) ?>">
                  <i class="ti ti-x" aria-hidden="true"></i>
                </button>
                <?php endif; ?>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>

  <!-- Paginación -->
  <?php if (($paginacion['paginas'] ?? 0) > 1): ?>
  <div class="card-footer" style="display:flex;align-items:center;justify-content:space-between">
    <div style="font-size:var(--font-size-sm);color:var(--text-muted)">
      <?= number_format($paginacion['total']) ?> pedidos en total
    </div>
    <div style="display:flex;gap:.375rem">
      <?php for ($i = 1; $i <= $paginacion['paginas']; $i++): ?>
      <?php $qs = http_build_query(array_merge($filtros, ['pagina' => $i])); ?>
      <a href="?<?= $qs ?>"
         class="btn btn-sm <?= $i === $paginacion['pagina_actual'] ? 'btn-primary' : 'btn-ghost' ?>">
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
(function () {
'use strict';
const BASE = '<?= $baseUrl ?>';

$('#tablaPedidos').DataTable({
  language: { url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json' },
  pageLength: 25,
  order: [[0, 'desc']],
  columnDefs: [{ orderable: false, targets: [2,3,4,5,7] }],
  dom: 'tp',
});

// ── Cancelar pedido desde lista ──
document.querySelectorAll('.btn-cancelar').forEach(btn => {
  btn.addEventListener('click', async function () {
    const id    = this.dataset.id;
    const folio = this.dataset.folio;

    const { value: motivo, isConfirmed } = await SwalInst.fire({
      icon:              'warning',
      title:             `Cancelar ${folio}`,
      input:             'textarea',
      inputLabel:        'Motivo de cancelación',
      inputPlaceholder:  'Describe el motivo…',
      inputAttributes:   { minlength: 5 },
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
        await SwalInst.fire({ icon: 'success', title: json.message, timer: 1800, showConfirmButton: false });
        location.reload();
      } else {
        SwalInst.fire({ icon: 'error', title: json.message });
      }
    } catch {
      SwalInst.fire({ icon: 'error', title: 'Error de conexión.' });
    }
  });
});
})();
</script>
<?php $extraJs = ob_get_clean(); ?>
