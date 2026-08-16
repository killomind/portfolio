<?php
declare(strict_types=1);

/**
 * Формат ответов и ошибок по спецификации Banki.ru.
 *
 * Ошибка: {"data":[{"errors":[{"code":"...","message":"...","field":"..."}]}]}
 */
final class Response
{
    public static function success(array $payload, int $httpCode = 200): string
    {
        return self::json(['data' => $payload], $httpCode);
    }

    public static function error(string $code, string $message, ?string $field = null, int $httpCode = 400): never
    {
        $error = ['code' => $code, 'message' => $message];
        if ($field !== null) {
            $error['field'] = $field;
        }

        http_response_code($httpCode);
        echo json_encode(
            ['data' => [['errors' => [$error]]]],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
        exit;
    }

    public static function json(array $data, int $httpCode = 200): string
    {
        http_response_code($httpCode);
        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Читает и разбирает JSON из тела запроса.
     */
    public static function readJsonBody(): array
    {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            self::error('invalid_json', 'Invalid JSON: ' . json_last_error_msg(), 'body', 400);
        }

        return is_array($data) ? $data : [];
    }

    /**
     * Извлекает Bearer-токен из заголовка Authorization.
     */
    public static function bearerToken(): string
    {
        $headers = array_change_key_case(getallheaders(), CASE_LOWER);
        $auth = $headers['authorization'] ?? '';

        if (stripos($auth, 'Bearer ') === 0) {
            return substr($auth, 7);
        }

        return $auth;
    }
}
