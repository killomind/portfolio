<?php
/**
 * Конфигурация интеграции Banki.ru ↔ Битрикс24.
 *
 * Скопируйте этот файл в config/config.php и подставьте реальные значения.
 * config/config.php добавлен в .gitignore — в репозиторий он не попадает.
 *
 * Все значения ниже — примеры-заглушки.
 */
return [

    // === Битрикс24 ===
    // Вебхук доступа к REST API. Формат: https://portal.bitrix24.ru/rest/<user>/<code>/
    'webhook_url' => 'https://your-portal.bitrix24.ru/rest/1/your-webhook-code/',

    // === Аутентификация API ===
    'token_password' => 'change-me',   // пароль для POST /token
    'token_ttl' => 5000,               // срок жизни Bearer-токена, сек

    // === Бизнес-правила ===
    'min_amount' => 100000,            // минимальная сумма заявки, ₽ (меньше — отказ)
    'max_pre_approved' => 5000000,     // порог автопредодобрения, ₽
    'source_id' => 'REPEAT_SALE',      // SOURCE_ID создаваемых лидов

    // === Ответственный за лиды ===
    'responsible_user_id' => 1,        // ID пользователя в Битрикс24

    // === Кастомные поля Битрикс24 ===
    'field_pledge_info' => 'UF_CRM_PLEDGE_INFO',       // описание предмета залога
    'field_approved_amount' => 'UF_CRM_APPROVED_AMOUNT', // одобренная сумма
    'field_issued_amount' => 'UF_CRM_ISSUED_AMOUNT',     // сумма выдачи

    // === Базовый URL и UTM для ссылки на анкету ===
    'application_url' => 'https://example.com/zayavka/',
    'utm' => [
        'source' => 'banki_ru',
        'medium' => 'api',
        'campaign' => 'kpzn',
    ],

    // === Маршрутизация ===
    // Базовый путь, под которым развёрнуто приложение (public/ — корень веба).
    // Пример: '' — приложение в корне домена; '/api/v1' — приложение в подкаталоге.
    'base_path' => '',

    // === Хранилище (токены и лог) ===
    'storage_dir' => __DIR__ . '/../storage',
    'token_storage' => __DIR__ . '/../storage/tokens.json',
    'duplicate_log' => __DIR__ . '/../storage/check_double.log',

    // === Воронки сделок: этап сделки → статус API ===
    // Категории и этапы Битрикс24 у каждого клиента свои. Неизвестная
    // категория/этап считается PRE-APPROVED. Списки ниже — пример.
    'funnels' => [
        [
            'category_id' => 1,
            'name' => 'Кредит под залог (пример)',
            'approved_stages' => ['C1:5', 'C1:6'],
            'issued_stages' => ['C1:7', 'C1:WON'],
            'declined_stages' => ['C1:LOSE', 'C1:8'],
        ],
        [
            'category_id' => 2,
            'name' => 'Вторая воронка (пример)',
            'approved_stages' => [],
            'issued_stages' => [],
            'declined_stages' => [],
        ],
    ],

    // === Доступ к диагностическому отчёту (tools/debug_duplicates_report.php) ===
    // Пустой пароль = доступ запрещён.
    'debug_report_user' => 'admin',
    'debug_report_password' => '',
];
