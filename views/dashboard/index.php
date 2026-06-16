<?php
if (!isset($config)) {
    $config = $GLOBALS['app_config'] ?? require ROOT_PATH . '/config/config.php';
}
/**
 * views/dashboard/index.php
 *
 * Vista principal del Dashboard.
 *
 * Variables disponibles (inyectadas por DashboardController):
 *   $kpis        array  — 6 tarjetas de KPI formateadas
 *   $movimientos array  — últimos 8 movimientos
 *   $stock_bajo  array  — productos con stock bajo/crítico
 *   $pedidos     array  — últimos 5 pedidos
 *   $grafico     array  — datos para el gráfico (labels/entradas/salidas)
 */
?>

<!-- ============================================================
     ENCABEZADO DE PÁGINA
============================================================ -->
<div class="page-header">
  <div>
    <h1 class="page-title">
      Buenos días<?php
        $hora = (int) date('H');
        if ($hora >= 12 && $hora < 19) echo ', buenas tardes';
        elseif ($hora >= 19)           echo ', buenas noches';
      ?>,
      <?= e($_SESSION['usuario_nombre'] ?? 'Usuario') ?>
    </h1>
    <p class="page-subtitle">
      <?= date('l, j \d\e F \d\e Y', time()) ?> &mdash;
      Resumen del sistema de almacén
    </p>
  </div>
  <div style="display:flex;gap:.625rem;align-items:center">
    <button class="btn btn-ghost btn-sm" id="btnRefrescar" title="Refrescar KPIs">
      <i class="ti ti-refresh" aria-hidden="true"></i>
      <span>Refrescar</span>
    </button>
    <a href="<?= e($config['app']['url']) ?>/inventario/entradas"
       class="btn btn-primary btn-sm">
      <i class="ti ti-plus" aria-hidden="true"></i>
      Nueva entrada
    </a>
  </div>
</div>

<!-- ============================================================
     FILA DE KPIs
============================================================ -->
<div class="stats-grid" id="kpiGrid" style="margin-bottom:1.75rem">

  <?php foreach ($kpis as $key => $kpi): ?>
  <div class="stat-card<?= !empty($kpi['alerta']) ? ' stat-card--alerta' : '' ?>"
       data-kpi="<?= e($key) ?>">
    <div class="stat-card-icon <?= e($kpi['tipo']) ?>">
      <i class="ti <?= e($kpi['icono']) ?>" style="font-size:1.375rem" aria-hidden="true"></i>
    </div>
    <div class="stat-card-info">
      <div class="stat-card-value" data-valor><?= e($kpi['valor']) ?></div>
      <div class="stat-card-label"><?= e($kpi['etiqueta']) ?></div>
    </div>
    <?php if (!empty($kpi['alerta'])): ?>
      <div style="width:8px;height:8px;border-radius:50%;
                  background:var(--color-accent);
                  box-shadow:0 0 0 3px rgba(242,129,29,.25);
                  flex-shrink:0" aria-label="Requiere atención"></div>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>

</div>

<!-- ============================================================
     FILA PRINCIPAL: Gráfico + Stock bajo
============================================================ -->
<div class="dashboard-grid" style="margin-bottom:1.75rem">

  <!-- Gráfico de actividad (14 días) -->
  <div class="card">
    <div class="card-header">
      <div>
        <div class="card-title">Actividad del inventario</div>
        <div class="card-subtitle">Últimos 14 días — entradas y salidas</div>
      </div>
    </div>
    <div class="card-body" style="padding-bottom:1.25rem">
      <canvas id="graficoActividad" height="200" aria-label="Gráfico de actividad de inventario"></canvas>
    </div>
  </div>

  <!-- Alertas de stock bajo -->
  <div class="card">
    <div class="card-header">
      <div>
        <div class="card-title">
          <i class="ti ti-alert-triangle"
             style="color:var(--color-accent);font-size:1rem;margin-right:.375rem"
             aria-hidden="true"></i>
          Stock bajo
        </div>
        <div class="card-subtitle">Productos que requieren atención</div>
      </div>
      <a href="<?= e($config['app']['url']) ?>/productos"
         class="btn btn-ghost btn-sm">Ver todos</a>
    </div>
    <div class="card-body" style="padding:0">

      <?php if (empty($stock_bajo)): ?>
        <div style="padding:2rem;text-align:center;color:var(--text-muted)">
          <i class="ti ti-circle-check" style="font-size:2rem;color:var(--color-primary);display:block;margin-bottom:.5rem"></i>
          Todos los productos tienen stock suficiente
        </div>
      <?php else: ?>
        <ul style="list-style:none">
          <?php foreach ($stock_bajo as $prod): ?>
          <li style="padding:.875rem 1.25rem;border-bottom:1px solid var(--border-color);
                     display:flex;align-items:center;gap:.875rem">
            <!-- Ícono de estado -->
            <div style="width:36px;height:36px;border-radius:var(--border-radius-sm);
                        background:var(--status-<?= $prod['estado'] === 'sin_stock' ? 'danger' : 'warning' ?>-bg);
                        display:flex;align-items:center;justify-content:center;flex-shrink:0">
              <i class="ti ti-package"
                 style="color:var(--status-<?= $prod['estado'] === 'sin_stock' ? 'danger' : 'warning' ?>-text)"
                 aria-hidden="true"></i>
            </div>

            <!-- Info del producto -->
            <div style="flex:1;min-width:0">
              <div style="font-size:var(--font-size-sm);font-weight:500;
                          color:var(--text-primary);
                          white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
                <?= e($prod['nombre']) ?>
              </div>
              <div style="font-size:var(--font-size-xs);color:var(--text-muted)">
                <?= e($prod['codigo']) ?> &mdash; <?= e($prod['categoria']) ?>
              </div>
              <!-- Barra de stock -->
              <div class="stock-bar" style="margin-top:.375rem">
                <div class="stock-bar-fill <?= e($prod['barra_clase']) ?>"
                     style="width:<?= (int) $prod['porcentaje'] ?>%"
                     role="progressbar"
                     aria-valuenow="<?= (int) $prod['porcentaje'] ?>"
                     aria-valuemin="0" aria-valuemax="100"></div>
              </div>
            </div>

            <!-- Badge + cantidad -->
            <div style="text-align:right;flex-shrink:0">
              <span class="badge <?= e($prod['badge_clase']) ?>" style="display:block;margin-bottom:.25rem">
                <?= e($prod['badge_label']) ?>
              </span>
              <div style="font-size:var(--font-size-xs);color:var(--text-muted)">
                <?= e($prod['presentacion']) ?>
              </div>
            </div>
          </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>

    </div>
  </div>

</div>

<!-- ============================================================
     FILA INFERIOR: Movimientos + Pedidos recientes
============================================================ -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.25rem">

  <!-- Últimos movimientos -->
  <div class="card">
    <div class="card-header">
      <div>
        <div class="card-title">Últimos movimientos</div>
        <div class="card-subtitle">Entradas y salidas recientes</div>
      </div>
      <a href="<?= e($config['app']['url']) ?>/inventario"
         class="btn btn-ghost btn-sm">Ver todos</a>
    </div>
    <div class="card-body" style="padding:0">

      <?php if (empty($movimientos)): ?>
        <div style="padding:2rem;text-align:center;color:var(--text-muted)">
          Sin movimientos registrados aún.
        </div>
      <?php else: ?>
        <ul style="list-style:none">
          <?php foreach ($movimientos as $mov): ?>
          <li style="padding:.75rem 1.25rem;border-bottom:1px solid var(--border-color);
                     display:flex;align-items:center;gap:.75rem">

            <!-- Ícono tipo -->
            <div style="width:32px;height:32px;border-radius:50%;
                        background:var(--status-<?= e($mov['tipo_clase']) ?>-bg);
                        display:flex;align-items:center;justify-content:center;flex-shrink:0">
              <i class="ti <?= e($mov['tipo_icono']) ?>"
                 style="font-size:.875rem;color:var(--status-<?= e($mov['tipo_clase']) ?>-text)"
                 aria-hidden="true"></i>
            </div>

            <!-- Detalle -->
            <div style="flex:1;min-width:0">
              <div style="font-size:var(--font-size-sm);font-weight:500;
                          white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
                <?= e($mov['producto_nombre']) ?>
              </div>
              <div style="font-size:var(--font-size-xs);color:var(--text-muted)">
                <?= e($mov['origen']) ?> &mdash; <?= e($mov['usuario_nombre']) ?>
              </div>
            </div>

            <!-- Cantidad + fecha -->
            <div style="text-align:right;flex-shrink:0">
              <div style="font-size:var(--font-size-sm);font-weight:600;
                          color:var(--status-<?= e($mov['tipo_clase']) ?>-text)">
                <?= e($mov['signo']) ?><?= e($mov['cantidad']) ?>
              </div>
              <div style="font-size:var(--font-size-xs);color:var(--text-muted)">
                <?= e($mov['fecha']) ?>
              </div>
            </div>

          </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>

    </div>
  </div>

  <!-- Pedidos recientes -->
  <div class="card">
    <div class="card-header">
      <div>
        <div class="card-title">Pedidos recientes</div>
        <div class="card-subtitle">Últimas solicitudes al almacén</div>
      </div>
      <a href="<?= e($config['app']['url']) ?>/pedidos"
         class="btn btn-ghost btn-sm">Ver todos</a>
    </div>
    <div class="card-body" style="padding:0">

      <?php if (empty($pedidos)): ?>
        <div style="padding:2rem;text-align:center;color:var(--text-muted)">
          Sin pedidos registrados aún.
        </div>
      <?php else: ?>
        <ul style="list-style:none">
          <?php foreach ($pedidos as $ped): ?>
          <li style="padding:.75rem 1.25rem;border-bottom:1px solid var(--border-color)">

            <div style="display:flex;align-items:center;justify-content:space-between;
                        margin-bottom:.25rem">
              <!-- Folio + urgente -->
              <div style="display:flex;align-items:center;gap:.5rem">
                <span style="font-size:var(--font-size-sm);font-weight:600;
                             color:var(--color-primary)">
                  <?= e($ped['folio']) ?>
                </span>
                <?php if ($ped['es_urgente']): ?>
                  <span class="badge badge-danger" style="font-size:.6rem">URGENTE</span>
                <?php endif; ?>
              </div>
              <!-- Estado -->
              <span class="badge <?= e($ped['estado_clase']) ?>">
                <?= e($ped['estado_label']) ?>
              </span>
            </div>

            <div style="display:flex;align-items:center;justify-content:space-between">
              <div style="font-size:var(--font-size-xs);color:var(--text-muted)">
                <?= e($ped['solicitante']) ?> &mdash;
                <?= e($ped['total_items']) ?> artículo<?= $ped['total_items'] !== 1 ? 's' : '' ?>
              </div>
              <div style="font-size:var(--font-size-xs);color:var(--text-muted)">
                <?= e($ped['fecha']) ?>
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
// ── Pasar datos del gráfico a JS de forma segura ──
$graficoJson = json_encode($grafico, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
$baseUrl     = e($config['app']['url']);

ob_start();
?>
<!-- Chart.js para el gráfico de actividad -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
(function () {
  'use strict';

  // ── Datos del servidor ──
  const grafico = <?= $graficoJson ?>;

  // ── Detectar tema para los colores del gráfico ──
  const isDark = document.documentElement.dataset.theme === 'dark';
  const gridColor   = isDark ? 'rgba(255,255,255,.07)' : 'rgba(0,0,0,.06)';
  const labelColor  = isDark ? '#a0c4b4' : '#4a6358';

  // ── Inicializar Chart.js ──
  const ctx = document.getElementById('graficoActividad').getContext('2d');
  new Chart(ctx, {
    type: 'bar',
    data: {
      labels: grafico.labels,
      datasets: [
        {
          label:           'Entradas',
          data:            grafico.entradas,
          backgroundColor: 'rgba(14,115,78,.65)',
          borderColor:     'rgba(14,115,78,1)',
          borderWidth:     0,
          borderRadius:    4,
        },
        {
          label:           'Salidas',
          data:            grafico.salidas,
          backgroundColor: 'rgba(242,129,29,.65)',
          borderColor:     'rgba(242,129,29,1)',
          borderWidth:     0,
          borderRadius:    4,
        },
      ],
    },
    options: {
      responsive:         true,
      maintainAspectRatio:false,
      interaction: { mode: 'index', intersect: false },
      plugins: {
        legend: {
          position: 'bottom',
          labels: {
            color:     labelColor,
            boxWidth:  12,
            boxHeight: 12,
            padding:   16,
            font: { size: 12 },
          },
        },
        tooltip: {
          callbacks: {
            label: ctx => ` ${ctx.dataset.label}: ${ctx.parsed.y} movimientos`,
          },
        },
      },
      scales: {
        x: {
          grid:  { color: gridColor },
          ticks: { color: labelColor, font: { size: 11 } },
        },
        y: {
          beginAtZero: true,
          grid:  { color: gridColor },
          ticks: {
            color:     labelColor,
            font:      { size: 11 },
            precision: 0,
          },
        },
      },
    },
  });

  // ── Botón de refrescar KPIs (AJAX) ──
  const btnRefrescar = document.getElementById('btnRefrescar');
  if (btnRefrescar) {
    btnRefrescar.addEventListener('click', async function () {
      this.disabled = true;
      this.innerHTML = '<i class="ti ti-loader-2" style="animation:spin .75s linear infinite"></i> Actualizando…';

      try {
        const res  = await fetch('<?= $baseUrl ?>/dashboard/stats', {
          headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN':     window.CSRF_TOKEN,
          },
        });
        const json = await res.json();

        if (json.success) {
          // Actualizar solo los valores visibles sin recargar
          document.querySelectorAll('[data-kpi]').forEach(card => {
            const key = card.dataset.kpi;
            if (json.data[key]) {
              const el = card.querySelector('[data-valor]');
              if (el) el.textContent = json.data[key].valor;
            }
          });
        }
      } catch (err) {
        console.error('Error refrescando stats:', err);
      } finally {
        this.disabled = false;
        this.innerHTML = '<i class="ti ti-refresh"></i> Refrescar';
      }
    });
  }

})();
</script>
<?php
$extraJs = ob_get_clean();
?>
