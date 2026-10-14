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
            // 1. Soporte para URL directa de Supabase / PostgreSQL (DATABASE_URL, POSTGRES_URL, POSTGRES_PRISMA_URL, SUPABASE_DB_URL)
            // Creadas automáticamente por la integración de Supabase en Vercel o manualmente
            $databaseUrl = getenv('DATABASE_URL') 
                ?: (getenv('POSTGRES_URL') 
                ?: (getenv('POSTGRES_PRISMA_URL') 
                ?: (getenv('SUPABASE_DB_URL') 
                ?: getenv('POSTGRES_URL_NON_POOLING'))));

            if (!empty($databaseUrl)) {
                $dbParts = parse_url($databaseUrl);
                $host    = $dbParts['host'] ?? 'db.dmmlhspmtumzoiudffsf.supabase.co';
                $port    = $dbParts['port'] ?? 5432;
                $dbname  = rtrim(ltrim($dbParts['path'] ?? 'postgres', '/'), '.');
                $user    = isset($dbParts['user']) ? urldecode($dbParts['user']) : 'postgres';
                $pass    = isset($dbParts['pass']) ? trim(urldecode($dbParts['pass']), '[]') : '';

                $dsn = "pgsql:host={$host};port={$port};dbname={$dbname};sslmode=require";
                $options = [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false
                ];

                try {
                    self::$instance = new PDO($dsn, $user, $pass, $options);
                    return self::$instance;
                } catch (PDOException $e) {
                    error_log("Supabase PostgreSQL Error: " . $e->getMessage());
                    http_response_code(500);
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode([
                        'status'  => 'error',
                        'message' => 'Error de conexión con Supabase PostgreSQL: ' . $e->getMessage()
                    ]);
                    exit;
                }
            }

            // 2. Conexión por variables individuales (MySQL local o PostgreSQL en la nube)
            // Soporta variables de Vercel/Supabase (POSTGRES_HOST, etc.) o estándar
            $isPgsql = getenv('DB_DRIVER') === 'pgsql' || getenv('POSTGRES_HOST') !== false;
            $driver  = $isPgsql ? 'pgsql' : (getenv('DB_DRIVER') ?: 'mysql');
            $host    = getenv('POSTGRES_HOST') ?: (getenv('DB_HOST') ?: (getenv('MYSQLHOST') ?: self::DB_HOST));
            $port    = getenv('POSTGRES_PORT') ?: (getenv('DB_PORT') ?: (getenv('MYSQLPORT') ?: ($driver === 'pgsql' ? 5432 : self::DB_PORT)));
            $dbname  = getenv('POSTGRES_DATABASE') ?: (getenv('DB_NAME') ?: (getenv('MYSQLDATABASE') ?: ($driver === 'pgsql' ? 'postgres' : self::DB_NAME)));
            $user    = getenv('POSTGRES_USER') ?: (getenv('DB_USER') ?: (getenv('MYSQLUSER') ?: self::DB_USER));
            $pass    = getenv('POSTGRES_PASSWORD') !== false 
                ? getenv('POSTGRES_PASSWORD') 
                : (getenv('DB_PASS') !== false 
                    ? getenv('DB_PASS') 
                    : (getenv('MYSQLPASSWORD') !== false 
                        ? getenv('MYSQLPASSWORD') 
                        : self::DB_PASS));

            if ($driver === 'pgsql') {
                $dsn = "pgsql:host={$host};port={$port};dbname={$dbname};sslmode=require";
                $options = [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false
                ];
            } else {
                $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', $host, $port, $dbname, self::DB_CHARSET);
                $options = [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
                ];
            }

            try {
                self::$instance = new PDO($dsn, $user, $pass, $options);
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
