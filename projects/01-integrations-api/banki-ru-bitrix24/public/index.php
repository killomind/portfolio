<?php
declare(strict_types=1);

/**
 * Front controller — точка входа REST API.
 * Разворачивается в корне веба (или подкаталоге, см. base_path в конфигурации).
 */

require dirname(__DIR__) . '/src/Bootstrap.php';

$router = new Router();

$router->add('POST', '/token', static function (): string {
    return Response::success((new TokenService())->issue());
});

$router->add('POST', '/check-double', static function (): string {
    (new TokenService())->validate();
    $data = Response::readJsonBody();

    return Response::success((new DuplicateService())->check((string)($data['phone'] ?? '')));
});

$router->add('POST', '/application-for-decisions', static function (): string {
    return (new ApplicationService())->create();
});

$checkDecision = static function (array $params): string {
    return (new DecisionService())->resolve((string)($params['id'] ?? ''));
};

$router->add('GET', '/check-decisions/{id}', $checkDecision);
$router->add('POST', '/check-decisions/{id}', $checkDecision);

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$basePath = rtrim((string)Config::get('base_path', ''), '/');

if ($basePath !== '' && str_starts_with($uri, $basePath)) {
    $uri = substr($uri, strlen($basePath));
}
if ($uri === '') {
    $uri = '/';
}

$router->dispatch($uri, $_SERVER['REQUEST_METHOD'] ?? 'GET');
