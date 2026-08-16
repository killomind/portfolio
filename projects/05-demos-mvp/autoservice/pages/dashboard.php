<?php
$u = current_user();
$role = $u['role'];
$orders = db_all('orders');
$today = date('Y-m-d');
$month = substr(date('Y-m'), 0, 7);

$my = scoped_orders($u);
$total = count($my);
$todayCount = count(array_filter($my, function ($o) use ($today) { return $o['date'] === $today; }));
$inWork = count(array_filter($my, function ($o) { return in_array($o['status'], ['diagnostics', 'work'], true); }));
$revenue = 0;
foreach ($my as $o) {
  if ($o['status'] !== 'cancelled' && substr($o['date'], 0, 7) === $month) $revenue += $o['total'];
}

usort($my, function ($a, $b) { return strcmp($b['created_at'], $a['created_at']); });
$recent = array_slice($my, 0, 8);
?>
<div class="cards">
  <div class="card"><div class="num"><?= $total ?></div><div class="lbl">Заказов всего</div></div>
  <div class="card"><div class="num"><?= $todayCount ?></div><div class="lbl">Заказов сегодня</div></div>
  <div class="card"><div class="num"><?= $inWork ?></div><div class="lbl">В работе</div></div>
  <div class="card"><div class="num"><?= money($revenue) ?></div><div class="lbl">Выручка за месяц</div></div>
</div>

<div class="panel">
  <div class="panel-head"><h2>Последние заказы</h2><a class="btn" href="index.php?page=orders">Все заказы</a></div>
  <table>
    <tr><th>№</th><th>Дата</th><th>Клиент</th><th>Авто</th><th>Филиал</th><th>Статус</th><th>Сумма</th><th></th></tr>
    <?php if (!$recent): ?>
      <tr><td colspan="8" class="empty">Заказов пока нет</td></tr>
    <?php endif; ?>
    <?php foreach ($recent as $o): ?>
      <?php $c = db_find('clients', $o['client_id']); $car = db_find('cars', $o['car_id']); ?>
      <tr>
        <td><?= esc($o['number']) ?></td>
        <td><?= esc($o['date']) ?> <?= esc($o['time']) ?></td>
        <td><?= $c ? esc($c['name']) : '—' ?></td>
        <td><?= $car ? esc($car['brand'] . ' ' . $car['model']) : '—' ?></td>
        <td><?= esc(branch_name($o['branch_id'])) ?></td>
        <td><?= status_badge($o['status']) ?></td>
        <td><strong><?= money($o['total']) ?></strong></td>
        <td><a class="btn btn-sm" href="index.php?page=order_view&id=<?= $o['id'] ?>">Открыть</a></td>
      </tr>
    <?php endforeach; ?>
  </table>
</div>

<?php if (in_array($role, ['advisor', 'manager', 'admin'], true)): ?>
  <?php $low = array_values(array_filter(db_all('parts'), function ($p) { return (int)$p['qty'] < (int)$p['min_qty']; })); ?>
  <div class="panel">
    <h2>Склад: детали ниже минимума</h2>
    <?php if (!$low): ?>
      <p class="muted">Все позиции в норме.</p>
    <?php else: ?>
      <table>
        <tr><th>Артикул</th><th>Наименование</th><th>Филиал</th><th>Остаток</th><th>Минимум</th></tr>
        <?php foreach ($low as $p): ?>
          <tr>
            <td><?= esc($p['sku']) ?></td>
            <td><?= esc($p['name']) ?></td>
            <td><?= esc(branch_name($p['branch_id'])) ?></td>
            <td><span class="low-stock"><?= $p['qty'] ?></span></td>
            <td><?= $p['min_qty'] ?></td>
          </tr>
        <?php endforeach; ?>
      </table>
    <?php endif; ?>
  </div>
<?php endif; ?>

<?php if ($role !== 'client'): ?>
  <?php $todayOrders = array_values(array_filter($orders, function ($o) use ($today) { return $o['date'] === $today && in_array($o['status'], ['new', 'confirmed'], true); })); ?>
  <div class="panel">
    <h2>Записи на сегодня</h2>
    <?php if (!$todayOrders): ?>
      <p class="muted">На сегодня записей нет.</p>
    <?php else: ?>
      <table>
        <tr><th>Время</th><th>Клиент</th><th>Авто</th><th>Филиал</th><th>Тип работ</th><th></th></tr>
        <?php foreach ($todayOrders as $o): ?>
          <?php $c = db_find('clients', $o['client_id']); $car = db_find('cars', $o['car_id']); ?>
          <tr>
            <td><?= esc($o['time']) ?></td>
            <td><?= $c ? esc($c['name']) : '—' ?></td>
            <td><?= $car ? esc($car['brand'] . ' ' . $car['model']) : '—' ?></td>
            <td><?= esc(branch_name($o['branch_id'])) ?></td>
            <td><?= esc($o['work_type']) ?></td>
            <td><a class="btn btn-sm" href="index.php?page=order_view&id=<?= $o['id'] ?>">Открыть</a></td>
          </tr>
        <?php endforeach; ?>
      </table>
    <?php endif; ?>
  </div>
<?php endif; ?>
