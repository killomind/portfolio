<?php
require_role(['admin', 'manager']);
$bookings = db_all('bookings');
$cottages = db_all('cottages');
$month = date('Y-m');

$months = [];
for ($i = 5; $i >= 0; $i--) $months[] = date('Y-m', strtotime($month . '-01 -' . $i . ' month'));

$byMonth = [];
foreach ($months as $mm) $byMonth[$mm] = 0;
foreach ($bookings as $b) {
  if (!empty($b['payment'])) {
    $pm = substr($b['payment']['at'], 0, 7);
    if (isset($byMonth[$pm])) $byMonth[$pm] += (int)$b['payment']['amount'];
  }
}

$byStatus = [];
foreach (STATUSES as $k => $v) $byStatus[$k] = ['count' => 0, 'sum' => 0];
foreach ($bookings as $b) {
  if (isset($byStatus[$b['status']])) {
    $byStatus[$b['status']]['count']++;
    if ($b['status'] !== 'cancelled') $byStatus[$b['status']]['sum'] += (int)$b['total'];
  }
}

$occByCottage = [];
$daysInMonth = (int)date('t', strtotime($month . '-01'));
foreach ($cottages as $c) {
  $n = 0;
  $ts = strtotime($month . '-01');
  for ($d = 0; $d < $daysInMonth; $d++) {
    $day = date('Y-m-d', $ts + $d * 86400);
    foreach ($bookings as $b) {
      if ((int)$b['cottage_id'] === (int)$c['id'] && $b['status'] !== 'cancelled' && strcmp($b['check_in'], $day) <= 0 && strcmp($b['check_out'], $day) > 0) { $n++; break; }
    }
  }
  $occByCottage[$c['id']] = round($n / $daysInMonth * 100);
}

$revByCottage = [];
foreach ($cottages as $c) $revByCottage[$c['id']] = 0;
foreach ($bookings as $b) {
  if (!empty($b['payment']) && strpos($b['payment']['at'], $month) === 0) $revByCottage[$b['cottage_id']] += (int)$b['payment']['amount'];
}
?>
<div class="cards">
  <?php $cur = $byMonth[$month]; $prev = $months[count($months) - 2]; $pv = $byMonth[$prev]; ?>
  <div class="card"><div class="num"><?= money($cur) ?></div><div class="lbl">Оплаты за <?= esc(date('F Y', strtotime($month . '-01'))) ?></div></div>
  <div class="card"><div class="num"><?= $pv ? round(($cur - $pv) / $pv * 100) . '%' : '—' ?></div><div class="lbl">Динамика к прошлому месяцу</div></div>
  <div class="card"><div class="num"><?= $byStatus['paid']['count'] + $byStatus['checked_in']['count'] + $byStatus['checked_out']['count'] ?></div><div class="lbl">Оплаченных броней</div></div>
</div>

<div class="two-col">
  <div class="panel">
    <h2>Оплаты по месяцам</h2>
    <table>
      <tr><th>Месяц</th><th>Сумма</th></tr>
      <?php foreach ($months as $mm): ?>
        <tr><td><?= esc(date('F Y', strtotime($mm . '-01'))) ?></td><td><strong><?= money($byMonth[$mm]) ?></strong></td></tr>
      <?php endforeach; ?>
    </table>
  </div>
  <div class="panel">
    <h2>Брони по статусам</h2>
    <table>
      <tr><th>Статус</th><th>Кол-во</th><th>Сумма</th></tr>
      <?php foreach (STATUSES as $k => $v): ?>
        <tr>
          <td><?= status_badge($k) ?></td>
          <td><?= $byStatus[$k]['count'] ?></td>
          <td><?= money($byStatus[$k]['sum']) ?></td>
        </tr>
      <?php endforeach; ?>
    </table>
  </div>
</div>

<div class="two-col">
  <div class="panel">
    <h2>Загрузка домиков за <?= esc(date('F Y', strtotime($month . '-01'))) ?></h2>
    <table>
      <tr><th>Домик</th><th>Загрузка</th><th>Баланс</th></tr>
      <?php foreach ($cottages as $c): ?>
        <?php $o = $occByCottage[$c['id']]; ?>
        <tr>
          <td><?= esc($c['name']) ?></td>
          <td><?= $o ?>%</td>
          <td><div class="bar"><div class="bar-fill" style="width:<?= $o ?>%"></div></div></td>
        </tr>
      <?php endforeach; ?>
    </table>
  </div>
  <div class="panel">
    <h2>Выручка по домикам за месяц</h2>
    <table>
      <tr><th>Домик</th><th>Сумма</th></tr>
      <?php foreach ($cottages as $c): ?>
        <tr><td><?= esc($c['name']) ?></td><td><strong><?= money($revByCottage[$c['id']]) ?></strong></td></tr>
      <?php endforeach; ?>
    </table>
  </div>
</div>
