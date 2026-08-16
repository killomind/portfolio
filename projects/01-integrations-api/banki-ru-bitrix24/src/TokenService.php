<?php
declare(strict_types=1);

/**
 * Выпуск и проверка Bearer-токенов.
 *
 * Токены хранятся в JSON-файле (по умолчанию storage/tokens.json),
 * каталог хранилища закрыт от прямого доступа через .htaccess.
 */
final class TokenService
{
    private string $storageFile;

    public function __construct(?string $storageFile = null)
    {
        $this->storageFile = $storageFile ?? (string)Config::get('token_storage', __DIR__ . '/../storage/tokens.json');
    }

    /**
     * Проверяет пароль из тела запроса и выпускает новый токен.
     *
     * @return array{token: string, exp: int}
     */
    public function issue(): array
    {
        $validPassword = (string)Config::get('token_password', '');
        $body = Response::readJsonBody();

        if (($body['password'] ?? null) !== $validPassword) {
            Response::error('invalid_password', 'Invalid or missing password', 'password', 401);
        }

        $token = bin2hex(random_bytes(32));
        $ttl = (int)Config::get('token_ttl', 5000);
        $expiresAt = time() + $ttl;

        $tokens = $this->readTokens();
        $tokens[$token] = $expiresAt;
        $this->writeTokens($tokens);

        return ['token' => $token, 'exp' => $ttl];
    }

    /**
     * Проверяет Bearer-токен из заголовка Authorization.
     */
    public function validate(): void
    {
        $received = Response::bearerToken();

        if ($received === '') {
            Response::error('missing_auth_header', 'Authorization header missing', 'authorization', 401);
        }

        if (!is_file($this->storageFile)) {
            Response::error('no_tokens_file', 'No valid tokens found', null, 401);
        }

        $tokens = $this->readTokens();

        if (!isset($tokens[$received])) {
            Response::error('token_not_found', 'Token not found or invalid', 'authorization', 401);
        }

        if (time() > $tokens[$received]) {
            unset($tokens[$received]);
            $this->writeTokens($tokens);
            Response::error('token_expired', 'Token expired', 'authorization', 401);
        }
    }

    private function readTokens(): array
    {
        if (!is_file($this->storageFile)) {
            return [];
        }

        $data = json_decode((string)file_get_contents($this->storageFile), true);
        return is_array($data) ? $data : [];
    }

    private function writeTokens(array $tokens): void
    {
        $dir = dirname($this->storageFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        file_put_contents($this->storageFile, json_encode($tokens));
    }
}
