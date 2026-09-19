<?php

declare(strict_types=1);

final class Database
{
    private static ?PDO $connection = null;
    private static bool $environmentLoaded = false;

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
        self::loadEnvironmentFile();
        $value = getenv($key);

        if ($value === false || $value === '') {
            if ($default !== null) {
                return $default;
            }

            throw new RuntimeException("A variavel de ambiente {$key} nao foi configurada.");
        }

        return $value;
    }

    private static function loadEnvironmentFile(): void
    {
        if (self::$environmentLoaded) {
            return;
        }

        self::$environmentLoaded = true;
        $file = dirname(__DIR__) . '/.env';
        if (!is_readable($file)) {
            return;
        }

        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }

            [$name, $value] = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);
            if ($name === '' || getenv($name) !== false) {
                continue;
            }

            if (
                strlen($value) >= 2
                && (($value[0] === '"' && $value[strlen($value) - 1] === '"')
                    || ($value[0] === "'" && $value[strlen($value) - 1] === "'"))
            ) {
                $value = substr($value, 1, -1);
            }

            putenv($name . '=' . $value);
        }
    }
}
