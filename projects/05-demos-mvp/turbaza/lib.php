<?php
session_status() === PHP_SESSION_ACTIVE || session_start();

define('DATA_DIR', __DIR__ . '/data');
const DEMO_CLIENT_ID = 5;
const DEMO_GUEST_ID = 1;
const BASE_NAME = 'Турбаза «Белая Гора»';

const ROLES = [
  'admin'       => 'Администратор базы',
  'manager'     => 'Менеджер по бронированию',
  'operator'    => 'Администратор приёма',
  'housekeeper' => 'Горничная',
  'client'      => 'Гость',
];

const STATUSES = [
  'new'         => 'Новая бронь',
  'confirmed'   => 'Подтверждена',
  'paid'        => 'Оплачена',
  'checked_in'  => 'Заселение',
  'checked_out' => 'Выезд',
  'cancelled'   => 'Отменена',
];

const STATUS_COLOR = [
  'new'         => '#2563eb',
  'confirmed'   => '#0d9488',
  'paid'        => '#16a34a',
  'checked_in'  => '#7c3aed',
  'checked_out' => '#475569',
  'cancelled'   => '#dc2626',
];

const TRANSITIONS = [
  'new'         => ['confirmed', 'paid', 'cancelled'],
  'confirmed'   => ['paid', 'cancelled'],
  'paid'        => ['checked_in', 'cancelled'],
  'checked_in'  => ['checked_out'],
  'checked_out' => [],
  'cancelled'   => [],
];

const PAGE_ROLES = [
  'dashboard'  => ['admin', 'manager', 'operator', 'housekeeper', 'client'],
  'catalog'    => ['admin', 'manager', 'operator', 'housekeeper', 'client'],
  'cottage'    => ['admin', 'manager', 'operator', 'housekeeper', 'client'],
  'booking'    => ['admin', 'manager', 'operator', 'client'],
  'booking_view'=> ['admin', 'manager', 'operator', 'housekeeper', 'client'],
  'bookings'   => ['admin', 'manager', 'operator', 'housekeeper'],
  'calendar'   => ['admin', 'manager', 'operator', 'client'],
  'services'   => ['admin', 'manager'],
  'guests'     => ['admin', 'manager', 'operator'],
  'settings'   => ['admin'],
  'reports'    => ['admin', 'manager'],
];

const PAGE_TITLES = [
  'dashboard'  => 'Главная',
  'catalog'    => 'Домики',
  'cottage'    => 'Домик',
  'booking'    => 'Бронирование',
  'booking_view' => 'Бронь',
  'bookings'   => 'Брони',
  'calendar'   => 'Календарь',
  'services'   => 'Доп. услуги',
  'guests'     => 'Гости',
  'settings'   => 'Тарифы и сезоны',
  'reports'    => 'Отчёты',
];

const NAV_LABELS = [
  'dashboard'  => 'Главная',
  'catalog'    => 'Домики',
  'calendar'   => 'Календарь',
  'bookings'   => 'Брони',
  'services'   => 'Доп. услуги',
  'guests'     => 'Гости',
  'settings'   => 'Тарифы',
  'reports'    => 'Отчёты',
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
  return isset($_SESSION['uid']) ? db_find('users', $_SESSION['uid']) : null;
}

function require_role($roles)
{
  $u = current_user();
  if (!$u || !in_array($u['role'], $roles, true)) redirect('dashboard', 'Нет доступа');
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

function status_badge($s)
{
  $c = isset(STATUS_COLOR[$s]) ? STATUS_COLOR[$s] : '#64748b';
  $t = isset(STATUSES[$s]) ? STATUSES[$s] : $s;
  return '<span class="badge" style="background:' . $c . '">' . esc($t) . '</span>';
}

function cottage_name($id)
{
  $c = db_find('cottages', $id);
  return $c ? $c['name'] : '—';
}

function guest_name($id)
{
  $g = db_find('guests', $id);
  return $g ? $g['name'] : '—';
}

function season_for_date($date)
{
  $md = substr((string)$date, 5, 5);
  foreach (db_all('seasons') as $s) {
    $from = $s['from'];
    $to = $s['to'];
    if (strcmp($from, $to) <= 0) {
      if (strcmp($md, $from) >= 0 && strcmp($md, $to) <= 0) return $s;
    } else {
      if (strcmp($md, $from) >= 0 || strcmp($md, $to) <= 0) return $s;
    }
  }
  return null;
}

function night_price($cottage, $date)
{
  $s = season_for_date($date);
  $mult = $s ? (float)$s['mult'] : 1.0;
  return (int)round((float)$cottage['price'] * $mult);
}

function season_label($date)
{
  $s = season_for_date($date);
  return $s ? $s['name'] : '—';
}

function nights_between($check_in, $check_out)
{
  return max(0, (int)round((strtotime($check_out) - strtotime($check_in)) / 86400));
}

function night_total_for($cottage, $check_in, $check_out)
{
  $t = 0;
  $ts = strtotime($check_in);
  $te = strtotime($check_out);
  for ($d = $ts; $d < $te; $d += 86400) {
    $t += night_price($cottage, date('Y-m-d', $d));
  }
  return $t;
}

function service_amount($svc, $nights, $guests)
{
  $price = (int)$svc['price'];
  if ($svc['per'] === 'night') return $price * max(0, $nights);
  if ($svc['per'] === 'person_night') return $price * max(0, $guests) * max(0, $nights);
  return $price;
}

function booking_price($cottage, $check_in, $check_out, $guests, $svc_ids)
{
  $nights = nights_between($check_in, $check_out);
  $night_total = night_total_for($cottage, $check_in, $check_out);
  $extras = 0;
  foreach ((array)$svc_ids as $id) {
    $svc = db_find('services', $id);
    if (!$svc) continue;
    $extras += service_amount($svc, $nights, $guests);
  }
  return ['nights' => $nights, 'night_total' => $night_total, 'extras_total' => $extras, 'total' => $night_total + $extras];
}

function svc_snapshot($svc, $amount)
{
  return ['id' => (int)$svc['id'], 'name' => $svc['name'], 'price' => (int)$svc['price'], 'per' => $svc['per'], 'amount' => $amount];
}

function is_available($cottage_id, $check_in, $check_out, $ignore_booking_id = 0)
{
  foreach (db_all('bookings') as $b) {
    if ((int)$b['cottage_id'] !== (int)$cottage_id) continue;
    if ((int)$b['id'] === (int)$ignore_booking_id) continue;
    if ($b['status'] === 'cancelled') continue;
    if (strcmp($b['check_in'], $check_out) < 0 && strcmp($b['check_out'], $check_in) > 0) return false;
  }
  return true;
}

function occupied_dates($cottage_id)
{
  $dates = [];
  foreach (db_all('bookings') as $b) {
    if ((int)$b['cottage_id'] !== (int)$cottage_id) continue;
    if ($b['status'] === 'cancelled') continue;
    $ts = strtotime($b['check_in']);
    $te = strtotime($b['check_out']);
    for ($d = $ts; $d < $te; $d += 86400) $dates[date('Y-m-d', $d)] = $b;
  }
  return $dates;
}

function scoped_bookings($u)
{
  $all = db_all('bookings');
  if ($u['role'] === 'client') {
    return array_values(array_filter($all, function ($b) { return (int)$b['guest_id'] === DEMO_GUEST_ID; }));
  }
  if ($u['role'] === 'housekeeper') {
    return array_values(array_filter($all, function ($b) { return in_array($b['status'], ['paid', 'confirmed', 'checked_in'], true); }));
  }
  return $all;
}

function nav_for_role($role)
{
  $nav = [];
  foreach (NAV_LABELS as $page => $label) {
    if (in_array($role, PAGE_ROLES[$page], true)) $nav[$page] = $label;
  }
  return $nav;
}

function photo_block($cottage, $size = '')
{
  $ch = substr($cottage['name'], strpos($cottage['name'], '«') + 1, 1);
  return '<div class="ph ' . $size . '" style="background:linear-gradient(135deg,' . $cottage['color'] . ' 0%,#16352a 100%)"><span>' . esc($ch) . '</span></div>';
}

function layout_header($page, $user)
{
  $nav = nav_for_role($user['role']);
  $msg = isset($_GET['msg']) ? $_GET['msg'] : '';
  echo '<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>' . esc(BASE_NAME) . ' — ' . esc(PAGE_TITLES[$page]) . '</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style.css">
</head>
<body>
<div class="app">
<aside class="sidebar">
  <div class="logo"><span class="logo-mark">БГ</span>Белая Гора</div>
  <nav>';
  foreach ($nav as $key => $label) {
    echo '<a href="index.php?page=' . $key . '"' . ($key === $page ? ' class="active"' : '') . '>' . esc($label) . '</a>';
  }
  echo '</nav>
  <div class="side-foot">Демо-версия веб-сервиса<br>бронирования домиков</div>
</aside>
<main class="main">
  <header class="topbar">
    <h1>' . esc(PAGE_TITLES[$page]) . '</h1>
    <div class="userbox">
      <strong>' . esc($user['name']) . '</strong>
      <span>' . esc(ROLES[$user['role']]) . '</span>
      <a class="btn btn-outline" href="index.php?act=logout">Выйти</a>
    </div>
  </header>';
  if ($msg) echo '<div class="flash">' . esc($msg) . '</div>';
  echo '<div class="content">';
}

function layout_footer()
{
  echo '</div>
</main>
</div>
</body>
</html>';
}

function show_login($err = null)
{
  $msg = $err ?: (isset($_GET['msg']) ? $_GET['msg'] : '');
  $demos = [
    ['role' => 'admin', 'label' => 'Администратор', 'desc' => 'Справочники, тарифы, отчёты'],
    ['role' => 'manager', 'label' => 'Менеджер', 'desc' => 'Брони, гости, подтверждение'],
    ['role' => 'operator', 'label' => 'Администратор приёма', 'desc' => 'Заселение и выезд гостей'],
    ['role' => 'housekeeper', 'label' => 'Горничная', 'desc' => 'Подготовка домиков'],
    ['role' => 'client', 'label' => 'Гость', 'desc' => 'Онлайн-бронирование и оплата'],
  ];
  echo '<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>' . esc(BASE_NAME) . ' — вход</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style.css">
</head>
<body class="login-body">
<div class="login-card">
  <div class="login-logo"><span class="logo-mark logo-lg">БГ</span></div>
  <h1>Белая Гора</h1>
  <p class="muted">Турбаза на берегу озера — онлайн-бронирование домиков</p>';
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
  <p class="muted small">Демо-логины: admin/admin, manager/manager, operator/operator, housekeeper/housekeeper, client/client</p>
</div>
</body>
</html>';
  exit;
}
