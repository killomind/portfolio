<?php
require_role(['admin']);
$vac = published_vacancies();
$all = db_all('vacancies');
$responses = db_all('responses');

$bySource = ['Поток' => 0, 'HeadHunter' => 0, 'На сайте' => 0];
foreach ($all as $v) {
  $src = isset($bySource[$v['source']]) ? $v['source'] : 'Поток';
  $bySource[$src]++;
}
foreach ($responses as $r) {
  $src = isset($bySource[$r['source']]) ? $r['source'] : 'Поток';
  $bySource[$src]++;
}

$topVac = $vac;
usort($topVac, function ($a, $b) { return (int)$b['responses'] <=> (int)$a['responses']; });
$topVac = array_slice($topVac, 0, 5);

$totalViews = 0;
foreach ($vac as $v) $totalViews += (int)$v['views'];
$maxViews = 0; foreach ($vac as $v) if ((int)$v['views'] > $maxViews) $maxViews = (int)$v['views'];
?>
<div class="page-hero">
  <h1>Статистика карьерного портала</h1>
  <p class="muted">Аналитика для администратора сайта.</p>
</div>

<div class="cards">
  <div class="card"><div class="num"><?= number_format($totalViews, 0, '.', ' ') ?></div><div class="lbl">Просмотров вакансий</div></div>
  <div class="card"><div class="num"><?= count($vac) ?></div><div class="lbl">Опубликовано вакансий</div></div>
  <div class="card"><div class="num"><?= count($responses) ?></div><div class="lbl">Откликов</div></div>
  <div class="card"><div class="num"><?= count(db_all('enterprises')) ?></div><div class="lbl">Предприятий</div></div>
</div>

<div class="panel">
  <div class="panel-head"><h2>Эффективность по источникам интеграций</h2></div>
  <div class="score-widget">
    <?php $maxSource = max(1, max($bySource)); foreach ($bySource as $src => $cnt): ?>
      <div class="score"><span><?= esc($src) ?></span><div class="bar"><i style="width:<?= (int)round($cnt / $maxSource * 100) ?>%"></i></div><b><?= $cnt ?></b></div>
    <?php endforeach; ?>
  </div>
</div>

<div class="panel">
  <div class="panel-head"><h2>Топ вакансий по откликам</h2></div>
  <table>
    <tr><th>#</th><th>Вакансия</th><th>Предприятие</th><th>Просмотры</th><th>Отклики</th><th>CR</th></tr>
    <?php $i = 0; foreach ($topVac as $v): $i++; $e = enterprise($v['enterprise_id']); $cr = (int)$v['views'] > 0 ? round((int)$v['responses'] / (int)$v['views'] * 100, 1) : 0; ?>
      <tr>
        <td><?= $i ?></td>
        <td><a href="index.php?page=vacancy&id=<?= (int)$v['id'] ?>"><b><?= esc($v['title']) ?></b></a></td>
        <td><?= esc($e['short']) ?></td>
        <td><?= (int)$v['views'] ?></td>
        <td><?= (int)$v['responses'] ?></td>
        <td><?= $cr ?>%</td>
      </tr>
    <?php endforeach; ?>
  </table>
</div>
