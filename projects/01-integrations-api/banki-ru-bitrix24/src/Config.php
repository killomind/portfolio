<?php
declare(strict_types=1);

/**
 * Простое хранилище конфигурации (без .env и composer).
 */
final class Config
{
    private static array $data = [];

    public static function load(string $file): void
    {
        self::$data = require $file;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::$data[$key] ?? $default;
    }
}
