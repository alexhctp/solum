<?php

declare(strict_types=1);

final class Database
{
    private static ?PDO $connection = null;

    public static function connection(): PDO
    {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }

        $host = self::environment('DB_HOST', '127.0.0.1');
        $port = self::environment('DB_PORT', '3306');
        $name = self::environment('DB_NAME', 'solum');
        $user = self::environment('DB_USER');
        $password = self::environment('DB_PASS');

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $host,
            $port,
            $name
        );

        try {
            self::$connection = new PDO($dsn, $user, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_STRINGIFY_FETCHES => false,
            ]);
        } catch (PDOException $exception) {
            error_log('Falha ao conectar ao banco de dados: ' . $exception->getMessage());
            throw new RuntimeException('Nao foi possivel conectar ao banco de dados.', 0, $exception);
        }

        return self::$connection;
    }

    private static function environment(string $key, ?string $default = null): string
    {
        $value = getenv($key);

        if ($value === false || $value === '') {
            if ($default !== null) {
                return $default;
            }

            throw new RuntimeException("A variavel de ambiente {$key} nao foi configurada.");
        }

        return $value;
    }
}
