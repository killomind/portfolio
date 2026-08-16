<?php
$u = current_user();
$role = $u['role'];

$date = isset($_GET['date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date']) ? $_GET['date'] : date('Y-m-d');
$branch = (int)($_GET['branch'] ?? 0);
if (!$branch) $branch = (int)$u['branch_id'];
if (!$branch) $branch = 1;
$book = isset($_GET['book']) ? 1 : 0;
$bookTime = isset($_GET['time']) ? preg_replace('/[^0-9:]/', '', $_GET['time']) : '';

$branches = db_all('branches');
$orders = db_all('orders');
$booked = array_filter($orders, function ($o) use ($date, $branch) { return $o['date'] === $date && (int)$o['branch_id'] === $branch && !in_array($o['status'], ['cancelled', 'issued'], true); });
$slots = [];
for ($h = 9; $h <= 19; $h++) {
  $t = str_pad($h, 2, '0', STR_PAD_LEFT) . ':00';
  $occupied = null;
  foreach ($booked as $o) if ($o['time'] === $t) { $occupied = $o; break; }
  $slots[$t] = $occupied;
}

$myCars = [];
if ($role === 'client') $myCars = array_values(array_filter(db_all('cars'), function ($car) { return (int)$car['client_id'] === DEMO_CLIENT_ID; }));
?>
<div class="panel">
  <div class="panel-head"><h2>Календарь записи</h2></div>
  <form class="filters" method="get" action="index.php">
    <input type="hidden" name="page" value="calendar">
    <input type="date" name="date" value="<?= esc($date) ?>">
    <select name="branch">
      <?php foreach ($branches as $b): ?>
        <option value="<?= $b['id'] ?>" <?= $branch === (int)$b['id'] ? 'selected' : '' ?>><?= esc($b['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn btn-sm" type="submit">Показать</button>
  </form>
  <div class="slot-grid">
    <?php foreach ($slots as $t => $o): ?>
      <?php if ($o): ?>
        <?php $c = db_find('clients', $o['client_id']); $car = db_find('cars', $o['car_id']); ?>
        <div class="slot occupied">
          <b><?= $t ?></b>
          <span><?= $c ? esc($c['name']) : '—' ?></span>
          <span class="muted small"><?= $car ? esc($car['brand'] . ' ' . $car['model']) : '' ?></span>
          <?= status_badge($o['status']) ?>
          <?php if ($role !== 'client'): ?>
            <a class="btn btn-sm" href="index.php?page=order_view&id=<?= $o['id'] ?>">Открыть</a>
          <?php endif; ?>
        </div>
      <?php else: ?>
        <div class="slot free">
          <b><?= $t ?></b>
          <span class="ok">свободно</span>
          <?php if ($role === 'client'): ?>
            <a class="btn btn-sm" href="index.php?page=calendar&date=<?= $date ?>&branch=<?= $branch ?>&book=1&time=<?= $t ?>">Записаться</a>
          <?php else: ?>
            <a class="btn btn-sm" href="index.php?page=order_edit&date=<?= $date ?>&branch=<?= $branch ?>&time=<?= $t ?>">Записать</a>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    <?php endforeach; ?>
  </div>
</div>

<?php if ($book && $bookTime && $role === 'client'): ?>
  <div class="panel">
    <h2>Онлайн-запись на <?= esc($date) ?> в <?= esc($bookTime) ?></h2>
    <?php if (!$myCars): ?>
      <p class="muted">Сначала добавьте автомобиль в разделе «Автомобили».</p>
    <?php else: ?>
      <form method="post" action="index.php" class="form-grid">
        <input type="hidden" name="act" value="order_save">
        <input type="hidden" name="branch_id" value="<?= $branch ?>">
        <input type="hidden" name="date" value="<?= esc($date) ?>">
        <input type="hidden" name="time" value="<?= esc($bookTime) ?>">
        <label>Автомобиль
          <select name="car_id">
            <?php foreach ($myCars as $car): ?>
              <option value="<?= $car['id'] ?>"><?= esc($car['brand'] . ' ' . $car['model'] . ' (' . $car['plate'] . ')') ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label>Тип работ <input type="text" name="work_type" placeholder="Например: диагностика, замена масла"></label>
        <label>Комментарий <textarea name="comment" rows="2"></textarea></label>
        <button class="btn" type="submit">Подтвердить запись</button>
      </form>
    <?php endif; ?>
  </div>
<?php endif; ?>
