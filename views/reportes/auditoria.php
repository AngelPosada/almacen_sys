<?php
if (!isset($config)) { $config = $GLOBALS['app_config'] ?? require ROOT_PATH . '/config/config.php'; }
/**
 * views/reportes/auditoria.php
 * Solo accesible para Admin (rol 1) y Auditor (rol 4).
 * Variables: $registros, $modulos, $usuarios, $top_usuarios, $filtros
 */

$baseUrl = e($config['app']['url']);
?>

<div class="page-header">
  <div>
    <h1 class="page-title">Bitácora de auditoría</h1>
    <p class="page-subtitle">
      Registro completo de acciones críticas del sistema ·
      Del <?= e($filtros['fecha_desde']) ?> al <?= e($filtros['fecha_hasta']) ?>
    </p>
  </div>
  <div style="display:flex;gap:.5rem;align-items:center">
    <span class="badge badge-danger" style="font-size:.7rem">
      <i class="ti ti-lock" style="font-size:.75rem"></i>
      Acceso restringido
    </span>
  </div>
</div>

<!-- Filtros -->
<div class="card" style="margin-bottom:1.25rem">
  <div class="card-body" style="padding:1rem 1.25rem">
    <form method="GET" style="display:flex;gap:.75rem;flex-wrap:wrap;align-items:flex-end">
      <div style="min-width:180px">
        <label class="form-label" for="fUsuario">Usuario</label>
        <select id="fUsuario" name="usuario_id" class="form-control">
          <option value="">Todos los usuarios</option>
          <?php foreach ($usuarios as $u): ?>
          <option value="<?= (int)$u['id'] ?>"
            <?= ($filtros['usuario_id']??'') == $u['id'] ? 'selected':'' ?>>
            <?= e($u['nombre_completo']) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div style="min-width:140px">
        <label class="form-label" for="fModulo">Módulo</label>
        <select id="fModulo" name="modulo" class="form-control">
          <option value="">Todos</option>
          <?php foreach ($modulos as $m): ?>
          <option value="<?= e($m['modulo']) ?>"
            <?= ($filtros['modulo']??'') === $m['modulo'] ? 'selected':'' ?>>
            <?= e(ucfirst($m['modulo'])) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div style="min-width:130px">
        <label class="form-label" for="fAcc">Acción</label>
        <input type="text" id="fAcc" name="accion" class="form-control"
               placeholder="Ej: login, crear"
               value="<?= e($filtros['accion'] ?? '') ?>">
      </div>
      <div style="min-width:120px">
        <label class="form-label" for="fIP">IP</label>
        <input type="text" id="fIP" name="ip" class="form-control"
               placeholder="192.168…"
               value="<?= e($filtros['ip'] ?? '') ?>">
      </div>
      <div style="min-width:130px">
        <label class="form-label" for="fADesde">Desde</label>
        <input type="date" id="fADesde" name="fecha_desde" class="form-control"
               value="<?= e($filtros['fecha_desde'] ?? '') ?>">
      </div>
      <div style="min-width:130px">
        <label class="form-label" for="fAHasta">Hasta</label>
        <input type="date" id="fAHasta" name="fecha_hasta" class="form-control"
               value="<?= e($filtros['fecha_hasta'] ?? '') ?>">
      </div>
      <div style="display:flex;gap:.5rem">
        <button type="submit" class="btn btn-primary">
          <i class="ti ti-filter"></i> Filtrar
        </button>
        <a href="<?= $baseUrl ?>/reportes/auditoria" class="btn btn-ghost">
          <i class="ti ti-x"></i>
        </a>
      </div>
    </form>
  </div>
</div>

<div style="display:grid;grid-template-columns:2fr 1fr;gap:1.25rem">

  <!-- Tabla de registros -->
  <div class="card">
    <div class="card-header">
      <div class="card-title">Registros de auditoría</div>
      <span style="font-size:var(--font-size-xs);color:var(--text-muted)">
        <?= number_format($registros['total'] ?? 0) ?> registros
      </span>
    </div>
    <div class="card-body" style="padding:0">
      <?php if (empty($registros['items'])): ?>
      <div style="padding:3rem;text-align:center;color:var(--text-muted)">
        <i class="ti ti-file-off" style="font-size:2rem;display:block;margin-bottom:.5rem"></i>
        Sin registros con los filtros actuales
      </div>
      <?php else: ?>
      <div class="table-container" style="border:none;border-radius:0">
        <table id="tablaAuditoria" class="table" style="width:100%">
          <thead>
            <tr>
              <th>Fecha y hora</th>
              <th>Usuario</th>
              <th>Módulo</th>
              <th>Acción</th>
              <th>Descripción</th>
              <th>IP</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($registros['items'] as $a): ?>
            <tr>
              <td style="font-size:var(--font-size-xs);white-space:nowrap;
                          color:var(--text-muted)">
                <?= e($a['fecha_fmt']) ?>
              </td>
              <td>
                <div style="font-size:var(--font-size-sm);font-weight:500">
                  <?= e($a['usuario_nombre'] ?? 'Sistema') ?>
                </div>
                <div style="font-size:var(--font-size-xs);color:var(--text-muted)">
                  <?= e($a['usuario_email'] ?? '') ?>
                </div>
              </td>
              <td>
                <span style="font-size:var(--font-size-xs);font-family:monospace;
                             background:var(--bg-surface-2);padding:2px 6px;
                             border-radius:3px;color:var(--text-secondary)">
                  <?= e($a['modulo_label']) ?>
                </span>
              </td>
              <td>
                <span class="badge <?= e($a['accion_clase']) ?>" style="font-size:.65rem">
                  <?= e($a['accion']) ?>
                </span>
              </td>
              <td style="font-size:var(--font-size-xs);color:var(--text-secondary);
                          max-width:240px;white-space:normal">
                <?= e($a['descripcion'] ?? '—') ?>
              </td>
              <td style="font-family:monospace;font-size:var(--font-size-xs);
                          color:var(--text-muted)">
                <?= e($a['ip']) ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <!-- Paginación -->
      <?php if (($registros['paginas'] ?? 0) > 1): ?>
      <div style="padding:1rem 1.25rem;display:flex;align-items:center;
                  justify-content:space-between;border-top:1px solid var(--border-color)">
        <div style="font-size:var(--font-size-sm);color:var(--text-muted)">
          <?= number_format($registros['total']) ?> registros
        </div>
        <div style="display:flex;gap:.375rem">
          <?php
            $maxPags = min($registros['paginas'], 8);
            for ($i = 1; $i <= $maxPags; $i++):
              $qs = http_build_query(array_merge($filtros, ['pagina' => $i]));
          ?>
          <a href="?<?= $qs ?>"
             class="btn btn-sm <?= $i === $registros['pagina_actual'] ? 'btn-primary':'btn-ghost' ?>">
            <?= $i ?>
          </a>
          <?php endfor;
            if ($registros['paginas'] > 8): ?>
          <span style="padding:0 .375rem;color:var(--text-muted)">…</span>
          <?php endif; ?>
        </div>
      </div>
      <?php endif; ?>

      <?php endif; ?>
    </div>
  </div>

  <!-- Panel de usuarios más activos -->
  <div class="card">
    <div class="card-header">
      <div class="card-title">Usuarios más activos</div>
      <span style="font-size:var(--font-size-xs);color:var(--text-muted)">
        Top 10 del período
      </span>
    </div>
    <div class="card-body" style="padding:0">
      <?php if (empty($top_usuarios)): ?>
      <div style="padding:2rem;text-align:center;color:var(--text-muted)">
        Sin actividad en el período
      </div>
      <?php else: ?>
      <ul style="list-style:none">
        <?php foreach ($top_usuarios as $i => $usr): ?>
        <li style="padding:.75rem 1.25rem;border-bottom:1px solid var(--border-color);
                   display:flex;align-items:center;gap:.75rem">
          <div style="width:26px;height:26px;border-radius:50%;
                      background:var(--color-primary-light);
                      display:flex;align-items:center;justify-content:center;
                      flex-shrink:0;font-size:var(--font-size-xs);
                      font-weight:700;color:var(--color-primary)">
            <?= $i + 1 ?>
          </div>
          <div style="flex:1;min-width:0">
            <div style="font-size:var(--font-size-sm);font-weight:500;
                        white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
              <?= e($usr['usuario_nombre'] ?? 'Sistema') ?>
            </div>
            <div style="font-size:var(--font-size-xs);color:var(--text-muted)">
              Última: <?= $usr['ultima_accion']
                ? date('d/m/Y H:i', strtotime($usr['ultima_accion'])) : '—' ?>
            </div>
          </div>
          <div style="font-weight:700;color:var(--color-primary);
                      font-size:var(--font-size-sm);flex-shrink:0">
            <?= number_format((int)$usr['total_acciones']) ?>
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
?>
<script>
$('#tablaAuditoria').DataTable({
  language: { url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json' },
  pageLength:50, order:[[0,'desc']],
  columnDefs:[{ orderable:false, targets:[4] }],
  dom:'tp',
});
</script>
<?php $extraJs = ob_get_clean(); ?>
