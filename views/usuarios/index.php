<?php
if (!isset($config)) { $config = $GLOBALS['app_config'] ?? require ROOT_PATH . '/config/config.php'; }
/**
 * views/usuarios/index.php
 * Variables: $usuarios, $roles
 */

$baseUrl = e($config['app']['url']);
?>

<div class="page-header">
  <div>
    <h1 class="page-title">Usuarios</h1>
    <p class="page-subtitle">
      Gestión de acceso y roles del sistema
    </p>
  </div>
  <div style="display:flex;align-items:center;gap:.75rem">
    <span class="badge badge-info">
      <?= count($usuarios) ?> usuario<?= count($usuarios) !== 1 ? 's' : '' ?> registrado<?= count($usuarios) !== 1 ? 's' : '' ?>
    </span>
  </div>
</div>

<!-- Info de roles -->
<div class="card" style="margin-bottom:1.25rem">
  <div class="card-body" style="padding:1rem 1.25rem">
    <div style="display:flex;gap:1.5rem;flex-wrap:wrap;align-items:center">
      <span style="font-size:var(--font-size-sm);color:var(--text-muted);font-weight:500">
        Roles del sistema:
      </span>
      <?php
      $rolInfo = [
        1 => ['clase' => 'badge-success', 'desc' => 'Acceso total'],
        2 => ['clase' => 'badge-info',    'desc' => 'Gestión de almacén'],
        3 => ['clase' => 'badge-muted',   'desc' => 'Solo solicitudes'],
        4 => ['clase' => 'badge-warning', 'desc' => 'Solo auditoría'],
      ];
      foreach ($roles as $id => $nombre):
        $info = $rolInfo[$id] ?? ['clase' => 'badge-muted', 'desc' => ''];
      ?>
      <div style="display:flex;align-items:center;gap:.375rem">
        <span class="badge <?= e($info['clase']) ?>"><?= e($nombre) ?></span>
        <span style="font-size:var(--font-size-xs);color:var(--text-muted)"><?= e($info['desc']) ?></span>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- Tabla de usuarios -->
<div class="card">
  <div class="card-body" style="padding:0">
    <div class="table-container" style="border:none;border-radius:0">
      <table id="tablaUsuarios" class="table" style="width:100%">
        <thead>
          <tr>
            <th style="width:44px"></th>
            <th>Usuario</th>
            <th>Correo</th>
            <th style="text-align:center">Rol</th>
            <th>Último acceso</th>
            <th>Registrado</th>
            <th style="text-align:center">Estado</th>
            <th style="text-align:center;width:120px">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($usuarios as $u): ?>
          <tr id="fila-usuario-<?= (int)$u['id'] ?>">
            <!-- Avatar -->
            <td style="text-align:center;padding:.5rem">
              <?php if ($u['avatar_url']): ?>
              <img src="<?= e($u['avatar_url']) ?>"
                   alt="Avatar"
                   style="width:36px;height:36px;border-radius:50%;object-fit:cover;
                          border:2px solid var(--border-color)">
              <?php else: ?>
              <div style="width:36px;height:36px;border-radius:50%;
                          background:var(--color-primary-light);
                          display:flex;align-items:center;justify-content:center;
                          margin:0 auto;font-weight:700;
                          color:var(--color-primary);font-size:.875rem">
                <?= strtoupper(substr($u['nombre'], 0, 1)) ?>
              </div>
              <?php endif; ?>
            </td>

            <!-- Nombre -->
            <td>
              <div style="font-weight:500;font-size:var(--font-size-sm)">
                <?= e($u['nombre'] . ' ' . $u['apellidos']) ?>
                <?php if ($u['es_yo']): ?>
                <span class="badge badge-info" style="font-size:.6rem;margin-left:4px">Tú</span>
                <?php endif; ?>
              </div>
            </td>

            <!-- Email -->
            <td style="font-size:var(--font-size-sm);color:var(--text-muted)">
              <?= e($u['email']) ?>
            </td>

            <!-- Rol -->
            <td style="text-align:center">
              <?php if (!$u['es_yo']): ?>
              <select class="form-control sel-rol"
                      data-id="<?= (int)$u['id'] ?>"
                      style="font-size:var(--font-size-xs);padding:.375rem .625rem;
                             width:auto;min-width:130px">
                <?php foreach ($roles as $rolId => $rolNombre): ?>
                <option value="<?= (int)$rolId ?>"
                  <?= (int)$u['rol_id'] === (int)$rolId ? 'selected' : '' ?>>
                  <?= e($rolNombre) ?>
                </option>
                <?php endforeach; ?>
              </select>
              <?php else: ?>
              <span class="badge badge-success">
                <?= e($u['rol_nombre']) ?>
              </span>
              <?php endif; ?>
            </td>

            <!-- Último acceso -->
            <td style="font-size:var(--font-size-sm);color:var(--text-muted)">
              <?= e($u['ultimo_acceso_fmt']) ?>
            </td>

            <!-- Registrado -->
            <td style="font-size:var(--font-size-sm);color:var(--text-muted)">
              <?= e($u['creado_fmt']) ?>
            </td>

            <!-- Estado -->
            <td style="text-align:center" id="estado-<?= (int)$u['id'] ?>">
              <span class="badge <?= $u['activo'] ? 'badge-success' : 'badge-danger' ?>">
                <?= $u['activo'] ? 'Activo' : 'Inactivo' ?>
              </span>
            </td>

            <!-- Acciones -->
            <td style="text-align:center">
              <?php if (!$u['es_yo']): ?>
              <button class="btn btn-sm <?= $u['activo'] ? 'btn-outline-danger' : 'btn-outline-primary' ?> btn-toggle-estado"
                      data-id="<?= (int)$u['id'] ?>"
                      data-activo="<?= (int)$u['activo'] ?>"
                      title="<?= $u['activo'] ? 'Desactivar usuario' : 'Activar usuario' ?>">
                <i class="ti ti-<?= $u['activo'] ? 'user-off' : 'user-check' ?>"></i>
                <?= $u['activo'] ? 'Desactivar' : 'Activar' ?>
              </button>
              <?php else: ?>
              <span style="font-size:var(--font-size-xs);color:var(--text-muted)">—</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php
ob_start();
$baseUrlJs = e($config['app']['url']);
?>
<script>
(function () {
'use strict';
const BASE = '<?= $baseUrlJs ?>';

// ── DataTable ──
$('#tablaUsuarios').DataTable({
  language: { url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json' },
  pageLength: 25,
  order: [[4, 'desc']],
  columnDefs: [{ orderable: false, targets: [0, 3, 6, 7] }],
});

// ── Cambiar rol ──
document.querySelectorAll('.sel-rol').forEach(sel => {
  sel.addEventListener('change', async function () {
    const id    = this.dataset.id;
    const rolId = this.value;
    const fd    = new FormData();
    fd.append('_csrf_token', window.CSRF_TOKEN);
    fd.append('rol_id', rolId);

    try {
      const res  = await fetch(`${BASE}/usuarios/${id}/rol`, { method: 'POST', body: fd });
      const json = await res.json();
      if (json.success) {
        await SwalInst.fire({
          icon: 'success', title: json.message,
          timer: 1500, showConfirmButton: false,
        });
      } else {
        SwalInst.fire({ icon: 'error', title: json.message });
        // Revertir el select
        location.reload();
      }
    } catch {
      SwalInst.fire({ icon: 'error', title: 'Error de conexión.' });
    }
  });
});

// ── Activar / Desactivar ──
document.querySelectorAll('.btn-toggle-estado').forEach(btn => {
  btn.addEventListener('click', async function () {
    const id     = this.dataset.id;
    const activo = this.dataset.activo === '1';
    const accion = activo ? 'desactivar' : 'activar';

    const { isConfirmed } = await SwalInst.fire({
      icon:              'warning',
      title:             `¿${accion.charAt(0).toUpperCase() + accion.slice(1)} usuario?`,
      text:              activo
                         ? 'El usuario no podrá iniciar sesión.'
                         : 'El usuario podrá volver a iniciar sesión.',
      showCancelButton:  true,
      confirmButtonText: `Sí, ${accion}`,
      cancelButtonText:  'Cancelar',
    });

    if (!isConfirmed) return;

    const fd = new FormData();
    fd.append('_csrf_token', window.CSRF_TOKEN);

    try {
      const res  = await fetch(`${BASE}/usuarios/${id}/estado`, { method: 'POST', body: fd });
      const json = await res.json();

      if (json.success) {
        // Actualizar badge de estado
        const badge = document.querySelector(`#estado-${id} .badge`);
        if (badge) {
          badge.className   = `badge ${json.data.activo ? 'badge-success' : 'badge-danger'}`;
          badge.textContent = json.data.activo ? 'Activo' : 'Inactivo';
        }
        // Actualizar botón
        this.dataset.activo = json.data.activo ? '1' : '0';
        this.className = `btn btn-sm ${json.data.activo ? 'btn-outline-danger' : 'btn-outline-primary'} btn-toggle-estado`;
        this.innerHTML = `<i class="ti ti-${json.data.activo ? 'user-off' : 'user-check'}"></i> ${json.data.activo ? 'Desactivar' : 'Activar'}`;

        await SwalInst.fire({
          icon: 'success', title: json.message,
          timer: 1500, showConfirmButton: false,
        });
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
