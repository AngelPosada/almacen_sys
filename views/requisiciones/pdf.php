<?php
if (!isset($config)) { $config = $GLOBALS['app_config'] ?? require ROOT_PATH . '/config/config.php'; }
/**
 * views/requisiciones/pdf.php
 * Réplica fiel del formato FOR 8.4 DeRM — Versión 14
 * Fecha de revisión: 05/05/2023
 */

$instConf = $config['institucion'] ?? [];
$logoPath = ROOT_PATH . '/assets/img/logo_cobaed.png';
$logoB64  = file_exists($logoPath)
            ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath))
            : '';

$subtotal = 0;
foreach ($req['items'] as $it) $subtotal += (float)$it['total'];
$totalIva = round($subtotal * 1.16, 2);
?><!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title><?= e($req['folio']) ?> — Requisición</title>
  <style>
    @page {
      size: letter portrait;
      margin: 1cm 1.2cm 1cm 1.2cm;
    }
    @media print {
      .no-print { display:none !important; }
      body { -webkit-print-color-adjust:exact; print-color-adjust:exact; }
    }
    * { box-sizing:border-box; margin:0; padding:0; }
    body {
      font-family: 'Century Gothic', 'Trebuchet MS', Arial, sans-serif;
      font-size: 8.5pt;
      color: #000;
      background: #fff;
      width: 17.5cm;
      margin: 0 auto;
    }

    /* ── Botones ── */
    .no-print {
      position:fixed; top:.8cm; right:.8cm;
      display:flex; gap:.4rem; z-index:999;
    }
    .btn-p { padding:.4rem .9rem; background:#0E734E; color:#fff; border:none; border-radius:3px; cursor:pointer; font-size:9pt; }
    .btn-c { padding:.4rem .9rem; background:#666; color:#fff; border:none; border-radius:3px; cursor:pointer; font-size:9pt; }

    /* ── Documento con borde exterior — ancho fijo carta ── */
    .doc {
      width: 17.5cm;
      max-width: 17.5cm;
      border: 1.2px solid #000;
    }

    /* ── Encabezado ── */
    .enc-wrap {
      display: flex;
      align-items: center;
      padding: 5pt 6pt 4pt 5pt;
      border-bottom: 1px solid #aaa;
    }
    .enc-logo { width:48pt; flex-shrink:0; }
    .enc-logo img { width:44pt; height:auto; }
    .enc-texto { flex:1; text-align:center; }
    .enc-inst  { font-size:11pt; font-weight:bold; color:#0E734E; }
    .enc-dpto  { font-size:9.5pt; font-weight:bold; margin-top:2pt; }
    .enc-mirror { width:48pt; flex-shrink:0; }

    /* ── Título REQUISICIÓN — sin líneas separadoras ── */
    .titulo-req {
      text-align: center;
      font-size: 12pt;
      font-weight: bold;
      letter-spacing: .5pt;
      padding: 5pt 0 3pt;
    }

    /* ── Fecha de Elaboración (centrada, encima de Clave Programática) ── */
    .fecha-wrap {
      display: flex;
      padding: 2pt 6pt;
    }
    .fecha-inner {
      margin-left: auto;
      width: 8.5cm;   /* mismo ancho que columnas COTIZACIÓN */
      display: flex;
      align-items: baseline;
      gap: 5pt;
    }
    .fecha-etq {
      font-weight: bold;
      font-size: 9pt;
      white-space: nowrap;
    }
    .fecha-val {
      border-bottom: 1px solid #000;
      flex: 1;
      padding-bottom: 1pt;
      font-size: 9pt;
      min-width: 3cm;
    }

    /* ── Plantel / Clave ── */
    .plantel-wrap {
      display: flex;
      align-items: baseline;
      padding: 2pt 6pt 3pt;
      gap: 0;
    }
    /* Plantel: desde el borde izquierdo, línea termina al final de CANTIDAD (~53% ancho tabla) */
    .plantel-bloque {
      display: flex;
      align-items: baseline;
      gap: 4pt;
      width: 9cm;
    }
    .clave-bloque {
      display: flex;
      align-items: baseline;
      gap: 4pt;
      width: 8.5cm;
    }
    .campo-etq { font-weight:bold; font-size:9pt; white-space:nowrap; }
    .campo-val {
      border-bottom: 1px solid #000;
      flex: 1;
      padding-bottom: 1pt;
      font-size: 9pt;
    }

    /* ── Tabla de artículos ── */
    table.arts {
      width: 100%;
      border-collapse: collapse;
    }
    table.arts th {
      background: #F2CCCC;
      font-weight: bold;
      font-size: 8pt;
      text-align: center;
      padding: 4pt 3pt;
      border: 1px solid #000;
    }
    table.arts td {
      border: 1px solid #999;
      padding: 2pt 3pt;
      font-size: 8pt;
      vertical-align: middle;
    }
    table.arts td.cn { text-align:center; }
    table.arts td.cr { text-align:right; }
    table.arts tr.vacia td { height:16pt; border-color:#ccc; }
    table.arts tr.par td { background:#fafafa; }
    table.arts tr.total-row td {
      border-top: 1.5px solid #000;
      font-weight: bold;
    }

    /* ── Justificación ── */
    .just-wrap { padding:3pt 6pt; }
    .just-lbl  { font-weight:bold; font-size:9pt; margin-bottom:2pt; }
    .just-box  {
      border: 1px solid #000;
      min-height:36pt; padding:4pt;
      font-size:8.5pt; line-height:1.5;
    }

    /* ── Firmas ── */
    .firmas-wrap { padding:5pt 6pt 3pt; }
    .firmas-row  { display:flex; justify-content:space-between; }
    .firma-b     { flex:1; text-align:center; }
    .firma-lbl   { font-weight:bold; font-size:9pt; margin-bottom:16pt; display:block; }
    .firma-linea { border-top:1px solid #000; margin:0 6pt 2pt; }
    .firma-nom   { font-size:8.5pt; min-height:11pt; }
    .firma-cargo { font-size:8pt; font-weight:bold; margin-top:1pt; }

    /* ── Sección interna — fondo BLANCO, sin relleno de color ── */
    .sep-interno {
      background: #f0f0f0;
      border-top: 1px solid #000;
      padding: 3pt 5pt;
      font-size: 7.5pt;
      font-weight: bold;
      font-style: italic;
      text-align: center;
    }

    /* Tabla interna — 4 columnas, 2 filas */
    table.tbl-int {
      width: 100%;
      border-collapse: collapse;
    }
    table.tbl-int td {
      border: 1px solid #000;
      padding: 2pt 3pt;
      font-size: 7pt;
      vertical-align: top;
    }
    table.tbl-int td.lbl-cell {
      font-weight: bold;
    }
    table.tbl-int td.val-cell {
      min-height: 18pt;
      height: 18pt;
    }

    /* ── Nota importante (fuera del borde exterior) ── */
    .nota-imp {
      margin-top: 3pt;
      font-size: 8pt;
      font-style: italic;
      padding: 0 2pt;
    }

    /* ── Pie del documento (fuera del borde) ── */
    .pie-doc {
      display: flex;
      justify-content: space-between;
      margin-top: 4pt;
      font-size: 8pt;
      color: #333;
      padding: 0 2pt;
    }
    .pie-center { text-align:center; }
    .pie-right  { text-align:right; }

    /* badge solo pantalla */
    .badge-est { display:inline-block; padding:2pt 8pt; border-radius:20pt; font-size:8pt; font-weight:bold; }
    .be-b { background:#e9ecef; color:#495057; }
    .be-a { background:#d4edda; color:#155724; }
    .be-x { background:#f8d7da; color:#721c24; }
  </style>
</head>
<body>

<div class="no-print">
  <button class="btn-p" onclick="window.print()">🖨️ Imprimir</button>
  <button class="btn-c" onclick="window.close()">✕ Cerrar</button>
</div>

<!-- ══ DOCUMENTO ══ -->
<div class="doc">

  <!-- ENCABEZADO -->
  <div class="enc-wrap">
    <div class="enc-logo">
      <?php if ($logoB64): ?>
      <img src="<?= $logoB64 ?>" alt="COBAED">
      <?php endif; ?>
    </div>
    <div class="enc-texto">
      <div class="enc-inst">
        <?= e($instConf['nombre'] ?? 'Colegio de Bachilleres del Estado de Durango') ?>
      </div>
      <div class="enc-dpto">Departamento de Recursos Materiales</div>
    </div>
    <div class="enc-mirror"></div>
  </div>

  <!-- TÍTULO — sin líneas separadoras -->
  <div class="titulo-req">REQUISICIÓN</div>

  <!-- badge solo pantalla -->
  <?php
    $beClase = match($req['estado']) {
        'autorizada','comprada' => 'be-a',
        'rechazada','cancelada' => 'be-x',
        default                 => 'be-b',
    };
  ?>
  <div class="no-print" style="text-align:center;padding:1pt 0">
    <span class="badge-est <?= $beClase ?>">
      <?= e(strtoupper($req['estado_label'])) ?> — <?= e($req['folio']) ?>
    </span>
  </div>

  <!-- FECHA DE ELABORACIÓN
       Centrada sobre la columna COTIZACIÓN (misma alineación que PRECIO UNIT + TOTAL) -->
  <div class="fecha-wrap">
    <div class="fecha-inner">
      <span class="fecha-etq">Fecha de<br>Elaboración:</span>
      <span class="fecha-val"><?= e($req['fecha_fmt']) ?></span>
    </div>
  </div>

  <!-- PLANTEL / CLAVE PROGRAMÁTICA
       Plantel: línea termina al final de CANTIDAD
       Clave: línea igual ancho que PRECIO UNITARIO + TOTAL -->
  <div class="plantel-wrap">
    <div class="plantel-bloque">
      <span class="campo-etq">Plantel/Area:</span>
      <span class="campo-val"><?= e($req['plantel']) ?></span>
    </div>
    <div class="clave-bloque">
      <span class="campo-etq">Clave Programática:</span>
      <span class="campo-val"><?= e($req['clave_programatica'] ?? '') ?></span>
    </div>
  </div>

  <!-- TABLA DE ARTÍCULOS -->
  <table class="arts">
    <thead>
      <tr>
        <th rowspan="2" style="width:5%">No.</th>
        <th rowspan="2" style="width:25%">CONCEPTO</th>
        <th rowspan="2" style="width:8%">CANTIDAD</th>
        <th rowspan="2" style="width:32%">ESPECIFICACIONES</th>
        <th colspan="2" style="width:30%">COTIZACIÓN</th>
      </tr>
      <tr>
        <th style="width:15%">PRECIO UNITARIO</th>
        <th style="width:15%">TOTAL</th>
      </tr>
    </thead>
    <tbody>
      <?php for ($n = 1; $n <= 10; $n++):
        $item = null;
        foreach ($req['items'] as $it) {
          if ((int)$it['numero_item'] === $n) { $item = $it; break; }
        }
        $esPar = $n % 2 === 0;
      ?>
      <?php if ($item): ?>
      <tr<?= $esPar ? ' class="par"' : '' ?>>
        <td class="cn"><?= $n ?></td>
        <td><?= e($item['concepto']) ?></td>
        <td class="cn"><?= e($item['cantidad_fmt']) ?></td>
        <td style="font-size:8pt"><?= e($item['especificaciones'] ?? '') ?></td>
        <td class="cr"><?= e($item['precio_fmt']) ?></td>
        <td class="cr"><?= e($item['total_fmt']) ?></td>
      </tr>
      <?php else: ?>
      <tr class="vacia<?= $esPar ? ' par' : '' ?>">
        <td class="cn"><?= $n ?></td>
        <td></td><td></td><td></td><td></td><td></td>
      </tr>
      <?php endif; endfor; ?>
      <tr class="total-row">
        <td colspan="5" style="text-align:right;padding-right:6pt">COSTO TOTAL C/IVA</td>
        <td class="cr">$<?= number_format($totalIva, 2) ?></td>
      </tr>
    </tbody>
  </table>

  <!-- JUSTIFICACIÓN -->
  <div class="just-wrap">
    <div class="just-lbl">JUSTIFICACIÓN:</div>
    <div class="just-box"><?= e($req['justificacion'] ?? '') ?></div>
  </div>

  <!-- FIRMAS -->
  <div class="firmas-wrap">
    <!-- Labels SOLICITA / VALIDA / AUTORIZA -->
    <div class="firmas-row" style="margin-bottom:18pt">
      <div class="firma-b"><span class="firma-lbl" style="margin-bottom:0">SOLICITA:</span></div>
      <div class="firma-b"><span class="firma-lbl" style="margin-bottom:0">VALIDA:</span></div>
      <div class="firma-b"><span class="firma-lbl" style="margin-bottom:0">AUTORIZA:</span></div>
    </div>
    <!-- Nombre JEFE encima de su línea (como en el original) -->
    <div class="firmas-row" style="margin-bottom:1pt">
      <div class="firma-b"><div class="firma-nom"></div></div>
      <div class="firma-b">
        <div class="firma-nom" style="text-align:center">
          <?= e($instConf['jefe_recursos'] ?? '') ?>
        </div>
      </div>
      <div class="firma-b"><div class="firma-nom"></div></div>
    </div>
    <!-- Líneas de firma -->
    <div class="firmas-row">
      <div class="firma-b"><div class="firma-linea"></div></div>
      <div class="firma-b"><div class="firma-linea"></div></div>
      <div class="firma-b"><div class="firma-linea"></div></div>
    </div>
    <!-- Vo.Bo. + cargos debajo de líneas -->
    <div class="firmas-row" style="margin-top:2pt">
      <div class="firma-b">
        <div class="firma-cargo">Vo.Bo.</div>
      </div>
      <div class="firma-b">
        <div class="firma-cargo">JEFE DE RECURSOS MATERIALES</div>
      </div>
      <div class="firma-b"></div>
    </div>
    <!-- Segunda fila de Vo.Bo. + AUTORIZA -->
    <div class="firmas-row" style="margin-top:1pt">
      <div class="firma-b">
        <div class="firma-cargo">DIRECTOR (A)</div>
      </div>
      <div class="firma-b"></div>
      <div class="firma-b">
        <div class="firma-cargo">DIRECTOR (A) ADMINISTRATIVO (A)</div>
      </div>
    </div>
    <!-- Nombre del solicitante debajo del cargo -->
    <?php if (!empty($req['solicita_nombre'])): ?>
    <div class="firmas-row" style="margin-top:2pt">
      <div class="firma-b" style="font-size:8pt">
        <?= e($req['solicita_nombre']) ?>
      </div>
      <div class="firma-b"></div>
      <div class="firma-b" style="font-size:8pt">
        <?= e($instConf['director_admin'] ?? '') ?>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <!-- SECCIÓN INTERNA — fondo gris claro en separador, blanco en celdas -->
  <div class="sep-interno">
    Espacio para llenado solamente por el Jefe(a) del Departamerto de Recursos Materiales
  </div>

  <!-- Tabla interna: 4 columnas, 2 filas (label + espacio vacío) -->
  <table class="tbl-int">
    <tr>
      <td class="lbl-cell" style="width:28%">ASIGNADO A:</td>
      <td class="lbl-cell" style="width:24%">FECHA DE ASIGNACIÓN A<br>COMPRADOR(A):</td>
      <td class="lbl-cell" style="width:30%">FECHA DE CONCLUSIÓN Ó CIERRE DE TRÁMITE:</td>
      <td class="lbl-cell" style="width:18%">VO.BO. JEFE(A) DPTO RECURSOS<br>MATERIALES</td>
    </tr>
    <tr>
      <td class="val-cell"></td>
      <td class="val-cell"></td>
      <td class="val-cell"></td>
      <td class="val-cell"></td>
    </tr>
  </table>

  <!-- Segunda fila: Nombre y firma + Padrón de proveedores -->
  <table class="tbl-int">
    <tr>
      <td class="lbl-cell" style="width:28%">NOMBRE Y FIRMA:</td>
      <td class="lbl-cell" colspan="3">
        EL PROVEEDOR SE ENCUENTRA EN EL PADRÓN DE PROVEEDORES DE GOBIERNO:
      </td>
    </tr>
    <tr>
      <td class="val-cell"></td>
      <td class="val-cell" style="width:24%;text-align:center">
        <strong>MARQUE CON UNA X:</strong>
      </td>
      <td class="val-cell" style="width:24%;text-align:center">
        SI [<?= ($req['en_padron_proveedores'] ?? '') === '1' ? 'X' : '&nbsp;&nbsp;' ?>]
      </td>
      <td class="val-cell" style="width:20%;text-align:center">
        NO [<?= ($req['en_padron_proveedores'] ?? '') === '0' ? 'X' : '&nbsp;&nbsp;' ?>]
      </td>
    </tr>
  </table>

</div><!-- /.doc -->

<!-- NOTA IMPORTANTE — fuera del borde exterior, como en el original -->
<div class="nota-imp">
  IMPORTANTE: CUANDO EL COSTO SEA MAYOR DE $25,000.00 SE ANEXARÁN 3 COTIZACIONES*
</div>

<!-- PIE DEL DOCUMENTO — fuera del borde, en tres columnas -->
<div class="pie-doc">
  <span>Fecha de revisión: 05/05/2023</span>
  <span class="pie-center">14</span>
  <span class="pie-right">FOR 8.4 DeRM 01<br>Pág. 1/1</span>
</div>

<script>
<?php if (in_array($req['estado'], ['autorizada','comprada'])): ?>
window.addEventListener('load', () => setTimeout(() => window.print(), 500));
<?php endif; ?>
</script>

</body>
</html>
