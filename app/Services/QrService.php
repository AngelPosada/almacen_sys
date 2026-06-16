<?php
/**
 * app/Services/QrService.php
 *
 * Generación de códigos QR para productos y empleados.
 * Usa endroid/qr-code v5.
 *
 * El QR codifica una URL del sistema:
 *   Producto:  {APP_URL}/productos/{id}
 *   Empleado:  {APP_URL}/empleados/{numero_empleado}
 *
 * Los archivos PNG se guardan en storage/qr/
 * y la ruta se persiste en la BD (productos.codigo_qr / empleados.codigo_qr).
 */

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Label\Label;
use Endroid\QrCode\Label\Font\OpenSans;

class QrService
{
    private string $storageQr;
    private string $appUrl;

    public function __construct()
    {
        $config          = require ROOT_PATH . '/config/config.php';
        $this->storageQr = ROOT_PATH . '/' . ltrim($config['paths']['storage_qr'], '/');
        $this->appUrl    = rtrim($config['app']['url'], '/');

        // Garantizar que el directorio existe
        if (!is_dir($this->storageQr)) {
            mkdir($this->storageQr, 0755, true);
        }
    }

    // ----------------------------------------------------------------
    // PRODUCTO
    // ----------------------------------------------------------------

    /**
     * Genera el QR de un producto y lo guarda en storage/qr/
     * Actualiza el campo codigo_qr en la BD.
     *
     * @param  int    $productoId
     * @param  string $codigo        Código institucional del producto
     * @param  string $nombre        Nombre del producto (etiqueta bajo el QR)
     * @return string Ruta relativa del archivo generado
     */
    public function generarParaProducto(int $productoId, string $codigo, string $nombre): string
    {
        $this->verificarDisponibilidad();

        $contenido = "{$this->appUrl}/productos/{$productoId}";
        $nombreArchivo = "prod_{$productoId}_{$codigo}.png";
        $rutaAbsoluta  = $this->storageQr . $nombreArchivo;
        $etiqueta      = strlen($nombre) > 30 ? substr($nombre, 0, 27) . '…' : $nombre;

        $this->generar($contenido, $rutaAbsoluta, $etiqueta);

        // Actualizar la BD con la ruta relativa
        $rutaRelativa = 'storage/qr/' . $nombreArchivo;
        $db = Database::getInstance();
        $db->prepare('UPDATE productos SET codigo_qr = :ruta WHERE id = :id')
           ->execute([':ruta' => $rutaRelativa, ':id' => $productoId]);

        AuditoriaService::log('productos', 'generar_qr', $productoId,
            "QR generado para producto: {$codigo}"
        );

        Logger::info('QR', "QR generado para producto #{$productoId}", [
            'codigo' => $codigo,
            'ruta'   => $rutaRelativa,
        ]);

        return $rutaRelativa;
    }

    /**
     * Genera el QR de un empleado.
     *
     * @param  int    $empleadoId
     * @param  string $numeroEmpleado
     * @param  string $nombreCompleto
     * @return string Ruta relativa del archivo generado
     */
    public function generarParaEmpleado(
        int    $empleadoId,
        string $numeroEmpleado,
        string $nombreCompleto
    ): string {
        $this->verificarDisponibilidad();

        // El QR del empleado codifica su número de empleado
        // para identificación rápida con scanner
        $contenido     = $numeroEmpleado;
        $nombreArchivo = "emp_{$empleadoId}_{$numeroEmpleado}.png";
        $rutaAbsoluta  = $this->storageQr . $nombreArchivo;
        $etiqueta      = $numeroEmpleado . ' — ' .
                         (strlen($nombreCompleto) > 25
                             ? substr($nombreCompleto, 0, 22) . '…'
                             : $nombreCompleto);

        $this->generar($contenido, $rutaAbsoluta, $etiqueta);

        $rutaRelativa = 'storage/qr/' . $nombreArchivo;
        $db = Database::getInstance();
        $db->prepare('UPDATE empleados SET codigo_qr = :ruta WHERE id = :id')
           ->execute([':ruta' => $rutaRelativa, ':id' => $empleadoId]);

        Logger::info('QR', "QR generado para empleado #{$empleadoId}", [
            'numero' => $numeroEmpleado,
        ]);

        return $rutaRelativa;
    }

    // ----------------------------------------------------------------
    // DESCARGA DIRECTA (para el endpoint del controller)
    // ----------------------------------------------------------------

    /**
     * Genera el QR y lo envía directamente como imagen PNG al navegador.
     * No guarda en disco — para previsualización rápida.
     */
    public function descargarQrProducto(int $productoId, string $codigo, string $nombre): void
    {
        $this->verificarDisponibilidad();

        $contenido = "{$this->appUrl}/productos/{$productoId}";
        $etiqueta  = strlen($nombre) > 30 ? substr($nombre, 0, 27) . '…' : $nombre;

        header('Content-Type: image/png');
        header("Content-Disposition: attachment; filename=\"QR_{$codigo}.png\"");
        header('Cache-Control: max-age=86400');

        echo $this->generarRaw($contenido, $etiqueta);
        exit;
    }

    // ----------------------------------------------------------------
    // GENERACIÓN INTERNA
    // ----------------------------------------------------------------

    /**
     * Genera el QR y lo guarda en $rutaAbsoluta.
     */
    private function generar(string $contenido, string $rutaAbsoluta, string $etiqueta): void
    {
        $writer = new PngWriter();

        $qr = QrCode::create($contenido)
            ->setEncoding(new Encoding('UTF-8'))
            ->setErrorCorrectionLevel(ErrorCorrectionLevel::High)
            ->setSize(300)
            ->setMargin(12)
            ->setRoundBlockSizeMode(RoundBlockSizeMode::Margin)
            ->setForegroundColor(new Color(14, 115, 78))   // Verde institucional
            ->setBackgroundColor(new Color(255, 255, 255));

        $label = Label::create($etiqueta)
            ->setTextColor(new Color(30, 30, 30));

        $result = $writer->write($qr, null, $label);
        $result->saveToFile($rutaAbsoluta);
    }

    /**
     * Genera el QR en memoria y retorna los bytes PNG.
     */
    private function generarRaw(string $contenido, string $etiqueta): string
    {
        $writer = new PngWriter();

        $qr = QrCode::create($contenido)
            ->setEncoding(new Encoding('UTF-8'))
            ->setErrorCorrectionLevel(ErrorCorrectionLevel::High)
            ->setSize(300)
            ->setMargin(12)
            ->setForegroundColor(new Color(14, 115, 78))
            ->setBackgroundColor(new Color(255, 255, 255));

        $label = Label::create($etiqueta)
            ->setTextColor(new Color(30, 30, 30));

        return $writer->write($qr, null, $label)->getString();
    }

    private function verificarDisponibilidad(): void
    {
        if (!class_exists('Endroid\\QrCode\\QrCode')) {
            throw new RuntimeException(
                'endroid/qr-code no está instalado. Ejecuta: composer install'
            );
        }
    }
}
