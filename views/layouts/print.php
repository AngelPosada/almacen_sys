<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Documento — <?= e($vale['folio'] ?? '') ?></title>
  <style>
    /* ── Reset y base para impresión ── */
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      font-family: Arial, Helvetica, sans-serif;
      font-size: 10pt;
      color: #000;
      background: #fff;
    }

    /* ── Botón de impresión — solo en pantalla ── */
    .btn-imprimir {
      display: block;
      margin: 16px auto 12px;
      padding: 10px 28px;
      background: #0E734E;
      color: #fff;
      border: none;
      border-radius: 6px;
      font-size: 13px;
      cursor: pointer;
      font-family: Arial, sans-serif;
    }
    .btn-imprimir:hover { background: #0a5c3d; }

    @media print {
      .btn-imprimir { display: none; }
      body { margin: 0; }
      .pagina { box-shadow: none; margin: 0; }
    }

    /* ── Página del documento ── */
    .pagina {
      width: 21cm;
      min-height: 27cm;
      margin: 0 auto;
      padding: 1.2cm 1.5cm;
      background: #fff;
      box-shadow: 0 2px 12px rgba(0,0,0,.15);
    }
  </style>
</head>
<body>
  <button class="btn-imprimir" onclick="window.print()">
    🖨 Imprimir documento
  </button>
  <?= $content ?>
</body>
</html>
