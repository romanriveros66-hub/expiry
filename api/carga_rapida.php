<?php
/**
 * Endpoint de Carga Rápida de Mercadería con Fecha de Expiración
 * Sistema de Vencimientos - Expiry
 */

require_once __DIR__ . '/security_helper.php';

$user = requireAuth();
$pdo = Database::getConnection();

// Verificar CSRF en POST
$csrf = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!validateCsrfToken($csrf)) {
    jsonResponse(['status' => 'error', 'message' => 'Token de seguridad inválido. Recargue la página.'], 403);
}

$codigoBarra = trim($_POST['codigo_barra'] ?? '');
$nombre = trim($_POST['nombre'] ?? '');
$marca = trim($_POST['marca'] ?? '');
$categoriaId = !empty($_POST['categoria_id']) ? (int)$_POST['categoria_id'] : null;
$cantidad = isset($_POST['cantidad']) && (int)$_POST['cantidad'] > 0 ? (int)$_POST['cantidad'] : 1;
$fechaVencimiento = trim($_POST['fecha_vencimiento'] ?? '');
$precioCosto = isset($_POST['precio_costo']) ? (float)$_POST['precio_costo'] : 0.00;
$precioVenta = isset($_POST['precio_venta']) && (float)$_POST['precio_venta'] > 0 ? (float)$_POST['precio_venta'] : ($precioCosto > 0 ? round($precioCosto * 1.30, 2) : 0.00);
$ubicacion = trim($_POST['ubicacion'] ?? 'Góndola');
$codigoLote = trim($_POST['codigo_lote'] ?? '');
$notas = trim($_POST['notas'] ?? '');

// Si no viene código de barra, autogenerar uno de 13 dígitos
if (empty($codigoBarra)) {
    $codigoBarra = '784' . str_pad((string)rand(100000000, 999999999), 10, '0', STR_PAD_LEFT);
}

// Validaciones
if (empty($nombre)) {
    jsonResponse(['status' => 'error', 'message' => 'El nombre del producto es obligatorio.'], 400);
}

if (empty($fechaVencimiento) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaVencimiento)) {
    jsonResponse(['status' => 'error', 'message' => 'Fecha de vencimiento inválida. Formato estructurado requerido: AAAA-MM-DD.'], 400);
}

$partesF = explode('-', $fechaVencimiento);
$anio = (int)$partesF[0];
$mes = (int)$partesF[1];
$dia = (int)$partesF[2];

if (!checkdate($mes, $dia, $anio)) {
    jsonResponse(['status' => 'error', 'message' => 'La fecha de vencimiento ingresada no es válida en el calendario.'], 400);
}

$anioActual = (int)date('Y');
if ($anio < ($anioActual - 2) || $anio > ($anioActual + 20)) {
    jsonResponse(['status' => 'error', 'message' => 'El año de vencimiento está fuera de rango.'], 400);
}

try {
    $pdo->beginTransaction();

    // 1. Buscar si el producto ya existe en catálogo
    $stmtProd = $pdo->prepare("SELECT id, precio_costo, precio_venta FROM productos WHERE codigo_barra = ? OR nombre = ? LIMIT 1");
    $stmtProd->execute([$codigoBarra, $nombre]);
    $producto = $stmtProd->fetch();

    $productoId = null;

    if ($producto) {
        $productoId = (int)$producto['id'];
        $stmtUpd = $pdo->prepare("
            UPDATE productos 
            SET nombre = ?, marca = COALESCE(NULLIF(?, ''), marca),
                categoria_id = COALESCE(?, categoria_id),
                precio_costo = CASE WHEN ? > 0 THEN ? ELSE precio_costo END,
                precio_venta = CASE WHEN ? > 0 THEN ? ELSE precio_venta END,
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmtUpd->execute([$nombre, $marca, $categoriaId, $precioCosto, $precioCosto, $precioVenta, $precioVenta, $productoId]);
    } else {
        $stmtIns = $pdo->prepare("
            INSERT INTO productos (codigo_barra, nombre, marca, categoria_id, unidad_medida, precio_costo, precio_venta, activo)
            VALUES (?, ?, ?, ?, 'UNIDAD', ?, ?, 1)
        ");
        $stmtIns->execute([$codigoBarra, $nombre, $marca, $categoriaId, $precioCosto, $precioVenta]);
        $productoId = (int)$pdo->lastInsertId();
    }

    // 2. Autogenerar código de lote si está vacío
    if (empty($codigoLote)) {
        $codigoLote = 'L-' . date('ymd') . '-' . substr(strtoupper(md5(uniqid())), 0, 4);
    }

    // 3. Insertar el lote con fecha de expiración
    $stmtLote = $pdo->prepare("
        INSERT INTO lotes_vencimiento 
        (producto_id, codigo_lote, cantidad_inicial, cantidad_actual, fecha_ingreso, fecha_vencimiento, ubicacion, estado, notas, creado_por)
        VALUES (?, ?, ?, ?, CURDATE(), ?, ?, 'activo', ?, ?)
    ");
    $stmtLote->execute([
        $productoId,
        $codigoLote,
        $cantidad,
        $cantidad,
        $fechaVencimiento,
        !empty($ubicacion) ? $ubicacion : 'Góndola',
        $notas,
        $user['id']
    ]);
    $loteId = (int)$pdo->lastInsertId();

    $pdo->commit();

    // 4. Consultar el estado calculado en el semáforo para responder con feedback visual
    $stmtSem = $pdo->prepare("SELECT * FROM v_semaforo_vencimientos WHERE lote_id = ? LIMIT 1");
    $stmtSem->execute([$loteId]);
    $loteInfo = $stmtSem->fetch();

    jsonResponse([
        'status' => 'success',
        'message' => '¡Mercadería y fecha de vencimiento registradas exitosamente!',
        'lote' => $loteInfo,
        'csrf_token' => getCsrfToken()
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    jsonResponse(['status' => 'error', 'message' => 'Error al guardar la mercadería: ' . $e->getMessage()], 500);
}
