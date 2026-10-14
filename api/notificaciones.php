<?php
/**
 * Endpoint de Notificaciones por Correo Electrónico (Resend API)
 * Sistema de Vencimientos - Expiry
 */

require_once __DIR__ . '/security_helper.php';
require_once __DIR__ . '/resend_helper.php';

$user = requireAuth();
$pdo = Database::getConnection();

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'get_config':
        $stmtCfg = $pdo->query("
            SELECT nombre_negocio, moneda_simbolo, resend_api_key, email_remitente, notificar_email, 
                   email_notificaciones, frecuencia_reporte, hora_reporte, emails_copia,
                   incluir_vencidos, incluir_rojos, incluir_amarillos, incluir_financiero, incluir_sugerencias,
                   email_metodo, smtp_host, smtp_port, smtp_seguridad, smtp_user, smtp_pass
            FROM configuracion 
            LIMIT 1
        ");
        $cfg = $stmtCfg->fetch();

        // Obtener email del usuario en sesión
        $stmtUser = $pdo->prepare("SELECT email FROM usuarios WHERE id = ? LIMIT 1");
        $stmtUser->execute([$user['id']]);
        $uData = $stmtUser->fetch();
        $userEmail = !empty($uData['email']) ? $uData['email'] : ($user['email'] ?? '');

        // Contar alertas enviadas
        $stmtCount = $pdo->query("SELECT COUNT(*) AS total FROM alertas_log");
        $count = $stmtCount->fetch();

        $stmtContactos = $pdo->query("SELECT id, nombre, email, cargo, tipo_destinatario, activo FROM contactos_reporte ORDER BY tipo_destinatario DESC, id ASC");
        $rawKey = (string)($cfg['resend_api_key'] ?? '');
        $keyMasked = !empty($rawKey) ? (substr($rawKey, 0, min(8, strlen($rawKey))) . '••••••••••••••••••••' . (strlen($rawKey) > 4 ? substr($rawKey, -4) : '')) : '';

        jsonResponse([
            'status' => 'success',
            'config' => [
                'resend_configured'   => !empty($rawKey),
                'api_key_masked'      => $keyMasked,
                'email_remitente'     => $cfg['email_remitente'] ?: 'Expiry Alertas <onboarding@resend.dev>',
                'email_metodo'        => strtoupper($cfg['email_metodo'] ?? 'RESEND'),
                'smtp_host'           => $cfg['smtp_host'] ?: 'smtp.gmail.com',
                'smtp_port'           => (int)($cfg['smtp_port'] ?: 465),
                'smtp_seguridad'      => strtolower($cfg['smtp_seguridad'] ?: 'ssl'),
                'smtp_user'           => !empty($cfg['smtp_user']) ? $cfg['smtp_user'] : $userEmail,
                'smtp_pass_configured'=> !empty($cfg['smtp_pass']),
                'notificar_email'     => (int)($cfg['notificar_email'] ?? 1),
                'email_notificaciones'=> !empty($cfg['email_notificaciones']) ? $cfg['email_notificaciones'] : $userEmail,
                'user_email'          => $userEmail,
                'frecuencia_reporte'  => $cfg['frecuencia_reporte'] ?? 'DIARIO',
                'hora_reporte'        => $cfg['hora_reporte'] ?? '08:00',
                'emails_copia'        => $cfg['emails_copia'] ?? '',
                'incluir_vencidos'    => (int)($cfg['incluir_vencidos'] ?? 1),
                'incluir_rojos'       => (int)($cfg['incluir_rojos'] ?? 1),
                'incluir_amarillos'   => (int)($cfg['incluir_amarillos'] ?? 1),
                'incluir_financiero'  => (int)($cfg['incluir_financiero'] ?? 1),
                'incluir_sugerencias' => (int)($cfg['incluir_sugerencias'] ?? 1),
                'total_alertas'       => (int)($count['total'] ?? 0)
            ],
            'contactos' => $contactos,
            'csrf_token' => getCsrfToken()
        ]);
        break;

    case 'add_contacto':
        $csrf = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!validateCsrfToken($csrf)) {
            jsonResponse(['status' => 'error', 'message' => 'Token CSRF inválido.'], 403);
        }

        $nombre = trim($_POST['nombre'] ?? '');
        $email  = trim($_POST['email'] ?? '');
        $cargo  = trim($_POST['cargo'] ?? '');
        $tipo   = strtoupper(trim($_POST['tipo_destinatario'] ?? 'CC'));
        if (!in_array($tipo, ['PRINCIPAL', 'CC'])) {
            $tipo = 'CC';
        }

        // Validación estricta de formulario obligatorio
        if (empty($nombre) || mb_strlen($nombre) < 2) {
            jsonResponse(['status' => 'error', 'message' => 'El Nombre completo del contacto es obligatorio (mínimo 2 letras).'], 400);
        }

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            jsonResponse(['status' => 'error', 'message' => 'El Correo Electrónico es obligatorio y debe tener un formato válido (ej: usuario@ejemplo.com).'], 400);
        }

        if (empty($cargo)) {
            jsonResponse(['status' => 'error', 'message' => 'El Cargo / Rol en el negocio es obligatorio (ej: Repositor, Encargado, Dueño).'], 400);
        }

        try {
            $pdo->beginTransaction();

            if ($tipo === 'PRINCIPAL') {
                // Degradar cualquier otro principal a CC para mantener unicidad de buzon maestro
                $pdo->exec("UPDATE contactos_reporte SET tipo_destinatario = 'CC' WHERE tipo_destinatario = 'PRINCIPAL'");
                $pdo->prepare("UPDATE configuracion SET email_notificaciones = ?, updated_at = CURRENT_TIMESTAMP WHERE id = 1")->execute([$email]);
            }

            // Insertar o actualizar con compatibilidad universal PostgreSQL y MySQL
            $stmtCheck = $pdo->prepare("SELECT id FROM contactos_reporte WHERE email = ? LIMIT 1");
            $stmtCheck->execute([$email]);
            $existingId = $stmtCheck->fetchColumn();

            if ($existingId) {
                $stmtUpd = $pdo->prepare("
                    UPDATE contactos_reporte 
                    SET nombre = ?, cargo = ?, tipo_destinatario = ?, activo = 1 
                    WHERE id = ?
                ");
                $stmtUpd->execute([$nombre, $cargo, $tipo, $existingId]);
            } else {
                $stmtIns = $pdo->prepare("
                    INSERT INTO contactos_reporte (nombre, email, cargo, tipo_destinatario, activo)
                    VALUES (?, ?, ?, ?, 1)
                ");
                $stmtIns->execute([$nombre, $email, $cargo, $tipo]);
            }

            // Sincronizar campo emails_copia en configuracion
            $stmtCc = $pdo->query("SELECT email FROM contactos_reporte WHERE tipo_destinatario = 'CC' AND activo = 1");
            $ccList = $stmtCc->fetchAll(PDO::FETCH_COLUMN);
            $ccStr = implode(', ', $ccList);
            $pdo->prepare("UPDATE configuracion SET emails_copia = ? WHERE id = 1")->execute([$ccStr]);

            $pdo->commit();

            // Devolver lista actualizada
            $stmtAll = $pdo->query("SELECT id, nombre, email, cargo, tipo_destinatario, activo FROM contactos_reporte ORDER BY tipo_destinatario DESC, id ASC");
            $allContactos = $stmtAll->fetchAll();

            jsonResponse([
                'status'    => 'success',
                'message'   => "¡Contacto \"{$nombre}\" ({$cargo}) agregado con éxito a la lista de reportes!",
                'contactos' => $allContactos,
                'emails_copia' => $ccStr,
                'csrf_token' => getCsrfToken()
            ]);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            jsonResponse(['status' => 'error', 'message' => 'Error al guardar contacto: ' . $e->getMessage()], 500);
        }
        break;

    case 'delete_contacto':
        $csrf = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!validateCsrfToken($csrf)) {
            jsonResponse(['status' => 'error', 'message' => 'Token CSRF inválido.'], 403);
        }

        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            jsonResponse(['status' => 'error', 'message' => 'ID de contacto inválido.'], 400);
        }

        $stmtCheck = $pdo->prepare("SELECT nombre, email, tipo_destinatario FROM contactos_reporte WHERE id = ?");
        $stmtCheck->execute([$id]);
        $contacto = $stmtCheck->fetch();

        if (!$contacto) {
            jsonResponse(['status' => 'error', 'message' => 'El contacto no existe o ya fue eliminado.'], 404);
        }

        if ($contacto['tipo_destinatario'] === 'PRINCIPAL') {
            $stmtCount = $pdo->query("SELECT COUNT(*) FROM contactos_reporte WHERE activo = 1");
            if ((int)$stmtCount->fetchColumn() <= 1) {
                jsonResponse(['status' => 'error', 'message' => 'No podés eliminar el único contacto principal del sistema.'], 400);
            }
        }

        try {
            $pdo->beginTransaction();

            $stmtDel = $pdo->prepare("DELETE FROM contactos_reporte WHERE id = ?");
            $stmtDel->execute([$id]);

            // Si se eliminó el principal, asignar el primero restante como principal
            $stmtHasPrincipal = $pdo->query("SELECT COUNT(*) FROM contactos_reporte WHERE tipo_destinatario = 'PRINCIPAL'");
            if ((int)$stmtHasPrincipal->fetchColumn() === 0) {
                $stmtFirst = $pdo->query("SELECT id, email FROM contactos_reporte ORDER BY id ASC LIMIT 1");
                $firstRow = $stmtFirst->fetch();
                if ($firstRow) {
                    $pdo->prepare("UPDATE contactos_reporte SET tipo_destinatario = 'PRINCIPAL' WHERE id = ?")->execute([$firstRow['id']]);
                    $pdo->prepare("UPDATE configuracion SET email_notificaciones = ? WHERE id = 1")->execute([$firstRow['email']]);
                }
            }

            // Sincronizar emails_copia
            $stmtCc = $pdo->query("SELECT email FROM contactos_reporte WHERE tipo_destinatario = 'CC' AND activo = 1");
            $ccList = $stmtCc->fetchAll(PDO::FETCH_COLUMN);
            $ccStr = implode(', ', $ccList);
            $pdo->prepare("UPDATE configuracion SET emails_copia = ? WHERE id = 1")->execute([$ccStr]);

            $pdo->commit();

            $stmtAll = $pdo->query("SELECT id, nombre, email, cargo, tipo_destinatario, activo FROM contactos_reporte ORDER BY tipo_destinatario DESC, id ASC");
            $allContactos = $stmtAll->fetchAll();

            jsonResponse([
                'status'    => 'success',
                'message'   => "Contacto \"{$contacto['nombre']}\" eliminado de la lista.",
                'contactos' => $allContactos,
                'emails_copia' => $ccStr,
                'csrf_token' => getCsrfToken()
            ]);
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            jsonResponse(['status' => 'error', 'message' => 'Error al eliminar contacto: ' . $e->getMessage()], 500);
        }
        break;

    case 'toggle_contacto':
        $csrf = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!validateCsrfToken($csrf)) {
            jsonResponse(['status' => 'error', 'message' => 'Token CSRF inválido.'], 403);
        }

        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            jsonResponse(['status' => 'error', 'message' => 'ID inválido.'], 400);
        }

        try {
            $pdo->beginTransaction();

            $stmtTgl = $pdo->prepare("UPDATE contactos_reporte SET activo = CASE WHEN activo = 1 THEN 0 ELSE 1 END WHERE id = ?");
            $stmtTgl->execute([$id]);

            // Sincronizar emails_copia
            $stmtCc = $pdo->query("SELECT email FROM contactos_reporte WHERE tipo_destinatario = 'CC' AND activo = 1");
            $ccList = $stmtCc->fetchAll(PDO::FETCH_COLUMN);
            $ccStr = implode(', ', $ccList);
            $pdo->prepare("UPDATE configuracion SET emails_copia = ? WHERE id = 1")->execute([$ccStr]);

            $pdo->commit();

            $stmtAll = $pdo->query("SELECT id, nombre, email, cargo, tipo_destinatario, activo FROM contactos_reporte ORDER BY tipo_destinatario DESC, id ASC");
            $allContactos = $stmtAll->fetchAll();

            jsonResponse([
                'status'    => 'success',
                'contactos' => $allContactos,
                'emails_copia' => $ccStr,
                'csrf_token' => getCsrfToken()
            ]);
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            jsonResponse(['status' => 'error', 'message' => 'Error al cambiar estado del contacto: ' . $e->getMessage()], 500);
        }
        break;

    case 'save_config':
        $csrf = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!validateCsrfToken($csrf)) {
            jsonResponse(['status' => 'error', 'message' => 'Token CSRF inválido.'], 403);
        }

        $emailDestino       = trim($_POST['email_destino'] ?? '');
        $notificarEmail     = isset($_POST['notificar_email']) ? 1 : 0;
        $frecuenciaReporte  = trim($_POST['frecuencia_reporte'] ?? 'DIARIO');
        $horaReporte        = trim($_POST['hora_reporte'] ?? '08:00');
        $emailsCopiaRaw     = trim($_POST['emails_copia'] ?? '');
        $incluirVencidos    = isset($_POST['incluir_vencidos']) ? 1 : 0;
        $incluirRojos       = isset($_POST['incluir_rojos']) ? 1 : 0;
        $incluirAmarillos   = isset($_POST['incluir_amarillos']) ? 1 : 0;
        $incluirFinanciero  = isset($_POST['incluir_financiero']) ? 1 : 0;
        $incluirSugerencias = isset($_POST['incluir_sugerencias']) ? 1 : 0;
        $apiKey             = trim($_POST['resend_api_key'] ?? '');

        // Nuevos campos de Motor SMTP
        $emailMetodo        = strtoupper(trim($_POST['email_metodo'] ?? 'RESEND'));
        if (!in_array($emailMetodo, ['RESEND', 'SMTP'])) {
            $emailMetodo = 'RESEND';
        }
        $smtpHost           = trim($_POST['smtp_host'] ?? 'smtp.gmail.com');
        $smtpPort           = (int)($_POST['smtp_port'] ?? 465);
        $smtpSeguridad      = strtolower(trim($_POST['smtp_seguridad'] ?? 'ssl'));
        $smtpUser           = trim($_POST['smtp_user'] ?? '');
        $smtpPass           = trim($_POST['smtp_pass'] ?? '');

        // Validar frecuencia
        $validFrecuencias = ['DIARIO', 'DIAS_ALTERNOS', 'SEMANAL', 'TIEMPO_REAL'];
        if (!in_array($frecuenciaReporte, $validFrecuencias)) {
            $frecuenciaReporte = 'DIARIO';
        }

        // Validar hora
        if (!preg_match('/^([01][0-9]|2[0-3]):[0-5][0-9]$/', $horaReporte)) {
            $horaReporte = '08:00';
        }

        // Validar email principal
        if (!empty($emailDestino) && !filter_var($emailDestino, FILTER_VALIDATE_EMAIL)) {
            jsonResponse(['status' => 'error', 'message' => 'El formato del correo principal no es válido.'], 400);
        }

        // Validar y sanitizar lista de correos en copia (CC)
        $emailsCopiaArray = [];
        if (!empty($emailsCopiaRaw)) {
            $rawList = explode(',', $emailsCopiaRaw);
            foreach ($rawList as $ec) {
                $trimmed = trim($ec);
                if (!empty($trimmed)) {
                    if (!filter_var($trimmed, FILTER_VALIDATE_EMAIL)) {
                        jsonResponse(['status' => 'error', 'message' => "El correo en copia '{$trimmed}' no tiene un formato válido."], 400);
                    }
                    $emailsCopiaArray[] = $trimmed;
                }
            }
        }
        $emailsCopiaFinal = implode(', ', array_unique($emailsCopiaArray));

        try {
            $pdo->beginTransaction();

            $sql = "
                UPDATE configuracion 
                SET notificar_email = :notificar_email, 
                    email_notificaciones = :email_notificaciones,
                    frecuencia_reporte = :frecuencia_reporte, 
                    hora_reporte = :hora_reporte, 
                    emails_copia = :emails_copia,
                    incluir_vencidos = :incluir_vencidos, 
                    incluir_rojos = :incluir_rojos, 
                    incluir_amarillos = :incluir_amarillos,
                    incluir_financiero = :incluir_financiero, 
                    incluir_sugerencias = :incluir_sugerencias,
                    email_metodo = :email_metodo,
                    smtp_host = :smtp_host,
                    smtp_port = :smtp_port,
                    smtp_seguridad = :smtp_seguridad,
                    smtp_user = :smtp_user,
                    updated_at = NOW()
            ";

            $params = [
                ':notificar_email'     => $notificarEmail,
                ':email_notificaciones'=> $emailDestino,
                ':frecuencia_reporte'  => $frecuenciaReporte,
                ':hora_reporte'        => $horaReporte,
                ':emails_copia'        => $emailsCopiaFinal,
                ':incluir_vencidos'    => $incluirVencidos,
                ':incluir_rojos'       => $incluirRojos,
                ':incluir_amarillos'   => $incluirAmarillos,
                ':incluir_financiero'  => $incluirFinanciero,
                ':incluir_sugerencias' => $incluirSugerencias,
                ':email_metodo'        => $emailMetodo,
                ':smtp_host'           => $smtpHost,
                ':smtp_port'           => $smtpPort,
                ':smtp_seguridad'      => $smtpSeguridad,
                ':smtp_user'           => $smtpUser
            ];

            if (!empty($apiKey)) {
                $sql .= ", resend_api_key = :resend_api_key";
                $params[':resend_api_key'] = $apiKey;
            }

            if (!empty($smtpPass)) {
                $sql .= ", smtp_pass = :smtp_pass";
                $params[':smtp_pass'] = $smtpPass;
            }

            $sql .= " WHERE id = 1";

            $stmtCfg = $pdo->prepare($sql);
            $stmtCfg->execute($params);

            // Actualizar también el email del usuario logeado si se indicó
            if (!empty($emailDestino)) {
                $stmtUser = $pdo->prepare("UPDATE usuarios SET email = ? WHERE id = ?");
                $stmtUser->execute([$emailDestino, $user['id']]);
                $_SESSION['user']['email'] = $emailDestino;
            }

            $pdo->commit();

            jsonResponse([
                'status'  => 'success',
                'message' => '¡Configuración de reporte, distribución y motor de envío guardada con éxito!',
                'csrf_token' => getCsrfToken()
            ]);
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            jsonResponse(['status' => 'error', 'message' => 'Error al guardar configuración: ' . $e->getMessage()], 500);
        }
        break;

    case 'test_smtp':
        $csrf = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!validateCsrfToken($csrf)) {
            jsonResponse(['status' => 'error', 'message' => 'Token CSRF inválido.'], 403);
        }

        $host = trim($_POST['smtp_host'] ?? 'smtp.gmail.com');
        $port = (int)($_POST['smtp_port'] ?? 465);
        $sec  = strtolower(trim($_POST['smtp_seguridad'] ?? 'ssl'));
        $userSmtp = trim($_POST['smtp_user'] ?? '');
        $pass = trim($_POST['smtp_pass'] ?? '');

        if (empty($pass)) {
            $stmtP = $pdo->query("SELECT smtp_pass FROM configuracion LIMIT 1");
            $pass = $stmtP->fetchColumn() ?: '';
        }

        if (empty($userSmtp) || empty($pass)) {
            jsonResponse(['status' => 'error', 'message' => 'Por favor completá el usuario/correo Gmail y la contraseña de aplicación para probar la conexión.'], 400);
        }

        require_once __DIR__ . '/smtp_helper.php';
        $testRes = SmtpMailer::testConnection($host, $port, $sec, $userSmtp, $pass);
        if ($testRes['success']) {
            jsonResponse([
                'status'  => 'success',
                'message' => "¡Conexión y autenticación exitosas con {$host}:{$port} ({$sec})! El servidor SMTP está listo para enviar a todo el equipo.",
                'csrf_token' => getCsrfToken()
            ]);
        } else {
            jsonResponse([
                'status'  => 'error',
                'message' => 'Fallo al autenticar con el servidor SMTP: ' . $testRes['error'],
                'csrf_token' => getCsrfToken()
            ], 400);
        }
        break;

    case 'send_test':
        $csrf = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!validateCsrfToken($csrf)) {
            jsonResponse(['status' => 'error', 'message' => 'Token CSRF inválido.'], 403);
        }

        $stmtCfg = $pdo->query("SELECT nombre_negocio, email_notificaciones, emails_copia, email_metodo FROM configuracion LIMIT 1");
        $cfg = $stmtCfg->fetch();
        $nombreNegocio = $cfg['nombre_negocio'] ?? 'Minimarket';

        $emailDestino = trim($_POST['email'] ?? '');
        if (empty($emailDestino)) {
            $stmtUser = $pdo->prepare("SELECT email FROM usuarios WHERE id = ? LIMIT 1");
            $stmtUser->execute([$user['id']]);
            $u = $stmtUser->fetch();
            $emailDestino = !empty($u['email']) ? $u['email'] : ($user['email'] ?? '');
        }

        if (empty($emailDestino) || !filter_var($emailDestino, FILTER_VALIDATE_EMAIL)) {
            jsonResponse(['status' => 'error', 'message' => 'No hay un correo electrónico válido configurado para enviar la prueba.'], 400);
        }

        $emailsCopia = isset($_POST['emails_copia']) ? trim($_POST['emails_copia']) : ($cfg['emails_copia'] ?? '');

        $resultado = ResendEmailService::sendTestEmail($emailDestino, $user['nombre'], $nombreNegocio, $emailsCopia);

        if ($resultado['success']) {
            $metodo = $resultado['metodo'] ?? 'RESEND';
            $recipients = $resultado['recipients'] ?? [$emailDestino];
            $recipientsList = implode(', ', $recipients);

            if ($metodo === 'SMTP' || $metodo === 'SMTP_AUTORESCUE') {
                $msg = "✅ ¡Correo de prueba enviado con éxito a TODOS los destinatarios seleccionados (" . count($recipients) . "): {$recipientsList} vía Gmail SMTP!";
            } elseif (!empty($resultado['sandbox_fallback'])) {
                $msg = "⚠️ Entregado únicamente a {$resultado['enviado_a']}. 🛑 Resend en modo prueba gratuito (onboarding@resend.dev) bloqueó el envío a: " . implode(', ', $resultado['omitidos']) . ". Para enviar a TODOS los seleccionados sin restricción, activá el motor 'Gmail / SMTP' en la pestaña Motor de Envío.";
            } else {
                $msg = "✅ ¡Correo de prueba enviado con éxito a {$recipientsList} vía Resend API!";
            }

            jsonResponse([
                'status'     => 'success',
                'message'    => $msg,
                'data'       => $resultado['data'] ?? null,
                'metodo'     => $metodo,
                'recipients' => $recipients,
                'csrf_token' => getCsrfToken()
            ]);
        } else {
            jsonResponse([
                'status'     => 'error',
                'message'    => 'Error al enviar prueba: ' . $resultado['error'],
                'details'    => $resultado['details'] ?? null,
                'csrf_token' => getCsrfToken()
            ], 400);
        }
        break;

    case 'send_report':
        $csrf = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!validateCsrfToken($csrf)) {
            jsonResponse(['status' => 'error', 'message' => 'Token CSRF inválido.'], 403);
        }

        // Obtener configuración completa
        $stmtCfg = $pdo->query("
            SELECT nombre_negocio, moneda_simbolo, email_notificaciones, 
                   frecuencia_reporte, hora_reporte, emails_copia,
                   incluir_vencidos, incluir_rojos, incluir_amarillos, incluir_financiero, incluir_sugerencias,
                   email_metodo
            FROM configuracion 
            LIMIT 1
        ");
        $cfg = $stmtCfg->fetch();
        $nombreNegocio = $cfg['nombre_negocio'] ?? 'Minimarket';
        $moneda = $cfg['moneda_simbolo'] ?? 'Gs.';

        $emailDestino = !empty($_POST['email']) ? trim($_POST['email']) : (!empty($cfg['email_notificaciones']) ? $cfg['email_notificaciones'] : ($user['email'] ?? ''));

        if (empty($emailDestino) || !filter_var($emailDestino, FILTER_VALIDATE_EMAIL)) {
            jsonResponse(['status' => 'error', 'message' => 'Por favor configure un correo electrónico de destino válido.'], 400);
        }

        $emailsCopia = !empty($_POST['emails_copia']) ? trim($_POST['emails_copia']) : ($cfg['emails_copia'] ?? '');

        // Consultar lotes rojos y vencidos
        $stmtRojos = $pdo->query("SELECT * FROM v_semaforo_vencimientos WHERE color_semaforo IN ('ROJO', 'VENCIDO') ORDER BY dias_restantes ASC");
        $lotesRojos = $stmtRojos->fetchAll();

        // Consultar lotes amarillos
        $stmtAmarillos = $pdo->query("SELECT * FROM v_semaforo_vencimientos WHERE color_semaforo = 'AMARILLO' ORDER BY dias_restantes ASC");
        $lotesAmarillos = $stmtAmarillos->fetchAll();

        $stmtResumen = $pdo->query("SELECT * FROM v_resumen_dashboard LIMIT 1");
        $resumen = $stmtResumen->fetch() ?: [];

        $filtrosConfig = [
            'frecuencia_reporte'  => $_POST['frecuencia_reporte'] ?? ($cfg['frecuencia_reporte'] ?? 'DIARIO'),
            'hora_reporte'        => $_POST['hora_reporte'] ?? ($cfg['hora_reporte'] ?? '08:00'),
            'email_destino'       => $emailDestino,
            'emails_copia'        => $emailsCopia,
            'incluir_vencidos'    => isset($_POST['incluir_vencidos']) ? (int)$_POST['incluir_vencidos'] : (int)($cfg['incluir_vencidos'] ?? 1),
            'incluir_rojos'       => isset($_POST['incluir_rojos']) ? (int)$_POST['incluir_rojos'] : (int)($cfg['incluir_rojos'] ?? 1),
            'incluir_amarillos'   => isset($_POST['incluir_amarillos']) ? (int)$_POST['incluir_amarillos'] : (int)($cfg['incluir_amarillos'] ?? 1),
            'incluir_financiero'  => isset($_POST['incluir_financiero']) ? (int)$_POST['incluir_financiero'] : (int)($cfg['incluir_financiero'] ?? 1),
            'incluir_sugerencias' => isset($_POST['incluir_sugerencias']) ? (int)$_POST['incluir_sugerencias'] : (int)($cfg['incluir_sugerencias'] ?? 1)
        ];

        $htmlBody = ResendEmailService::buildExpirationAlertHtml(
            $nombreNegocio,
            $user['nombre'],
            $lotesRojos,
            $lotesAmarillos,
            $resumen,
            $moneda,
            $filtrosConfig
        );

        $totalItems = count($lotesRojos);
        $subject = "⚠️ [Alerta Semáforo] {$totalItems} productos críticos en zona de riesgo — {$nombreNegocio}";

        $resultado = ResendEmailService::sendEmail($emailDestino, $subject, $htmlBody, $emailsCopia);

        if ($resultado['success']) {
            $metodo = $resultado['metodo'] ?? 'RESEND';
            $recipients = $resultado['recipients'] ?? [$emailDestino];
            $recipientsList = implode(', ', $recipients);

            // Guardar en alertas_log
            try {
                $stmtLog = $pdo->prepare("
                    INSERT INTO alertas_log (lote_id, nivel_alerta, dias_al_vencimiento, mensaje, leido)
                    VALUES (?, ?, ?, ?, 1)
                ");
                foreach ($lotesRojos as $lr) {
                    $stmtLog->execute([
                        $lr['lote_id'],
                        $lr['color_semaforo'],
                        $lr['dias_restantes'],
                        "Reporte ejecutivo enviado a {$recipientsList} via {$metodo}. Lote: {$lr['producto_nombre']}"
                    ]);
                }
            } catch (Exception $e) {}

            if ($metodo === 'SMTP' || $metodo === 'SMTP_AUTORESCUE') {
                $msg = "✅ ¡Reporte ejecutivo despachado con éxito a TODOS los destinatarios seleccionados (" . count($recipients) . "): {$recipientsList} vía Gmail SMTP! Se procesaron {$totalItems} lotes en alerta.";
            } elseif (!empty($resultado['sandbox_fallback'])) {
                $msg = "⚠️ Reporte entregado únicamente a {$resultado['enviado_a']}. 🛑 Resend en modo prueba gratuito (onboarding@resend.dev) no permite enviar a terceros y omitió a: " . implode(', ', $resultado['omitidos']) . ". Para enviar a TODOS tus colaboradores, activá el motor 'Gmail / SMTP' en la configuración de reportes.";
            } else {
                $msg = "✅ ¡Reporte ejecutivo despachado con éxito a {$recipientsList} vía Resend API! Se procesaron {$totalItems} lotes en alerta.";
            }

            jsonResponse([
                'status'     => 'success',
                'message'    => $msg,
                'data'       => $resultado['data'] ?? null,
                'metodo'     => $metodo,
                'recipients' => $recipients,
                'csrf_token' => getCsrfToken()
            ]);
        } else {
            jsonResponse([
                'status'     => 'error',
                'message'    => 'Error al despachar reporte: ' . $resultado['error'],
                'details'    => $resultado['details'] ?? null,
                'csrf_token' => getCsrfToken()
            ], 400);
        }
        break;

    case 'history':
        $stmt = $pdo->query("
            SELECT a.id, a.nivel_alerta, a.dias_al_vencimiento, a.mensaje, a.created_at, p.nombre AS producto_nombre
            FROM alertas_log a
            INNER JOIN lotes_vencimiento l ON a.lote_id = l.id
            INNER JOIN productos p ON l.producto_id = p.id
            ORDER BY a.created_at DESC
            LIMIT 20
        ");
        $historial = $stmt->fetchAll();

        jsonResponse([
            'status' => 'success',
            'historial' => $historial
        ]);
        break;

    default:
        jsonResponse(['status' => 'error', 'message' => 'Acción no permitida.'], 400);
}
