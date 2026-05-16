<?php

declare(strict_types=1);

namespace Quiz\Database;

use PDO;
use PDOException;
use Quiz\Exceptions\DatabaseConfigurationException;

class Connection
{
    private static ?PDO $instance = null;
    private static bool $envLoaded = false;

    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            try {
                self::loadEnvIfNeeded();

                $host = self::getEnv('DB_HOST', 'localhost');
                $port = self::getEnv('DB_PORT', '3306');
                $socket = self::getEnv('DB_SOCKET');
                $name = self::getEnv('DB_NAME', '');
                $user = self::getEnv('DB_USER', '');
                $pass = self::normalizePassword(self::getEnv('DB_PASS', ''));

                $dsn = self::buildDsn($host, $port, $name, $socket);

                self::$instance = new PDO(
                    $dsn,
                    $user,
                    $pass,
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    ]
                );
            } catch (PDOException $e) {
                http_response_code(500);
                echo json_encode(['error' => 'Database connection failed']);
                exit;
            } catch (DatabaseConfigurationException $e) {
                http_response_code(500);
                echo json_encode(['error' => $e->getMessage()]);
                exit;
            }
        }

        return self::$instance;
    }

    private static function getEnv(string $key, ?string $default = null): ?string
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

        if ($value === false || $value === null || $value === '') {
            return $default;
        }

        return (string) $value;
    }

    private static function buildDsn(
        string $host,
        ?string $port,
        string $name,
        ?string $socket
    ): string {
        $dsnParts = ['mysql:'];

        if ($socket !== null && $socket !== '') {
            $dsnParts[] = "unix_socket={$socket}";
        } else {
            $dsnParts[] = "host={$host}";

            if ($port !== null && $port !== '') {
                $dsnParts[] = "port={$port}";
            }
        }

        $dsnParts[] = "dbname={$name}";
        $dsnParts[] = 'charset=utf8mb4';

        return implode(';', $dsnParts);
    }

    private static function normalizePassword(?string $password): ?string
    {
        if ($password === null) {
            return null;
        }

        $password = trim($password);

        return $password === '' ? null : $password;
    }

    private static function loadEnvIfNeeded(): void
    {
        if (self::$envLoaded) {
            return;
        }

        self::$envLoaded = true;

        $envFile = dirname(__DIR__, 2) . '/.env'; // backend/.env
        if (!is_readable($envFile)) {
            return;
        }

        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed !== '' && !str_starts_with($trimmed, '#')) {
                $parts = explode('=', $trimmed, 2);
                if (count($parts) === 2) {
                    $key = trim($parts[0]);
                    $value = trim(trim($parts[1]), '"\'');
                    if ($key !== '' && !array_key_exists($key, $_ENV)) {
                        $_ENV[$key] = $value;
                    }
                }
            }
        }
    }

    private function __construct()
    {
        // Singleton pattern
    }
    private function __clone()
    {
        // Singleton pattern
    }
}

/**
 * Ez egy Singleton — az adatbázis kapcsolat csak egyszer jön létre, minden osztály ugyanazt használja.
 */
