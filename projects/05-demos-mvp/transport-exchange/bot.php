<?php
declare(strict_types=1);
require __DIR__ . '/db.php';
$cfg = require __DIR__ . '/config.php';
$pdo = db();
init_db($pdo);

function send_telegram_message($chat_id, string $text): void
{
    $cfg = require __DIR__ . '/config.php';
    if (empty($cfg['telegram_bot_token']) || $cfg['telegram_bot_token'] === 'PASTE_BOT_TOKEN_OR_EMPTY') {
        return;
    }
    $url = 'https://api.telegram.org/bot' . $cfg['telegram_bot_token'] . '/sendMessage';
    file_get_contents($url . '?' . http_build_query(['chat_id' => $chat_id, 'text' => $text]));
}

$isTest = isset($_GET['test']);
if ($isTest || $_SERVER['REQUEST_METHOD'] === 'GET') {
    header('Content-Type: text/html; charset=utf-8');
    $cargoes = $pdo->query("SELECT c.*, u.name AS owner FROM cargoes c JOIN users u ON u.id=c.user_id WHERE c.status='active' LIMIT 5")->fetchAll();
    $vehicles = $pdo->query("SELECT v.*, u.name AS owner FROM vehicles v JOIN users u ON u.id=v.user_id LIMIT 5")->fetchAll();
    ?>
    <!DOCTYPE html>
    <html lang="ru">
    <head>
        <meta charset="UTF-8">
        <title>Telegram-бот · Демо</title>
        <link rel="stylesheet" href="assets/style.css">
    </head>
    <body>
    <header class="topbar">
        <div class="logo">🤖 Telegram-бот <span>Демо</span></div>
        <button class="ghost" onclick="window.open('index.php','_blank')">← На главную</button>
    </header>
    <div style="padding:24px; max-width:700px;">
        <h2>Симуляция уведомлений</h2>
        <p style="color:#94a3b8; margin:12px 0 20px;">Так будут выглядеть сообщения бота после привязки аккаунта.</p>

        <h3>Перевозчику — новые грузы</h3>
        <?php foreach ($cargoes as $c): ?>
            <div class="card" style="margin-bottom:12px;">
                <b>🚛 Новый груз</b><br>
                <span><?php echo htmlspecialchars($c['title']); ?></span><br>
                <span style="color:#94a3b8;">Кузов: <?php echo htmlspecialchars($c['body_types']); ?> · <?php echo $c['weight_t']; ?> т · <?php echo $c['volume_m3']; ?> м³</span><br>
                <span style="color:#10b981;">Ставка: <?php echo (int)$c['rate']; ?> ₽</span><br>
                <span style="color:#94a3b8;"><?php echo htmlspecialchars($c['load_address']); ?> → <?php echo htmlspecialchars($c['unload_address']); ?></span><br>
                <a href="index.php">Открыть карточку</a>
            </div>
        <?php endforeach; ?>

        <h3 style="margin-top:24px;">Грузовладельцу — транспорт</h3>
        <?php foreach ($vehicles as $v): ?>
            <div class="card" style="margin-bottom:12px;">
                <b>🚚 Доступный транспорт</b><br>
                <span><?php echo htmlspecialchars($v['plate']); ?> · <?php echo htmlspecialchars($v['body_type']); ?></span><br>
                <span style="color:#94a3b8;">Грузоподъёмность: <?php echo $v['capacity_t']; ?> т · <?php echo $v['volume_m3']; ?> м³</span><br>
                <span style="color:#10b981;">Себестоимость: <?php echo (int)$v['cost_per_km']; ?> ₽/км</span><br>
                <a href="index.php">Открыть карточку</a>
            </div>
        <?php endforeach; ?>
    </div>
    </body>
    </html>
    <?php
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    echo json_encode(['ok' => false]);
    exit;
}

$message = $input['message'] ?? null;
if ($message) {
    $chatId = $message['chat']['id'] ?? null;
    $text = trim($message['text'] ?? '');

    if (mb_strpos($text, '/start') === 0) {
        send_telegram_message($chatId, 'Добро пожаловать в демо транспортной биржи! Отправьте ИНН для привязки аккаунта.');
    } elseif (preg_match('/^\d{10,12}$/', $text)) {
        $stmt = $pdo->prepare('SELECT id, name, verified FROM users WHERE inn = ?');
        $stmt->execute([$text]);
        $user = $stmt->fetch();
        if ($user) {
            $pdo->prepare('UPDATE users SET telegram_chat_id = ? WHERE id = ?')->execute([$chatId, $user['id']]);
            send_telegram_message($chatId, "Аккаунт «{$user['name']}» привязан. Статус: " . ($user['verified'] ? 'верифицирован' : 'не верифицирован'));
        } else {
            send_telegram_message($chatId, 'ИНН не найден. Попробуйте ещё раз.');
        }
    } else {
        send_telegram_message($chatId, 'Демо-бот: отправьте ИНН или /start');
    }
}

echo json_encode(['ok' => true]);