<?php
declare(strict_types=1);

/**
 * Тонкая обёртка для tools/debug_duplicates_report.php:
 * процедурная функция callBitrixApi() поверх BitrixClient.
 */

require_once dirname(__DIR__) . '/src/Bootstrap.php';

if (!function_exists('callBitrixApi')) {
    function callBitrixApi(string $method, array $params = []): array
    {
        return BitrixClient::instance()->call($method, $params);
    }
}
