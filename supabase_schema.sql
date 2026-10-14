-- ==============================================================================
-- SISTEMA DE ALERTA DE VENCIMIENTOS PARA DESPENSAS Y MINIMARKETS (EXPIRY)
-- Script de Migración Oficial para SUPABASE (PostgreSQL)
-- Copiar y pegar directamente en el SQL Editor de Supabase y presionar "RUN"
-- ==============================================================================

-- 0. FUNCIONES DE COMPATIBILIDAD CON QUERIES DE MYSQL (DATEDIFF, CURDATE, IFNULL)
CREATE OR REPLACE FUNCTION DATEDIFF(d1 DATE, d2 DATE)
RETURNS INTEGER AS $$
BEGIN
    RETURN (d1 - d2);
END;
$$ LANGUAGE plpgsql IMMUTABLE;

CREATE OR REPLACE FUNCTION CURDATE()
RETURNS DATE AS $$
BEGIN
    RETURN CURRENT_DATE;
END;
$$ LANGUAGE plpgsql STABLE;

CREATE OR REPLACE FUNCTION IFNULL(val ANYELEMENT, default_val ANYELEMENT)
RETURNS ANYELEMENT AS $$
BEGIN
    RETURN COALESCE(val, default_val);
END;
$$ LANGUAGE plpgsql IMMUTABLE;

-- Eliminar vistas y tablas existentes de forma limpia si ya existían
DROP VIEW IF EXISTS v_resumen_dashboard CASCADE;
DROP VIEW IF EXISTS v_semaforo_vencimientos CASCADE;
DROP TABLE IF EXISTS alertas_log CASCADE;
DROP TABLE IF EXISTS mermas_liquidaciones CASCADE;
DROP TABLE IF EXISTS auditoria_seguridad_pin CASCADE;
DROP TABLE IF EXISTS lotes_vencimiento CASCADE;
DROP TABLE IF EXISTS productos CASCADE;
DROP TABLE IF EXISTS categorias CASCADE;
DROP TABLE IF EXISTS usuarios CASCADE;
DROP TABLE IF EXISTS contactos_reporte CASCADE;
DROP TABLE IF EXISTS configuracion CASCADE;

-- ------------------------------------------------------------------------------
-- 1. TABLA: configuracion
-- Parámetros del negocio, semáforo, correos y PIN de seguridad maestro
-- ------------------------------------------------------------------------------
CREATE TABLE configuracion (
    id SERIAL PRIMARY KEY,
    nombre_negocio VARCHAR(150) NOT NULL DEFAULT 'Minimarket & Despensa Express',
    moneda_simbolo VARCHAR(10) NOT NULL DEFAULT 'Gs.',
    moneda_nombre VARCHAR(30) NOT NULL DEFAULT 'Guaraníes',
    dias_alerta_amarillo INT NOT NULL DEFAULT 30,
    dias_alerta_rojo INT NOT NULL DEFAULT 7,
    admin_pin_hash VARCHAR(255) NOT NULL,
    pin_intentos_max INT NOT NULL DEFAULT 5,
    bloqueo_minutos INT NOT NULL DEFAULT 15,
    telefono_alerta VARCHAR(30) NULL,
    notificar_whatsapp SMALLINT NOT NULL DEFAULT 0,
    resend_api_key VARCHAR(255) NULL,
    email_remitente VARCHAR(150) NOT NULL DEFAULT 'Expiry Alertas <onboarding@resend.dev>',
    email_metodo VARCHAR(20) NOT NULL DEFAULT 'RESEND',
    smtp_host VARCHAR(150) NOT NULL DEFAULT 'smtp.gmail.com',
    smtp_port INT NOT NULL DEFAULT 465,
    smtp_seguridad VARCHAR(10) NOT NULL DEFAULT 'ssl',
    smtp_user VARCHAR(150) NOT NULL DEFAULT 'roman.riveros.66@gmail.com',
    smtp_pass VARCHAR(255) NULL,
    notificar_email SMALLINT NOT NULL DEFAULT 1,
    email_notificaciones VARCHAR(150) NULL,
    frecuencia_reporte VARCHAR(30) NOT NULL DEFAULT 'DIARIO',
    hora_reporte VARCHAR(10) NOT NULL DEFAULT '08:00',
    emails_copia TEXT NULL,
    incluir_vencidos SMALLINT NOT NULL DEFAULT 1,
    incluir_rojos SMALLINT NOT NULL DEFAULT 1,
    incluir_amarillos SMALLINT NOT NULL DEFAULT 1,
    incluir_financiero SMALLINT NOT NULL DEFAULT 1,
    incluir_sugerencias SMALLINT NOT NULL DEFAULT 1,
    updated_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- ------------------------------------------------------------------------------
-- 1.B TABLA: contactos_reporte
-- Lista de distribución de contactos para entrega de alertas y reportes
-- ------------------------------------------------------------------------------
CREATE TABLE contactos_reporte (
    id SERIAL PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    cargo VARCHAR(80) NOT NULL DEFAULT 'Colaborador',
    tipo_destinatario VARCHAR(20) NOT NULL DEFAULT 'CC' CHECK (tipo_destinatario IN ('PRINCIPAL', 'CC')),
    activo SMALLINT NOT NULL DEFAULT 1,
    created_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- ------------------------------------------------------------------------------
-- 2. TABLA: usuarios
-- Operadores del sistema (administradores y empleados/cajeros)
-- ------------------------------------------------------------------------------
CREATE TABLE usuarios (
    id SERIAL PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    usuario VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(120) NULL,
    password_hash VARCHAR(255) NOT NULL,
    rol VARCHAR(20) NOT NULL DEFAULT 'empleado' CHECK (rol IN ('admin', 'empleado')),
    activo SMALLINT NOT NULL DEFAULT 1,
    created_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- ------------------------------------------------------------------------------
-- 3. TABLA: categorias
-- Clasificación rápida de mercadería para filtrado ágil
-- ------------------------------------------------------------------------------
CREATE TABLE categorias (
    id SERIAL PRIMARY KEY,
    nombre VARCHAR(80) NOT NULL UNIQUE,
    descripcion VARCHAR(255) NULL,
    icono VARCHAR(50) NOT NULL DEFAULT 'fa-box',
    created_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- ------------------------------------------------------------------------------
-- 4. TABLA: productos
-- Catálogo centralizado con soporte para lector de código de barras (EAN/SKU)
-- ------------------------------------------------------------------------------
CREATE TABLE productos (
    id SERIAL PRIMARY KEY,
    codigo_barra VARCHAR(64) NOT NULL UNIQUE,
    nombre VARCHAR(150) NOT NULL,
    descripcion VARCHAR(255) NULL,
    categoria_id INT NULL REFERENCES categorias(id) ON DELETE SET NULL ON UPDATE CASCADE,
    unidad_medida VARCHAR(20) NOT NULL DEFAULT 'UNIDAD',
    precio_costo NUMERIC(12, 2) NOT NULL DEFAULT 0.00,
    precio_venta NUMERIC(12, 2) NOT NULL DEFAULT 0.00,
    marca VARCHAR(100) NULL,
    stock_minimo_alerta INT NOT NULL DEFAULT 2,
    activo SMALLINT NOT NULL DEFAULT 1,
    created_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_producto_categoria ON productos(categoria_id);
CREATE INDEX idx_producto_nombre ON productos(nombre);

-- ------------------------------------------------------------------------------
-- 5. TABLA: lotes_vencimiento
-- Núcleo de la carga rápida de fechas y control del semáforo por lote
-- ------------------------------------------------------------------------------
CREATE TABLE lotes_vencimiento (
    id SERIAL PRIMARY KEY,
    producto_id INT NOT NULL REFERENCES productos(id) ON DELETE CASCADE ON UPDATE CASCADE,
    codigo_lote VARCHAR(50) NULL,
    cantidad_inicial INT NOT NULL,
    cantidad_actual INT NOT NULL,
    fecha_ingreso DATE NOT NULL,
    fecha_vencimiento DATE NOT NULL,
    ubicacion VARCHAR(100) NOT NULL DEFAULT 'Góndola',
    estado VARCHAR(20) NOT NULL DEFAULT 'activo' CHECK (estado IN ('activo', 'en_oferta', 'vencido', 'agotado', 'retirado')),
    notas TEXT NULL,
    creado_por INT NULL REFERENCES usuarios(id) ON DELETE SET NULL ON UPDATE CASCADE,
    created_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_lote_vencimiento ON lotes_vencimiento(fecha_vencimiento);
CREATE INDEX idx_lote_estado ON lotes_vencimiento(estado);
CREATE INDEX idx_lote_producto_venc ON lotes_vencimiento(producto_id, fecha_vencimiento);

-- ------------------------------------------------------------------------------
-- 6. TABLA: auditoria_seguridad_pin
-- Blindaje de seguridad: Registro inmutable de acciones críticas validadas con PIN
-- ------------------------------------------------------------------------------
CREATE TABLE auditoria_seguridad_pin (
    id SERIAL PRIMARY KEY,
    accion VARCHAR(60) NOT NULL,
    tabla_afectada VARCHAR(50) NULL,
    registro_id INT NULL,
    valores_anteriores TEXT NULL,
    valores_nuevos TEXT NULL,
    motivo VARCHAR(255) NULL,
    usuario_id INT NULL REFERENCES usuarios(id) ON DELETE SET NULL ON UPDATE CASCADE,
    ip_address VARCHAR(45) NULL,
    autorizado SMALLINT NOT NULL DEFAULT 0,
    fecha_hora TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_audit_fecha ON auditoria_seguridad_pin(fecha_hora);
CREATE INDEX idx_audit_accion ON auditoria_seguridad_pin(accion);

-- ------------------------------------------------------------------------------
-- 7. TABLA: mermas_liquidaciones
-- Justificación de valor comercial: Dinero salvado vs perdido
-- ------------------------------------------------------------------------------
CREATE TABLE mermas_liquidaciones (
    id SERIAL PRIMARY KEY,
    lote_id INT NOT NULL REFERENCES lotes_vencimiento(id) ON DELETE CASCADE ON UPDATE CASCADE,
    tipo_accion VARCHAR(40) NOT NULL CHECK (tipo_accion IN ('oferta_relampago', 'descarte_perdida', 'devolucion_proveedor', 'consumo_interno')),
    cantidad INT NOT NULL,
    precio_costo_unitario NUMERIC(12, 2) NOT NULL,
    precio_liquidado_unitario NUMERIC(12, 2) NOT NULL DEFAULT 0.00,
    dinero_recuperado NUMERIC(12, 2) NOT NULL DEFAULT 0.00,
    perdida_total NUMERIC(12, 2) NOT NULL DEFAULT 0.00,
    motivo VARCHAR(255) NULL,
    autorizado_por_pin SMALLINT NOT NULL DEFAULT 1,
    fecha_movimiento TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_mermas_lote ON mermas_liquidaciones(lote_id);
CREATE INDEX idx_mermas_tipo ON mermas_liquidaciones(tipo_accion);
CREATE INDEX idx_mermas_fecha ON mermas_liquidaciones(fecha_movimiento);

-- ------------------------------------------------------------------------------
-- 8. TABLA: alertas_log
-- Historial y cola de avisos emitidos para control de notificaciones
-- ------------------------------------------------------------------------------
CREATE TABLE alertas_log (
    id SERIAL PRIMARY KEY,
    lote_id INT NOT NULL REFERENCES lotes_vencimiento(id) ON DELETE CASCADE ON UPDATE CASCADE,
    nivel_alerta VARCHAR(20) NOT NULL CHECK (nivel_alerta IN ('AMARILLO', 'ROJO', 'VENCIDO')),
    dias_al_vencimiento INT NOT NULL,
    mensaje VARCHAR(255) NOT NULL,
    leido SMALLINT NOT NULL DEFAULT 0,
    created_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_alerta_lote ON alertas_log(lote_id);
CREATE INDEX idx_alerta_leido ON alertas_log(leido);
CREATE INDEX idx_alerta_nivel ON alertas_log(nivel_alerta);

-- ==============================================================================
-- VISTAS INTELIGENTES (SEMÁFORO DE VENCIMIENTOS Y DASHBOARD FINANCIERO)
-- ==============================================================================

-- Vista 1: Semáforo dinámico de vencimientos en tiempo real
CREATE OR REPLACE VIEW v_semaforo_vencimientos AS
SELECT 
    l.id AS lote_id,
    p.id AS producto_id,
    p.codigo_barra,
    p.nombre AS producto_nombre,
    c.nombre AS categoria_nombre,
    p.unidad_medida,
    l.codigo_lote,
    l.ubicacion,
    l.cantidad_actual,
    l.fecha_ingreso,
    l.fecha_vencimiento,
    l.estado AS lote_estado,
    p.precio_costo,
    p.precio_venta,
    (l.cantidad_actual * p.precio_costo) AS dinero_en_riesgo,
    (l.cantidad_actual * p.precio_venta) AS valor_venta_potencial,
    (l.fecha_vencimiento - CURRENT_DATE) AS dias_restantes,
    CASE 
        WHEN (l.fecha_vencimiento - CURRENT_DATE) < 0 THEN 'VENCIDO'
        WHEN (l.fecha_vencimiento - CURRENT_DATE) <= cfg.dias_alerta_rojo THEN 'ROJO'
        WHEN (l.fecha_vencimiento - CURRENT_DATE) <= cfg.dias_alerta_amarillo THEN 'AMARILLO'
        ELSE 'VERDE'
    END AS color_semaforo,
    CASE 
        WHEN (l.fecha_vencimiento - CURRENT_DATE) < 0 THEN 'Mercadería vencida: retirar de góndola'
        WHEN (l.fecha_vencimiento - CURRENT_DATE) = 0 THEN '¡Vence HOY! Rematar o consumir de inmediato'
        WHEN (l.fecha_vencimiento - CURRENT_DATE) <= cfg.dias_alerta_rojo THEN 'Crítico: Vence en pocos días, aplicar oferta relámpago'
        WHEN (l.fecha_vencimiento - CURRENT_DATE) <= cfg.dias_alerta_amarillo THEN 'Alerta preventiva: Ubicar al frente de góndola'
        ELSE 'Óptimo: Tiempo de vida útil seguro'
    END AS sugerencia_accion
FROM lotes_vencimiento l
INNER JOIN productos p ON l.producto_id = p.id
LEFT JOIN categorias c ON p.categoria_id = c.id
INNER JOIN configuracion cfg ON cfg.id = 1
WHERE l.cantidad_actual > 0 
  AND l.estado IN ('activo', 'en_oferta');

-- Vista 2: Resumen ejecutivo del Dashboard
CREATE OR REPLACE VIEW v_resumen_dashboard AS
SELECT 
    COUNT(DISTINCT v.producto_id) AS total_productos_en_seguimiento,
    COUNT(v.lote_id) AS total_lotes_activos,
    COALESCE(SUM(CASE WHEN v.color_semaforo = 'ROJO' THEN 1 ELSE 0 END), 0) AS total_lotes_rojos,
    COALESCE(SUM(CASE WHEN v.color_semaforo = 'AMARILLO' THEN 1 ELSE 0 END), 0) AS total_lotes_amarillos,
    COALESCE(SUM(CASE WHEN v.color_semaforo = 'VERDE' THEN 1 ELSE 0 END), 0) AS total_lotes_verdes,
    COALESCE(SUM(CASE WHEN v.color_semaforo = 'VENCIDO' THEN 1 ELSE 0 END), 0) AS total_lotes_vencidos,
    COALESCE(SUM(CASE WHEN v.color_semaforo IN ('ROJO', 'AMARILLO') THEN v.dinero_en_riesgo ELSE 0 END), 0) AS capital_en_riesgo_total,
    COALESCE(SUM(CASE WHEN v.color_semaforo = 'VENCIDO' THEN v.dinero_en_riesgo ELSE 0 END), 0) AS capital_vencido_total,
    COALESCE(SUM(v.dinero_en_riesgo), 0) AS capital_total_inventario
FROM v_semaforo_vencimientos v;

-- ==============================================================================
-- DATOS INICIALES SEMILLA (SEED DATA)
-- ==============================================================================

-- 1. Configuración por defecto (PIN Administrador: 1234)
INSERT INTO configuracion (id, nombre_negocio, moneda_simbolo, moneda_nombre, dias_alerta_amarillo, dias_alerta_rojo, admin_pin_hash, telefono_alerta, notificar_whatsapp)
VALUES (1, 'Minimarket & Despensa El Ahorro', 'Gs.', 'Guaraníes', 30, 7, '$2y$10$FHm1LO5XDNPKThqvOYsXwekQrZnrEJ0ZFggPDWTDTTjXNsZ5gr6cy', '+595981000111', 1)
ON CONFLICT (id) DO NOTHING;

-- 2. Usuarios por defecto (admin: admin123 / cajero1: 123456)
INSERT INTO usuarios (id, nombre, usuario, password_hash, rol, activo) VALUES
(1, 'Administrador Propietario', 'admin', '$2y$10$yV.F3gOGX/MUNva4r9AVnuDYdnuEm8I3RuIDzEgz6a2tjNQRWBn9K', 'admin', 1),
(2, 'Cajero Turno Tarde', 'cajero1', '$2y$10$yV.F3gOGX/MUNva4r9AVnuDYdnuEm8I3RuIDzEgz6a2tjNQRWBn9K', 'empleado', 1)
ON CONFLICT (usuario) DO NOTHING;

-- 3. Categorías típicas
INSERT INTO categorias (id, nombre, descripcion, icono) VALUES
(1, 'Lácteos y Yogures', 'Leches, quesos, yogures y postres refrigerados', 'fa-cheese'),
(2, 'Fiambrería y Embutidos', 'Jamones, salchichas, salames y fiambres', 'fa-drumstick-bite'),
(3, 'Panificados y Bollería', 'Pan lactal, panes de hamburguesa, medialunas', 'fa-bread-slice'),
(4, 'Bebidas y Jugos', 'Gaseosas, jugos pasteurizados, cervezas', 'fa-wine-bottle'),
(5, 'Galletitas y Snacks', 'Galletas dulces, saladas, papas fritas y snacks', 'fa-cookie-bite'),
(6, 'Pastas y Salsas', 'Fideos, tapas de empanadas, salsa de tomate', 'fa-utensils'),
(7, 'Golosinas y Chocolates', 'Chocolates, alfajores, caramelos', 'fa-candy-cane'),
(8, 'Enlatados y Conservas', 'Atún, choclo, arvejas, duraznos al natural', 'fa-box-open')
ON CONFLICT (nombre) DO NOTHING;

-- 4. Productos de ejemplo
INSERT INTO productos (id, codigo_barra, nombre, descripcion, categoria_id, unidad_medida, precio_costo, precio_venta, marca, stock_minimo_alerta, activo) VALUES
(1, '7840001001015', 'Yogur Frutilla Sache 1L', 'Yogur entero bebible sabor frutilla', 1, 'UNIDAD', 6500.00, 9000.00, 'Lactolanda', 5, 1),
(2, '7840001001022', 'Queso Sandwich Feteado 200g', 'Queso tipo barra en fetas', 1, 'UNIDAD', 12000.00, 16500.00, 'Trebol', 4, 1),
(3, '7840002002012', 'Pan Lactal Blanco 500g', 'Pan de molde tradicional', 3, 'UNIDAD', 7000.00, 10500.00, 'Bimbo', 3, 1),
(4, '7840003003019', 'Salchichas Tipo Viena 6u', 'Paquete de salchichas cocidas', 2, 'UNIDAD', 5500.00, 8000.00, 'Ochsi', 5, 1),
(5, '7840004004016', 'Tapa para Empanadas x 12', 'Masa hojaldrada para horno', 6, 'UNIDAD', 4500.00, 6500.00, 'La Criolla', 6, 1),
(6, '7840005005013', 'Gaseosa Cola 2L Descartable', 'Bebida carbonatada clásica', 4, 'UNIDAD', 9000.00, 12500.00, 'Coca-Cola', 10, 1),
(7, '7840006006010', 'Galletitas Rellenas Vainilla 120g', 'Galletita dulce con crema de vainilla', 5, 'UNIDAD', 3000.00, 4500.00, 'Oreo', 8, 1),
(8, '7840007007017', 'Alfajor Triple Chocolate 60g', 'Alfajor relleno con dulce de leche', 7, 'UNIDAD', 3500.00, 5500.00, 'Havanna', 10, 1),
(9, '7840008008014', 'Atún al Natural en Lata 170g', 'Lomo de atún desmenuzado en agua', 8, 'UNIDAD', 8500.00, 12000.00, 'Gomes da Costa', 4, 1)
ON CONFLICT (codigo_barra) DO NOTHING;

-- 5. Lotes con fechas calculadas dinámicamente
INSERT INTO lotes_vencimiento (id, producto_id, codigo_lote, cantidad_inicial, cantidad_actual, fecha_ingreso, fecha_vencimiento, ubicacion, estado, notas, creado_por) VALUES
(1, 1, 'L-YOG-01', 12, 4, CURRENT_DATE - INTERVAL '18 days', CURRENT_DATE - INTERVAL '2 days', 'Heladera Lácteos 1', 'activo', 'Retirar urgente', 1),
(2, 1, 'L-YOG-02', 15, 8, CURRENT_DATE - INTERVAL '10 days', CURRENT_DATE + INTERVAL '3 days', 'Heladera Lácteos 1', 'activo', 'Liquidación 2x1', 1),
(3, 3, 'L-PAN-88', 10, 7, CURRENT_DATE - INTERVAL '4 days', CURRENT_DATE + INTERVAL '5 days', 'Góndola Panificados', 'activo', 'Mover al frente', 2),
(4, 4, 'L-SAL-12', 20, 16, CURRENT_DATE - INTERVAL '5 days', CURRENT_DATE + INTERVAL '14 days', 'Heladera Fiambrería', 'activo', 'Vigilar rotación FIFO', 2),
(5, 2, 'L-QUE-45', 18, 12, CURRENT_DATE - INTERVAL '8 days', CURRENT_DATE + INTERVAL '24 days', 'Heladera Lácteos 2', 'activo', 'Rotar al frente', 1),
(6, 5, 'L-TAP-09', 24, 20, CURRENT_DATE - INTERVAL '3 days', CURRENT_DATE + INTERVAL '28 days', 'Heladera Masas', 'activo', 'Stock regular', 1),
(7, 6, 'L-GAS-77', 30, 26, CURRENT_DATE - INTERVAL '15 days', CURRENT_DATE + INTERVAL '90 days', 'Góndola Bebidas', 'activo', 'Fecha segura', 1),
(8, 7, 'L-GAL-33', 25, 25, CURRENT_DATE - INTERVAL '10 days', CURRENT_DATE + INTERVAL '120 days', 'Estantería Golosinas', 'activo', 'Stock nuevo', 2),
(9, 9, 'L-ATU-10', 40, 38, CURRENT_DATE - INTERVAL '20 days', CURRENT_DATE + INTERVAL '500 days', 'Góndola Enlatados', 'activo', 'Vencimiento largo', 1)
ON CONFLICT (id) DO NOTHING;

-- Sincronizar secuencias para inserciones futuras automáticas
SELECT setval('configuracion_id_seq', (SELECT COALESCE(MAX(id), 1) FROM configuracion));
SELECT setval('usuarios_id_seq', (SELECT COALESCE(MAX(id), 1) FROM usuarios));
SELECT setval('categorias_id_seq', (SELECT COALESCE(MAX(id), 1) FROM categorias));
SELECT setval('productos_id_seq', (SELECT COALESCE(MAX(id), 1) FROM productos));
SELECT setval('lotes_vencimiento_id_seq', (SELECT COALESCE(MAX(id), 1) FROM lotes_vencimiento));
