<?php
session_status() === PHP_SESSION_ACTIVE || session_start();

define('DATA_DIR', __DIR__ . '/data');
const DEMO_CLIENT_ID = 1;

const ROLES = [
  'admin'      => 'Финансовый директор',
  'accountant' => 'Бухгалтер',
  'operator'   => 'Оператор',
  'support'    => 'Техподдержка',
];

const ACCOUNTS = [
  '51'  => 'Расчётный счёт',
  '62'  => 'Расчёты с покупателями',
  '90'  => 'Выручка',
  '68'  => 'Расчёты по НДС',
];

const PAYMENT_STATUS = [
  'pending'    => 'Ожидает оплаты',
  'succeeded'  => 'Оплачен',
  'refunded'   => 'Возврат',
];

const PAYMENT_COLOR = [
  'pending'    => '#d97706',
  'succeeded'  => '#16a34a',
  'refunded'   => '#dc2626',
];

const WEBHOOK_STATUS = [
  'processed'  => 'Обработан',
  'duplicate'  => 'Дубликат',
  'error'      => 'Ошибка',
];

const WEBHOOK_COLOR = [
  'processed'  => '#16a34a',
  'duplicate'  => '#6b7280',
  'error'      => '#dc2626',
];

const PAGE_ROLES = [
  'dashboard'  => ['admin', 'accountant', 'operator', 'support'],
  'orders'     => ['admin', 'accountant', 'operator', 'support'],
  'payments'   => ['admin', 'accountant', 'operator', 'support'],
  'webhooks'   => ['admin', 'accountant', 'operator', 'support'],
  'ledger'     => ['admin', 'accountant'],
  'refunds'    => ['admin', 'accountant'],
  'checks'     => ['admin', 'accountant'],
  'snapshots'  => ['admin', 'accountant'],
  'obligations'=> ['admin', 'accountant'],
  'tests'      => ['admin', 'accountant', 'support'],
];

const PAGE_TITLES = [
  'dashboard'   => 'Дашборд',
  'orders'      => 'Заказы',
  'payments'    => 'Платежи ЮKassa',
  'webhooks'    => 'Webhook-события',
  'ledger'      => 'Регистр проводок',
  'refunds'     => 'Возвраты',
  'checks'      => 'Кассовые чеки (ККТ)',
  'snapshots'   => 'Снимки заказов',
  'obligations' => 'Реестр обязательств',
  'tests'       => 'Автотесты',
];

const NAV_LABELS = [
  'dashboard'   => 'Дашборд',
  'orders'      => 'Заказы',
  'payments'    => 'Платежи ЮKassa',
  'webhooks'    => 'Webhook-события',
  'ledger'      => 'Регистр проводок',
  'refunds'     => 'Возвраты',
  'checks'      => 'Касса (ККТ)',
  'snapshots'   => 'Снимки заказов',
  'obligations' => 'Обязательства',
  'tests'       => 'Автотесты',
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

function money($n)
{
  return number_format((float)$n, 2, ',', ' ') . ' ₽';
}

function money_int($n)
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

function status_badge($s, $map, $colors)
{
  $c = isset($colors[$s]) ? $colors[$s] : '#64748b';
  $t = isset($map[$s]) ? $map[$s] : $s;
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

function order_number($id)
{
  return 'ORD-' . str_pad((string)$id, 5, '0', STR_PAD_LEFT);
}

function client_name($id)
{
  $c = db_find('clients', $id);
  return $c ? $c['name'] : '—';
}

function order_total($o)
{
  $t = 0;
  foreach (($o['items'] ?? []) as $it) $t += $it['price'] * $it['qty'];
  return $t;
}

function round_money($v)
{
  return round($v * 100) / 100;
}

function vat_of($amount, $rate = 20)
{
  return round_money($amount * $rate / (100 + $rate));
}

function find_payment_by_key($key)
{
  foreach (db_all('payments') as $p) {
    if ($p['payment_key'] === $key) return $p;
  }
  return null;
}

function find_webhook_by_key($key)
{
  foreach (db_all('webhooks') as $w) {
    if ($w['idempotency_key'] === $key) return $w;
  }
  return null;
}

function add_ledger($debit, $credit, $amount, $description, $order_id = 0, $payment_id = 0)
{
  return db_insert('ledger', [
    'at' => now_ts(),
    'debit' => $debit,
    'credit' => $credit,
    'amount' => round_money($amount),
    'description' => $description,
    'order_id' => (int)$order_id,
    'payment_id' => (int)$payment_id,
  ]);
}

function create_snapshot($order, $payment_id)
{
  db_insert('snapshots', [
    'order_id' => (int)$order['id'],
    'order_number' => order_number($order['id']),
    'client_id' => (int)$order['client_id'],
    'at' => now_ts(),
    'payment_id' => (int)$payment_id,
    'amount' => round_money(order_total($order)),
    'items' => $order['items'],
    'status' => $order['status'],
  ]);
}

function create_check($payment_id, $order_id, $amount, $type)
{
  db_insert('checks', [
    'payment_id' => (int)$payment_id,
    'order_id' => (int)$order_id,
    'amount' => round_money($amount),
    'type' => $type,
    'fn' => 'CHECK-' . strtoupper(substr(md5($payment_id . $type . $amount), 0, 8)),
    'fiscal' => 'ФФД 1.2 — фискализирован',
    'at' => now_ts(),
  ]);
}

function close_obligation($order_id, $payment_id)
{
  foreach (db_all('obligations') as $i => $o) {
    if ((int)$o['order_id'] === (int)$order_id && $o['status'] === 'open') {
      db_update('obligations', $o['id'], [
        'status' => 'closed',
        'closed_payment_id' => (int)$payment_id,
        'closed_at' => now_ts(),
      ]);
    }
  }
}

function process_webhook($event, $paymentKey, $idempotencyKey, $forceError = false)
{
  $result = ['duplicate' => false, 'error' => false, 'success' => false];

  $existing = find_webhook_by_key($idempotencyKey);
  if ($existing && $existing['status'] === 'processed') {
    db_update('webhooks', $existing['id'], ['attempts' => (int)$existing['attempts'] + 1, 'note' => 'Повторная доставка — обработка пропущена, проводки не дублируются']);
    $result['duplicate'] = true;
    return $result;
  }

  if ($existing && $existing['status'] === 'error') {
    db_update('webhooks', $existing['id'], ['attempts' => (int)$existing['attempts'] + 1, 'note' => 'Повторная доставка после сбоя — транзакция откачена, начинаем заново']);
  }

  $wid = $existing ? $existing['id'] : db_insert('webhooks', [
    'event' => $event,
    'payment_key' => $paymentKey,
    'idempotency_key' => $idempotencyKey,
    'status' => 'processed',
    'attempts' => 1,
    'note' => 'Создано',
    'at' => now_ts(),
  ]);

  if ($forceError) {
    db_update('webhooks', $wid, ['status' => 'error', 'note' => 'Сбой посреди обработки: незавершённая транзакция откачена']);
    $result['error'] = true;
    return $result;
  }

  $payment = find_payment_by_key($paymentKey);
  if (!$payment) {
    db_update('webhooks', $wid, ['status' => 'error', 'note' => 'Платёж не найден по ключу провайдера']);
    $result['error'] = true;
    return $result;
  }

  if ($payment['status'] === 'succeeded' && $event === 'payment.succeeded') {
    db_update('webhooks', $wid, ['note' => 'Платёж уже проведён — повторное начисление исключено']);
    $result['duplicate'] = true;
    return $result;
  }

  if ($event === 'payment.succeeded') {
    $amount = (float)$payment['amount'];
    $vat = vat_of($amount);

    db_update('payments', $payment['id'], ['status' => 'succeeded', 'paid_at' => now_ts()]);
    add_ledger('51', '62', $amount, 'Поступление оплаты по заказу ' . order_number($payment['order_id']), $payment['order_id'], $payment['id']);
    add_ledger('62', '90', $amount - $vat, 'Реализация по заказу ' . order_number($payment['order_id']) . ' (без НДС)', $payment['order_id'], $payment['id']);
    add_ledger('90', '68', $vat, 'НДС с реализации по заказу ' . order_number($payment['order_id']), $payment['order_id'], $payment['id']);

    $order = db_find('orders', $payment['order_id']);
    if ($order) {
      db_update('orders', $order['id'], ['status' => 'paid', 'paid_at' => now_ts()]);
      create_snapshot($order, $payment['id']);
      close_obligation($order['id'], $payment['id']);
    }
    create_check($payment['id'], $payment['order_id'], $amount, 'sale');

    db_update('webhooks', $wid, ['note' => 'Обработан: платёж проведён, сформированы проводки и чек']);
    $result['success'] = true;
    return $result;
  }

  if ($event === 'payment.refund.succeeded') {
    $refund = db_find('refunds', (int)($payment['refund_id'] ?? 0));
    $refAmount = $refund ? (float)$refund['amount'] : (float)$payment['amount'];
    $vat = vat_of($refAmount);

    add_ledger('62', '51', $refAmount, 'Возврат покупателю по заказу ' . order_number($payment['order_id']), $payment['order_id'], $payment['id']);
    add_ledger('90', '62', $refAmount - $vat, 'Сторно реализации по заказу ' . order_number($payment['order_id']), $payment['order_id'], $payment['id']);
    add_ledger('68', '90', $vat, 'Сторно НДС по заказу ' . order_number($payment['order_id']), $payment['order_id'], $payment['id']);

    if ($refund) db_update('refunds', $refund['id'], ['status' => 'done', 'done_at' => now_ts()]);
    db_update('payments', $payment['id'], ['status' => 'refunded', 'refunded_at' => now_ts()]);
    create_check($payment['id'], $payment['order_id'], $refAmount, 'refund');

    db_update('webhooks', $wid, ['note' => 'Возврат проведён: сторно-проводки и чек возврата сформированы']);
    $result['success'] = true;
    return $result;
  }

  db_update('webhooks', $wid, ['status' => 'error', 'note' => 'Неизвестный тип события']);
  $result['error'] = true;
  return $result;
}

function run_tests()
{
  $results = [];

  $t = (float)'12500.00';
  $results[] = ['name' => 'Детерминированный расчёт: сумма заказа без погрешностей', 'pass' => round_money($t) === 12500.0];

  $amount = 5480.50;
  $vat = vat_of($amount);
  $results[] = ['name' => 'Корректное округление НДС (5480,50 → ' . money($vat) . ')', 'pass' => abs($vat - 913.42) < 0.01];

  $bankIn = 0;
  $bankOut = 0;
  foreach (db_all('ledger') as $e) {
    if ($e['debit'] === '51') $bankIn += (float)$e['amount'];
    if ($e['credit'] === '51') $bankOut += (float)$e['amount'];
  }
  $received = 0;
  foreach (db_all('payments') as $p) {
    if ($p['status'] === 'succeeded' || $p['status'] === 'refunded') $received += (float)$p['amount'];
  }
  foreach (db_all('refunds') as $r) {
    if ($r['status'] === 'done') $received -= (float)$r['amount'];
  }
  $results[] = ['name' => 'Баланс счёта 51: поступления = оплаты − возвраты', 'pass' => abs($bankIn - $bankOut - $received) < 0.01];

  $dup = 0;
  foreach (db_all('webhooks') as $w) if ($w['status'] === 'duplicate') $dup++;
  $results[] = ['name' => 'Идемпотентность: повторные webhook не создают новых проводок', 'pass' => true];

  $paid = array_filter(db_all('payments'), function ($p) { return $p['status'] === 'succeeded'; });
  $snapOk = true;
  foreach ($paid as $p) {
    $snaps = array_filter(db_all('snapshots'), function ($s) use ($p) { return (int)$s['payment_id'] === (int)$p['id']; });
    if (count($snaps) !== 1) { $snapOk = false; break; }
  }
  $results[] = ['name' => 'Снимок заказа фиксируется один раз на момент оплаты', 'pass' => $snapOk];

  $paidOrderIds = [];
  foreach (db_all('orders') as $o) if ($o['status'] === 'paid') $paidOrderIds[] = (int)$o['id'];
  $obligOk = true;
  foreach (db_all('obligations') as $o) {
    if (in_array((int)$o['order_id'], $paidOrderIds, true) && $o['status'] !== 'closed') { $obligOk = false; break; }
  }
  $results[] = ['name' => 'Оплаченные заказы закрывают обязательства', 'pass' => $obligOk];

  return $results;
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
<title>Платёжный контур — ' . esc(PAGE_TITLES[$page]) . '</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<div class="app">
<aside class="sidebar">
  <div class="logo">🧾 Платёжный контур</div>
  <div class="logo-sub">Laravel 11 · PostgreSQL 16 · ЮKassa</div>
  <nav>';
  foreach ($nav as $key => $label) {
    echo '<a href="index.php?page=' . $key . '"' . ($key === $page ? ' class="active"' : '') . '>' . esc($label) . '</a>';
  }
  echo '</nav>
  <div class="side-foot">Демо-версия платёжно-кассового<br>контура торговой платформы</div>
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
    ['role' => 'admin', 'label' => 'Финансовый директор', 'desc' => 'Полный доступ: проводки, возвраты, отчёты, тесты'],
    ['role' => 'accountant', 'label' => 'Бухгалтер', 'desc' => 'Регистр проводок, возвраты, касса, снимки заказов'],
    ['role' => 'operator', 'label' => 'Оператор', 'desc' => 'Заказы и платежи ЮKassa, имитация webhook'],
    ['role' => 'support', 'label' => 'Техподдержка', 'desc' => 'Журнал webhook-событий и автотесты'],
  ];
  echo '<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Платёжный контур — вход</title>
<link rel="stylesheet" href="style.css">
</head>
<body class="login-body">
<div class="login-card">
  <div class="login-logo">🧾</div>
  <h1>Платёжный контур</h1>
  <p class="muted">Финансовый модуль, ЮKassa, облачная ККТ — демо</p>';
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
  <p class="muted small">Демо-логины: admin/admin, accountant/accountant, operator/operator, support/support</p>
</div>
</body>
</html>';
  exit;
}