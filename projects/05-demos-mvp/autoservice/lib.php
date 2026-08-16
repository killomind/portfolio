<?php
session_status() === PHP_SESSION_ACTIVE || session_start();

define('DATA_DIR', __DIR__ . '/data');
const DEMO_CLIENT_ID = 1;

const ROLES = [
  'admin'    => 'Администратор сети',
  'manager'  => 'Руководитель филиала',
  'advisor'  => 'Мастер-приёмщик',
  'mechanic' => 'Механик',
  'client'   => 'Клиент',
];

const STATUSES = [
  'new'         => 'Новая заявка',
  'confirmed'   => 'Подтверждена',
  'diagnostics' => 'Диагностика',
  'work'        => 'В ремонте',
  'ready'       => 'Готов к выдаче',
  'issued'      => 'Выдан',
  'cancelled'   => 'Отменён',
];

const STATUS_COLOR = [
  'new'         => '#2563eb',
  'confirmed'   => '#64748b',
  'diagnostics' => '#f59e0b',
  'work'        => '#7c3aed',
  'ready'       => '#16a34a',
  'issued'      => '#1e293b',
  'cancelled'   => '#dc2626',
];

const TRANSITIONS = [
  'new'         => ['confirmed', 'cancelled'],
  'confirmed'   => ['diagnostics', 'cancelled'],
  'diagnostics' => ['work', 'ready', 'cancelled'],
  'work'        => ['ready', 'cancelled'],
  'ready'       => ['issued'],
  'issued'      => [],
  'cancelled'   => [],
];

const PAGE_ROLES = [
  'dashboard' => ['admin', 'manager', 'advisor', 'mechanic', 'client'],
  'orders'    => ['admin', 'manager', 'advisor', 'mechanic'],
  'order_edit'=> ['admin', 'manager', 'advisor'],
  'order_view'=> ['admin', 'manager', 'advisor', 'mechanic', 'client'],
  'calendar'  => ['admin', 'manager', 'advisor', 'client'],
  'clients'   => ['admin', 'manager', 'advisor'],
  'cars'      => ['admin', 'manager', 'advisor', 'client'],
  'warehouse' => ['admin', 'manager', 'advisor'],
  'catalog'   => ['admin', 'manager', 'advisor', 'mechanic'],
  'reports'   => ['admin', 'manager'],
  'employees' => ['admin', 'manager'],
];

const PAGE_TITLES = [
  'dashboard'  => 'Главная',
  'orders'     => 'Заказы',
  'order_edit' => 'Заказ',
  'order_view' => 'Заказ',
  'calendar'   => 'Календарь записи',
  'clients'    => 'Клиенты',
  'cars'       => 'Автомобили',
  'warehouse'  => 'Склад',
  'catalog'    => 'Работы и цены',
  'reports'    => 'Отчёты',
  'employees'  => 'Сотрудники',
];

const NAV_LABELS = [
  'dashboard'  => 'Главная',
  'orders'     => 'Заказы',
  'calendar'   => 'Календарь записи',
  'clients'    => 'Клиенты',
  'cars'       => 'Автомобили',
  'warehouse'  => 'Склад',
  'catalog'    => 'Работы и цены',
  'reports'    => 'Отчёты',
  'employees'  => 'Сотрудники',
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

function branch_name($id)
{
  $b = db_find('branches', $id);
  return $b ? $b['name'] : '—';
}

function user_name($id)
{
  $u = db_find('users', $id);
  return $u ? $u['name'] : '—';
}

function status_badge($s)
{
  $c = isset(STATUS_COLOR[$s]) ? STATUS_COLOR[$s] : '#64748b';
  $t = isset(STATUSES[$s]) ? STATUSES[$s] : $s;
  return '<span class="badge" style="background:' . $c . '">' . esc($t) . '</span>';
}

function nav_for_role($role)
{
  $nav = [];
  foreach (NAV_LABELS as $page => $label) {
    if (in_array($role, PAGE_ROLES[$page], true)) $nav[$page] = $label;
  }
  return $nav;
}

function scoped_orders($u)
{
  $orders = db_all('orders');
  $role = $u['role'];
  if ($role === 'client') return array_values(array_filter($orders, function ($o) { return (int)$o['client_id'] === DEMO_CLIENT_ID; }));
  if ($role === 'mechanic') return array_values(array_filter($orders, function ($o) use ($u) { return (int)$o['mechanic_id'] === (int)$u['id']; }));
  if ($role === 'manager') return array_values(array_filter($orders, function ($o) use ($u) { return (int)$o['branch_id'] === (int)$u['branch_id']; }));
  return $orders;
}

function scoped_parts($u)
{
  $parts = db_all('parts');
  if ((int)$u['branch_id'] > 0) return array_values(array_filter($parts, function ($p) use ($u) { return (int)$p['branch_id'] === (int)$u['branch_id']; }));
  return $parts;
}

function adjust_part($part_id, $delta, $user_name, $type, $order_id = 0, $note = '')
{
  $p = db_find('parts', $part_id);
  if (!$p) return false;
  $p['qty'] = max(0, (int)$p['qty'] + $delta);
  db_update('parts', $part_id, $p);
  db_insert('movements', [
    'at' => now_ts(),
    'user' => $user_name,
    'part_id' => $part_id,
    'part_name' => $p['name'],
    'branch_id' => $p['branch_id'],
    'type' => $type,
    'qty' => abs($delta),
    'order_id' => $order_id,
    'note' => $note,
  ]);
  return true;
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
<title>Автосервис 360 — ' . esc(PAGE_TITLES[$page]) . '</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<div class="app">
<aside class="sidebar">
  <div class="logo">🔧 Автосервис 360</div>
  <nav>';
  foreach ($nav as $key => $label) {
    echo '<a href="index.php?page=' . $key . '"' . ($key === $page ? ' class="active"' : '') . '>' . esc($label) . '</a>';
  }
  echo '</nav>
  <div class="side-foot">Демо-версия платформы<br>управления сетью автосервисов</div>
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
    ['role' => 'admin', 'label' => 'Администратор', 'desc' => 'Вся сеть: справочники, сотрудники, отчёты'],
    ['role' => 'manager', 'label' => 'Руководитель', 'desc' => 'Управление филиалом №1'],
    ['role' => 'advisor', 'label' => 'Мастер-приёмщик', 'desc' => 'Приём заказов, календарь, склад'],
    ['role' => 'mechanic', 'label' => 'Механик', 'desc' => 'Статусы ремонта, свои заказы'],
    ['role' => 'client', 'label' => 'Клиент', 'desc' => 'Запись на СТО и статус ремонта'],
  ];
  echo '<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Автосервис 360 — вход</title>
<link rel="stylesheet" href="style.css">
</head>
<body class="login-body">
<div class="login-card">
  <div class="login-logo">🔧</div>
  <h1>Автосервис 360</h1>
  <p class="muted">Платформа управления сетью автосервисов — демо</p>';
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
  <p class="muted small">Демо-логины: admin/admin, manager/manager, advisor/advisor, mechanic/mechanic, client/client</p>
</div>
</body>
</html>';
  exit;
}
