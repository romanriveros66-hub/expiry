<?php
/**
 * Endpoint de Autenticación y Registro de Negocios
 * Sistema de Vencimientos - Expiry
 */

require_once __DIR__ . '/security_helper.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$pdo = Database::getConnection();

switch ($action) {
    case 'check':
        $user = getAuthUser();
        $stmtCfg = $pdo->query("SELECT nombre_negocio, moneda_simbolo, dias_alerta_amarillo, dias_alerta_rojo FROM configuracion LIMIT 1");
        $config = $stmtCfg->fetch() ?: [
            'nombre_negocio' => 'Vencimientos Alert - Minimarket San José',
            'moneda_simbolo' => 'Gs.',
            'dias_alerta_amarillo' => 30,
            'dias_alerta_rojo' => 7
        ];

        jsonResponse([
            'status' => 'success',
            'authenticated' => $user !== null,
            'user' => $user,
            'config' => $config,
            'csrf_token' => getCsrfToken()
        ]);
        break;

    case 'login':
        $identificador = trim($_POST['usuario'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($identificador) || empty($password)) {
            jsonResponse(['status' => 'error', 'message' => 'Por favor complete usuario y contraseña.'], 400);
        }

        $stmt = $pdo->prepare("SELECT id, nombre, usuario, email, password_hash, rol, activo FROM usuarios WHERE usuario = ? OR email = ? LIMIT 1");
        $stmt->execute([$identificador, $identificador]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            jsonResponse(['status' => 'error', 'message' => 'Credenciales incorrectas. Verifique usuario y contraseña.'], 401);
        }

        if ((int)$user['activo'] !== 1) {
            jsonResponse(['status' => 'error', 'message' => 'Esta cuenta de usuario se encuentra deshabilitada.'], 403);
        }

        // Regenerar ID de sesión para prevenir Session Fixation
        session_regenerate_id(true);

        $_SESSION['user'] = [
            'id' => (int)$user['id'],
            'nombre' => $user['nombre'],
            'usuario' => $user['usuario'],
            'email' => $user['email'],
            'rol' => $user['rol']
        ];

        jsonResponse([
            'status' => 'success',
            'message' => '¡Bienvenido de nuevo, ' . htmlspecialchars($user['nombre'], ENT_QUOTES, 'UTF-8') . '!',
            'user' => $_SESSION['user'],
            'csrf_token' => getCsrfToken()
        ]);
        break;

    case 'register':
        $nombre = trim($_POST['nombre'] ?? '');
        $usuario = trim($_POST['usuario'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $nombreNegocio = trim($_POST['nombre_negocio'] ?? '');
        $password = $_POST['password'] ?? '';
        $adminPin = trim($_POST['admin_pin'] ?? '');

        // Validaciones
        if (empty($nombre) || empty($usuario) || empty($password) || empty($adminPin)) {
            jsonResponse(['status' => 'error', 'message' => 'Todos los campos obligatorios deben ser completados.'], 400);
        }

        if (strlen($usuario) < 3) {
            jsonResponse(['status' => 'error', 'message' => 'El nombre de usuario debe tener al menos 3 caracteres.'], 400);
        }

        if (strlen($password) < 6) {
            jsonResponse(['status' => 'error', 'message' => 'La contraseña debe tener al menos 6 caracteres.'], 400);
        }

        if (!preg_match('/^\d{4,6}$/', $adminPin)) {
            jsonResponse(['status' => 'error', 'message' => 'El PIN de Administrador debe ser numérico de 4 a 6 dígitos.'], 400);
        }

        // Verificar si usuario ya existe
        $stmtCheck = $pdo->prepare("SELECT id FROM usuarios WHERE usuario = ? OR (email != '' AND email IS NOT NULL AND email = ?)");
        $stmtCheck->execute([$usuario, $email]);
        if ($stmtCheck->fetch()) {
            jsonResponse(['status' => 'error', 'message' => 'El nombre de usuario o correo ya se encuentra registrado.'], 409);
        }

        try {
            $pdo->beginTransaction();

            $passHash = password_hash($password, PASSWORD_BCRYPT);
            $pinHash = password_hash($adminPin, PASSWORD_BCRYPT);

            // Crear usuario administrador
            $stmtInsert = $pdo->prepare("
                INSERT INTO usuarios (nombre, usuario, email, password_hash, rol, activo)
                VALUES (?, ?, ?, ?, 'admin', 1)
            ");
            $stmtInsert->execute([$nombre, $usuario, !empty($email) ? $email : null, $passHash]);
            $newUserId = (int)$pdo->lastInsertId();

            // Si especificó nombre de negocio, actualizar la configuración del minimarket y el PIN
            if (!empty($nombreNegocio)) {
                $stmtCfg = $pdo->prepare("
                    UPDATE configuracion 
                    SET nombre_negocio = ?, admin_pin_hash = ?
                    WHERE id = 1
                ");
                $stmtCfg->execute([$nombreNegocio, $pinHash]);
            } else {
                $stmtCfg = $pdo->prepare("UPDATE configuracion SET admin_pin_hash = ? WHERE id = 1");
                $stmtCfg->execute([$pinHash]);
            }

            $pdo->commit();

            session_regenerate_id(true);
            $_SESSION['user'] = [
                'id' => $newUserId,
                'nombre' => $nombre,
                'usuario' => $usuario,
                'email' => $email,
                'rol' => 'admin'
            ];

            jsonResponse([
                'status' => 'success',
                'message' => '¡Cuenta y negocio creados exitosamente! Has iniciado sesión como Administrador.',
                'user' => $_SESSION['user'],
                'csrf_token' => getCsrfToken()
            ]);

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            jsonResponse(['status' => 'error', 'message' => 'Error al registrar la cuenta: ' . $e->getMessage()], 500);
        }
        break;

    case 'demo_login':
        $rol = $_POST['rol'] ?? 'admin';
        $userDemo = ($rol === 'empleado') ? 'cajero1' : 'admin';

        $stmt = $pdo->prepare("SELECT id, nombre, usuario, email, rol, activo FROM usuarios WHERE usuario = ? LIMIT 1");
        $stmt->execute([$userDemo]);
        $user = $stmt->fetch();

        if ($user) {
            session_regenerate_id(true);
            $_SESSION['user'] = [
                'id' => (int)$user['id'],
                'nombre' => $user['nombre'],
                'usuario' => $user['usuario'],
                'email' => $user['email'],
                'rol' => $user['rol']
            ];

            jsonResponse([
                'status' => 'success',
                'message' => 'Acceso demo concedido como ' . ($rol === 'admin' ? 'Administrador' : 'Empleado / Cajero'),
                'user' => $_SESSION['user'],
                'csrf_token' => getCsrfToken()
            ]);
        } else {
            jsonResponse(['status' => 'error', 'message' => 'Usuario demo no encontrado.'], 404);
        }
        break;

    case 'logout':
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();
        jsonResponse(['status' => 'success', 'message' => 'Sesión cerrada correctamente.']);
        break;

    default:
        jsonResponse(['status' => 'error', 'message' => 'Acción no permitida.'], 400);
}
