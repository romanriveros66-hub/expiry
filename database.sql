-- ==============================================================================
-- SISTEMA DE ALERTA DE VENCIMIENTOS PARA DESPENSAS Y MINIMARKETS
-- Base de Datos: expiry
-- "Evitá pérdidas tirando mercadería: el semáforo de vencimientos para tu negocio"
-- ==============================================================================

CREATE DATABASE IF NOT EXISTS `expiry` 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

USE `expiry`;

-- Desactivar temporalmente revisión de foreign keys para recreación limpia
SET FOREIGN_KEY_CHECKS = 0;

DROP VIEW IF EXISTS `v_resumen_dashboard`;
DROP VIEW IF EXISTS `v_semaforo_vencimientos`;
DROP TABLE IF EXISTS `alertas_log`;
DROP TABLE IF EXISTS `mermas_liquidaciones`;
DROP TABLE IF EXISTS `auditoria_seguridad_pin`;
DROP TABLE IF EXISTS `lotes_vencimiento`;
DROP TABLE IF EXISTS `productos`;
DROP TABLE IF EXISTS `categorias`;
DROP TABLE IF EXISTS `usuarios`;
DROP TABLE IF EXISTS `configuracion`;

SET FOREIGN_KEY_CHECKS = 1;

-- ------------------------------------------------------------------------------
-- 1. TABLA: configuracion
-- Parámetros del negocio, semáforo y PIN de seguridad maestro
-- ------------------------------------------------------------------------------
CREATE TABLE `configuracion` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `nombre_negocio` VARCHAR(150) NOT NULL DEFAULT 'Minimarket & Despensa Express',
    `moneda_simbolo` VARCHAR(10) NOT NULL DEFAULT 'Gs.',
    `moneda_nombre` VARCHAR(30) NOT NULL DEFAULT 'Guaraníes',
    `dias_alerta_amarillo` INT NOT NULL DEFAULT 30 COMMENT 'Días previos para alerta preventiva (amarillo)',
    `dias_alerta_rojo` INT NOT NULL DEFAULT 7 COMMENT 'Días previos para alerta crítica (rojo)',
    `admin_pin_hash` VARCHAR(255) NOT NULL COMMENT 'PIN maestro hasheado con bcrypt (Default PIN: 1234)',
    `pin_intentos_max` INT NOT NULL DEFAULT 5,
    `bloqueo_minutos` INT NOT NULL DEFAULT 15,
    `telefono_alerta` VARCHAR(30) NULL COMMENT 'Teléfono de WhatsApp para avisos automáticos',
    `notificar_whatsapp` TINYINT(1) NOT NULL DEFAULT 0,
    `resend_api_key` VARCHAR(255) NULL COMMENT 'API Key de Resend (Ej: re_...)',
    `email_remitente` VARCHAR(150) NOT NULL DEFAULT 'Expiry Alertas <onboarding@resend.dev>',
    `email_metodo` VARCHAR(20) NOT NULL DEFAULT 'RESEND' COMMENT 'RESEND o SMTP (Gmail, etc.)',
    `smtp_host` VARCHAR(150) NOT NULL DEFAULT 'smtp.gmail.com',
    `smtp_port` INT NOT NULL DEFAULT 465,
    `smtp_seguridad` VARCHAR(10) NOT NULL DEFAULT 'ssl' COMMENT 'ssl o tls',
    `smtp_user` VARCHAR(150) NOT NULL DEFAULT 'roman.riveros.66@gmail.com',
    `smtp_pass` VARCHAR(255) NULL COMMENT 'Contraseña o App Password de SMTP',
    `notificar_email` TINYINT(1) NOT NULL DEFAULT 1,
    `email_notificaciones` VARCHAR(150) NULL COMMENT 'Correo destino principal de alertas',
    `frecuencia_reporte` VARCHAR(30) NOT NULL DEFAULT 'DIARIO' COMMENT 'DIARIO, DIAS_ALTERNOS, SEMANAL, TIEMPO_REAL',
    `hora_reporte` VARCHAR(10) NOT NULL DEFAULT '08:00' COMMENT 'Hora programada para envío',
    `emails_copia` TEXT NULL COMMENT 'Correos secundarios en copia (CC)',
    `incluir_vencidos` TINYINT(1) NOT NULL DEFAULT 1,
    `incluir_rojos` TINYINT(1) NOT NULL DEFAULT 1,
    `incluir_amarillos` TINYINT(1) NOT NULL DEFAULT 1,
    `incluir_financiero` TINYINT(1) NOT NULL DEFAULT 1,
    `incluir_sugerencias` TINYINT(1) NOT NULL DEFAULT 1,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 1.B TABLA: contactos_reporte
-- Lista de distribución de contactos para entrega de alertas y reportes
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `contactos_reporte` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `nombre` VARCHAR(100) NOT NULL,
    `email` VARCHAR(150) NOT NULL,
    `cargo` VARCHAR(80) NOT NULL DEFAULT 'Colaborador',
    `tipo_destinatario` ENUM('PRINCIPAL', 'CC') NOT NULL DEFAULT 'CC',
    `activo` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_contacto_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 2. TABLA: usuarios
-- Operadores del sistema (administradores y empleados/cajeros)
-- ------------------------------------------------------------------------------
CREATE TABLE `usuarios` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `nombre` VARCHAR(100) NOT NULL,
    `usuario` VARCHAR(50) NOT NULL,
    `email` VARCHAR(120) NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `rol` ENUM('admin', 'empleado') NOT NULL DEFAULT 'empleado',
    `activo` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_usuario` (`usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 3. TABLA: categorias
-- Clasificación rápida de mercadería para filtrado ágil
-- ------------------------------------------------------------------------------
CREATE TABLE `categorias` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `nombre` VARCHAR(80) NOT NULL,
    `descripcion` VARCHAR(255) NULL,
    `icono` VARCHAR(50) NOT NULL DEFAULT 'fa-box',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_categoria_nombre` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 4. TABLA: productos
-- Catálogo centralizado con soporte para lector de código de barras (EAN/SKU)
-- ------------------------------------------------------------------------------
CREATE TABLE `productos` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `codigo_barra` VARCHAR(64) NOT NULL COMMENT 'Código de barra leído con escáner o código manual',
    `nombre` VARCHAR(150) NOT NULL,
    `descripcion` VARCHAR(255) NULL,
    `categoria_id` INT NULL,
    `unidad_medida` VARCHAR(20) NOT NULL DEFAULT 'UNIDAD' COMMENT 'UNIDAD, KG, PACK, LITRO, etc.',
    `precio_costo` DECIMAL(12, 2) NOT NULL DEFAULT 0.00 COMMENT 'Costo de compra para calcular pérdidas potenciales',
    `precio_venta` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    `marca` VARCHAR(100) NULL,
    `stock_minimo_alerta` INT NOT NULL DEFAULT 2,
    `activo` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_producto_codigo` (`codigo_barra`),
    KEY `idx_producto_categoria` (`categoria_id`),
    KEY `idx_producto_nombre` (`nombre`),
    CONSTRAINT `fk_prod_categoria` FOREIGN KEY (`categoria_id`) 
        REFERENCES `categorias` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 5. TABLA: lotes_vencimiento
-- Núcleo de la carga rápida de fechas y control del semáforo por partida/lote
-- ------------------------------------------------------------------------------
CREATE TABLE `lotes_vencimiento` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `producto_id` INT NOT NULL,
    `codigo_lote` VARCHAR(50) NULL COMMENT 'Lote impreso en empaque o autogenerado',
    `cantidad_inicial` INT NOT NULL,
    `cantidad_actual` INT NOT NULL,
    `fecha_ingreso` DATE NOT NULL,
    `fecha_vencimiento` DATE NOT NULL COMMENT 'Fecha de expiración para el cálculo del semáforo',
    `ubicacion` VARCHAR(100) NOT NULL DEFAULT 'Góndola' COMMENT 'Góndola, Heladera, Mostrador, Depósito, Cámara de frío',
    `estado` ENUM('activo', 'en_oferta', 'vencido', 'agotado', 'retirado') NOT NULL DEFAULT 'activo',
    `notas` TEXT NULL,
    `creado_por` INT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_lote_vencimiento` (`fecha_vencimiento`),
    KEY `idx_lote_estado` (`estado`),
    KEY `idx_lote_producto_venc` (`producto_id`, `fecha_vencimiento`),
    CONSTRAINT `fk_lote_producto` FOREIGN KEY (`producto_id`) 
        REFERENCES `productos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_lote_usuario` FOREIGN KEY (`creado_por`) 
        REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 6. TABLA: auditoria_seguridad_pin
-- Blindaje de seguridad: Registro inmutable de acciones críticas validadas con PIN
-- ------------------------------------------------------------------------------
CREATE TABLE `auditoria_seguridad_pin` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `accion` VARCHAR(60) NOT NULL COMMENT 'ELIMINAR_LOTE, EDITAR_VENCIMIENTO, DESCARTE, CAMBIO_PIN, etc.',
    `tabla_afectada` VARCHAR(50) NULL,
    `registro_id` INT NULL,
    `valores_anteriores` TEXT NULL COMMENT 'JSON o texto de los datos antes de la mutación',
    `valores_nuevos` TEXT NULL COMMENT 'JSON o texto de los datos modificados',
    `motivo` VARCHAR(255) NULL COMMENT 'Explicación del empleado/admin para la modificación',
    `usuario_id` INT NULL,
    `ip_address` VARCHAR(45) NULL,
    `autorizado` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1=PIN Válido, 0=PIN Denegado/Erróneo',
    `fecha_hora` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_audit_fecha` (`fecha_hora`),
    KEY `idx_audit_accion` (`accion`),
    CONSTRAINT `fk_audit_usuario` FOREIGN KEY (`usuario_id`) 
        REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 7. TABLA: mermas_liquidaciones
-- Justificación de valor comercial: Registro de dinero salvado vs perdido
-- ------------------------------------------------------------------------------
CREATE TABLE `mermas_liquidaciones` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `lote_id` INT NOT NULL,
    `tipo_accion` ENUM('oferta_relampago', 'descarte_perdida', 'devolucion_proveedor', 'consumo_interno') NOT NULL,
    `cantidad` INT NOT NULL,
    `precio_costo_unitario` DECIMAL(12, 2) NOT NULL,
    `precio_liquidado_unitario` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    `dinero_recuperado` DECIMAL(12, 2) NOT NULL DEFAULT 0.00 COMMENT 'Dinero salvado por poner en oferta o liquidar a tiempo',
    `perdida_total` DECIMAL(12, 2) NOT NULL DEFAULT 0.00 COMMENT 'Pérdida neta real asumida',
    `motivo` VARCHAR(255) NULL,
    `autorizado_por_pin` TINYINT(1) NOT NULL DEFAULT 1,
    `fecha_movimiento` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_mermas_lote` (`lote_id`),
    KEY `idx_mermas_tipo` (`tipo_accion`),
    KEY `idx_mermas_fecha` (`fecha_movimiento`),
    CONSTRAINT `fk_merma_lote` FOREIGN KEY (`lote_id`) 
        REFERENCES `lotes_vencimiento` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 8. TABLA: alertas_log
-- Historial y cola de avisos emitidos para control de notificaciones
-- ------------------------------------------------------------------------------
CREATE TABLE `alertas_log` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `lote_id` INT NOT NULL,
    `nivel_alerta` ENUM('AMARILLO', 'ROJO', 'VENCIDO') NOT NULL,
    `dias_al_vencimiento` INT NOT NULL,
    `mensaje` VARCHAR(255) NOT NULL,
    `leido` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_alerta_lote` (`lote_id`),
    KEY `idx_alerta_leido` (`leido`),
    KEY `idx_alerta_nivel` (`nivel_alerta`),
    CONSTRAINT `fk_alerta_lote` FOREIGN KEY (`lote_id`) 
        REFERENCES `lotes_vencimiento` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==============================================================================
-- VISTAS INTELIGENTES (SEMÁFORO DE VENCIMIENTOS Y DASHBOARD FINANCIERO)
-- ==============================================================================

-- Vista 1: Semáforo dinámico de vencimientos en tiempo real
CREATE OR REPLACE VIEW `v_semaforo_vencimientos` AS
SELECT 
    l.`id` AS `lote_id`,
    p.`id` AS `producto_id`,
    p.`codigo_barra`,
    p.`nombre` AS `producto_nombre`,
    c.`nombre` AS `categoria_nombre`,
    p.`unidad_medida`,
    l.`codigo_lote`,
    l.`ubicacion`,
    l.`cantidad_actual`,
    l.`fecha_ingreso`,
    l.`fecha_vencimiento`,
    l.`estado` AS `lote_estado`,
    p.`precio_costo`,
    p.`precio_venta`,
    (l.`cantidad_actual` * p.`precio_costo`) AS `dinero_en_riesgo`,
    (l.`cantidad_actual` * p.`precio_venta`) AS `valor_venta_potencial`,
    DATEDIFF(l.`fecha_vencimiento`, CURDATE()) AS `dias_restantes`,
    CASE 
        WHEN DATEDIFF(l.`fecha_vencimiento`, CURDATE()) < 0 THEN _utf8mb4'VENCIDO' COLLATE utf8mb4_unicode_ci
        WHEN DATEDIFF(l.`fecha_vencimiento`, CURDATE()) <= cfg.`dias_alerta_rojo` THEN _utf8mb4'ROJO' COLLATE utf8mb4_unicode_ci
        WHEN DATEDIFF(l.`fecha_vencimiento`, CURDATE()) <= cfg.`dias_alerta_amarillo` THEN _utf8mb4'AMARILLO' COLLATE utf8mb4_unicode_ci
        ELSE _utf8mb4'VERDE' COLLATE utf8mb4_unicode_ci
    END AS `color_semaforo`,
    CASE 
        WHEN DATEDIFF(l.`fecha_vencimiento`, CURDATE()) < 0 THEN _utf8mb4'Mercadería vencida: retirar de góndola' COLLATE utf8mb4_unicode_ci
        WHEN DATEDIFF(l.`fecha_vencimiento`, CURDATE()) = 0 THEN _utf8mb4'¡Vence HOY! Rematar o consumir de inmediato' COLLATE utf8mb4_unicode_ci
        WHEN DATEDIFF(l.`fecha_vencimiento`, CURDATE()) <= cfg.`dias_alerta_rojo` THEN _utf8mb4'Crítico: Vence en pocos días, aplicar oferta relámpago' COLLATE utf8mb4_unicode_ci
        WHEN DATEDIFF(l.`fecha_vencimiento`, CURDATE()) <= cfg.`dias_alerta_amarillo` THEN _utf8mb4'Alerta preventiva: Ubicar al frente de góndola' COLLATE utf8mb4_unicode_ci
        ELSE _utf8mb4'Óptimo: Tiempo de vida útil seguro' COLLATE utf8mb4_unicode_ci
    END AS `sugerencia_accion`
FROM `lotes_vencimiento` l
INNER JOIN `productos` p ON l.`producto_id` = p.`id`
LEFT JOIN `categorias` c ON p.`categoria_id` = c.`id`
INNER JOIN `configuracion` cfg ON cfg.`id` = 1
WHERE l.`cantidad_actual` > 0 
  AND l.`estado` IN ('activo', 'en_oferta');

-- Vista 2: Resumen ejecutivo del Dashboard (Indicadores clave y dinero en juego)
CREATE OR REPLACE VIEW `v_resumen_dashboard` AS
SELECT 
    COUNT(DISTINCT v.`producto_id`) AS `total_productos_en_seguimiento`,
    COUNT(v.`lote_id`) AS `total_lotes_activos`,
    SUM(CASE WHEN v.`color_semaforo` = 'ROJO' THEN 1 ELSE 0 END) AS `total_lotes_rojos`,
    SUM(CASE WHEN v.`color_semaforo` = 'AMARILLO' THEN 1 ELSE 0 END) AS `total_lotes_amarillos`,
    SUM(CASE WHEN v.`color_semaforo` = 'VERDE' THEN 1 ELSE 0 END) AS `total_lotes_verdes`,
    SUM(CASE WHEN v.`color_semaforo` = 'VENCIDO' THEN 1 ELSE 0 END) AS `total_lotes_vencidos`,
    COALESCE(SUM(CASE WHEN v.`color_semaforo` IN ('ROJO', 'AMARILLO') THEN v.`dinero_en_riesgo` ELSE 0 END), 0) AS `capital_en_riesgo_total`,
    COALESCE(SUM(CASE WHEN v.`color_semaforo` = 'VENCIDO' THEN v.`dinero_en_riesgo` ELSE 0 END), 0) AS `capital_vencido_total`,
    COALESCE(SUM(v.`dinero_en_riesgo`), 0) AS `capital_total_inventario`
FROM `v_semaforo_vencimientos` v;

-- ==============================================================================
-- DATOS INICIALES SEMILLA (SEED DATA REALISTA PARA MINIMARKETS / DESPENSAS)
-- ==============================================================================

-- 1. Configuración por defecto (PIN Administrador predeterminado: 1234)
INSERT INTO `configuracion` (`id`, `nombre_negocio`, `moneda_simbolo`, `moneda_nombre`, `dias_alerta_amarillo`, `dias_alerta_rojo`, `admin_pin_hash`, `telefono_alerta`, `notificar_whatsapp`)
VALUES (1, 'Minimarket & Despensa El Ahorro', 'Gs.', 'Guaraníes', 30, 7, '$2y$10$FHm1LO5XDNPKThqvOYsXwekQrZnrEJ0ZFggPDWTDTTjXNsZ5gr6cy', '+595981000111', 1);

-- 2. Usuarios por defecto (Clave: admin123 para admin / 123456 para cajero)
INSERT INTO `usuarios` (`id`, `nombre`, `usuario`, `password_hash`, `rol`, `activo`) VALUES
(1, 'Administrador Propietario', 'admin', '$2y$10$yV.F3gOGX/MUNva4r9AVnuDYdnuEm8I3RuIDzEgz6a2tjNQRWBn9K', 'admin', 1),
(2, 'Cajero Turno Tarde', 'cajero1', '$2y$10$yV.F3gOGX/MUNva4r9AVnuDYdnuEm8I3RuIDzEgz6a2tjNQRWBn9K', 'empleado', 1);

-- 3. Categorías típicas con alta rotación y criticidad de vencimiento
INSERT INTO `categorias` (`id`, `nombre`, `descripcion`, `icono`) VALUES
(1, 'Lácteos y Yogures', 'Leches, quesos, yogures y postres refrigerados', 'fa-cheese'),
(2, 'Fiambrería y Embutidos', 'Jamones, salchichas, salames y fiambres', 'fa-drumstick-bite'),
(3, 'Panificados y Bollería', 'Pan lactal, panes de hamburguesa, medialunas', 'fa-bread-slice'),
(4, 'Bebidas y Jugos', 'Gaseosas, jugos pasteurizados, cervezas', 'fa-wine-bottle'),
(5, 'Galletitas y Snacks', 'Galletas dulces, saladas, papas fritas y snacks', 'fa-cookie-bite'),
(6, 'Pastas y Salsas', 'Fideos, tapas de empanadas, salsa de tomate', 'fa-utensils'),
(7, 'Golosinas y Chocolates', 'Chocolates, alfajores, caramelos', 'fa-candy-cane'),
(8, 'Enlatados y Conservas', 'Atún, choclo, arvejas, duraznos al natural', 'fa-box-open');

-- 4. Productos de ejemplo comunes en góndola
INSERT INTO `productos` (`id`, `codigo_barra`, `nombre`, `descripcion`, `categoria_id`, `unidad_medida`, `precio_costo`, `precio_venta`, `marca`, `stock_minimo_alerta`, `activo`) VALUES
(1, '7840001001015', 'Yogur Frutilla Sache 1L', 'Yogur entero bebible sabor frutilla', 1, 'UNIDAD', 6500.00, 9000.00, 'Lactolanda', 5, 1),
(2, '7840001001022', 'Queso Sandwich Feteado 200g', 'Queso tipo barra en fetas', 1, 'UNIDAD', 12000.00, 16500.00, 'Trebol', 4, 1),
(3, '7840002002012', 'Pan Lactal Blanco 500g', 'Pan de molde tradicional', 3, 'UNIDAD', 7000.00, 10500.00, 'Bimbo', 3, 1),
(4, '7840003003019', 'Salchichas Tipo Viena 6u', 'Paquete de salchichas cocidas', 2, 'UNIDAD', 5500.00, 8000.00, 'Ochsi', 5, 1),
(5, '7840004004016', 'Tapa para Empanadas x 12', 'Masa hojaldrada para horno', 6, 'UNIDAD', 4500.00, 6500.00, 'La Criolla', 6, 1),
(6, '7840005005013', 'Gaseosa Cola 2L Descartable', 'Bebida carbonatada clásica', 4, 'UNIDAD', 9000.00, 12500.00, 'Coca-Cola', 10, 1),
(7, '7840006006010', 'Galletitas Rellenas Vainilla 120g', 'Galletita dulce con crema de vainilla', 5, 'UNIDAD', 3000.00, 4500.00, 'Oreo', 8, 1),
(8, '7840007007017', 'Alfajor Triple Chocolate 60g', 'Alfajor relleno con dulce de leche', 7, 'UNIDAD', 3500.00, 5500.00, 'Havanna', 10, 1),
(9, '7840008008014', 'Atún al Natural en Lata 170g', 'Lomo de atún desmenuzado en agua', 8, 'UNIDAD', 8500.00, 12000.00, 'Gomes da Costa', 4, 1);

-- 5. Lotes con fechas calculadas dinámicamente para ilustrar el semáforo al instante
-- 🔴 Vencido: -3 días
-- 🔴 Crítico: +2 días, +5 días
-- 🟡 Alerta preventiva: +14 días, +24 días
-- 🟢 Seguro: +60 días, +180 días, +365 días
INSERT INTO `lotes_vencimiento` (`producto_id`, `codigo_lote`, `cantidad_inicial`, `cantidad_actual`, `fecha_ingreso`, `fecha_vencimiento`, `ubicacion`, `estado`, `notas`, `creado_por`) VALUES
-- Yogur: Lote 1 VENCIDO hace 2 días (🔴 VENCIDO)
(1, 'L-YOG-01', 12, 4, DATE_SUB(CURDATE(), INTERVAL 18 DAY), DATE_SUB(CURDATE(), INTERVAL 2 DAY), 'Heladera Lácteos 1', 'activo', 'Retirar urgente para no vender por error', 1),
-- Yogur: Lote 2 Vence en 3 días (🔴 ROJO CRÍTICO)
(1, 'L-YOG-02', 15, 8, DATE_SUB(CURDATE(), INTERVAL 10 DAY), DATE_ADD(CURDATE(), INTERVAL 3 DAY), 'Heladera Lácteos 1', 'activo', 'Poner cartel 2x1 o bajar precio para liquidar', 1),
-- Pan Lactal: Vence en 5 días (🔴 ROJO CRÍTICO)
(3, 'L-PAN-88', 10, 7, DATE_SUB(CURDATE(), INTERVAL 4 DAY), DATE_ADD(CURDATE(), INTERVAL 5 DAY), 'Góndola Central Panificados', 'activo', 'Mover al frente de la góndola', 2),
-- Salchichas: Vence en 14 días (🟡 AMARILLO ALERTA)
(4, 'L-SAL-12', 20, 16, DATE_SUB(CURDATE(), INTERVAL 5 DAY), DATE_ADD(CURDATE(), INTERVAL 14 DAY), 'Heladera Fiambrería', 'activo', 'Fecha próxima, vigilar rotación', 2),
-- Queso Sandwich: Vence en 24 días (🟡 AMARILLO ALERTA)
(2, 'L-QUE-45', 18, 12, DATE_SUB(CURDATE(), INTERVAL 8 DAY), DATE_ADD(CURDATE(), INTERVAL 24 DAY), 'Heladera Lácteos 2', 'activo', 'Buen estado, rotar según método FIFO', 1),
-- Tapas Empanadas: Vence en 28 días (🟡 AMARILLO ALERTA)
(5, 'L-TAP-09', 24, 20, DATE_SUB(CURDATE(), INTERVAL 3 DAY), DATE_ADD(CURDATE(), INTERVAL 28 DAY), 'Heladera Masas', 'activo', 'Stock regular', 1),
-- Gaseosa: Vence en 90 días (🟢 VERDE ÓPTIMO)
(6, 'L-GAS-77', 30, 26, DATE_SUB(CURDATE(), INTERVAL 15 DAY), DATE_ADD(CURDATE(), INTERVAL 90 DAY), 'Góndola Bebidas', 'activo', 'Fecha lejana y segura', 1),
-- Galletitas: Vence en 120 días (🟢 VERDE ÓPTIMO)
(7, 'L-GAL-33', 25, 25, DATE_SUB(CURDATE(), INTERVAL 10 DAY), DATE_ADD(CURDATE(), INTERVAL 120 DAY), 'Estantería Golosinas', 'activo', 'Stock nuevo', 2),
-- Atún: Vence en 500 días (🟢 VERDE ÓPTIMO)
(9, 'L-ATU-10', 40, 38, DATE_SUB(CURDATE(), INTERVAL 20 DAY), DATE_ADD(CURDATE(), INTERVAL 500 DAY), 'Góndola Enlatados', 'activo', 'Vencimiento prolongado', 1);

-- 6. Auditoría de Seguridad PIN (Ejemplo de auditoría registrada)
INSERT INTO `auditoria_seguridad_pin` (`accion`, `tabla_afectada`, `registro_id`, `valores_anteriores`, `valores_nuevos`, `motivo`, `usuario_id`, `ip_address`, `autorizado`) VALUES
('MODIFICAR_FECHA_VENCIMIENTO', 'lotes_vencimiento', 1, '{"fecha_vencimiento": "2026-10-10"}', '{"fecha_vencimiento": "2026-10-11"}', 'Ajuste por verificación en paquete físico', 1, '127.0.0.1', 1),
('INTENTO_ELIMINACION_LOTE', 'lotes_vencimiento', 2, NULL, NULL, 'Intento de borrado no autorizado por cajero', 2, '127.0.0.1', 0);

-- 7. Registro de Mercadería Liquidada para Evitar Pérdidas (Ejemplo de éxito comercial)
INSERT INTO `mermas_liquidaciones` (`lote_id`, `tipo_accion`, `cantidad`, `precio_costo_unitario`, `precio_liquidado_unitario`, `dinero_recuperado`, `perdida_total`, `motivo`, `autorizado_por_pin`) VALUES
(1, 'oferta_relampago', 8, 6500.00, 7000.00, 56000.00, 0.00, 'Liquidación exitosa en semáforo rojo 2 días antes de vencer', 1);
