<?php
// Защищённая страница отчёта для клиента (пример механизма).
// Пользователь/пароль вынесены в переменные окружения, чтобы не хранить
// их в коде. Отчёт (report.html) подключается только после авторизации.

$valid_username = getenv('REPORT_USER') ?: 'demo';
$valid_password = getenv('REPORT_PASS') ?: 'demo';

$httpUser = isset($_SERVER['PHP_AUTH_USER']) ? $_SERVER['PHP_AUTH_USER'] : '';
$httpPass = isset($_SERVER['PHP_AUTH_PW']) ? $_SERVER['PHP_AUTH_PW'] : '';

if ($httpUser !== $valid_username || $httpPass !== $valid_password) {
    header('WWW-Authenticate: Basic realm="Отчет"');
    header('HTTP/1.0 401 Unauthorized');
    echo 'Доступ запрещён. Требуется авторизация.';
    exit;
}

// === Авторизация успешна — выводим отчёт ===
$report_file = __DIR__ . '/report.html';

if (file_exists($report_file)) {
    include $report_file;
} else {
    echo 'Файл отчёта не найден. Обратитесь к администратору.';
}