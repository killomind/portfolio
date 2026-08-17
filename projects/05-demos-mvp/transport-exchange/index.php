<?php
declare(strict_types=1);
require __DIR__ . '/db.php';
$pdo = db();
init_db($pdo);

$vehicles = $pdo->query('SELECT id, user_id, body_type, plate, capacity_t, volume_m3, base_address, base_lat, base_lng, route_json, corridor_km FROM vehicles')->fetchAll();
$cargoes = $pdo->query("SELECT id, user_id, title, body_types, load_address, load_lat, load_lng, unload_address, unload_lat, unload_lng FROM cargoes WHERE status = 'active'")->fetchAll();
$bodyTypes = ['тент', 'рефрижератор', 'изотерм', 'фургон', 'борт', 'контейнер'];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Транспортная биржа · Демо</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<header class="topbar">
    <div class="logo">🚚 Транспортная биржа <span>Демо</span></div>
    <div class="top-actions">
        <button class="ghost" onclick="window.open('bot.php?test=1','_blank')">🤖 Telegram-бот</button>
        <button class="ghost" onclick="window.open('admin.php','_blank')">⚙️ Админка</button>
    </div>
</header>

<div class="layout">
    <aside class="filters">
        <h3>Фильтры</h3>

        <label>Режим</label>
        <div class="toggle">
            <button data-role="carrier" class="active">Перевозчик</button>
            <button data-role="cargo_owner">Грузовладелец</button>
        </div>

        <div id="carrierFilters">
            <label>Транспорт</label>
            <select id="vehicleSelect">
                <?php foreach ($vehicles as $v): ?>
                    <option value="<?php echo (int)$v['id']; ?>">
                        <?php echo htmlspecialchars($v['plate'] . ' · ' . $v['body_type'] . ' · ' . $v['capacity_t'] . ' т'); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label>Тип кузова</label>
            <select id="bodyType">
                <option value="">Все</option>
                <?php foreach ($bodyTypes as $bt): ?>
                    <option value="<?php echo htmlspecialchars($bt); ?>"><?php echo htmlspecialchars($bt); ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div id="cargoOwnerFilters" style="display:none">
            <label>Груз</label>
            <select id="cargoSelect">
                <?php foreach ($cargoes as $c): ?>
                    <option value="<?php echo (int)$c['id']; ?>">
                        <?php echo htmlspecialchars($c['title'] . ' · ' . $c['body_types']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <label>Радиус, км</label>
        <input id="radius" type="number" value="500" min="1" max="5000">

        <label>Сортировка</label>
        <select id="sort">
            <option value="profit">По выгодности</option>
            <option value="distance">По расстоянию</option>
            <option value="rate">По ставке</option>
        </select>

        <label class="checkbox">
            <input id="useRoute" type="checkbox"> Учитывать коридор маршрута
        </label>

        <button id="searchBtn" class="primary">Показать</button>
    </aside>

    <main class="content">
        <div id="map"></div>
        <div id="results" class="results"></div>
    </main>
</div>

<script>
window.DEMO = {
    vehicles: <?php echo json_encode($vehicles, JSON_UNESCAPED_UNICODE); ?>,
    cargoes: <?php echo json_encode($cargoes, JSON_UNESCAPED_UNICODE); ?>
};
</script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="assets/app.js"></script>
</body>
</html>