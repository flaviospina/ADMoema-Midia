<?php
// app/config.php — lê o arquivo .env e expõe a função config()
declare(strict_types=1);

final class Config
{
    private static ?array $values = null;

    public static function load(string $envFile): void
    {
        $values = [];
        if (is_readable($envFile)) {
            foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                $line = trim($line);
                if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
                    continue;
                }
                [$key, $value] = explode('=', $line, 2);
                $value = trim($value);
                // Remove aspas opcionais
                if (strlen($value) >= 2 && ($value[0] === '"' || $value[0] === "'") && $value[-1] === $value[0]) {
                    $value = substr($value, 1, -1);
                }
                $values[trim($key)] = $value;
            }
        }
        self::$values = $values;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        if (self::$values === null) {
            throw new RuntimeException('Configuração não carregada.');
        }
        $value = self::$values[$key] ?? '';
        return $value === '' ? $default : $value;
    }

    public static function int(string $key, int $default): int
    {
        $value = self::get($key);
        return is_numeric($value) ? (int) $value : $default;
    }

    public static function bool(string $key, bool $default): bool
    {
        $value = self::get($key);
        if ($value === null) {
            return $default;
        }
        return in_array(strtolower((string) $value), ['1', 'true', 'sim', 'yes', 'on'], true);
    }

    public static function isLocal(): bool
    {
        return self::get('APP_ENV', 'production') === 'local';
    }
}
