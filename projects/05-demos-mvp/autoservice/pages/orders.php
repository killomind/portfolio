<?php
$u = current_user();
$orders = scoped_orders($u);

$fStatus = isset($_GET['f_status']) ? $_GET['f_status'] : '';
$fBranch = isset($_GET['f_branch']) ? (int)$_GET['f_branch'] : 0;
$fFrom = isset($_GET['f_from']) ? $_GET['f_from'] : '';
$fTo = isset($_GET['f_to']) ? $_GET['f_to'] : '';

$filtered = array_filter($orders, function ($o) use ($fStatus, $fBranch, $fFrom, $fTo) {
  if ($fStatus !== '' && $o['status'] !== $fStatus) return false;
  if ($fBranch && (int)$o['branch_id'] !== $fBranch) return false;
  if ($fFrom && $o['date'] < $fFrom) return false;
  if ($fTo && $o['date'] > $fTo) return false;
  return true;
});
usort($filtered, function ($a, $b) { return strcmp($b['created_at'], $a['created_at']); });

function qs($extra = [])
{
  $p = array_merge(['page' => 'orders'], array_filter(['f_status' => $_GET['f_status'] ?? '', 'f_branch' => $_GET['f_branch'] ?? '', 'f_from' => $_GET['f_from'] ?? '', 'f_to' => $_GET['f_to'] ?? ''], function ($v) { return $v !== ''; }), $extra);
  return 'index.php?' . http_build_query($p);
}
?>
<div class="panel">
  <div class="panel-head">
    <h2>Заказы</h2>
    <?php if (in_array($u['role'], ['admin', 'manager', 'advisor'], true)): ?>
      <a class="btn" href="index.php?page=order_edit">+ Новый заказ</a>
    <?php endif; ?>
  </div>
  <form class="filters" method="get" action="index.php">
    <input type="hidden" name="page" value="orders">
    <select name="f_status">
      <option value="">Все статусы</option>
      <?php foreach (STATUSES as $k => $v): ?>
        <option value="<?= $k ?>" <?= $fStatus === $k ? 'selected' : '' ?>><?= $v ?></option>
      <?php endforeach; ?>
    </select>
    <?php if ($u['role'] !== 'manager'): ?>
      <select name="f_branch">
        <option value="0">Все филиалы</option>
        <?php foreach (db_all('branches') as $b): ?>
          <option value="<?= $b['id'] ?>" <?= $fBranch === (int)$b['id'] ? 'selected' : '' ?>><?= esc($b['name']) ?></option>
        <?php endforeach; ?>
      </select>
    <?php endif; ?>
    <input type="date" name="f_from" value="<?= esc($fFrom) ?>">
    <input type="date" name="f_to" value="<?= esc($fTo) ?>">
    <button class="btn btn-sm" type="submit">Фильтр</button>
    <a class="btn btn-sm btn-ghost" href="index.php?page=orders">Сброс</a>
  </form>
  <table>
    <tr><th>№</th><th>Дата</th><th>Клиент</th><th>Авто</th><th>Филиал</th><th>Приёмщик</th><th>Механик</th><th>Сумма</th><th>Статус</th><th></th></tr>
    <?php if (!$filtered): ?>
      <tr><td colspan="10" class="empty">Ничего не найдено</td></tr>
    <?php endif; ?>
    <?php foreach ($filtered as $o): ?>
      <?php $c = db_find('clients', $o['client_id']); $car = db_find('cars', $o['car_id']); ?>
      <tr>
        <td><?= esc($o['number']) ?></td>
        <td><?= esc($o['date']) ?><br><span class="muted small"><?= esc($o['time']) ?></span></td>
        <td><?= $c ? esc($c['name']) : '—' ?></td>
        <td><?= $car ? esc($car['brand'] . ' ' . $car['model'] . ' ' . $car['plate']) : '—' ?></td>
        <td><?= esc(branch_name($o['branch_id'])) ?></td>
        <td><?= $o['advisor_id'] ? esc(user_name($o['advisor_id'])) : '—' ?></td>
        <td><?= $o['mechanic_id'] ? esc(user_name($o['mechanic_id'])) : '—' ?></td>
        <td><strong><?= money($o['total']) ?></strong></td>
        <td><?= status_badge($o['status']) ?></td>
        <td><a class="btn btn-sm" href="index.php?page=order_view&id=<?= $o['id'] ?>">Открыть</a></td>
      </tr>
    <?php endforeach; ?>
  </table>
</div>
