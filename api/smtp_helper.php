<?php
/**
 * Servicio de Envío de Correo SMTP Nativo (Zero Dependencies)
 * Sistema de Vencimientos - Expiry
 * Compatible con Gmail, Outlook, Brevo y servidores SMTP estándar
 * Soporta PHP 7.2+
 */

class SmtpMailer {
    /**
     * Envía un correo electrónico mediante SMTP con soporte para múltiples destinatarios y copias (CC)
     *
     * @param string $host
     * @param int $port
     * @param string $security 'ssl' o 'tls'
     * @param string $user
     * @param string $pass
     * @param string $fromEmail
     * @param string $fromName
     * @param string|array $to
     * @param string $subject
     * @param string $htmlBody
     * @param string|array $cc
     * @return array
     */
    public static function send($host, $port, $security, $user, $pass, $fromEmail, $fromName, $to, $subject, $htmlBody, $cc = []) {
        $timeout = 15;
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ]);

        $prefix = '';
        if (strtolower($security) === 'ssl' || (int)$port === 465) {
            $prefix = 'ssl://';
        }

        $socket = @stream_socket_client("{$prefix}{$host}:{$port}", $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $context);
        if (!$socket) {
            return [
                'success' => false,
                'error'   => "No se pudo conectar al servidor SMTP ({$host}:{$port}): {$errstr} ({$errno})"
            ];
        }

        stream_set_timeout($socket, $timeout);

        $readResponse = function() use ($socket) {
            $data = '';
            while ($line = fgets($socket, 512)) {
                $data .= $line;
                if (isset($line[3]) && $line[3] === ' ') {
                    break;
                }
            }
            return $data;
        };

        $sendCommand = function($cmd, $expectedCode = 250) use ($socket, $readResponse) {
            fputs($socket, $cmd . "\r\n");
            $response = $readResponse();
            $code = (int)substr($response, 0, 3);
            if ($expectedCode && $code !== $expectedCode) {
                return ['success' => false, 'code' => $code, 'response' => trim($response)];
            }
            return ['success' => true, 'code' => $code, 'response' => trim($response)];
        };

        // Banner inicial
        $banner = $readResponse();
        if ((int)substr($banner, 0, 3) !== 220) {
            fclose($socket);
            return ['success' => false, 'error' => "Respuesta SMTP inesperada: {$banner}"];
        }

        // EHLO
        $ehlo = $sendCommand("EHLO " . gethostname());
        if (!$ehlo['success']) {
            fclose($socket);
            return ['success' => false, 'error' => "Fallo EHLO: {$ehlo['response']}"];
        }

        // STARTTLS si es requerido (puerto 587)
        if (strtolower($security) === 'tls' || (int)$port === 587) {
            $starttls = $sendCommand("STARTTLS", 220);
            if (!$starttls['success']) {
                fclose($socket);
                return ['success' => false, 'error' => "Fallo STARTTLS: {$starttls['response']}"];
            }
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT)) {
                fclose($socket);
                return ['success' => false, 'error' => "No se pudo negociar cifrado TLS seguro con {$host}."];
            }
            // Re-enviar EHLO tras establecer TLS
            $sendCommand("EHLO " . gethostname());
        }

        // AUTH LOGIN
        $auth = $sendCommand("AUTH LOGIN", 334);
        if (!$auth['success']) {
            fclose($socket);
            return ['success' => false, 'error' => "Fallo al iniciar autenticación SMTP: {$auth['response']}"];
        }

        $userRes = $sendCommand(base64_encode($user), 334);
        if (!$userRes['success']) {
            fclose($socket);
            return ['success' => false, 'error' => "Usuario SMTP rechazado por {$host}: {$userRes['response']}"];
        }

        $passRes = $sendCommand(base64_encode($pass), 235);
        if (!$passRes['success']) {
            fclose($socket);
            return [
                'success' => false,
                'error'   => "Contraseña o clave de aplicación SMTP rechazada: {$passRes['response']}. Si usás Gmail, recordá generar una 'Contraseña de aplicación' en tu cuenta Google."
            ];
        }

        // MAIL FROM
        $mailFrom = $sendCommand("MAIL FROM:<{$fromEmail}>", 250);
        if (!$mailFrom['success']) {
            fclose($socket);
            return ['success' => false, 'error' => "Remitente rechazado: {$mailFrom['response']}"];
        }

        // Recopilar todos los destinatarios
        $toArray = is_array($to) ? $to : array_map('trim', explode(',', $to));
        $allRecipients = $toArray;
        if (!empty($cc)) {
            $ccArray = is_array($cc) ? $cc : array_map('trim', explode(',', $cc));
            $allRecipients = array_merge($allRecipients, $ccArray);
        }
        $allRecipients = array_values(array_unique(array_filter($allRecipients, function($e) {
            return filter_var($e, FILTER_VALIDATE_EMAIL);
        })));

        if (empty($allRecipients)) {
            fclose($socket);
            return ['success' => false, 'error' => "No se encontraron destinatarios válidos."];
        }

        $enviados = [];
        $fallidos = [];
        foreach ($allRecipients as $rcpt) {
            $rcptRes = $sendCommand("RCPT TO:<{$rcpt}>", 250);
            if ($rcptRes['success']) {
                $enviados[] = $rcpt;
            } else {
                $fallidos[] = "{$rcpt} ({$rcptRes['response']})";
            }
        }

        if (empty($enviados)) {
            fclose($socket);
            return ['success' => false, 'error' => "Todos los destinatarios fueron rechazados: " . implode(', ', $fallidos)];
        }

        // DATA
        $dataCmd = $sendCommand("DATA", 354);
        if (!$dataCmd['success']) {
            fclose($socket);
            return ['success' => false, 'error' => "Comando DATA rechazado: {$dataCmd['response']}"];
        }

        $toHeader = is_array($to) ? implode(', ', $to) : $to;
        $ccHeader = !empty($cc) ? (is_array($cc) ? implode(', ', $cc) : $cc) : '';

        $encodedSubject = "=?UTF-8?B?" . base64_encode($subject) . "?=";
        $encodedFromName = "=?UTF-8?B?" . base64_encode($fromName) . "?=";

        $headers = [
            "Date: " . date('r'),
            "From: {$encodedFromName} <{$fromEmail}>",
            "Reply-To: <{$fromEmail}>",
            "To: {$toHeader}",
            "Subject: {$encodedSubject}",
            "MIME-Version: 1.0",
            "Content-Type: text/html; charset=UTF-8",
            "Content-Transfer-Encoding: base64",
            "X-Mailer: Expiry-Mailer/2.0"
        ];
        if (!empty($ccHeader)) {
            $headers[] = "Cc: {$ccHeader}";
        }

        $message = implode("\r\n", $headers) . "\r\n\r\n" . chunk_split(base64_encode($htmlBody)) . "\r\n.\r\n";
        fputs($socket, $message);

        $sendRes = $readResponse();
        $sendCode = (int)substr($sendRes, 0, 3);

        $sendCommand("QUIT", null);
        fclose($socket);

        if ($sendCode === 250) {
            return [
                'success'        => true,
                'recipients'     => $enviados,
                'fallidos'       => $fallidos,
                'total_enviados' => count($enviados),
                'metodo'         => 'SMTP'
            ];
        } else {
            return [
                'success' => false,
                'error'   => "Error al finalizar envío SMTP: " . trim($sendRes)
            ];
        }
    }

    /**
     * Verifica la conectividad y autenticación SMTP sin enviar correo
     */
    public static function testConnection($host, $port, $security, $user, $pass) {
        $timeout = 10;
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ]);

        $prefix = (strtolower($security) === 'ssl' || (int)$port === 465) ? 'ssl://' : '';
        $socket = @stream_socket_client("{$prefix}{$host}:{$port}", $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $context);
        if (!$socket) {
            return ['success' => false, 'error' => "No se pudo conectar a {$host}:{$port} ($errstr)"];
        }

        stream_set_timeout($socket, $timeout);

        $read = function() use ($socket) {
            $d = '';
            while ($l = fgets($socket, 512)) {
                $d .= $l;
                if (isset($l[3]) && $l[3] === ' ') break;
            }
            return $d;
        };

        $banner = $read();
        fputs($socket, "EHLO " . gethostname() . "\r\n");
        $read();

        if (strtolower($security) === 'tls' || (int)$port === 587) {
            fputs($socket, "STARTTLS\r\n");
            $read();
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT)) {
                fclose($socket);
                return ['success' => false, 'error' => "Error negociando TLS con {$host}."];
            }
            fputs($socket, "EHLO " . gethostname() . "\r\n");
            $read();
        }

        fputs($socket, "AUTH LOGIN\r\n");
        $read();
        fputs($socket, base64_encode($user) . "\r\n");
        $read();
        fputs($socket, base64_encode($pass) . "\r\n");
        $authRes = $read();

        fputs($socket, "QUIT\r\n");
        fclose($socket);

        if ((int)substr($authRes, 0, 3) === 235) {
            return ['success' => true, 'message' => "¡Conexión y autenticación SMTP exitosas con {$host}!"];
        } else {
            return ['success' => false, 'error' => "Autenticación fallida: " . trim($authRes)];
        }
    }
}
