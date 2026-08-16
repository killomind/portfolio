<?php
$u = current_user();
$role = $u['role'];
$today = date('Y-m-d');
$month = substr(date('Y-m'), 0, 7);

$bookings = scoped_bookings($u);
$active = array_values(array_filter($bookings, function ($b) { return in_array($b['status'], ['new', 'confirmed', 'paid'], true); }));
$todayIn = array_values(array_filter($bookings, function ($b) use ($today) { return $b['check_in'] === $today && !in_array($b['status'], ['cancelled', 'checked_out'], true); }));
$todayOut = array_values(array_filter($bookings, function ($b) use ($today) { return $b['check_out'] === $today && in_array($b['status'], ['checked_in'], true); }));

$revenue = 0;
foreach ($bookings as $b) {
  if (!empty($b['payment']) && strpos($b['payment']['at'], $month) === 0) $revenue += (int)$b['payment']['amount'];
}

$cottages = db_all('cottages');
$daysInMonth = (int)date('t', strtotime($month . '-01'));
$occ = 0;
$ts = strtotime($month . '-01');
for ($d = 0; $d < $daysInMonth; $d++) {
  $day = date('Y-m-d', $ts + $d * 86400);
  foreach ($cottages as $c) {
    foreach (db_all('bookings') as $b) {
      if ((int)$b['cottage_id'] !== (int)$c['id'] || $b['status'] === 'cancelled') continue;
      if (strcmp($b['check_in'], $day) <= 0 && strcmp($b['check_out'], $day) > 0) { $occ++; break; }
    }
  }
}
$occupancy = $cottages && $daysInMonth ? round($occ / (count($cottages) * $daysInMonth) * 100) : 0;

usort($bookings, function ($a, $b) { return strcmp($b['created_at'], $a['created_at']); });
$recent = array_slice($bookings, 0, 8);
?>
<div class="cards">
  <div class="card"><div class="num"><?= count($active) ?></div><div class="lbl">Активных броней</div></div>
  <div class="card"><div class="num"><?= count($todayIn) ?></div><div class="lbl">Заезды сегодня</div></div>
  <div class="card"><div class="num"><?= count($todayOut) ?></div><div class="lbl">Выезды сегодня</div></div>
  <div class="card"><div class="num"><?= $occupancy ?>%</div><div class="lbl">Загрузка в <?= date('F', strtotime($month . '-01')) ?></div></div>
  <div class="card"><div class="num"><?= money($revenue) ?></div><div class="lbl">Оплат в этом месяце</div></div>
</div>

<div class="panel">
  <div class="panel-head"><h2>Последние брони</h2><a class="btn" href="index.php?page=bookings">Все брони</a></div>
  <table>
    <tr><th>№</th><th>Домик</th><th>Гость</th><th>Даты</th><th>Гости</th><th>Статус</th><th>Сумма</th><th></th></tr>
    <?php if (!$recent): ?>
      <tr><td colspan="8" class="empty">Броней пока нет</td></tr>
    <?php endif; ?>
    <?php foreach ($recent as $b): ?>
      <tr>
        <td class="mono"><?= esc($b['number']) ?></td>
        <td><?= esc(cottage_name($b['cottage_id'])) ?></td>
        <td><?= esc($b['guest_name']) ?></td>
        <td><?= esc(date('d.m', strtotime($b['check_in']))) ?> – <?= esc(date('d.m.Y', strtotime($b['check_out']))) ?></td>
        <td><?= $b['guests'] ?></td>
        <td><?= status_badge($b['status']) ?></td>
        <td><strong><?= money($b['total']) ?></strong></td>
        <td><a class="btn btn-sm" href="index.php?page=booking_view&id=<?= $b['id'] ?>">Открыть</a></td>
      </tr>
    <?php endforeach; ?>
  </table>
</div>

<?php if ($role !== 'client' && $role !== 'housekeeper'): ?>
<div class="two-col">
  <div class="panel">
    <h2>Заезды сегодня (<?= date('d.m', strtotime($today)) ?>)</h2>
    <?php if (!$todayIn): ?>
      <p class="muted">Заездов нет.</p>
    <?php else: ?>
      <table>
        <tr><th>№</th><th>Домик</th><th>Гость</th><th></th></tr>
        <?php foreach ($todayIn as $b): ?>
          <tr>
            <td class="mono"><?= esc($b['number']) ?></td>
            <td><?= esc(cottage_name($b['cottage_id'])) ?></td>
            <td><?= esc($b['guest_name']) ?></td>
            <td><a class="btn btn-sm" href="index.php?page=booking_view&id=<?= $b['id'] ?>">Оформить</a></td>
          </tr>
        <?php endforeach; ?>
      </table>
    <?php endif; ?>
  </div>
  <div class="panel">
    <h2>Выезды сегодня</h2>
    <?php if (!$todayOut): ?>
      <p class="muted">Выездов нет.</p>
    <?php else: ?>
      <table>
        <tr><th>№</th><th>Домик</th><th>Гость</th><th></th></tr>
        <?php foreach ($todayOut as $b): ?>
          <tr>
            <td class="mono"><?= esc($b['number']) ?></td>
            <td><?= esc(cottage_name($b['cottage_id'])) ?></td>
            <td><?= esc($b['guest_name']) ?></td>
            <td><a class="btn btn-sm" href="index.php?page=booking_view&id=<?= $b['id'] ?>">Открыть</a></td>
          </tr>
        <?php endforeach; ?>
      </table>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<?php if ($role === 'client'): ?>
<div class="panel">
  <h2>Мои поездки</h2>
  <p class="muted">Бронируйте домики в разделе <a href="index.php?page=catalog">«Домики»</a> — выбор дат, расчёт цены и онлайн-оплата на сайте.</p>
</div>
<?php endif; ?>
