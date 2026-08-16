<?php
// ========== НАСТРОЙКА ДОЛГОЙ СЕССИИ (90 дней) ==========
$lifetime = 60 * 60 * 24 * 90;
session_set_cookie_params([
    'lifetime' => $lifetime,
    'path' => '/',
    'secure' => false,
    'httponly' => true,
    'samesite' => 'Lax'
]);
ini_set('session.gc_maxlifetime', $lifetime);
session_start();

if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    setcookie(session_name(), session_id(), time() + $lifetime, '/');
}

// Учётные данные берутся из переменных окружения (демо-значения — demo/demo).
$valid_username = getenv('READ_PREP_USER') ?: 'demo';
$valid_password = getenv('READ_PREP_PASS') ?: 'demo';

$isLoggedIn = false;
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    $isLoggedIn = true;
}

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login']) && isset($_POST['password'])) {
    $login = $_POST['login'];
    $password = $_POST['password'];
    if ($login === $valid_username && hash_equals($valid_password, $password)) {
        $_SESSION['admin_logged_in'] = true;
        session_regenerate_id(true);
        header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
        exit;
    } else {
        $loginError = 'Неверный логин или пароль';
    }
}

if (!$isLoggedIn) {
    ?>
    <!DOCTYPE html>
    <html lang="ru">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
        <title>Вход в админку</title>
        <style>
            *{box-sizing:border-box}body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;background:#f5f5f7;margin:0;padding:20px;display:flex;justify-content:center;align-items:center;min-height:100vh}
            .login-container{max-width:400px;width:100%;background:#fff;border-radius:28px;padding:32px 24px;box-shadow:0 8px 28px rgba(0,0,0,0.05);text-align:center}
            h2{margin-bottom:24px;font-weight:600;color:#1c1c1e}
            input{width:100%;padding:14px;margin-bottom:16px;border:1px solid #c6c6c8;border-radius:14px;font-size:16px}
            input:focus{outline:none;border-color:#ff9f00;box-shadow:0 0 0 3px rgba(255,159,0,0.1)}
            button{width:100%;background:#fff;color:#ff9f00;border:2px solid #ff9f00;padding:12px;font-size:16px;font-weight:600;border-radius:40px;cursor:pointer}
            button:hover{background:#fff6e5}
            .error{color:#ff3b30;margin-bottom:16px;font-size:14px}
        </style>
    </head>
    <body>
        <div class="login-container">
            <h2>Вход в систему</h2>
            <?php if (isset($loginError)) echo '<div class="error">' . htmlspecialchars($loginError) . '</div>'; ?>
            <form method="post">
                <input type="text" name="login" placeholder="Логин" autocomplete="username" required>
                <input type="password" name="password" placeholder="Пароль" autocomplete="current-password" required>
                <button type="submit">Войти</button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// ========== ОСНОВНАЯ ЛОГИКА ==========
$tabsDir = __DIR__ . '/tabs';
if (!is_dir($tabsDir)) {
    mkdir($tabsDir, 0755, true);
}

$pageConfigFile = __DIR__ . '/.page_config';
if (!file_exists($pageConfigFile)) {
    file_put_contents($pageConfigFile, json_encode([]));
}

function getSavedPage($tabId) {
    global $pageConfigFile;
    $config = json_decode(file_get_contents($pageConfigFile), true);
    return isset($config[$tabId]) ? (int)$config[$tabId] : 1;
}

function setSavedPage($tabId, $page) {
    global $pageConfigFile;
    $config = json_decode(file_get_contents($pageConfigFile), true);
    if (!is_array($config)) $config = [];
    $config[$tabId] = $page;
    file_put_contents($pageConfigFile, json_encode($config));
}

function getTabTitle($file) {
    $data = json_decode(file_get_contents($file), true);
    $messages = $data['messages'] ?? [];
    if (empty($messages)) {
        return 'Новая вкладка';
    }
    $firstMsg = $messages[0]['original'] ?? '';
    $title = mb_substr($firstMsg, 0, 25);
    if (mb_strlen($firstMsg) > 25) $title .= '…';
    return $title ?: 'Новая вкладка';
}

function getTabs() {
    global $tabsDir;
    $files = glob($tabsDir . '/*.json');
    $tabs = [];
    foreach ($files as $file) {
        $tabId = basename($file, '.json');
        if ($tabId === '_trash') continue;
        $title = getTabTitle($file);
        $tabs[] = ['id' => $tabId, 'title' => $title, 'file' => $file];
    }
    usort($tabs, function($a, $b) { return strcmp($a['id'], $b['id']); });
    return $tabs;
}

function getTrashTab() {
    global $tabsDir;
    $trashFile = $tabsDir . '/_trash.json';
    if (!file_exists($trashFile)) {
        file_put_contents($trashFile, json_encode(['messages' => []]));
    }
    return $trashFile;
}

function moveToTrash($message) {
    $trashFile = getTrashTab();
    $trashData = json_decode(file_get_contents($trashFile), true);
    if (!isset($trashData['messages'])) $trashData['messages'] = [];
    $trashData['messages'][] = [
        'original' => $message['original'],
        'cleaned' => $message['cleaned']
    ];
    file_put_contents($trashFile, json_encode($trashData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function deleteTabToTrash($tabId) {
    $file = __DIR__ . '/tabs/' . $tabId . '.json';
    if (!file_exists($file)) return;
    $data = json_decode(file_get_contents($file), true);
    $messages = $data['messages'] ?? [];
    if (!empty($messages)) {
        $trashFile = getTrashTab();
        $trashData = json_decode(file_get_contents($trashFile), true);
        if (!isset($trashData['messages'])) $trashData['messages'] = [];
        foreach ($messages as $msg) {
            $trashData['messages'][] = [
                'original' => $msg['original'],
                'cleaned' => $msg['cleaned']
            ];
        }
        file_put_contents($trashFile, json_encode($trashData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
    unlink($file);
    $config = json_decode(file_get_contents(__DIR__ . '/.page_config'), true);
    if (isset($config[$tabId])) unset($config[$tabId]);
    file_put_contents(__DIR__ . '/.page_config', json_encode($config));
}

function clearTabToTrash($file, $messages) {
    if (empty($messages)) return;
    $trashFile = getTrashTab();
    $trashData = json_decode(file_get_contents($trashFile), true);
    if (!isset($trashData['messages'])) $trashData['messages'] = [];
    foreach ($messages as $msg) {
        $trashData['messages'][] = [
            'original' => $msg['original'],
            'cleaned' => $msg['cleaned']
        ];
    }
    file_put_contents($trashFile, json_encode($trashData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    file_put_contents($file, json_encode(['messages' => []]));
}

if (isset($_GET['new_tab'])) {
    $newId = uniqid();
    $newFile = $tabsDir . '/' . $newId . '.json';
    file_put_contents($newFile, json_encode(['messages' => []]));
    setSavedPage($newId, 1);
    header('Location: ?tab=' . $newId . '&page=1');
    exit;
}

$isTrash = (isset($_GET['tab']) && $_GET['tab'] === '_trash');

if ($isTrash) {
    $currentTab = '_trash';
    $currentFile = getTrashTab();
    $tabData = json_decode(file_get_contents($currentFile), true);
    $messages = $tabData['messages'] ?? [];
} else {
    $currentTab = isset($_GET['tab']) && preg_match('/^[a-zA-Z0-9]+$/', $_GET['tab']) ? $_GET['tab'] : null;
    $tabs = getTabs();
    if (empty($tabs)) {
        $newId = uniqid();
        file_put_contents($tabsDir . '/' . $newId . '.json', json_encode(['messages' => []]));
        setSavedPage($newId, 1);
        $tabs = getTabs();
        $currentTab = $tabs[0]['id'];
        header('Location: ?tab=' . $currentTab . '&page=1');
        exit;
    }
    if (!$currentTab || !file_exists($tabsDir . '/' . $currentTab . '.json')) {
        $currentTab = $tabs[0]['id'];
        header('Location: ?tab=' . $currentTab);
        exit;
    }
    $currentFile = $tabsDir . '/' . $currentTab . '.json';
    $tabData = json_decode(file_get_contents($currentFile), true);
    $messages = $tabData['messages'] ?? [];
}

if (!$isTrash && !isset($_GET['page'])) {
    $savedPage = getSavedPage($currentTab);
    $redirectUrl = '?tab=' . urlencode($currentTab) . '&page=' . $savedPage .
                   '&chunk_size=' . ($_GET['chunk_size'] ?? '') .
                   '&max_block=' . ($_GET['max_block'] ?? '') .
                   '&settings_open=' . ($_GET['settings_open'] ?? '');
    header('Location: ' . $redirectUrl);
    exit;
}

function cleanText($text, $mode = 'normal') {
    $lines = preg_split('/\r\n|\r|\n/', $text);
    $processed = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') continue;
        $line = preg_replace('/\s+/', ' ', $line);
        if ($mode === 'book') {
            $line = preg_replace('/[-\x{2013}\x{2014}]$/u', '', $line);
            $processed[] = $line;
        } else {
            $lastChar = mb_substr($line, -1);
            if (!in_array($lastChar, ['.', '!', '?', ';', ':', ','])) {
                $line .= '.';
            }
            $processed[] = $line;
        }
    }
    return implode(' ', $processed);
}

function saveTabData($file, $messages) {
    file_put_contents($file, json_encode(['messages' => $messages], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function paginateMessages($messages, $chunkSize, $maxBlock) {
    if (empty($messages)) return [];
    $pages = [];
    $currentPage = [];
    $currentLength = 0;
    foreach ($messages as $idx => $msg) {
        $cleaned = $msg['cleaned'];
        $len = mb_strlen($cleaned, 'UTF-8');
        if ($len > $maxBlock) {
            $parts = [];
            $offset = 0;
            while ($offset < $len) {
                $partLen = min($chunkSize, $len - $offset);
                $partCleaned = mb_substr($cleaned, $offset, $partLen, 'UTF-8');
                $parts[] = [
                    'original' => $msg['original'],
                    'cleaned'  => $partCleaned,
                    'fragment' => true,
                    'original_index' => $idx,
                ];
                $offset += $partLen;
            }
            foreach ($parts as $part) {
                $partLen = mb_strlen($part['cleaned'], 'UTF-8');
                if ($currentLength + $partLen > $chunkSize && !empty($currentPage)) {
                    $pages[] = $currentPage;
                    $currentPage = [];
                    $currentLength = 0;
                }
                $currentPage[] = $part;
                $currentLength += $partLen;
            }
            continue;
        }
        if ($currentLength + $len > $chunkSize && !empty($currentPage)) {
            $pages[] = $currentPage;
            $currentPage = [];
            $currentLength = 0;
        }
        $msg['original_index'] = $idx;
        $currentPage[] = $msg;
        $currentLength += $len;
    }
    if (!empty($currentPage)) $pages[] = $currentPage;
    return $pages;
}

$settingsFile = __DIR__ . '/.pagination_config';
$defaultChunkSize = 10000;
$defaultMaxBlock = 20000;
$defaultTextMode = 'normal';
if (file_exists($settingsFile)) {
    $settings = json_decode(file_get_contents($settingsFile), true);
    $chunkSize = isset($settings['chunk_size']) ? (int)$settings['chunk_size'] : $defaultChunkSize;
    $maxBlock = isset($settings['max_block']) ? (int)$settings['max_block'] : $defaultMaxBlock;
    $textMode = isset($settings['text_mode']) ? $settings['text_mode'] : $defaultTextMode;
} else {
    $chunkSize = $defaultChunkSize;
    $maxBlock = $defaultMaxBlock;
    $textMode = $defaultTextMode;
}
$chunkSize = max(1000, min(200000, $chunkSize));
$maxBlock = max(1000, min(500000, $maxBlock));

$page = isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 ? (int)$_GET['page'] : 1;
$settingsOpen = isset($_GET['settings_open']) && $_GET['settings_open'] == 1;

if (!$isTrash && $page != getSavedPage($currentTab)) {
    setSavedPage($currentTab, $page);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Удаление пустой вкладки (крестик)
    if (isset($_POST['delete_tab_submit']) && ctype_alnum($_POST['delete_tab_submit'])) {
        $delId = $_POST['delete_tab_submit'];
        deleteTabToTrash($delId);
        $tabs = getTabs();
        if (empty($tabs)) {
            header('Location: ?new_tab=1');
        } else {
            $nextTab = $tabs[0]['id'];
            $nextPage = getSavedPage($nextTab);
            header('Location: ?tab=' . $nextTab . '&page=' . $nextPage);
        }
        exit;
    }
    // Очистка всей вкладки (корзина)
    if (isset($_POST['clear_active']) && !$isTrash) {
        clearTabToTrash($currentFile, $messages);
        setSavedPage($currentTab, 1);
        $redirectUrl = '?tab=' . urlencode($currentTab) . '&page=1&chunk_size=' . $chunkSize . '&max_block=' . $maxBlock;
        if ($settingsOpen) $redirectUrl .= '&settings_open=1';
        header('Location: ' . $redirectUrl);
        exit;
    }
    // Удаление отдельного сообщения (перемещение в корзину)
    if (isset($_POST['delete_message']) && isset($_POST['original_index']) && !$isTrash) {
        $indexToDelete = (int)$_POST['original_index'];
        if (isset($messages[$indexToDelete])) {
            moveToTrash($messages[$indexToDelete]);
            array_splice($messages, $indexToDelete, 1);
            saveTabData($currentFile, $messages);
        }
        $redirectUrl = '?tab=' . urlencode($currentTab) . '&page=' . $page . '&chunk_size=' . $chunkSize . '&max_block=' . $maxBlock;
        if ($settingsOpen) $redirectUrl .= '&settings_open=1';
        header('Location: ' . $redirectUrl);
        exit;
    }
    // Окончательное удаление из корзины
    if (isset($_POST['delete_message_permanent']) && isset($_POST['original_index']) && $isTrash) {
        $indexToDelete = (int)$_POST['original_index'];
        $trashData = json_decode(file_get_contents($currentFile), true);
        if (isset($trashData['messages'][$indexToDelete])) {
            array_splice($trashData['messages'], $indexToDelete, 1);
            file_put_contents($currentFile, json_encode($trashData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
        $redirectUrl = '?tab=_trash&page=' . $page . '&chunk_size=' . $chunkSize . '&max_block=' . $maxBlock;
        if ($settingsOpen) $redirectUrl .= '&settings_open=1';
        header('Location: ' . $redirectUrl);
        exit;
    }
    // Очистка всей корзины
    if (isset($_POST['clear_trash']) && $isTrash) {
        file_put_contents($currentFile, json_encode(['messages' => []]));
        header('Location: ?tab=_trash&page=1');
        exit;
    }
    // Автосохранение с поддержкой base64-декодирования
    if (isset($_POST['auto_save']) && isset($_POST['text']) && trim($_POST['text']) !== '' && !$isTrash) {
        $rawText = $_POST['text'];
        if (strpos($rawText, 'b64:') === 0) {
            $decoded = base64_decode(substr($rawText, 4));
            if ($decoded !== false) {
                $rawText = $decoded;
            }
        }
        $cleaned = cleanText($rawText, $textMode);
        $messages[] = ['original' => $rawText, 'cleaned' => $cleaned];
        saveTabData($currentFile, $messages);
        $redirectUrl = $_SERVER['HTTP_REFERER'] ?? ('?tab=' . urlencode($currentTab) . '&page=' . $page);
        $separator = strpos($redirectUrl, '?') === false ? '?' : '&';
        $redirectUrl .= $separator . 'toast=save';
        header('Location: ' . $redirectUrl);
        exit;
    }
    // Обновление настроек
    if (isset($_POST['update_settings'])) {
        $newChunk = isset($_POST['chunk_size_input']) ? (int)$_POST['chunk_size_input'] : $chunkSize;
        $newMax = isset($_POST['max_block_input']) ? (int)$_POST['max_block_input'] : $maxBlock;
        $newTextMode = isset($_POST['text_mode']) ? $_POST['text_mode'] : $textMode;
        $newChunk = max(1000, min(200000, $newChunk));
        $newMax = max(1000, min(500000, $newMax));
        file_put_contents($settingsFile, json_encode(['chunk_size' => $newChunk, 'max_block' => $newMax, 'text_mode' => $newTextMode]));
        if (!$isTrash) {
            setSavedPage($currentTab, 1);
            header('Location: ?tab=' . urlencode($currentTab) . '&page=1&chunk_size=' . $newChunk . '&max_block=' . $newMax . '&settings_open=' . ($settingsOpen ? 1 : 0));
        } else {
            header('Location: ?tab=_trash&page=1&chunk_size=' . $newChunk . '&max_block=' . $newMax . '&settings_open=' . ($settingsOpen ? 1 : 0));
        }
        exit;
    }
}

$pages = paginateMessages($messages, $chunkSize, $maxBlock);
$totalPages = count($pages);
if ($totalPages == 0) $totalPages = 1;
if ($page > $totalPages) $page = $totalPages;
$currentPageMessages = ($totalPages > 0 && $page <= $totalPages) ? $pages[$page-1] : [];

$totalChars = 0;
foreach ($messages as $msg) {
    $totalChars += mb_strlen($msg['cleaned'] ?? '');
}
$totalMinutes = round($totalChars / 1000);

$pageChars = 0;
foreach ($currentPageMessages as $msg) {
    $pageChars += mb_strlen($msg['cleaned'] ?? '');
}
$pageMinutes = round($pageChars / 1000);

// Собираем исходные тексты с переносами (приоритет original)
$allOriginal = [];
foreach ($messages as $msg) {
    $text = $msg['original'] ?? $msg['cleaned'] ?? '';
    if (!empty($text)) {
        $allOriginal[] = $text;
    }
}
// Склеиваем с двумя переносами строки между сообщениями
$fullOriginalText = implode("\n\n", $allOriginal);

$toastType = isset($_GET['toast']) ? $_GET['toast'] : '';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <meta name="robots" content="noindex, nofollow">
    <title>Текстовый накопитель — вкладки</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        *{box-sizing:border-box}
        body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;background:#f5f5f7;margin:0;padding:20px;color:#1c1c1e}
        .container{max-width:1000px;margin:0 auto;background:#fff;border-radius:28px;box-shadow:0 2px 12px rgba(0,0,0,0.05);padding:24px 20px 32px}
        .tabs-bar{background:#DCDCDC;border-radius:16px;padding:6px;display:flex;flex-wrap:wrap;gap:6px;margin-bottom:24px;align-items:center}
        .tab{background:#DCDCDC;border-radius:12px;padding:8px 14px;font-size:14px;font-weight:500;cursor:pointer;text-decoration:none;color:#000;transition:0.2s;display:inline-flex;align-items:center;gap:8px;white-space:nowrap}
        .tab.active{background:#fff;color:#000}
        .tab .tab-title{max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
        .tab .tab-meta{font-size:11px;color:#6c6c6c;margin-left:4px;font-weight:400;white-space:nowrap}
        .tab .delete-tab{background:transparent;border:none;color:#8e8e93;cursor:pointer;font-size:12px;padding:2px 4px;border-radius:20px;transition:0.2s}
        .tab .delete-tab:hover{background:#c0c0c0;color:#ff9f00}
        .new-tab-btn{background:#DCDCDC;border:none;border-radius:50%;width:36px;height:36px;display:inline-flex;align-items:center;justify-content:center;color:#000;text-decoration:none;font-size:18px;transition:0.2s;cursor:pointer;flex-shrink:0}
        .new-tab-btn:hover{background:#c0c0c0}
        textarea{width:100%;padding:16px;font-size:16px;border:1px solid #c6c6c8;border-radius:14px;resize:vertical;margin-bottom:12px}
        textarea:focus{outline:none;border-color:#ff9f00;box-shadow:0 0 0 3px rgba(255,159,0,0.1)}
        .message-block{background:#f9f9fb;border-radius:20px;padding:16px;margin-bottom:20px;border:1px solid #e1e1e6;position:relative;cursor:pointer}
        .message-block:active{background:#e9e9ef}
        .delete-message{position:absolute;top:12px;right:12px;background:transparent;border:none;color:#8e8e93;cursor:pointer;font-size:16px;padding:4px 8px;border-radius:20px;z-index:2;transition:0.2s}
        .delete-message:hover{background:#e0e0e0;color:#ff9f00}
        .message-content{padding-right:40px;font-size:16px;line-height:1.5;word-wrap:break-word}
        .empty-message{color:#8e8e93;text-align:center;padding:32px}
        .empty-message i{margin-right:8px}
        .page-stats{text-align:center;font-size:13px;color:#6c6c6c;margin:12px 0 6px;padding:6px;background:#f5f5f7;border-radius:20px}
        .pagination{display:flex;flex-wrap:wrap;justify-content:center;gap:8px;margin:20px 0}
        .pagination a,.pagination span{display:inline-flex;align-items:center;justify-content:center;min-width:44px;height:44px;padding:0 12px;border-radius:30px;background:#f9f9fb;color:#ff9f00;text-decoration:none;font-weight:500;font-size:15px;border:1px solid transparent}
        .pagination a:hover{background:#fff6e5;border:2px solid #ff9f00}
        .pagination .current{background:#fff;border:2px solid #ff9f00;color:#ff9f00}
        .pagination .disabled{color:#c6c6c8;pointer-events:none}
        .copy-all-btn{display:block;width:100%;padding:12px;margin:20px 0 10px;background:#fff;color:#ff9f00;border:2px solid #ff9f00;border-radius:40px;cursor:pointer;font-weight:600;text-align:center;font-size:15px}
        .copy-all-btn:hover{background:#fff6e5}
        .logout-bottom{margin-top:40px;text-align:center;border-top:1px solid #e9e9ef;padding-top:24px}
        .logout-bottom a{background:#fff;color:#ff9f00;border:2px solid #ff9f00;padding:10px 24px;border-radius:40px;text-decoration:none;font-weight:600;display:inline-flex;align-items:center;gap:8px}
        .logout-bottom a:hover{background:#fff6e5}
        .floating-buttons{position:fixed;bottom:20px;right:20px;display:flex;flex-direction:column;gap:12px;z-index:1000}
        .floating-btn{background:#ff9f00;color:#fff;width:50px;height:50px;border-radius:50%;display:flex;align-items:center;justify-content:center;cursor:pointer;transition:0.2s;box-shadow:0 2px 10px rgba(0,0,0,0.2);border:none;font-size:20px}
        .floating-btn:hover{background:#e68a00}
        .scroll-btn{opacity:0;visibility:hidden;transition:opacity 0.3s,visibility 0.3s}
        .scroll-btn.show{opacity:1;visibility:visible}
        .modal{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:2000;align-items:center;justify-content:center}
        .modal-content{background:#fff;border-radius:28px;max-width:500px;width:90%;padding:24px;box-shadow:0 8px 28px rgba(0,0,0,0.2)}
        .modal-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px}
        .modal-header h3{margin:0;font-weight:600}
        .close-modal{background:none;border:none;font-size:24px;cursor:pointer;color:#8e8e93}
        .settings-row-modal{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:20px}
        .settings-row-modal label{display:flex;align-items:center;gap:8px;font-size:14px;font-weight:500}
        .chunk-controls-modal{display:flex;align-items:center;gap:8px;background:#fff;border-radius:60px;padding:4px;box-shadow:0 1px 2px rgba(0,0,0,0.05)}
        .chunk-controls-modal button{width:44px;background:#e9e9ef;border:none;border-radius:50px;cursor:pointer;font-size:18px;padding:6px 0}
        .chunk-controls-modal input{width:120px;padding:8px 5px;border-radius:40px;border:1px solid #c6c6c8;text-align:center}
        .save-settings-modal{background:#fff;color:#ff9f00;border:2px solid #ff9f00;padding:8px 20px;border-radius:40px;cursor:pointer;font-weight:600;width:100%}
        .action-buttons-modal{display:flex;gap:10px;margin-top:10px}
        .action-btn{background:#fff;color:#ff9f00;border:2px solid #ff9f00;padding:8px 20px;border-radius:40px;cursor:pointer;font-weight:600;width:100%;text-align:center;display:inline-block;text-decoration:none}
        select{padding:8px 12px;border-radius:30px;border:1px solid #c6c6c8}
        .toast{position:fixed;bottom:30px;left:50%;transform:translateX(-50%);background:#1c1c1e;color:#fff;padding:10px 24px;border-radius:40px;font-size:14px;z-index:1001;opacity:0;transition:opacity 0.2s;pointer-events:none;display:flex;gap:8px;white-space:nowrap;box-shadow:0 4px 12px rgba(0,0,0,0.15)}
        .toast.show{opacity:1}
        @media (max-width:600px){
            .container{padding:20px 16px}
            .tabs-bar{flex-direction:column;align-items:stretch}
            .tab{display:flex;justify-content:space-between;width:100%;white-space:normal}
            .tab .tab-title{max-width:none;white-space:normal;word-break:break-word}
            .tab .tab-meta{font-size:10px;white-space:nowrap}
            .tab .delete-tab{font-size:16px;padding:4px 8px}
            .new-tab-btn{width:44px;height:44px;font-size:20px;align-self:center}
            .floating-buttons{bottom:15px;right:15px}
            .floating-btn{width:44px;height:44px;font-size:18px}
            .toast{white-space:normal;max-width:80vw;text-align:center}
        }
    </style>
</head>
<body>
<div class="container">
    <div class="tabs-bar">
        <?php if (!$isTrash): ?>
            <?php foreach ($tabs as $tab): 
                $tabFile = $tab['file'];
                $tabDataCheck = json_decode(file_get_contents($tabFile), true);
                $tabMessages = $tabDataCheck['messages'] ?? [];
                $tabHasMessages = !empty($tabMessages);
                $tabTotalChars = 0;
                if ($tabHasMessages) {
                    foreach ($tabMessages as $msg) {
                        $tabTotalChars += mb_strlen($msg['cleaned'] ?? '');
                    }
                }
                $tabMinutes = round($tabTotalChars / 1000);
            ?>
                <div class="tab <?php echo $tab['id'] === $currentTab ? 'active' : ''; ?>">
                    <a href="?tab=<?php echo urlencode($tab['id']); ?>&page=<?php echo getSavedPage($tab['id']); ?>&chunk_size=<?php echo $chunkSize; ?>&max_block=<?php echo $maxBlock; ?>&settings_open=<?php echo $settingsOpen ? 1 : 0; ?>" style="text-decoration:none; color:inherit; display:inline-flex; align-items:center; gap:6px; flex:1;">
                        <span class="tab-title"><?php echo htmlspecialchars($tab['title']); ?></span>
                        <?php if ($tabHasMessages): ?>
                            <span class="tab-meta">~<?php echo $tabMinutes; ?> мин</span>
                        <?php endif; ?>
                    </a>
                    <?php if ($tabHasMessages): ?>
                        <form method="post" style="display:inline;" action="?tab=<?php echo urlencode($tab['id']); ?>&page=1&chunk_size=<?php echo $chunkSize; ?>&max_block=<?php echo $maxBlock; ?>&settings_open=<?php echo $settingsOpen ? 1 : 0; ?>">
                            <input type="hidden" name="clear_active" value="1">
                            <button type="submit" class="delete-tab" aria-label="Очистить вкладку"><i class="fas fa-trash-alt"></i></button>
                        </form>
                    <?php else: ?>
                        <form method="post" style="display:inline;" action="?tab=<?php echo urlencode($tab['id']); ?>&page=1&chunk_size=<?php echo $chunkSize; ?>&max_block=<?php echo $maxBlock; ?>&settings_open=<?php echo $settingsOpen ? 1 : 0; ?>">
                            <input type="hidden" name="delete_tab_submit" value="<?php echo htmlspecialchars($tab['id']); ?>">
                            <button type="submit" class="delete-tab" aria-label="Удалить вкладку"><i class="fas fa-times"></i></button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            <a href="?new_tab=1" class="new-tab-btn"><i class="fas fa-plus"></i></a>
        <?php else: ?>
            <div class="tab active" style="background:#fff;">
                <span class="tab-title">Корзина</span>
                <form method="post" action="?tab=_trash&page=1" style="display:inline;" onsubmit="return confirm('Очистить корзину? Все сообщения будут удалены безвозвратно.');">
                    <input type="hidden" name="clear_trash" value="1">
                    <button type="submit" class="delete-tab" aria-label="Очистить корзину" style="background:transparent; border:none; color:#8e8e93; cursor:pointer; font-size:14px; padding:2px 6px; border-radius:20px;"><i class="fas fa-trash-alt"></i></button>
                </form>
            </div>
            <a href="?tab=<?php echo urlencode($tabs[0]['id']); ?>&page=1" class="new-tab-btn" style="background:#DCDCDC; color:#000; text-decoration:none; display:inline-flex; align-items:center; gap:6px;"><i class="fas fa-folder-open"></i> Вернуться</a>
        <?php endif; ?>
    </div>

    <?php if (!$isTrash): ?>
    <form id="auto_save_form" method="post" action="?tab=<?php echo urlencode($currentTab); ?>&page=<?php echo $page; ?>&chunk_size=<?php echo $chunkSize; ?>&max_block=<?php echo $maxBlock; ?>&settings_open=<?php echo $settingsOpen ? 1 : 0; ?>">
        <textarea name="text" id="main_text" rows="4" placeholder="Вставьте текст для чтения — сохранится автоматически"></textarea>
        <input type="hidden" name="auto_save" value="1">
    </form>
    <?php endif; ?>

    <?php if ($totalPages > 1): ?>
    <div class="pagination">
        <?php if ($page > 1): ?>
            <a href="<?php echo $isTrash ? '?tab=_trash' : '?tab=' . urlencode($currentTab); ?>&page=1&chunk_size=<?php echo $chunkSize; ?>&max_block=<?php echo $maxBlock; ?>&settings_open=<?php echo $settingsOpen ? 1 : 0; ?>"><i class="fas fa-angles-left"></i></a>
            <a href="<?php echo $isTrash ? '?tab=_trash' : '?tab=' . urlencode($currentTab); ?>&page=<?php echo $page-1; ?>&chunk_size=<?php echo $chunkSize; ?>&max_block=<?php echo $maxBlock; ?>&settings_open=<?php echo $settingsOpen ? 1 : 0; ?>"><i class="fas fa-chevron-left"></i></a>
        <?php else: ?>
            <span class="disabled"><i class="fas fa-angles-left"></i></span>
            <span class="disabled"><i class="fas fa-chevron-left"></i></span>
        <?php endif; ?>
        <?php
        $startPage = max(1, $page - 2);
        $endPage = min($totalPages, $page + 2);
        if ($startPage > 1) echo '<span>...</span>';
        for ($i = $startPage; $i <= $endPage; $i++):
        ?>
            <?php if ($i == $page): ?>
                <span class="current"><?php echo $i; ?></span>
            <?php else: ?>
                <a href="<?php echo $isTrash ? '?tab=_trash' : '?tab=' . urlencode($currentTab); ?>&page=<?php echo $i; ?>&chunk_size=<?php echo $chunkSize; ?>&max_block=<?php echo $maxBlock; ?>&settings_open=<?php echo $settingsOpen ? 1 : 0; ?>"><?php echo $i; ?></a>
            <?php endif; ?>
        <?php endfor;
        if ($endPage < $totalPages) echo '<span>...</span>';
        ?>
        <?php if ($page < $totalPages): ?>
            <a href="<?php echo $isTrash ? '?tab=_trash' : '?tab=' . urlencode($currentTab); ?>&page=<?php echo $page+1; ?>&chunk_size=<?php echo $chunkSize; ?>&max_block=<?php echo $maxBlock; ?>&settings_open=<?php echo $settingsOpen ? 1 : 0; ?>"><i class="fas fa-chevron-right"></i></a>
            <a href="<?php echo $isTrash ? '?tab=_trash' : '?tab=' . urlencode($currentTab); ?>&page=<?php echo $totalPages; ?>&chunk_size=<?php echo $chunkSize; ?>&max_block=<?php echo $maxBlock; ?>&settings_open=<?php echo $settingsOpen ? 1 : 0; ?>"><i class="fas fa-angles-right"></i></a>
        <?php else: ?>
            <span class="disabled"><i class="fas fa-chevron-right"></i></span>
            <span class="disabled"><i class="fas fa-angles-right"></i></span>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if (!$isTrash && !empty($messages)): ?>
    <div class="page-stats">
        <i class="fas fa-clock"></i> На этой странице: ~<?php echo $pageMinutes; ?> мин. Всего во вкладке: ~<?php echo $totalMinutes; ?> мин
    </div>
    <?php endif; ?>

    <div class="output-section" id="outputSection">
        <?php if (empty($currentPageMessages)): ?>
            <div class="empty-message"><i class="fas fa-inbox"></i> <?php echo $isTrash ? 'Корзина пуста.' : 'Пока нет текста. Вставьте текст выше.'; ?></div>
        <?php else: ?>
            <?php foreach ($currentPageMessages as $msg): ?>
                <div class="message-block" data-original="<?php echo htmlspecialchars($msg['original'], ENT_QUOTES, 'UTF-8'); ?>">
                    <?php if ($isTrash): ?>
                        <form method="post" style="position:absolute; top:12px; right:12px;" onsubmit="return confirm('Удалить сообщение безвозвратно?');">
                            <input type="hidden" name="delete_message_permanent" value="1">
                            <input type="hidden" name="original_index" value="<?php echo $msg['original_index']; ?>">
                            <button type="submit" class="delete-message" aria-label="Удалить навсегда"><i class="fas fa-times-circle"></i></button>
                        </form>
                    <?php else: ?>
                        <form method="post" style="display:inline;">
                            <input type="hidden" name="delete_message" value="1">
                            <input type="hidden" name="original_index" value="<?php echo $msg['original_index']; ?>">
                            <button type="submit" class="delete-message" aria-label="Удалить"><i class="fas fa-trash-alt"></i></button>
                        </form>
                    <?php endif; ?>
                    <div class="message-content">
                        <?php echo nl2br(htmlspecialchars($msg['cleaned'])); ?>
                        <?php if (isset($msg['fragment']) && $msg['fragment']): ?>
                            <br><small style="color:#8e8e93;"><i class="fas fa-cut"></i> Фрагмент (копируется полный текст)</small>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if (!$isTrash && $page == $totalPages && !empty($messages)): ?>
                <button class="copy-all-btn" id="copyAllCardsBtn"><i class="fas fa-copy"></i> Скопировать все карточки вкладки</button>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <div class="logout-bottom">
        <a href="?logout=1"><i class="fas fa-sign-out-alt"></i> Выйти</a>
    </div>
</div>

<div class="floating-buttons">
    <button class="floating-btn" id="settingsBtn" title="Настройки"><i class="fas fa-cog"></i></button>
    <button class="floating-btn scroll-btn" id="scrollBtn" title="Прокрутить вниз"><i class="fas fa-arrow-down"></i></button>
</div>

<div id="settingsModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Настройки пагинации и режима текста</h3>
            <button class="close-modal" id="closeModalBtn">&times;</button>
        </div>
        <form id="modal_settings_form" method="post" action="<?php echo $isTrash ? '?tab=_trash' : '?tab=' . urlencode($currentTab); ?>&page=1&chunk_size=<?php echo $chunkSize; ?>&max_block=<?php echo $maxBlock; ?>">
            <div class="settings-row-modal">
                <label><i class="fas fa-file-alt"></i> Размер страницы (символов):</label>
                <div class="chunk-controls-modal">
                    <button type="button" class="chunk-dec-modal" data-target="chunk_size_input_modal" data-step="10000"><i class="fas fa-minus"></i></button>
                    <input type="number" name="chunk_size_input" id="chunk_size_input_modal" value="<?php echo $chunkSize; ?>" min="1000" max="200000">
                    <button type="button" class="chunk-inc-modal" data-target="chunk_size_input_modal" data-step="10000"><i class="fas fa-plus"></i></button>
                </div>
            </div>
            <div class="settings-row-modal">
                <label><i class="fas fa-layer-group"></i> Макс. размер неразбиваемого блока:</label>
                <div class="chunk-controls-modal">
                    <button type="button" class="chunk-dec-modal" data-target="max_block_input_modal" data-step="10000"><i class="fas fa-minus"></i></button>
                    <input type="number" name="max_block_input" id="max_block_input_modal" value="<?php echo $maxBlock; ?>" min="1000" max="500000">
                    <button type="button" class="chunk-inc-modal" data-target="max_block_input_modal" data-step="10000"><i class="fas fa-plus"></i></button>
                </div>
            </div>
            <div class="settings-row-modal">
                <label><i class="fas fa-book"></i> Режим обработки текста:</label>
                <select name="text_mode" style="padding:8px 12px; border-radius:30px; border:1px solid #c6c6c8;">
                    <option value="normal" <?php echo $textMode === 'normal' ? 'selected' : ''; ?>>Обычный (добавлять точки)</option>
                    <option value="book" <?php echo $textMode === 'book' ? 'selected' : ''; ?>>Книжный (удалять переносы, без точек)</option>
                </select>
            </div>
            <div class="settings-row-modal">
                <button type="submit" name="update_settings" class="save-settings-modal"><i class="fas fa-save"></i> Применить</button>
            </div>
            <div class="action-buttons-modal">
                <a href="?tab=_trash" class="action-btn"><i class="fas fa-trash-alt"></i> Корзина</a>
                <button type="button" id="copyAllBtnModal" class="action-btn"><i class="fas fa-copy"></i> Копировать всё</button>
            </div>
        </form>
    </div>
</div>

<div id="toast" class="toast"><i class="fas fa-check-circle"></i> <span id="toastMessage"></span></div>

<script>
    function copyToClipboard(text) {
        // Используем execCommand как основной метод для надёжного сохранения переносов
        try {
            const textarea = document.createElement('textarea');
            textarea.value = text;
            textarea.style.position = 'fixed';
            textarea.style.left = '-9999px';
            textarea.style.top = '-9999px';
            document.body.appendChild(textarea);
            textarea.select();
            const success = document.execCommand('copy');
            document.body.removeChild(textarea);
            if (success) {
                showToast('Скопировано!');
                return;
            }
        } catch(e) {
            // fallback на clipboard API
        }
        // fallback
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(() => {
                showToast('Скопировано!');
            }).catch(() => {
                showToast('Не удалось скопировать');
            });
        } else {
            showToast('Не удалось скопировать');
        }
    }

    function showToast(message) {
        const toast = document.getElementById('toast');
        if (!toast) return;
        const toastMessageSpan = document.getElementById('toastMessage');
        if (toastMessageSpan) toastMessageSpan.textContent = message;
        toast.classList.add('show');
        setTimeout(() => {
            toast.classList.remove('show');
        }, 2500);
    }

    // Кодирование текста в base64 перед отправкой (для обхода mod_security)
    function encodeTextForSending(text) {
        if (!text) return text;
        try {
            return 'b64:' + btoa(encodeURIComponent(text).replace(/%([0-9A-F]{2})/g, function(match, p1) {
                return String.fromCharCode(parseInt(p1, 16));
            }));
        } catch(e) {
            return text;
        }
    }

    // Перехватываем отправку формы автосохранения
    const autoSaveForm = document.getElementById('auto_save_form');
    if (autoSaveForm) {
        autoSaveForm.addEventListener('submit', function(e) {
            const textarea = document.getElementById('main_text');
            if (textarea && textarea.value.trim() !== '') {
                const originalValue = textarea.value;
                const encoded = encodeTextForSending(originalValue);
                let hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = 'text';
                hiddenInput.value = encoded;
                this.appendChild(hiddenInput);
                textarea.removeAttribute('name');
            }
        });
    }

    document.querySelectorAll('.message-block').forEach(block => {
        block.addEventListener('click', (e) => {
            if (e.target.closest('.delete-message')) return;
            const original = block.getAttribute('data-original');
            if (original) copyToClipboard(original);
            else {
                const content = block.querySelector('.message-content')?.innerText;
                if (content) copyToClipboard(content);
            }
        });
    });

    // Кнопка "Скопировать все карточки вкладки"
    const copyAllCardsBtn = document.getElementById('copyAllCardsBtn');
    if (copyAllCardsBtn) {
        copyAllCardsBtn.addEventListener('click', function() {
            const fullOriginalText = <?php echo json_encode($fullOriginalText); ?>;
            console.log('Текст для копирования:', fullOriginalText); // отладка в консоли
            if (fullOriginalText.trim()) {
                copyToClipboard(fullOriginalText);
            } else {
                showToast('Нет текста для копирования');
            }
        });
    }

    <?php if (!$isTrash): ?>
    let timeoutId;
    const textarea = document.getElementById('main_text');
    function autoSave() {
        if (textarea.value.trim() !== '') {
            autoSaveForm.submit();
        }
    }
    if (textarea) {
        textarea.addEventListener('input', () => {
            clearTimeout(timeoutId);
            timeoutId = setTimeout(autoSave, 500);
        });
    }
    <?php endif; ?>

    const modal = document.getElementById('settingsModal');
    const settingsBtn = document.getElementById('settingsBtn');
    const closeModalBtn = document.getElementById('closeModalBtn');
    if (settingsBtn) {
        settingsBtn.addEventListener('click', () => { modal.style.display = 'flex'; });
    }
    if (closeModalBtn) {
        closeModalBtn.addEventListener('click', () => { modal.style.display = 'none'; });
    }
    window.addEventListener('click', (e) => { if (e.target === modal) modal.style.display = 'none'; });

    document.querySelectorAll('.chunk-dec-modal, .chunk-inc-modal').forEach(btn => {
        btn.addEventListener('click', () => {
            const targetId = btn.getAttribute('data-target');
            const input = document.getElementById(targetId);
            if (!input) return;
            let step = parseInt(btn.getAttribute('data-step')) || 10000;
            let delta = btn.classList.contains('chunk-dec-modal') ? -step : step;
            let newVal = (parseInt(input.value) || 0) + delta;
            let min = parseInt(input.getAttribute('min')) || 1000;
            let max = parseInt(input.getAttribute('max')) || 200000;
            if (targetId === 'max_block_input_modal') max = 500000;
            newVal = Math.min(max, Math.max(min, newVal));
            input.value = newVal;
        });
    });

    const copyAllBtn = document.getElementById('copyAllBtnModal');
    if (copyAllBtn) {
        copyAllBtn.addEventListener('click', () => {
            const outputSection = document.getElementById('outputSection');
            if (!outputSection) return;
            const textToCopy = outputSection.innerText;
            if (textToCopy.trim()) {
                copyToClipboard(textToCopy);
            } else {
                showToast('Нет текста для копирования');
            }
        });
    }

    <?php if ($toastType === 'save'): ?> showToast('Текст сохранён!'); <?php endif; ?>

    const scrollBtn = document.getElementById('scrollBtn');
    let scrollTarget = 'bottom';
    function updateScrollButton() {
        const scrollTop = window.scrollY;
        const atTop = scrollTop <= 50;
        if (atTop) {
            scrollBtn.classList.add('show');
            scrollBtn.innerHTML = '<i class="fas fa-arrow-down"></i>';
            scrollBtn.title = 'Прокрутить вниз';
            scrollTarget = 'bottom';
        } else {
            scrollBtn.classList.add('show');
            scrollBtn.innerHTML = '<i class="fas fa-arrow-up"></i>';
            scrollBtn.title = 'Прокрутить наверх';
            scrollTarget = 'top';
        }
    }
    window.addEventListener('scroll', updateScrollButton);
    window.addEventListener('resize', updateScrollButton);
    updateScrollButton();
    scrollBtn.addEventListener('click', () => {
        if (scrollTarget === 'top') {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        } else {
            window.scrollTo({ top: document.documentElement.scrollHeight, behavior: 'smooth' });
        }
    });
</script>
</body>
</html>