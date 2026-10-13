<?php
/**
 * Conexión Centralizada PDO MySQL (Singleton)
 * Base de Datos: expiry
 * Sistema: Alerta de Vencimientos para Despensas / Minimarkets
 */

class Database {
    /** @var PDO|null */
    private static $instance = null;

    const DB_HOST = '127.0.0.1';
    const DB_PORT = '3306';
    const DB_NAME = 'expiry';
    const DB_USER = 'root';
    const DB_PASS = '';
    const DB_CHARSET = 'utf8mb4';

    private function __construct() {}
    private function __clone() {}

    public static function getConnection(): PDO {
        if (self::$instance === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                self::DB_HOST,
                self::DB_PORT,
                self::DB_NAME,
                self::DB_CHARSET
            );

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
            ];

            try {
                self::$instance = new PDO($dsn, self::DB_USER, self::DB_PASS, $options);
            } catch (PDOException $e) {
                // Registro seguro de error sin revelar credenciales en pantalla
                error_log("Database Connection Error: " . $e->getMessage());
                http_response_code(500);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Error de conexión con la base de datos de vencimientos'
                ]);
                exit;
            }
        }

        return self::$instance;
    }
}
