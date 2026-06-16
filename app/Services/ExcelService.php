<?php
/**
 * app/Services/ExcelService.php
 *
 * Estrategia: abrir la plantilla oficial y escribir solo los datos.
 * El diseño, fuentes, colores y logo ya están en la plantilla.
 *
 * Mapa de celdas de la plantilla 299v14.xlsx:
 *   G6       → Fecha de Elaboración (valor)
 *   C8:D8    → Plantel/Area (valor)
 *   G8:H8    → Clave Programática (valor)
 *   B12:C12  → Concepto ítem 1    (filas 12-21 para ítems 1-10)
 *   D12      → Cantidad ítem 1
 *   E12:F12  → Especificaciones ítem 1
 *   G12      → Precio unitario ítem 1
 *   H12      → Total ítem 1
 *   H22      → COSTO TOTAL C/IVA
 *   A24:H25  → Justificación
 *   A27      → SOLICITA (nombre del solicitante)
 */

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class ExcelService
{
    private const VERDE_OSC = '0E734E';
    private const BLANCO    = 'FFFFFF';
    private const GRIS_CLR  = 'F0F0F0';
    private const GRIS_LIN  = 'AAAAAA';
    private const AMARILLO  = 'FFF2CC';
    private const ROJO_NT   = 'C00000';
    private const GRIS_PIE  = '888888';

    // ----------------------------------------------------------------
    // REQUISICIÓN — escribe sobre la plantilla oficial
    // ----------------------------------------------------------------

    public function exportarRequisicion(array $datos, ?string $ruta = null): ?string
    {
        $this->verificar();

        $plantillaPath = ROOT_PATH . '/storage/plantillas/requisicion_v14.xlsx';

        if (!file_exists($plantillaPath)) {
            throw new RuntimeException(
                'Plantilla oficial no encontrada en storage/plantillas/requisicion_v14.xlsx'
            );
        }

        // Cargar la plantilla original (preserva diseño, logo y estilos)
        $wb = IOFactory::load($plantillaPath);
        $ws = $wb->getActiveSheet();

        // ── Datos de cabecera ──
        // Fecha de Elaboración → celda G6 (el valor va en H6, F6 es la etiqueta)
        $ws->getCell('G6')->setValue($datos['fecha_elaboracion'] ?? '');

        // Plantel/Area → C8:D8
        $ws->getCell('C8')->setValue($datos['plantel'] ?? '');

        // Clave Programática → H8
        $ws->getCell('G8')->setValue($datos['clave_programatica'] ?? '');

        // ── 10 renglones de artículos (filas 12-21) ──
        for ($n = 1; $n <= 10; $n++) {
            $r    = 11 + $n; // fila 12..21
            $item = null;

            foreach ($datos['items'] as $it) {
                if ((int)$it['numero_item'] === $n) { $item = $it; break; }
            }

            if ($item) {
                // Concepto → B{r}:C{r} (merge ya existente en plantilla)
                $ws->getCell("B{$r}")->setValue($item['concepto'] ?? '');

                // Cantidad → D{r}
                $ws->getCell("D{$r}")->setValue((float)$item['cantidad']);

                // Especificaciones → E{r}:F{r}
                $ws->getCell("E{$r}")->setValue($item['especificaciones'] ?? '');

                // Precio Unitario → G{r}
                $ws->getCell("G{$r}")->setValue((float)$item['precio_unitario']);
                $ws->getStyle("G{$r}")->getNumberFormat()
                   ->setFormatCode('"$"#,##0.0000');

                // Total → H{r}
                $ws->getCell("H{$r}")->setValue((float)$item['total']);
                $ws->getStyle("H{$r}")->getNumberFormat()
                   ->setFormatCode('"$"#,##0.00');
            }
        }

        // ── COSTO TOTAL C/IVA → H22 ──
        $ws->getCell('H22')->setValue((float)($datos['total_estimado'] ?? 0));
        $ws->getStyle('H22')->getNumberFormat()
           ->setFormatCode('"$"#,##0.00');

        // ── Justificación → A24:H25 ──
        $ws->getCell('A24')->setValue($datos['justificacion'] ?? '');

        // ── Firmantes ──
        // Solicita: va debajo de A27 — en la línea A28 hay espacio vacío
        // En el original A30 tiene "Vo.Bo." y E30 tiene "AUTORIZA:"
        // El nombre del solicitante va en A26 (encima de la línea)
        $ws->getCell('A28')->setValue($datos['solicita_nombre'] ?? '');
        $ws->getCell('A31')->setValue($datos['solicita_nombre'] ?? '');

        // VALIDA (nombre ya está hardcoded en E28 en la plantilla — LEC. JOSE HUGO...)
        // Solo actualizamos si viene en los datos
        if (!empty($datos['valida_nombre'])) {
            $ws->getCell('E28')->setValue($datos['valida_nombre']);
        }

        // AUTORIZA (nombre ya está en E31 en la plantilla — LIC. JOSE ANTONIO...)
        if (!empty($datos['autoriza_nombre'])) {
            $ws->getCell('E31')->setValue($datos['autoriza_nombre']);
        }

        // ── Folio en el pie (opcional — la plantilla no lo tiene) ──
        // Podemos agregarlo en una celda libre del área del pie
        // La fila 41 tiene la nota de cotizaciones
        // No modificamos el pie para no alterar el diseño original

        return $this->descargar(
            $wb,
            'Requisicion_' . ($datos['folio'] ?? date('Ymd')),
            $ruta
        );
    }

    // ----------------------------------------------------------------
    // REPORTE DE INVENTARIO (sin plantilla — generado programáticamente)
    // ----------------------------------------------------------------

    public function exportarInventario(array $datos, ?string $ruta = null): ?string
    {
        $this->verificar();

        $wb = new Spreadsheet();
        $ws = $wb->getActiveSheet();
        $ws->setTitle('Inventario');

        $cols = [
            'A'=>['Código',9],     'B'=>['Nombre',30],
            'C'=>['Categoría',18], 'D'=>['Unidad',10],
            'E'=>['U/Caja',7],     'F'=>['Piezas',12],
            'G'=>['Stock',18],     'H'=>['Precio unit.',13],
            'I'=>['Valor total',13],'J'=>['Estado',13],
        ];
        foreach ($cols as $l=>[$t,$w]) {
            $ws->getColumnDimension($l)->setWidth($w);
        }

        // Encabezado
        $wb->getDefaultStyle()->getFont()->setName('Arial')->setSize(9);

        $ws->mergeCells('A1:J1');
        $ws->getCell('A1')->setValue(strtoupper($datos['inst_nombre'] ?? ''));
        $ws->getStyle('A1')->applyFromArray([
            'font'      => ['name'=>'Arial','size'=>12,'bold'=>true,
                            'color'=>['rgb'=>self::BLANCO]],
            'fill'      => ['fillType'=>Fill::FILL_SOLID,
                            'startColor'=>['rgb'=>self::VERDE_OSC]],
            'alignment' => ['horizontal'=>Alignment::HORIZONTAL_CENTER,
                            'vertical'  =>Alignment::VERTICAL_CENTER],
        ]);
        $ws->getRowDimension(1)->setRowHeight(22);

        $ws->mergeCells('A2:J2');
        $ws->getCell('A2')->setValue('REPORTE DE INVENTARIO VALORIZADO');
        $ws->getStyle('A2')->applyFromArray([
            'font'      => ['name'=>'Arial','size'=>11,'bold'=>true,
                            'color'=>['rgb'=>self::VERDE_OSC]],
            'fill'      => ['fillType'=>Fill::FILL_SOLID,
                            'startColor'=>['rgb'=>'E8F4EF']],
            'alignment' => ['horizontal'=>Alignment::HORIZONTAL_CENTER],
        ]);

        $ws->mergeCells('A3:J3');
        $ws->getCell('A3')->setValue(
            "Generado: {$datos['fecha']} | Por: {$datos['generado_por']}"
        );
        $ws->getStyle('A3')->applyFromArray([
            'font'      => ['size'=>8,'color'=>['rgb'=>'666666']],
            'alignment' => ['horizontal'=>Alignment::HORIZONTAL_CENTER],
        ]);

        // Cabeceras
        $fila = 4;
        foreach ($cols as $l=>[$titulo]) {
            $ws->getCell("{$l}{$fila}")->setValue($titulo);
        }
        $ws->getStyle("A4:J4")->applyFromArray([
            'font'      => ['name'=>'Arial','size'=>9,'bold'=>true,
                            'color'=>['rgb'=>self::BLANCO]],
            'fill'      => ['fillType'=>Fill::FILL_SOLID,
                            'startColor'=>['rgb'=>self::VERDE_OSC]],
            'alignment' => ['horizontal'=>Alignment::HORIZONTAL_CENTER,
                            'vertical'  =>Alignment::VERTICAL_CENTER],
            'borders'   => ['allBorders'=>['borderStyle'=>Border::BORDER_THIN,
                                           'color'=>['rgb'=>'0A5C3D']]],
        ]);
        $ws->getRowDimension(4)->setRowHeight(18);

        // Datos
        $fila     = 5;
        $filas    = $datos['filas'];
        array_shift($filas);
        $totalVal = 0;

        foreach ($filas as $dato) {
            [$cod,$nom,$cat,$uni,$upc,$pzs,$stk,$pre,$val,$est] = array_values($dato);
            $totalVal += (float)$val;
            $clr = match($est) {
                'sin_stock','critico' => 'FDECEA',
                'bajo'               => 'FEF6EC',
                default              => $fila % 2 === 0 ? 'F8FDF9' : self::BLANCO,
            };
            foreach (range('A','J') as $i=>$l) {
                $ws->getCell("{$l}{$fila}")->setValue(
                    [$cod,$nom,$cat,$uni,(int)$upc,(int)$pzs,$stk,(float)$pre,(float)$val,$est][$i]
                );
            }
            $ws->getStyle("A{$fila}:J{$fila}")->applyFromArray([
                'font'    => ['name'=>'Arial','size'=>9],
                'fill'    => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>$clr]],
                'borders' => ['allBorders'=>['borderStyle'=>Border::BORDER_THIN,
                                             'color'=>['rgb'=>'DDDDDD']]],
            ]);
            $ws->getStyle("H{$fila}")->getNumberFormat()->setFormatCode('"$"#,##0.0000');
            $ws->getStyle("I{$fila}")->getNumberFormat()->setFormatCode('"$"#,##0.00');
            $fila++;
        }

        // Total
        $ws->getCell("A{$fila}")->setValue('TOTAL');
        $ws->getCell("I{$fila}")->setValue($totalVal);
        $ws->getStyle("A{$fila}:J{$fila}")->applyFromArray([
            'font'    => ['name'=>'Arial','bold'=>true,
                          'color'=>['rgb'=>self::VERDE_OSC]],
            'fill'    => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'E8F4EF']],
            'borders' => ['top'=>['borderStyle'=>Border::BORDER_MEDIUM,
                                  'color'=>['rgb'=>self::VERDE_OSC]]],
        ]);
        $ws->getStyle("I{$fila}")->getNumberFormat()->setFormatCode('"$"#,##0.00');
        $ws->freezePane('A5');

        return $this->descargar($wb, 'Inventario_' . date('Ymd_His'), $ruta);
    }

    // ----------------------------------------------------------------
    // REPORTE DE MOVIMIENTOS
    // ----------------------------------------------------------------

    public function exportarMovimientos(array $movimientos, array $filtros, ?string $ruta = null): ?string
    {
        $this->verificar();
        $wb = new Spreadsheet();
        $ws = $wb->getActiveSheet();
        $ws->setTitle('Movimientos');

        $cols = ['A'=>['Fecha',14],'B'=>['Tipo',10],'C'=>['Producto',28],
                 'D'=>['Código',10],'E'=>['Cantidad',14],'F'=>['Anterior',12],
                 'G'=>['Posterior',12],'H'=>['Usuario',20]];
        foreach ($cols as $l=>[$t,$w]) {
            $ws->getColumnDimension($l)->setWidth($w);
            $ws->getCell("{$l}1")->setValue($t);
        }
        $ws->getStyle('A1:H1')->applyFromArray([
            'font'      => ['name'=>'Arial','bold'=>true,
                            'color'=>['rgb'=>self::BLANCO]],
            'fill'      => ['fillType'=>Fill::FILL_SOLID,
                            'startColor'=>['rgb'=>self::VERDE_OSC]],
            'alignment' => ['horizontal'=>Alignment::HORIZONTAL_CENTER],
        ]);
        $ws->getRowDimension(1)->setRowHeight(18);

        $fila = 2;
        foreach ($movimientos as $m) {
            $data = [$m['fecha_fmt'],$m['tipo'],$m['producto_nombre'],
                     $m['producto_codigo'],$m['signo'].$m['cantidad_texto'],
                     $m['anterior_texto'],$m['posterior_texto'],$m['usuario_nombre']];
            foreach (range('A','H') as $i=>$l) {
                $ws->getCell("{$l}{$fila}")->setValue($data[$i]);
            }
            $clr = isset($m['es_salida']) && $m['es_salida']
                   ? 'FEF6EC' : ($fila%2===0?'F8FDF9':self::BLANCO);
            $ws->getStyle("A{$fila}:H{$fila}")->applyFromArray([
                'font'    => ['name'=>'Arial','size'=>9],
                'fill'    => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>$clr]],
                'borders' => ['allBorders'=>['borderStyle'=>Border::BORDER_THIN,
                                             'color'=>['rgb'=>'DDDDDD']]],
            ]);
            $fila++;
        }

        return $this->descargar($wb, 'Movimientos_'.date('Ymd_His'), $ruta);
    }

    // ----------------------------------------------------------------
    // HELPERS
    // ----------------------------------------------------------------

    private function verificar(): void
    {
        if (!class_exists('PhpOffice\\PhpSpreadsheet\\Spreadsheet')) {
            throw new RuntimeException(
                'PhpSpreadsheet no está instalado. Ejecuta: composer install'
            );
        }
    }

    private function descargar($wb, string $nombre, ?string $ruta): ?string
    {
        $writer  = new Xlsx($wb);
        if ($ruta !== null) { $writer->save($ruta); return $ruta; }
        $archivo = $nombre . '.xlsx';
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header("Content-Disposition: attachment; filename=\"{$archivo}\"");
        header('Cache-Control: max-age=0');
        header('Pragma: no-cache');
        $writer->save('php://output');
        exit;
    }
}
