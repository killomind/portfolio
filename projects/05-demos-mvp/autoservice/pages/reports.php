<?php
$u = current_user();
$orders = scoped_orders($u);

$revenue = 0;
$byStatus = array_fill_keys(array_keys(STATUSES), 0);
$byBranch = [];
foreach (db_all('branches') as $b) $byBranch[$b['id']] = 0;
$byMonth = [];

foreach ($orders as $o) {
  if (isset($byStatus[$o['status']])) $byStatus[$o['status']]++;
  if (isset($byBranch[(int)$o['branch_id']])) $byBranch[(int)$o['branch_id']] += $o['total'];
  if ($o['status'] !== 'cancelled') $revenue += $o['total'];
  $m = substr($o['date'], 0, 7);
  if (!isset($byMonth[$m])) $byMonth[$m] = ['count' => 0, 'revenue' => 0];
  $byMonth[$m]['count']++;
  if ($o['status'] !== 'cancelled') $byMonth[$m]['revenue'] += $o['total'];
}
ksort($byMonth);
$avg = count($orders) ? (int)round($revenue / count($orders)) : 0;
$months = array_slice(array_keys($byMonth), -6);
?>
<div class="cards">
  <div class="card"><div class="num"><?= money($revenue) ?></div><div class="lbl">Выручка</div></div>
  <div class="card"><div class="num"><?= count($orders) ?></div><div class="lbl">Заказов</div></div>
  <div class="card"><div class="num"><?= money($avg) ?></div><div class="lbl">Средний чек</div></div>
  <div class="card"><div class="num"><?= $byStatus['work'] + $byStatus['diagnostics'] ?></div><div class="lbl">Сейчас в работе</div></div>
</div>

<div class="two-col">
  <div class="panel">
    <h2>Заказы по статусам</h2>
    <table>
      <tr><th>Статус</th><th>Кол-во</th></tr>
      <?php foreach (STATUSES as $k => $v): ?>
        <tr><td><?= status_badge($k) ?></td><td><?= $byStatus[$k] ?></td></tr>
      <?php endforeach; ?>
    </table>
  </div>
  <div class="panel">
    <h2>Выручка по филиалам</h2>
    <table>
      <tr><th>Филиал</th><th>Выручка</th></tr>
      <?php foreach ($byBranch as $bid => $sum): ?>
        <tr><td><?= esc(branch_name($bid)) ?></td><td><?= money($sum) ?></td></tr>
      <?php endforeach; ?>
    </table>
  </div>
</div>

<div class="panel">
  <h2>Динамика по месяцам</h2>
  <table>
    <tr><th>Месяц</th><th>Заказов</th><th>Выручка</th></tr>
    <?php
      $russian = ['01' => 'январь', '02' => 'февраль', '03' => 'март', '04' => 'апрель', '05' => 'май', '06' => 'июнь', '07' => 'июль', '08' => 'август', '09' => 'сентябрь', '10' => 'октябрь', '11' => 'ноябрь', '12' => 'декабрь'];
      foreach ($months as $m):
    ?>
      <tr>
        <td><?= esc(($russian[substr($m, 5, 2)] ?? $m) . ' ' . substr($m, 0, 4)) ?></td>
        <td><?= $byMonth[$m]['count'] ?></td>
        <td><?= money($byMonth[$m]['revenue']) ?></td>
      </tr>
    <?php endforeach; ?>
  </table>
</div>
