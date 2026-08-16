<?php
declare(strict_types=1);

/**
 * Простой роутер: метод + шаблон пути → обработчик.
 * Плейсхолдер {id} захватывает сегмент пути.
 */
final class Router
{
    /** @var array<int, array{method: string, pattern: string, handler: callable}> */
    private array $routes = [];

    public function add(string $method, string $pattern, callable $handler): void
    {
        $this->routes[] = [
            'method' => strtoupper($method),
            'pattern' => $pattern,
            'handler' => $handler,
        ];
    }

    public function dispatch(string $uri, string $method = 'GET'): void
    {
        $method = strtoupper($method);

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            if (preg_match($this->regex($route['pattern']), $uri, $matches) === 1) {
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                echo ($route['handler'])($params);
                return;
            }
        }

        Response::error('endpoint_not_found', 'Endpoint not found: ' . $uri, null, 404);
    }

    private function regex(string $pattern): string
    {
        $pattern = preg_quote($pattern, '#');
        $pattern = str_replace('\\{id\\}', '(?<id>[^/]+)', $pattern);

        return '#^' . $pattern . '$#';
    }
}
