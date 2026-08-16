<?php
declare(strict_types=1);

require_once __DIR__ . '/Config.php';

/**
 * Загрузка конфигурации и автозагрузка классов src/.
 */
$configFile = __DIR__ . '/../config/config.php';
if (!is_file($configFile)) {
    $configFile = __DIR__ . '/../config/config.example.php';
}
Config::load($configFile);

spl_autoload_register(static function (string $class): void {
    $file = __DIR__ . '/' . $class . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});

date_default_timezone_set('Europe/Moscow');
error_reporting(E_ALL);
ini_set('display_errors', '0');

set_exception_handler(static function (Throwable $e): void {
    Response::error('internal_error', 'Internal server error: ' . $e->getMessage(), null, 500);
});
