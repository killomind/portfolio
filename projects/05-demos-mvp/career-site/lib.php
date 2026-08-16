<?php
session_status() === PHP_SESSION_ACTIVE || session_start();

define('DATA_DIR', __DIR__ . '/data');

const SITE_NAME = 'ГРК «Горизонт» — карьера';

const ROLES = [
  'guest'     => 'Гость',
  'candidate' => 'Соискатель',
  'employer'  => 'Компания-работодатель',
  'hr'        => 'HR-менеджер',
  'admin'     => 'Администратор',
];

const MODERATION = [
  'draft'        => 'Черновик',
  'on_moderation'=> 'На модерации',
  'published'    => 'Опубликована',
  'rejected'     => 'Отклонена',
];

const MODERATION_COLOR = [
  'draft'        => '#64748b',
  'on_moderation'=> '#f59e0b',
  'published'    => '#16a34a',
  'rejected'     => '#dc2626',
];

const RESP_STATUS = [
  'new'       => 'Новый',
  'interview' => 'Собеседование',
  'offer'     => 'Оффер',
  'hired'     => 'Нанят',
  'rejected'  => 'Отклонён',
];

const RESP_COLOR = [
  'new'       => '#2563eb',
  'interview' => '#f59e0b',
  'offer'     => '#7c3aed',
  'hired'     => '#16a34a',
  'rejected'  => '#dc2626',
];

const SOURCE_COLOR = [
  'Поток'     => '#0ea5e9',
  'HeadHunter'=> '#dc2626',
  'На сайте'  => '#16a34a',
];

const ROUTE_LABELS = [
  'worker' => 'Рабочий специалист',
  'engineer' => 'Инженер',
  'it' => 'IT-специалист',
  'young' => 'Молодой специалист',
  'manager' => 'Руководитель',
];

const DIRECTIONS = [
  'Горное дело', 'Инженерное дело', 'IT и автоматизация', 'Электротехника',
  'Рабочие специальности', 'Наука и аналитика', 'Геология', 'HR', 'Маркетинг', 'Охрана труда и ИБ',
];

const PAGE_ROLES = [
  'home'              => ['guest', 'candidate', 'employer', 'hr', 'admin'],
  'vacancies'         => ['guest', 'candidate', 'employer', 'hr', 'admin'],
  'vacancy'           => ['guest', 'candidate', 'employer', 'hr', 'admin'],
  'enterprises'       => ['guest', 'candidate', 'employer', 'hr', 'admin'],
  'enterprise'        => ['guest', 'candidate', 'employer', 'hr', 'admin'],
  'games'             => ['guest', 'candidate', 'employer', 'hr', 'admin'],
  'game_route'        => ['guest', 'candidate', 'employer', 'hr', 'admin'],
  'game_test'         => ['guest', 'candidate', 'employer', 'hr', 'admin'],
  'moderation'        => ['admin', 'hr'],
  'company_vacancies' => ['admin', 'employer'],
  'responses'         => ['admin', 'hr', 'employer'],
  'my'                => ['candidate'],
  'stats'             => ['admin'],
];

const PUBLIC_PAGES = ['home', 'vacancies', 'vacancy', 'enterprises', 'enterprise', 'games', 'game_route', 'game_test'];

const PAGE_TITLES = [
  'home'              => 'Главная',
  'vacancies'         => 'Поиск вакансий',
  'vacancy'           => 'Вакансия',
  'enterprises'       => 'Предприятия',
  'enterprise'        => 'Предприятие',
  'games'             => 'Игровые механики',
  'game_route'        => 'Карьерный маршрут',
  'game_test'         => 'Тест на совместимость',
  'moderation'        => 'Модерация вакансий',
  'company_vacancies' => 'Мои вакансии',
  'responses'         => 'Отклики',
  'my'                => 'Мои отклики и результаты',
  'stats'             => 'Статистика',
];

const NAV_LABELS = [
  'home'       => 'Главная',
  'vacancies'  => 'Вакансии',
  'enterprises'=> 'Предприятия',
  'games'      => 'Игровые механики',
  'moderation' => 'Модерация',
  'company_vacancies' => 'Мои вакансии',
  'responses'  => 'Отклики',
  'my'         => 'Мой профиль',
  'stats'      => 'Статистика',
];

function db_all($name)
{
  $f = DATA_DIR . '/' . $name . '.json';
  if (!is_file($f)) return [];
  $d = json_decode(file_get_contents($f), true);
  return is_array($d) ? $d : [];
}

function db_save($name, $arr)
{
  file_put_contents(DATA_DIR . '/' . $name . '.json', json_encode($arr, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
}

function db_find($name, $id)
{
  foreach (db_all($name) as $r) {
    if ((int)$r['id'] === (int)$id) return $r;
  }
  return null;
}

function db_insert($name, $row)
{
  $a = db_all($name);
  $max = 0;
  foreach ($a as $r) if ((int)$r['id'] > $max) $max = (int)$r['id'];
  $row['id'] = $max + 1;
  $a[] = $row;
  db_save($name, $a);
  return $row['id'];
}

function db_update($name, $id, $row)
{
  $a = db_all($name);
  foreach ($a as $i => $r) {
    if ((int)$r['id'] === (int)$id) {
      $a[$i] = array_merge($r, $row);
      $a[$i]['id'] = (int)$id;
    }
  }
  db_save($name, $a);
}

function db_delete($name, $id)
{
  $a = db_all($name);
  foreach ($a as $i => $r) if ((int)$r['id'] === (int)$id) unset($a[$i]);
  db_save($name, array_values($a));
}

function current_user()
{
  if (!isset($_SESSION['uid'])) return null;
  return db_find('users', $_SESSION['uid']);
}

function require_role($roles)
{
  $u = current_user();
  if (!$u || !in_array($u['role'], $roles, true)) redirect('home', 'Нет доступа');
}

function esc($s)
{
  return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function money($n)
{
  return number_format((float)$n, 0, '.', ' ') . ' ₽';
}

function now_ts()
{
  return date('Y-m-d H:i:s');
}

function redirect($page, $msg = null)
{
  $q = 'index.php?page=' . $page;
  if ($msg) $q .= '&msg=' . rawurlencode($msg);
  header('Location: ' . $q);
  exit;
}

function enterprise($id)
{
  return db_find('enterprises', $id);
}

function enterprise_name($id)
{
  $e = enterprise($id);
  return $e ? $e['name'] : '—';
}

function vacancy_salary($v)
{
  if ((int)$v['salary_min'] > 0 && (int)$v['salary_max'] > 0) {
    return money($v['salary_min']) . ' – ' . money($v['salary_max']);
  }
  if ((int)$v['salary_max'] > 0) return 'до ' . money($v['salary_max']);
  return money($v['salary_min']);
}

function moderation_badge($s)
{
  $c = isset(MODERATION_COLOR[$s]) ? MODERATION_COLOR[$s] : '#64748b';
  $t = isset(MODERATION[$s]) ? MODERATION[$s] : $s;
  return '<span class="badge" style="background:' . $c . '">' . esc($t) . '</span>';
}

function resp_badge($s)
{
  $c = isset(RESP_COLOR[$s]) ? RESP_COLOR[$s] : '#64748b';
  $t = isset(RESP_STATUS[$s]) ? RESP_STATUS[$s] : $s;
  return '<span class="badge" style="background:' . $c . '">' . esc($t) . '</span>';
}

function source_badge($s)
{
  $c = isset(SOURCE_COLOR[$s]) ? SOURCE_COLOR[$s] : '#64748b';
  return '<span class="badge" style="background:' . $c . '">' . esc($s) . '</span>';
}

function nav_for_role($role)
{
  $nav = [];
  foreach (NAV_LABELS as $page => $label) {
    if (in_array($role, PAGE_ROLES[$page], true)) $nav[$page] = $label;
  }
  return $nav;
}

function published_vacancies()
{
  return array_values(array_filter(db_all('vacancies'), function ($v) { return $v['status'] === 'published'; }));
}

function visible_vacancies($u)
{
  if (!$u) return published_vacancies();
  if ($u['role'] === 'admin' || $u['role'] === 'hr') return db_all('vacancies');
  if ($u['role'] === 'employer') {
    return array_values(array_filter(db_all('vacancies'), function ($v) use ($u) {
      return (int)$v['enterprise_id'] === (int)$u['company_id'] || $v['status'] === 'published';
    }));
  }
  return published_vacancies();
}

function map_svg($active_id = 0)
{
  $enterprises = db_all('enterprises');
  $html = '<svg class="grk-map" viewBox="0 0 100 100" preserveAspectRatio="none">';
  $html .= '<defs><radialGradient id="mapGlow" cx="50%" cy="50%" r="50%"><stop offset="0%" stop-color="#eef2ff"/><stop offset="100%" stop-color="#e2e8f0"/></radialGradient></defs>';
  $html .= '<rect x="0" y="0" width="100" height="100" rx="2" fill="url(#mapGlow)"/>';
  $html .= '<pattern id="grid" width="10" height="10" patternUnits="userSpaceOnUse"><path d="M10 0L0 0 0 10" fill="none" stroke="#c7d2fe" stroke-width="0.15"/></pattern>';
  $html .= '<rect x="0" y="0" width="100" height="100" fill="url(#grid)"/>';
  foreach ($enterprises as $e) {
    $active = ((int)$active_id === (int)$e['id']);
    $r = $active ? 3.4 : 2.4;
    $html .= '<g class="map-marker' . ($active ? ' active' : '') . '" data-name="' . esc($e['city'] . ', ' . $e['region']) . '" data-id="' . (int)$e['id'] . '">';
    $html .= '<circle cx="' . $e['map_x'] . '" cy="' . $e['map_y'] . '" r="' . $r . '" fill="' . $e['color'] . '" opacity="0.25"><animate attributeName="r" values="' . $r . ';' . ($r + 2) . ';' . $r . '" dur="2.5s" repeatCount="indefinite"/></circle>';
    $html .= '<circle cx="' . $e['map_x'] . '" cy="' . $e['map_y'] . '" r="1.5" fill="' . $e['color'] . '" stroke="#fff" stroke-width="0.4"/>';
    $html .= '</g>';
  }
  $html .= '</svg>';
  $html .= '<div class="map-legend">';
  $html .= '<div class="map-tip"></div>';
  foreach ($enterprises as $e) {
    $html .= '<a class="map-chip' . ((int)$active_id === (int)$e['id'] ? ' active' : '') . '" style="--dot:' . $e['color'] . '" href="index.php?page=enterprise&id=' . (int)$e['id'] . '"><i></i>' . esc($e['short']) . '</a>';
  }
  $html .= '</div>';
  return $html;
}

function layout_header($page, $user)
{
  $nav = nav_for_role($user ? $user['role'] : 'guest');
  $msg = isset($_GET['msg']) ? $_GET['msg'] : '';
  $isPub = in_array($page, PUBLIC_PAGES, true);
  echo '<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>' . esc(SITE_NAME . ' — ' . PAGE_TITLES[$page]) . '</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<div class="topnav">
  <div class="wrap topnav-in">
    <a class="brand" href="index.php?page=home">⛰ ГРК «Горизонт»<span>Карьерный портал</span></a>
    <nav class="main-nav">';
  foreach ($nav as $key => $label) {
    echo '<a href="index.php?page=' . $key . '"' . ($key === $page ? ' class="active"' : '') . '>' . esc($label) . '</a>';
  }
  echo '</nav>
    <div class="userbox">';
  if ($user) {
    echo '<div class="who"><strong>' . esc($user['name']) . '</strong><span>' . esc(ROLES[$user['role']]) . '</span></div>';
    if ($user['role'] === 'candidate') echo '<a class="btn btn-sm" href="index.php?page=my">Профиль</a>';
    echo '<a class="btn btn-sm btn-outline" href="index.php?act=logout">Выйти</a>';
  } else {
    echo '<a class="btn btn-sm" href="index.php?login=1">Войти</a>';
  }
  echo '</div>
  </div>
</div>
<div class="wrap">';
  if ($msg) echo '<div class="flash">' . esc($msg) . '</div>';
  echo '<div class="content">';
}

function layout_footer()
{
  echo '</div>
</div>
<div class="foot">
  <div class="wrap">Демо-версия карьерного портала ГРК «Горизонт». Все данные — вымышленные.<br>
  Интеграции: HR-система «Поток», HeadHunter · Авторизация по SMS и Telegram (демо: быстрый вход по ролям)</div>
</div>
<script>
document.addEventListener("click", function (ev) {
  var m = ev.target.closest ? ev.target.closest(".map-marker") : null;
  if (m) {
    var tip = document.querySelector(".map-tip");
    if (tip) { tip.textContent = m.dataset.name; tip.style.display = "block"; tip.style.left = "50%"; tip.style.transform = "translateX(-50%)"; }
  }
});
</script>
</body>
</html>';
}

function show_login($err = null)
{
  $msg = $err ?: (isset($_GET['msg']) ? $_GET['msg'] : '');
  $demos = [
    ['role' => 'admin', 'label' => 'Администратор', 'desc' => 'Модерация, интеграции, статистика'],
    ['role' => 'hr', 'label' => 'HR-менеджер', 'desc' => 'Модерация и публикация вакансий'],
    ['role' => 'employer', 'label' => 'Компания-работодатель', 'desc' => 'Свои вакансии и отклики'],
    ['role' => 'candidate', 'label' => 'Соискатель', 'desc' => 'Поиск, отклики, тесты и маршруты'],
    ['role' => 'guest', 'label' => 'Гость', 'desc' => 'Просмотр каталога без авторизации'],
  ];
  echo '<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>' . esc(SITE_NAME . ' — вход') . '</title>
<link rel="stylesheet" href="style.css">
</head>
<body class="login-body">
<div class="login-card">
  <div class="login-logo">⛰</div>
  <h1>ГРК «Горизонт»</h1>
  <p class="muted">Карьерный портал холдинга — демо-версия</p>';
  if ($msg) echo '<div class="flash">' . esc($msg) . '</div>';
  echo '<div class="login-grid">';
  foreach ($demos as $d) {
    echo '<form method="post" action="index.php">
      <input type="hidden" name="act" value="login">
      <input type="hidden" name="role" value="' . $d['role'] . '">
      <button class="demo-btn" type="submit">
        <strong>' . $d['label'] . '</strong>
        <span>' . $d['desc'] . '</span>
      </button>
    </form>';
  }
  echo '</div>
  <div class="divider">или вход по логину</div>
  <form method="post" action="index.php" class="login-form">
    <input type="hidden" name="act" value="login">
    <label>Логин</label>
    <input type="text" name="login" autocomplete="username">
    <label>Пароль</label>
    <input type="password" name="pass" autocomplete="current-password">
    <button class="btn" type="submit">Войти</button>
  </form>
  <p class="muted small">Демо-логины: admin/admin, hr/hr, employer/employer, employer2/employer2, candidate/candidate, guest/guest.<br>
  В публичной части (вакансии, предприятия, тесты) можно работать без входа.</p>
</div>
</body>
</html>';
  exit;
}
