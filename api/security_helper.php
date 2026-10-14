<?php
/**
 * Helper de Seguridad, Sesiones y Cumplimiento OWASP / VulnScope
 * Sistema de Vencimientos - Expiry
 */

error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED & ~E_NOTICE);
ini_set('display_errors', '0');
if (!ob_get_level()) {
    ob_start();
}

require_once __DIR__ . '/../db.php';

// 1. Configuración de Cookies Seguras (Cookies & CORS)
if (session_status() === PHP_SESSION_NONE) {
    $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
    
    // PHP 7.2 compatible session cookie parameters
    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'domain'   => '',
            'secure'   => $isSecure,
            'httponly' => true,
            'samesite' => 'Strict'
        ]);
    } else {
        session_set_cookie_params(0, '/; samesite=Strict', '', $isSecure, true);
    }

    ini_set('session.use_only_cookies', 1);
    ini_set('session.use_trans_sid', 0);
    session_start();
}

// 2. Cabeceras de Seguridad HTTP (Cabeceras, CSP, HSTS)
function sendSecurityHeaders() {
    if (!headers_sent()) {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://fonts.googleapis.com; font-src 'self' https://cdnjs.cloudflare.com https://fonts.gstatic.com data:; img-src 'self' data: https:; connect-src 'self'; frame-ancestors 'self'; base-uri 'self'; form-action 'self';");
        header("Permissions-Policy: camera=(self), microphone=(), geolocation=(), payment=()");

        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
    }
}

sendSecurityHeaders();

// 3. Clave Secreta y Token Criptográfico de Sesión para Entornos Serverless
function getAuthSecretKey(): string {
    return hash('sha256', getenv('DATABASE_URL') ?: (getenv('SUPABASE_DB_URL') ?: 'expiry_secure_salt_key_2026_minimarket'));
}

function setSignedAuthCookie(array $user): void {
    $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

    $csrfToken = getCsrfToken();
    $payload = [
        'user'       => $user,
        'csrf_token' => $csrfToken,
        'exp'        => time() + (86400 * 30) // 30 días
    ];
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
    $b64 = rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
    $sig = hash_hmac('sha256', $b64, getAuthSecretKey());
    $token = $b64 . '.' . $sig;

    if (PHP_VERSION_ID >= 70300) {
        setcookie('expiry_auth_token', $token, [
            'expires'  => time() + (86400 * 30),
            'path'     => '/',
            'domain'   => '',
            'secure'   => $isSecure,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
    } else {
        setcookie('expiry_auth_token', $token, time() + (86400 * 30), '/; samesite=Lax', '', $isSecure, true);
    }
    $_COOKIE['expiry_auth_token'] = $token;
}

function clearSignedAuthCookie(): void {
    if (PHP_VERSION_ID >= 70300) {
        setcookie('expiry_auth_token', '', [
            'expires'  => time() - 3600,
            'path'     => '/',
            'domain'   => '',
            'secure'   => false,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
    } else {
        setcookie('expiry_auth_token', '', time() - 3600, '/; samesite=Lax', '', false, true);
    }
    unset($_COOKIE['expiry_auth_token']);
}

function loadSignedAuthCookie(): ?array {
    $token = $_COOKIE['expiry_auth_token'] ?? '';
    if (empty($token) || strpos($token, '.') === false) {
        return null;
    }

    list($b64, $sig) = explode('.', $token, 2);
    $expectedSig = hash_hmac('sha256', $b64, getAuthSecretKey());
    if (!hash_equals($expectedSig, $sig)) {
        return null;
    }

    $json = base64_decode(strtr($b64, '-_', '+/'));
    $payload = json_decode($json, true);
    if (!is_array($payload) || empty($payload['user']) || empty($payload['exp'])) {
        return null;
    }

    if ($payload['exp'] < time()) {
        return null;
    }

    $_SESSION['user'] = $payload['user'];
    if (!empty($payload['csrf_token'])) {
        $_SESSION['csrf_token'] = $payload['csrf_token'];
    }

    return $payload['user'];
}

// 4. Manejo de CSRF Token
function getCsrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        loadSignedAuthCookie();
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCsrfToken(?string $token): bool {
    if (empty($_SESSION['csrf_token'])) {
        loadSignedAuthCookie();
    }
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

// 5. Salida Segura JSON
function jsonResponse($data, int $statusCode = 200) {
    if (ob_get_length()) {
        ob_clean();
    }
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// 6. Autenticación y Sesión de Usuario
function getAuthUser(): ?array {
    if (!empty($_SESSION['user'])) {
        return $_SESSION['user'];
    }
    return loadSignedAuthCookie();
}

function requireAuth(): array {
    $user = getAuthUser();
    if (!$user) {
        jsonResponse([
            'status' => 'error',
            'code' => 'UNAUTHORIZED',
            'message' => 'Sesión expirada o no iniciada. Por favor ingrese al sistema.'
        ], 401);
    }
    return $user;
}

function requireAdmin(): array {
    $user = requireAuth();
    if ($user['rol'] !== 'admin') {
        jsonResponse([
            'status' => 'error',
            'code' => 'FORBIDDEN',
            'message' => 'Acceso denegado: se requieren permisos de administrador.'
        ], 403);
    }
    return $user;
}

// 6. Obtención de IP Segura
function getClientIp(): string {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '127.0.0.1';
}

// 7. Verificación de PIN de Administrador con Auditoría Estricta
function verificarPinAdmin(string $pinIngresado, string $accion, $loteId = null, string $motivo = '', $valoresAnt = null, $valoresNuevos = null): bool {
    $pdo = Database::getConnection();

    // Consultar configuración
    $stmt = $pdo->query("SELECT admin_pin_hash, pin_intentos_max, bloqueo_minutos FROM configuracion LIMIT 1");
    $cfg = $stmt->fetch();

    if (!$cfg) {
        return false;
    }

    $ip = getClientIp();
    $userId = $_SESSION['user']['id'] ?? null;

    // Verificar si el PIN coincide
    $esValido = password_verify($pinIngresado, $cfg['admin_pin_hash']);

    // Registrar en auditoría
    try {
        $stmtAudit = $pdo->prepare("
            INSERT INTO auditoria_seguridad_pin 
            (accion, tabla_afectada, registro_id, valores_anteriores, valores_nuevos, motivo, usuario_id, ip_address, autorizado)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $antStr = is_array($valoresAnt) ? json_encode($valoresAnt, JSON_UNESCAPED_UNICODE) : $valoresAnt;
        $nuevStr = is_array($valoresNuevos) ? json_encode($valoresNuevos, JSON_UNESCAPED_UNICODE) : $valoresNuevos;

        $stmtAudit->execute([
            $accion,
            'lotes_vencimiento',
            $loteId,
            $antStr,
            $nuevStr,
            $motivo,
            $userId,
            $ip,
            $esValido ? 1 : 0
        ]);
    } catch (Exception $e) {
        error_log("Error guardando auditoría: " . $e->getMessage());
    }

    return $esValido;
}
