<?php
// debug_duplicates_report.php
// Универсальный инструмент: анализ лога + поиск дублей в CRM с передачей ID

// ============================================================
// Окружение (Bootstrap + callBitrixApi)
// ============================================================
require_once __DIR__ . '/call_bitrix_api.php';

// ============================================================
// HTTP Basic Auth — защита страницы
// Учётные данные задаются в конфигурации (config.php).
// ============================================================
$valid_user = (string)Config::get('debug_report_user', '');
$valid_password = (string)Config::get('debug_report_password', '');

if ($valid_user === '' || $valid_password === '' ||
    !isset($_SERVER['PHP_AUTH_USER']) || 
    $_SERVER['PHP_AUTH_USER'] !== $valid_user || 
    $_SERVER['PHP_AUTH_PW'] !== $valid_password) {
    
    header('WWW-Authenticate: Basic realm="Debug Duplicates Report"');
    header('HTTP/1.0 401 Unauthorized');
    echo 'Требуется авторизация';
    exit;
}
// ============================================================

ini_set('memory_limit', '512M');
ob_start();

// ---- Обработчики ошибок ----
function handleError($errno, $errstr, $errfile, $errline) {
    $error = "PHP Error: [$errno] $errstr in $errfile on line $errline";
    if (isset($_GET['action'])) {
        header('Content-Type: application/json');
        if (ob_get_level()) ob_clean();
        echo json_encode(['error' => $error]);
        exit;
    }
    return false;
}
set_error_handler('handleError');

register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        if (isset($_GET['action'])) {
            header('Content-Type: application/json');
            if (ob_get_level()) ob_clean();
            echo json_encode(['error' => "Fatal: " . $error['message']]);
            exit;
        }
    }
});

session_start();

// Bitrix API подключён через call_bitrix_api.php выше.

header('Content-Type: text/html; charset=utf-8');

$logFile = (string)Config::get('duplicate_log', __DIR__ . '/../storage/check_double.log');
$cacheFile = sys_get_temp_dir() . '/log_index_cache_' . md5(__DIR__) . '.dat';

// ================================================================
// AJAX-обработчики
// ================================================================
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    $action = $_GET['action'];

    try {
        // ---- 1. Построение индекса лога (с поддержкой Request ID) ----
        if ($action === 'build_index') {
            if (!file_exists($logFile)) {
                throw new Exception('Лог-файл не найден: ' . $logFile);
            }

            // Собираем IN и OUT по ID
            $inMap = [];
            $outMap = [];

            $handle = fopen($logFile, 'r');
            while (($line = fgets($handle)) !== false) {
                $line = trim($line);
                // Ищем IN с ID
                if (preg_match('/^(?P<date>[\d\-: ]+) \[(?P<reqid>[^\]]+)\] IN:\s*(?P<phone>\d+)/', $line, $matches)) {
                    $reqId = $matches['reqid'];
                    $inMap[$reqId] = ['phone' => $matches['phone'], 'line' => $line];
                }
                // Ищем OUT с ID
                if (preg_match('/^(?P<date>[\d\-: ]+) \[(?P<reqid>[^\]]+)\] OUT:\s*(repeat|new)/', $line, $matches)) {
                    $reqId = $matches['reqid'];
                    $outMap[$reqId] = ['status' => $matches[2], 'line' => $line];
                }
                // Старый формат без ID – обрабатываем через стек
                if (preg_match('/^(?P<date>[\d\-: ]+) IN:\s*(?P<phone>\d+)/', $line, $matches) && !strpos($line, '[')) {
                    // сохраняем в отдельный массив для старых
                    $legacyIn[] = ['phone' => $matches['phone'], 'line' => $line];
                }
                if (preg_match('/^(?P<date>[\d\-: ]+) OUT:\s*(repeat|new)/', $line, $matches) && !strpos($line, '[')) {
                    $legacyOut[] = ['status' => $matches[2], 'line' => $line];
                }
            }
            fclose($handle);

            // Объединяем по ID
            $requests = [];
            foreach ($inMap as $reqId => $inData) {
                $requests[$reqId] = [
                    'phone' => $inData['phone'],
                    'in_line' => $inData['line'],
                    'out_line' => isset($outMap[$reqId]) ? $outMap[$reqId]['line'] : null,
                    'status' => isset($outMap[$reqId]) ? $outMap[$reqId]['status'] : null,
                ];
            }

            // Старые логи без ID – связываем по порядку (стек)
            if (!empty($legacyIn) && !empty($legacyOut)) {
                $stack = $legacyIn;
                foreach ($legacyOut as $out) {
                    if (!empty($stack)) {
                        $in = array_shift($stack);
                        $tmpId = 'legacy_' . uniqid();
                        $requests[$tmpId] = [
                            'phone' => $in['phone'],
                            'in_line' => $in['line'],
                            'out_line' => $out['line'],
                            'status' => $out['status'],
                        ];
                    }
                }
            }

            // Сохраняем в кэш
            file_put_contents($cacheFile, serialize($requests));

            $phones = array_unique(array_column($requests, 'phone'));
            echo json_encode([
                'total_entries' => count($requests),
                'unique_phones' => count($phones)
            ]);
            exit;
        }

        // ---- 2. Анализ лога для заданного списка ID ----
        if ($action === 'process') {
            if (!file_exists($cacheFile)) {
                throw new Exception('Кэш не найден. Сначала выполните построение индекса.');
            }
            $requests = unserialize(file_get_contents($cacheFile));
            if ($requests === false) {
                throw new Exception('Не удалось прочитать кэш');
            }

            // Получаем список ID из запроса (если передан) или используем пустой массив
            $idsParam = isset($_GET['ids']) ? $_GET['ids'] : '';
            if (empty($idsParam)) {
                throw new Exception('Не передан список ID лидов для анализа.');
            }
            // Разбираем ID: через запятую, пробел или перенос строки
            $ids = array_unique(array_filter(array_map('trim', preg_split('/[\s,]+/', $idsParam))));
            if (empty($ids)) {
                throw new Exception('Список ID пуст или содержит некорректные значения.');
            }

            $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
            $chunk = array_slice($ids, $offset, $limit);

            $results = [];
            foreach ($chunk as $id) {
                try {
                    $leadData = callBitrixApi('crm.lead.get', [
                        'id' => $id,
                        'select' => ['ID', 'TITLE', 'PHONE', 'STATUS_ID', 'DATE_CREATE']
                    ]);
                } catch (Exception $e) {
                    $leadData = ['error' => $e->getMessage()];
                }

                $phone = null;
                $leadStatus = 'not_found';
                $leadTitle = '';
                $dateCreate = '';
                if (isset($leadData['error']) || empty($leadData['result'])) {
                    $leadStatus = 'not_found';
                } else {
                    $lead = $leadData['result'];
                    $leadTitle = $lead['TITLE'] ?? '';
                    $leadStatus = $lead['STATUS_ID'] ?? '';
                    $dateCreate = $lead['DATE_CREATE'] ?? '';
                    if (!empty($lead['PHONE']) && is_array($lead['PHONE'])) {
                        foreach ($lead['PHONE'] as $p) {
                            if (!empty($p['VALUE'])) {
                                $phone = $p['VALUE'];
                                break;
                            }
                        }
                    }
                }

                // Собираем все записи лога для этого телефона
                $logEntries = [];
                if ($phone) {
                    foreach ($requests as $req) {
                        if ($req['phone'] === $phone) {
                            $logEntries[] = [
                                'in_line' => $req['in_line'],
                                'out_line' => $req['out_line'] ?? '—',
                                'status' => $req['status'] ?? '—'
                            ];
                        }
                    }
                }

                $results[] = [
                    'id' => $id,
                    'phone' => $phone,
                    'lead_status' => $leadStatus,
                    'lead_title' => $leadTitle,
                    'date_create' => $dateCreate,
                    'log_entries' => $logEntries,
                ];
            }

            echo json_encode([
                'results' => $results,
                'has_more' => ($offset + $limit) < count($ids)
            ]);
            exit;
        }

        // ---- 3. Поиск дублей в CRM (по всей базе, но с фильтром по дате) ----
        if ($action === 'find_duplicates') {
            $dateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : null;
            $dateTo = isset($_GET['date_to']) ? $_GET['date_to'] : null;

            if (!$dateFrom || !$dateTo) {
                throw new Exception('Укажите обе даты (с и по)');
            }

            $from = $dateFrom . ' 00:00:00';
            $to = $dateTo . ' 23:59:59';

            // Шаг 1: получаем все лиды за период (для фильтрации)
            $leadsInPeriod = [];
            $start = 0;
            do {
                $params = [
                    'filter' => [
                        '>=DATE_CREATE' => $from,
                        '<=DATE_CREATE' => $to,
                    ],
                    'select' => ['ID', 'PHONE', 'DATE_CREATE', 'STATUS_ID'],
                    'start' => $start,
                    'limit' => 50
                ];
                $result = callBitrixApi('crm.lead.list', $params);
                if (isset($result['error'])) {
                    throw new Exception('Ошибка Bitrix: ' . $result['error_description']);
                }
                if (empty($result['result'])) break;
                $leadsInPeriod = array_merge($leadsInPeriod, $result['result']);
                $start += 50;
            } while (count($result['result']) >= 50);

            if (empty($leadsInPeriod)) {
                echo json_encode([
                    'total_leads' => 0,
                    'total_phones' => 0,
                    'duplicate_groups' => 0,
                    'groups' => []
                ]);
                exit;
            }

            // Шаг 2: собираем телефоны, которые есть в периоде (чтобы потом проверить дубли по всей базе)
            $phonesInPeriod = [];
            foreach ($leadsInPeriod as $lead) {
                $phone = null;
                if (!empty($lead['PHONE']) && is_array($lead['PHONE'])) {
                    foreach ($lead['PHONE'] as $p) {
                        if (!empty($p['VALUE'])) {
                            $phone = $p['VALUE'];
                            break;
                        }
                    }
                }
                if ($phone) $phonesInPeriod[] = $phone;
            }
            $phonesInPeriod = array_unique($phonesInPeriod);

            if (empty($phonesInPeriod)) {
                echo json_encode([
                    'total_leads' => count($leadsInPeriod),
                    'total_phones' => 0,
                    'duplicate_groups' => 0,
                    'groups' => []
                ]);
                exit;
            }

            // Шаг 3: для каждого телефона из периода проверяем все лиды с этим телефоном (по всей базе)
            $allGroups = [];
            foreach ($phonesInPeriod as $phone) {
                // Ищем все лиды с этим телефоном (без ограничения по дате)
                $leadsForPhone = [];
                $start2 = 0;
                do {
                    $params2 = [
                        'filter' => ['PHONE' => $phone],
                        'select' => ['ID', 'DATE_CREATE', 'STATUS_ID'],
                        'start' => $start2,
                        'limit' => 50
                    ];
                    $res2 = callBitrixApi('crm.lead.list', $params2);
                    if (isset($res2['error'])) break;
                    if (empty($res2['result'])) break;
                    $leadsForPhone = array_merge($leadsForPhone, $res2['result']);
                    $start2 += 50;
                } while (count($res2['result']) >= 50);

                if (count($leadsForPhone) > 1) {
                    // Сортируем лиды по дате (новые сверху)
                    usort($leadsForPhone, function($a, $b) {
                        return strtotime($b['DATE_CREATE']) - strtotime($a['DATE_CREATE']);
                    });
                    $allGroups[$phone] = $leadsForPhone;
                }
            }

            // Сортируем группы по дате самого свежего лида (новые сверху)
            uasort($allGroups, function($a, $b) {
                $maxA = max(array_map('strtotime', array_column($a, 'DATE_CREATE')));
                $maxB = max(array_map('strtotime', array_column($b, 'DATE_CREATE')));
                return $maxB - $maxA;
            });

            echo json_encode([
                'total_leads' => count($leadsInPeriod),
                'total_phones' => count($phonesInPeriod),
                'duplicate_groups' => count($allGroups),
                'groups' => $allGroups
            ]);
            exit;
        }

        echo json_encode(['error' => 'Неизвестное действие']);
        exit;

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
        exit;
    }
}

// ================================================================
// HTML-интерфейс
// ================================================================
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <title>Диагностика дублей + поиск дублей в CRM</title>
    <style>
        body { font-family: 'Segoe UI', system-ui, sans-serif; background: #f4f6fa; padding: 30px; margin: 0; }
        .container { max-width: 1400px; margin: 0 auto; background: white; padding: 30px; border-radius: 20px; box-shadow: 0 8px 30px rgba(0,0,0,0.08); }
        h1 { margin-top: 0; font-weight: 600; }
        .toolbar { margin: 24px 0; display: flex; gap: 16px; align-items: center; flex-wrap: wrap; }
        .btn { padding: 10px 24px; background: #1e6f9f; color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; transition: background 0.2s; }
        .btn:hover { background: #155a82; }
        .btn:disabled { opacity: 0.5; cursor: not-allowed; }
        .btn-success { background: #2a9d8f; }
        .btn-success:hover { background: #21867a; }
        .btn-warning { background: #e67e22; }
        .btn-warning:hover { background: #d35400; }
        .btn-download { background: #e76f51; }
        .btn-download:hover { background: #d45a3a; }
        #progress { font-weight: 500; color: #1e6f9f; }
        .summary-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; margin: 24px 0; }
        .stat-card { background: #f8f9fb; padding: 16px 20px; border-radius: 12px; border-left: 4px solid #1e6f9f; }
        .stat-card .number { font-size: 28px; font-weight: 700; }
        .stat-card .label { color: #5a6e85; font-size: 14px; }
        .group-card { background: white; border: 1px solid #e2e8f0; border-radius: 16px; margin-bottom: 24px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.04); }
        .group-header { display: flex; justify-content: space-between; align-items: center; padding: 16px 24px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; flex-wrap: wrap; gap: 12px; }
        .group-header .phone { font-weight: 700; font-size: 18px; }
        .group-header .badge { padding: 4px 12px; border-radius: 20px; font-size: 13px; font-weight: 600; }
        .badge-new { background: #ffd6d6; color: #b91c1c; }
        .badge-repeat { background: #d4edda; color: #155724; }
        .badge-unknown { background: #e9ecef; color: #495057; }
        .badge-danger { background: #f8d7da; color: #721c24; }
        .group-body { padding: 16px 24px; overflow-x: auto; }
        .group-body table { width: 100%; border-collapse: collapse; font-size: 14px; margin-top: 8px; margin-bottom: 16px; }
        .group-body th, .group-body td { padding: 10px 12px; text-align: left; border-bottom: 1px solid #eef2f6; }
        .group-body th { background: #f8fafc; font-weight: 600; color: #1e293b; }
        .group-body tr:last-child td { border-bottom: none; }
        .warning-banner { background: #fff3cd; padding: 12px 20px; border-left: 4px solid #ffc107; margin: 16px 24px; border-radius: 6px; }
        .no-data { color: #6c757d; text-align: center; padding: 40px; }
        #reportContainer { margin-top: 24px; }
        .fade-in { animation: fadeIn 0.5s ease-in; }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        .sub-header { font-weight: 600; margin: 12px 0 6px 0; color: #1e293b; }
        @media print {
            .toolbar { display: none; }
            .no-print { display: none; }
            .container { box-shadow: none; border: 1px solid #ddd; }
        }
        .btn-success:disabled { background: #a8c9c1; }
        .status-ok { color: #27ae60; }
        .status-error { color: #e74c3c; }
        .filter-group { display: flex; gap: 16px; align-items: center; margin: 12px 0; padding: 8px 16px; background: #f8fafc; border-radius: 8px; border: 1px solid #e2e8f0; flex-wrap: wrap; }
        .filter-group label { display: flex; align-items: center; gap: 6px; cursor: pointer; font-size: 14px; }
        .filter-group input[type="radio"] { margin: 0; }
        .filter-group .filter-label { font-weight: 600; margin-right: 8px; }
        .badge-filter { padding: 2px 10px; border-radius: 12px; font-size: 12px; }
        .badge-filter-all { background: #e9ecef; color: #495057; }
        .badge-filter-duplicates { background: #ffd6d6; color: #b91c1c; }
        .badge-filter-no-duplicates { background: #d4edda; color: #155724; }
        .mode-tabs { display: flex; gap: 8px; margin-bottom: 16px; }
        .mode-tab { padding: 8px 20px; background: #e9ecef; border-radius: 20px; cursor: pointer; font-weight: 600; border: none; }
        .mode-tab.active { background: #1e6f9f; color: white; }
        .date-picker { display: flex; gap: 12px; align-items: center; flex-wrap: wrap; background: #f8fafc; padding: 8px 16px; border-radius: 8px; border: 1px solid #e2e8f0; }
        .date-picker input[type="date"] { padding: 6px 12px; border: 1px solid #ced4da; border-radius: 6px; }
        .date-picker label { font-weight: 500; }
        .ids-input { width: 100%; padding: 8px; border: 1px solid #ced4da; border-radius: 6px; font-family: monospace; font-size: 13px; }
        .checkbox-group { display: flex; align-items: center; gap: 8px; }
        .checkbox-group input[type="checkbox"] { width: 18px; height: 18px; cursor: pointer; }
        .btn-analyze-selected { background: #6f42c1; color: white; }
        .btn-analyze-selected:hover { background: #5a32a3; }
    </style>
</head>
<body>
<div class="container">
    <h1>🔍 Диагностика дублей + поиск дублей в CRM</h1>
    <p>Анализ лога <code>check_double.log</code> (поддержка Request ID) и поиск дублирующихся лидов в Bitrix24.</p>

    <!-- Вкладки режимов -->
    <div class="mode-tabs">
        <button class="mode-tab active" data-mode="log">📊 Анализ лога</button>
        <button class="mode-tab" data-mode="crm">🏢 Поиск дублей в CRM</button>
    </div>

    <!-- Панель для режима "Анализ лога" -->
    <div id="logMode" class="mode-panel">
        <div class="toolbar">
            <button class="btn btn-warning" id="btnBuildIndex">🔄 Построить индекс лога</button>
            <button class="btn btn-success" id="btnAnalyze" disabled>📊 Сформировать диагностику</button>
            <button class="btn btn-download" id="btnDownload" disabled>📥 Скачать отчёт (HTML)</button>
            <span id="progress">⏳ Ожидание</span>
        </div>
        <div style="margin: 12px 0;">
            <label for="idsInput">📋 ID лидов для анализа (через запятую, пробел или новую строку):</label>
            <textarea id="idsInput" class="ids-input" rows="3" placeholder="Введите ID лидов, например: 76201, 76202, 74527"></textarea>
        </div>
        <div id="statusMessage" style="margin-bottom: 16px; padding: 10px; border-radius: 8px; background: #f8f9fa; border-left: 4px solid #1e6f9f;">
            <span id="statusText">Введите ID лидов и нажмите «Построить индекс лога», затем «Сформировать диагностику».</span>
        </div>

        <!-- Фильтр дублей (для лога) -->
        <div class="filter-group" id="filterGroup" style="display: none;">
            <span class="filter-label">🎯 Фильтр:</span>
            <label><input type="radio" name="filter" value="all" checked> <span class="badge-filter badge-filter-all">Все</span></label>
            <label><input type="radio" name="filter" value="duplicates"> <span class="badge-filter badge-filter-duplicates">Только дубли</span></label>
            <label><input type="radio" name="filter" value="no_duplicates"> <span class="badge-filter badge-filter-no-duplicates">Без дублей</span></label>
            <span style="margin-left: auto; font-size: 14px; color: #5a6e85;" id="filterCount"></span>
        </div>

        <div id="reportContainer">
            <div class="no-data">Введите ID, постройте индекс лога, затем сформируйте диагностику.</div>
        </div>
    </div>

    <!-- Панель для режима "Поиск дублей в CRM" -->
    <div id="crmMode" class="mode-panel" style="display: none;">
        <div class="date-picker">
            <label>📅 Период:</label>
            <input type="date" id="dateFrom" value="<?php echo date('Y-m-d', strtotime('-7 days')); ?>">
            <label>по</label>
            <input type="date" id="dateTo" value="<?php echo date('Y-m-d'); ?>">
            <button class="btn btn-success" id="btnFindDuplicates">🔍 Найти дубли</button>
            <button class="btn btn-analyze-selected" id="btnAnalyzeSelected" disabled>📊 Проанализировать выбранные дубли</button>
            <button class="btn btn-download" id="btnDownloadCrm">📥 Выгрузить дубликаты в HTML</button>
            <span id="crmProgress" style="margin-left: 12px;">Ожидание</span>
        </div>
        <div id="crmReportContainer">
            <div class="no-data">Выберите период и нажмите «Найти дубли».</div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // ---- Переключение вкладок ----
    const modeTabs = document.querySelectorAll('.mode-tab');
    const logMode = document.getElementById('logMode');
    const crmMode = document.getElementById('crmMode');

    modeTabs.forEach(tab => {
        tab.addEventListener('click', function() {
            modeTabs.forEach(t => t.classList.remove('active'));
            this.classList.add('active');
            if (this.dataset.mode === 'log') {
                logMode.style.display = 'block';
                crmMode.style.display = 'none';
            } else {
                logMode.style.display = 'none';
                crmMode.style.display = 'block';
            }
        });
    });

    // ---- Элементы для режима лога ----
    const btnBuild = document.getElementById('btnBuildIndex');
    const btnAnalyze = document.getElementById('btnAnalyze');
    const btnDownload = document.getElementById('btnDownload');
    const progress = document.getElementById('progress');
    const statusText = document.getElementById('statusText');
    const container = document.getElementById('reportContainer');
    const filterGroup = document.getElementById('filterGroup');
    const filterCount = document.getElementById('filterCount');
    const idsInput = document.getElementById('idsInput');

    let allResults = [];
    let allGroups = [];
    let warnings = [];
    let filterMode = 'all';
    let indexBuilt = false;
    let currentIds = [];

    // ---- Построение индекса лога ----
    btnBuild.addEventListener('click', function() {
        btnBuild.disabled = true;
        progress.textContent = '⏳ Построение индекса...';
        statusText.textContent = 'Чтение и парсинг лога...';

        fetch('?action=build_index')
            .then(res => {
                if (!res.ok) throw new Error(`HTTP ${res.status}`);
                return res.json();
            })
            .then(data => {
                if (data.error) {
                    alert('Ошибка: ' + data.error);
                    btnBuild.disabled = false;
                    progress.textContent = '❌ Ошибка';
                    statusText.textContent = 'Ошибка построения индекса';
                    return;
                }
                indexBuilt = true;
                progress.textContent = `✅ Индекс построен (${data.total_entries} записей, ${data.unique_phones} телефонов)`;
                statusText.textContent = 'Индекс готов. Введите ID и нажмите «Сформировать диагностику».';
                btnAnalyze.disabled = false;
                btnBuild.disabled = false;
                container.innerHTML = '<div class="no-data">Индекс построен. Введите ID и нажмите «Сформировать диагностику».</div>';
                filterGroup.style.display = 'none';
            })
            .catch(err => {
                alert('Ошибка: ' + err.message);
                btnBuild.disabled = false;
                progress.textContent = '❌ Ошибка';
                statusText.textContent = 'Ошибка построения индекса';
                console.error(err);
            });
    });

    // ---- Сформировать диагностику ----
    btnAnalyze.addEventListener('click', function() {
        if (!indexBuilt) {
            alert('Сначала постройте индекс лога.');
            return;
        }
        const ids = idsInput.value.trim();
        if (!ids) {
            alert('Введите ID лидов для анализа.');
            return;
        }
        // Очищаем и разбиваем
        const idList = ids.split(/[\s,]+/).filter(id => id !== '').map(id => parseInt(id, 10)).filter(id => !isNaN(id));
        if (idList.length === 0) {
            alert('Не найдено корректных ID.');
            return;
        }
        currentIds = idList;
        btnAnalyze.disabled = true;
        btnDownload.disabled = true;
        progress.textContent = '⏳ Загрузка лидов...';
        statusText.textContent = 'Получение данных из Bitrix24...';

        allResults = [];
        const total = idList.length;
        const limit = 20;
        let offset = 0;

        function loadChunk() {
            const chunkIds = idList.slice(offset, offset + limit);
            if (chunkIds.length === 0) {
                finishDiagnostics();
                return;
            }
            const idsParam = chunkIds.join(',');
            fetch(`?action=process&ids=${encodeURIComponent(idsParam)}&offset=${offset}&limit=${limit}`)
                .then(res => res.json())
                .then(data => {
                    if (data.error) {
                        alert('Ошибка: ' + data.error);
                        btnAnalyze.disabled = false;
                        progress.textContent = '❌ Ошибка';
                        statusText.textContent = 'Ошибка загрузки';
                        return;
                    }
                    allResults = allResults.concat(data.results);
                    offset += chunkIds.length;
                    progress.textContent = `⏳ Обработано ${allResults.length}/${total}`;
                    if (data.has_more) {
                        loadChunk();
                    } else {
                        finishDiagnostics();
                    }
                })
                .catch(err => {
                    alert('Ошибка: ' + err.message);
                    btnAnalyze.disabled = false;
                    progress.textContent = '❌ Ошибка';
                    statusText.textContent = 'Ошибка загрузки';
                    console.error(err);
                });
        }
        loadChunk();
    });

    function finishDiagnostics() {
        progress.textContent = '✅ Готово! Формируем отчёт...';
        statusText.textContent = 'Отчёт сформирован.';
        const { groups, warnings: w } = buildGroups(allResults);
        allGroups = groups;
        warnings = w;
        renderReport(groups, warnings, filterMode);
        btnAnalyze.disabled = false;
        btnDownload.disabled = false;
        filterGroup.style.display = 'flex';
        progress.textContent = `✅ Готово! Обработано ${allResults.length} ID.`;
    }

    function buildGroups(results) {
        const groups = {};
        results.forEach(item => {
            const phone = item.phone || 'Без телефона';
            if (!groups[phone]) {
                groups[phone] = { phone, items: [], log_entries: [] };
            }
            groups[phone].items.push(item);
            if (item.log_entries && item.log_entries.length > 0) {
                item.log_entries.forEach(entry => {
                    const exists = groups[phone].log_entries.some(e => e.in_line === entry.in_line);
                    if (!exists) groups[phone].log_entries.push(entry);
                });
            }
        });

        const warnings = [];
        Object.values(groups).forEach(g => {
            const hasNew = g.log_entries.some(e => e.status === 'new');
            const hasRepeat = g.log_entries.some(e => e.status === 'repeat');
            const hasLeads = g.items.some(i => i.lead_status !== 'not_found');
            if (hasNew && hasLeads) {
                warnings.push(`Телефон ${g.phone}: в логе есть "new", но лиды существуют (возможно, дубли из-за скорости).`);
            }
            if (hasRepeat && g.items.length > 1) {
                warnings.push(`Телефон ${g.phone}: в логе есть "repeat", но дубли всё равно есть (возможно, Banki.ru игнорирует ответ).`);
            }
            if (g.log_entries.length === 0 && hasLeads) {
                warnings.push(`Телефон ${g.phone}: лиды есть, но в логе нет записи (созданы вручную или через другую интеграцию).`);
            }
        });

        return { groups, warnings };
    }

    function renderReport(groups, warnings, filter) {
        let filteredGroups = Object.values(groups);
        if (filter === 'duplicates') {
            filteredGroups = filteredGroups.filter(g => g.items.length > 1);
        } else if (filter === 'no_duplicates') {
            filteredGroups = filteredGroups.filter(g => g.items.length === 1);
        }

        // Сортировка групп по максимальной дате создания лида (новые сверху)
        filteredGroups.sort((a, b) => {
            const getMaxDate = (group) => {
                let maxTs = 0;
                group.items.forEach(item => {
                    if (item.date_create) {
                        const ts = new Date(item.date_create).getTime();
                        if (ts > maxTs) maxTs = ts;
                    }
                });
                return maxTs;
            };
            return getMaxDate(b) - getMaxDate(a);
        });

        const totalGroups = filteredGroups.length;
        let withLog = 0, logNew = 0, logRepeat = 0;
        filteredGroups.forEach(g => {
            const hasNew = g.log_entries.some(e => e.status === 'new');
            const hasRepeat = g.log_entries.some(e => e.status === 'repeat');
            if (hasNew) logNew++;
            if (hasRepeat) logRepeat++;
            if (g.log_entries.length > 0) withLog++;
        });
        const filteredWarnings = warnings.filter(w => filteredGroups.some(g => w.includes(g.phone)));

        filterCount.textContent = `Показано ${totalGroups} групп (из ${Object.keys(groups).length})`;

        let html = `
            <div class="summary-grid">
                <div class="stat-card"><div class="number">${totalGroups}</div><div class="label">Групп по телефону (по фильтру)</div></div>
                <div class="stat-card"><div class="number">${withLog}</div><div class="label">Найдено в логе</div></div>
                <div class="stat-card"><div class="number">${logNew}</div><div class="label">Статус "new"</div></div>
                <div class="stat-card"><div class="number">${logRepeat}</div><div class="label">Статус "repeat"</div></div>
                <div class="stat-card" style="border-left-color: #e76f51;"><div class="number">${filteredWarnings.length}</div><div class="label">⚠️ Аномалий</div></div>
            </div>
        `;

        if (filteredWarnings.length > 0) {
            html += `<div style="background:#fff3cd; padding:16px 20px; border-radius:12px; margin-bottom:24px; border-left:4px solid #ffc107;">
                <strong>⚠️ Обнаружены аномалии:</strong><ul style="margin:8px 0 0 20px;">${filteredWarnings.map(w => `<li>${w}</li>`).join('')}</ul>
            </div>`;
        }

        if (filteredGroups.length === 0) {
            html += `<div class="no-data">Нет групп, соответствующих выбранному фильтру.</div>`;
        } else {
            filteredGroups.forEach(group => {
                const isWarning = filteredWarnings.some(w => w.includes(group.phone));
                const hasNew = group.log_entries.some(e => e.status === 'new');
                const hasRepeat = group.log_entries.some(e => e.status === 'repeat');
                const badgeClass = hasNew ? 'badge-new' : (hasRepeat ? 'badge-repeat' : 'badge-unknown');
                const badgeText = hasNew ? 'new' : (hasRepeat ? 'repeat' : 'нет в логе');
                const borderColor = isWarning ? '#ffc107' : '#e2e8f0';
                const duplicateCount = group.items.length;

                // Сортируем лиды внутри группы по дате (новые сверху)
                group.items.sort((a, b) => {
                    const da = a.date_create ? new Date(a.date_create).getTime() : 0;
                    const db = b.date_create ? new Date(b.date_create).getTime() : 0;
                    return db - da;
                });

                html += `
                    <div class="group-card" style="border-color: ${borderColor};">
                        <div class="group-header">
                            <span class="phone">📞 ${group.phone}</span>
                            <div>
                                <span class="badge ${badgeClass}">${badgeText}</span>
                                ${isWarning ? ' <span style="font-size:14px; color:#856404;">⚠️</span>' : ''}
                                <span style="margin-left:12px; font-size:14px; color:#5a6e85;">📋 ${group.log_entries.length} записей, 👤 ${duplicateCount} лидов</span>
                                ${duplicateCount > 1 ? ' <span style="background:#ffd6d6; padding:2px 8px; border-radius:10px; font-size:12px; color:#b91c1c;">ДУБЛЬ</span>' : ''}
                            </div>
                        </div>
                        <div class="group-body">
                            <div class="sub-header">📋 Записи в логе (хронологически)</div>
                            <table>
                                <thead><tr><th>Строка IN</th><th>Строка OUT</th><th>Результат</th></tr></thead>
                                <tbody>
                `;
                if (group.log_entries.length > 0) {
                    group.log_entries.forEach(entry => {
                        html += `<tr>
                            <td style="font-size:12px; font-family:monospace;">${entry.in_line}</td>
                            <td style="font-size:12px; font-family:monospace;">${entry.out_line || '—'}</td>
                            <td>${entry.status || '—'}</td>
                        </tr>`;
                    });
                } else {
                    html += `<tr><td colspan="3">Нет записей в логе</td></tr>`;
                }
                html += `</tbody></table>`;

                html += `<div class="sub-header" style="margin-top:20px;">👤 Лиды (${duplicateCount})</div>
                    <table>
                        <thead><tr><th>ID</th><th>Дата создания</th><th>Статус CRM</th></tr></thead>
                        <tbody>
                `;
                group.items.forEach(item => {
                    const statusCrm = item.lead_status === 'not_found' ? '❌ Не найден' : item.lead_status;
                    const dateCreate = item.date_create ? new Date(item.date_create).toLocaleString('ru-RU') : '—';
                    html += `<tr>
                        <td><strong>${item.id}</strong></td>
                        <td>${dateCreate}</td>
                        <td>${statusCrm}</td>
                    </tr>`;
                });
                html += `</tbody></table></div></div>`;
            });
        }

        container.innerHTML = html;
    }

    // ---- Фильтр ----
    document.querySelectorAll('input[name="filter"]').forEach(radio => {
        radio.addEventListener('change', function() {
            if (this.checked) {
                filterMode = this.value;
                if (allGroups.length > 0) {
                    renderReport(allGroups, warnings, filterMode);
                }
            }
        });
    });

    // ---- Скачивание отчёта (первая вкладка) ----
    btnDownload.addEventListener('click', function() {
        if (allGroups.length === 0) {
            alert('Сначала сформируйте диагностику.');
            return;
        }
        const currentFilter = document.querySelector('input[name="filter"]:checked').value;
        const content = document.querySelector('.container').cloneNode(true);
        content.querySelectorAll('.toolbar, .btn-download, .no-print, .filter-group, .mode-tabs, .mode-panel').forEach(el => el.remove());
        const styles = document.querySelector('style').innerHTML;
        const printHtml = `
            <!DOCTYPE html>
            <html><head><meta charset="utf-8"><title>Отчёт по дублям (фильтр: ${currentFilter})</title><style>${styles}</style></head>
            <body>${content.outerHTML}</body></html>
        `;
        const blob = new Blob([printHtml], {type: 'text/html'});
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `diagnostika_dubley_${currentFilter}.html`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    });

    // ============================================================
    // ---- РЕЖИМ CRM (поиск дублей) ----
    // ============================================================
    const btnFind = document.getElementById('btnFindDuplicates');
    const crmProgress = document.getElementById('crmProgress');
    const crmContainer = document.getElementById('crmReportContainer');
    const btnDownloadCrm = document.getElementById('btnDownloadCrm');
    const btnAnalyzeSelected = document.getElementById('btnAnalyzeSelected');

    let crmGroups = {};

    btnFind.addEventListener('click', function() {
        const dateFrom = document.getElementById('dateFrom').value;
        const dateTo = document.getElementById('dateTo').value;
        if (!dateFrom || !dateTo) {
            alert('Выберите обе даты.');
            return;
        }
        btnFind.disabled = true;
        crmProgress.textContent = '⏳ Поиск...';
        crmContainer.innerHTML = '<div class="no-data">Загрузка данных из Bitrix24...</div>';

        fetch(`?action=find_duplicates&date_from=${dateFrom}&date_to=${dateTo}`)
            .then(res => {
                if (!res.ok) throw new Error(`HTTP ${res.status}`);
                return res.json();
            })
            .then(data => {
                if (data.error) {
                    alert('Ошибка: ' + data.error);
                    btnFind.disabled = false;
                    crmProgress.textContent = '❌ Ошибка';
                    return;
                }
                crmGroups = data.groups;
                crmProgress.textContent = `✅ Найдено: ${data.total_leads} лидов в периоде, ${data.duplicate_groups} групп с дублями`;

                let html = `
                    <div class="summary-grid">
                        <div class="stat-card"><div class="number">${data.total_leads}</div><div class="label">Всего лидов за период</div></div>
                        <div class="stat-card"><div class="number">${data.total_phones}</div><div class="label">Уникальных телефонов</div></div>
                        <div class="stat-card" style="border-left-color: #e76f51;"><div class="number">${data.duplicate_groups}</div><div class="label">⚠️ Групп с дублями</div></div>
                    </div>
                `;

                if (data.duplicate_groups === 0) {
                    html += `<div class="no-data">За выбранный период дублирующихся лидов не найдено.</div>`;
                } else {
                    let groupIndex = 0;
                    for (const [phone, items] of Object.entries(data.groups)) {
                        // сортируем лиды внутри группы по дате (новые сверху)
                        items.sort((a, b) => {
                            return new Date(b.DATE_CREATE) - new Date(a.DATE_CREATE);
                        });
                        const latestDate = new Date(items[0].DATE_CREATE).toLocaleString('ru-RU');
                        html += `
                            <div class="group-card" style="border-color: #ffc107;">
                                <div class="group-header">
                                    <span class="phone">
                                        <input type="checkbox" class="group-checkbox" data-group-index="${groupIndex}" style="width:18px;height:18px;margin-right:10px;">
                                        📞 ${phone}
                                    </span>
                                    <div>
                                        <span class="badge badge-danger">ДУБЛЬ (${items.length})</span>
                                        <span style="margin-left:12px; font-size:13px; color:#5a6e85;">Самый свежий: ${latestDate}</span>
                                    </div>
                                </div>
                                <div class="group-body">
                                    <table>
                                        <thead><tr><th>ID лида</th><th>Дата создания</th><th>Статус</th></tr></thead>
                                        <tbody>
                        `;
                        items.forEach(item => {
                            html += `<tr>
                                <td><strong>${item.ID}</strong></td>
                                <td>${new Date(item.DATE_CREATE).toLocaleString('ru-RU')}</td>
                                <td>${item.STATUS_ID}</td>
                            </tr>`;
                        });
                        html += `</tbody></table></div></div>`;
                        groupIndex++;
                    }

                    btnAnalyzeSelected.disabled = false;
                }

                crmContainer.innerHTML = html;
                btnFind.disabled = false;
                updateAnalyzeButton();
            })
            .catch(err => {
                alert('Ошибка: ' + err.message);
                btnFind.disabled = false;
                crmProgress.textContent = '❌ Ошибка';
                console.error(err);
            });
    });

    // ---- Обработка чекбоксов ----
    crmContainer.addEventListener('change', function(e) {
        if (e.target.classList.contains('group-checkbox')) {
            updateAnalyzeButton();
        }
    });

    function updateAnalyzeButton() {
        const checked = document.querySelectorAll('.group-checkbox:checked');
        btnAnalyzeSelected.disabled = (checked.length === 0);
    }

    // ---- Кнопка "Проанализировать выбранные дубли" ----
    btnAnalyzeSelected.addEventListener('click', function() {
        const checked = document.querySelectorAll('.group-checkbox:checked');
        if (checked.length === 0) {
            alert('Выберите хотя бы одну группу.');
            return;
        }
        let allIds = [];
        checked.forEach(cb => {
            const groupIndex = parseInt(cb.dataset.groupIndex, 10);
            const groupCards = document.querySelectorAll('.group-card');
            if (groupCards[groupIndex]) {
                const phone = groupCards[groupIndex].querySelector('.phone').textContent.trim().replace('📞 ', '');
                if (crmGroups[phone]) {
                    crmGroups[phone].forEach(item => {
                        allIds.push(item.ID);
                    });
                }
            }
        });
        if (allIds.length === 0) {
            alert('Не удалось собрать ID лидов.');
            return;
        }
        document.querySelector('.mode-tab[data-mode="log"]').click();
        idsInput.value = allIds.join(', ');
        btnAnalyze.click();
    });

    // ---- Кнопка "Выгрузить дубликаты в HTML" ----
    btnDownloadCrm.addEventListener('click', function() {
        const content = document.getElementById('crmMode').cloneNode(true);
        content.querySelectorAll('.btn, .date-picker, .toolbar, .no-print').forEach(el => el.remove());
        const styles = document.querySelector('style').innerHTML;
        const printHtml = `
            <!DOCTYPE html>
            <html><head><meta charset="utf-8"><title>Дубликаты лидов в CRM</title><style>${styles}</style></head>
            <body><div class="container">${content.innerHTML}</div></body></html>
        `;
        const blob = new Blob([printHtml], {type: 'text/html'});
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'dublikaty_v_crm.html';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    });
});
</script>
</body>
</html>