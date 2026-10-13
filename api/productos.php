<?php
/**
 * Búsqueda Rápida de Productos por Código de Barra o Nombre
 * Sistema de Vencimientos - Expiry
 */

require_once __DIR__ . '/security_helper.php';

$pdo = Database::getConnection();

$term = trim($_GET['term'] ?? $_GET['codigo_barra'] ?? '');

if (empty($term)) {
    jsonResponse(['status' => 'success', 'producto' => null, 'resultados' => []]);
}

// 1. Coincidencia exacta por código de barra (para pistola / escáner láser)
$stmt = $pdo->prepare("
    SELECT p.*, c.nombre AS categoria_nombre 
    FROM productos p
    LEFT JOIN categorias c ON p.categoria_id = c.id
    WHERE p.codigo_barra = ? AND p.activo = 1
    LIMIT 1
");
$stmt->execute([$term]);
$producto = $stmt->fetch();

if ($producto) {
    jsonResponse([
        'status' => 'success',
        'encontrado' => true,
        'producto' => $producto
    ]);
}

// 2. Si no es código exacto, buscar sugerencias por nombre
$stmtSug = $pdo->prepare("
    SELECT p.*, c.nombre AS categoria_nombre 
    FROM productos p
    LEFT JOIN categorias c ON p.categoria_id = c.id
    WHERE (p.nombre LIKE ? OR p.codigo_barra LIKE ?) AND p.activo = 1
    LIMIT 8
");
$termLike = '%' . $term . '%';
$stmtSug->execute([$termLike, $termLike]);
$sugerencias = $stmtSug->fetchAll();

jsonResponse([
    'status' => 'success',
    'encontrado' => false,
    'producto' => null,
    'resultados' => $sugerencias
]);
