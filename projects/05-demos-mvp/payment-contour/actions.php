<?php
if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;

$act = isset($_POST['act']) ? $_POST['act'] : '';
$u = current_user();

switch ($act) {

  case 'login':
    $login = trim($_POST['login'] ?? '');
    $pass = trim($_POST['pass'] ?? '');
    $role = trim($_POST['role'] ?? '');
    if ($role) {
      $login = $role;
      $pass = $role;
    }
    foreach (db_all('users') as $x) {
      if ($x['login'] === $login && $x['pass'] === $pass) {
        $_SESSION['uid'] = (int)$x['id'];
        redirect('dashboard', 'Добро пожаловать, ' . $x['name']);
      }
    }
    show_login('Неверный логин или пароль');
    break;

  case 'create_payment':
    require_role(['admin', 'operator']);
    $orderId = (int)($_POST['order_id'] ?? 0);
    $o = db_find('orders', $orderId);
    if (!$o) redirect('payments', 'Заказ не найден');
    if ($o['status'] === 'paid') redirect('payments', 'Заказ уже оплачен');
    $amount = round_money(order_total($o));
    $key = '2e16a7c0-' . substr(md5($orderId . $amount . microtime()), 0, 12) . '-' . str_pad((string)(count(db_all('payments')) + 1), 3, '0', STR_PAD_LEFT);
    $pid = db_insert('payments', [
      'payment_key' => $key,
      'order_id' => $orderId,
      'amount' => $amount,
      'status' => 'pending',
      'created_at' => now_ts(),
    ]);
    redirect('payments', 'Платёж создан в ЮKassa: ' . $key);
    break;

  case 'fire_success':
    require_role(['admin', 'operator', 'support']);
    $paymentId = (int)($_POST['payment_id'] ?? 0);
    $p = db_find('payments', $paymentId);
    if (!$p) redirect('payments', 'Платёж не найден');
    $key = 'evt_' . $p['payment_key'] . '_' . substr(md5(microtime()), 0, 6);
    $res = process_webhook('payment.succeeded', $p['payment_key'], $key);
    if ($res['success']) redirect('webhooks', 'Webhook payment.succeeded обработан: платёж проведён');
    if ($res['duplicate']) redirect('webhooks', 'Повторное уведомление: проводки не дублированы');
    redirect('webhooks', 'Ошибка обработки webhook');
    break;

  case 'fire_duplicate':
    require_role(['admin', 'operator', 'support']);
    $paymentId = (int)($_POST['payment_id'] ?? 0);
    $p = db_find('payments', $paymentId);
    if (!$p) redirect('payments', 'Платёж не найден');
    $lastKey = null;
    foreach (db_all('webhooks') as $w) {
      if ($w['payment_key'] === $p['payment_key']) $lastKey = $w['idempotency_key'];
    }
    if (!$lastKey) redirect('webhooks', 'Нет обработанных событий для этого платежа');
    $res = process_webhook('payment.succeeded', $p['payment_key'], $lastKey);
    if ($res['duplicate']) redirect('webhooks', 'Повторная доставка перехвачена: обработка пропущена');
    redirect('webhooks', 'Событие обработано');
    break;

  case 'fire_error':
    require_role(['admin', 'operator', 'support']);
    $paymentId = (int)($_POST['payment_id'] ?? 0);
    $p = db_find('payments', $paymentId);
    if (!$p) redirect('payments', 'Платёж не найден');
    $key = 'evt_fail_' . $p['payment_key'] . '_' . substr(md5(microtime()), 0, 6);
    $res = process_webhook('payment.succeeded', $p['payment_key'], $key, true);
    redirect('webhooks', $res['error'] ? 'Сбой смоделирован: незавершённая транзакция откачена' : 'Событие обработано');
    break;

  case 'refund':
    require_role(['admin', 'accountant']);
    $paymentId = (int)($_POST['payment_id'] ?? 0);
    $mode = trim($_POST['mode'] ?? 'partial');
    $p = db_find('payments', $paymentId);
    if (!$p) redirect('payments', 'Платёж не найден');
    if ($p['status'] !== 'succeeded') redirect('payments', 'Возврат доступен только для оплаченных платежей');
    $full = $mode === 'full';
    $amount = $full ? (float)$p['amount'] : round_money((float)$p['amount'] * 0.3);
    $rid = db_insert('refunds', [
      'payment_id' => $paymentId,
      'order_id' => $p['order_id'],
      'amount' => $amount,
      'type' => $full ? 'Полный' : 'Частичный (30%)',
      'status' => 'open',
      'created_at' => now_ts(),
    ]);
    db_update('payments', $paymentId, ['refund_id' => $rid]);
    $key = 'evt_refund_' . $p['payment_key'] . '_' . substr(md5(microtime()), 0, 6);
    $res = process_webhook('payment.refund.succeeded', $p['payment_key'], $key);
    if ($res['success']) redirect('refunds', 'Возврат проведён: сторно-проводки и чек возврата сформированы');
    redirect('refunds', 'Ошибка при возврате');
    break;

  case 'run_tests':
    require_role(['admin', 'accountant', 'support']);
    $allPass = true;
    foreach (run_tests() as $t) if (!$t['pass']) $allPass = false;
    redirect('tests', $allPass ? 'Все проверки пройдены (инварианты целостности соблюдены)' : 'Часть проверок не пройдена — смотрите таблицу');
    break;
}