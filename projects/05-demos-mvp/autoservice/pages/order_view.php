<?php
$u = current_user();
$id = (int)($_GET['id'] ?? 0);
$o = db_find('orders', $id);
if (!$o) redirect('orders', 'Заказ не найден');

$canView = in_array($u['role'], ['admin', 'manager', 'advisor', 'mechanic'], true);
if ($u['role'] === 'client') $canView = (int)$o['client_id'] === DEMO_CLIENT_ID;
if ($u['role'] === 'mechanic') $canView = (int)$o['mechanic_id'] === (int)$u['id'] || (int)$o['mechanic_id'] === 0;
if ($u['role'] === 'manager') $canView = (int)$o['branch_id'] === (int)$u['branch_id'];
if (!$canView) redirect('orders', 'Нет доступа к заказу');

$c = db_find('clients', $o['client_id']);
$car = db_find('cars', $o['car_id']);

$allowed = [];
if (in_array($u['role'], ['admin', 'manager'], true)) $allowed = TRANSITIONS[$o['status']] ?? [];
if ($u['role'] === 'advisor') $allowed = TRANSITIONS[$o['status']] ?? [];
if ($u['role'] === 'mechanic') {
  foreach (TRANSITIONS[$o['status']] ?? [] as $t) if (in_array($t, ['work', 'ready'], true)) $allowed[] = $t;
}
?>
<div class="panel">
  <div class="panel-head">
    <h2>Заказ <?= esc($o['number']) ?></h2>
    <?php if (in_array($u['role'], ['admin', 'manager', 'advisor'], true)): ?>
      <a class="btn" href="index.php?page=order_edit&id=<?= $o['id'] ?>">Редактировать</a>
    <?php endif; ?>
  </div>
  <div class="info-grid">
    <div><span>Клиент</span><?= $c ? esc($c['name']) : '—' ?><br><small class="muted"><?= $c ? esc($c['phone']) : '' ?></small></div>
    <div><span>Автомобиль</span><?= $car ? esc($car['brand'] . ' ' . $car['model']) : '—' ?><br><small class="muted"><?= $car ? esc($car['year'] . ' • ' . $car['plate'] . ' • ' . $car['vin']) : '' ?></small></div>
    <div><span>Филиал</span><?= esc(branch_name($o['branch_id'])) ?></div>
    <div><span>Дата и время</span><?= esc($o['date']) ?> <?= esc($o['time']) ?></div>
    <div><span>Тип работ</span><?= esc($o['work_type'] ?: '—') ?></div>
    <div><span>Статус</span><?= status_badge($o['status']) ?></div>
    <div><span>Мастер-приёмщик</span><?= $o['advisor_id'] ? esc(user_name($o['advisor_id'])) : 'онлайн-запись' ?></div>
    <div><span>Механик</span><?= $o['mechanic_id'] ? esc(user_name($o['mechanic_id'])) : '—' ?></div>
  </div>
  <?php if ($o['comment']): ?><div class="comment"><b>Комментарий:</b> <?= esc($o['comment']) ?></div><?php endif; ?>
</div>

<div class="two-col">
  <div class="panel">
    <h2>Услуги</h2>
    <table>
      <tr><th>Наименование</th><th>Цена</th></tr>
      <?php if (!$o['services']): ?><tr><td colspan="2" class="empty">Не указаны</td></tr><?php endif; ?>
      <?php foreach ($o['services'] as $s): ?>
        <tr><td><?= esc($s['name']) ?></td><td><?= money($s['price']) ?></td></tr>
      <?php endforeach; ?>
    </table>
  </div>
  <div class="panel">
    <h2>Запчасти</h2>
    <table>
      <tr><th>Наименование</th><th>Кол-во</th><th>Сумма</th></tr>
      <?php if (!$o['parts']): ?><tr><td colspan="3" class="empty">Не указаны</td></tr><?php endif; ?>
      <?php foreach ($o['parts'] as $p): ?>
        <tr><td><?= esc($p['name']) ?></td><td><?= $p['qty'] ?></td><td><?= money($p['price'] * $p['qty']) ?></td></tr>
      <?php endforeach; ?>
    </table>
  </div>
</div>

<div class="panel total-line">Итого: <strong><?= money($o['total']) ?></strong></div>

<?php if ($allowed): ?>
  <div class="panel">
    <h2>Сменить статус</h2>
    <form method="post" action="index.php" class="inline-form">
      <input type="hidden" name="act" value="order_status">
      <input type="hidden" name="id" value="<?= $o['id'] ?>">
      <select name="to">
        <?php foreach ($allowed as $t): ?>
          <option value="<?= $t ?>">→ <?= esc(STATUSES[$t]) ?></option>
        <?php endforeach; ?>
      </select>
      <input type="text" name="note" placeholder="Комментарий к переходу (необязательно)">
      <button class="btn" type="submit">Применить</button>
    </form>
  </div>
<?php endif; ?>

<div class="panel">
  <h2>История статусов</h2>
  <div class="timeline">
    <?php foreach (array_reverse($o['history'] ?? []) as $h): ?>
      <div class="tl-item">
        <div class="tl-dot" style="background:<?= isset(STATUS_COLOR[$h['to']]) ? STATUS_COLOR[$h['to']] : '#64748b' ?>"></div>
        <div class="tl-body">
          <div class="tl-top"><?= status_badge($h['to']) ?> <span class="muted small"><?= esc($h['at']) ?></span></div>
          <div class="tl-user"><?= esc($h['user']) ?><?= $h['from'] ? ' · из «' . esc(STATUSES[$h['from']] ?? $h['from']) . '»' : '' ?></div>
          <?php if ($h['note']): ?><div class="tl-note"><?= esc($h['note']) ?></div><?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<?php if (in_array($u['role'], ['admin', 'manager'], true)): ?>
  <form method="post" action="index.php" onsubmit="return confirm('Удалить заказ безвозвратно?')">
    <input type="hidden" name="act" value="order_delete">
    <input type="hidden" name="id" value="<?= $o['id'] ?>">
    <button class="btn btn-danger" type="submit">Удалить заказ</button>
  </form>
<?php endif; ?>
