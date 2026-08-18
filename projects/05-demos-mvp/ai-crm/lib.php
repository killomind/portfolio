<?php
// lib.php — база данных (JSON), роли, хелперы, layout
session_start();

define('DATA_DIR', __DIR__ . '/data/');

/**
 * Загрузка JSON-файла
 */
function load_json($filename) {
    $file = DATA_DIR . $filename;
    if (!file_exists($file)) {
        return [];
    }
    $content = file_get_contents($file);
    return json_decode($content, true) ?: [];
}

/**
 * Сохранение JSON-файла
 */
function save_json($filename, $data) {
    $file = DATA_DIR . $filename;
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    file_put_contents($file, $json, LOCK_EX);
}

/**
 * Получение всех данных
 */
function get_users() {
    return load_json('users.json');
}

function get_clients() {
    return load_json('clients.json');
}

function get_appointments() {
    return load_json('appointments.json');
}

function get_services() {
    return load_json('services.json');
}

function get_payments() {
    return load_json('payments.json');
}

function get_expenses() {
    return load_json('expenses.json');
}

function get_blocks() {
    return load_json('blocks.json');
}

function get_ai_log() {
    return load_json('ai_log.json');
}

/**
 * Поиск по ID
 */
function find_by_id($array, $id) {
    foreach ($array as $item) {
        if ($item['id'] == $id) {
            return $item;
        }
    }
    return null;
}

function find_user_by_id($id) {
    return find_by_id(get_users(), $id);
}

function find_client_by_id($id) {
    return find_by_id(get_clients(), $id);
}

function find_service_by_id($id) {
    return find_by_id(get_services(), $id);
}

/**
 * Авторизация
 */
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

function current_user() {
    if (!is_logged_in()) {
        return null;
    }
    return find_user_by_id($_SESSION['user_id']);
}

function login($user_id) {
    $_SESSION['user_id'] = $user_id;
}

function logout() {
    unset($_SESSION['user_id']);
    session_destroy();
}

function require_login() {
    if (!is_logged_in()) {
        header('Location: index.php');
        exit;
    }
}

function require_role($role) {
    require_login();
    $user = current_user();
    if ($user['role'] !== $role && $user['role'] !== 'admin') {
        // admin имеет доступ ко всему
        header('Location: index.php?page=dashboard');
        exit;
    }
}

/**
 * Flash-сообщения
 */
function set_flash($type, $message) {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function get_flash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Хелперы форматирования
 */
function format_currency($amount) {
    return number_format($amount, 0, ',', ' ') . ' ₽';
}

function format_date($date) {
    $months = [
        1 => 'января', 2 => 'февраля', 3 => 'марта', 4 => 'апреля',
        5 => 'мая', 6 => 'июня', 7 => 'июля', 8 => 'августа',
        9 => 'сентября', 10 => 'октября', 11 => 'ноября', 12 => 'декабря'
    ];
    $timestamp = strtotime($date);
    return date('j', $timestamp) . ' ' . $months[date('n', $timestamp)] . ' ' . date('Y', $timestamp);
}

function format_time($time) {
    return $time;
}

/**
 * Layout
 */
function render_header($title = '') {
    $user = current_user();
    $company_name = 'ООО «Салон красоты»';
    $page_title = $title ? $title . ' — ' . $company_name : $company_name;
    ?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="app">
    <header class="header">
        <div class="header__inner container">
            <div class="logo">
                <span class="logo__icon">💇</span>
                <span class="logo__text">AI-CRM</span>
                <span class="badge badge--company"><?php echo htmlspecialchars($company_name); ?></span>
            </div>
            <nav class="nav">
                <?php if (is_logged_in()): ?>
                    <?php if ($user['role'] == 'admin' || $user['role'] == 'manager'): ?>
                        <a href="index.php?page=dashboard" class="nav__link <?php echo ($_GET['page'] ?? '') == 'dashboard' ? 'active' : ''; ?>">Дашборд</a>
                    <?php endif; ?>
                    <?php if ($user['role'] == 'admin' || $user['role'] == 'manager' || $user['role'] == 'operator'): ?>
                        <a href="index.php?page=clients" class="nav__link <?php echo ($_GET['page'] ?? '') == 'clients' || ($_GET['page'] ?? '') == 'client_card' ? 'active' : ''; ?>">Клиенты</a>
                        <a href="index.php?page=calendar" class="nav__link <?php echo ($_GET['page'] ?? '') == 'calendar' ? 'active' : ''; ?>">Записи</a>
                    <?php endif; ?>
                    <a href="index.php?page=ai" class="nav__link <?php echo ($_GET['page'] ?? '') == 'ai' ? 'active' : ''; ?>">AI-панель</a>
                    <span class="nav__user">
                        <?php echo htmlspecialchars($user['name']); ?> (<?php echo htmlspecialchars($user['role']); ?>)
                    </span>
                    <form action="actions.php" method="post" class="nav__logout">
                        <input type="hidden" name="action" value="logout">
                        <button type="submit" class="btn btn--link">Выйти</button>
                    </form>
                <?php endif; ?>
            </nav>
        </div>
    </header>
    <main class="main container">
        <?php $flash = get_flash(); if ($flash): ?>
            <div class="alert alert--<?php echo htmlspecialchars($flash['type']); ?>">
                <?php echo htmlspecialchars($flash['message']); ?>
            </div>
        <?php endif; ?>
    <?php
}

function render_footer() {
    ?>
    </main>
    <footer class="footer">
        <div class="container">
            <p>Демо-прототип AI-CRM для бизнеса в сфере услуг. PHP 7.3, JSON, без внешних библиотек.</p>
        </div>
    </footer>
</div>
</body>
</html>
    <?php
}

/**
 * Проверка прав доступа к странице
 */
function check_page_access($page) {
    $user = current_user();
    if (!$user) {
        return false;
    }
    switch ($page) {
        case 'dashboard':
            return in_array($user['role'], ['admin', 'manager']);
        case 'clients':
        case 'client_card':
            return in_array($user['role'], ['admin', 'manager', 'operator']);
        case 'calendar':
            return in_array($user['role'], ['admin', 'manager', 'operator', 'client']);
        case 'ai':
            return in_array($user['role'], ['admin', 'manager', 'operator', 'client']);
        default:
            return false;
    }
}