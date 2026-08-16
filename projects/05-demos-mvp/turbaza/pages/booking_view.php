<?php
$u = current_user();
$role = $u['role'];
$b = db_find('bookings', (int)($_GET['id'] ?? 0));
if (!$b) redirect('bookings', 'Бронь не найдена');
if ($role === 'client' && (int)$b['guest_id'] !== DEMO_GUEST_ID) redirect('dashboard', 'Нет доступа');
$c = db_find('cottages', $b['cottage_id']);
$pay = isset($_GET['pay']);
$next = TRANSITIONS[$b['status']] ?? [];
$editable = in_array($role, ['admin', 'manager', 'operator'], true);
$clientCancel = $role === 'client' && in_array($b['status'], ['new', 'confirmed', 'paid'], true) && in_array('cancelled', $next, true);
?>
<div class="crumbs"><a href="index.php?page=bookings">Брони</a> / <?= esc($b['number']) ?></div>

<div class="panel">
  <div class="panel-head">
    <h2>Бронь <?= esc($b['number']) ?> <?= status_badge($b['status']) ?></h2>
    <?php if (in_array($role, ['admin', 'manager'], true)): ?>
      <form method="post" action="index.php" onsubmit="return confirm('Удалить бронь?')">
        <input type="hidden" name="act" value="booking_delete">
        <input type="hidden" name="id" value="<?= $b['id'] ?>">
        <button class="btn btn-danger btn-sm" type="submit">Удалить</button>
      </form>
    <?php endif; ?>
  </div>
  <div class="info-grid">
    <div><span>Домик</span><a href="index.php?page=cottage&id=<?= $c['id'] ?>"><?= esc($c['name']) ?></a></div>
    <div><span>Даты</span><?= esc(date('d.m.Y', strtotime($b['check_in']))) ?> – <?= esc(date('d.m.Y', strtotime($b['check_out']))) ?> (<?= $b['nights'] ?> ночей)</div>
    <div><span>Гости</span><?= $b['guests'] ?> чел.</div>
    <div><span>Гость</span><?= esc($b['guest_name']) ?></div>
    <div><span>Телефон</span><?= esc($b['guest_phone']) ?></div>
    <div><span>E-mail</span><?= esc($b['guest_email']) ?></div>
    <div><span>Источник</span><?= $b['source'] === 'site' ? 'онлайн-бронирование' : 'менеджер' ?></div>
    <div><span>Создана</span><?= esc(date('d.m.Y H:i', strtotime($b['created_at']))) ?></div>
  </div>
  <?php if ($b['comment']): ?><div class="comment">Комментарий: <?= esc($b['comment']) ?></div><?php endif; ?>
</div>

<div class="two-col">
  <div class="panel">
    <h2>Состав и стоимость</h2>
    <table>
      <tr><th>Проживание (<?= $b['nights'] ?> ночей)</th><td><strong><?= money($b['night_total']) ?></strong></td></tr>
      <?php foreach ($b['services'] as $sv): ?>
        <tr><td><?= esc($sv['name']) ?></td><td><?= money($sv['amount']) ?></td></tr>
      <?php endforeach; ?>
      <tr><th>Итого</th><td><strong class="total-txt"><?= money($b['total']) ?></strong></td></tr>
    </table>
    <?php if ($b['payment']): ?>
      <div class="comment">
        Оплата: <?= esc($b['payment']['method']) ?>, <?= esc(date('d.m.Y H:i', strtotime($b['payment']['at']))) ?>, <?= money($b['payment']['amount']) ?>.
      </div>
    <?php else: ?>
      <div class="comment">Оплата ещё не получена.</div>
    <?php endif; ?>
  </div>

  <div class="panel">
    <h2>История</h2>
    <div class="timeline">
      <?php foreach (array_reverse($b['history']) as $h): ?>
        <div class="tl-item">
          <span class="tl-dot" style="background:<?= isset(STATUS_COLOR[$h['to']]) ? STATUS_COLOR[$h['to']] : '#64748b' ?>"></span>
          <div>
            <div class="tl-top"><strong><?= esc(STATUSES[$h['to']] ?? $h['to']) ?></strong> — <?= esc(date('d.m H:i', strtotime($h['at']))) ?></div>
            <div class="tl-user"><?= esc($h['user']) ?><?= $h['note'] ? ': ' . esc($h['note']) : '' ?></div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<?php if (($role === 'client' || $pay) && in_array($b['status'], ['new', 'confirmed'], true)): ?>
  <div class="panel">
    <h2>Онлайн-оплата</h2>
    <?php if ($pay): ?>
      <form method="post" action="index.php" class="stack pay-form">
        <input type="hidden" name="act" value="payment_pay">
        <input type="hidden" name="id" value="<?= $b['id'] ?>">
        <p class="muted">Демо-экран оплаты. Списывается <?= money($b['total']) ?>. В боевой версии здесь подключается платёжный шлюз.</p>
        <div class="form-grid">
          <label>Номер карты <input type="text" value="5100 0000 0000 0000" placeholder="0000 0000 0000 0000"></label>
          <label>Срок <input type="text" value="12/28" placeholder="ММ/ГГ"></label>
          <label>CVC <input type="text" value="123" placeholder="000"></label>
        </div>
        <button class="btn btn-dark btn-lg" type="submit">Оплатить <?= money($b['total']) ?></button>
      </form>
    <?php else: ?>
      <p class="muted">Бронь ждёт оплаты. После оплаты подтверждение придёт на <?= esc($b['guest_email']) ?>.</p>
      <a class="btn btn-dark btn-lg" href="index.php?page=booking_view&id=<?= $b['id'] ?>&pay=1">Перейти к оплате</a>
    <?php endif; ?>
  </div>
<?php endif; ?>

<?php if ($editable): ?>
  <div class="panel">
    <h2>Действия</h2>
    <?php if (!$next): ?>
      <p class="muted">Дальнейших переходов для статуса «<?= esc(STATUSES[$b['status']]) ?>» нет.</p>
    <?php else: ?>
      <form method="post" action="index.php" class="inline-form">
        <input type="hidden" name="act" value="booking_status">
        <input type="hidden" name="id" value="<?= $b['id'] ?>">
        <label>Перевести в статус
          <select name="to">
            <?php foreach ($next as $st): ?>
              <option value="<?= $st ?>"><?= esc(STATUSES[$st]) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label>Комментарий <input type="text" name="note" placeholder="необязательно"></label>
        <button class="btn" type="submit">Применить</button>
      </form>
    <?php endif; ?>
  </div>
<?php endif; ?>

<?php if ($clientCancel): ?>
  <div class="panel">
    <form method="post" action="index.php" onsubmit="return confirm('Отменить бронь?')">
      <input type="hidden" name="act" value="booking_status">
      <input type="hidden" name="id" value="<?= $b['id'] ?>">
      <input type="hidden" name="to" value="cancelled">
      <button class="btn btn-danger" type="submit">Отменить бронь</button>
    </form>
  </div>
<?php endif; ?>
