<?php
// index.php — вход и роутинг
require_once __DIR__ . '/lib.php';

// Если не залогинен — показываем экран быстрого входа
if (!is_logged_in()) {
    ?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Вход — AI-CRM</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body class="login-page">
    <div class="login-container">
        <div class="login-card">
            <div class="logo logo--large">
                <span class="logo__icon">💇</span>
                <span class="logo__text">AI-CRM</span>
            </div>
            <h1>Демо-прототип CRM для услуг</h1>
            <p class="login-subtitle">Выберите роль для быстрого входа:</p>
            <div class="login-buttons">
                <?php $users = get_users(); foreach ($users as $u): ?>
                    <form action="actions.php" method="post">
                        <input type="hidden" name="action" value="login">
                        <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                        <button type="submit" class="btn btn--role btn--<?php echo $u['role']; ?>">
                            <span class="btn__role-icon">
                                <?php
                                switch ($u['role']) {
                                    case 'admin': echo '👑'; break;
                                    case 'manager': echo '📊'; break;
                                    case 'operator': echo '📅'; break;
                                    case 'client': echo '🧑'; break;
                                }
                                ?>
                            </span>
                            <span class="btn__role-text">
                                <strong><?php echo htmlspecialchars($u['name']); ?></strong>
                                <small><?php echo htmlspecialchars($u['role']); ?></small>
                            </span>
                        </button>
                    </form>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</body>
</html>
    <?php
    exit;
}

// Пользователь залогинен — маршрутизация
$page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';

// Проверка доступа
if (!check_page_access($page)) {
    $page = '404';
}

$page_file = __DIR__ . '/pages/' . $page . '.php';
if (!file_exists($page_file)) {
    $page = '404';
    $page_file = __DIR__ . '/pages/404.php';
}

// Рендер страницы
$page_title = '';
switch ($page) {
    case 'dashboard': $page_title = 'Дашборд'; break;
    case 'clients': $page_title = 'Клиенты'; break;
    case 'client_card': $page_title = 'Карточка клиента'; break;
    case 'calendar': $page_title = 'Записи / Календарь'; break;
    case 'ai': $page_title = 'AI-панель'; break;
    case '404': $page_title = 'Страница не найдена'; break;
}

render_header($page_title);
include $page_file;
render_footer();