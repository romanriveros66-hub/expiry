<?php
/**
 * Servicio de Notificaciones por Correo Electrónico con Resend API
 * Sistema de Vencimientos - Expiry
 */

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/smtp_helper.php';

class ResendEmailService {
    const RESEND_API_URL = 'https://api.resend.com/emails';
    const DEFAULT_API_KEY = '';
    const DEFAULT_FROM = 'Expiry Alertas <onboarding@resend.dev>';

    /**
     * Envía un correo electrónico utilizando el motor activo (SMTP / Gmail o Resend API)
     * Soporta entrega simultánea a múltiples destinatarios (To + CC)
     */
    public static function sendEmail($to, string $subject, string $htmlBody, $cc = null, $apiKey = null, $from = null): array {
        $pdo = Database::getConnection();

        // Obtener configuración activa completa
        $stmtCfg = $pdo->query("
            SELECT resend_api_key, email_remitente, notificar_email, nombre_negocio, moneda_simbolo,
                   email_metodo, smtp_host, smtp_port, smtp_seguridad, smtp_user, smtp_pass 
            FROM configuracion 
            LIMIT 1
        ");
        $cfg = $stmtCfg->fetch();

        $metodo = strtoupper($cfg['email_metodo'] ?? 'RESEND');
        $nombreNegocio = !empty($cfg['nombre_negocio']) ? $cfg['nombre_negocio'] : 'Minimarket San José';

        $toArray = is_array($to) ? $to : array_map('trim', explode(',', $to));
        $toArray = array_values(array_filter($toArray, function($e) {
            return filter_var($e, FILTER_VALIDATE_EMAIL);
        }));

        if (empty($toArray)) {
            return [
                'success'   => false,
                'http_code' => 400,
                'error'     => 'No se proporcionó ningún destinatario principal (to) con correo electrónico válido.'
            ];
        }

        // =========================================================================
        // MOTOR 1: GMAIL / SERVIDOR SMTP (Envía a TODOS sin restricciones de dominio)
        // =========================================================================
        if ($metodo === 'SMTP') {
            if (empty($cfg['smtp_pass'])) {
                return [
                    'success'   => false,
                    'http_code' => 400,
                    'error'     => 'El motor está configurado en Gmail / SMTP, pero falta la Contraseña de Aplicación de Google. Ingresala en la configuración de reportes para enviar a todos los destinatarios.',
                    'metodo'    => 'SMTP'
                ];
            }

            $host     = !empty($cfg['smtp_host']) ? $cfg['smtp_host'] : 'smtp.gmail.com';
            $port     = (int)(!empty($cfg['smtp_port']) ? $cfg['smtp_port'] : 465);
            $sec      = !empty($cfg['smtp_seguridad']) ? $cfg['smtp_seguridad'] : 'ssl';
            $user     = !empty($cfg['smtp_user']) ? $cfg['smtp_user'] : $toArray[0];
            $pass     = $cfg['smtp_pass'];
            $fromName = "Expiry Alertas - {$nombreNegocio}";
            $fromMail = $user;

            $smtpRes = SmtpMailer::send($host, $port, $sec, $user, $pass, $fromMail, $fromName, $toArray, $subject, $htmlBody, $cc);
            if ($smtpRes['success']) {
                return [
                    'success'        => true,
                    'http_code'      => 200,
                    'metodo'         => 'SMTP',
                    'recipients'     => $smtpRes['recipients'],
                    'total_enviados' => $smtpRes['total_enviados'],
                    'data'           => ['id' => 'smtp-' . time()]
                ];
            } else {
                return [
                    'success'   => false,
                    'http_code' => 500,
                    'metodo'    => 'SMTP',
                    'error'     => $smtpRes['error']
                ];
            }
        }

        // =========================================================================
        // MOTOR 2: BREVO API (NUBE) O RESEND API (NUBE)
        // =========================================================================
        $key = !empty($apiKey) ? $apiKey : (!empty($cfg['resend_api_key']) ? $cfg['resend_api_key'] : self::DEFAULT_API_KEY);
        $sender = !empty($from) ? $from : (!empty($cfg['email_remitente']) ? $cfg['email_remitente'] : self::DEFAULT_FROM);

        // Procesar destinatarios en copia (CC) si fueron provistos
        $ccArray = [];
        if (!empty($cc)) {
            $rawCc = is_array($cc) ? $cc : array_map('trim', explode(',', $cc));
            $ccArray = array_values(array_filter($rawCc, function($e) {
                return filter_var($e, FILTER_VALIDATE_EMAIL);
            }));
        }

        // Si la clave es de Brevo (inicia con xkeysib-)
        if (strpos($key, 'xkeysib-') === 0) {
            $brevoSenderEmail = 'mckakucorp@gmail.com';
            if (!empty($sender) && filter_var($sender, FILTER_VALIDATE_EMAIL) && strpos($sender, 'resend.dev') === false) {
                $brevoSenderEmail = $sender;
            }

            $payloadBrevo = [
                'sender'      => [
                    'name'  => "Expiry Alertas - {$nombreNegocio}",
                    'email' => $brevoSenderEmail
                ],
                'to'          => array_map(function($em) { return ['email' => $em]; }, $toArray),
                'subject'     => $subject,
                'htmlContent' => $htmlBody
            ];

            if (!empty($ccArray)) {
                $payloadBrevo['cc'] = array_map(function($em) { return ['email' => $em]; }, $ccArray);
            }

            $ch = curl_init('https://api.brevo.com/v3/smtp/email');
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER     => [
                    'api-key: ' . $key,
                    'Content-Type: application/json',
                    'Accept: application/json'
                ],
                CURLOPT_POSTFIELDS     => json_encode($payloadBrevo),
                CURLOPT_TIMEOUT        => 15,
                CURLOPT_SSL_VERIFYPEER => false
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($curlError) {
                return [
                    'success'   => false,
                    'http_code' => $httpCode,
                    'error'     => 'Error cURL con Brevo API: ' . $curlError
                ];
            }

            $responseData = json_decode($response, true);
            if ($httpCode >= 200 && $httpCode < 300) {
                $allDest = array_merge($toArray, $ccArray);
                return [
                    'success'        => true,
                    'http_code'      => $httpCode,
                    'metodo'         => 'BREVO',
                    'data'           => $responseData,
                    'recipients'     => $allDest,
                    'total_enviados' => count($allDest)
                ];
            } else {
                $err = $responseData['message'] ?? 'Error desconocido al enviar con Brevo API';
                return [
                    'success'   => false,
                    'http_code' => $httpCode,
                    'error'     => "Brevo API Error ({$httpCode}): {$err}",
                    'metodo'    => 'BREVO'
                ];
            }
        }

        // De lo contrario, utilizar Resend API
        $payload = [
            'from'    => $sender,
            'to'      => $toArray,
            'subject' => $subject,
            'html'    => $htmlBody
        ];

        if (!empty($ccArray)) {
            $payload['cc'] = $ccArray;
        }

        $ch = curl_init(self::RESEND_API_URL);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $key,
                'Content-Type: application/json'
            ],
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => false // Asegura compatibilidad en entornos locales Windows
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            return [
                'success'    => false,
                'http_code'  => $httpCode,
                'error'      => 'Error de conexión cURL con Resend: ' . $curlError
            ];
        }

        $responseData = json_decode($response, true);

        if ($httpCode >= 200 && $httpCode < 300) {
            $allDest = array_merge($payload['to'], $payload['cc'] ?? []);
            return [
                'success'        => true,
                'http_code'      => $httpCode,
                'metodo'         => 'RESEND',
                'data'           => $responseData,
                'recipients'     => $allDest,
                'total_enviados' => count($allDest)
            ];
        } else {
            $errorMsg = $responseData['message'] ?? ($responseData['name'] ?? 'Error desconocido al enviar con Resend');

            // Caso: Modo prueba de Resend (dominio no verificado).
            // Resend bloquea envíos a cualquiera que no sea el correo de la cuenta registrada.
            if ($httpCode === 403
                && stripos($errorMsg, 'only send testing emails') !== false
                && preg_match('/\(([^()\s]+@[^()\s]+)\)/', $errorMsg, $m)
                && filter_var($m[1], FILTER_VALIDATE_EMAIL)
            ) {
                $permitido = strtolower($m[1]);
                $todos = array_merge($payload['to'], $payload['cc'] ?? []);
                $omitidos = array_values(array_filter($todos, function ($e) use ($permitido) {
                    return strtolower($e) !== $permitido;
                }));

                // Si SMTP está configurado con contraseña, realizar rescate automático enviando a TODOS por SMTP
                if (!empty($cfg['smtp_pass'])) {
                    $host     = !empty($cfg['smtp_host']) ? $cfg['smtp_host'] : 'smtp.gmail.com';
                    $port     = (int)(!empty($cfg['smtp_port']) ? $cfg['smtp_port'] : 465);
                    $sec      = !empty($cfg['smtp_seguridad']) ? $cfg['smtp_seguridad'] : 'ssl';
                    $user     = !empty($cfg['smtp_user']) ? $cfg['smtp_user'] : $permitido;
                    $pass     = $cfg['smtp_pass'];
                    $fromName = "Expiry Alertas - {$nombreNegocio}";
                    $fromMail = $user;

                    $smtpRescue = SmtpMailer::send($host, $port, $sec, $user, $pass, $fromMail, $fromName, $toArray, $subject, $htmlBody, $cc);
                    if ($smtpRescue['success']) {
                        return [
                            'success'        => true,
                            'http_code'      => 200,
                            'metodo'         => 'SMTP_AUTORESCUE',
                            'recipients'     => $smtpRescue['recipients'],
                            'total_enviados' => $smtpRescue['total_enviados'],
                            'data'           => ['id' => 'smtp-rescue-' . time()],
                            'nota'           => 'Resend bloqueó remitentes por sandbox; se entregó automáticamente a todos mediante Gmail SMTP.'
                        ];
                    }
                }

                // Si no hay SMTP configurado, reintentar al menos al dueño en Resend pero señalizar claramente la restricción de sandbox
                $retryPayload = $payload;
                $retryPayload['to'] = [$permitido];
                unset($retryPayload['cc']);

                $ch = curl_init(self::RESEND_API_URL);
                curl_setopt_array($ch, [
                    CURLOPT_POST           => true,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_HTTPHEADER     => [
                        'Authorization: Bearer ' . $key,
                        'Content-Type: application/json'
                    ],
                    CURLOPT_POSTFIELDS     => json_encode($retryPayload),
                    CURLOPT_TIMEOUT        => 15,
                    CURLOPT_SSL_VERIFYPEER => false
                ]);
                $retryResp = curl_exec($ch);
                $retryCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($retryCode >= 200 && $retryCode < 300) {
                    return [
                        'success'          => true,
                        'http_code'        => $retryCode,
                        'metodo'           => 'RESEND_SANDBOX',
                        'data'             => json_decode($retryResp, true),
                        'sandbox_fallback' => true,
                        'enviado_a'        => $permitido,
                        'omitidos'         => $omitidos,
                        'recipients'       => [$permitido],
                        'total_enviados'   => 1
                    ];
                }
            }

            return [
                'success'    => false,
                'http_code'  => $httpCode,
                'metodo'     => 'RESEND',
                'error'      => $errorMsg,
                'details'    => $responseData
            ];
        }
    }

    /**
     * Construye un correo HTML profesional y modular para el reporte de vencimientos
     * Respeta filtros de contenido (vencidos, rojos, amarillos, balance financiero, sugerencias)
     */
    public static function buildExpirationAlertHtml(
        string $nombreNegocio,
        string $nombreUsuario,
        array $lotesRojos,
        array $lotesAmarillos,
        array $resumen,
        string $moneda = 'Gs.',
        array $filtrosConfig = []
    ): string {
        $fechaHoy = date('d/m/Y H:i');

        // Opciones de configuración con valores por defecto
        $incVencidos   = isset($filtrosConfig['incluir_vencidos'])   ? ((int)$filtrosConfig['incluir_vencidos'] === 1)   : true;
        $incRojos      = isset($filtrosConfig['incluir_rojos'])      ? ((int)$filtrosConfig['incluir_rojos'] === 1)      : true;
        $incAmarillos  = isset($filtrosConfig['incluir_amarillos'])  ? ((int)$filtrosConfig['incluir_amarillos'] === 1)  : true;
        $incFinanciero = isset($filtrosConfig['incluir_financiero']) ? ((int)$filtrosConfig['incluir_financiero'] === 1) : true;
        $incSugerencias= isset($filtrosConfig['incluir_sugerencias'])? ((int)$filtrosConfig['incluir_sugerencias'] === 1): true;

        $remitenteNombre = !empty($filtrosConfig['remitente_nombre']) ? htmlspecialchars($filtrosConfig['remitente_nombre']) : htmlspecialchars($nombreUsuario ?: 'Administrador Propietario');
        $remitenteCargo  = !empty($filtrosConfig['remitente_cargo'])  ? htmlspecialchars($filtrosConfig['remitente_cargo'])  : '👑 Dueño / Administrador General';

        $frecuencia    = $filtrosConfig['frecuencia_reporte'] ?? 'DIARIO';
        $horaReporte   = $filtrosConfig['hora_reporte'] ?? '08:00';
        $emailDestino  = $filtrosConfig['email_destino'] ?? '';
        $emailsCopia   = $filtrosConfig['emails_copia'] ?? '';

        $frecuenciasNombres = [
            'DIARIO'        => 'Diario matutino (' . $horaReporte . ' hs)',
            'DIAS_ALTERNOS' => 'Días por medio (cada 48hs)',
            'SEMANAL'       => 'Semanal (Lunes a las ' . $horaReporte . ' hs)',
            'TIEMPO_REAL'   => 'Tiempo real ante alertas críticas'
        ];
        $textoFrecuencia = $frecuenciasNombres[$frecuencia] ?? 'Programación automática';

        // Separar lotes ya vencidos de lotes en semáforo rojo (0 a 7 días)
        $lotesVencidos = [];
        $lotesRojosVigentes = [];
        foreach ($lotesRojos as $l) {
            if ((int)$l['dias_restantes'] < 0) {
                $lotesVencidos[] = $l;
            } else {
                $lotesRojosVigentes[] = $l;
            }
        }

        $totalVencidos = count($lotesVencidos);
        $totalRojos = count($lotesRojosVigentes);
        $totalAmarillos = count($lotesAmarillos);

        // Capital en riesgo
        $dineroRiesgoNum = $resumen['capital_en_riesgo_total'] ?? 0;
        $dineroRiesgo = number_format($dineroRiesgoNum, 0, ',', '.') . ' ' . $moneda;

        // 1. Bloque de Balance Financiero
        $bloqueFinanciero = '';
        if ($incFinanciero) {
            $bloqueFinanciero = "
                <table class='mobile-full-width' width='100%' cellpadding='0' cellspacing='0' style='background: linear-gradient(145deg, #1e293b 0%, #0f172a 100%); border-radius: 12px; border: 1px solid #334155; margin-bottom: 20px;'>
                    <tr>
                        <td class='mobile-pad-card' style='padding: 20px 22px;'>
                            <table width='100%' cellpadding='0' cellspacing='0'>
                                <tr>
                                    <td class='mobile-stack' style='vertical-align: top;'>
                                        <div style='font-size: 11px; text-transform: uppercase; letter-spacing: 0.8px; color: #94a3b8; font-weight: 700;'>
                                            💰 Capital Total en Riesgo de Merma
                                        </div>
                                        <div class='mobile-amount-text' style='font-size: 28px; font-weight: 800; color: #f87171; margin-top: 4px; letter-spacing: -0.5px;'>
                                            {$dineroRiesgo}
                                        </div>
                                        <div style='font-size: 12px; color: #cbd5e1; margin-top: 6px; line-height: 1.4;'>
                                            Inversión en productos con fecha límite próxima. Evitá tirar mercadería aplicando promociones antes del vencimiento.
                                        </div>
                                    </td>
                                    <td class='mobile-stack mobile-badge-row' align='right' style='vertical-align: top; padding-top: 4px;'>
                                        <div style='background: #374151; padding: 7px 12px; border-radius: 8px; font-size: 11px; color: #e2e8f0; font-weight: 600; display: inline-block; white-space: nowrap;'>
                                            ⏱️ {$textoFrecuencia}
                                        </div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            ";
        }

        // 2. Bloque de Mercadería Ya Vencida (Cards 100% Fluidas Móviles)
        $bloqueVencidos = '';
        if ($incVencidos) {
            if ($totalVencidos > 0) {
                $filasVencidas = '';
                foreach ($lotesVencidos as $l) {
                    $dias = abs((int)$l['dias_restantes']);
                    $riesgo = number_format($l['dinero_en_riesgo'], 0, ',', '.') . ' ' . $moneda;
                    $filasVencidas .= "
                        <div style='background: #0f172a; border: 1px solid #374151; border-radius: 8px; padding: 12px 14px; margin-bottom: 8px;'>
                            <table width='100%' cellpadding='0' cellspacing='0' style='border-collapse: collapse;'>
                                <tr>
                                    <td style='vertical-align: top;'>
                                        <strong style='color: #ffffff; font-size: 14px; display: block; line-height: 1.3;'>{$l['producto_nombre']}</strong>
                                        <span style='color: #94a3b8; font-size: 11px; font-family: monospace;'>Cód: {$l['codigo_barra']}</span>
                                    </td>
                                    <td align='right' style='vertical-align: top; white-space: nowrap; padding-left: 12px;'>
                                        <div style='color: #ef4444; font-size: 15px; font-weight: 800;'>{$riesgo}</div>
                                        <span style='color: #94a3b8; font-size: 10px; text-transform: uppercase;'>Pérdida Neta</span>
                                    </td>
                                </tr>
                                <tr>
                                    <td colspan='2' style='padding-top: 8px; margin-top: 8px; border-top: 1px solid #1e293b;'>
                                        <table width='100%' cellpadding='0' cellspacing='0'>
                                            <tr>
                                                <td style='font-size: 12px; color: #cbd5e1;'>
                                                    📅 <strong>{$l['fecha_vencimiento']}</strong> • <span style='color: #ef4444; font-weight: 800; background: rgba(239,68,68,0.2); padding: 2px 6px; border-radius: 4px; font-size: 11px;'>Venció hace {$dias}d</span>
                                                </td>
                                                <td align='right' style='font-size: 12px; color: #94a3b8;'>
                                                    📦 <strong>{$l['cantidad_actual']} unid.</strong> ({$l['ubicacion']})
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    ";
                }

                $bloqueVencidos = "
                    <div style='margin-bottom: 22px;'>
                        <div style='background: #450a0a; border-left: 4px solid #ef4444; padding: 12px 14px; border-radius: 8px; margin-bottom: 10px;'>
                            <h3 style='margin: 0; font-size: 14px; color: #fca5a5;'>
                                🏴 Mercadería Ya Vencida — Retiro Obligatorio ({$totalVencidos} lotes)
                            </h3>
                            <p style='margin: 3px 0 0; font-size: 11px; color: #fecaca;'>
                                Acción obligatoria: Retirar inmediatamente de góndolas para evitar sanciones y proteger la confianza del cliente.
                            </p>
                        </div>
                        {$filasVencidas}
                    </div>
                ";
            } else {
                $bloqueVencidos = "
                    <div style='margin-bottom: 22px; background: #064e3b; border-left: 4px solid #10b981; padding: 12px 16px; border-radius: 8px;'>
                        <h3 style='margin: 0; font-size: 14px; color: #a7f3d0;'>
                            ✅ Mercadería Ya Vencida (0 productos caducados)
                        </h3>
                        <p style='margin: 3px 0 0; font-size: 11px; color: #d1fae5;'>
                            ¡Excelente estado! No hay mercadería vencida en góndolas ni depósitos.
                        </p>
                    </div>
                ";
            }
        }

        // 3. Bloque de Semáforo Rojo (Vencimiento en ≤ 7 días - Cards 100% Fluidas Móviles)
        $bloqueRojos = '';
        if ($incRojos) {
            if ($totalRojos > 0) {
                $filasRojas = '';
                foreach ($lotesRojosVigentes as $l) {
                    $dias = (int)$l['dias_restantes'];
                    $textoDias = $dias === 0 ? "<span style='color: #ef4444; font-weight: 800; background: rgba(239,68,68,0.2); padding: 2px 6px; border-radius: 4px; font-size: 11px;'>¡VENCE HOY!</span>" : "<span style='color: #f87171; font-weight: 700; font-size: 11px;'>Vence en {$dias}d</span>";
                    $riesgo = number_format($l['dinero_en_riesgo'], 0, ',', '.') . ' ' . $moneda;
                    $filasRojas .= "
                        <div style='background: #0f172a; border: 1px solid #374151; border-radius: 8px; padding: 12px 14px; margin-bottom: 8px;'>
                            <table width='100%' cellpadding='0' cellspacing='0' style='border-collapse: collapse;'>
                                <tr>
                                    <td style='vertical-align: top;'>
                                        <strong style='color: #ffffff; font-size: 14px; display: block; line-height: 1.3;'>{$l['producto_nombre']}</strong>
                                        <span style='color: #94a3b8; font-size: 11px; font-family: monospace;'>Cód: {$l['codigo_barra']}</span>
                                    </td>
                                    <td align='right' style='vertical-align: top; white-space: nowrap; padding-left: 12px;'>
                                        <div style='color: #f87171; font-size: 15px; font-weight: 800;'>{$riesgo}</div>
                                        <span style='color: #94a3b8; font-size: 10px; text-transform: uppercase;'>Costo en Riesgo</span>
                                    </td>
                                </tr>
                                <tr>
                                    <td colspan='2' style='padding-top: 8px; margin-top: 8px; border-top: 1px solid #1e293b;'>
                                        <table width='100%' cellpadding='0' cellspacing='0'>
                                            <tr>
                                                <td style='font-size: 12px; color: #cbd5e1;'>
                                                    📅 <strong>{$l['fecha_vencimiento']}</strong> • {$textoDias}
                                                </td>
                                                <td align='right' style='font-size: 12px; color: #94a3b8;'>
                                                    📦 <strong>{$l['cantidad_actual']} unid.</strong> ({$l['ubicacion']})
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    ";
                }

                $bloqueRojos = "
                    <div style='margin-bottom: 22px;'>
                        <div style='background: #3b0764; border-left: 4px solid #dc2626; padding: 12px 14px; border-radius: 8px; margin-bottom: 10px;'>
                            <h3 style='margin: 0; font-size: 14px; color: #fca5a5;'>
                                🔴 Semáforo Rojo — Vencimiento Crítico (≤ 7 días: {$totalRojos} lotes)
                            </h3>
                            <p style='margin: 3px 0 0; font-size: 11px; color: #e9d5ff;'>
                                Acción recomendada: Activar ofertas relámpago, carteles en caja o promociones 2x1 para recuperar el costo.
                            </p>
                        </div>
                        {$filasRojas}
                    </div>
                ";
            } else {
                $bloqueRojos = "
                    <div style='margin-bottom: 22px; background: #064e3b; border-left: 4px solid #10b981; padding: 12px 16px; border-radius: 8px;'>
                        <h3 style='margin: 0; font-size: 14px; color: #a7f3d0;'>
                            ✅ Semáforo Rojo (0 productos críticos)
                        </h3>
                        <p style='margin: 3px 0 0; font-size: 11px; color: #d1fae5;'>
                            No hay lotes con vencimiento urgente en los próximos 7 días.
                        </p>
                    </div>
                ";
            }
        }

        // 4. Bloque de Semáforo Amarillo (8 a 30 días - Cards 100% Fluidas Móviles)
        $bloqueAmarillos = '';
        if ($incAmarillos) {
            if ($totalAmarillos > 0) {
                $filasAmarillas = '';
                foreach ($lotesAmarillos as $l) {
                    $dias = (int)$l['dias_restantes'];
                    $riesgo = number_format($l['dinero_en_riesgo'], 0, ',', '.') . ' ' . $moneda;
                    $filasAmarillas .= "
                        <div style='background: #0f172a; border: 1px solid #374151; border-radius: 8px; padding: 12px 14px; margin-bottom: 8px;'>
                            <table width='100%' cellpadding='0' cellspacing='0' style='border-collapse: collapse;'>
                                <tr>
                                    <td style='vertical-align: top;'>
                                        <strong style='color: #ffffff; font-size: 14px; display: block; line-height: 1.3;'>{$l['producto_nombre']}</strong>
                                        <span style='color: #94a3b8; font-size: 11px; font-family: monospace;'>Cód: {$l['codigo_barra']}</span>
                                    </td>
                                    <td align='right' style='vertical-align: top; white-space: nowrap; padding-left: 12px;'>
                                        <div style='color: #fcd34d; font-size: 15px; font-weight: 800;'>{$riesgo}</div>
                                        <span style='color: #94a3b8; font-size: 10px; text-transform: uppercase;'>Capital en Stock</span>
                                    </td>
                                </tr>
                                <tr>
                                    <td colspan='2' style='padding-top: 8px; margin-top: 8px; border-top: 1px solid #1e293b;'>
                                        <table width='100%' cellpadding='0' cellspacing='0'>
                                            <tr>
                                                <td style='font-size: 12px; color: #cbd5e1;'>
                                                    📅 <strong>{$l['fecha_vencimiento']}</strong> • <span style='color: #fbbf24; font-weight: 700; font-size: 11px;'>En {$dias} días</span>
                                                </td>
                                                <td align='right' style='font-size: 12px; color: #94a3b8;'>
                                                    📦 <strong>{$l['cantidad_actual']} unid.</strong> ({$l['ubicacion']})
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    ";
                }

                $bloqueAmarillos = "
                    <div style='margin-bottom: 22px;'>
                        <div style='background: #451a03; border-left: 4px solid #f59e0b; padding: 12px 14px; border-radius: 8px; margin-bottom: 10px;'>
                            <h3 style='margin: 0; font-size: 14px; color: #fde68a;'>
                                🟡 Semáforo Amarillo — Alerta Preventiva (8 a 30 días: {$totalAmarillos} lotes)
                            </h3>
                            <p style='margin: 3px 0 0; font-size: 11px; color: #fef3c7;'>
                                Rotación preventiva: Colocar al frente de la góndola o heladera para garantizar venta por rotación FIFO.
                            </p>
                        </div>
                        {$filasAmarillas}
                    </div>
                ";
            } else {
                $bloqueAmarillos = "
                    <div style='margin-bottom: 22px; background: #064e3b; border-left: 4px solid #10b981; padding: 12px 16px; border-radius: 8px;'>
                        <h3 style='margin: 0; font-size: 14px; color: #a7f3d0;'>
                            ✅ Semáforo Amarillo (0 productos en alerta preventiva)
                        </h3>
                        <p style='margin: 3px 0 0; font-size: 11px; color: #d1fae5;'>
                            No hay lotes con vencimiento entre 8 y 30 días.
                        </p>
                    </div>
                ";
            }
        }

        // 5. Bloque de Sugerencias de Acción Comercial
        $bloqueSugerencias = '';
        if ($incSugerencias) {
            $bloqueSugerencias = "
                <div style='background: #1e293b; border: 1px solid #334155; border-radius: 10px; padding: 18px 20px; margin-bottom: 24px;'>
                    <div style='font-size: 13px; font-weight: 800; color: #38bdf8; margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.5px;'>
                        💡 Plan de Acción Comercial Recomendado
                    </div>
                    <ul style='margin: 0; padding-left: 20px; font-size: 13px; color: #cbd5e1; line-height: 1.6;'>
                        <li><strong>Rotación Góndola:</strong> Adelantar mercadería amarilla y ubicar detrás la que vence más tarde.</li>
                        <li><strong>Cartel de Oferta Relámpago:</strong> Agrupar productos en semáforo rojo en una canasta de liquidación de salida con descuento.</li>
                        <li><strong>Ajuste de Compras:</strong> Si un producto vence repetidamente sin venderse, reducir la cantidad solicitada al distribuidor.</li>
                    </ul>
                </div>
            ";
        }

        // Estado vacío si el usuario desmarcó absolutamente todos los bloques
        $bloqueVacio = '';
        if (!$incFinanciero && !$incVencidos && !$incRojos && !$incAmarillos && !$incSugerencias) {
            $bloqueVacio = "
                <div style='background: #1e293b; border: 1px dashed #475569; border-radius: 12px; padding: 30px; text-align: center; margin-bottom: 24px;'>
                    <div style='font-size: 32px; margin-bottom: 8px;'>📋</div>
                    <strong style='color: #f1f5f9; font-size: 15px;'>Sin Bloques de Contenido Seleccionados</strong>
                    <p style='margin: 8px auto 0; font-size: 13px; color: #94a3b8; max-width: 440px; line-height: 1.4;'>
                        En la sección 'Qué Mandar' se desmarcaron todos los bloques de información. Podés activar los filtros deseados (Balance, Vencidos, Rojos, etc.) desde tu panel de configuración.
                    </p>
                </div>
            ";
        }

        // Determinar título contextual según bloques seleccionados
        $tituloReporte = "Semáforo de Vencimientos & Mermas";
        if ($incFinanciero && !$incVencidos && !$incRojos && !$incAmarillos && !$incSugerencias) {
            $tituloReporte = "Balance Financiero & Capital en Riesgo";
        } elseif ($incVencidos && !$incRojos && !$incAmarillos && !$incFinanciero && !$incSugerencias) {
            $tituloReporte = "Mercadería Ya Vencida — Retiro Obligatorio";
        } elseif ($incRojos && !$incVencidos && !$incAmarillos && !$incFinanciero && !$incSugerencias) {
            $tituloReporte = "Semáforo Rojo — Productos Críticos (≤ 7 días)";
        } elseif ($incAmarillos && !$incVencidos && !$incRojos && !$incFinanciero && !$incSugerencias) {
            $tituloReporte = "Semáforo Amarillo — Alerta Preventiva FIFO";
        } elseif ($incSugerencias && !$incVencidos && !$incRojos && !$incAmarillos && !$incFinanciero) {
            $tituloReporte = "Plan de Acción Comercial & Tips Operativos";
        }

        // Destinatarios info en cabecera
        $copiaTexto = !empty($emailsCopia) ? "<br><span style='color: #94a3b8;'>Con copia a: " . htmlspecialchars($emailsCopia) . "</span>" : "";

        return "
        <!DOCTYPE html>
        <html lang='es' xmlns='http://www.w3.org/1999/xhtml'>
        <head>
            <meta charset='utf-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0, maximum-scale=1.0'>
            <meta http-equiv='X-UA-Compatible' content='IE=edge'>
            <meta name='x-apple-disable-message-reformatting'>
            <meta name='format-detection' content='telephone=no,address=no,email=no,date=no,url=no'>
            <meta name='color-scheme' content='dark'>
            <meta name='supported-color-schemes' content='dark'>
            <title>{$tituloReporte} - {$nombreNegocio}</title>
            <style>
                html, body {
                    margin: 0 !important;
                    padding: 0 !important;
                    height: 100% !important;
                    width: 100% !important;
                    -webkit-text-size-adjust: 100%;
                    -ms-text-size-adjust: 100%;
                    background-color: #0b0f19;
                }
                table, td {
                    mso-table-lspace: 0pt !important;
                    mso-table-rspace: 0pt !important;
                }
                table {
                    border-spacing: 0 !important;
                    border-collapse: collapse !important;
                    table-layout: fixed !important;
                    margin: 0 auto !important;
                }
                * {
                    box-sizing: border-box;
                }
                @media only screen and (max-width: 620px) {
                    .email-outer-table {
                        padding: 0 !important;
                        width: 100% !important;
                    }
                    .email-main-card {
                        width: 100% !important;
                        max-width: 100% !important;
                        border-radius: 0 !important;
                        border-left: none !important;
                        border-right: none !important;
                    }
                    .email-header-pad {
                        padding: 20px 14px !important;
                    }
                    .email-body-pad {
                        padding: 16px 12px !important;
                    }
                    .mobile-col {
                        display: block !important;
                        width: 100% !important;
                        text-align: left !important;
                    }
                    .mobile-date-badge {
                        display: inline-block !important;
                        margin-top: 10px !important;
                        text-align: left !important;
                    }
                    .mobile-auth-badge {
                        margin-top: 12px !important;
                        text-align: left !important;
                    }
                    .title-h1 {
                        font-size: 19px !important;
                        line-height: 1.3 !important;
                    }
                    .footer-pad {
                        padding: 18px 14px !important;
                    }
                }
            </style>
        </head>
        <body style='margin: 0; padding: 0; background-color: #0b0f19; font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, Helvetica, Arial, sans-serif; color: #f8fafc;'>
            <table role='presentation' class='email-outer-table' width='100%' cellpadding='0' cellspacing='0' style='background-color: #0b0f19; padding: 20px 10px; width: 100%;'>
                <tr>
                    <td align='center'>
                        <table role='presentation' class='email-main-card' width='100%' cellpadding='0' cellspacing='0' style='background-color: #111827; border: 1px solid #1f2937; border-radius: 16px; overflow: hidden; box-shadow: 0 15px 35px rgba(0,0,0,0.6); max-width: 640px; width: 100%; margin: 0 auto;'>
                            <!-- Cabecera Ejecutiva -->
                            <tr>
                                <td class='email-header-pad' style='background: linear-gradient(135deg, #1e3a8a 0%, #1e1b4b 50%, #311042 100%); padding: 26px 28px; border-bottom: 1px solid #374151;'>
                                    <table role='presentation' width='100%' cellpadding='0' cellspacing='0'>
                                        <tr>
                                            <td class='mobile-col' style='vertical-align: top;'>
                                                <div style='display: inline-block; background: rgba(16, 185, 129, 0.2); border: 1px solid #10b981; color: #34d399; font-size: 11px; font-weight: 800; padding: 3px 10px; border-radius: 20px; text-transform: uppercase; margin-bottom: 8px;'>
                                                    🚦 Expiry • Reporte Filtrado
                                                </div>
                                                <h1 class='title-h1' style='margin: 0; font-size: 22px; font-weight: 800; color: #ffffff; letter-spacing: -0.3px; line-height: 1.25;'>
                                                    {$tituloReporte}
                                                </h1>
                                                <p style='margin: 6px 0 0; font-size: 13px; color: #cbd5e1; line-height: 1.4;'>
                                                    {$nombreNegocio} • Para: <strong style='color: #ffffff;'>{$nombreUsuario}</strong> {$copiaTexto}
                                                </p>
                                                <!-- Badge Destacado de Quién Mandó y su Cargo / Rol -->
                                                <div style='margin-top: 12px; display: inline-block; background: rgba(15, 23, 42, 0.85); border: 1px solid #334155; padding: 6px 12px; border-radius: 8px;'>
                                                    <span style='color: #94a3b8; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px;'>📤 Despachado por:</span>
                                                    <strong style='color: #ffffff; font-size: 12px; margin-left: 4px;'>{$remitenteNombre}</strong>
                                                    <span style='background: rgba(56, 189, 248, 0.2); color: #38bdf8; border: 1px solid rgba(56, 189, 248, 0.4); padding: 1px 7px; border-radius: 10px; font-size: 10px; font-weight: 700; margin-left: 6px;'>
                                                        💼 {$remitenteCargo}
                                                    </span>
                                                </div>
                                            </td>
                                            <td class='mobile-col' align='right' style='vertical-align: top;'>
                                                <div class='mobile-date-badge' style='background: rgba(15, 23, 42, 0.6); padding: 6px 12px; border-radius: 8px; border: 1px solid #334155; font-size: 12px; color: #94a3b8; display: inline-block; white-space: nowrap;'>
                                                    📅 {$fechaHoy}
                                                </div>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>

                            <!-- Cuerpo del Informe -->
                            <tr>
                                <td class='email-body-pad' style='padding: 24px 26px;'>
                                    {$bloqueFinanciero}
                                    {$bloqueVencidos}
                                    {$bloqueRojos}
                                    {$bloqueAmarillos}
                                    {$bloqueSugerencias}
                                    {$bloqueVacio}

                                    <!-- Bloque Institucional: Quién Mandó y Cargo o Rol en el Negocio -->
                                    <table role='presentation' width='100%' cellpadding='0' cellspacing='0' style='margin-top: 16px; background: linear-gradient(145deg, #1e293b 0%, #0f172a 100%); border: 1px solid #334155; border-radius: 12px;'>
                                        <tr>
                                            <td style='padding: 16px 20px;'>
                                                <table role='presentation' width='100%' cellpadding='0' cellspacing='0'>
                                                    <tr>
                                                        <td class='mobile-col' style='vertical-align: middle;'>
                                                            <div style='font-size: 10px; text-transform: uppercase; letter-spacing: 0.8px; color: #94a3b8; font-weight: 700;'>
                                                                👤 Responsable de Emisión del Reporte
                                                            </div>
                                                            <div style='font-size: 15px; font-weight: 800; color: #ffffff; margin-top: 3px;'>
                                                                {$remitenteNombre}
                                                            </div>
                                                            <div style='font-size: 12px; color: #38bdf8; font-weight: 700; margin-top: 3px;'>
                                                                💼 Cargo o Rol en el Negocio: <span style='color: #bae6fd;'>{$remitenteCargo}</span>
                                                            </div>
                                                            <div style='font-size: 11px; color: #94a3b8; margin-top: 5px; line-height: 1.4;'>
                                                                Informe emitido y validado oficialmente desde el sistema <strong>Expiry</strong>.
                                                            </div>
                                                        </td>
                                                        <td class='mobile-col' align='right' style='vertical-align: middle;'>
                                                            <div class='mobile-auth-badge' style='background: rgba(16, 185, 129, 0.15); border: 1px solid #10b981; border-radius: 8px; padding: 7px 12px; text-align: center; display: inline-block;'>
                                                                <span style='font-size: 11px; color: #34d399; font-weight: 800; text-transform: uppercase; display: block;'>
                                                                    ✔ Autenticado
                                                                </span>
                                                                <span style='font-size: 10px; color: #94a3b8;'>
                                                                    Control Oficial
                                                                </span>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                </table>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>

                            <!-- Pie de Página Institucional -->
                            <tr>
                                <td class='footer-pad' style='background-color: #0b0f19; padding: 20px 24px; text-align: center; border-top: 1px solid #1f2937; font-size: 11px; color: #64748b; line-height: 1.5;'>
                                    Informe emitido por el sistema <strong>Expiry</strong> a través de <strong>Resend API</strong>.<br>
                                    Frecuencia de entrega configurada: <strong>{$textoFrecuencia}</strong>.<br>
                                    <span style='color: #475569;'>Para modificar destinatarios, horario o filtros, ingresá a la pestaña 'Qué Mandar' desde tu panel administrativo.</span>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </body>
        </html>
        ";
    }

    /**
     * Envía un correo de prueba simple para verificar la conexión con Resend / Brevo
     */
    public static function sendTestEmail(string $to, string $nombreUsuario, string $nombreNegocio, ?string $cc = null, string $remitenteNombre = '', string $remitenteCargo = ''): array {
        $subject = "✅ Prueba Exitosa de Reporte — Expiry ({$nombreNegocio})";
        $remitenteNombre = !empty($remitenteNombre) ? htmlspecialchars($remitenteNombre) : htmlspecialchars($nombreUsuario ?: 'Administrador Propietario');
        $remitenteCargo  = !empty($remitenteCargo) ? htmlspecialchars($remitenteCargo) : '👑 Dueño / Administrador General';

        $html = "
        <!DOCTYPE html>
        <html lang='es'>
        <head>
            <meta charset='utf-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        </head>
        <body style='margin: 0; padding: 15px 10px; background-color: #0b0f19; font-family: -apple-system, BlinkMacSystemFont, Segoe UI, Roboto, sans-serif;'>
            <div style='background: #111827; color: #f8fafc; padding: 24px; border-radius: 14px; max-width: 520px; width: 100%; margin: 0 auto; border: 1px solid #1f2937; box-shadow: 0 10px 25px rgba(0,0,0,0.5);'>
                <div style='background: rgba(16, 185, 129, 0.15); border: 1px solid #10b981; color: #10b981; display: inline-block; padding: 4px 10px; border-radius: 6px; font-weight: 800; font-size: 11px; text-transform: uppercase;'>
                    ✔ Conexión Verificada
                </div>
                <h2 style='color: #ffffff; margin: 16px 0 8px; font-size: 20px;'>🚦 Conexión de Reportes Operativa</h2>
                <p style='color: #cbd5e1; font-size: 14px; margin-top: 0;'>Hola <strong>{$nombreUsuario}</strong>,</p>
                <p style='color: #94a3b8; font-size: 13px; line-height: 1.5;'>
                    El motor de avisos automáticos de <strong>{$nombreNegocio}</strong> está configurado y listo para entregar los reportes según la frecuencia y los módulos seleccionados.
                </p>
                <div style='background: #1e293b; padding: 14px 16px; border-radius: 8px; margin: 18px 0; border-left: 4px solid #10b981; font-size: 13px;'>
                    <div style='color: #ffffff; font-weight: bold;'>Destinatario Principal:</div>
                    <div style='color: #38bdf8;'>{$to}</div>
                    " . (!empty($cc) ? "<div style='color: #ffffff; font-weight: bold; margin-top: 8px;'>Destinatarios en Copia (CC):</div><div style='color: #94a3b8;'>{$cc}</div>" : "") . "
                    <div style='margin-top: 12px; padding-top: 10px; border-top: 1px solid #334155;'>
                        <div style='color: #94a3b8; font-size: 11px; text-transform: uppercase;'>👤 Despachado por:</div>
                        <div style='color: #ffffff; font-weight: bold; font-size: 14px; margin-top: 2px;'>{$remitenteNombre}</div>
                        <div style='color: #38bdf8; font-size: 12px; font-weight: 700; margin-top: 2px;'>💼 Cargo o Rol en el Negocio: {$remitenteCargo}</div>
                    </div>
                </div>
                <p style='font-size: 12px; color: #64748b; margin-bottom: 0;'>
                    Si recibiste este correo, tu configuración y credenciales son 100% funcionales.
                </p>
            </div>
        </body>
        </html>
        ";

        return self::sendEmail($to, $subject, $html, $cc);
    }
}
