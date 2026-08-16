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

  case 'client_save':
    require_role(['admin', 'manager', 'advisor']);
    db_insert('clients', [
      'name' => trim($_POST['name'] ?? ''),
      'phone' => trim($_POST['phone'] ?? ''),
      'email' => trim($_POST['email'] ?? ''),
      'created_at' => date('Y-m-d'),
    ]);
    redirect('clients', 'Клиент добавлен');
    break;

  case 'car_save':
    require_role(['admin', 'manager', 'advisor', 'client']);
    $clientId = (int)($_POST['client_id'] ?? 0);
    if ($u['role'] === 'client') $clientId = DEMO_CLIENT_ID;
    db_insert('cars', [
      'client_id' => $clientId ?: DEMO_CLIENT_ID,
      'brand' => trim($_POST['brand'] ?? ''),
      'model' => trim($_POST['model'] ?? ''),
      'year' => (int)($_POST['year'] ?? 0),
      'plate' => trim($_POST['plate'] ?? ''),
      'vin' => strtoupper(trim($_POST['vin'] ?? '')),
    ]);
    redirect('cars', 'Автомобиль добавлен');
    break;

  case 'order_save':
    require_role(['admin', 'manager', 'advisor', 'client']);
    $id = (int)($_POST['id'] ?? 0);
    $clientId = (int)($_POST['client_id'] ?? 0);
    $carId = (int)($_POST['car_id'] ?? 0);
    $branchId = (int)($_POST['branch_id'] ?? 0);
    if ($u['role'] === 'client') $clientId = DEMO_CLIENT_ID;
    $date = trim($_POST['date'] ?? '');
    $time = trim($_POST['time'] ?? '');
    $workType = trim($_POST['work_type'] ?? '');
    $comment = trim($_POST['comment'] ?? '');

    $services = [];
    $stotal = 0;
    foreach (($_POST['services'] ?? []) as $sid) {
      $s = db_find('services', $sid);
      if (!$s) continue;
      $services[] = ['id' => $s['id'], 'name' => $s['name'], 'price' => $s['price']];
      $stotal += $s['price'];
    }

    $parts = [];
    $ptotal = 0;
    foreach (($_POST['part_ids'] ?? []) as $pid) {
      $p = db_find('parts', $pid);
      if (!$p) continue;
      $qty = max(1, (int)($_POST['part_qty'][$pid] ?? 1));
      $parts[] = ['id' => $p['id'], 'name' => $p['name'], 'price' => $p['price'], 'qty' => $qty];
      $ptotal += $p['price'] * $qty;
    }

    if ($id > 0) {
      $o = db_find('orders', $id);
      if (!$o) redirect('orders', 'Заказ не найден');
      db_update('orders', $id, [
        'client_id' => $clientId,
        'car_id' => $carId,
        'branch_id' => $branchId,
        'date' => $date,
        'time' => $time,
        'work_type' => $workType,
        'comment' => $comment,
        'services' => $services,
        'parts' => $parts,
        'total' => $stotal + $ptotal,
        'updated_at' => now_ts(),
      ]);
      redirect('order_view&id=' . $id, 'Заказ обновлён');
    }

    $next = count(db_all('orders')) + 1;
    $row = [
      'number' => 'ЗН-' . date('Y') . '-' . str_pad($next, 3, '0', STR_PAD_LEFT),
      'client_id' => $clientId,
      'car_id' => $carId,
      'branch_id' => $branchId,
      'advisor_id' => $u['role'] === 'client' ? 0 : (int)$u['id'],
      'mechanic_id' => 0,
      'status' => 'new',
      'date' => $date,
      'time' => $time,
      'work_type' => $workType,
      'comment' => $comment,
      'services' => $services,
      'parts' => $parts,
      'total' => $stotal + $ptotal,
      'created_at' => now_ts(),
      'updated_at' => now_ts(),
      'history' => [['at' => now_ts(), 'user' => $u['name'], 'from' => '', 'to' => 'new', 'note' => 'Заказ создан' . ($u['role'] === 'client' ? ' через онлайн-запись' : '')]],
    ];
    $nid = db_insert('orders', $row);
    foreach ($parts as $p) adjust_part($p['id'], -$p['qty'], $u['name'], 'out', $nid, 'Списание по заказу');
    redirect('order_view&id=' . $nid, 'Заказ создан');
    break;

  case 'order_status':
    $id = (int)($_POST['id'] ?? 0);
    $to = trim($_POST['to'] ?? '');
    $note = trim($_POST['note'] ?? '');
    $o = db_find('orders', $id);
    if (!$o) redirect('orders', 'Заказ не найден');
    require_role(['admin', 'manager', 'advisor', 'mechanic']);
    $ok = false;
    if ($u['role'] === 'admin' || $u['role'] === 'manager') $ok = true;
    if ($u['role'] === 'advisor') $ok = in_array($to, TRANSITIONS[$o['status']] ?? [], true);
    if ($u['role'] === 'mechanic') $ok = in_array($to, ['work', 'ready'], true) && in_array($o['status'], ['diagnostics', 'work'], true);
    if ($ok && $to !== $o['status']) {
      $h = $o['history'] ?? [];
      $h[] = ['at' => now_ts(), 'user' => $u['name'], 'from' => $o['status'], 'to' => $to, 'note' => $note];
      db_update('orders', $id, ['status' => $to, 'history' => $h, 'updated_at' => now_ts()]);
    }
    redirect('order_view&id=' . $id, $ok ? 'Статус обновлён' : 'Недопустимый переход статуса');
    break;

  case 'order_delete':
    require_role(['admin', 'manager']);
    db_delete('orders', (int)($_POST['id'] ?? 0));
    redirect('orders', 'Заказ удалён');
    break;

  case 'part_in':
    require_role(['admin', 'manager', 'advisor']);
    adjust_part((int)$_POST['part_id'] ?? 0, max(1, (int)($_POST['qty'] ?? 1)), $u['name'], 'in', 0, 'Приход от поставщика' . (trim($_POST['note'] ?? '') ? ': ' . trim($_POST['note']) : ''));
    redirect('warehouse', 'Приход оформлен');
    break;

  case 'part_out':
    require_role(['admin', 'manager', 'advisor']);
    adjust_part((int)($_POST['part_id'] ?? 0), -max(1, (int)($_POST['qty'] ?? 1)), $u['name'], 'out', 0, 'Списание' . (trim($_POST['note'] ?? '') ? ': ' . trim($_POST['note']) : ''));
    redirect('warehouse', 'Списание оформлено');
    break;

  case 'part_transfer':
    require_role(['admin', 'manager', 'advisor']);
    $pid = (int)($_POST['part_id'] ?? 0);
    $toBranch = (int)($_POST['to_branch'] ?? 0);
    $qty = max(1, (int)($_POST['qty'] ?? 1));
    $p = db_find('parts', $pid);
    if (!$p) redirect('warehouse', 'Запчасть не найдена');
    if ((int)$p['qty'] < $qty) redirect('warehouse', 'Недостаточно остатка');
    $p['qty'] -= $qty;
    db_update('parts', $pid, $p);
    $dst = null;
    foreach (db_all('parts') as $x) {
      if ($x['sku'] === $p['sku'] && (int)$x['branch_id'] === $toBranch) { $dst = $x; break; }
    }
    if ($dst) {
      $dst['qty'] += $qty;
      db_update('parts', $dst['id'], $dst);
    } else {
      db_insert('parts', ['sku' => $p['sku'], 'name' => $p['name'], 'price' => $p['price'], 'branch_id' => $toBranch, 'qty' => $qty, 'min_qty' => $p['min_qty'], 'unit' => $p['unit']]);
    }
    db_insert('movements', ['at' => now_ts(), 'user' => $u['name'], 'part_id' => $p['id'], 'part_name' => $p['name'], 'branch_id' => $toBranch, 'type' => 'transfer', 'qty' => $qty, 'order_id' => 0, 'note' => 'Перемещение из филиала ' . branch_name($p['branch_id'])]);
    redirect('warehouse', 'Перемещение выполнено');
    break;

  case 'service_save':
    require_role(['admin', 'manager']);
    db_insert('services', [
      'name' => trim($_POST['name'] ?? ''),
      'category' => trim($_POST['category'] ?? 'ТО'),
      'hours' => (float)($_POST['hours'] ?? 0),
      'price' => (int)($_POST['price'] ?? 0),
    ]);
    redirect('catalog', 'Работа добавлена');
    break;

  case 'service_delete':
    require_role(['admin', 'manager']);
    db_delete('services', (int)($_POST['id'] ?? 0));
    redirect('catalog', 'Работа удалена');
    break;

  case 'employee_save':
    require_role(['admin']);
    db_insert('users', [
      'login' => trim($_POST['login'] ?? ''),
      'pass' => trim($_POST['pass'] ?? ''),
      'name' => trim($_POST['name'] ?? ''),
      'role' => trim($_POST['role'] ?? 'advisor'),
      'branch_id' => (int)($_POST['branch_id'] ?? 0),
      'phone' => trim($_POST['phone'] ?? ''),
      'email' => trim($_POST['email'] ?? ''),
    ]);
    redirect('employees', 'Сотрудник добавлен');
    break;
}
