<?php
declare(strict_types=1);
require __DIR__ . '/db.php';
$pdo = db();
init_db($pdo);

$stats = [
    'users' => (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn(),
    'verified' => (int)$pdo->query('SELECT COUNT(*) FROM users WHERE verified=1')->fetchColumn(),
    'cargoes' => (int)$pdo->query("SELECT COUNT(*) FROM cargoes WHERE status='active'")->fetchColumn(),
    'vehicles' => (int)$pdo->query('SELECT COUNT(*) FROM vehicles')->fetchColumn(),
];
$users = $pdo->query('SELECT id, role, name, inn, verified, status, rating FROM users ORDER BY id')->fetchAll();
$audit = $pdo->query('SELECT * FROM audit_log ORDER BY id DESC LIMIT 20')->fetchAll();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Админка · Транспортная биржа</title>
    <link rel="stylesheet" href="assets/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>
<body>
<header class="topbar">
    <div class="logo">⚙️ Админка <span>Демо</span></div>
    <button class="ghost" onclick="window.open('index.php','_blank')">← На главную</button>
</header>

<div style="padding:24px; display:grid; grid-template-columns:repeat(auto-fit, minmax(180px,1fr)); gap:16px;">
    <div class="card"><h3>Пользователи</h3><p style="font-size:28px;font-weight:700;"><?php echo $stats['users']; ?></p></div>
    <div class="card"><h3>Верифицировано</h3><p style="font-size:28px;font-weight:700;color:#10b981;"><?php echo $stats['verified']; ?></p></div>
    <div class="card"><h3>Активные грузы</h3><p style="font-size:28px;font-weight:700;color:#f59e0b;"><?php echo $stats['cargoes']; ?></p></div>
    <div class="card"><h3>Транспорт</h3><p style="font-size:28px;font-weight:700;color:#60a5fa;"><?php echo $stats['vehicles']; ?></p></div>
</div>

<div style="padding:24px; display:grid; grid-template-columns:1fr 1fr; gap:24px;">
    <div class="card">
        <h3>Пользователи</h3>
        <table style="width:100%; border-collapse:collapse; margin-top:12px;">
            <tr style="color:#94a3b8; font-size:13px;">
                <th style="text-align:left; padding:8px;">ID</th>
                <th style="text-align:left;">Роль</th>
                <th style="text-align:left;">Имя</th>
                <th style="text-align:left;">ИНН</th>
                <th style="text-align:left;">Вериф.</th>
                <th style="text-align:left;">Рейтинг</th>
            </tr>
            <?php foreach ($users as $u): ?>
                <tr style="border-top:1px solid #334155;">
                    <td style="padding:8px;"><?php echo (int)$u['id']; ?></td>
                    <td><?php echo htmlspecialchars($u['role']); ?></td>
                    <td><?php echo htmlspecialchars($u['name']); ?></td>
                    <td><?php echo htmlspecialchars($u['inn'] ?: '—'); ?></td>
                    <td><?php echo $u['verified'] ? '✓' : '✗'; ?></td>
                    <td><span class="rating">★ <?php echo $u['rating']; ?></span></td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>

    <div class="card">
        <h3>Журнал аудита</h3>
        <div style="max-height:400px; overflow-y:auto; margin-top:12px;">
            <?php foreach ($audit as $a): ?>
                <div style="padding:8px 0; border-bottom:1px solid #334155; font-size:13px; color:#94a3b8;">
                    <b style="color:#e2e8f0;"><?php echo htmlspecialchars($a['event']); ?></b><br>
                    <?php echo htmlspecialchars($a['details']); ?>
                    <div style="font-size:11px; color:#64748b;"><?php echo htmlspecialchars($a['created_at']); ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
</body>
</html>