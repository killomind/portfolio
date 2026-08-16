<?php
$u = current_user();
$checks = db_all('checks');
$forms = db_all('forms');
$today = date('Y-m-d');

$todayChecks = array_values(array_filter($checks, function ($c) use ($today) { return substr($c['at'], 0, 10) === $today; }));
$okToday = count(array_filter($todayChecks, function ($c) { return $c['verdict'] === 'ok'; }));
$reworkToday = count(array_filter($todayChecks, function ($c) { return $c['verdict'] === 'rework'; }));
$rejectToday = count(array_filter($todayChecks, function ($c) { return $c['verdict'] === 'reject'; }));

$weekFrom = date('Y-m-d', strtotime('-7 days'));
$weekChecks = array_values(array_filter($checks, function ($c) use ($weekFrom) { return $c['at'] >= $weekFrom . ' 00:00:00'; }));
$weekOk = count(array_filter($weekChecks, function ($c) { return $c['verdict'] === 'ok'; }));
$weekY = count($weekChecks);
$yield = $weekY ? round(100 * $weekOk / $weekY, 1) : 0;

$defectCount = [];
foreach ($weekChecks as $c) {
  foreach ($c['found'] as $d) $defectCount[$d['type']] = isset($defectCount[$d['type']]) ? $defectCount[$d['type']] + 1 : 1;
}
arsort($defectCount);
$maxD = $defectCount ? max($defectCount) : 1;

$queue = array_values(array_filter($forms, function ($f) { return $f['status'] === 'queue'; }));
usort($checks, function ($a, $b) { return strcmp($b['at'], $a['at']); });
$recent = array_slice($checks, 0, 8);
?>
<div class="cards">
  <div class="card"><div class="num"><?= count($todayChecks) ?></div><div class="lbl">Проверок сегодня</div></div>
  <div class="card"><div class="num"><?= $yield ?>%</div><div class="lbl">Выход годных за 7 дней</div></div>
  <div class="card"><div class="num"><?= count($queue) ?></div><div class="lbl">В очереди контроля</div></div>
  <div class="card"><div class="num"><?= $rejectToday ?></div><div class="lbl">Брак сегодня</div></div>
</div>

<div class="two-col">
  <div class="panel">
    <div class="panel-head">
      <h2>Последние проверки</h2>
      <a class="btn btn-ghost btn-sm" href="index.php?page=checks">Весь журнал</a>
    </div>
    <table>
      <tr><th>Время</th><th>Форма</th><th>Оператор</th><th>Дефектов</th><th>Вердикт</th><th></th></tr>
      <?php if (!$recent): ?>
        <tr><td colspan="6" class="empty">Проверок пока нет</td></tr>
      <?php endif; ?>
      <?php foreach ($recent as $c): ?>
        <?php $f = db_find('forms', $c['form_id']); ?>
        <tr>
          <td class="mono"><?= esc(substr($c['at'], 5, 11)) ?></td>
          <td><?= $f ? esc($f['custom_no']) : '—' ?><br><span class="muted small"><?= $f ? esc($f['client']) : '' ?></span></td>
          <td><?= esc(user_name($c['operator_id'])) ?></td>
          <td><?= count($c['found']) ?></td>
          <td><?= verdict_badge($c['verdict']) ?></td>
          <td><a class="btn btn-sm" href="index.php?page=check_view&id=<?= $c['id'] ?>">Открыть</a></td>
        </tr>
      <?php endforeach; ?>
    </table>
  </div>

  <div class="panel">
    <h2>Дефекты за 7 дней, по типам</h2>
    <?php if (!$defectCount): ?>
      <p class="muted">Данных за период нет.</p>
    <?php else: ?>
      <div class="stack">
        <?php foreach ($defectCount as $key => $cnt): ?>
          <?php $dt = defect_type($key); ?>
          <div>
            <div class="stat-line">
              <span><?= $dt ? esc($dt['name']) : esc($key) ?></span>
              <b><?= $cnt ?> шт.</b>
            </div>
            <div class="bar"><div style="width:<?= round(100 * $cnt / $maxD) ?>%; background:<?= $dt ? esc($dt['color']) : '#64748b' ?>"></div></div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
    <div class="legend">
      <?php foreach (db_all('defects') as $d): ?>
        <span><i style="background:<?= esc($d['color']) ?>"></i><?= esc($d['name']) ?></span>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<?php if ($u['role'] === 'operator' || $u['role'] === 'engineer' || $u['role'] === 'admin'): ?>
  <div class="panel">
    <div class="panel-head">
      <h2>Очередь контроля форма — <?= count($queue) ?></h2>
      <a class="btn" href="index.php?page=scan">Рабочее место</a>
    </div>
    <?php if (!$queue): ?>
      <p class="muted">Очередь пуста — все формы проверены.</p>
    <?php else: ?>
      <table>
        <tr><th>№ формы</th><th>Клиент</th><th>Продукт</th><th>Размер</th><th>Полимер</th></tr>
        <?php foreach (array_slice($queue, 0, 6) as $f): ?>
          <tr>
            <td class="mono"><?= esc($f['custom_no']) ?></td>
            <td><?= esc($f['client']) ?></td>
            <td><?= esc($f['product']) ?></td>
            <td><?= esc($f['size_w']) ?>×<?= esc($f['size_h']) ?> мм</td>
            <td><?= esc($f['polymer']) ?></td>
          </tr>
        <?php endforeach; ?>
      </table>
    <?php endif; ?>
  </div>
<?php endif; ?>