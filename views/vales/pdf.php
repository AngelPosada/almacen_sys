<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title><?= e($vale['folio']) ?> — Vale de <?= $vale['tipo'] === 'salida' ? 'Salida' : 'Resguardo' ?></title>
  <style>
    /*
     * Estilos del impreso institucional
     * Fiel al formato FOR 8.4 DeRM v4 — Colegio de Bachilleres del Estado de Durango
     * Fecha de revisión: 13/07/2017 | Versión: 4
     */
    @page { size: letter; margin: 1.5cm 2cm; }
    @media print {
      .no-print { display: none !important; }
      body       { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    }

    * { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      font-family: Arial, Helvetica, sans-serif;
      font-size: 10pt;
      color: #000;
      background: #fff;
    }

    /* ── Botón imprimir ── */
    .no-print {
      position: fixed;
      top: 1cm;
      right: 1cm;
      display: flex;
      gap: .5rem;
      z-index: 999;
    }
    .btn-print {
      padding: .5rem 1rem;
      background: #0E734E;
      color: #fff;
      border: none;
      border-radius: 4px;
      cursor: pointer;
      font-size: 10pt;
      font-family: Arial, sans-serif;
    }
    .btn-print:hover { background: #0a5c3d; }
    .btn-close {
      padding: .5rem 1rem;
      background: #6c757d;
      color: #fff;
      border: none;
      border-radius: 4px;
      cursor: pointer;
      font-size: 10pt;
    }

    /* ── Contenedor del documento ── */
    .documento {
      max-width: 18cm;
      margin: 0 auto;
      padding: 0.5cm 0;
    }

    /* ── Encabezado institucional ── */
    .encabezado-inst {
      text-align: center;
      margin-bottom: 4pt;
    }
    .encabezado-inst .nombre-inst {
      font-size: 12pt;
      font-weight: bold;
      text-transform: uppercase;
      letter-spacing: .5pt;
    }
    .encabezado-inst .area {
      font-size: 10pt;
      font-weight: bold;
      margin-top: 3pt;
    }
    .encabezado-inst .dpto {
      font-size: 10pt;
    }

    /* ── Título del documento ── */
    .titulo-doc {
      text-align: center;
      font-size: 13pt;
      font-weight: bold;
      text-transform: uppercase;
      border-top: 2px solid #000;
      border-bottom: 2px solid #000;
      padding: 4pt 0;
      margin: 6pt 0;
      letter-spacing: 1pt;
    }

    /* ── Campos de cabecera ── */
    .campos-cabecera {
      display: flex;
      justify-content: space-between;
      gap: 8pt;
      margin-bottom: 6pt;
    }
    .campo-linea {
      display: flex;
      align-items: baseline;
      gap: 4pt;
      border-bottom: 1px solid #000;
      flex: 1;
      padding-bottom: 2pt;
      font-size: 9.5pt;
    }
    .campo-linea .etq {
      font-weight: bold;
      white-space: nowrap;
      flex-shrink: 0;
    }
    .campo-linea .val {
      flex: 1;
    }

    /* ── Tabla de artículos ── */
    table.articulos {
      width: 100%;
      border-collapse: collapse;
      margin: 8pt 0;
    }
    table.articulos th {
      background: #000;
      color: #fff;
      font-weight: bold;
      text-align: center;
      padding: 4pt 6pt;
      font-size: 9.5pt;
      border: 1px solid #000;
    }
    table.articulos td {
      border: 1px solid #666;
      padding: 4pt 6pt;
      font-size: 9.5pt;
      vertical-align: top;
    }
    table.articulos td.num    { text-align: center; }
    table.articulos td.right  { text-align: right; }
    table.articulos .fila-total td {
      border-top: 2px solid #000;
      font-weight: bold;
    }
    table.articulos .fila-vacia td {
      border-color: #ccc;
      height: 16pt;
    }

    /* ── Sección de firmas ── */
    .firmas {
      margin-top: 24pt;
      display: flex;
      justify-content: space-between;
      gap: 12pt;
    }
    .firma-bloque {
      flex: 1;
      text-align: center;
    }
    .firma-linea {
      border-top: 1px solid #000;
      margin: 4pt 8pt 3pt;
    }
    .firma-cargo {
      font-weight: bold;
      font-size: 9pt;
    }
    .firma-nombre {
      font-size: 9pt;
      margin-top: 2pt;
    }

    /* ── Pie de documento ── */
    .pie-doc {
      margin-top: 12pt;
      display: flex;
      justify-content: space-between;
      font-size: 8pt;
      color: #555;
      border-top: 1px solid #ccc;
      padding-top: 4pt;
    }

    /* ── Badge de estado (solo en pantalla) ── */
    .badge-estado {
      display: inline-block;
      padding: 2pt 8pt;
      border-radius: 20pt;
      font-size: 8pt;
      font-weight: bold;
      margin-bottom: 6pt;
    }
    .badge-borrador  { background: #e9ecef; color: #495057; }
    .badge-emitido   { background: #d4edda; color: #155724; }
    .badge-cancelado { background: #f8d7da; color: #721c24; }
  </style>
</head>
<body>

<!-- Botones de pantalla -->
<div class="no-print">
  <button class="btn-print" onclick="window.print()">
    🖨️ Imprimir
  </button>
  <button class="btn-close" onclick="window.close()">
    ✕ Cerrar
  </button>
</div>

<div class="documento">

  <?php
    $config = require ROOT_PATH . '/config/config.php';
    $inst   = $config['institucion'];
  ?>

  <!-- Encabezado institucional con logo -->
  <div style="display:flex;align-items:center;margin-bottom:4pt">
    <div style="flex-shrink:0;margin-right:10pt">
      <?php
        $logoPath = ROOT_PATH . '/assets/img/logo_cobaed.png';
        if (file_exists($logoPath)):
          $logoData = base64_encode(file_get_contents($logoPath));
      ?>
      <img src="data:image/png;base64,<?= $logoData ?>"
           alt="COBAED"
           style="width:55pt;height:auto">
      <?php endif; ?>
    </div>
    <div class="encabezado-inst" style="flex:1">
      <div class="nombre-inst">
        <?= htmlspecialchars($inst['nombre'] ?? 'Colegio de Bachilleres del Estado de Durango', ENT_QUOTES) ?>
      </div>
      <div class="area">DIRECCIÓN ADMINISTRATIVA</div>
      <div class="dpto">Departamento de Recursos Materiales</div>
    </div>
    <div style="width:55pt;flex-shrink:0"></div>
  </div>

  <!-- Título del documento -->
  <div class="titulo-doc">
    <?= $vale['tipo'] === 'salida' ? 'VALE DE SALIDA DE ALMACÉN' : 'VALE DE RESGUARDO' ?>
  </div>

  <!-- Estado (solo en pantalla) -->
  <div class="no-print" style="text-align:center">
    <?php
      $badgeClase = match($vale['estado']) {
        'emitido'   => 'badge-emitido',
        'cancelado' => 'badge-cancelado',
        default     => 'badge-borrador',
      };
    ?>
    <span class="badge-estado <?= $badgeClase ?>">
      <?= match($vale['estado']) {
          'borrador'  => '⚠ BORRADOR — Aún no emitido',
          'emitido'   => '✓ EMITIDO',
          'cancelado' => '✗ CANCELADO',
          default     => strtoupper($vale['estado']),
      } ?>
    </span>
  </div>

  <!-- Campos: Fecha | Folio -->
  <div class="campos-cabecera">
    <div class="campo-linea" style="max-width:8cm">
      <span class="etq">Fecha:</span>
      <span class="val">
        <?= date('d \d\e F \d\e Y', strtotime($vale['fecha_emision'])) ?>
      </span>
    </div>
    <div class="campo-linea" style="max-width:6cm">
      <span class="etq">Folio:</span>
      <span class="val" style="font-weight:bold"><?= e($vale['folio']) ?></span>
    </div>
  </div>

  <!-- Referencia -->
  <div class="campo-linea" style="margin-bottom:4pt">
    <span class="etq">Referencia:</span>
    <span class="val"><?= e($vale['referencia'] ?? '') ?></span>
  </div>

  <!-- Plantel -->
  <div class="campo-linea" style="margin-bottom:8pt">
    <span class="etq">Plantel:</span>
    <span class="val"><?= e($vale['plantel'] ?? '') ?></span>
  </div>

  <?php if ($vale['empleado_nombre'] && trim($vale['empleado_nombre'])): ?>
  <!-- Empleado -->
  <div class="campo-linea" style="margin-bottom:8pt">
    <span class="etq">Para:</span>
    <span class="val">
      <?= e(trim($vale['empleado_nombre'])) ?>
      <?= $vale['empleado_puesto'] ? ' — ' . e($vale['empleado_puesto']) : '' ?>
    </span>
  </div>
  <?php endif; ?>

  <!-- Tabla de artículos -->
  <?php
    $numRenglones = max(10, count($vale['items']));
    $importeTotal = 0;
  ?>
  <table class="articulos">
    <thead>
      <tr>
        <th style="width:10%">CANTIDAD</th>
        <th style="width:10%">UNIDAD</th>
        <th>DESCRIPCIÓN</th>
        <th style="width:14%">COSTO UNIT.</th>
        <th style="width:14%">IMPORTE</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($vale['items'] as $item): ?>
      <?php $importeTotal += (float) $item['importe']; ?>
      <tr>
        <td class="num"><?= e($item['cant_texto']) ?></td>
        <td class="num"><?= e(strtoupper($item['unidad_medida'])) ?></td>
        <td><?= e($item['descripcion_item'] ?? $item['producto_nombre']) ?></td>
        <td class="right"><?= e($item['precio_fmt']) ?></td>
        <td class="right"><?= e($item['importe_fmt']) ?></td>
      </tr>
      <?php endforeach; ?>

      <!-- Renglones vacíos hasta completar 10 -->
      <?php for ($i = count($vale['items']); $i < 10; $i++): ?>
      <tr class="fila-vacia">
        <td></td><td></td><td></td><td></td><td></td>
      </tr>
      <?php endfor; ?>

      <!-- Total -->
      <tr class="fila-total">
        <td colspan="3" style="text-align:right;padding-right:8pt">TOTAL</td>
        <td></td>
        <td class="right">$<?= number_format($importeTotal, 2) ?></td>
      </tr>
    </tbody>
  </table>

  <?php if ($vale['observaciones']): ?>
  <div style="margin-bottom:12pt;font-size:9pt">
    <strong>Observaciones:</strong> <?= e($vale['observaciones']) ?>
  </div>
  <?php endif; ?>

  <!-- Firmas -->
  <div class="firmas">

    <!-- Almacenista -->
    <div class="firma-bloque">
      <div style="height:40pt"></div>
      <div class="firma-linea"></div>
      <div class="firma-cargo">Almacenista</div>
      <div class="firma-nombre">
        <?= e($vale['autorizo_nombre'] ?? '') ?>
      </div>
    </div>

    <!-- Autorizó -->
    <div class="firma-bloque">
      <div style="height:40pt"></div>
      <div class="firma-linea"></div>
      <div class="firma-cargo">Autorizó</div>
      <div class="firma-nombre">
        <?= e($inst['director_admin'] ?? '') ?>
      </div>
      <div class="firma-cargo" style="margin-top:2pt">Nombre y Firma</div>
    </div>

    <!-- Recibido -->
    <div class="firma-bloque">
      <?php if ($vale['recibio_nombre']): ?>
      <div style="height:40pt;display:flex;align-items:flex-end;
                  justify-content:center;padding-bottom:4pt;font-size:9pt">
        <?= e($vale['recibio_nombre']) ?>
      </div>
      <?php else: ?>
      <div style="height:40pt"></div>
      <?php endif; ?>
      <div class="firma-linea"></div>
      <div class="firma-cargo">Nombre y Firma de Recibido</div>
    </div>

  </div>

  <!-- Pie del documento — datos del formato oficial -->
  <div class="pie-doc">
    <span>Fecha de revisión: 13/07/2017</span>
    <span>Versión: 4</span>
    <span>FOR 8.4 DeRM 10*</span>
  </div>

</div><!-- /.documento -->

<script>
// Auto-abrir diálogo de impresión si el vale está emitido
<?php if ($vale['estado'] === 'emitido'): ?>
window.addEventListener('load', function () {
  setTimeout(() => window.print(), 500);
});
<?php endif; ?>
</script>

</body>
</html>
