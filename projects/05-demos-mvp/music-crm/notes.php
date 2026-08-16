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
    header('Location: index.php');
    exit;
}

if (!$isLoggedIn && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'], $_POST['password'])) {
    if ($_POST['login'] === 'admin' && password_verify($_POST['password'], file_get_contents($hashFile))) {
        $_SESSION['muzyka_admin'] = true;
        session_regenerate_id(true);
        header('Location: index.php');
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
        <title>Накопитель — вход</title>
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
            <h2>Накопитель заметок</h2>
            <div class="login-sub">CRM музыкантов — демо-версия</div>
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
if (!is_dir($dir)) mkdir($dir, 0755, true);
$notesFile = $dir . '/notes.json';

function loadJson($file, $default) {
    if (!file_exists($file)) {
        file_put_contents($file, json_encode($default, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
    $data = json_decode(file_get_contents($file), true);
    return is_array($data) ? $data : $default;
}
function saveNotes($notes) {
    global $notesFile;
    file_put_contents($notesFile, json_encode(['notes' => $notes], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}
$store = loadJson($notesFile, ['notes' => []]);
$notes = $store['notes'];

// ========== POST-ДЕЙСТВИЯ ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Сохранение заметки (новая или редактирование)
    if (isset($_POST['save_note']) && isset($_POST['text'])) {
        $text = trim($_POST['text']);
        $id = isset($_POST['note_id']) ? trim($_POST['note_id']) : '';
        $now = date('Y-m-d H:i');
        if ($text !== '') {
            $found = false;
            foreach ($notes as $i => $n) {
                if (($n['id'] ?? '') === $id) {
                    $notes[$i]['text'] = $text;
                    $notes[$i]['updated_at'] = $now;
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $notes[] = ['id' => 'n_' . uniqid(), 'text' => $text, 'created_at' => $now, 'updated_at' => $now, 'processed' => false];
            }
            saveNotes($notes);
        }
        header('Location: notes.php?toast=save');
        exit;
    }

    // Удаление заметки
    if (isset($_POST['delete_note']) && isset($_POST['note_id'])) {
        $delId = $_POST['note_id'];
        foreach ($notes as $i => $n) {
            if (($n['id'] ?? '') === $delId) { array_splice($notes, $i, 1); break; }
        }
        saveNotes($notes);
        header('Location: notes.php?toast=delete');
        exit;
    }

    // Отметить обработанной / снять отметку
    if (isset($_POST['toggle_note']) && isset($_POST['note_id'])) {
        $tId = $_POST['note_id'];
        foreach ($notes as $i => $n) {
            if (($n['id'] ?? '') === $tId) { $notes[$i]['processed'] = !($n['processed'] ?? false); break; }
        }
        saveNotes($notes);
        header('Location: notes.php');
        exit;
    }

    // Удалить все обработанные заметки
    if (isset($_POST['delete_processed'])) {
        $kept = [];
        foreach ($notes as $n) {
            if (!($n['processed'] ?? false)) $kept[] = $n;
        }
        $removed = count($notes) - count($kept);
        saveNotes($kept);
        header('Location: notes.php?toast=processed&n=' . $removed);
        exit;
    }
}

// Показывать ли только обработанные?
$show = isset($_GET['show']) ? $_GET['show'] : 'all';

$displayNotes = $notes;
if ($show === 'processed') {
    $displayNotes = array_values(array_filter($notes, function($n){ return $n['processed'] ?? false; }));
}
if ($show === 'open') {
    $displayNotes = array_values(array_filter($notes, function($n){ return !($n['processed'] ?? false); }));
}
$displayNotes = array_reverse($displayNotes);

$processedCount = count(array_filter($notes, function($n){ return $n['processed'] ?? false; }));
$openCount = count($notes) - $processedCount;

$toastType = isset($_GET['toast']) ? $_GET['toast'] : '';
$toastN = isset($_GET['n']) ? (int)$_GET['n'] : 0;
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <meta name="robots" content="noindex, nofollow">
    <title>Накопитель заметок</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons+Outlined">
    <style>
        *{box-sizing:border-box}
        body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;background:#f5f5f7;margin:0;padding:20px;color:#1c1c1e}
        .container{max-width:860px;margin:0 auto;background:#fff;border-radius:28px;box-shadow:0 2px 12px rgba(0,0,0,0.05);padding:24px 20px 32px}
        .topbar{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:20px}
        .topbar h1{font-size:22px;font-weight:600;margin:0;display:flex;align-items:center;gap:10px}
        .topbar h1 .material-icons-outlined{color:#ff9f00}
        .nav-tabs{display:flex;gap:6px;flex-wrap:wrap}
        .nav-tab{display:inline-flex;align-items:center;gap:6px;background:#DCDCDC;border-radius:12px;padding:8px 14px;font-size:14px;font-weight:500;cursor:pointer;text-decoration:none;color:#000;transition:0.2s}
        .nav-tab.active{background:#fff;color:#ff9f00;box-shadow:inset 0 0 0 2px #ff9f00}
        .nav-tab:hover{background:#c0c0c0}
        textarea{width:100%;padding:14px;font-size:16px;border:1px solid #c6c6c8;border-radius:14px;resize:vertical;margin-bottom:10px}
        textarea:focus{outline:none;border-color:#ff9f00;box-shadow:0 0 0 3px rgba(255,159,0,0.1)}
        .save-row{display:flex;gap:10px;margin-bottom:22px;align-items:center;flex-wrap:wrap}
        .btn-primary{background:#ff9f00;color:#fff;border:none;border-radius:40px;padding:12px 24px;font-size:15px;font-weight:600;cursor:pointer;flex:1;min-width:160px}
        .btn-primary:hover{background:#e68a00}
        .hint{color:#8e8e93;font-size:13px;display:flex;align-items:center;gap:6px}
        .filter-tabs{display:flex;gap:6px;flex-wrap:wrap;margin-bottom:16px}
        .filter-tab{display:inline-flex;align-items:center;gap:5px;background:#f1f1f4;border-radius:30px;padding:6px 14px;font-size:13px;cursor:pointer;text-decoration:none;color:#4a4a4c}
        .filter-tab.active{background:#fff6e5;color:#ff9f00;box-shadow:inset 0 0 0 2px #ff9f00}
        .card{position:relative;background:#f9f9fb;border-radius:20px;padding:16px 16px 14px;border:1px solid #e1e1e6;margin-bottom:14px;cursor:pointer;transition:0.2s}
        .card:hover{border-color:#ff9f00}
        .card.processed{opacity:.6;border-style:dashed}
        .card-top{display:flex;align-items:flex-start;justify-content:space-between;gap:8px}
        .card-date{font-size:12px;color:#8e8e93;display:flex;align-items:center;gap:5px}
        .card-date .material-icons-outlined{font-size:14px}
        .card-text{font-size:15px;line-height:1.5;word-wrap:break-word;white-space:pre-wrap;margin-top:8px}
        .card-actions{display:flex;gap:2px;align-items:center}
        .icon-btn{background:transparent;border:none;color:#8e8e93;cursor:pointer;font-size:21px;padding:4px;border-radius:12px;transition:0.2s;line-height:1}
        .icon-btn:hover{color:#ff9f00;background:#e0e0e0}
        .icon-btn.danger:hover{color:#ff3b30}
        .icon-btn.done:hover{color:#1f6b24}
        .icon-btn.done.active{color:#1f6b24}
        .empty-message{color:#8e8e93;text-align:center;padding:40px 20px}
        .empty-message .material-icons-outlined{font-size:40px;color:#d0d0d5;display:block;margin-bottom:8px}
        .processed-bar{margin-top:22px;border-top:1px solid #e9e9ef;padding-top:16px;display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap}
        .logout-bottom{margin-top:22px;text-align:center}
        .logout-bottom a{background:#fff;color:#ff9f00;border:2px solid #ff9f00;padding:10px 24px;border-radius:40px;text-decoration:none;font-weight:600;display:inline-flex;align-items:center;gap:8px}
        .modal{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:2000;align-items:flex-start;justify-content:center;overflow-y:auto;padding:20px 12px}
        .modal.show{display:flex}
        .modal-content{background:#fff;border-radius:28px;max-width:640px;width:100%;padding:24px;box-shadow:0 8px 28px rgba(0,0,0,0.2);margin:auto}
        .modal-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:14px}
        .modal-header h3{margin:0;font-weight:600;font-size:18px}
        .close-modal{background:none;border:none;font-size:28px;cursor:pointer;color:#8e8e93;line-height:1}
        .form-actions{display:flex;gap:10px;margin-top:14px}
        .btn-cancel{flex:1;background:#fff;color:#8e8e93;border:2px solid #c6c6c8;border-radius:40px;padding:12px;font-size:15px;font-weight:600;cursor:pointer}
        .btn-cancel:hover{background:#f2f2f7}
        .toast{position:fixed;bottom:30px;left:50%;transform:translateX(-50%);background:#1c1c1e;color:#fff;padding:10px 24px;border-radius:40px;font-size:14px;z-index:1001;opacity:0;transition:opacity 0.2s;pointer-events:none;display:flex;gap:8px;white-space:nowrap;box-shadow:0 4px 12px rgba(0,0,0,0.15)}
        .toast.show{opacity:1}
        .fade-in{animation:fadeIn .25s ease}
        @keyframes fadeIn{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:none}}
        @media (max-width:600px){
            body{padding:12px}
            .container{padding:20px 14px}
            .topbar{flex-direction:column;align-items:stretch}
            .toast{white-space:normal;max-width:80vw;text-align:center}
        }
    </style>
</head>
<body>
<div class="container">
    <div class="topbar">
        <h1><i class="material-icons-outlined">sticky_note_2</i> Накопитель заметок</h1>
        <div class="nav-tabs">
            <a class="nav-tab" href="index.php"><i class="material-icons-outlined" style="font-size:18px">people</i> Музыканты</a>
            <a class="nav-tab active" href="notes.php"><i class="material-icons-outlined" style="font-size:18px">edit_note</i> Накопитель</a>
        </div>
    </div>

    <form method="post">
        <input type="hidden" name="save_note" value="1">
        <textarea name="text" rows="5" placeholder="Голосом/текстом: с кем переговорил, о чём договорились, какие даты, кто поучаствует…"></textarea>
        <div class="save-row">
            <button type="submit" class="btn-primary"><i class="material-icons-outlined" style="font-size:18px;vertical-align:-3px">save</i> Сохранить текст</button>
            <span class="hint"><i class="material-icons-outlined" style="font-size:16px">info</i> Заметки разберёт и занесёт в карточки CRM ИИ-модель (LLM, искусственный интеллект)</span>
        </div>
    </form>

    <div class="filter-tabs">
        <a class="filter-tab <?php echo $show === 'all' ? 'active' : ''; ?>" href="notes.php?show=all">Все (<?php echo count($notes); ?>)</a>
        <a class="filter-tab <?php echo $show === 'open' ? 'active' : ''; ?>" href="notes.php?show=open">К разбору (<?php echo $openCount; ?>)</a>
        <a class="filter-tab <?php echo $show === 'processed' ? 'active' : ''; ?>" href="notes.php?show=processed">Обработано (<?php echo $processedCount; ?>)</a>
    </div>

    <?php if (empty($displayNotes)): ?>
        <div class="empty-message">
            <i class="material-icons-outlined">inbox</i>
            Пока пусто. Надиктуйте заметку выше и нажмите «Сохранить текст».
        </div>
    <?php else: ?>
        <?php foreach ($displayNotes as $n): ?>
            <div class="card <?php echo ($n['processed'] ?? false) ? 'processed' : ''; ?> fade-in" onclick="openNote('<?php echo htmlspecialchars($n['id']); ?>')">
                <div class="card-top">
                    <div class="card-date">
                        <i class="material-icons-outlined">schedule</i>
                        создано <?php echo htmlspecialchars($n['created_at'] ?? ''); ?>
                        <?php if (($n['updated_at'] ?? '') !== ($n['created_at'] ?? '')): ?>
                             · изм. <?php echo htmlspecialchars($n['updated_at'] ?? ''); ?>
                        <?php endif; ?>
                    </div>
                    <div class="card-actions">
                        <form method="post" style="display:inline" onsubmit="event.stopPropagation();">
                            <input type="hidden" name="toggle_note" value="1">
                            <input type="hidden" name="note_id" value="<?php echo htmlspecialchars($n['id']); ?>">
                            <button class="icon-btn done <?php echo ($n['processed'] ?? false) ? 'active' : ''; ?>" title="Обработано / снять" onclick="event.stopPropagation();">
                                <i class="material-icons-outlined"><?php echo ($n['processed'] ?? false) ? 'check_circle' : 'radio_button_unchecked'; ?></i>
                            </button>
                        </form>
                        <button class="icon-btn" title="Редактировать" onclick="event.stopPropagation(); openNote('<?php echo htmlspecialchars($n['id']); ?>')"><i class="material-icons-outlined">edit</i></button>
                        <form method="post" style="display:inline" onsubmit="event.stopPropagation(); return confirm('Удалить заметку?');">
                            <input type="hidden" name="delete_note" value="1">
                            <input type="hidden" name="note_id" value="<?php echo htmlspecialchars($n['id']); ?>">
                            <button class="icon-btn danger" title="Удалить" onclick="event.stopPropagation();"><i class="material-icons-outlined">delete</i></button>
                        </form>
                    </div>
                </div>
                <div class="card-text"><?php echo htmlspecialchars($n['text'] ?? ''); ?></div>
            </div>
        <?php endforeach; ?>

        <?php if ($processedCount > 0): ?>
        <div class="processed-bar">
            <span style="font-size:13px;color:#6c6c6c;">Отмечено как обработанное: <?php echo $processedCount; ?></span>
            <form method="post" style="display:inline" onsubmit="return confirm('Удалить все обработанные заметки? (ИИ-модель разобрала их в CRM)');">
                <input type="hidden" name="delete_processed" value="1">
                <button type="submit" class="btn-ghost" style="background:#fff;color:#ff3b30;border:2px solid #ff3b30;border-radius:40px;padding:8px 18px;font-size:14px;font-weight:600;cursor:pointer;">Удалить обработанные</button>
            </form>
        </div>
        <?php endif; ?>
    <?php endif; ?>

    <div class="logout-bottom">
        <a href="notes.php?logout=1"><i class="material-icons-outlined" style="font-size:18px">logout</i> Выйти</a>
    </div>
</div>

<!-- Модальное окно редактирования заметки -->
<div class="modal" id="noteModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Редактирование заметки</h3>
            <button class="close-modal" onclick="closeNote()">&times;</button>
        </div>
        <form method="post">
            <input type="hidden" name="save_note" value="1">
            <input type="hidden" name="note_id" id="n_id" value="">
            <textarea name="text" id="n_text" rows="8"></textarea>
            <div class="dates-info" id="n_dates" style="font-size:12px;color:#8e8e93;margin-bottom:8px"></div>
            <div class="form-actions">
                <button type="button" class="btn-cancel" onclick="closeNote()">Отмена</button>
                <button type="submit" class="btn-primary">Сохранить</button>
            </div>
        </form>
    </div>
</div>

<div class="toast" id="toast"></div>

<script>
var NOTES = <?php
    $map = [];
    foreach ($notes as $n) { $map[$n['id']] = $n; }
    echo json_encode($map, JSON_UNESCAPED_UNICODE);
?>;

function openNote(id) {
    var n = NOTES[id];
    var modal = document.getElementById('noteModal');
    document.getElementById('n_id').value = n ? n.id : '';
    document.getElementById('n_text').value = n ? (n.text || '') : '';
    var di = document.getElementById('n_dates');
    di.innerHTML = n ? ('Создано: ' + (n.created_at || '—') + ' · Изменено: ' + (n.updated_at || '—')
        + (n.processed ? ' · <span style="color:#1f6b24">обработано</span>' : '')) : '';
    modal.classList.add('show');
}
function closeNote() {
    document.getElementById('noteModal').classList.remove('show');
}
document.getElementById('noteModal').addEventListener('click', function(e){ if (e.target === this) closeNote(); });

var toastType = '<?php echo $toastType; ?>';
if (toastType !== '') {
    var msgs = {save: 'Замётка сохранена', delete: 'Удалено', processed: 'Удалено обработанных: <?php echo $toastN; ?>'};
    var toast = document.getElementById('toast');
    toast.textContent = msgs[toastType] || 'Готово';
    toast.classList.add('show');
    setTimeout(function(){ toast.classList.remove('show'); }, 2200);
}
</script>
</body>
</html>