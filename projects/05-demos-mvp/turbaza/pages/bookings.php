<?php
$u = current_user();
$role = $u['role'];
$fStatus = trim($_GET['status'] ?? '');
$fCottage = (int)($_GET['cottage'] ?? 0);

$bookings = scoped_bookings($u);
if ($fStatus && in_array($fStatus, array_keys(STATUSES), true)) {
  $bookings = array_values(array_filter($bookings, function ($b) use ($fStatus) { return $b['status'] === $fStatus; }));
}
if ($fCottage) {
  $bookings = array_values(array_filter($bookings, function ($b) use ($fCottage) { return (int)$b['cottage_id'] === $fCottage; }));
}
usort($bookings, function ($a, $b) { return strcmp($b['created_at'], $a['created_at']); });
?>
<div class="panel">
  <div class="panel-head">
    <h2><?= $role === 'housekeeper' ? 'Брони к подготовке' : 'Список броней' ?></h2>
    <?php if (in_array($role, ['admin', 'manager', 'operator'], true)): ?>
      <a class="btn" href="index.php?page=catalog">Новая бронь</a>
    <?php endif; ?>
  </div>
  <form class="filters" method="get" action="index.php">
    <input type="hidden" name="page" value="bookings">
    <select name="status">
      <option value="">Все статусы</option>
      <?php foreach (STATUSES as $k => $v): ?>
        <option value="<?= $k ?>" <?= $fStatus === $k ? 'selected' : '' ?>><?= esc($v) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="cottage">
      <option value="0">Все домики</option>
      <?php foreach (db_all('cottages') as $c): ?>
        <option value="<?= $c['id'] ?>" <?= $fCottage === (int)$c['id'] ? 'selected' : '' ?>><?= esc($c['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn btn-sm" type="submit">Фильтр</button>
  </form>
  <table>
    <tr><th>№</th><th>Домик</th><th>Гость</th><th>Даты</th><th>Гости</th><th>Источник</th><th>Статус</th><th>Сумма</th><th></th></tr>
    <?php if (!$bookings): ?>
      <tr><td colspan="9" class="empty">Броней нет</td></tr>
    <?php endif; ?>
    <?php foreach ($bookings as $b): ?>
      <tr>
        <td class="mono"><?= esc($b['number']) ?></td>
        <td><?= esc(cottage_name($b['cottage_id'])) ?></td>
        <td><?= esc($b['guest_name']) ?></td>
        <td><?= esc(date('d.m', strtotime($b['check_in']))) ?> – <?= esc(date('d.m.Y', strtotime($b['check_out']))) ?></td>
        <td><?= $b['guests'] ?></td>
        <td><?= $b['source'] === 'site' ? 'сайт' : 'менеджер' ?></td>
        <td><?= status_badge($b['status']) ?></td>
        <td><strong><?= money($b['total']) ?></strong></td>
        <td><a class="btn btn-sm" href="index.php?page=booking_view&id=<?= $b['id'] ?>">Открыть</a></td>
      </tr>
    <?php endforeach; ?>
  </table>
</div>
