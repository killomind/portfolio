<?php
$checks = db_all('checks');
$period = isset($_GET['period']) ? $_GET['period'] : '14';
$from = date('Y-m-d', strtotime('-' . (int)$period . ' days'));
$list = array_values(array_filter($checks, function ($c) use ($from) { return $c['at'] >= $from . ' 00:00:00'; }));

$total = count($list);
$ok = count(array_filter($list, function ($c) { return $c['verdict'] === 'ok'; }));
$rework = count(array_filter($list, function ($c) { return $c['verdict'] === 'rework'; }));
$reject = count(array_filter($list, function ($c) { return $c['verdict'] === 'reject'; }));
$yield = $total ? round(100 * $ok / $total, 1) : 0;

$defByType = [];
$sevCritical = 0;
$sevMajor = 0;
foreach ($list as $c) {
  foreach ($c['found'] as $d) {
    $defByType[$d['type']] = isset($defByType[$d['type']]) ? $defByType[$d['type']] + 1 : 1;
    $dt = defect_type($d['type']);
    if ($dt && $dt['severity'] === 'critical') $sevCritical++;
    elseif ($dt && $dt['severity'] === 'major') $sevMajor++;
  }
}
arsort($defByType);
$maxD = $defByType ? max($defByType) : 1;

$byOperator = [];
foreach ($list as $c) {
  $name = user_name($c['operator_id']);
  if (!isset($byOperator[$name])) $byOperator[$name] = ['n' => 0, 'ok' => 0];
  $byOperator[$name]['n']++;
  if ($c['verdict'] === 'ok') $byOperator[$name]['ok']++;
}

$byDay = [];
foreach ($list as $c) {
  $day = substr($c['at'], 0, 10);
  if (!isset($byDay[$day])) $byDay[$day] = ['n' => 0, 'ok' => 0];
  $byDay[$day]['n']++;
  if ($c['verdict'] === 'ok') $byDay[$day]['ok']++;
}
ksort($byDay);
?>
<div class="filters">
  <form method="get" action="index.php" class="inline-form">
    <input type="hidden" name="page" value="reports">
    <select name="period" onchange="this.form.submit()">
      <option value="7" <?= $period === '7' ? 'selected' : '' ?>>7 дней</option>
      <option value="14" <?= $period === '14' ? 'selected' : '' ?>>14 дней</option>
      <option value="30" <?= $period === '30' ? 'selected' : '' ?>>30 дней</option>
    </select>
    <noscript><button class="btn">Применить</button></noscript>
  </form>
</div>

<div class="cards">
  <div class="card"><div class="num"><?= $total ?></div><div class="lbl">Проверок за период</div></div>
  <div class="card"><div class="num"><?= $yield ?>%</div><div class="lbl">Выход годных</div></div>
  <div class="card"><div class="num"><?= $rework ?></div><div class="lbl">На доработку</div></div>
  <div class="card"><div class="num"><?= $reject ?></div><div class="lbl">Брак</div></div>
  <div class="card alt"><div class="num"><?= $sevCritical ?></div><div class="lbl">Критических дефектов</div></div>
  <div class="card alt"><div class="num"><?= $sevMajor ?></div><div class="lbl">Существенных дефектов</div></div>
</div>

<div class="three-col">
  <div class="panel">
    <h2>Динамика по дням</h2>
    <?php if (!$byDay): ?>
      <p class="muted">Нет данных за период.</p>
    <?php else: ?>
      <div class="stack">
        <?php foreach ($byDay as $day => $d): ?>
          <div>
            <div class="stat-line"><span class="mono"><?= esc(substr($day, 5)) ?></span><b><?= $d['n'] ?> (годных <?= $d['ok'] ?>)</b></div>
            <div class="bar"><div style="width:<?= $d['n'] ? 100 : 0 ?>%;background:#1d4ed8"></div></div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <div class="panel">
    <h2>Структура дефектов</h2>
    <?php if (!$defByType): ?>
      <p class="muted">Нет данных за период.</p>
    <?php else: ?>
      <div class="stack">
        <?php foreach ($defByType as $key => $cnt): ?>
          <?php $dt = defect_type($key); ?>
          <div>
            <div class="stat-line"><span><?= $dt ? esc($dt['name']) : esc($key) ?></span><b><?= $cnt ?></b></div>
            <div class="bar"><div style="width:<?= round(100 * $cnt / $maxD) ?>%;background:<?= $dt ? esc($dt['color']) : '#64748b' ?>"></div></div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <div class="panel">
    <h2>По операторам</h2>
    <?php if (!$byOperator): ?>
      <p class="muted">Нет данных за период.</p>
    <?php else: ?>
      <?php foreach ($byOperator as $name => $d): ?>
        <div class="stat-line"><span><?= esc($name) ?></span><b><?= $d['ok'] ?> / <?= $d['n'] ?> годных</b></div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>