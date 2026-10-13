<?php
/**
 * Endpoint de Auditoría de Seguridad PIN y Mermas Recuperadas
 * Sistema de Vencimientos - Expiry
 */

require_once __DIR__ . '/security_helper.php';

$user = requireAuth();
$pdo = Database::getConnection();

// 1. Logs de Auditoría de PIN
$stmtAudit = $pdo->query("
    SELECT 
        a.id,
        a.accion,
        a.tabla_afectada,
        a.registro_id,
        a.motivo,
        a.autorizado,
        a.ip_address,
        a.fecha_hora,
        u.nombre AS usuario_nombre,
        u.usuario AS usuario_login
    FROM auditoria_seguridad_pin a
    LEFT JOIN usuarios u ON a.usuario_id = u.id
    ORDER BY a.fecha_hora DESC
    LIMIT 25
");
$auditorias = $stmtAudit->fetchAll();

// 2. Resumen y registros de mermas y dinero salvado
$stmtMermas = $pdo->query("
    SELECT 
        m.id,
        m.tipo_accion,
        m.cantidad,
        m.precio_costo_unitario,
        m.precio_liquidado_unitario,
        m.dinero_recuperado,
        m.perdida_total,
        m.motivo,
        m.fecha_movimiento,
        p.nombre AS producto_nombre,
        p.codigo_barra
    FROM mermas_liquidaciones m
    INNER JOIN lotes_vencimiento l ON m.lote_id = l.id
    INNER JOIN productos p ON l.producto_id = p.id
    ORDER BY m.fecha_movimiento DESC
    LIMIT 25
");
$mermas = $stmtMermas->fetchAll();

// Métricas acumuladas de dinero salvado vs perdido
$stmtTotales = $pdo->query("
    SELECT 
        COALESCE(SUM(dinero_recuperado), 0) AS total_dinero_salvado,
        COALESCE(SUM(perdida_total), 0) AS total_dinero_perdido,
        COUNT(*) AS total_operaciones_merma
    FROM mermas_liquidaciones
");
$totales = $stmtTotales->fetch();

jsonResponse([
    'status' => 'success',
    'auditorias' => $auditorias,
    'mermas' => $mermas,
    'totales_ahorro' => $totales
]);
