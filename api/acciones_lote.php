<?php
/**
 * Acciones Protegidas por PIN de Administrador (Edición de fecha, Borrado, Descarte y Oferta)
 * Sistema de Vencimientos - Expiry
 */

require_once __DIR__ . '/security_helper.php';

$user = requireAuth();
$pdo = Database::getConnection();

// Validar CSRF
$csrf = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!validateCsrfToken($csrf)) {
    jsonResponse(['status' => 'error', 'message' => 'Token CSRF inválido. Actualice la sesión.'], 403);
}

$accion = trim($_POST['accion'] ?? '');
$loteId = isset($_POST['lote_id']) ? (int)$_POST['lote_id'] : 0;
$adminPin = trim($_POST['admin_pin'] ?? '');

if (empty($accion)) {
    jsonResponse(['status' => 'error', 'message' => 'Acción no especificada.'], 400);
}

// 1. Acción especial: Solo verificar PIN
if ($accion === 'verificar_pin') {
    if (empty($adminPin)) {
        jsonResponse(['status' => 'error', 'message' => 'Por favor ingrese el PIN.'], 400);
    }

    $valido = verificarPinAdmin($adminPin, 'VERIFICACION_TEST_PIN', $loteId, 'Test de PIN en interfaz');
    if ($valido) {
        jsonResponse(['status' => 'success', 'message' => 'PIN de Administrador confirmado correctamente.']);
    } else {
        jsonResponse(['status' => 'error', 'message' => 'PIN incorrecto. Intento registrado en auditoría.'], 401);
    }
}

// Para el resto de acciones críticas sobre un lote, se requiere lote_id
if ($loteId <= 0) {
    jsonResponse(['status' => 'error', 'message' => 'Identificador de lote no válido.'], 400);
}

// Obtener datos actuales del lote
$stmtLote = $pdo->prepare("
    SELECT l.*, p.nombre AS producto_nombre, p.precio_costo, p.precio_venta 
    FROM lotes_vencimiento l
    INNER JOIN productos p ON l.producto_id = p.id
    WHERE l.id = ? LIMIT 1
");
$stmtLote->execute([$loteId]);
$loteActual = $stmtLote->fetch();

if (!$loteActual) {
    jsonResponse(['status' => 'error', 'message' => 'Lote no encontrado.'], 404);
}

switch ($accion) {
    case 'modificar_fecha':
        $nuevaFecha = trim($_POST['nueva_fecha'] ?? '');
        $motivo = trim($_POST['motivo'] ?? 'Ajuste de fecha de vencimiento');

        if (empty($nuevaFecha) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $nuevaFecha)) {
            jsonResponse(['status' => 'error', 'message' => 'Nueva fecha de vencimiento inválida. Formato requerido: AAAA-MM-DD.'], 400);
        }

        $partesN = explode('-', $nuevaFecha);
        $anioN = (int)$partesN[0];
        $mesN = (int)$partesN[1];
        $diaN = (int)$partesN[2];

        if (!checkdate($mesN, $diaN, $anioN)) {
            jsonResponse(['status' => 'error', 'message' => 'La nueva fecha de vencimiento no es válida en el calendario.'], 400);
        }

        // Requiere validación de PIN
        if (!verificarPinAdmin($adminPin, 'MODIFICAR_FECHA_VENCIMIENTO', $loteId, $motivo, ['fecha_anterior' => $loteActual['fecha_vencimiento']], ['fecha_nueva' => $nuevaFecha])) {
            jsonResponse(['status' => 'error', 'message' => 'PIN de Administrador incorrecto. Acción denegada.'], 403);
        }

        $stmtUpd = $pdo->prepare("UPDATE lotes_vencimiento SET fecha_vencimiento = ?, updated_at = NOW() WHERE id = ?");
        $stmtUpd->execute([$nuevaFecha, $loteId]);

        jsonResponse([
            'status' => 'success',
            'message' => 'Fecha de vencimiento modificada correctamente y auditada con PIN.',
            'csrf_token' => getCsrfToken()
        ]);
        break;

    case 'eliminar_lote':
        $motivo = trim($_POST['motivo'] ?? 'Eliminación manual de registro');

        // Requiere validación de PIN
        if (!verificarPinAdmin($adminPin, 'ELIMINAR_REGISTRO_LOTE', $loteId, $motivo, $loteActual, null)) {
            jsonResponse(['status' => 'error', 'message' => 'PIN de Administrador incorrecto. Eliminación denegada.'], 403);
        }

        $stmtDel = $pdo->prepare("DELETE FROM lotes_vencimiento WHERE id = ?");
        $stmtDel->execute([$loteId]);

        jsonResponse([
            'status' => 'success',
            'message' => 'Lote de mercadería eliminado. Acción registrada en auditoría.',
            'csrf_token' => getCsrfToken()
        ]);
        break;

    case 'oferta_relampago':
        $descuentoPorcentaje = isset($_POST['descuento']) ? (int)$_POST['descuento'] : 30;
        $precioLiquidado = $loteActual['precio_venta'] * (1 - ($descuentoPorcentaje / 100));
        if ($precioLiquidado < $loteActual['precio_costo']) {
            $precioLiquidado = $loteActual['precio_costo']; // Mínimo al costo para salvar capital
        }

        $dineroRecuperado = $loteActual['cantidad_actual'] * $precioLiquidado;

        try {
            $pdo->beginTransaction();

            // Marcar lote como en oferta
            $stmtOferta = $pdo->prepare("UPDATE lotes_vencimiento SET estado = 'en_oferta', updated_at = NOW() WHERE id = ?");
            $stmtOferta->execute([$loteId]);

            // Registrar merma salvada
            $stmtMerma = $pdo->prepare("
                INSERT INTO mermas_liquidaciones 
                (lote_id, tipo_accion, cantidad, precio_costo_unitario, precio_liquidado_unitario, dinero_recuperado, perdida_total, motivo, autorizado_por_pin)
                VALUES (?, 'oferta_relampago', ?, ?, ?, ?, 0.00, ?, 1)
            ");
            $stmtMerma->execute([
                $loteId,
                $loteActual['cantidad_actual'],
                $loteActual['precio_costo'],
                $precioLiquidado,
                $dineroRecuperado,
                "Activación de oferta relámpago con {$descuentoPorcentaje}% de descuento"
            ]);

            $pdo->commit();

            jsonResponse([
                'status' => 'success',
                'message' => "¡Oferta relámpago activada! Capital potencial salvado: " . number_format($dineroRecuperado, 0, ',', '.') . " Gs.",
                'csrf_token' => getCsrfToken()
            ]);
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            jsonResponse(['status' => 'error', 'message' => 'Error al activar oferta: ' . $e->getMessage()], 500);
        }
        break;

    case 'descarte_vencido':
        $motivo = trim($_POST['motivo'] ?? 'Descarte por vencimiento definitivo');

        // Requiere validación de PIN
        if (!verificarPinAdmin($adminPin, 'DESCARTE_MERCADERIA_VENCIDA', $loteId, $motivo, $loteActual, null)) {
            jsonResponse(['status' => 'error', 'message' => 'PIN de Administrador requerido para autorizar descarte.'], 403);
        }

        $perdidaTotal = $loteActual['cantidad_actual'] * $loteActual['precio_costo'];

        try {
            $pdo->beginTransaction();

            $stmtDesc = $pdo->prepare("UPDATE lotes_vencimiento SET estado = 'retirado', cantidad_actual = 0, updated_at = NOW() WHERE id = ?");
            $stmtDesc->execute([$loteId]);

            $stmtMerma = $pdo->prepare("
                INSERT INTO mermas_liquidaciones 
                (lote_id, tipo_accion, cantidad, precio_costo_unitario, precio_liquidado_unitario, dinero_recuperado, perdida_total, motivo, autorizado_por_pin)
                VALUES (?, 'descarte_perdida', ?, ?, 0.00, 0.00, ?, ?, 1)
            ");
            $stmtMerma->execute([
                $loteId,
                $loteActual['cantidad_actual'],
                $loteActual['precio_costo'],
                $perdidaTotal,
                $motivo
            ]);

            $pdo->commit();

            jsonResponse([
                'status' => 'success',
                'message' => 'Mercadería retirada y asentada como merma bajo autorización de PIN.',
                'csrf_token' => getCsrfToken()
            ]);
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            jsonResponse(['status' => 'error', 'message' => 'Error al registrar descarte: ' . $e->getMessage()], 500);
        }
        break;

    default:
        jsonResponse(['status' => 'error', 'message' => 'Operación desconocida.'], 400);
}
