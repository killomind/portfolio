<?php
// ========== ДОЛГАЯ СЕССИЯ (90 дней) ==========
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

if (isset($_SESSION['muzyka_admin']) && $_SESSION['muzyka_admin'] === true) {
    setcookie(session_name(), session_id(), time() + $lifetime, '/');
}

$hashFile = __DIR__ . '/.admin_hash';
if (!is_file($hashFile)) {
    file_put_contents($hashFile, password_hash('admin', PASSWORD_DEFAULT));
}

$isLoggedIn = isset($_SESSION['muzyka_admin']) && $_SESSION['muzyka_admin'] === true;

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

if (!$isLoggedIn && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'], $_POST['password'])) {
    if ($_POST['login'] === 'admin' && password_verify($_POST['password'], file_get_contents($hashFile))) {
        $_SESSION['muzyka_admin'] = true;
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
        <title>CRM музыкантов — вход</title>
        <style>
            *{box-sizing:border-box}
            body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;background:#f5f5f7;margin:0;padding:20px;display:flex;justify-content:center;align-items:center;min-height:100vh}
            .login-container{max-width:400px;width:100%;background:#fff;border-radius:28px;padding:32px 24px;box-shadow:0 8px 28px rgba(0,0,0,0.05);text-align:center}
            h2{margin-bottom:8px;font-weight:600;color:#1c1c1e}
            .login-sub{color:#8e8e93;font-size:13px;margin-bottom:24px}
            input{width:100%;padding:14px;margin-bottom:16px;border:1px solid #c6c6c8;border-radius:14px;font-size:16px}
            input:focus{outline:none;border-color:#ff9f00;box-shadow:0 0 0 3px rgba(255,159,0,0.1)}
            button{width:100%;background:#fff;color:#ff9f00;border:2px solid #ff9f00;padding:12px;font-size:16px;font-weight:600;border-radius:40px;cursor:pointer}
            button:hover{background:#fff6e5}
            button.demo-btn{background:#ff9f00;color:#fff;margin-bottom:14px}
            button.demo-btn:hover{background:#ffb224;border-color:#ffb224}
            .divider{color:#8e8e93;font-size:12px;margin:18px 0;display:flex;align-items:center;gap:10px}
            .divider:before,.divider:after{content:"";flex:1;height:1px;background:#e5e5ea}
            .demo-hint{color:#8e8e93;font-size:12px;margin-top:16px}
            .error{color:#ff3b30;margin-bottom:16px;font-size:14px}
        </style>
    </head>
    <body>
        <div class="login-container">
            <h2>CRM музыкантов</h2>
            <div class="login-sub">Концерты и выступления — демо-версия</div>
            <?php if (isset($loginError)) echo '<div class="error">' . htmlspecialchars($loginError) . '</div>'; ?>
            <form method="post">
                <input type="hidden" name="login" value="admin">
                <input type="hidden" name="password" value="admin">
                <button type="submit" class="demo-btn">Войти в демо-версию</button>
            </form>
            <div class="divider">или вход по логину</div>
            <form method="post">
                <input type="text" name="login" placeholder="Логин" autocomplete="username" required>
                <input type="password" name="password" placeholder="Пароль" autocomplete="current-password" required>
                <button type="submit">Войти</button>
            </form>
            <p class="demo-hint">Демо-доступ: admin / admin</p>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// ========== ДАННЫЕ ==========
$dir = __DIR__ . '/data';
if (!is_dir($dir)) {
    mkdir($dir, 0755, true);
}
$dataFile = $dir . '/musicians.json';
$settingsFile = $dir . '/settings.json';
$photoDir = __DIR__ . '/photos';

function loadJson($file, $default) {
    if (!file_exists($file)) {
        file_put_contents($file, json_encode($default, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
    $data = json_decode(file_get_contents($file), true);
    return is_array($data) ? $data : $default;
}
function saveJson($file, $data) {
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}
function saveMusicians($musicians) {
    global $dataFile;
    saveJson($dataFile, ['musicians' => $musicians]);
}

$store = loadJson($dataFile, ['musicians' => []]);
$musicians = $store['musicians'];

$settings = loadJson($settingsFile, ['page_size' => 20]);
if (!isset($settings['page_size']) || (int)$settings['page_size'] < 1) $settings['page_size'] = 20;
$pageSize = (int)$settings['page_size'];

function carryGet($exclude = []) {
    $parts = [];
    foreach ($_GET as $k => $v) {
        if (in_array($k, $exclude)) continue;
        if ($v === '' || $v === null) continue;
        $parts[] = urlencode($k) . '=' . urlencode($v);
    }
    return implode('&', $parts);
}

// ========== POST-ДЕЙСТВИЯ ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Сохранение карточки (новая или редактирование)
    if (isset($_POST['save_card'])) {
        $id = isset($_POST['card_id']) ? trim($_POST['card_id']) : '';
        $now = date('Y-m-d H:i');
        $card = [
            'id' => $id !== '' ? $id : 'm_' . uniqid(),
            'name' => trim($_POST['name'] ?? ''),
            'phone' => trim($_POST['phone'] ?? ''),
            'vk' => trim($_POST['vk'] ?? ''),
            'role' => trim($_POST['role'] ?? ''),
            'equipment' => trim($_POST['equipment'] ?? ''),
            'status' => trim($_POST['status'] ?? ''),
            'priority' => trim($_POST['priority'] ?? ''),
            'note' => trim($_POST['note'] ?? ''),
            'promised_date' => trim($_POST['promised_date'] ?? ''),
            'created_at' => '',
            'updated_at' => $now,
        ];
        if ($card['name'] !== '') {
            $found = false;
            foreach ($musicians as $i => $m) {
                if (($m['id'] ?? '') === $card['id']) {
                    $card['created_at'] = $m['created_at'] ?? $now;
                    $card['photo'] = $m['photo'] ?? '';
                    $musicians[$i] = array_merge($m, $card);
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $card['created_at'] = $now;
                $card['photo'] = '';
                $musicians[] = $card;
            }
            saveMusicians($musicians);
        }
        if (isset($_FILES['photo']) && is_uploaded_file($_FILES['photo']['tmp_name']) && $card['name'] !== '') {
            if (!is_dir($photoDir)) mkdir($photoDir, 0755, true);
            $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
            $ext = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp']) ? $ext : 'jpg';
            $name = $card['id'] . '.' . $ext;
            if (move_uploaded_file($_FILES['photo']['tmp_name'], $photoDir . '/' . $name)) {
                foreach ($musicians as $i => $m) {
                    if (($m['id'] ?? '') === $card['id']) {
                        $musicians[$i]['photo'] = $name;
                        break;
                    }
                }
                saveMusicians($musicians);
            }
        }
        $q = carryGet(['page']);
        if (!empty($_POST['ajax'])) {
            header('Content-Type: application/json; charset=utf-8');
            $final = null;
            foreach ($musicians as $i => $m) {
                if (($m['id'] ?? '') === $card['id']) { $final = $musicians[$i]; break; }
            }
            echo json_encode(['ok' => true, 'card' => $final], JSON_UNESCAPED_UNICODE);
            exit;
        }
        header('Location: ?' . ($q !== '' ? $q . '&' : '') . 'toast=save');
        exit;
    }

    // Удаление фото карточки
    if (isset($_POST['remove_photo']) && isset($_POST['card_id'])) {
        $rid = $_POST['card_id'];
        $rmPath = __DIR__ . '/photos/' . basename($_POST['remove_photo'] ?? '');
        if (is_file($rmPath)) unlink($rmPath);
        foreach ($musicians as $i => $m) {
            if (($m['id'] ?? '') === $rid) {
                $musicians[$i]['photo'] = '';
                break;
            }
        }
        saveMusicians($musicians);
        $q = carryGet(['page']);
        if (!empty($_POST['ajax'])) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => true, 'card_id' => $rid, 'photo' => ''], JSON_UNESCAPED_UNICODE);
            exit;
        }
        header('Location: ?' . ($q !== '' ? $q . '&' : '') . 'toast=save');
        exit;
    }

    // Удаление карточки
    if (isset($_POST['delete_card']) && isset($_POST['card_id'])) {
        $delId = $_POST['card_id'];
        foreach ($musicians as $i => $m) {
            if (($m['id'] ?? '') === $delId) {
                array_splice($musicians, $i, 1);
                break;
            }
        }
        saveMusicians($musicians);
        $q = carryGet(['page']);
        header('Location: ?' . ($q !== '' ? $q . '&' : '') . 'toast=delete');
        exit;
    }

    // Обновление настроек
    if (isset($_POST['update_settings'])) {
        $newSize = isset($_POST['page_size']) ? (int)$_POST['page_size'] : $pageSize;
        $newSize = max(5, min(100, $newSize));
        $settings['page_size'] = $newSize;
        saveJson($settingsFile, $settings);
        $msg = 'toast=save';
        if (isset($_POST['new_password']) && trim($_POST['new_password']) !== '') {
            file_put_contents($hashFile, password_hash(trim($_POST['new_password']), PASSWORD_DEFAULT));
            $msg = 'toast=pass';
        }
        $q = carryGet(['page']);
        header('Location: ?' . ($q !== '' ? $q . '&' : '') . $msg);
        exit;
    }
}

// ========== ПОИСК, ФИЛЬТР, ПАГИНАЦИЯ ==========
$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$pdate = isset($_GET['pdate']) ? trim($_GET['pdate']) : '';
$prio = isset($_GET['prio']) ? trim($_GET['prio']) : '';
$page = isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 ? (int)$_GET['page'] : 1;

$prioLabels = ['high' => 'Приоритет', 'medium' => 'Средний', 'low' => 'Не приоритет', '' => 'Не задан'];

// доступные даты участия для фильтра
$promisedDates = [];
foreach ($musicians as $m) {
    $d = trim($m['promised_date'] ?? '');
    if ($d !== '') $promisedDates[$d] = true;
}
ksort($promisedDates);
$promisedDates = array_keys($promisedDates);

function fmtDateRu($dateStr) {
    if ($dateStr === '' || $dateStr === null) return '';
    $t = strtotime($dateStr);
    if (!$t) return htmlspecialchars($dateStr);
    $months = [1 => 'января','февраля','марта','апреля','мая','июня','июля','августа','сентября','октября','ноября','декабря'];
    $m = (int)date('n', $t);
    $d = (int)date('j', $t);
    $y = (int)date('Y', $t);
    $cur = (int)date('Y');
    return $d . ' ' . ($months[$m] ?? $m) . ($y !== $cur ? ' ' . $y : '');
}

$filtered = [];
foreach ($musicians as $m) {
    $name = $m['name'] ?? '';
    $phone = $m['phone'] ?? '';
    $vk = $m['vk'] ?? '';
    $role = $m['role'] ?? '';
    $note = $m['note'] ?? '';
    $status = $m['status'] ?? '';
    $equipment = $m['equipment'] ?? '';
    $priority = trim($m['priority'] ?? '');
    $promDate = trim($m['promised_date'] ?? '');
    if ($pdate !== '' && $promDate !== $pdate) continue;
    if ($prio === 'unset' && $priority !== '') continue;
    if ($prio !== '' && $prio !== 'unset' && $priority !== $prio) continue;
    if ($q !== '') {
        $hay = mb_strtolower($name . ' ' . $phone . ' ' . $vk . ' ' . $role . ' ' . $note . ' ' . $status . ' ' . $equipment);
        if (mb_strpos($hay, mb_strtolower($q)) === false) continue;
    }
    $filtered[] = $m;
}

usort($filtered, function($a, $b) {
    $ad = $a['promised_date'] ?? '';
    $bd = $b['promised_date'] ?? '';
    $aName = mb_strtolower($a['name'] ?? '');
    $bName = mb_strtolower($b['name'] ?? '');
    if ($ad !== $bd) return strcmp($ad, $bd); // пустые даты идут первыми
    return strcmp($aName, $bName);
});

$total = count($filtered);
$totalPages = max(1, (int)ceil($total / $pageSize));
if ($page > $totalPages) $page = $totalPages;
$offset = ($page - 1) * $pageSize;
$pageItems = array_slice($filtered, $offset, $pageSize);

$baseQ = [];
if ($q !== '') $baseQ[] = 'q=' . urlencode($q);
if ($pdate !== '') $baseQ[] = 'pdate=' . urlencode($pdate);
if ($prio !== '') $baseQ[] = 'prio=' . urlencode($prio);

function pageUrl($p) {
    global $baseQ;
    return '?page=' . $p . ($baseQ ? '&' . implode('&', $baseQ) : '');
}

$toastType = isset($_GET['toast']) ? $_GET['toast'] : '';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <meta name="robots" content="noindex, nofollow">
    <title>CRM музыкантов</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons+Outlined">
    <style>
        *{box-sizing:border-box}
        body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;background:#f5f5f7;margin:0;padding:20px;color:#1c1c1e}
        .container{max-width:1000px;margin:0 auto;background:#fff;border-radius:28px;box-shadow:0 2px 12px rgba(0,0,0,0.05);padding:24px 20px 32px}
        .topbar{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:20px}
        .topbar h1{font-size:22px;font-weight:600;margin:0;display:flex;align-items:center;gap:10px}
        .topbar h1 .material-icons-outlined{color:#ff9f00}
        .nav-tabs{display:flex;gap:6px;flex-wrap:wrap}
        .nav-tab{display:inline-flex;align-items:center;gap:6px;background:#DCDCDC;border-radius:12px;padding:8px 14px;font-size:14px;font-weight:500;cursor:pointer;text-decoration:none;color:#000;transition:0.2s}
        .nav-tab.active{background:#fff;color:#ff9f00;box-shadow:inset 0 0 0 2px #ff9f00}
        .nav-tab:hover{background:#c0c0c0}
        .toolbar{display:flex;flex-wrap:wrap;gap:10px;margin-bottom:18px;align-items:center}
        .search-wrap{position:relative;flex:1;min-width:200px}
        .search-wrap input{width:100%;padding:12px 14px 12px 40px;border:1px solid #c6c6c8;border-radius:14px;font-size:15px}
        .search-wrap .material-icons-outlined{position:absolute;left:12px;top:50%;transform:translateY(-50%);color:#8e8e93;font-size:20px}
        input:focus,select:focus,textarea:focus{outline:none;border-color:#ff9f00;box-shadow:0 0 0 3px rgba(255,159,0,0.1)}
        select{padding:10px 12px;border:1px solid #c6c6c8;border-radius:14px;font-size:14px;background:#fff}
        .btn-add{display:inline-flex;align-items:center;gap:6px;background:#ff9f00;color:#fff;border:none;border-radius:14px;padding:10px 16px;font-size:14px;font-weight:600;cursor:pointer;text-decoration:none}
        .btn-add:hover{background:#e68a00}
        .btn-ghost{display:inline-flex;align-items:center;gap:6px;background:#fff;color:#ff9f00;border:2px solid #ff9f00;border-radius:14px;padding:8px 14px;font-size:14px;font-weight:600;cursor:pointer;text-decoration:none}
        .btn-ghost:hover{background:#fff6e5}
        .cards{display:grid;grid-template-columns:1fr 1fr;gap:16px}
        @media (max-width:700px){.cards{grid-template-columns:1fr}}
        .card{position:relative;background:#f9f9fb;border-radius:20px;padding:16px;border:1px solid #e1e1e6;cursor:pointer;transition:0.2s;min-height:120px;display:flex;flex-direction:column;gap:8px}
        .card:hover{border-color:#ff9f00;box-shadow:0 4px 16px rgba(255,159,0,0.12)}
        .card:active{background:#eef0f4}
        .card-top{display:flex;justify-content:space-between;align-items:flex-start;gap:8px;padding-right:70px}
        .avatar{width:52px;height:52px;border-radius:50%;object-fit:cover;border:2px solid #fff;box-shadow:0 2px 8px rgba(0,0,0,0.12);flex-shrink:0;background:#e1e1e6}
        .card-head{display:flex;align-items:center;gap:12px}
        .avatar-edit{display:flex;align-items:center;gap:14px;background:#f5f5f7;border:1px solid #e1e1e6;border-radius:16px;padding:12px;flex-wrap:wrap}
        .avatar-edit .avatar{width:72px;height:72px;border:3px solid #fff}
        .avatar-edit-controls{display:flex;align-items:center;gap:8px}
        .card-name{font-size:17px;font-weight:600;line-height:1.25;word-break:break-word}
        .card-actions{position:absolute;top:12px;right:12px;display:flex;gap:4px;z-index:3}
        .icon-btn{background:transparent;border:none;color:#8e8e93;cursor:pointer;font-size:22px;padding:2px;border-radius:12px;transition:0.2s;line-height:1}
        .icon-btn:hover{color:#ff9f00;background:#e0e0e0}
        .icon-btn.danger:hover{color:#ff3b30}
        .chip-row{display:flex;flex-wrap:wrap;gap:6px}
        .chip{display:inline-flex;align-items:center;gap:4px;background:#fff;border:1px solid #e1e1e6;border-radius:30px;padding:4px 10px;font-size:12.5px;color:#3a3a3c}
        .chip .material-icons-outlined{font-size:15px;color:#ff9f00}
        .chip.status-1{background:#fff6e5;border-color:#ffcf96;color:#9a5b00}
        .chip.status-2{background:#e8f7e9;border-color:#b5e3b8;color:#1f6b24}
        .chip.status-3{background:#fdeaea;border-color:#f5b9b9;color:#a11}
        .chip.p-high{background:#e8f7e9;border-color:#b5e3b8;color:#1f6b24}
        .chip.p-medium{background:#e8f1fb;border-color:#b8d4f5;color:#1d4f8f}
        .chip.p-low{background:#f1f1f4;border-color:#d8d8dc;color:#6c6c6c}
        .chip.p-none{background:#f1f1f4;border-color:#d8d8dc;color:#8e8e93}
        .promised{background:#fff0df;border-color:#ffd9a8;color:#9a5b00;font-weight:600}
        .card-note{font-size:13.5px;color:#6c6c6c;line-height:1.45;word-wrap:break-word;flex:1}
        .card-meta{display:flex;flex-wrap:wrap;gap:4px 12px;font-size:12.5px;color:#8e8e93}
        .card-meta .material-icons-outlined{font-size:14px;vertical-align:-2px;color:#c0c0c0}
        .empty-message{color:#8e8e93;text-align:center;padding:40px 20px}
        .empty-message .material-icons-outlined{font-size:40px;color:#d0d0d5;display:block;margin-bottom:8px}
        .results-info{font-size:13px;color:#6c6c6c;margin-bottom:12px}
        .pagination{display:flex;flex-wrap:wrap;justify-content:center;gap:8px;margin:20px 0 8px}
        .pagination a,.pagination span{display:inline-flex;align-items:center;justify-content:center;min-width:44px;height:44px;padding:0 12px;border-radius:30px;background:#f9f9fb;color:#ff9f00;text-decoration:none;font-weight:500;font-size:15px;border:1px solid transparent}
        .pagination a:hover{background:#fff6e5;border:1px solid #ff9f00}
        .pagination .current{background:#fff;border:2px solid #ff9f00;color:#ff9f00}
        .pagination .disabled{color:#c6c6c8;pointer-events:none}
        .logout-bottom{margin-top:36px;text-align:center;border-top:1px solid #e9e9ef;padding-top:20px;display:flex;justify-content:center;gap:10px;flex-wrap:wrap}
        .logout-bottom a{background:#fff;color:#ff9f00;border:2px solid #ff9f00;padding:10px 24px;border-radius:40px;text-decoration:none;font-weight:600;display:inline-flex;align-items:center;gap:8px}
        .logout-bottom a:hover{background:#fff6e5}
        .modal{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:2000;align-items:flex-start;justify-content:center;overflow-y:auto;padding:20px 12px}
        .modal.show{display:flex}
        .modal-content{background:#fff;border-radius:28px;max-width:640px;width:100%;padding:24px;box-shadow:0 8px 28px rgba(0,0,0,0.2);margin:auto}
        .modal-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:16px}
        .modal-header h3{margin:0;font-weight:600;font-size:18px}
        .close-modal{background:none;border:none;font-size:28px;cursor:pointer;color:#8e8e93;line-height:1}
        .form-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
        @media (max-width:700px){.form-grid{grid-template-columns:1fr}}
        .field label{display:block;font-size:12.5px;font-weight:600;color:#6c6c6c;margin-bottom:5px}
        .field input,.field select,.field textarea{width:100%;padding:11px 12px;border:1px solid #c6c6c8;border-radius:12px;font-size:15px;font-family:inherit;background:#fff}
        .field textarea{min-height:90px;resize:vertical}
        .field.full{grid-column:1 / -1}
        .dates-info{font-size:12px;color:#8e8e93;margin:10px 0 0}
        .form-actions{display:flex;gap:10px;margin-top:18px}
        .btn-primary{flex:1;background:#ff9f00;color:#fff;border:none;border-radius:40px;padding:12px;font-size:15px;font-weight:600;cursor:pointer}
        .btn-primary:hover{background:#e68a00}
        .btn-cancel{flex:1;background:#fff;color:#8e8e93;border:2px solid #c6c6c8;border-radius:40px;padding:12px;font-size:15px;font-weight:600;cursor:pointer}
        .btn-cancel:hover{background:#f2f2f7}
        .settings-form{padding:6px 0}
        .settings-form .row{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:18px;flex-wrap:wrap}
        .settings-form label{font-size:14px;font-weight:500;display:flex;align-items:center;gap:8px}
        .settings-form input{width:140px;padding:10px 12px;border:1px solid #c6c6c8;border-radius:12px;font-size:15px}
        .toast{position:fixed;bottom:30px;left:50%;transform:translateX(-50%);background:#1c1c1e;color:#fff;padding:10px 24px;border-radius:40px;font-size:14px;z-index:1001;opacity:0;transition:opacity 0.2s;pointer-events:none;display:flex;gap:8px;white-space:nowrap;box-shadow:0 4px 12px rgba(0,0,0,0.15)}
        .toast.show{opacity:1}
        @media (max-width:600px){
            body{padding:12px}
            .container{padding:20px 14px}
            .topbar{flex-direction:column;align-items:stretch}
            .toolbar{flex-direction:column;align-items:stretch}
            .card-name{font-size:16px}
            .toast{white-space:normal;max-width:80vw;text-align:center}
        }
        .fade-in{animation:fadeIn .25s ease}
        @keyframes fadeIn{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:none}}
    </style>
</head>
<body>
<div class="container">
    <div class="topbar">
        <h1><i class="material-icons-outlined">library_music</i> CRM музыкантов</h1>
        <div class="nav-tabs">
            <a class="nav-tab active" href="index.php"><i class="material-icons-outlined" style="font-size:18px">people</i> Музыканты</a>
            <a class="nav-tab" href="notes.php"><i class="material-icons-outlined" style="font-size:18px">sticky_note_2</i> Накопитель</a>
            <button class="nav-tab" id="settingsBtn" style="border:none"><i class="material-icons-outlined" style="font-size:18px">settings</i> Настройки</button>
        </div>
    </div>

    <div class="toolbar">
        <div class="search-wrap">
            <i class="material-icons-outlined">search</i>
            <form method="get" id="searchForm" style="margin:0">
                <?php if ($pdate !== ''): ?><input type="hidden" name="pdate" value="<?php echo htmlspecialchars($pdate); ?>"><?php endif; ?>
                <?php if ($prio !== ''): ?><input type="hidden" name="prio" value="<?php echo htmlspecialchars($prio); ?>"><?php endif; ?>
                <input type="text" name="q" id="qInput" value="<?php echo htmlspecialchars($q); ?>" placeholder="Поиск: имя, телефон, инструмент, заметка…">
            </form>
        </div>
        <form method="get" id="filterForm" style="display:flex;gap:10px;margin:0">
            <select name="pdate" id="pdateSelect">
                <option value="">Участие: все даты</option>
                <?php foreach ($promisedDates as $d): ?>
                    <option value="<?php echo htmlspecialchars($d); ?>" <?php echo $pdate === $d ? 'selected' : ''; ?>><?php echo fmtDateRu($d); ?></option>
                <?php endforeach; ?>
            </select>
            <select name="prio" id="prioSelect">
                <option value="">Приоритет: все</option>
                <option value="high" <?php echo $prio === 'high' ? 'selected' : ''; ?>>Приоритет</option>
                <option value="medium" <?php echo $prio === 'medium' ? 'selected' : ''; ?>>Средний</option>
                <option value="low" <?php echo $prio === 'low' ? 'selected' : ''; ?>>Не приоритет</option>
                <option value="unset" <?php echo $prio === 'unset' ? 'selected' : ''; ?>>Не задан</option>
            </select>
            <?php if ($q !== ''): ?><input type="hidden" name="q" value="<?php echo htmlspecialchars($q); ?>"><?php endif; ?>
        </form>
        <button class="btn-add" onclick="openCard(null)"><i class="material-icons-outlined" style="font-size:18px">person_add</i> Добавить</button>
    </div>

    <div class="results-info"><?php echo $total; ?> музыкантов.</div>

    <?php if (empty($pageItems)): ?>
        <div class="empty-message">
            <i class="material-icons-outlined">people_outline</i>
            Пока никого не нашлось. Нажмите «Добавить», чтобы создать карточку.
        </div>
    <?php else: ?>
        <div class="cards">
            <?php foreach ($pageItems as $m):
                $name = $m['name'] ?? 'Без имени';
                $phone = $m['phone'] ?? '';
                $vk = $m['vk'] ?? '';
                $role = $m['role'] ?? '';
                $note = $m['note'] ?? '';
                $status = $m['status'] ?? '';
                $promDate = trim($m['promised_date'] ?? '');
                $stClass = '';
                $stLow = mb_strtolower($status);
                if (in_array($stLow, ['контактировал', 'написал'])) $stClass = 'status-1';
                elseif (in_array($stLow, ['подтвердил', 'активен', 'выступал'])) $stClass = 'status-2';
                elseif (in_array($stLow, ['отказался', 'не смог написать', 'не ответил'])) $stClass = 'status-3';
            ?>
                <div class="card fade-in" onclick="openCard('<?php echo htmlspecialchars($m['id']); ?>')">
                    <div class="card-actions">
                        <button class="icon-btn" title="Редактировать" onclick="event.stopPropagation(); openCard('<?php echo htmlspecialchars($m['id']); ?>')"><i class="material-icons-outlined">edit</i></button>
                        <form method="post" style="display:inline" onsubmit="event.stopPropagation(); return confirm('Удалить карточку «<?php echo addslashes($name); ?>»?');">
                            <input type="hidden" name="delete_card" value="1">
                            <input type="hidden" name="card_id" value="<?php echo htmlspecialchars($m['id']); ?>">
                            <button class="icon-btn danger" title="Удалить" type="submit" onclick="event.stopPropagation();"><i class="material-icons-outlined">delete</i></button>
                        </form>
                    </div>
                    <div class="card-top">
                        <div class="card-head">
                            <?php if (($m['photo'] ?? '') !== '' && file_exists($photoDir . '/' . $m['photo'])): ?>
                                <img class="avatar" src="photos/<?php echo rawurlencode($m['photo']); ?>" alt="">
                            <?php endif; ?>
                            <div class="card-name"><?php echo htmlspecialchars($name); ?></div>
                        </div>
                    </div>
                    <div class="chip-row">
                        <?php if ($role !== ''): ?>
                            <span class="chip"><i class="material-icons-outlined">music_note</i> <?php echo htmlspecialchars($role); ?></span>
                        <?php endif; ?>
                        <?php $priority = trim($m['priority'] ?? ''); ?>
                <?php if ($priority !== ''): ?>
                    <span class="chip p-<?php echo htmlspecialchars($priority); ?>"><i class="material-icons-outlined">flag</i> <?php echo htmlspecialchars($prioLabels[$priority] ?? $priority); ?></span>
                <?php endif; ?>
                <?php if ($status !== ''): ?>
                            <span class="chip <?php echo $stClass; ?>"><?php echo htmlspecialchars($status); ?></span>
                        <?php endif; ?>
                        <?php if ($promDate !== ''): ?>
                            <span class="chip promised"><i class="material-icons-outlined">event</i> <?php echo fmtDateRu($promDate); ?></span>
                        <?php endif; ?>
                    </div>
                    <?php if ($note !== ''): ?>
                        <div class="card-note"><?php echo htmlspecialchars(mb_strimwidth($note, 0, 220, '…')); ?></div>
                    <?php endif; ?>
                    <div class="card-meta">
                        <?php if ($phone !== ''): ?><span><i class="material-icons-outlined">call</i> <?php echo htmlspecialchars($phone); ?></span><?php endif; ?>
                        <?php if ($vk !== ''): ?><span><i class="material-icons-outlined">link</i> <?php echo htmlspecialchars($vk); ?></span><?php endif; ?>
                        <span><i class="material-icons-outlined">schedule</i> обн. <?php echo htmlspecialchars($m['updated_at'] ?? ''); ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="<?php echo pageUrl($page - 1); ?>">‹</a>
            <?php else: ?>
                <span class="disabled">‹</span>
            <?php endif; ?>
            <?php
            $start = max(1, $page - 2);
            $end = min($totalPages, $page + 2);
            if ($start > 1) echo '<span>…</span>';
            for ($i = $start; $i <= $end; $i++):
            ?>
                <?php if ($i === $page): ?>
                    <span class="current"><?php echo $i; ?></span>
                <?php else: ?>
                    <a href="<?php echo pageUrl($i); ?>"><?php echo $i; ?></a>
                <?php endif; ?>
            <?php endfor;
            if ($end < $totalPages) echo '<span>…</span>';
            ?>
            <?php if ($page < $totalPages): ?>
                <a href="<?php echo pageUrl($page + 1); ?>">›</a>
            <?php else: ?>
                <span class="disabled">›</span>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    <?php endif; ?>

    <div class="logout-bottom">
        <a href="?logout=1"><i class="material-icons-outlined" style="font-size:18px">logout</i> Выйти</a>
    </div>
</div>

<!-- Модальное окно редактирования карточки -->
<div class="modal" id="cardModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="cardModalTitle">Карточка музыканта</h3>
            <button class="close-modal" onclick="requestClose()">&times;</button>
        </div>
        <form id="cardForm" method="post" enctype="multipart/form-data">
            <input type="hidden" name="save_card" value="1">
            <input type="hidden" name="ajax" value="1">
            <input type="hidden" name="card_id" id="f_id" value="">
            <div class="form-grid">
                <div class="field full">
                    <label>Фото (аватар)</label>
                    <div class="avatar-edit">
                        <img id="f_photo_preview" class="avatar" src="" alt="">
                        <div class="avatar-edit-controls">
                            <label class="btn-ghost" style="cursor:pointer" for="f_photo"><i class="material-icons-outlined" style="font-size:16px">add_a_photo</i> Загрузить</label>
                            <input type="file" name="photo" id="f_photo" accept="image/*" style="display:none">
                            <button type="button" class="icon-btn danger" id="f_remove_photo" title="Удалить фото" style="display:none"><i class="material-icons-outlined">delete</i></button>
                        </div>
                    </div>
                </div>
                <div class="field">
                    <label>Имя</label>
                    <input type="text" name="name" id="f_name" required>
                </div>
                <div class="field">
                    <label>Телефон</label>
                    <input type="text" name="phone" id="f_phone" placeholder="+7 900 000-00-00">
                </div>
                <div class="field">
                    <label>ВКонтакте / Telegram</label>
                    <input type="text" name="vk" id="f_vk" placeholder="https://vk.com/... или @telegram">
                </div>
                <div class="field">
                    <label>На чём играет</label>
                    <input type="text" name="role" id="f_role" placeholder="вокал, гитара, перкуссия…">
                </div>
                <div class="field full">
                    <label>Что нужно по техническому оснащению</label>
                    <input type="text" name="equipment" id="f_equipment" placeholder="кабель, стойка, микрофон, гнездо…">
                </div>
                <div class="field">
                    <label>Ситуация / статус</label>
                    <input type="text" name="status" id="f_status" list="statusList" placeholder="не контактировал…">
                    <datalist id="statusList">
                        <option value="не контактировал"><option value="написал"><option value="разговор"><option value="подтвердил"><option value="выступал"><option value="активен"><option value="отказался"><option value="не смог написать">
                    </datalist>
                </div>
                <div class="field">
                    <label>Дата участия</label>
                    <input type="date" name="promised_date" id="f_promised">
                </div>
                <div class="field">
                    <label>Приоритет</label>
                    <select name="priority" id="f_priority">
                        <option value="">Не задан</option>
                        <option value="high">Приоритет</option>
                        <option value="medium">Средний</option>
                        <option value="low">Не приоритет</option>
                    </select>
                </div>
                <div class="field full">
                    <label>Комментарий / договорённости</label>
                    <textarea name="note" id="f_note" placeholder="С кем поговорил, о чём договорились…"></textarea>
                </div>
            </div>
            <div class="dates-info" id="datesInfo"></div>
            <div class="form-actions">
                <button type="button" class="btn-cancel" onclick="requestClose()">Отмена</button>
                <button type="submit" class="btn-primary">Сохранить</button>
            </div>
        </form>
    </div>
</div>

<!-- Настройки -->
<div class="modal" id="settingsModal">
    <div class="modal-content" style="max-width:520px">
        <div class="modal-header">
            <h3>Настройки</h3>
            <button class="close-modal" id="closeSettingsBtn">&times;</button>
        </div>
        <form method="post" class="settings-form">
            <input type="hidden" name="update_settings" value="1">
            <div class="row">
                <label><i class="material-icons-outlined" style="color:#ff9f00">view_agenda</i> Карточек на странице:</label>
                <input type="number" name="page_size" value="<?php echo $pageSize; ?>" min="5" max="100">
            </div>
            <div class="row">
                <label><i class="material-icons-outlined" style="color:#ff9f00">lock</i> Новый пароль (если менять):</label>
                <input type="password" name="new_password" placeholder="— оставить прежний —">
            </div>
            <button type="submit" class="btn-primary">Сохранить</button>
        </form>
    </div>
</div>

<div class="toast" id="toast"></div>

<script>
var CARDS = <?php
    $map = [];
    foreach ($musicians as $m) { $map[$m['id']] = $m; }
    echo json_encode($map, JSON_UNESCAPED_UNICODE);
?>;

function openCard(id) {
    var t = document.getElementById('cardModal');
    var c = CARDS[id];
    document.getElementById('cardModalTitle').textContent = c ? 'Редактирование карточки' : 'Новая карточка';
    document.getElementById('f_id').value = c ? c.id : '';
    document.getElementById('f_name').value = c ? (c.name || '') : '';
    document.getElementById('f_phone').value = c ? (c.phone || '') : '';
    document.getElementById('f_vk').value = c ? (c.vk || '') : '';
    document.getElementById('f_role').value = c ? (c.role || '') : '';
    document.getElementById('f_equipment').value = c ? (c.equipment || '') : '';
    document.getElementById('f_status').value = c ? (c.status || '') : '';
    document.getElementById('f_priority').value = c ? (c.priority || '') : '';
    document.getElementById('f_promised').value = c ? (c.promised_date || '') : '';
    document.getElementById('f_note').value = c ? (c.note || '') : '';
    var pv = document.getElementById('f_photo_preview');
    var rp = document.getElementById('f_remove_photo');
    if (c && c.photo) {
        pv.src = 'photos/' + encodeURIComponent(c.photo);
        pv.style.display = '';
        rp.style.display = '';
        rp.dataset.photo = c.photo;
    } else {
        pv.src = '';
        pv.style.display = 'none';
        rp.style.display = 'none';
        rp.dataset.photo = '';
    }
    document.getElementById('f_photo').value = '';
    var di = document.getElementById('datesInfo');
    di.textContent = c ? ('Создано: ' + (c.created_at || '—') + ' · Изменено: ' + (c.updated_at || '—')) : '';
    setDirty(false);
    t.classList.add('show');
}

var cardDirty = false;
function setDirty(v) {
    cardDirty = v;
}
function markDirty() {
    if (!cardDirty) cardDirty = true;
}

// отслеживание изменений в полях формы
var cardForm = document.getElementById('cardForm');
cardForm.querySelectorAll('input, textarea, select').forEach(function(el){
    ['input', 'change'].forEach(function(ev){
        el.addEventListener(ev, markDirty, {passive: true});
    });
});
// выбор нового фото тоже считается изменением
document.getElementById('f_photo').addEventListener('change', function(){
    if (this.files && this.files[0]) {
        var pv = document.getElementById('f_photo_preview');
        pv.src = URL.createObjectURL(this.files[0]);
        pv.style.display = '';
        markDirty();
    }
});

function requestClose() {
    if (cardDirty && !confirm('В карточке есть несохранённые изменения. Сохранить? \n\n«OK» — сохранить и закрыть, «Отмена» — закрыть без сохранения.')) {
        return; // закрыть без сохранения
    }
    if (cardDirty) {
        saveCard(function(){ closeCard(); });
    } else {
        closeCard();
    }
}

function saveCard(done) {
    var btn = cardForm.querySelector('.btn-primary');
    var label = btn.textContent;
    btn.disabled = true; btn.textContent = 'Сохранение…';
    var fd = new FormData(cardForm);
    fd.append('ajax', '1');
    fetch('', {method: 'POST', body: fd})
        .then(function(r){ return r.json(); })
        .then(function(res){
            if (res && res.ok && res.card) {
                var id = res.card.id;
                CARDS[id] = res.card;
                var cid = document.getElementById('f_id').value;
                if (cid === '') document.getElementById('f_id').value = id;
                setDirty(false);
                var pv = document.getElementById('f_photo_preview');
                var rp = document.getElementById('f_remove_photo');
                if (res.card.photo) {
                    pv.src = 'photos/' + encodeURIComponent(res.card.photo) + '?v=' + Date.now();
                    rp.style.display = '';
                    rp.dataset.photo = res.card.photo;
                } else {
                    rp.style.display = 'none';
                    rp.dataset.photo = '';
                }
                showToast('Сохранено');
            } else {
                alert('Не удалось сохранить: ' + (res && res.error ? res.error : 'сервер вернул пустой ответ'));
            }
        })
        .catch(function(e){ alert('Ошибка сохранения: ' + e); })
        .finally(function(){
            btn.disabled = false; btn.textContent = label;
            if (done) done();
        });
}

cardForm.addEventListener('submit', function(e){
    e.preventDefault();
    saveCard(function(){ closeCard(); });
});

function closeCard() {
    document.getElementById('cardModal').classList.remove('show');
}

function showToast(msg) {
    var toast = document.getElementById('toast');
    toast.textContent = msg;
    toast.classList.add('show');
    setTimeout(function(){ toast.classList.remove('show'); }, 1800);
}

document.querySelectorAll('#cardModal .modal-content').forEach(function(el){
    el.addEventListener('click', function(e){ e.stopPropagation(); });
});
document.getElementById('cardModal').addEventListener('click', function(){
    if (!cardDirty || confirm('В карточке есть несохранённые изменения. Закрыть без сохранения?')) {
        closeCard();
    }
});

// удаление фото — AJAX
var removePhotoBtn = document.getElementById('f_remove_photo');
if (removePhotoBtn) removePhotoBtn.addEventListener('click', function(){
    var pid = document.getElementById('f_id').value;
    var photo = removePhotoBtn.dataset.photo || '';
    if (!pid || !photo) return;
    if (!confirm('Удалить фото?')) return;
    var fd = new FormData();
    fd.append('remove_photo', photo);
    fd.append('card_id', pid);
    fd.append('ajax', '1');
    fetch('', {method: 'POST', body: fd})
        .then(function(r){ return r.json(); })
        .then(function(res){
            if (res && res.ok) {
                var pv = document.getElementById('f_photo_preview');
                pv.src = '';
                pv.style.display = 'none';
                removePhotoBtn.style.display = 'none';
                removePhotoBtn.dataset.photo = '';
                var c = CARDS[pid];
                if (c) { c.photo = ''; CARDS[pid] = c; }
                showToast('Сохранено');
            } else {
                alert('Не удалось удалить фото');
            }
        })
        .catch(function(e){ alert('Ошибка: ' + e); });
});

var sm = document.getElementById('settingsModal');
document.getElementById('settingsBtn').addEventListener('click', function(){ sm.classList.add('show'); });
document.getElementById('closeSettingsBtn').addEventListener('click', function(){ sm.classList.remove('show'); });
sm.addEventListener('click', function(e){ if (e.target === sm) sm.classList.remove('show'); });

// живой поиск
var qInput = document.getElementById('qInput');
var searchTimer = null;
qInput.addEventListener('input', function(){
    clearTimeout(searchTimer);
    searchTimer = setTimeout(function(){
        document.getElementById('searchForm').submit();
    }, 600);
});

// фильтр по дате — автосабмит
var pdateSelect = document.getElementById('pdateSelect');
if (pdateSelect) pdateSelect.addEventListener('change', function(){ document.getElementById('filterForm').submit(); });
var prioSelect = document.getElementById('prioSelect');
if (prioSelect) prioSelect.addEventListener('change', function(){ document.getElementById('filterForm').submit(); });

// тост
var toastType = '<?php echo $toastType; ?>';
if (toastType !== '') {
    var msgs = {save: 'Сохранено', delete: 'Удалено', pass: 'Пароль обновлён'};
    var toast = document.getElementById('toast');
    toast.textContent = msgs[toastType] || 'Готово';
    toast.classList.add('show');
    setTimeout(function(){ toast.classList.remove('show'); }, 1800);
}
</script>
</body>
</html>