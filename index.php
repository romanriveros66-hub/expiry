<?php
/**
 * Sistema de Alerta de Vencimientos para Despensas y Minimarkets
 * Landing Page Profesional + Aplicación de Gestión Semáforo
 * Seguridad: OWASP Top 10 / VulnScope Compliance
 */
require_once __DIR__ . '/api/security_helper.php';

// Asegurar cabeceras de Referrer-Policy en respuestas directas
if (!headers_sent()) {
    header('Referrer-Policy: strict-origin-when-cross-origin');
}

// Calcular hash SHA-384 dinámico para Subresource Integrity (SRI) del script de la aplicación
$appJsPath = __DIR__ . '/js/app.js';
$appJsHash = file_exists($appJsPath) ? 'sha384-' . base64_encode(hash('sha384', file_get_contents($appJsPath), true)) : '';
$appJsVer  = file_exists($appJsPath) ? filemtime($appJsPath) : time();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <meta name="referrer" content="strict-origin-when-cross-origin">
    <title>Expiry — El Semáforo de Vencimientos para Despensas y Minimarkets</title>
    <meta name="description" content="Evitá pérdidas tirando mercadería: el semáforo inteligente de vencimientos para tu negocio. Carga rápida de lotes y seguridad blindada con PIN de Administrador.">
    
    <!-- Fuentes Google: Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com" crossorigin>
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet" crossorigin="anonymous">
    
    <!-- Iconografía FontAwesome 6 (CDN Seguro con SRI) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" integrity="sha512-z3gLpd7yknf1YoNbCzqRKc4qyor8gaKU1qmn+CShxbuBusANI9QpRohGBreCFkKxLhei6S9CQXFEbbKuqLg0DA==" crossorigin="anonymous">
    
    <!-- Estilos de la Aplicación -->
    <link rel="stylesheet" href="css/styles.css?v=<?= time() ?>">
</head>
<body>

    <!-- =========================================================================
         1. VISTA LANDING PAGE PÚBLICA
         ========================================================================= -->
    <!-- =========================================================================
         1. VISTA LANDING PAGE PÚBLICA (BASE BLANCA + DETALLES CELESTES ANIMADOS)
         ========================================================================= -->
    <section id="viewLanding" class="view-landing-white">
        <!-- Barra de Navegación Flotante Superior -->
        <header class="landing-header-white">
            <div class="landing-nav-container">
                <!-- Brand Logo con Luces del Semáforo -->
                <a href="#" class="landing-brand-wrap" onclick="location.reload(); return false;">
                    <div class="landing-brand-dots">
                        <span class="brand-dot dot-red"></span>
                        <span class="brand-dot dot-amber"></span>
                        <span class="brand-dot dot-green"></span>
                    </div>
                    <span class="landing-brand-title">Expiry</span>
                    <span class="landing-version-tag">v2.4</span>
                </a>

                <!-- Enlaces de Navegación Interna -->
                <nav class="landing-nav-menu">
                    <a href="#como-funciona" class="landing-nav-item">Cómo Funciona</a>
                    <a href="#motor-semaforo" class="landing-nav-item">Motor Predictivo</a>
                    <a href="#testimonios" class="landing-nav-item">Testimonios</a>
                    <a href="#faq" class="landing-nav-item">Preguntas Frecuentes</a>
                </nav>

                <!-- Botones de Acción / Auth -->
                <div class="landing-nav-actions">
                    <button type="button" class="btn-landing-subtle" onclick="App.openVulnScopeModal()">
                        <i class="fas fa-shield-halved text-celeste"></i>
                        <span>VulnScope</span>
                    </button>
                    <button type="button" class="btn-landing-ghost" onclick="App.openAuthModal('login')">
                        <i class="fas fa-sign-in-alt"></i>
                        <span>Iniciar Sesión</span>
                    </button>
                    <button type="button" class="btn-landing-primary" onclick="App.openAuthModal('register')">
                        <i class="fas fa-rocket"></i>
                        <span>Comenzar Ahora</span>
                    </button>
                </div>
            </div>
        </header>

        <!-- ==================== HERO SECTION ==================== -->
        <section class="landing-hero-wrap">
            <div class="landing-container">
                <div class="landing-hero-center">
                    <!-- Live Pill Animado -->
                    <div class="landing-live-pill">
                        <span class="pulse-dot-green"></span>
                        <span class="pill-text-content">🚀 EL 8% AL 15% DE TU STOCK PERECEDERO YA NO SE PIERDE</span>
                    </div>

                    <!-- Titular de Gran Impacto -->
                    <h1 class="landing-hero-headline">
                        Dejá de tirar plata a la basura: <br class="hidden sm:inline" />
                        <span class="text-celeste-gradient">protegé tu mercadería</span> antes de que venza
                    </h1>

                    <!-- Bajada Comercial Clara -->
                    <p class="landing-hero-subhead">
                        El sistema web inteligente para despensas, autoservicios y minimarkets que clasifica tus lotes por semáforo, activa ofertas relámpago oportunas y alerta a todo tu equipo antes de abrir la persiana.
                    </p>

                    <!-- CTAs Principales -->
                    <div class="landing-hero-buttons">
                        <button type="button" class="btn-landing-primary btn-cta-prominent" onclick="App.openAuthModal('register')">
                            <span>Empezar Prueba Gratis de 14 Días</span>
                            <i class="fas fa-arrow-right"></i>
                        </button>
                        <button type="button" class="btn-landing-secondary btn-cta-prominent" onclick="App.loginAsDemo('admin')">
                            <i class="fas fa-play text-celeste"></i>
                            <span>Probar Demo en Vivo</span>
                        </button>
                    </div>

                    <!-- Micro-Confianza -->
                    <div class="landing-trust-row">
                        <span class="trust-badge-item"><i class="fas fa-check-circle text-celeste"></i> Sin tarjeta de crédito</span>
                        <span class="trust-badge-item"><i class="fas fa-check-circle text-celeste"></i> Funciona en celular o PC</span>
                        <span class="trust-badge-item"><i class="fas fa-check-circle text-celeste"></i> Carga express en 3 segundos</span>
                    </div>
                </div>

                <!-- Cockpit Visual Mockup Animado en Vivo -->
                <div class="landing-cockpit-frame">
                    <!-- Topbar de la Consola Mockup -->
                    <div class="cockpit-header-bar">
                        <div class="cockpit-dots-group">
                            <span class="mock-dot dot-red"></span>
                            <span class="mock-dot dot-amber"></span>
                            <span class="mock-dot dot-green"></span>
                            <span class="cockpit-title-label">EXPIRY INTELLIGENCE CONSOLE — SUCURSAL 01 (CÓRDOBA CENTRO)</span>
                        </div>
                        <div class="cockpit-header-right">
                            <span class="cockpit-pill-audit">
                                <span class="pulse-dot-green"></span> MODO AUDITORÍA ACTIVO
                            </span>
                            <span class="cockpit-pin-badge">PIN: ****</span>
                        </div>
                    </div>

                    <!-- 4 Tarjetas de Métricas Cockpit -->
                    <div class="cockpit-kpis-grid">
                        <div class="cockpit-kpi-card border-kpi-blue">
                            <div class="kpi-card-header">
                                <span class="kpi-tag-label">CAPITAL PROTEGIDO TOTAL</span>
                                <i class="fas fa-shield-alt text-celeste"></i>
                            </div>
                            <div class="kpi-card-number text-dark">$1.420.500</div>
                            <div class="kpi-card-meta text-celeste">
                                <i class="fas fa-arrow-trend-up"></i> 100% de lotes inventariados
                            </div>
                        </div>

                        <div class="cockpit-kpi-card border-kpi-red halo-card-red">
                            <div class="kpi-card-header">
                                <span class="kpi-tag-label text-red">LOTES EN RIESGO ROJO</span>
                                <span class="pulse-dot-red"></span>
                            </div>
                            <div class="kpi-card-number text-red">7 lotes</div>
                            <div class="kpi-card-meta text-red">
                                <i class="fas fa-triangle-exclamation"></i> Vencen en ≤ 7 días
                            </div>
                        </div>

                        <div class="cockpit-kpi-card border-kpi-amber halo-card-amber">
                            <div class="kpi-card-header">
                                <span class="kpi-tag-label text-amber">OFERTAS ACTIVADAS HOY</span>
                                <i class="fas fa-bolt text-amber"></i>
                            </div>
                            <div class="kpi-card-number text-dark">14</div>
                            <div class="kpi-card-meta text-amber">
                                <i class="fas fa-tags"></i> Liquidación 35% y 2x1
                            </div>
                        </div>

                        <div class="cockpit-kpi-card border-kpi-green halo-card-green">
                            <div class="kpi-card-header">
                                <span class="kpi-tag-label text-green">DINERO SALVADO ESTE MES</span>
                                <i class="fas fa-circle-check text-green"></i>
                            </div>
                            <div class="kpi-card-number text-green">$385.200</div>
                            <div class="kpi-card-meta text-green">
                                <i class="fas fa-chart-line"></i> Recuperación neta de margen
                            </div>
                        </div>
                    </div>

                    <!-- Tabla de Góndola en Vivo -->
                    <div class="cockpit-table-responsive">
                        <table class="cockpit-inventory-table">
                            <thead>
                                <tr>
                                    <th>CÓDIGO / PRODUCTO</th>
                                    <th>UBICACIÓN</th>
                                    <th>ESTADO SEMÁFORO</th>
                                    <th>DÍAS RESTANTES</th>
                                    <th style="text-align: right;">ACCIÓN SUGERIDA</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>
                                        <div class="prod-cell-content">
                                            <div class="prod-icon-box bg-red-soft text-red">
                                                <i class="fas fa-barcode"></i>
                                            </div>
                                            <div>
                                                <strong>Leche Entera Clásica 1L (La Serenísima)</strong>
                                                <span class="prod-code-sub">EAN: 7790123456789</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td><span class="ubicacion-pill">Heladera Lácteos 02</span></td>
                                    <td>
                                        <span class="badge-semaforo-table table-rojo">
                                            <span class="pulse-dot-red"></span> ROJO CRÍTICO
                                        </span>
                                    </td>
                                    <td><span class="font-bold text-red">3 días (12/Nov)</span></td>
                                    <td style="text-align: right;">
                                        <span class="action-tag tag-oferta">
                                            <i class="fas fa-fire"></i> 35% OFF Relámpago
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <div class="prod-cell-content">
                                            <div class="prod-icon-box bg-amber-soft text-amber">
                                                <i class="fas fa-barcode"></i>
                                            </div>
                                            <div>
                                                <strong>Yogur Firme Vainilla 190g x2</strong>
                                                <span class="prod-code-sub">EAN: 7790987654321</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td><span class="ubicacion-pill">Góndola Refrigerada</span></td>
                                    <td>
                                        <span class="badge-semaforo-table table-amarillo">
                                            <span class="dot-amber-static"></span> AMARILLO PREVENTIVO
                                        </span>
                                    </td>
                                    <td><span class="font-bold text-amber">12 días (21/Nov)</span></td>
                                    <td style="text-align: right;">
                                        <span class="action-tag tag-fifo">
                                            <i class="fas fa-rotate"></i> Rotar al frente (FIFO)
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <div class="prod-cell-content">
                                            <div class="prod-icon-box bg-green-soft text-green">
                                                <i class="fas fa-barcode"></i>
                                            </div>
                                            <div>
                                                <strong>Galletitas Chips Chocolate 250g</strong>
                                                <span class="prod-code-sub">EAN: 7790456123789</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td><span class="ubicacion-pill">Pasillo Galletitería A3</span></td>
                                    <td>
                                        <span class="badge-semaforo-table table-verde">
                                            <span class="dot-green-static"></span> VERDE ÓPTIMO
                                        </span>
                                    </td>
                                    <td><span class="font-bold text-green">45 días (24/Dic)</span></td>
                                    <td style="text-align: right;">
                                        <span class="text-muted" style="font-size: 0.82rem;">Sin acción requerida</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Simulador Interactivo del Semáforo Integrado -->
                    <div class="cockpit-simulator-container">
                        <div class="simulator-top-row">
                            <div class="sim-title-group">
                                <span class="sim-badge-tag"><i class="fas fa-sliders"></i> SIMULADOR EN VIVO</span>
                                <strong>Deslizá los días para comprobar cómo reacciona el semáforo inteligente:</strong>
                            </div>
                            <div class="sim-live-indicator">
                                <span id="simColorBadge" class="sim-result-badge badge-rojo-glow">🔴 ROJO CRÍTICO</span>
                                <strong id="simDaysLabel" class="sim-days-display">4 días</strong>
                            </div>
                        </div>

                        <div class="sim-slider-box">
                            <input type="range" id="simDaysSlider" min="0" max="60" value="4" class="custom-range-slider" />
                            <div class="sim-slider-markers">
                                <span>0d (Vencido)</span>
                                <span>7d (Límite Rojo)</span>
                                <span>30d (Límite Amarillo)</span>
                                <span>+60d (Verde Seguro)</span>
                            </div>
                        </div>

                        <div class="sim-advice-banner" id="simAdviceCard">
                            <i class="fas fa-lightbulb sim-advice-icon"></i>
                            <span id="simAdviceText">
                                Zona Crítica: Vence en pocos días. Recomendación: Aplicar oferta relámpago con un toque o liquidar al costo para recuperar el capital invertido.
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ==================== STATS Y PRUEBA SOCIAL ==================== -->
        <section class="landing-stats-bar">
            <div class="landing-container">
                <div class="landing-stats-grid">
                    <div class="stat-item-box">
                        <div class="stat-big-number text-celeste">+450</div>
                        <div class="stat-small-text">COMERCIOS Y DESPENSAS ACTIVAS</div>
                    </div>
                    <div class="stat-item-box">
                        <div class="stat-big-number text-dark">Hasta 70%</div>
                        <div class="stat-small-text">CAPITAL MERMADO RECUPERADO</div>
                    </div>
                    <div class="stat-item-box">
                        <div class="stat-big-number text-celeste">&lt; 3 seg</div>
                        <div class="stat-small-text">POR CARGA CON CÓDIGO EAN</div>
                    </div>
                    <div class="stat-item-box">
                        <div class="stat-big-number text-dark">&lt; 10 días</div>
                        <div class="stat-small-text">RETORNO TOTAL DE INVERSIÓN</div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ==================== EL PROBLEMA VS LA SOLUCIÓN ==================== -->
        <section class="landing-content-section bg-light-soft" id="como-funciona">
            <div class="landing-container">
                <div class="section-title-wrapper">
                    <span class="section-badge-pill">COMPARATIVA OPERATIVA</span>
                    <h2 class="section-title-large">La brecha entre perder dinero y blindar tu góndola</h2>
                    <p class="section-desc-text">
                        Las libretas manuscritas y planillas de cálculo quedan obsoletas frente a la velocidad del vencimiento en el punto de venta.
                    </p>
                </div>

                <div class="comparison-cards-grid">
                    <!-- Tarjeta 1: Método Tradicional -->
                    <div class="comparison-card card-problem">
                        <div class="card-head-row">
                            <div class="head-icon-circle bg-red-soft text-red">
                                <i class="fas fa-times-circle"></i>
                            </div>
                            <div>
                                <span class="card-kicker text-red">EL MÉTODO TRADICIONAL</span>
                                <h3 class="card-title-text">Cuadernos y planillas manuales</h3>
                            </div>
                        </div>
                        <ul class="comparison-bullets">
                            <li>
                                <span class="bullet-x">✕</span>
                                <span><strong>Pérdidas del 8% al 15%:</strong> Mercadería que se descubre vencida recién cuando el cliente la lleva a la caja o en reclamos.</span>
                            </li>
                            <li>
                                <span class="bullet-x">✕</span>
                                <span><strong>Planillas abandonadas:</strong> Los repositores se olvidan de mirar el Excel o el cuaderno cuando el local se llena.</span>
                            </li>
                            <li>
                                <span class="bullet-x">✕</span>
                                <span><strong>Descuentos tardíos:</strong> Intentan liquidar cuando ya quedan 24 horas y nadie compra el lote.</span>
                            </li>
                            <li>
                                <span class="bullet-x">✕</span>
                                <span><strong>Riesgo de multas sanitarias:</strong> Inspecciones de bromatología que clausuran por 1 paquete olvidado en el fondo.</span>
                            </li>
                        </ul>
                    </div>

                    <!-- Tarjeta 2: Con Expiry Intelligence -->
                    <div class="comparison-card card-solution">
                        <div class="card-head-row">
                            <div class="head-icon-circle bg-celeste-soft text-celeste">
                                <i class="fas fa-circle-check"></i>
                            </div>
                            <div>
                                <span class="card-kicker text-celeste">CON EXPIRY INTELLIGENCE</span>
                                <h3 class="card-title-text">El Semáforo Digital en Cada Celular</h3>
                            </div>
                        </div>
                        <ul class="comparison-bullets">
                            <li>
                                <span class="bullet-check text-celeste">✓</span>
                                <span><strong>Prioridad Visual Instantánea:</strong> El semáforo rojo/amarillo/verde indica exactamente qué tocar primero en la góndola.</span>
                            </li>
                            <li>
                                <span class="bullet-check text-celeste">✓</span>
                                <span><strong>Ofertas Relámpago a Tiempo:</strong> Activá un 2x1 o 30% OFF con margen recuperado 7 días antes de la fecha límite.</span>
                            </li>
                            <li>
                                <span class="bullet-check text-celeste">✓</span>
                                <span><strong>Reporte Matutino Automático:</strong> Recibí en tu WhatsApp y Gmail el listado de lo que vence hoy antes de levantar la persiana.</span>
                            </li>
                            <li>
                                <span class="bullet-check text-celeste">✓</span>
                                <span><strong>Auditoría Blindada con PIN:</strong> Ningún empleado da de baja mercadería sin registrar código y motivo exacto.</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </section>

        <!-- ==================== MOTOR DEL SEMÁFORO (4 ESTADOS) ==================== -->
        <section class="landing-content-section bg-white" id="motor-semaforo">
            <div class="landing-container">
                <div class="section-title-wrapper">
                    <span class="section-badge-pill">ALGORITMO PREDICTIVO</span>
                    <h2 class="section-title-large">Cómo Funciona el Motor del Semáforo</h2>
                    <p class="section-desc-text">
                        Cada producto ingresado es segmentado en tiempo real según sus días críticos de vida útil en góndola.
                    </p>
                </div>

                <div class="semaforo-states-grid">
                    <!-- Estado 1: Rojo Crítico -->
                    <div class="state-card border-red">
                        <div class="state-card-top">
                            <span class="state-badge-pill badge-pill-red">
                                <span class="pulse-dot-red"></span> ≤ 7 DÍAS
                            </span>
                            <div class="state-icon-circle bg-red-soft text-red">
                                <i class="fas fa-fire"></i>
                            </div>
                        </div>
                        <h3 class="state-card-title text-red">🔴 Rojo Crítico</h3>
                        <p class="state-card-desc">
                            Zona de máximo riesgo. El sistema sugiere y activa automáticamente la <strong>Oferta Relámpago</strong> (-30%, -40% o 2x1) para recuperar el costo mayorista en 48hs.
                        </p>
                        <div class="state-card-action text-red">
                            <i class="fas fa-bolt"></i> ACCIONAR: Liquidación inmediata
                        </div>
                    </div>

                    <!-- Estado 2: Amarillo Preventivo -->
                    <div class="state-card border-amber">
                        <div class="state-card-top">
                            <span class="state-badge-pill badge-pill-amber">
                                <span class="dot-amber-static"></span> 8 A 30 DÍAS
                            </span>
                            <div class="state-icon-circle bg-amber-soft text-amber">
                                <i class="fas fa-clock"></i>
                            </div>
                        </div>
                        <h3 class="state-card-title text-amber">🟡 Amarillo Preventivo</h3>
                        <p class="state-card-desc">
                            Zona de rotación. Genera una orden de reposición <strong>FIFO (First In, First Out)</strong> para mover estos lotes a la primera fila de la heladera o puntera de pasillo.
                        </p>
                        <div class="state-card-action text-amber">
                            <i class="fas fa-rotate"></i> ACCIONAR: Mover al frente
                        </div>
                    </div>

                    <!-- Estado 3: Verde Óptimo -->
                    <div class="state-card border-green">
                        <div class="state-card-top">
                            <span class="state-badge-pill badge-pill-green">
                                <span class="dot-green-static"></span> &gt; 30 DÍAS
                            </span>
                            <div class="state-icon-circle bg-green-soft text-green">
                                <i class="fas fa-shield-check"></i>
                            </div>
                        </div>
                        <h3 class="state-card-title text-green">🟢 Verde Óptimo</h3>
                        <p class="state-card-desc">
                            Vida útil completamente segura. Stock almacenado y exhibido sin riesgo financiero. No genera alertas ni distrae la atención del personal.
                        </p>
                        <div class="state-card-action text-green">
                            <i class="fas fa-check"></i> ACCIONAR: Monitoreo pasivo
                        </div>
                    </div>

                    <!-- Estado 4: Retiro Inmediato -->
                    <div class="state-card border-dark">
                        <div class="state-card-top">
                            <span class="state-badge-pill badge-pill-dark">
                                <i class="fas fa-ban text-red"></i> VENCIDO
                            </span>
                            <div class="state-icon-circle bg-dark-soft text-dark">
                                <i class="fas fa-triangle-exclamation"></i>
                            </div>
                        </div>
                        <h3 class="state-card-title text-dark">⛔ Retiro Inmediato</h3>
                        <p class="state-card-desc">
                            Bloqueo preventivo total. Emite alerta en pantalla para retiro de góndola urgente, blindando al comercio ante multas de inspecciones bromatológicas.
                        </p>
                        <div class="state-card-action text-dark">
                            <i class="fas fa-lock"></i> ACCIONAR: Baja con PIN y acta
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ==================== TESTIMONIOS Y SOCIAL PROOF ==================== -->
        <section class="landing-content-section bg-light-soft" id="testimonios">
            <div class="landing-container">
                <div class="section-title-wrapper">
                    <span class="section-badge-pill">CASOS REALES</span>
                    <h2 class="section-title-large">Comercios que ya dejaron de tirar mercadería</h2>
                    <p class="section-desc-text">
                        Despensas y autoservicios de barrio que multiplicaron su rentabilidad mensual.
                    </p>
                </div>

                <div class="testimonials-cards-grid">
                    <!-- Testimonio 1 -->
                    <div class="testimonial-box">
                        <div class="testimonial-stars-row">
                            <i class="fas fa-star text-amber"></i>
                            <i class="fas fa-star text-amber"></i>
                            <i class="fas fa-star text-amber"></i>
                            <i class="fas fa-star text-amber"></i>
                            <i class="fas fa-star text-amber"></i>
                        </div>
                        <p class="testimonial-text">
                            "Tirábamos yogures, quesos cremosos y fiambres todas las semanas. Con el semáforo en rojo, 5 días antes pongo un cartel de 2x1 y salvo el costo exacto. Recuperé más de $280.000 el primer mes."
                        </p>
                        <div class="testimonial-user-row">
                            <div class="avatar-initials bg-celeste-soft text-celeste">MG</div>
                            <div>
                                <strong class="user-name-title">Martín Gómez</strong>
                                <span class="user-store-sub">Minimarket El Ombú (Rosario)</span>
                            </div>
                        </div>
                    </div>

                    <!-- Testimonio 2 -->
                    <div class="testimonial-box">
                        <div class="testimonial-stars-row">
                            <i class="fas fa-star text-amber"></i>
                            <i class="fas fa-star text-amber"></i>
                            <i class="fas fa-star text-amber"></i>
                            <i class="fas fa-star text-amber"></i>
                            <i class="fas fa-star text-amber"></i>
                        </div>
                        <p class="testimonial-text">
                            "El escáner desde el celular del repositor es un golazo. No tuvimos que comprar pistolas láser caras. En 3 segundos leen el código de barra del cartón de leche y ya queda registrado."
                        </p>
                        <div class="testimonial-user-row">
                            <div class="avatar-initials bg-celeste-soft text-celeste">LR</div>
                            <div>
                                <strong class="user-name-title">Laura Rossi</strong>
                                <span class="user-store-sub">Autoservicio San Cayetano (Córdoba)</span>
                            </div>
                        </div>
                    </div>

                    <!-- Testimonio 3 -->
                    <div class="testimonial-box">
                        <div class="testimonial-stars-row">
                            <i class="fas fa-star text-amber"></i>
                            <i class="fas fa-star text-amber"></i>
                            <i class="fas fa-star text-amber"></i>
                            <i class="fas fa-star text-amber"></i>
                            <i class="fas fa-star text-amber"></i>
                        </div>
                        <p class="testimonial-text">
                            "El PIN de seguridad nos ordenó el equipo. Antes desaparecía mercadería bajo la excusa de 'se venció y la tiré'. Ahora todo movimiento queda registrado con hora y motivo."
                        </p>
                        <div class="testimonial-user-row">
                            <div class="avatar-initials bg-celeste-soft text-celeste">EB</div>
                            <div>
                                <strong class="user-name-title">Esteban Benítez</strong>
                                <span class="user-store-sub">Cadena La Esquina (3 Sucursales, Bs. As.)</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ==================== PREGUNTAS FRECUENTES (FAQ) ==================== -->
        <section class="landing-content-section bg-white" id="faq">
            <div class="landing-container max-w-4xl">
                <div class="section-title-wrapper">
                    <span class="section-badge-pill">RESPUESTAS RÁPIDAS</span>
                    <h2 class="section-title-large">Preguntas Frecuentes</h2>
                    <p class="section-desc-text">
                        Respuestas claras para empezar a proteger tu mercadería sin dudas técnicas.
                    </p>
                </div>

                <div class="faq-accordion-group">
                    <!-- Q1 -->
                    <div class="faq-card-item" onclick="this.classList.toggle('open')">
                        <div class="faq-header-bar">
                            <span class="faq-question-text">¿Necesito comprar una lectora de código de barras especial?</span>
                            <i class="fas fa-chevron-down faq-arrow-icon"></i>
                        </div>
                        <div class="faq-content-body">
                            <p>No. Expiry funciona perfectamente con la cámara estándar de cualquier teléfono celular Android o iPhone para escanear los códigos de barra EAN o UPC en décimas de segundo. Si ya tenés una lectora USB o Bluetooth en la caja, también es 100% compatible sin instalar programas adicionales.</p>
                        </div>
                    </div>

                    <!-- Q2 -->
                    <div class="faq-card-item" onclick="this.classList.toggle('open')">
                        <div class="faq-header-bar">
                            <span class="faq-question-text">¿Cuánto tiempo demora cargar la mercadería que llega del proveedor?</span>
                            <i class="fas fa-chevron-down faq-arrow-icon"></i>
                        </div>
                        <div class="faq-content-body">
                            <p>Menos de 3 segundos por lote. El sistema ya cuenta con base de datos de productos de almacén. Al escanear, el nombre se autocompleta y seleccionás la fecha con un solo clic (+3d, +7d, +15d, etc.) según la fecha impresa en el paquete.</p>
                        </div>
                    </div>

                    <!-- Q3 -->
                    <div class="faq-card-item" onclick="this.classList.toggle('open')">
                        <div class="faq-header-bar">
                            <span class="faq-question-text">¿Cómo ayuda a evitar multas de Bromatología?</span>
                            <i class="fas fa-chevron-down faq-arrow-icon"></i>
                        </div>
                        <div class="faq-content-body">
                            <p>La alerta 'Retiro Inmediato' (estado rojo vencido) te notifica con aviso sonoro y visual para retirar cualquier producto de la góndola antes de la fecha límite, impidiendo que quede mercadería caducada expuesta a clientes o inspectores sanitarios.</p>
                        </div>
                    </div>

                    <!-- Q4 -->
                    <div class="faq-card-item" onclick="this.classList.toggle('open')">
                        <div class="faq-header-bar">
                            <span class="faq-question-text">¿Qué pasa cuando terminan los 14 días gratis?</span>
                            <i class="fas fa-chevron-down faq-arrow-icon"></i>
                        </div>
                        <div class="faq-content-body">
                            <p>No te cobramos nada automáticamente porque no solicitamos tarjeta de crédito para la prueba. Si el sistema te sirvió y salvó tu mercadería, podés continuar protegiendo tu negocio sin interrupciones.</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ==================== BANNER FINAL CTA ==================== -->
        <section class="landing-content-section bg-light-soft">
            <div class="landing-container">
                <div class="final-cta-container">
                    <span class="final-cta-tag"><i class="fas fa-shield-halved"></i> PROTECCIÓN INMEDIATA</span>
                    <h2 class="final-cta-heading">Tu mercadería no espera: empezá a proteger tu góndola hoy</h2>
                    <p class="final-cta-sub">
                        Súmate a más de 450 despensas y minimarkets que convirtieron la merma en capital recuperado. Activá tu cuenta en menos de 3 minutos.
                    </p>
                    <div class="final-cta-btn-group">
                        <button type="button" class="btn-landing-primary btn-cta-prominent" onclick="App.openAuthModal('register')">
                            <span>Probar Gratis 14 Días Sin Tarjeta</span>
                            <i class="fas fa-arrow-right"></i>
                        </button>
                        <button type="button" class="btn-landing-secondary btn-cta-prominent" onclick="App.loginAsDemo('admin')">
                            <i class="fas fa-play text-celeste"></i>
                            <span>Probar Demo en Vivo</span>
                        </button>
                    </div>
                </div>
            </div>
        </section>

        <!-- ==================== FOOTER BLANCO Y CELESTE CON LOS 3 APARTADOS ==================== -->
        <footer class="landing-footer-white">
            <div class="landing-container">
                <div class="footer-primary-row">
                    <!-- Brand -->
                    <div class="footer-brand-side">
                        <div class="landing-brand-dots">
                            <span class="brand-dot dot-red"></span>
                            <span class="brand-dot dot-amber"></span>
                            <span class="brand-dot dot-green"></span>
                        </div>
                        <span class="footer-brand-title">Expiry</span>
                        <span class="footer-tagline">El Semáforo de Vencimientos</span>
                    </div>

                    <!-- Los 3 Apartados Solicitados en la Imagen 3 con Acción Interactiva -->
                    <div class="footer-three-links">
                        <button type="button" class="footer-tab-link" onclick="App.openLegalModal('terminos')">
                            <i class="fas fa-file-contract text-celeste"></i>
                            <span>Términos y Condiciones</span>
                        </button>
                        <button type="button" class="footer-tab-link" onclick="App.openLegalModal('privacidad')">
                            <i class="fas fa-user-shield text-celeste"></i>
                            <span>Política de Privacidad</span>
                        </button>
                        <button type="button" class="footer-tab-link" onclick="App.openLegalModal('soporte')">
                            <i class="fas fa-headset text-celeste"></i>
                            <span>Soporte Técnico</span>
                        </button>
                    </div>
                </div>

                <div class="footer-secondary-row">
                    <p class="footer-copy-text">
                        © 2025 Expiry SaaS. El Semáforo Inteligente de Vencimientos para Despensas y Minimarkets. Todos los derechos reservados.
                    </p>
                    <div class="footer-badges-list">
                        <span class="footer-engine-badge">v2.4 Production Engine</span>
                        <button type="button" class="footer-vuln-btn" onclick="App.openVulnScopeModal()">
                            <i class="fas fa-shield-halved text-celeste"></i> VulnScope 100% OK
                        </button>
                    </div>
                </div>
            </div>
        </footer>
    </section>

    <!-- =========================================================================
         2. VISTA APLICACIÓN PRINCIPAL (REDISIEÑO FIDEDIGNO A LA IMAGEN)
         ========================================================================= -->
    <main id="viewApp" class="app-layout hidden">
        <!-- Topbar Azul Fidedigno -->
        <header class="app-header-blue">
            <a href="#" class="header-brand" onclick="location.reload(); return false;">
                <span class="brand-icon-calendar"><i class="fas fa-calendar-alt"></i></span>
                <span id="appBrandName">Vencimientos Alert - Minimarket San José</span>
            </a>

            <div class="header-actions">
                <button type="button" class="icon-bell-btn" onclick="App.openNotificacionesModal()" title="Alertas de Vencimiento">
                    <i class="fas fa-bell"></i>
                    <span class="bell-badge-dot"></span>
                </button>

                <!-- Perfil de Usuario con Menú Desplegable Interactivo -->
                <div class="user-dropdown-wrapper" id="userDropdownWrapper">
                    <button type="button" class="header-user-profile" id="headerUserProfile" onclick="App.toggleUserDropdown()" aria-expanded="false" aria-haspopup="true" title="Opciones de perfil y cargo">
                        <div class="user-avatar-circle" id="appUserAvatar">
                            <i class="fas fa-user"></i>
                        </div>
                        <div class="header-user-meta">
                            <span class="header-user-name" id="appUserName">Administrador</span>
                            <span class="header-user-role role-admin" id="appRoleBadge">Dueño / Admin</span>
                        </div>
                        <i class="fas fa-chevron-down user-dropdown-caret" id="userDropdownCaret"></i>
                    </button>

                    <!-- Menú Desplegable Flotante Moderno -->
                    <div class="user-dropdown-menu hidden" id="userDropdownMenu">
                        <div class="dropdown-header-info">
                            <div class="dropdown-user-details">
                                <strong class="dropdown-user-fullname" id="dropdownUserName">Administrador</strong>
                                <span class="dropdown-user-role-tag role-admin" id="dropdownRoleTag">Dueño / Admin</span>
                            </div>
                            <span class="dropdown-session-status"><i class="fas fa-check-circle text-green"></i> Sesión activa</span>
                        </div>

                        <div class="dropdown-divider"></div>

                        <!-- Opción Destacada: Cambiar de Cargo / Rol -->
                        <button type="button" class="dropdown-item-action item-highlight-role" onclick="App.openSwitchRoleModal()">
                            <div class="dropdown-item-icon icon-swap">
                                <i class="fas fa-user-shield"></i>
                            </div>
                            <div class="dropdown-item-text">
                                <span class="dropdown-item-title">Cambiar de Cargo</span>
                                <span class="dropdown-item-subtitle" id="dropdownSwitchSubtitle">Alternar a Cajero / Empleado</span>
                            </div>
                            <span class="dropdown-badge-pill">Cambiar</span>
                        </button>

                        <div class="dropdown-divider"></div>

                        <!-- Opción 2: Auditoría y Seguridad PIN -->
                        <button type="button" class="dropdown-item-action" onclick="App.openVulnScopeModal(); App.closeUserDropdown();">
                            <div class="dropdown-item-icon">
                                <i class="fas fa-shield-halved text-blue"></i>
                            </div>
                            <div class="dropdown-item-text">
                                <span class="dropdown-item-title">Auditoría & PIN</span>
                                <span class="dropdown-item-subtitle">Registros inmutables de seguridad</span>
                            </div>
                        </button>

                        <!-- Opción 3: Reportes por Correo -->
                        <button type="button" class="dropdown-item-action" onclick="App.openNotificacionesModal(); App.closeUserDropdown();">
                            <div class="dropdown-item-icon">
                                <i class="fas fa-envelope-open-text text-amber"></i>
                            </div>
                            <div class="dropdown-item-text">
                                <span class="dropdown-item-title">Reportes por Email</span>
                                <span class="dropdown-item-subtitle">Alertas automáticas del semáforo</span>
                            </div>
                        </button>

                        <div class="dropdown-divider"></div>

                        <!-- Opción 4: Cerrar Sesión Segura -->
                        <button type="button" class="dropdown-item-action text-danger" onclick="App.logout()">
                            <div class="dropdown-item-icon icon-danger">
                                <i class="fas fa-sign-out-alt"></i>
                            </div>
                            <div class="dropdown-item-text">
                                <span class="dropdown-item-title">Cerrar Sesión</span>
                                <span class="dropdown-item-subtitle">Finalizar sesión en este equipo</span>
                            </div>
                        </button>
                    </div>
                </div>

                <!-- Botón Visible para Salir / Cerrar Sesión -->
                <button type="button" class="btn-header-logout" onclick="App.logout()" title="Cerrar Sesión Segura" aria-label="Cerrar Sesión">
                    <i class="fas fa-sign-out-alt"></i>
                    <span class="btn-logout-text">Cerrar Sesión</span>
                </button>
            </div>
        </header>

        <div class="main-wrapper">
            <!-- Título Dashboard -->
            <div class="dashboard-title-row">
                <h1 class="dashboard-title">Dashboard</h1>
            </div>

            <!-- Fila Superior: KPIs (Izquierda) + Carga Rápida (Derecha) -->
            <div class="dashboard-top-grid">
                <!-- Bloque Izquierdo: 3 KPIs + 2 Acciones -->
                <div class="kpis-and-features-wrap">
                    <!-- Fila de 3 KPIs Pastel (como en la imagen) -->
                    <div class="kpis-row">
                        <div class="kpi-card-pastel kpi-card-blue" onclick="document.querySelector('.tab-todos')?.click()" title="Filtrar Todos">
                            <div class="kpi-pastel-header">
                                <span class="kpi-pastel-label">Total Productos</span>
                                <i class="fas fa-users kpi-pastel-icon"></i>
                            </div>
                            <div class="kpi-pastel-value" id="kpiTotalCount">345</div>
                        </div>

                        <div class="kpi-card-pastel kpi-card-amber" onclick="document.querySelector('.tab-rojo')?.click()" title="Filtrar Por Vencer">
                            <div class="kpi-pastel-header">
                                <span class="kpi-pastel-label">Por Vencer (7 dias)</span>
                                <i class="fas fa-calendar-alt kpi-pastel-icon"></i>
                            </div>
                            <div class="kpi-pastel-value" id="kpiRojoCount">28</div>
                        </div>

                        <div class="kpi-card-pastel kpi-card-red" onclick="document.querySelector('.tab-vencido')?.click()" title="Filtrar Vencidos">
                            <div class="kpi-pastel-header">
                                <span class="kpi-pastel-label">Vencidos</span>
                                <i class="fas fa-clock kpi-pastel-icon"></i>
                            </div>
                            <div class="kpi-pastel-value" id="kpiVencidoCount">12</div>
                        </div>
                    </div>

                    <!-- Fila de 2 Tarjetas Funcionales (como en la imagen) -->
                    <div class="features-row-two">
                        <div class="feature-action-card" onclick="document.getElementById('quickNombre').focus()">
                            <div class="feature-info">
                                <h4>Gestión de Stock</h4>
                                <p>Control y rotación de mercadería</p>
                            </div>
                            <div class="feature-icon-circle">
                                <i class="fas fa-box"></i>
                            </div>
                        </div>

                        <div class="feature-action-card" onclick="App.openNotificacionesModal()">
                            <div class="feature-info">
                                <h4>Reportes</h4>
                                <p>Semáforo financiero y mermas</p>
                            </div>
                            <div class="feature-icon-circle">
                                <i class="fas fa-chart-line"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Bloque Derecho: Tarjeta Carga Rápida (como en la imagen) -->
                <aside class="quick-charge-card">
                    <h3 class="quick-charge-title">Carga Rápida</h3>
                    <form id="quickEntryForm">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(getCsrfToken()); ?>">
                        <input type="hidden" id="quickCodigoBarra" name="codigo_barra" value="">

                        <div class="charge-form-grid">
                            <div class="input-field-group">
                                <label class="input-field-label" for="quickNombre">Producto</label>
                                <input type="text" id="quickNombre" name="nombre" class="input-charge-text" placeholder="Producto" required>
                            </div>
                            <div class="input-field-group">
                                <label class="input-field-label" for="quickMarca">Marca</label>
                                <input type="text" id="quickMarca" name="marca" class="input-charge-text" placeholder="Marca">
                            </div>
                            <div class="input-field-group">
                                <label class="input-field-label" for="quickVencimiento">Fecha de Vencimiento</label>
                                <input type="date" id="quickVencimiento" name="fecha_vencimiento" class="input-charge-text" required onchange="App.onExpiryInputChange(this.value)">
                                <div class="quick-date-pills-row">
                                    <button type="button" class="btn-quick-date-pill" onclick="App.setQuickExpiryDays(3)" title="+3 días">+3d</button>
                                    <button type="button" class="btn-quick-date-pill" onclick="App.setQuickExpiryDays(7)" title="+7 días">+7d</button>
                                    <button type="button" class="btn-quick-date-pill" onclick="App.setQuickExpiryDays(15)" title="+15 días">+15d</button>
                                    <button type="button" class="btn-quick-date-pill" onclick="App.setQuickExpiryDays(30)" title="+1 mes">+1m</button>
                                    <button type="button" class="btn-quick-date-pill" onclick="App.setQuickExpiryDays(60)" title="+2 meses">+2m</button>
                                    <button type="button" class="btn-quick-date-pill" onclick="App.setQuickExpiryDays(90)" title="+3 meses">+3m</button>
                                    <button type="button" class="btn-quick-date-pill" onclick="App.setQuickExpiryDays(180)" title="+6 meses">+6m</button>
                                    <button type="button" class="btn-quick-date-pill" onclick="App.setQuickExpiryDays(365)" title="+1 año">+1a</button>
                                </div>
                                <div id="quickVencimientoFeedback" class="expiry-preview-hint">
                                    Formato estructurado: DD/MM/AAAA
                                </div>
                            </div>
                            <div class="input-field-group">
                                <label class="input-field-label" for="quickLote">Lote</label>
                                <input type="text" id="quickLote" name="codigo_lote" class="input-charge-text" placeholder="Lote">
                            </div>
                            <div class="input-field-group">
                                <label class="input-field-label" for="quickPrecioCosto">Monto / Costo</label>
                                <input type="number" id="quickPrecioCosto" name="precio_costo" class="input-charge-text" placeholder="Ej: 15000" min="0" step="any" required>
                            </div>
                            <div class="input-field-group">
                                <label class="input-field-label" for="quickCantidad">Cantidad</label>
                                <input type="number" id="quickCantidad" name="cantidad" class="input-charge-text" placeholder="1" value="1" min="1" required>
                            </div>
                        </div>

                        <button type="submit" class="btn-charge-blue">
                            <i class="fas fa-plus"></i> Cargar
                        </button>
                    </form>
                </aside>
            </div>

            <!-- Sección Semáforo de Vencimientos (como en la imagen) -->
            <section class="semaforo-section-card">
                <div class="semaforo-card-header">
                    <h2 class="semaforo-section-title">Semáforo de Vencimientos</h2>
                    <div class="semaforo-header-actions">
                        <button class="btn-header-outline" onclick="document.getElementById('quickNombre').focus()">
                            <i class="fas fa-plus"></i> Gestión de Stock
                        </button>
                        <button class="btn-header-outline" onclick="App.openNotificacionesModal()">
                            <i class="fas fa-file-alt"></i> Reportes
                        </button>
                    </div>
                </div>

                <!-- Barra de Filtros y Buscador -->
                <div style="padding: 12px 20px; background: #fafbfc; border-bottom: 1px solid var(--border-light); display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap;">
                    <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                        <button class="btn-header-outline filter-tab tab-todos active" data-color="ALL">Todos</button>
                        <button class="btn-header-outline filter-tab tab-rojo" data-color="ROJO" style="color: #ef4444; font-weight: 700;">🔴 ROJO</button>
                        <button class="btn-header-outline filter-tab tab-amarillo" data-color="AMARILLO" style="color: #f59e0b; font-weight: 700;">🟡 AMARILLO</button>
                        <button class="btn-header-outline filter-tab tab-verde" data-color="VERDE" style="color: #10b981; font-weight: 700;">🟢 VERDE</button>
                        <button class="btn-header-outline filter-tab tab-vencido" data-color="VENCIDO" style="color: #991b1b; font-weight: 700;">⛔ VENCIDOS</button>
                    </div>
                    <div style="position: relative; width: 250px;">
                        <input type="text" id="tableSearchInput" class="input-charge-text" placeholder="Buscar producto..." style="padding-left: 32px;">
                        <i class="fas fa-search" style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 0.85rem;"></i>
                    </div>
                </div>

                <!-- Tabla Limpia (Escritorio / PC) -->
                <div class="table-container-responsive">
                    <table class="expiry-clean-table">
                        <thead>
                            <tr>
                                <th style="width: 32%;">Producto</th>
                                <th style="width: 18%;">Fecha de carga</th>
                                <th style="width: 16%;">Estado</th>
                                <th style="width: 22%;">Fecha de vencimiento</th>
                                <th style="width: 12%; text-align: center;">Acción</th>
                            </tr>
                        </thead>
                        <tbody id="semaforoTableBody">
                            <!-- Filas renderizadas por JS -->
                        </tbody>
                    </table>
                </div>

                <!-- Tarjetas para Móviles -->
                <div id="semaforoMobileCards" class="mobile-cards-feed">
                    <!-- Renderizado dinámico en pantalla móvil -->
                </div>

                <!-- Estado Vacío -->
                <div id="semaforoEmptyState" class="hidden" style="text-align: center; padding: 40px; color: var(--text-muted);">
                    <i class="fas fa-check-circle" style="font-size: 2rem; color: #10b981; margin-bottom: 8px;"></i>
                    <p>No hay productos para mostrar en este filtro.</p>
                </div>
            </section>
        </div>

        <!-- Barra Inferior Azul (Dock como en la imagen) -->
        <footer class="bottom-dock-bar">
            <div class="dock-left-icon" onclick="App.showAppView()" title="Calendario / Dashboard">
                <i class="fas fa-calendar-alt"></i>
            </div>
            <div class="dock-center-action" onclick="document.getElementById('quickNombre').focus()">
                <i class="fas fa-bars"></i>
                <span>Gestión de Stock</span>
            </div>
            <div class="dock-right-icon" onclick="App.openVulnScopeModal()" title="Seguridad & Red">
                <i class="fas fa-globe"></i>
            </div>
        </footer>
    </main>

    <!-- =========================================================================
         3. MODALES DE LA APLICACIÓN
         ========================================================================= -->

    <!-- Modal 0: Modificar Fecha de Vencimiento Estructurada -->
    <div id="editExpiryModal" class="modal-overlay hidden">
        <div class="modal-card" style="max-width: 460px; background: #ffffff; border: 1px solid var(--border-light);">
            <div class="modal-header" style="border-bottom: 1px solid var(--border-light);">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <div class="brand-icon" style="width: 34px; height: 34px; font-size: 0.95rem; background: #2563eb; color: #ffffff;">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <div>
                        <h3 style="font-size: 1.15rem; font-weight: 700; color: #0f172a;">Modificar Vencimiento</h3>
                        <span id="modalEditProductoNombre" style="font-size: 0.8rem; color: #64748b; font-weight: 600;">Producto</span>
                    </div>
                </div>
                <button class="modal-close-btn" onclick="App.closeEditExpiryModal()" style="color: #64748b;">&times;</button>
            </div>

            <div class="modal-body" style="padding: 20px;">
                <form id="formEditExpiry" onsubmit="App.submitEditExpiryForm(event)">
                    <div style="background: #f8fafc; border: 1px solid var(--border-light); border-radius: 8px; padding: 12px; margin-bottom: 16px;">
                        <div style="font-size: 0.78rem; color: #64748b; margin-bottom: 4px;">Fecha de vencimiento actual:</div>
                        <div id="modalEditFechaActualDisplay" style="font-size: 1.05rem; font-weight: 800; color: #0f172a;">--/--/----</div>
                    </div>

                    <div class="form-group" style="margin-bottom: 16px;">
                        <label class="form-label" for="modalEditNuevaFecha" style="color: #0f172a; font-weight: 700; margin-bottom: 6px;">
                            Nueva Fecha de Vencimiento:
                        </label>
                        <input type="date" id="modalEditNuevaFecha" class="form-control" style="background: #ffffff; color: #0f172a; border-color: #cbd5e1; font-weight: 600;" required onchange="App.onModalExpiryInputChange(this.value)">
                        
                        <!-- Presets rápidos estructurados -->
                        <div class="quick-date-pills-row" style="margin-top: 8px;">
                            <button type="button" class="btn-quick-date-pill" onclick="App.setModalExpiryDays(3)">+3d</button>
                            <button type="button" class="btn-quick-date-pill" onclick="App.setModalExpiryDays(7)">+7d</button>
                            <button type="button" class="btn-quick-date-pill" onclick="App.setModalExpiryDays(15)">+15d</button>
                            <button type="button" class="btn-quick-date-pill" onclick="App.setModalExpiryDays(30)">+1m</button>
                            <button type="button" class="btn-quick-date-pill" onclick="App.setModalExpiryDays(60)">+2m</button>
                            <button type="button" class="btn-quick-date-pill" onclick="App.setModalExpiryDays(90)">+3m</button>
                            <button type="button" class="btn-quick-date-pill" onclick="App.setModalExpiryDays(180)">+6m</button>
                            <button type="button" class="btn-quick-date-pill" onclick="App.setModalExpiryDays(365)">+1a</button>
                        </div>

                        <div id="modalEditExpiryFeedback" class="expiry-preview-hint" style="margin-top: 6px;">
                            Estructura: DD/MM/AAAA
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom: 20px;">
                        <label class="form-label" for="modalEditMotivo" style="color: #0f172a; font-weight: 600;">Motivo del cambio:</label>
                        <input type="text" id="modalEditMotivo" class="form-control" style="background: #ffffff; color: #0f172a; border-color: #cbd5e1;" value="Ajuste de fecha por revisión física" required>
                    </div>

                    <div style="display: flex; gap: 10px;">
                        <button type="button" class="btn btn-secondary" style="flex: 1;" onclick="App.closeEditExpiryModal()">Cancelar</button>
                        <button type="submit" class="btn btn-primary" style="flex: 1; background: #2563eb; color: #ffffff;">
                            <i class="fas fa-key"></i> Autorizar con PIN
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal 1: Autenticación Profesional y Dinámica (Iniciar Sesión / Crear Nueva Cuenta) -->
    <div id="authModal" class="modal-overlay hidden" role="dialog" aria-modal="true" aria-labelledby="authModalTitle">
        <div class="auth-modal-card">
            <!-- Cabecera Dinámica del Modal -->
            <div class="auth-modal-header">
                <div class="auth-brand-wrapper">
                    <div class="auth-icon-badge">
                        <i class="fas fa-shield-halved"></i>
                    </div>
                    <div>
                        <h3 id="authModalTitle" class="auth-modal-title">Acceso al Sistema</h3>
                        <p class="auth-modal-subtitle">Control predictivo de vencimientos para tu comercio</p>
                    </div>
                </div>
                <button type="button" class="auth-modal-close" onclick="App.closeAuthModal()" aria-label="Cerrar ventana">&times;</button>
            </div>

            <div class="auth-modal-body">
                <!-- Selector de Pestañas Tipo Segmented Control Dinámico -->
                <div class="auth-segmented-tabs">
                    <button type="button" id="tabBtnLogin" class="auth-tab-pill active" onclick="App.switchAuthTab('login')">
                        <i class="fas fa-sign-in-alt"></i>
                        <span>Iniciar Sesión</span>
                    </button>
                    <button type="button" id="tabBtnRegister" class="auth-tab-pill" onclick="App.switchAuthTab('register')">
                        <i class="fas fa-user-plus"></i>
                        <span>Crear Cuenta</span>
                    </button>
                </div>

                <!-- Formulario Iniciar Sesión -->
                <form id="formLogin" class="auth-form-animated">
                    <div class="auth-form-field">
                        <label class="auth-field-label" for="loginUsuario">
                            <i class="fas fa-user-circle"></i> Usuario o Correo:
                        </label>
                        <div class="auth-input-group">
                            <i class="fas fa-user auth-field-icon"></i>
                            <input type="text" name="usuario" id="loginUsuario" class="auth-input-control" placeholder="admin o tu usuario" autocomplete="username" required>
                        </div>
                    </div>

                    <div class="auth-form-field">
                        <label class="auth-field-label" for="loginPassword">
                            <i class="fas fa-lock"></i> Contraseña:
                        </label>
                        <div class="auth-input-group">
                            <i class="fas fa-key auth-field-icon"></i>
                            <input type="password" name="password" id="loginPassword" class="auth-input-control" placeholder="••••••••" autocomplete="current-password" required>
                            <button type="button" class="auth-eye-btn" onclick="App.togglePasswordVisibility('loginPassword', 'eyeIconLogin')" title="Ver u ocultar contraseña">
                                <i id="eyeIconLogin" class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Botón Principal Dinámico -->
                    <button type="submit" class="btn-auth-primary-action">
                        <span>Ingresar al Semáforo</span>
                        <i class="fas fa-arrow-right"></i>
                    </button>

                    <!-- Separador Elegante -->
                    <div class="auth-or-separator">
                        <span>o ingresá al instante en modo prueba</span>
                    </div>

                    <!-- Cuentas Demo Preconfiguradas (1 Clic) Rediseñadas como Tarjetas Interactivas -->
                    <div class="auth-demo-grid">
                        <button type="button" class="auth-demo-card demo-card-admin" onclick="App.loginAsDemo('admin')" title="Entrar como Dueño / Administrador con 1 Clic">
                            <div class="demo-card-top">
                                <span class="demo-card-icon crown-icon"><i class="fas fa-crown"></i></span>
                                <span class="demo-card-badge badge-admin">Acceso Total</span>
                            </div>
                            <strong class="demo-card-title">Dueño / Admin</strong>
                            <span class="demo-card-desc">PIN maestro y semáforo full</span>
                        </button>

                        <button type="button" class="auth-demo-card demo-card-empleado" onclick="App.loginAsDemo('empleado')" title="Entrar como Cajero / Empleado con 1 Clic">
                            <div class="demo-card-top">
                                <span class="demo-card-icon user-icon"><i class="fas fa-user-tag"></i></span>
                                <span class="demo-card-badge badge-empleado">Operativo</span>
                            </div>
                            <strong class="demo-card-title">Cajero / Empleado</strong>
                            <span class="demo-card-desc">Carga express y alertas</span>
                        </button>
                    </div>
                </form>

                <!-- Formulario Crear Nueva Cuenta -->
                <form id="formRegister" class="auth-form-animated hidden">
                    <div class="auth-form-field">
                        <label class="auth-field-label">
                            <i class="fas fa-id-card"></i> Tu Nombre Completo:
                        </label>
                        <div class="auth-input-group">
                            <i class="fas fa-user auth-field-icon"></i>
                            <input type="text" name="nombre" class="auth-input-control" placeholder="Ej: Juan Pérez" required>
                        </div>
                    </div>

                    <div class="auth-fields-row">
                        <div class="auth-form-field">
                            <label class="auth-field-label">
                                <i class="fas fa-at"></i> Usuario:
                            </label>
                            <div class="auth-input-group">
                                <i class="fas fa-user-tag auth-field-icon"></i>
                                <input type="text" name="usuario" class="auth-input-control" placeholder="juanperez" required>
                            </div>
                        </div>

                        <div class="auth-form-field">
                            <label class="auth-field-label">
                                <i class="fas fa-envelope"></i> Email:
                            </label>
                            <div class="auth-input-group">
                                <i class="fas fa-paper-plane auth-field-icon"></i>
                                <input type="email" name="email" class="auth-input-control" placeholder="correo@ejemplo.com">
                            </div>
                        </div>
                    </div>

                    <div class="auth-form-field">
                        <label class="auth-field-label">
                            <i class="fas fa-store"></i> Nombre de tu Despensa / Minimarket:
                        </label>
                        <div class="auth-input-group">
                            <i class="fas fa-shop auth-field-icon"></i>
                            <input type="text" name="nombre_negocio" class="auth-input-control" placeholder="Ej: Despensa Don Juan" required>
                        </div>
                    </div>

                    <div class="auth-form-field">
                        <label class="auth-field-label">
                            <i class="fas fa-lock"></i> Contraseña:
                        </label>
                        <div class="auth-input-group">
                            <i class="fas fa-key auth-field-icon"></i>
                            <input type="password" name="password" id="registerPassword" class="auth-input-control" placeholder="Mínimo 6 caracteres" required>
                            <button type="button" class="auth-eye-btn" onclick="App.togglePasswordVisibility('registerPassword', 'eyeIconRegister')" title="Ver u ocultar contraseña">
                                <i id="eyeIconRegister" class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Caja Destacada de PIN de Administrador (Seguridad Comercial) -->
                    <div class="auth-pin-security-box">
                        <div class="auth-pin-header">
                            <div class="auth-pin-badge-icon">
                                <i class="fas fa-shield-alt"></i>
                            </div>
                            <div>
                                <label class="auth-pin-label">Definir PIN de Administrador (4 a 6 dígitos):</label>
                                <span class="auth-pin-hint">Protege acciones críticas como borrado y cambios de fecha</span>
                            </div>
                        </div>
                        <div class="auth-input-group" style="margin-top: 8px;">
                            <i class="fas fa-lock auth-field-icon"></i>
                            <input type="password" name="admin_pin" maxlength="6" class="auth-input-control auth-pin-input" placeholder="Ej: 1234" required pattern="\d{4,6}" inputmode="numeric">
                        </div>
                    </div>

                    <button type="submit" class="btn-auth-primary-action btn-register-action">
                        <i class="fas fa-rocket"></i>
                        <span>Crear Cuenta y Empezar Gratis</span>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal: Cambiar de Cargo Operativo con Verificación de Contraseña -->
    <div id="switchRoleModal" class="modal-overlay hidden" role="dialog" aria-modal="true" aria-labelledby="switchRoleModalTitle">
        <div class="auth-modal-card switch-role-card">
            <div class="auth-modal-header">
                <div class="auth-brand-wrapper">
                    <div class="auth-icon-badge icon-badge-purple">
                        <i class="fas fa-user-shield"></i>
                    </div>
                    <div>
                        <h3 id="switchRoleModalTitle" class="auth-modal-title">Cambiar de Cargo</h3>
                        <p class="auth-modal-subtitle">Modificá tu perfil operativo y permisos en el sistema</p>
                    </div>
                </div>
                <button type="button" class="auth-modal-close" onclick="App.closeSwitchRoleModal()" aria-label="Cerrar modal">&times;</button>
            </div>

            <div class="auth-modal-body">
                <form id="formSwitchRole" onsubmit="App.handleSwitchRoleSubmit(event)" class="auth-form-animated">
                    <!-- Selector Visual del Nuevo Cargo -->
                    <div class="switch-role-selector-wrap">
                        <span class="auth-field-label">Seleccioná el nuevo cargo a activar:</span>
                        <div class="role-cards-selection">
                            <label class="role-select-box" id="roleBoxAdmin" onclick="App.selectRoleOption('admin')">
                                <input type="radio" name="nuevo_rol" value="admin" id="radioRoleAdmin" class="role-radio-hidden">
                                <div class="role-box-content">
                                    <div class="role-box-top">
                                        <i class="fas fa-crown text-amber"></i>
                                        <span class="role-pill-tag tag-admin">Acceso Total</span>
                                    </div>
                                    <strong class="role-box-name">Dueño / Admin</strong>
                                    <span class="role-box-desc">Control de mermas, auditoría, PIN y reportes</span>
                                </div>
                            </label>

                            <label class="role-select-box" id="roleBoxEmpleado" onclick="App.selectRoleOption('empleado')">
                                <input type="radio" name="nuevo_rol" value="empleado" id="radioRoleEmpleado" class="role-radio-hidden">
                                <div class="role-box-content">
                                    <div class="role-box-top">
                                        <i class="fas fa-user-tag text-blue"></i>
                                        <span class="role-pill-tag tag-empleado">Operativo</span>
                                    </div>
                                    <strong class="role-box-name">Cajero / Empleado</strong>
                                    <span class="role-box-desc">Carga rápida de mercadería y semáforo diario</span>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- Validación de Seguridad: Contraseña o PIN -->
                    <div class="auth-form-field" style="margin-top: 18px;">
                        <label class="auth-field-label" for="switchRolePassword">
                            <i class="fas fa-lock"></i> Contraseña o PIN de Seguridad:
                        </label>
                        <div class="auth-input-group">
                            <i class="fas fa-key auth-field-icon"></i>
                            <input type="password" name="password" id="switchRolePassword" class="auth-input-control" placeholder="Ingresá tu contraseña o PIN maestro" autocomplete="current-password" required>
                            <button type="button" class="auth-eye-btn" onclick="App.togglePasswordVisibility('switchRolePassword', 'eyeIconSwitch')" title="Ver u ocultar contraseña">
                                <i id="eyeIconSwitch" class="fas fa-eye"></i>
                            </button>
                        </div>
                        <small class="auth-security-note">
                            <i class="fas fa-shield-alt text-celeste"></i> Se requiere validar tu contraseña o PIN maestro para autorizar el cambio de cargo.
                        </small>
                    </div>

                    <!-- Botones de Acción -->
                    <div class="modal-actions-row" style="display: flex; gap: 10px; margin-top: 20px;">
                        <button type="button" class="btn btn-secondary" style="flex: 1;" onclick="App.closeSwitchRoleModal()">
                            Cancelar
                        </button>
                        <button type="submit" class="btn-auth-primary-action" style="flex: 2; margin-top: 0;" id="btnSubmitSwitchRole">
                            <span>Confirmar Cambio</span>
                            <i class="fas fa-check-circle"></i>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal 2: Keypad de PIN de Administrador (Protección de Acciones Críticas) -->
    <div id="pinModal" class="modal-overlay hidden">
        <div class="modal-card">
            <div class="modal-header">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <div class="brand-icon" style="width: 32px; height: 32px; font-size: 0.9rem; background: #dc2626;">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <div>
                        <h3 style="font-size: 1.1rem; font-weight: 700;">Seguridad de Administrador</h3>
                        <span id="pinActionLabel" style="font-size: 0.75rem; color: var(--text-muted);">Autorización requerida</span>
                    </div>
                </div>
                <button class="modal-close-btn" onclick="App.closePinModal()">&times;</button>
            </div>

            <div class="modal-body">
                <div class="pin-display-wrapper">
                    <p style="font-size: 0.85rem; color: var(--text-muted);">
                        Ingresá el PIN maestro para autorizar esta operación crítica:
                    </p>
                    <div class="pin-dots">
                        <div class="pin-dot"></div>
                        <div class="pin-dot"></div>
                        <div class="pin-dot"></div>
                        <div class="pin-dot"></div>
                    </div>
                    <small style="font-size: 0.72rem; color: #a5b4fc;">(PIN predeterminado inicial: 1234)</small>
                </div>

                <!-- Keypad Numérico Táctil -->
                <div class="pin-keypad">
                    <button class="keypad-btn" onclick="App.enterPinDigit('1')">1</button>
                    <button class="keypad-btn" onclick="App.enterPinDigit('2')">2</button>
                    <button class="keypad-btn" onclick="App.enterPinDigit('3')">3</button>
                    <button class="keypad-btn" onclick="App.enterPinDigit('4')">4</button>
                    <button class="keypad-btn" onclick="App.enterPinDigit('5')">5</button>
                    <button class="keypad-btn" onclick="App.enterPinDigit('6')">6</button>
                    <button class="keypad-btn" onclick="App.enterPinDigit('7')">7</button>
                    <button class="keypad-btn" onclick="App.enterPinDigit('8')">8</button>
                    <button class="keypad-btn" onclick="App.enterPinDigit('9')">9</button>
                    <button class="keypad-btn action" onclick="App.deletePinDigit()"><i class="fas fa-backspace"></i></button>
                    <button class="keypad-btn" onclick="App.enterPinDigit('0')">0</button>
                    <button class="keypad-btn action" style="color: #10b981;" onclick="App.submitPinAction()"><i class="fas fa-check"></i></button>
                </div>

                <div style="margin-top: 20px; text-align: center;">
                    <button class="btn btn-secondary btn-sm" onclick="App.closePinModal()">Cancelar Operación</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal 3: Auditoría y Checklist de VulnScope -->
    <div id="vulnscopeModal" class="modal-overlay hidden">
        <div class="modal-card" style="max-width: 680px;">
            <div class="modal-header">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <div class="brand-icon" style="width: 32px; height: 32px; font-size: 0.9rem; background: #0284c7;">
                        <i class="fas fa-shield-halved"></i>
                    </div>
                    <div>
                        <h3 style="font-size: 1.15rem; font-weight: 700;">Auditoría de Seguridad & VulnScope</h3>
                        <span style="font-size: 0.75rem; color: #38bdf8;">Conformidad OWASP Top 10</span>
                    </div>
                </div>
                <button class="modal-close-btn" onclick="App.closeVulnScopeModal()">&times;</button>
            </div>

            <div class="modal-body" style="max-height: 75vh; overflow-y: auto;">
                <h4 style="font-size: 0.95rem; margin-bottom: 10px; color: #fff;">1. Verificación de los 6 Pilares de VulnScope:</h4>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 24px;">
                    <div style="background: rgba(15, 23, 42, 0.8); padding: 10px; border-radius: 8px; border-left: 3px solid #10b981;">
                        <strong style="font-size: 0.82rem; color: #fff;">🔒 TLS / HTTPS & HSTS</strong>
                        <div style="font-size: 0.72rem; color: var(--text-muted);">HSTS activo y transporte seguro configurado</div>
                    </div>
                    <div style="background: rgba(15, 23, 42, 0.8); padding: 10px; border-radius: 8px; border-left: 3px solid #10b981;">
                        <strong style="font-size: 0.82rem; color: #fff;">🛡️ Cabeceras Seguras</strong>
                        <div style="font-size: 0.72rem; color: var(--text-muted);">CSP, X-Frame-Options, NoSniff aplicados</div>
                    </div>
                    <div style="background: rgba(15, 23, 42, 0.8); padding: 10px; border-radius: 8px; border-left: 3px solid #10b981;">
                        <strong style="font-size: 0.82rem; color: #fff;">🍪 Cookies & CORS</strong>
                        <div style="font-size: 0.72rem; color: var(--text-muted);">HttpOnly, SameSite=Strict, Anti-Fixation</div>
                    </div>
                    <div style="background: rgba(15, 23, 42, 0.8); padding: 10px; border-radius: 8px; border-left: 3px solid #10b981;">
                        <strong style="font-size: 0.82rem; color: #fff;">📁 Archivos Protegidos</strong>
                        <div style="font-size: 0.72rem; color: var(--text-muted);">.git, .env, .sql, logs bloqueados 100%</div>
                    </div>
                    <div style="background: rgba(15, 23, 42, 0.8); padding: 10px; border-radius: 8px; border-left: 3px solid #10b981;">
                        <strong style="font-size: 0.82rem; color: #fff;">📦 Componentes Seguros</strong>
                        <div style="font-size: 0.72rem; color: var(--text-muted);">Vanilla ES6 limpio sin librerías vulnerables</div>
                    </div>
                    <div style="background: rgba(15, 23, 42, 0.8); padding: 10px; border-radius: 8px; border-left: 3px solid #10b981;">
                        <strong style="font-size: 0.82rem; color: #fff;">🧩 Anti-OWASP & SQLi</strong>
                        <div style="font-size: 0.72rem; color: var(--text-muted);">Sentencias preparadas PDO y tokens CSRF</div>
                    </div>
                </div>

                <h4 style="font-size: 0.95rem; margin-bottom: 10px; color: #fff;">2. Registro Inmutable de Auditoría con PIN:</h4>
                <div class="table-responsive" style="max-height: 220px; overflow-y: auto;">
                    <table class="semaforo-table" style="font-size: 0.78rem;">
                        <thead>
                            <tr>
                                <th>Fecha/Hora</th>
                                <th>Acción</th>
                                <th>Motivo</th>
                                <th>Resultado</th>
                                <th>IP</th>
                            </tr>
                        </thead>
                        <tbody id="auditLogTableBody">
                            <tr>
                                <td colspan="5" style="text-align: center; color: var(--text-muted);">Cargando registros...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div style="margin-top: 20px; text-align: right;">
                    <button class="btn btn-secondary btn-sm" onclick="App.closeVulnScopeModal()">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal 4: Configuración de Reportes y Alertas por Correo (Resend API) -->
    <div id="notificacionesModal" class="modal-overlay hidden">
        <div class="modal-card report-modal-card">
            <!-- Cabecera Moderna con Icono y Degradado -->
            <div class="report-modal-header">
                <div class="report-header-left">
                    <div class="report-icon-circle">
                        <i class="fas fa-paper-plane"></i>
                    </div>
                    <div>
                        <h3 class="report-modal-title">Configuración de Reportes</h3>
                        <p class="report-modal-subtitle">Avisos programados de vencimientos y semáforo financiero por email</p>
                    </div>
                </div>
                <button class="modal-close-btn report-close-btn" onclick="App.closeNotificacionesModal()">&times;</button>
            </div>

            <div class="report-modal-body">
                <!-- Barra de Navegación por Pestañas (SaaS Moderno) -->
                <div class="report-tabs-nav">
                    <button type="button" class="report-tab-btn active" id="tabBtnDestinatarios" onclick="App.switchReportTab('destinatarios')">
                        <i class="fas fa-users"></i>
                        <span>1. A Quiénes</span>
                    </button>
                    <button type="button" class="report-tab-btn" id="tabBtnMotor" onclick="App.switchReportTab('motor')">
                        <i class="fas fa-paper-plane"></i>
                        <span>2. Motor de Envío</span>
                    </button>
                    <button type="button" class="report-tab-btn" id="tabBtnFrecuencia" onclick="App.switchReportTab('frecuencia')">
                        <i class="fas fa-clock"></i>
                        <span>3. Frecuencia</span>
                    </button>
                    <button type="button" class="report-tab-btn" id="tabBtnContenido" onclick="App.switchReportTab('contenido')">
                        <i class="fas fa-sliders-h"></i>
                        <span>4. Qué Mandar</span>
                    </button>
                </div>

                <!-- Formulario Central de Configuración -->
                <form id="formEmailConfig" onsubmit="App.guardarConfiguracionEmail(event)">
                    
                    <!-- PESTAÑA 1: DESTINATARIOS (A QUIÉNES MANDAR) -->
                    <div id="tabPaneDestinatarios" class="report-tab-pane active">
                        <div class="report-section-intro">
                            <div class="report-intro-flex">
                                <div>
                                    <span class="report-section-tag"><i class="fas fa-users-cog"></i> Lista de Distribución</span>
                                    <p class="report-section-desc">Gestioná los colaboradores y destinatarios que recibirán los informes del semáforo.</p>
                                </div>
                                <button type="button" class="btn-add-contact-trigger" id="btnToggleAddContact" onclick="App.toggleFormNuevoContacto()">
                                    <i class="fas fa-user-plus"></i> + Agregar Contacto
                                </button>
                            </div>
                        </div>

                        <!-- Formulario Obligatorio para Agregar Contacto (Desplegable) -->
                        <div id="boxNuevoContacto" class="box-nuevo-contacto hidden">
                            <div class="box-contacto-header">
                                <div class="box-contacto-title-wrap">
                                    <div class="box-contacto-icon">
                                        <i class="fas fa-user-edit"></i>
                                    </div>
                                    <div>
                                        <strong class="box-contacto-title">Nuevo Contacto para Reportes</strong>
                                        <span class="box-contacto-subtitle">Formulario obligatorio: completá todos los campos requeridos (*)</span>
                                    </div>
                                </div>
                                <button type="button" class="btn-close-box-contacto" onclick="App.toggleFormNuevoContacto(false)">&times;</button>
                            </div>

                            <div class="add-contact-fields-grid">
                                <!-- Campo 1: Nombre Completo (Obligatorio) -->
                                <div class="report-form-group">
                                    <label class="report-label required-label" for="addContactoNombre">
                                        <i class="fas fa-id-badge"></i> Nombre Completo <span class="required-asterisk">*</span>:
                                    </label>
                                    <div class="report-input-wrapper">
                                        <i class="fas fa-user report-input-icon"></i>
                                        <input type="text" id="addContactoNombre" class="report-input-email" placeholder="Ej: Carlos Repositor" required minlength="2">
                                    </div>
                                </div>

                                <!-- Campo 2: Correo Electrónico (Obligatorio) -->
                                <div class="report-form-group">
                                    <label class="report-label required-label" for="addContactoEmail">
                                        <i class="fas fa-at"></i> Correo Electrónico <span class="required-asterisk">*</span>:
                                    </label>
                                    <div class="report-input-wrapper">
                                        <i class="fas fa-envelope report-input-icon"></i>
                                        <input type="email" id="addContactoEmail" class="report-input-email" placeholder="carlos@minimarket.com" required>
                                    </div>
                                </div>

                                <!-- Campo 3: Cargo / Rol (Obligatorio) -->
                                <div class="report-form-group">
                                    <label class="report-label required-label" for="addContactoCargo">
                                        <i class="fas fa-briefcase"></i> Cargo o Rol en el Negocio <span class="required-asterisk">*</span>:
                                    </label>
                                    <div class="report-input-wrapper">
                                        <i class="fas fa-tag report-input-icon"></i>
                                        <select id="addContactoCargo" class="report-input-select" required>
                                            <option value="" disabled selected>-- Seleccioná el cargo obligatorio --</option>
                                            <option value="Repositor de Góndola">📦 Repositor / Góndola</option>
                                            <option value="Encargado de Compras">🛒 Encargado de Compras / Proveedores</option>
                                            <option value="Cajero / Empleado">🏷️ Cajero / Atención al Cliente</option>
                                            <option value="Dueño / Administrador">👑 Dueño / Administrador General</option>
                                            <option value="Contador / Finanzas">📊 Contador / Control de Mermas</option>
                                            <option value="Socio Comercial">🤝 Socio / Gerente de Sucursal</option>
                                        </select>
                                    </div>
                                </div>

                                <!-- Campo 4: Tipo de Destinatario (Obligatorio) -->
                                <div class="report-form-group">
                                    <label class="report-label required-label" for="addContactoTipo">
                                        <i class="fas fa-paper-plane"></i> Tipo de Envío <span class="required-asterisk">*</span>:
                                    </label>
                                    <div class="report-input-wrapper">
                                        <i class="fas fa-layer-group report-input-icon"></i>
                                        <select id="addContactoTipo" class="report-input-select" required>
                                            <option value="CC" selected>📨 En Copia (CC) — Recibe copia de alertas</option>
                                            <option value="PRINCIPAL">👑 Destinatario Principal — Buzón maestro</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="box-contacto-actions">
                                <button type="button" class="btn-save-contact-action" id="btnSubmitNewContact" onclick="App.guardarNuevoContacto()">
                                    <i class="fas fa-check-circle"></i> Guardar Contacto Obligatorio
                                </button>
                                <button type="button" class="btn-cancel-contact-action" onclick="App.toggleFormNuevoContacto(false)">
                                    Cancelar
                                </button>
                            </div>
                        </div>

                        <!-- Lista Visual de Contactos Registrados -->
                        <div class="contactos-list-wrapper">
                            <div class="contactos-list-header">
                                <span class="contactos-list-title"><i class="fas fa-address-book"></i> Destinatarios Activos</span>
                                <span id="contactosCountBadge" class="contactos-count-badge">0 contactos</span>
                            </div>
                            <div id="contactosListContainer" class="contactos-cards-container">
                                <div class="contactos-empty-state">
                                    <i class="fas fa-spinner fa-spin"></i> Cargando contactos...
                                </div>
                            </div>
                        </div>

                        <!-- Campos ocultos de sincronización para compatibilidad -->
                        <input type="hidden" id="notifUserEmail" name="email_destino" value="roman.riveros.66@gmail.com">
                        <input type="hidden" id="notifEmailsCopia" name="emails_copia" value="">

                        <!-- Switch de Activación General -->
                        <div class="report-toggle-card">
                            <div class="toggle-card-info">
                                <strong class="toggle-card-title"><i class="fas fa-satellite-dish" style="color: #2563eb;"></i> Envío Automático Programado</strong>
                                <span class="toggle-card-desc">Mantener activos los reportes periódicos según la frecuencia configurada.</span>
                            </div>
                            <label class="switch-toggle" for="notifActivoCheckbox">
                                <input type="checkbox" id="notifActivoCheckbox" name="notificar_email" value="1">
                                <span class="switch-slider"></span>
                            </label>
                        </div>
                    </div>

                    <!-- PESTAÑA 2: MOTOR DE ENVÍO (GMAIL SMTP / RESEND API) -->
                    <div id="tabPaneMotor" class="report-tab-pane">
                        <div class="report-section-intro">
                            <span class="report-section-tag"><i class="fas fa-server"></i> Servidor y Motor de Envío</span>
                            <p class="report-section-desc">Elegí la tecnología de entrega de correos. Para enviar a todo tu equipo sin límites de dominio, usá Gmail / SMTP.</p>
                        </div>

                        <!-- Selector Visual de Motor de Envío -->
                        <div class="report-freq-grid engine-select-grid">
                            <!-- Opción A: Gmail / SMTP (Recomendada para múltiples destinatarios) -->
                            <label class="freq-card engine-card active" id="cardEngineSMTP" for="metodoSMTP" onclick="App.selectEngine('SMTP')">
                                <input type="radio" id="metodoSMTP" name="email_metodo" value="SMTP" checked>
                                <div class="freq-card-content">
                                    <div class="freq-card-header">
                                        <i class="fas fa-envelope-circle-check freq-icon" style="color: #10b981;"></i>
                                        <span class="freq-badge" style="background: rgba(16, 185, 129, 0.15); color: #059669; border: 1px solid #10b981;">Recomendado</span>
                                    </div>
                                    <strong class="freq-title">Gmail / Servidor SMTP</strong>
                                    <p class="freq-desc">Envía a <strong>todos los contactos</strong> seleccionados sin necesidad de registrar dominio web.</p>
                                </div>
                            </label>

                            <!-- Opción B: Resend API -->
                            <label class="freq-card engine-card" id="cardEngineResend" for="metodoResend" onclick="App.selectEngine('RESEND')">
                                <input type="radio" id="metodoResend" name="email_metodo" value="RESEND">
                                <div class="freq-card-content">
                                    <div class="freq-card-header">
                                        <i class="fas fa-bolt freq-icon" style="color: #3b82f6;"></i>
                                        <span class="freq-badge secondary">Resend Cloud</span>
                                    </div>
                                    <strong class="freq-title">Resend API (Nube)</strong>
                                    <p class="freq-desc">API Cloud. En modo gratuito <code>onboarding@resend.dev</code> solo entrega a la cuenta registrada.</p>
                                </div>
                            </label>
                        </div>

                        <!-- PANEL 1: CONFIGURACIÓN GMAIL / SMTP -->
                        <div id="panelConfigSMTP" class="engine-config-panel">
                            <div class="engine-panel-box">
                                <div class="engine-box-header">
                                    <i class="fab fa-google" style="color: #ea4335; font-size: 1.25rem;"></i>
                                    <div>
                                        <strong style="color: #0f172a; font-size: 0.95rem;">Configuración de Gmail / SMTP</strong>
                                        <div style="font-size: 0.75rem; color: #64748b;">Tus alertas se enviarán desde tu propia casilla a todo el equipo</div>
                                    </div>
                                </div>

                                <div class="add-contact-fields-grid" style="margin-top: 14px;">
                                    <div class="report-form-group">
                                        <label class="report-label required-label" for="smtpUser">
                                            <i class="fas fa-at"></i> Tu Correo Gmail / Emisor <span class="required-asterisk">*</span>:
                                        </label>
                                        <div class="report-input-wrapper">
                                            <i class="fas fa-user report-input-icon"></i>
                                            <input type="email" id="smtpUser" name="smtp_user" class="report-input-email" placeholder="tu_correo@gmail.com" value="roman.riveros.66@gmail.com">
                                        </div>
                                    </div>

                                    <div class="report-form-group">
                                        <label class="report-label required-label" for="smtpPass">
                                            <i class="fas fa-key"></i> Contraseña de Aplicación de Google <span class="required-asterisk">*</span>:
                                        </label>
                                        <div class="report-input-wrapper password-wrapper">
                                            <i class="fas fa-lock report-input-icon"></i>
                                            <input type="password" id="smtpPass" name="smtp_pass" class="report-input-email" placeholder="•••• •••• •••• •••• (16 letras)">
                                            <button type="button" class="btn-toggle-eye" onclick="App.toggleSmtpPassVisibility()" title="Ver contraseña">
                                                <i class="fas fa-eye" id="iconEyePass"></i>
                                            </button>
                                        </div>
                                        <span id="smtpPassSavedBadge" class="smtp-saved-badge hidden">
                                            <i class="fas fa-check-circle"></i> Contraseña configurada y guardada
                                        </span>
                                    </div>
                                </div>

                                <!-- Guía Desplegable para Generar Contraseña de Google -->
                                <div class="gmail-guide-wrapper">
                                    <button type="button" class="btn-toggle-guide" onclick="App.toggleGuiaGmail()">
                                        <i class="fas fa-question-circle" style="color: #2563eb;"></i>
                                        <span>¿Cómo obtener tu Contraseña de Aplicación de Google en 1 minuto?</span>
                                        <i class="fas fa-chevron-down guide-chevron" id="guideChevronIcon"></i>
                                    </button>
                                    <div id="guiaGmailContent" class="guide-steps-box hidden">
                                        <ol class="guide-steps-list">
                                            <li>Abrí tu cuenta de Google en <a href="https://myaccount.google.com/security" target="_blank" rel="noopener">myaccount.google.com/security</a>.</li>
                                            <li>Asegurate de que la <strong>"Verificación en dos pasos"</strong> esté activada.</li>
                                            <li>Ingresá a <a href="https://myaccount.google.com/apppasswords" target="_blank" rel="noopener">Contraseñas de aplicaciones</a>.</li>
                                            <li>Escribí <em>"Expiry Alertas"</em> como nombre y presioná <strong>Crear</strong>.</li>
                                            <li>Copiá el código de 16 letras que Google te muestra y pegalo arriba.</li>
                                        </ol>
                                        <div class="guide-benefit-tag">
                                            <i class="fas fa-shield-alt"></i> <strong>100% Seguro:</strong> No revelás tu contraseña personal y podés revocarla cuando quieras.
                                        </div>
                                    </div>
                                </div>

                                <!-- Opciones Técnicas Avanzadas (Opcionales) -->
                                <details class="smtp-advanced-details">
                                    <summary><i class="fas fa-cog"></i> Parámetros Técnicos Avanzados (Opcional)</summary>
                                    <div class="add-contact-fields-grid" style="margin-top: 10px;">
                                        <div class="report-form-group">
                                            <label class="report-label" for="smtpHost">Servidor Host:</label>
                                            <input type="text" id="smtpHost" name="smtp_host" value="smtp.gmail.com" class="report-input-email">
                                        </div>
                                        <div class="report-form-group">
                                            <label class="report-label" for="smtpPort">Puerto:</label>
                                            <input type="number" id="smtpPort" name="smtp_port" value="465" class="report-input-email">
                                        </div>
                                        <div class="report-form-group">
                                            <label class="report-label" for="smtpSeguridad">Cifrado:</label>
                                            <select id="smtpSeguridad" name="smtp_seguridad" class="report-input-select">
                                                <option value="ssl" selected>SSL (Recomendado - Puerto 465)</option>
                                                <option value="tls">TLS / STARTTLS (Puerto 587)</option>
                                            </select>
                                        </div>
                                    </div>
                                </details>

                                <!-- Botón de Prueba Rápida de Conexión SMTP -->
                                <div class="smtp-test-btn-wrap">
                                    <button type="button" class="btn-test-smtp-action" id="btnTestSmtpAction" onclick="App.probarConexionSMTP()">
                                        <i class="fas fa-plug"></i> Probar Conexión SMTP Ahora
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- PANEL 2: CONFIGURACIÓN RESEND API -->
                        <div id="panelConfigResend" class="engine-config-panel hidden">
                            <div class="engine-panel-box">
                                <div class="engine-box-header">
                                    <i class="fas fa-bolt" style="color: #3b82f6; font-size: 1.25rem;"></i>
                                    <div>
                                        <strong style="color: #0f172a; font-size: 0.95rem;">Configuración de Resend API</strong>
                                        <div style="font-size: 0.75rem; color: #64748b;">Entrega cloud mediante api.resend.com</div>
                                    </div>
                                </div>

                                <div class="add-contact-fields-grid" style="margin-top: 14px;">
                                    <div class="report-form-group">
                                        <label class="report-label" for="notifApiKeyInput">
                                            <i class="fas fa-key"></i> Resend API Key:
                                        </label>
                                        <div class="report-input-wrapper">
                                            <i class="fas fa-lock report-input-icon"></i>
                                            <input type="text" id="notifApiKeyInput" name="resend_api_key" placeholder="re_123456789..." class="report-input-email">
                                        </div>
                                    </div>
                                    <div class="report-form-group">
                                        <label class="report-label" for="notifSenderInput">
                                            <i class="fas fa-paper-plane"></i> Correo Remitente:
                                        </label>
                                        <div class="report-input-wrapper">
                                            <i class="fas fa-envelope report-input-icon"></i>
                                            <input type="text" id="notifSenderInput" name="email_remitente" value="Expiry Alertas &lt;onboarding@resend.dev&gt;" class="report-input-email">
                                        </div>
                                    </div>
                                </div>

                                <!-- Alerta explicativa de la restricción de Resend Sandbox -->
                                <div class="sandbox-warning-callout">
                                    <i class="fas fa-info-circle warning-callout-icon"></i>
                                    <div class="warning-callout-text">
                                        <strong>¿Por qué solo envía al correo principal con Resend?</strong>
                                        <p>
                                            El remitente gratuito <code>onboarding@resend.dev</code> está restringido por seguridad en los servidores de Resend para entregar <em>únicamente a la cuenta registrada del dueño</em>. Para enviar a todo tu equipo (repositor, socios, etc.), seleccioná la opción <strong>"Gmail / Servidor SMTP"</strong> arriba.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- PESTAÑA 3: FRECUENCIA Y HORARIO (CUÁNDO MANDAR) -->
                    <div id="tabPaneFrecuencia" class="report-tab-pane">
                        <div class="report-section-intro">
                            <span class="report-section-tag"><i class="fas fa-calendar-alt"></i> Periodicidad de Entrega</span>
                            <p class="report-section-desc">Elegí la frecuencia ideal para revisar vencimientos sin saturar tu bandeja.</p>
                        </div>

                        <div class="report-freq-grid">
                            <!-- Opción 1: Diario -->
                            <label class="freq-card" for="freqDiario">
                                <input type="radio" id="freqDiario" name="frecuencia_reporte" value="DIARIO" checked>
                                <div class="freq-card-content">
                                    <div class="freq-card-header">
                                        <i class="fas fa-sun freq-icon" style="color: #f59e0b;"></i>
                                        <span class="freq-badge">Más Elegido</span>
                                    </div>
                                    <strong class="freq-title">Diario Matutino</strong>
                                    <p class="freq-desc">Todos los días a la hora fijada para coordinar ofertas antes de abrir.</p>
                                </div>
                            </label>

                            <!-- Opción 2: Días por medio -->
                            <label class="freq-card" for="freqAlterno">
                                <input type="radio" id="freqAlterno" name="frecuencia_reporte" value="DIAS_ALTERNOS">
                                <div class="freq-card-content">
                                    <div class="freq-card-header">
                                        <i class="fas fa-sync-alt freq-icon" style="color: #3b82f6;"></i>
                                        <span class="freq-badge secondary">Cada 48 hs</span>
                                    </div>
                                    <strong class="freq-title">Días por Medio</strong>
                                    <p class="freq-desc">Monitoreo intermedio (Lunes, Miércoles y Viernes) para rotación constante.</p>
                                </div>
                            </label>

                            <!-- Opción 3: Semanal -->
                            <label class="freq-card" for="freqSemanal">
                                <input type="radio" id="freqSemanal" name="frecuencia_reporte" value="SEMANAL">
                                <div class="freq-card-content">
                                    <div class="freq-card-header">
                                        <i class="fas fa-calendar-check freq-icon" style="color: #10b981;"></i>
                                        <span class="freq-badge secondary">Lunes</span>
                                    </div>
                                    <strong class="freq-title">Semanal (Lunes)</strong>
                                    <p class="freq-desc">Resumen integral al arrancar la semana comercial del minimarket.</p>
                                </div>
                            </label>

                            <!-- Opción 4: Tiempo Real -->
                            <label class="freq-card" for="freqTiempoReal">
                                <input type="radio" id="freqTiempoReal" name="frecuencia_reporte" value="TIEMPO_REAL">
                                <div class="freq-card-content">
                                    <div class="freq-card-header">
                                        <i class="fas fa-bolt freq-icon" style="color: #ef4444;"></i>
                                        <span class="freq-badge alert">Inmediato</span>
                                    </div>
                                    <strong class="freq-title">En Tiempo Real</strong>
                                    <p class="freq-desc">Disparo instantáneo cuando un producto entra en Semáforo Rojo o Vence.</p>
                                </div>
                            </label>
                        </div>

                        <!-- Selector de Horario de Entrega -->
                        <div class="report-time-box">
                            <div class="time-box-info">
                                <label for="notifHoraReporte" class="time-box-label">
                                    <i class="fas fa-hourglass-start" style="color: #2563eb;"></i> Horario Preferido de Recepción:
                                </label>
                                <span class="time-box-desc">Hora exacta en la que se despacha el correo cada jornada.</span>
                            </div>
                            <div class="time-input-wrap">
                                <input type="time" id="notifHoraReporte" name="hora_reporte" value="08:00" class="report-input-time" required>
                            </div>
                        </div>
                    </div>

                    <!-- PESTAÑA 3: CONTENIDO DEL INFORME (QUÉ MANDAR) -->
                    <div id="tabPaneContenido" class="report-tab-pane">
                        <div class="report-section-intro">
                            <span class="report-section-tag"><i class="fas fa-filter"></i> Filtros de Datos</span>
                            <p class="report-section-desc">Marcá qué bloques de información querés que aparezcan en el correo.</p>
                        </div>

                        <div class="report-content-toggles">
                            <!-- Toggle 1: Vencidos -->
                            <div class="report-toggle-card item-toggle active-toggle" id="cardToggleVencidos">
                                <div class="toggle-card-info">
                                    <strong class="toggle-card-title">
                                        <span class="badge-dot dot-black"></span> Mercadería Ya Vencida
                                    </strong>
                                    <span class="toggle-card-desc">Lista de productos caducados para retiro urgente de góndola.</span>
                                </div>
                                <label class="switch-toggle" for="chkIncVencidos">
                                    <input type="checkbox" id="chkIncVencidos" name="incluir_vencidos" value="1" checked onchange="App.onFiltroToggleChange(this)">
                                    <span class="switch-slider"></span>
                                </label>
                            </div>

                            <!-- Toggle 2: Semáforo Rojo -->
                            <div class="report-toggle-card item-toggle active-toggle" id="cardToggleRojos">
                                <div class="toggle-card-info">
                                    <strong class="toggle-card-title">
                                        <span class="badge-dot dot-red"></span> Semáforo Rojo (≤ 7 días)
                                    </strong>
                                    <span class="toggle-card-desc">Lotes con vencimiento crítico para liquidación o promoción 2x1.</span>
                                </div>
                                <label class="switch-toggle" for="chkIncRojos">
                                    <input type="checkbox" id="chkIncRojos" name="incluir_rojos" value="1" checked onchange="App.onFiltroToggleChange(this)">
                                    <span class="switch-slider"></span>
                                </label>
                            </div>

                            <!-- Toggle 3: Semáforo Amarillo -->
                            <div class="report-toggle-card item-toggle active-toggle" id="cardToggleAmarillos">
                                <div class="toggle-card-info">
                                    <strong class="toggle-card-title">
                                        <span class="badge-dot dot-yellow"></span> Semáforo Amarillo (8 a 30 días)
                                    </strong>
                                    <span class="toggle-card-desc">Alerta preventiva para aplicar rotación en góndola (FIFO).</span>
                                </div>
                                <label class="switch-toggle" for="chkIncAmarillos">
                                    <input type="checkbox" id="chkIncAmarillos" name="incluir_amarillos" value="1" checked onchange="App.onFiltroToggleChange(this)">
                                    <span class="switch-slider"></span>
                                </label>
                            </div>

                            <!-- Toggle 4: Balance Financiero -->
                            <div class="report-toggle-card item-toggle active-toggle" id="cardToggleFinanciero">
                                <div class="toggle-card-info">
                                    <strong class="toggle-card-title">
                                        <span class="badge-dot dot-green"></span> Balance Financiero y Capital en Riesgo
                                    </strong>
                                    <span class="toggle-card-desc">Totalización de dinero invertido en mercadería vulnerable.</span>
                                </div>
                                <label class="switch-toggle" for="chkIncFinanciero">
                                    <input type="checkbox" id="chkIncFinanciero" name="incluir_financiero" value="1" checked onchange="App.onFiltroToggleChange(this)">
                                    <span class="switch-slider"></span>
                                </label>
                            </div>

                            <!-- Toggle 5: Sugerencias Comerciales -->
                            <div class="report-toggle-card item-toggle active-toggle" id="cardToggleSugerencias">
                                <div class="toggle-card-info">
                                    <strong class="toggle-card-title">
                                        <span class="badge-dot dot-blue"></span> Recomendaciones Comerciales
                                    </strong>
                                    <span class="toggle-card-desc">Tips y pasos rápidos de acción recomendados para el personal.</span>
                                </div>
                                <label class="switch-toggle" for="chkIncSugerencias">
                                    <input type="checkbox" id="chkIncSugerencias" name="incluir_sugerencias" value="1" checked onchange="App.onFiltroToggleChange(this)">
                                    <span class="switch-slider"></span>
                                </label>
                            </div>
                        </div>

                        <!-- Responsable de Emisión del Reporte & Cargo o Rol en el Negocio -->
                        <div class="report-sender-card">
                            <div class="sender-card-header">
                                <div class="sender-card-title-wrap">
                                    <div class="sender-card-icon">
                                        <i class="fas fa-id-badge"></i>
                                    </div>
                                    <div>
                                        <strong class="sender-card-title">Responsable de Emisión del Reporte</strong>
                                        <span class="sender-card-subtitle">Firma institucional que se mostrará en la cabecera y pie del correo.</span>
                                    </div>
                                </div>
                            </div>

                            <div class="sender-card-body">
                                <div class="add-contact-fields-grid">
                                    <!-- Campo 1: Nombre de Quién Envía -->
                                    <div class="report-form-group">
                                        <label class="report-label required-label" for="notifRemitenteNombre">
                                            <i class="fas fa-user-tie"></i> Quién Envía el Reporte <span class="required-asterisk">*</span>:
                                        </label>
                                        <div class="report-input-wrapper">
                                            <i class="fas fa-user report-input-icon"></i>
                                            <input type="text" id="notifRemitenteNombre" name="remitente_nombre" class="report-input-email" placeholder="Ej: Román Riveros" required>
                                        </div>
                                    </div>

                                    <!-- Campo 2: Cargo o Rol en el Negocio (Obligatorio) -->
                                    <div class="report-form-group">
                                        <label class="report-label required-label" for="notifRemitenteCargo">
                                            <i class="fas fa-briefcase"></i> Cargo o Rol en el Negocio <span class="required-asterisk">*</span>:
                                        </label>
                                        <div class="report-input-wrapper">
                                            <i class="fas fa-tag report-input-icon"></i>
                                            <select id="notifRemitenteCargo" name="remitente_cargo" class="report-input-select" required>
                                                <option value="Dueño / Administrador">👑 Dueño / Administrador General</option>
                                                <option value="Repositor de Góndola">📦 Repositor / Góndola</option>
                                                <option value="Encargado de Compras">🛒 Encargado de Compras / Proveedores</option>
                                                <option value="Cajero / Empleado">🏷️ Cajero / Atención al Cliente</option>
                                                <option value="Contador / Finanzas">📊 Contador / Control de Mermas</option>
                                                <option value="Socio Comercial">🤝 Socio / Gerente de Sucursal</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <!-- Selección Rápida de Emisores del Equipo -->
                                <div class="quick-senders-box" id="quickSendersBox">
                                    <span class="quick-senders-label"><i class="fas fa-bolt"></i> Elegir rápidamente emisor registrado:</span>
                                    <div class="quick-senders-chips" id="quickSendersChips">
                                        <!-- Se puebla dinámicamente -->
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Botón de Guardado Principal -->
                    <div class="report-save-action-wrap">
                        <button type="submit" class="btn-save-report-config" id="btnSaveConfigEmail">
                            <i class="fas fa-save"></i> Guardar Configuración de Reportes
                        </button>
                    </div>
                </form>

                <!-- Tarjeta: Disparadores de Prueba y Envío Inmediato -->
                <div class="report-triggers-card">
                    <div class="triggers-header">
                        <div class="triggers-title-wrap">
                            <i class="fas fa-bolt" style="color: #f59e0b;"></i>
                            <h4>Acciones Inmediatas en Tiempo Real</h4>
                        </div>
                        <span class="triggers-pill" id="notifPillEngine">Gmail SMTP Activo</span>
                    </div>
                    <p class="triggers-desc">
                        Podés verificar la entrega ahora mismo o enviar un informe completo con los filtros configurados a <strong>todos los destinatarios activos</strong>.
                    </p>
                    <div class="triggers-buttons-grid">
                        <button type="button" class="btn-report-action-secondary" onclick="App.enviarEmailPrueba()" id="btnSendTest">
                            <i class="fas fa-paper-plane"></i> Probar Envío a Todos
                        </button>
                        <button type="button" class="btn-report-action-primary" onclick="App.enviarReporteEmailRapido()" id="btnSendReport">
                            <i class="fas fa-file-invoice-dollar"></i> Enviar Alerta a Todos Ahora
                        </button>
                    </div>
                </div>

                <!-- Estado del Motor de Envío -->
                <div class="report-status-box compact">
                    <div class="report-status-header">
                        <div class="report-status-badge">
                            <span class="pulse-green-dot"></span>
                            <span id="notifEngineStatusText">Motor Activo: Gmail / SMTP (Entrega a Todo el Equipo)</span>
                        </div>
                        <span id="notifSender" class="report-sender-text">roman.riveros.66@gmail.com</span>
                    </div>
                </div>

                <div class="report-modal-footer">
                    <button type="button" class="btn-report-close-footer" onclick="App.closeNotificacionesModal()">
                        Cerrar Ventana
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- =========================================================================
         Modal Informativo: Términos y Condiciones, Privacidad y Soporte Técnico
         ========================================================================= -->
    <div id="legalModal" class="modal-overlay hidden">
        <div class="modal-card legal-modal-card">
            <div class="modal-header">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <div class="brand-icon" style="background: #0284c7; color: #fff;">
                        <i class="fas fa-scale-balanced"></i>
                    </div>
                    <div>
                        <h3 id="legalModalTitle" style="font-size: 1.15rem; font-weight: 700; color: #0f172a;">Centro Legal y Soporte Expiry</h3>
                        <span style="font-size: 0.78rem; color: #64748b;">Información transparente para despensas y minimarkets</span>
                    </div>
                </div>
                <button class="modal-close-btn" onclick="App.closeLegalModal()">&times;</button>
            </div>

            <!-- Pestañas Interactivas del Modal Legal -->
            <div class="legal-modal-tabs">
                <button type="button" id="legalTabBtnTerminos" class="legal-tab-btn active" onclick="App.switchLegalTab('terminos')">
                    <i class="fas fa-file-contract"></i> Términos y Condiciones
                </button>
                <button type="button" id="legalTabBtnPrivacidad" class="legal-tab-btn" onclick="App.switchLegalTab('privacidad')">
                    <i class="fas fa-user-shield"></i> Política de Privacidad
                </button>
                <button type="button" id="legalTabBtnSoporte" class="legal-tab-btn" onclick="App.switchLegalTab('soporte')">
                    <i class="fas fa-headset"></i> Soporte Técnico
                </button>
            </div>

            <div class="modal-body legal-modal-body">
                <!-- PANE 1: TÉRMINOS Y CONDICIONES -->
                <div id="legalPaneTerminos" class="legal-pane">
                    <div class="legal-article">
                        <h4 class="legal-article-title"><i class="fas fa-circle-check text-celeste"></i> 1. Objeto y Alcance del Servicio</h4>
                        <p>Expiry es una plataforma web tipo Software como Servicio (SaaS) orientada a la gestión inteligente, clasificación semafórica de lotes y prevención de pérdidas por caducidad de productos en despensas, autoservicios y minimarkets. La utilización del software confiere una licencia de uso bajo los presentes términos.</p>
                    </div>

                    <div class="legal-article">
                        <h4 class="legal-article-title"><i class="fas fa-shield-alt text-celeste"></i> 2. Custodia del PIN de Seguridad y Responsabilidad</h4>
                        <p>El administrador del negocio es el único responsable de la custodia y confidencialidad del <strong>PIN Maestro de 4 dígitos</strong>. Dicho código autoriza operaciones irreversibles en el inventario, incluyendo la baja por descarte de mercadería y la edición manual de fechas de expiración.</p>
                    </div>

                    <div class="legal-article">
                        <h4 class="legal-article-title"><i class="fas fa-clipboard-check text-celeste"></i> 3. Responsabilidad Bromatológica y Comercial</h4>
                        <p>Expiry calcula días remanentes, genera clasificaciones automáticas y sugiere promociones relámpago a partir de los datos cargados por los usuarios. La verificación física de los paquetes, su estado organoléptico y el cumplimiento de las normativas sanitarias municipales y bromatológicas corresponden al comercio.</p>
                    </div>

                    <div class="legal-article">
                        <h4 class="legal-article-title"><i class="fas fa-laptop-code text-celeste"></i> 4. Propiedad Intelectual y Restricciones</h4>
                        <p>El código fuente, arquitectura de base de datos, algoritmos de cálculo de rotación FIFO y componentes visuales son propiedad intelectual exclusiva de Expiry SaaS. Queda expresamente prohibida su descompilación, clonación o redistribución no autorizada.</p>
                    </div>

                    <div class="legal-article">
                        <h4 class="legal-article-title"><i class="fas fa-server text-celeste"></i> 5. Disponibilidad y Respaldos</h4>
                        <p>Garantizamos un acuerdo de nivel de servicio (SLA) con disponibilidad del 99.8% anual en infraestructura segura en la nube. Las tareas de mantenimiento se efectúan en horarios de bajo impacto comercial con copias de seguridad incrementales automáticas.</p>
                    </div>
                </div>

                <!-- PANE 2: POLÍTICA DE PRIVACIDAD -->
                <div id="legalPanePrivacidad" class="legal-pane hidden">
                    <div class="legal-article">
                        <h4 class="legal-article-title"><i class="fas fa-lock text-celeste"></i> 1. Confidencialidad de Costos y Precios</h4>
                        <p>Toda la información financiera ingresada en Expiry (precios de costo de proveedores, precios de venta al público, valor del stock en riesgo y estimaciones de ahorro) pertenece exclusivamente al comercio usuario. Expiry jamás comparte ni analiza estos datos con fines comerciales externos.</p>
                    </div>

                    <div class="legal-article">
                        <h4 class="legal-article-title"><i class="fas fa-handshake-slash text-celeste"></i> 2. No Comercialización de Información</h4>
                        <p>Bajo ningún concepto vendemos, comercializamos, alquilamos ni transferimos bases de datos o información de productos a distribuidores mayoristas, marcas de consumo masivo ni terceras empresas.</p>
                    </div>

                    <div class="legal-article">
                        <h4 class="legal-article-title"><i class="fas fa-shield-halved text-celeste"></i> 3. Estándares OWASP y Cifrado de Grado Bancario</h4>
                        <p>Nuestra plataforma cumple con las directivas de seguridad VulnScope y OWASP Top 10: tráfico 100% cifrado bajo HTTPS con forzado HSTS, contraseñas protegidas con algoritmos BCrypt con salt criptográfico, cookies blindadas (HttpOnly, SameSite=Strict) y protección integral contra ataques CSRF e inyección SQL.</p>
                    </div>

                    <div class="legal-article">
                        <h4 class="legal-article-title"><i class="fas fa-history text-celeste"></i> 4. Auditoría Inmutable</h4>
                        <p>Cada modificación de inventario, cambio de fecha o baja por merma queda registrada en la bitácora histórica con marca temporal y usuario responsable, brindando protección al dueño del negocio ante controversias o descuidos internos.</p>
                    </div>

                    <div class="legal-article">
                        <h4 class="legal-article-title"><i class="fas fa-download text-celeste"></i> 5. Portabilidad y Eliminación Total</h4>
                        <p>El cliente tiene derecho en todo momento a solicitar la descarga completa de su historial en formatos abiertos (CSV / JSON) o requerir la eliminación total y definitiva de sus datos de nuestros servidores.</p>
                    </div>
                </div>

                <!-- PANE 3: SOPORTE TÉCNICO -->
                <div id="legalPaneSoporte" class="legal-pane hidden">
                    <div class="support-cards-grid">
                        <div class="support-box-card">
                            <div class="support-badge-icon icon-whatsapp">
                                <i class="fab fa-whatsapp"></i>
                            </div>
                            <div>
                                <strong class="support-title">WhatsApp de Soporte Urgente</strong>
                                <p class="support-desc">Canal directo para consultas operativas rápidas, dudas de reposición o desbloqueo de acceso.</p>
                                <a href="https://wa.me/595981000111?text=Hola,%20necesito%20soporte%20con%20Expiry" target="_blank" class="support-link link-whatsapp">
                                    <i class="fab fa-whatsapp"></i> +595 981 000 111 (Chat en Vivo)
                                </a>
                            </div>
                        </div>

                        <div class="support-box-card">
                            <div class="support-badge-icon icon-email">
                                <i class="fas fa-envelope"></i>
                            </div>
                            <div>
                                <strong class="support-title">Mesa de Ayuda por Correo</strong>
                                <p class="support-desc">Atención técnica para vinculación de cuentas de correo, servidor SMTP y configuración avanzada.</p>
                                <a href="mailto:roman.riveros.66@gmail.com?subject=Soporte%20Tecnico%20Expiry" class="support-link link-email">
                                    <i class="fas fa-paper-plane"></i> roman.riveros.66@gmail.com
                                </a>
                            </div>
                        </div>
                    </div>

                    <div class="legal-article" style="margin-top: 16px;">
                        <h4 class="legal-article-title"><i class="fas fa-clock text-celeste"></i> Horario de Cobertura y Atención</h4>
                        <p><strong>Lunes a Sábados: 07:00 a 21:00 hs.</strong> Atención prioritaria matutina durante la recepción de mercadería perecedera. Guardias pasivas para contingencias críticas los días domingos.</p>
                    </div>

                    <div class="legal-article">
                        <h4 class="legal-article-title"><i class="fas fa-barcode text-celeste"></i> Asistencia para Lectores de Código de Barras</h4>
                        <ul class="support-list-bullets">
                            <li><strong>Cámara del Celular:</strong> Compatible con Chrome en Android y Safari en iPhone sin instalar APKs externas.</li>
                            <li><strong>Lectores USB de Mano:</strong> Compatibles 100% Plug & Play con cualquier PC de caja (modo emulación teclado automático).</li>
                            <li><strong>Lectores Inalámbricos Bluetooth:</strong> Enlace instantáneo para reposición y control en góndolas y depósito.</li>
                        </ul>
                    </div>

                    <div class="legal-article">
                        <h4 class="legal-article-title"><i class="fas fa-paper-plane text-celeste"></i> Puesta en Marcha de Reportes Matutinos</h4>
                        <p>Te acompañamos paso a paso para configurar tu contraseña de aplicación de Gmail o API de Resend para que todo tu equipo reciba a las 08:00 AM el correo con los productos en riesgo del día.</p>
                    </div>
                </div>
            </div>

            <div class="modal-footer" style="padding: 14px 24px; background: #f8fafc; border-top: 1px solid #e2e8f0; display: flex; justify-content: flex-end;">
                <button type="button" class="btn btn-secondary" onclick="App.closeLegalModal()">
                    <i class="fas fa-check"></i> Entendido / Cerrar
                </button>
            </div>
        </div>
    </div>

    <!-- Contenedor de Alertas Toast -->
    <div id="toastContainer" class="toast-container"></div>

    <!-- Script Principal de la Aplicación con Subresource Integrity (SRI) -->
    <script src="js/app.js?v=<?= $appJsVer ?>" <?= !empty($appJsHash) ? 'integrity="' . $appJsHash . '" crossorigin="anonymous"' : '' ?>></script>
</body>
</html>
