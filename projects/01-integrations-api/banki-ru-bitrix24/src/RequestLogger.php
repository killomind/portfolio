<?php
declare(strict_types=1);

/**
 * Логирование запросов проверки дублей.
 *
 * Формат строк зафиксирован: его разбирает tools/debug_duplicates_report.php.
 *   IN: 2026-08-12 10:00:00 [req_...] IN: 79000000000
 *   OUT: 2026-08-12 10:00:00 [req_...] OUT: repeat (lead found)
 */
final class RequestLogger
{
    private string $file;

    public function __construct(?string $file = null)
    {
        $this->file = $file ?? (string)Config::get('duplicate_log', __DIR__ . '/../storage/check_double.log');
    }

    /**
     * ID запроса: берётся из заголовка X-Request-ID либо генерируется.
     */
    public static function requestId(): string
    {
        $headers = array_change_key_case(getallheaders(), CASE_LOWER);
        if (!empty($headers['x-request-id'])) {
            return (string)$headers['x-request-id'];
        }

        return uniqid('req_', true) . '_' . random_int(1000, 9999);
    }

    public function logIn(string $requestId, string $phone): void
    {
        $this->append("{$requestId}] IN: {$phone}");
    }

    public function logOut(string $requestId, string $reason): void
    {
        $this->append("{$requestId}] OUT: {$reason}\n");
    }

    private function append(string $message): void
    {
        $line = date('Y-m-d H:i:s') . " [{$message}\n";
        @file_put_contents($this->file, $line, FILE_APPEND);
    }
}
