<?php
declare(strict_types=1);

/**
 * Тонкий клиент REST API Битрикс24 через вебхук.
 *
 * @see https://dev.1c-bitrix.ru/rest_help/
 */
final class BitrixClient
{
    private string $webhookUrl;
    private int $timeout;

    private static ?self $instance = null;

    public function __construct(string $webhookUrl, int $timeout = 10)
    {
        $this->webhookUrl = rtrim($webhookUrl, '/');
        $this->timeout = $timeout;
    }

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self(
                (string)Config::get('webhook_url', ''),
                (int)Config::get('bitrix_timeout', 10)
            );
        }

        return self::$instance;
    }

    /**
     * Вызывает метод REST API.
     *
     * @return array{result?: mixed, error?: string, error_description?: string}
     */
    public function call(string $method, array $params = []): array
    {
        $url = $this->webhookUrl . '/' . $method . '.json';

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => 'Content-Type: application/x-www-form-urlencoded',
                'content' => http_build_query($params),
                'timeout' => $this->timeout,
                'ignore_errors' => true,
            ],
        ]);

        $response = @file_get_contents($url, false, $context);

        if ($response === false) {
            $message = error_get_last()['message'] ?? 'Unknown error';
            error_log("[BankiRu] Bitrix API error: {$message}");
            return ['error' => $message];
        }

        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            return ['error' => 'Invalid response from Bitrix API'];
        }

        if (isset($decoded['error'])) {
            error_log(
                '[BankiRu] Bitrix API error: ' . ($decoded['error'] ?? '')
                . ' ' . ($decoded['error_description'] ?? '')
            );
        }

        return $decoded;
    }
}
