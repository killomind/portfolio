<?php
declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $cfg = require __DIR__ . '/config.php';
        $pdo = new PDO('sqlite:' . $cfg['db_path']);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('PRAGMA foreign_keys = ON');
    }
    return $pdo;
}

function init_db(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        role TEXT NOT NULL CHECK(role IN ('carrier','cargo_owner','admin')),
        name TEXT NOT NULL,
        inn TEXT UNIQUE,
        phone TEXT UNIQUE,
        email TEXT,
        legal_type TEXT CHECK(legal_type IN ('legal','individual','ip')),
        verified INTEGER DEFAULT 0,
        status TEXT DEFAULT 'not_verified',
        rating REAL DEFAULT 0,
        telegram_chat_id TEXT,
        created_at TEXT DEFAULT (datetime('now','localtime'))
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS vehicles (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        body_type TEXT NOT NULL,
        plate TEXT,
        capacity_t REAL,
        volume_m3 REAL,
        loading_methods TEXT DEFAULT 'зад',
        base_address TEXT,
        base_lat REAL,
        base_lng REAL,
        cost_per_km REAL,
        home_mode INTEGER DEFAULT 0,
        desired_directions TEXT,
        unwanted_directions TEXT,
        route_json TEXT,
        corridor_km REAL DEFAULT 0,
        created_at TEXT DEFAULT (datetime('now','localtime')),
        FOREIGN KEY(user_id) REFERENCES users(id)
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS cargoes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        title TEXT,
        weight_t REAL,
        volume_m3 REAL,
        body_types TEXT DEFAULT '',
        load_address TEXT,
        load_lat REAL,
        load_lng REAL,
        unload_address TEXT,
        unload_lat REAL,
        unload_lng REAL,
        loading_methods TEXT DEFAULT 'любой',
        rate REAL,
        date TEXT,
        status TEXT DEFAULT 'active',
        created_at TEXT DEFAULT (datetime('now','localtime')),
        FOREIGN KEY(user_id) REFERENCES users(id)
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS responses (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        cargo_id INTEGER,
        vehicle_id INTEGER,
        from_user_id INTEGER,
        to_user_id INTEGER,
        type TEXT CHECK(type IN ('vehicle_to_cargo','cargo_to_vehicle')),
        status TEXT DEFAULT 'pending',
        created_at TEXT DEFAULT (datetime('now','localtime'))
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS ratings (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        from_user_id INTEGER,
        to_user_id INTEGER,
        score INTEGER,
        comment TEXT,
        created_at TEXT DEFAULT (datetime('now','localtime'))
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS audit_log (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        event TEXT,
        details TEXT,
        created_at TEXT DEFAULT (datetime('now','localtime'))
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS telegram_notifications (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        entity_type TEXT,
        entity_id INTEGER,
        unique_hash TEXT UNIQUE,
        sent_at TEXT,
        telegram_message_id INTEGER,
        created_at TEXT DEFAULT (datetime('now','localtime'))
    )");

    $count = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    if ($count === 0) {
        seed($pdo);
    }
}

function seed(PDO $pdo): void
{
    $pdo->exec('BEGIN');
    try {
        $stmt = $pdo->prepare('INSERT INTO users (id, role, name, inn, phone, email, legal_type, verified, status, rating) VALUES (?,?,?,?,?,?,?,?,?,?)');
        $users = [
            [1, 'carrier', 'ИП Иванов Иван Иванович', '7701234567', '+79031234567', 'ivanov@mail.ru', 'ip', 1, 'verified', 78],
            [2, 'carrier', 'ООО «ТрансЛогистик»', '7707654321', '+78121234567', 'info@translog.ru', 'legal', 1, 'verified', 85],
            [3, 'carrier', 'ООО «ХолодТранс»', '7701234568', '+79161234567', 'holod@trans.ru', 'legal', 1, 'verified', 91],
            [4, 'cargo_owner', 'ООО «ТоргСервис»', '7712345678', '+74951234567', 'sales@torg.ru', 'legal', 1, 'verified', 72],
            [5, 'cargo_owner', 'ИП Смирнов Алексей', '7712345679', '+79261234567', 'smirnov@mail.ru', 'ip', 0, 'not_verified', 20],
            [6, 'admin', 'Администратор системы', null, null, 'admin@demo.ru', 'legal', 1, 'verified', 100],
        ];
        foreach ($users as $u) {
            $stmt->execute($u);
        }

        $vstmt = $pdo->prepare('INSERT INTO vehicles (id, user_id, body_type, plate, capacity_t, volume_m3, loading_methods, base_address, base_lat, base_lng, cost_per_km, home_mode, desired_directions, unwanted_directions, route_json, corridor_km) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $vehicles = [
            [1, 1, 'тент', 'А123ВС777', 1.5, 9, 'зад', 'Москва, ул. Складочная, 1', 55.7558, 37.6173, 22, 1, 'Москва→Казань', '', '[{"lat":55.7558,"lng":37.6173},{"lat":56.3269,"lng":44.0059},{"lat":55.7963,"lng":49.1088}]', 50],
            [2, 2, 'тент', 'В456КХ178', 10, 40, 'зад,бок', 'Санкт-Петербург, Пискарёвский пр., 100', 59.9343, 30.3351, 35, 1, 'СПб→Москва', '', null, 0],
            [3, 2, 'рефрижератор', 'Е789КУ178', 20, 82, 'зад', 'Санкт-Петербург, Софийская ул., 4', 59.8877, 30.3350, 50, 0, null, null, null, 0],
            [4, 3, 'рефрижератор', 'А111АА116', 20, 82, 'зад,бок', 'Казань, ул. Деловая, 5', 55.7963, 49.1088, 55, 0, null, null, null, 0],
            [5, 3, 'изотерм', 'В222ВВ116', 20, 82, 'верх,зад', 'Екатеринбург, ул. Первомайская, 77', 56.8389, 60.6057, 60, 0, null, null, null, 0],
            [6, 3, 'борт', 'С333СС154', 5, 20, 'зад,бок', 'Новосибирск, ул. Станционная, 30', 55.0302, 82.9204, 40, 0, null, null, null, 0],
            [7, 1, 'фургон', 'М444ММ777', 2.5, 16, 'зад', 'Москва, Ленинградское ш., 50', 55.8500, 37.5000, 25, 0, null, null, null, 0],
            [8, 2, 'контейнер', 'А888АА777', 25, 90, 'зад', 'Москва, Каширское ш., 61', 55.6086, 37.5645, 50, 0, null, null, null, 0],
            [9, 3, 'борт', 'Е555ЕЕ116', 15, 60, 'зад,бок', 'Казань, ул. Аделя Кутуя, 15', 55.7695, 49.1562, 42, 0, null, null, null, 0],
            [10, 3, 'изотерм', 'К666КК116', 20, 82, 'зад', 'Казань, ул. Ершова, 8', 55.7906, 49.1249, 48, 0, null, null, null, 0],
            [11, 1, 'тент', 'О777ОО136', 5, 24, 'зад,верх', 'Воронеж, ул. Дорожная, 12', 51.6826, 39.1449, 28, 0, null, null, null, 0],
            [12, 3, 'фургон', 'Р111РР178', 2, 12, 'зад', 'Санкт-Петербург, ул. Парковая, 3', 59.9568, 30.2832, 25, 0, null, null, null, 0],
            [13, 2, 'рефрижератор', 'С222СС199', 5, 22, 'зад', 'Москва, Волгоградский пр., 20', 55.7194, 37.7218, 35, 0, null, null, null, 0],
            [14, 2, 'тент', 'Т333ТТ199', 20, 82, 'зад,бок', 'Москва, ул. Полярная, 25', 55.8376, 37.6809, 40, 0, null, null, null, 0],
            [15, 1, 'борт', 'У444УУ199', 10, 40, 'зад,бок', 'Москва, 3-я Мытищинская ул., 10', 55.8366, 37.6854, 32, 0, null, null, null, 0],
        ];
        foreach ($vehicles as $v) {
            $vstmt->execute($v);
        }

        $cstmt = $pdo->prepare('INSERT INTO cargoes (id, user_id, title, weight_t, volume_m3, body_types, load_address, load_lat, load_lng, unload_address, unload_lat, unload_lng, loading_methods, rate, date, status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $cargoes = [
            [1, 4, 'Оборудование Москва → СПб', 10, 35, 'тент,рефрижератор', 'Москва, ул. Ленина, 1', 55.7558, 37.6173, 'Санкт-Петербург, Невский пр., 10', 59.9343, 30.3351, 'зад', 45000, '2026-08-25', 'active'],
            [2, 4, 'Бытовая техника Москва → Казань', 8, 30, 'тент', 'Москва, ул. Тверская, 5', 55.7558, 37.6173, 'Казань, ул. Баумана, 20', 55.7963, 49.1088, 'зад', 35000, '2026-08-26', 'active'],
            [3, 4, 'Медикаменты СПб → Екатеринбург', 15, 60, 'рефрижератор', 'Санкт-Петербург, Московский пр., 200', 59.8877, 30.3350, 'Екатеринбург, ул. Малышева, 30', 56.8389, 60.6057, 'зад,бок', 90000, '2026-08-27', 'active'],
            [4, 5, 'Стройматериалы Казань → Новосибирск', 12, 50, 'изотерм,борт', 'Казань, ул. Профсоюзная, 10', 55.7963, 49.1088, 'Новосибирск, ул. Ленина, 50', 55.0302, 82.9204, 'зад,бок', 65000, '2026-08-28', 'active'],
            [5, 4, 'Мебель Москва → Нижний Новгород', 4, 18, 'борт', 'Москва, ул. Складочная, 10', 55.7558, 37.6173, 'Нижний Новгород, ул. Рождественская, 12', 56.3269, 44.0059, 'бок', 12000, '2026-08-29', 'active'],
            [6, 5, 'Продукты Казань → Москва', 6, 25, 'рефрижератор', 'Казань, ул. Деловая, 3', 55.7963, 49.1088, 'Москва, ул. Ленина, 7', 55.7558, 37.6173, 'зад', 28000, '2026-08-30', 'active'],
            [7, 4, 'Курьерский груз Москва → Нижний Новгород', 1, 5, 'тент', 'Москва, Волгоградский пр., 40', 55.7194, 37.7218, 'Нижний Новгород, ул. Белинского, 15', 56.3178, 43.9944, 'зад', 18000, '2026-08-27', 'active'],
            [8, 5, 'Запчасти СПб → Москва', 8, 30, 'тент', 'Санкт-Петербург, пр. Обуховской Обороны, 90', 59.9219, 30.4442, 'Москва, МКАД 75 км', 55.6450, 37.6710, 'зад', 30000, '2026-08-28', 'active'],
            [9, 4, 'Продукты СПб → Москва', 12, 45, 'рефрижератор', 'Санкт-Петербург, Софийская ул., 40', 59.8819, 30.3579, 'Москва, ул. Верхние Поля, 20', 55.6640, 37.8450, 'зад,бок', 52000, '2026-08-29', 'active'],
            [10, 4, 'Стройматериалы Екатеринбург → Тюмень', 10, 40, 'изотерм', 'Екатеринбург, ул. Ткачей, 10', 56.8420, 60.6000, 'Тюмень, ул. Республики, 20', 57.1610, 65.5340, 'зад', 42000, '2026-08-30', 'active'],
            [11, 5, 'Металлоконструкции Новосибирск → Барнаул', 3, 14, 'борт', 'Новосибирск, ул. Станционная, 60', 55.0302, 82.9204, 'Барнаул, пр. Ленина, 30', 53.3480, 83.7800, 'зад,бок', 24000, '2026-08-31', 'active'],
            [12, 4, 'Бытовая техника Москва → Подольск', 1.5, 8, 'фургон', 'Москва, Каширское ш., 61', 55.6086, 37.5645, 'Подольск, ул. Ленина, 5', 55.4310, 37.5450, 'зад', 12000, '2026-08-26', 'active'],
            [13, 4, 'Контейнер Москва → СПб', 20, 80, 'контейнер', 'Москва, Каширское ш., 61', 55.6086, 37.5645, 'Санкт-Петербург, КАД 12 км', 59.9311, 30.3609, 'зад', 68000, '2026-08-28', 'active'],
            [14, 5, 'Стройматериалы Воронеж → Москва', 4, 18, 'тент', 'Воронеж, ул. Дорожная, 12', 51.6826, 39.1449, 'Москва, ул. Дорожная, 3', 55.6050, 37.6400, 'зад', 26000, '2026-08-27', 'active'],
            [15, 5, 'Мебель СПб → Новгород', 1, 6, 'фургон', 'Санкт-Петербург, ул. Парковая, 3', 59.9568, 30.2832, 'Великий Новгород, ул. Большая Московская, 10', 58.5230, 31.2710, 'зад', 15000, '2026-08-29', 'active'],
            [16, 4, 'Медикаменты Москва → Казань', 4, 18, 'рефрижератор', 'Москва, Волгоградский пр., 20', 55.7194, 37.7218, 'Казань, ул. Баумана, 20', 55.7963, 49.1088, 'зад', 40000, '2026-08-30', 'active'],
            [17, 5, 'Бытовая техника НН → Москва', 6, 25, 'тент', 'Нижний Новгород, ул. Рождественская, 12', 56.3269, 44.0059, 'Москва, ул. Полярная, 25', 55.8376, 37.6809, 'зад', 32000, '2026-08-31', 'active'],
            [18, 4, 'Контейнер Москва → Самара', 22, 88, 'контейнер', 'Москва, 3-я Мытищинская ул., 10', 55.8366, 37.6854, 'Самара, ул. Ленина, 7', 53.1959, 50.1063, 'зад', 85000, '2026-08-29', 'active'],
            [19, 5, 'Срочный груз Тула → Москва', 0.7, 3, 'тент', 'Тула, пр. Ленина, 20', 54.1931, 37.6176, 'Москва, ул. Тверская, 5', 55.7558, 37.6173, 'зад', 8000, '2026-08-27', 'active'],
            [20, 4, 'Посылки Ярославль → Москва', 1.2, 5, 'тент', 'Ярославль, ул. Свободы, 10', 57.6261, 39.8845, 'Москва, ул. Ленина, 1', 55.7558, 37.6173, 'зад', 12000, '2026-08-28', 'active'],
            [21, 5, 'Запчасти Рязань → Москва', 1.4, 6, 'тент', 'Рязань, ул. Ленина, 30', 54.6288, 39.6915, 'Москва, МКАД 75 км', 55.6450, 37.6710, 'зад', 11000, '2026-08-29', 'active'],
            [22, 4, 'Оборудование Тверь → Москва', 1.5, 7, 'тент', 'Тверь, пр. Победы, 15', 56.8596, 35.9119, 'Москва, Каширское ш., 61', 55.6086, 37.5645, 'зад', 13000, '2026-08-30', 'active'],
            [23, 5, 'Сборный груз Москва → Владимир', 2, 10, 'фургон', 'Москва, Ленинградское ш., 50', 55.8500, 37.5000, 'Владимир, ул. Большая Московская, 8', 56.1291, 40.4070, 'зад', 14000, '2026-08-28', 'active'],
            [24, 4, 'Мебель Тверь → Москва', 2.2, 11, 'фургон', 'Тверь, ул. Молодёжная, 5', 56.8610, 35.9180, 'Москва, ул. Полярная, 25', 55.8376, 37.6809, 'зад', 15000, '2026-08-29', 'active'],
            [25, 5, 'Пиломатериалы Новосибирск → Кемерово', 4, 16, 'борт', 'Новосибирск, ул. Станционная, 90', 55.0310, 82.9220, 'Кемерово, пр. Советский, 25', 55.3558, 86.0837, 'зад', 26000, '2026-08-31', 'active'],
            [26, 4, 'Продукты Екатеринбург → Пермь', 8, 30, 'изотерм', 'Екатеринбург, ул. Ткачей, 10', 56.8420, 60.6000, 'Пермь, ул. Ленина, 60', 58.0105, 56.2502, 'зад', 38000, '2026-08-30', 'active'],
            [27, 4, 'Мясо СПб → Москва', 10, 38, 'рефрижератор', 'Санкт-Петербург, Софийская ул., 40', 59.8819, 30.3579, 'Москва, ул. Верхние Поля, 20', 55.6640, 37.8450, 'зад', 48000, '2026-08-28', 'active'],
            [28, 5, 'Промтовары СПб → Казань', 9, 36, 'тент', 'Санкт-Петербург, Пискарёвский пр., 100', 59.9343, 30.3351, 'Казань, ул. Деловая, 3', 55.7963, 49.1088, 'зад', 55000, '2026-08-29', 'active'],
            [29, 5, 'Замороженные продукты Казань → Уфа', 8, 30, 'рефрижератор', 'Казань, ул. Ершова, 8', 55.7906, 49.1249, 'Уфа, пр. Октября, 40', 54.7348, 55.9579, 'зад', 40000, '2026-08-30', 'active'],
            [30, 4, 'Продукты Москва → НН', 5, 20, 'рефрижератор', 'Москва, ул. Полярная, 25', 55.8376, 37.6809, 'Нижний Новгород, ул. Белинского, 15', 56.3178, 43.9944, 'зад', 30000, '2026-08-31', 'active'],
            [31, 5, 'Металлопрокат Москва → Казань', 8, 32, 'борт', 'Москва, Каширское ш., 61', 55.6086, 37.5645, 'Казань, ул. Аделя Кутуя, 15', 55.7695, 49.1562, 'зад,бок', 45000, '2026-08-29', 'active'],
            [32, 4, 'Стройматериалы Воронеж → Ростов-на-Дону', 4.5, 20, 'тент', 'Воронеж, ул. Дорожная, 12', 51.6826, 39.1449, 'Ростов-на-Дону, пр. Ворошиловский, 30', 47.2357, 39.7015, 'зад', 28000, '2026-08-30', 'active'],
            [33, 5, 'Сборный груз СПб → Псков', 1.8, 9, 'фургон', 'Санкт-Петербург, ул. Парковая, 3', 59.9568, 30.2832, 'Псков, ул. Ленина, 12', 57.8210, 28.3390, 'зад', 11000, '2026-08-31', 'active'],
        ];
        foreach ($cargoes as $c) {
            $cstmt->execute($c);
        }

        $pdo->exec("INSERT INTO responses (cargo_id, vehicle_id, from_user_id, to_user_id, type, status) VALUES (1,1,1,4,'vehicle_to_cargo','accepted')");
        $pdo->exec("INSERT INTO responses (cargo_id, vehicle_id, from_user_id, to_user_id, type, status) VALUES (2,2,2,4,'vehicle_to_cargo','pending')");
        $pdo->exec("INSERT INTO responses (cargo_id, vehicle_id, from_user_id, to_user_id, type, status) VALUES (3,4,3,4,'vehicle_to_cargo','pending')");

        $pdo->exec("INSERT INTO ratings (from_user_id, to_user_id, score, comment) VALUES (4,1,5,'Отличная подача машины')");
        $pdo->exec("INSERT INTO ratings (from_user_id, to_user_id, score, comment) VALUES (4,2,5,'Приехал вовремя')");
        $pdo->exec("INSERT INTO ratings (from_user_id, to_user_id, score, comment) VALUES (1,4,5,'Платит быстро')");

        $pdo->exec("INSERT INTO audit_log (user_id, event, details) VALUES (1,'register','ИП Иванов зарегистрирован')");
        $pdo->exec("INSERT INTO audit_log (user_id, event, details) VALUES (4,'verify_inn','ИНН 7712345678 проверен')");

        $pdo->exec('COMMIT');
    } catch (Throwable $e) {
        $pdo->exec('ROLLBACK');
        throw $e;
    }
}