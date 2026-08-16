<?php
session_status() === PHP_SESSION_ACTIVE || session_start();

define('DATA_DIR', __DIR__ . '/data');

const ROLES = [
  'admin'    => 'Администратор системы',
  'manager'  => 'Руководитель ОТК',
  'operator' => 'Контролёр ОТК',
  'engineer' => 'Инженер по качеству',
  'director' => 'Директор производства',
];

const VERDICTS = [
  'ok'     => ['label' => 'Годна',       'color' => '#16a34a'],
  'rework' => ['label' => 'На доработку', 'color' => '#f59e0b'],
  'reject' => ['label' => 'Брак',         'color' => '#dc2626'],
];

const PAGE_ROLES = [
  'dashboard' => ['admin', 'manager', 'operator', 'engineer', 'director'],
  'scan'      => ['admin', 'manager', 'operator', 'engineer'],
  'checks'    => ['admin', 'manager', 'operator', 'engineer', 'director'],
  'check_view'=> ['admin', 'manager', 'operator', 'engineer', 'director'],
  'forms'     => ['admin', 'manager', 'operator', 'engineer', 'director'],
  'defects'   => ['admin', 'manager', 'engineer'],
  'reports'   => ['admin', 'manager', 'director'],
  'models'    => ['admin', 'engineer'],
  'employees' => ['admin', 'manager'],
];

const PAGE_TITLES = [
  'dashboard'  => 'Сводка',
  'scan'       => 'Рабочее место контроля',
  'checks'     => 'Журнал контроля',
  'check_view' => 'Результат проверки',
  'forms'      => 'Флексоформы',
  'defects'    => 'Справочник дефектов',
  'reports'    => 'Аналитика',
  'models'     => 'Модель распознавания',
  'employees'  => 'Сотрудники',
];

const NAV_LABELS = [
  'dashboard'  => 'Сводка',
  'scan'       => 'Рабочее место',
  'checks'     => 'Журнал контроля',
  'forms'      => 'Флексоформы',
  'defects'    => 'Справочник дефектов',
  'reports'    => 'Аналитика',
  'models'     => 'Модель ИИ',
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

function user_name($id)
{
  $u = db_find('users', $id);
  return $u ? $u['name'] : '—';
}

function defect_type($key)
{
  foreach (db_all('defects') as $d) {
    if ($d['key'] === $key) return $d;
  }
  return null;
}

function verdict_badge($v)
{
  $m = VERDICTS[$v] ?? ['label' => $v, 'color' => '#64748b'];
  return '<span class="badge" style="background:' . $m['color'] . '">' . esc($m['label']) . '</span>';
}

function form_status_badge($s)
{
  static $map = [
    'queue'   => ['Очередь контроля', '#64748b'],
    'ok'      => ['Годна', '#16a34a'],
    'rework'  => ['На доработке', '#f59e0b'],
    'reject'  => ['Брак', '#dc2626'],
    'shipped' => ['Отгружена', '#1e293b'],
  ];
  $m = isset($map[$s]) ? $map[$s] : ['—', '#64748b'];
  return '<span class="badge" style="background:' . $m[1] . '">' . $m[0] . '</span>';
}

function nav_for_role($role)
{
  $nav = [];
  foreach (NAV_LABELS as $page => $label) {
    if (in_array($role, PAGE_ROLES[$page], true)) $nav[$page] = $label;
  }
  return $nav;
}

function scan_render($form, $found)
{
  $fw = (int)$form['size_w'];
  $fh = (int)$form['size_h'];
  $pw = 680;
  $ph = round($pw * $fh / $fw);
  $ph = max(160, min(560, $ph));
  $scale = $pw / $fw;
  $ox = 30;
  $oy = 30;

  $svg = '<svg class="table-svg" viewBox="0 0 ' . ($pw + 2 * $ox) . ' ' . ($ph + 2 * $oy + 34) . '" xmlns="http://www.w3.org/2000/svg">';
  $svg .= '<defs>
  <radialGradient id="light" cx="50%" cy="50%" r="75%">
    <stop offset="0%" stop-color="#eaf6ff"/>
    <stop offset="70%" stop-color="#cfe8fb"/>
    <stop offset="100%" stop-color="#aed6f0"/>
  </radialGradient>
  <pattern id="grid" width="34" height="34" patternUnits="userSpaceOnUse">
    <path d="M 34 0 L 0 0 0 34" fill="none" stroke="#ffffff" stroke-opacity="0.5" stroke-width="1"/>
  </pattern>
  </defs>';

  $svg .= '<rect x="0" y="0" width="' . ($pw + 2 * $ox) . '" height="' . ($ph + 2 * $oy + 34) . '" fill="#0e2233"/>';
  $svg .= '<rect x="' . $ox . '" y="' . $oy . '" width="' . $pw . '" height="' . $ph . '" fill="url(#light)"/>';
  $svg .= '<rect x="' . $ox . '" y="' . $oy . '" width="' . $pw . '" height="' . $ph . '" fill="url(#grid)"/>';

  $shape = isset($form['shape']) ? $form['shape'] : 'иконка';
  if ($shape === 'жестяная банка') $shape = 'ТМ банки';
  elseif ($shape === 'гибкая упаковка') $shape = 'Упаковка';
  elseif ($shape === 'этикетка') $shape = 'Этикетка';
  elseif ($shape === 'плёнка') $shape = 'Плёнка';
  foreach ($form['print_zones'] ?? [] as $zi => $zone) {
    $zx = $ox + (int)$zone['x'] * $scale;
    $zy = $oy + (int)$zone['y'] * $scale;
    $zw = max(20, (int)$zone['w'] * $scale);
    $zh = max(20, (int)$zone['h'] * $scale);
    $hue = 160 + $zi * 30;
    $svg .= '<g transform="translate(' . $zx . ',' . $zy . ')">
      <rect width="' . $zw . '" height="' . $zh . '" fill="hsl(' . $hue . ',55%,62%)" fill-opacity="0.25" stroke="hsl(' . $hue . ',70%,40%)" stroke-width="1.5" stroke-dasharray="6 4"/>
      <text x="8" y="20" font-size="13" fill="hsl(' . $hue . ',80%,22%)" font-weight="700">' . esc($shape) . '</text>
      <text x="8" y="40" font-size="11" fill="#334155">зона печати ' . ($zi + 1) . '</text>
    </g>';
  }

  $svg .= '<rect x="' . $ox . '" y="' . $oy . '" width="' . $pw . '" height="' . $ph . '" fill="none" stroke="#1d4ed8" stroke-width="2"/>';
  $svg .= '<text x="' . ($ox + $pw / 2) . '" y="' . ($oy + $ph / 2) . '" text-anchor="middle" font-size="15" fill="#1e3a8a" font-weight="700" opacity="0.45">' . esc($form['custom_no']) . '</text>';

  $n = 0;
  foreach ($found as $d) {
    $n++;
    $dx = $ox + (float)$d['x'] * $scale;
    $dy = $oy + (float)$d['y'] * $scale;
    $r = max(7, (float)$d['size'] * $scale * 0.35);
    $t = $d['type'];
    $info = defect_type($t);
    $col = '#dc2626';
    if ($info) {
      $sev = $info['severity'];
      $col = $sev === 'critical' ? '#dc2626' : ($sev === 'major' ? '#f59e0b' : '#2563eb');
    }
    $svg .= '<g>
      <circle cx="' . $dx . '" cy="' . $dy . '" r="' . ($r + 4) . '" fill="none" stroke="' . $col . '" stroke-width="2" stroke-dasharray="4 3"/>
      <rect x="' . ($dx - $r) . '" y="' . ($dy - $r) . '" width="' . (2 * $r) . '" height="' . (2 * $r) . '" fill="none" stroke="' . $col . '" stroke-width="1.5" opacity="0.7"/>
      <circle cx="' . $dx . '" cy="' . $dy . '" r="3" fill="' . $col . '"/>
      <text x="' . ($dx + $r + 6) . '" y="' . ($dy - 4) . '" font-size="12" fill="' . $col . '" font-weight="700">D' . $n . '</text>
    </g>';
  }

  $svg .= '<text x="' . ($ox + 8) . '" y="' . ($oy + $ph + 22) . '" font-size="11" fill="#cbd5e1">Размер формы: ' . $form['size_w'] . ' × ' . $form['size_h'] . ' мм · растр ' . $form['raster'] . ' · ' . esc($form['polymer']) . '</text>';
  $svg .= '<text x="' . ($ox + $pw) . '" y="' . ($oy + $ph + 22) . '" text-anchor="end" font-size="11" fill="#cbd5e1">рабочее поле сканера, мм</text>';
  $svg .= '</svg>';
  return $svg;
}

function defect_from_key($found, $n)
{
  return $found[$n - 1] ?? null;
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
<title>FlexQC — контроль качества флексоформ — ' . esc(PAGE_TITLES[$page]) . '</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style.css">
</head>
<body>
<div class="app">
<aside class="sidebar">
  <div class="logo"><span class="logo-mark">FQ</span> FlexQC</div>
  <div class="logo-sub">Контроль качества флексоформ</div>
  <nav>';
  foreach ($nav as $key => $label) {
    echo '<a href="index.php?page=' . $key . '"' . ($key === $page ? ' class="active"' : '') . '>' . esc($label) . '</a>';
  }
  echo '</nav>
  <div class="side-foot">Демо-версия системы<br>автоматизированного визуального<br>контроля флексоформ</div>
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
    ['role' => 'operator', 'label' => 'Контролёр ОТК', 'desc' => 'Рабочее место: сканирование форм, разметка дефектов'],
    ['role' => 'engineer', 'label' => 'Инженер по качеству', 'desc' => 'Модель распознавания, справочник дефектов, контроль порогов'],
    ['role' => 'manager', 'label' => 'Руководитель ОТК', 'desc' => 'Журнал, аналитика, регламенты приёмки'],
    ['role' => 'director', 'label' => 'Директор производства', 'desc' => 'Сводка по сменам: выход годного, потери, тренды'],
    ['role' => 'admin', 'label' => 'Администратор', 'desc' => 'Сотрудники, справочники, настройки системы'],
  ];
  echo '<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>FlexQC — контроль качества флексоформ</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style.css">
</head>
<body class="login-body">
<div class="login-card">
  <div class="login-logo">FQ</div>
  <h1>FlexQC</h1>
  <p class="muted">Автоматизированный визуальный контроль<br>флексоформ и печатных форм — демо</p>';
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
  <p class="muted small">Демо-логины: operator/operator, engineer/engineer, manager/manager, director/director, admin/admin</p>
</div>
</body>
</html>';
  exit;
}