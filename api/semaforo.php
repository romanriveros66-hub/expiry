<?php
/**
 * Endpoint del Semáforo de Vencimientos y Métricas
 * Sistema de Vencimientos - Expiry
 */

require_once __DIR__ . '/security_helper.php';

$pdo = Database::getConnection();

$colorFiltro = strtoupper(trim($_GET['color'] ?? 'ALL'));
$busqueda = trim($_GET['search'] ?? '');
$categoriaId = isset($_GET['categoria_id']) && is_numeric($_GET['categoria_id']) ? (int)$_GET['categoria_id'] : null;

// 1. Obtener métricas globales para el semáforo y dashboard
$stmtResumen = $pdo->query("SELECT * FROM v_resumen_dashboard LIMIT 1");
$resumen = $stmtResumen->fetch() ?: [
    'total_productos_en_seguimiento' => 0,
    'total_lotes_activos' => 0,
    'total_lotes_rojos' => 0,
    'total_lotes_amarillos' => 0,
    'total_lotes_verdes' => 0,
    'total_lotes_vencidos' => 0,
    'capital_en_riesgo_total' => 0,
    'capital_vencido_total' => 0,
    'capital_total_inventario' => 0
];

// 2. Consulta filtrada del Semáforo
$sql = "
    SELECT 
        lote_id,
        producto_id,
        codigo_barra,
        producto_nombre,
        categoria_nombre,
        unidad_medida,
        codigo_lote,
        ubicacion,
        cantidad_actual,
        fecha_ingreso,
        fecha_vencimiento,
        lote_estado,
        precio_costo,
        precio_venta,
        dinero_en_riesgo,
        valor_venta_potencial,
        dias_restantes,
        color_semaforo,
        sugerencia_accion
    FROM v_semaforo_vencimientos
    WHERE 1=1
";
$params = [];

if ($colorFiltro !== 'ALL' && in_array($colorFiltro, ['ROJO', 'AMARILLO', 'VERDE', 'VENCIDO'])) {
    $sql .= " AND color_semaforo = ? ";
    $params[] = $colorFiltro;
}

if (!empty($busqueda)) {
    $sql .= " AND (producto_nombre LIKE ? OR codigo_barra LIKE ? OR codigo_lote LIKE ?) ";
    $paramLike = '%' . $busqueda . '%';
    $params[] = $paramLike;
    $params[] = $paramLike;
    $params[] = $paramLike;
}

$sql .= " ORDER BY dias_restantes ASC, fecha_vencimiento ASC ";

$stmtLotes = $pdo->prepare($sql);
$stmtLotes->execute($params);
$lotes = $stmtLotes->fetchAll();

// 3. Obtener categorías para selectores rápidos
$stmtCat = $pdo->query("SELECT id, nombre, icono FROM categorias ORDER BY nombre ASC");
$categorias = $stmtCat->fetchAll();

jsonResponse([
    'status' => 'success',
    'resumen' => $resumen,
    'lotes' => $lotes,
    'categorias' => $categorias,
    'total_filtrados' => count($lotes),
    'csrf_token' => getCsrfToken()
]);
