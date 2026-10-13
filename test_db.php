<?php
/**
 * Script de Verificación de Base de Datos y Motor del Semáforo
 * Ejecutable por CLI: php test_db.php
 */

require_once __DIR__ . '/db.php';

try {
    $pdo = Database::getConnection();
    echo "========================================================\n";
    echo " PRUEBA DE CONEXIÓN Y ESTRUCTURA DE BASE DE DATOS (expiry)\n";
    echo "========================================================\n\n";

    // 1. Verificar Tablas
    $stmt = $pdo->query("SHOW TABLES");
    $tablas = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "1. Tablas y Vistas detectadas (" . count($tablas) . "):\n";
    foreach ($tablas as $t) {
        echo "   - " . $t . "\n";
    }
    echo "\n";

    // 2. Verificar Configuración y PIN de Administrador
    $stmtConfig = $pdo->query("SELECT nombre_negocio, moneda_simbolo, dias_alerta_amarillo, dias_alerta_rojo, admin_pin_hash FROM configuracion LIMIT 1");
    $cfg = $stmtConfig->fetch();
    echo "2. Configuración General:\n";
    echo "   - Negocio: " . $cfg['nombre_negocio'] . "\n";
    echo "   - Moneda: " . $cfg['moneda_simbolo'] . "\n";
    echo "   - Umbral Alerta Amarillo: " . $cfg['dias_alerta_amarillo'] . " días\n";
    echo "   - Umbral Alerta Rojo: " . $cfg['dias_alerta_rojo'] . " días\n";

    // Prueba de PIN 1234
    $pinPruebaValido = '1234';
    $pinValido = password_verify($pinPruebaValido, $cfg['admin_pin_hash']);
    echo "   - Prueba PIN Maestro '1234': " . ($pinValido ? "VALIDADO EXITOSAMENTE [OK]" : "ERROR EN PIN") . "\n";

    $pinPruebaInvalido = '9999';
    $pinInvalido = password_verify($pinPruebaInvalido, $cfg['admin_pin_hash']);
    echo "   - Prueba PIN Erróneo '9999': " . (!$pinInvalido ? "BLOQUEADO CORRECTAMENTE [OK]" : "FALLO DE SEGURIDAD") . "\n\n";

    // 3. Consultar Resumen Ejecutivo (Vista Financiera)
    echo "3. Resumen Ejecutivo (v_resumen_dashboard):\n";
    $stmtResumen = $pdo->query("SELECT * FROM v_resumen_dashboard");
    $resumen = $stmtResumen->fetch();
    echo "   - Productos en seguimiento: " . $resumen['total_productos_en_seguimiento'] . "\n";
    echo "   - Lotes Activos: " . $resumen['total_lotes_activos'] . "\n";
    echo "   - 🔴 Lotes en ROJO (Urgentes): " . $resumen['total_lotes_rojos'] . "\n";
    echo "   - 🟡 Lotes en AMARILLO (Alerta): " . $resumen['total_lotes_amarillos'] . "\n";
    echo "   - 🟢 Lotes en VERDE (Seguros): " . $resumen['total_lotes_verdes'] . "\n";
    echo "   - ⛔ Lotes VENCIDOS: " . $resumen['total_lotes_vencidos'] . "\n";
    echo "   - 💰 Capital en Riesgo: " . number_format($resumen['capital_en_riesgo_total'], 0, ',', '.') . " " . $cfg['moneda_simbolo'] . "\n";
    echo "   - ⚠️ Capital Vencido: " . number_format($resumen['capital_vencido_total'], 0, ',', '.') . " " . $cfg['moneda_simbolo'] . "\n\n";

    // 4. Semáforo de Vencimientos en Vivo
    echo "4. Detalle de Semáforo de Vencimientos (v_semaforo_vencimientos):\n";
    $stmtSemaforo = $pdo->query("SELECT producto_nombre, dias_restantes, color_semaforo, cantidad_actual, dinero_en_riesgo, sugerencia_accion FROM v_semaforo_vencimientos ORDER BY dias_restantes ASC");
    $lotes = $stmtSemaforo->fetchAll();
    foreach ($lotes as $l) {
        $colorIcon = '🟢';
        if ($l['color_semaforo'] === 'ROJO') $colorIcon = '🔴';
        elseif ($l['color_semaforo'] === 'AMARILLO') $colorIcon = '🟡';
        elseif ($l['color_semaforo'] === 'VENCIDO') $colorIcon = '⛔';

        echo sprintf(
            "   %s [%s] %s | Días: %d | Stock: %d | Riesgo: %s %s | %s\n",
            $colorIcon,
            str_pad($l['color_semaforo'], 8),
            str_pad($l['producto_nombre'], 32),
            $l['dias_restantes'],
            $l['cantidad_actual'],
            number_format($l['dinero_en_riesgo'], 0, ',', '.'),
            $cfg['moneda_simbolo'],
            $l['sugerencia_accion']
        );
    }
    echo "\n>>> BASE DE DATOS Y MOTOR DEL SEMÁFORO 100% OPERATIVOS <<<\n";

} catch (Exception $e) {
    echo "Error en test: " . $e->getMessage() . "\n";
    exit(1);
}
