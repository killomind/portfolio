<?php
$u = current_user();
$role = $u['role'];
$c = db_find('cottages', (int)($_GET['id'] ?? 0));
if (!$c) redirect('catalog', 'Домик не найден');

$in = isset($_GET['in']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['in']) ? $_GET['in'] : '';
$out = isset($_GET['out']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['out']) ? $_GET['out'] : '';
$guests = max(1, (int)($_GET['guests'] ?? 1));

$m = isset($_GET['m']) && preg_match('/^\d{4}-\d{2}$/', $_GET['m']) ? $_GET['m'] : date('Y-m');
$year = (int)substr($m, 0, 4);
$mon = (int)substr($m, 5, 2);
$first = mktime(0, 0, 0, $mon, 1, $year);
$daysInMonth = (int)date('t', $first);
$wd0 = (int)date('w', $first);
$wd0 = $wd0 === 0 ? 6 : $wd0 - 1;
$occ = occupied_dates($c['id']);
$prev = date('Y-m', strtotime($m . '-01 -1 month'));
$next = date('Y-m', strtotime($m . '-01 +1 month'));
$today = date('Y-m-d');
$range = $in && $out && strcmp($out, $in) > 0;
$nights = $range ? nights_between($in, $out) : 0;
$nTotal = $range ? night_total_for($c, $in, $out) : 0;
$avail = $range ? is_available($c['id'], $in, $out) : null;
?>
<div class="crumbs"><a href="index.php?page=catalog">Домики</a> / <?= esc($c['name']) ?></div>

<div class="two-col">
  <div>
    <?= photo_block($c, 'lg') ?>
    <div class="panel">
      <h2>Об домике</h2>
      <p><?= esc($c['description']) ?></p>
      <div class="info-grid">
        <div><span>Тип</span><?= esc($c['type']) ?></div>
        <div><span>Вместимость</span>до <?= $c['capacity'] ?> гостей</div>
        <div><span>Площадь</span><?= $c['area'] ?> м²</div>
        <div><span>Мин. срок</span><?= $c['min_nights'] ?> ночи</div>
        <div><span>Базовая цена</span><?= money($c['price']) ?> / ночь</div>
      </div>
      <h3 class="cat-title">Удобства</h3>
      <ul class="amenity-list">
        <?php foreach ($c['amenities'] as $a): ?>
          <li><?= esc($a) ?></li>
        <?php endforeach; ?>
      </ul>
      <?php if (in_array($role, ['admin'], true)): ?>
        <div class="card-actions">
          <a class="btn btn-outline" href="index.php?page=catalog&edit=<?= $c['id'] ?>">Изменить</a>
          <form method="post" action="index.php" onsubmit="return confirm('Удалить домик?')">
            <input type="hidden" name="act" value="cottage_delete">
            <input type="hidden" name="id" value="<?= $c['id'] ?>">
            <button class="btn btn-danger" type="submit">Удалить</button>
          </form>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <div>
    <div class="panel">
      <h2>Цена по сезонам</h2>
      <table>
        <tr><th>Сезон</th><th>Период</th><th>Коэф.</th><th>₽/ночь</th></tr>
        <?php foreach (db_all('seasons') as $s): ?>
          <tr>
            <td><?= esc($s['name']) ?></td>
            <td><?= esc(date('d.m', strtotime($s['from'] . '-2000'))) ?> – <?= esc(date('d.m', strtotime($s['to'] . '-2000'))) ?></td>
            <td>×<?= (float)$s['mult'] ?></td>
            <td><strong><?= money((int)round($c['price'] * (float)$s['mult'])) ?></strong></td>
          </tr>
        <?php endforeach; ?>
      </table>
    </div>

    <div class="panel">
      <h2>Календарь доступности — <?= esc(date('F Y', $first)) ?></h2>
      <div class="cal-nav">
        <a class="btn btn-sm btn-ghost" href="index.php?page=cottage&id=<?= $c['id'] ?>&m=<?= $prev ?>">←</a>
        <a class="btn btn-sm btn-ghost" href="index.php?page=cottage&id=<?= $c['id'] ?>&m=<?= $next ?>">→</a>
      </div>
      <div class="mini-cal">
        <div class="cal-wd">Пн</div><div class="cal-wd">Вт</div><div class="cal-wd">Ср</div><div class="cal-wd">Чт</div><div class="cal-wd">Пт</div><div class="cal-wd">Сб</div><div class="cal-wd">Вс</div>
        <?php for ($i = 0; $i < $wd0; $i++): ?>
          <div class="cal-cell blank"></div>
        <?php endfor; ?>
        <?php for ($d = 1; $d <= $daysInMonth; $d++): ?>
          <?php $day = sprintf('%04d-%02d-%02d', $year, $mon, $d); ?>
          <div class="cal-cell <?= isset($occ[$day]) ? 'busy' : 'free' ?> <?= $day === $today ? 'today' : '' ?>"><?= $d ?></div>
        <?php endfor; ?>
      </div>
      <p class="muted small"><span class="dot free"></span> свободно&nbsp;&nbsp;<span class="dot busy"></span> занято</p>
    </div>

    <div class="panel">
      <h2>Быстрое бронирование</h2>
      <form method="get" action="index.php" class="stack">
        <input type="hidden" name="page" value="booking">
        <input type="hidden" name="cottage" value="<?= $c['id'] ?>">
        <div class="form-grid">
          <label>Заезд <input type="date" name="in" value="<?= esc($in ?: $today) ?>" min="<?= esc($today) ?>"></label>
          <label>Выезд <input type="date" name="out" value="<?= esc($out ?: date('Y-m-d', strtotime($today . ' +2 day'))) ?>"></label>
          <label>Гостей
            <select name="guests">
              <?php for ($g = 1; $g <= (int)$c['capacity']; $g++): ?>
                <option value="<?= $g ?>" <?= $guests === $g ? 'selected' : '' ?>><?= $g ?></option>
              <?php endfor; ?>
            </select>
          </label>
        </div>
        <label class="svc-block">Доп. услуги (отметить нужные)</label>
        <div class="svc-list">
          <?php foreach (db_all('services') as $sv): ?>
            <label class="svc-item">
              <input type="checkbox" name="services[]" value="<?= $sv['id'] ?>">
              <span><?= esc($sv['name']) ?> — <?= money($sv['price']) ?></span>
            </label>
          <?php endforeach; ?>
        </div>
        <button class="btn btn-dark btn-lg" type="submit">Рассчитать и забронировать</button>
      </form>
      <?php if ($range): ?>
        <div class="calc-note">
          <?php if ($avail === false): ?>
            <span class="b-bad-txt">Домик занят на выбранные даты.</span>
          <?php else: ?>
            <span class="b-ok-txt">Свободен: <?= $nights ?> ночи, итого <strong><?= money($nTotal) ?></strong> (без доп. услуг).</span>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
