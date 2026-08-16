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

  case 'booking_create':
    $cottageId = (int)($_POST['cottage_id'] ?? 0);
    $cottage = db_find('cottages', $cottageId);
    if (!$cottage) redirect('catalog', 'Домик не найден');

    $checkIn = trim($_POST['check_in'] ?? '');
    $checkOut = trim($_POST['check_out'] ?? '');
    $guests = max(1, (int)($_POST['guests'] ?? 1));
    $comment = trim($_POST['comment'] ?? '');
    $svcIds = array_map('intval', (array)($_POST['services'] ?? []));

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $checkIn) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $checkOut)) {
      redirect('booking&cottage=' . $cottageId . '&in=' . $checkIn . '&out=' . $checkOut, 'Укажите корректные даты');
    }
    if (strcmp($checkIn, $checkOut) >= 0) redirect('booking&cottage=' . $cottageId, 'Дата выезда должна быть позже даты заезда');

    if ($u['role'] === 'client' && strcmp($checkIn, date('Y-m-d')) < 0) {
      redirect('booking&cottage=' . $cottageId . '&in=' . $checkIn . '&out=' . $checkOut, 'Дата заезда не может быть в прошлом');
    }
    if ((int)$cottage['capacity'] < $guests) {
      redirect('booking&cottage=' . $cottageId . '&in=' . $checkIn . '&out=' . $checkOut, 'Этот домик вмещает не более ' . $cottage['capacity'] . ' гостей');
    }
    if (nights_between($checkIn, $checkOut) < (int)$cottage['min_nights']) {
      redirect('booking&cottage=' . $cottageId . '&in=' . $checkIn . '&out=' . $checkOut, 'Минимальный срок проживания — ' . $cottage['min_nights'] . ' ночи');
    }
    if (!is_available($cottageId, $checkIn, $checkOut)) {
      redirect('booking&cottage=' . $cottageId . '&in=' . $checkIn . '&out=' . $checkOut, 'Домик занят на выбранные даты');
    }

    $guestId = 0;
    $gName = ''; $gPhone = ''; $gEmail = '';
    if ($u['role'] === 'client') {
      $guestId = DEMO_GUEST_ID;
      $gName = $u['name'];
      $gPhone = $u['phone'];
      $gEmail = $u['email'];
    } else {
      $guestId = (int)($_POST['guest_id'] ?? 0);
      $g = db_find('guests', $guestId);
      if (!$g) redirect('booking&cottage=' . $cottageId, 'Выберите гостя');
      $gName = $g['name'];
      $gPhone = $g['phone'];
      $gEmail = $g['email'];
    }

    $price = booking_price($cottage, $checkIn, $checkOut, $guests, $svcIds);
    $snap = [];
    foreach ($svcIds as $id) {
      $svc = db_find('services', $id);
      if (!$svc) continue;
      $snap[] = svc_snapshot($svc, service_amount($svc, $price['nights'], $guests));
    }

    $count = count(db_all('bookings'));
    $row = [
      'number' => 'БГ-' . date('Y') . '-' . str_pad($count + 1, 3, '0', STR_PAD_LEFT),
      'cottage_id' => $cottageId,
      'guest_id' => $guestId,
      'guest_name' => $gName,
      'guest_phone' => $gPhone,
      'guest_email' => $gEmail,
      'check_in' => $checkIn,
      'check_out' => $checkOut,
      'nights' => $price['nights'],
      'guests' => $guests,
      'services' => $snap,
      'night_total' => $price['night_total'],
      'extras_total' => $price['extras_total'],
      'total' => $price['total'],
      'status' => 'new',
      'payment' => null,
      'source' => $u['role'] === 'client' ? 'site' : 'staff',
      'comment' => $comment,
      'created_at' => now_ts(),
      'updated_at' => now_ts(),
      'history' => [['at' => now_ts(), 'user' => $gName, 'from' => '', 'to' => 'new', 'note' => 'Бронь создана' . ($u['role'] === 'client' ? ' через онлайн-бронирование' : '')]],
    ];
    $nid = db_insert('bookings', $row);
    redirect('booking_view&id=' . $nid, 'Бронь создана' . ($u['role'] === 'client' ? '. Подтверждение отправлено на ' . $gEmail : ''));
    break;

  case 'booking_status':
    $id = (int)($_POST['id'] ?? 0);
    $to = trim($_POST['to'] ?? '');
    $note = trim($_POST['note'] ?? '');
    $b = db_find('bookings', $id);
    if (!$b) redirect('bookings', 'Бронь не найдена');

    $ok = false;
    if (in_array($u['role'], ['admin', 'manager', 'operator'], true)) {
      $ok = in_array($to, TRANSITIONS[$b['status']] ?? [], true);
    }
    if ($u['role'] === 'client') {
      $ok = $to === 'cancelled' && (int)$b['guest_id'] === DEMO_GUEST_ID && in_array($b['status'], ['new', 'confirmed', 'paid'], true);
    }
    if ($ok && $to !== $b['status']) {
      $h = $b['history'] ?? [];
      $h[] = ['at' => now_ts(), 'user' => $u['name'], 'from' => $b['status'], 'to' => $to, 'note' => $note];
      db_update('bookings', $id, ['status' => $to, 'history' => $h, 'updated_at' => now_ts()]);
    }
    redirect('booking_view&id=' . $id, $ok ? 'Статус обновлён' : 'Недопустимый переход статуса');
    break;

  case 'payment_pay':
    $id = (int)($_POST['id'] ?? 0);
    $b = db_find('bookings', $id);
    if (!$b) redirect('bookings', 'Бронь не найдена');
    if (!in_array($b['status'], ['new', 'confirmed'], true)) redirect('booking_view&id=' . $id, 'Бронь уже оплачена или не может быть оплачена');
    if ($u['role'] === 'client' && (int)$b['guest_id'] !== DEMO_GUEST_ID) redirect('dashboard', 'Нет доступа');

    $h = $b['history'] ?? [];
    $h[] = ['at' => now_ts(), 'user' => $u['name'], 'from' => $b['status'], 'to' => 'paid', 'note' => 'Онлайн-оплата картой, подтверждение отправлено на ' . $b['guest_email']];
    db_update('bookings', $id, [
      'status' => 'paid',
      'payment' => ['method' => 'Онлайн-оплата картой', 'at' => now_ts(), 'amount' => (int)$b['total']],
      'history' => $h,
      'updated_at' => now_ts(),
    ]);
    redirect('booking_view&id=' . $id, 'Оплата прошла успешно. Подтверждение брони отправлено на ' . $b['guest_email']);
    break;

  case 'booking_delete':
    require_role(['admin', 'manager']);
    db_delete('bookings', (int)($_POST['id'] ?? 0));
    redirect('bookings', 'Бронь удалена');
    break;

  case 'cottage_save':
    require_role(['admin']);
    $id = (int)($_POST['id'] ?? 0);
    $amenities = array_values(array_filter(array_map('trim', explode("\n", $_POST['amenities'] ?? ''))));
    $row = [
      'name' => trim($_POST['name'] ?? ''),
      'type' => trim($_POST['type'] ?? ''),
      'capacity' => max(1, (int)($_POST['capacity'] ?? 1)),
      'area' => max(1, (int)($_POST['area'] ?? 1)),
      'price' => max(1, (int)($_POST['price'] ?? 0)),
      'min_nights' => max(1, (int)($_POST['min_nights'] ?? 1)),
      'color' => preg_match('/^#[0-9a-fA-F]{6}$/', trim($_POST['color'] ?? '')) ? trim($_POST['color']) : '#3d6b4f',
      'amenities' => $amenities,
      'description' => trim($_POST['description'] ?? ''),
    ];
    if ($id > 0) {
      db_update('cottages', $id, $row);
      redirect('cottage&id=' . $id, 'Домик обновлён');
    }
    db_insert('cottages', $row);
    redirect('catalog', 'Домик добавлен');
    break;

  case 'cottage_delete':
    require_role(['admin']);
    $id = (int)($_POST['id'] ?? 0);
    foreach (db_all('bookings') as $b) {
      if ((int)$b['cottage_id'] === $id && $b['status'] !== 'cancelled') {
        redirect('catalog', 'Нельзя удалить домик с активными бронями');
      }
    }
    db_delete('cottages', $id);
    redirect('catalog', 'Домик удалён');
    break;

  case 'service_save':
    require_role(['admin']);
    db_insert('services', [
      'name' => trim($_POST['name'] ?? ''),
      'price' => max(0, (int)($_POST['price'] ?? 0)),
      'per' => in_array($_POST['per'] ?? '', ['once', 'night', 'person_night'], true) ? $_POST['per'] : 'once',
      'unit' => trim($_POST['unit'] ?? ''),
    ]);
    redirect('services', 'Услуга добавлена');
    break;

  case 'service_delete':
    require_role(['admin']);
    db_delete('services', (int)($_POST['id'] ?? 0));
    redirect('services', 'Услуга удалена');
    break;

  case 'season_save':
    require_role(['admin']);
    db_insert('seasons', [
      'name' => trim($_POST['name'] ?? ''),
      'from' => trim($_POST['from'] ?? '01-01'),
      'to' => trim($_POST['to'] ?? '12-31'),
      'mult' => max(0.5, (float)($_POST['mult'] ?? 1)),
    ]);
    redirect('settings', 'Сезон добавлен');
    break;

  case 'season_delete':
    require_role(['admin']);
    db_delete('seasons', (int)($_POST['id'] ?? 0));
    redirect('settings', 'Сезон удалён');
    break;

  case 'guest_save':
    require_role(['admin', 'manager', 'operator']);
    db_insert('guests', [
      'user_id' => 0,
      'name' => trim($_POST['name'] ?? ''),
      'phone' => trim($_POST['phone'] ?? ''),
      'email' => trim($_POST['email'] ?? ''),
      'created_at' => date('Y-m-d'),
      'notes' => trim($_POST['notes'] ?? ''),
    ]);
    redirect('guests', 'Гость добавлен');
    break;
}
