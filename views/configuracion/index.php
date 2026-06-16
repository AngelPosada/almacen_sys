<?php
if (!isset($config)) { $config = $GLOBALS['app_config'] ?? require ROOT_PATH . '/config/config.php'; }
/**
 * views/configuracion/index.php
 * Variables: $configuracion (array clave => [valor, descripcion, tipo])
 */

$baseUrl = e($config['app']['url']);

// Helper para obtener valor actual
$val = fn(string $clave, string $default = '') =>
    e($configuracion[$clave]['valor'] ?? $default);
?>

<div class="page-header">
  <div>
    <h1 class="page-title">Configuración institucional</h1>
    <p class="page-subtitle">
      Datos del sistema que aparecen en documentos, reportes y correos
    </p>
  </div>
</div>

<form id="formConfig" novalidate>
<?= Security::csrfField() ?>

<div style="display:grid;grid-template-columns:2fr 1fr;gap:1.25rem;align-items:start">

  <!-- Panel principal -->
  <div style="display:flex;flex-direction:column;gap:1.25rem">

    <!-- Datos institucionales -->
    <div class="card">
      <div class="card-header">
        <div>
          <div class="card-title">
            <i class="ti ti-building-bank"
               style="color:var(--color-primary);margin-right:.375rem"></i>
            Datos institucionales
          </div>
          <div class="card-subtitle">
            Aparecen en el encabezado de documentos PDF y correos
          </div>
        </div>
      </div>
      <div class="card-body">

        <div class="form-group">
          <label class="form-label required" for="inst_nombre">
            Nombre de la institución
          </label>
          <input type="text" id="inst_nombre" name="inst_nombre"
                 class="form-control" maxlength="200"
                 value="<?= $val('inst_nombre') ?>">
        </div>

        <div class="form-group">
          <label class="form-label" for="inst_area">
            Área / Departamento
          </label>
          <input type="text" id="inst_area" name="inst_area"
                 class="form-control" maxlength="200"
                 value="<?= $val('inst_area') ?>">
          <div class="form-hint">
            Ej: Dirección Administrativa - Dpto. Recursos Materiales
          </div>
        </div>

        <div class="form-group">
          <label class="form-label" for="inst_director">
            Director del plantel
          </label>
          <input type="text" id="inst_director" name="inst_director"
                 class="form-control" maxlength="150"
                 value="<?= $val('inst_director') ?>"
                 placeholder="Nombre completo con cargo">
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
          <div class="form-group">
            <label class="form-label" for="inst_director_admin">
              Director Administrativo
            </label>
            <input type="text" id="inst_director_admin" name="inst_director_admin"
                   class="form-control" maxlength="150"
                   value="<?= $val('inst_director_admin') ?>">
            <div class="form-hint">Firma en requisiciones y vales</div>
          </div>

          <div class="form-group">
            <label class="form-label" for="inst_jefe_recursos">
              Jefe de Recursos Materiales
            </label>
            <input type="text" id="inst_jefe_recursos" name="inst_jefe_recursos"
                   class="form-control" maxlength="150"
                   value="<?= $val('inst_jefe_recursos') ?>">
            <div class="form-hint">Valida las requisiciones</div>
          </div>
        </div>

        <div class="form-group" style="margin-bottom:0">
          <label class="form-label" for="inst_whatsapp">
            WhatsApp institucional
          </label>
          <div class="input-group">
            <div class="input-group-prepend">
              <i class="ti ti-brand-whatsapp"
                 style="color:#25D366"></i>
            </div>
            <input type="text" id="inst_whatsapp" name="inst_whatsapp"
                   class="form-control" maxlength="20"
                   value="<?= $val('inst_whatsapp') ?>"
                   placeholder="+52 618 000 0000">
          </div>
        </div>

      </div>
    </div>

    <!-- Configuración del sistema -->
    <div class="card">
      <div class="card-header">
        <div>
          <div class="card-title">
            <i class="ti ti-settings"
               style="color:var(--color-primary);margin-right:.375rem"></i>
            Parámetros del sistema
          </div>
        </div>
      </div>
      <div class="card-body">

        <div class="form-group">
          <label class="form-label" for="sistema_nombre">
            Nombre del sistema
          </label>
          <input type="text" id="sistema_nombre" name="sistema_nombre"
                 class="form-control" maxlength="100"
                 value="<?= $val('sistema_nombre', 'Sistema de Almacén Escolar') ?>">
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
          <div class="form-group">
            <label class="form-label" for="cotizacion_umbral">
              Umbral de cotizaciones ($)
            </label>
            <div class="input-group">
              <div class="input-group-prepend">$</div>
              <input type="number" id="cotizacion_umbral" name="cotizacion_umbral"
                     class="form-control" min="1" step="1"
                     value="<?= $val('cotizacion_umbral', '25000') ?>">
            </div>
            <div class="form-hint">
              Requisiciones que superen este monto requieren 3 cotizaciones
            </div>
          </div>

          <div class="form-group">
            <label class="form-label" for="items_por_pagina">
              Registros por página
            </label>
            <select id="items_por_pagina" name="items_por_pagina"
                    class="form-control">
              <?php foreach ([10, 15, 25, 50, 100] as $n): ?>
              <option value="<?= $n ?>"
                <?= $val('items_por_pagina', '25') == $n ? 'selected' : '' ?>>
                <?= $n ?> registros
              </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="form-group" style="margin-bottom:0">
          <label style="display:flex;align-items:center;gap:.75rem;cursor:pointer">
            <input type="checkbox" name="stock_alerta_email" value="1"
                   <?= $val('stock_alerta_email', '1') === '1' ? 'checked' : '' ?>>
            <div>
              <span class="form-label" style="margin:0;cursor:pointer">
                Enviar alertas de stock por correo
              </span>
              <div class="form-hint" style="margin-top:.125rem">
                Notifica al almacenista cuando un producto baje del mínimo
              </div>
            </div>
          </label>
        </div>

      </div>
    </div>

  </div>

  <!-- Panel lateral -->
  <div style="display:flex;flex-direction:column;gap:1.25rem">

    <!-- Acciones -->
    <div class="card">
      <div class="card-body">
        <button type="button" class="btn btn-primary w-100" id="btnGuardarConfig">
          <span id="btnConfigTexto">
            <i class="ti ti-device-floppy"></i>
            Guardar configuración
          </span>
          <span id="btnConfigSpinner" style="display:none">
            <i class="ti ti-loader-2"
               style="animation:spin .75s linear infinite"></i>
            Guardando…
          </span>
        </button>
      </div>
    </div>

    <!-- Aviso importante -->
    <div class="card" style="border-color:var(--color-accent-soft)">
      <div class="card-body">
        <div style="display:flex;gap:.75rem;align-items:flex-start">
          <i class="ti ti-info-circle"
             style="color:var(--color-accent);font-size:1.25rem;flex-shrink:0"></i>
          <div style="font-size:var(--font-size-sm)">
            <strong>Nota:</strong>
            <p style="color:var(--text-secondary);margin-top:.25rem;line-height:1.6">
              Los cambios aquí afectan inmediatamente los documentos generados
              (vales, requisiciones, correos). Los nombres de firmantes aparecen
              impresos en todos los documentos.
            </p>
          </div>
        </div>
      </div>
    </div>

    <!-- Vista previa de datos actuales -->
    <div class="card">
      <div class="card-header">
        <div class="card-title">Vista previa del documento</div>
      </div>
      <div class="card-body"
           style="font-size:var(--font-size-xs);line-height:1.8;
                  color:var(--text-secondary)">
        <div style="text-align:center;margin-bottom:.75rem;
                    padding-bottom:.75rem;border-bottom:1px solid var(--border-color)">
          <strong id="prev_inst_nombre">
            <?= $val('inst_nombre') ?>
          </strong><br>
          <span id="prev_inst_area">
            <?= $val('inst_area') ?>
          </span>
        </div>
        <div style="display:flex;flex-direction:column;gap:.375rem">
          <div>
            <span style="color:var(--text-muted)">Director Adm.:</span>
            <span id="prev_director_admin">
              <?= $val('inst_director_admin') ?>
            </span>
          </div>
          <div>
            <span style="color:var(--text-muted)">Jefe Rec. Mat.:</span>
            <span id="prev_jefe_recursos">
              <?= $val('inst_jefe_recursos') ?>
            </span>
          </div>
          <div>
            <span style="color:var(--text-muted)">Umbral cotiz.:</span>
            $<span id="prev_umbral">
              <?= number_format((float)$val('cotizacion_umbral', '25000'), 0) ?>
            </span>
          </div>
        </div>
      </div>
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
const BASE = '<?= $baseUrlJs ?>';

// ── Preview en tiempo real ──
const previewMap = {
  'inst_nombre':       'prev_inst_nombre',
  'inst_area':         'prev_inst_area',
  'inst_director_admin':'prev_director_admin',
  'inst_jefe_recursos':'prev_jefe_recursos',
  'cotizacion_umbral': 'prev_umbral',
};

Object.entries(previewMap).forEach(([inputId, previewId]) => {
  const input   = document.getElementById(inputId);
  const preview = document.getElementById(previewId);
  if (!input || !preview) return;

  input.addEventListener('input', function () {
    let val = this.value;
    if (inputId === 'cotizacion_umbral') {
      val = parseFloat(val) > 0
        ? parseFloat(val).toLocaleString('es-MX', { maximumFractionDigits: 0 })
        : '0';
    }
    preview.textContent = val;
  });
});

// ── Guardar ──
document.getElementById('btnGuardarConfig').addEventListener('click', async function () {
  setLoading(true);

  const fd = new FormData(document.getElementById('formConfig'));

  // Asegurar que el checkbox envíe 0 si no está marcado
  if (!document.querySelector('[name="stock_alerta_email"]').checked) {
    fd.set('stock_alerta_email', '0');
  }

  try {
    const res  = await fetch(`${BASE}/configuracion`, { method: 'POST', body: fd });
    const json = await res.json();

    if (json.success) {
      await SwalInst.fire({
        icon:              'success',
        title:             json.message,
        timer:             2000,
        showConfirmButton: false,
      });
    } else {
      SwalInst.fire({ icon: 'error', title: json.message || 'Error al guardar.' });
    }
  } catch {
    SwalInst.fire({ icon: 'error', title: 'Error de conexión.' });
  } finally {
    setLoading(false);
  }
});

function setLoading(on) {
  document.getElementById('btnGuardarConfig').disabled = on;
  document.getElementById('btnConfigTexto').style.display   = on ? 'none'   : 'inline';
  document.getElementById('btnConfigSpinner').style.display = on ? 'inline' : 'none';
}

})();
</script>
<?php $extraJs = ob_get_clean(); ?>
