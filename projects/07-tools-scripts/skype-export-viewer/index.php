<?php
// ---------- Автозагрузчик JsonMachine (или composer) ----------
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
} else {
    spl_autoload_register(function ($class) {
        $prefix = 'JsonMachine\\';
        $baseDir = __DIR__ . '/lib/src/';
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) return;
        $relativeClass = substr($class, $len);
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
        if (file_exists($file)) require $file;
    });
}

use JsonMachine\Items;
use JsonMachine\JsonDecoder\ExtJsonDecoder;

// ---------- Настройки ----------
// Файл экспорта Skype (экспорт → messages.json). Пароль — из переменных окружения.
define('JSON_FILE', __DIR__ . '/messages.json');
define('ITEMS_PER_PAGE', 10);          // бесед на страницу
define('AUTH_USER', getenv('VIEWER_USER') ?: 'demo');
define('AUTH_PASS', getenv('VIEWER_PASS') ?: 'demo');

// ---------- HTTP Basic Auth ----------
function authenticate(): void
{
    header('WWW-Authenticate: Basic realm="JSON Viewer"');
    header('HTTP/1.0 401 Unauthorized');
    echo 'Требуется авторизация.';
    exit;
}
if (
    !isset($_SERVER['PHP_AUTH_USER']) ||
    !isset($_SERVER['PHP_AUTH_PW'])  ||
    $_SERVER['PHP_AUTH_USER'] !== AUTH_USER ||
    $_SERVER['PHP_AUTH_PW']   !== AUTH_PASS
) {
    authenticate();
}

// ---------- Проверка файла ----------
if (!file_exists(JSON_FILE)) {
    http_response_code(500);
    echo "Файл " . htmlspecialchars(JSON_FILE) . " не найден.";
    exit;
}

// Владелец экспорта берётся из корня JSON (userId), чтобы помечать свои сообщения.
$exportHeader = file_get_contents(JSON_FILE, false, null, 0, 2048) ?: '';
$ownerId = '8:self';
if (preg_match('/"userId"\s*:\s*"([^"]+)"/', $exportHeader, $m)) {
    $ownerId = $m[1];
}

// ---------- Параметры поиска и пагинации ----------
$searchQuery = trim($_GET['search'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = ITEMS_PER_PAGE;

// ---------- Открытие потока ----------
$handle = fopen(JSON_FILE, 'r');
if (!$handle) {
    http_response_code(500);
    echo "Не удалось открыть файл.";
    exit;
}

// Определяем корневой тип и указатель
$firstChar = '';
while (!feof($handle)) {
    $chunk = fread($handle, 1);
    if ($chunk === false) break;
    $ch = trim($chunk);
    if ($ch !== '') {
        $firstChar = $chunk;
        break;
    }
}
rewind($handle);

$pointer = '';
if ($firstChar === '[') {
    $iterator = Items::fromStream($handle, ['decoder' => new ExtJsonDecoder(true)]);
} elseif ($firstChar === '{') {
    $pointer = $_GET['pointer'] ?? '/conversations';
    $iterator = Items::fromStream($handle, [
        'pointer' => $pointer,
        'decoder' => new ExtJsonDecoder(true)
    ]);
} else {
    fclose($handle);
    http_response_code(500);
    echo "JSON должен начинаться с '[' или '{'.";
    exit;
}

// ---------- Вспомогательная функция: обработка контактов и участников (без поиска) ----------
function processConversation(array &$conv): void
{
    // Участники
    if (isset($conv['threadProperties']['members'])) {
        $members = json_decode($conv['threadProperties']['members'], true);
        $conv['threadProperties']['members'] = is_array($members) ? $members : [];
    } else {
        $conv['threadProperties']['members'] = [];
    }

    // Сортировка сообщений
    if (isset($conv['MessageList']) && is_array($conv['MessageList'])) {
        usort($conv['MessageList'], function ($a, $b) {
            $timeA = strtotime($a['originalarrivaltime'] ?? '');
            $timeB = strtotime($b['originalarrivaltime'] ?? '');
            return $timeA <=> $timeB;
        });
    }

    // Сбор контактов (с проверкой на string)
    $contacts = [];
    if (!empty($conv['MessageList'])) {
        foreach ($conv['MessageList'] as $msg) {
            $from = $msg['from'] ?? null;
            $importedBy = $msg['properties']['importedBy']['Identifier'] ?? null;
            $identifier = $from ?? $importedBy;
            if ($identifier && !isset($contacts[$identifier])) {
                $displayName = $msg['displayName'] ?? '';
                $contacts[$identifier] = $displayName ?: $identifier;
            }

            $content = $msg['content'] ?? '';
            if (!is_string($content)) {
                continue;
            }

            if (preg_match('/<initiator>([^<]+)<\/initiator>/', $content, $m)) {
                $initiator = $m[1];
                if (!isset($contacts[$initiator])) {
                    $contacts[$initiator] = $initiator;
                }
            }
            if (preg_match_all('/<target>([^<]+)<\/target>/', $content, $targets)) {
                foreach ($targets[1] as $target) {
                    if (!isset($contacts[$target])) {
                        $contacts[$target] = $target;
                    }
                }
            }
            if (preg_match_all('/<part identity="([^"]+)"/', $content, $parts)) {
                foreach ($parts[1] as $partId) {
                    if (strpos($partId, ':') === false) {
                        $partId = '8:' . $partId;
                    }
                    if (!isset($contacts[$partId])) {
                        $contacts[$partId] = $partId;
                    }
                }
            }
        }
    }
    $conv['contacts'] = $contacts;
}

// ---------- Фильтр для поиска ----------
function matchesSearch(array $conv, string $query): bool
{
    $query = mb_strtolower($query);
    // название
    if (mb_strpos(mb_strtolower($conv['displayName'] ?? ''), $query) !== false) {
        return true;
    }
    // участники
    if (!empty($conv['threadProperties']['members'])) {
        foreach ($conv['threadProperties']['members'] as $member) {
            if (mb_strpos(mb_strtolower($member), $query) !== false) {
                return true;
            }
        }
    }
    // текст сообщений (только RichText/Text)
    if (!empty($conv['MessageList'])) {
        foreach ($conv['MessageList'] as $msg) {
            $type = $msg['messagetype'] ?? '';
            if (in_array($type, ['RichText', 'Text'])) {
                $text = $msg['content'] ?? '';
                if (is_string($text) && mb_strpos(mb_strtolower($text), $query) !== false) {
                    return true;
                }
            }
        }
    }
    return false;
}

// ---------- Основной цикл чтения и пагинация/поиск ----------
$pageConversations = [];
$foundCount = 0;      // счётчик подходящих (для поиска) или всех (для обычного)
$hasNext = false;
$finished = false;    // флаг завершения обхода файла
$offset = ($page - 1) * $perPage;
$searchMode = !empty($searchQuery);

try {
    foreach ($iterator as $conversation) {
        // Всегда обрабатываем контакты и пр. (даже если беседа не попадёт на страницу)
        processConversation($conversation);

        if ($searchMode) {
            // Проверяем, подходит ли беседа под поиск
            if (matchesSearch($conversation, $searchQuery)) {
                $foundCount++;
                if ($foundCount > $offset && $foundCount <= $offset + $perPage) {
                    $pageConversations[] = $conversation;
                }
                // Если набрали нужную страницу, проверяем, есть ли ещё
                if ($foundCount == $offset + $perPage) {
                    // Продолжаем цикл, но только чтобы проверить наличие следующего результата
                    // (не сохраняя данные). Можно выйти, если найдём ещё один.
                    // Для этого мы не прерываемся сразу, а продолжаем искать следующий подходящий.
                    // Но чтобы не замедлять, поступим проще: если набрали страницу, значит страница полна,
                    // предположим, что дальше может быть ещё (неточный hasNext). Для точного нужно проверить.
                    // В этом коде мы всё равно продолжаем цикл, потому что мы не знаем, что набрали страницу,
                    // пока не дойдём до условия ниже. Перепишем логику: будем собирать элементы, пока
                    // count($pageConversations) < $perPage. Как только набрали, можем продолжить цикл,
                    // пока не встретим следующий подходящий (чтобы установить hasNext=true) или конец файла.
                    // Реализуем это через флаг $collecting (завершён сбор страницы).
                }
            }
            // Оптимизация: если мы уже собрали страницу и нашли следующий подходящий, можно прервать
            if (count($pageConversations) == $perPage) {
                // Продолжаем, чтобы проверить наличие следующего (один раз)
                // Для этого используем флаг $needCheckNext = true.
                // Но сейчас мы внутри foreach, мы не можем просто так выйти после проверки.
                // Поэтому сделаем так: если $foundCount > $offset + $perPage, то мы знаем, что есть ещё элементы.
                // Но мы увеличиваем $foundCount для всех подходящих, поэтому когда он превысит $offset + $perPage,
                // это значит, что был по крайней мере один подходящий после нашей страницы.
                // Установим $hasNext = true и прервём цикл.
                if ($foundCount > $offset + $perPage) {
                    $hasNext = true;
                    break;
                }
            }
        } else {
            // Обычный режим (без поиска)
            $foundCount++;
            if ($foundCount > $offset && $foundCount <= $offset + $perPage) {
                $pageConversations[] = $conversation;
            }
            // Если набрали страницу и следующего элемента нет, прервёмся
            if ($foundCount == $offset + $perPage) {
                // Проверим, есть ли ещё элементы: сделаем ещё одну итерацию (если возможно)
                // Но так как мы уже внутри foreach, проще: если после набора страницы мы можем
                // продолжить, то проверим следующий элемент (без сохранения).
                // Для этого будем продолжать цикл, пока не встретим следующий элемент, и тогда $hasNext=true и break.
                // Выходим после набора страницы, если мы не хотим точного hasNext.
                // В старой версии мы просто выходили по break, и $hasNext = true.
                // Здесь сделаем так же: если $foundCount == $offset + $perPage, значит мы только что добавили последний элемент страницы.
                // После этого мы можем попытаться прочитать следующий элемент и, если он существует, установить $hasNext=true.
                // Читаем следующий элемент, не добавляя его.
                // Для этого нужно выйти из switch в итератор? Нет, проще: продолжаем цикл, но не добавляем.
                // Введём флаг $pageFull = true, и если он true, то следующий элемент просто установит $hasNext=true и прервёт.
                if ($foundCount == $offset + $perPage) {
                    // сейчас мы находимся на элементе, который только что добавили.
                    // попробуем получить следующий элемент вне цикла? Нет, остаёмся в цикле, но меняем логику:
                    // допустим, мы просто выйдем, и $hasNext останется false, но если мы не дошли до конца, мы не знаем.
                    // Поэтому для обычного режима оставим упрощённый вариант: $hasNext = (count($pageConversations) == $perPage);
                    // и прервём цикл.
                    // Но тогда мы не знаем точно, есть ли следующая страница, если элементов ровно $perPage.
                    // Это приемлемо.
                    $hasNext = true;
                    break;
                }
            }
        }

        // Если мы в обычном режиме и прошли все нужные элементы, можно прерваться
        if (!$searchMode && $foundCount > $offset + $perPage) {
            break;
        }
    }
} catch (\Exception $e) {
    fclose($handle);
    http_response_code(500);
    echo "Ошибка разбора JSON: " . htmlspecialchars($e->getMessage());
    exit;
}
fclose($handle);

// Пост-обработка для поиска: если мы не прервались и $foundCount <= $offset + $perPage, значит hasNext = false
if ($searchMode) {
    // Если мы вышли из цикла, не достигнув $foundCount > $offset + $perPage, значит больше нет результатов
    if (!$hasNext) {
        $hasNext = false;
    }
    $totalResults = $foundCount; // для отображения
} else {
    $hasNext = (count($pageConversations) == $perPage);
    $totalResults = $foundCount; // приблизительно (может быть больше, если прервались, но это не страшно)
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Skype чаты</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background: #f0f2f5;
            padding: 20px;
            color: #1c1e21;
        }
        .container { max-width: 1000px; margin: 0 auto; }
        .header {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        .header h1 { font-size: 24px; }
        .search-form {
            display: flex;
            gap: 8px;
        }
        .search-form input[type="text"] {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            width: 250px;
        }
        .search-form button {
            padding: 8px 16px;
            background: #1877f2;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
        }
        .search-form button:hover { background: #166fe5; }
        .nav { display: flex; gap: 10px; align-items: center; }
        .nav a {
            text-decoration: none;
            padding: 8px 16px;
            background: #1877f2;
            color: white;
            border-radius: 6px;
            font-weight: 500;
            transition: background 0.2s;
        }
        .nav a:hover { background: #166fe5; }
        .nav .current { background: #e4e6eb; color: #333; cursor: default; }
        .conversation {
            background: white;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .conv-header {
            background: #1877f2;
            color: white;
            padding: 15px 20px;
        }
        .conv-header h2 { font-size: 18px; margin-bottom: 5px; }
        .conv-header .meta { font-size: 14px; opacity: 0.9; margin-bottom: 3px; }
        .message-list {
            padding: 15px 20px;
            max-height: 600px;
            overflow-y: auto;
        }
        .message { display: flex; margin-bottom: 15px; }
        .message.owner { justify-content: flex-end; }
        .msg-bubble {
            max-width: 70%;
            padding: 10px 14px;
            border-radius: 18px;
            background: #f0f2f5;
            position: relative;
            word-wrap: break-word;
        }
        .message.owner .msg-bubble { background: #1877f2; color: white; }
        .sender { font-size: 13px; font-weight: 600; margin-bottom: 4px; color: #65676b; }
        .message.owner .sender { color: #e4e6eb; }
        .time { font-size: 11px; color: #65676b; margin-top: 4px; text-align: right; }
        .message.owner .time { color: #e4e6eb; }
        .system-msg { text-align: center; color: #65676b; font-style: italic; margin: 10px 0; }
        .system-msg .msg-bubble { background: none; max-width: 100%; padding: 0; }
        .empty { text-align: center; padding: 40px; color: #65676b; }
        a { color: #1877f2; text-decoration: none; }
        a:hover { text-decoration: underline; }
        .id { font-weight: normal; opacity: 0.8; font-size: 0.9em; }
        .results-info { margin-bottom: 15px; padding: 8px 15px; background: white; border-radius: 6px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>💬 Skype чаты</h1>
            <form class="search-form" method="get">
                <input type="hidden" name="pointer" value="<?= htmlspecialchars($pointer) ?>">
                <input type="text" name="search" value="<?= htmlspecialchars($searchQuery) ?>" placeholder="Поиск по чатам...">
                <button type="submit">🔍 Искать</button>
                <?php if (!empty($searchQuery)): ?>
                    <a href="?page=1&pointer=<?= urlencode($pointer) ?>" class="nav" style="background:none; color:#1877f2; padding:0; font-weight:normal; text-decoration:underline;">Сбросить</a>
                <?php endif; ?>
            </form>
            <div class="nav">
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($searchQuery) ?>&pointer=<?= urlencode($pointer) ?>">← Назад</a>
                <?php endif; ?>
                <span class="current">Страница <?= $page ?></span>
                <?php if ($hasNext): ?>
                    <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($searchQuery) ?>&pointer=<?= urlencode($pointer) ?>">Вперёд →</a>
                <?php else: ?>
                    <span class="current" style="background:#e9ecef;">Последняя</span>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($searchMode): ?>
            <div class="results-info">
                🔎 Найдено результатов: <strong><?= $totalResults ?></strong>
            </div>
        <?php endif; ?>

        <?php if (empty($pageConversations)): ?>
            <div class="empty">
                <?= $searchMode ? 'Ничего не найдено.' : 'Нет бесед для отображения.' ?>
            </div>
        <?php else: ?>
            <?php foreach ($pageConversations as $conv): ?>
                <?php
                    $title = htmlspecialchars($conv['displayName'] ?? 'Без названия');
                    $members = $conv['threadProperties']['members'] ?? [];
                    $contacts = $conv['contacts'] ?? [];
                    $messages = $conv['MessageList'] ?? [];
                ?>
                <div class="conversation">
                    <div class="conv-header">
                        <h2><?= $title ?></h2>
                        <?php if (!empty($members)): ?>
                            <div class="meta">👥 Участники: <?= htmlspecialchars(implode(', ', $members)) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($contacts)): ?>
                            <div class="meta">
                                📇 Контакты:
                                <?php
                                $contactParts = [];
                                foreach ($contacts as $id => $name) {
                                    $display = $name === $id ? $id : "$name ($id)";
                                    $contactParts[] = htmlspecialchars($display);
                                }
                                echo implode(', ', $contactParts);
                                ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="message-list">
                        <?php if (empty($messages)): ?>
                            <div class="system-msg"><span class="msg-bubble">Нет сообщений</span></div>
                        <?php else: ?>
                            <?php foreach ($messages as $msg): ?>
                                <?php
                                    $from = $msg['from'] ?? '';
                                    $isOwner = ($from === $ownerId);
                                    $content = $msg['content'] ?? '';
                                    $time = date('d.m.Y H:i', strtotime($msg['originalarrivaltime'] ?? ''));
                                    $type = $msg['messagetype'] ?? '';

                                    // Отправитель
                                    if ($isOwner) {
                                        $senderDisplay = 'Вы';
                                    } else {
                                        $displayName = $msg['displayName'] ?? '';
                                        if (!empty($displayName)) {
                                            $senderDisplay = htmlspecialchars($displayName);
                                            if (!empty($from)) {
                                                $senderDisplay .= ' <span class="id">(' . htmlspecialchars($from) . ')</span>';
                                            }
                                        } elseif (!empty($from)) {
                                            $senderDisplay = htmlspecialchars($from);
                                        } else {
                                            $senderDisplay = 'Участник';
                                        }
                                    }

                                    // Системные сообщения
                                    $systemText = '';
                                    if (is_string($content)) {
                                        if ($type === 'ThreadActivity/AddMember') {
                                            if (preg_match_all('/<target>([^<]+)<\/target>/', $content, $targets)) {
                                                $added = array_map(function($id){ return htmlspecialchars($id); }, $targets[1]);
                                                $systemText = '👋 Добавлены участники: ' . implode(', ', $added);
                                            }
                                        } elseif ($type === 'ThreadActivity/TopicUpdate') {
                                            if (preg_match('/<value>([^<]+)<\/value>/', $content, $val)) {
                                                $systemText = '📝 Тема изменена на: ' . htmlspecialchars($val[1]);
                                            }
                                        } elseif ($type === 'ThreadActivity/HistoryDisclosedUpdate') {
                                            if (preg_match('/<value>([^<]+)<\/value>/', $content, $val)) {
                                                $disclosed = ($val[1] === 'true') ? 'открыта' : 'скрыта';
                                                $systemText = '🔒 История переписки теперь ' . $disclosed;
                                            }
                                        } elseif (strpos($content, '<partlist') !== false) {
                                            if (preg_match('/type="started"/', $content)) {
                                                $systemText = '📞 Начат звонок';
                                            } elseif (preg_match('/type="ended"/', $content)) {
                                                $systemText = '📞 Звонок завершён';
                                            }
                                            if (preg_match_all('/<part identity="([^"]+)"[^>]*>(.*?)<\/part>/', $content, $parts, PREG_SET_ORDER)) {
                                                $participants = [];
                                                foreach ($parts as $p) {
                                                    $name = '';
                                                    if (preg_match('/<name>([^<]+)<\/name>/', $p[2], $nm)) {
                                                        $name = $nm[1];
                                                    }
                                                    $participants[] = $name ?: $p[1];
                                                }
                                                $systemText .= ' (' . implode(', ', $participants) . ')';
                                            }
                                        }
                                    }

                                    if ($systemText) {
                                        echo '<div class="system-msg"><span class="msg-bubble">' . $systemText . '</span></div>';
                                    } else {
                                        $cleanContent = is_string($content) ? htmlspecialchars($content) : '[вложенные данные]';
                                    ?>
                                        <div class="message <?= $isOwner ? 'owner' : '' ?>">
                                            <div class="msg-bubble">
                                                <div class="sender"><?= $senderDisplay ?></div>
                                                <div class="text"><?= nl2br($cleanContent) ?></div>
                                                <div class="time"><?= $time ?></div>
                                            </div>
                                        </div>
                                    <?php
                                    }
                                ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <div class="header" style="justify-content: center; margin-top: 10px;">
            <div class="nav">
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($searchQuery) ?>&pointer=<?= urlencode($pointer) ?>">← Назад</a>
                <?php endif; ?>
                <span class="current">Страница <?= $page ?></span>
                <?php if ($hasNext): ?>
                    <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($searchQuery) ?>&pointer=<?= urlencode($pointer) ?>">Вперёд →</a>
                <?php else: ?>
                    <span class="current" style="background:#e9ecef;">Последняя</span>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>