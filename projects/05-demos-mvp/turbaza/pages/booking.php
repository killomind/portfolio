<?php
$u = current_user();
$role = $u['role'];
$c = db_find('cottages', (int)($_GET['cottage'] ?? 0));
if (!$c) redirect('catalog', 'Выберите домик');

$today = date('Y-m-d');
$in = isset($_GET['in']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['in']) ? $_GET['in'] : $today;
$out = isset($_GET['out']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['out']) ? $_GET['out'] : date('Y-m-d', strtotime($today . ' +2 day'));
$guests = max(1, (int)($_GET['guests'] ?? 1));
$svcIds = array_map('intval', (array)($_GET['services'] ?? []));

$nights = nights_between($in, $out);
$nightTotal = night_total_for($c, $in, $out);
$extras = [];
$extrasTotal = 0;
foreach ($svcIds as $id) {
  $sv = db_find('services', $id);
  if (!$sv) continue;
  $amt = service_amount($sv, $nights, $guests);
  $extras[] = svc_snapshot($sv, $amt);
  $extrasTotal += $amt;
}
$total = $nightTotal + $extrasTotal;

$errors = [];
if (strcmp($out, $in) <= 0) $errors[] = 'Дата выезда должна быть позже даты заезда';
if ($nights < (int)$c['min_nights']) $errors[] = 'Минимальный срок проживания — ' . $c['min_nights'] . ' ночи';
if ($guests > (int)$c['capacity']) $errors[] = 'Домик вмещает не более ' . $c['capacity'] . ' гостей';
if (!is_available($c['id'], $in, $out)) $errors[] = 'Домик занят на выбранные даты';

$ts = strtotime($in);
$nightRows = [];
for ($d = $ts; $d < strtotime($out); $d += 86400) {
  $nightRows[] = ['date' => date('Y-m-d', $d), 'season' => season_label(date('Y-m-d', $d)), 'price' => night_price($c, date('Y-m-d', $d))];
}
?>
<div class="crumbs"><a href="index.php?page=catalog">Домики</a> / <a href="index.php?page=cottage&id=<?= $c['id'] ?>"><?= esc($c['name']) ?></a> / Бронирование</div>

<div class="two-col">
  <div>
    <div class="panel">
      <h2>Бронирование: <?= esc($c['name']) ?></h2>
      <form method="post" action="index.php" class="stack">
        <input type="hidden" name="act" value="booking_create">
        <input type="hidden" name="cottage_id" value="<?= $c['id'] ?>">
        <div class="form-grid">
          <label>Заезд <input type="date" name="check_in" value="<?= esc($in) ?>" min="<?= esc($today) ?>"></label>
          <label>Выезд <input type="date" name="check_out" value="<?= esc($out) ?>"></label>
          <label>Гостей
            <select name="guests">
              <?php for ($g = 1; $g <= 6; $g++): ?>
                <option value="<?= $g ?>" <?= $guests === $g ? 'selected' : '' ?>><?= $g ?></option>
              <?php endfor; ?>
            </select>
          </label>
        </div>

        <label class="svc-block">Доп. услуги</label>
        <div class="svc-list">
          <?php foreach (db_all('services') as $sv): ?>
            <label class="svc-item">
              <input type="checkbox" name="services[]" value="<?= $sv['id'] ?>" <?= in_array((int)$sv['id'], $svcIds, true) ? 'checked' : '' ?>>
              <span><?= esc($sv['name']) ?> — <?= money($sv['price']) ?> <?= esc($sv['unit']) ?></span>
            </label>
          <?php endforeach; ?>
        </div>

        <?php if ($role === 'client'): ?>
          <div class="comment">
            <strong>Гость: <?= esc($u['name']) ?></strong><br>
            <span class="muted"><?= esc($u['phone']) ?>, <?= esc($u['email']) ?></span><br>
            Подтверждение брони придёт на этот e-mail.
          </div>
        <?php else: ?>
          <label>Гость
            <select name="guest_id">
              <option value="0">— выберите гостя —</option>
              <?php foreach (db_all('guests') as $g): ?>
                <option value="<?= $g['id'] ?>"><?= esc($g['name'] . ' — ' . $g['phone']) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
        <?php endif; ?>

        <label>Комментарий <textarea name="comment" rows="2" placeholder="Пожелания, время заезда..."></textarea></label>

        <?php if ($errors): ?>
          <div class="errors">
            <?php foreach ($errors as $er): ?><div>⚠ <?= esc($er) ?></div><?php endforeach; ?>
          </div>
          <a class="btn btn-outline" href="index.php?page=cottage&id=<?= $c['id'] ?>">Выбрать другие даты</a>
        <?php else: ?>
          <button class="btn btn-dark btn-lg" type="submit">Подтвердить бронь</button>
        <?php endif; ?>
      </form>
    </div>
  </div>

  <div>
    <div class="panel">
      <h2>Расчёт стоимости</h2>
      <table>
        <tr><th>Ночь</th><th>Сезон</th><th>Цена</th></tr>
        <?php foreach ($nightRows as $nr): ?>
          <tr>
            <td><?= esc(date('d.m.Y', strtotime($nr['date']))) ?></td>
            <td><?= esc($nr['season']) ?></td>
            <td><?= money($nr['price']) ?></td>
          </tr>
        <?php endforeach; ?>
        <tr><td colspan="2"><strong>Проживание (<?= $nights ?> ночей)</strong></td><td><strong><?= money($nightTotal) ?></strong></td></tr>
        <?php if ($extras): ?>
          <tr><th colspan="3">Доп. услуги</th></tr>
          <?php foreach ($extras as $ex): ?>
            <tr><td colspan="2"><?= esc($ex['name']) ?></td><td><?= money($ex['amount']) ?></td></tr>
          <?php endforeach; ?>
          <tr><td colspan="2"><strong>Услуги</strong></td><td><strong><?= money($extrasTotal) ?></strong></td></tr>
        <?php endif; ?>
      </table>
      <div class="total-line">Итого: <strong id="total"><?= money($total) ?></strong></div>
      <p class="muted small">Оплата — онлайн на сайте после подтверждения брони. Стоимость рассчитана с учётом сезонных коэффициентов.</p>
    </div>
  </div>
</div>
