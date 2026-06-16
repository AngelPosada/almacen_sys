<?php
/**
 * app/Services/MailService.php
 *
 * Servicio de correo electrónico institucional.
 * Usa PHPMailer con la configuración SMTP del .env.
 *
 * Flujo de envío:
 *   1. encolar()  → INSERT en tabla notificaciones (estado = pendiente)
 *   2. procesar() → lo ejecuta el cron/worker cada N minutos
 *
 * El envío directo via enviarDirecto() está disponible
 * para casos urgentes (alertas de stock crítico, etc.)
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception as MailException;

class MailService
{
    private array $smtpConfig;
    private array $instConfig;

    public function __construct()
    {
        $config          = require ROOT_PATH . '/config/config.php';
        $this->smtpConfig = $config['smtp'];
        $this->instConfig = $config['institucion'];
    }

    // ----------------------------------------------------------------
    // COLA DE NOTIFICACIONES
    // ----------------------------------------------------------------

    /**
     * Encola un correo para envío diferido.
     * No bloquea el hilo principal del request.
     *
     * @param string      $destino    Email o número de WhatsApp
     * @param string      $asunto     Asunto del correo
     * @param string      $cuerpo     HTML del cuerpo
     * @param string      $canal      'email' | 'whatsapp' | 'sistema'
     * @param int|null    $usuarioId  Destinatario interno (opcional)
     * @param string|null $refTipo    Tabla de referencia (vales, pedidos…)
     * @param int|null    $refId      ID del documento relacionado
     */
    public function encolar(
        string  $destino,
        string  $asunto,
        string  $cuerpo,
        string  $canal      = 'email',
        ?int    $usuarioId  = null,
        ?string $refTipo    = null,
        ?int    $refId      = null
    ): void {
        try {
            $db = Database::getInstance();
            $stmt = $db->prepare(
                'INSERT INTO notificaciones
                   (usuario_id, canal, destino, asunto, cuerpo,
                    referencia_tipo, referencia_id)
                 VALUES
                   (:uid, :canal, :destino, :asunto, :cuerpo,
                    :ref_tipo, :ref_id)'
            );
            $stmt->execute([
                ':uid'      => $usuarioId,
                ':canal'    => $canal,
                ':destino'  => $destino,
                ':asunto'   => $asunto,
                ':cuerpo'   => $cuerpo,
                ':ref_tipo' => $refTipo,
                ':ref_id'   => $refId,
            ]);
        } catch (Throwable $e) {
            Logger::error('MAIL', 'Error al encolar notificación: ' . $e->getMessage());
        }
    }

    /**
     * Procesa la cola de notificaciones pendientes.
     * Llamado por cron: php cron/procesar_notificaciones.php
     * O manualmente desde el panel de administración.
     *
     * @param  int $limite Máximo de notificaciones a procesar por llamado
     * @return array ['enviados' => N, 'fallidos' => N]
     */
    public function procesarCola(int $limite = 20): array
    {
        $db = Database::getInstance();

        // Obtener pendientes con menos de 3 intentos
        $stmt = $db->prepare(
            "SELECT * FROM notificaciones
             WHERE estado = 'pendiente' AND intentos < 3
             ORDER BY creado_en ASC
             LIMIT :lim"
        );
        $stmt->bindValue(':lim', $limite, PDO::PARAM_INT);
        $stmt->execute();
        $pendientes = $stmt->fetchAll();

        $enviados = 0;
        $fallidos = 0;

        foreach ($pendientes as $notif) {
            try {
                if ($notif['canal'] === 'email') {
                    $this->enviarDirecto(
                        $notif['destino'],
                        $notif['asunto'] ?? '(sin asunto)',
                        $notif['cuerpo']
                    );
                } elseif ($notif['canal'] === 'whatsapp') {
                    $this->enviarWhatsApp($notif['destino'], $notif['cuerpo']);
                }

                // Marcar como enviado
                $db->prepare(
                    "UPDATE notificaciones
                     SET estado = 'enviado', procesado_en = NOW(),
                         intentos = intentos + 1
                     WHERE id = :id"
                )->execute([':id' => $notif['id']]);

                $enviados++;

            } catch (Throwable $e) {
                $intentos = $notif['intentos'] + 1;
                $nuevoEstado = $intentos >= 3 ? 'fallido' : 'pendiente';

                $db->prepare(
                    "UPDATE notificaciones
                     SET estado = :estado,
                         intentos = :intentos,
                         ultimo_intento = NOW(),
                         error_detalle = :error
                     WHERE id = :id"
                )->execute([
                    ':estado'   => $nuevoEstado,
                    ':intentos' => $intentos,
                    ':error'    => $e->getMessage(),
                    ':id'       => $notif['id'],
                ]);

                Logger::warning('MAIL', "Fallo al enviar notif #{$notif['id']}: " . $e->getMessage());
                $fallidos++;
            }
        }

        return compact('enviados', 'fallidos');
    }

    // ----------------------------------------------------------------
    // ENVÍO DIRECTO (SÍNCRONO)
    // ----------------------------------------------------------------

    /**
     * Envía un correo de forma inmediata.
     * Para alertas urgentes o cuando no hay worker disponible.
     *
     * @throws MailException si el envío falla
     */
    public function enviarDirecto(
        string $destinoEmail,
        string $asunto,
        string $cuerpoHtml,
        string $cuerpoTexto = ''
    ): void {
        if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
            throw new RuntimeException(
                'PHPMailer no está instalado. Ejecuta: composer install'
            );
        }

        $mail = new PHPMailer(true);
        $smtp = $this->smtpConfig;

        // Configuración del servidor
        $mail->isSMTP();
        $mail->Host        = $smtp['host'];
        $mail->SMTPAuth    = true;
        $mail->Username    = $smtp['user'];
        $mail->Password    = $smtp['pass'];
        $mail->SMTPSecure  = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port        = $smtp['port'];
        $mail->CharSet     = 'UTF-8';

        // Remitente y destinatario
        $mail->setFrom($smtp['from_email'], $smtp['from_name']);
        $mail->addAddress($destinoEmail);
        $mail->addReplyTo($smtp['from_email'], $smtp['from_name']);

        // Contenido
        $mail->isHTML(true);
        $mail->Subject = $asunto;
        $mail->Body    = $this->envolverEnPlantilla($cuerpoHtml, $asunto);
        $mail->AltBody = $cuerpoTexto ?: strip_tags($cuerpoHtml);

        $mail->send();

        Logger::info('MAIL', "Correo enviado a {$destinoEmail}", ['asunto' => $asunto]);
    }

    // ----------------------------------------------------------------
    // WHATSAPP (API externa)
    // ----------------------------------------------------------------

    /**
     * Envía mensaje vía WhatsApp usando la API configurada en .env.
     */
    private function enviarWhatsApp(string $numero, string $mensaje): void
    {
        $config = require ROOT_PATH . '/config/config.php';
        $wa     = $config['whatsapp'];

        if (empty($wa['api_url']) || empty($wa['api_token'])) {
            throw new RuntimeException('WhatsApp API no configurada en .env');
        }

        $payload = json_encode([
            'to'      => $numero,
            'type'    => 'text',
            'message' => $mensaje,
        ]);

        $ch = curl_init($wa['api_url']);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $wa['api_token'],
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($error || $httpCode >= 400) {
            throw new RuntimeException(
                "WhatsApp API error {$httpCode}: {$error}"
            );
        }

        Logger::info('WHATSAPP', "Mensaje enviado a {$numero}");
    }

    // ----------------------------------------------------------------
    // PLANTILLAS DE CORREO INSTITUCIONALES
    // ----------------------------------------------------------------

    /**
     * Alerta de stock crítico — para Almacenista y Admin.
     */
    public function alertaStockCritico(
        string $destinoEmail,
        array  $productos
    ): void {
        $lista = '';
        foreach ($productos as $p) {
            $lista .= "<tr>
                <td style='padding:6px 12px;border-bottom:1px solid #eee'>{$p['codigo']}</td>
                <td style='padding:6px 12px;border-bottom:1px solid #eee'>{$p['nombre']}</td>
                <td style='padding:6px 12px;border-bottom:1px solid #eee;color:#c0392b;font-weight:bold'>
                    {$p['stock_texto']}
                </td>
                <td style='padding:6px 12px;border-bottom:1px solid #eee;color:#888'>
                    Mín: {$p['stock_minimo']} pz.
                </td>
            </tr>";
        }

        $cuerpo = "
            <h2 style='color:#0E734E'>⚠ Alerta de stock bajo</h2>
            <p>Los siguientes productos están por debajo del nivel mínimo:</p>
            <table style='width:100%;border-collapse:collapse;margin-top:16px'>
                <thead>
                    <tr style='background:#0E734E;color:#fff'>
                        <th style='padding:8px 12px;text-align:left'>Código</th>
                        <th style='padding:8px 12px;text-align:left'>Producto</th>
                        <th style='padding:8px 12px;text-align:left'>Stock actual</th>
                        <th style='padding:8px 12px;text-align:left'>Mínimo</th>
                    </tr>
                </thead>
                <tbody>{$lista}</tbody>
            </table>
            <p style='margin-top:20px'>
                <a href='" . env('APP_URL') . "/reportes/inventario'
                   style='background:#0E734E;color:#fff;padding:10px 20px;
                          text-decoration:none;border-radius:4px'>
                   Ver reporte de inventario
                </a>
            </p>
        ";

        $this->encolar(
            $destinoEmail,
            '⚠ Alerta: ' . count($productos) . ' producto(s) con stock bajo',
            $cuerpo,
            'email'
        );
    }

    /**
     * Notificación de nuevo pedido al almacenista.
     */
    public function notificarNuevoPedido(
        string $destinoEmail,
        array  $pedido
    ): void {
        $urgente = $pedido['prioridad'] === 'urgente'
            ? '<span style="background:#F2811D;color:#fff;padding:2px 8px;
                            border-radius:4px;font-size:12px">🔥 URGENTE</span> '
            : '';

        $cuerpo = "
            <h2 style='color:#0E734E'>Nuevo pedido recibido</h2>
            <p>{$urgente}Se ha registrado el pedido <strong>{$pedido['folio']}</strong>.</p>
            <table style='width:100%;border-collapse:collapse;margin:16px 0'>
                <tr>
                    <td style='padding:6px 0;color:#888;width:140px'>Solicitante:</td>
                    <td style='padding:6px 0;font-weight:500'>{$pedido['solicitante']}</td>
                </tr>
                <tr>
                    <td style='padding:6px 0;color:#888'>Plantel:</td>
                    <td style='padding:6px 0'>" . ($pedido['plantel'] ?? '—') . "</td>
                </tr>
                <tr>
                    <td style='padding:6px 0;color:#888'>Artículos:</td>
                    <td style='padding:6px 0'>{$pedido['total_items']}</td>
                </tr>
                <tr>
                    <td style='padding:6px 0;color:#888'>Fecha requerida:</td>
                    <td style='padding:6px 0'>" . ($pedido['fecha_requerida'] ?? 'No especificada') . "</td>
                </tr>
            </table>
            <a href='" . env('APP_URL') . "/pedidos/{$pedido['id']}'
               style='background:#0E734E;color:#fff;padding:10px 20px;
                      text-decoration:none;border-radius:4px'>
               Ver pedido
            </a>
        ";

        $this->encolar(
            $destinoEmail,
            "{$urgente}Nuevo pedido {$pedido['folio']}",
            $cuerpo,
            'email'
        );
    }

    /**
     * Notificación de vale emitido al receptor.
     */
    public function notificarValeEmitido(
        string $destinoEmail,
        array  $vale
    ): void {
        $tipo   = $vale['tipo'] === 'salida' ? 'Vale de salida' : 'Vale de resguardo';
        $cuerpo = "
            <h2 style='color:#0E734E'>{$tipo} emitido</h2>
            <p>Se ha emitido el {$tipo} <strong>{$vale['folio']}</strong>.</p>
            <p style='margin-top:16px'>
                <a href='" . env('APP_URL') . "/vales/{$vale['id']}/pdf'
                   target='_blank'
                   style='background:#0E734E;color:#fff;padding:10px 20px;
                          text-decoration:none;border-radius:4px'>
                   Ver / Imprimir vale
                </a>
            </p>
        ";

        $this->encolar(
            $destinoEmail,
            "{$tipo} emitido: {$vale['folio']}",
            $cuerpo,
            'email'
        );
    }

    // ----------------------------------------------------------------
    // PLANTILLA HTML BASE
    // ----------------------------------------------------------------

    private function envolverEnPlantilla(string $cuerpoHtml, string $titulo): string
    {
        $inst    = $this->instConfig['nombre'] ?? 'Sistema de Almacén';
        $anio    = date('Y');

        return <<<HTML
        <!DOCTYPE html>
        <html lang="es">
        <head>
          <meta charset="UTF-8">
          <meta name="viewport" content="width=device-width,initial-scale=1">
          <title>{$titulo}</title>
        </head>
        <body style="margin:0;padding:0;background:#f2f2f2;font-family:Arial,sans-serif">
          <table width="100%" cellpadding="0" cellspacing="0" style="background:#f2f2f2">
            <tr><td align="center" style="padding:32px 16px">
              <table width="600" cellpadding="0" cellspacing="0"
                     style="background:#fff;border-radius:8px;
                            box-shadow:0 2px 8px rgba(0,0,0,.08)">
                <!-- Header -->
                <tr>
                  <td style="background:#0E734E;padding:24px 32px;
                              border-radius:8px 8px 0 0;text-align:center">
                    <div style="color:#fff;font-size:18px;font-weight:bold">
                      Sistema de Almacén Escolar
                    </div>
                    <div style="color:rgba(255,255,255,.75);font-size:13px;margin-top:4px">
                      {$inst}
                    </div>
                  </td>
                </tr>
                <!-- Body -->
                <tr>
                  <td style="padding:32px">
                    {$cuerpoHtml}
                  </td>
                </tr>
                <!-- Footer -->
                <tr>
                  <td style="background:#f8f9fa;padding:16px 32px;
                              border-radius:0 0 8px 8px;text-align:center;
                              color:#888;font-size:12px;
                              border-top:1px solid #eee">
                    {$inst} &copy; {$anio} &mdash;
                    Este correo fue generado automáticamente.
                  </td>
                </tr>
              </table>
            </td></tr>
          </table>
        </body>
        </html>
        HTML;
    }
}
